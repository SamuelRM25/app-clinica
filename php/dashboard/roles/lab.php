<?php
/**
 * dashboard/roles/lab.php — Dashboard del usuario de Laboratorio (tipo "lab").
 * Prioridad: crear órdenes nuevas y avanzar la cola de trabajo.
 */
require_once __DIR__ . '/_common.php';

$h = (int) $id_hospital;

$k_pendientes = (int) rd_query($conn, "SELECT COUNT(*) FROM ordenes_laboratorio WHERE estado = 'Pendiente' AND id_hospital = ?", [$h], 'value');
$k_muestras   = (int) rd_query($conn, "SELECT COUNT(*) FROM ordenes_laboratorio WHERE estado = 'Muestra_Recibida' AND id_hospital = ?", [$h], 'value');
$k_en_proceso = (int) rd_query($conn, "SELECT COUNT(*) FROM ordenes_laboratorio WHERE estado = 'En_Proceso' AND id_hospital = ?", [$h], 'value');
$k_validar    = (int) rd_query($conn, "SELECT COUNT(*) FROM orden_pruebas op JOIN ordenes_laboratorio ol ON op.id_orden = ol.id_orden WHERE op.estado = 'En_Proceso' AND ol.id_hospital = ?", [$h], 'value');
$k_hoy        = (int) rd_query($conn, "SELECT COUNT(*) FROM ordenes_laboratorio WHERE DATE(fecha_orden) = CURDATE() AND estado IN ('Completada', 'Validada') AND id_hospital = ?", [$h], 'value');
$k_retraso    = (int) rd_query($conn, "SELECT COUNT(*) FROM ordenes_laboratorio WHERE estado = 'Pendiente' AND DATE(fecha_orden) <= ? AND id_hospital = ?", [date('Y-m-d', strtotime('-2 days')), $h], 'value');
$k_ordenes_hoy = (int) rd_query($conn, "SELECT COUNT(*) FROM ordenes_laboratorio WHERE DATE(fecha_orden) = CURDATE() AND id_hospital = ?", [$h], 'value');

// Cola de trabajo: lo más antiguo primero
$cola = rd_query($conn, "
    SELECT ol.id_orden, ol.numero_orden, ol.estado, ol.fecha_orden, ol.laboratorio_externo,
           CONCAT(p.nombre, ' ', p.apellido) AS paciente,
           CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido, '')) AS doctor,
           COUNT(op.id_orden_prueba) AS num_pruebas
    FROM ordenes_laboratorio ol
    JOIN pacientes p ON ol.id_paciente = p.id_paciente
    LEFT JOIN usuarios u ON ol.id_doctor = u.idUsuario
    LEFT JOIN orden_pruebas op ON ol.id_orden = op.id_orden
    WHERE ol.estado IN ('Pendiente', 'Muestra_Recibida', 'En_Proceso') AND ol.id_hospital = ?
    GROUP BY ol.id_orden
    ORDER BY ol.fecha_orden ASC
    LIMIT 12", [$h]);

// Resultados listos para entregar
$listas = rd_query($conn, "
    SELECT ol.id_orden, ol.numero_orden, ol.estado, ol.fecha_orden, CONCAT(p.nombre, ' ', p.apellido) AS paciente
    FROM ordenes_laboratorio ol
    JOIN pacientes p ON ol.id_paciente = p.id_paciente
    WHERE ol.estado IN ('Completada', 'Validada') AND ol.id_hospital = ?
    ORDER BY ol.fecha_orden DESC
    LIMIT 6", [$h]);

// Pruebas más pedidas del mes
$top = rd_query($conn, "
    SELECT cp.nombre_prueba, COUNT(op.id_orden_prueba) AS cantidad
    FROM orden_pruebas op
    JOIN catalogo_pruebas cp ON op.id_prueba = cp.id_prueba
    JOIN ordenes_laboratorio ol ON op.id_orden = ol.id_orden
    WHERE MONTH(ol.fecha_orden) = MONTH(CURDATE()) AND YEAR(ol.fecha_orden) = YEAR(CURDATE()) AND ol.id_hospital = ?
    GROUP BY cp.id_prueba
    ORDER BY cantidad DESC
    LIMIT 5", [$h]);
$top_max = $top ? max(array_column($top, 'cantidad')) : 1;

$estado_txt = ['Pendiente' => 'Sin muestra', 'Muestra_Recibida' => 'Muestra recibida', 'En_Proceso' => 'En proceso', 'Completada' => 'Completada', 'Validada' => 'Validada'];
$estado_tone = ['Pendiente' => 'gold', 'Muestra_Recibida' => 'blue', 'En_Proceso' => 'navy', 'Completada' => 'green', 'Validada' => 'green'];

$activos = $k_pendientes + $k_muestras + $k_en_proceso;
$summary = $activos === 0
    ? 'No hay órdenes esperando. Todo al día.'
    : 'Hay <strong>' . $activos . ' ' . ($activos === 1 ? 'orden' : 'órdenes') . '</strong> en la cola' . ($k_retraso ? ', <strong>' . $k_retraso . '</strong> con más de 2 días.' : '.');

rd_hero($user_name, 'Laboratorio clínico', 'bi-droplet-half', $summary, [
    ['href' => '../laboratory/crear_orden.php', 'label' => 'Nueva orden', 'icon' => 'bi-plus-lg', 'style' => 'primary'],
    ['href' => '../laboratory/registrar_muestra.php', 'label' => 'Recibir muestras', 'icon' => 'bi-box-arrow-in-down', 'style' => 'ghost'],
    ['href' => '../laboratory/index.php', 'label' => 'Todas las órdenes', 'icon' => 'bi-list-ul', 'style' => 'ghost'],
    ['href' => '../almacen/index.php?area=laboratorio', 'label' => 'Almacén', 'icon' => 'bi-box-seam', 'style' => 'ghost'],
]);
?>

<div class="rd-kpis">
    <?php rd_kpi('Esperan muestra', $k_pendientes, 'bi-hourglass-split', 'gold', $k_retraso ? $k_retraso . ' con más de 2 días' : 'Al día', '../laboratory/registrar_muestra.php'); ?>
    <?php rd_kpi('Muestras por procesar', $k_muestras, 'bi-eyedropper', 'blue'); ?>
    <?php rd_kpi('Pruebas por validar', $k_validar, 'bi-clipboard2-check', 'navy'); ?>
    <?php rd_kpi('Terminadas hoy', $k_hoy, 'bi-check2-circle', 'green', $k_ordenes_hoy . ' órdenes nuevas hoy'); ?>
</div>

<div class="rd-grid">
    <section class="rd-panel">
        <header class="rd-panel__head">
            <h3>Cola de trabajo</h3>
            <a href="../laboratory/index.php">Ver todas</a>
        </header>
        <?php if (!$cola): ?>
            <?php rd_empty('bi-cup-hot', 'No hay órdenes en la cola', 'Las órdenes nuevas aparecen aquí en cuanto se crean.'); ?>
        <?php else: ?>
            <ul class="rd-list">
                <?php foreach ($cola as $o):
                    $tarde = $o['estado'] === 'Pendiente' && strtotime($o['fecha_orden']) <= strtotime('-2 days');
                    if ($o['estado'] === 'Pendiente') { $act_href = '../laboratory/registrar_muestra.php'; $act_txt = 'Recibir muestra'; }
                    else { $act_href = '../laboratory/procesar_orden.php?id=' . (int) $o['id_orden']; $act_txt = 'Procesar'; }
                ?>
                    <li class="rd-item<?php echo $tarde ? ' rd-item--late' : ''; ?>">
                        <span class="rd-avatar"><?php echo rd_e(rd_initials($o['paciente'])); ?></span>
                        <div class="rd-item__main">
                            <a class="rd-item__title" href="../laboratory/ver_orden.php?id=<?php echo (int) $o['id_orden']; ?>"><?php echo rd_e($o['paciente']); ?></a>
                            <?php
                                $meta = [$o['numero_orden'], (int) $o['num_pruebas'] . ' ' . ((int) $o['num_pruebas'] === 1 ? 'prueba' : 'pruebas')];
                                if (trim($o['doctor'])) $meta[] = 'Dr(a). ' . trim($o['doctor']);
                                if (!empty($o['laboratorio_externo'])) $meta[] = 'externo: ' . $o['laboratorio_externo'];
                            ?>
                            <span class="rd-item__meta"><?php echo rd_e(implode(', ', $meta)); ?></span>
                        </div>
                        <div class="rd-item__side">
                            <span class="rd-pill rd-pill--<?php echo $estado_tone[$o['estado']] ?? 'navy'; ?>"><?php echo rd_e($estado_txt[$o['estado']] ?? $o['estado']); ?></span>
                            <span class="rd-time<?php echo $tarde ? ' rd-time--late' : ''; ?>"><?php echo rd_e(rd_ago($o['fecha_orden'])); ?></span>
                        </div>
                        <a class="rd-action" href="<?php echo rd_e($act_href); ?>"><?php echo rd_e($act_txt); ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <div class="rd-side">
        <?php $rd_area = 'laboratorio'; include __DIR__ . '/_almacen.php'; ?>
        <section class="rd-panel">
            <header class="rd-panel__head"><h3>Resultados listos</h3></header>
            <?php if (!$listas): ?>
                <?php rd_empty('bi-file-earmark-medical', 'Sin resultados por entregar', 'Cuando termine una orden, la verá aquí para imprimirla.'); ?>
            <?php else: ?>
                <ul class="rd-list rd-list--compact">
                    <?php foreach ($listas as $o): ?>
                        <li class="rd-item">
                            <div class="rd-item__main">
                                <a class="rd-item__title" href="../laboratory/ver_orden.php?id=<?php echo (int) $o['id_orden']; ?>"><?php echo rd_e($o['paciente']); ?></a>
                                <span class="rd-item__meta"><?php echo rd_e($o['numero_orden']); ?>, <?php echo rd_e(rd_ago($o['fecha_orden'])); ?></span>
                            </div>
                            <a class="rd-icon-btn" href="../laboratory/imprimir_resultados.php?id=<?php echo (int) $o['id_orden']; ?>" title="Imprimir resultados" aria-label="Imprimir resultados de <?php echo rd_e($o['paciente']); ?>"><i class="bi bi-printer"></i></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="rd-panel">
            <header class="rd-panel__head"><h3>Más pedidas este mes</h3></header>
            <?php if (!$top): ?>
                <?php rd_empty('bi-bar-chart', 'Todavía sin datos', 'Se llena con las órdenes del mes.'); ?>
            <?php else: ?>
                <ul class="rd-bars">
                    <?php foreach ($top as $t): ?>
                        <li>
                            <span class="rd-bars__label"><?php echo rd_e($t['nombre_prueba']); ?></span>
                            <span class="rd-bars__value"><?php echo (int) $t['cantidad']; ?></span>
                            <span class="rd-bars__track"><span style="width: <?php echo round($t['cantidad'] / $top_max * 100); ?>%"></span></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
