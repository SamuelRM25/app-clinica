<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

date_default_timezone_set('America/Guatemala');
verify_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        $id_paciente = $_POST['id_paciente'] ?? null;
        $nombre = $_POST['nombre'];
        $apellido = $_POST['apellido'];
        $fecha_nacimiento = $_POST['fecha_nacimiento'];
        $genero = $_POST['genero'];
        $direccion = $_POST['direccion'] ?? null;
        $telefono = $_POST['telefono'] ?? null;
        $correo = $_POST['correo'] ?? null;

        // Validar género
        $valid_genders = ['Masculino', 'Femenino'];
        if (!in_array($genero, $valid_genders)) {
            throw new Exception('Género inválido');
        }

        // 1. DUPLICATE CHECK
        // If it's an update, we only check for duplicates that ARE NOT the current patient
        if (!$id_paciente) {
            $checkStmt = $conn->prepare("SELECT id_paciente FROM pacientes WHERE nombre = ? AND apellido = ?");
            $checkStmt->execute([$nombre, $apellido]);
            $existingPatient = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingPatient && !isset($_POST['confirm_action'])) {
                $_SESSION['duplicate_patient_data'] = $_POST;
                $_SESSION['existing_patient_id'] = $existingPatient['id_paciente'];
                header("Location: confirm_duplicate.php");
                exit;
            }
        }

        // 2. UPDATE OR INSERT
        if ($id_paciente) {
            // Updating existing patient
            $stmt = $conn->prepare("
                UPDATE pacientes SET
                    nombre = ?,
                    apellido = ?,
                    fecha_nacimiento = ?,
                    genero = ?,
                    direccion = ?,
                    telefono = ?,
                    correo = ?
                WHERE id_paciente = ?
            ");
            $stmt->execute([$nombre, $apellido, $fecha_nacimiento, $genero, $direccion, $telefono, $correo, $id_paciente]);

            $_SESSION['message'] = "Paciente actualizado correctamente";
            $_SESSION['message_type'] = "success";
            header("Location: index.php"); // Return to list after edit
            exit;
        } else {
            // Inserting new patient
            $stmt = $conn->prepare("
                INSERT INTO pacientes (
                    nombre, 
                    apellido, 
                    fecha_nacimiento, 
                    genero, 
                    direccion, 
                    telefono, 
                    correo
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nombre, $apellido, $fecha_nacimiento, $genero, $direccion, $telefono, $correo]);
            $id_paciente = $conn->lastInsertId();

            $_SESSION['message'] = "Paciente agregado correctamente";
            $_SESSION['message_type'] = "success";
            header("Location: medical_history.php?id=" . $id_paciente);
            exit;
        }

    } catch (Exception $e) {
        $_SESSION['message'] = "Error: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
        header("Location: index.php");
        exit;
    }
}