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

    // Descuentos aplicados a esta cirugía
    $stmtDesc = $conn->prepare("
        SELECT id_descuento, concepto, monto, creado_en, cancelado, motivo_cancelacion
        FROM cirugia_descuentos
        WHERE id_cirugia = ? AND id_hospital = ?
        ORDER BY creado_en DESC
    ");
    $stmtDesc->execute([$id_cirugia, $id_hospital]);
    $descuentos = $stmtDesc->fetchAll(PDO::FETCH_ASSOC);

    // Total descuentos activos (no cancelados)
    $total_descuentos = 0.0;
    foreach ($descuentos as $d) {
        if (!$d['cancelado']) $total_descuentos += (float)$d['monto'];
    }

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
                            <?php if (!empty($cirugia['id_combo'])): ?>
                                <button class="btn btn-warning" onclick="cargarComboCirugia()" id="btnCargarCombo">
                                    <i class="bi bi-box-seam"></i> Cargar Combo
                                </button>
                            <?php endif; ?>
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
                                            <?php if (in_array($cirugia['estado'], ['Programada', 'En_Curso'], true)): ?>
                                                <th class="text-center">Acción</th>
                                            <?php endif; ?>
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
                                                <?php if (in_array($cirugia['estado'], ['Programada', 'En_Curso'], true)): ?>
                                                    <td class="text-center">
                                                        <button class="btn btn-sm btn-outline-danger"
                                                                onclick="eliminarConsumo(<?php echo (int)$c['id']; ?>, '<?php echo htmlspecialchars(addslashes($c['nom_medicamento'])); ?>', <?php echo (float)$c['cantidad']; ?>)"
                                                                title="Retornar al inventario de Quirófano">
                                                            <i class="bi bi-arrow-counterclockwise"></i>
                                                        </button>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-light">
                                            <td colspan="<?php echo in_array($cirugia['estado'], ['Programada', 'En_Curso'], true) ? '5' : '4'; ?>" class="text-end fw-bold">Total Consumos:</td>
                                            <td class="text-end fw-bold text-primary">Q<?php echo number_format(array_sum(array_column($consumos, 'subtotal')), 2); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Descuentos -->
            <div class="col-12">
                <div class="card shadow-sm border-0 rounded-3 border-start border-success border-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-percent me-2 text-success"></i>Descuentos Aplicados</h5>
                        <?php if (in_array($cirugia['estado'], ['Programada', 'En_Curso', 'Finalizada'], true)): ?>
                            <button class="btn btn-sm btn-success" onclick="openDescuentoModal()">
                                <i class="bi bi-plus"></i> Agregar Descuento
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($descuentos)): ?>
                            <div class="text-center text-muted p-4">
                                <i class="bi bi-percent" style="font-size: 2rem;"></i>
                                <p class="mt-2 mb-0">No se han aplicado descuentos a esta cirugía.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="data-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Concepto</th>
                                            <th class="text-end">Monto</th>
                                            <th class="text-center">Aplicado</th>
                                            <?php if (in_array($cirugia['estado'], ['Programada', 'En_Curso', 'Finalizada'], true)): ?>
                                                <th class="text-center">Acción</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($descuentos as $d): ?>
                                            <tr class="<?= $d['cancelado'] ? 'text-decoration-line-through text-muted' : '' ?>">
                                                <td><?php echo htmlspecialchars($d['concepto']); ?></td>
                                                <td class="text-end fw-bold text-success">-Q<?php echo number_format((float)$d['monto'], 2); ?></td>
                                                <td class="text-center"><small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($d['creado_en'])); ?></small></td>
                                                <?php if (in_array($cirugia['estado'], ['Programada', 'En_Curso', 'Finalizada'], true)): ?>
                                                    <td class="text-center">
                                                        <?php if (!$d['cancelado']): ?>
                                                        <button class="btn btn-sm btn-outline-danger"
                                                                onclick="eliminarDescuento(<?= (int)$d['id_descuento']; ?>, '<?= htmlspecialchars(addslashes($d['concepto'])); ?>', <?= (float)$d['monto']; ?>)"
                                                                title="Eliminar descuento">
                                                            <i class="bi bi-x-circle"></i>
                                                        </button>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Cancelado</span>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-success">
                                            <td class="fw-bold">Total Descuentos:</td>
                                            <td class="text-end fw-bold text-success">-Q<?php echo number_format($total_descuentos, 2); ?></td>
                                            <td colspan="<?= in_array($cirugia['estado'], ['Programada', 'En_Curso', 'Finalizada'], true) ? '2' : '1' ?>"></td>
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

<!-- Modal Agregar Descuento -->
<div class="modal fade" id="descuentoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-percent me-2"></i>Agregar Descuento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="descuentoForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="id_cirugia" value="<?= $id_cirugia ?>">
                    <div class="mb-3">
                        <label class="form-label">Concepto del Descuento *</label>
                        <input type="text" class="form-control" name="concepto" id="desc-concepto" required maxlength="255" placeholder="Ej: Descuento por convenio, promoción...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Monto del Descuento (Q) *</label>
                        <input type="number" step="0.01" min="0.01" class="form-control form-control-lg" name="monto" id="desc-monto" required>
                        <small class="text-muted">Este monto se restará del total de la cirugía al momento de aplicar cargos al paciente.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Aplicar Descuento</button>
                </div>
            </form>
        </div>
    </div>
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
        // Si pasó a En_Curso y hay combo con medicamentos, preguntar si quiere cargar
        if (estado === 'En_Curso' && document.getElementById('btnCargarCombo')) {
            Swal.fire({
                icon: 'question',
                title: 'Cirugía iniciada',
                text: '¿Desea cargar los medicamentos del Combo y descontarlos del stock de Quirófano?',
                showCancelButton: true,
                confirmButtonText: 'Sí, cargar combo',
                cancelButtonText: 'Más tarde'
            }).then(r => {
                if (r.isConfirmed) cargarComboCirugia();
                else location.reload();
            });
        } else {
            Swal.fire('OK', json.message, 'success').then(() => location.reload());
        }
    } else { Swal.fire('Error', json.message, 'error'); }
}

async function cargarComboCirugia(forzar = false) {
    if (!forzar) {
        const r = await Swal.fire({
            title: '¿Cargar medicamentos del Combo?',
            html: 'Se descontará el stock de Quirófano de todos los medicamentos vinculados al combo.',
            icon: 'question', showCancelButton: true,
            confirmButtonText: 'Sí, cargar',
            cancelButtonText: 'Cancelar'
        });
        if (!r.isConfirmed) return;
    }

    const fd = new FormData();
    fd.append('id_cirugia', idCirugia);
    fd.append('forzar', forzar ? '1' : '0');
    fd.append('csrf_token', csrf);

    Swal.fire({ title: 'Cargando combo...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const res = await fetch('api/cargar_combo_cirugia.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            Swal.fire({
                icon: json.descargados > 0 ? 'success' : 'info',
                title: 'Combo procesado',
                text: json.message,
                html: json.descargados > 0
                    ? `<div class="text-start small mt-2"><strong>${json.descargados}</strong> medicamento(s) descontado(s) de Quirófano.${json.errores_stock && json.errores_stock.length ? '<br><span class="text-warning">Advertencias: ' + json.errores_stock.join('; ') + '</span>' : ''}</div>`
                    : json.message
            }).then(() => location.reload());
        } else if (json.ya_cargado) {
            const r2 = await Swal.fire({
                title: 'Ya se cargaron medicamentos',
                text: json.message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Recargar (duplicar)',
                cancelButtonText: 'Cancelar'
            });
            if (r2.isConfirmed) cargarComboCirugia(true);
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Fallo de red: ' + err.message, 'error');
    }
}

// --- DESCUENTOS ---
let descuentoModal;
document.addEventListener('DOMContentLoaded', () => {
    const dm = document.getElementById('descuentoModal');
    if (dm) descuentoModal = new bootstrap.Modal(dm);
    const df = document.getElementById('descuentoForm');
    if (df) df.addEventListener('submit', saveDescuento);
});

function openDescuentoModal() {
    const f = document.getElementById('descuentoForm');
    if (f) f.reset();
    descuentoModal.show();
}

async function saveDescuento(e) {
    e.preventDefault();
    const concepto = document.getElementById('desc-concepto').value.trim();
    const monto = parseFloat(document.getElementById('desc-monto').value) || 0;
    if (!concepto) {
        Swal.fire('Error', 'Ingrese un concepto para el descuento', 'error');
        return;
    }
    if (monto <= 0) {
        Swal.fire('Error', 'El monto del descuento debe ser mayor a cero', 'error');
        return;
    }
    const fd = new FormData(e.target);
    Swal.fire({ title: 'Aplicando descuento...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    try {
        const res = await fetch('api/save_descuento_cirugia.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            descuentoModal.hide();
            Swal.fire({
                icon: 'success',
                title: '✓ Descuento aplicado',
                text: json.message,
                html: '<div class="text-start small mt-2">Se desconto Q' + parseFloat(json.monto).toFixed(2) + ' por "' + escapeHtml(json.concepto) + '".<br>Total descuentos acumulados: <strong>Q' + parseFloat(json.total_descuentos).toFixed(2) + '</strong></div>'
            }).then(() => location.reload());
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Fallo de red: ' + err.message, 'error');
    }
}

async function eliminarDescuento(idDescuento, concepto, monto) {
    const r = await Swal.fire({
        title: '¿Eliminar descuento?',
        html: '<div class="text-start"><p>Se eliminara el descuento de <strong>Q' + parseFloat(monto).toFixed(2) + '</strong> por concepto <strong>"' + escapeHtml(concepto) + '"</strong>.</p><p class="text-muted small mb-0">Esta accion no se puede deshacer.</p></div>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Si, eliminar',
        cancelButtonText: 'Cancelar'
    });
    if (!r.isConfirmed) return;
    const fd = new FormData();
    fd.append('id_descuento', idDescuento);
    fd.append('csrf_token', csrf);
    Swal.fire({ title: 'Eliminando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    try {
        const res = await fetch('api/delete_descuento_cirugia.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            Swal.fire({ icon: 'success', title: '✓ Descuento eliminado', text: json.message }).then(() => location.reload());
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Fallo de red: ' + err.message, 'error');
    }
}

async function eliminarConsumo(idConsumo, nombreMedicamento, cantidad) {
    // Modal con input de cantidad a retornar (permite retorno parcial)
    const { value: formValues } = await Swal.fire({
        title: 'Retornar al inventario',
        html: `<div class="text-start">
            <p>Medicamento: <strong>${escapeHtml(nombreMedicamento)}</strong></p>
            <p>Cantidad consumida: <strong>${cantidad} unidades</strong></p>
            <label class="form-label fw-bold mt-2">Cantidad a retornar *</label>
            <input type="number" id="retorno-cantidad" class="form-control form-control-lg text-end" min="0.01" max="${cantidad}" step="0.01" value="${cantidad}" required>
            <div class="d-flex gap-2 mt-2">
                <button type="button" id="btn-retorno-all" class="btn btn-sm btn-outline-secondary flex-fill" data-set-retorno="all">Todo (${cantidad})</button>
                <button type="button" id="btn-retorno-half" class="btn btn-sm btn-outline-secondary flex-fill" data-set-retorno="half">Mitad</button>
                <button type="button" id="btn-retorno-one" class="btn btn-sm btn-outline-secondary flex-fill" data-set-retorno="one">Solo 1</button>
            </div>
            <p class="text-muted small mb-0 mt-2"><i class="bi bi-info-circle"></i> Si retorna menos del total, el consumo se reducirá con la cantidad restante.</p>
        </div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Retornar',
        cancelButtonText: 'Cancelar',
        didOpen: () => {
            // Asignar eventos a los botones de selección rápida (cantidad total, mitad, 1)
            // El problema es que las comillas dobles en onclick="..." dentro de un template literal JS
            // cierran el onclick prematuramente. Usamos data-attributes + addEventListener aquí.
            const inputCant = document.getElementById('retorno-cantidad');
            const btnAll = document.getElementById('btn-retorno-all');
            const btnHalf = document.getElementById('btn-retorno-half');
            const btnOne = document.getElementById('btn-retorno-one');
            const maxCant = parseFloat(inputCant ? inputCant.max : 0) || 0;
            if (btnAll) btnAll.addEventListener('click', () => { if (inputCant) inputCant.value = maxCant; });
            if (btnHalf) btnHalf.addEventListener('click', () => { if (inputCant) inputCant.value = Math.max(1, Math.floor(maxCant / 2)); });
            if (btnOne) btnOne.addEventListener('click', () => { if (inputCant) inputCant.value = 1; });
        },
        preConfirm: () => {
            const inputEl = document.getElementById('retorno-cantidad');
            const v = parseFloat(inputEl ? inputEl.value : 0);
            if (!v || v <= 0) {
                Swal.showValidationMessage('Ingrese una cantidad válida');
                return false;
            }
            const cantMax = parseFloat(inputEl ? inputEl.max : 0) || 0;
            if (v > cantMax) {
                Swal.showValidationMessage('No puede retornar más de ' + cantMax);
                return false;
            }
            return { cantidad_retorno: v };
        }
    });
    if (!formValues) return;

    const fd = new FormData();
    fd.append('id_consumo', idConsumo);
    fd.append('cantidad_retorno', formValues.cantidad_retorno);
    fd.append('csrf_token', csrf);

    Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const res = await fetch('api/delete_consumo_cirugia.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            const esTotal = json.retorno_total !== false;
            const titleTxt = esTotal ? '✓ Stock retornado' : '✓ Retorno parcial';
            const extraTxt = esTotal
                ? '<br>Stock actual: <strong>' + json.stock_nuevo + '</strong>'
                : '<br>Stock actual: <strong>' + json.stock_nuevo + '</strong><br>Quedan <strong>' + json.cantidad_restante + '</strong> unidades en el consumo';
            Swal.fire({
                icon: 'success',
                title: titleTxt,
                text: json.message,
                html: '<div class="text-start small mt-2">Se retornaron <strong>' + json.cantidad + '</strong> unidades de <strong>' + escapeHtml(json.medicamento) + '</strong> al inventario de <strong>' + json.origen_label + '</strong>.' + extraTxt + '</div>'
            }).then(() => location.reload());
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Fallo de red: ' + err.message, 'error');
    }
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