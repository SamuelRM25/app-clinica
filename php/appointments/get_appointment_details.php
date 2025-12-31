<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

header('Content-Type: application/json');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID de cita no proporcionado']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $id_cita = $_GET['id'];
    
    $stmt = $conn->prepare("SELECT * FROM citas WHERE id_cita = :id_cita");
    $stmt->bindParam(':id_cita', $id_cita);
    $stmt->execute();
    
    // Obtener la cita
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        echo json_encode(['status' => 'error', 'message' => 'Cita no encontrada']);
        exit;
    }
    
    echo json_encode([
        'status' => 'success',
        'id_cita' => $appointment['id_cita'],
        'nombre_pac' => $appointment['nombre_pac'],
        'apellido_pac' => $appointment['apellido_pac'],
        'paciente_completo' => $appointment['nombre_pac'] . ' ' . $appointment['apellido_pac'],
        'num_cita' => $appointment['num_cita'],
        'fecha_cita' => $appointment['fecha_cita'],
        'hora_cita' => $appointment['hora_cita'],
        'telefono' => $appointment['telefono'] ?? '',
        'id_doctor' => $appointment['id_doctor'] ?? null
    ]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}