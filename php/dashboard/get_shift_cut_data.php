<?php
// get_shift_cut_data.php
// Returns JSON data for the Shift Cut report with both totals and detailed transaction rows
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (($_SESSION['tipoUsuario'] ?? '') !== 'admin') {
    echo json_encode(['error' => 'Acceso denegado']);
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

    // 1. Pharmacy Sales (ventas) — item-level detail (1 row per line item)
    $pharmacy_breakdown = ['Efectivo' => 0, 'Tarjeta' => 0, 'Transferencia' => 0];
    $pharmacy_total = 0;
    foreach ($methods as $method) {
        $sql = "SELECT COALESCE(SUM(dv.cantidad_vendida * dv.precio_unitario), 0)
                FROM detalle_ventas dv
                JOIN ventas v ON v.id_venta = dv.id_venta
                WHERE v.tipo_pago = ?
                  AND v.fecha_venta BETWEEN ? AND ?
                  AND v.id_hospital = ?
                  AND dv.precio_unitario > 0";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$method, $start_datetime, $end_datetime, $id_hospital]);
        $pharmacy_breakdown[$method] = (float)($stmt->fetchColumn() ?: 0);
        $pharmacy_total += $pharmacy_breakdown[$method];
    }

    $pharmacy_rows_sql = "SELECT v.fecha_venta AS hora,
                                 (dv.cantidad_vendida * dv.precio_unitario) AS monto,
                                 v.tipo_pago,
                                 v.nombre_cliente AS cliente,
                                 i.nom_medicamento,
                                 i.presentacion_med,
                                 i.mol_medicamento,
                                 dv.cantidad_vendida AS cantidad,
                                 dv.precio_unitario AS precio_unitario,
                                 dv.id_detalle,
                                 dv.id_inventario
                          FROM ventas v
                          JOIN detalle_ventas dv ON v.id_venta = dv.id_venta
                          JOIN inventario i ON dv.id_inventario = i.id_inventario
                          WHERE v.fecha_venta BETWEEN ? AND ?
                            AND v.id_hospital = ?
                            AND dv.precio_unitario > 0
                          ORDER BY v.fecha_venta ASC";
    $stmt = $conn->prepare($pharmacy_rows_sql);
    $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        if (!empty($r['hora'])) {
            $r['hora'] = date('H:i', strtotime($r['hora']));
        }
    }
    $pharmacy = ['breakdown' => $pharmacy_breakdown, 'total' => $pharmacy_total, 'details' => $rows];

    // 2. Consultations (cobros) — needs JOIN pacientes for nombre del paciente
    $cons_breakdown = ['Efectivo' => 0, 'Tarjeta' => 0, 'Transferencia' => 0];
    $cons_total = 0;
    foreach ($methods as $method) {
        $sql = "SELECT SUM(c.cantidad_consulta) FROM cobros c
                WHERE c.tipo_pago = ?
                  AND c.fecha_consulta BETWEEN ? AND ?
                  AND c.id_hospital = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$method, $start_datetime, $end_datetime, $id_hospital]);
        $cons_breakdown[$method] = (float)($stmt->fetchColumn() ?: 0);
        $cons_total += $cons_breakdown[$method];
    }
    $cons_rows_sql = "SELECT c.fecha_consulta AS hora,
                             c.cantidad_consulta AS monto,
                             c.tipo_pago,
                             c.tipo_consulta AS detalle,
                             CONCAT(u.nombre, ' ', u.apellido) AS medico,
                             CONCAT(p.nombre, ' ', p.apellido) AS paciente,
                             COALESCE(
                                 CASE WHEN WEEKDAY(c.fecha_consulta) >= 5 OR TIME(c.fecha_consulta) >= '18:00:00'
                                      THEN t.costo_inhabil ELSE t.costo_normal END,
                                 0
                             ) AS costo
                      FROM cobros c
                      JOIN pacientes p ON c.paciente_cobro = p.id_paciente
                      LEFT JOIN usuarios u ON c.id_doctor = u.idUsuario
                      LEFT JOIN tarifas_servicios t
                        ON t.id_hospital = c.id_hospital
                       AND t.tipo_servicio = LOWER(c.tipo_consulta)
                       AND t.id_medico <=> c.id_doctor
                      WHERE c.fecha_consulta BETWEEN ? AND ?
                        AND c.id_hospital = ?
                      ORDER BY c.fecha_consulta ASC";
    $stmt = $conn->prepare($cons_rows_sql);
    $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        if (!empty($r['hora'])) {
            $r['hora'] = date('H:i', strtotime($r['hora']));
        }
        // null costo (admin didn't set one) → treat as 0 for the shift sum; renderer will show "N/D"
        if ($r['costo'] === null) $r['costo'] = 0;
    }
    $consultations = ['breakdown' => $cons_breakdown, 'total' => $cons_total, 'details' => $rows];
    $consultations['cost'] = array_sum(array_column($rows, 'costo'));
    $consultations['has_cost_data'] = (bool) array_filter($rows, fn($r) => (float)$r['costo'] > 0);

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

    // 4. Procedures (procedimientos_menores) — JOIN tarifas_servicios for cost
    $proc_sql = "SELECT pm.id_procedimiento, pm.cobro AS monto, pm.tipo_pago, pm.procedimiento AS detalle,
                       pm.nombre_paciente AS paciente, pm.fecha_procedimiento AS fecha_full,
                       COALESCE(
                           CASE WHEN WEEKDAY(pm.fecha_procedimiento) >= 5 OR TIME(pm.fecha_procedimiento) >= '18:00:00'
                                THEN t.costo_inhabil ELSE t.costo_normal END,
                           0
                       ) AS costo
                FROM procedimientos_menores pm
                LEFT JOIN tarifas_servicios t
                  ON t.id_hospital = pm.id_hospital
                 AND t.tipo_servicio = 'procedimiento'
                 AND t.nombre_servicio = pm.procedimiento
                WHERE pm.fecha_procedimiento BETWEEN ? AND ?
                  AND pm.id_hospital = ?
                ORDER BY pm.fecha_procedimiento ASC";
    $stmt = $conn->prepare($proc_sql);
    $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
    $proc_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($proc_rows as &$r) {
        $r['hora'] = date('H:i', strtotime($r['fecha_full']));
        if ($r['costo'] === null) $r['costo'] = 0;
    }
    unset($r);
    $procedures = [
        'breakdown' => $cons_breakdown, // reuse (same method values)
        'total'     => array_sum(array_column($proc_rows, 'monto')),
        'details'   => $proc_rows,
        'cost'      => array_sum(array_column($proc_rows, 'costo')),
    ];
    // Re-compute procedure breakdown by método
    $procedures['breakdown'] = ['Efectivo' => 0, 'Tarjeta' => 0, 'Transferencia' => 0];
    foreach ($proc_rows as $r) {
        $mpago = $r['tipo_pago'] ?? 'Efectivo';
        if (isset($procedures['breakdown'][$mpago])) $procedures['breakdown'][$mpago] += (float)$r['monto'];
    }

    // 5. Ultrasound (combine new ultrasonidos + old examenes_realizados rows)
    $us_sql_new = "SELECT us.id_ultrasonido, us.cobro AS monto, us.tipo_pago,
                          us.tipo_ultrasonido AS detalle, us.nombre_paciente AS paciente,
                          us.fecha_ultrasonido AS fecha_full,
                          COALESCE(
                              CASE WHEN WEEKDAY(us.fecha_ultrasonido) >= 5 OR TIME(us.fecha_ultrasonido) >= '18:00:00'
                                   THEN t.costo_inhabil ELSE t.costo_normal END,
                              0
                          ) AS costo
                   FROM ultrasonidos us
                   LEFT JOIN tarifas_servicios t
                     ON t.id_hospital = us.id_hospital
                    AND t.tipo_servicio = 'ultrasonido'
                    AND t.nombre_servicio <=> us.tipo_ultrasonido
                   WHERE us.fecha_ultrasonido BETWEEN ? AND ?
                     AND us.id_hospital = ?
                   ORDER BY us.fecha_ultrasonido ASC";
    $stmt = $conn->prepare($us_sql_new);
    $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
    $us_new_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($us_new_rows as &$r) {
        $r['hora'] = date('H:i', strtotime($r['fecha_full']));
        if ($r['costo'] === null) $r['costo'] = 0;
    }
    unset($r);

    $us_sql_old = "SELECT e.id_examen_realizado, e.cobro AS monto, e.tipo_pago,
                          e.tipo_examen AS detalle, e.nombre_paciente AS paciente,
                          e.fecha_examen AS fecha_full,
                          COALESCE(
                              CASE WHEN WEEKDAY(e.fecha_examen) >= 5 OR TIME(e.fecha_examen) >= '18:00:00'
                                   THEN t.costo_inhabil ELSE t.costo_normal END,
                              0
                          ) AS costo
                   FROM examenes_realizados e
                   LEFT JOIN tarifas_servicios t
                     ON t.id_hospital = e.id_hospital
                    AND t.tipo_servicio = 'ultrasonido'
                    AND t.nombre_servicio <=> e.tipo_examen
                   WHERE e.fecha_examen BETWEEN ? AND ?
                     AND e.id_hospital = ?
                     AND e.tipo_examen LIKE '%ultrasonido%'
                   ORDER BY e.fecha_examen ASC";
    $stmt = $conn->prepare($us_sql_old);
    $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
    $us_old_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($us_old_rows as &$r) {
        $r['hora'] = date('H:i', strtotime($r['fecha_full']));
        if ($r['costo'] === null) $r['costo'] = 0;
    }
    unset($r);
    $all_us_rows = array_merge($us_new_rows, $us_old_rows);
    usort($all_us_rows, fn($a, $b) => strcmp($a['fecha_full'], $b['fecha_full']));
    $ultrasound = [
        'total' => array_sum(array_column($all_us_rows, 'monto')),
        'breakdown' => ['Efectivo' => 0, 'Tarjeta' => 0, 'Transferencia' => 0],
        'details' => $all_us_rows,
        'cost' => array_sum(array_column($all_us_rows, 'costo')),
    ];
    foreach ($all_us_rows as $r) {
        $mpago = $r['tipo_pago'] ?? 'Efectivo';
        if (isset($ultrasound['breakdown'][$mpago])) $ultrasound['breakdown'][$mpago] += (float)$r['monto'];
    }

    // 6. X-Rays
    $rx_sql_new = "SELECT rx.id_rayos_x, rx.cobro AS monto, rx.tipo_pago,
                          rx.tipo_estudio AS detalle, rx.nombre_paciente AS paciente,
                          rx.fecha_estudio AS fecha_full,
                          COALESCE(
                              CASE WHEN WEEKDAY(rx.fecha_estudio) >= 5 OR TIME(rx.fecha_estudio) >= '18:00:00'
                                   THEN t.costo_inhabil ELSE t.costo_normal END,
                              0
                          ) AS costo
                   FROM rayos_x rx
                   LEFT JOIN tarifas_servicios t
                     ON t.id_hospital = rx.id_hospital
                    AND t.tipo_servicio = 'rayos_x'
                    AND t.nombre_servicio <=> rx.tipo_estudio
                   WHERE rx.fecha_estudio BETWEEN ? AND ?
                     AND rx.id_hospital = ?
                   ORDER BY rx.fecha_estudio ASC";
    $stmt = $conn->prepare($rx_sql_new);
    $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
    $rx_new_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rx_new_rows as &$r) {
        $r['hora'] = date('H:i', strtotime($r['fecha_full']));
        if ($r['costo'] === null) $r['costo'] = 0;
    }
    unset($r);

    $rx_sql_old = "SELECT e.id_examen_realizado, e.cobro AS monto, e.tipo_pago,
                          e.tipo_examen AS detalle, e.nombre_paciente AS paciente,
                          e.fecha_examen AS fecha_full,
                          COALESCE(
                              CASE WHEN WEEKDAY(e.fecha_examen) >= 5 OR TIME(e.fecha_examen) >= '18:00:00'
                                   THEN t.costo_inhabil ELSE t.costo_normal END,
                              0
                          ) AS costo
                   FROM examenes_realizados e
                   LEFT JOIN tarifas_servicios t
                     ON t.id_hospital = e.id_hospital
                    AND t.tipo_servicio = 'rayos_x'
                    AND t.nombre_servicio <=> e.tipo_examen
                   WHERE e.fecha_examen BETWEEN ? AND ?
                     AND e.id_hospital = ?
                     AND (e.tipo_examen LIKE '%rayos x%' OR e.tipo_examen LIKE '%rx%')
                   ORDER BY e.fecha_examen ASC";
    $stmt = $conn->prepare($rx_sql_old);
    $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
    $rx_old_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rx_old_rows as &$r) {
        $r['hora'] = date('H:i', strtotime($r['fecha_full']));
        if ($r['costo'] === null) $r['costo'] = 0;
    }
    unset($r);
    $all_rx_rows = array_merge($rx_new_rows, $rx_old_rows);
    usort($all_rx_rows, fn($a, $b) => strcmp($a['fecha_full'], $b['fecha_full']));
    $xray = [
        'total' => array_sum(array_column($all_rx_rows, 'monto')),
        'breakdown' => ['Efectivo' => 0, 'Tarjeta' => 0, 'Transferencia' => 0],
        'details' => $all_rx_rows,
        'cost' => array_sum(array_column($all_rx_rows, 'costo')),
    ];
    foreach ($all_rx_rows as $r) {
        $mpago = $r['tipo_pago'] ?? 'Efectivo';
        if (isset($xray['breakdown'][$mpago])) $xray['breakdown'][$mpago] += (float)$r['monto'];
    }

    // 7. Electrocardiograms
    $elec_sql = "SELECT ec.id_electro, ec.precio AS monto, ec.tipo_pago,
                       'Electrocardiograma' AS detalle,
                       CONCAT(p.nombre, ' ', p.apellido) AS paciente,
                       ec.fecha_realizado AS fecha_full,
                       ec.id_paciente,
                       COALESCE(
                           CASE WHEN WEEKDAY(ec.fecha_realizado) >= 5 OR TIME(ec.fecha_realizado) >= '18:00:00'
                                THEN t.costo_inhabil ELSE t.costo_normal END,
                           0
                       ) AS costo
                FROM electrocardiogramas ec
                JOIN pacientes p ON ec.id_paciente = p.id_paciente
                LEFT JOIN tarifas_servicios t
                  ON t.id_hospital = ec.id_hospital
                 AND t.tipo_servicio = 'electrocardiograma'
                WHERE ec.fecha_realizado BETWEEN ? AND ?
                  AND ec.id_hospital = ?
                ORDER BY ec.fecha_realizado ASC";
    $stmt = $conn->prepare($elec_sql);
    $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
    $elec_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($elec_rows as &$r) {
        $r['hora'] = date('H:i', strtotime($r['fecha_full']));
        if ($r['costo'] === null) $r['costo'] = 0;
    }
    unset($r);
    $electro = [
        'breakdown' => ['Efectivo' => 0, 'Tarjeta' => 0, 'Transferencia' => 0],
        'total' => array_sum(array_column($elec_rows, 'monto')),
        'details' => $elec_rows,
        'cost' => array_sum(array_column($elec_rows, 'costo')),
    ];
    foreach ($elec_rows as $r) {
        $mpago = $r['tipo_pago'] ?? 'Efectivo';
        if (isset($electro['breakdown'][$mpago])) $electro['breakdown'][$mpago] += (float)$r['monto'];
    }

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

// Sum costs across the 5 categories that source from tarifas_servicios
// (pharmacy uses inventario, lab has no cost source; both default to 0 here)
$grand_cost = (float)($consultations['cost'] ?? 0)
           + (float)($procedures['cost'] ?? 0)
           + (float)($ultrasound['cost'] ?? 0)
           + (float)($xray['cost'] ?? 0)
           + (float)($electro['cost'] ?? 0);
$grand_profit = $grand_total - $grand_cost;

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
        'grand_cost'  => $grand_cost,
        'grand_profit'=> $grand_profit,
        'period' => ['start' => $start_datetime, 'end' => $end_datetime, 'shift' => $shift]
    ]
]);

} catch (Exception $e) {
    error_log('Error en php/dashboard/get_shift_cut_data.php: ' . $e->getMessage() . ' en línea ' . $e->getLine());
    echo json_encode(['success' => false, 'error' => 'Error del servidor.']);
}