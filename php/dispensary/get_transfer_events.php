<?php
// dispensary/get_transfer_events.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

require_once '../../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    // FullCalendar sends 'start' and 'end' as ISO8601 strings
    $start = $_GET['start'] ?? date('Y-m-01');
    $end = $_GET['end'] ?? date('Y-m-t');

    $sql = "SELECT id_venta as id, nombre_cliente as title, fecha_venta as start, total as total
            FROM ventas 
            WHERE tipo_pago = 'Traslado' AND fecha_venta BETWEEN ? AND ?
            ORDER BY fecha_venta ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$start, $end]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format for FullCalendar
    $calendar_events = [];
    foreach ($events as $event) {
        $calendar_events[] = [
            'id' => $event['id'],
            'title' => 'Q' . number_format($event['total'], 2) . ' - ' . $event['title'],
            'start' => $event['start'],
            'allDay' => false,
            'backgroundColor' => '#dc3545', // Danger color for transfers as per UI theme
            'borderColor' => '#dc3545',
            'textColor' => '#ffffff',
            'extendedProps' => [
                'total' => $event['total'],
                'cliente' => $event['title']
            ]
        ];
    }

    echo json_encode($calendar_events);

} catch (Exception $e) {
    error_log("Error in get_transfer_events.php: " . $e->getMessage());
    echo json_encode([]);
}
