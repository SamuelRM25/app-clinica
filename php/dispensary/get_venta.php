<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';



verify_session();

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get sale data
    $id_hospital = $_SESSION['id_hospital'] ?? 0;

    $stmt = $conn->prepare("SELECT * FROM ventas WHERE id_venta = ? AND id_hospital = ?");
    $stmt->execute([$_GET['id'], $id_hospital]);

    if (!$venta) {
        echo json_encode(['status' => 'error', 'message' => 'Venta no encontrada']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT dv.*, i.nom_medicamento, i.mol_medicamento, i.presentacion_med, i.casa_farmaceutica
        FROM detalle_ventas dv
        JOIN inventario i ON dv.id_inventario = i.id_inventario
        WHERE dv.id_venta = ? AND i.id_hospital = ?
    ");
    $stmt->execute([$_GET['id'], $id_hospital]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'venta' => $venta, 'items' => $items]);

} catch (Exception $e) {
    error_log('Error en dispensary/get_venta.php: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error del servidor.']);
}