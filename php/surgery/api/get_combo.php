<?php
// surgery/api/get_combo.php
session_start();
require_once '../../../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);
    $id_combo = (int)($_GET['id_combo'] ?? 0);

    if (!$id_combo) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM cirugia_combos WHERE id_combo = ? AND id_hospital = ?");
    $stmt->execute([$id_combo, $id_hospital]);
    $combo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$combo) {
        echo json_encode(['success' => false, 'message' => 'Combo no encontrado']);
        exit;
    }

    $stmtItems = $conn->prepare("SELECT id_item, tipo, categoria, descripcion, monto, id_inventario, cantidad
                                    FROM cirugia_combo_items WHERE id_combo = ? ORDER BY tipo, categoria");
    $stmtItems->execute([$id_combo]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => ['combo' => $combo, 'items' => $items]]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}