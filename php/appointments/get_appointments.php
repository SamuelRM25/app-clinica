<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';



verify_session();

try {
    $database = new Database();
    $conn = $database->getConnection();

    $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

    $stmt = $conn->prepare("
        SELECT c.*, 
               u.nombre as doc_nombre, u.apellido as doc_apellido,
               p.id_paciente
        FROM citas c
        LEFT JOIN usuarios u ON c.id_doctor = u.idUsuario
        LEFT JOIN pacientes p ON (c.nombre_pac = p.nombre AND c.apellido_pac = p.apellido)
        WHERE c.id_hospital = ?
    ");
    $stmt->execute([$id_hospital]);

    $events = [];
    while ($row = $stmt->fetch()) {
        $doctorName = $row['doc_nombre'] ? "Dr. " . $row['doc_nombre'] . " " . $row['doc_apellido'] : "Sin Asignar";
        $events[] = [
            'id' => $row['id_cita'],
            'title' => $row['nombre_pac'] . ' ' . $row['apellido_pac'] . ' - ' . $doctorName,
            'start' => $row['fecha_cita'] . 'T' . $row['hora_cita'],
            'extendedProps' => [
                'doctor' => $doctorName,
                'id_paciente' => $row['id_paciente'] ?? null,
                'tipo' => 'primary'
            ]
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($events);
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al cargar las citas']);
}