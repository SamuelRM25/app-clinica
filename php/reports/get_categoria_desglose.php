<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Sesión no iniciada']);
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/module_guard.php';

$database = new Database();
$conn = $database->getConnection();
$conn->exec("SET time_zone = '-06:00'");

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);
$categoria   = $_GET['categoria'] ?? '';
$start       = $_GET['start'] ?? '';
$end         = $_GET['end'] ?? '';

if (!$categoria || !$start || !$end) {
    echo json_encode(['error' => 'Parámetros incompletos (categoria, start, end requeridos)', 'rows' => [], 'total_monto' => 0, 'total_costo' => 0, 'total_profit' => 0]);
    exit;
}

$queries = [];

$queries['farmacia'] = [
    'sql' => "SELECT
                DATE(v.fecha_venta) AS fecha,
                COALESCE(v.nombre_cliente, '—') AS paciente,
                CONCAT(dv.cantidad_vendida, 'x ', i.nom_medicamento) AS descripcion,
                (dv.cantidad_vendida * dv.precio_unitario) AS monto,
                (dv.cantidad_vendida * COALESCE(pi.unit_cost, 0)) AS costo
              FROM ventas v
              JOIN detalle_ventas dv ON v.id_venta = dv.id_venta
              JOIN inventario i ON dv.id_inventario = i.id_inventario
              LEFT JOIN purchase_items pi ON i.id_purchase_item = pi.id
              WHERE v.fecha_venta BETWEEN ? AND ?
                AND v.id_hospital = ?
                AND v.tipo_pago != 'Traslado'
                AND dv.precio_unitario > 0
              ORDER BY v.fecha_venta DESC",
    'params' => [$start, $end, $id_hospital],
];

$queries['consultas'] = [
    'sql' => "SELECT
                DATE(c.fecha_consulta) AS fecha,
                CONCAT(p.nombre, ' ', p.apellido) AS paciente,
                c.tipo_consulta AS descripcion,
                c.cantidad_consulta AS monto,
                COALESCE(
                    CASE WHEN WEEKDAY(c.fecha_consulta) >= 5 OR TIME(c.fecha_consulta) >= '18:00:00'
                         THEN t.costo_inhabil ELSE t.costo_normal END,
                    0
                ) AS costo
              FROM cobros c
              JOIN pacientes p ON c.paciente_cobro = p.id_paciente
              LEFT JOIN tarifas_servicios t
                ON t.id_hospital = c.id_hospital
               AND t.tipo_servicio = LOWER(c.tipo_consulta)
               AND t.id_medico <=> c.id_doctor
              WHERE c.fecha_consulta BETWEEN ? AND ?
                AND c.id_hospital = ?
              ORDER BY c.fecha_consulta DESC",
    'params' => [$start, $end, $id_hospital],
];

$queries['laboratorio'] = [
    'sql' => "SELECT
                DATE(er.fecha_examen) AS fecha,
                er.nombre_paciente AS paciente,
                er.tipo_examen AS descripcion,
                er.cobro AS monto,
                0 AS costo
              FROM examenes_realizados er
              WHERE er.fecha_examen BETWEEN ? AND ?
                AND er.id_hospital = ?
                AND (er.tipo_examen IS NULL OR (er.tipo_examen NOT LIKE '%ultrasonido%' AND er.tipo_examen NOT LIKE '%rayos x%' AND er.tipo_examen NOT LIKE '%rx%'))
              ORDER BY er.fecha_examen DESC",
    'params' => [$start, $end, $id_hospital],
];

$queries['ultrasonido'] = [
    'sql' => "SELECT
                DATE(us.fecha_ultrasonido) AS fecha,
                us.nombre_paciente AS paciente,
                us.tipo_ultrasonido AS descripcion,
                us.cobro AS monto,
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
              ORDER BY us.fecha_ultrasonido DESC",
    'params' => [$start, $end, $id_hospital],
];

$queries['rayos_x'] = [
    'sql' => "SELECT
                DATE(rx.fecha_estudio) AS fecha,
                rx.nombre_paciente AS paciente,
                rx.tipo_estudio AS descripcion,
                rx.cobro AS monto,
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
              ORDER BY rx.fecha_estudio DESC",
    'params' => [$start, $end, $id_hospital],
];

$queries['electro'] = [
    'sql' => "SELECT
                DATE(ec.fecha_realizado) AS fecha,
                COALESCE(CONCAT(p.nombre, ' ', p.apellido), '—') AS paciente,
                COALESCE(ec.observaciones, 'Electrocardiograma') AS descripcion,
                ec.precio AS monto,
                COALESCE(
                    CASE WHEN WEEKDAY(ec.fecha_realizado) >= 5 OR TIME(ec.fecha_realizado) >= '18:00:00'
                         THEN t.costo_inhabil ELSE t.costo_normal END,
                    0
                ) AS costo
              FROM electrocardiogramas ec
              LEFT JOIN pacientes p ON ec.id_paciente = p.id_paciente
              LEFT JOIN tarifas_servicios t
                ON t.id_hospital = ec.id_hospital
               AND t.tipo_servicio = 'electrocardiograma'
              WHERE ec.fecha_realizado BETWEEN ? AND ?
                AND ec.id_hospital = ?
              ORDER BY ec.fecha_realizado DESC",
    'params' => [$start, $end, $id_hospital],
];

$queries['procedimientos'] = [
    'sql' => "SELECT
                DATE(pm.fecha_procedimiento) AS fecha,
                pm.nombre_paciente AS paciente,
                pm.procedimiento AS descripcion,
                pm.cobro AS monto,
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
              ORDER BY pm.fecha_procedimiento DESC",
    'params' => [$start, $end, $id_hospital],
];

$queries['hospitalizacion'] = [
    'sql' => "SELECT
                DATE(ch.fecha_cargo) AS fecha,
                COALESCE(CONCAT(p.nombre, ' ', p.apellido), '—') AS paciente,
                CONCAT(ch.tipo_cargo, ': ', ch.descripcion) AS descripcion,
                ch.subtotal AS monto,
                CASE WHEN ch.tipo_cargo IN ('Medicamento','Insumo')
                          AND i.id_purchase_item IS NOT NULL
                     THEN ch.cantidad * COALESCE(pi.unit_cost, 0)
                     ELSE 0
                END AS costo
              FROM cargos_hospitalarios ch
              JOIN cuenta_hospitalaria cu ON ch.id_cuenta = cu.id_cuenta
              JOIN encamamientos e ON cu.id_encamamiento = e.id_encamamiento
              LEFT JOIN pacientes p ON e.id_paciente = p.id_paciente
              LEFT JOIN inventario i ON ch.referencia_id = i.id_inventario
                                    AND ch.referencia_tabla = 'inventario'
              LEFT JOIN purchase_items pi ON i.id_purchase_item = pi.id
              WHERE ch.cancelado = 0
                AND ch.fecha_cargo BETWEEN ? AND ?
                AND e.id_hospital = ?
              ORDER BY e.id_encamamiento DESC, ch.fecha_cargo ASC",
    'params' => [$start, $end, $id_hospital],
];

$queries['gastos_varios'] = [
    'sql' => "SELECT
                g.fecha AS fecha,
                CONCAT(u.nombre, ' ', u.apellido) AS paciente,
                g.descripcion AS descripcion,
                g.total AS monto,
                0 AS costo
              FROM gastos g
              LEFT JOIN usuarios u ON g.created_by = u.idUsuario
              WHERE DATE(g.fecha) BETWEEN DATE(?) AND DATE(?)
                AND g.id_hospital = ?
              ORDER BY g.fecha DESC",
    'params' => [$start, $end, $id_hospital],
];

$queries['pago_proveedores'] = [
    'sql' => "SELECT
                pp.payment_date AS fecha,
                ph.provider_name AS paciente,
                CONCAT(ph.document_type, ' ', COALESCE(ph.document_number, ''), ' (#', ph.id, ') — ', pp.payment_method) AS descripcion,
                pp.amount AS monto,
                0 AS costo
              FROM purchase_payments pp
              JOIN purchase_headers ph ON pp.purchase_header_id = ph.id
              WHERE pp.payment_date BETWEEN ? AND ?
                AND pp.id_hospital = ?
              ORDER BY pp.payment_date DESC, pp.created_at DESC",
    'params' => [$start, $end, $id_hospital],
];

if (!isset($queries[$categoria])) {
    echo json_encode(['error' => 'Categoría no válida: ' . $categoria, 'rows' => [], 'total_monto' => 0, 'total_costo' => 0, 'total_profit' => 0]);
    exit;
}

try {
    $q = $queries[$categoria];
    $stmt = $conn->prepare($q['sql']);
    $stmt->execute($q['params']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_monto = 0;
    $total_costo = 0;
    $has_costo = !in_array($categoria, ['laboratorio', 'gastos_varios', 'pago_proveedores']);

    foreach ($rows as &$row) {
        $row['monto']  = (float)($row['monto'] ?? 0);
        $row['costo']  = (float)($row['costo'] ?? 0);
        $row['profit'] = $row['monto'] - $row['costo'];
        $total_monto  += $row['monto'];
        $total_costo  += $row['costo'];
        $row['fecha']        = $row['fecha'] ?? '';
        $row['paciente']     = $row['paciente'] ?? '—';
        $row['descripcion']  = $row['descripcion'] ?? '—';
    }
    unset($row);

    $total_profit = $total_monto - $total_costo;

    echo json_encode([
        'rows'        => $rows,
        'total_monto' => $total_monto,
        'total_costo' => $total_costo,
        'total_profit'=> $total_profit,
        'categoria'   => $categoria,
        'has_costo'   => $has_costo,
    ]);
} catch (PDOException $e) {
    $msg = 'Error al consultar datos';
    if ($_SESSION['tipoUsuario'] === 'admin') {
        $msg .= ': ' . $e->getMessage();
    }
    error_log('get_categoria_desglose.php [' . $categoria . '] error: ' . $e->getMessage());
    echo json_encode(['error' => $msg, 'rows' => [], 'total_monto' => 0, 'total_costo' => 0, 'total_profit' => 0]);
}
