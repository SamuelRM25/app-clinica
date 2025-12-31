<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->query("SELECT * FROM citas");
    
    $events = [];
    while ($row = $stmt->fetch()) {
        // Update the events array creation to include the ID
        $events[] = [
            'id' => $row['id_cita'],  // Make sure to include this
            'title' => $row['nombre_pac'] . ' ' . $row['apellido_pac'],
            'start' => $row['fecha_cita'] . 'T' . $row['hora_cita'],
            'backgroundColor' => '#007bff',
            'borderColor' => '#0056b3'
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($events);
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al cargar las citas']);
}