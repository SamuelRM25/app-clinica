<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

header('Content-Type: application/json');

if (!isset($_GET['id_inventario']) || !is_numeric($_GET['id_inventario'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Query directly from inventario
    $stmt = $conn->prepare("SELECT precio_venta FROM inventario WHERE id_inventario = ?");
    $stmt->execute([$_GET['id_inventario']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo json_encode(['status' => 'success', 'precio_venta' => floatval($result['precio_venta'])]);
    } else {
        echo json_encode(['status' => 'success', 'precio_venta' => 0.00]);
    }
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
