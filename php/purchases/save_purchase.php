<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

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
    
    if (!$data || !isset($data['header']) || !isset($data['items'])) {
        throw new Exception('Datos incompletos');
    }
    
    $header = $data['header'];
    $items = $data['items'];
    
    $conn->beginTransaction();
    
    // 1. Insert Header
    $stmt = $conn->prepare("INSERT INTO purchase_headers (document_type, document_number, provider_name, purchase_date, total_amount, status) VALUES (?, ?, ?, ?, ?, 'Pendiente')");
    $stmt->execute([
        $header['document_type'],
        $header['document_number'],
        $header['provider_name'],
        $header['purchase_date'],
        $header['total_amount']
    ]);
    $headerId = $conn->lastInsertId();
    
    // 2. Insert Items and Inventory
    $stmtItem = $conn->prepare("INSERT INTO purchase_items (purchase_header_id, product_name, presentation, molecule, pharmaceutical_house, quantity, unit_cost, sale_price, subtotal, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente')");
    
    // Insert into inventory
    // Using correct column names from schema: nom_medicamento, presentacion_med, mol_medicamento, casa_farmaceutica, cantidad_med, fecha_adquisicion, fecha_vencimiento
    // Added: precio_venta, estado, id_purchase_item
    $stmtInv = $conn->prepare("INSERT INTO inventario (nom_medicamento, presentacion_med, mol_medicamento, casa_farmaceutica, cantidad_med, fecha_adquisicion, fecha_vencimiento, precio_venta, estado, id_purchase_item) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente', ?)");
    
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
            $item['subtotal']
        ]);
        $itemId = $conn->lastInsertId();
        
        // Insert into Inventory (Pendiente)
        // fecha_vencimiento is set to purchase_date initially as a placeholder until received
        $stmtInv->execute([
            $item['name'],
            $item['presentation'],
            $item['molecule'],
            $header['provider_name'], // Provider is the pharma house
            $item['qty'],
            $header['purchase_date'], // fecha_adquisicion
            $header['purchase_date'], // fecha_vencimiento (placeholder)
            $item['sale_price'],
            $itemId
        ]);
    }
    
    $conn->commit();
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
