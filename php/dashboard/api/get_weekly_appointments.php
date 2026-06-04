<?php
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/multitenant.php';

header('Content-Type: application/json');

csrf_token();

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);
if (!isset($_SESSION['user_id']) || empty($_SESSION['id_hospital'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión inválida']);
    exit;
}

$weekStart = isset($_GET['week_start']) ? $_GET['week_start'] : date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week', strtotime($weekStart)));

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("
        SELECT c.*,
               p.nombre as paciente_nombre, p.apellido as paciente_apellido,
               u.nombre as doctor_nombre, u.apellido as doctor_apellido
        FROM citas c
        LEFT JOIN pacientes p ON c.id_paciente = p.id_paciente
        LEFT JOIN usuarios u ON c.id_doctor = u.idUsuario
        WHERE c.id_hospital = ?
          AND DATE(c.fecha_cita) BETWEEN ? AND ?
        ORDER BY c.fecha_cita ASC, c.hora_cita ASC
    ");
    $stmt->execute([$id_hospital, $weekStart, $weekEnd]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'week_start' => $weekStart,
        'week_end' => $weekEnd,
        'appointments' => $appointments
    ]);

} catch (Exception $e) {
    error_log('Error en api/get_weekly_appointments.php: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error del servidor']);
}