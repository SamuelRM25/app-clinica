<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer la zona horaria correcta
date_default_timezone_set('America/Guatemala');

verify_session();

try {
    // Initialize database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get all patients for the dropdown
    $stmt = $conn->prepare("SELECT id_paciente, CONCAT(nombre, ' ', apellido) as nombre_completo FROM pacientes ORDER BY nombre");
    $stmt->execute();
    $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all billings with pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 25;
    $offset = ($page - 1) * $limit;
    
    // Get total count for pagination
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM cobros");
    $stmt->execute();
    $total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $limit);
    
    // Get billings data with patient name
    $stmt = $conn->prepare("
        SELECT c.*, CONCAT(p.nombre, ' ', p.apellido) as nombre_paciente 
        FROM cobros c
        JOIN pacientes p ON c.paciente_cobro = p.id_paciente
        ORDER BY c.fecha_consulta DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $cobros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $page_title = "Cobros - Clínica";
    include_once '../../includes/header.php';
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!-- Inject Dashboard Custom Styles -->
<link rel="stylesheet" href="../../assets/css/dashboard-reengineered.css">


<style>
    .billing-card {
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(12px);
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.4);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
        padding: 0.5rem;
    }
    
    .table-reengineered {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 2px;
        table-layout: fixed;
    }

    .table-reengineered thead th {
        border: none;
        color: #6b7280;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.65rem;
        letter-spacing: 0.02em;
        padding: 0.4rem 0.5rem;
    }

    .table-reengineered thead th:nth-child(1) { width: 40%; }
    .table-reengineered thead th:nth-child(2) { width: 20%; }
    .table-reengineered thead th:nth-child(3) { width: 25%; }
    .table-reengineered thead th:nth-child(4) { width: 15%; }

    .table-reengineered tbody tr {
        background: rgba(255, 255, 255, 0.4);
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        border-radius: 12px;
    }

    .table-reengineered tbody tr:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }

    .table-reengineered tbody td {
        padding: 0.35rem 0.5rem;
        border: none;
        vertical-align: middle;
        font-size: 0.8rem;
    }

    .table-reengineered tbody td:first-child { 
        border-radius: 12px 0 0 12px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .table-reengineered tbody td:last-child { border-radius: 0 12px 12px 0; }

    .btn-action {
        width: 26px;
        height: 26px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        transition: all 0.2s;
        font-size: 0.85rem;
    }

    .pagination-sm .page-link {
        padding: 0.2rem 0.4rem;
        font-size: 0.7rem;
        min-width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .pagination-sm .page-item {
        margin: 0 1px;
    }
</style>

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
            <li><a href="../patients/index.php" class="nav-link"><i class="bi bi-people"></i> Pacientes</a></li>
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
            <li><a href="../billing/index.php" class="nav-link active"><i class="bi bi-cash-coin"></i> Cobros</a></li>
            <?php endif; ?>
        </ul>
        
        <div class="mt-auto">
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle p-2 rounded hover-effect" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false" style="color: var(--text-color);">
                    <div class="avatar-circle me-2 bg-primary text-white d-flex align-items-center justify-content-center rounded-circle" style="width: 32px; height: 32px;">
                        <?php echo strtoupper(substr($_SESSION['nombre'] ?? 'U', 0, 1)); ?>
                    </div>
                    <strong><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></strong>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="dropdownUser1">
                    <li><a class="dropdown-item" href="../auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Main Content Reengineered -->
    <div class="main-content-glass">
        <div class="container-fluid p-1 p-md-2">
            <!-- Header Section -->
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <h2 class="fw-bold text-dark mb-0 fs-5">Gestión de Cobros</h2>
                    <p class="text-muted text-xs mb-0">Administración de recaudación y recibos</p>
                </div>
                <button type="button" class="btn btn-primary rounded-pill px-3 py-1 text-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#newBillingModal">
                    <i class="bi bi-plus-circle me-1"></i>Nuevo Cobro
                </button>
            </div>
            
            <!-- Billing Content -->
            <div class="billing-card shadow-sm">
                <div class="table-responsive">
                    <table class="table-reengineered">
                        <thead>
                            <tr>
                                <th>Paciente</th>
                                <th>Monto</th>
                                <th>Fecha</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($cobros) > 0): ?>
                                <?php foreach ($cobros as $cobro): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-light text-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 24px; height: 24px; font-size: 0.75rem;">
                                                    <i class="bi bi-person"></i>
                                                </div>
                                                <span class="fw-semibold text-sm"><?php echo htmlspecialchars($cobro['nombre_paciente']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-dark">Q<?php echo number_format($cobro['cantidad_consulta'], 2); ?></span>
                                        </td>
                                        <td>
                                            <span class="text-muted"><i class="bi bi-calendar3 me-2"></i><?php echo date('d/m/Y', strtotime($cobro['fecha_consulta'])); ?></span>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-2">
                                                <button type="button" class="btn btn-light btn-action text-info view-details" data-bs-toggle="modal" data-bs-target="#viewDetailsModal" data-id="<?php echo $cobro['in_cobro']; ?>" title="Ver Detalles">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <a href="print_receipt.php?id=<?php echo $cobro['in_cobro']; ?>" target="_blank" class="btn btn-light btn-action text-primary" title="Imprimir Recibo">
                                                    <i class="bi bi-printer"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">No hay cobros registrados</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination pagination-sm pagination-rounded justify-content-center mb-0 mt-2">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>"><i class="bi bi-chevron-left"></i></a>
                        </li>
                        <?php 
                        // Smart pagination - show max 5 page numbers
                        $range = 2; // Show 2 pages on each side of current page
                        $start = max(1, $page - $range);
                        $end = min($total_pages, $page + $range);
                        
                        // Show first page
                        if ($start > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                            if ($start > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }
                        
                        // Show page range
                        for ($i = $start; $i <= $end; $i++): 
                        ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php 
                        endfor;
                        
                        // Show last page
                        if ($end < $total_pages) {
                            if ($end < $total_pages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '">' . $total_pages . '</a></li>';
                        }
                        ?>
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>"><i class="bi bi-chevron-right"></i></a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- New Billing Modal -->
<div class="modal fade" id="newBillingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold text-dark pt-3 px-3">Nuevo Cobro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="newBillingForm">
                    <div class="mb-4">
                        <label for="paciente_search" class="form-label text-xs fw-bold text-muted uppercase-label">Paciente</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control bg-light border-0" id="paciente_search" placeholder="Buscar paciente..." autocomplete="off">
                        </div>
                        <input type="hidden" id="paciente" name="paciente" required>
                        <div id="pacienteResults" class="list-group mt-2 shadow-sm rounded-3 border-0 d-none" style="position: absolute; z-index: 1000; width: calc(100% - 3rem);"></div>
                        <div id="selectedPatient" class="mt-3 p-3 bg-primary bg-opacity-10 border-0 rounded-3 d-none">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-primary" id="patientName"></span>
                                <button type="button" class="btn btn-sm btn-link text-danger p-0 text-decoration-none" id="clearPatient">
                                    <i class="bi bi-x-circle-fill"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="cantidad" class="form-label text-xs fw-bold text-muted uppercase-label">Cantidad a Cobrar (Q)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0">Q</span>
                            <input type="number" class="form-control bg-light border-0" id="cantidad" name="cantidad" min="0.01" step="0.01" placeholder="0.00" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="fecha_consulta" class="form-label text-xs fw-bold text-muted uppercase-label">Fecha de Consulta</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="bi bi-calendar-event"></i></span>
                            <input type="date" class="form-control bg-light border-0" id="fecha_consulta" name="fecha_consulta" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" id="saveBillingBtn">Guardar Cobro</button>
            </div>
        </div>
    </div>
</div>

<!-- View Details Modal -->
<div class="modal fade" id="viewDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold text-dark pt-3 px-3">Detalles del Cobro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="p-4 bg-light rounded-4 mb-4">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <p class="text-xs fw-bold text-muted mb-1 uppercase-label">Paciente</p>
                            <h5 class="fw-bold text-dark mb-0" id="modal-paciente"></h5>
                        </div>
                        <div class="col-4 text-end">
                            <p class="text-xs fw-bold text-muted mb-1 uppercase-label">Monto</p>
                            <h4 class="fw-extrabold text-primary mb-0">Q<span id="modal-cantidad"></span></h4>
                        </div>
                    </div>
                </div>
                
                <div class="ps-3 border-start border-primary border-4">
                    <p class="text-xs fw-bold text-muted mb-1 uppercase-label">Fecha de Generación</p>
                    <p class="fw-bold text-dark mb-0" id="modal-fecha"></p>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cerrar</button>
                <a href="#" class="btn btn-primary rounded-pill px-4 shadow-sm" id="modal-print-btn" target="_blank">
                    <i class="bi bi-printer me-2"></i>Imprimir Recibo
                </a>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
<script src="../../assets/js/dashboard-reengineered.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Patient search functionality
    const pacienteSearch = document.getElementById('paciente_search');
    const pacienteInput = document.getElementById('paciente');
    const pacienteResults = document.getElementById('pacienteResults');
    const selectedPatient = document.getElementById('selectedPatient');
    const patientName = document.getElementById('patientName');
    const clearPatient = document.getElementById('clearPatient');
    
    // Search for patients as user types
    pacienteSearch.addEventListener('input', function() {
        const searchTerm = this.value.trim();
        
        if (searchTerm.length < 2) {
            pacienteResults.innerHTML = '';
            pacienteResults.classList.add('d-none');
            return;
        }
        
        fetch(`search_patients.php?term=${encodeURIComponent(searchTerm)}`)
            .then(response => response.json())
            .then(data => {
                pacienteResults.innerHTML = '';
                
                if (data.length === 0) {
                    pacienteResults.innerHTML = '<div class="list-group-item border-0">No se encontraron pacientes</div>';
                } else {
                    data.forEach(patient => {
                        const item = document.createElement('a');
                        item.href = '#';
                        item.className = 'list-group-item list-group-item-action border-0 py-3';
                        item.innerHTML = `<i class="bi bi-person me-2"></i> ${patient.nombre_completo}`;
                        item.addEventListener('click', function(e) {
                            e.preventDefault();
                            selectPatient(patient.id_paciente, patient.nombre_completo);
                        });
                        pacienteResults.appendChild(item);
                    });
                }
                
                pacienteResults.classList.remove('d-none');
            })
            .catch(error => console.error('Error:', error));
    });
    
    // Select a patient from results
    function selectPatient(id, name) {
        pacienteInput.value = id;
        patientName.textContent = name;
        selectedPatient.classList.remove('d-none');
        pacienteSearch.value = '';
        pacienteResults.innerHTML = '';
        pacienteResults.classList.add('d-none');
    }
    
    // Clear selected patient
    clearPatient.addEventListener('click', function() {
        pacienteInput.value = '';
        patientName.textContent = '';
        selectedPatient.classList.add('d-none');
        pacienteSearch.focus();
    });
    
    // Close results when clicking outside
    document.addEventListener('click', function(e) {
        if (!pacienteSearch.contains(e.target) && !pacienteResults.contains(e.target)) {
            pacienteResults.classList.add('d-none');
        }
    });
    
    // Save new billing
    document.getElementById('saveBillingBtn').addEventListener('click', function() {
        const form = document.getElementById('newBillingForm');
        
        if (!form.checkValidity() || !pacienteInput.value) {
            if (!pacienteInput.value) alert('Por favor seleccione un paciente');
            form.reportValidity();
            return;
        }
        
        const data = {
            paciente: pacienteInput.value,
            cantidad: document.getElementById('cantidad').value,
            fecha_consulta: document.getElementById('fecha_consulta').value
        };
        
        fetch('save_billing.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Cobro guardado',
                    text: 'El cobro ha sido registrado exitosamente',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => window.location.reload());
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => console.error('Error:', error));
    });
    
    // View details modal
    document.querySelectorAll('.view-details').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            fetch(`get_billing_details.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        document.getElementById('modal-paciente').textContent = data.cobro.nombre_paciente;
                        document.getElementById('modal-cantidad').textContent = parseFloat(data.cobro.cantidad_consulta).toFixed(2);
                        document.getElementById('modal-fecha').textContent = data.cobro.fecha_formateada;
                        document.getElementById('modal-print-btn').href = `print_receipt.php?id=${id}`;
                    }
                })
                .catch(error => console.error('Error:', error));
        });
    });
});
</script>