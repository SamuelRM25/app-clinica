<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

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
    $stmt = $conn->prepare("SELECT estado FROM ventas WHERE id_venta = ?");
    $stmt->execute([$data['id']]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$venta) {
        throw new Exception("Venta no encontrada");
    }
    
    // Update sale status
    $stmt = $conn->prepare("UPDATE ventas SET estado = ? WHERE id_venta = ?");
    $stmt->execute([$data['estado'], $data['id']]);
    
    // Handle inventory adjustments
    if ($venta['estado'] !== $data['estado']) {
        // Get sale items
        $stmt = $conn->prepare("SELECT id_inventario, cantidad_vendida FROM detalle_ventas WHERE id_venta = ?");
        $stmt->execute([$data['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($venta['estado'] === 'Pendiente' && $data['estado'] === 'Pagado') {
            // Reduce inventory when completing a pending sale
            foreach ($items as $item) {
                $stmt = $conn->prepare("UPDATE inventario SET cantidad_med = cantidad_med - ? WHERE id_inventario = ?");
                $stmt->execute([$item['cantidad_vendida'], $item['id_inventario']]);
            }
        } 
        else if ($venta['estado'] === 'Pagado' && $data['estado'] === 'Cancelado') {
            // Return to inventory when canceling a completed sale
            foreach ($items as $item) {
                $stmt = $conn->prepare("UPDATE inventario SET cantidad_med = cantidad_med + ? WHERE id_inventario = ?");
                $stmt->execute([$item['cantidad_vendida'], $item['id_inventario']]);
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