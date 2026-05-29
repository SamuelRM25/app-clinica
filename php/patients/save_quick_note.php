<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

// Establecer la zona horaria correcta
date_default_timezone_set('America/Guatemala');

verify_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF validation
        $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
        if (empty($csrfHeader) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfHeader)) {
            throw new Exception('Token CSRF inválido');
        }

        $database = new Database();
        $conn = $database->getConnection();

        $id_paciente = $_POST['id_paciente'] ?? null;
        $nota = $_POST['nota'] ?? '';

        if (empty($id_paciente)) {
            throw new Exception("ID de paciente no proporcionado");
        }

        // Update the 'notas' column in the 'pacientes' table
        $sql = "UPDATE pacientes SET notas = :nota WHERE id_paciente = :id_paciente AND id_hospital = :id_hospital";

        $stmt = $conn->prepare($sql);

        $stmt->bindParam(':id_paciente', $id_paciente);
        $stmt->bindParam(':nota', $nota);
        $stmt->bindParam(':id_hospital', $id_hospital, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Nota flotante actualizada correctamente";
            $_SESSION['message_type'] = "success";
        } else {
            throw new Exception("Error al actualizar la nota");
        }

    } catch (Exception $e) {
        error_log('Error en patients/save_quick_note.php: ' . $e->getMessage());
        $_SESSION['message'] = "Error: " . 'Error del servidor.';
        $_SESSION['message_type'] = "danger";
    }

    // Redirect back to the patients index
    header("Location: index.php");
    exit;
}
