<?php
// get_discharges_report.php - API to fetch discharged patients and revenue
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit;
}

require_once '../../../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    $start = $_GET['start'] ?? date('Y-m-01');
    $end = $_GET['end'] ?? date('Y-m-d');

    // Add time to end date to cover the whole day
    $end_datetime = $end . ' 23:59:59';
    $start_datetime = $start . ' 00:00:00';

    $stmt = $conn->prepare("
        SELECT 
            e.id_encamamiento,
            e.fecha_alta,
            e.tipo_ingreso,
            pac.nombre as nombre_paciente,
            pac.apellido as apellido_paciente,
            u.nombre as nombre_doctor,
            u.apellido as apellido_doctor,
            DATEDIFF(e.fecha_alta, e.fecha_ingreso) as dias_hospitalizado,
            ch.total_general
        FROM encamamientos e
        INNER JOIN pacientes pac ON e.id_paciente = pac.id_paciente
        LEFT JOIN usuarios u ON e.id_doctor = u.idUsuario
        LEFT JOIN cuenta_hospitalaria ch ON e.id_encamamiento = ch.id_encamamiento
        WHERE e.estado IN ('Alta_Medica', 'Alta_Administrativa')
        AND e.fecha_alta BETWEEN ? AND ?
        ORDER BY e.fecha_alta DESC
    ");

    $stmt->execute([$start_datetime, $end_datetime]);
    $report = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'report' => $report
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
