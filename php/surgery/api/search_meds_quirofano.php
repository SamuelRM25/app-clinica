<?php
// surgery/api/search_meds_quirofano.php - Buscar medicamentos con stock en quirófano
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

    $sql = "SELECT i.id_inventario, i.codigo_barras, i.nom_medicamento, i.presentacion_med,
                   i.stock_quirofano, i.precio_venta, i.precio_hospital, i.precio_quirofano,
                   COALESCE(NULLIF(i.precio_compra, 0), pi.unit_cost, 0) as precio_compra
            FROM inventario i
            LEFT JOIN purchase_items pi ON i.id_purchase_item = pi.id
            WHERE i.id_hospital = ? AND i.stock_quirofano > 0";
    $params = [$id_hospital];

    if (strlen($q) >= 1) {
        $sql .= " AND (i.nom_medicamento LIKE ? OR i.codigo_barras LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    $sql .= " ORDER BY i.nom_medicamento ASC LIMIT 20";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $items]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}