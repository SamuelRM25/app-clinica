<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

// Set content type to JSON
header('Content-Type: application/json');

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID de cobro inválido']);
    exit;
}

$id_cobro = $_GET['id'];

try {
    // Initialize database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get billing data with patient name
    $stmt = $conn->prepare("
        SELECT c.*, CONCAT(p.nombre, ' ', p.apellido) as nombre_paciente 
        FROM cobros c
        JOIN pacientes p ON c.paciente_cobro = p.id_paciente
        WHERE c.in_cobro = ?
    ");
    $stmt->execute([$id_cobro]);
    $cobro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cobro) {
        echo json_encode(['status' => 'error', 'message' => 'Cobro no encontrado']);
        exit;
    }
    
    // Format date
    $fecha = new DateTime($cobro['fecha_consulta']);
    $cobro['fecha_formateada'] = $fecha->format('d/m/Y');
    
    echo json_encode(['status' => 'success', 'cobro' => $cobro]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}