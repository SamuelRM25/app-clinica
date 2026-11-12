<?php
/**
 * php/farmacia/reportes.php — Reportes de ClinicApp Farmacia.
 * Ventas, utilidad, productos, métodos de pago, vendedores, compras e inventario.
 * ?p=hoy|7d|mes|mes_ant|30d|custom &desde=YYYY-MM-DD &hasta=YYYY-MM-DD  [&export=ventas|productos]
 */
require_once __DIR__ . '/_init.php';
check_module_access('reports');

$h = $id_hospital;
$V = FX_VENTA_VALIDA;

// ── Período ─────────────────────────────────────────────────────────────
$p = $_GET['p'] ?? 'mes';
$hoy = date('Y-m-d');
switch ($p) {
    case 'hoy':     $desde = $hasta = $hoy; break;
    case '7d':      $desde = date('Y-m-d', strtotime('-6 day')); $hasta = $hoy; break;
    case '30d':     $desde = date('Y-m-d', strtotime('-29 day')); $hasta = $hoy; break;
    case 'mes_ant': $desde = date('Y-m-01', strtotime('first day of last month')); $hasta = date('Y-m-t', strtotime('last day of last month')); break;
    case 'custom':
        $ok = fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d) && strtotime($d);
        $desde = $ok($_GET['desde'] ?? '') ? $_GET['desde'] : date('Y-m-01');
        $hasta = $ok($_GET['hasta'] ?? '') ? $_GET['hasta'] : $hoy;
        if ($desde > $hasta) [$desde, $hasta] = [$hasta, $desde];
        break;
    default: $p = 'mes'; $desde = date('Y-m-01'); $hasta = $hoy;
}
$ini = $desde . ' 00:00:00';
$fin = $hasta . ' 23:59:59';
$dias_rango = (int) round((strtotime($hasta) - strtotime($desde)) / 86400) + 1;

// Costo unitario: precio de compra del producto o, si no tiene, el costo de su compra
$COSTO = "COALESCE(NULLIF(i.precio_compra, 0), pi.unit_cost, 0)";

// ── Exportar CSV ────────────────────────────────────────────────────────
if (isset($_GET['export'])) {
    $tipo = $_GET['export'] === 'productos' ? 'productos' : 'ventas';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $tipo . '_' . $desde . '_' . $hasta . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel lea las tildes
    if ($tipo === 'ventas') {
        fputcsv($out, ['Folio', 'Fecha', 'Cliente', 'NIT', 'Método de pago', 'Vendedor', 'Total (Q)']);
        foreach (fx_all($conn, "SELECT v.id_venta, v.fecha_venta, v.nombre_cliente, v.nit_cliente, v.tipo_pago, v.total,
                                       TRIM(CONCAT(COALESCE(u.nombre,''),' ',COALESCE(u.apellido,''))) vendedor
                                FROM ventas v LEFT JOIN usuarios u ON u.idUsuario = v.id_usuario
                                WHERE v.id_hospital = ? AND v.fecha_venta BETWEEN ? AND ? AND $V ORDER BY v.fecha_venta", [$h, $ini, $fin]) as $r) {
            fputcsv($out, ['VTA-' . str_pad((string) $r['id_venta'], 5, '0', STR_PAD_LEFT), $r['fecha_venta'], $r['nombre_cliente'], $r['nit_cliente'] ?? '', $r['tipo_pago'], $r['vendedor'], number_format((float) $r['total'], 2, '.', '')]);
        }
    } else {
        fputcsv($out, ['Producto', 'Presentación', 'Unidades', 'Ingreso (Q)', 'Costo (Q)', 'Utilidad (Q)', 'Margen %']);
        foreach (fx_all($conn, "SELECT i.nom_medicamento, i.presentacion_med, SUM(dv.cantidad_vendida) uds,
                                       SUM(dv.cantidad_vendida * dv.precio_unitario) ingreso, SUM(dv.cantidad_vendida * $COSTO) costo
                                FROM detalle_ventas dv JOIN ventas v ON v.id_venta = dv.id_venta
                                JOIN inventario i ON i.id_inventario = dv.id_inventario LEFT JOIN purchase_items pi ON pi.id = i.id_purchase_item
                                WHERE v.id_hospital = ? AND v.fecha_venta BETWEEN ? AND ? AND $V
                                GROUP BY i.nom_medicamento, i.presentacion_med ORDER BY ingreso DESC", [$h, $ini, $fin]) as $r) {
            $u = $r['ingreso'] - $r['costo'];
            fputcsv($out, [$r['nom_medicamento'], $r['presentacion_med'], $r['uds'], number_format($r['ingreso'], 2, '.', ''), number_format($r['costo'], 2, '.', ''), number_format($u, 2, '.', ''), $r['ingreso'] > 0 ? round($u / $r['ingreso'] * 100, 1) : 0]);
        }
    }
    fclose($out);
    exit;
}

// ── Datos ───────────────────────────────────────────────────────────────
$res = fx_one($conn, "SELECT COUNT(*) n, COALESCE(SUM(v.total),0) t FROM ventas v WHERE v.id_hospital = ? AND v.fecha_venta BETWEEN ? AND ? AND $V", [$h, $ini, $fin]);
$costo = (float) fx_val($conn, "SELECT COALESCE(SUM(dv.cantidad_vendida * $COSTO),0)
                                FROM detalle_ventas dv JOIN ventas v ON v.id_venta = dv.id_venta
                                JOIN inventario i ON i.id_inventario = dv.id_inventario LEFT JOIN purchase_items pi ON pi.id = i.id_purchase_item
                                WHERE v.id_hospital = ? AND v.fecha_venta BETWEEN ? AND ? AND $V", [$h, $ini, $fin]);
$uds = (int) fx_val($conn, "SELECT COALESCE(SUM(dv.cantidad_vendida),0) FROM detalle_ventas dv JOIN ventas v ON v.id_venta = dv.id_venta
                            WHERE v.id_hospital = ? AND v.fecha_venta BETWEEN ? AND ? AND $V", [$h, $ini, $fin]);
$ventas_t = (float) $res['t'];
$utilidad = $ventas_t - $costo;
$margen = $ventas_t > 0 ? $utilidad / $ventas_t * 100 : 0;
$ticket = $res['n'] > 0 ? $ventas_t / $res['n'] : 0;

// Período anterior del mismo largo (para comparar)
$prev_fin = date('Y-m-d', strtotime($desde . ' -1 day'));
$prev_ini = date('Y-m-d', strtotime($prev_fin . ' -' . ($dias_rango - 1) . ' day'));
$prev_t = (float) fx_val($conn, "SELECT COALESCE(SUM(v.total),0) FROM ventas v WHERE v.id_hospital = ? AND v.fecha_venta BETWEEN ? AND ? AND $V", [$h, $prev_ini . ' 00:00:00', $prev_fin . ' 23:59:59']);
$cambio = $prev_t > 0 ? ($ventas_t - $prev_t) / $prev_t * 100 : null;

$area = function_exists('purchases_have_area') && purchases_have_area($conn) ? " AND COALESCE(area,'farmacia') = 'farmacia'" : '';
$compras = fx_one($conn, "SELECT COUNT(*) n, COALESCE(SUM(total_amount),0) t, COALESCE(SUM(total_amount - COALESCE(paid_amount,0)),0) saldo
                          FROM purchase_headers WHERE id_hospital = ? AND purchase_date BETWEEN ? AND ? $area", [$h, $desde, $hasta]);

// Serie: por día (hasta 62 días) o por mes
$por_mes = $dias_rango > 62;
$serie = [];
if ($por_mes) {
    $mm = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    $rows = fx_all($conn, "SELECT DATE_FORMAT(v.fecha_venta,'%Y-%m') k, SUM(v.total) t FROM ventas v WHERE v.id_hospital = ? AND v.fecha_venta BETWEEN ? AND ? AND $V GROUP BY k", [$h, $ini, $fin]);
    $map = array_column($rows, 't', 'k');
    for ($d = strtotime(date('Y-m-01', strtotime($desde))); $d <= strtotime($hasta); $d = strtotime('+1 month', $d)) {
        $k = date('Y-m', $d);
        $serie[] = ['label' => $mm[(int) date('n', $d) - 1], 'hint' => $mm[(int) date('n', $d) - 1] . ' ' . date('Y', $d), 'valor' => (float) ($map[$k] ?? 0)];
    }
} else {
    $rows = fx_all($conn, "SELECT DATE(v.fecha_venta) k, SUM(v.total) t FROM ventas v WHERE v.id_hospital = ? AND v.fecha_venta BETWEEN ? AND ? AND $V GROUP BY k", [$h, $ini, $fin]);
    $map = array_column($rows, 't', 'k');
    for ($d = strtotime($desde); $d <= strtotime($hasta); $d += 86400) {
        $k = date('Y-m-d', $d);
        $serie[] = ['label' => date('j', $d), 'hint' => fx_fecha_corta($k), 'valor' => (float) ($map[$k] ?? 0)];
    }
}

$metodos = fx_all($conn, "SELECT COALESCE(NULLIF(v.tipo_pago,''),'Sin dato') m, COUNT(*) n, SUM(v.total) t FROM ventas v
                          WHERE v.id_hospital = ? AND v.fecha_venta BETWEEN ? AND ? AND $V GROUP BY m ORDER BY t DESC", [$h, $ini, $fin]);

$productos = fx_all($conn, "SELECT i.nom_medicamento nombre, i.presentacion_med pres, SUM(dv.cantidad_vendida) uds,
                                   SUM(dv.cantidad_vendida * dv.precio_unitario) ingreso, SUM(dv.cantidad_vendida * $COSTO) costo
                            FROM detalle_ventas dv JOIN ventas v ON v.id_venta = dv.id_venta
                            JOIN inventario i ON i.id_inventario = dv.id_inventario LEFT JOIN purchase_items pi ON pi.id = i.id_purchase_item
                            WHERE v.id_hospital = ? AND v.fecha_venta BETWEEN ? AND ? AND $V
                            GROUP BY i.nom_medicamento, i.presentacion_med ORDER BY ingreso DESC LIMIT 10", [$h, $ini, $fin]);

$vendedores = fx_all($conn, "SELECT TRIM(CONCAT(COALESCE(u.nombre,'Sistema'),' ',COALESCE(u.apellido,''))) nombre, COUNT(*) n, SUM(v.total) t
                             FROM ventas v LEFT JOIN usuarios u ON u.idUsuario = v.id_usuario
                             WHERE v.id_hospital = ? AND v.fecha_venta BETWEEN ? AND ? AND $V GROUP BY v.id_usuario, nombre ORDER BY t DESC", [$h, $ini, $fin]);

$inv = fx_one($conn, "SELECT COUNT(*) productos,
                             COALESCE(SUM(GREATEST(i.cantidad_med,0) * $COSTO),0) costo,
                             COALESCE(SUM(GREATEST(i.cantidad_med,0) * COALESCE(i.precio_venta,0)),0) venta,
                             SUM(CASE WHEN i.cantidad_med <= 0 THEN 1 ELSE 0 END) agotados,
                             SUM(CASE WHEN i.fecha_vencimiento < CURDATE() AND i.cantidad_med > 0 THEN 1 ELSE 0 END) vencidos,
                             SUM(CASE WHEN i.fecha_vencimiento BETWEEN CURDATE() AND CURDATE() + INTERVAL 90 DAY AND i.cantidad_med > 0 THEN 1 ELSE 0 END) vencen
                      FROM inventario i LEFT JOIN purchase_items pi ON pi.id = i.id_purchase_item
                      WHERE i.id_hospital = ? AND COALESCE(i.estado,'Disponible') <> 'Pendiente'", [$h]);
$sin_mov = fx_all($conn, "SELECT i.nom_medicamento, i.presentacion_med, i.cantidad_med, GREATEST(i.cantidad_med,0) * $COSTO capital
                          FROM inventario i LEFT JOIN purchase_items pi ON pi.id = i.id_purchase_item
                          WHERE i.id_hospital = ? AND i.cantidad_med > 0 AND COALESCE(i.estado,'Disponible') <> 'Pendiente'
                            AND NOT EXISTS (SELECT 1 FROM detalle_ventas dv JOIN ventas v ON v.id_venta = dv.id_venta
                                            WHERE dv.id_inventario = i.id_inventario AND v.fecha_venta BETWEEN ? AND ?)
                          ORDER BY capital DESC LIMIT 5", [$h, $ini, $fin]);

$presets = ['hoy' => 'Hoy', '7d' => '7 días', '30d' => '30 días', 'mes' => 'Este mes', 'mes_ant' => 'Mes anterior'];
$qs = http_build_query(['p' => $p, 'desde' => $desde, 'hasta' => $hasta]);
$rango_txt = $desde === $hasta ? fx_fecha_corta($desde) . ' ' . date('Y', strtotime($desde)) : fx_fecha_corta($desde) . ' – ' . fx_fecha_corta($hasta) . ' ' . date('Y', strtotime($hasta));
$met_max = $metodos ? max(array_column($metodos, 't')) : 1;
$ven_max = $vendedores ? max(array_column($vendedores, 't')) : 1;

farmacia_shell('reportes', ['sub' => $rango_txt, 'accion' => ['Exportar ventas', 'reportes.php?' . $qs . '&export=ventas', 'bi-download']]);
fx_head('Reportes');
?>
<main class="fx-main">
    <form class="fx-filter" method="get" action="reportes.php">
        <div class="fx-seg" role="group" aria-label="Período">
            <?php foreach ($presets as $k => $t): ?>
                <a class="fx-seg__btn<?php echo $p === $k ? ' is-on' : ''; ?>" href="?p=<?php echo $k; ?>"<?php echo $p === $k ? ' aria-current="true"' : ''; ?>><?php echo $t; ?></a>
            <?php endforeach; ?>
        </div>
        <div class="fx-range">
            <input type="hidden" name="p" value="custom">
            <label><span>Desde</span><input type="date" name="desde" value="<?php echo fx_e($desde); ?>" max="<?php echo $hoy; ?>"></label>
            <label><span>Hasta</span><input type="date" name="hasta" value="<?php echo fx_e($hasta); ?>" max="<?php echo $hoy; ?>"></label>
            <button class="fx-btn" type="submit"><i class="bi bi-funnel"></i>Aplicar</button>
        </div>
    </form>

    <section class="fx-kpis fx-kpis--6">
        <article class="fx-kpi">
            <span class="fx-kpi__ico"><i class="bi bi-cash-stack"></i></span>
            <span class="fx-kpi__label">Ventas</span>
            <strong><?php echo fx_q($ventas_t); ?></strong>
            <small>
                <?php if ($cambio !== null): ?>
                    <span class="fx-chg <?php echo $cambio >= 0 ? 'is-up' : 'is-down'; ?>"><i class="bi <?php echo $cambio >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right'; ?>"></i><?php echo number_format(abs($cambio), 0); ?>%</span> vs período anterior
                <?php else: ?>Sin ventas en el período anterior<?php endif; ?>
            </small>
        </article>
        <article class="fx-kpi">
            <span class="fx-kpi__ico fx-kpi__ico--info"><i class="bi bi-receipt"></i></span>
            <span class="fx-kpi__label">Operaciones</span>
            <strong><?php echo fx_n($res['n']); ?></strong>
            <small><?php echo fx_n($uds); ?> unidades vendidas</small>
        </article>
        <article class="fx-kpi">
            <span class="fx-kpi__ico fx-kpi__ico--info"><i class="bi bi-receipt-cutoff"></i></span>
            <span class="fx-kpi__label">Ticket promedio</span>
            <strong><?php echo fx_q($ticket); ?></strong>
            <small>Por venta</small>
        </article>
        <article class="fx-kpi">
            <span class="fx-kpi__ico fx-kpi__ico--warn"><i class="bi bi-box2"></i></span>
            <span class="fx-kpi__label">Costo de lo vendido</span>
            <strong><?php echo fx_q($costo); ?></strong>
            <small>Según precio de compra</small>
        </article>
        <article class="fx-kpi fx-kpi--accent">
            <span class="fx-kpi__ico"><i class="bi bi-graph-up-arrow"></i></span>
            <span class="fx-kpi__label">Utilidad bruta</span>
            <strong><?php echo fx_q($utilidad); ?></strong>
            <small>Margen <?php echo number_format($margen, 1); ?>%</small>
        </article>
        <article class="fx-kpi">
            <span class="fx-kpi__ico fx-kpi__ico--coral"><i class="bi bi-bag-check"></i></span>
            <span class="fx-kpi__label">Compras</span>
            <strong><?php echo fx_q($compras['t'] ?? 0); ?></strong>
            <small><?php echo fx_n($compras['n'] ?? 0); ?> facturas · saldo <?php echo fx_q($compras['saldo'] ?? 0); ?></small>
        </article>
    </section>

    <section class="fx-grid fx-grid--2">
        <article class="fx-card">
            <header class="fx-card__head"><div><h2>Ventas <?php echo $por_mes ? 'por mes' : 'por día'; ?></h2><p><?php echo fx_e($rango_txt); ?></p></div></header>
            <?php echo fx_barras($serie, 'chartRango'); ?>
        </article>
        <article class="fx-card">
            <header class="fx-card__head"><div><h2>Métodos de pago</h2><p>Cómo pagan sus clientes</p></div></header>
            <?php if (!$metodos): ?>
                <p class="fx-empty"><i class="bi bi-credit-card"></i>Sin ventas en este período.</p>
            <?php else: ?>
                <ul class="fx-meters">
                    <?php foreach ($metodos as $m): $pct = $ventas_t > 0 ? $m['t'] / $ventas_t * 100 : 0; ?>
                        <li>
                            <div class="fx-meters__row"><b><?php echo fx_e($m['m']); ?></b><span><?php echo fx_q($m['t']); ?> · <?php echo number_format($pct, 0); ?>%</span></div>
                            <div class="fx-rank__bar"><span style="width: <?php echo max(3, round($m['t'] / $met_max * 100)); ?>%"></span></div>
                            <small><?php echo fx_n($m['n']); ?> <?php echo $m['n'] == 1 ? 'venta' : 'ventas'; ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
    </section>

    <article class="fx-card">
        <header class="fx-card__head">
            <div><h2>Productos más rentables</h2><p>Top 10 por ingreso en el período</p></div>
            <a class="fx-link" href="reportes.php?<?php echo $qs; ?>&amp;export=productos"><i class="bi bi-download"></i> CSV de productos</a>
        </header>
        <?php if (!$productos): ?>
            <p class="fx-empty"><i class="bi bi-capsule"></i>Sin productos vendidos en este período.</p>
        <?php else: ?>
            <div class="fx-table-wrap">
                <table class="fx-table">
                    <thead><tr><th>Producto</th><th class="num">Unidades</th><th class="num">Ingreso</th><th class="num">Costo</th><th class="num">Utilidad</th><th class="num">Margen</th></tr></thead>
                    <tbody>
                        <?php foreach ($productos as $r): $u = $r['ingreso'] - $r['costo']; $mg = $r['ingreso'] > 0 ? $u / $r['ingreso'] * 100 : 0; ?>
                            <tr>
                                <td><b><?php echo fx_e($r['nombre']); ?></b><small><?php echo fx_e($r['pres'] ?: '—'); ?></small></td>
                                <td class="num"><?php echo fx_n($r['uds']); ?></td>
                                <td class="num"><?php echo fx_q($r['ingreso']); ?></td>
                                <td class="num muted"><?php echo fx_q($r['costo']); ?></td>
                                <td class="num"><b><?php echo fx_q($u); ?></b></td>
                                <td class="num"><span class="fx-tag<?php echo $mg < 20 ? ' fx-tag--warning' : ''; ?>"><?php echo number_format($mg, 0); ?>%</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>

    <section class="fx-grid fx-grid--3">
        <article class="fx-card">
            <header class="fx-card__head"><div><h2>Ventas por usuario</h2><p>Quién vendió en el período</p></div></header>
            <?php if (!$vendedores): ?>
                <p class="fx-empty"><i class="bi bi-person"></i>Sin ventas en este período.</p>
            <?php else: ?>
                <ul class="fx-meters">
                    <?php foreach ($vendedores as $v): ?>
                        <li>
                            <div class="fx-meters__row"><b><?php echo fx_e($v['nombre']); ?></b><span><?php echo fx_q($v['t']); ?></span></div>
                            <div class="fx-rank__bar"><span style="width: <?php echo max(3, round($v['t'] / $ven_max * 100)); ?>%"></span></div>
                            <small><?php echo fx_n($v['n']); ?> ventas</small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>

        <article class="fx-card">
            <header class="fx-card__head"><div><h2>Inventario hoy</h2><p><?php echo fx_n($inv['productos'] ?? 0); ?> productos</p></div></header>
            <dl class="fx-dl">
                <div><dt>Valor a costo</dt><dd><?php echo fx_q($inv['costo'] ?? 0); ?></dd></div>
                <div><dt>Valor a precio de venta</dt><dd><?php echo fx_q($inv['venta'] ?? 0); ?></dd></div>
                <div><dt>Utilidad potencial</dt><dd class="good"><?php echo fx_q(($inv['venta'] ?? 0) - ($inv['costo'] ?? 0)); ?></dd></div>
                <div><dt>Vencen en 90 días</dt><dd class="warn"><?php echo fx_n($inv['vencen'] ?? 0); ?></dd></div>
                <div><dt>Vencidos con stock</dt><dd class="bad"><?php echo fx_n($inv['vencidos'] ?? 0); ?></dd></div>
                <div><dt>Agotados</dt><dd class="bad"><?php echo fx_n($inv['agotados'] ?? 0); ?></dd></div>
            </dl>
        </article>

        <article class="fx-card">
            <header class="fx-card__head"><div><h2>Sin movimiento</h2><p>Con stock y sin ventas en el período</p></div></header>
            <?php if (!$sin_mov): ?>
                <p class="fx-empty"><i class="bi bi-check2-circle"></i>Todos los productos con stock tuvieron ventas.</p>
            <?php else: ?>
                <ul class="fx-list">
                    <?php foreach ($sin_mov as $s): ?>
                        <li>
                            <span class="fx-dot fx-dot--warning"><i class="bi bi-pause-circle"></i></span>
                            <div class="fx-list__body"><b><?php echo fx_e($s['nom_medicamento']); ?></b><small><?php echo fx_n($s['cantidad_med']); ?> uds · <?php echo fx_e($s['presentacion_med'] ?: '—'); ?></small></div>
                            <div class="fx-list__end"><b class="fx-money"><?php echo fx_q($s['capital']); ?></b><small>detenido</small></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
    </section>
</main>
<?php fx_foot(); ?>
