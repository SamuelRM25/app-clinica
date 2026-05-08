<?php
// dispensary/get_recent_sales.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
    exit;
}

require_once '../../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    date_default_timezone_set('America/Guatemala');
    $now = new DateTime();
    $current_hour = (int) $now->format('H');
    $current_date = $now->format('Y-m-d');

    // Lógica de Jornada
    // Matutina: 08:00 AM - 05:00 PM (17:00)
    // Nocturna: 05:00 PM - 08:00 AM del día siguiente

    if ($current_hour >= 8 && $current_hour < 17) {
        // Jornada Matutina
        $start_datetime = $current_date . ' 08:00:00';
        $end_datetime = $current_date . ' 17:00:00';
    } else {
        // Jornada Nocturna
        if ($current_hour >= 17) {
            $start_datetime = $current_date . ' 17:00:00';
            $end_datetime = (new DateTime($current_date))->modify('+1 day')->format('Y-m-d') . ' 07:59:59';
        } else {
            // Es entre las 00:00 y las 07:59 del día actual (pertenece a la nocturna del día anterior)
            $start_datetime = (new DateTime($current_date))->modify('-1 day')->format('Y-m-d') . ' 17:00:00';
            $end_datetime = $current_date . ' 07:59:59';
        }
    }

    $type = $_GET['type'] ?? '';

    $sql = "SELECT id_venta, nombre_cliente, total, DATE_FORMAT(fecha_venta, '%H:%i') as hora, tipo_pago 
            FROM ventas 
            WHERE fecha_venta BETWEEN ? AND ?";

    $params = [$start_datetime, $end_datetime];

    if (!empty($type)) {
        $sql .= " AND tipo_pago = ?";
        $params[] = $type;
    }

    $sql .= " ORDER BY fecha_venta DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'sales' => $sales,
        'period' => [
            'start' => $start_datetime,
            'end' => $end_datetime
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
