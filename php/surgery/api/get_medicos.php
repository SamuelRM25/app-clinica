<?php
// surgery/api/get_medicos.php
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

    $stmt = $conn->prepare("
        SELECT idUsuario, nombre, apellido, especialidad
        FROM usuarios
        WHERE id_hospital = ? AND estado = 'Activo'
        ORDER BY nombre ASC, apellido ASC
    ");
    $stmt->execute([$id_hospital]);
    $medicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $medicos]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}