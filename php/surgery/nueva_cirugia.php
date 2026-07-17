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

    $stmtCombos = $conn->prepare("SELECT id_combo, codigo, nombre, precio_total FROM cirugia_combos WHERE id_hospital = ? AND estado = 'Activo' ORDER BY nombre");
    $stmtCombos->execute([$id_hospital]);
    $combos = $stmtCombos->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $salas = $combos = [];
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
    <link rel="icon" type="image/png" href="../../assets/img/cmhs.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=optional" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css?v=4">
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css?v=4">
</head>
<body>
<div class="marble-effect"></div>
<div class="dashboard-container">
    <header class="dashboard-header">
        <div class="header-content">
            <div class="brand-container">
                <img src="../../assets/img/cmhs.png" alt="CMHS" class="brand-logo" width="40" height="40">
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
                <small class="text-muted">Si está intentando crear salas o combos, primero hágalo desde Quirófano → Salas / Combos.</small>
            </div>
        <?php endif; ?>

        <?php if (empty($salas)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-info-circle me-2"></i>
                No hay salas quirúrgicas registradas en este hospital.
                <a href="salas.php" class="btn btn-sm btn-warning ms-2"><i class="bi bi-plus"></i> Crear Sala</a>
            </div>
        <?php endif; ?>

        <?php if (empty($combos)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                No hay combos registrados. Puede continuar sin combo e ingresar el cargo manualmente.
                <a href="combos.php" class="btn btn-sm btn-info ms-2"><i class="bi bi-plus"></i> Crear Combo</a>
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
                            <label class="form-label">Combo de Operación <small class="text-muted">(opcional)</small></label>
                            <select class="form-select" name="id_combo" id="id_combo">
                                <option value="">— Sin combo (ingresar cargo manual) —</option>
                                <?php foreach ($combos as $c): ?>
                                    <option value="<?php echo $c['id_combo']; ?>" data-precio="<?php echo $c['precio_total']; ?>">
                                        <?php echo htmlspecialchars($c['nombre']); ?> — Q<?php echo number_format($c['precio_total'], 2); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <div id="combo-meds-preview" class="d-none"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cirujano <small class="text-muted">(escribir manualmente)</small></label>
                            <input type="text" name="cirujano_nombre" id="cirujano_nombre" class="form-control" placeholder="Dr. Juan Pérez" maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Anestesista <small class="text-muted">(escribir manualmente)</small></label>
                            <input type="text" name="anestesista_nombre" id="anestesista_nombre" class="form-control" placeholder="Dr. María López" maxlength="150">
                        </div>
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

    // Vista previa del combo: muestra los medicamentos del combo seleccionado
    const previewDiv = document.getElementById('combo-meds-preview');
    document.getElementById('id_combo').addEventListener('change', async (e) => {
        const idCombo = e.target.value;
        previewDiv.classList.add('d-none');
        previewDiv.innerHTML = '';
        if (!idCombo) return;
        try {
            const res = await fetch('api/get_combo_meds.php?id_combo=' + idCombo);
            const json = await res.json();
            if (!json.success) return;
            const meds = json.medicamentos || [];
            if (meds.length === 0) {
                previewDiv.innerHTML = `<div class="alert alert-info border-0 mb-3"><i class="bi bi-info-circle me-2"></i>Este combo no tiene medicamentos vinculados. Solo incluye cargos fijos.</div>`;
                previewDiv.classList.remove('d-none');
                return;
            }
            const stockAlerts = meds.filter(m => !m.stock_suficiente);
            let html = `<div class="alert alert-${stockAlerts.length > 0 ? 'warning' : 'success'} border-0 mb-3">
                <h6 class="alert-heading"><i class="bi bi-${stockAlerts.length > 0 ? 'exclamation-triangle' : 'check-circle'} me-2"></i>Medicamentos del Combo: ${json.combo.nombre}</h6>
                <p class="mb-2 small">Al iniciar la cirugía se descontarán automáticamente del stock de Quirófano:</p>
                <ul class="mb-0 small">`;
            meds.forEach(m => {
                const ok = m.stock_suficiente;
                html += `<li>
                    <strong>${escapeHtml(m.nom_medicamento)}</strong>
                    <span class="text-muted">(cantidad: ${parseInt(m.cantidad)})</span>
                    — Stock en Quirófano: <span class="badge bg-${ok ? 'success' : 'danger'}">${parseInt(m.stock_quirofano || 0)}</span>
                    ${!ok ? '<span class="text-danger ms-1">(faltan ' + m.stock_faltante + ')</span>' : ''}
                </li>`;
            });
            html += '</ul></div>';
            previewDiv.innerHTML = html;
            previewDiv.classList.remove('d-none');
        } catch (err) {
            console.error('combo preview error:', err);
        }
    });

    // Form submit
    document.getElementById('cirugiaForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        try {
            const res = await fetch('api/create_cirugia.php', { method: 'POST', body: fd, credentials: 'same-origin' });
            const json = await res.json();
            if (json.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Cirugía registrada',
                    text: json.message,
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

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}
function escapeHtmlAttr(str) {
    return (str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
</script>
</body>
</html>