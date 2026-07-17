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
    <link rel="stylesheet" href="../../assets/css/style.css?v=3">
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css?v=3">
    <style>
        .combo-card { border: 1px solid var(--color-border); border-radius: 12px; padding: 1rem; background: var(--color-card); transition: all .2s; }
        .combo-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
        .combo-section-title { font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; padding: .25rem .5rem; border-radius: 4px; display: inline-block; }
        .combo-section-ganancia { background: rgba(25,135,84,.1); color: #198754; }
        .combo-section-gasto { background: rgba(220,53,69,.1); color: #dc3545; }

        /* Modal body scroll */
        #comboModal .modal-body { padding: 1.5rem; max-height: calc(100vh - 180px); }
        #comboModal .modal-content { display: flex; flex-direction: column; height: 100vh; }

        /* Sobrescribir el max-width:500px del global para que modal-fullscreen funcione */
        #comboModal.modal-dialog,
        #comboModal.modal-fullscreen {
            max-width: 100vw !important;
            width: 100vw !important;
            height: 100vh !important;
            margin: 0 !important;
        }

        /* Section panels for Ganancias/Gastos */
        .combo-section-panel {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .combo-section-panel.gan { border-left: 4px solid #198754; }
        .combo-section-panel.gas { border-left: 4px solid #dc3545; }
        .combo-section-panel .section-title-bar {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: .75rem;
        }

        /* Item row layout (grid responsive) */
        .item-row {
            display: grid;
            grid-template-columns: minmax(160px, 1.4fr) minmax(180px, 2fr) minmax(120px, 1fr) 38px;
            gap: .6rem;
            margin-bottom: .5rem;
            align-items: center;
            padding: .5rem .65rem;
            background: var(--color-surface);
            border-radius: 8px;
            border: 1px solid var(--color-border);
            transition: all .15s;
        }
        .item-row:hover { border-color: var(--color-primary); }
        .item-row .item-cat {
            font-weight: 600; font-size: .9rem;
            background: var(--color-bg);
        }
        .item-row .item-desc {
            font-size: .9rem;
            background: var(--color-bg);
        }
        .item-row .item-monto {
            font-size: 1.05rem;
            font-weight: 600;
            text-align: end;
            background: var(--color-bg);
        }
        .item-row .btn-remove {
            width: 38px; height: 38px;
            display: inline-flex;
            align-items: center; justify-content: center;
            padding: 0;
        }
        .item-row.is-extra { background: rgba(13,110,253,.05); }

        /* Add-more dashed button */
        .add-more {
            border: 2px dashed var(--color-border);
            background: transparent;
            color: var(--color-text-secondary);
            width: 100%;
            padding: .6rem;
            border-radius: 8px;
            font-size: .9rem;
            margin-top: .25rem;
            cursor: pointer;
            transition: all .15s;
        }
        .add-more:hover {
            border-color: var(--color-primary);
            color: var(--color-primary);
            background: rgba(13,110,253,.04);
        }

        /* Totals panel */
        .totals-panel {
            background: var(--color-surface);
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            border: 1px solid var(--color-border);
        }
        .totals-panel .total-block {
            background: var(--color-bg);
            border-radius: 8px;
            padding: .75rem 1rem;
            border: 1px solid var(--color-border);
        }

        /* Responsive */
        @media (max-width: 575.98px) {
            .item-row { grid-template-columns: 1fr; gap: .35rem; }
            .item-row .btn-remove { width: 100%; height: 32px; }
        }
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

<!-- Modal — Fullscreen en md+, modal-xl en lg -->
<div class="modal fade" id="comboModal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen modal-dialog-scrollable" style="max-width:100vw !important;width:100vw !important;height:100vh !important;margin:0 !important;padding:0 !important;">
        <div class="modal-content" style="border-radius:0 !important;height:100vh !important;max-height:100vh !important;">
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="comboModalTitle">
                    <i class="bi bi-stack me-2"></i>Nuevo Combo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="comboForm">
                <div class="modal-body">
                    <input type="hidden" id="id_combo" name="id_combo">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Código *</label>
                            <input type="text" class="form-control form-control-lg" id="codigo" name="codigo" required maxlength="30" placeholder="ej: CIR-001">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fw-semibold">Nombre *</label>
                            <input type="text" class="form-control form-control-lg" id="nombre" name="nombre" required maxlength="150" placeholder="ej: Apendicectomía">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Estado</label>
                            <select class="form-select form-select-lg" id="estado" name="estado">
                                <option value="Activo">Activo</option>
                                <option value="Inactivo">Inactivo</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="2" placeholder="Breve descripción del combo..."></textarea>
                    </div>

                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="combo-section-panel gan">
                                <div class="section-title-bar">
                                    <h6 class="mb-0 text-success fw-bold">
                                        <i class="bi bi-arrow-up-circle me-1"></i> Ganancias
                                        <small class="text-muted ms-2">(lo que cobra al paciente)</small>
                                    </h6>
                                    <span class="badge bg-success" id="badge-count-gan">0</span>
                                </div>
                                <div id="items-ganancia"></div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="combo-section-panel gas">
                                <div class="section-title-bar">
                                    <h6 class="mb-0 text-danger fw-bold">
                                        <i class="bi bi-arrow-down-circle me-1"></i> Gastos
                                        <small class="text-muted ms-2">(costos internos)</small>
                                    </h6>
                                    <span class="badge bg-danger" id="badge-count-gas">0</span>
                                </div>
                                <div id="items-gasto"></div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info border-0 mb-3 small">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Tip:</strong> Para que un item descuente stock al iniciar la cirugía, seleccione un medicamento del inventario en la categoría. Use "Agregar otro cargo" para items personalizados (sin descuento de stock).
                    </div>

                    <div class="totals-panel">
                        <div class="row g-2 align-items-center">
                            <div class="col-md-4">
                                <div class="total-block">
                                    <small class="text-muted text-uppercase fw-bold d-block">Total Ganancias</small>
                                    <div class="fs-3 fw-bold text-success" id="sum-ganancia">Q0.00</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="total-block">
                                    <small class="text-muted text-uppercase fw-bold d-block">Total Gastos</small>
                                    <div class="fs-3 fw-bold text-danger" id="sum-gasto">Q0.00</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="total-block">
                                    <small class="text-muted text-uppercase fw-bold d-block">Precio Total (Q)</small>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-lg fw-bold text-primary" id="precio_total" name="precio_total" value="0">
                                </div>
                            </div>
                        </div>
                    </div>

                    <datalist id="list-cat-Ganancia">
                        <option value="Encamamiento">
                        <option value="Medicamento de sala">
                        <option value="Uso de sala">
                        <option value="Medicamentos de habitación">
                    </datalist>
                    <datalist id="list-cat-Gasto">
                        <option value="Costo de medicamento de sala">
                        <option value="Costo de medicamento de habitación">
                        <option value="Anestesia">
                        <option value="Dietas">
                        <option value="Ingreso">
                        <option value="Circulación">
                    </datalist>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save"></i> Guardar Combo</button>
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

function escapeAttr(s) {
    return (s == null ? '' : String(s))
        .replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function itemRowHtml(tipo, cat, desc, monto, isExtra = false, id_inventario = null, cantidad = 1, med_nombre = '') {
    const listId = 'list-cat-' + tipo;
    return `
    <div class="item-row ${isExtra ? 'is-extra' : ''} ${id_inventario ? 'has-med' : ''}" data-tipo="${tipo}" data-predef="${isExtra ? '0' : '1'}">
        <div>
            <input list="${listId}" type="text" class="form-control form-control-sm item-cat" value="${escapeAttr(cat)}" placeholder="Escribir o seleccionar..." maxlength="50" autocomplete="off">
        </div>
        <div class="item-med-search">
            ${id_inventario
                ? `<div class="input-group input-group-sm">
                        <span class="input-group-text bg-success text-white" title="Medicamento vinculado"><i class="bi bi-capsule"></i></span>
                        <input type="text" class="form-control item-med-input" placeholder="Buscar medicamento..." value="${escapeAttr(med_nombre || desc)}">
                        <button type="button" class="btn btn-outline-danger" onclick="unlinkMed(this)" title="Quitar medicamento"><i class="bi bi-x"></i></button>
                    </div>
                    <input type="hidden" class="item-inv-id" value="${id_inventario}">
                    <div class="med-results small"></div>`
                : `<div class="input-group input-group-sm">
                        <input type="text" class="form-control item-med-input" placeholder="(Opcional) Vincular medicamento del inventario...">
                        <button type="button" class="btn btn-outline-success" onclick="searchMedInline(this)" title="Buscar"><i class="bi bi-search"></i></button>
                    </div>
                    <input type="hidden" class="item-inv-id" value="">
                    <div class="med-results small"></div>`
            }
        </div>
        <div class="d-flex gap-1 align-items-center">
            <input type="number" step="0.01" min="0" class="form-control form-control-sm item-monto text-end" value="${parseFloat(monto || 0).toFixed(2)}" placeholder="0.00" title="Precio/Monto">
            <input type="number" step="1" min="1" class="form-control form-control-sm item-cantidad text-end" value="${parseInt(cantidad || 1)}" placeholder="1" title="Cantidad a descontar" style="width: 60px;">
        </div>
        <button type="button" class="btn btn-outline-danger btn-remove" onclick="removeItem(this)" title="Eliminar fila">
            <i class="bi bi-trash"></i>
        </button>
    </div>`;
}

function unlinkMed(btn) {
    const row = btn.closest('.item-row');
    row.classList.remove('has-med');
    row.querySelector('.item-inv-id').value = '';
    const id_inventario = row.querySelector('.item-inv-id').value;
    const cat = row.querySelector('.item-cat').value;
    const desc = row.querySelector('.item-desc')?.value || '';
    const monto = row.querySelector('.item-monto').value;
    const cantidad = row.querySelector('.item-cantidad')?.value || 1;
    const tipo = row.dataset.tipo;
    const predef = row.dataset.predef === '1' ? 1 : 0;
    row.outerHTML = itemRowHtml(tipo, cat, desc, monto, predef === 0, null, cantidad, '');
}

function searchMedInline(btn) {
    const row = btn.closest('.item-row');
    const input = row.querySelector('.item-med-input');
    const resultsDiv = row.querySelector('.med-results');
    input.focus();
    // Setup search on input if not done
    if (!input.dataset.bound) {
        input.dataset.bound = '1';
        let timer;
        input.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => doMedSearch(row, input.value.trim(), resultsDiv), 300);
        });
    }
}

async function doMedSearch(row, q, resultsDiv) {
    if (q.length < 2) { resultsDiv.innerHTML = ''; return; }
    try {
        const res = await fetch('api/search_meds.php?q=' + encodeURIComponent(q));
        const json = await res.json();
        if (json.success && json.data.length) {
            resultsDiv.innerHTML = json.data.map(m =>
                `<a href="javascript:void(0)" class="list-group-item list-group-item-action py-1 px-2 small" data-id="${m.id_inventario}" data-name="${escapeAttr(m.nom_medicamento + ' (' + (m.presentacion_med || '') + ')')}">
                    <strong>${escapeHtml(m.nom_medicamento)}</strong>
                    <small class="text-muted ms-1">${escapeHtml(m.presentacion_med || '')}</small>
                    <span class="badge bg-info ms-1">${parseFloat(m.stock_quirofano || 0).toFixed(0)} en Quirófano</span>
                </a>`
            ).join('');
            resultsDiv.classList.add('list-group');
            resultsDiv.querySelectorAll('a').forEach(a => {
                a.addEventListener('click', () => {
                    const idInv = a.dataset.id;
                    const name = a.dataset.name;
                    row.querySelector('.item-inv-id').value = idInv;
                    row.classList.add('has-med');
                    row.querySelector('.item-med-input').value = name;
                    // Update the row visually
                    const tipo = row.dataset.tipo;
                    const cat = row.querySelector('.item-cat').value;
                    const desc = name;
                    const monto = row.querySelector('.item-monto').value;
                    const cantidad = row.querySelector('.item-cantidad').value;
                    const predef = row.dataset.predef === '1' ? 1 : 0;
                    row.outerHTML = itemRowHtml(tipo, cat, desc, monto, predef === 0, idInv, cantidad, name);
                    resultsDiv.innerHTML = '';
                });
            });
        } else {
            resultsDiv.innerHTML = '<div class="text-muted small">Sin resultados</div>';
            resultsDiv.classList.remove('list-group');
        }
    } catch (e) {
        resultsDiv.innerHTML = '<div class="text-danger small">Error en búsqueda</div>';
    }
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
}

function addExtraItem(tipo) {
    const containerId = tipo === 'Ganancia' ? 'items-ganancia' : 'items-gasto';
    document.getElementById(containerId).insertAdjacentHTML('beforeend',
        itemRowHtml(tipo, '', '', 0, true)
    );
    updateTotals();
}

function removeItem(btn) {
    const row = btn.closest('.item-row');
    const tipo = row.dataset.tipo;
    row.remove();
    // Si era fila predefinida (original), dejar que el usuario la re-agregue manualmente con "+"
    updateTotals();
}

function addMoreButton(tipo) {
    return `<button type="button" class="add-more" onclick="addExtraItem('${tipo}')">
        <i class="bi bi-plus-circle me-1"></i> Agregar otro cargo
    </button>`;
}

function seedItems(tipo) {
    const cats = tipo === 'Ganancia' ? CATS_GANANCIA : CATS_GASTO;
    const containerId = tipo === 'Ganancia' ? 'items-ganancia' : 'items-gasto';
    cats.forEach(c => {
        // Solo las categorías de medicamentos se precargan con búsqueda; las demás quedan vacías
        if (tipo === 'Ganancia' && (c === 'Medicamento de sala' || c === 'Medicamentos de habitación')) {
            document.getElementById(containerId).insertAdjacentHTML('beforeend',
                itemRowHtml(tipo, c, '', 0, false, null, 1, '')
            );
        } else {
            document.getElementById(containerId).insertAdjacentHTML('beforeend',
                itemRowHtml(tipo, c, '', 0, false, null, 1, '')
            );
        }
    });
    document.getElementById(containerId).insertAdjacentHTML('beforeend', addMoreButton(tipo));
}

function openComboModal() {
    document.getElementById('comboModalTitle').innerHTML = '<i class="bi bi-stack me-2"></i>Nuevo Combo';
    document.getElementById('comboForm').reset();
    document.getElementById('id_combo').value = '';
    document.getElementById('items-ganancia').innerHTML = '';
    document.getElementById('items-gasto').innerHTML = '';

    seedItems('Ganancia');
    seedItems('Gasto');

    updateTotals();
    comboModal.show();
}

async function editCombo(id) {
    try {
        const res = await fetch('api/get_combo.php?id_combo=' + id);
        const json = await res.json();
        if (!json.success) {
            Swal.fire('Error', json.message || json.error, 'error');
            return;
        }
        const c = json.data.combo;
        document.getElementById('comboModalTitle').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Editar Combo';
        document.getElementById('id_combo').value = c.id_combo;
        document.getElementById('codigo').value = c.codigo;
        document.getElementById('nombre').value = c.nombre;
        document.getElementById('descripcion').value = c.descripcion || '';
        document.getElementById('precio_total').value = parseFloat(c.precio_total || 0).toFixed(2);
        document.getElementById('estado').value = c.estado;

        document.getElementById('items-ganancia').innerHTML = '';
        document.getElementById('items-gasto').innerHTML = '';

        // Seed all predefined rows with default 0
        seedItems('Ganancia');
        seedItems('Gasto');

        // Apply existing items into matching predefined rows
        const predefinedG = new Set(CATS_GANANCIA);
        const predefinedE = new Set(CATS_GASTO);

        const existingByCat = {};
        const usedItemIds = new Set();
        json.data.items.forEach(it => {
            const k = it.tipo + '|' + it.categoria;
            // Si ya hay uno para esta categoría, acumulamos en lugar de sobreescribir
            if (!existingByCat[k]) existingByCat[k] = [];
            existingByCat[k].push(it);
        });

        document.querySelectorAll('#items-ganancia .item-row[data-predef="1"], #items-gasto .item-row[data-predef="1"]').forEach(row => {
            const tipo = row.dataset.tipo;
            const cat = row.querySelector('.item-cat').value;
            const matches = existingByCat[tipo + '|' + cat];
            if (matches && matches.length) {
                // Tomar el primero
                const match = matches.shift();
                usedItemIds.add(match.id_item);
                row.querySelector('.item-monto').value = parseFloat(match.monto || 0).toFixed(2);
                // Set description in .item-med-input (it's always present in itemRowHtml)
                const medInput = row.querySelector('.item-med-input');
                if (medInput) medInput.value = match.descripcion || '';
                // Set cantidad
                const cantInput = row.querySelector('.item-cantidad');
                if (cantInput) cantInput.value = parseFloat(match.cantidad || 1);
                if (match.id_inventario) {
                    const invInput = row.querySelector('.item-inv-id');
                    if (invInput) invInput.value = match.id_inventario;
                    row.classList.add('has-med');
                    // Reemplazar la fila con la versión con medicamento (incluye búsqueda X para desvincular)
                    row.outerHTML = itemRowHtml(
                        match.tipo, match.categoria, '',
                        parseFloat(match.monto || 0), false,
                        match.id_inventario, parseFloat(match.cantidad || 1),
                        match.descripcion || ''
                    );
                }
            }
        });

        // Existing items whose category is NOT in predefined list → add as extras
        json.data.items.forEach(it => {
            const isPredef = it.tipo === 'Ganancia' ? predefinedG.has(it.categoria) : predefinedE.has(it.categoria);
            if (!isPredef || usedItemIds.has(it.id_item)) {
                if (!usedItemIds.has(it.id_item)) {
                    const containerId = it.tipo === 'Ganancia' ? 'items-ganancia' : 'items-gasto';
                    document.getElementById(containerId).insertAdjacentHTML('beforeend',
                        itemRowHtml(it.tipo, it.categoria, it.descripcion || '', parseFloat(it.monto || 0), true,
                                    it.id_inventario, parseFloat(it.cantidad || 1), it.descripcion || '')
                    );
                    usedItemIds.add(it.id_item);
                }
            }
        });

        updateTotals();
        comboModal.show();
    } catch (e) {
        Swal.fire('Error', 'No se pudo cargar el combo', 'error');
    }
}

function updateTotals() {
    let sg = 0, sd = 0;
    let countG = 0, countD = 0;
    document.querySelectorAll('#items-ganancia .item-row').forEach(r => {
        countG++;
        sg += parseFloat(r.querySelector('.item-monto').value) || 0;
    });
    document.querySelectorAll('#items-gasto .item-row').forEach(r => {
        countD++;
        sd += parseFloat(r.querySelector('.item-monto').value) || 0;
    });
    document.getElementById('sum-ganancia').textContent = 'Q' + sg.toFixed(2);
    document.getElementById('sum-gasto').textContent = 'Q' + sd.toFixed(2);
    document.getElementById('badge-count-gan').textContent = countG;
    document.getElementById('badge-count-gas').textContent = countD;
    const pt = document.getElementById('precio_total');
    if (document.activeElement !== pt) pt.value = sg.toFixed(2);
}

async function saveCombo(e) {
    e.preventDefault();
    const items = [];
    document.querySelectorAll('#comboForm .item-row').forEach(row => {
        const tipo = row.dataset.tipo;
        const cat = row.querySelector('.item-cat').value;
        const desc = row.querySelector('.item-desc')?.value || '';
        const medInput = row.querySelector('.item-med-input')?.value || '';
        const idInv = parseInt(row.querySelector('.item-inv-id')?.value || 0) || null;
        const monto = parseFloat(row.querySelector('.item-monto').value) || 0;
        const cantidad = parseFloat(row.querySelector('.item-cantidad')?.value || 1) || 1;
        const predef = row.dataset.predef === '1' ? 1 : 0;
        // Para filas predefinidas vacías → no guardar
        if (predef === 1 && monto === 0 && !desc.trim() && !medInput.trim() && !idInv && cat) {
            return;
        }
        items.push({
            tipo,
            categoria: cat,
            descripcion: idInv ? medInput : desc,
            monto,
            id_inventario: idInv,
            cantidad
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