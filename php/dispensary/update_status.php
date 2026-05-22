<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';



verify_session();

header('Content-Type: application/json');

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id']) || !is_numeric($data['id']) || !isset($data['estado'])) {
    echo json_encode(['status' => 'error', 'message' => 'Datos inválidos']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Start transaction
    $conn->beginTransaction();

    // Get current sale data
    $id_hospital = $_SESSION['id_hospital'] ?? 0;

    $stmt = $conn->prepare("SELECT estado FROM ventas WHERE id_venta = ? AND id_hospital = ?");
    $stmt->execute([$data['id'], $id_hospital]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$venta) {
        throw new Exception("Venta no encontrada");
    }

    // Update sale status
    $stmt = $conn->prepare("UPDATE ventas SET estado = ? WHERE id_venta = ? AND id_hospital = ?");
    $stmt->execute([$data['estado'], $data['id'], $id_hospital]);

    // Handle inventory adjustments
    if ($venta['estado'] !== $data['estado']) {
        // Get sale items
        $stmt = $conn->prepare("SELECT id_inventario, cantidad_vendida FROM detalle_ventas WHERE id_venta = ? AND id_hospital = ?");
        $stmt->execute([$data['id'], $id_hospital]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($venta['estado'] === 'Pendiente' && $data['estado'] === 'Pagado') {
            foreach ($items as $item) {
                $stmt = $conn->prepare("UPDATE inventario SET cantidad_med = cantidad_med - ? WHERE id_inventario = ? AND id_hospital = ?");
                $stmt->execute([$item['cantidad_vendida'], $item['id_inventario'], $id_hospital]);
            }
        } else if ($venta['estado'] === 'Pagado' && $data['estado'] === 'Cancelado') {
            foreach ($items as $item) {
                $stmt = $conn->prepare("UPDATE inventario SET cantidad_med = cantidad_med + ? WHERE id_inventario = ? AND id_hospital = ?");
                $stmt->execute([$item['cantidad_vendida'], $item['id_inventario'], $id_hospital]);
            }
        }
    }

    // Commit transaction
    $conn->commit();

    echo json_encode(['status' => 'success', 'message' => 'Estado actualizado correctamente']);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollBack();

    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}