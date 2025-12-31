<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

date_default_timezone_set('America/Guatemala');
verify_session();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Validate required fields
        if (empty($_POST['nombre_pac']) || empty($_POST['apellido_pac']) || empty($_POST['fecha_cita']) || empty($_POST['hora_cita']) || empty($_POST['id_doctor'])) {
            throw new Exception("Los campos de nombre, apellido, fecha, hora y médico son obligatorios");
        }
        
        // Get the next appointment number
        $stmt = $conn->query("SELECT MAX(num_cita) as max_num FROM citas");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $num_cita = ($result['max_num'] ?? 0) + 1;
        
        // Prepare SQL statement
        $sql = "INSERT INTO citas (nombre_pac, apellido_pac, num_cita, fecha_cita, hora_cita, telefono, id_doctor) 
                VALUES (:nombre_pac, :apellido_pac, :num_cita, :fecha_cita, :hora_cita, :telefono, :id_doctor)";
        
        $stmt = $conn->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':nombre_pac', $_POST['nombre_pac']);
        $stmt->bindParam(':apellido_pac', $_POST['apellido_pac']);
        $stmt->bindParam(':num_cita', $num_cita);
        $stmt->bindParam(':fecha_cita', $_POST['fecha_cita']);
        $stmt->bindParam(':hora_cita', $_POST['hora_cita']);
        $stmt->bindParam(':telefono', $_POST['telefono']);
        $stmt->bindParam(':id_doctor', $_POST['id_doctor']);
        
        if ($stmt->execute()) {
            // Check if patient exists
            $checkPatient = $conn->prepare("SELECT id_paciente FROM pacientes WHERE nombre = ? AND apellido = ?");
            $checkPatient->execute([$_POST['nombre_pac'], $_POST['apellido_pac']]);
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
        echo json_encode([
            'status' => 'error', 
            'message' => "Error: " . $e->getMessage()
        ]);
    }
}
?>