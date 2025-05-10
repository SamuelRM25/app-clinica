<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer la zona horaria correcta
date_default_timezone_set('America/Mexico_City'); // Ajusta esto a tu zona horaria local


verify_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        // Validate gender value
        $valid_genders = ['Masculino', 'Femenino'];
        if (!in_array($_POST['genero'], $valid_genders)) {
            throw new Exception('Género inválido');
        }

        // Insert new patient with updated field names
        $stmt = $conn->prepare("INSERT INTO pacientes (nombre, apellido, fecha_nacimiento, genero, direccion, telefono, correo) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $_POST['nombre'],
            $_POST['apellido'],
            $_POST['fecha_nacimiento'],
            $_POST['genero'],
            $_POST['direccion'] ?? null,
            $_POST['telefono'] ?? null,
            $_POST['correo'] ?? null
        ]);

        // Get the ID of the newly inserted patient
        $patient_id = $conn->lastInsertId();

        $_SESSION['message'] = "Paciente agregado correctamente";
        $_SESSION['message_type'] = "success";
        
        // Redirect directly to the medical history page instead of index.php
        header("Location: medical_history.php?id=" . $patient_id);
        exit;

    } catch (Exception $e) {
        $_SESSION['message'] = "Error: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
        header("Location: index.php");
        exit;
    }
}