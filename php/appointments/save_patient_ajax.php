<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';



verify_session();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        // Validar datos mínimos
        if (empty($_POST['nombre']) || empty($_POST['apellido']) || empty($_POST['genero'])) {
            throw new Exception("Nombre, apellido y género son obligatorios");
        }

        // Verificar duplicados
        $id_hospital = $_SESSION['id_hospital'] ?? 0;

        $checkStmt = $conn->prepare("SELECT id_paciente FROM pacientes WHERE nombre = ? AND apellido = ? AND id_hospital = ?");
        $checkStmt->execute([$_POST['nombre'], $_POST['apellido'], $id_hospital]);
        if ($checkStmt->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'El paciente ya existe']);
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
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
}
?>