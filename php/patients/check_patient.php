<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

// Verificar si se recibió el nombre y apellido del paciente
if (!isset($_GET['nombre']) || !isset($_GET['apellido'])) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

$nombre = $_GET['nombre'];
$apellido = $_GET['apellido'];

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Buscar al paciente por nombre y apellido
    $stmt = $conn->prepare("SELECT id_paciente FROM pacientes WHERE nombre = ? AND apellido = ?");
    $stmt->execute([$nombre, $apellido]);
    $paciente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($paciente) {
        // Si el paciente existe, devolver su ID
        echo json_encode([
            'status' => 'success', 
            'exists' => true, 
            'id' => $paciente['id_paciente']
        ]);
    } else {
        // Si el paciente no existe, indicarlo
        echo json_encode([
            'status' => 'success', 
            'exists' => false
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}