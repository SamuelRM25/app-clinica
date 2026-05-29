<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';



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
        $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

        $stmt = $conn->prepare("SELECT * FROM purchase_headers WHERE id = ? AND id_hospital = ?");
        $stmt->execute([$_GET['id'], $id_hospital]);
        $header = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$header) {
            throw new Exception('Compra no encontrada');
        }

        $stmtItems = $conn->prepare("SELECT * FROM purchase_items WHERE purchase_header_id = ? AND id_hospital = ?");
        $stmtItems->execute([$_GET['id'], $id_hospital]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'header' => $header,
        'items' => $items
    ]);

} catch (Exception $e) {
    error_log('Error en purchases/get_purchase_details.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor.']);
}
?>