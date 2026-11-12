<?php
/**
 * php/farmacia/sucursales.php — Módulo de Sucursales (próximamente).
 * Página informativa: todavía no guarda nada.
 */
require_once __DIR__ . '/_init.php';

$local = $_SESSION['hospital_nombre'] ?? FARMA_NOMBRE;
$productos = (int) fx_val($conn, "SELECT COUNT(*) FROM inventario WHERE id_hospital = ? AND COALESCE(estado,'Disponible') <> 'Pendiente'", [$id_hospital]);
$usuarios = (int) fx_val($conn, "SELECT COUNT(*) FROM usuarios WHERE id_hospital = ? AND COALESCE(activo,1) = 1", [$id_hospital]);

$funciones = [
    ['bi-boxes',          'Inventario por sucursal',     'Cada local con sus propias existencias, lotes y vencimientos.'],
    ['bi-arrow-left-right','Traslados entre sucursales', 'Mueva producto de una farmacia a otra con su comprobante.'],
    ['bi-graph-up',       'Ventas consolidadas',         'Compare sucursales y vea el total de la cadena en un solo reporte.'],
    ['bi-people',         'Usuarios por sucursal',       'Cada cajero entra solo a la farmacia donde trabaja.'],
    ['bi-tags',           'Precios por sucursal',        'Precios generales o diferentes según el local.'],
    ['bi-bag-check',      'Compras centralizadas',       'Compre para toda la cadena y reparta al recibir.'],
];

farmacia_shell('sucursales', ['sub' => 'Próximamente', 'accion' => false]);
fx_head('Sucursales');
?>
<main class="fx-main">
    <section class="fx-soon">
        <div class="fx-soon__copy">
            <span class="fx-badge-soon"><i class="bi bi-stars"></i>Próximamente</span>
            <h2>Todas sus farmacias, un solo sistema.</h2>
            <p>Con Sucursales podrá manejar varios locales desde la misma cuenta: inventario, ventas y usuarios separados por farmacia, con reportes de toda la cadena.</p>
            <button type="button" class="fx-btn" disabled aria-disabled="true"><i class="bi bi-plus-lg"></i>Agregar sucursal</button>
            <small class="fx-soon__hint">Disponible en una próxima versión de <?php echo fx_e(FARMA_NOMBRE); ?>.</small>
        </div>

        <div class="fx-soon__art" aria-hidden="true">
            <div class="fx-branch is-main">
                <span class="fx-branch__ico"><i class="bi bi-shop"></i></span>
                <div><b><?php echo fx_e($local); ?></b><small>Sucursal principal · activa</small></div>
                <span class="fx-tag">Actual</span>
                <dl><div><dt>Productos</dt><dd><?php echo fx_n($productos); ?></dd></div><div><dt>Usuarios</dt><dd><?php echo fx_n($usuarios); ?></dd></div></dl>
            </div>
            <div class="fx-branch is-ghost">
                <span class="fx-branch__ico"><i class="bi bi-shop-window"></i></span>
                <div><b>Sucursal 2</b><small>Sin configurar</small></div>
            </div>
            <div class="fx-branch is-ghost">
                <span class="fx-branch__ico"><i class="bi bi-shop-window"></i></span>
                <div><b>Sucursal 3</b><small>Sin configurar</small></div>
            </div>
        </div>
    </section>

    <section>
        <h3 class="fx-h3">Lo que podrá hacer</h3>
        <div class="fx-features">
            <?php foreach ($funciones as [$ico, $t, $d]): ?>
                <article class="fx-feature">
                    <span class="fx-feature__ico"><i class="bi <?php echo $ico; ?>"></i></span>
                    <h4><?php echo fx_e($t); ?></h4>
                    <p><?php echo fx_e($d); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</main>
<?php fx_foot(); ?>
