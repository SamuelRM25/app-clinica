<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

require_once '../../../config/database.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!hash_equals($_SESSION['csrf_token'] ?? '', $data['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Token CSRF inválido']);
    exit;
}

if (empty($data['id_prueba'])) {
    echo json_encode(['success' => false, 'error' => 'Falta id_prueba']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

    $stmt = $conn->prepare("
        UPDATE catalogo_pruebas 
        SET precio_medilab = ?, precio_la_esperanza = ?
        WHERE id_prueba = ? AND id_hospital = ?
    ");
    $stmt->execute([
        floatval($data['precio_medilab'] ?? 0),
        floatval($data['precio_la_esperanza'] ?? 0),
        intval($data['id_prueba']),
        $id_hospital
    ]);

    echo json_encode(['success' => true, 'message' => 'Costos actualizados correctamente']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
