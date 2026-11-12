<?php
/**
 * php/farmacia/inicio.php — Panel principal de ClinicApp Farmacia.
 */
require_once __DIR__ . '/_init.php';
// El inicio lo ven todos los usuarios con sesión (cada tarjeta respeta los permisos)

$h = $id_hospital;
$V = FX_VENTA_VALIDA;

// ── Ventas ──────────────────────────────────────────────────────────────
$hoy   = fx_one($conn, "SELECT COUNT(*) n, COALESCE(SUM(v.total),0) t FROM ventas v WHERE v.id_hospital = ? AND DATE(v.fecha_venta) = CURDATE() AND $V", [$h]);
$ayer  = fx_one($conn, "SELECT COUNT(*) n, COALESCE(SUM(v.total),0) t FROM ventas v WHERE v.id_hospital = ? AND DATE(v.fecha_venta) = CURDATE() - INTERVAL 1 DAY AND $V", [$h]);
$mes   = fx_one($conn, "SELECT COUNT(*) n, COALESCE(SUM(v.total),0) t FROM ventas v WHERE v.id_hospital = ? AND v.fecha_venta >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND $V", [$h]);
$ticket = ($mes['n'] ?? 0) > 0 ? $mes['t'] / $mes['n'] : 0;
$delta = ($ayer['t'] ?? 0) > 0 ? (($hoy['t'] - $ayer['t']) / $ayer['t']) * 100 : null;

// Últimos 14 días
$dias = [];
for ($i = 13; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("-$i day")); $dias[$d] = 0.0; }
foreach (fx_all($conn, "SELECT DATE(v.fecha_venta) d, SUM(v.total) t FROM ventas v
                        WHERE v.id_hospital = ? AND v.fecha_venta >= CURDATE() - INTERVAL 13 DAY AND $V GROUP BY DATE(v.fecha_venta)", [$h]) as $r) {
    if (isset($dias[$r['d']])) $dias[$r['d']] = (float) $r['t'];
}
$serie = [];
foreach ($dias as $d => $t) $serie[] = ['label' => date('j', strtotime($d)), 'hint' => fx_fecha_corta($d), 'valor' => $t];
$total14 = array_sum($dias);
$con_venta = array_filter($dias);
$prom14 = $con_venta ? $total14 / count($dias) : 0;
$mejor_dia = $con_venta ? array_search(max($dias), $dias) : null;

// Más vendidos del mes
$top = fx_all($conn, "SELECT i.nom_medicamento nombre, i.presentacion_med pres, SUM(dv.cantidad_vendida) uds, SUM(dv.cantidad_vendida * dv.precio_unitario) ingreso
                      FROM detalle_ventas dv
                      JOIN ventas v ON v.id_venta = dv.id_venta
                      JOIN inventario i ON i.id_inventario = dv.id_inventario
                      WHERE v.id_hospital = ? AND v.fecha_venta >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND $V
                      GROUP BY i.nom_medicamento, i.presentacion_med ORDER BY uds DESC LIMIT 5", [$h]);
$top_max = $top ? max(array_column($top, 'uds')) : 1;

$ultimas = fx_all($conn, "SELECT v.id_venta, v.nombre_cliente, v.total, v.tipo_pago, v.fecha_venta FROM ventas v
                          WHERE v.id_hospital = ? AND $V ORDER BY v.fecha_venta DESC LIMIT 6", [$h]);

// ── Inventario ──────────────────────────────────────────────────────────
$inv = fx_one($conn, "SELECT COUNT(*) productos,
                             SUM(CASE WHEN cantidad_med <= 0 THEN 1 ELSE 0 END) agotados,
                             SUM(CASE WHEN cantidad_med BETWEEN 1 AND 9 THEN 1 ELSE 0 END) bajos,
                             SUM(CASE WHEN fecha_vencimiento IS NOT NULL AND fecha_vencimiento BETWEEN CURDATE() AND CURDATE() + INTERVAL 60 DAY AND cantidad_med > 0 THEN 1 ELSE 0 END) por_vencer,
                             COALESCE(SUM(GREATEST(cantidad_med,0) * COALESCE(precio_venta,0)),0) valor
                      FROM inventario WHERE id_hospital = ? AND COALESCE(estado,'Disponible') <> 'Pendiente'", [$h]);
$alertas = fx_all($conn, "SELECT nom_medicamento, presentacion_med, cantidad_med, fecha_vencimiento,
                                 CASE WHEN cantidad_med <= 0 THEN 'agotado'
                                      WHEN fecha_vencimiento IS NOT NULL AND fecha_vencimiento < CURDATE() THEN 'vencido'
                                      WHEN fecha_vencimiento IS NOT NULL AND fecha_vencimiento <= CURDATE() + INTERVAL 60 DAY THEN 'vence'
                                      ELSE 'bajo' END tipo
                          FROM inventario
                          WHERE id_hospital = ? AND COALESCE(estado,'Disponible') <> 'Pendiente'
                            AND (cantidad_med < 10 OR (fecha_vencimiento IS NOT NULL AND fecha_vencimiento <= CURDATE() + INTERVAL 60 DAY AND cantidad_med > 0))
                          ORDER BY FIELD(tipo,'vencido','agotado','vence','bajo'), fecha_vencimiento IS NULL, fecha_vencimiento, cantidad_med
                          LIMIT 7", [$h]);

// ── Compras por pagar ───────────────────────────────────────────────────
$area = function_exists('purchases_have_area') && purchases_have_area($conn) ? " AND COALESCE(area,'farmacia') = 'farmacia'" : '';
$pagar = fx_one($conn, "SELECT COUNT(*) n, COALESCE(SUM(total_amount - COALESCE(paid_amount,0)),0) saldo
                        FROM purchase_headers WHERE id_hospital = ? AND (total_amount - COALESCE(paid_amount,0)) > 0 $area", [$h]);

$hora = (int) date('G');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
$no_disp = isset($_GET['no_disponible']);
$sin_permiso = isset($_GET['acceso_denegado']);

$tipos = [
    'vencido' => ['Vencido', 'danger', 'bi-x-octagon'],
    'agotado' => ['Agotado', 'danger', 'bi-slash-circle'],
    'vence'   => ['Por vencer', 'warning', 'bi-hourglass-split'],
    'bajo'    => ['Stock bajo', 'info', 'bi-arrow-down-circle'],
];
$pagos = ['Efectivo' => 'bi-cash', 'Tarjeta' => 'bi-credit-card', 'Transferencia' => 'bi-bank'];

farmacia_shell('inicio', ['titulo' => 'Inicio', 'accion' => false, 'sub' => $_SESSION['hospital_nombre'] ?? FARMA_NOMBRE]);
fx_head('Inicio');
?>
<main class="fx-main">
    <?php if ($no_disp): ?>
        <div class="fx-note" role="status"><i class="bi bi-info-circle"></i><div>Ese módulo no forma parte de <?php echo fx_e(FARMA_NOMBRE); ?>. Aquí tiene todo lo de la farmacia.</div></div>
    <?php elseif ($sin_permiso): ?>
        <div class="fx-note" role="status"><i class="bi bi-lock"></i><div>Su usuario no tiene acceso a ese módulo. Pídale al administrador que lo habilite.</div></div>
    <?php endif; ?>

    <section class="fx-grid fx-grid--hero">
        <article class="fx-hero">
            <div class="fx-hero__top">
                <p class="fx-eyebrow"><?php echo fx_e($saludo . ', ' . $user_name); ?></p>
                <span class="fx-live"><span></span>Hoy</span>
            </div>
            <div class="fx-hero__num">
                <span class="fx-hero__label">Vendido hoy</span>
                <strong><?php echo fx_q($hoy['t'] ?? 0); ?></strong>
                <span class="fx-hero__meta">
                    <?php echo fx_n($hoy['n'] ?? 0); ?> <?php echo ($hoy['n'] ?? 0) == 1 ? 'venta' : 'ventas'; ?>
                    <?php if ($delta !== null): ?>
                        <span class="fx-delta <?php echo $delta >= 0 ? 'is-up' : 'is-down'; ?>">
                            <i class="bi <?php echo $delta >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right'; ?>"></i><?php echo number_format(abs($delta), 0); ?>% vs ayer
                        </span>
                    <?php elseif (($ayer['n'] ?? 0) == 0): ?>
                        <span class="fx-delta">Ayer sin ventas</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="fx-hero__actions">
                <?php if (farmacia_puede('pharmacy')): ?><a class="fx-btn fx-btn--light" href="../dispensary/index.php"><i class="bi bi-upc-scan"></i>Nueva venta</a><?php endif; ?>
                <?php if (farmacia_puede('purchases')): ?><a class="fx-btn fx-btn--ghost" href="../purchases/index.php"><i class="bi bi-bag-plus"></i>Registrar compra</a><?php endif; ?>
                <?php if (farmacia_puede('inventory')): ?><a class="fx-btn fx-btn--ghost" href="../inventory/index.php"><i class="bi bi-plus-square"></i>Agregar producto</a><?php endif; ?>
            </div>
        </article>

        <div class="fx-kpis">
            <article class="fx-kpi">
                <span class="fx-kpi__ico"><i class="bi bi-calendar3"></i></span>
                <span class="fx-kpi__label">Ventas del mes</span>
                <strong><?php echo fx_q($mes['t'] ?? 0); ?></strong>
                <small><?php echo fx_n($mes['n'] ?? 0); ?> operaciones</small>
            </article>
            <article class="fx-kpi">
                <span class="fx-kpi__ico"><i class="bi bi-receipt-cutoff"></i></span>
                <span class="fx-kpi__label">Ticket promedio</span>
                <strong><?php echo fx_q($ticket); ?></strong>
                <small>Por venta este mes</small>
            </article>
            <article class="fx-kpi">
                <span class="fx-kpi__ico fx-kpi__ico--warn"><i class="bi bi-hourglass-split"></i></span>
                <span class="fx-kpi__label">Por vencer · 60 días</span>
                <strong><?php echo fx_n($inv['por_vencer'] ?? 0); ?></strong>
                <small><?php echo fx_n($inv['agotados'] ?? 0); ?> agotados · <?php echo fx_n($inv['bajos'] ?? 0); ?> con stock bajo</small>
            </article>
            <?php if (farmacia_puede('purchases')): ?>
            <article class="fx-kpi">
                <span class="fx-kpi__ico fx-kpi__ico--coral"><i class="bi bi-wallet2"></i></span>
                <span class="fx-kpi__label">Por pagar a proveedores</span>
                <strong><?php echo fx_q($pagar['saldo'] ?? 0); ?></strong>
                <small><?php echo fx_n($pagar['n'] ?? 0); ?> <?php echo ($pagar['n'] ?? 0) == 1 ? 'factura pendiente' : 'facturas pendientes'; ?></small>
            </article>
            <?php else: ?>
            <article class="fx-kpi">
                <span class="fx-kpi__ico fx-kpi__ico--info"><i class="bi bi-capsule"></i></span>
                <span class="fx-kpi__label">Productos</span>
                <strong><?php echo fx_n($inv['productos'] ?? 0); ?></strong>
                <small>En el inventario de la farmacia</small>
            </article>
            <?php endif; ?>
        </div>
    </section>

    <section class="fx-grid fx-grid--2">
        <article class="fx-card">
            <header class="fx-card__head">
                <div><h2>Ventas de los últimos 14 días</h2><p><?php echo fx_q($total14); ?> en total</p></div>
                <?php if (farmacia_puede('reports')): ?><a class="fx-link" href="reportes.php">Ver reportes <i class="bi bi-arrow-right"></i></a><?php endif; ?>
            </header>
            <?php echo fx_barras($serie, 'chart14'); ?>
            <dl class="fx-stats">
                <div><dt>Promedio diario</dt><dd><?php echo fx_q($prom14); ?></dd></div>
                <div><dt>Mejor día</dt><dd><?php echo $mejor_dia ? fx_fecha_corta($mejor_dia) . ' · ' . fx_q($dias[$mejor_dia]) : '—'; ?></dd></div>
                <div><dt>Días con ventas</dt><dd><?php echo count($con_venta); ?> de 14</dd></div>
            </dl>
        </article>

        <article class="fx-card">
            <header class="fx-card__head"><div><h2>Más vendidos</h2><p>Este mes, por unidades</p></div></header>
            <?php if (!$top): ?>
                <p class="fx-empty"><i class="bi bi-bag"></i>Todavía no hay ventas este mes.</p>
            <?php else: ?>
                <ol class="fx-rank">
                    <?php foreach ($top as $i => $t): ?>
                        <li>
                            <span class="fx-rank__n"><?php echo $i + 1; ?></span>
                            <div class="fx-rank__body">
                                <div class="fx-rank__row"><b><?php echo fx_e($t['nombre']); ?></b><span><?php echo fx_n($t['uds']); ?> uds</span></div>
                                <div class="fx-rank__bar"><span style="width: <?php echo max(4, round($t['uds'] / $top_max * 100)); ?>%"></span></div>
                                <small><?php echo fx_e($t['pres'] ?: '—'); ?> · <?php echo fx_q($t['ingreso']); ?></small>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </article>
    </section>

    <section class="fx-grid fx-grid--2">
        <article class="fx-card">
            <header class="fx-card__head">
                <div><h2>Alertas de inventario</h2><p><?php echo fx_n($inv['productos'] ?? 0); ?> productos · valor a precio de venta <?php echo fx_q($inv['valor'] ?? 0); ?></p></div>
                <?php if (farmacia_puede('inventory')): ?><a class="fx-link" href="../inventory/index.php">Inventario <i class="bi bi-arrow-right"></i></a><?php endif; ?>
            </header>
            <?php if (!$alertas): ?>
                <p class="fx-empty"><i class="bi bi-check2-circle"></i>Todo en orden: sin productos agotados ni por vencer.</p>
            <?php else: ?>
                <ul class="fx-list">
                    <?php foreach ($alertas as $a): [$lbl, $tone, $ico] = $tipos[$a['tipo']]; ?>
                        <li>
                            <span class="fx-dot fx-dot--<?php echo $tone; ?>"><i class="bi <?php echo $ico; ?>"></i></span>
                            <div class="fx-list__body">
                                <b><?php echo fx_e($a['nom_medicamento']); ?></b>
                                <small><?php echo fx_e($a['presentacion_med'] ?: '—'); ?><?php if ($a['fecha_vencimiento']): ?> · vence <?php echo date('d/m/Y', strtotime($a['fecha_vencimiento'])); ?><?php endif; ?></small>
                            </div>
                            <div class="fx-list__end">
                                <span class="fx-tag fx-tag--<?php echo $tone; ?>"><?php echo $lbl; ?></span>
                                <small><?php echo fx_n($a['cantidad_med']); ?> uds</small>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>

        <article class="fx-card">
            <header class="fx-card__head">
                <div><h2>Últimas ventas</h2><p>Lo más reciente del mostrador</p></div>
                <?php if (farmacia_puede('sales')): ?><a class="fx-link" href="../sales/index.php">Historial <i class="bi bi-arrow-right"></i></a><?php endif; ?>
            </header>
            <?php if (!$ultimas): ?>
                <p class="fx-empty"><i class="bi bi-receipt"></i>Aún no hay ventas registradas.</p>
            <?php else: ?>
                <ul class="fx-list">
                    <?php foreach ($ultimas as $u): ?>
                        <li>
                            <span class="fx-dot"><i class="bi <?php echo $pagos[$u['tipo_pago']] ?? 'bi-receipt'; ?>"></i></span>
                            <div class="fx-list__body">
                                <b><?php echo fx_e(trim($u['nombre_cliente'] ?? '') ?: 'Consumidor final'); ?></b>
                                <small>#VTA-<?php echo str_pad((string) $u['id_venta'], 5, '0', STR_PAD_LEFT); ?> · <?php echo fx_e($u['tipo_pago'] ?: '—'); ?> · <?php echo fx_fecha_corta($u['fecha_venta']) . ' ' . date('H:i', strtotime($u['fecha_venta'])); ?></small>
                            </div>
                            <div class="fx-list__end"><b class="fx-money"><?php echo fx_q($u['total']); ?></b></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
    </section>
</main>
<?php fx_foot(); ?>
