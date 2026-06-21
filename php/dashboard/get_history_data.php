<?php
// get_history_data.php
// Returns JSON data for the Historial del Turno modal:
// All recent cobros from the 7 Acciones Rápidas categories, grouped by category,
// with all metadata needed to display and reprint each one.
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

    // Shift window
    if ($shift === 'morning') {
        $start_datetime = $date . ' 08:00:00';
        $end_datetime = $date . ' 17:00:00';
    } else {
        $start_datetime = $date . ' 17:00:00';
        $end_datetime = date('Y-m-d', strtotime($date . ' +1 day')) . ' 07:59:59';
    }

    $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);
    $total_all = 0;

    /**
     * Run a SELECT for a single source table.
     * Returns array of rows with normalized fields:
     *   - id         : the source PK
     *   - fuente     : the 'fuente' key (cobro, venta, examen, etc.)
     *   - hora       : HH:MM:SS
     *   - fecha_full : YYYY-MM-DD HH:MM:SS
     *   - paciente   : patient name (or NULL)
     *   - doctor     : doctor name (or NULL)
     *   - detalle    : description (tipo_consulta, tipo_examen, etc.)
     *   - tipo_pago  : payment method
     *   - monto      : amount
     *   - print_url  : relative URL to the print file with ?id= pre-filled
     */
    $fetchRows = function ($conn, $sql, $params, $fuente) use ($id_hospital) {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $hora = '';
            $fecha_full = '';
            if (!empty($r['fecha_full'])) {
                $ts = strtotime($r['fecha_full']);
                if ($ts) {
                    $fecha_full = date('Y-m-d H:i:s', $ts);
                    $hora = date('H:i:s', $ts);
                }
            }
            $out[] = [
                'id' => (int) $r['id'],
                'fuente' => $fuente,
                'hora' => $hora,
                'fecha_full' => $fecha_full,
                'paciente' => $r['paciente'] ?? null,
                'doctor' => $r['doctor'] ?? null,
                'detalle' => $r['detalle'] ?? '',
                'tipo_pago' => $r['tipo_pago'] ?? 'Efectivo',
                'monto' => (float) $r['monto'],
                'print_url' => $r['print_url'] ?? null,
            ];
        }
        return $out;
    };

    $printMap = [
        'cobro'         => '../billing/print_billing.php?id=',
        'venta'         => '../dispensary/print_receipt.php?id=',
        'examen'        => '../laboratory/print_lab_receipt.php?id=',
        'procedimiento' => 'print_procedure_receipt.php?id=',
        'ultrasonido'   => '../ultrasonidos/print_us_receipt.php?id=',
        'rayos_x'       => '../rayos_x/print_rx_receipt.php?id=',
        'electro'       => '../billing/print_electro.php?id=',
    ];

    // 1. Consultations (cobros)
    $sql_cobros = "SELECT c.in_cobro AS id,
                          c.cantidad_consulta AS monto,
                          c.tipo_pago,
                          c.tipo_consulta AS detalle,
                          c.fecha_consulta AS fecha_full,
                          CONCAT(p.nombre, ' ', p.apellido) AS paciente,
                          CONCAT(u.nombre, ' ', u.apellido) AS doctor
                   FROM cobros c
                   JOIN pacientes p ON c.paciente_cobro = p.id_paciente
                   LEFT JOIN usuarios u ON c.id_doctor = u.idUsuario
                   WHERE c.fecha_consulta BETWEEN ? AND ? AND c.id_hospital = ?
                   ORDER BY c.fecha_consulta ASC";
    $consultations = $fetchRows($conn, $sql_cobros, [$start_datetime, $end_datetime, $id_hospital], 'cobro');
    foreach ($consultations as &$r) { $r['print_url'] = $printMap['cobro'] . $r['id']; }
    unset($r);

    // 2. Pharmacy Sales (ventas)
    $sql_ventas = "SELECT v.id_venta AS id,
                          v.total AS monto,
                          v.tipo_pago,
                          v.nombre_cliente AS paciente,
                          v.fecha_venta AS fecha_full,
                          CONCAT(u.nombre, ' ', u.apellido) AS doctor,
                          'Medicamento' AS detalle
                   FROM ventas v
                   LEFT JOIN usuarios u ON v.id_usuario = u.idUsuario
                   WHERE v.fecha_venta BETWEEN ? AND ? AND v.id_hospital = ?
                   ORDER BY v.fecha_venta ASC";
    $pharmacy = $fetchRows($conn, $sql_ventas, [$start_datetime, $end_datetime, $id_hospital], 'venta');
    foreach ($pharmacy as &$r) { $r['print_url'] = $printMap['venta'] . $r['id']; }
    unset($r);

    // 3. Laboratory (examenes_realizados, excluding US and RX)
    // Note: examenes_realizados stores the operator as plain text in `usuario` (no FK to usuarios).
    // We do a fuzzy match on name (full or first word) and fall back to the stored text.
    $lab_extra = "AND tipo_examen NOT LIKE '%ultrasonido%' AND tipo_examen NOT LIKE '%rayos x%' AND tipo_examen NOT LIKE '%rx%'";
    $sql_lab = "SELECT e.id_examen_realizado AS id,
                       e.cobro AS monto,
                       e.tipo_pago,
                       e.tipo_examen AS detalle,
                       e.nombre_paciente AS paciente,
                       e.fecha_examen AS fecha_full,
                       COALESCE(NULLIF(CONCAT(u.nombre, ' ', u.apellido), ''), e.usuario) AS doctor
                FROM examenes_realizados e
                LEFT JOIN usuarios u ON (
                    LOWER(CONCAT(u.nombre, ' ', u.apellido)) = LOWER(e.usuario)
                    OR LOWER(u.nombre) = LOWER(SUBSTRING_INDEX(e.usuario, ' ', 1))
                )
                WHERE e.fecha_examen BETWEEN ? AND ? AND e.id_hospital = ? $lab_extra
                ORDER BY e.fecha_examen ASC";
    $laboratory = $fetchRows($conn, $sql_lab, [$start_datetime, $end_datetime, $id_hospital], 'examen');
    foreach ($laboratory as &$r) { $r['print_url'] = $printMap['examen'] . $r['id']; }
    unset($r);

    // 4. Procedures
    // Same as lab: `usuario` is plain text, fuzzy match with fallback.
    $sql_proc = "SELECT pm.id_procedimiento AS id,
                        pm.cobro AS monto,
                        pm.tipo_pago,
                        pm.procedimiento AS detalle,
                        pm.nombre_paciente AS paciente,
                        pm.fecha_procedimiento AS fecha_full,
                        COALESCE(NULLIF(CONCAT(u.nombre, ' ', u.apellido), ''), pm.usuario) AS doctor
                 FROM procedimientos_menores pm
                 LEFT JOIN usuarios u ON (
                    LOWER(CONCAT(u.nombre, ' ', u.apellido)) = LOWER(pm.usuario)
                    OR LOWER(u.nombre) = LOWER(SUBSTRING_INDEX(pm.usuario, ' ', 1))
                 )
                 WHERE pm.fecha_procedimiento BETWEEN ? AND ? AND pm.id_hospital = ?
                 ORDER BY pm.fecha_procedimiento ASC";
    $procedures = $fetchRows($conn, $sql_proc, [$start_datetime, $end_datetime, $id_hospital], 'procedimiento');
    foreach ($procedures as &$r) { $r['print_url'] = $printMap['procedimiento'] . $r['id']; }
    unset($r);

    // 5. Ultrasound (combine new ultrasonidos + old examenes_realizados rows)
    $sql_us_new = "SELECT us.id_ultrasonido AS id,
                          us.cobro AS monto,
                          us.tipo_pago,
                          us.tipo_ultrasonido AS detalle,
                          us.nombre_paciente AS paciente,
                          us.fecha_ultrasonido AS fecha_full,
                          COALESCE(NULLIF(CONCAT(u.nombre, ' ', u.apellido), ''), us.usuario) AS doctor
                   FROM ultrasonidos us
                   LEFT JOIN usuarios u ON (
                       LOWER(CONCAT(u.nombre, ' ', u.apellido)) = LOWER(us.usuario)
                       OR LOWER(u.nombre) = LOWER(SUBSTRING_INDEX(us.usuario, ' ', 1))
                   )
                   WHERE us.fecha_ultrasonido BETWEEN ? AND ? AND us.id_hospital = ?
                   ORDER BY us.fecha_ultrasonido ASC";
    $us_new = $fetchRows($conn, $sql_us_new, [$start_datetime, $end_datetime, $id_hospital], 'ultrasonido');
    foreach ($us_new as &$r) { $r['print_url'] = $printMap['ultrasonido'] . $r['id']; }
    unset($r);

    $us_extra = "AND (tipo_examen LIKE '%ultrasonido%')";
    $sql_us_old = "SELECT e.id_examen_realizado AS id,
                          e.cobro AS monto,
                          e.tipo_pago,
                          e.tipo_examen AS detalle,
                          e.nombre_paciente AS paciente,
                          e.fecha_examen AS fecha_full,
                          COALESCE(NULLIF(CONCAT(u.nombre, ' ', u.apellido), ''), e.usuario) AS doctor
                   FROM examenes_realizados e
                   LEFT JOIN usuarios u ON (
                       LOWER(CONCAT(u.nombre, ' ', u.apellido)) = LOWER(e.usuario)
                       OR LOWER(u.nombre) = LOWER(SUBSTRING_INDEX(e.usuario, ' ', 1))
                   )
                   WHERE e.fecha_examen BETWEEN ? AND ? AND e.id_hospital = ? $us_extra
                   ORDER BY e.fecha_examen ASC";
    $us_old = $fetchRows($conn, $sql_us_old, [$start_datetime, $end_datetime, $id_hospital], 'ultrasonido');
    foreach ($us_old as &$r) { $r['print_url'] = $printMap['ultrasonido'] . $r['id']; }
    unset($r);
    $ultrasound = array_merge($us_new, $us_old);
    usort($ultrasound, fn($a, $b) => strcmp($a['fecha_full'], $b['fecha_full']));

    // 6. X-Rays
    $sql_rx_new = "SELECT rx.id_rayos_x AS id,
                          rx.cobro AS monto,
                          rx.tipo_pago,
                          rx.tipo_estudio AS detalle,
                          rx.nombre_paciente AS paciente,
                          rx.fecha_estudio AS fecha_full,
                          COALESCE(NULLIF(CONCAT(u.nombre, ' ', u.apellido), ''), rx.usuario) AS doctor
                   FROM rayos_x rx
                   LEFT JOIN usuarios u ON (
                       LOWER(CONCAT(u.nombre, ' ', u.apellido)) = LOWER(rx.usuario)
                       OR LOWER(u.nombre) = LOWER(SUBSTRING_INDEX(rx.usuario, ' ', 1))
                   )
                   WHERE rx.fecha_estudio BETWEEN ? AND ? AND rx.id_hospital = ?
                   ORDER BY rx.fecha_estudio ASC";
    $rx_new = $fetchRows($conn, $sql_rx_new, [$start_datetime, $end_datetime, $id_hospital], 'rayos_x');
    foreach ($rx_new as &$r) { $r['print_url'] = $printMap['rayos_x'] . $r['id']; }
    unset($r);

    $rx_extra = "AND (tipo_examen LIKE '%rayos x%' OR tipo_examen LIKE '%rx%')";
    $sql_rx_old = "SELECT e.id_examen_realizado AS id,
                          e.cobro AS monto,
                          e.tipo_pago,
                          e.tipo_examen AS detalle,
                          e.nombre_paciente AS paciente,
                          e.fecha_examen AS fecha_full,
                          COALESCE(NULLIF(CONCAT(u.nombre, ' ', u.apellido), ''), e.usuario) AS doctor
                   FROM examenes_realizados e
                   LEFT JOIN usuarios u ON (
                       LOWER(CONCAT(u.nombre, ' ', u.apellido)) = LOWER(e.usuario)
                       OR LOWER(u.nombre) = LOWER(SUBSTRING_INDEX(e.usuario, ' ', 1))
                   )
                   WHERE e.fecha_examen BETWEEN ? AND ? AND e.id_hospital = ? $rx_extra
                   ORDER BY e.fecha_examen ASC";
    $rx_old = $fetchRows($conn, $sql_rx_old, [$start_datetime, $end_datetime, $id_hospital], 'rayos_x');
    foreach ($rx_old as &$r) { $r['print_url'] = $printMap['rayos_x'] . $r['id']; }
    unset($r);
    $xray = array_merge($rx_new, $rx_old);
    usort($xray, fn($a, $b) => strcmp($a['fecha_full'], $b['fecha_full']));

    // 7. Electrocardiograms
    $sql_electro = "SELECT ec.id_electro AS id,
                           ec.precio AS monto,
                           ec.tipo_pago,
                           'Electrocardiograma' AS detalle,
                           CONCAT(p.nombre, ' ', p.apellido) AS paciente,
                           ec.fecha_realizado AS fecha_full,
                           CONCAT(u.nombre, ' ', u.apellido) AS doctor
                    FROM electrocardiogramas ec
                    JOIN pacientes p ON ec.id_paciente = p.id_paciente
                    LEFT JOIN usuarios u ON ec.id_doctor = u.idUsuario
                    WHERE ec.fecha_realizado BETWEEN ? AND ? AND ec.id_hospital = ?
                    ORDER BY ec.fecha_realizado ASC";
    $electro = $fetchRows($conn, $sql_electro, [$start_datetime, $end_datetime, $id_hospital], 'electro');
    foreach ($electro as &$r) { $r['print_url'] = $printMap['electro'] . $r['id']; }
    unset($r);

    // Compute totals per category
    $sum = fn($arr) => array_sum(array_column($arr, 'monto'));
    $categories = [
        'consultations' => $consultations,
        'pharmacy'      => $pharmacy,
        'laboratory'    => $laboratory,
        'ultrasound'    => $ultrasound,
        'xray'          => $xray,
        'electro'       => $electro,
        'procedures'    => $procedures,
    ];
    $totals = [];
    $grand_total = 0;
    foreach ($categories as $key => $rows) {
        $tot = $sum($rows);
        $totals[$key] = $tot;
        $grand_total += $tot;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'consultations' => $consultations,
            'pharmacy'      => $pharmacy,
            'laboratory'    => $laboratory,
            'ultrasound'    => $ultrasound,
            'xray'          => $xray,
            'electro'       => $electro,
            'procedures'    => $procedures,
            'totals'        => $totals,
            'grand_total'   => $grand_total,
            'count'         => array_sum(array_map('count', $categories)),
            'period'        => ['start' => $start_datetime, 'end' => $end_datetime, 'shift' => $shift]
        ]
    ]);

} catch (Exception $e) {
    error_log('Error en php/dashboard/get_history_data.php: ' . $e->getMessage() . ' en línea ' . $e->getLine());
    echo json_encode(['success' => false, 'error' => 'Error del servidor.']);
}
