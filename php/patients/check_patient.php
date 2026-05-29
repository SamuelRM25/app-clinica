<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

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
    $stmt = $conn->prepare("SELECT id_paciente FROM pacientes WHERE nombre = ? AND apellido = ? AND id_hospital = ?");
    $stmt->execute([$nombre, $apellido, $id_hospital]);
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
    error_log('Error en patients/check_patient.php: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error del servidor.']);
}