<?php
ob_start();
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Sesión no iniciada']);
    exit;
}

require_once '../../../config/database.php';
include_once '../../../includes/functions.php';
include_once '../../../includes/multitenant.php';

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);
if ($id_hospital === 0) {
    ob_clean();
    echo json_encode(['error' => 'Hospital no identificado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$purchase_header_id = (int)($input['purchase_header_id'] ?? 0);
if ($purchase_header_id === 0) {
    ob_clean();
    echo json_encode(['error' => 'ID de compra no válido']);
    exit;
}

ob_clean();
try {
    $database = new Database();
    $conn = $database->getConnection();

    $conn->beginTransaction();

    // 1. Verificar idempotencia (cualquier pago Transferencia ya cubre los ajustes)
    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM purchase_payments
        WHERE purchase_header_id = ? AND payment_method = 'Transferencia' AND id_hospital = ?");
    $stmt_check->execute([$purchase_header_id, $id_hospital]);
    if ((int)$stmt_check->fetchColumn() > 0) {
        throw new Exception('El ajuste histórico ya fue aplicado anteriormente.');
    }

    // 2. Obtener resumen de traslados agrupado por purchase_item
    $stmt_trans = $conn->prepare("
        SELECT pi.id AS pi_id, pi.unit_cost, pi.quantity AS pi_current_qty,
               SUM(dv.cantidad_vendida) AS total_transferred,
               i.nom_medicamento
        FROM purchase_items pi
        JOIN inventario i ON i.id_purchase_item = pi.id
        JOIN detalle_ventas dv ON dv.id_inventario = i.id_inventario
        JOIN ventas v ON v.id_venta = dv.id_venta AND v.tipo_pago = 'Traslado'
        WHERE pi.purchase_header_id = ? AND v.id_hospital = ?
        GROUP BY pi.id, pi.unit_cost, pi.quantity, i.nom_medicamento
    ");
    $stmt_trans->execute([$purchase_header_id, $id_hospital]);
    $items = $stmt_trans->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        throw new Exception('No se encontraron traslados históricos para esta compra.');
    }

    $stmt_upd_pi = $conn->prepare("
        UPDATE purchase_items
        SET quantity = quantity - ?,
            subtotal = subtotal - ROUND(? * unit_cost, 2)
        WHERE id = ? AND quantity >= ?
    ");

    $total_reduction = 0;
    $total_qty = 0;
    $product_details = [];

    foreach ($items as $item) {
        $qty = (int)$item['total_transferred'];
        $unit_cost = (float)$item['unit_cost'];
        $reduction = round($qty * $unit_cost, 2);

        $stmt_upd_pi->execute([$qty, $qty, $item['pi_id'], $qty]);
        if ($stmt_upd_pi->rowCount() === 0) {
            throw new Exception(
                "Stock insuficiente en el ítem de compra para '{$item['nom_medicamento']}'. " .
                "Disponible: {$item['pi_current_qty']}, solicitado: {$qty}."
            );
        }

        $total_reduction += $reduction;
        $total_qty += $qty;
        $product_details[] = "{$qty} de {$item['nom_medicamento']} (Q{$reduction})";
    }

    // 3. Reducir total de la factura de compra
    $stmt_upd_ph = $conn->prepare("
        UPDATE purchase_headers
        SET total_amount = total_amount - ?
        WHERE id = ? AND total_amount >= ?
    ");
    $stmt_upd_ph->execute([$total_reduction, $purchase_header_id, $total_reduction]);

    // 4. Registrar pago documentando el ajuste histórico
    $product_summary = implode(', ', $product_details);
    $notes = "Ajuste histórico por traslados: {$total_qty} unidades ({$product_summary}). Total: Q{$total_reduction}";

    $stmt_pay = $conn->prepare("
        INSERT INTO purchase_payments
            (purchase_header_id, amount, payment_date, payment_method, notes, id_hospital)
        VALUES (?, ?, NOW(), 'Transferencia', ?, ?)
    ");
    $stmt_pay->execute([$purchase_header_id, $total_reduction, $notes, $id_hospital]);

    $conn->commit();

    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => "Ajuste aplicado correctamente. Total reducido: Q{$total_reduction}",
        'total_reduction' => $total_reduction,
        'total_qty' => $total_qty
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    ob_clean();
    error_log('Error en apply_transfer_adjustment.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
