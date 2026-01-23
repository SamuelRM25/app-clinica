<?php
// get_shift_cut_data.php
// Returns JSON data for the Shift Cut report with detailed breakdown
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get input parameters
    $date = $_GET['date'] ?? date('Y-m-d');
    $shift = $_GET['shift'] ?? 'morning'; // 'morning' or 'night'

    // Define shift ranges
    if ($shift === 'morning') {
        $start_datetime = $date . ' 08:00:00';
        $end_datetime = $date . ' 17:00:00';
    } else {
        $start_datetime = $date . ' 17:00:00';
        $end_datetime = date('Y-m-d', strtotime($date . ' +1 day')) . ' 07:59:59';
    }

    $methods = ['Efectivo', 'Tarjeta', 'Transferencia'];

    // Updated helper to get totals with optional where clause and DATE support
    $getTotals = function ($conn, $table, $column_amount, $column_date, $start, $end, $column_pago = 'tipo_pago', $extra_where = '') use ($methods, $shift, $date) {
        $results = [];
        $total = 0;
        foreach ($methods as $method) {
            $sql = "SELECT SUM($column_amount) FROM $table WHERE $column_pago = ?";
            $params = [$method];

            // If it's a morning shift, we also include records that are DATE only (interpreted as 00:00:00) 
            // matching the selected day, to handle legacy or non-timestamped data.
            if ($shift === 'morning') {
                $sql .= " AND ($column_date BETWEEN ? AND ? OR DATE($column_date) = ?)";
                $params[] = $start;
                $params[] = $end;
                $params[] = $date;
            } else {
                $sql .= " AND $column_date BETWEEN ? AND ?";
                $params[] = $start;
                $params[] = $end;
            }

            if ($extra_where) {
                $sql .= " AND $extra_where";
            }

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $val = (float) ($stmt->fetchColumn() ?: 0);
            $results[$method] = $val;
            $total += $val;
        }
        return ['breakdown' => $results, 'total' => $total];
    };

    // 1. Pharmacy Sales (Ventas)
    $pharmacy = $getTotals($conn, 'ventas', 'total', 'fecha_venta', $start_datetime, $end_datetime, 'tipo_pago');

    // 2. Consultations (Cobros) - Detailed by Doctor
    $consultations_breakdown = [];
    $consultations_total = 0;

    // Get all doctors who made charges in this period
    // We use the same DATE() fallback for morning shift to find doctors with legacy entries
    $doc_query = "SELECT DISTINCT u.idUsuario, u.nombre, u.apellido 
                  FROM cobros c
                  JOIN usuarios u ON c.id_doctor = u.idUsuario
                  WHERE ";
    $doc_params = [];

    if ($shift === 'morning') {
        $doc_query .= "(c.fecha_consulta BETWEEN ? AND ? OR DATE(c.fecha_consulta) = ?)";
        $doc_params = [$start_datetime, $end_datetime, $date];
    } else {
        $doc_query .= "c.fecha_consulta BETWEEN ? AND ?";
        $doc_params = [$start_datetime, $end_datetime];
    }

    $stmt_docs = $conn->prepare($doc_query);
    $stmt_docs->execute($doc_params);
    $doctors = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);

    foreach ($doctors as $doc) {
        $doc_name = $doc['nombre'] . ' ' . $doc['apellido'];
        $doc_data = [];
        $doc_total = 0;
        foreach ($methods as $method) {
            $q = "SELECT SUM(cantidad_consulta) FROM cobros WHERE id_doctor = ? AND tipo_pago = ?";
            $p = [$doc['idUsuario'], $method];

            if ($shift === 'morning') {
                $q .= " AND (fecha_consulta BETWEEN ? AND ? OR DATE(fecha_consulta) = ?)";
                $p[] = $start_datetime;
                $p[] = $end_datetime;
                $p[] = $date;
            } else {
                $q .= " AND fecha_consulta BETWEEN ? AND ?";
                $p[] = $start_datetime;
                $p[] = $end_datetime;
            }

            $stmt = $conn->prepare($q);
            $stmt->execute($p);
            $val = (float) ($stmt->fetchColumn() ?: 0);
            $doc_data[$method] = $val;
            $doc_total += $val;
        }
        $consultations_breakdown[] = [
            'doctor' => $doc_name,
            'breakdown' => $doc_data,
            'total' => $doc_total
        ];
        $consultations_total += $doc_total;
    }

    // Calculate aggregate breakdown for consultations to show in the main table
    $consultations_aggregate_breakdown = ['Efectivo' => 0, 'Tarjeta' => 0, 'Transferencia' => 0];
    foreach ($consultations_breakdown as $doc_b) {
        foreach ($methods as $method) {
            $consultations_aggregate_breakdown[$method] += $doc_b['breakdown'][$method];
        }
    }

    // 3. Laboratories (examenes_realizados)
    // Filter out Ultrasound and X-Ray from general laboratory
    $lab_extra = "tipo_examen NOT LIKE '%ultrasonido%' AND tipo_examen NOT LIKE '%rayos x%' AND tipo_examen NOT LIKE '%rx%'";
    $lab = $getTotals($conn, 'examenes_realizados', 'cobro', 'fecha_examen', $start_datetime, $end_datetime, 'tipo_pago', $lab_extra);

    // 4. Minor Procedures (procedimientos_menores)
    $procedures = $getTotals($conn, 'procedimientos_menores', 'cobro', 'fecha_procedimiento', $start_datetime, $end_datetime);

    // 5. Ultrasound (Combine new dedicated table and legacy examenes_realizados entries)
    $us_new = $getTotals($conn, 'ultrasonidos', 'cobro', 'fecha_ultrasonido', $start_datetime, $end_datetime);
    $us_old = $getTotals($conn, 'examenes_realizados', 'cobro', 'fecha_examen', $start_datetime, $end_datetime, 'tipo_pago', "tipo_examen LIKE '%ultrasonido%'");
    $ultrasound = [
        'total' => $us_new['total'] + $us_old['total'],
        'breakdown' => [
            'Efectivo' => $us_new['breakdown']['Efectivo'] + $us_old['breakdown']['Efectivo'],
            'Tarjeta' => $us_new['breakdown']['Tarjeta'] + $us_old['breakdown']['Tarjeta'],
            'Transferencia' => $us_new['breakdown']['Transferencia'] + $us_old['breakdown']['Transferencia'],
        ]
    ];

    // 6. X-Rays (Combine new dedicated table and legacy examenes_realizados entries)
    $rx_new = $getTotals($conn, 'rayos_x', 'cobro', 'fecha_estudio', $start_datetime, $end_datetime);
    $rx_old = $getTotals($conn, 'examenes_realizados', 'cobro', 'fecha_examen', $start_datetime, $end_datetime, 'tipo_pago', "(tipo_examen LIKE '%rayos x%' OR tipo_examen LIKE '%rx%')");
    $xray = [
        'total' => $rx_new['total'] + $rx_old['total'],
        'breakdown' => [
            'Efectivo' => $rx_new['breakdown']['Efectivo'] + $rx_old['breakdown']['Efectivo'],
            'Tarjeta' => $rx_new['breakdown']['Tarjeta'] + $rx_old['breakdown']['Tarjeta'],
            'Transferencia' => $rx_new['breakdown']['Transferencia'] + $rx_old['breakdown']['Transferencia'],
        ]
    ];

    $grand_total = $pharmacy['total'] + $consultations_total + $lab['total'] + $procedures['total'] + $ultrasound['total'] + $xray['total'];

    echo json_encode([
        'success' => true,
        'period' => [
            'start' => $start_datetime,
            'end' => $end_datetime,
            'shift' => $shift
        ],
        'data' => [
            'pharmacy' => $pharmacy,
            'consultations' => [
                'by_doctor' => $consultations_breakdown,
                'breakdown' => $consultations_aggregate_breakdown,
                'total' => $consultations_total
            ],
            'laboratory' => $lab,
            'procedures' => $procedures,
            'ultrasound' => $ultrasound,
            'xray' => $xray,
            'grand_total' => $grand_total
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>