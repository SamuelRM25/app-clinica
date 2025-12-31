<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer la zona horaria correcta
date_default_timezone_set('America/Guatemala');


verify_session();

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $page_title = "Gestión de Pacientes - Clínica";
    include_once '../../includes/header.php';

    // Fetch patients
    if ($_SESSION['tipoUsuario'] === 'doc') {
        // Patients who have an appointment with this doctor
        $stmt = $conn->prepare("
            SELECT DISTINCT p.* 
            FROM pacientes p
            JOIN citas c ON (p.nombre = c.nombre_pac AND p.apellido = c.apellido_pac)
            WHERE c.id_doctor = ?
            ORDER BY p.apellido, p.nombre
        ");
        $stmt->execute([$_SESSION['user_id']]);
    } else {
        // Admin or other users see all patients
        $stmt = $conn->prepare("SELECT * FROM pacientes ORDER BY apellido, nombre");
        $stmt->execute();
    }
    $patients = $stmt->fetchAll();
} catch (Exception $e) {
    die("Error: No se pudo conectar a la base de datos");
}
?>

<!-- Inject Dashboard Custom Styles -->
<link rel="stylesheet" href="../../assets/css/dashboard-reengineered.css">


<div class="dashboard-wrapper sidebar-collapsed">
    <!-- Mobile Overlay -->
    <div class="dashboard-mobile-overlay"></div>

    <!-- Desktop Sidebar Toggle -->
    <button class="btn btn-white shadow-sm border rounded-circle position-fixed d-none d-md-flex align-items-center justify-content-center" id="desktopSidebarToggle" title="Mostrar Menú" style="top: 20px; left: 20px; width: 45px; height: 45px; z-index: 1040; transition: all 0.3s ease;">
        <i class="bi bi-list text-primary fs-4"></i>
    </button>

    <!-- Sidebar Reengineered -->
    <div class="sidebar-glass p-3 d-flex flex-column">
        <div class="brand-section d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center text-decoration-none">
                <img src="../../assets/img/siloe.png" alt="Logo" style="height: 40px; margin-right: 15px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));"/>
            </div>
            <button class="btn btn-sm btn-light text-primary rounded-circle shadow-sm d-none d-md-flex align-items-center justify-content-center" id="sidebarCloseBtn" style="width: 32px; height: 32px;">
                <i class="bi bi-chevron-left"></i>
            </button>
        </div>
        

        <ul class="nav nav-pills flex-column mb-auto">
            <?php $rol = $_SESSION['tipoUsuario'] ?? $_SESSION['rol'] ?? ''; ?>
            <?php if ($rol === 'admin' || $rol === 'doc' || $rol === 'user'): ?>
            <li class="nav-item"><a href="../dashboard/index.php" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a href="../patients/index.php" class="nav-link active"><i class="bi bi-people"></i> Pacientes</a></li>
            <?php endif; ?>
            <?php if ($rol === 'admin' || $rol === 'user'): ?>
            <li><a href="../appointments/index.php" class="nav-link"><i class="bi bi-calendar"></i> Citas</a></li>
            <li><a href="../minor_procedures/index.php" class="nav-link"><i class="bi bi-bandaid"></i> Proc. Menores</a></li>
            <li><a href="../examinations/index.php" class="nav-link"><i class="bi bi-file-earmark-medical"></i> Exámenes</a></li>
            <li><a href="../dispensary/index.php" class="nav-link"><i class="bi bi-cart4"></i> Dispensario</a></li>
            <li><a href="../inventory/index.php" class="nav-link"><i class="bi bi-box-seam"></i> Inventario</a></li>
            <?php endif; ?>
            <?php if ($rol === 'admin'): ?>
            <li><a href="../purchases/index.php" class="nav-link"><i class="bi bi-cart-plus"></i> Compras</a></li>
            <li><a href="../sales/index.php" class="nav-link"><i class="bi bi-receipt"></i> Ventas</a></li>
            <li><a href="../reports/index.php" class="nav-link"><i class="bi bi-bar-chart-line"></i> Reportes</a></li>
            <?php endif; ?>
            <?php if ($rol === 'admin' || $rol === 'user'): ?>
            <li><a href="../billing/index.php" class="nav-link"><i class="bi bi-cash-coin"></i> Cobros</a></li>
            <?php endif; ?>
        </ul>
        
        <div class="mt-auto">
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle p-2 rounded hover-effect" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false" style="color: var(--text-color);">
                    <div class="avatar-circle me-2 bg-primary text-white d-flex align-items-center justify-content-center rounded-circle" style="width: 32px; height: 32px;">
                        <?php echo strtoupper(substr($_SESSION['nombre'], 0, 1)); ?>
                    </div>
                    <strong><?php echo htmlspecialchars($_SESSION['nombre']); ?></strong>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="dropdownUser1">
                    <li><a class="dropdown-item" href="../auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Main Content Reengineered -->
    <div class="main-content-glass">
        <div class="container-fluid">
            <!-- Header Section -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Gestión de Pacientes</h2>
                    <p class="text-muted"><i class="bi bi-people me-1"></i> <?php echo count($patients); ?> pacientes registrados</p>
                </div>
                <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#newPatientModal">
                    <i class="bi bi-person-plus-fill me-2"></i>Nuevo Paciente
                </button>
            </div>

            <!-- Search Section -->
            <div class="card-glass mb-4">
                <div class="card-body p-3">
                    <div class="input-group input-group-lg border-0 shadow-none">
                        <span class="input-group-text bg-transparent border-0 text-primary">
                            <i class="bi bi-search fs-4"></i>
                        </span>
                        <input type="text" id="searchInput" class="form-control bg-transparent border-0 ps-2" placeholder="Buscar por nombre, apellido, teléfono o correo..." style="box-shadow: none;">
                    </div>
                </div>
            </div>

            <!-- Patients List Section -->
            <div class="card-glass shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="patientsTable">
                            <thead class="bg-light bg-opacity-50">
                                <tr>
                                    <th class="ps-4 py-3 text-muted fw-semibold">Paciente</th>
                                    <th class="py-3 text-muted fw-semibold">Contacto</th>
                                    <th class="py-3 text-muted fw-semibold">Información</th>
                                    <th class="py-3 text-muted fw-semibold text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($patients as $patient): ?>
                                <tr class="hover-row">
                                    <td class="ps-4 py-3">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle me-3 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center rounded-circle" style="width: 45px; height: 45px; font-weight: 600;">
                                                <?php echo strtoupper(substr($patient['nombre'] ?? '', 0, 1) . substr($patient['apellido'] ?? '', 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars(($patient['nombre'] ?? '') . ' ' . ($patient['apellido'] ?? '')); ?></div>
                                                <div class="text-xs text-muted">ID: #<?php echo str_pad($patient['id_paciente'], 5, '0', STR_PAD_LEFT); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <div class="d-flex flex-column">
                                            <span class="text-sm"><i class="bi bi-telephone text-muted me-2"></i><?php echo htmlspecialchars($patient['telefono'] ?? 'N/A'); ?></span>
                                            <small class="text-muted"><i class="bi bi-envelope text-muted me-2"></i><?php echo htmlspecialchars($patient['correo'] ?? 'N/A'); ?></small>
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <div class="d-flex flex-column">
                                            <span class="text-sm"><i class="bi bi-calendar3 text-muted me-2"></i><?php echo htmlspecialchars($patient['fecha_nacimiento'] ?? 'N/A'); ?></span>
                                            <small class="text-muted">
                                                <i class="bi bi-gender-ambiguous text-muted me-2"></i>
                                                <span class="badge bg-opacity-10 <?php echo ($patient['genero'] == 'Masculino') ? 'bg-primary text-primary' : (($patient['genero'] == 'Femenino') ? 'bg-danger text-danger' : 'bg-secondary text-secondary'); ?> rounded-pill px-2">
                                                    <?php echo htmlspecialchars($patient['genero'] ?? 'No definido'); ?>
                                                </span>
                                            </small>
                                        </div>
                                    </td>
                                    <td class="py-3 text-center pe-4">
                                        <a href="medical_history.php?id=<?php echo $patient['id_paciente']; ?>" class="btn btn-icon btn-glass text-success hover-scale" title="Historial Clínico">
                                            <i class="bi bi-clipboard2-pulse-fill"></i>
                                        </a>
                                        <button class="btn btn-icon btn-glass text-primary hover-scale" title="Editar Información" onclick="editPatient(<?php echo $patient['id_paciente']; ?>)">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($patients)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">
                                        <i class="bi bi-people fs-1 d-block mb-3 opacity-25"></i>
                                        No se encontraron pacientes registrados.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Patient Modal -->
<div class="modal fade" id="newPatientModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="background: rgba(255, 255, 255, 0.98);">
            <div class="modal-header border-0 bg-primary bg-opacity-10">
                <h5 class="modal-title fw-bold text-primary-emphasis"><i class="bi bi-person-plus-fill me-2"></i>Registro de Paciente Nuevo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="newPatientForm" action="save_patient.php" method="POST">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-sm text-muted">Nombres</label>
                            <input type="text" class="form-control bg-light border-0" name="nombre" placeholder="Ej: Juan Antonio" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-sm text-muted">Apellidos</label>
                            <input type="text" class="form-control bg-light border-0" name="apellido" placeholder="Ej: Perez Sosa" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-sm text-muted">Fecha de Nacimiento</label>
                            <input type="date" class="form-control bg-light border-0" name="fecha_nacimiento" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-sm text-muted">Género</label>
                            <select class="form-select bg-light border-0" name="genero" required>
                                <option value="">Seleccionar...</option>
                                <option value="Masculino">Masculino</option>
                                <option value="Femenino">Femenino</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-sm text-muted">Dirección</label>
                            <input type="text" class="form-control bg-light border-0" name="direccion" placeholder="Ej: Barrio San Juan, Nentón">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-sm text-muted">Teléfono</label>
                            <input type="tel" class="form-control bg-light border-0" name="telefono" placeholder="Ej: 46232418">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-sm text-muted">Correo Electrónico</label>
                            <input type="email" class="form-control bg-light border-0" name="correo" placeholder="Ej: juan@gmail.com">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm">Guardar Registro</button>
                </div>
            </form>
        </div>
    </div>
</div>

<button class="mobile-nav-toggle d-md-none rounded-circle shadow-lg" id="sidebarToggle" style="border:none;">
    <i class="bi bi-list fs-4"></i>
</button>

<?php include_once '../../includes/footer.php'; ?>

<script src="../../assets/js/dashboard-reengineered.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Real-time search with visual feedback
    const searchInput = document.getElementById('searchInput');
    const tableBody = document.querySelector('#patientsTable tbody');
    
    searchInput.addEventListener('input', function() {
        const searchText = this.value.toLowerCase().trim();
        const rows = tableBody.querySelectorAll('tr.hover-row');
        let hasResults = false;
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(searchText)) {
                row.style.display = '';
                hasResults = true;
            } else {
                row.style.display = 'none';
            }
        });

        // Show "No results" message if needed
        const noResultsMsg = document.getElementById('noResultsMsg');
        if (!hasResults && searchText !== '') {
            if (!noResultsMsg) {
                const tr = document.createElement('tr');
                tr.id = 'noResultsMsg';
                tr.innerHTML = `<td colspan="4" class="text-center py-5 text-muted animate__animated animate__fadeIn">
                    <i class="bi bi-search fs-1 d-block mb-3 opacity-25"></i>
                    No se encontraron coincidencias para "${searchText}"
                </td>`;
                tableBody.appendChild(tr);
            } else {
                noResultsMsg.innerHTML = `<td colspan="4" class="text-center py-5 text-muted">
                    <i class="bi bi-search fs-1 d-block mb-3 opacity-25"></i>
                    No se encontraron coincidencias para "${searchText}"
                </td>`;
                noResultsMsg.style.display = '';
            }
        } else if (noResultsMsg) {
            noResultsMsg.style.display = 'none';
        }
    });
});

function editPatient(id) {
    // Implementation for quick edit modal can be added here if needed
    // For now, redirect to medical history which has edit options
    window.location.href = 'medical_history.php?id=' + id;
}
</script>