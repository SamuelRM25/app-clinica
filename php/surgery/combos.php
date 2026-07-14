<?php
// surgery/combos.php - CRUD de Combos de Operación
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

try {
    $database = new Database();
    $conn = $database->getConnection();
    $stmt = $conn->prepare("
        SELECT c.id_combo, c.codigo, c.nombre, c.precio_total, c.estado,
               COALESCE((SELECT SUM(monto) FROM cirugia_combo_items WHERE id_combo = c.id_combo AND tipo = 'Ganancia'), 0) AS total_ganancia,
               COALESCE((SELECT SUM(monto) FROM cirugia_combo_items WHERE id_combo = c.id_combo AND tipo = 'Gasto'), 0) AS total_gasto,
               (SELECT COUNT(*) FROM cirugia_combo_items WHERE id_combo = c.id_combo) AS total_items
        FROM cirugia_combos c
        WHERE c.id_hospital = ?
        ORDER BY c.nombre ASC
    ");
    $stmt->execute([$id_hospital]);
    $combos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $combos = [];
}
$page_title = "Combos de Operación";
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
    <style>
        .combo-card { border: 1px solid var(--color-border); border-radius: 12px; padding: 1rem; background: var(--color-card); transition: all .2s; }
        .combo-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
        .combo-section-title { font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; padding: .25rem .5rem; border-radius: 4px; display: inline-block; }
        .combo-section-ganancia { background: rgba(25,135,84,.1); color: #198754; }
        .combo-section-gasto { background: rgba(220,53,69,.1); color: #dc3545; }
        .item-row { display: flex; gap: .5rem; margin-bottom: .5rem; align-items: center; }
        .item-row select, .item-row input { font-size: .85rem; }
    </style>
</head>
<body>
<div class="marble-effect"></div>
<div class="dashboard-container">
    <header class="dashboard-header">
        <div class="header-content">
            <div class="brand-container">
                <img src="../../assets/img/cmhs.png" alt="CMHS" class="brand-logo" width="40" height="40">
                <div><h2 class="mb-0" style="font-size: 1.25rem;">Combos de Operación</h2></div>
            </div>
            <div class="header-controls">
                <a href="index.php" class="back-btn"><i class="bi bi-arrow-left"></i><span>Quirófano</span></a>
            </div>
        </div>
    </header>
    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="section-title mb-0">Catálogo de Combos</h3>
            <?php if ($user_type === 'admin'): ?>
                <button class="action-btn primary" onclick="openComboModal()">
                    <i class="bi bi-plus-circle"></i> Nuevo Combo
                </button>
            <?php endif; ?>
        </div>

        <?php if (empty($combos)): ?>
            <div class="card shadow-sm border-0 rounded-3">
                <div class="empty-state p-5 text-center text-muted">
                    <i class="bi bi-stack" style="font-size: 3rem;"></i>
                    <p class="mt-3">No hay combos configurados. Cree el primero para empezar a registrar cirugías.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($combos as $cb):
                    $margen = $cb['precio_total'] - $cb['total_gasto'];
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="combo-card h-100">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 class="mb-1"><?php echo htmlspecialchars($cb['nombre']); ?></h5>
                                <small class="text-muted"><?php echo htmlspecialchars($cb['codigo']); ?> · <?php echo $cb['total_items']; ?> items</small>
                            </div>
                            <span class="badge <?php echo $cb['estado'] === 'Activo' ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $cb['estado']; ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="combo-section-title combo-section-ganancia">Ganancias</span>
                            <strong class="text-success">Q<?php echo number_format($cb['total_ganancia'], 2); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="combo-section-title combo-section-gasto">Gastos</span>
                            <strong class="text-danger">Q<?php echo number_format($cb['total_gasto'], 2); ?></strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold">Precio Total:</span>
                            <strong class="text-primary fs-5">Q<?php echo number_format($cb['precio_total'], 2); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">Margen:</small>
                            <small class="fw-bold <?php echo $margen >= 0 ? 'text-success' : 'text-danger'; ?>">
                                Q<?php echo number_format($margen, 2); ?>
                            </small>
                        </div>
                        <?php if ($user_type === 'admin'): ?>
                        <div class="d-flex gap-2 mt-3">
                            <button class="btn btn-sm btn-outline-primary flex-fill" onclick='editCombo(<?php echo (int)$cb['id_combo']; ?>)'>
                                <i class="bi bi-pencil"></i> Editar
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteCombo(<?php echo (int)$cb['id_combo']; ?>)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Modal -->
<div class="modal fade" id="comboModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="comboModalTitle">Nuevo Combo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="comboForm">
                <div class="modal-body">
                    <input type="hidden" id="id_combo" name="id_combo">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Código *</label>
                            <input type="text" class="form-control" id="codigo" name="codigo" required maxlength="30" placeholder="ej: CIR-001">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nombre *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required maxlength="150" placeholder="ej: Apendicectomía">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Estado</label>
                            <select class="form-select" id="estado" name="estado">
                                <option value="Activo">Activo</option>
                                <option value="Inactivo">Inactivo</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="2"></textarea>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0 text-success"><i class="bi bi-plus-circle"></i> Ganancias</h6>
                                <button type="button" class="btn btn-sm btn-outline-success" onclick="addItem('Ganancia')">
                                    <i class="bi bi-plus"></i>
                                </button>
                            </div>
                            <div id="items-ganancia"></div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0 text-danger"><i class="bi bi-dash-circle"></i> Gastos</h6>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="addItem('Gasto')">
                                    <i class="bi bi-plus"></i>
                                </button>
                            </div>
                            <div id="items-gasto"></div>
                        </div>
                    </div>

                    <hr>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Total Ganancias:</label>
                            <div class="fs-5 text-success fw-bold" id="sum-ganancia">Q0.00</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Total Gastos:</label>
                            <div class="fs-5 text-danger fw-bold" id="sum-gasto">Q0.00</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Precio Total (Q):</label>
                            <input type="number" step="0.01" min="0" class="form-control form-control-lg fw-bold" id="precio_total" name="precio_total" value="0">
                            <small class="text-muted">Puede ajustar manualmente; por defecto es la suma de ganancias.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const CATS_GANANCIA = ['Encamamiento', 'Medicamento de sala', 'Uso de sala', 'Medicamentos de habitación'];
const CATS_GASTO = ['Costo de medicamento de sala', 'Costo de medicamento de habitación', 'Anestesia', 'Dietas', 'Ingreso', 'Circulación'];

let comboModal;
document.addEventListener('DOMContentLoaded', () => {
    comboModal = new bootstrap.Modal(document.getElementById('comboModal'));
    document.getElementById('comboForm').addEventListener('submit', saveCombo);
    document.getElementById('comboForm').addEventListener('input', updateTotals);
});

function openComboModal() {
    document.getElementById('comboModalTitle').textContent = 'Nuevo Combo';
    document.getElementById('comboForm').reset();
    document.getElementById('id_combo').value = '';
    document.getElementById('items-ganancia').innerHTML = '';
    document.getElementById('items-gasto').innerHTML = '';
    updateTotals();
    comboModal.show();
}

function addItem(tipo, cat = '', desc = '', monto = 0) {
    const containerId = tipo === 'Ganancia' ? 'items-ganancia' : 'items-gasto';
    const cats = tipo === 'Ganancia' ? CATS_GANANCIA : CATS_GASTO;
    const idx = Date.now() + Math.random();
    const catOptions = cats.map(c => `<option value="${c}" ${c === cat ? 'selected' : ''}>${c}</option>`).join('');
    const html = `
    <div class="item-row" data-idx="${idx}" data-tipo="${tipo}">
        <select class="form-select form-select-sm item-cat" style="flex: 1.2;">${catOptions}</select>
        <input type="text" class="form-control form-control-sm item-desc" placeholder="Detalle (opcional)" value="${desc}" style="flex: 1.5;">
        <input type="number" step="0.01" min="0" class="form-control form-control-sm item-monto text-end" value="${monto}" placeholder="0.00" style="flex: 1;">
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.item-row').remove(); updateTotals();">
            <i class="bi bi-x"></i>
        </button>
    </div>`;
    document.getElementById(containerId).insertAdjacentHTML('beforeend', html);
    updateTotals();
}

async function editCombo(id) {
    try {
        const res = await fetch('api/get_combo.php?id_combo=' + id);
        const json = await res.json();
        if (!json.success) { Swal.fire('Error', json.message || json.error, 'error'); return; }
        const c = json.data.combo;
        document.getElementById('comboModalTitle').textContent = 'Editar Combo';
        document.getElementById('id_combo').value = c.id_combo;
        document.getElementById('codigo').value = c.codigo;
        document.getElementById('nombre').value = c.nombre;
        document.getElementById('descripcion').value = c.descripcion || '';
        document.getElementById('precio_total').value = parseFloat(c.precio_total || 0).toFixed(2);
        document.getElementById('estado').value = c.estado;
        document.getElementById('items-ganancia').innerHTML = '';
        document.getElementById('items-gasto').innerHTML = '';
        json.data.items.forEach(it => addItem(it.tipo, it.categoria, it.descripcion || '', parseFloat(it.monto || 0)));
        updateTotals();
        comboModal.show();
    } catch (e) {
        Swal.fire('Error', 'No se pudo cargar el combo', 'error');
    }
}

function updateTotals() {
    let sg = 0, sd = 0;
    document.querySelectorAll('#items-ganancia .item-monto').forEach(i => sg += parseFloat(i.value) || 0);
    document.querySelectorAll('#items-gasto .item-monto').forEach(i => sd += parseFloat(i.value) || 0);
    document.getElementById('sum-ganancia').textContent = 'Q' + sg.toFixed(2);
    document.getElementById('sum-gasto').textContent = 'Q' + sd.toFixed(2);
    const pt = document.getElementById('precio_total');
    if (document.activeElement !== pt) pt.value = sg.toFixed(2);
}

async function saveCombo(e) {
    e.preventDefault();
    const items = [];
    document.querySelectorAll('#comboForm .item-row').forEach(row => {
        items.push({
            tipo: row.dataset.tipo,
            categoria: row.querySelector('.item-cat').value,
            descripcion: row.querySelector('.item-desc').value,
            monto: parseFloat(row.querySelector('.item-monto').value) || 0,
        });
    });
    const fd = new FormData(e.target);
    fd.set('items_json', JSON.stringify(items));
    try {
        const res = await fetch('api/save_combo.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            Swal.fire('Éxito', json.message, 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Fallo de red', 'error');
    }
}

async function deleteCombo(id) {
    const csrf = document.querySelector('input[name="csrf_token"]').value;
    const r = await Swal.fire({
        title: '¿Eliminar combo?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Eliminar',
        cancelButtonText: 'Cancelar'
    });
    if (!r.isConfirmed) return;
    const fd = new FormData();
    fd.append('id_combo', id);
    fd.append('csrf_token', csrf);
    const res = await fetch('api/delete_combo.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.success) {
        Swal.fire('Eliminado', json.message, 'success').then(() => location.reload());
    } else {
        Swal.fire('Error', json.message, 'error');
    }
}
</script>
</body>
</html>