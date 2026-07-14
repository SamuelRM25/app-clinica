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

    $sql = "SELECT id_inventario, codigo_barras, nom_medicamento, presentacion_med,
                   stock_quirofano, precio_venta, precio_hospital
            FROM inventario
            WHERE id_hospital = ? AND stock_quirofano > 0";
    $params = [$id_hospital];

    if (strlen($q) >= 1) {
        $sql .= " AND (nom_medicamento LIKE ? OR codigo_barras LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    $sql .= " ORDER BY nom_medicamento ASC LIMIT 20";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $items]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}