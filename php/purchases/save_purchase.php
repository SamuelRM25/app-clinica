<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

csrf_token();



header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get JSON input
    $data = json_decode(file_get_contents('php://input'), true);

    // CSRF validation for JSON requests
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($csrfHeader) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfHeader)) {
        throw new Exception('Token CSRF inválido');
    }

    if (!$data || !isset($data['header']) || !isset($data['items'])) {
        throw new Exception('Datos incompletos');
    }

    $header = $data['header'];
    $items = $data['items'];
    $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

    $conn->beginTransaction();

    // 1. Insert Header
    $stmt = $conn->prepare("INSERT INTO purchase_headers (document_type, document_number, provider_name, purchase_date, total_amount, status, created_by, id_hospital) VALUES (?, ?, ?, ?, ?, 'Pendiente', ?, ?)");
    $stmt->execute([
        $header['document_type'],
        $header['document_number'],
        $header['provider_name'],
        $header['purchase_date'],
        $header['total_amount'],
        $_SESSION['user_id'],
        $id_hospital
    ]);
    $headerId = $conn->lastInsertId();

    // 2. Insert Items and Inventory
    $stmtItem = $conn->prepare("INSERT INTO purchase_items (purchase_header_id, product_name, presentation, molecule, pharmaceutical_house, quantity, unit_cost, sale_price, subtotal, status, id_hospital) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente', ?)");

    // Insert into inventory
    // Using correct column names from schema: nom_medicamento, presentacion_med, mol_medicamento, casa_farmaceutica, cantidad_med, fecha_adquisicion, fecha_vencimiento
    // Added: precio_venta, estado, id_purchase_item
    $stmtInv = $conn->prepare("INSERT INTO inventario (nom_medicamento, presentacion_med, mol_medicamento, casa_farmaceutica, cantidad_med, fecha_adquisicion, fecha_vencimiento, precio_venta, estado, id_purchase_item, id_hospital) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente', ?, ?)");

    foreach ($items as $item) {
        // Insert Purchase Item
        $stmtItem->execute([
            $headerId,
            $item['name'],
            $item['presentation'],
            $item['molecule'],
            $header['provider_name'], // Use provider name as pharmaceutical house per user request
            $item['qty'],
            $item['cost'],
            $item['sale_price'],
            $item['subtotal'],
            $id_hospital
        ]);
        $itemId = $conn->lastInsertId();

        // Insert into Inventory (Pendiente)
        // fecha_vencimiento uses item expiry date if provided, otherwise use far future date as placeholder
        $vencimiento = !empty($item['expiry_date']) ? $item['expiry_date'] : '2099-12-31';
        $stmtInv->execute([
            $item['name'],
            $item['presentation'],
            $item['molecule'],
            $header['provider_name'],
            $item['qty'],
            $header['purchase_date'],
            $vencimiento,
            $item['sale_price'],
            $itemId,
            $id_hospital
        ]);
    }

    $conn->commit();

    audit_log('create', 'purchases', "Compra #$headerId - Proveedor: {$header['provider_name']} - Total: Q{$header['total_amount']}", [
        'table_name' => 'purchase_headers',
        'record_id' => (int)$headerId,
        'new_data' => [
            'provider_name' => $header['provider_name'],
            'document_type' => $header['document_type'],
            'document_number' => $header['document_number'],
            'purchase_date' => $header['purchase_date'],
            'total_amount' => $header['total_amount'],
            'items_count' => count($items)
        ]
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Error en purchases/save_purchase.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>