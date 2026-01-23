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

    if (!$data || !isset($data['id_inventario']) || !isset($data['fecha_vencimiento'])) {
        throw new Exception('Datos incompletos');
    }

    $id = $data['id_inventario'];
    $expiry = $data['fecha_vencimiento'];
    $doc = $data['documento_referencia'] ?? null;
    $barcode = $data['codigo_barras'] ?? null;

    $conn->beginTransaction();

    // 1. Get linked purchase item ID
    $stmtGet = $conn->prepare("SELECT id_purchase_item FROM inventario WHERE id_inventario = ?");
    $stmtGet->execute([$id]);
    $row = $stmtGet->fetch(PDO::FETCH_ASSOC);
    $purchaseItemId = $row['id_purchase_item'] ?? null;

    // 2. Update Inventory
    // We also update codigo_barras here if provided
    $sqlInv = "UPDATE inventario SET estado = 'Disponible', fecha_vencimiento = ?";
    $params = [$expiry];

    if ($barcode !== null) {
        $sqlInv .= ", codigo_barras = ?";
        $params[] = $barcode;
    }

    $sqlInv .= " WHERE id_inventario = ?";
    $params[] = $id;

    $stmtUpdateInv = $conn->prepare($sqlInv);
    $stmtUpdateInv->execute($params);

    // 3. Update Purchase Item Status if linked
    if ($purchaseItemId) {
        $stmtUpdateItem = $conn->prepare("UPDATE purchase_items SET status = 'Recibido' WHERE id = ?");
        $stmtUpdateItem->execute([$purchaseItemId]);

        // Check if all items in that purchase are received
        $stmtCheck = $conn->prepare("SELECT purchase_header_id FROM purchase_items WHERE id = ?");
        $stmtCheck->execute([$purchaseItemId]);
        $headerId = $stmtCheck->fetchColumn();

        if ($headerId) {
            $stmtCount = $conn->prepare("SELECT COUNT(*) FROM purchase_items WHERE purchase_header_id = ? AND status = 'Pendiente'");
            $stmtCount->execute([$headerId]);
            $pendingCount = $stmtCount->fetchColumn();

            if ($pendingCount == 0) {
                $stmtUpdateHeader = $conn->prepare("UPDATE purchase_headers SET status = 'Completado' WHERE id = ?");
                $stmtUpdateHeader->execute([$headerId]);
            }

            // If doc number provided, maybe update header?
            if ($doc) {
                $stmtUpdateDoc = $conn->prepare("UPDATE purchase_headers SET document_number = ? WHERE id = ?");
                $stmtUpdateDoc->execute([$doc, $headerId]);
            }
        }
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