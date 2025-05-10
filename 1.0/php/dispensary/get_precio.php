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
    
    // Modified query to match your database structure
    // Modified query to use JOIN with inventario table
    $stmt = $conn->prepare("
        SELECT c.precio_unidad 
        FROM compras c
        WHERE c.nombre_compra = (
            SELECT i.nom_medicamento 
            FROM inventario i 
            WHERE i.id_inventario = ?
        )
        ORDER BY c.fecha_compra DESC 
        LIMIT 1
    ");
    $stmt->execute([$_GET['id_inventario']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo json_encode(['status' => 'success', 'precio_unidad' => floatval($result['precio_unidad'])]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No se encontró precio para este medicamento']);
    }
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}