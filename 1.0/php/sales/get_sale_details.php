<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get sale data with ID
    $stmt = $conn->prepare("
        SELECT id_venta, fecha_venta, nombre_cliente, tipo_pago, total, estado 
        FROM ventas 
        WHERE id_venta = ?
    ");
    $stmt->execute([$_GET['id']]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$venta) {
        echo json_encode(['status' => 'error', 'message' => 'Venta no encontrada']);
        exit;
    }
    
    // Format date
    $fecha = new DateTime($venta['fecha_venta']);
    $venta['fecha_formateada'] = $fecha->format('d/m/Y H:i:s');
    
    // Get sale items
    $stmt = $conn->prepare("
        SELECT dv.*, i.nom_medicamento, i.mol_medicamento, i.presentacion_med, i.casa_farmaceutica
        FROM detalle_ventas dv
        JOIN inventario i ON dv.id_inventario = i.id_inventario
        WHERE dv.id_venta = ?
    ");
    $stmt->execute([$_GET['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'venta' => $venta,
        'items' => $items
    ]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}