<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

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
        $checkStmt = $conn->prepare("SELECT id_paciente FROM pacientes WHERE nombre = ? AND apellido = ?");
        $checkStmt->execute([$_POST['nombre'], $_POST['apellido']]);
        if ($checkStmt->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'El paciente ya existe']);
            exit;
        }

        // Insertar nuevo paciente
        $stmt = $conn->prepare("
            INSERT INTO pacientes (nombre, apellido, fecha_nacimiento, genero, direccion, telefono) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_POST['nombre'],
            $_POST['apellido'],
            $_POST['fecha_nacimiento'] ?? null,
            $_POST['genero'],
            $_POST['direccion'] ?? null,
            $_POST['telefono'] ?? null
        ]);

        echo json_encode(['status' => 'success', 'message' => 'Paciente registrado correctamente']);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
}
?>
