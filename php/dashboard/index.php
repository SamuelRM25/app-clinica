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

    // Queries Logic (Kept exactly as original)
    $today = date('Y-m-d');
    $is_doc = $_SESSION['tipoUsuario'] === 'doc';
    $doc_id = $_SESSION['user_id'];
    $where_doc = $is_doc ? " AND id_doctor = ?" : "";
    $params_doc = $is_doc ? [$today, $doc_id] : [$today];

    $stmt = $conn->prepare("SELECT COUNT(*) as today_appointments FROM citas WHERE fecha_cita = ?" . $where_doc);
    $stmt->execute($params_doc);
    $today_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['today_appointments'] ?? 0;
    
    $current_year = date('Y');
    $first_day_of_year = $current_year . '-01-01';
    $last_day_of_year = $current_year . '-12-31';
    $params_total_patients = $is_doc ? [$first_day_of_year, $last_day_of_year, $doc_id] : [$first_day_of_year, $last_day_of_year];
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT CONCAT(nombre_pac, ' ', apellido_pac)) as total_patients FROM citas WHERE (fecha_cita BETWEEN ? AND ?)" . $where_doc);
    $stmt->execute($params_total_patients);
    $total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total_patients'] ?? 0;
    
    $params_pending = $is_doc ? [$today, $doc_id] : [$today];
    $stmt = $conn->prepare("SELECT COUNT(*) as pending_appointments FROM citas WHERE fecha_cita > ?" . $where_doc);
    $stmt->execute($params_pending);
    $pending_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['pending_appointments'] ?? 0;
    
    $first_day_of_month = date('Y-m-01');
    $last_day_of_month = date('Y-m-t');
    $params_month = $is_doc ? [$first_day_of_month, $last_day_of_month, $doc_id] : [$first_day_of_month, $last_day_of_month];
    $stmt = $conn->prepare("SELECT COUNT(*) as month_consultations FROM citas WHERE (fecha_cita BETWEEN ? AND ?)" . $where_doc);
    $stmt->execute($params_month);
    $month_consultations = $stmt->fetch(PDO::FETCH_ASSOC)['month_consultations'] ?? 0;
    
    $today = date('Y-m-d');
    $stmt = $conn->prepare("
        SELECT id_cita, nombre_pac, apellido_pac, hora_cita, telefono 
        FROM citas 
        WHERE fecha_cita = ?" . $where_doc . "
        ORDER BY hora_cita
    ");
    $stmt->execute($params_doc);
    $todays_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM citas");
    $stmt->execute();
    $total_citas = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $stmt = $conn->prepare("
        SELECT SUM(cantidad_med) as total_medicamentos 
        FROM inventario
        WHERE cantidad_med > 0
    ");
    $stmt->execute();
    $total_medicamentos = $stmt->fetch(PDO::FETCH_ASSOC)['total_medicamentos'] ?? 0;
    
    $un_mes_despues = date('Y-m-d', strtotime('+1 month'));
    $hoy = date('Y-m-d');
    $stmt = $conn->prepare("
        SELECT id_inventario, nom_medicamento, fecha_vencimiento, cantidad_med 
        FROM inventario 
        WHERE fecha_vencimiento BETWEEN ? AND ? AND cantidad_med > 0
        ORDER BY fecha_vencimiento ASC
    ");
    $stmt->execute([$hoy, $un_mes_despues]);
    $medicamentos_por_caducar = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $conn->prepare("
        SELECT id_inventario, nom_medicamento, cantidad_med 
        FROM inventario 
        WHERE cantidad_med > 0 AND cantidad_med < 5
        ORDER BY cantidad_med
    ");
    $stmt->execute();
    $medicamentos_stock_bajo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check for pending purchases (received items needing confirmation)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM inventario WHERE estado = 'Pendiente'");
    $stmt->execute();
    $pending_inventory_count = $stmt->fetchColumn();
    
    $page_title = "Dashboard - Clínica";
    include_once '../../includes/header.php';
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!-- Inject Dashboard Custom Styles -->
<link rel="stylesheet" href="../../assets/css/dashboard-reengineered.css">


<div class="dashboard-wrapper">
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
                <img src="../../assets/img/siloe.png" alt="Logo" style="height: 40px; margin-right: 15px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));">
            </div>
            <button class="btn btn-sm btn-light text-primary rounded-circle shadow-sm d-none d-md-flex align-items-center justify-content-center" id="sidebarCloseBtn" style="width: 32px; height: 32px;">
                <i class="bi bi-chevron-left"></i>
            </button>
        </div>
        
        <ul class="nav nav-pills flex-column mb-auto">
            <?php $rol = $_SESSION['tipoUsuario'] ?? $_SESSION['rol'] ?? ''; ?>
            <?php if ($rol === 'admin' || $rol === 'doc' || $rol === 'user'): ?>
            <li class="nav-item"><a href="../dashboard/index.php" class="nav-link active"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
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
            <!-- Welcome Card -->
            <div class="row mb-4">
                <div class="col-12">
                    <?php if (isset($pending_inventory_count) && $pending_inventory_count > 0): ?>
                    <div class="alert alert-warning alert-dismissible fade show mb-4 shadow-sm" role="alert" style="border-radius: 12px; border: none; background: rgba(255, 193, 7, 0.15); color: #856404;">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-box-seam fs-4 me-3"></i>
                            <div>
                                <h5 class="alert-heading fw-bold mb-1">¡Nuevas compras registradas!</h5>
                                <p class="mb-0">Hay <strong><?php echo $pending_inventory_count; ?></strong> productos pendientes de recibir en inventario. <a href="../inventory/index.php" class="alert-link">Ir a Inventario</a> para procesarlos.</p>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <div class="card-glass p-4" style="background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(255,255,255,0.7));">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-1" id="greeting-text">Bienvenido/a, <?php echo htmlspecialchars($_SESSION['nombre']); ?></h2>
                                <p class="text-muted mb-0">
                                    <i class="bi bi-building me-2"></i><?php echo htmlspecialchars($_SESSION['clinica']); ?>
                                    <span class="mx-2">|</span>
                                    <i class="bi bi-person-badge me-2"></i><?php echo htmlspecialchars($_SESSION['especialidad']); ?>
                                </p>
                            </div>
                            <div class="d-none d-md-block opacity-50">
                                <i class="bi bi-heart-pulse fs-1 text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="row g-4 mb-4">
                <div class="col-md-6 col-xl-3">
                    <div class="card-glass h-100 p-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted text-uppercase text-xs fw-bold mb-2">Citas Hoy</h6>
                                <h2 class="mb-0 fw-bold text-dark"><?php echo $today_appointments; ?></h2>
                            </div>
                            <div class="stats-card-icon bg-gradient-primary-soft">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card-glass h-100 p-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted text-uppercase text-xs fw-bold mb-2">Pacientes</h6>
                                <h2 class="mb-0 fw-bold text-dark"><?php echo $total_patients; ?></h2>
                            </div>
                            <div class="stats-card-icon bg-gradient-success-soft">
                                <i class="bi bi-people"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card-glass h-100 p-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted text-uppercase text-xs fw-bold mb-2">Pendientes</h6>
                                <h2 class="mb-0 fw-bold text-dark"><?php echo $pending_appointments; ?></h2>
                            </div>
                            <div class="stats-card-icon bg-gradient-warning-soft">
                                <i class="bi bi-clock-history"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card-glass h-100 p-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted text-uppercase text-xs fw-bold mb-2">Consultas Mes</h6>
                                <h2 class="mb-0 fw-bold text-dark"><?php echo $month_consultations; ?></h2>
                            </div>
                            <div class="stats-card-icon bg-gradient-info-soft">
                                <i class="bi bi-graph-up"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Second Row of Stats (Inventory) -->
             <div class="row g-4 mb-4">
                <div class="col-md-6 col-xl-3">
                    <div class="card-glass h-100 p-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted text-uppercase text-xs fw-bold mb-2">Total Medicamentos</h6>
                                <h2 class="mb-0 fw-bold text-dark"><?php echo $total_medicamentos; ?></h2>
                            </div>
                            <div class="stats-card-icon bg-gradient-success-soft">
                                <i class="bi bi-capsule"></i>
                            </div>
                        </div>
                    </div>
                </div>
             </div>
            
            <!-- Main Tables Section -->
            <div class="row">
                <!-- Todays Appointments -->
                <div class="col-12 mb-4">
                    <div class="card-glass">
                        <div class="card-header-glass">
                            <h5 class="mb-0 text-primary"><i class="bi bi-calendar-day me-2"></i>Citas Programadas para Hoy</h5>
                            <a href="../appointments/index.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                <i class="bi bi-plus-circle me-1"></i>Nueva Cita
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($todays_appointments) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-glass table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Paciente</th>
                                                <th>Hora</th>
                                                <th>Teléfono</th>
                                                <th class="text-end">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($todays_appointments as $cita): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-sm bg-light-primary rounded-circle me-2 d-flex align-items-center justify-content-center" style="width:30px;height:30px;font-size:12px;">
                                                                <?php echo strtoupper(substr($cita['nombre_pac'], 0, 1) . substr($cita['apellido_pac'], 0, 1)); ?>
                                                            </div>
                                                            <span class="fw-medium"><?php echo htmlspecialchars($cita['nombre_pac'] . ' ' . $cita['apellido_pac']); ?></span>
                                                        </div>
                                                    </td>
                                                    <td><span class="badge bg-light text-dark border"><i class="bi bi-clock me-1"></i><?php echo htmlspecialchars($cita['hora_cita']); ?></span></td>
                                                    <td><?php echo htmlspecialchars($cita['telefono'] ?? 'No disponible'); ?></td>
                                                    <td class="text-end">
                                                        <a href="../appointments/edit_appointment.php?id=<?php echo $cita['id_cita']; ?>" class="btn btn-sm btn-light text-primary" title="Editar">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <a href="#" class="btn btn-sm btn-light text-info ms-1 check-patient" 
                                                           title="Historial"
                                                           data-nombre="<?php echo htmlspecialchars($cita['nombre_pac']); ?>" 
                                                           data-apellido="<?php echo htmlspecialchars($cita['apellido_pac']); ?>">
                                                            <i class="bi bi-file-medical"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="p-5 text-center text-muted">
                                    <i class="bi bi-calendar-x fs-1 mb-3 d-block opacity-50"></i>
                                    <p class="mb-0">No hay citas programadas para hoy.</p>
                                    <small>Total en base de datos: <?php echo $total_citas; ?></small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Expiring Meds -->
                <div class="col-md-6 mb-4">
                    <div class="card-glass h-100">
                        <div class="card-header-glass">
                            <h5 class="mb-0 text-warning"><i class="bi bi-exclamation-triangle me-2"></i>Riesgo de Caducidad</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($medicamentos_por_caducar) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-glass table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Medicamento</th>
                                                <th>Vencimiento</th>
                                                <th>Cant.</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($medicamentos_por_caducar as $med): ?>
                                                <?php 
                                                    $fecha_venc = new DateTime($med['fecha_vencimiento']);
                                                    $hoy = new DateTime();
                                                    $diff = $hoy->diff($fecha_venc);
                                                    $is_expired = $fecha_venc < $hoy;
                                                ?>
                                                <tr>
                                                    <td class="fw-medium"><?php echo htmlspecialchars($med['nom_medicamento']); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $is_expired ? 'bg-danger' : 'bg-warning text-dark'; ?>">
                                                            <?php echo $fecha_venc->format('d/m/Y'); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($med['cantidad_med']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="p-4 text-center text-success">
                                    <i class="bi bi-check-circle fs-2 mb-2 d-block"></i>
                                    <p class="mb-0">Inventario saludable</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Low Stock -->
                <div class="col-md-6 mb-4">
                    <div class="card-glass h-100">
                        <div class="card-header-glass">
                            <h5 class="mb-0 text-danger"><i class="bi bi-arrow-down-circle me-2"></i>Stock Bajo</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($medicamentos_stock_bajo) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-glass table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Medicamento</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($medicamentos_stock_bajo as $med): ?>
                                                <tr>
                                                    <td class="fw-medium"><?php echo htmlspecialchars($med['nom_medicamento']); ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="progress flex-grow-1" style="height: 6px;">
                                                                <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo min($med['cantidad_med'] * 10, 100); ?>%"></div>
                                                            </div>
                                                            <span class="ms-2 badge bg-light text-danger border border-danger"><?php echo htmlspecialchars($med['cantidad_med']); ?></span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="p-4 text-center text-success">
                                    <i class="bi bi-check-circle fs-2 mb-2 d-block"></i>
                                    <p class="mb-0">Noc hay alertas de stock</p>
                                </div>
                            <?php endif; ?>
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

<?php include_once '../../includes/footer.php'; ?>

<!-- Modal for New Patient -->
<div class="modal fade" id="newPatientModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-primary">Nuevo Paciente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="newPatientForm" action="../patients/save_patient.php" method="POST">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label text-sm text-muted">Nombre</label>
                            <input type="text" class="form-control bg-light border-0" name="nombre" id="modal-nombre" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-sm text-muted">Apellido</label>
                            <input type="text" class="form-control bg-light border-0" name="apellido" id="modal-apellido" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-sm text-muted">Fecha Nacimiento</label>
                            <input type="date" class="form-control bg-light border-0" name="fecha_nacimiento" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-sm text-muted">Género</label>
                            <select class="form-select bg-light border-0" name="genero" required>
                                <option value="">Seleccionar...</option>
                                <option value="Masculino">Masculino</option>
                                <option value="Femenino">Femenino</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-sm text-muted">Teléfono</label>
                            <input type="tel" class="form-control bg-light border-0" name="telefono">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-sm text-muted">Dirección</label>
                            <textarea class="form-control bg-light border-0" name="direccion" rows="2" placeholder="Dirección completa..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 rounded-pill">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../../assets/js/dashboard-reengineered.js"></script>
<script>
// Keep original script for check-patient logic
document.addEventListener('DOMContentLoaded', function() {
    const checkPatientButtons = document.querySelectorAll('.check-patient');
    
    checkPatientButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const nombre = this.getAttribute('data-nombre');
            const apellido = this.getAttribute('data-apellido');
            
            fetch(`../patients/check_patient.php?nombre=${encodeURIComponent(nombre)}&apellido=${encodeURIComponent(apellido)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        if (data.exists) {
                            window.location.href = `../patients/medical_history.php?id=${data.id}`;
                        } else {
                            document.getElementById('modal-nombre').value = nombre;
                            document.getElementById('modal-apellido').value = apellido;
                            const modal = new bootstrap.Modal(document.getElementById('newPatientModal'));
                            modal.show();
                        }
                    } else {
                        console.error('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        });
    });
});

<?php if (($_SESSION['rol'] ?? '') === 'admin'): ?>
// Jornada Notification Logic
document.addEventListener('DOMContentLoaded', function() {
    const lastSummaryDate = localStorage.getItem('lastJornadaSummary');
    const today = new Date().toISOString().split('T')[0];
    const currentHour = new Date().getHours();

    // Show notification if it's after 8 AM and we haven't shown it today
    if (currentHour >= 8 && lastSummaryDate !== today) {
        const yesterday = new Date();
        yesterday.setDate(yesterday.getDate() - 1);
        const jornadaDate = yesterday.toISOString().split('T')[0];
        const formattedDate = yesterday.toLocaleDateString('es-GT', { day: 'numeric', month: 'long' });

        Swal.fire({
            title: `¡Jornada del ${formattedDate} Finalizada!`,
            text: "¿Desea generar el reporte diario ahora?",
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-file-earmark-pdf me-2"></i>Generar Reporte',
            cancelButtonText: 'Más tarde',
            confirmButtonColor: '#3b82f6',
            reverseButtons: true,
            backdrop: `rgba(0,0,123,0.1)`
        }).then((result) => {
            localStorage.setItem('lastJornadaSummary', today);
            if (result.isConfirmed) {
                window.open(`../reports/export_jornada.php?date=${jornadaDate}`, '_blank');
            }
        });
    }
});
<?php endif; ?>
</script>
