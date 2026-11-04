<?php
/**
 * dashboard/roles/farm.php — Dashboard del usuario de Farmacia (tipo "farm").
 * Prioridad: vender, y saber qué reabastecer o qué está por vencer.
 */
require_once __DIR__ . '/_common.php';

$h   = (int) $id_hospital;
$uid = (int) $user_id;
$hoy = date('Y-m-d');
$en30 = date('Y-m-d', strtotime('+30 days'));
$en60 = date('Y-m-d', strtotime('+60 days'));

$ventas_hoy = rd_query($conn, "SELECT COUNT(*) AS n, COALESCE(SUM(total), 0) AS total FROM ventas WHERE DATE(fecha_venta) = ? AND id_hospital = ?", [$hoy, $h], 'row');
$mis_ventas = rd_query($conn, "SELECT COUNT(*) AS n, COALESCE(SUM(total), 0) AS total FROM ventas WHERE DATE(fecha_venta) = ? AND id_usuario = ? AND id_hospital = ?", [$hoy, $uid, $h], 'row');

$k_agotados = (int) rd_query($conn, "SELECT COUNT(*) FROM inventario WHERE cantidad_med = 0 AND id_hospital = ?", [$h], 'value');
$k_bajos    = (int) rd_query($conn, "SELECT COUNT(*) FROM inventario WHERE cantidad_med > 0 AND cantidad_med <= 10 AND id_hospital = ?", [$h], 'value');
$k_vencen   = (int) rd_query($conn, "SELECT COUNT(*) FROM inventario WHERE fecha_vencimiento BETWEEN ? AND ? AND cantidad_med > 0 AND id_hospital = ?", [$hoy, $en30, $h], 'value');
$k_vencidos = (int) rd_query($conn, "SELECT COUNT(*) FROM inventario WHERE fecha_vencimiento < ? AND cantidad_med > 0 AND id_hospital = ?", [$hoy, $h], 'value');
$k_recibir  = (int) rd_query($conn, "SELECT COUNT(*) FROM inventario WHERE estado = 'Pendiente' AND id_hospital = ?", [$h], 'value');

$reabastecer = rd_query($conn, "
    SELECT id_inventario, nom_medicamento, presentacion_med, casa_farmaceutica, cantidad_med
    FROM inventario
    WHERE cantidad_med <= 10 AND id_hospital = ?
    ORDER BY cantidad_med ASC, nom_medicamento ASC
    LIMIT 8", [$h]);

$por_vencer = rd_query($conn, "
    SELECT id_inventario, nom_medicamento, presentacion_med, cantidad_med, fecha_vencimiento
    FROM inventario
    WHERE fecha_vencimiento IS NOT NULL AND fecha_vencimiento <= ? AND cantidad_med > 0 AND id_hospital = ?
    ORDER BY fecha_vencimiento ASC
    LIMIT 8", [$en60, $h]);

$ultimas = rd_query($conn, "
    SELECT v.id_venta, v.nombre_cliente, v.total, v.tipo_pago, v.estado, v.fecha_venta,
           u.nombre AS vendedor
    FROM ventas v
    LEFT JOIN usuarios u ON v.id_usuario = u.idUsuario
    WHERE DATE(v.fecha_venta) = ? AND v.id_hospital = ?
    ORDER BY v.fecha_venta DESC
    LIMIT 8", [$hoy, $h]);

$n_hoy = (int) ($ventas_hoy['n'] ?? 0);
$alertas = $k_agotados + $k_vencidos;
$summary = ($n_hoy ? 'Van <strong>' . $n_hoy . ' ' . ($n_hoy === 1 ? 'venta' : 'ventas') . '</strong> hoy por <strong>' . rd_money($ventas_hoy['total'] ?? 0) . '</strong>. ' : 'Todavía no hay ventas hoy. ')
         . ($alertas ? 'Revise <strong>' . $alertas . '</strong> ' . ($alertas === 1 ? 'producto agotado o vencido' : 'productos agotados o vencidos') . '.' : 'El inventario está en orden.');

rd_hero($user_name, 'Farmacia', 'bi-capsule', $summary, [
    ['href' => '../dispensary/index.php', 'label' => 'Abrir punto de venta', 'icon' => 'bi-cart-plus', 'style' => 'primary'],
    ['href' => '../inventory/index.php', 'label' => 'Inventario', 'icon' => 'bi-box-seam', 'style' => 'ghost'],
]);
?>

<div class="rd-kpis">
    <?php rd_kpi('Mis ventas de hoy', rd_money($mis_ventas['total'] ?? 0), 'bi-person-check', 'navy', (int) ($mis_ventas['n'] ?? 0) . ' de ' . $n_hoy . ' ventas del día'); ?>
    <?php rd_kpi('Ventas de hoy', rd_money($ventas_hoy['total'] ?? 0), 'bi-cash-stack', 'green', $n_hoy . ' ' . ($n_hoy === 1 ? 'venta' : 'ventas')); ?>
    <?php rd_kpi('Agotados o bajos', $k_agotados + $k_bajos, 'bi-exclamation-triangle', $k_agotados ? 'red' : 'gold', $k_agotados . ' agotados, ' . $k_bajos . ' con 10 o menos', '../inventory/index.php'); ?>
    <?php rd_kpi('Vencen en 30 días', $k_vencen, 'bi-calendar-x', 'gold', $k_vencidos ? $k_vencidos . ' ya vencidos' : 'Ninguno vencido', '../inventory/index.php'); ?>
</div>

<?php if ($k_recibir > 0): ?>
    <a class="rd-banner" href="../inventory/index.php">
        <i class="bi bi-truck"></i>
        <span>Hay <strong><?php echo $k_recibir; ?></strong> <?php echo $k_recibir === 1 ? 'producto' : 'productos'; ?> de compras por recibir en inventario.</span>
        <span class="rd-banner__go">Revisar</span>
    </a>
<?php endif; ?>

<div class="rd-grid">
    <section class="rd-panel">
        <header class="rd-panel__head">
            <h3>Reabastecer</h3>
            <a href="../inventory/index.php">Ver inventario</a>
        </header>
        <?php if (!$reabastecer): ?>
            <?php rd_empty('bi-check2-all', 'Nada por reabastecer', 'Todos los productos tienen más de 10 unidades.'); ?>
        <?php else: ?>
            <ul class="rd-list">
                <?php foreach ($reabastecer as $m): $cant = (int) $m['cantidad_med']; ?>
                    <li class="rd-item">
                        <span class="rd-stock rd-stock--<?php echo $cant === 0 ? 'out' : 'low'; ?>"><?php echo $cant; ?></span>
                        <div class="rd-item__main">
                            <span class="rd-item__title"><?php echo rd_e($m['nom_medicamento']); ?></span>
                            <span class="rd-item__meta"><?php echo rd_e(trim(($m['presentacion_med'] ?? '') . ($m['casa_farmaceutica'] ? ', ' . $m['casa_farmaceutica'] : ''), ', ')); ?></span>
                        </div>
                        <span class="rd-pill rd-pill--<?php echo $cant === 0 ? 'red' : 'gold'; ?>"><?php echo $cant === 0 ? 'Agotado' : 'Bajo'; ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <div class="rd-side">
        <section class="rd-panel">
            <header class="rd-panel__head"><h3>Vencen pronto</h3></header>
            <?php if (!$por_vencer): ?>
                <?php rd_empty('bi-calendar-check', 'Nada vence en 60 días', 'Los productos próximos a vencer aparecen aquí.'); ?>
            <?php else: ?>
                <ul class="rd-list rd-list--compact">
                    <?php foreach ($por_vencer as $m):
                        $dias = (int) floor((strtotime($m['fecha_vencimiento']) - strtotime($hoy)) / 86400);
                        $txt = $dias < 0 ? 'Vencido' : ($dias === 0 ? 'Vence hoy' : 'En ' . $dias . ' ' . ($dias === 1 ? 'día' : 'días'));
                        $tone = $dias < 0 ? 'red' : ($dias <= 30 ? 'gold' : 'blue');
                    ?>
                        <li class="rd-item">
                            <div class="rd-item__main">
                                <span class="rd-item__title"><?php echo rd_e($m['nom_medicamento']); ?></span>
                                <span class="rd-item__meta"><?php echo date('d/m/Y', strtotime($m['fecha_vencimiento'])); ?>, <?php echo (int) $m['cantidad_med']; ?> unid.</span>
                            </div>
                            <span class="rd-pill rd-pill--<?php echo $tone; ?>"><?php echo rd_e($txt); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="rd-panel">
            <header class="rd-panel__head"><h3>Ventas de hoy</h3></header>
            <?php if (!$ultimas): ?>
                <?php rd_empty('bi-receipt', 'Sin ventas todavía', 'Las ventas del punto de venta aparecen aquí al momento.'); ?>
            <?php else: ?>
                <ul class="rd-list rd-list--compact">
                    <?php foreach ($ultimas as $v): ?>
                        <li class="rd-item">
                            <div class="rd-item__main">
                                <span class="rd-item__title"><?php echo rd_e($v['nombre_cliente'] ?: 'Consumidor final'); ?></span>
                                <span class="rd-item__meta"><?php echo date('H:i', strtotime($v['fecha_venta'])); ?><?php echo $v['tipo_pago'] ? ', ' . rd_e($v['tipo_pago']) : ''; ?><?php echo $v['vendedor'] ? ', ' . rd_e($v['vendedor']) : ''; ?></span>
                            </div>
                            <span class="rd-amount"><?php echo rd_money($v['total']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
