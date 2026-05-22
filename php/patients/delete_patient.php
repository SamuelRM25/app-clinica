<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

verify_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        // Check if patient has appointments before deleting
        $stmt = $conn->prepare("SELECT COUNT(*) FROM citas WHERE paciente_cita = ? AND id_hospital = ?");
        $stmt->execute([$_POST['id'], $id_hospital]);
        $hasAppointments = $stmt->fetchColumn() > 0;

        if ($hasAppointments) {
            throw new Exception('No se puede eliminar el paciente porque tiene citas registradas');
        }

        // Delete patient
        $stmt = $conn->prepare("DELETE FROM pacientes WHERE id_paciente = ? AND id_hospital = ?");
        $stmt->execute([$_POST['id'], $id_hospital]);

        echo "success";

    } catch (Exception $e) {
        error_log($e->getMessage());
        echo "error";
    }
    exit;
}