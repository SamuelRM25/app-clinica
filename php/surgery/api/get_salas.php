<?php
// surgery/api/get_salas.php
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

    $stmt = $conn->prepare("SELECT id_sala, codigo, nombre, tipo, tarifa_base, estado FROM salas_quirurgicas WHERE id_hospital = ? ORDER BY codigo ASC");
    $stmt->execute([$id_hospital]);
    $salas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $salas]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}