<?php
// surgery/salas.php - CRUD de Salas Quirúrgicas
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
$user_name = $_SESSION['nombre'];
$csrf_token = $_SESSION['csrf_token'] ?? '';

try {
    $database = new Database();
    $conn = $database->getConnection();
    $stmt = $conn->prepare("SELECT id_sala, codigo, nombre, tipo, tarifa_base, estado FROM salas_quirurgicas WHERE id_hospital = ? ORDER BY codigo ASC");
    $stmt->execute([$id_hospital]);
    $salas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $salas = [];
    error_log('salas.php: ' . $e->getMessage());
}
$page_title = "Salas Quirúrgicas";
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
                <div><h2 class="mb-0" style="font-size: 1.25rem;">Salas Quirúrgicas</h2></div>
            </div>
            <div class="header-controls">
                <a href="index.php" class="back-btn"><i class="bi bi-arrow-left"></i><span>Quirófano</span></a>
            </div>
        </div>
    </header>
    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="section-title mb-0">Gestión de Salas</h3>
            <?php if ($user_type === 'admin'): ?>
                <button class="action-btn primary" onclick="openSalaModal()">
                    <i class="bi bi-plus-circle"></i> Nueva Sala
                </button>
            <?php endif; ?>
        </div>

        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-0">
                <?php if (empty($salas)): ?>
                    <div class="empty-state p-5 text-center text-muted">
                        <i class="bi bi-door-closed" style="font-size: 3rem;"></i>
                        <p class="mt-3">No hay salas registradas. Cree la primera sala para empezar.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Nombre</th>
                                    <th>Tipo</th>
                                    <th>Tarifa Base (Q)</th>
                                    <th>Estado</th>
                                    <?php if ($user_type === 'admin'): ?><th class="text-center">Acción</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($salas as $s): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($s['codigo']); ?></td>
                                    <td><?php echo htmlspecialchars($s['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($s['tipo'] ?? '—'); ?></td>
                                    <td class="text-end">Q<?php echo number_format($s['tarifa_base'], 2); ?></td>
                                    <td>
                                        <?php
                                        $cls = ['Disponible' => 'bg-success', 'Ocupada' => 'bg-danger', 'Mantenimiento' => 'bg-warning text-dark'][$s['estado']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge <?php echo $cls; ?>"><?php echo $s['estado']; ?></span>
                                    </td>
                                    <?php if ($user_type === 'admin'): ?>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-primary" onclick='editSala(<?php echo json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteSala(<?php echo (int)$s['id_sala']; ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Modal -->
<div class="modal fade" id="salaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="salaModalTitle">Nueva Sala Quirúrgica</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="salaForm">
                <div class="modal-body">
                    <input type="hidden" id="id_sala" name="id_sala">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <div class="mb-3">
                        <label class="form-label">Código *</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" required maxlength="20" placeholder="ej: QUIRO-01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nombre *</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required maxlength="100" placeholder="ej: Quirófano Principal">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipo</label>
                        <select class="form-select" id="tipo" name="tipo">
                            <option value="Cirugía General">Cirugía General</option>
                            <option value="Partos">Partos</option>
                            <option value="Endoscopía">Endoscopía</option>
                            <option value="Ortopedia">Ortopedia</option>
                            <option value="Cardiovascular">Cardiovascular</option>
                            <option value="Otra">Otra</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tarifa Base (Q)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="tarifa_base" name="tarifa_base" value="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="Disponible">Disponible</option>
                            <option value="Ocupada">Ocupada</option>
                            <option value="Mantenimiento">Mantenimiento</option>
                        </select>
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
let salaModal;
document.addEventListener('DOMContentLoaded', () => {
    salaModal = new bootstrap.Modal(document.getElementById('salaModal'));
    document.getElementById('salaForm').addEventListener('submit', saveSala);
});

function openSalaModal() {
    document.getElementById('salaModalTitle').textContent = 'Nueva Sala Quirúrgica';
    document.getElementById('salaForm').reset();
    document.getElementById('id_sala').value = '';
    salaModal.show();
}

function editSala(s) {
    document.getElementById('salaModalTitle').textContent = 'Editar Sala';
    document.getElementById('id_sala').value = s.id_sala;
    document.getElementById('codigo').value = s.codigo;
    document.getElementById('nombre').value = s.nombre;
    document.getElementById('tipo').value = s.tipo || 'Cirugía General';
    document.getElementById('tarifa_base').value = parseFloat(s.tarifa_base || 0).toFixed(2);
    document.getElementById('estado').value = s.estado;
    salaModal.show();
}

async function saveSala(e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    try {
        const res = await fetch('api/save_sala.php', { method: 'POST', body: fd });
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

async function deleteSala(id) {
    const csrf = document.querySelector('input[name="csrf_token"]').value;
    const r = await Swal.fire({
        title: '¿Eliminar sala?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Eliminar',
        cancelButtonText: 'Cancelar'
    });
    if (!r.isConfirmed) return;
    const fd = new FormData();
    fd.append('id_sala', id);
    fd.append('csrf_token', csrf);
    const res = await fetch('api/delete_sala.php', { method: 'POST', body: fd });
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