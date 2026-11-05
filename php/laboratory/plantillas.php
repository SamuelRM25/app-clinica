<?php
/**
 * laboratory/plantillas.php — Plantillas de resultados de laboratorio.
 */
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../auth/login.php'); exit; }
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';
require_once '../../includes/module_guard.php';
require_once '../../includes/lab_seed.php';

check_module_access('laboratory');
verify_session();
$rol = $_SESSION['tipoUsuario'] ?? '';
if (!in_array($rol, ['admin', 'lab'], true)) { header('Location: index.php'); exit; }
$id_hospital = hospital_id();
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$conn = (new Database())->getConnection();
$ready = lab_tables_ready($conn);
$grupos = [];
$plan = null;
if ($ready) {
    $st = $conn->prepare("SELECT lp.id_plantilla, lp.nombre, lp.hoja, lp.diseno, lp.activo, lp.updated_at,
                                 GROUP_CONCAT(cp.nombre_prueba ORDER BY cp.nombre_prueba SEPARATOR ', ') AS pruebas
                          FROM lab_plantillas lp
                          LEFT JOIN catalogo_pruebas cp ON cp.id_plantilla = lp.id_plantilla AND cp.id_hospital = lp.id_hospital
                          WHERE lp.id_hospital = ? GROUP BY lp.id_plantilla ORDER BY lp.activo DESC, lp.hoja, lp.nombre");
    $st->execute([$id_hospital]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $d = lab_tpl_normalize($p['diseno']);
        $n = 0;
        foreach ($d['secciones'] as $s) if (in_array($s['tipo'], ['tabla', 'matriz'], true)) $n += count($s['filas']);
        $p['n_examenes'] = $n;
        $grupos[$p['hoja'] ?: 'Sin hoja'][] = $p;
    }
    $plan = lab_seed_plan($conn, $id_hospital, false, true);
}
$pend_tpl = $plan ? count(array_filter($plan['plantillas'], fn($p) => !$p[1])) : 0;
$pend_pr = $plan ? count(array_filter($plan['pruebas'], fn($p) => $p[2] !== 'igual')) : 0;
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $e(csrf_token()); ?>">
    <title>Plantillas de resultados · CMRM</title>
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
                <span class="al-eyebrow"><i class="bi bi-droplet-half"></i> Laboratorio</span>
                <h1>Plantillas de resultados</h1>
                <p>Cómo se registra e imprime el resultado de cada prueba.</p>
            </div>
            <?php if ($ready): ?>
                <div class="al-actions">
                    <a class="al-btn al-btn--line" href="catalogo_pruebas.php"><i class="bi bi-list-check"></i>Catálogo de pruebas</a>
                    <a class="al-btn al-btn--gold" href="plantilla_editor.php"><i class="bi bi-plus-lg"></i>Nueva plantilla</a>
                </div>
            <?php endif; ?>
        </section>

        <?php if (!$ready): ?>
            <section class="al-panel al-install">
                <i class="bi bi-file-earmark-medical"></i>
                <h2>Activar plantillas de resultados</h2>
                <p>Se crean las tablas para guardar plantillas y resultados por paciente. No se modifica ninguna orden ni resultado existente.</p>
                <button class="al-btn al-btn--primary" type="button" id="btnInstall"><i class="bi bi-magic"></i>Activar</button>
            </section>
        <?php else: ?>

            <?php if ($pend_tpl || $pend_pr): ?>
                <div class="al-panel mb-3 d-flex flex-wrap gap-3 align-items-center justify-content-between" style="border-color:rgba(var(--color-accent-rgb),.5);background:rgba(var(--color-accent-rgb),.07)">
                    <div>
                        <b>Plantillas y precios del Laboratorio Clínico Morales</b>
                        <div class="al-muted small">Tomados de las hojas de resultados y del listado de precios: <?php echo $pend_tpl; ?> plantillas por agregar y <?php echo $pend_pr; ?> pruebas con cambios.</div>
                    </div>
                    <button class="al-btn al-btn--gold" type="button" data-bs-toggle="modal" data-bs-target="#seedModal"><i class="bi bi-cloud-download"></i>Revisar y cargar</button>
                </div>
            <?php endif; ?>

            <?php if (!$grupos): ?>
                <div class="al-panel al-empty"><i class="bi bi-file-earmark-text"></i><b>Todavía no hay plantillas</b>
                    <p>Cargue las del laboratorio con el botón de arriba, o cree una nueva.</p></div>
            <?php endif; ?>

            <?php foreach ($grupos as $hoja => $lista): ?>
                <section class="tp-group">
                    <h2>Hoja: <?php echo $e($hoja); ?></h2>
                    <div class="tp-grid">
                        <?php foreach ($lista as $p): ?>
                            <article class="tp-card <?php echo $p['activo'] ? '' : 'is-off'; ?>">
                                <h3><?php echo $e($p['nombre']); ?></h3>
                                <p><?php echo (int) $p['n_examenes']; ?> <?php echo $p['n_examenes'] == 1 ? 'examen' : 'exámenes'; ?><?php echo $p['activo'] ? '' : ' · Inactiva'; ?></p>
                                <p><?php echo $p['pruebas'] ? '<i class="bi bi-link-45deg"></i> ' . $e($p['pruebas']) : '<span style="color:#a8783a">No está asignada a ninguna prueba</span>'; ?></p>
                                <div class="tp-foot">
                                    <a class="al-btn al-btn--primary al-btn--sm" href="plantilla_editor.php?id=<?php echo (int) $p['id_plantilla']; ?>"><i class="bi bi-pencil"></i>Editar</a>
                                    <button class="al-icon" type="button" data-dup="<?php echo (int) $p['id_plantilla']; ?>" title="Duplicar" aria-label="Duplicar <?php echo $e($p['nombre']); ?>"><i class="bi bi-files"></i></button>
                                    <button class="al-icon" type="button" data-toggle="<?php echo (int) $p['id_plantilla']; ?>" title="<?php echo $p['activo'] ? 'Desactivar' : 'Activar'; ?>" aria-label="<?php echo $p['activo'] ? 'Desactivar' : 'Activar'; ?>"><i class="bi <?php echo $p['activo'] ? 'bi-eye-slash' : 'bi-eye'; ?>"></i></button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</div>

<?php if ($plan): ?>
<div class="modal fade" id="seedModal" tabindex="-1" aria-labelledby="seedTitle" aria-hidden="true">
    <div class="modal-dialog al-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content al-modal">
        <div class="modal-header"><h5 class="modal-title" id="seedTitle">Cargar plantillas y precios</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body">
            <p class="al-muted">Solo se agrega lo que falta. Las plantillas que ya existen no se tocan.</p>
            <div class="al-table-wrap">
                <table class="al-table al-table--dense">
                    <thead><tr><th>Código</th><th>Prueba</th><th>Qué pasa</th><th class="num">Precio actual</th><th class="num">Precio listado</th></tr></thead>
                    <tbody>
                    <?php foreach ($plan['pruebas'] as [$cod, $nom, $acc, $pa, $pn]): ?>
                        <tr>
                            <td><?php echo $e($cod); ?></td>
                            <td><?php echo $e($nom); ?></td>
                            <td><?php echo ['nueva' => '<span class="al-pill al-pill--ok">Nueva</span>', 'precio' => '<span class="al-pill al-pill--warn">Cambia precio</span>', 'plantilla' => '<span class="al-pill al-pill--ok">Se asigna plantilla</span>', 'igual' => '<span class="al-muted">Sin cambios</span>'][$acc]; ?></td>
                            <td class="num"><?php echo $pa === null ? '—' : 'Q' . number_format($pa, 2); ?></td>
                            <td class="num"><?php echo $pn > 0 ? 'Q' . number_format($pn, 2) : '<span class="al-warn">Sin precio</span>'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <label class="d-flex gap-2 align-items-center mt-3"><input type="checkbox" id="seedPrecios" checked> Actualizar los precios de las pruebas que ya existen</label>
            <p class="al-muted small mt-2 mb-0">Orina y Heces no vienen en el listado: se crean con precio Q0.00 para que lo ponga en el catálogo.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="al-btn al-btn--line" data-bs-dismiss="modal">Cancelar</button>
            <button type="button" class="al-btn al-btn--gold" id="btnSeed"><i class="bi bi-cloud-download"></i>Cargar</button>
        </div>
    </div></div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../../assets/js/processing-overlay.js"></script>
<script>
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    async function api(action, data) {
        try {
            var r = await fetch('api/resultados.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(Object.assign({ action: action }, data || {})) });
            return await r.json();
        } catch (e) { return { success: false, message: 'No hubo respuesta del servidor.' }; }
    }
    function done(j) {
        if (!j.success) return Swal.fire({ icon: 'error', title: 'No se pudo', text: j.message, confirmButtonColor: '#283251' });
        if (window.showProcessing) window.showProcessing(j.message, 'Actualizando…');
        setTimeout(function () { location.reload(); }, 350);
    }
    var bi = document.getElementById('btnInstall'); if (bi) bi.addEventListener('click', async function () { done(await api('install')); });
    var bs = document.getElementById('btnSeed'); if (bs) bs.addEventListener('click', async function () {
        bootstrap.Modal.getInstance(document.getElementById('seedModal')).hide();
        done(await api('seed', { actualizar_precios: document.getElementById('seedPrecios').checked ? 1 : 0 }));
    });
    document.addEventListener('click', async function (e) {
        var d = e.target.closest('[data-dup]'); if (d) return done(await api('plantilla_duplicar', { id: d.getAttribute('data-dup') }));
        var t = e.target.closest('[data-toggle]'); if (t) return done(await api('plantilla_toggle', { id: t.getAttribute('data-toggle') }));
    });
})();
</script>
</body>
</html>
