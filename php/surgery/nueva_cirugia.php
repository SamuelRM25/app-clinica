<?php
// surgery/nueva_cirugia.php - Formulario para nueva cirugía
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit; }
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';
require_once '../../includes/module_guard.php';
require_once '../../includes/breadcrumbs.php';

check_module_access('surgery');
$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);
verify_session();
date_default_timezone_set('America/Guatemala');

$csrf_token = $_SESSION['csrf_token'] ?? '';
$error_msg = '';

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmtSalas = $conn->prepare("SELECT id_sala, codigo, nombre, tipo FROM salas_quirurgicas WHERE id_hospital = ? AND estado != 'Mantenimiento' ORDER BY codigo");
    $stmtSalas->execute([$id_hospital]);
    $salas = $stmtSalas->fetchAll(PDO::FETCH_ASSOC);

    // Plantillas de operaciones y procedimientos (insumos que se descuentan)
    $plantillas = [];
    if (area_tables_ready($conn)) {
        $stmtPl = $conn->prepare("SELECT p.id_plantilla, p.nombre, p.tipo, p.precio,
                                         (SELECT COUNT(*) FROM area_recetas r WHERE r.id_hospital = p.id_hospital AND r.ref_tipo = 'plantilla' AND r.ref_id = p.id_plantilla) AS n_items
                                  FROM qx_plantillas p WHERE p.id_hospital = ? AND p.activo = 1 ORDER BY p.tipo, p.nombre");
        $stmtPl->execute([$id_hospital]);
        $plantillas = $stmtPl->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $salas = [];
    $plantillas = [];
    $error_msg = 'Error cargando datos: ' . htmlspecialchars($e->getMessage());
    error_log('nueva_cirugia.php: ' . $e->getMessage());
}
$page_title = "Nueva Cirugía";
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?></title>
    <meta name="csrf-token" content="<?php echo csrf_token(); ?>">
    <link rel="icon" type="image/png" href="../../assets/img/favicon-cmrm.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=optional" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css?v=4">
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css?v=4">
    <link rel="stylesheet" href="../../assets/css/processing-overlay.css">
    <link rel="stylesheet" href="../../assets/css/almacen.css?v=1">
</head>
<body>
<div class="marble-effect"></div>
<div class="dashboard-container">
    <header class="dashboard-header">
        <div class="header-content">
            <div class="brand-container">
                <img src="../../assets/img/logo_cmrm.svg" alt="Clínica Médica Dr. Roberto Morales" class="brand-logo">
                <div><h2 class="mb-0" style="font-size: 1.25rem;">Nueva Cirugía</h2></div>
            </div>
            <div class="header-controls">
                <a href="index.php" class="back-btn"><i class="bi bi-arrow-left"></i><span>Quirófano</span></a>
            </div>
        </div>
    </header>
    <main class="main-content">
        <?php if ($error_msg): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error_msg; ?>
                <hr>
                <small class="text-muted">Si está intentando crear salas o plantillas, primero hágalo desde Quirófano → Salas / Plantillas.</small>
            </div>
        <?php endif; ?>

        <?php if (empty($salas)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-info-circle me-2"></i>
                No hay salas quirúrgicas registradas en este hospital.
                <a href="salas.php" class="btn btn-sm btn-warning ms-2"><i class="bi bi-plus"></i> Crear Sala</a>
            </div>
        <?php endif; ?>


        <form id="cirugiaForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="card shadow-sm border-0 rounded-3 mb-3">
                <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-person me-2"></i>Datos del Paciente</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Tipo de Paciente *</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="tipo_paciente" id="tipo_interno" value="Interno" checked>
                                <label class="btn btn-outline-primary" for="tipo_interno"><i class="bi bi-person-check"></i> Interno</label>
                                <input type="radio" class="btn-check" name="tipo_paciente" id="tipo_referido" value="Referido">
                                <label class="btn btn-outline-warning" for="tipo_referido"><i class="bi bi-person-plus"></i> Referido</label>
                            </div>
                        </div>
                        <div class="col-md-12" id="box-interno">
                            <label class="form-label">Buscar Paciente * <small class="text-muted">(escriba al menos 2 caracteres)</small></label>
                            <input type="text" id="search-paciente" class="form-control" placeholder="Nombre, apellido o DPI..." autocomplete="off" minlength="2">
                            <input type="hidden" name="id_paciente" id="id_paciente">
                            <div id="paciente-results" class="list-group mt-1" style="max-height: 200px; overflow-y: auto;"></div>
                            <div id="paciente-seleccionado" class="alert alert-success mt-2 d-none"></div>
                        </div>
                        <div class="col-md-12 d-none" id="box-referido">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">Nombre *</label>
                                    <input type="text" name="referido_nombre" id="referido_nombre" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Apellido</label>
                                    <input type="text" name="referido_apellido" id="referido_apellido" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 rounded-3 mb-3">
                <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-door-open me-2"></i>Detalles de la Cirugía</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Sala Quirúrgica *</label>
                            <select class="form-select" name="id_sala" required>
                                <option value="">-- Seleccione sala --</option>
                                <?php foreach ($salas as $s): ?>
                                    <option value="<?php echo $s['id_sala']; ?>"><?php echo htmlspecialchars($s['codigo'] . ' — ' . $s['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cirujano <small class="text-muted">(escribir manualmente)</small></label>
                            <input type="text" name="cirujano_nombre" id="cirujano_nombre" class="form-control" placeholder="Dr. Juan Pérez" maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Anestesista <small class="text-muted">(escribir manualmente)</small></label>
                            <input type="text" name="anestesista_nombre" id="anestesista_nombre" class="form-control" placeholder="Dr. María López" maxlength="150">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="id_plantilla">Operación o procedimiento <small class="text-muted">(plantilla)</small></label>
                            <select class="form-select" name="id_plantilla" id="id_plantilla">
                                <option value="">— Sin plantilla (nada se descuenta solo) —</option>
                                <?php $tipoAnt = null; foreach ($plantillas as $pl): if ($pl['tipo'] !== $tipoAnt): if ($tipoAnt !== null) echo '</optgroup>'; $tipoAnt = $pl['tipo']; ?>
                                    <optgroup label="<?php echo $pl['tipo'] === 'Procedimiento' ? 'Procedimientos' : 'Cirugías'; ?>">
                                <?php endif; ?>
                                    <option value="<?php echo (int) $pl['id_plantilla']; ?>" data-precio="<?php echo (float) $pl['precio']; ?>" data-nombre="<?php echo htmlspecialchars($pl['nombre']); ?>">
                                        <?php echo htmlspecialchars($pl['nombre']); ?><?php echo $pl['n_items'] ? ' · ' . (int) $pl['n_items'] . ' insumos' : ''; ?>
                                    </option>
                                <?php endforeach; if ($tipoAnt !== null) echo '</optgroup>'; ?>
                            </select>
                            <small class="text-muted"><?php echo $plantillas ? 'Al registrar, se descuentan sus insumos y medicamentos.' : 'Todavía no hay plantillas.'; ?> <a href="plantillas.php">Administrar plantillas</a></small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="cargo_total">Cargo de la operación (Q)</label>
                            <input type="number" class="form-control" name="cargo_total" id="cargo_total" min="0" step="0.01" placeholder="0.00">
                            <small class="text-muted">Se cobra al iniciar la cirugía.</small>
                        </div>
                        <div class="col-12" id="plantillaPreview" hidden></div>
                        <div class="col-md-12">
                            <label class="form-label">Procedimiento</label>
                            <textarea class="form-control" name="procedimiento" rows="2" placeholder="Descripción del procedimiento quirúrgico..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha y Hora Programada *</label>
                            <input type="datetime-local" class="form-control" name="fecha_programada" required value="<?php echo date('Y-m-d\TH:i'); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mb-4">
                <a href="index.php" class="btn btn-light">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Registrar Cirugía</button>
            </div>
        </form>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>window.ES_CREADOR = <?php echo isset($_SESSION['es_creador']) && $_SESSION['es_creador'] ? 'true' : 'false'; ?>;</script>
<script defer src="../../assets/js/security.js"></script>
<script src="../../assets/js/almacen.js?v=1"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Toggle paciente type
    document.querySelectorAll('input[name="tipo_paciente"]').forEach(r => {
        r.addEventListener('change', e => {
            if (e.target.value === 'Interno') {
                document.getElementById('box-interno').classList.remove('d-none');
                document.getElementById('box-referido').classList.add('d-none');
            } else {
                document.getElementById('box-interno').classList.add('d-none');
                document.getElementById('box-referido').classList.remove('d-none');
            }
        });
    });

    // Search paciente — ruta corregida
    const searchInput = document.getElementById('search-paciente');
    const resultsBox = document.getElementById('paciente-results');
    let timer;
    searchInput.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            const q = searchInput.value.trim();
            if (q.length < 2) { resultsBox.innerHTML = ''; return; }
            try {
                const res = await fetch('../dashboard/api/search_patients.php?q=' + encodeURIComponent(q), {
                    credentials: 'same-origin'
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const json = await res.json();
                if (json.status === 'success' && json.patients && json.patients.length) {
                    resultsBox.innerHTML = json.patients.map(p =>
                        `<a href="javascript:void(0)" class="list-group-item list-group-item-action" data-id="${p.id_paciente}" data-name="${escapeHtmlAttr(p.nombre_completo)}">
                            <strong>${escapeHtml(p.nombre_completo)}</strong> · DPI: ${p.dpi || '—'} · ${p.edad || '?'} años
                        </a>`
                    ).join('');
                    resultsBox.querySelectorAll('a').forEach(a => {
                        a.addEventListener('click', () => {
                            document.getElementById('id_paciente').value = a.dataset.id;
                            const div = document.getElementById('paciente-seleccionado');
                            div.textContent = '✓ Seleccionado: ' + a.dataset.name;
                            div.classList.remove('d-none');
                            resultsBox.innerHTML = '';
                            searchInput.value = a.dataset.name;
                        });
                    });
                } else {
                    resultsBox.innerHTML = '<div class="list-group-item text-muted">Sin resultados</div>';
                }
            } catch (e) {
                resultsBox.innerHTML = '<div class="list-group-item text-danger">Error en búsqueda: ' + e.message + '</div>';
                console.error('Patient search error:', e);
            }
        }, 300);
    });

    // Form submit
    document.getElementById('cirugiaForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        try {
            const res = await fetch('api/create_cirugia.php', { method: 'POST', body: fd, credentials: 'same-origin' });
            const json = await res.json();
            if (json.success) {
                const avisos = json.avisos_almacen || [];
                Swal.fire({
                    icon: avisos.length ? 'warning' : 'success',
                    title: 'Cirugía registrada',
                    html: escapeHtml(json.message) + (avisos.length
                        ? '<div style="text-align:left;margin-top:1rem;font-size:.9rem"><b>Revise el inventario:</b><ul style="margin:.4rem 0 0;padding-left:1.2rem">' +
                          avisos.map(a => '<li>' + escapeHtml(a) + '</li>').join('') + '</ul></div>' : ''),
                    confirmButtonText: 'Ir al detalle'
                }).then(() => { window.location.href = 'detalle_cirugia.php?id=' + json.id_cirugia; });
            } else {
                Swal.fire('Error', json.message, 'error');
            }
        } catch (err) {
            Swal.fire('Error', 'Fallo de red', 'error');
        }
    });
});

// Vista previa de lo que descuenta la plantilla
(function () {
    const sel = document.getElementById('id_plantilla');
    const box = document.getElementById('plantillaPreview');
    const cargo = document.getElementById('cargo_total');
    const proc = document.querySelector('textarea[name="procedimiento"]');
    if (!sel || !window.AL) return;
    sel.addEventListener('change', async () => {
        const opt = sel.options[sel.selectedIndex];
        if (!sel.value) { box.hidden = true; return; }
        if (opt.dataset.precio && parseFloat(opt.dataset.precio) > 0) cargo.value = parseFloat(opt.dataset.precio).toFixed(2);
        if (proc && !proc.value.trim()) proc.value = opt.dataset.nombre;
        box.hidden = false;
        box.innerHTML = '<div class="text-muted small">Cargando insumos…</div>';
        const r = await AL.api('plantilla', { id: sel.value }, 'GET');
        if (!r.success) { box.innerHTML = '<div class="alert alert-warning mb-0">' + escapeHtml(r.message) + '</div>'; return; }
        if (!r.items.length) { box.innerHTML = '<div class="alert alert-light border mb-0">Esta plantilla no tiene insumos. No se descontará nada.</div>'; return; }
        box.innerHTML = '<div class="al-receta__rows">' + r.items.map(it => {
            const falta = parseFloat(it.stock) < parseFloat(it.cantidad);
            return '<div class="al-receta__row"><div class="al-receta__name">' + escapeHtml(it.nombre) +
                (it.fuente === 'farmacia' ? ' <span class="al-tag al-tag--farm">Stock quirófano</span>' : ' <span class="al-tag">Almacén QX</span>') +
                '<small>Hay ' + AL.qty(it.stock) + ' ' + escapeHtml(it.unidad) + '</small></div>' +
                '<b class="' + (falta ? 'text-danger' : '') + '">' + AL.qty(it.cantidad) + ' ' + escapeHtml(it.unidad) + (falta ? ' <i class="bi bi-exclamation-triangle" title="No alcanza"></i>' : '') + '</b></div>';
        }).join('') + '</div>' +
        (r.items.some(it => parseFloat(it.stock) < parseFloat(it.cantidad))
            ? '<div class="small text-danger mt-1"><i class="bi bi-exclamation-triangle"></i> Algunos no alcanzan. Los del almacén quedarán en negativo; los medicamentos que falten no se descuentan.</div>' : '');
    });
})();

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}
function escapeHtmlAttr(str) {
    return (str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
</script>
    <script src="../../assets/js/processing-overlay.js"></script>
</body>
</html>