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

        // Validar género
        $valid_genders = ['Masculino', 'Femenino'];
        if (!in_array($_POST['genero'], $valid_genders)) {
            throw new Exception('Género inválido');
        }

        // Verificar si ya existe un paciente con el mismo nombre y apellido
        $checkStmt = $conn->prepare("
            SELECT id_paciente 
            FROM pacientes 
            WHERE nombre = ? AND apellido = ?
        ");
        $checkStmt->execute([$_POST['nombre'], $_POST['apellido']]);
        $existingPatient = $checkStmt->fetch(PDO::FETCH_ASSOC);

        // Si existe un paciente duplicado y no se ha confirmado la acción
        if ($existingPatient && !isset($_POST['confirm_action'])) {
            // Guardar datos en sesión para mostrar en la confirmación
            $_SESSION['duplicate_patient_data'] = $_POST;
            $_SESSION['existing_patient_id'] = $existingPatient['id_paciente'];
            
            // Redirigir a página de confirmación
            header("Location: confirm_duplicate.php");
            exit;
        }

        // Si se confirmó una acción sobre un duplicado
        if (isset($_POST['confirm_action'])) {
            $action = $_POST['confirm_action'];
            $patient_id = $_POST['existing_patient_id'];

            switch ($action) {
                case 'replace':
                    // Eliminar paciente existente
                    $deleteStmt = $conn->prepare("DELETE FROM pacientes WHERE id_paciente = ?");
                    $deleteStmt->execute([$patient_id]);
                    // Continuar con inserción (caerá al siguiente bloque)
                    break;
                    
                case 'overwrite':
                    // Actualizar paciente existente
                    $updateStmt = $conn->prepare("
                        UPDATE pacientes SET
                            fecha_nacimiento = ?,
                            genero = ?,
                            direccion = ?,
                            telefono = ?,
                            correo = ?
                        WHERE id_paciente = ?
                    ");
                    $updateStmt->execute([
                        $_POST['fecha_nacimiento'],
                        $_POST['genero'],
                        $_POST['direccion'] ?? null,
                        $_POST['telefono'] ?? null,
                        $_POST['correo'] ?? null,
                        $patient_id
                    ]);
                    
                    $_SESSION['message'] = "Paciente actualizado correctamente";
                    $_SESSION['message_type'] = "success";
                    header("Location: medical_history.php?id=" . $patient_id);
                    exit;
                    
                case 'cancel':
                    $_SESSION['message'] = "Operación cancelada";
                    $_SESSION['message_type'] = "warning";
                    header("Location: index.php");
                    exit;
            }
        }

        // Insertar nuevo paciente (solo si no existe o se eligió reemplazar)
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
        
        $stmt->execute([
            $_POST['nombre'],
            $_POST['apellido'],
            $_POST['fecha_nacimiento'],
            $_POST['genero'],
            $_POST['direccion'] ?? null,
            $_POST['telefono'] ?? null,
            $_POST['correo'] ?? null
        ]);

        $patient_id = $conn->lastInsertId();

        $_SESSION['message'] = "Paciente agregado correctamente";
        $_SESSION['message_type'] = "success";
        header("Location: medical_history.php?id=" . $patient_id);
        exit;

    } catch (Exception $e) {
        $_SESSION['message'] = "Error: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
        header("Location: index.php");
        exit;
    }
}