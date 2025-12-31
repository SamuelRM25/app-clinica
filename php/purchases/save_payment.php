<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $purchase_id = $_POST['purchase_id'] ?? null;
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $payment_method = $_POST['payment_method'] ?? 'Efectivo';
    $notes = $_POST['notes'] ?? '';

    if (!$purchase_id || $amount <= 0) {
        throw new Exception("Datos inválidos");
    }

    $conn->beginTransaction();

    // 1. Get current purchase info
    $stmt = $conn->prepare("SELECT total_amount, paid_amount FROM purchase_headers WHERE id = ? FOR UPDATE");
    $stmt->execute([$purchase_id]);
    $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$purchase) {
        throw new Exception("Compra no encontrada");
    }

    $new_paid_amount = $purchase['paid_amount'] + $amount;
    
    // Check if overpayment (optional validation, but good to have)
    // if ($new_paid_amount > $purchase['total_amount']) {
    //     throw new Exception("El monto excede el saldo pendiente");
    // }

    // 2. Insert payment record
    $stmtInsert = $conn->prepare("INSERT INTO purchase_payments (purchase_header_id, amount, payment_date, payment_method, notes) VALUES (?, ?, ?, ?, ?)");
    $stmtInsert->execute([$purchase_id, $amount, $payment_date, $payment_method, $notes]);

    // 3. Update purchase header status and paid amount
    $payment_status = 'Pendiente';
    if ($new_paid_amount >= $purchase['total_amount']) {
        $payment_status = 'Pagado';
    } elseif ($new_paid_amount > 0) {
        $payment_status = 'Parcial';
    }

    $stmtUpdate = $conn->prepare("UPDATE purchase_headers SET paid_amount = ?, payment_status = ? WHERE id = ?");
    $stmtUpdate->execute([$new_paid_amount, $payment_status, $purchase_id]);

    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Abono registrado correctamente', 'new_balance' => $purchase['total_amount'] - $new_paid_amount]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
