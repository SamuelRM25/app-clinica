<?php
/**
 * dashboard/roles/_nav.php — Menú lateral para Laboratorio, Quirófano y Farmacia.
 * Solo muestra lo que cada rol puede usar.
 */
$rd_menu = [
    'lab' => [
        ['module' => 'laboratory', 'href' => '../laboratory/crear_orden.php',       'icon' => 'bi-plus-circle',      'text' => 'Nueva orden'],
        ['module' => 'laboratory', 'href' => '../laboratory/index.php',             'icon' => 'bi-droplet-half',     'text' => 'Laboratorio'],
        ['module' => 'laboratory', 'href' => '../laboratory/registrar_muestra.php', 'icon' => 'bi-box-arrow-in-down', 'text' => 'Recibir muestras'],
    ],
    'qx' => [
        ['module' => 'surgery', 'href' => '../surgery/index.php',         'icon' => 'bi-heart-pulse',  'text' => 'Quirófano'],
        ['module' => 'surgery', 'href' => '../surgery/nueva_cirugia.php', 'icon' => 'bi-plus-circle',  'text' => 'Programar cirugía'],
        ['module' => 'surgery', 'href' => '../surgery/salas.php',         'icon' => 'bi-door-open',    'text' => 'Salas'],
    ],
    'farm' => [
        ['module' => 'pharmacy',  'href' => '../dispensary/index.php', 'icon' => 'bi-cart-plus', 'text' => 'Punto de venta'],
        ['module' => 'inventory', 'href' => '../inventory/index.php',  'icon' => 'bi-box-seam',  'text' => 'Inventario'],
    ],
];
?>
<li class="nav-item">
    <a href="index.php" class="nav-link active">
        <i class="bi bi-speedometer2 nav-icon"></i>
        <span class="nav-text">Mi dashboard</span>
    </a>
</li>
<?php foreach ($rd_menu[$user_type] ?? [] as $item): ?>
    <li class="nav-item">
        <?php if (is_module_active($item['module'])): ?>
            <a href="<?php echo htmlspecialchars($item['href']); ?>" class="nav-link">
                <i class="bi <?php echo $item['icon']; ?> nav-icon"></i>
                <span class="nav-text"><?php echo htmlspecialchars($item['text']); ?></span>
            </a>
        <?php else: ?>
            <a href="javascript:void(0)" class="nav-link locked" onclick="lockedModule('<?php echo htmlspecialchars($item['text'], ENT_QUOTES); ?>')">
                <i class="bi <?php echo $item['icon']; ?> nav-icon"></i>
                <span class="nav-text"><?php echo htmlspecialchars($item['text']); ?></span>
            </a>
        <?php endif; ?>
    </li>
<?php endforeach; ?>
