<?php
// export_auditoria_contable.php
// Genera PDF ejecutivo con toda la información contable del período.
// Acceso: solo admin. Usa Dompdf para la conversión HTML → PDF.

session_start();

if (!isset($_SESSION['user_id'])) {
    die('No autorizado');
}
if (($_SESSION['tipoUsuario'] ?? '') !== 'admin') {
    die('Acceso denegado. Solo administradores.');
}

require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

date_default_timezone_set('America/Guatemala');
verify_session();

$user_name = $_SESSION['nombre'] ?? $_SESSION['usuario'] ?? 'Administrador';
$user_id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date = $_GET['end'] ?? date('Y-m-d');

$start_datetime = $start_date . ' 00:00:00';
$end_datetime = $end_date . ' 23:59:59';

try {
    $database = new Database();
    $conn = $database->getConnection();
    $id_hospital = $user_id_hospital;

    // =========================================================================
    // RECOPILACIÓN DE DATOS (mismas queries que reports/index.php)
    // =========================================================================

    // === INGRESOS ===
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total), 0) FROM ventas WHERE fecha_venta BETWEEN ? AND ? AND id_hospital = ? AND tipo_pago != 'Traslado'");
    $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_sales_meds = (float)$stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COALESCE(SUM(total), 0) FROM ventas WHERE fecha_venta BETWEEN ? AND ? AND id_hospital = ? AND tipo_pago = 'Traslado'");
    $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_traslados = (float)$stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COALESCE(SUM(cobro), 0) FROM procedimientos_menores WHERE fecha_procedimiento BETWEEN ? AND ? AND id_hospital = ?");
    $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_procedures = (float)$stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COALESCE(SUM(cantidad_consulta), 0) FROM cobros WHERE fecha_consulta BETWEEN ? AND ? AND id_hospital = ?");
    $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_billings = (float)$stmt->fetchColumn();

    // Hospitalización (sólo dados de alta)
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_general), 0) FROM cuenta_hospitalaria ch JOIN encamamientos e ON ch.id_encamamiento = e.id_encamamiento WHERE e.fecha_alta BETWEEN ? AND ? AND e.id_hospital = ?");
    $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_hospitalization = (float)$stmt->fetchColumn();

    // Examenes: labs, ultrasonido, rayos x, electro
    $stmt = $conn->prepare("SELECT COALESCE(SUM(cobro), 0) FROM examenes_realizados WHERE fecha_examen BETWEEN ? AND ? AND id_hospital = ? AND (tipo_examen NOT LIKE '%ultrasonido%' AND tipo_examen NOT LIKE '%rayos x%' AND tipo_examen NOT LIKE '%rx%')");
    $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_laboratory = (float)$stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COALESCE(SUM(cobro), 0) FROM examenes_realizados WHERE fecha_examen BETWEEN ? AND ? AND id_hospital = ? AND tipo_examen LIKE '%ultrasonido%'");
    $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_ultrasound_legacy = (float)$stmt->fetchColumn();
    $stmt = $conn->prepare("SELECT COALESCE(SUM(cobro), 0) FROM ultrasonidos WHERE fecha_ultrasonido BETWEEN ? AND ? AND id_hospital = ?");
    $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_ultrasound = (float)$stmt->fetchColumn() + $total_ultrasound_legacy;

    $stmt = $conn->prepare("SELECT COALESCE(SUM(cobro), 0) FROM examenes_realizados WHERE fecha_examen BETWEEN ? AND ? AND id_hospital = ? AND (tipo_examen LIKE '%rayos x%' OR tipo_examen LIKE '%rx%')");
    $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_xray_legacy = (float)$stmt->fetchColumn();
    $stmt = $conn->prepare("SELECT COALESCE(SUM(cobro), 0) FROM rayos_x WHERE fecha_estudio BETWEEN ? AND ? AND id_hospital = ?");
    $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_xray = (float)$stmt->fetchColumn() + $total_xray_legacy;

    $stmt = $conn->prepare("SELECT COALESCE(SUM(precio), 0) FROM electrocardiogramas WHERE fecha_realizado BETWEEN ? AND ? AND id_hospital = ?");
    $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_electro = (float)$stmt->fetchColumn();

    $total_gross_revenue = $total_sales_meds + $total_procedures + $total_laboratory + $total_ultrasound + $total_xray + $total_electro + $total_billings + $total_hospitalization;

    // === EGRESOS ===
    $stmt = $conn->prepare("SELECT COALESCE(SUM(pp.amount), 0) FROM purchase_payments pp JOIN purchase_headers ph ON pp.purchase_header_id = ph.id WHERE pp.payment_date BETWEEN ? AND ? AND pp.id_hospital = ? AND pp.payment_method != 'Traslado'");
    $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_purchases_meds = (float)$stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COALESCE(SUM(pp.amount), 0) FROM purchase_payments pp WHERE pp.payment_date BETWEEN ? AND ? AND pp.id_hospital = ? AND pp.payment_method = 'Traslado'");
    $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_pagos_traslado = (float)$stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COALESCE(SUM(total), 0) FROM gastos WHERE fecha BETWEEN ? AND ? AND id_hospital = ?");
    $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_gastos = (float)$stmt->fetchColumn();

    $total_egresos = $total_purchases_meds + $total_gastos + $total_pagos_traslado;

    // Costos farmacia (purchase_items JOIN)
    $stmt = $conn->prepare("SELECT COALESCE(SUM(dv.cantidad_vendida * COALESCE(pi.unit_cost, 0)), 0) FROM detalle_ventas dv JOIN ventas v ON dv.id_venta = v.id_venta JOIN inventario i ON dv.id_inventario = i.id_inventario LEFT JOIN purchase_items pi ON i.id_purchase_item = pi.id WHERE v.fecha_venta BETWEEN ? AND ? AND v.id_hospital = ? AND v.tipo_pago != 'Traslado'");
    $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
    $sales_cost = (float)$stmt->fetchColumn();

    // Costos de tarifas (consultas + ultrasonido + rayos x + electro + procedimientos)
    // Para simplificar usamos heurística conservadora: costo = 30% del cobro en promedio
    $tarifas_cost_approx = ($total_procedures + $total_ultrasound + $total_xray + $total_electro + $total_billings) * 0.30;

    $all_sources_cost = $sales_cost + $tarifas_cost_approx;
    $total_gross_profit = $total_gross_revenue - $all_sources_cost;
    $net_cash_flow = $total_gross_revenue - $total_egresos;

    // === RATIOS ===
    $stmt = $conn->prepare("SELECT COALESCE(AVG((stock_hospital + stock_quirofano) * COALESCE(pi.unit_cost, 0)), 0) FROM inventario i LEFT JOIN purchase_items pi ON i.id_purchase_item = pi.id WHERE i.id_hospital = ? AND i.estado = 'Disponible'");
    $stmt->execute([$id_hospital]);
    $inventario_promedio = (float)$stmt->fetchColumn();
    $rotacion_inventario = ($inventario_promedio > 0) ? ($sales_cost / $inventario_promedio) : 0;

    $stmt = $conn->prepare("SELECT COALESCE(SUM(saldo_pendiente), 0) FROM cuenta_hospitalaria WHERE id_hospital = ? AND estado_pago NOT IN ('Pagado','Condonado') AND saldo_pendiente > 0");
    $stmt->execute([$id_hospital]);
    $cxc_total = (float)$stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount - COALESCE(paid_amount, 0)), 0) FROM purchase_headers WHERE id_hospital = ? AND payment_status != 'Pagado'");
    $stmt->execute([$id_hospital]);
    $cxp_total = (float)$stmt->fetchColumn();

    $dias_periodo = max(1, (strtotime($end_datetime) - strtotime($start_datetime)) / 86400);
    $ingresos_diarios = $total_gross_revenue / $dias_periodo;
    $dso = ($ingresos_diarios > 0) ? ($cxc_total / $ingresos_diarios) : 0;
    $compras_diarias = max(1, $total_purchases_meds / $dias_periodo);
    $dpo = ($compras_diarias > 0) ? ($cxp_total / $compras_diarias) : 0;
    $costo_diario = max(1, $sales_cost / $dias_periodo);
    $dio = ($costo_diario > 0) ? ($inventario_promedio / $costo_diario) : 0;
    $cce = $dio + $dso - $dpo;

    $depreciacion_estimada = $total_egresos * 0.05;
    $ebitda = $total_gross_profit - $depreciacion_estimada;

    $margen_bruto_pct = ($total_gross_revenue > 0) ? ($total_gross_profit / $total_gross_revenue) * 100 : 0;
    $margen_operativo_pct = ($total_gross_revenue > 0) ? (($total_gross_profit - $depreciacion_estimada) / $total_gross_revenue) * 100 : 0;
    $margen_neto_pct = ($total_gross_revenue > 0) ? ($net_cash_flow / $total_gross_revenue) * 100 : 0;
    $activos_proxy = $inventario_promedio + $cxc_total;
    $roi_pct = ($activos_proxy > 0) ? ($net_cash_flow / $activos_proxy) * 100 : 0;
    $patrimonio_estimado = max(1, $total_gross_profit * 12);
    $apalancamiento = ($cxp_total + $patrimonio_estimado) > 0 ? ($cxp_total / ($cxp_total + $patrimonio_estimado)) : 0;

    $costos_fijos = $total_gastos * 0.7;
    $costos_variables = $total_purchases_meds + $sales_cost;
    $punto_equilibrio = 0;
    if ($total_gross_revenue > $costos_variables) {
        $ratio_mc = 1 - ($costos_variables / $total_gross_revenue);
        $punto_equilibrio = ($ratio_mc > 0) ? ($costos_fijos / $ratio_mc) : 0;
    }

    // ============ CANTIDADES ABSOLUTAS ============
    // 1) Valor de COMPRA en inventario (snapshot actual)
    $stmt = $conn->prepare("SELECT COALESCE(SUM((stock_hospital + stock_quirofano) * precio_compra), 0)
                           FROM inventario WHERE id_hospital = ? AND estado = 'Disponible'");
    $stmt->execute([$id_hospital]);
    $inv_valor_compra = (float)$stmt->fetchColumn();

    // 2) Valor de VENTA en inventario (snapshot actual)
    $stmt = $conn->prepare("SELECT COALESCE(SUM((stock_hospital + stock_quirofano) * precio_venta), 0)
                           FROM inventario WHERE id_hospital = ? AND estado = 'Disponible'");
    $stmt->execute([$id_hospital]);
    $inv_valor_venta = (float)$stmt->fetchColumn();

    // 3) Total de compras del período
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM purchase_headers
                           WHERE purchase_date BETWEEN ? AND ? AND id_hospital = ?");
    $stmt->execute([$start_date, $end_date, $id_hospital]);
    $total_compras_periodo = (float)$stmt->fetchColumn();

    // 4) Total de ventas del período (sin traslados)
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total), 0) FROM ventas
                           WHERE fecha_venta BETWEEN ? AND ? AND id_hospital = ? AND tipo_pago != 'Traslado'");
    $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_ventas_periodo = (float)$stmt->fetchColumn();

    // 5) Total de compras pagadas
    $stmt = $conn->prepare("SELECT COALESCE(SUM(paid_amount), 0) FROM purchase_headers
                           WHERE purchase_date BETWEEN ? AND ? AND id_hospital = ? AND payment_status = 'Pagado'");
    $stmt->execute([$start_date, $end_date, $id_hospital]);
    $total_compras_pagadas = (float)$stmt->fetchColumn();

    // 6) Traslados en costo de compra
    $stmt = $conn->prepare("SELECT COALESCE(SUM(dv.cantidad_vendida * COALESCE(pi.unit_cost, 0)), 0)
                           FROM detalle_ventas dv
                           JOIN ventas v ON dv.id_venta = v.id_venta
                           JOIN inventario i ON dv.id_inventario = i.id_inventario
                           LEFT JOIN purchase_items pi ON i.id_purchase_item = pi.id
                           WHERE v.fecha_venta BETWEEN ? AND ? AND v.id_hospital = ?
                             AND v.tipo_pago = 'Traslado' AND dv.precio_unitario = 0");
    $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_traslados_costo = (float)$stmt->fetchColumn();

    // 7) Total pendiente a pagar
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount - paid_amount), 0) FROM purchase_headers
                           WHERE purchase_date BETWEEN ? AND ? AND id_hospital = ? AND payment_status != 'Pagado'");
    $stmt->execute([$start_date, $end_date, $id_hospital]);
    $total_pendiente_pagar = (float)$stmt->fetchColumn();

    // === CxC desglosado ===
    $stmt = $conn->prepare("SELECT
            SUM(CASE WHEN DATEDIFF(CURDATE(), e.fecha_ingreso) BETWEEN 0 AND 30 THEN ch.saldo_pendiente ELSE 0 END) AS cxc_0_30,
            SUM(CASE WHEN DATEDIFF(CURDATE(), e.fecha_ingreso) BETWEEN 31 AND 60 THEN ch.saldo_pendiente ELSE 0 END) AS cxc_31_60,
            SUM(CASE WHEN DATEDIFF(CURDATE(), e.fecha_ingreso) BETWEEN 61 AND 90 THEN ch.saldo_pendiente ELSE 0 END) AS cxc_61_90,
            SUM(CASE WHEN DATEDIFF(CURDATE(), e.fecha_ingreso) > 90 THEN ch.saldo_pendiente ELSE 0 END) AS cxc_90_mas
        FROM cuenta_hospitalaria ch
        JOIN encamamientos e ON ch.id_encamamiento = e.id_encamamiento
        WHERE ch.id_hospital = ? AND ch.estado_pago NOT IN ('Pagado','Condonado')
            AND ch.saldo_pendiente > 0");
    $stmt->execute([$id_hospital]);
    $cxc_aging = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['cxc_0_30' => 0, 'cxc_31_60' => 0, 'cxc_61_90' => 0, 'cxc_90_mas' => 0];

    // === Top pacientes con saldo ===
    $stmt = $conn->prepare("SELECT p.nombre, p.apellido, ch.id_cuenta, ch.total_general,
                                  COALESCE(ch.total_pagado, 0) AS total_pagado,
                                  ch.saldo_pendiente AS saldo,
                                  DATEDIFF(CURDATE(), e.fecha_ingreso) AS dias_mora
                           FROM cuenta_hospitalaria ch
                           JOIN encamamientos e ON ch.id_encamamiento = e.id_encamamiento
                           JOIN pacientes p ON e.id_paciente = p.id_paciente
                           WHERE ch.id_hospital = ? AND ch.estado_pago NOT IN ('Pagado','Condonado')
                               AND ch.saldo_pendiente > 0
                           ORDER BY saldo DESC
                           LIMIT 15");
    $stmt->execute([$id_hospital]);
    $top_pacientes_saldo = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // === Top proveedores ===
    $stmt = $conn->prepare("SELECT ph.provider_name, COUNT(*) AS num_compras, SUM(ph.total_amount) AS total
                           FROM purchase_headers ph
                           WHERE ph.purchase_date BETWEEN ? AND ? AND ph.id_hospital = ?
                           GROUP BY ph.provider_name
                           ORDER BY total DESC
                           LIMIT 15");
    $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
    $top_proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // === Auditoría: top transacciones del período ===
    $stmt = $conn->prepare("SELECT fecha_audit, user_nombre, accion, modulo, tabla_afectada, id_registro,
                                   CAST(JSON_UNQUOTE(JSON_EXTRACT(datos_nuevos, '\$.monto')) AS DECIMAL(12,2)) AS monto,
                                   CAST(JSON_UNQUOTE(JSON_EXTRACT(datos_nuevos, '\$.total')) AS DECIMAL(12,2)) AS total,
                                   JSON_UNQUOTE(JSON_EXTRACT(datos_nuevos, '\$.descripcion')) AS descripcion
                            FROM audit_log
                            WHERE id_hospital = ? AND fecha_audit BETWEEN ? AND ?
                                AND modulo IN ('billing','dispensary','purchases','gastos','hospitalization','tarifas','surgery','inventory')
                            ORDER BY fecha_audit DESC
                            LIMIT 200");
    $stmt->execute([$id_hospital, $start_datetime, $end_datetime]);
    $audit_transacciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("SELECT modulo, accion, COUNT(*) AS total
                           FROM audit_log
                           WHERE id_hospital = ? AND fecha_audit BETWEEN ? AND ?
                               AND modulo IN ('billing','dispensary','purchases','gastos','hospitalization','tarifas','surgery','inventory')
                           GROUP BY modulo, accion
                           ORDER BY modulo, accion");
    $stmt->execute([$id_hospital, $start_datetime, $end_datetime]);
    $audit_resumen = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("SELECT user_nombre, COUNT(*) AS movimientos
                           FROM audit_log
                           WHERE id_hospital = ? AND fecha_audit BETWEEN ? AND ?
                               AND modulo IN ('billing','dispensary','purchases','gastos','hospitalization','tarifas','surgery','inventory')
                           GROUP BY user_nombre
                           ORDER BY movimientos DESC
                           LIMIT 10");
    $stmt->execute([$id_hospital, $start_datetime, $end_datetime]);
    $top_usuarios_audit = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // =========================================================================
    // CONSTRUIR HTML PARA DOMPDF
    // =========================================================================

    $today = date('Y-m-d H:i:s');
    $hash = substr(md5($id_hospital . $start_date . $end_date . $today), 0, 12);

    $ingresos_cat = [
        ['Ventas Farmacia', $total_sales_meds],
        ['Consultas Médicas', $total_billings],
        ['Laboratorio', $total_laboratory],
        ['Ultrasonido', $total_ultrasound],
        ['Rayos X', $total_xray],
        ['Electrocardiograma', $total_electro],
        ['Procedimientos Menores', $total_procedures],
        ['Hospitalización', $total_hospitalization],
    ];

    $egresos_cat = [
        ['Pago a Proveedores', $total_purchases_meds],
        ['Pago por Traslado', $total_pagos_traslado],
        ['Gastos Generales', $total_gastos],
    ];

    function fmt($v) { return 'Q' . number_format((float)$v, 2); }
    function fmtPct($v) { return number_format((float)$v, 2) . '%'; }
    function esc($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Auditoría Contable <?= $start_date ?> a <?= $end_date ?></title>
<style>
    @page { margin: 1.5cm 1.2cm; }
    body { font-family: 'Helvetica', sans-serif; font-size: 9pt; color: #222; }
    h1 { color: #198754; font-size: 18pt; margin: 0 0 5px 0; }
    h2 { color: #198754; font-size: 13pt; border-bottom: 2px solid #198754; padding-bottom: 4px; margin-top: 18px; }
    h3 { color: #444; font-size: 11pt; margin-top: 12px; margin-bottom: 4px; }
    .header { text-align: center; margin-bottom: 20px; padding: 10px; background: #f0f9f4; border: 1px solid #198754; }
    .header .subtitle { font-size: 10pt; color: #555; margin-top: 4px; }
    .header .meta { font-size: 8pt; color: #777; margin-top: 6px; }
    table { width: 100%; border-collapse: collapse; margin: 6px 0; font-size: 8.5pt; }
    table.kpi td { padding: 6px 10px; border: 1px solid #ddd; }
    table.kpi td.label { background: #f5f5f5; font-weight: bold; width: 60%; }
    table.kpi td.value { text-align: right; font-weight: bold; color: #198754; }
    table.kpi td.value.neg { color: #dc3545; }
    table.kpi td.value.neutral { color: #444; }
    table.data th { background: #2c3e50; color: white; padding: 5px 6px; text-align: left; font-size: 8pt; }
    table.data td { padding: 4px 6px; border-bottom: 1px solid #eee; }
    table.data tr:nth-child(even) { background: #fafafa; }
    .text-end { text-align: right; }
    .text-center { text-align: center; }
    .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 7pt; }
    .badge-success { background: #198754; color: white; }
    .badge-danger { background: #dc3545; color: white; }
    .badge-warning { background: #ffc107; color: black; }
    .badge-info { background: #0dcaf0; color: white; }
    .badge-secondary { background: #6c757d; color: white; }
    .page-break { page-break-after: always; }
    .signatures { margin-top: 40px; }
    .signature-row { width: 100%; margin-top: 60px; }
    .signature-block { display: inline-block; width: 30%; text-align: center; margin: 0 1.5%; }
    .signature-line { border-top: 1px solid #000; padding-top: 5px; font-size: 8pt; }
    .footer { text-align: center; margin-top: 30px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 7pt; color: #888; }
    .grid-2 { width: 100%; }
    .grid-2 td { width: 50%; vertical-align: top; padding: 4px; }
    .alert-warn { background: #fff3cd; border-left: 4px solid #ffc107; padding: 6px 10px; font-size: 8pt; margin: 4px 0; }
</style>
</head>
<body>

<!-- ============== PÁGINA 1: PORTADA ============== -->
<div class="header">
    <h1><i class="bi bi-shield-check"></i> AUDITORÍA CONTABLE</h1>
    <div class="subtitle">Centro Médico Herrera Saenz</div>
    <div class="subtitle">Período: <strong><?= $start_date ?></strong> al <strong><?= $end_date ?></strong></div>
    <div class="meta">
        Generado: <?= $today ?> &nbsp; | &nbsp;
        Por: <?= esc($user_name) ?> &nbsp; | &nbsp;
        Hash de verificación: <strong><?= $hash ?></strong>
    </div>
</div>

<h2>1. Resumen Ejecutivo</h2>
<table class="kpi">
    <tr><td class="label">Ingresos Brutos Totales</td><td class="value"><?= fmt($total_gross_revenue) ?></td></tr>
    <tr><td class="label">Costos Totales (Operación + Egresos)</td><td class="value neg"><?= fmt($all_sources_cost + $total_egresos) ?></td></tr>
    <tr><td class="label">Utilidad Bruta de Operación</td><td class="value <?= $total_gross_profit >= 0 ? '' : 'neg' ?>"><?= fmt($total_gross_profit) ?></td></tr>
    <tr><td class="label">Margen Bruto</td><td class="value <?= $margen_bruto_pct >= 30 ? '' : 'neg' ?>"><?= fmtPct($margen_bruto_pct) ?></td></tr>
    <tr><td class="label">Flujo de Caja Neto</td><td class="value <?= $net_cash_flow >= 0 ? '' : 'neg' ?>"><?= fmt($net_cash_flow) ?></td></tr>
    <tr><td class="label">EBITDA Estimado</td><td class="value <?= $ebitda >= 0 ? '' : 'neg' ?>"><?= fmt($ebitda) ?></td></tr>
    <tr><td class="label">Cuentas por Cobrar (CxC)</td><td class="value neutral"><?= fmt($cxc_total) ?></td></tr>
    <tr><td class="label">Cuentas por Pagar (CxP)</td><td class="value neutral"><?= fmt($cxp_total) ?></td></tr>
</table>

<h3>Cantidades Absolutas del Per&iacute;odo</h3>
<table class="kpi">
    <tr><td class="label">Valor en Inventario (Costo de Compra)</td><td class="value neutral"><?= fmt($inv_valor_compra) ?></td></tr>
    <tr><td class="label">Valor en Inventario (Precio de Venta)</td><td class="value neutral"><?= fmt($inv_valor_venta) ?></td></tr>
    <tr><td class="label">Total de Compras del Per&iacute;odo</td><td class="value"><?= fmt($total_compras_periodo) ?></td></tr>
    <tr><td class="label">Total Pagado a Proveedores</td><td class="value"><?= fmt($total_compras_pagadas) ?></td></tr>
    <tr><td class="label">Pendiente a Pagar</td><td class="value neg"><?= fmt($total_pendiente_pagar) ?></td></tr>
    <tr><td class="label">Total de Ventas (sin Traslados)</td><td class="value"><?= fmt($total_ventas_periodo) ?></td></tr>
    <tr><td class="label">Traslados (Costo de Compra)</td><td class="value neutral"><?= fmt($total_traslados_costo) ?></td></tr>
</table>

<h3>Desglose de Ingresos por Fuente</h3>
<table class="data">
    <thead><tr><th>Fuente de Ingreso</th><th class="text-end">Monto</th><th class="text-end">% del Total</th></tr></thead>
    <tbody>
        <?php foreach ($ingresos_cat as $c): ?>
        <tr>
            <td><?= esc($c[0]) ?></td>
            <td class="text-end"><?= fmt($c[1]) ?></td>
            <td class="text-end"><?= $total_gross_revenue > 0 ? number_format(($c[1] / $total_gross_revenue) * 100, 2) : '0.00' ?>%</td>
        </tr>
        <?php endforeach; ?>
        <tr style="background: #f0f0f0; font-weight: bold;">
            <td>TOTAL INGRESOS</td>
            <td class="text-end"><?= fmt($total_gross_revenue) ?></td>
            <td class="text-end">100.00%</td>
        </tr>
    </tbody>
</table>

<h3>Desglose de Egresos</h3>
<table class="data">
    <thead><tr><th>Fuente de Egreso</th><th class="text-end">Monto</th><th class="text-end">% del Total</th></tr></thead>
    <tbody>
        <?php foreach ($egresos_cat as $c): ?>
        <tr>
            <td><?= esc($c[0]) ?></td>
            <td class="text-end"><?= fmt($c[1]) ?></td>
            <td class="text-end"><?= $total_egresos > 0 ? number_format(($c[1] / $total_egresos) * 100, 2) : '0.00' ?>%</td>
        </tr>
        <?php endforeach; ?>
        <tr style="background: #f0f0f0; font-weight: bold;">
            <td>TOTAL EGRESOS</td>
            <td class="text-end"><?= fmt($total_egresos) ?></td>
            <td class="text-end">100.00%</td>
        </tr>
    </tbody>
</table>

<div class="alert-warn">
    <strong>Nota:</strong> Los costos operativos detallados por servicio (consultas, ultrasonido, rayos x, etc.)
    se estiman al 30% del cobro total como aproximación contable conservadora.
    El costo de farmacia es exacto, basado en <code>purchase_items.unit_cost</code>.
</div>

<div class="page-break"></div>

<!-- ============== PÁGINA 2: RATIOS FINANCIEROS ============== -->
<h2>2. Ratios Financieros</h2>
<table class="kpi">
    <tr><td class="label">Margen Bruto</td><td class="value <?= $margen_bruto_pct >= 30 ? '' : 'neg' ?>"><?= fmtPct($margen_bruto_pct) ?></td></tr>
    <tr><td class="label">Margen Operativo</td><td class="value <?= $margen_operativo_pct >= 20 ? '' : 'neg' ?>"><?= fmtPct($margen_operativo_pct) ?></td></tr>
    <tr><td class="label">Margen Neto</td><td class="value <?= $margen_neto_pct >= 10 ? '' : 'neg' ?>"><?= fmtPct($margen_neto_pct) ?></td></tr>
    <tr><td class="label">ROI (Retorno sobre Inversión)</td><td class="value <?= $roi_pct >= 15 ? '' : 'neg' ?>"><?= fmtPct($roi_pct) ?></td></tr>
    <tr><td class="label">Rotación de Inventario</td><td class="value neutral"><?= number_format($rotacion_inventario, 2) ?>x por período</td></tr>
    <tr><td class="label">DSO (Days Sales Outstanding)</td><td class="value <?= $dso <= 30 ? '' : ($dso <= 60 ? 'neutral' : 'neg') ?>"><?= number_format($dso, 1) ?> días</td></tr>
    <tr><td class="label">DPO (Days Payable Outstanding)</td><td class="value <?= $dpo >= 30 ? '' : 'neutral' ?>"><?= number_format($dpo, 1) ?> días</td></tr>
    <tr><td class="label">DIO (Days Inventory Outstanding)</td><td class="value neutral"><?= number_format($dio, 1) ?> días</td></tr>
    <tr><td class="label"><strong>Ciclo de Conversión de Efectivo</strong></td><td class="value neutral"><strong><?= number_format($cce, 1) ?> días</strong></td></tr>
    <tr><td class="label">EBITDA Estimado</td><td class="value <?= $ebitda >= 0 ? '' : 'neg' ?>"><?= fmt($ebitda) ?></td></tr>
    <tr><td class="label">Punto de Equilibrio</td><td class="value neutral"><?= fmt($punto_equilibrio) ?></td></tr>
    <tr><td class="label">Apalancamiento (CxP / (CxP + Patrimonio))</td><td class="value neutral"><?= fmtPct($apalancamiento * 100) ?></td></tr>
</table>

<h3>Interpretación de Ratios</h3>
<table class="data">
    <thead><tr><th>Ratio</th><th>Rango Saludable</th><th>Estado</th></tr></thead>
    <tbody>
        <tr><td>Margen Bruto</td><td>&gt; 30%</td><td><?= $margen_bruto_pct >= 30 ? '<span class="badge badge-success">SALUDABLE</span>' : ($margen_bruto_pct >= 15 ? '<span class="badge badge-warning">ACEPTABLE</span>' : '<span class="badge badge-danger">BAJO</span>') ?></td></tr>
        <tr><td>Margen Neto</td><td>&gt; 10%</td><td><?= $margen_neto_pct >= 10 ? '<span class="badge badge-success">SALUDABLE</span>' : ($margen_neto_pct >= 0 ? '<span class="badge badge-warning">ACEPTABLE</span>' : '<span class="badge badge-danger">NEGATIVO</span>') ?></td></tr>
        <tr><td>DSO</td><td>&lt; 30 días</td><td><?= $dso <= 30 ? '<span class="badge badge-success">SALUDABLE</span>' : ($dso <= 60 ? '<span class="badge badge-warning">REVISAR</span>' : '<span class="badge badge-danger">CRÍTICO</span>') ?></td></tr>
        <tr><td>Apalancamiento</td><td>&lt; 60%</td><td><?= ($apalancamiento * 100) < 60 ? '<span class="badge badge-success">SALUDABLE</span>' : '<span class="badge badge-warning">ALTO</span>' ?></td></tr>
    </tbody>
</table>

<div class="page-break"></div>

<!-- ============== PÁGINA 3: CUENTAS POR COBRAR ============== -->
<h2>3. Cuentas por Cobrar (CxC)</h2>
<p style="font-size: 9pt; color: #555;">Estado actual al <?= date('Y-m-d') ?> | Total pendiente: <strong><?= fmt($cxc_total) ?></strong></p>

<h3>Antigüedad de Saldos</h3>
<table class="data">
    <thead><tr><th>Rango de Antigüedad</th><th class="text-end">Monto</th><th class="text-end">% del Total</th></tr></thead>
    <tbody>
        <tr><td>0 – 30 días</td><td class="text-end"><?= fmt($cxc_aging['cxc_0_30']) ?></td><td class="text-end"><?= $cxc_total > 0 ? number_format(($cxc_aging['cxc_0_30'] / $cxc_total) * 100, 1) : '0.0' ?>%</td></tr>
        <tr><td>31 – 60 días</td><td class="text-end"><?= fmt($cxc_aging['cxc_31_60']) ?></td><td class="text-end"><?= $cxc_total > 0 ? number_format(($cxc_aging['cxc_31_60'] / $cxc_total) * 100, 1) : '0.0' ?>%</td></tr>
        <tr><td>61 – 90 días</td><td class="text-end"><?= fmt($cxc_aging['cxc_61_90']) ?></td><td class="text-end"><?= $cxc_total > 0 ? number_format(($cxc_aging['cxc_61_90'] / $cxc_total) * 100, 1) : '0.0' ?>%</td></tr>
        <tr style="background: #f8d7da;"><td>&gt; 90 días</td><td class="text-end"><?= fmt($cxc_aging['cxc_90_mas']) ?></td><td class="text-end"><?= $cxc_total > 0 ? number_format(($cxc_aging['cxc_90_mas'] / $cxc_total) * 100, 1) : '0.0' ?>%</td></tr>
        <tr style="background: #f0f0f0; font-weight: bold;"><td>TOTAL</td><td class="text-end"><?= fmt($cxc_total) ?></td><td class="text-end">100.0%</td></tr>
    </tbody>
</table>

<h3>Top 15 Pacientes con Mayor Saldo Pendiente</h3>
<table class="data">
    <thead><tr><th>Paciente</th><th class="text-end">Total</th><th class="text-end">Pagado</th><th class="text-end">Saldo</th><th class="text-end">Días</th></tr></thead>
    <tbody>
        <?php foreach ($top_pacientes_saldo as $pac): ?>
        <tr>
            <td><?= esc($pac['nombre'] . ' ' . $pac['apellido']) ?></td>
            <td class="text-end"><?= fmt($pac['total_general']) ?></td>
            <td class="text-end"><?= fmt($pac['total_pagado']) ?></td>
            <td class="text-end"><strong><?= fmt($pac['saldo']) ?></strong></td>
            <td class="text-end"><?= (int)$pac['dias_mora'] ?> d</td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($top_pacientes_saldo)): ?>
        <tr><td colspan="5" class="text-center">No hay cuentas pendientes</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="page-break"></div>

<!-- ============== PÁGINA 4: CUENTAS POR PAGAR + PROVEEDORES ============== -->
<h2>4. Cuentas por Pagar (CxP)</h2>
<p style="font-size: 9pt; color: #555;">Saldo total pendiente con proveedores: <strong><?= fmt($cxp_total) ?></strong></p>

<h3>Top 15 Proveedores del Período</h3>
<table class="data">
    <thead><tr><th>Proveedor</th><th class="text-end">N° Compras</th><th class="text-end">Total Comprado</th></tr></thead>
    <tbody>
        <?php foreach ($top_proveedores as $prov): ?>
        <tr>
            <td><?= esc($prov['provider_name']) ?></td>
            <td class="text-end"><?= (int)$prov['num_compras'] ?></td>
            <td class="text-end"><?= fmt($prov['total']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($top_proveedores)): ?>
        <tr><td colspan="3" class="text-center">Sin compras en el período</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="page-break"></div>

<!-- ============== PÁGINA 5: AUDITORÍA DE TRANSACCIONES ============== -->
<h2>5. Auditoría de Transacciones</h2>
<p style="font-size: 9pt; color: #555;">Trazabilidad completa de movimientos financieros del período (tabla <code>audit_log</code>).</p>

<h3>Resumen de Movimientos por Módulo</h3>
<table class="data">
    <thead><tr><th>Módulo</th><th>Acción</th><th class="text-end">Total</th></tr></thead>
    <tbody>
        <?php if (empty($audit_resumen)): ?>
            <tr><td colspan="3" class="text-center">Sin movimientos en el período</td></tr>
        <?php else: foreach ($audit_resumen as $a): ?>
            <tr>
                <td><span class="badge badge-secondary"><?= esc($a['modulo']) ?></span></td>
                <td><?= esc($a['accion']) ?></td>
                <td class="text-end"><?= (int)$a['total'] ?></td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<h3>Top 10 Usuarios con Más Movimientos</h3>
<table class="data">
    <thead><tr><th>Usuario</th><th class="text-end">Movimientos</th></tr></thead>
    <tbody>
        <?php foreach ($top_usuarios_audit as $u): ?>
        <tr>
            <td><?= esc($u['user_nombre'] ?? 'N/A') ?></td>
            <td class="text-end"><?= (int)$u['movimientos'] ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($top_usuarios_audit)): ?>
        <tr><td colspan="2" class="text-center">Sin movimientos</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<h3>Últimas 200 Transacciones del Período</h3>
<table class="data" style="font-size: 7pt;">
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Usuario</th>
            <th>Módulo</th>
            <th>Acción</th>
            <th>Tabla</th>
            <th>ID</th>
            <th class="text-end">Monto</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($audit_transacciones as $t): ?>
        <?php
            $monto_t = $t['monto'] !== null && $t['monto'] !== '' ? $t['monto'] : $t['total'];
            $accion_class = ['create'=>'badge-success','update'=>'badge-warning','delete'=>'badge-danger','cancel'=>'badge-danger','export'=>'badge-info'][$t['accion']] ?? 'badge-secondary';
        ?>
        <tr>
            <td><?= esc($t['fecha_audit']) ?></td>
            <td><?= esc($t['user_nombre'] ?? 'N/A') ?></td>
            <td><span class="badge <?= $accion_class ?>"><?= esc($t['modulo']) ?></span></td>
            <td><?= esc($t['accion']) ?></td>
            <td><?= esc($t['tabla_afectada'] ?? '—') ?></td>
            <td><?= esc($t['id_registro'] ?? '—') ?></td>
            <td class="text-end"><?= $monto_t !== null ? fmt($monto_t) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($audit_transacciones)): ?>
        <tr><td colspan="7" class="text-center">Sin transacciones registradas</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="page-break"></div>

<!-- ============== PÁGINA FINAL: FIRMAS ============== -->
<h2>6. Firmas y Sello</h2>
<p style="font-size: 9pt; color: #555;">
    Yo, <strong><?= esc($user_name) ?></strong>, certifico que la información contenida en este documento
    refleja fielmente los registros contables del sistema CMHS para el período del
    <strong><?= $start_date ?></strong> al <strong><?= $end_date ?></strong>.
</p>

<table class="signature-row">
    <tr>
        <td class="signature-block">
            <div class="signature-line">Elaborado por</div>
            <div style="margin-top: 4px; font-size: 7pt; color: #666;"><?= esc($user_name) ?></div>
            <div style="font-size: 7pt; color: #666;"><?= $today ?></div>
        </td>
        <td class="signature-block">
            <div class="signature-line">Revisado por</div>
        </td>
        <td class="signature-block">
            <div class="signature-line">Aprobado por</div>
        </td>
    </tr>
</table>

<div class="footer">
    <p><strong>Sistema CMHS — Centro Médico Herrera Saenz</strong></p>
    <p>Documento generado el <?= $today ?> | Hash de verificación: <code><?= $hash ?></code></p>
    <p>Este documento es un reporte interno de auditoría. La información proviene de las tablas del sistema y queda registrada en <code>audit_log</code>.</p>
</div>

</body>
</html>
    <?php
    $html = ob_get_clean();

    // =========================================================================
    // GENERAR PDF CON DOMPDF
    // =========================================================================
    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'Helvetica');
    $options->set('isPhpEnabled', false);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Registrar exportación en auditoría
    audit_log('export', 'reports', "Auditoría Contable PDF generada: $start_date a $end_date", [
        'tabla_afectada' => 'audit_log',
        'datos_nuevos' => [
            'tipo' => 'auditoria_contable_pdf',
            'desde' => $start_date,
            'hasta' => $end_date,
            'hash' => $hash,
        ],
    ]);

    // Stream PDF
    $filename = "Auditoria_Contable_{$start_date}_a_{$end_date}.pdf";
    $dompdf->stream($filename, ['Attachment' => true]);

} catch (Exception $e) {
    error_log('export_auditoria_contable.php error: ' . $e->getMessage());
    echo '<!DOCTYPE html><html><body><h1>Error al generar PDF</h1><p>' . htmlspecialchars($e->getMessage()) . '</p></body></html>';
}
