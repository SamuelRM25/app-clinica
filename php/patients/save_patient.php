<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

date_default_timezone_set('America/Guatemala');
verify_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    try {
        $database = new Database();
        $conn = $database->getConnection();

        $id_paciente = $_POST['id_paciente'] ?? null;
        $nombre = $_POST['nombre'];
        $apellido = $_POST['apellido'];
        $fecha_nacimiento = $_POST['fecha_nacimiento'];
        $genero = $_POST['genero'];
        $dpi = $_POST['dpi'] ?? null;
        $direccion = $_POST['direccion'] ?? null;
        $telefono = $_POST['telefono'] ?? null;
        $correo = $_POST['correo'] ?? null;

        // Validar género
        $valid_genders = ['Masculino', 'Femenino'];
        if (!in_array($genero, $valid_genders)) {
            throw new Exception('Género inválido');
        }

        // 1. DUPLICATE CHECK — incluye fecha_nacimiento para mayor precisión
        // If it's an update, we only check for duplicates that ARE NOT the current patient
        if (!$id_paciente) {
            $checkStmt = $conn->prepare("SELECT id_paciente FROM pacientes WHERE nombre = ? AND apellido = ? AND fecha_nacimiento = ? AND id_hospital = ?");
            $checkStmt->execute([$nombre, $apellido, $fecha_nacimiento, $id_hospital]);
            $existingPatient = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingPatient && !isset($_POST['confirm_action'])) {
                $_SESSION['duplicate_patient_data'] = $_POST;
                $_SESSION['existing_patient_id'] = $existingPatient['id_paciente'];

                // Fetch existing patient details for the confirmation screen
                $existingStmt = $conn->prepare("SELECT nombre, apellido, fecha_registro FROM pacientes WHERE id_paciente = ? AND id_hospital = ?");
                $existingStmt->execute([$existingPatient['id_paciente'], $id_hospital]);
                $existingData = $existingStmt->fetch(PDO::FETCH_ASSOC);
                $_SESSION['existing_patient_nombre'] = $existingData['nombre'] ?? 'N/A';
                $_SESSION['existing_patient_apellido'] = $existingData['apellido'] ?? 'N/A';
                $_SESSION['existing_patient_fecha'] = $existingData['fecha_registro'] ?? null;

                // Count previous consultations
                $consultasStmt = $conn->prepare("SELECT COUNT(*) FROM historial_clinico WHERE id_paciente = ? AND id_hospital = ?");
                $consultasStmt->execute([$existingPatient['id_paciente'], $id_hospital]);
                $_SESSION['existing_patient_consultas'] = $consultasStmt->fetchColumn() ?: 0;

                header("Location: confirm_duplicate.php");
                exit;
            }
        }

        // 2. HANDLE CONFIRMED ACTIONS FROM DUPLICATE SCREEN
        if (isset($_POST['confirm_action'])) {
            $existing_patient_id = $_POST['existing_patient_id'] ?? null;

            if ($_POST['confirm_action'] === 'cancel') {
                $_SESSION['message'] = "Operación cancelada.";
                $_SESSION['message_type'] = "info";
                header("Location: index.php");
                exit;
            }

            if ($_POST['confirm_action'] === 'replace' && $existing_patient_id) {
                // Delete existing patient and all related records
                $conn->beginTransaction();
                try {
                    $stmt_del = $conn->prepare("DELETE FROM pacientes WHERE id_paciente = ? AND id_hospital = ?");
                    $stmt_del->execute([$existing_patient_id, $id_hospital]);
                    audit_log('delete', 'patients', "Paciente eliminado (reemplazo): $nombre $apellido", [
                        'table_name' => 'pacientes', 'record_id' => $existing_patient_id
                    ]);
                    $conn->commit();
                } catch (Exception $e) {
                    $conn->rollBack();
                    throw $e;
                }
            }

            if ($_POST['confirm_action'] === 'overwrite' && $existing_patient_id) {
                // Update existing patient with new data (keeps history)
                $id_paciente = $existing_patient_id;
            }
        }

        // 3. UPDATE OR INSERT
        if ($id_paciente) {
            // Fetch old data for audit
            $oldStmt = $conn->prepare("SELECT nombre, apellido, fecha_nacimiento, genero, dpi, direccion, telefono, correo FROM pacientes WHERE id_paciente = ? AND id_hospital = ?");
            $oldStmt->execute([$id_paciente, $id_hospital]);
            $oldData = $oldStmt->fetch(PDO::FETCH_ASSOC);

            // Updating existing patient
            $stmt = $conn->prepare("
                UPDATE pacientes SET
                    nombre = ?,
                    apellido = ?,
                    fecha_nacimiento = ?,
                    genero = ?,
                    dpi = ?,
                    direccion = ?,
                    telefono = ?,
                    correo = ?
                WHERE id_paciente = ? AND id_hospital = ?
            ");
            $stmt->execute([$nombre, $apellido, $fecha_nacimiento, $genero, $dpi, $direccion, $telefono, $correo, $id_paciente, $id_hospital]);

            audit_log('update', 'patients', "Paciente actualizado: $nombre $apellido", [
                'table_name' => 'pacientes',
                'record_id' => $id_paciente,
                'old_data' => $oldData,
                'new_data' => [
                    'nombre' => $nombre,
                    'apellido' => $apellido,
                    'fecha_nacimiento' => $fecha_nacimiento,
                    'genero' => $genero,
                    'dpi' => $dpi,
                    'direccion' => $direccion,
                    'telefono' => $telefono,
                    'correo' => $correo
                ]
            ]);

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
                    dpi, 
                    direccion, 
                    telefono, 
                    correo,
                    fecha_registro,
                    id_hospital
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $stmt->execute([$nombre, $apellido, $fecha_nacimiento, $genero, $dpi, $direccion, $telefono, $correo, $id_hospital]);
            $id_paciente = $conn->lastInsertId();

            audit_log('create', 'patients', "Nuevo paciente registrado: $nombre $apellido", [
                'table_name' => 'pacientes',
                'record_id' => $id_paciente,
                'new_data' => [
                    'nombre' => $nombre,
                    'apellido' => $apellido,
                    'fecha_nacimiento' => $fecha_nacimiento,
                    'genero' => $genero,
                    'dpi' => $dpi,
                    'telefono' => $telefono,
                    'correo' => $correo
                ]
            ]);

            $_SESSION['message'] = "Paciente agregado correctamente";
            $_SESSION['message_type'] = "success";
            header("Location: medical_history.php?id=" . $id_paciente);
            exit;
        }

    } catch (Exception $e) {
        error_log('Error en patients/save_patient.php: ' . $e->getMessage());
        $errorMsg = $e->getMessage();
        $_SESSION['message'] = "Error al guardar paciente: $errorMsg";
        $_SESSION['message_type'] = "danger";
        header("Location: index.php");
        exit;
    }
}