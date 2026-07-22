<?php
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
start_app_session();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

    $stmt = $conn->prepare("
        SELECT id_prueba, codigo_prueba, nombre_prueba, categoria, precio, precio_medilab, precio_la_esperanza
        FROM catalogo_pruebas
        WHERE id_hospital = ?
        ORDER BY nombre_prueba ASC
    ");
    $stmt->execute([$id_hospital]);
    $pruebas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $pruebas]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
