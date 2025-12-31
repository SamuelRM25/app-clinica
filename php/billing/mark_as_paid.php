<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

header('Content-Type: application/json');

// Get JSON data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !isset($data['id']) || !is_numeric($data['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Update billing status
    $stmt = $conn->prepare("UPDATE cobros SET estado = 'Pagado' WHERE in_cobro = ?");
    $stmt->execute([$data['id']]);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Cobro no encontrado o ya está marcado como pagado']);
        exit;
    }
    
    echo json_encode(['status' => 'success', 'message' => 'Cobro marcado como pagado correctamente']);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}