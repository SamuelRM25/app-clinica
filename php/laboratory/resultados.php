<?php
/**
 * laboratory/resultados.php — Registrar resultados de una orden con las plantillas.
 */
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../auth/login.php'); exit; }
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';
require_once '../../includes/module_guard.php';

check_module_access('laboratory');
verify_session();
date_default_timezone_set('America/Guatemala');

$id_hospital = hospital_id();
$rol = $_SESSION['tipoUsuario'] ?? '';
$id_orden = (int) ($_GET['id'] ?? 0);
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$conn = (new Database())->getConnection();
if (!lab_tables_ready($conn)) { header('Location: plantillas.php'); exit; }

$st = $conn->prepare("SELECT ol.*, p.nombre, p.apellido, p.genero, p.fecha_nacimiento,
                             TRIM(CONCAT(COALESCE(u.nombre,''), ' ', COALESCE(u.apellido,''))) AS doctor
                      FROM ordenes_laboratorio ol
                      JOIN pacientes p ON ol.id_paciente = p.id_paciente
                      LEFT JOIN usuarios u ON ol.id_doctor = u.idUsuario
                      WHERE ol.id_orden = ? AND ol.id_hospital = ?");
$st->execute([$id_orden, $id_hospital]);
$orden = $st->fetch(PDO::FETCH_ASSOC);
if (!$orden) { header('Location: index.php'); exit; }

$st = $conn->prepare("SELECT op.id_orden_prueba, op.estado, cp.id_prueba, cp.nombre_prueba, cp.codigo_prueba, cp.id_plantilla,
                             lp.nombre AS plantilla_nombre, lp.diseno, lp.activo AS plantilla_activa,
                             lr.plantilla AS snap, lr.valores, lr.observaciones, lr.finalizado, lr.fecha_guardado
                      FROM orden_pruebas op
                      JOIN catalogo_pruebas cp ON cp.id_prueba = op.id_prueba
                      LEFT JOIN lab_plantillas lp ON lp.id_plantilla = cp.id_plantilla AND lp.id_hospital = ?
                      LEFT JOIN lab_resultados lr ON lr.id_orden_prueba = op.id_orden_prueba
                      WHERE op.id_orden = ? AND op.estado <> 'Devuelto'
                      ORDER BY op.id_orden_prueba");
$st->execute([$id_hospital, $id_orden]);
$pruebas = $st->fetchAll(PDO::FETCH_ASSOC);

$finalizada = in_array($orden['estado'], ['Completada', 'Validada'], true);
$pendiente = $orden['estado'] === 'Pendiente';
$editable = !$finalizada && !$pendiente;
$sin_plantilla = array_filter($pruebas, fn($p) => !$p['snap'] && !$p['diseno']);
$puede_plantillas = in_array($rol, ['admin', 'lab'], true);
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $e(csrf_token()); ?>">
    <title>Resultados <?php echo $e($orden['numero_orden']); ?> · CMRM</title>
    <link rel="icon" type="image/png" href="../../assets/img/favicon-cmrm.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=optional" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
    <?php include '../../includes/theme_head.php'; ?>
    <link rel="stylesheet" href="../../assets/css/processing-overlay.css">
    <link rel="stylesheet" href="../../assets/css/almacen.css?v=2">
    <link rel="stylesheet" href="../../assets/css/lab_resultados.css?v=1">
</head>
<body>
<div class="marble-effect"></div>
<div class="dashboard-container">
    <header class="dashboard-header">
        <div class="header-content">
            <div class="brand-container"><img src="../../assets/img/logo_cmrm.svg" alt="Centro Médico Dr. Roberto Morales" class="brand-logo"></div>
            <div class="header-controls">
                <div class="theme-toggle"><button id="themeSwitch" class="theme-btn" aria-label="Cambiar tema claro/oscuro"><i class="bi bi-sun theme-icon sun-icon"></i><i class="bi bi-moon theme-icon moon-icon"></i></button></div>
                <a href="index.php" class="back-btn"><i class="bi bi-arrow-left"></i><span>Laboratorio</span></a>
            </div>
        </div>
    </header>

    <main class="main-content al-page">
        <section class="al-head">
            <div>
                <span class="al-eyebrow"><i class="bi bi-clipboard2-pulse"></i> Orden <?php echo $e($orden['numero_orden']); ?></span>
                <h1><?php echo $e($orden['nombre'] . ' ' . $orden['apellido']); ?></h1>
                <p><?php echo $finalizada ? 'Resultados finalizados y enviados a la historia clínica.' : ($pendiente ? 'La muestra todavía no se ha recibido.' : 'Escriba los resultados en cada plantilla.'); ?></p>
            </div>
            <div class="al-actions">
                <a class="al-btn al-btn--line" href="imprimir.php?id=<?php echo $id_orden; ?>" target="_blank"><i class="bi bi-printer"></i><?php echo $finalizada ? 'Imprimir' : 'Vista previa'; ?></a>
                <?php if ($finalizada): ?>
                    <button type="button" class="al-btn al-btn--gold" id="btnReabrir"><i class="bi bi-pencil"></i>Corregir</button>
                <?php elseif ($pendiente): ?>
                    <button type="button" class="al-btn al-btn--gold" id="btnRecibir"><i class="bi bi-box-arrow-in-down"></i>Marcar muestra recibida</button>
                <?php endif; ?>
            </div>
        </section>

        <section class="lr-pat" aria-label="Datos de la orden">
            <div><span>Edad</span><b><?php echo $e(lab_edad($orden['fecha_nacimiento']) ?: '—'); ?></b></div>
            <div><span>Sexo</span><b><?php echo $e($orden['genero'] ?: '—'); ?></b></div>
            <div><span>Médico</span><b><?php echo $e(trim($orden['doctor']) ? 'Dr(a). ' . trim($orden['doctor']) : 'Particular'); ?></b></div>
            <div><span>Fecha de orden</span><b><?php echo date('d/m/Y H:i', strtotime($orden['fecha_orden'])); ?></b></div>
        </section>

        <?php if ($sin_plantilla): ?>
            <div class="al-alert" role="alert"><i class="bi bi-exclamation-triangle"></i>
                <div><strong><?php echo count($sin_plantilla) === 1 ? 'Una prueba no tiene' : count($sin_plantilla) . ' pruebas no tienen'; ?> plantilla de resultados.</strong>
                    <?php echo $puede_plantillas ? 'Asígnela en el <a href="catalogo_pruebas.php">catálogo</a> o cree una en <a href="plantillas.php">Plantillas</a>, y recargue esta página.' : 'Pida a Laboratorio que la asigne.'; ?>
                    También puede <a href="procesar_orden.php?id=<?php echo $id_orden; ?>">subir el archivo del equipo</a>.</div>
            </div>
        <?php endif; ?>

        <form id="resForm" autocomplete="off" onsubmit="return false">
            <?php foreach ($pruebas as $p):
                $tplSrc = $p['snap'] ?: $p['diseno'];
                $vals = $p['valores'] ? (json_decode($p['valores'], true) ?: []) : [];
                $tiene = lab_tiene_valores($vals); ?>
                <article class="lr-card<?php echo $tiene ? ' is-done' : ''; ?>">
                    <header class="lr-card__head">
                        <div>
                            <h2><?php echo $e($p['nombre_prueba']); ?></h2>
                            <small><?php echo $e($p['plantilla_nombre'] ? 'Plantilla: ' . $p['plantilla_nombre'] : ($p['snap'] ? 'Plantilla guardada con el resultado' : 'Sin plantilla')); ?></small>
                        </div>
                        <span class="lr-state lr-state--<?php echo $tiene ? 'ok' : 'wait'; ?>"><i class="bi <?php echo $tiene ? 'bi-check2-circle' : 'bi-hourglass-split'; ?>"></i><?php echo $tiene ? 'Con resultado' : 'Sin resultado'; ?></span>
                    </header>
                    <div class="lr-card__body">
                        <?php if (!$tplSrc): ?>
                            <p class="al-muted m-0">Esta prueba no tiene plantilla.</p>
                        <?php else:
                            echo lab_render_form(lab_tpl_normalize($tplSrc), $vals, 'r[' . (int) $p['id_orden_prueba'] . ']', !$editable); ?>
                            <div class="lr-obs">
                                <label for="obs<?php echo (int) $p['id_orden_prueba']; ?>">Observaciones</label>
                                <textarea class="form-control" rows="2" id="obs<?php echo (int) $p['id_orden_prueba']; ?>" name="obs[<?php echo (int) $p['id_orden_prueba']; ?>]" <?php echo $editable ? '' : 'readonly'; ?> placeholder="Opcional. Aparece en la hoja impresa."><?php echo $e($p['observaciones']); ?></textarea>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>

            <?php if ($editable): ?>
                <div class="lr-bar">
                    <span class="al-muted small"><i class="bi bi-info-circle"></i> Los valores fuera de rango se marcan en rojo. "Finalizar" envía los resultados a la historia clínica.</span>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="al-btn al-btn--line" id="btnGuardar"><i class="bi bi-save"></i>Guardar avance</button>
                        <button type="button" class="al-btn al-btn--gold" id="btnFinalizar"><i class="bi bi-send-check"></i>Finalizar resultados</button>
                    </div>
                </div>
            <?php endif; ?>
        </form>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../../assets/js/processing-overlay.js"></script>
<script>
(function () {
    'use strict';
    var ID = <?php echo $id_orden; ?>;
    var brand = '#283251';
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    async function api(action, data) {
        try {
            var r = await fetch('api/resultados.php', { method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(Object.assign({ action: action }, data)) });
            return await r.json();
        } catch (e) { return { success: false, message: 'No hubo respuesta del servidor. Revise la conexión.' }; }
    }
    function err(m) { return Swal.fire({ icon: 'error', title: 'No se pudo', text: m, confirmButtonColor: brand }); }
    function reload(m) { if (window.showProcessing) window.showProcessing(m || 'Listo', 'Actualizando…'); setTimeout(function () { location.reload(); }, 350); }

    // Valores fuera de rango y botones rápidos
    function flag(inp) {
        var v = parseFloat(String(inp.value).replace(',', '.')), mn = inp.dataset.min, mx = inp.dataset.max;
        inp.classList.remove('is-alto', 'is-bajo');
        if (inp.value.trim() === '' || isNaN(v) || !/^\s*-?[\d.,]+\s*$/.test(inp.value)) return;
        if (mn !== undefined && v < parseFloat(mn)) inp.classList.add('is-bajo');
        else if (mx !== undefined && v > parseFloat(mx)) inp.classList.add('is-alto');
    }
    function marcarQuick(cell) {
        var inp = cell.querySelector('.lr-in');
        cell.querySelectorAll('.lr-quick button').forEach(function (b) { b.classList.toggle('is-on', b.dataset.v === inp.value); });
    }
    document.querySelectorAll('.lr-in').forEach(function (i) { flag(i); });
    document.querySelectorAll('.lr-val').forEach(marcarQuick);
    document.addEventListener('input', function (e) {
        if (e.target.classList.contains('lr-in')) { flag(e.target); var c = e.target.closest('.lr-val'); if (c) marcarQuick(c); dirty = true; }
        if (e.target.tagName === 'TEXTAREA') dirty = true;
    });
    document.addEventListener('click', function (e) {
        var b = e.target.closest('.lr-quick button');
        if (!b || b.closest('form').dataset.ro) return;
        var cell = b.closest('.lr-val'), inp = cell.querySelector('.lr-in');
        if (inp.readOnly) return;
        inp.value = b.dataset.v; flag(inp); marcarQuick(cell); dirty = true;
        var next = cell.closest('tr').nextElementSibling; if (next) { var n = next.querySelector('.lr-in'); if (n) n.focus(); }
    });
    // Enter pasa al siguiente campo
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || !e.target.classList.contains('lr-in')) return;
        e.preventDefault();
        var all = Array.prototype.slice.call(document.querySelectorAll('.lr-in:not([readonly])')), i = all.indexOf(e.target);
        if (all[i + 1]) { all[i + 1].focus(); all[i + 1].select(); }
    });

    var dirty = false;
    window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

    function datos() {
        var r = {}, obs = {};
        document.querySelectorAll('#resForm [name^="r["]').forEach(function (i) {
            var m = i.name.match(/^r\[(\d+)\]\[(.+)\]$/); if (!m) return;
            (r[m[1]] = r[m[1]] || {})[m[2]] = i.value;
        });
        document.querySelectorAll('#resForm [name^="obs["]').forEach(function (t) { var m = t.name.match(/^obs\[(\d+)\]$/); if (m) obs[m[1]] = t.value; });
        return { id_orden: ID, r: r, obs: obs };
    }
    var g = document.getElementById('btnGuardar');
    if (g) g.addEventListener('click', async function () {
        var j = await api('guardar', datos());
        if (!j.success) return err(j.message);
        dirty = false; reload(j.message);
    });
    var f = document.getElementById('btnFinalizar');
    if (f) f.addEventListener('click', async function () {
        var c = await Swal.fire({ icon: 'question', title: 'Finalizar resultados',
            html: 'Se enviarán a la <b>historia clínica</b> del paciente y quedarán listos para imprimir.<br><span class="text-muted small">Después solo se podrán cambiar con "Corregir".</span>',
            showCancelButton: true, confirmButtonText: 'Finalizar', cancelButtonText: 'Revisar otra vez', confirmButtonColor: brand });
        if (!c.isConfirmed) return;
        var j = await api('guardar', Object.assign(datos(), { finalizar: 1 }));
        if (!j.success) return err(j.message);
        dirty = false;
        var p = await Swal.fire({ icon: 'success', title: 'Resultados finalizados', text: j.message, showCancelButton: true,
            confirmButtonText: '<i class="bi bi-printer"></i> Imprimir', cancelButtonText: 'Volver a la cola', confirmButtonColor: brand });
        if (p.isConfirmed) { window.open('imprimir.php?id=' + ID, '_blank'); reload('Listo'); }
        else location.href = 'index.php';
    });
    var rc = document.getElementById('btnRecibir');
    if (rc) rc.addEventListener('click', async function () {
        var j = await api('recibir_muestra', { id_orden: ID });
        j.success ? reload(j.message) : err(j.message);
    });
    var ra = document.getElementById('btnReabrir');
    if (ra) ra.addEventListener('click', async function () {
        var res = await Swal.fire({ title: 'Corregir resultados', input: 'text', inputLabel: 'Motivo de la corrección (queda en la bitácora)',
            showCancelButton: true, confirmButtonText: 'Corregir', cancelButtonText: 'Cancelar', confirmButtonColor: brand,
            inputValidator: function (v) { if (!v || !v.trim()) return 'Escriba el motivo'; } });
        if (!res.isConfirmed) return;
        var j = await api('reabrir', { id_orden: ID, motivo: res.value });
        j.success ? reload(j.message) : err(j.message);
    });
})();
</script>
</body>
</html>
