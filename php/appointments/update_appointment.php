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
        if (empty($_POST['id_cita']) || empty($_POST['nombre_pac']) || empty($_POST['apellido_pac']) || empty($_POST['fecha_cita']) || empty($_POST['hora_cita']) || empty($_POST['id_doctor'])) {
            throw new Exception("Todos los campos marcados como obligatorios son necesarios");
        }
        
        // Prepare SQL statement
        $sql = "UPDATE citas SET 
                nombre_pac = :nombre_pac, 
                apellido_pac = :apellido_pac, 
                fecha_cita = :fecha_cita, 
                hora_cita = :hora_cita, 
                telefono = :telefono, 
                id_doctor = :id_doctor
                WHERE id_cita = :id_cita";
        
        $stmt = $conn->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':nombre_pac', $_POST['nombre_pac']);
        $stmt->bindParam(':apellido_pac', $_POST['apellido_pac']);
        $stmt->bindParam(':fecha_cita', $_POST['fecha_cita']);
        $stmt->bindParam(':hora_cita', $_POST['hora_cita']);
        $stmt->bindParam(':telefono', $_POST['telefono']);
        $stmt->bindParam(':id_doctor', $_POST['id_doctor']);
        $stmt->bindParam(':id_cita', $_POST['id_cita']);
        
        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Cita actualizada correctamente'
            ]);
        } else {
            throw new Exception("Error al actualizar la cita");
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error', 
            'message' => "Error: " . $e->getMessage()
        ]);
    }
}
?>
