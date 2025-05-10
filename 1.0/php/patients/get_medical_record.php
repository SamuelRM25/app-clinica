<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $stmt = $conn->prepare("SELECT * FROM historial_clinico WHERE id_historial = ?");
    $stmt->execute([$_GET['id']]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($record) {
        echo json_encode(['status' => 'success', 'record' => $record]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Registro no encontrado']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}