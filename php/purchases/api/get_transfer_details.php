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

$purchase_id = (int)($_GET['purchase_header_id'] ?? 0);
if ($purchase_id === 0) {
    ob_clean();
    echo json_encode(['error' => 'ID de compra no válido']);
    exit;
}

ob_clean();
try {
    $database = new Database();
    $conn = $database->getConnection();

    // Verificar si ya se aplicó un ajuste (nuevo sistema o histórico)
    $stmt_applied = $conn->prepare("SELECT COUNT(*) FROM purchase_payments
        WHERE purchase_header_id = ? AND payment_method = 'Transferencia' AND id_hospital = ?");
    $stmt_applied->execute([$purchase_id, $id_hospital]);
    $already_applied = (int)$stmt_applied->fetchColumn() > 0;

    // Obtener detalle de traslados históricos vinculados a esta compra
    $stmt = $conn->prepare("
        SELECT v.id_venta, v.fecha_venta, v.nombre_cliente AS destino,
               COALESCE(u.nombre, u.usuario, 'Desconocido') AS usuario,
               i.nom_medicamento, dv.cantidad_vendida,
               pi.unit_cost,
               ROUND(dv.cantidad_vendida * pi.unit_cost, 2) AS valor,
               pi.id AS purchase_item_id,
               pi.quantity AS pi_current_qty
        FROM ventas v
        JOIN detalle_ventas dv ON dv.id_venta = v.id_venta
        JOIN inventario i ON i.id_inventario = dv.id_inventario
        JOIN purchase_items pi ON pi.id = i.id_purchase_item
        LEFT JOIN usuarios u ON u.idUsuario = v.id_usuario
        WHERE pi.purchase_header_id = ?
          AND v.tipo_pago = 'Traslado'
          AND v.id_hospital = ?
        ORDER BY v.fecha_venta DESC
    ");
    $stmt->execute([$purchase_id, $id_hospital]);
    $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Agrupar por purchase_item para saber si ya fueron ajustados
    $adjusted_items = [];
    foreach ($transfers as &$t) {
        $key = $t['purchase_item_id'];
        if (!isset($adjusted_items[$key])) {
            $adjusted_items[$key] = 0;
        }
        $adjusted_items[$key] += (int)$t['cantidad_vendida'];
    }
    unset($t);

    // Determinar si cada transfer aún puede aplicarse
    // (si purchase_items.quantity aún cubre la cantidad transferida)
    $stmt_pi_qty = $conn->prepare("SELECT id, quantity FROM purchase_items WHERE purchase_header_id = ? AND id_hospital = ?");
    $stmt_pi_qty->execute([$purchase_id, $id_hospital]);
    $pi_qties = [];
    while ($row = $stmt_pi_qty->fetch(PDO::FETCH_ASSOC)) {
        $pi_qties[$row['id']] = (int)$row['quantity'];
    }

    foreach ($transfers as &$t) {
        $pi_id = $t['purchase_item_id'];
        $needed = (int)$t['cantidad_vendida'];
        $available = $pi_qties[$pi_id] ?? 0;
        $t['can_adjust'] = ($available >= $needed);
    }
    unset($t);

    // Totales
    $total_valor = array_sum(array_column($transfers, 'valor'));
    $total_qty = array_sum(array_column($transfers, 'cantidad_vendida'));

    ob_clean();
    echo json_encode([
        'success' => true,
        'already_applied' => $already_applied,
        'transfers' => $transfers,
        'total_valor' => round($total_valor, 2),
        'total_qty' => (int)$total_qty,
        'purchase_header_id' => $purchase_id
    ]);

} catch (Exception $e) {
    ob_clean();
    error_log('Error en get_transfer_details.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error del servidor: ' . $e->getMessage()]);
}
