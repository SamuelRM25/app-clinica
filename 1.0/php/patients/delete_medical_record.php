<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

header('Content-Type: application/json');

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id']) || !is_numeric($data['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // First, get the patient ID for the redirect
    $stmt = $conn->prepare("SELECT id_paciente FROM historial_clinico WHERE id_historial = ?");
    $stmt->execute([$data['id']]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$record) {
        echo json_encode(['status' => 'error', 'message' => 'Registro no encontrado']);
        exit;
    }
    
    // Delete the record
    $stmt = $conn->prepare("DELETE FROM historial_clinico WHERE id_historial = ?");
    if ($stmt->execute([$data['id']])) {
        echo json_encode(['status' => 'success', 'message' => 'Registro eliminado correctamente']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al eliminar el registro']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}