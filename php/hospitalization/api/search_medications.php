<?php
// hospitalization/api/search_medications.php
session_start();
require_once '../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/multitenant.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$search = $_GET['q'] ?? '';

if (strlen($search) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("
        SELECT i.id_inventario, i.nom_medicamento, i.mol_medicamento, i.presentacion_med, 
               i.stock_hospital, i.cantidad_med as stock_farmacia, i.precio_hospital, i.precio_venta,
               COALESCE(NULLIF(i.precio_compra, 0), pi.unit_cost, 0) as precio_compra
        FROM inventario i
        LEFT JOIN purchase_items pi ON i.id_purchase_item = pi.id
        WHERE (i.nom_medicamento LIKE ? OR i.mol_medicamento LIKE ? OR i.codigo_barras LIKE ?) 
        AND i.estado = 'Disponible'
        AND i.stock_hospital > 0
        AND i.id_hospital = ?
        LIMIT 20
    ");

    $term = "%$search%";
    $stmt->execute([$term, $term, $term, $id_hospital]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($results);

} catch (Exception $e) {
        error_log("hospitalization/api/search_medications.php error: " . $e->getMessage());
        echo json_encode(['error' => 'Error del servidor.']);
}
?>