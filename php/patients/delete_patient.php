<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

verify_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    verify_csrf_token();
    try {
        $database = new Database();
        $conn = $database->getConnection();

        // Check if patient has appointments before deleting
        $stmt = $conn->prepare("SELECT COUNT(*) FROM citas WHERE id_paciente = ? AND id_hospital = ?");
        $stmt->execute([$_POST['id'], $id_hospital]);
        $hasAppointments = $stmt->fetchColumn() > 0;

        // Also check for hospitalizations, medical records, lab orders, billing, exams, procedures
        if (!$hasAppointments) {
            $tables_to_check = [
                'encamamientos' => 'id_paciente',
                'historial_clinico' => 'id_paciente',
                'ordenes_laboratorio' => 'id_paciente',
                'cobros' => 'paciente_cobro',
                'examenes_realizados' => 'id_paciente',
                'procedimientos_menores' => 'id_paciente',
                'ultrasonidos' => 'id_paciente',
                'rayos_x' => 'id_paciente',
                'electrocardiogramas' => 'id_paciente'
            ];
            $allowed_tables = ['encamamientos', 'historial_clinico', 'ordenes_laboratorio', 'cobros', 'examenes_realizados', 'procedimientos_menores', 'ultrasonidos', 'rayos_x', 'electrocardiogramas'];
            foreach ($tables_to_check as $table => $column) {
                if (!in_array($table, $allowed_tables, true)) continue;
                $stmt = $conn->prepare("SELECT COUNT(*) FROM `$table` WHERE `$column` = ? AND id_hospital = ?");
                $stmt->execute([$_POST['id'], $id_hospital]);
                if ($stmt->fetchColumn() > 0) {
                    $hasAppointments = true;
                    throw new Exception("No se puede eliminar el paciente porque tiene registros asociados en $table");
                }
            }
        }

        if ($hasAppointments) {
            throw new Exception('No se puede eliminar el paciente porque tiene citas o registros asociados');
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