<?php
/**
 * surgery/plantillas.php — Plantillas de operaciones y procedimientos.
 * Cada plantilla define los insumos del almacén de Quirófano y los medicamentos
 * del stock quirófano que se descuentan solos al programar la cirugía.
 */
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../auth/login.php'); exit; }
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';
require_once '../../includes/module_guard.php';

check_module_access('surgery');
verify_session();
$id_hospital = (int) ($_SESSION['id_hospital'] ?? 0);
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$conn = (new Database())->getConnection();
$ready = area_tables_ready($conn);
$plantillas = [];
if ($ready) {
    $st = $conn->prepare("
        SELECT p.*,
               (SELECT COUNT(*) FROM area_recetas r WHERE r.id_hospital = p.id_hospital AND r.ref_tipo = 'plantilla' AND r.ref_id = p.id_plantilla AND r.fuente = 'area') AS n_insumos,
               (SELECT COUNT(*) FROM area_recetas r WHERE r.id_hospital = p.id_hospital AND r.ref_tipo = 'plantilla' AND r.ref_id = p.id_plantilla AND r.fuente = 'farmacia') AS n_meds,
               (SELECT COUNT(*) FROM cirugias c WHERE c.id_hospital = p.id_hospital AND c.id_plantilla = p.id_plantilla) AS n_usos
        FROM qx_plantillas p WHERE p.id_hospital = ? ORDER BY p.activo DESC, p.tipo, p.nombre");
    try {
        $st->execute([$id_hospital]);
    } catch (Exception $ex) {
        // Sin la columna cirugias.id_plantilla todavía
        $st = $conn->prepare("SELECT p.*, 0 AS n_insumos, 0 AS n_meds, 0 AS n_usos FROM qx_plantillas p WHERE p.id_hospital = ? ORDER BY p.activo DESC, p.tipo, p.nombre");
        $st->execute([$id_hospital]);
    }
    $plantillas = $st->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $e(csrf_token()); ?>">
    <title>Plantillas de Quirófano · CMRM</title>
    <link rel="icon" type="image/png" href="../../assets/img/favicon-cmrm.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=optional" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
    <?php include '../../includes/theme_head.php'; ?>
    <link rel="stylesheet" href="../../assets/css/processing-overlay.css">
    <link rel="stylesheet" href="../../assets/css/almacen.css?v=1">
    <style>
        .pl-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem; }
        .pl-card { display: flex; flex-direction: column; gap: .6rem; padding: 1.15rem 1.2rem; border: 1px solid var(--color-border); border-radius: 16px; background: var(--color-card); }
        .pl-card.is-off { opacity: .6; }
        .pl-card h3 { margin: 0; font-size: 1.05rem; font-weight: 700; color: var(--color-text); }
        .pl-card p { margin: 0; font-size: .87rem; color: var(--color-text-secondary); }
        .pl-meta { display: flex; flex-wrap: wrap; gap: .4rem .9rem; font-size: .84rem; color: var(--color-text-secondary); }
        .pl-meta span { display: inline-flex; align-items: center; gap: .3rem; }
        .pl-price { font-size: 1.25rem; font-weight: 700; color: var(--color-text); font-variant-numeric: tabular-nums; }
        .pl-foot { display: flex; gap: .4rem; margin-top: auto; padding-top: .6rem; border-top: 1px solid var(--color-border); }
    </style>
</head>
<body>
<div class="marble-effect"></div>
<div class="dashboard-container">
    <header class="dashboard-header">
        <div class="header-content">
            <div class="brand-container"><img src="../../assets/img/logo_cmrm.svg" alt="Centro Médico Dr. Roberto Morales" class="brand-logo"></div>
            <div class="header-controls">
                <div class="theme-toggle">
                    <button id="themeSwitch" class="theme-btn" aria-label="Cambiar tema claro/oscuro"><i class="bi bi-sun theme-icon sun-icon"></i><i class="bi bi-moon theme-icon moon-icon"></i></button>
                </div>
                <a href="index.php" class="back-btn"><i class="bi bi-arrow-left"></i><span>Quirófano</span></a>
            </div>
        </div>
    </header>

    <main class="main-content al-page">
        <section class="al-head">
            <div>
                <span class="al-eyebrow"><i class="bi bi-heart-pulse"></i> Quirófano</span>
                <h1>Plantillas de operaciones</h1>
                <p>Lo que se usa en cada cirugía o procedimiento. Se descuenta solo al programarla.</p>
            </div>
            <?php if ($ready): ?>
                <div class="al-actions">
                    <a class="al-btn al-btn--line" href="../almacen/index.php?area=quirofano"><i class="bi bi-box-seam"></i>Almacén de Quirófano</a>
                    <button class="al-btn al-btn--gold" type="button" id="btnNueva"><i class="bi bi-plus-lg"></i>Nueva plantilla</button>
                </div>
            <?php endif; ?>
        </section>

        <?php if (!$ready): ?>
            <section class="al-panel al-install"><i class="bi bi-database-add"></i><h2>Primero instale el almacén</h2>
                <p>Las plantillas usan el almacén de Quirófano. Un administrador puede instalarlo desde <a href="../almacen/index.php?area=quirofano">Almacén</a>.</p></section>
        <?php elseif (!$plantillas): ?>
            <section class="al-panel al-empty">
                <i class="bi bi-clipboard2-pulse"></i>
                <b>Todavía no hay plantillas</b>
                <p>Ej.: "Cesárea", "Apendicectomía" o "Sutura simple", con los guantes, gasas, suturas y medicamentos que se usan.</p>
                <button class="al-btn al-btn--primary" type="button" onclick="document.getElementById('btnNueva').click()"><i class="bi bi-plus-lg"></i>Crear la primera</button>
            </section>
        <?php else: ?>
            <div class="pl-grid">
                <?php foreach ($plantillas as $p): ?>
                    <article class="pl-card <?php echo $p['activo'] ? '' : 'is-off'; ?>">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <h3><?php echo $e($p['nombre']); ?></h3>
                            <span class="al-tag"><?php echo $e($p['tipo']); ?></span>
                        </div>
                        <?php if ($p['descripcion']): ?><p><?php echo $e($p['descripcion']); ?></p><?php endif; ?>
                        <div class="pl-price"><?php echo $p['precio'] > 0 ? 'Q' . number_format($p['precio'], 2) : '<span class="al-muted" style="font-size:.9rem;font-weight:500">Sin cargo fijo</span>'; ?></div>
                        <div class="pl-meta">
                            <span><i class="bi bi-box-seam"></i><?php echo (int) $p['n_insumos']; ?> insumos</span>
                            <span><i class="bi bi-capsule"></i><?php echo (int) $p['n_meds']; ?> medicamentos</span>
                            <span><i class="bi bi-calendar-check"></i>Usada <?php echo (int) $p['n_usos']; ?> <?php echo (int) $p['n_usos'] === 1 ? 'vez' : 'veces'; ?></span>
                            <?php if (!$p['activo']): ?><span class="al-bad">Inactiva</span><?php endif; ?>
                        </div>
                        <div class="pl-foot">
                            <button class="al-btn al-btn--primary al-btn--sm" type="button" data-edit="<?php echo (int) $p['id_plantilla']; ?>"><i class="bi bi-pencil"></i>Editar</button>
                            <button class="al-btn al-btn--line al-btn--sm" type="button" data-toggle="<?php echo (int) $p['id_plantilla']; ?>"><?php echo $p['activo'] ? 'Desactivar' : 'Activar'; ?></button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php if ($ready): ?>
<div class="modal fade" id="plModal" tabindex="-1" aria-labelledby="plTitle" aria-hidden="true">
    <div class="modal-dialog al-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content al-modal">
        <div class="modal-header"><h5 class="modal-title" id="plTitle">Nueva plantilla</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body">
            <input type="hidden" id="plId">
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label" for="plNombre">Nombre *</label><input class="form-control" id="plNombre" maxlength="150" placeholder="Ej.: Cesárea"></div>
                <div class="col-md-3"><label class="form-label" for="plTipo">Tipo</label><select class="form-select" id="plTipo"><option>Cirugía</option><option>Procedimiento</option></select></div>
                <div class="col-md-3"><label class="form-label" for="plPrecio">Cargo (Q)</label><input type="number" class="form-control" id="plPrecio" min="0" step="0.01" placeholder="0.00"></div>
                <div class="col-12"><label class="form-label" for="plDesc">Descripción</label><input class="form-control" id="plDesc" maxlength="500" placeholder="Opcional"></div>
            </div>
            <label class="form-label">Insumos y medicamentos que se usan</label>
            <p class="al-muted small mb-2">Insumos salen del <b>almacén de Quirófano</b>. Medicamentos salen del <b>stock quirófano</b> de farmacia y se cobran al paciente como hoy.</p>
            <div id="plEditor"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="al-btn al-btn--line" data-bs-dismiss="modal">Cancelar</button>
            <button type="button" class="al-btn al-btn--primary" id="btnGuardar"><i class="bi bi-check2"></i>Guardar plantilla</button>
        </div>
    </div></div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../../assets/js/processing-overlay.js"></script>
<script src="../../assets/js/almacen.js?v=1"></script>
<script>
(function () {
    var btnNueva = document.getElementById('btnNueva');
    if (!btnNueva) return;
    var editor = null;
    var modal = function () { return bootstrap.Modal.getOrCreateInstance(document.getElementById('plModal')); };
    function err(m) { Swal.fire({ icon: 'error', title: 'No se pudo', text: m, confirmButtonColor: '#283251' }); }
    function done(m) { if (window.showProcessing) window.showProcessing(m, 'Actualizando…'); setTimeout(function () { location.reload(); }, 300); }

    function abrir(p, items) {
        document.getElementById('plTitle').textContent = p ? 'Editar ' + p.nombre : 'Nueva plantilla';
        document.getElementById('plId').value = p ? p.id_plantilla : '';
        document.getElementById('plNombre').value = p ? p.nombre : '';
        document.getElementById('plTipo').value = p ? p.tipo : 'Cirugía';
        document.getElementById('plPrecio').value = p && parseFloat(p.precio) > 0 ? p.precio : '';
        document.getElementById('plDesc').value = p ? (p.descripcion || '') : '';
        var box = document.getElementById('plEditor'); box.innerHTML = '';
        editor = AL.Receta(box, { area: 'quirofano', farmacia: true, items: items || [] });
        modal().show();
        setTimeout(function () { document.getElementById('plNombre').focus(); }, 350);
    }
    btnNueva.addEventListener('click', function () { abrir(null, []); });
    document.addEventListener('click', async function (e) {
        var b = e.target.closest('[data-edit]');
        if (b) {
            var r = await AL.api('plantilla', { id: b.getAttribute('data-edit') }, 'GET');
            if (!r.success) return err(r.message);
            abrir(r.plantilla, r.items);
            return;
        }
        var t = e.target.closest('[data-toggle]');
        if (t) {
            var r2 = await AL.api('toggle_plantilla', { id: t.getAttribute('data-toggle') });
            r2.success ? done(r2.message) : err(r2.message);
        }
    });
    document.getElementById('btnGuardar').addEventListener('click', async function () {
        var nombre = document.getElementById('plNombre').value.trim();
        if (!nombre) { document.getElementById('plNombre').focus(); return err('Escriba el nombre de la operación o procedimiento.'); }
        var r = await AL.api('save_plantilla', {
            id_plantilla: document.getElementById('plId').value, nombre: nombre, tipo: document.getElementById('plTipo').value,
            precio: document.getElementById('plPrecio').value, descripcion: document.getElementById('plDesc').value, items: editor.items()
        });
        if (!r.success) return err(r.message);
        modal().hide(); done(r.message);
    });
})();
</script>
</body>
</html>
