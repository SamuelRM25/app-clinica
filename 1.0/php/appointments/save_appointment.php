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
        
        // Validate required fields
        if (empty($_POST['nombre_pac']) || empty($_POST['apellido_pac']) || empty($_POST['fecha_cita']) || empty($_POST['hora_cita'])) {
            throw new Exception("Los campos de nombre, apellido, fecha y hora son obligatorios");
        }
        
        // Get the next appointment number
        $stmt = $conn->query("SELECT MAX(num_cita) as max_num FROM citas");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $num_cita = ($result['max_num'] ?? 0) + 1;
        
        // Prepare SQL statement
        $sql = "INSERT INTO citas (nombre_pac, apellido_pac, num_cita, fecha_cita, hora_cita, telefono) 
                VALUES (:nombre_pac, :apellido_pac, :num_cita, :fecha_cita, :hora_cita, :telefono)";
        
        $stmt = $conn->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':nombre_pac', $_POST['nombre_pac']);
        $stmt->bindParam(':apellido_pac', $_POST['apellido_pac']);
        $stmt->bindParam(':num_cita', $num_cita);
        $stmt->bindParam(':fecha_cita', $_POST['fecha_cita']);
        $stmt->bindParam(':hora_cita', $_POST['hora_cita']);
        $stmt->bindParam(':telefono', $_POST['telefono']);
        
        // Execute the statement
        if ($stmt->execute()) {
            $_SESSION['appointment_message'] = "Cita guardada correctamente";
            $_SESSION['appointment_status'] = "success";
        } else {
            throw new Exception("Error al guardar la cita");
        }
        
    } catch (Exception $e) {
        $_SESSION['appointment_message'] = "Error: " . $e->getMessage();
        $_SESSION['appointment_status'] = "error";
    }
    
    // Redirect back to the appointments page
    header("Location: index.php");
    exit;
}