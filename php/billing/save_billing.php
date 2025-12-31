<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer la zona horaria correcta
date_default_timezone_set('America/Guatemala');


verify_session();

// Set content type to JSON
header('Content-Type: application/json');

// Get JSON data
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Validate data
if (!isset($data['paciente']) || !isset($data['cantidad']) || !isset($data['fecha_consulta'])) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

try {
    // Initialize database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Insert new billing
    $stmt = $conn->prepare("
        INSERT INTO cobros (paciente_cobro, cantidad_consulta, fecha_consulta) 
        VALUES (?, ?, ?)
    ");
    
    $stmt->execute([
        $data['paciente'],
        $data['cantidad'],
        $data['fecha_consulta']
    ]);
    
    echo json_encode(['status' => 'success', 'message' => 'Cobro guardado correctamente']);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}