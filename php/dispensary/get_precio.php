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
    
    // Query from inventario and join with compras if id_purchase_item exists
    $stmt = $conn->prepare("
        SELECT i.precio_venta as inv_price, c.precio_venta as comp_price 
        FROM inventario i
        LEFT JOIN compras c ON i.id_purchase_item = c.id_compras
        WHERE i.id_inventario = ?
    ");
    $stmt->execute([$_GET['id_inventario']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $precio = floatval($result['inv_price']);
        // Fallback to purchase price if inventory price is 0
        if ($precio <= 0 && isset($result['comp_price'])) {
            $precio = floatval($result['comp_price']);
        }
        echo json_encode(['status' => 'success', 'precio_venta' => $precio]);
    } else {
        echo json_encode(['status' => 'success', 'precio_venta' => 0.00]);
    }
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
