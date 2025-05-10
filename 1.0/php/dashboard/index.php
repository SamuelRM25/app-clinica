<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer la zona horaria correcta
date_default_timezone_set('America/Mexico_City'); // Ajusta esto a tu zona horaria local

verify_session();

try {
    // Initialize database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get today's appointments count
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as today_appointments FROM citas WHERE fecha_cita = ?");
    $stmt->execute([$today]);
    $today_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['today_appointments'] ?? 0;
    
    // Get patients count for current year only
    $current_year = date('Y');
    $first_day_of_year = $current_year . '-01-01';
    $last_day_of_year = $current_year . '-12-31';
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT CONCAT(nombre_pac, ' ', apellido_pac)) as total_patients FROM citas WHERE fecha_cita BETWEEN ? AND ?");
    $stmt->execute([$first_day_of_year, $last_day_of_year]);
    $total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total_patients'] ?? 0;
    
    // Get pending appointments count - all future appointments
    $stmt = $conn->prepare("SELECT COUNT(*) as pending_appointments FROM citas WHERE fecha_cita > ?");
    $stmt->execute([$today]);
    $pending_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['pending_appointments'] ?? 0;
    
    // Get this month's consultations - all appointments in current month
    $first_day_of_month = date('Y-m-01');
    $last_day_of_month = date('Y-m-t');
    $stmt = $conn->prepare("SELECT COUNT(*) as month_consultations FROM citas WHERE fecha_cita BETWEEN ? AND ?");
    $stmt->execute([$first_day_of_month, $last_day_of_month]);
    $month_consultations = $stmt->fetch(PDO::FETCH_ASSOC)['month_consultations'] ?? 0;
    
    // NUEVAS CONSULTAS
    
    // 1. Obtener las citas programadas para hoy con detalles
    $today = date('Y-m-d');
    $stmt = $conn->prepare("
        SELECT id_cita, nombre_pac, apellido_pac, hora_cita, telefono 
        FROM citas 
        WHERE fecha_cita = ?
        ORDER BY hora_cita
    ");
    $stmt->execute([$today]);
    $todays_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Para depuración - Verificar si hay citas en la base de datos
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM citas");
    $stmt->execute();
    $total_citas = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // 2. Obtener el total de medicamentos en inventario
    $stmt = $conn->prepare("
        SELECT SUM(cantidad_med) as total_medicamentos 
        FROM inventario
        WHERE cantidad_med > 0
    ");
    $stmt->execute();
    $total_medicamentos = $stmt->fetch(PDO::FETCH_ASSOC)['total_medicamentos'] ?? 0;
    
    // 3. Obtener medicamentos con riesgo de caducidad (próximo mes)
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
    
    // 4. Medicamentos con stock bajo (menos de 5 unidades)
    $stmt = $conn->prepare("
        SELECT id_inventario, nom_medicamento, cantidad_med 
        FROM inventario 
        WHERE cantidad_med > 0 AND cantidad_med < 5
        ORDER BY cantidad_med
    ");
    $stmt->execute();
    $medicamentos_stock_bajo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $page_title = "Dashboard - Clínica";
    include_once '../../includes/header.php';
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<div class="d-flex">
    <!-- Sidebar -->
    <div class="sidebar p-3">
        <div class="d-flex align-items-center mb-3 mb-md-0 me-md-auto">
            <span class="fs-4">Sistema de Gestión</span>
        </div>
        <hr>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="../dashboard/index.php" class="nav-link active bg-primary text-white">
                    <i class="bi bi-speedometer2 me-2"></i>
                    Dashboard
                </a>
            </li>
            <li>
                <a href="../appointments/index.php" class="nav-link text-dark">
                    <i class="bi bi-calendar me-2"></i>
                    Citas
                </a>
            </li>
            <li>
                <a href="../patients/index.php" class="nav-link text-dark">
                    <i class="bi bi-people me-2"></i>
                    Pacientes
                </a>
            </li>
            <li>
                <a href="../dispensary/index.php" class="nav-link text-dark">
                    <i class="bi bi-calendar-check me-2"></i>
                    Despacho
                </a>
            </li>
            <li>
                <a href="../inventory/index.php" class="nav-link text-dark">
                    <i class="bi bi-box-seam me-2"></i>
                    Inventario
                </a>
            </li>
            <li>
                <a href="../purchases/index.php" class="nav-link text-dark">
                    <i class="bi bi-cart-plus me-2"></i>
                    Compras
                </a>
            </li>
            <li>
                <a href="../sales/index.php" class="nav-link text-dark">
                    <i class="bi bi-shop me-2"></i>
                    Ventas
                </a>
            </li>
            <li>
                <a href="../billing/index.php" class="nav-link text-dark">
                    <i class="bi bi-cash-coin me-2"></i>
                    Cobros
                </a>
            </li>
        </ul>
        <hr>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle me-2"></i>
                <strong><?php echo htmlspecialchars($_SESSION['nombre']); ?></strong>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="dropdownUser1">
                <li><a class="dropdown-item" href="../auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión</a></li>
            </ul>
        </div>
    </div>

    <!-- Main content -->
    <div class="main-content flex-grow-1">
        <div class="container-fluid">
            <!-- Welcome Card -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Bienvenido/a,  <?php echo htmlspecialchars($_SESSION['nombre'] . ' ' . $_SESSION['apellido']); ?></h5>
                            <p class="card-text">
                                <strong>Clínica:</strong> <?php echo htmlspecialchars($_SESSION['clinica']); ?><br>
                                <strong>Especialidad:</strong> <?php echo htmlspecialchars($_SESSION['especialidad']); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Stats -->
            <div class="row g-4">
                <div class="col-md-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h6 class="text-muted mb-2">Citas Hoy</h6>
                                    <h4 class="mb-0"><?php echo $today_appointments; ?></h4>
                                </div>
                                <div class="avatar bg-light-primary">
                                    <i class="bi bi-calendar-check fs-3 text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h6 class="text-muted mb-2">Pacientes Totales</h6>
                                    <h4 class="mb-0"><?php echo $total_patients; ?></h4>
                                </div>
                                <div class="avatar bg-light-success">
                                    <i class="bi bi-people fs-3 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h6 class="text-muted mb-2">Citas Pendientes</h6>
                                    <h4 class="mb-0"><?php echo $pending_appointments; ?></h4>
                                </div>
                                <div class="avatar bg-light-warning">
                                    <i class="bi bi-clock-history fs-3 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h6 class="text-muted mb-2">Consultas este mes</h6>
                                    <h4 class="mb-0"><?php echo $month_consultations; ?></h4>
                                </div>
                                <div class="avatar bg-light-info">
                                    <i class="bi bi-graph-up fs-3 text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- NUEVAS SECCIONES -->
            
            <!-- Sección de Inventario -->
            <div class="row mt-4">
                <div class="col-md-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h6 class="text-muted mb-2">Total Medicamentos</h6>
                                    <h4 class="mb-0"><?php echo $total_medicamentos; ?></h4>
                                </div>
                                <div class="avatar bg-light-success">
                                    <i class="bi bi-capsule fs-3 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Citas de Hoy -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-calendar-day me-2"></i>Citas Programadas para Hoy</h5>
                            <a href="../appointments/index.php" class="btn btn-sm btn-light">
                                <i class="bi bi-plus-circle me-1"></i>Nueva Cita
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (count($todays_appointments) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Paciente</th>
                                                <th>Hora</th>
                                                <th>Teléfono</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($todays_appointments as $cita): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($cita['nombre_pac'] . ' ' . $cita['apellido_pac']); ?></td>
                                                    <td><?php echo htmlspecialchars($cita['hora_cita']); ?></td>
                                                    <td><?php echo htmlspecialchars($cita['telefono'] ?? 'No disponible'); ?></td>
                                                    <td>
                                                        <a href="../appointments/edit_appointment.php?id=<?php echo $cita['id_cita']; ?>" class="btn btn-sm btn-primary">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <a href="#" class="btn btn-sm btn-info ms-1 check-patient" 
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
                                <div class="alert alert-info mb-0">
                                    <i class="bi bi-info-circle me-2"></i>No hay citas programadas para hoy (<?php echo date('d/m/Y', strtotime($today)); ?>).
                                    <p class="small mt-2 mb-0">Total de citas en la base de datos: <?php echo $total_citas; ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Medicamentos por Caducar -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Medicamentos con Riesgo de Caducidad</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($medicamentos_por_caducar) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Medicamento</th>
                                                <th>Fecha de Vencimiento</th>
                                                <th>Cantidad</th>
                                                </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($medicamentos_por_caducar as $med): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($med['nom_medicamento']); ?></td>
                                                    <td>
                                                        <?php 
                                                        $fecha_venc = new DateTime($med['fecha_vencimiento']);
                                                        $hoy = new DateTime();
                                                        $diff = $hoy->diff($fecha_venc);
                                                        $clase = ($fecha_venc < $hoy) ? 'text-danger' : 'text-warning';
                                                        echo '<span class="'.$clase.'">' . $fecha_venc->format('d/m/Y');
                                                        if ($fecha_venc < $hoy) {
                                                            echo ' (Caducado)';
                                                        } else {
                                                            echo ' (' . $diff->days . ' días)';
                                                        }
                                                        echo '</span>';
                                                        ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($med['cantidad_med']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success mb-0">
                                    <i class="bi bi-check-circle me-2"></i>No hay medicamentos con riesgo de caducidad próxima.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Medicamentos con Stock Bajo -->
            <div class="row mt-4 mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="bi bi-exclamation-circle me-2"></i>Medicamentos con Stock Bajo</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($medicamentos_stock_bajo) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Medicamento</th>
                                                <th>Cantidad</th>
                                                </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($medicamentos_stock_bajo as $med): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($med['nom_medicamento']); ?></td>
                                                    <td>
                                                        <span class="badge bg-danger"><?php echo htmlspecialchars($med['cantidad_med']); ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success mb-0">
                                    <i class="bi bi-check-circle me-2"></i>No hay medicamentos con stock bajo.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>

            <!-- Modal para Nuevo Paciente -->
            <div class="modal fade" id="newPatientModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Nuevo Paciente</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form id="newPatientForm" action="../patients/save_patient.php" method="POST">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Nombre</label>
                                    <input type="text" class="form-control" name="nombre" id="modal-nombre" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Apellido</label>
                                    <input type="text" class="form-control" name="apellido" id="modal-apellido" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Fecha de Nacimiento</label>
                                    <input type="date" class="form-control" name="fecha_nacimiento" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Género</label>
                                    <select class="form-select" name="genero" required>
                                        <option value="">Seleccionar...</option>
                                        <option value="Masculino">Masculino</option>
                                        <option value="Femenino">Femenino</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Dirección</label>
                                    <input type="text" class="form-control" name="direccion">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Teléfono</label>
                                    <input type="tel" class="form-control" name="telefono">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Correo</label>
                                    <input type="email" class="form-control" name="correo">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Guardar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Manejar el clic en el botón de historial clínico
    const checkPatientButtons = document.querySelectorAll('.check-patient');
    
    checkPatientButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const nombre = this.getAttribute('data-nombre');
            const apellido = this.getAttribute('data-apellido');
            
            // Verificar si el paciente existe
            fetch(`../patients/check_patient.php?nombre=${encodeURIComponent(nombre)}&apellido=${encodeURIComponent(apellido)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        if (data.exists) {
                            // Si el paciente existe, redirigir a su historial médico
                            window.location.href = `../patients/medical_history.php?id=${data.id}`;
                        } else {
                            // Si el paciente no existe, abrir el modal para nuevo paciente
                            // y prellenar los campos de nombre y apellido
                            document.getElementById('modal-nombre').value = nombre;
                            document.getElementById('modal-apellido').value = apellido;
                            
                            // Abrir el modal
                            const modal = new bootstrap.Modal(document.getElementById('newPatientModal'));
                            modal.show();
                        }
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al verificar el paciente');
                });
        });
    });
});
</script>