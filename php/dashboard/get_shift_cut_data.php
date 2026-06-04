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
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    $date = $_GET['date'] ?? date('Y-m-d');
    $shift = $_GET['shift'] ?? 'morning';

    if ($shift === 'morning') {
        $start_datetime = $date . ' 08:00:00';
        $end_datetime = $date . ' 17:00:00';
    } else {
        $start_datetime = $date . ' 17:00:00';
        $end_datetime = date('Y-m-d', strtotime($date . ' +1 day')) . ' 07:59:59';
    }

    $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);
    $methods = ['Efectivo', 'Tarjeta', 'Transferencia'];

    $getDetailedData = function ($conn, $table, $column_amount, $column_date, $start, $end, $column_pago = 'tipo_pago', $extra_where = '', $select_extras = '', $joins = '') use ($methods, $id_hospital) {
        $breakdown = [];
        $total = 0;

        foreach ($methods as $method) {
            $sql = "SELECT SUM($column_amount) FROM $table $joins WHERE $column_pago = ? AND $column_date BETWEEN ? AND ? AND $table.id_hospital = ?";
            $params = [$method, $start, $end, $id_hospital];
            if ($extra_where) {
                $sql .= " AND $extra_where";
            }

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $val = (float) ($stmt->fetchColumn() ?: 0);
            $breakdown[$method] = $val;
            $total += $val;
        }

        $sql_rows = "SELECT $column_date as hora, $column_amount as monto, $column_pago as tipo_pago $select_extras FROM $table $joins WHERE $column_date BETWEEN ? AND ? AND $table.id_hospital = ?";
        $params_rows = [$start, $end, $id_hospital];
        if ($extra_where) {
            $sql_rows .= " AND $extra_where";
        }
        $sql_rows .= " ORDER BY $column_date ASC";

        $stmt_rows = $conn->prepare($sql_rows);
        $stmt_rows->execute($params_rows);
        $rows = $stmt_rows->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            if (isset($row['hora'])) {
                $row['hora'] = date('H:i', strtotime($row['hora']));
            }
        }

        return ['breakdown' => $breakdown, 'total' => $total, 'details' => $rows];
    };

    // 1. Pharmacy Sales (ventas)
    $pharmacy = $getDetailedData(
        $conn, 'ventas', 'total', 'fecha_venta',
        $start_datetime, $end_datetime, 'tipo_pago', '',
        ', nombre_cliente as cliente',
        ''
    );

    // 2. Consultations (cobros)
    $consultations = $getDetailedData(
        $conn, 'cobros', 'cantidad_consulta', 'fecha_consulta',
        $start_datetime, $end_datetime, 'tipo_pago', '',
        ', CONCAT(u.nombre, " ", u.apellido) as medico, paciente_cobro as paciente_id, COALESCE(DATE_FORMAT(cobros.fecha_consulta, "%H:%i"), "N/A") as hora',
        'JOIN usuarios u ON cobros.id_doctor = u.idUsuario'
    );

    // Doctors breakdown
    $doc_query = "SELECT DISTINCT u.idUsuario, u.nombre, u.apellido
                  FROM cobros c
                  JOIN usuarios u ON c.id_doctor = u.idUsuario
                  WHERE c.fecha_consulta BETWEEN ? AND ? AND c.id_hospital = ?";
    $stmt_docs = $conn->prepare($doc_query);
    $stmt_docs->execute([$start_datetime, $end_datetime, $id_hospital]);
    $doctors = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);
    $by_doctor = [];
    foreach ($doctors as $doc) {
        $doc_breakdown = [];
        $doc_total = 0;
        foreach ($methods as $method) {
            $q = "SELECT SUM(cantidad_consulta) FROM cobros WHERE id_doctor = ? AND tipo_pago = ? AND fecha_consulta BETWEEN ? AND ? AND id_hospital = ?";
            $stmt = $conn->prepare($q);
            $stmt->execute([$doc['idUsuario'], $method, $start_datetime, $end_datetime, $id_hospital]);
            $v = (float) ($stmt->fetchColumn() ?: 0);
            $doc_breakdown[$method] = $v;
            $doc_total += $v;
        }
        $by_doctor[] = ['doctor' => $doc['nombre'] . ' ' . $doc['apellido'], 'breakdown' => $doc_breakdown, 'total' => $doc_total];
    }
    $consultations['by_doctor'] = $by_doctor;

    // 3. Laboratory (examenes_realizados, excluding US and RX)
    $lab_extra = "tipo_examen NOT LIKE '%ultrasonido%' AND tipo_examen NOT LIKE '%rayos x%' AND tipo_examen NOT LIKE '%rx%'";
    $laboratory = $getDetailedData(
        $conn, 'examenes_realizados', 'cobro', 'fecha_examen',
        $start_datetime, $end_datetime, 'tipo_pago', $lab_extra,
        ', nombre_paciente as paciente', ''
    );

    // 4. Procedures
    $procedures = $getDetailedData(
        $conn, 'procedimientos_menores', 'cobro', 'fecha_procedimiento',
        $start_datetime, $end_datetime, 'tipo_pago', '',
        ', nombre_paciente as paciente', ''
    );

    // 5. Ultrasound
    $us_new = $getDetailedData(
        $conn, 'ultrasonidos', 'cobro', 'fecha_ultrasonido',
        $start_datetime, $end_datetime, 'tipo_pago', '',
        ', nombre_paciente as paciente', ''
    );
    $us_old = $getDetailedData(
        $conn, 'examenes_realizados', 'cobro', 'fecha_examen',
        $start_datetime, $end_datetime, 'tipo_pago', "tipo_examen LIKE '%ultrasonido%'",
        ', nombre_paciente as paciente', ''
    );
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
    $rx_new = $getDetailedData(
        $conn, 'rayos_x', 'cobro', 'fecha_estudio',
        $start_datetime, $end_datetime, 'tipo_pago', '',
        ', nombre_paciente as paciente', ''
    );
    $rx_old = $getDetailedData(
        $conn, 'examenes_realizados', 'cobro', 'fecha_examen',
        $start_datetime, $end_datetime, 'tipo_pago', "(tipo_examen LIKE '%rayos x%' OR tipo_examen LIKE '%rx%')",
        ', nombre_paciente as paciente', ''
    );
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
        $conn, 'electrocardiogramas', 'precio', 'fecha_realizado',
        $start_datetime, $end_datetime, 'tipo_pago', '',
        ', p.id_paciente, CONCAT(p.nombre, " ", p.apellido) as paciente',
        'JOIN pacientes p ON electrocardiogramas.id_paciente = p.id_paciente'
    );

    // 8. Hospitalization (abonos)
    $hospitalization = $getDetailedData(
        $conn, 'abonos_hospitalarios', 'monto', 'fecha_abono',
        $start_datetime, $end_datetime, 'metodo_pago', '',
        ', abonos_hospitalarios.id_abono as extra_id',
        ''
    );

    $grand_total = $pharmacy['total'] + $consultations['total'] + $laboratory['total']
        + $procedures['total'] + $ultrasound['total'] + $xray['total']
        + $electro['total'] + $hospitalization['total'];

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
    error_log('Error en php/dashboard/get_shift_cut_data.php: ' . $e->getMessage() . ' en línea ' . $e->getLine());
    echo json_encode(['success' => false, 'error' => 'Error del servidor.']);
}