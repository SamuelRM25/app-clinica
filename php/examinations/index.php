<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();
$page_title = "Registro de Exámenes";
include_once '../../includes/header.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener solo pacientes para el selector
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
    /* Specific overrides for this page to fix search visibility and look */
    .choices__inner {
        background-color: rgba(255, 255, 255, 0.5) !important;
        border: 1px solid rgba(255, 255, 255, 0.4) !important;
        border-radius: 10px !important;
        padding: 0.5rem 1rem !important;
        min-height: 45px !important;
    }
    .choices__list--dropdown {
        background-color: rgba(255, 255, 255, 0.95) !important;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.4) !important;
        border-radius: 10px !important;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
    }
    .exam-row {
        transition: all 0.3s ease;
    }
    .exam-row:hover {
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
            <li><a href="../minor_procedures/index.php" class="nav-link"><i class="bi bi-bandaid"></i> Proc. Menores</a></li>
            <li><a href="../examinations/index.php" class="nav-link active"><i class="bi bi-file-earmark-medical"></i> Exámenes</a></li>
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
                    <h2 class="fw-bold text-dark mb-1">Exámenes Clínicos</h2>
                    <p class="text-muted mb-0">Registre nuevos exámenes realizados</p>
                </div>
                <div>
                     <a href="historial_examenes.php" class="btn btn-info text-white rounded-pill px-3 shadow-sm me-2">
                        <i class="bi bi-clock-history me-2"></i> Ver Historial
                    </a>
                </div>
            </div>

            <!-- Alerts -->
            <?php if (isset($_GET['status'])): ?>
                <div>
                    <?php if ($_GET['status'] == 'success'): ?>
                        <div class="alert alert-success border-0 card-glass d-flex align-items-center shadow-sm mb-4">
                            <i class="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
                            <div>
                                <h6 class="fw-bold mb-0">¡Registro Exitoso!</h6>
                                <p class="mb-0 small"><?php echo htmlspecialchars($_GET['message']); ?></p>
                            </div>
                            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                        </div>
                    <?php elseif ($_GET['status'] == 'error'): ?>
                        <div class="alert alert-danger border-0 card-glass d-flex align-items-center shadow-sm mb-4">
                            <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-danger"></i>
                             <div>
                                <h6 class="fw-bold mb-0">Error en el Registro</h6>
                                <p class="mb-0 small"><?php echo htmlspecialchars($_GET['message']); ?></p>
                            </div>
                            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card-glass shadow-lg">
                        <div class="card-header bg-transparent border-bottom border-light p-4">
                            <h5 class="fw-bold text-primary mb-0"><i class="bi bi-pencil-square me-2"></i>Nuevo Registro de Examen</h5>
                        </div>
                        <div class="card-body p-4">
                            <form action="save_exam.php" method="POST" id="examForm">
                                
                                <!-- Step 1: Patient -->
                                <div class="mb-5">
                                    <h6 class="fw-bold text-uppercase text-xs text-muted mb-3 ls-1">
                                        <span class="badge bg-primary rounded-pill me-2">1</span> Seleccionar Paciente
                                    </h6>
                                    <div class="bg-white bg-opacity-50 p-3 rounded-3 border border-light shadow-sm">
                                        <select id="id_paciente" name="id_paciente" class="form-select" required>
                                            <option value="">Buscar paciente...</option>
                                            <?php foreach ($patients as $patient): ?>
                                                <option value="<?php echo $patient['id_paciente']; ?>" data-nombre="<?php echo htmlspecialchars($patient['nombre_completo']); ?>">
                                                    <?php echo htmlspecialchars($patient['nombre_completo']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="hidden" name="nombre_paciente" id="nombre_paciente">
                                    </div>
                                </div>

                                <!-- Step 2: Exams -->
                                <div class="mb-5">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="fw-bold text-uppercase text-xs text-muted mb-0 ls-1">
                                            <span class="badge bg-primary rounded-pill me-2">2</span> Elegir Exámenes
                                        </h6>
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" id="btnAddExam">
                                            <i class="bi bi-plus-lg me-1"></i> Agregar Otro
                                        </button>
                                    </div>
                                    
                                    <div id="examsContainer" class="bg-white bg-opacity-50 p-4 rounded-3 border border-light shadow-sm">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="form-check custom-checkbox mb-2">
                                                    <input class="form-check-input" type="checkbox" name="examenes[]" value="Electrocardiograma (ECG)" id="ex1">
                                                    <label class="form-check-label w-100" for="ex1">Electrocardiograma (ECG)</label>
                                                </div>
                                                <div class="form-check custom-checkbox mb-2">
                                                    <input class="form-check-input" type="checkbox" name="examenes[]" value="Ultrasonido" id="ex2">
                                                    <label class="form-check-label w-100" for="ex2">Ultrasonido</label>
                                                </div>
                                                <div class="form-check custom-checkbox mb-2">
                                                    <input class="form-check-input" type="checkbox" name="examenes[]" value="Radiografía" id="ex3">
                                                    <label class="form-check-label w-100" for="ex3">Radiografía</label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check custom-checkbox mb-2">
                                                    <input class="form-check-input" type="checkbox" name="examenes[]" value="Examen general de orina" id="ex4">
                                                    <label class="form-check-label w-100" for="ex4">Examen general de orina</label>
                                                </div>
                                                <div class="form-check custom-checkbox mb-2">
                                                    <input class="form-check-input" type="checkbox" name="examenes[]" value="Hematología completa" id="ex5">
                                                    <label class="form-check-label w-100" for="ex5">Hematología completa</label>
                                                </div>
                                                <div class="form-check custom-checkbox mb-2">
                                                    <input class="form-check-input" type="checkbox" name="examenes[]" value="Prueba de Papanicolaou" id="ex6">
                                                    <label class="form-check-label w-100" for="ex6">Prueba de Papanicolaou</label>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div id="dynamicExams" class="mt-3 pt-3 border-top border-light">
                                            <!-- Dynamic rows will appear here -->
                                        </div>
                                    </div>
                                    <small class="text-muted ms-2 mt-2 d-block"><i class="bi bi-info-circle me-1"></i>Puede seleccionar varios o agregar personalizados.</small>
                                </div>

                                <!-- Step 3: Cost -->
                                <div class="mb-5">
                                    <h6 class="fw-bold text-uppercase text-xs text-muted mb-3 ls-1">
                                        <span class="badge bg-primary rounded-pill me-2">3</span> Finalizar y Cobrar
                                    </h6>
                                    <div class="bg-white bg-opacity-50 p-3 rounded-3 border border-light shadow-sm d-flex align-items-center" style="max-width: 300px;">
                                        <span class="fs-4 text-success fw-bold me-2">Q</span>
                                        <input type="number" class="form-control fs-4 fw-bold border-0 bg-transparent text-dark" id="cobro" name="cobro" step="0.01" min="0" required placeholder="0.00">
                                    </div>
                                </div>

                                <hr class="border-light opacity-50 my-4">
                                
                                <button type="submit" class="btn btn-primary rounded-pill px-5 py-3 fw-bold shadow-lg w-100 w-md-auto scale-up">
                                    <i class="bi bi-save me-2"></i>Guardar Registro
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

<datalist id="examsList">
    <option value="Perfil lipídico">
    <option value="Glucosa en ayunas">
    <option value="Prueba de embarazo">
    <option value="Antígeno prostático">
    <option value="TSH (Tiroides)">
    <option value="Creatinina">
    <option value="Ácido úrico">
</datalist>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script src="../../assets/js/dashboard-reengineered.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Choices.js with search fixed
    const choices = new Choices('#id_paciente', {
        searchEnabled: true,
        itemSelectText: '',
        removeItemButton: true,
        placeholder: true,
        placeholderValue: 'Buscar paciente...',
        noResultsText: 'No se encontraron resultados',
        shouldSort: false, // Maintain database order
    });

    // Handle Patient Selection
    const pacienteSelect = document.getElementById('id_paciente');
    const nombrePacienteInput = document.getElementById('nombre_paciente');
    
    pacienteSelect.addEventListener('addItem', function(event) {
        nombrePacienteInput.value = event.detail.label;
    });
    
    pacienteSelect.addEventListener('removeItem', function() {
        nombrePacienteInput.value = '';
    });

    // Dynamic Exams Logic
    const dynamicContainer = document.getElementById('dynamicExams');
    const btnAdd = document.getElementById('btnAddExam');

    btnAdd.addEventListener('click', function() {
        const div = document.createElement('div');
        div.className = 'exam-row mb-2 animate__animated animate__fadeIn';
        
        div.innerHTML = `
            <div class="input-group">
                <span class="input-group-text border-0 bg-white"><i class="bi bi-file-earmark-medical"></i></span>
                <input class="form-control border-0 bg-light rounded-start" list="examsList" name="examenes[]" placeholder="Especificar otro examen..." required style="background: rgba(255,255,255,0.8) !important;">
                <button type="button" class="btn btn-light text-danger border-0 remove-exam-row" title="Eliminar">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `;
        
        dynamicContainer.appendChild(div);
        div.querySelector('input').focus();
    });

    // Event delegation for remove buttons
    dynamicContainer.addEventListener('click', function(e) {
        if (e.target.closest('.remove-exam-row')) {
            e.target.closest('.exam-row').remove();
        }
    });

});
</script>

<?php include_once '../../includes/footer.php'; ?>