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
    $procedimientos = $_POST['procedimientos'] ?? [];
    $cobro = $_POST['cobro'];

    // Filtrar procedimientos vacíos (del campo "otro" si no se llenó)
    $procedimientos_filtrados = array_filter($procedimientos, function($value) {
        return !empty($value);
    });

    if (empty($id_paciente) || empty($procedimientos_filtrados) || !is_numeric($cobro)) {
        header('Location: index.php?status=error&message=Faltan datos por llenar.');
        exit;
    }

    try {
        $database = new Database();
        $conn = $database->getConnection();

        // Preparar la consulta para insertar
        $stmt = $conn->prepare(
            "INSERT INTO procedimientos_menores (id_paciente, nombre_paciente, procedimiento, cobro, fecha_procedimiento, usuario) VALUES (:id_paciente, :nombre_paciente, :procedimiento, :cobro, :fecha_procedimiento, :usuario)"
        );

        // Combinar todos los procedimientos en un solo texto
        $procedimiento_texto = implode(', ', $procedimientos_filtrados);
        
        // Obtener la fecha y hora actual en la zona horaria de Guatemala
        $fecha_actual = date('Y-m-d H:i:s');

        $stmt->bindParam(':id_paciente', $id_paciente);
        $stmt->bindParam(':nombre_paciente', $nombre_paciente);
        $stmt->bindParam(':procedimiento', $procedimiento_texto);
        $stmt->bindParam(':cobro', $cobro);
        $stmt->bindParam(':fecha_procedimiento', $fecha_actual);
        $stmt->bindParam(':usuario', $_SESSION['nombre']);
        
        $stmt->execute();

        header('Location: index.php?status=success&message=Procedimiento guardado exitosamente.');
        exit;

    } catch (PDOException $e) {
        header('Location: index.php?status=error&message=' . urlencode('Error al guardar: ' . $e->getMessage()));
        exit;
    }
} else {
    // Si no es POST, redirigir
    header('Location: index.php');
    exit;
}
?>