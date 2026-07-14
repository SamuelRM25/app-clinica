<?php
// surgery/api/get_combos.php
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
    $only_active = isset($_GET['only_active']) && $_GET['only_active'] === '1';

    $sql = "SELECT c.id_combo, c.codigo, c.nombre, c.descripcion, c.precio_total, c.estado,
                   (SELECT COALESCE(SUM(monto), 0) FROM cirugia_combo_items WHERE id_combo = c.id_combo AND tipo = 'Ganancia') AS total_ganancia,
                   (SELECT COALESCE(SUM(monto), 0) FROM cirugia_combo_items WHERE id_combo = c.id_combo AND tipo = 'Gasto') AS total_gasto,
                   (SELECT COUNT(*) FROM cirugia_combo_items WHERE id_combo = c.id_combo) AS total_items
            FROM cirugia_combos c
            WHERE c.id_hospital = ?";
    $params = [$id_hospital];
    if ($only_active) $sql .= " AND c.estado = 'Activo'";
    $sql .= " ORDER BY c.nombre ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $combos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $combos]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}