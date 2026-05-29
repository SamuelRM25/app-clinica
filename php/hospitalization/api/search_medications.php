<?php
// hospitalization/api/search_medications.php
session_start();
require_once '../../../config/database.php';
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
        SELECT id_inventario, nom_medicamento, mol_medicamento, presentacion_med, 
               stock_hospital, cantidad_med as stock_farmacia, precio_hospital, precio_venta
        FROM inventario 
        WHERE (nom_medicamento LIKE ? OR mol_medicamento LIKE ? OR codigo_barras LIKE ?) 
        AND estado = 'Disponible'
        AND stock_hospital > 0
        AND id_hospital = ?
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