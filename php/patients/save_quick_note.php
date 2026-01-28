<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer la zona horaria correcta
date_default_timezone_set('America/Guatemala');

verify_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        $id_paciente = $_POST['id_paciente'] ?? null;
        $nota = $_POST['nota'] ?? '';

        if (empty($id_paciente)) {
            throw new Exception("ID de paciente no proporcionado");
        }

        // Update the 'notas' column in the 'pacientes' table
        $sql = "UPDATE pacientes SET notas = :nota WHERE id_paciente = :id_paciente";

        $stmt = $conn->prepare($sql);

        $stmt->bindParam(':id_paciente', $id_paciente);
        $stmt->bindParam(':nota', $nota);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Nota flotante actualizada correctamente";
            $_SESSION['message_type'] = "success";
        } else {
            throw new Exception("Error al actualizar la nota");
        }

    } catch (Exception $e) {
        $_SESSION['message'] = "Error: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }

    // Redirect back to the patients index
    header("Location: index.php");
    exit;
}
