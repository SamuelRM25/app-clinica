<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer la zona horaria correcta
date_default_timezone_set('America/Guatemala');

verify_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_paciente = $_POST['id_paciente'];
    $nombre_paciente = $_POST['nombre_paciente'];
    $examenes = $_POST['examenes'] ?? [];
    $cobro = $_POST['cobro'];

    // Filtrar exámenes vacíos
    $examenes_filtrados = array_filter($examenes, function($value) {
        return !empty($value);
    });

    if (empty($id_paciente) || empty($examenes_filtrados) || !is_numeric($cobro)) {
        header('Location: index.php?status=error&message=Faltan datos por llenar.');
        exit;
    }

    try {
        $database = new Database();
        $conn = $database->getConnection();

        // Usar solo los campos que existen en la tabla
        $stmt = $conn->prepare(
            "INSERT INTO examenes_realizados (id_paciente, nombre_paciente, tipo_examen, cobro, fecha_examen, usuario) 
             VALUES (:id_paciente, :nombre_paciente, :tipo_examen, :cobro, :fecha_examen, :usuario)"
        );

        // Combinar todos los exámenes en un solo texto
        $examen_texto = implode(', ', $examenes_filtrados);
        
        // Obtener la fecha y hora actual en la zona horaria de Guatemala
        $fecha_actual = date('Y-m-d H:i:s');

        $stmt->bindParam(':id_paciente', $id_paciente);
        $stmt->bindParam(':nombre_paciente', $nombre_paciente);
        $stmt->bindParam(':tipo_examen', $examen_texto);
        $stmt->bindParam(':cobro', $cobro);
        $stmt->bindParam(':fecha_examen', $fecha_actual);
        $stmt->bindParam(':usuario', $_SESSION['nombre']);
        
        $stmt->execute();

        header('Location: index.php?status=success&message=Examen guardado exitosamente.');
        exit;

    } catch (PDOException $e) {
        header('Location: index.php?status=error&message=' . urlencode('Error al guardar: ' . $e->getMessage()));
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
?>