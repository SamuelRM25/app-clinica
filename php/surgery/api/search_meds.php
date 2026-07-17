<?php
// surgery/api/search_meds.php - Buscar medicamentos del inventario (general, todos los stocks)
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
    $q = trim($_GET['q'] ?? '');

    $sql = "SELECT id_inventario, codigo_barras, nom_medicamento, presentacion_med,
                   cantidad_med, stock_hospital, stock_quirofano, precio_venta, precio_hospital, id_purchase_item
            FROM inventario
            WHERE id_hospital = ? AND (cantidad_med > 0 OR stock_hospital > 0 OR stock_quirofano > 0)";
    $params = [$id_hospital];

    if (strlen($q) >= 1) {
        $sql .= " AND (nom_medicamento LIKE ? OR codigo_barras LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    $sql .= " ORDER BY nom_medicamento ASC LIMIT 30";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $items]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}