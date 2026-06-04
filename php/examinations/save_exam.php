<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';



// Establecer la zona horaria correcta
date_default_timezone_set('America/Guatemala');

verify_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $id_paciente = $_POST['id_paciente'];
    $nombre_paciente = $_POST['nombre_paciente'];
    $examenes = $_POST['examenes'] ?? [];
    $cobro = $_POST['cobro'];

    // Filtrar exámenes vacíos
    $examenes_filtrados = array_filter($examenes, function ($value) {
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
        $id_hospital = $_SESSION['id_hospital'] ?? 0;

        $stmt = $conn->prepare(
            "INSERT INTO examenes_realizados (id_paciente, nombre_paciente, tipo_examen, cobro, fecha_examen, usuario, id_hospital) 
             VALUES (:id_paciente, :nombre_paciente, :tipo_examen, :cobro, :fecha_examen, :usuario, :id_hospital)"
        );

        $examen_texto = implode(', ', $examenes_filtrados);
        $fecha_actual = date('Y-m-d H:i:s');

        $stmt->bindParam(':id_paciente', $id_paciente);
        $stmt->bindParam(':nombre_paciente', $nombre_paciente);
        $stmt->bindParam(':tipo_examen', $examen_texto);
        $stmt->bindParam(':cobro', $cobro);
        $stmt->bindParam(':fecha_examen', $fecha_actual);
        $stmt->bindParam(':usuario', $_SESSION['nombre']);
        $stmt->bindParam(':id_hospital', $id_hospital);

        $stmt->execute();
        $id_examen = $conn->lastInsertId();

        audit_log('create', 'examinations', "Examen realizado: $examen_texto - Paciente: $nombre_paciente", [
            'table_name' => 'examenes_realizados',
            'record_id' => (int)$id_examen,
            'new_data' => [
                'id_paciente' => $id_paciente,
                'nombre_paciente' => $nombre_paciente,
                'tipo_examen' => $examen_texto,
                'cobro' => $cobro,
                'fecha_examen' => $fecha_actual,
            ]
        ]);

        header('Location: index.php?status=success&message=Examen guardado exitosamente.');
        exit;

    } catch (PDOException $e) {
        error_log("php/examinations/save_exam.php error: " . $e->getMessage());
        header('Location: index.php?status=error&message=' . urlencode('Error del servidor.'));
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
?>