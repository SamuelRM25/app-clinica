<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $stmt = $conn->prepare("SELECT * FROM purchase_headers WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $header = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$header) {
        throw new Exception('Compra no encontrada');
    }
    
    $stmtItems = $conn->prepare("SELECT * FROM purchase_items WHERE purchase_header_id = ?");
    $stmtItems->execute([$_GET['id']]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'header' => $header,
        'items' => $items
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
