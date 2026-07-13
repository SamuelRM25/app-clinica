<?php
// index.php - Módulo de Reportes - Centro Médico Herrera Saenz
// Versión 4.0 - Integrado al Diseño del Dashboard Principal
session_start();

// Verificar sesión activa
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Incluir configuraciones y funciones
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';
require_once '../../includes/module_guard.php';
require_once '../../includes/breadcrumbs.php';

check_module_access('reports');

// Establecer zona horaria
date_default_timezone_set('America/Guatemala');
verify_session();

try {
    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener información del usuario
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['tipoUsuario'];
    $user_name = $_SESSION['nombre'];
    $user_specialty = $_SESSION['especialidad'] ?? 'Profesional Médico';
    $id_hospital = (int) ($_SESSION['id_hospital'] ?? 0);

    // Tipo de filtro: 'jornada' (por día, 24h) o 'mes' (mes completo)
    $filtro_tipo = $_GET['filtro_tipo'] ?? 'mes';
    $mes_filtro = $_GET['mes_filtro'] ?? date('Y-m');

    if ($filtro_tipo === 'mes') {
        $start_datetime = $mes_filtro . '-01 00:00:00';
        $end_datetime   = date('Y-m-t 23:59:59', strtotime($mes_filtro . '-01'));
        $fecha_filtro   = $mes_filtro . '-01';
    } else {
        $fecha_filtro = $_GET['fecha_filtro'] ?? date('Y-m-d');
        // Ajustar para rangos de jornada (08:00 AM del día seleccionado a 07:59 AM del día siguiente)
        $start_datetime = $fecha_filtro . ' 08:00:00';
        $end_datetime   = date('Y-m-d', strtotime($fecha_filtro . ' +1 day')) . ' 07:59:59';
    }

    // Variables para compatibilidad con lógica existente que use fecha_inicio/fin
    $fecha_inicio = $start_datetime;
    $fecha_fin = $end_datetime;

    // ============ CONSULTAS ESTADÍSTICAS PARA EL DASHBOARD ============

    // Configurar filtros según tipo de usuario
    $is_doctor = $user_type === 'doc';
    $doctor_filter = $is_doctor ? " AND id_doctor = ?" : "";
    $today = date('Y-m-d');

    // 1. Citas de hoy
    $params = $is_doctor ? [$today, $id_hospital, $user_id] : [$today, $id_hospital];
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM citas WHERE fecha_cita = ? AND id_hospital = ?" . $doctor_filter);
    $stmt->execute($params);
    $today_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 2. Total de citas en el sistema
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM citas WHERE id_hospital = ?");
    $stmt->execute([$id_hospital]);
    $total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 3. Hospitalizaciones Activas
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM encamamientos WHERE estado = 'Activo' AND id_hospital = ?");
    $stmt->execute([$id_hospital]);
    $active_hospitalizations = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 4. Compras pendientes
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventario WHERE estado = 'Pendiente' AND id_hospital = ?");
    $stmt->execute([$id_hospital]);
    $pending_purchases = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // ============ CÁLCULO DE MÉTRICAS DE REPORTES ============

    // 1. Ventas de medicamentos
    $stmt_sales = $conn->prepare("SELECT SUM(total) as total_sales FROM ventas WHERE fecha_venta BETWEEN ? AND ? AND id_hospital = ?");
    $stmt_sales->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_sales_meds = $stmt_sales->fetch(PDO::FETCH_ASSOC)['total_sales'] ?? 0;

    // 2. Compras de medicamentos — basado en PAGOS (cash accounting)
    $stmt_purchases = $conn->prepare("SELECT COALESCE(SUM(pp.amount), 0) as total_purchases FROM purchase_payments pp WHERE pp.payment_date BETWEEN ? AND ? AND pp.id_hospital = ?");
    $stmt_purchases->execute([$fecha_inicio, $fecha_fin, $id_hospital]);
    $total_purchases_meds = (float)($stmt_purchases->fetch(PDO::FETCH_ASSOC)['total_purchases'] ?? 0);

    // 2b. Gastos generales del hospital
    $stmt_gastos = $conn->prepare("SELECT COALESCE(SUM(total), 0) as total_gastos FROM gastos WHERE fecha BETWEEN ? AND ? AND id_hospital = ?");
    $stmt_gastos->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_gastos = (float)($stmt_gastos->fetch(PDO::FETCH_ASSOC)['total_gastos'] ?? 0);

    // 3. Cálculo de Ganancia Real — farmacia (detalle_ventas → inventario → purchase_items)
    $stmt_actual_profit = $conn->prepare("
        SELECT
            SUM(dv.cantidad_vendida * dv.precio_unitario) as revenue,
            SUM(dv.cantidad_vendida * COALESCE(pi.unit_cost, 0)) as cost
        FROM detalle_ventas dv
        JOIN ventas v ON dv.id_venta = v.id_venta
        JOIN inventario i ON dv.id_inventario = i.id_inventario
        LEFT JOIN purchase_items pi ON i.id_purchase_item = pi.id
        WHERE v.fecha_venta BETWEEN ? AND ?
        AND v.tipo_pago != 'Traslado'
        AND dv.precio_unitario > 0
        AND v.id_hospital = ?
    ");
    $stmt_actual_profit->execute([$start_datetime, $end_datetime, $id_hospital]);
    $profit_data = $stmt_actual_profit->fetch(PDO::FETCH_ASSOC);
    $sales_revenue = $profit_data['revenue'] ?? 0;
    $sales_cost = $profit_data['cost'] ?? 0;
    $actual_sales_margin = $sales_revenue - $sales_cost;

    // 3b. Cálculo de Ganancia por categoría tarifada (cobros, ultrasonidos, rayos_x, electrocardiogramas, procedimientos)
    // usando LEFT JOIN a tarifas_servicios con NULL-safe equality y regla hábil/inhábil.
    $category_profit = [];

    // Helper: build the same JOIN expression used for all 5 categories
    $tarifa_join_template = function ($alias, $date_col, $region_join = null) {
        $region_sql = $region_join ? "AND t.region_count = {$alias}.{$region_join}" : '';
        return "LEFT JOIN tarifas_servicios t
                  ON t.id_hospital = {$alias}.id_hospital
                 AND t.tipo_servicio = %s
                 {$region_sql}
                 AND t.id_medico <=> {$alias}.id_doctor";
    };

    $profit_queries = [
        // 1. cobros (consultas/reconsultas) — tipo_servicio from c.tipo_consulta
        'consultations' => [
            'sql' => "SELECT
                        SUM(c.cantidad_consulta) AS revenue,
                        SUM(COALESCE(
                            CASE WHEN WEEKDAY(c.fecha_consulta) >= 5 OR TIME(c.fecha_consulta) >= '18:00:00'
                                 THEN t.costo_inhabil ELSE t.costo_normal END,
                            0
                        )) AS cost
                       FROM cobros c
                       LEFT JOIN tarifas_servicios t
                         ON t.id_hospital = c.id_hospital
                        AND t.tipo_servicio = LOWER(c.tipo_consulta)
                        AND t.id_medico <=> c.id_doctor
                       WHERE c.fecha_consulta BETWEEN ? AND ?
                         AND c.id_hospital = ?",
            'params' => [$start_datetime, $end_datetime, $id_hospital],
        ],
        // 2. ultrasonidos — by tipo_ultrasonido (NULL-safe)
        'ultrasonidos' => [
            'sql' => "SELECT
                        SUM(us.cobro) AS revenue,
                        SUM(COALESCE(
                            CASE WHEN WEEKDAY(us.fecha_ultrasonido) >= 5 OR TIME(us.fecha_ultrasonido) >= '18:00:00'
                                 THEN t.costo_inhabil ELSE t.costo_normal END,
                            0
                        )) AS cost
                       FROM ultrasonidos us
                       LEFT JOIN tarifas_servicios t
                         ON t.id_hospital = us.id_hospital
                        AND t.tipo_servicio = 'ultrasonido'
                        AND t.nombre_servicio <=> us.tipo_ultrasonido
                       WHERE us.fecha_ultrasonido BETWEEN ? AND ?
                         AND us.id_hospital = ?",
            'params' => [$start_datetime, $end_datetime, $id_hospital],
        ],
        // 3. rayos_x — by tipo_estudio (NULL-safe, no region_count column in rayos_x)
        'rayos_x' => [
            'sql' => "SELECT
                        SUM(rx.cobro) AS revenue,
                        SUM(COALESCE(
                            CASE WHEN WEEKDAY(rx.fecha_estudio) >= 5 OR TIME(rx.fecha_estudio) >= '18:00:00'
                                 THEN (COALESCE(t.costo_digital_inhabil, 0) + COALESCE(t.costo_impreso_inhabil, 0)) / 2
                                 ELSE (COALESCE(t.costo_digital_normal, 0)  + COALESCE(t.costo_impreso_normal, 0))  / 2
                            END,
                            0
                        )) AS cost
                       FROM rayos_x rx
                       LEFT JOIN tarifas_servicios t
                         ON t.id_hospital = rx.id_hospital
                        AND t.tipo_servicio = 'rayos_x'
                        AND t.nombre_servicio <=> rx.tipo_estudio
                       WHERE rx.fecha_estudio BETWEEN ? AND ?
                         AND rx.id_hospital = ?",
            'params' => [$start_datetime, $end_datetime, $id_hospital],
        ],
        // 4. electrocardiogramas
        'electrocardiogramas' => [
            'sql' => "SELECT
                        SUM(ec.precio) AS revenue,
                        SUM(COALESCE(
                            CASE WHEN WEEKDAY(ec.fecha_realizado) >= 5 OR TIME(ec.fecha_realizado) >= '18:00:00'
                                 THEN t.costo_inhabil ELSE t.costo_normal END,
                            0
                        )) AS cost
                       FROM electrocardiogramas ec
                       LEFT JOIN tarifas_servicios t
                         ON t.id_hospital = ec.id_hospital
                        AND t.tipo_servicio = 'electrocardiograma'
                       WHERE ec.fecha_realizado BETWEEN ? AND ?
                         AND ec.id_hospital = ?",
            'params' => [$start_datetime, $end_datetime, $id_hospital],
        ],
        // 5. procedimientos_menores — by nombre_servicio
        'procedimientos' => [
            'sql' => "SELECT
                        SUM(pm.cobro) AS revenue,
                        SUM(COALESCE(
                            CASE WHEN WEEKDAY(pm.fecha_procedimiento) >= 5 OR TIME(pm.fecha_procedimiento) >= '18:00:00'
                                 THEN t.costo_inhabil ELSE t.costo_normal END,
                            0
                        )) AS cost
                       FROM procedimientos_menores pm
                       LEFT JOIN tarifas_servicios t
                         ON t.id_hospital = pm.id_hospital
                        AND t.tipo_servicio = 'procedimiento'
                        AND t.nombre_servicio = pm.procedimiento
                       WHERE pm.fecha_procedimiento BETWEEN ? AND ?
                         AND pm.id_hospital = ?",
            'params' => [$start_datetime, $end_datetime, $id_hospital],
        ],
    ];

    foreach ($profit_queries as $key => $q) {
        $stmt = $conn->prepare($q['sql']);
        $stmt->execute($q['params']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $category_profit[$key] = [
            'revenue' => (float)($row['revenue'] ?? 0),
            'cost'    => (float)($row['cost'] ?? 0),
            'profit'  => (float)($row['revenue'] ?? 0) - (float)($row['cost'] ?? 0),
        ];
    }

    // Grand totals (farmacia + 5 categorías tarifadas)
    $tarifas_revenue = array_sum(array_column($category_profit, 'revenue'));
    $tarifas_cost    = array_sum(array_column($category_profit, 'cost'));
    $tarifas_profit  = array_sum(array_column($category_profit, 'profit'));

    $grand_revenue = $sales_revenue + $tarifas_revenue;
    $grand_cost    = $sales_cost + $tarifas_cost;
    $grand_profit  = $grand_revenue - $grand_cost;
    $grand_margin_pct = $grand_revenue > 0 ? ($grand_profit / $grand_revenue) * 100 : 0;

    // 4. Procedimientos menores
    $stmt_proc = $conn->prepare("SELECT SUM(cobro) FROM procedimientos_menores WHERE fecha_procedimiento BETWEEN ? AND ? AND id_hospital = ?");
    $stmt_proc->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_procedures = $stmt_proc->fetchColumn() ?: 0;

    // 5. Ingresos por servicios clínicos (desglosados)
    $stmt_lab = $conn->prepare("
        SELECT SUM(cobro) FROM examenes_realizados
        WHERE fecha_examen BETWEEN ? AND ? AND id_hospital = ?
        AND tipo_examen NOT LIKE '%ultrasonido%'
        AND tipo_examen NOT LIKE '%rayos x%' AND tipo_examen NOT LIKE '%rx%'
    ");
    $stmt_lab->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_laboratory = (float) ($stmt_lab->fetchColumn() ?: 0);

    $stmt_us = $conn->prepare("SELECT SUM(cobro) FROM ultrasonidos WHERE fecha_ultrasonido BETWEEN ? AND ? AND id_hospital = ?");
    $stmt_us->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_ultrasound = (float) ($stmt_us->fetchColumn() ?: 0);

    $stmt_us_legacy = $conn->prepare("
        SELECT SUM(cobro) FROM examenes_realizados
        WHERE fecha_examen BETWEEN ? AND ? AND id_hospital = ? AND tipo_examen LIKE '%ultrasonido%'
    ");
    $stmt_us_legacy->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_ultrasound += (float) ($stmt_us_legacy->fetchColumn() ?: 0);

    $stmt_rx = $conn->prepare("SELECT SUM(cobro) FROM rayos_x WHERE fecha_estudio BETWEEN ? AND ? AND id_hospital = ?");
    $stmt_rx->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_xray = (float) ($stmt_rx->fetchColumn() ?: 0);

    $stmt_rx_legacy = $conn->prepare("
        SELECT SUM(cobro) FROM examenes_realizados
        WHERE fecha_examen BETWEEN ? AND ? AND id_hospital = ?
        AND (tipo_examen LIKE '%rayos x%' OR tipo_examen LIKE '%rx%')
    ");
    $stmt_rx_legacy->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_xray += (float) ($stmt_rx_legacy->fetchColumn() ?: 0);

    $stmt_electro = $conn->prepare("SELECT SUM(precio) FROM electrocardiogramas WHERE fecha_realizado BETWEEN ? AND ? AND id_hospital = ?");
    $stmt_electro->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_electro = (float) ($stmt_electro->fetchColumn() ?: 0);

    $total_exams_revenue = $total_laboratory + $total_ultrasound + $total_xray;

    // 6. Cobros de consultas
    $stmt_billings = $conn->prepare("SELECT SUM(cantidad_consulta) FROM cobros WHERE fecha_consulta BETWEEN ? AND ? AND id_hospital = ?");
    $stmt_billings->execute([$fecha_inicio, $fecha_fin, $id_hospital]);
    $total_billings = $stmt_billings->fetchColumn() ?: 0;

    // 6.b Hospitalizaciones (Cuentas de encamamientos dados de alta)
    $stmt_hosp = $conn->prepare("
        SELECT SUM(total_general) 
        FROM cuenta_hospitalaria ch 
        JOIN encamamientos e ON ch.id_encamamiento = e.id_encamamiento 
        WHERE e.fecha_alta BETWEEN ? AND ?
        AND e.id_hospital = ?
    ");
    $stmt_hosp->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_hospitalization = $stmt_hosp->fetchColumn() ?: 0;

    // 7. Ingresos brutos totales
    $total_gross_revenue = $total_sales_meds + $total_procedures + $total_laboratory + $total_ultrasound
        + $total_xray + $total_electro + $total_billings + $total_hospitalization;

    $ingresos_categorias = [
        ['label' => 'Ventas Farmacia', 'categoria' => 'farmacia', 'icon' => 'bi-capsule', 'badge' => 'charge-farmacia', 'monto' => (float) $total_sales_meds],
        ['label' => 'Consultas Médicas', 'categoria' => 'consultas', 'icon' => 'bi-stethoscope', 'badge' => 'charge-consulta', 'monto' => (float) $total_billings],
        ['label' => 'Laboratorio', 'categoria' => 'laboratorio', 'icon' => 'bi-droplet-half', 'badge' => 'charge-laboratorio', 'monto' => $total_laboratory],
        ['label' => 'Ultrasonido', 'categoria' => 'ultrasonido', 'icon' => 'bi-soundwave', 'badge' => 'charge-ultrasonido', 'monto' => $total_ultrasound],
        ['label' => 'Rayos X', 'categoria' => 'rayos_x', 'icon' => 'bi-radioactive', 'badge' => 'charge-rayos-x', 'monto' => $total_xray],
        ['label' => 'Electrocardiograma', 'categoria' => 'electro', 'icon' => 'bi-heart-pulse', 'badge' => 'charge-electro', 'monto' => $total_electro],
        ['label' => 'Procedimientos', 'categoria' => 'procedimientos', 'icon' => 'bi-bandaid', 'badge' => 'charge-procedimiento', 'monto' => (float) $total_procedures],
        ['label' => 'Hospitalización', 'categoria' => 'hospitalizacion', 'icon' => 'bi-hospital', 'badge' => 'charge-otro', 'monto' => (float) $total_hospitalization],
    ];

    $total_egresos = (float)$total_purchases_meds + $total_gastos;

    $egresos_categorias = [
        ['label' => 'Pago a Proveedores', 'categoria' => 'pago_proveedores', 'icon' => 'bi-cart-plus', 'monto' => (float) $total_purchases_meds],
        ['label' => 'Gastos Generales', 'categoria' => 'gastos_varios', 'icon' => 'bi-wallet2', 'monto' => $total_gastos],
    ];

    // 8. Utilidad Bruta — incluye TODAS las fuentes de profit (farmacia + 5 categorías tarifadas)
    // The original $total_gross_revenue variable only tracks `ventas.total`, so we recompute
    // the true total revenue across all 6 sources (farmacia + 5 tarifadas) and subtract costs.
    $all_sources_revenue = $sales_revenue + $tarifas_revenue;
    $all_sources_cost    = $sales_cost    + $tarifas_cost;
    $total_gross_profit  = $all_sources_revenue - $all_sources_cost;

    // 9. Desempeño neto
    $net_cash_flow = $total_gross_revenue - $total_egresos;

// ============ MÉTRICAS ADICIONALES ============

    // Total de pacientes registrados
    $total_pacientes = $conn->prepare("SELECT COUNT(*) FROM pacientes WHERE id_hospital = ?");
    $total_pacientes->execute([$id_hospital]);
    $total_pacientes = $total_pacientes->fetchColumn();

    // Citas en el período
    $stmt_citas = $conn->prepare("SELECT COUNT(*) FROM citas WHERE fecha_cita BETWEEN ? AND ? AND id_hospital = ?");
    $stmt_citas->execute([$start_datetime, $end_datetime, $id_hospital]);
    $citas_count = $stmt_citas->fetchColumn();

    // Exámenes realizados en el período (conteo)
    $stmt_examenes_count = $conn->prepare("SELECT COUNT(*) FROM examenes_realizados WHERE fecha_examen BETWEEN ? AND ? AND id_hospital = ?");
    $stmt_examenes_count->execute([$start_datetime, $end_datetime, $id_hospital]);
    $examenes_count = $stmt_examenes_count->fetchColumn();

    // Medicamentos en stock
    $total_medicamentos = $conn->prepare("SELECT COUNT(*) FROM inventario WHERE cantidad_med > 0 AND id_hospital = ?");
    $total_medicamentos->execute([$id_hospital]);
    $total_medicamentos = $total_medicamentos->fetchColumn();

    // Título de la página
    $page_title = "Reportes - Centro Médico Herrera Saenz";

    // ============ REPORTE DETALLADO DE MEDICAMENTOS (Farmacia + Hospitalización) ============

    // Año completo
    $year_start_dt = date('Y-01-01 00:00:00');
    $year_end_dt = date('Y-12-31 23:59:59');

    // Mantener compatibilidad con export links de otros tabs
    $profit_start = date('Y-01-01');
    $profit_end = date('Y-12-31');
    $profit_start_datetime = $year_start_dt;
    $profit_end_datetime = $year_end_dt;

    $meds_farm = [];
    $meds_hosp = [];

    try {
        // Farmacia — medicamentos vendidos agrupados por fecha
        $stmt_meds_farm = $conn->prepare("
            SELECT
                i.nom_medicamento,
                DATE(v.fecha_venta) as fecha,
                'Farmacia' as origen,
                SUM(dv.cantidad_vendida) as cantidad,
                SUM(dv.cantidad_vendida * dv.precio_unitario) as total_venta,
                SUM(dv.cantidad_vendida * COALESCE(pi.unit_cost, 0)) as total_costo
            FROM detalle_ventas dv
            JOIN ventas v ON dv.id_venta = v.id_venta
            JOIN inventario i ON dv.id_inventario = i.id_inventario
            LEFT JOIN purchase_items pi ON i.id_purchase_item = pi.id
            WHERE v.fecha_venta BETWEEN ? AND ?
            AND v.tipo_pago != 'Traslado'
            AND dv.precio_unitario > 0
            AND v.id_hospital = ?
            GROUP BY i.id_inventario, i.nom_medicamento, DATE(v.fecha_venta)
        ");
        $stmt_meds_farm->execute([$year_start_dt, $year_end_dt, $id_hospital]);
        $meds_farm = $stmt_meds_farm->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Error en reporte medicamentos farmacia: ' . $e->getMessage());
    }

    try {
        // Hospitalización — medicamentos administrados agrupados por fecha
        $stmt_meds_hosp = $conn->prepare("
            SELECT
                ch.descripcion as nom_medicamento,
                DATE(ch.fecha_cargo) as fecha,
                'Hospitalización' as origen,
                SUM(ch.cantidad) as cantidad,
                SUM(ch.subtotal) as total_venta,
                SUM(ch.cantidad * COALESCE(pi.unit_cost, 0)) as total_costo
            FROM cargos_hospitalarios ch
            JOIN cuenta_hospitalaria cu ON ch.id_cuenta = cu.id_cuenta
            JOIN encamamientos e ON cu.id_encamamiento = e.id_encamamiento
            LEFT JOIN inventario i ON ch.referencia_id = i.id_inventario AND ch.referencia_tabla = 'inventario'
            LEFT JOIN purchase_items pi ON i.id_purchase_item = pi.id
            WHERE ch.tipo_cargo = 'Medicamento'
            AND ch.cancelado = 0
            AND ch.fecha_cargo BETWEEN ? AND ?
            AND e.id_hospital = ?
            GROUP BY ch.descripcion, DATE(ch.fecha_cargo)
        ");
        $stmt_meds_hosp->execute([$year_start_dt, $year_end_dt, $id_hospital]);
        $meds_hosp = $stmt_meds_hosp->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Error en reporte medicamentos hospitalizacion: ' . $e->getMessage());
    }

    // Combinar y agrupar por mes → día
    $meds_all = array_merge($meds_farm, $meds_hosp);
    $grouped_meds = [];
    $total_meds_venta = 0;
    $total_meds_costo = 0;

    foreach ($meds_all as $row) {
        $ts = strtotime($row['fecha']);
        $mes_key = date('Y-m', $ts);
        $dia_key = $row['fecha'];

        if (!isset($grouped_meds[$mes_key])) {
            $meses_arr = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
            $grouped_meds[$mes_key] = [
                'nombre' => $meses_arr[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts),
                'dias' => []
            ];
        }
        if (!isset($grouped_meds[$mes_key]['dias'][$dia_key])) {
            $grouped_meds[$mes_key]['dias'][$dia_key] = ['items' => [], 'total_venta' => 0, 'total_costo' => 0];
        }
        $grouped_meds[$mes_key]['dias'][$dia_key]['items'][] = $row;
        $grouped_meds[$mes_key]['dias'][$dia_key]['total_venta'] += $row['total_venta'];
        $grouped_meds[$mes_key]['dias'][$dia_key]['total_costo'] += $row['total_costo'];
        $total_meds_venta += $row['total_venta'];
        $total_meds_costo += $row['total_costo'];
    }
    $total_meds_ganancia = $total_meds_venta - $total_meds_costo;
    $total_meds_margen = $total_meds_venta > 0 ? ($total_meds_ganancia / $total_meds_venta) * 100 : 0;

    // ============ REPORTE DETALLADO DE LABORATORIOS ============

    $labs_start_month = $_GET['labs_start'] ?? null;
    $labs_end_month = $_GET['labs_end'] ?? null;

    $labs_where = "";
    $labs_params = [];
    if ($labs_start_month && $labs_end_month) {
        $labs_where = "AND ol.fecha_orden BETWEEN ? AND ?";
        $labs_params = [$labs_start_month . '-01 00:00:00', date('Y-m-t 23:59:59', strtotime($labs_end_month . '-01'))];
    } elseif ($labs_start_month) {
        $labs_where = "AND ol.fecha_orden >= ?";
        $labs_params = [$labs_start_month . '-01 00:00:00'];
    } elseif ($labs_end_month) {
        $labs_where = "AND ol.fecha_orden <= ?";
        $labs_params = [date('Y-m-t 23:59:59', strtotime($labs_end_month . '-01'))];
    }

    $labs_where .= " AND ol.id_hospital = ?";
    $labs_params[] = $id_hospital;

    $stmt_labs_detail = $conn->prepare("
        SELECT
            p.nombre as paciente_nombre,
            p.apellido as paciente_apellido,
            cp.nombre_prueba,
            DATE(ol.fecha_orden) as fecha,
            TIME(ol.fecha_orden) as hora,
            cp.precio,
            ol.laboratorio_externo
        FROM ordenes_laboratorio ol
        JOIN orden_pruebas op ON ol.id_orden = op.id_orden
        JOIN catalogo_pruebas cp ON op.id_prueba = cp.id_prueba
        JOIN pacientes p ON ol.id_paciente = p.id_paciente
        WHERE op.estado != 'Devuelto'
        $labs_where
        ORDER BY ol.fecha_orden DESC
    ");
    $stmt_labs_detail->execute($labs_params);
    $labs_detail_data_raw = $stmt_labs_detail->fetchAll(PDO::FETCH_ASSOC);

    $total_labs_report = 0;
    $grouped_labs = [];
    foreach ($labs_detail_data_raw as $lab) {
        $total_labs_report += $lab['precio'];

        $timestamp = strtotime($lab['fecha']);
        // Formatear mes en español
        $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $mes_num = (int) date('m', $timestamp) - 1;
        $anio = date('Y', $timestamp);
        $mes_nombre = $meses[$mes_num] . ' ' . $anio;

        $dia_str = date('d/m/Y', $timestamp);
        $paciente_nombre = $lab['paciente_nombre'] . ' ' . $lab['paciente_apellido'];

        if (!isset($grouped_labs[$mes_nombre])) {
            $grouped_labs[$mes_nombre] = ['total' => 0, 'count' => 0, 'dias' => []];
        }
        $grouped_labs[$mes_nombre]['total'] += $lab['precio'];
        $grouped_labs[$mes_nombre]['count'] += 1;

        if (!isset($grouped_labs[$mes_nombre]['dias'][$dia_str])) {
            $grouped_labs[$mes_nombre]['dias'][$dia_str] = ['total' => 0, 'count' => 0, 'pacientes' => []];
        }
        $grouped_labs[$mes_nombre]['dias'][$dia_str]['total'] += $lab['precio'];
        $grouped_labs[$mes_nombre]['dias'][$dia_str]['count'] += 1;

        if (!isset($grouped_labs[$mes_nombre]['dias'][$dia_str]['pacientes'][$paciente_nombre])) {
            $grouped_labs[$mes_nombre]['dias'][$dia_str]['pacientes'][$paciente_nombre] = ['total' => 0, 'count' => 0, 'labs' => []];
        }
        $grouped_labs[$mes_nombre]['dias'][$dia_str]['pacientes'][$paciente_nombre]['total'] += $lab['precio'];
        $grouped_labs[$mes_nombre]['dias'][$dia_str]['pacientes'][$paciente_nombre]['count'] += 1;

        $grouped_labs[$mes_nombre]['dias'][$dia_str]['pacientes'][$paciente_nombre]['labs'][] = $lab;
    }

    // ============ REPORTE DE TRASLADOS (Acceso Restringido) ============
    $can_view_transfers = in_array($user_id, [1, 9, 10]);
    $transfers_data = [];
    $total_transfers_amount = 0;

    if ($can_view_transfers) {
        $stmt_transfers = $conn->prepare("
            SELECT 
                i.nom_medicamento,
                dv.cantidad_vendida,
                v.fecha_venta,
                v.nombre_cliente as destino,
                v.total as valor_traslado,
                COALESCE(pi.unit_cost, i.precio_compra, 0) as precio_compra,
                CONCAT(u.nombre, ' ', u.apellido) as realizado_por
            FROM ventas v
            JOIN detalle_ventas dv ON v.id_venta = dv.id_venta
            JOIN inventario i ON dv.id_inventario = i.id_inventario
            LEFT JOIN purchase_items pi ON i.id_purchase_item = pi.id
            LEFT JOIN usuarios u ON v.id_usuario = u.idUsuario
            WHERE v.tipo_pago = 'Traslado'
            AND v.fecha_venta BETWEEN ? AND ?
            AND v.id_hospital = ?
            ORDER BY v.fecha_venta DESC
        ");
        $stmt_transfers->execute([$profit_start_datetime, $profit_end_datetime, $id_hospital]);
        $raw_transfers = $stmt_transfers->fetchAll(PDO::FETCH_ASSOC);

        $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        foreach ($raw_transfers as $transfer) {
            $total_transfers_amount += $transfer['valor_traslado'];

            $t_date = new DateTime($transfer['fecha_venta']);
            $mes_num = (int) date('m', $t_date->getTimestamp()) - 1;
            $anio = date('Y', $t_date->getTimestamp());
            $mes_nombre = $meses[$mes_num] . ' ' . $anio;
            $dia_str = $t_date->format('d/m/Y');
            $destino = $transfer['destino'] ?: 'Sin Destino';

            if (!isset($transfers_data[$mes_nombre])) {
                $transfers_data[$mes_nombre] = ['total' => 0, 'dias' => []];
            }
            if (!isset($transfers_data[$mes_nombre]['dias'][$dia_str])) {
                $transfers_data[$mes_nombre]['dias'][$dia_str] = ['total' => 0, 'destinos' => []];
            }
            if (!isset($transfers_data[$mes_nombre]['dias'][$dia_str]['destinos'][$destino])) {
                $transfers_data[$mes_nombre]['dias'][$dia_str]['destinos'][$destino] = ['total' => 0, 'items' => []];
            }

            $transfers_data[$mes_nombre]['total'] += $transfer['valor_traslado'];
            $transfers_data[$mes_nombre]['dias'][$dia_str]['total'] += $transfer['valor_traslado'];
            $transfers_data[$mes_nombre]['dias'][$dia_str]['destinos'][$destino]['total'] += $transfer['valor_traslado'];

            $transfers_data[$mes_nombre]['dias'][$dia_str]['destinos'][$destino]['items'][] = $transfer;
        }
    }

} catch (PDOException $e) {
    // Error específico de base de datos
    error_log("Error DB en módulo de reportes: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    // Mostrar mensaje amigable al usuario
    $error_message = "Error al conectar con la base de datos. Por favor, contacte al administrador.";
    error_log('Error en reports/index.php: ' . $e->getMessage());
    if ($_SESSION['tipoUsuario'] === 'admin') {
        $error_message .= "<br><small>Detalles técnicos: " . htmlspecialchars($e->getMessage()) . "</small>";
    }
    die($error_message);
} catch (Exception $e) {
    // Otros errores generales
    error_log("Error general en módulo de reportes: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    die("Error al cargar los reportes. Por favor, contacte al administrador.");
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Módulo de Reportes - Centro Médico Herrera Saenz - Sistema de gestión médica">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <!-- logo -->
    <link rel="icon" type="image/png" href="../../assets/img/cmhs.png">

    <!-- Google Fonts - Inter (moderno y legible) -->
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Chart.js removido -->

    <!-- Seguridad y Protección de Código -->
    <script src="../../assets/js/security.js"></script>

    <!-- CSS Crítico (incrustado para máxima velocidad) -->
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
    <?php include '../../includes/theme_head.php'; ?>

    <style>
        /* ===== FILTER PANEL ===== */
        .filter-panel {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-xl);
            padding: 1.75rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }

        .filter-header {
            margin-bottom: 1rem;
        }

        .filter-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--color-text);
            margin: 0 0 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-form input[type="date"] {
            padding: 0.625rem 0.875rem;
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-md);
            background: var(--color-surface);
            color: var(--color-text);
            font-family: var(--font-family);
            font-size: 0.875rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            min-width: 180px;
        }

        .filter-form input[type="date"]:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.13);
        }

        /* ===== CONTENT SECTION ===== */
        .content-section {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
        }

        /* Override Bootstrap table styles to match our theme */
        .content-section .table {
            --bs-table-bg: var(--color-card);
            color: var(--color-text);
        }

        .content-section .table thead.bg-light {
            background: var(--color-surface) !important;
        }

        .content-section .table> :not(caption)>*>* {
            border-color: var(--color-border);
            color: var(--color-text);
        }

        .content-section .card {
            background: var(--color-card) !important;
        }

        .content-section .card-header {
            background: var(--color-surface) !important;
            border-color: var(--color-border) !important;
        }

        /* ===== AMOUNT BADGE ===== */
        .amount-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0.875rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .amount-badge.income {
            background: rgba(var(--color-success-rgb), 0.12);
            color: var(--color-success);
            border: 1px solid rgba(var(--color-success-rgb), 0.25);
        }

        .amount-badge.expense {
            background: rgba(var(--color-danger-rgb), 0.12);
            color: var(--color-danger);
            border: 1px solid rgba(var(--color-danger-rgb), 0.25);
        }

        /* ===== LABS ACCORDION ===== */
        .custom-accordion-wrapper {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .report-details {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: box-shadow 0.2s;
        }

        .report-details[open] {
            box-shadow: var(--shadow-sm);
        }

        .report-details.level-2 {
            margin-top: 0.5rem;
            border-radius: var(--radius-md);
            background: var(--color-surface);
        }

        .report-details.level-3 {
            margin-top: 0.35rem;
            border-radius: var(--radius-sm);
        }

        .custom-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.25rem;
            cursor: pointer;
            user-select: none;
            list-style: none;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--color-text);
            transition: background 0.15s;
            gap: 1rem;
        }

        .custom-summary::-webkit-details-marker {
            display: none;
        }

        .custom-summary:hover {
            background: rgba(var(--color-primary-rgb), 0.05);
        }

        .report-details[open]>.custom-summary {
            border-bottom: 1px solid var(--color-border);
        }

        .report-details.level-2 .custom-summary {
            padding: 0.75rem 1rem;
            font-size: 0.85rem;
        }

        .report-details.level-3 .custom-summary {
            padding: 0.6rem 1rem;
            font-size: 0.8rem;
        }

        .report-details-body {
            padding: 0.75rem 1rem;
        }

        .report-details.level-2 .report-details-body {
            padding: 0.5rem 0.75rem;
        }

        /* Lab items table */
        .lab-items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }

        .lab-items-table th {
            color: var(--color-text-secondary);
            font-weight: 700;
            font-size: 0.72rem;
            text-transform: uppercase;
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid var(--color-border);
        }

        .lab-items-table td {
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid var(--color-border);
            color: var(--color-text);
        }

        .lab-items-table tr:last-child td {
            border-bottom: none;
        }

        /* ===== PROFITABILITY TABLE ===== */
        .profit-table {
            width: 100%;
            border-collapse: collapse;
        }

        .profit-table th {
            padding: 0.875rem 1.25rem;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--color-text-secondary);
            background: var(--color-surface);
            border-bottom: 1px solid var(--color-border);
            text-align: left;
        }

        .profit-table td {
            padding: 0.875rem 1.25rem;
            border-bottom: 1px solid var(--color-border);
            color: var(--color-text);
            font-size: 0.875rem;
        }

        .profit-table tbody tr:hover {
            background: rgba(var(--color-primary-rgb), 0.03);
        }

        .profit-table .text-success {
            color: var(--color-success) !important;
            font-weight: 700;
        }

        .profit-table .text-danger {
            color: var(--color-danger) !important;
        }

        .profit-positive {
            color: var(--color-success);
            font-weight: 700;
        }

        .profit-negative {
            color: var(--color-danger);
            font-weight: 700;
        }

        /* Chart containers */
        .chart-container {
            position: relative;
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .chart-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--color-text);
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* ===== PREMIUM TABS ===== */
        .reports-tabs-container {
            margin-bottom: 2rem;
            position: relative;
        }

        .reports-tabs {
            display: flex;
            gap: 0.35rem;
            background: rgba(var(--color-card-rgb), 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid var(--color-border);
            padding: 0.35rem;
            border-radius: var(--radius-xl);
            overflow-x: auto;
            position: relative;
            scrollbar-width: none;
        }

        .reports-tabs::-webkit-scrollbar {
            display: none;
        }

        .reports-tab-btn {
            flex: 1;
            min-width: 150px;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-lg);
            border: none;
            background: transparent;
            color: var(--color-text-secondary);
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            white-space: nowrap;
            position: relative;
            z-index: 2;
        }

        .reports-tab-btn:hover {
            color: var(--color-primary);
            background: rgba(var(--color-primary-rgb), 0.05);
        }

        .reports-tab-btn.active {
            color: #ffffff;
            background: var(--color-primary);
            box-shadow: 0 4px 12px rgba(var(--color-primary-rgb), 0.25);
        }

        /* ===== TAB CONTENT ANIMATION ===== */
        .tab-content {
            display: none;
            opacity: 0;
            transform: translateY(10px);
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .tab-content.active {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        /* ===== SHADOW AND GLOW STAT CARDS ===== */
        .stat-card {
            position: relative;
            border: 1px solid var(--color-border) !important;
            background: var(--color-card) !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: inherit;
            pointer-events: none;
            box-shadow: inset 0 0 12px rgba(var(--color-primary-rgb), 0);
            transition: box-shadow 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg), 0 0 15px rgba(var(--color-primary-rgb), 0.12);
            border-color: rgba(var(--color-primary-rgb), 0.3) !important;
        }

        .stat-card:hover::after {
            box-shadow: inset 0 0 12px rgba(var(--color-primary-rgb), 0.05);
        }

        /* ===== ACCORDION PREMIUM OVERHAUL ===== */
        .report-details.level-1 {
            background: rgba(var(--color-card-rgb), 0.4);
            backdrop-filter: blur(8px);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            margin-bottom: 0.75rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .report-details.level-1:hover {
            border-color: rgba(var(--color-primary-rgb), 0.3);
            box-shadow: var(--shadow-sm);
        }

        .report-details.level-1[open] {
            border-color: rgba(var(--color-primary-rgb), 0.4);
            box-shadow: var(--shadow-md);
        }

        .report-details.level-1>.custom-summary {
            border-left: 4px solid var(--color-primary);
        }

        .report-details.level-2 {
            border-left: 3px solid var(--color-info);
        }

        .report-details.level-3 {
            border-left: 2px solid var(--color-secondary);
        }

        .custom-summary {
            position: relative;
            padding: 1.1rem 2.5rem 1.1rem 1.5rem !important;
        }

        .custom-summary::after {
            content: '\F282';
            /* bootstrap icon chevron-down */
            font-family: 'Bootstrap Icons';
            position: absolute;
            right: 1.5rem;
            top: 50%;
            transform: translateY(-50%) rotate(0deg);
            transition: transform 0.25s ease;
            font-size: 0.85rem;
            color: var(--color-text-secondary);
        }

        .report-details[open]>.custom-summary::after {
            transform: translateY(-50%) rotate(180deg);
        }

        /* ===== SEARCH WRAPPER FOR PHARMACY ===== */
        .search-wrapper {
            position: relative;
            width: 100%;
            max-width: 380px;
            margin-bottom: 1.25rem;
        }

        .search-input {
            width: 100%;
            padding: 0.625rem 1rem 0.625rem 2.5rem;
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-md);
            background: var(--color-surface);
            color: var(--color-text);
            font-size: 0.875rem;
            outline: none;
            transition: all 0.2s;
        }

        .search-input:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.15);
            background: var(--color-card);
        }

        .search-icon {
            position: absolute;
            left: 0.875rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--color-text-secondary);
            font-size: 0.95rem;
        }

        /* Content sections: tables & headers */
        .content-section .section-header {
            flex-wrap: wrap;
            gap: 0.75rem 1rem;
            padding: 1.25rem 1.5rem 0;
            margin-bottom: 0;
            border-bottom: none;
        }

        .content-section > .table-responsive,
        .content-section > .custom-accordion-wrapper {
            padding: 0 1.25rem 1.25rem;
        }

        .content-section .data-table {
            margin: 0;
        }

        .content-section .table thead.bg-light,
        .content-section .table thead.bg-light th {
            background: var(--color-surface) !important;
            color: var(--color-text-secondary) !important;
        }

        [data-theme="dark"] .content-section .badge.bg-light,
        [data-theme="dark"] .content-section .badge.bg-secondary {
            background: var(--color-surface) !important;
            color: var(--color-text) !important;
            border: 1px solid var(--color-border);
        }

        .custom-summary {
            flex-wrap: wrap;
            gap: 0.5rem 1rem;
        }

        .custom-summary > span:last-child,
        .custom-summary .amount-badge {
            margin-left: auto;
            flex-shrink: 0;
        }

        .report-details-body .table {
            --bs-table-bg: transparent;
            color: var(--color-text);
        }

        .report-details-body .table thead {
            background: var(--color-surface) !important;
        }

        .report-details-body .table td,
        .report-details-body .table th {
            border-color: var(--color-border) !important;
            color: var(--color-text);
        }

        .empty-report-hint {
            padding: 2.5rem 1.5rem;
            text-align: center;
            color: var(--color-text-secondary);
            background: var(--color-surface);
            border: 1px dashed var(--color-border);
            border-radius: var(--radius-lg);
        }

        .empty-report-hint i {
            font-size: 2rem;
            opacity: 0.45;
            display: block;
            margin-bottom: 0.75rem;
        }

        @media (max-width: 768px) {
            .reports-tab-btn {
                min-width: 120px;
                font-size: 0.78rem;
                padding: 0.6rem 0.75rem;
            }

            .custom-summary {
                padding-right: 2.5rem !important;
            }

            .section-header .amount-badge {
                width: 100%;
                justify-content: center;
            }
        }

        /* ===== CONTABILIDAD DETALLADA ===== */
        .accounting-body {
            padding: 0 1.5rem 1.5rem;
        }

        .accounting-period-hint {
            font-size: 0.8rem;
            color: var(--color-text-secondary);
            margin: -0.25rem 0 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .accounting-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1.25rem;
        }

        @media (max-width: 991px) {
            .accounting-grid {
                grid-template-columns: 1fr;
            }
        }

        .accounting-card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .accounting-card--income {
            border-top: 3px solid var(--color-success);
        }

        .accounting-card--expense {
            border-top: 3px solid var(--color-danger);
        }

        .accounting-card--performance {
            border-top: 3px solid var(--color-primary);
        }

        .accounting-card__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.1rem 1.25rem;
            border-bottom: 1px solid var(--color-border);
            background: var(--color-card);
        }

        .accounting-card__title {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 1rem;
            font-weight: 700;
            color: var(--color-text);
            margin: 0;
        }

        .accounting-card__title i {
            font-size: 1.25rem;
        }

        .accounting-card__total {
            font-size: 1rem;
            font-weight: 800;
            padding: 0.4rem 0.9rem;
            border-radius: 50px;
            white-space: nowrap;
        }

        .accounting-card__total--income {
            background: rgba(var(--color-success-rgb), 0.12);
            color: var(--color-success);
            border: 1px solid rgba(var(--color-success-rgb), 0.3);
        }

        .accounting-card__total--expense {
            background: rgba(var(--color-danger-rgb), 0.12);
            color: var(--color-danger);
            border: 1px solid rgba(var(--color-danger-rgb), 0.3);
        }

        .accounting-ledger {
            list-style: none;
            margin: 0;
            padding: 0.5rem 0;
        }

        .accounting-ledger__row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 1rem 1.5rem;
            align-items: center;
            padding: 0.85rem 1.25rem;
            border-bottom: 1px solid var(--color-border);
            transition: background 0.15s ease;
            cursor: pointer;
        }

        .accounting-ledger__row:last-child {
            border-bottom: none;
        }

        .accounting-ledger__row:hover {
            background: rgba(var(--color-primary-rgb), 0.04);
        }

        .accounting-ledger__label {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            min-width: 0;
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--color-text);
        }

        .accounting-ledger__label .charge-type-badge {
            flex-shrink: 0;
            text-transform: none;
            letter-spacing: 0;
            font-size: 0.7rem;
        }

        .charge-type-badge {
            display: inline-flex; align-items: center; gap: 0.3rem;
            padding: 0.3rem 0.7rem; border-radius: 50px;
            font-size: 0.72rem; font-weight: 700;
        }
        .charge-farmacia {
            background: rgba(16, 185, 129, 0.12) !important;
            color: #059669 !important;
        }
        .charge-hospitalizacion {
            background: rgba(99, 102, 241, 0.12) !important;
            color: #6366f1 !important;
        }

        .accounting-ledger__label-text {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .accounting-ledger__values {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.35rem;
            min-width: 140px;
        }

        .accounting-ledger__amount {
            font-size: 0.95rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            color: var(--color-text);
            white-space: nowrap;
        }

        .accounting-ledger__amount--income {
            color: var(--color-success);
        }

        .accounting-ledger__amount--expense {
            color: var(--color-danger);
        }

        .accounting-ledger__pct {
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--color-text-secondary);
        }

        .accounting-ledger__bar {
            width: 100%;
            max-width: 120px;
            height: 4px;
            background: var(--color-border);
            border-radius: 4px;
            overflow: hidden;
        }

        .accounting-ledger__bar-fill {
            height: 100%;
            border-radius: 4px;
            background: var(--color-primary);
            transition: width 0.3s ease;
        }

        .accounting-ledger__bar-fill--income {
            background: var(--color-success);
        }

        .accounting-ledger__bar-fill--expense {
            background: var(--color-danger);
        }

        .accounting-empty-note {
            padding: 1.5rem 1.25rem;
            text-align: center;
            font-size: 0.82rem;
            color: var(--color-text-secondary);
        }

        .accounting-empty-note i {
            display: block;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            opacity: 0.5;
        }

        .accounting-performance {
            margin-top: 1.25rem;
        }

        .accounting-metric-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
            padding: 1rem 1.25rem 1.25rem;
        }

        @media (max-width: 768px) {
            .accounting-metric-grid {
                grid-template-columns: 1fr;
            }

            .accounting-ledger__row {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }

            .accounting-ledger__values {
                align-items: flex-start;
                min-width: 0;
            }

            .accounting-ledger__bar {
                max-width: 100%;
            }
        }

        .accounting-metric {
            padding: 1rem 1.1rem;
            border-radius: var(--radius-md);
            background: var(--color-card);
            border: 1px solid var(--color-border);
        }

        .accounting-metric__label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--color-text-secondary);
            margin-bottom: 0.35rem;
        }

        .accounting-metric__value {
            font-size: 1.35rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            line-height: 1.2;
        }

        .accounting-metric__meta {
            font-size: 0.78rem;
            color: var(--color-text-secondary);
            margin-top: 0.35rem;
        }

        .accounting-metric--highlight {
            grid-column: 1 / -1;
            background: rgba(var(--color-primary-rgb), 0.06);
            border-color: rgba(var(--color-primary-rgb), 0.25);
        }

        /* Hide tabs in print mode */
        /* ===== CUSTOM MODAL ===== */
        .custom-modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
            animation: fadeIn 0.2s ease;
        }

        .custom-modal-container {
            background: var(--color-card);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            width: 90%;
            max-width: 1100px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            animation: slideUp 0.25s ease;
        }

        .custom-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--color-border);
        }

        .custom-modal-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--color-text);
            margin: 0;
        }

        .custom-modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--color-text-secondary);
            cursor: pointer;
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            transition: background 0.15s;
        }

        .custom-modal-close:hover {
            background: var(--color-surface);
        }

        .custom-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 1.25rem 1.5rem;
        }

        .custom-modal-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--color-border);
        }

        .custom-modal-footer .total-label {
            font-size: 0.85rem;
            color: var(--color-text-secondary);
        }

        .custom-modal-footer .total-amount {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--color-success);
        }

        .desglose-table {
            width: 100%;
            border-collapse: collapse;
        }

        .desglose-table th {
            padding: 0.75rem 0.875rem;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--color-text-secondary);
            background: var(--color-surface);
            border-bottom: 2px solid var(--color-border);
            text-align: left;
        }

        .desglose-table th.text-end {
            text-align: right;
        }

        .desglose-table td {
            padding: 0.7rem 0.875rem;
            border-bottom: 1px solid var(--color-border);
            color: var(--color-text);
            font-size: 0.85rem;
        }

        .desglose-table td.text-end {
            text-align: right;
        }

        .desglose-table tbody tr:hover {
            background: rgba(var(--color-primary-rgb), 0.03);
        }

        .desglose-table .row-num {
            color: var(--color-text-secondary);
            font-size: 0.78rem;
            width: 40px;
        }

        .desglose-empty {
            padding: 3rem 1.5rem;
            text-align: center;
            color: var(--color-text-secondary);
        }

        .desglose-empty i {
            font-size: 2.5rem;
            opacity: 0.35;
            display: block;
            margin-bottom: 0.75rem;
        }

        .desglose-loading {
            padding: 3rem 1.5rem;
            text-align: center;
            color: var(--color-text-secondary);
        }

        .desglose-loading i {
            font-size: 2rem;
            display: block;
            margin-bottom: 0.75rem;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .paciente-row {
            cursor: pointer;
        }
        .paciente-row:hover {
            background: rgba(var(--color-primary-rgb), 0.06) !important;
        }
        .paciente-icon {
            transition: transform 0.2s ease;
            font-size: 0.85rem;
        }
        .detalle-row > td {
            padding: 0 !important;
            border-bottom: none !important;
        }
        .sub-table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(var(--color-primary-rgb), 0.03);
        }
        .sub-table th {
            padding: 0.6rem 0.875rem;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--color-text-secondary);
            background: rgba(var(--color-primary-rgb), 0.05);
            border-bottom: 1px solid var(--color-border);
            text-align: left;
        }
        .sub-table th.text-end {
            text-align: right;
        }
        .sub-table td {
            padding: 0.55rem 0.875rem;
            border-bottom: 1px solid rgba(var(--color-border-rgb), 0.5);
            color: var(--color-text);
            font-size: 0.82rem;
        }
        .sub-table td.text-end {
            text-align: right;
        }
        .sub-table tbody tr:last-child td {
            border-bottom: none;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @media print {

            .reports-tabs-container,
            .filter-panel,
            .chart-container,
            .page-actions,
            .theme-toggle,
            .btn-group {
                display: none !important;
            }

            .tab-content {
                display: block !important;
                opacity: 1 !important;
                transform: none !important;
            }

            body {
                background: white !important;
                color: black !important;
            }

            .content-section {
                border: none !important;
                box-shadow: none !important;
                background: transparent !important;
                page-break-inside: avoid;
            }
        }
    </style>

</head>

<body>
    <!-- Efecto de mármol animado -->
    <div class="marble-effect"></div>

    <div class="dashboard-container">
        <!-- Header Superior -->
        <header class="dashboard-header">
            <div class="header-content">
                <!-- logo -->
                <div class="brand-container">
                    <img src="../../assets/img/cmhs.png" alt="Centro Médico Herrera Saenz" class="brand-logo" width="40" height="40"
                        height="40">
                </div>

                <!-- Controles -->
                <div class="header-controls">
                    <!-- Control de tema -->
                    <div class="theme-toggle">
                        <button id="themeSwitch" class="theme-btn" aria-label="Cambiar tema claro/oscuro">
                            <i class="bi bi-sun theme-icon sun-icon"></i>
                            <i class="bi bi-moon theme-icon moon-icon"></i>
                        </button>
                    </div>

                    <!-- Información del usuario -->
                    <div class="header-user">
                        <div class="header-avatar">
                            <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                        </div>
                        <div class="header-details">
                            <span class="header-name"><?php echo htmlspecialchars($user_name); ?></span>
                            <span class="header-role"><?php echo htmlspecialchars($user_specialty); ?></span>
                        </div>
                    </div>

                    <!-- Back Button -->
                    <a href="../dashboard/index.php" class="action-btn secondary">
                        <i class="bi bi-arrow-left"></i>
                        Dashboard
                    </a>

                    <!-- Botón de cerrar sesión -->
                    <a href="../auth/logout.php" class="logout-btn">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Salir</span>
                    </a>
                </div>
            </div>
        </header>

        <main class="main-content">
            <?php render_breadcrumbs([
                ['label' => 'Dashboard', 'url' => '../dashboard/index.php'],
                ['label' => 'Reportes'],
            ]); ?>
            <!-- Encabezado de página -->
            <div class="page-header">
                <div class="page-title-section">
                    <h1 class="page-title">Centro de Analítica</h1>
                    <p class="page-subtitle">Monitoreo en tiempo real y métricas estratégicas</p>
                </div>
                <div class="page-actions">
                    <?php if ($user_type === 'admin'): ?>
                            <a href="export_jornada.php" target="_blank" class="action-btn" onclick="showPdfLoading()">
                                <i class="bi bi-file-earmark-pdf me-2"></i>
                                Exportar Jornada
                            </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Panel de filtros -->
            <div class="filter-panel animate-in">
                <div class="filter-header mb-4">
                    <h3 class="filter-title">
                        <i class="bi bi-calendar3 text-primary"></i>
                        Parámetros del Reporte
                    </h3>
                    <p class="text-muted small">Seleccione jornada o mes para auditar los movimientos</p>
                </div>
                <form method="GET" class="filter-form">
                    <div class="d-flex gap-2 mb-3">
                        <button type="button" class="btn btn-sm <?php echo $filtro_tipo === 'jornada' ? 'btn-primary' : 'btn-outline-secondary'; ?>" onclick="switchFiltroTipo('jornada')">
                            <i class="bi bi-calendar-day"></i> Por Jornada
                        </button>
                        <button type="button" class="btn btn-sm <?php echo $filtro_tipo === 'mes' ? 'btn-primary' : 'btn-outline-secondary'; ?>" onclick="switchFiltroTipo('mes')">
                            <i class="bi bi-calendar-month"></i> Por Mes
                        </button>
                    </div>
                    <div class="d-flex gap-3 align-items-end">
                        <input type="hidden" name="filtro_tipo" id="filtro_tipo" value="<?php echo $filtro_tipo; ?>">

                        <div class="form-group flex-grow-1" id="jornadaInput" style="<?php echo $filtro_tipo === 'mes' ? 'display:none;' : ''; ?>">
                            <label for="fecha_filtro" class="form-label fw-semibold">Fecha de Jornada</label>
                            <input type="date" class="form-control" id="fecha_filtro" name="fecha_filtro"
                                value="<?php echo htmlspecialchars($fecha_filtro); ?>">
                        </div>

                        <div class="form-group flex-grow-1" id="mesInput" style="<?php echo $filtro_tipo === 'jornada' ? 'display:none;' : ''; ?>">
                            <label for="mes_filtro" class="form-label fw-semibold">Mes</label>
                            <input type="month" class="form-control" id="mes_filtro" name="mes_filtro"
                                value="<?php echo $mes_filtro; ?>">
                        </div>

                        <button type="submit" class="action-btn">
                            <i class="bi bi-search me-2"></i>
                            Generar Análisis
                        </button>
                    </div>
                </form>
            </div>

            <!-- Menú de Pestañas Premium (Tabs) -->
            <div class="reports-tabs-container animate-in">
                <div class="reports-tabs">
                    <button class="reports-tab-btn active" data-tab="overview">
                        <i class="bi bi-grid-1x2"></i> Métricas & BI
                    </button>
                    <button class="reports-tab-btn" data-tab="accounting">
                        <i class="bi bi-cash-coin"></i> Contabilidad & Ratios
                    </button>
                    <button class="reports-tab-btn" data-tab="pharmacy">
                        <i class="bi bi-capsule"></i> Auditoría de Medicamento
                    </button>
                    <button class="reports-tab-btn" data-tab="labs">
                        <i class="bi bi-droplet-half"></i> Auditoría de Labs
                    </button>
                    <?php if ($can_view_transfers): ?>
                            <button class="reports-tab-btn" data-tab="transfers">
                                <i class="bi bi-arrow-left-right"></i> Dispensario
                            </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB 1: OVERVIEW & BI -->
            <div id="tab-overview" class="tab-content active">
                <!-- Estadísticas principales -->
                <div class="stats-grid">
                    <!-- Pacientes registrados -->
                    <div class="stat-card animate-in">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Pacientes Registrados</div>
                                <div class="stat-value"><?php echo $total_pacientes; ?></div>
                            </div>
                            <div class="stat-icon primary">
                                <i class="bi bi-people"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Citas en período -->
                    <div class="stat-card animate-in delay-1">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Citas en Periodo</div>
                                <div class="stat-value"><?php echo $citas_count; ?></div>
                            </div>
                            <div class="stat-icon success">
                                <i class="bi bi-calendar-event"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Exámenes realizados -->
                    <div class="stat-card animate-in delay-2">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Exámenes Realizados</div>
                                <div class="stat-value"><?php echo $examenes_count; ?></div>
                            </div>
                            <div class="stat-icon info">
                                <i class="bi bi-clipboard2-pulse"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Medicamentos en stock -->
                    <div class="stat-card animate-in delay-3">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Medicamentos en Stock</div>
                                <div class="stat-value"><?php echo $total_medicamentos; ?></div>
                            </div>
                            <div class="stat-icon warning">
                                <i class="bi bi-capsule"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mt-2">
                    <!-- Procedimientos menores -->
                    <div class="col-lg-6">
                        <div class="content-section" style="height: 100%;">
                            <div class="section-header">
                                <h4 class="section-title">
                                    <i class="bi bi-bandaid section-title-icon"></i>
                                    Procedimientos Menores Recientes
                                </h4>
                                <span class="amount-badge income">
                                    Total: Q<?php echo number_format($total_procedures, 2); ?>
                                </span>
                            </div>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Paciente</th>
                                            <th class="text-end">Cobro</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $stmt = $conn->prepare("
                                                    SELECT fecha_procedimiento, nombre_paciente, cobro 
                                                    FROM procedimientos_menores 
                                                    WHERE fecha_procedimiento BETWEEN ? AND ? 
                                                    AND id_hospital = ?
                                                    ORDER BY fecha_procedimiento DESC 
                                                    LIMIT 5
                                                ");
                                        $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
                                        $hasProc = false;
                                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            $hasProc = true;
                                            echo "<tr>
                                                        <td>" . date('d/m/y', strtotime($row['fecha_procedimiento'])) . "</td>
                                                        <td>" . htmlspecialchars($row['nombre_paciente']) . "</td>
                                                        <td class='text-end'>
                                                            <span class='amount-badge income'>
                                                                Q" . number_format($row['cobro'], 2) . "
                                                            </span>
                                                        </td>
                                                    </tr>";
                                        }
                                        if (!$hasProc) {
                                            echo "<tr><td colspan='3' class='text-center text-muted py-4'>No hay procedimientos en este período</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Exámenes realizados -->
                    <div class="col-lg-6">
                        <div class="content-section" style="height: 100%;">
                            <div class="section-header">
                                <h4 class="section-title">
                                    <i class="bi bi-clipboard2-pulse section-title-icon"></i>
                                    Exámenes Recientes
                                </h4>
                                <span class="amount-badge income">
                                    Total: Q<?php echo number_format($total_exams_revenue, 2); ?>
                                </span>
                            </div>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Paciente</th>
                                            <th class="text-end">Cobro</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $stmt = $conn->prepare("
                                                    SELECT fecha_examen, nombre_paciente, cobro 
                                                    FROM examenes_realizados 
                                                    WHERE fecha_examen BETWEEN ? AND ? 
                                                    AND id_hospital = ?
                                                    ORDER BY fecha_examen DESC 
                                                    LIMIT 5
                                                ");
                                        $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
                                        $hasExam = false;
                                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            $hasExam = true;
                                            echo "<tr>
                                                        <td>" . date('d/m/y', strtotime($row['fecha_examen'])) . "</td>
                                                        <td>" . htmlspecialchars($row['nombre_paciente']) . "</td>
                                                        <td class='text-end'>
                                                            <span class='amount-badge income'>
                                                                Q" . number_format($row['cobro'], 2) . "
                                                            </span>
                                                        </td>
                                                    </tr>";
                                        }
                                        if (!$hasExam) {
                                            echo "<tr><td colspan='3' class='text-center text-muted py-4'>No hay exámenes en este período</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: CONTABILIDAD DETALLADA -->
            <div id="tab-accounting" class="tab-content">
                <div class="content-section animate-in">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="bi bi-cash-coin section-title-icon"></i>
                            Contabilidad Detallada
                        </h3>
                        <span class="amount-badge <?php echo $total_gross_profit >= 0 ? 'income' : 'expense'; ?>">
                            <i
                                class="bi <?php echo $total_gross_profit >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right'; ?>"></i>
                            Utilidad: Q<?php echo number_format($total_gross_profit, 2); ?>
                        </span>
                    </div>

                    <div class="accounting-body" data-period-start="<?php echo $start_datetime; ?>" data-period-end="<?php echo $end_datetime; ?>">
                        <p class="accounting-period-hint">
                            <i class="bi bi-calendar3"></i>
                            Jornada del <?php echo date('d/m/Y', strtotime($fecha_filtro)); ?>
                            (08:00 – 07:59 del día siguiente)
                        </p>

                        <div class="accounting-grid">
                            <!-- Ingresos -->
                            <div class="accounting-card accounting-card--income">
                                <div class="accounting-card__head">
                                    <h4 class="accounting-card__title">
                                        <i class="bi bi-arrow-down-left-circle-fill text-success"></i>
                                        Fuentes de Ingresos
                                    </h4>
                                    <span class="accounting-card__total accounting-card__total--income">
                                        Q<?php echo number_format($total_gross_revenue, 2); ?>
                                    </span>
                                </div>
                                <ul class="accounting-ledger">
                                    <?php foreach ($ingresos_categorias as $cat):
                                        $pct = $total_gross_revenue > 0 ? ($cat['monto'] / $total_gross_revenue) * 100 : 0;
                                        ?>
                                            <li class="accounting-ledger__row" data-categoria="<?php echo $cat['categoria']; ?>" onclick="openCategoriaDesglose(this)">
                                                <div class="accounting-ledger__label">
                                                    <span class="charge-type-badge <?php echo htmlspecialchars($cat['badge']); ?>">
                                                        <i class="bi <?php echo htmlspecialchars($cat['icon']); ?>"></i>
                                                    </span>
                                                    <span class="accounting-ledger__label-text">
                                                        <?php echo htmlspecialchars($cat['label']); ?>
                                                    </span>
                                                </div>
                                                <div class="accounting-ledger__values">
                                                    <div class="accounting-ledger__bar" title="<?php echo number_format($pct, 1); ?>% del total">
                                                        <div class="accounting-ledger__bar-fill accounting-ledger__bar-fill--income"
                                                            style="width: <?php echo min(100, max(0, $pct)); ?>%;"></div>
                                                    </div>
                                                    <span class="accounting-ledger__amount accounting-ledger__amount--income">
                                                        Q<?php echo number_format($cat['monto'], 2); ?>
                                                    </span>
                                                    <span class="accounting-ledger__pct">
                                                        <?php echo number_format($pct, 1); ?>% del total
                                                    </span>
                                                </div>
                                            </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>

                            <!-- Egresos -->
                            <div class="accounting-card accounting-card--expense">
                                <div class="accounting-card__head">
                                    <h4 class="accounting-card__title">
                                        <i class="bi bi-arrow-up-right-circle-fill text-danger"></i>
                                        Egresos e Inversión
                                    </h4>
                                    <span class="accounting-card__total accounting-card__total--expense">
                                        Q<?php echo number_format($total_egresos, 2); ?>
                                    </span>
                                </div>
                                <ul class="accounting-ledger">
                                    <?php foreach ($egresos_categorias as $cat):
                                        $pct_eg = $total_gross_revenue > 0 ? ($cat['monto'] / $total_gross_revenue) * 100 : 0;
                                        ?>
                                            <li class="accounting-ledger__row" data-categoria="<?php echo $cat['categoria']; ?>" onclick="openCategoriaDesglose(this)">
                                                <div class="accounting-ledger__label">
                                                    <span class="charge-type-badge charge-otro">
                                                        <i class="bi <?php echo htmlspecialchars($cat['icon']); ?>"></i>
                                                    </span>
                                                    <span class="accounting-ledger__label-text">
                                                        <?php echo htmlspecialchars($cat['label']); ?>
                                                    </span>
                                                </div>
                                                <div class="accounting-ledger__values">
                                                    <div class="accounting-ledger__bar">
                                                        <div class="accounting-ledger__bar-fill accounting-ledger__bar-fill--expense"
                                                            style="width: <?php echo min(100, max(0, $pct_eg)); ?>%;"></div>
                                                    </div>
                                                    <span class="accounting-ledger__amount accounting-ledger__amount--expense">
                                                        Q<?php echo number_format($cat['monto'], 2); ?>
                                                    </span>
                                                    <span class="accounting-ledger__pct">
                                                        <?php echo number_format($pct_eg, 1); ?>% vs ingresos
                                                    </span>
                                                </div>
                                            </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>

                        <!-- Rentabilidad operativa -->
                        <div class="accounting-performance">
                            <div class="accounting-card accounting-card--performance">
                                <div class="accounting-card__head">
                                    <h4 class="accounting-card__title">
                                        <i class="bi bi-graph-up-arrow text-primary"></i>
                                        Análisis de Rentabilidad Operativa
                                    </h4>
                                </div>
                                <div class="accounting-metric-grid">
                                    <div class="accounting-metric">
                                        <div class="accounting-metric__label">Ingresos brutos</div>
                                        <div class="accounting-metric__value text-success">
                                            Q<?php echo number_format($total_gross_revenue, 2); ?>
                                        </div>
                                        <div class="accounting-metric__meta">100% base del período</div>
                                    </div>
                                    <div class="accounting-metric">
                                        <div class="accounting-metric__label">Costo ventas (farmacia)</div>
                                        <div class="accounting-metric__value text-danger">
                                            Q<?php echo number_format($sales_cost, 2); ?>
                                        </div>
                                        <div class="accounting-metric__meta">
                                            <?php echo $total_gross_revenue > 0 ? number_format(($sales_cost / $total_gross_revenue) * 100, 1) : '0'; ?>%
                                            del ingreso bruto
                                        </div>
                                    </div>
                                    <div class="accounting-metric accounting-metric--highlight">
                                        <div class="accounting-metric__label">Utilidad bruta de operación</div>
                                        <div class="accounting-metric__value <?php echo $total_gross_profit >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            Q<?php echo number_format($total_gross_profit, 2); ?>
                                        </div>
                                        <div class="accounting-metric__meta">
                                            Margen:
                                            <span class="charge-type-badge <?php echo $total_gross_profit >= 0 ? 'charge-consulta' : 'charge-electro'; ?>">
                                                <?php echo $total_gross_revenue > 0 ? number_format(($total_gross_profit / $total_gross_revenue) * 100, 1) : '0'; ?>%
                                            </span>
                                        </div>
                                    </div>
                                    <div class="accounting-metric">
                                        <div class="accounting-metric__label">Flujo de efectivo neto</div>
                                        <div class="accounting-metric__value <?php echo $net_cash_flow >= 0 ? 'text-primary' : 'text-danger'; ?>">
                                            Q<?php echo number_format($net_cash_flow, 2); ?>
                                        </div>
                                        <div class="accounting-metric__meta">Ingresos − pagos a proveedores y gastos</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: VENTAS Y RENTABILIDAD DE FARMACIA -->
            <div id="tab-pharmacy" class="tab-content">
                <div class="content-section animate-in">
                    <div class="section-header">
                        <h3 class="section-title h4 mb-1">
                            <i class="bi bi-capsule text-success me-2"></i>
                            Auditoría de Medicamento
                        </h3>
                        <p class="text-muted small mb-0">Desglose de medicamentos — Farmacia vs Hospitalización</p>
                    </div>

                    <!-- ======================================================================== -->
                    <!-- DESGLOSE POR DÍA — FARMACIA vs HOSPITALIZACIÓN -->
                    <!-- ======================================================================== -->
                    <h3 class="section-title h5 mb-1">
                        <i class="bi bi-calendar-range text-info me-2"></i>
                        Desglose por Día
                    </h3>
                    <p class="text-muted small mb-0">Medicamentos vendidos en Farmacia y administrados en Hospitalización</p>

                    <div class="custom-accordion-wrapper" id="medsAuditAccordion">
                        <?php if (empty($grouped_meds)): ?>
                            <div class="empty-report-hint">
                                <i class="bi bi-bandaid"></i>
                                No se encontraron movimientos de medicamentos en este periodo.
                            </div>
                        <?php else: ?>
                            <?php krsort($grouped_meds); $mes_actual_key = date('Y-m'); ?>
                            <?php foreach ($grouped_meds as $mes_key => $mes_data): ?>
                                <details class="report-details level-1" name="meds_mes_accordion" <?php echo $mes_key === $mes_actual_key ? 'open' : ''; ?>>
                                    <summary class="custom-summary">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-calendar3 me-2 text-primary"></i>
                                            <span><?php echo $mes_data['nombre']; ?></span>
                                            <span class="badge bg-primary ms-3 rounded-pill"><?php echo count($mes_data['dias']); ?> días</span>
                                        </div>
                                        <span class="text-success">Q <?php echo number_format(array_sum(array_column($mes_data['dias'], 'total_venta')), 2); ?></span>
                                    </summary>
                                    <div class="report-details-body">
                                        <?php krsort($mes_data['dias']); foreach ($mes_data['dias'] as $dia_key => $dia_data): ?>
                                            <details class="report-details level-2" name="meds_dia_accordion">
                                                <summary class="custom-summary">
                                                    <div class="d-flex align-items-center">
                                                        <i class="bi bi-calendar-day me-2 text-info"></i>
                                                        <span>Día: <?php echo date('d/m/Y', strtotime($dia_key)); ?></span>
                                                        <span class="badge bg-info text-dark ms-3 rounded-pill"><?php echo count($dia_data['items']); ?> medicamentos</span>
                                                    </div>
                                                    <span class="text-success fw-semibold">Q <?php echo number_format($dia_data['total_venta'], 2); ?></span>
                                                </summary>
                                                <div class="report-details-body p-0">
                                                    <div class="table-responsive">
                                                        <table class="lab-items-table">
                                                            <thead>
                                                                <tr>
                                                                    <th>Medicamento</th>
                                                                    <th>Origen</th>
                                                                    <th class="text-center">Uds.</th>
                                                                    <th class="text-end">P. Venta</th>
                                                                    <th class="text-end">P. Costo</th>
                                                                    <th class="text-end">Total Venta</th>
                                                                    <th class="text-end">Total Costo</th>
                                                                    <th class="text-end">Ganancia</th>
                                                                    <th class="text-center">Margen</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($dia_data['items'] as $item):
                                                                    $p_venta = $item['cantidad'] > 0 ? $item['total_venta'] / $item['cantidad'] : 0;
                                                                    $p_costo = $item['cantidad'] > 0 ? $item['total_costo'] / $item['cantidad'] : 0;
                                                                    $ganancia = $item['total_venta'] - $item['total_costo'];
                                                                    $margen = $item['total_venta'] > 0 ? ($ganancia / $item['total_venta']) * 100 : 0;
                                                                    $origen_class = $item['origen'] === 'Farmacia' ? 'charge-farmacia' : 'charge-hospitalizacion';
                                                                ?>
                                                                    <tr>
                                                                        <td><?php echo htmlspecialchars($item['nom_medicamento']); ?></td>
                                                                        <td><span class="charge-type-badge <?php echo $origen_class; ?>"><?php echo $item['origen']; ?></span></td>
                                                                        <td class="text-center"><?php echo $item['cantidad']; ?></td>
                                                                        <td class="text-end text-muted">Q<?php echo number_format($p_venta, 2); ?></td>
                                                                        <td class="text-end text-muted">Q<?php echo number_format($p_costo, 2); ?></td>
                                                                        <td class="text-end fw-bold">Q<?php echo number_format($item['total_venta'], 2); ?></td>
                                                                        <td class="text-end">Q<?php echo number_format($item['total_costo'], 2); ?></td>
                                                                        <td class="text-end fw-bold <?php echo $ganancia >= 0 ? 'text-success' : 'text-danger'; ?>">Q<?php echo number_format($ganancia, 2); ?></td>
                                                                        <td class="text-center">
                                                                            <?php
                                                                            $margen_color = $margen > 30 ? 'bg-success' : ($margen > 15 ? 'bg-warning text-dark' : 'bg-danger');
                                                                            ?>
                                                                            <span class="badge <?php echo $margen_color; ?> rounded-pill px-2" style="min-width: 45px;">
                                                                                <?php echo number_format($margen, 0); ?>%
                                                                            </span>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                            <tfoot>
                                                                <tr class="table-light fw-bold">
                                                                    <td colspan="2">Total del día</td>
                                                                    <td class="text-center">—</td>
                                                                    <td class="text-end">—</td>
                                                                    <td class="text-end">—</td>
                                                                    <td class="text-end text-success">Q<?php echo number_format($dia_data['total_venta'], 2); ?></td>
                                                                    <td class="text-end">Q<?php echo number_format($dia_data['total_costo'], 2); ?></td>
                                                                    <td class="text-end <?php echo ($dia_data['total_venta'] - $dia_data['total_costo']) >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                                        Q<?php echo number_format($dia_data['total_venta'] - $dia_data['total_costo'], 2); ?>
                                                                    </td>
                                                                    <td class="text-center">—</td>
                                                                </tr>
                                                            </tfoot>
                                                        </table>
                                                    </div>
                                                </div>
                                            </details>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                            <?php endforeach; ?>

                            <!-- Totales generales -->
                            <div class="mt-3 p-3 rounded" style="background: var(--color-surface);">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                    <span class="text-muted">
                                        <i class="bi bi-box-seam me-1"></i>
                                        <?php echo count($meds_farm) + count($meds_hosp); ?> registros —
                                        <span class="badge charge-farmacia ms-1">Farmacia: <?php echo count($meds_farm); ?></span>
                                        <span class="badge charge-hospitalizacion ms-1">Hospitalización: <?php echo count($meds_hosp); ?></span>
                                    </span>
                                    <div class="d-flex gap-4">
                                        <span>Total Venta: <strong class="text-success">Q<?php echo number_format($total_meds_venta, 2); ?></strong></span>
                                        <span>Total Costo: <strong class="text-danger">Q<?php echo number_format($total_meds_costo, 2); ?></strong></span>
                                        <span>Ganancia: <strong class="<?php echo $total_meds_ganancia >= 0 ? 'text-success' : 'text-danger'; ?>">Q<?php echo number_format($total_meds_ganancia, 2); ?></strong></span>
                                        <span>Margen: <strong><?php echo number_format($total_meds_margen, 1); ?>%</strong></span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- TAB 4: AUDITORÍA DE LABORATORIOS -->
            <div id="tab-labs" class="tab-content">
                <div class="content-section animate-in">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="bi bi-droplet-half section-title-icon" style="color: var(--color-info);"></i>
                            Reporte Detallado de Laboratorios
                        </h3>
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <span class="amount-badge income">
                                Total: Q<?php echo number_format($total_labs_report, 2); ?>
                            </span>
                            <?php if ($user_type === 'admin'): ?>
                                    <a href="export_labs.php?start=<?php echo $profit_start; ?>&end=<?php echo $profit_end; ?>"
                                        target="_blank" class="action-btn" style="background: var(--color-success)">
                                        <i class="bi bi-file-earmark-excel me-2"></i>
                                        Exportar Laboratorios
                                    </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="custom-accordion-wrapper" id="labsAccordion">
                        <?php if (empty($grouped_labs)): ?>
                                <div class="empty-report-hint">
                                    <i class="bi bi-droplet-half"></i>
                                    No se encontraron laboratorios realizados en este período.
                                </div>
                        <?php else: ?>
                                <?php
                                // Obtener el nombre del mes actual para expandirlo por defecto
                                $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                                $mes_actual_nombre = $meses[(int) date('n') - 1] . ' ' . date('Y');

                                $mes_id = 0;
                                foreach ($grouped_labs as $mes_nombre => $mes_data):
                                    $mes_id++;
                                    $is_current_month = ($mes_nombre === $mes_actual_nombre);
                                    ?>
                                        <details class="report-details level-1" name="mes_accordion" <?php echo $is_current_month ? 'open' : ''; ?>>
                                            <summary class="custom-summary">
                                                <div class="d-flex align-items-center">
                                                    <i class="bi bi-calendar3 me-2 text-primary"></i>
                                                    <span><?php echo $mes_nombre; ?></span>
                                                    <span class="badge bg-primary ms-3 rounded-pill"><?php echo $mes_data['count']; ?>
                                                        labs</span>
                                                </div>
                                                <span class="text-success">Q <?php echo number_format($mes_data['total'], 2); ?></span>
                                            </summary>
                                            <div class="report-details-body">
                                                <?php $dia_id = 0;
                                                foreach ($mes_data['dias'] as $dia_str => $dia_data):
                                                    $dia_id++; ?>
                                                        <details class="report-details level-2" name="dia_accordion_<?php echo $mes_id; ?>">
                                                            <summary class="custom-summary">
                                                                <div class="d-flex align-items-center">
                                                                    <i class="bi bi-calendar-day me-2 text-info"></i>
                                                                    <span>Día: <?php echo $dia_str; ?></span>
                                                                    <span
                                                                        class="badge bg-info text-dark ms-3 rounded-pill"><?php echo $dia_data['count']; ?>
                                                                        labs</span>
                                                                </div>
                                                                <span class="text-success fw-semibold">Q
                                                                    <?php echo number_format($dia_data['total'], 2); ?></span>
                                                            </summary>
                                                            <div class="report-details-body">
                                                                <?php $pac_id = 0;
                                                                foreach ($dia_data['pacientes'] as $paciente_nombre => $pac_data):
                                                                    $pac_id++; ?>
                                                                        <details class="report-details level-3"
                                                                            name="paciente_accordion_<?php echo $mes_id . '_' . $dia_id; ?>">
                                                                            <summary class="custom-summary">
                                                                                <div class="d-flex align-items-center">
                                                                                    <i class="bi bi-person me-2 text-secondary"></i>
                                                                                    <span><?php echo htmlspecialchars($paciente_nombre); ?></span>
                                                                                    <span
                                                                                        class="badge bg-secondary ms-3 rounded-pill"><?php echo $pac_data['count']; ?>
                                                                                        labs</span>
                                                                                </div>
                                                                                <span class="text-success fw-medium">Q
                                                                                    <?php echo number_format($pac_data['total'], 2); ?></span>
                                                                            </summary>
                                                                            <div class="report-details-body p-0">
                                                                                <div class="table-responsive">
<table class="lab-items-table">
                                            <thead>
                                                <tr>
                                                    <th>Examen (Prueba)</th>
                                                    <th>Hora</th>
                                                    <th>Laboratorio</th>
                                                    <th class="text-end">Precio</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pac_data['labs'] as $lab): ?>
                                                        <tr>
                                                            <td>
                                                                <span class="charge-type-badge charge-laboratorio">
                                                                    <i class="bi bi-droplet-half"></i>
                                                                    <?php echo htmlspecialchars($lab['nombre_prueba']); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <small class="text-muted"><?php echo date('h:i A', strtotime($lab['hora'])); ?></small>
                                                            </td>
                                                            <td>
                                                                <?php if (!empty($lab['laboratorio_externo'])): ?>
                                                                    <span class="badge bg-info-subtle text-info border border-info-subtle">
                                                                        <i class="bi bi-building me-1"></i>
                                                                        <?php echo htmlspecialchars($lab['laboratorio_externo']); ?>
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="text-muted">—</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-end">
                                                                <span class="amount-badge income">
                                                                    Q<?php echo number_format($lab['precio'], 2); ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                                                                </div>
                                                                            </div>
                                                                        </details>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </details>
                                                <?php endforeach; ?>
                                            </div>
                                        </details>
                                <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- TAB 5: REPORTES DE TRASLADOS -->
            <?php if ($can_view_transfers): ?>
                    <div id="tab-transfers" class="tab-content">
                        <div class="content-section animate-in">
                            <div class="section-header">
                                <h3 class="section-title">
                                    <i class="bi bi-arrow-left-right section-title-icon"
                                        style="color: var(--color-danger);"></i>
                                    Reporte de Traslados (Dispensario)
                                </h3>
                                <div class="d-flex align-items-center gap-3 flex-wrap">
                                    <span class="amount-badge expense">
                                        Valor Total: Q<?php echo number_format($total_transfers_amount, 2); ?>
                                    </span>
                                    <a href="export_transfers.php?start=<?php echo $profit_start; ?>&end=<?php echo $profit_end; ?>"
                                        target="_blank" class="action-btn" style="background: var(--color-success)">
                                        <i class="bi bi-file-earmark-excel me-2"></i>
                                        Exportar Traslados
                                    </a>
                                </div>
                            </div>

                            <?php if (!empty($transfers_data)): ?>
                                    <div class="custom-accordion-wrapper">
                                        <?php foreach ($transfers_data as $mes_nombre => $mes_data): ?>
                                                <details class="report-details level-1" open>
                                                    <summary class="custom-summary">
                                                        <div class="d-flex align-items-center gap-3">
                                                            <i class="bi bi-calendar3 text-primary"></i>
                                                            <span><?php echo htmlspecialchars($mes_nombre); ?></span>
                                                            <span
                                                                class="badge bg-primary-subtle text-primary"><?php echo count($mes_data['dias']); ?>
                                                                día(s)</span>
                                                        </div>
                                                        <span
                                                            class="amount-badge expense">Q<?php echo number_format($mes_data['total'], 2); ?></span>
                                                    </summary>
                                                    <div class="report-details-body">
                                                        <?php foreach ($mes_data['dias'] as $dia_str => $dia_data): ?>
                                                                <details class="report-details level-2" open>
                                                                    <summary class="custom-summary">
                                                                        <div class="d-flex align-items-center gap-2">
                                                                            <i class="bi bi-calendar-event text-info"></i>
                                                                            <span><?php echo htmlspecialchars($dia_str); ?></span>
                                                                            <span
                                                                                class="badge bg-info-subtle text-info"><?php echo count($dia_data['destinos']); ?>
                                                                                destino(s)</span>
                                                                        </div>
                                                                        <span
                                                                            class="amount-badge expense">Q<?php echo number_format($dia_data['total'], 2); ?></span>
                                                                    </summary>
                                                                    <div class="report-details-body">
                                                                        <?php foreach ($dia_data['destinos'] as $destino => $dest_data): ?>
                                                                                <details class="report-details level-3">
                                                                                    <summary class="custom-summary">
                                                                                        <div class="d-flex align-items-center gap-2">
                                                                                            <i class="bi bi-person text-secondary"></i>
                                                                                            <span><?php echo htmlspecialchars($destino); ?></span>
                                                                                            <span
                                                                                                class="badge bg-secondary-subtle text-secondary"><?php echo count($dest_data['items']); ?>
                                                                                                item(s)</span>
                                                                                        </div>
                                                                                        <span
                                                                                            class="amount-badge expense">Q<?php echo number_format($dest_data['total'], 2); ?></span>
                                                                                    </summary>
                                                                                    <div class="report-details-body">
                                                                                        <table class="lab-items-table">
                                                                                            <thead>
                                                                                                <tr>
                                                                                                    <th>Medicamento</th>
                                                                                                    <th class="text-center">Cant.</th>
                                                                                                    <th>Realizado por</th>
                                                                                                    <th class="text-end">Valor</th>
                                                                                                </tr>
                                                                                            </thead>
                                                                                            <tbody>
                                                                                                <?php foreach ($dest_data['items'] as $item): ?>
                                                                                                        <tr>
                                                                                                            <td><?php echo htmlspecialchars($item['nom_medicamento']); ?>
                                                                                                            </td>
                                                                                                            <td class="text-center">
                                                                                                                <span
                                                                                                                    class="badge bg-light text-dark border"><?php echo $item['cantidad_vendida']; ?></span>
                                                                                                            </td>
                                                                                                            <td><?php echo htmlspecialchars($item['realizado_por']); ?>
                                                                                                            </td>
                                                                                                            <td class="text-end fw-bold text-danger">
                                                                                                                Q<?php echo number_format($item['valor_traslado'], 2); ?>
                                                                                                            </td>
                                                                                                        </tr>
                                                                                                <?php endforeach; ?>
                                                                                            </tbody>
                                                                                        </table>
                                                                                    </div>
                                                                                </details>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                </details>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </details>
                                        <?php endforeach; ?>
                                    </div>
                            <?php else: ?>
                                    <div class="empty-report-hint">
                                        <i class="bi bi-arrow-left-right"></i>
                                        <p class="mb-0">No se encontraron traslados en este período.</p>
                                    </div>
                            <?php endif; ?>
                        </div>
                    </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Categoría Desglose Modal -->
    <div id="categoriaDesgloseModal" class="custom-modal-overlay" style="display:none;">
        <div class="custom-modal-container">
            <div class="custom-modal-header">
                <h5 class="custom-modal-title">
                    <i class="bi bi-list-ul me-2"></i>
                    Detalle: <span id="desgloseTitulo"></span>
                </h5>
                <button type="button" class="custom-modal-close" onclick="closeDesgloseModal()">&times;</button>
            </div>
            <div class="custom-modal-body" id="desgloseBody">
                <div class="desglose-loading">
                    <i class="bi bi-arrow-clockwise"></i>
                    Cargando detalle...
                </div>
            </div>
            <div class="custom-modal-footer">
                <div class="d-flex gap-4 align-items-center">
                    <div>
                        <div class="total-label">Total Ingresos</div>
                        <span class="total-amount" id="desgloseTotal">Q0.00</span>
                    </div>
                    <div id="desgloseCostoFooter">
                        <div class="total-label">Total Costo</div>
                        <span class="total-amount" id="desgloseTotalCosto" style="color:var(--color-danger);">Q0.00</span>
                    </div>
                    <div id="desgloseProfitFooter">
                        <div class="total-label">Ganancia</div>
                        <span class="total-amount" id="desgloseProfit" style="color:var(--color-success);">Q0.00</span>
                    </div>
                </div>
                <button type="button" class="action-btn secondary" onclick="closeDesgloseModal()">
                    <i class="bi bi-x-lg"></i> Cerrar
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript Optimizado -->
    <script>
        // Módulo de Reportes - Centro Médico Herrera Saenz
        // JavaScript para funcionalidades del módulo de reportes

        (function () {
            'use strict';

            // ==========================================================================
            // CONFIGURACIÓN Y CONSTANTES
            // ==========================================================================
            const CONFIG = {
                themeKey: 'dashboard-theme',

                transitionDuration: 300
            };

            // ==========================================================================
            // REFERENCIAS A ELEMENTOS DOM
            // ==========================================================================
            const DOM = {
                html: document.documentElement,
                body: document.body,
                themeSwitch: document.getElementById('themeSwitch')
            };

            // ==========================================================================
            // MANEJO DE TEMA (DÍA/NOCHE)
            // ==========================================================================
            class ThemeManager {
                constructor() {
                    this.theme = this.getInitialTheme();
                    this.applyTheme(this.theme);
                    this.setupEventListeners();
                }

                getInitialTheme() {
                    const savedTheme = localStorage.getItem(CONFIG.themeKey);
                    if (savedTheme) return savedTheme;

                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    if (prefersDark) return 'dark';

                    return 'light';
                }

                applyTheme(theme) {
                    DOM.html.setAttribute('data-theme', theme);
                    localStorage.setItem(CONFIG.themeKey, theme);

                    const metaTheme = document.querySelector('meta[name="theme-color"]');
                    if (metaTheme) {
                        metaTheme.setAttribute('content', theme === 'dark' ? '#0f172a' : '#ffffff');
                    }
                }

                toggleTheme() {
                    const newTheme = this.theme === 'light' ? 'dark' : 'light';
                    this.theme = newTheme;
                    this.applyTheme(newTheme);

                    if (DOM.themeSwitch) {
                        DOM.themeSwitch.style.transform = 'rotate(180deg)';
                        setTimeout(() => {
                            DOM.themeSwitch.style.transform = 'rotate(0)';
                        }, CONFIG.transitionDuration);
                    }

                    // Notificar actualización de gráficos
                    if (window.dashboard && window.dashboard.animations) {
                        window.dashboard.animations.updateCharts();
                    }
                }

                setupEventListeners() {
                    if (DOM.themeSwitch) {
                        DOM.themeSwitch.addEventListener('click', () => this.toggleTheme());
                    }

                    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                        if (!localStorage.getItem(CONFIG.themeKey)) {
                            this.theme = e.matches ? 'dark' : 'light';
                            this.applyTheme(this.theme);
                            if (window.dashboard && window.dashboard.animations) {
                                window.dashboard.animations.updateCharts();
                            }
                        }
                    });
                }
            }

            // ==========================================================================
            // DELEGADO DE PESTAÑAS (TAB MANAGER)
            // ==========================================================================
            class TabManager {
                constructor() {
                    this.tabs = document.querySelectorAll('.reports-tab-btn');
                    this.contents = document.querySelectorAll('.tab-content');
                    this.activeTab = localStorage.getItem('reports-active-tab') || 'overview';
                    this.init();
                }

                init() {
                    if (this.tabs.length === 0) return;

                    // Escuchar clics en las pestañas
                    this.tabs.forEach(btn => {
                        btn.addEventListener('click', () => {
                            const tabId = btn.getAttribute('data-tab');
                            this.switchTab(tabId);
                        });
                    });

                    // Cargar pestaña inicial
                    this.switchTab(this.activeTab, true);
                }

                switchTab(tabId, immediate = false) {
                    const targetBtn = document.querySelector(`.reports-tab-btn[data-tab="${tabId}"]`);
                    const targetContent = document.getElementById(`tab-${tabId}`);

                    if (!targetBtn || !targetContent) {
                        // Fallback si no existe la pestaña activa guardada (ej. si no tiene permisos para transfers)
                        if (tabId !== 'overview') {
                            this.switchTab('overview', immediate);
                        }
                        return;
                    }

                    this.activeTab = tabId;
                    localStorage.setItem('reports-active-tab', tabId);

                    // Actualizar botones
                    this.tabs.forEach(btn => btn.classList.remove('active'));
                    targetBtn.classList.add('active');

                    if (immediate) {
                        this.contents.forEach(content => {
                            content.classList.remove('active');
                            content.style.display = 'none';
                            content.style.opacity = '0';
                            content.style.transform = 'translateY(10px)';
                        });
                        targetContent.style.display = 'block';
                        // Forzar reflow
                        targetContent.offsetHeight;
                        targetContent.classList.add('active');
                        targetContent.style.opacity = '1';
                        targetContent.style.transform = 'translateY(0)';
                    } else {
                        // Animación suave de salida/entrada
                        const activeContents = Array.from(this.contents).filter(c => c.classList.contains('active'));

                        if (activeContents.length > 0 && activeContents[0] !== targetContent) {
                            activeContents.forEach(content => {
                                content.style.opacity = '0';
                                content.style.transform = 'translateY(10px)';
                                setTimeout(() => {
                                    content.classList.remove('active');
                                    content.style.display = 'none';

                                    // Entrada de la nueva pestaña
                                    targetContent.style.display = 'block';
                                    setTimeout(() => {
                                        targetContent.classList.add('active');
                                        targetContent.style.opacity = '1';
                                        targetContent.style.transform = 'translateY(0)';
                                    }, 20);
                                }, 150);
                            });
                        } else if (activeContents.length === 0) {
                            targetContent.style.display = 'block';
                            setTimeout(() => {
                                targetContent.classList.add('active');
                                targetContent.style.opacity = '1';
                                targetContent.style.transform = 'translateY(0)';
                            }, 20);
                        }
                    }
                }
            }

            // ==========================================================================
            // ANIMACIONES (GRÁFICOS CHART.JS ELIMINADOS)
            // ==========================================================================
            class AnimationManager {
                constructor() {
                    this.setupAnimations();
                }

                setupAnimations() {
                    const observerOptions = {
                        root: null,
                        rootMargin: '0px',
                        threshold: 0.1
                    };

                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                entry.target.classList.add('animate-in');
                                observer.unobserve(entry.target);
                            }
                        });
                    }, observerOptions);

                    document.querySelectorAll('.stat-card, .content-section, .filter-panel').forEach(el => {
                        observer.observe(el);
                    });
                }

                updateCharts() {
                    // No-op: charts removed
                }
            }

            // ==========================================================================
            // INICIALIZACIÓN DE LA APLICACIÓN
            // ==========================================================================
            document.addEventListener('DOMContentLoaded', () => {
                // Inicializar componentes
                const themeManager = new ThemeManager();
                const animationManager = new AnimationManager();
                const tabManager = new TabManager();

                // Exponer APIs necesarias globalmente
                window.dashboard = {
                    theme: themeManager,
                    animations: animationManager,
                    tabs: tabManager
                };

                // ==========================================================================
                // MODAL DE DESGLOSE POR CATEGORÍA
                // ==========================================================================
                window.openCategoriaDesglose = function(el) {
                    const categoria = el.getAttribute('data-categoria');
                    const container = document.querySelector('.accounting-body');
                    const start = container.getAttribute('data-period-start');
                    const end = container.getAttribute('data-period-end');
                    const label = el.querySelector('.accounting-ledger__label-text')?.textContent || categoria;

                    document.getElementById('desgloseTitulo').textContent = label;
                    document.getElementById('desgloseBody').innerHTML =
                        '<div class="desglose-loading"><i class="bi bi-arrow-clockwise"></i>Cargando detalle...</div>';
                    document.getElementById('desgloseTotal').textContent = 'Q0.00';
                    document.getElementById('desgloseTotalCosto').textContent = 'Q0.00';
                    document.getElementById('desgloseProfit').textContent = 'Q0.00';
                    document.getElementById('desgloseProfit').style.color = 'var(--color-success)';
                    document.getElementById('desgloseCostoFooter').style.display = '';
                    document.getElementById('desgloseProfitFooter').style.display = '';
                    document.getElementById('categoriaDesgloseModal').style.display = 'flex';

                    const url = 'get_categoria_desglose.php?categoria=' + encodeURIComponent(categoria) +
                        '&start=' + encodeURIComponent(start) +
                        '&end=' + encodeURIComponent(end) +
                        '&id_hospital=<?php echo $id_hospital; ?>';

                    fetch(url)
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data.error) {
                                document.getElementById('desgloseBody').innerHTML =
                                    '<div class="desglose-empty"><i class="bi bi-exclamation-triangle"></i><p>' +
                                    data.error + '</p></div>';
                                return;
                            }
                            renderDesgloseTable(data);
                        })
                        .catch(function(err) {
                            document.getElementById('desgloseBody').innerHTML =
                                '<div class="desglose-empty"><i class="bi bi-exclamation-triangle"></i><p>Error al cargar datos.</p></div>';
                            console.error(err);
                        });
                };

                window.closeDesgloseModal = function() {
                    document.getElementById('categoriaDesgloseModal').style.display = 'none';
                };

                function renderDesgloseTable(data) {
                    var rows = data.rows || [];
                    var totalMonto = data.total_monto || 0;
                    var totalCosto = data.total_costo || 0;
                    var totalProfit = data.total_profit || 0;
                    var hasCosto = data.has_costo !== false;

                    if (rows.length === 0) {
                        document.getElementById('desgloseBody').innerHTML =
                            '<div class="desglose-empty"><i class="bi bi-inbox"></i><p>No hay registros para este período.</p></div>';
                        document.getElementById('desgloseTotal').textContent = 'Q0.00';
                        document.getElementById('desgloseTotalCosto').textContent = 'Q0.00';
                        document.getElementById('desgloseProfit').textContent = 'Q0.00';
                        document.getElementById('desgloseCostoFooter').style.display = hasCosto ? '' : 'none';
                        document.getElementById('desgloseProfitFooter').style.display = hasCosto ? '' : 'none';
                        return;
                    }

                    document.getElementById('desgloseCostoFooter').style.display = hasCosto ? '' : 'none';
                    document.getElementById('desgloseProfitFooter').style.display = hasCosto ? '' : 'none';

                    if (data.categoria === 'hospitalizacion') {
                        renderHospitalizacionAcordeon(rows, hasCosto);
                        document.getElementById('desgloseTotal').textContent = 'Q' + formatNumber(totalMonto);
                        document.getElementById('desgloseTotalCosto').textContent = 'Q' + formatNumber(totalCosto);
                        document.getElementById('desgloseProfit').textContent = 'Q' + formatNumber(totalProfit);
                        document.getElementById('desgloseProfit').style.color = totalProfit >= 0 ? 'var(--color-success)' : 'var(--color-danger)';
                        return;
                    }

            var isLab = (data.categoria === 'laboratorio');
            var html = '<table class="desglose-table"><thead><tr>' +
                '<th class="row-num">#</th>' +
                '<th>Fecha</th>' +
                '<th>Paciente</th>' +
                '<th>Descripción</th>' +
                (isLab ? '<th>Laboratorio</th>' : '') +
                '<th class="text-end">Monto</th>' +
                (hasCosto ? '<th class="text-end">Costo</th><th class="text-end">Ganancia</th>' : '') +
                '</tr></thead><tbody>';

            for (var i = 0; i < rows.length; i++) {
                var r = rows[i];
                var profit = (r.profit !== undefined ? r.profit : r.monto - (r.costo || 0));
                var profitClass = profit >= 0 ? 'text-success' : 'text-danger';
                html += '<tr>' +
                    '<td class="row-num">' + (i + 1) + '</td>' +
                    '<td>' + (r.fecha || '') + '</td>' +
                    '<td>' + escapeHtml(r.paciente || '') + '</td>' +
                    '<td>' + escapeHtml(r.descripcion || '') + '</td>';
                if (isLab) {
                    html += '<td class="text-muted small">' + escapeHtml(r.laboratorio || '—') + '</td>';
                }
                html += '<td class="text-end fw-bold text-success">Q' + formatNumber(r.monto) + '</td>';
                if (hasCosto) {
                    html += '<td class="text-end text-danger">Q' + formatNumber(r.costo || 0) + '</td>' +
                        '<td class="text-end fw-bold ' + profitClass + '">Q' + formatNumber(profit) + '</td>';
                }
                html += '</tr>';
            }

                    html += '</tbody></table>';
                    document.getElementById('desgloseBody').innerHTML = html;
                    document.getElementById('desgloseTotal').textContent = 'Q' + formatNumber(totalMonto);
                    document.getElementById('desgloseTotalCosto').textContent = 'Q' + formatNumber(totalCosto);
                    document.getElementById('desgloseProfit').textContent = 'Q' + formatNumber(totalProfit);
                    document.getElementById('desgloseProfit').style.color = totalProfit >= 0 ? 'var(--color-success)' : 'var(--color-danger)';
                }

                function escapeHtml(str) {
                    var div = document.createElement('div');
                    div.appendChild(document.createTextNode(str));
                    return div.innerHTML;
                }

                function formatNumber(n) {
                    return Number(n).toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                function renderHospitalizacionAcordeon(rows, hasCosto) {
                    var grupos = {};
                    for (var i = 0; i < rows.length; i++) {
                        var r = rows[i];
                        var paciente = r.paciente || '—';
                        if (!grupos[paciente]) {
                            grupos[paciente] = { rows: [], totalMonto: 0, totalCosto: 0 };
                        }
                        grupos[paciente].rows.push(r);
                        grupos[paciente].totalMonto += r.monto;
                        grupos[paciente].totalCosto += (r.costo || 0);
                    }

                    var pacientes = Object.keys(grupos).sort(function(a, b) {
                        return grupos[b].totalMonto - grupos[a].totalMonto;
                    });

                    var html = '<table class="desglose-table"><thead><tr>' +
                        '<th style="width:32px"></th>' +
                        '<th>Paciente</th>' +
                        '<th class="text-end">Total Monto</th>';
                    if (hasCosto) {
                        html += '<th class="text-end">Total Costo</th><th class="text-end">Total Ganancia</th>';
                    }
                    html += '</tr></thead><tbody>';

                    for (var i = 0; i < pacientes.length; i++) {
                        var p = pacientes[i];
                        var g = grupos[p];
                        var profit = g.totalMonto - g.totalCosto;
                        var profitClass = profit >= 0 ? 'text-success' : 'text-danger';
                        var detailId = 'detalle-paciente-' + i;

                        html += '<tr class="paciente-row" onclick="togglePacienteDetalle(\'' + detailId + '\', this)">' +
                            '<td class="text-center"><i class="bi bi-chevron-right paciente-icon"></i></td>' +
                            '<td><strong>' + escapeHtml(p) + '</strong> <span class="text-muted small">(' + g.rows.length + ' cargos)</span></td>' +
                            '<td class="text-end fw-bold text-success">Q' + formatNumber(g.totalMonto) + '</td>';
                        if (hasCosto) {
                            html += '<td class="text-end text-danger">Q' + formatNumber(g.totalCosto) + '</td>' +
                                '<td class="text-end fw-bold ' + profitClass + '">Q' + formatNumber(profit) + '</td>';
                        }
                        html += '</tr>';
                        html += '<tr id="' + detailId + '" class="detalle-row" style="display:none">' +
                            '<td colspan="' + (hasCosto ? 5 : 3) + '" style="padding:0">' +
                            '<table class="sub-table"><thead><tr>' +
                            '<th>Fecha</th><th>Descripción</th>' +
                            '<th class="text-end">Monto</th>';
                        if (hasCosto) {
                            html += '<th class="text-end">Costo</th><th class="text-end">Ganancia</th>';
                        }
                        html += '</tr></thead><tbody>';

                        for (var j = 0; j < g.rows.length; j++) {
                            var r = g.rows[j];
                            var rProfit = (r.profit !== undefined ? r.profit : r.monto - (r.costo || 0));
                            var rProfitClass = rProfit >= 0 ? 'text-success' : 'text-danger';
                            html += '<tr>' +
                                '<td>' + (r.fecha || '') + '</td>' +
                                '<td>' + escapeHtml(r.descripcion || '') + '</td>' +
                                '<td class="text-end text-success">Q' + formatNumber(r.monto) + '</td>';
                            if (hasCosto) {
                                html += '<td class="text-end text-danger">Q' + formatNumber(r.costo || 0) + '</td>' +
                                    '<td class="text-end fw-bold ' + rProfitClass + '">Q' + formatNumber(rProfit) + '</td>';
                            }
                            html += '</tr>';
                        }

                        html += '</tbody></table>' +
                            '</td></tr>';
                    }

                    html += '</tbody></table>';
                    document.getElementById('desgloseBody').innerHTML = html;
                }

                window.togglePacienteDetalle = function(id, row) {
                    var detalle = document.getElementById(id);
                    var icon = row.querySelector('.paciente-icon');
                    if (detalle.style.display === 'none') {
                        detalle.style.display = '';
                        icon.className = 'bi bi-chevron-down paciente-icon';
                    } else {
                        detalle.style.display = 'none';
                        icon.className = 'bi bi-chevron-right paciente-icon';
                    }
                };

                // ==========================================================================
                // SWITCH FILTRO TIPO (JORNADA / MES)
                // ==========================================================================
                window.switchFiltroTipo = function(tipo) {
                    document.getElementById('filtro_tipo').value = tipo;
                    document.getElementById('jornadaInput').style.display = tipo === 'mes' ? 'none' : '';
                    document.getElementById('mesInput').style.display = tipo === 'jornada' ? 'none' : '';
                };

                // Close modal on overlay click
                document.addEventListener('click', function(e) {
                    var modal = document.getElementById('categoriaDesgloseModal');
                    if (e.target === modal) {
                        modal.style.display = 'none';
                    }
                });

                // Close modal on Escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        var modal = document.getElementById('categoriaDesgloseModal');
                        if (modal.style.display === 'flex') {
                            modal.style.display = 'none';
                        }
                    }
                });

                // Log de inicialización
                console.log('Módulo de Reportes - CMS v4.0 (Premium Overhauled)');
                console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
                console.log('Rol: <?php echo htmlspecialchars($user_type); ?>');
                console.log('Periodo: <?php echo $fecha_inicio; ?> - <?php echo $fecha_fin; ?>');
            });

            // ==========================================================================
            // POLYFILLS
            // ==========================================================================
            if (!NodeList.prototype.forEach) {
                NodeList.prototype.forEach = Array.prototype.forEach;
            }

        })();
    </script>
    <script>
        function showPdfLoading() { Swal.fire({ title: 'Generando reporte...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } }); }
    </script>
</body>

</html>