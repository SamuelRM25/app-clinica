<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

header('Content-Type: application/json');

verify_session();

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

$data = json_decode(file_get_contents('php://input'), true);
$id = isset($data['id']) ? (int)$data['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de compra inválido']);
    exit;
}

$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $data['csrf_token'] ?? '';
if (empty($csrfHeader) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfHeader)) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $conn->beginTransaction();

    $stmt = $conn->prepare("SELECT id FROM purchase_items WHERE purchase_header_id = ? AND id_hospital = ?");
    $stmt->execute([$id, $id_hospital]);
    $itemIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($itemIds)) {
        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $params = array_merge($itemIds, [$id_hospital]);
        $stmt = $conn->prepare("DELETE FROM inventario WHERE id_purchase_item IN ($placeholders) AND id_hospital = ?");
        $stmt->execute($params);
    }

    $stmt = $conn->prepare("DELETE FROM purchase_payments WHERE purchase_header_id = ? AND id_hospital = ?");
    $stmt->execute([$id, $id_hospital]);

    $stmt = $conn->prepare("DELETE FROM purchase_items WHERE purchase_header_id = ? AND id_hospital = ?");
    $stmt->execute([$id, $id_hospital]);

    $stmt = $conn->prepare("SELECT document_number, provider_name FROM purchase_headers WHERE id = ? AND id_hospital = ?");
    $stmt->execute([$id, $id_hospital]);
    $header = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("DELETE FROM purchase_headers WHERE id = ? AND id_hospital = ?");
    $stmt->execute([$id, $id_hospital]);

    $conn->commit();

    $docInfo = $header ? ($header['document_number'] ?? '') . ' - ' . ($header['provider_name'] ?? '') : "ID #$id";
    audit_log('delete', 'purchases', "Compra eliminada: $docInfo", ['purchase_header_id' => $id], $_SESSION['user_id']);

    echo json_encode(['success' => true, 'message' => 'Compra eliminada correctamente']);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error en delete_purchase_new.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al eliminar la compra']);
}
