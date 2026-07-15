<?php
// surgery/detalle_cirugia.php - Vista detalle de una cirugía
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

$user_type = $_SESSION['tipoUsuario'];
$csrf_token = $_SESSION['csrf_token'] ?? '';
$id_cirugia = (int)($_GET['id'] ?? 0);

if (!$id_cirugia) { header('Location: index.php'); exit; }

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("
        SELECT c.*,
                COALESCE(CONCAT(p.nombre, ' ', p.apellido), CONCAT(c.referido_nombre, ' ', c.referido_apellido)) AS paciente,
                p.dpi, p.fecha_nacimiento, p.genero,
                s.nombre AS sala, s.codigo AS sala_codigo,
                cc.nombre AS combo_nombre,
                c.cirujano_nombre, c.anestesista_nombre
         FROM cirugias c
         LEFT JOIN pacientes p ON c.id_paciente = p.id_paciente
         LEFT JOIN salas_quirurgicas s ON c.id_sala = s.id_sala
         LEFT JOIN cirugia_combos cc ON c.id_combo = cc.id_combo
         WHERE c.id_cirugia = ? AND c.id_hospital = ?
    ");
    $stmt->execute([$id_cirugia, $id_hospital]);
    $cirugia = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cirugia) { header('Location: index.php'); exit; }

    // Consumos
    $stmtCons = $conn->prepare("
        SELECT cc.*, inv.nom_medicamento, inv.presentacion_med
        FROM cirugia_consumos cc
        JOIN inventario inv ON cc.id_inventario = inv.id_inventario
        WHERE cc.id_cirugia = ?
        ORDER BY cc.id DESC
    ");
    $stmtCons->execute([$id_cirugia]);
    $consumos = $stmtCons->fetchAll(PDO::FETCH_ASSOC);

    // Equipo
    $stmtEq = $conn->prepare("SELECT ce.*, u.nombre, u.apellido, u.especialidad FROM cirugia_equipo ce JOIN usuarios u ON ce.id_usuario = u.idUsuario WHERE ce.id_cirugia = ?");
    $stmtEq->execute([$id_cirugia]);
    $equipo = $stmtEq->fetchAll(PDO::FETCH_ASSOC);

    // Si tiene encamamiento
    $encamamiento = null;
    if ($cirugia['id_encamamiento']) {
        $stmtEnc = $conn->prepare("
            SELECT e.*, h.numero_habitacion, c.numero_cama, ch.id_cuenta
            FROM encamamientos e
            JOIN camas c ON e.id_cama = c.id_cama
            JOIN habitaciones h ON c.id_habitacion = h.id_habitacion
            LEFT JOIN cuenta_hospitalaria ch ON e.id_encamamiento = ch.id_encamamiento AND ch.id_hospital = ?
            WHERE e.id_encamamiento = ?
        ");
        $stmtEnc->execute([$id_hospital, $cirugia['id_encamamiento']]);
        $encamamiento = $stmtEnc->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log('detalle_cirugia.php: ' . $e->getMessage());
    die('Error al cargar la cirugía.');
}

$page_title = "Cirugía #" . $cirugia['numero_cirugia'];
$edad = '';
if ($cirugia['fecha_nacimiento'] && $cirugia['fecha_nacimiento'] !== '1900-01-01') {
    $edad = (new DateTime($cirugia['fecha_nacimiento']))->diff(new DateTime())->y . ' años';
}
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
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
</head>
<body>
<div class="marble-effect"></div>
<div class="dashboard-container">
    <header class="dashboard-header">
        <div class="header-content">
            <div class="brand-container">
                <img src="../../assets/img/cmhs.png" alt="CMHS" class="brand-logo" width="40" height="40">
                <div>
                    <h2 class="mb-0" style="font-size: 1.25rem;"><?php echo $page_title; ?></h2>
                    <small class="text-muted"><?php echo htmlspecialchars($cirugia['paciente']); ?></small>
                </div>
            </div>
            <div class="header-controls">
                <a href="index.php" class="back-btn"><i class="bi bi-arrow-left"></i><span>Quirófano</span></a>
            </div>
        </div>
    </header>

    <main class="main-content">
        <!-- Header del paciente + acciones -->
        <div class="card shadow-sm border-0 rounded-3 mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">
                            <?php echo htmlspecialchars($cirugia['paciente']); ?>
                            <?php if ($cirugia['tipo_paciente'] === 'Referido'): ?>
                                <span class="badge bg-warning text-dark">Referido</span>
                            <?php endif; ?>
                        </h4>
                        <p class="mb-1 text-muted">
                            <?php echo $edad ? $edad . ' · ' : ''; ?>
                            <?php echo htmlspecialchars($cirugia['genero'] ?? ''); ?>
                            · DPI: <?php echo htmlspecialchars($cirugia['dpi'] ?? '—'); ?>
                        </p>
                        <p class="mb-0">
                            <span class="badge 
                                <?php echo ['Programada' => 'bg-secondary', 'En_Curso' => 'bg-danger', 'Finalizada' => 'bg-success', 'Cancelada' => 'bg-dark'][$cirugia['estado']]; ?>">
                                <?php echo $cirugia['estado']; ?>
                            </span>
                            · Sala: <?php echo htmlspecialchars($cirugia['sala'] ?? '—'); ?>
                            · Combo: <?php echo htmlspecialchars($cirugia['combo_nombre'] ?? '—'); ?>
                        </p>
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-start">
                        <?php if ($cirugia['estado'] === 'Programada'): ?>
                            <button class="btn btn-danger" onclick="cambiarEstado('En_Curso')">
                                <i class="bi bi-play-fill"></i> Iniciar Cirugía
                            </button>
                            <button class="btn btn-outline-dark" onclick="cambiarEstado('Cancelada')">
                                <i class="bi bi-x-circle"></i> Cancelar
                            </button>
                        <?php elseif ($cirugia['estado'] === 'En_Curso'): ?>
                            <button class="btn btn-primary" onclick="openConsumoModal()">
                                <i class="bi bi-capsule"></i> Agregar Medicamento
                            </button>
                            <button class="btn btn-info" onclick="previewAsignacion()">
                                <i class="bi bi-eye"></i> Ver Asignación
                            </button>
                            <button class="btn btn-success" onclick="finalizarCirugia()">
                                <i class="bi bi-check-circle"></i> Finalizar Cirugía
                            </button>
                        <?php endif; ?>
                        <?php if ($encamamiento): ?>
                            <a href="../hospitalization/detalle_encamamiento.php?id=<?php echo (int)$encamamiento['id_encamamiento']; ?>" class="btn btn-outline-info">
                                <i class="bi bi-hospital"></i> Ver Encamamiento
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel Vista Previa de Asignación (oculto, se muestra con el botón) -->
        <div class="card shadow-sm border-0 rounded-3 mb-3 d-none" id="preview-asignacion-card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-eye me-2"></i>Vista Previa — Asignación post-operatoria</h5>
            </div>
            <div class="card-body" id="preview-asignacion-body">
                <div class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm me-2"></div>Cargando...</div>
            </div>
        </div>

        <div class="row g-3">
            <!-- Detalles -->
            <div class="col-md-6">
                <div class="card shadow-sm border-0 rounded-3 h-100">
                    <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-clipboard-data me-2"></i>Detalles</h5></div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5">Fecha Programada:</dt><dd class="col-sm-7"><?php echo $cirugia['fecha_programada'] ? date('d/m/Y H:i', strtotime($cirugia['fecha_programada'])) : '—'; ?></dd>
                            <dt class="col-sm-5">Inicio:</dt><dd class="col-sm-7"><?php echo $cirugia['fecha_inicio'] ? date('d/m/Y H:i', strtotime($cirugia['fecha_inicio'])) : '—'; ?></dd>
                            <dt class="col-sm-5">Fin:</dt><dd class="col-sm-7"><?php echo $cirugia['fecha_fin'] ? date('d/m/Y H:i', strtotime($cirugia['fecha_fin'])) : '—'; ?></dd>
                            <dt class="col-sm-5">Cirujano:</dt><dd class="col-sm-7"><?php echo htmlspecialchars($cirugia['cirujano_nombre'] ?? '—'); ?></dd>
                            <dt class="col-sm-5">Anestesista:</dt><dd class="col-sm-7"><?php echo htmlspecialchars($cirugia['anestesista_nombre'] ?? '—'); ?></dd>
                            <dt class="col-sm-5">Cargo Total:</dt><dd class="col-sm-7 fw-bold text-primary fs-5">Q<?php echo number_format($cirugia['cargo_total'], 2); ?></dd>
                        </dl>
                        <?php if ($cirugia['procedimiento']): ?>
                        <hr>
                        <h6 class="text-muted small text-uppercase">Procedimiento</h6>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($cirugia['procedimiento'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Equipo -->
            <div class="col-md-6">
                <div class="card shadow-sm border-0 rounded-3 h-100">
                    <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-people me-2"></i>Equipo Quirúrgico</h5></div>
                    <div class="card-body">
                        <?php if (empty($equipo)): ?>
                            <p class="text-muted text-center my-3">Sin equipo registrado</p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($equipo as $m): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <div>
                                            <strong><?php echo htmlspecialchars($m['nombre'] . ' ' . $m['apellido']); ?></strong>
                                            <small class="text-muted d-block"><?php echo htmlspecialchars($m['especialidad'] ?? ''); ?></small>
                                        </div>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($m['rol']); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Consumos -->
            <div class="col-12">
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-capsule me-2"></i>Medicamentos Consumidos</h5>
                        <?php if (in_array($cirugia['estado'], ['Programada', 'En_Curso'], true)): ?>
                            <button class="btn btn-sm btn-primary" onclick="openConsumoModal()">
                                <i class="bi bi-plus"></i> Agregar
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($consumos)): ?>
                            <div class="text-center text-muted p-4">
                                <i class="bi bi-bandaid" style="font-size: 2rem;"></i>
                                <p class="mt-2 mb-0">No se han consumido medicamentos.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="data-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Medicamento</th>
                                            <th>Presentación</th>
                                            <th class="text-end">Cantidad</th>
                                            <th class="text-end">Precio Unit.</th>
                                            <th class="text-end">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($consumos as $c): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($c['nom_medicamento']); ?></td>
                                                <td><?php echo htmlspecialchars($c['presentacion_med'] ?? '—'); ?></td>
                                                <td class="text-end"><?php echo number_format($c['cantidad'], 2); ?></td>
                                                <td class="text-end">Q<?php echo number_format($c['precio_unitario'], 2); ?></td>
                                                <td class="text-end fw-bold">Q<?php echo number_format($c['subtotal'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-light">
                                            <td colspan="4" class="text-end fw-bold">Total Consumos:</td>
                                            <td class="text-end fw-bold text-primary">Q<?php echo number_format(array_sum(array_column($consumos, 'subtotal')), 2); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal Agregar Medicamento -->
<div class="modal fade" id="consumoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Agregar Medicamento Usado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="consumoForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="id_cirugia" value="<?php echo $id_cirugia; ?>">
                    <div class="mb-3">
                        <label class="form-label">Buscar Medicamento *</label>
                        <input type="text" id="search-med" class="form-control" placeholder="Escriba nombre o código..." autocomplete="off">
                        <div id="med-results" class="list-group mt-1" style="max-height: 200px; overflow-y: auto;"></div>
                        <input type="hidden" name="id_inventario" id="id_inventario">
                        <div id="med-seleccionado" class="alert alert-success mt-2 d-none"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cantidad *</label>
                        <input type="number" step="0.01" min="0.01" class="form-control" name="cantidad" id="cantidad" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Agregar y Descontar Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const idCirugia = <?php echo $id_cirugia; ?>;
const csrf = <?php echo json_encode($csrf_token); ?>;
let consumoModal;

document.addEventListener('DOMContentLoaded', () => {
    consumoModal = new bootstrap.Modal(document.getElementById('consumoModal'));
    document.getElementById('consumoForm').addEventListener('submit', saveConsumo);

    const searchMed = document.getElementById('search-med');
    const medResults = document.getElementById('med-results');
    let timer;
    searchMed.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            const q = searchMed.value.trim();
            if (q.length < 1) { medResults.innerHTML = ''; return; }
            try {
                const res = await fetch('api/search_meds_quirofano.php?q=' + encodeURIComponent(q));
                const json = await res.json();
                if (json.success && json.data.length) {
                    medResults.innerHTML = json.data.map(m =>
                        `<a href="javascript:void(0)" class="list-group-item list-group-item-action" data-id="${m.id_inventario}" data-name="${m.nom_medicamento}" data-stock="${m.stock_quirofano}">
                            <strong>${m.nom_medicamento}</strong> · Stock: ${m.stock_quirofano} · Q${parseFloat(m.precio_hospital || m.precio_venta).toFixed(2)}
                        </a>`
                    ).join('');
                    medResults.querySelectorAll('a').forEach(a => {
                        a.addEventListener('click', () => {
                            document.getElementById('id_inventario').value = a.dataset.id;
                            document.getElementById('cantidad').max = a.dataset.stock;
                            const div = document.getElementById('med-seleccionado');
                            div.textContent = '✓ ' + a.dataset.name + ' (Stock disponible: ' + a.dataset.stock + ')';
                            div.classList.remove('d-none');
                            medResults.innerHTML = '';
                            searchMed.value = a.dataset.name;
                        });
                    });
                } else {
                    medResults.innerHTML = '<div class="list-group-item text-muted">Sin resultados o sin stock</div>';
                }
            } catch (e) { medResults.innerHTML = '<div class="list-group-item text-danger">Error</div>'; }
        }, 300);
    });
});

function openConsumoModal() {
    document.getElementById('consumoForm').reset();
    document.getElementById('med-results').innerHTML = '';
    document.getElementById('med-seleccionado').classList.add('d-none');
    consumoModal.show();
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
}

async function saveConsumo(e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    try {
        const res = await fetch('api/add_consumo_cirugia.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            Swal.fire('Agregado', json.message, 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    } catch (e) { Swal.fire('Error', 'Fallo de red', 'error'); }
}

async function cambiarEstado(estado) {
    const txt = estado === 'En_Curso' ? 'iniciar' : 'cancelar';
    const r = await Swal.fire({
        title: '¿' + txt.charAt(0).toUpperCase() + txt.slice(1) + ' cirugía?',
        icon: 'question', showCancelButton: true,
        confirmButtonText: 'Sí, ' + txt, cancelButtonText: 'No'
    });
    if (!r.isConfirmed) return;
    const fd = new FormData();
    fd.append('id_cirugia', idCirugia);
    fd.append('estado', estado);
    fd.append('csrf_token', csrf);
    const res = await fetch('api/cambiar_estado_cirugia.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.success) {
        Swal.fire('OK', json.message, 'success').then(() => location.reload());
    } else { Swal.fire('Error', json.message, 'error'); }
}

async function finalizarCirugia() {
    const r = await Swal.fire({
        title: '¿Finalizar cirugía?',
        html: 'El paciente será <strong>trasladado automáticamente</strong> a una habitación disponible (excluyendo la 401) con Q600 la primera noche y luego la tarifa normal.',
        icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Finalizar y Trasladar',
        cancelButtonText: 'Cancelar'
    });
    if (!r.isConfirmed) return;
    const fd = new FormData();
    fd.append('id_cirugia', idCirugia);
    fd.append('auto_trasladar', '1');
    fd.append('csrf_token', csrf);
    const res = await fetch('api/finalizar_cirugia.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.success) {
        Swal.fire({
            icon: 'success', title: 'Cirugía finalizada',
            text: json.message,
            confirmButtonText: 'Recargar'
        }).then(() => location.reload());
    } else { Swal.fire('Error', json.message, 'error'); }
}

async function previewAsignacion() {
    const card = document.getElementById('preview-asignacion-card');
    const body = document.getElementById('preview-asignacion-body');
    card.classList.remove('d-none');
    body.innerHTML = '<div class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm me-2"></div>Buscando cama disponible...</div>';

    try {
        const res = await fetch('api/preview_asignacion.php?id_cirugia=' + idCirugia);
        const json = await res.json();

        if (!json.success) {
            body.innerHTML = `<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>${escapeHtml(json.message || 'Error')}</div>`;
            return;
        }

        if (json.ya_hospitalizado) {
            body.innerHTML = `
                <div class="alert alert-info border-0 mb-3">
                    <i class="bi bi-info-circle me-2"></i><strong>Paciente ya hospitalizado</strong>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="text-center p-3 bg-light rounded">
                            <small class="text-muted text-uppercase d-block">Habitación</small>
                            <h3 class="mb-0 text-primary">${escapeHtml(json.habitacion)}</h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center p-3 bg-light rounded">
                            <small class="text-muted text-uppercase d-block">Cama</small>
                            <h3 class="mb-0 text-primary">${escapeHtml(json.cama)}</h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center p-3 bg-light rounded">
                            <small class="text-muted text-uppercase d-block">Tarifa / noche</small>
                            <h3 class="mb-0 text-primary">Q${parseFloat(json.tarifa_por_noche).toFixed(2)}</h3>
                        </div>
                    </div>
                </div>
                <p class="text-muted small mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Los cargos de la cirugía se agregarán a la cuenta hospitalaria existente.</p>
            `;
        } else if (!json.disponible) {
            body.innerHTML = `
                <div class="alert alert-warning border-0 mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i><strong>No hay camas disponibles</strong>
                    <p class="mb-0 mt-2">${escapeHtml(json.mensaje)}</p>
                </div>
            `;
        } else {
            const c = json.seleccionada;
            const otrasCamas = json.camas.length - 1;
            body.innerHTML = `
                <div class="alert alert-success border-0 mb-3">
                    <i class="bi bi-check-circle me-2"></i><strong>Habitación sugerida para asignación:</strong>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-primary bg-opacity-10 rounded">
                            <small class="text-muted text-uppercase d-block">Habitación</small>
                            <h3 class="mb-0 text-primary">${escapeHtml(c.numero_habitacion)}</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-info bg-opacity-10 rounded">
                            <small class="text-muted text-uppercase d-block">Cama</small>
                            <h3 class="mb-0 text-info">${escapeHtml(c.numero_cama)}</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                            <small class="text-muted text-uppercase d-block">Tipo</small>
                            <h5 class="mb-0">${escapeHtml(c.tipo_habitacion || '—')}</h5>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-success bg-opacity-10 rounded">
                            <small class="text-muted text-uppercase d-block">Tarifa / noche</small>
                            <h4 class="mb-0 text-success">Q${parseFloat(c.tarifa_por_noche).toFixed(2)}</h4>
                        </div>
                    </div>
                </div>
                <div class="border rounded p-3" style="background: rgba(13,110,253,.04);">
                    <h6 class="mb-2"><i class="bi bi-cash-stack me-1"></i>Cargos post-operatorios a aplicar:</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="mb-0 small">
                                <li>Primera noche: <strong class="text-primary">Q600.00</strong> <small class="text-muted">(tarifa fija cirugía)</small></li>
                                <li>Habitación: <strong>${escapeHtml(c.numero_habitacion)} - Cama ${escapeHtml(c.numero_cama)}</strong></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="mb-0 small">
                                <li>Noches subsiguientes: <strong class="text-success">Q${parseFloat(c.tarifa_por_noche).toFixed(2)}</strong> / noche</li>
                                ${otrasCamas > 0 ? `<li class="text-muted"><i class="bi bi-info-circle me-1"></i>${otrasCamas} cama(s) alternativa(s) disponible(s)</li>` : ''}
                            </ul>
                        </div>
                    </div>
                </div>
                <p class="text-muted small mt-3 mb-0"><i class="bi bi-shield-check me-1"></i>Esta es la habitación que se asignará automáticamente al finalizar la cirugía.</p>
            `;
        }
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (err) {
        body.innerHTML = `<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>Error de red: ${escapeHtml(err.message)}</div>`;
    }
}
</script>
</body>
</html>