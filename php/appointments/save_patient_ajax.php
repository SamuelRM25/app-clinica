<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';



verify_session();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    try {
        $database = new Database();
        $conn = $database->getConnection();

        // Validar datos mínimos
        if (empty($_POST['nombre']) || empty($_POST['apellido']) || empty($_POST['genero'])) {
            throw new Exception("Nombre, apellido y género son obligatorios");
        }

        // Verificar duplicados (incluye fecha_nacimiento)
        $id_hospital = $_SESSION['id_hospital'] ?? 0;

        $checkStmt = $conn->prepare("SELECT id_paciente, nombre, apellido FROM pacientes WHERE nombre = ? AND apellido = ? AND fecha_nacimiento = ? AND id_hospital = ?");
        $checkStmt->execute([$_POST['nombre'], $_POST['apellido'], $_POST['fecha_nacimiento'] ?? null, $id_hospital]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            echo json_encode(['status' => 'exists', 'message' => 'El paciente ya existe', 'existing_id' => $existing['id_paciente']]);
            exit;
        }

        $stmt = $conn->prepare("
            INSERT INTO pacientes (nombre, apellido, fecha_nacimiento, genero, direccion, telefono, id_hospital) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $_POST['nombre'],
            $_POST['apellido'],
            $_POST['fecha_nacimiento'] ?? null,
            $_POST['genero'],
            $_POST['direccion'] ?? null,
            $_POST['telefono'] ?? null,
            $id_hospital
        ]);

        echo json_encode(['status' => 'success', 'message' => 'Paciente registrado correctamente']);

    } catch (Exception $e) {
        error_log('Error en appointments/save_patient_ajax.php: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Error del servidor.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
}
?>