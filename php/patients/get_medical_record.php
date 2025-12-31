<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

// Disable error display to prevent JSON corruption
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Start output buffering to catch any unwanted output (warnings, whitespace)
ob_start();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $stmt = $conn->prepare("SELECT * FROM historial_clinico WHERE id_historial = ?");
    $stmt->execute([$_GET['id']]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    ob_end_clean(); // Clean buffer before output
    
    if ($record) {
        echo json_encode(['status' => 'success', 'record' => $record]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Registro no encontrado']);
    }
} catch (Exception $e) {
    ob_end_clean(); // Clean buffer before error output
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}