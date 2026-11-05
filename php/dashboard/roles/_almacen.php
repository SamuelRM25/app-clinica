<?php
/**
 * dashboard/roles/_almacen.php — Panel "Almacén" para los dashboards de Laboratorio y Quirófano.
 * Requiere $conn, $id_hospital y $rd_area ('laboratorio' | 'quirofano').
 */
if (!area_tables_ready($conn) || !in_array($rd_area, area_allowed_for_user(), true)) return;

$rd_alm = rd_query($conn, "
    SELECT id_item, nombre, unidad, stock, stock_minimo
    FROM area_items
    WHERE id_hospital = ? AND area = ? AND activo = 1 AND stock <= stock_minimo
    ORDER BY (stock < 0) DESC, (stock = 0) DESC, (stock / NULLIF(stock_minimo, 0)) ASC, nombre
    LIMIT 6", [(int) $id_hospital, $rd_area]);
$rd_alm_total = (int) rd_query($conn, "SELECT COUNT(*) FROM area_items WHERE id_hospital = ? AND area = ? AND activo = 1 AND stock <= stock_minimo", [(int) $id_hospital, $rd_area], 'value');
$rd_alm_url = '../almacen/index.php?area=' . $rd_area;
?>
<section class="rd-panel">
    <header class="rd-panel__head">
        <h3>Almacén<?php echo $rd_alm_total ? ' · ' . $rd_alm_total . ' por reponer' : ''; ?></h3>
        <a href="<?php echo rd_e($rd_alm_url); ?>">Abrir</a>
    </header>
    <?php if (!$rd_alm): ?>
        <?php rd_empty('bi-box-seam', 'Existencias al día', 'Ningún insumo está bajo su mínimo.'); ?>
    <?php else: ?>
        <ul class="rd-list rd-list--compact">
            <?php foreach ($rd_alm as $a): $s = (float) $a['stock']; ?>
                <li class="rd-item">
                    <div class="rd-item__main">
                        <span class="rd-item__title"><?php echo rd_e($a['nombre']); ?></span>
                        <span class="rd-item__meta">Mínimo <?php echo rd_e(area_qty($a['stock_minimo'])); ?> <?php echo rd_e($a['unidad']); ?></span>
                    </div>
                    <span class="rd-pill rd-pill--<?php echo $s <= 0 ? 'red' : 'gold'; ?>"><?php echo rd_e(area_qty($s)); ?> <?php echo rd_e($a['unidad']); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <a class="rd-action mt-2 d-inline-block" href="<?php echo rd_e($rd_alm_url); ?>#compras">Registrar compra</a>
    <?php endif; ?>
</section>
