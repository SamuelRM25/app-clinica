<?php
// get_shift_cut_data.php
// Returns JSON data for the Shift Cut report with both totals and detailed transaction rows
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
    $shift = $_GET['shift'] ?? 'morning';

    // Define shift ranges
    if ($shift === 'morning') {
        $start_datetime = $date . ' 08:00:00';
        $end_datetime = $date . ' 17:00:00';
    } else {
        $start_datetime = $date . ' 17:00:00';
        $end_datetime = date('Y-m-d', strtotime($date . ' +1 day')) . ' 07:59:59';
    }

    $methods = ['Efectivo', 'Tarjeta', 'Transferencia'];

    // Helper to get detailed data (totals and individual rows)
    $getDetailedData = function ($conn, $table, $column_amount, $column_date, $start, $end, $column_pago = 'tipo_pago', $extra_where = '', $select_extras = '', $joins = '') use ($methods, $shift, $date) {
        $breakdown = [];
        $total = 0;

        // 1. Calculate Breakdown
        foreach ($methods as $method) {
            $sql = "SELECT SUM($column_amount) FROM $table $joins WHERE $column_pago = ?";
            $params = [$method];
            if ($shift === 'morning') {
                $sql .= " AND $column_date BETWEEN ? AND ?";
                $params[] = $start;
                $params[] = $end;
            } else {
                $sql .= " AND $column_date BETWEEN ? AND ?";
                $params[] = $start;
                $params[] = $end;
            }
            if ($extra_where)
                $sql .= " AND $extra_where";

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $val = (float) ($stmt->fetchColumn() ?: 0);
            $breakdown[$method] = $val;
            $total += $val;
        }

        // 2. Fetch Individual Rows
        $sql_rows = "SELECT $column_date as hora, $column_amount as monto, $column_pago as tipo_pago $select_extras FROM $table $joins WHERE ";
        $params_rows = [];
        if ($shift === 'morning') {
            $sql_rows .= "$column_date BETWEEN ? AND ?";
            $params_rows = [$start, $end];
        } else {
            $sql_rows .= "$column_date BETWEEN ? AND ?";
            $params_rows = [$start, $end];
        }
        if ($extra_where)
            $sql_rows .= " AND $extra_where";
        $sql_rows .= " ORDER BY $column_date ASC";

        $stmt_rows = $conn->prepare($sql_rows);
        $stmt_rows->execute($params_rows);
        $rows = $stmt_rows->fetchAll(PDO::FETCH_ASSOC);

        // Format hora to HH:MM
        foreach ($rows as &$row) {
            if (isset($row['hora'])) {
                $row['hora'] = date('H:i', strtotime($row['hora']));
            }
        }

        return ['breakdown' => $breakdown, 'total' => $total, 'details' => $rows];
    };

    // 1. Pharmacy Sales (ventas)
    // Joined with detailing to get medication names
    $pharmacy = $getDetailedData(
        $conn,
        'ventas',
        'total',
        'fecha_venta',
        $start_datetime,
        $end_datetime,
        'tipo_pago',
        '',
        ', nombre_cliente as cliente, (SELECT GROUP_CONCAT(i.nom_medicamento SEPARATOR ", ") 
         FROM detalle_ventas dv 
         JOIN inventario i ON dv.id_inventario = i.id_inventario 
         WHERE dv.id_venta = ventas.id_venta) as detalle',
        ''
    );

    // 2. Consultations (cobros)
    // Joined with appointments to get the scheduled time
    // Fixed: cobros table doesn't have id_cita, using paciente_cobro and id_doctor instead
    $consultations_raw = $getDetailedData(
        $conn,
        'cobros',
        'cantidad_consulta',
        'fecha_consulta',
        $start_datetime,
        $end_datetime,
        'tipo_pago',
        '',
        ', CONCAT(u.nombre, " ", u.apellido) as medico, CONCAT(p.nombre, " ", p.apellido) as paciente, COALESCE(ci.hora_cita, DATE_FORMAT(cobros.fecha_consulta, "%H:%i")) as hora',
        'JOIN usuarios u ON cobros.id_doctor = u.idUsuario 
         JOIN pacientes p ON cobros.paciente_cobro = p.id_paciente 
         LEFT JOIN citas ci ON cobros.paciente_cobro = ci.historial_id AND DATE(cobros.fecha_consulta) = ci.fecha_cita AND cobros.id_doctor = ci.id_doctor'
    );

    // We also need doctors breakdown for the consultations section
    $doc_query = "SELECT DISTINCT u.idUsuario, u.nombre, u.apellido 
                  FROM cobros c
                  JOIN usuarios u ON c.id_doctor = u.idUsuario
                  WHERE c.fecha_consulta BETWEEN ? AND ?";
    $stmt_docs = $conn->prepare($doc_query);
    $stmt_docs->execute([$start_datetime, $end_datetime]);
    $doctors = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);
    $by_doctor = [];
    foreach ($doctors as $doc) {
        $doc_breakdown = [];
        $doc_total = 0;
        foreach ($methods as $method) {
            $q = "SELECT SUM(cantidad_consulta) FROM cobros WHERE id_doctor = ? AND tipo_pago = ? AND fecha_consulta BETWEEN ? AND ?";
            $p = [$doc['idUsuario'], $method, $start_datetime, $end_datetime];
            $stmt = $conn->prepare($q);
            $stmt->execute($p);
            $v = (float) ($stmt->fetchColumn() ?: 0);
            $doc_breakdown[$method] = $v;
            $doc_total += $v;
        }
        $by_doctor[] = ['doctor' => $doc['nombre'] . ' ' . $doc['apellido'], 'breakdown' => $doc_breakdown, 'total' => $doc_total];
    }
    $consultations = [
        'breakdown' => $consultations_raw['breakdown'],
        'total' => $consultations_raw['total'],
        'details' => $consultations_raw['details'],
        'by_doctor' => $by_doctor
    ];

    // 3. Laboratory (exclude US/RX)
    $lab_extra = "tipo_examen NOT LIKE '%ultrasonido%' AND tipo_examen NOT LIKE '%rayos x%' AND tipo_examen NOT LIKE '%rx%'";
    $laboratory = $getDetailedData($conn, 'examenes_realizados', 'cobro', 'fecha_examen', $start_datetime, $end_datetime, 'tipo_pago', $lab_extra, ', nombre_paciente as paciente', '');

    // 4. Procedures
    $procedures = $getDetailedData($conn, 'procedimientos_menores', 'cobro', 'fecha_procedimiento', $start_datetime, $end_datetime, 'tipo_pago', '', ', nombre_paciente as paciente', '');

    // 5. Ultrasound
    $us_new = $getDetailedData($conn, 'ultrasonidos', 'cobro', 'fecha_ultrasonido', $start_datetime, $end_datetime, 'tipo_pago', '', ', nombre_paciente as paciente', '');
    $us_old = $getDetailedData($conn, 'examenes_realizados', 'cobro', 'fecha_examen', $start_datetime, $end_datetime, 'tipo_pago', "tipo_examen LIKE '%ultrasonido%'", ', nombre_paciente as paciente', '');
    $ultrasound = [
        'total' => $us_new['total'] + $us_old['total'],
        'breakdown' => [
            'Efectivo' => $us_new['breakdown']['Efectivo'] + $us_old['breakdown']['Efectivo'],
            'Tarjeta' => $us_new['breakdown']['Tarjeta'] + $us_old['breakdown']['Tarjeta'],
            'Transferencia' => $us_new['breakdown']['Transferencia'] + $us_old['breakdown']['Transferencia'],
        ],
        'details' => array_merge($us_new['details'], $us_old['details'])
    ];
    // 6. X-Rays
    $rx_new = $getDetailedData($conn, 'rayos_x', 'cobro', 'fecha_estudio', $start_datetime, $end_datetime, 'tipo_pago', '', ', nombre_paciente as paciente', '');
    $rx_old = $getDetailedData($conn, 'examenes_realizados', 'cobro', 'fecha_examen', $start_datetime, $end_datetime, 'tipo_pago', "(tipo_examen LIKE '%rayos x%' OR tipo_examen LIKE '%rx%')", ', nombre_paciente as paciente', '');
    $xray = [
        'total' => $rx_new['total'] + $rx_old['total'],
        'breakdown' => [
            'Efectivo' => $rx_new['breakdown']['Efectivo'] + $rx_old['breakdown']['Efectivo'],
            'Tarjeta' => $rx_new['breakdown']['Tarjeta'] + $rx_old['breakdown']['Tarjeta'],
            'Transferencia' => $rx_new['breakdown']['Transferencia'] + $rx_old['breakdown']['Transferencia'],
        ],
        'details' => array_merge($rx_new['details'], $rx_old['details'])
    ];

    // 7. Electrocardiograms
    $electro = $getDetailedData(
        $conn,
        'electrocardiogramas',
        'precio',
        'fecha_realizado',
        $start_datetime,
        $end_datetime,
        'tipo_pago',
        '',
        ', CONCAT(p.nombre, " ", p.apellido) as paciente',
        'JOIN pacientes p ON electrocardiogramas.id_paciente = p.id_paciente'
    );

    // 8. Hospitalization (Abonos)
    $hospitalization = $getDetailedData($conn, 'abonos_hospitalarios', 'ah.monto', 'ah.fecha_abono', $start_datetime, $end_datetime, 'ah.metodo_pago', '', ", CONCAT(p.nombre, ' ', p.apellido) as paciente", "ah JOIN cuenta_hospitalaria ch ON ah.id_cuenta = ch.id_cuenta JOIN encamamientos e ON ch.id_encamamiento = e.id_encamamiento JOIN pacientes p ON e.id_paciente = p.id_paciente");

    $grand_total = $pharmacy['total'] + $consultations['total'] + $laboratory['total'] + $procedures['total'] + $ultrasound['total'] + $xray['total'] + $electro['total'] + $hospitalization['total'];

    echo json_encode([
        'success' => true,
        'data' => [
            'pharmacy' => $pharmacy,
            'consultations' => $consultations,
            'laboratory' => $laboratory,
            'procedures' => $procedures,
            'ultrasound' => $ultrasound,
            'xray' => $xray,
            'electro' => $electro,
            'hospitalization' => $hospitalization,
            'grand_total' => $grand_total,
            'period' => ['start' => $start_datetime, 'end' => $end_datetime, 'shift' => $shift]
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}