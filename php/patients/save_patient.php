<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

date_default_timezone_set('America/Guatemala');
verify_session();

// Detectar si es una petición AJAX (vía header X-Requested-With o Accept)
$is_ajax = (
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
);

if ($is_ajax) {
    header('Content-Type: application/json; charset=utf-8');
}

/**
 * Helper para enviar respuesta JSON en modo AJAX o redirigir en modo form.
 */
function respond_ajax($data, $is_ajax) {
    if ($is_ajax) {
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    // En modo no-AJAX no se usa (porque los flujos exitosos ya hacen redirect)
}

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
                if ($is_ajax) {
                    $existingStmt = $conn->prepare("SELECT nombre, apellido, fecha_registro, telefono, correo FROM pacientes WHERE id_paciente = ? AND id_hospital = ?");
                    $existingStmt->execute([$existingPatient['id_paciente'], $id_hospital]);
                    $existingData = $existingStmt->fetch(PDO::FETCH_ASSOC);

                    $consultasStmt = $conn->prepare("SELECT COUNT(*) FROM historial_clinico WHERE id_paciente = ? AND id_hospital = ?");
                    $consultasStmt->execute([$existingPatient['id_paciente'], $id_hospital]);
                    $consultas = $consultasStmt->fetchColumn() ?: 0;

                    respond_ajax([
                        'success' => false,
                        'duplicate' => true,
                        'existing_id' => (int)$existingPatient['id_paciente'],
                        'existing' => [
                            'nombre' => $existingData['nombre'] ?? $nombre,
                            'apellido' => $existingData['apellido'] ?? $apellido,
                            'fecha_registro' => $existingData['fecha_registro'] ?? null,
                            'telefono' => $existingData['telefono'] ?? null,
                            'correo' => $existingData['correo'] ?? null,
                            'consultas' => (int)$consultas
                        ],
                        'message' => "Ya existe un paciente con el mismo nombre y fecha de nacimiento."
                    ], true);
                }
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
                if ($is_ajax) {
                    respond_ajax(['success' => false, 'cancelled' => true, 'message' => 'Operación cancelada'], true);
                }
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

            if ($is_ajax) {
                respond_ajax([
                    'success' => true,
                    'action' => 'updated',
                    'id_paciente' => (int)$id_paciente,
                    'message' => "Paciente actualizado correctamente"
                ], true);
            }

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

            if ($is_ajax) {
                respond_ajax([
                    'success' => true,
                    'action' => 'created',
                    'id_paciente' => (int)$id_paciente,
                    'nombre_completo' => trim($nombre . ' ' . $apellido),
                    'message' => "Paciente registrado correctamente"
                ], true);
            }

            $_SESSION['message'] = "Paciente agregado correctamente";
            $_SESSION['message_type'] = "success";
            header("Location: medical_history.php?id=" . $id_paciente);
            exit;
        }

    } catch (Exception $e) {
        error_log('Error en patients/save_patient.php: ' . $e->getMessage());
        $errorMsg = $e->getMessage();

        if ($is_ajax) {
            respond_ajax([
                'success' => false,
                'message' => 'Error al guardar paciente: ' . $errorMsg
            ], true);
        }

        $_SESSION['message'] = "Error al guardar paciente: $errorMsg";
        $_SESSION['message_type'] = "danger";
        header("Location: index.php");
        exit;
    }
}