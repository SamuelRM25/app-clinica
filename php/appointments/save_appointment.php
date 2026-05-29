<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

date_default_timezone_set('America/Guatemala');
verify_session();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    try {
        $database = new Database();
        $conn = $database->getConnection();
        $conn->beginTransaction();

        // Validate required fields
        if (empty($_POST['nombre_pac']) || empty($_POST['apellido_pac']) || empty($_POST['fecha_cita']) || empty($_POST['hora_cita']) || empty($_POST['id_doctor'])) {
            throw new Exception("Los campos de nombre, apellido, fecha, hora y médico son obligatorios");
        }

        // Get the next appointment number (locked within transaction)
        $stmt = $conn->prepare("SELECT COALESCE(MAX(num_cita), 0) + 1 as next_num FROM citas WHERE id_hospital = ? FOR UPDATE");
        $stmt->execute([$id_hospital]);
        $num_cita = $stmt->fetch(PDO::FETCH_ASSOC)['next_num'];

        // Prepare SQL statement
        $sql = "INSERT INTO citas (id_paciente, nombre_pac, apellido_pac, num_cita, fecha_cita, hora_cita, telefono, id_doctor, id_hospital) 
                VALUES (:id_paciente, :nombre_pac, :apellido_pac, :num_cita, :fecha_cita, :hora_cita, :telefono, :id_doctor, :id_hospital)";

        $stmt = $conn->prepare($sql);

        // Bind parameters
        $id_paciente = !empty($_POST['id_paciente']) ? $_POST['id_paciente'] : null;
        $stmt->bindParam(':id_paciente', $id_paciente);
        $stmt->bindParam(':nombre_pac', $_POST['nombre_pac']);
        $stmt->bindParam(':apellido_pac', $_POST['apellido_pac']);
        $stmt->bindParam(':num_cita', $num_cita);
        $stmt->bindParam(':fecha_cita', $_POST['fecha_cita']);
        $stmt->bindParam(':hora_cita', $_POST['hora_cita']);
        $stmt->bindParam(':telefono', $_POST['telefono']);
        $stmt->bindParam(':id_doctor', $_POST['id_doctor']);
        $stmt->bindParam(':id_hospital', $id_hospital, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $conn->commit();

            // Check if patient exists
            $checkPatient = $conn->prepare("SELECT id_paciente FROM pacientes WHERE nombre = ? AND apellido = ? AND id_hospital = ?");
            $checkPatient->execute([$_POST['nombre_pac'], $_POST['apellido_pac'], $id_hospital]);
            $patientExists = $checkPatient->fetch() ? true : false;

            echo json_encode([
                'status' => 'success',
                'message' => 'Cita guardada correctamente',
                'patient_exists' => $patientExists,
                'patient_data' => [
                    'nombre' => $_POST['nombre_pac'],
                    'apellido' => $_POST['apellido_pac'],
                    'telefono' => $_POST['telefono']
                ]
            ]);
        } else {
            throw new Exception("Error al guardar la cita");
        }

    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode([
            'status' => 'error',
'message' => "Error: Error del servidor."
        ]);
    }
}
?>