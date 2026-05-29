<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

verify_session();

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("SELECT * FROM historial_clinico WHERE id_historial = ? AND id_hospital = ?");
    $stmt->execute([$_GET['id'], $id_hospital]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($record) {
        echo json_encode(['status' => 'success', 'record' => $record]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Registro no encontrado']);
    }
} catch (Exception $e) {
    error_log('Error en patients/get_medical_record.php: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error del servidor.']);
}