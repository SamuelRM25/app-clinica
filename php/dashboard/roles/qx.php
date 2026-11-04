<?php
/**
 * dashboard/roles/qx.php — Dashboard del usuario de Quirófano (tipo "qx").
 * Prioridad: lo que está pasando ahora en sala y la agenda del día.
 */
require_once __DIR__ . '/_common.php';

$h = (int) $id_hospital;

$k_hoy        = (int) rd_query($conn, "SELECT COUNT(*) FROM cirugias WHERE DATE(fecha_programada) = CURDATE() AND id_hospital = ?", [$h], 'value');
$k_curso      = (int) rd_query($conn, "SELECT COUNT(*) FROM cirugias WHERE estado = 'En_Curso' AND id_hospital = ?", [$h], 'value');
$k_programadas = (int) rd_query($conn, "SELECT COUNT(*) FROM cirugias WHERE estado = 'Programada' AND id_hospital = ?", [$h], 'value');
$k_fin_hoy    = (int) rd_query($conn, "SELECT COUNT(*) FROM cirugias WHERE DATE(fecha_fin) = CURDATE() AND estado = 'Finalizada' AND id_hospital = ?", [$h], 'value');

$salas = rd_query($conn, "SELECT id_sala, nombre, estado FROM salas_quirurgicas WHERE id_hospital = ? ORDER BY nombre", [$h]);
$salas_libres = count(array_filter($salas, fn($s) => $s['estado'] === 'Disponible'));

$sql_base = "
    SELECT c.id_cirugia, c.numero_cirugia, c.estado, c.fecha_programada, c.fecha_inicio, c.fecha_fin,
           COALESCE(CONCAT(p.nombre, ' ', p.apellido), CONCAT(c.referido_nombre, ' ', c.referido_apellido)) AS paciente,
           s.nombre AS sala
    FROM cirugias c
    LEFT JOIN pacientes p ON c.id_paciente = p.id_paciente
    LEFT JOIN salas_quirurgicas s ON c.id_sala = s.id_sala
    WHERE c.id_hospital = ? ";

$en_curso = rd_query($conn, $sql_base . "AND c.estado = 'En_Curso' ORDER BY c.fecha_inicio ASC", [$h]);
$agenda   = rd_query($conn, $sql_base . "AND DATE(c.fecha_programada) = CURDATE() ORDER BY c.fecha_programada ASC", [$h]);
$proximas = rd_query($conn, $sql_base . "AND c.estado = 'Programada' AND DATE(c.fecha_programada) > CURDATE() ORDER BY c.fecha_programada ASC LIMIT 6", [$h]);

$estado_txt  = ['Programada' => 'Programada', 'En_Curso' => 'En curso', 'Finalizada' => 'Finalizada', 'Cancelada' => 'Cancelada'];
$estado_tone = ['Programada' => 'blue', 'En_Curso' => 'gold', 'Finalizada' => 'green', 'Cancelada' => 'red'];
$sala_tone   = ['Disponible' => 'green', 'Ocupada' => 'gold', 'Mantenimiento' => 'red'];

if ($k_curso > 0) {
    $summary = '<strong>' . $k_curso . ' ' . ($k_curso === 1 ? 'cirugía' : 'cirugías') . '</strong> en curso ahora. ' . $k_hoy . ' en la agenda de hoy.';
} elseif ($k_hoy > 0) {
    $summary = 'Hay <strong>' . $k_hoy . ' ' . ($k_hoy === 1 ? 'cirugía' : 'cirugías') . '</strong> en la agenda de hoy.';
} else {
    $summary = 'No hay cirugías en la agenda de hoy.';
}

rd_hero($user_name, 'Quirófano', 'bi-heart-pulse', $summary, [
    ['href' => '../surgery/nueva_cirugia.php', 'label' => 'Programar cirugía', 'icon' => 'bi-plus-lg', 'style' => 'primary'],
    ['href' => '../surgery/index.php', 'label' => 'Todas las cirugías', 'icon' => 'bi-list-ul', 'style' => 'ghost'],
    ['href' => '../surgery/salas.php', 'label' => 'Salas', 'icon' => 'bi-door-open', 'style' => 'ghost'],
]);
?>

<div class="rd-kpis">
    <?php rd_kpi('En curso', $k_curso, 'bi-activity', 'gold'); ?>
    <?php rd_kpi('Agenda de hoy', $k_hoy, 'bi-calendar2-check', 'navy', $k_fin_hoy . ' ' . ($k_fin_hoy === 1 ? 'finalizada' : 'finalizadas')); ?>
    <?php rd_kpi('Programadas', $k_programadas, 'bi-calendar2-week', 'blue', 'Pendientes de realizar'); ?>
    <?php rd_kpi('Salas libres', $salas_libres . '/' . count($salas), 'bi-door-open', 'green', '', '../surgery/salas.php'); ?>
</div>

<?php if ($en_curso): ?>
    <section class="rd-live" aria-label="Cirugías en curso">
        <?php foreach ($en_curso as $c): ?>
            <a class="rd-live__card" href="../surgery/detalle_cirugia.php?id=<?php echo (int) $c['id_cirugia']; ?>">
                <span class="rd-live__dot" aria-hidden="true"></span>
                <span class="rd-live__label">En curso<?php echo $c['sala'] ? ', ' . rd_e($c['sala']) : ''; ?></span>
                <strong><?php echo rd_e($c['paciente'] ?: 'Paciente sin nombre'); ?></strong>
                <span class="rd-live__time"><?php echo $c['fecha_inicio'] ? rd_e(rd_elapsed($c['fecha_inicio'])) : 'Sin hora de inicio'; ?></span>
                <span class="rd-live__meta"><?php echo rd_e($c['numero_cirugia']); ?><?php echo $c['fecha_inicio'] ? ', inició ' . date('H:i', strtotime($c['fecha_inicio'])) : ''; ?></span>
            </a>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<div class="rd-grid">
    <section class="rd-panel">
        <header class="rd-panel__head">
            <h3>Agenda de hoy</h3>
            <a href="../surgery/index.php">Ver todas</a>
        </header>
        <?php if (!$agenda): ?>
            <?php rd_empty('bi-calendar2', 'Nada agendado para hoy', 'Las cirugías programadas para hoy aparecen aquí en orden de hora.'); ?>
        <?php else: ?>
            <ol class="rd-timeline">
                <?php foreach ($agenda as $c): ?>
                    <li class="rd-timeline__item rd-timeline__item--<?php echo $estado_tone[$c['estado']] ?? 'navy'; ?>">
                        <span class="rd-timeline__hour"><?php echo date('H:i', strtotime($c['fecha_programada'])); ?></span>
                        <div class="rd-item__main">
                            <a class="rd-item__title" href="../surgery/detalle_cirugia.php?id=<?php echo (int) $c['id_cirugia']; ?>"><?php echo rd_e($c['paciente'] ?: 'Paciente sin nombre'); ?></a>
                            <span class="rd-item__meta"><?php echo rd_e($c['numero_cirugia']); ?><?php echo $c['sala'] ? ', ' . rd_e($c['sala']) : ''; ?></span>
                        </div>
                        <span class="rd-pill rd-pill--<?php echo $estado_tone[$c['estado']] ?? 'navy'; ?>"><?php echo rd_e($estado_txt[$c['estado']] ?? $c['estado']); ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <div class="rd-side">
        <section class="rd-panel">
            <header class="rd-panel__head"><h3>Salas</h3><a href="../surgery/salas.php">Gestionar</a></header>
            <?php if (!$salas): ?>
                <?php rd_empty('bi-door-closed', 'No hay salas registradas', 'Un administrador puede crearlas en Quirófano > Salas.'); ?>
            <?php else: ?>
                <ul class="rd-rooms">
                    <?php foreach ($salas as $s): ?>
                        <li class="rd-room rd-room--<?php echo $sala_tone[$s['estado']] ?? 'navy'; ?>">
                            <span class="rd-room__name"><?php echo rd_e($s['nombre']); ?></span>
                            <span class="rd-room__state"><?php echo rd_e($s['estado']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="rd-panel">
            <header class="rd-panel__head"><h3>Próximos días</h3></header>
            <?php if (!$proximas): ?>
                <?php rd_empty('bi-calendar-week', 'Sin cirugías próximas', 'Las que programe aparecerán aquí.'); ?>
            <?php else: ?>
                <ul class="rd-list rd-list--compact">
                    <?php foreach ($proximas as $c): ?>
                        <li class="rd-item">
                            <span class="rd-date-chip"><b><?php echo date('d', strtotime($c['fecha_programada'])); ?></b><?php echo rd_mes_corto($c['fecha_programada']); ?></span>
                            <div class="rd-item__main">
                                <a class="rd-item__title" href="../surgery/detalle_cirugia.php?id=<?php echo (int) $c['id_cirugia']; ?>"><?php echo rd_e($c['paciente'] ?: 'Paciente sin nombre'); ?></a>
                                <span class="rd-item__meta"><?php echo date('H:i', strtotime($c['fecha_programada'])); ?><?php echo $c['sala'] ? ', ' . rd_e($c['sala']) : ''; ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
