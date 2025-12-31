<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $purchase_id = $_GET['id'] ?? null;

    if (!$purchase_id) {
        throw new Exception("ID de compra no proporcionado");
    }

    // Get Purchase Header Info
    $stmtHeader = $conn->prepare("SELECT id, total_amount, paid_amount, payment_status, document_type, document_number FROM purchase_headers WHERE id = ?");
    $stmtHeader->execute([$purchase_id]);
    $header = $stmtHeader->fetch(PDO::FETCH_ASSOC);

    if (!$header) {
        throw new Exception("Compra no encontrada");
    }

    // Get Payments History
    $stmtPayments = $conn->prepare("SELECT * FROM purchase_payments WHERE purchase_header_id = ? ORDER BY payment_date DESC, created_at DESC");
    $stmtPayments->execute([$purchase_id]);
    $payments = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'header' => $header,
        'payments' => $payments
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
