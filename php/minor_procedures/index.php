<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();
$page_title = "Procedimientos Menores";
// Include header but we will override styles
include_once '../../includes/header.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt_patients = $conn->prepare("SELECT id_paciente, CONCAT(nombre, ' ', apellido) as nombre_completo FROM pacientes ORDER BY nombre_completo ASC");
    $stmt_patients->execute();
    $patients = $stmt_patients->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $patients = [];
    $error_message = "Error de conexión: " . $e->getMessage();
}
?>

<!-- Styles -->
<link rel="stylesheet" href="../../assets/css/dashboard-reengineered.css">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />

<style>
    /* Specific overrides for this page */
    .choices__inner {
        background-color: rgba(255, 255, 255, 0.5) !important;
        border: 1px solid rgba(255, 255, 255, 0.4) !important;
        border-radius: 10px !important;
        padding: 0.5rem 1rem !important;
    }
    .procedure-row {
        transition: all 0.3s ease;
    }
    .procedure-row:hover {
        background: rgba(255,255,255,0.3);
        border-radius: 8px;
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
            <li><a href="../minor_procedures/index.php" class="nav-link active"><i class="bi bi-bandaid"></i> Proc. Menores</a></li>
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

    <!-- Main Content -->
    <div class="main-content-glass">
        <div class="container-fluid">
            
            <!-- Header -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4">
                <div class="mb-3 mb-md-0">
                    <h2 class="fw-bold text-dark mb-1">Procedimientos Menores</h2>
                    <p class="text-muted mb-0">Registre y gestione los procedimientos realizados</p>
                </div>
                <div>
                     <a href="historial_procedimientos.php" class="btn btn-info text-white rounded-pill px-3 shadow-sm me-2">
                        <i class="bi bi-clock-history me-2"></i> Historial
                    </a>
                </div>
            </div>

            <!-- Messages -->
             <div class="row">
                <div class="col-12">
                    <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
                        <div class="alert alert-success alert-dismissible fade show card-glass border-0" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <strong>¡Éxito!</strong> <?php echo htmlspecialchars($_GET['message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php elseif (isset($_GET['status']) && $_GET['status'] == 'error'): ?>
                        <div class="alert alert-danger alert-dismissible fade show card-glass border-0" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>¡Error!</strong> <?php echo htmlspecialchars($_GET['message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Form Card -->
            <div class="row">
                <div class="col-12">
                     <div class="card-glass">
                        <div class="card-header-glass">
                            <h5 class="mb-0 text-primary"><i class="bi bi-pencil-square me-2"></i>Nuevo Registro de Procedimiento</h5>
                        </div>
                        <div class="card-body p-4">
                            <form action="save_procedure.php" method="POST" id="procedureForm">
                                
                                <!-- Step 1: Patient -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-muted mb-2">1. Seleccionar Paciente</label>
                                    <select id="id_paciente" name="id_paciente" required class="form-select border-0 bg-light">
                                        <option value="">Buscar paciente...</option>
                                        <?php foreach ($patients as $patient): ?>
                                            <option value="<?php echo $patient['id_paciente']; ?>" data-nombre="<?php echo htmlspecialchars($patient['nombre_completo']); ?>">
                                                <?php echo htmlspecialchars($patient['nombre_completo']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="nombre_paciente" id="nombre_paciente">
                                </div>

                                <!-- Step 2: Procedures -->
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label fw-bold text-muted mb-0">2. Procedimientos Realizados</label>
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" id="btnAddProcedure">
                                            <i class="bi bi-plus-lg me-1"></i> Agregar Otro
                                        </button>
                                    </div>
                                    
                                    <div id="proceduresContainer" class="p-3 bg-light rounded-3" style="background: rgba(248, 249, 252, 0.5) !important;">
                                        <!-- Initial Row -->
                                        <div class="procedure-row mb-2">
                                            <div class="input-group">
                                                <span class="input-group-text border-0 bg-white"><i class="bi bi-bandaid"></i></span>
                                                <input class="form-control border-0 bg-white" list="proceduresList" name="procedimientos[]" placeholder="Escriba o seleccione un procedimiento..." required>
                                                <!-- Delete button hidden for first row usually, but we can allow it if we validate at least one -->
                                            </div>
                                        </div>
                                    </div>
                                    <small class="text-muted ms-2"><i class="bi bi-info-circle me-1"></i>Puede agregar múltiples procedimientos.</small>
                                </div>

                                <!-- Datalist for Auto-complete -->
                                <datalist id="proceduresList">
                                    <option value="Sutura de herida">
                                    <option value="Curación de herida">
                                    <option value="Extracción de uña encarnada">
                                    <option value="Drenaje de absceso">
                                    <option value="Retiro de puntos">
                                    <option value="Infiltración">
                                    <option value="Nebulización">
                                    <option value="Lavado de oídos">
                                    <option value="Cauterización">
                                </datalist>

                                <!-- Step 3: Total Cost -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-muted mb-2">3. Costo Total</label>
                                    <div class="input-group input-group-lg">
                                        <span class="input-group-text border-0 bg-primary text-white">Q</span>
                                        <input type="number" class="form-control border-0 bg-light" id="cobro" name="cobro" step="0.01" min="0" required placeholder="0.00" style="font-weight: bold; color: var(--dash-primary);">
                                    </div>
                                </div>

                                <hr class="border-light my-4">
                                
                                <button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill shadow-sm hover-effect">
                                    <i class="bi bi-check-lg me-2"></i>Guardar y Registrar
                                </button>
                            </form>
                        </div>
                     </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Mobile Toggle Button -->
<button class="mobile-nav-toggle" id="sidebarToggle">
    <i class="bi bi-list fs-4"></i>
</button>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script src="../../assets/js/dashboard-reengineered.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Choices
    const choices = new Choices('#id_paciente', {
        searchEnabled: true,
        itemSelectText: '',
        removeItemButton: true,
        placeholder: true,
        placeholderValue: 'Buscar paciente...',
        noResultsText: 'No se encontraron resultados',
    });

    // Handle patient selection to fill hidden name field
    const pacienteSelect = document.getElementById('id_paciente');
    const nombrePacienteInput = document.getElementById('nombre_paciente');
    
    // Choices hides original select, listen to its custom event 'addItem'
    pacienteSelect.addEventListener('addItem', function(event) {
        // Accessing the label from the event detail or finding it in options
        // For simple select, choices puts the value in the select. 
        // We need to find the option with that value to get the data-attribute.
        // However, choices.js modifies the DOM. A safer way with PHP generated options:
        // Parse the initial options array or map manually.
        // Actually, we can just grab the label from the choices instance or look up in the original options if they persist.
        const selectedVal = event.detail.value;
        const displayLabel = event.detail.label;
        nombrePacienteInput.value = displayLabel; 
    });

    // Dynamic Procedures Logic
    const container = document.getElementById('proceduresContainer');
    const btnAdd = document.getElementById('btnAddProcedure');

    btnAdd.addEventListener('click', function() {
        // Create new row
        const div = document.createElement('div');
        div.className = 'procedure-row mb-2 animate__animated animate__fadeIn';
        
        div.innerHTML = `
            <div class="input-group">
                <span class="input-group-text border-0 bg-white"><i class="bi bi-bandaid"></i></span>
                <input class="form-control border-0 bg-white" list="proceduresList" name="procedimientos[]" placeholder="Otro procedimiento..." required>
                <button type="button" class="btn btn-light text-danger border-0 remove-row" title="Eliminar">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `;
        
        container.appendChild(div);
        
        // Focus new input
        div.querySelector('input').focus();
    });

    // Event delegation for remove buttons
    container.addEventListener('click', function(e) {
        if (e.target.closest('.remove-row')) {
            const row = e.target.closest('.procedure-row');
            // Prevent removing the last row if we want to enforce at least one (optional)
            // if (container.querySelectorAll('.procedure-row').length > 1) {
                row.remove();
            // }
        }
    });
});
</script>

<?php include_once '../../includes/footer.php'; ?>