<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

date_default_timezone_set('America/Guatemala');
verify_session();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id_cita = $_GET['id'];
$database = new Database();
$conn = $database->getConnection();

// Fetch doctors for the dropdown
$stmtDocs = $conn->prepare("SELECT idUsuario, nombre, apellido FROM usuarios WHERE tipoUsuario = 'doc' ORDER BY nombre, apellido");
$stmtDocs->execute();
$doctors = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($_POST['nombre_pac']) || empty($_POST['apellido_pac']) || empty($_POST['fecha_cita']) || empty($_POST['hora_cita']) || empty($_POST['id_doctor'])) {
            throw new Exception("Todos los campos marcados como obligatorios son necesarios");
        }
        
        $sql = "UPDATE citas SET 
                nombre_pac = :nombre_pac,
                apellido_pac = :apellido_pac,
                fecha_cita = :fecha_cita,
                hora_cita = :hora_cita,
                telefono = :telefono,
                id_doctor = :id_doctor
                WHERE id_cita = :id_cita";
                
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':nombre_pac', $_POST['nombre_pac']);
        $stmt->bindParam(':apellido_pac', $_POST['apellido_pac']);
        $stmt->bindParam(':fecha_cita', $_POST['fecha_cita']);
        $stmt->bindParam(':hora_cita', $_POST['hora_cita']);
        $stmt->bindParam(':telefono', $_POST['telefono']);
        $stmt->bindParam(':id_doctor', $_POST['id_doctor']);
        $stmt->bindParam(':id_cita', $id_cita);
        
        if ($stmt->execute()) {
            $_SESSION['appointment_message'] = "Cita actualizada correctamente";
            $_SESSION['appointment_status'] = "success";
            header("Location: index.php");
            exit;
        } else {
            throw new Exception("Error al actualizar la cita");
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get appointment data
try {
    $stmt = $conn->prepare("SELECT * FROM citas WHERE id_cita = :id_cita");
    $stmt->bindParam(':id_cita', $id_cita);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        header("Location: index.php");
        exit;
    }
    
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

$page_title = "Editar Cita - Clínica";
include_once '../../includes/header.php';
?>

<!-- Inject Dashboard Custom Styles -->
<link rel="stylesheet" href="../../assets/css/dashboard-reengineered.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

<div class="dashboard-wrapper">
    <!-- Sidebar Reengineered -->
    <div class="sidebar-glass p-3 d-flex flex-column">
        <div class="brand-section">
            <div class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-decoration-none">
                <img src="../../assets/img/siloe.png" alt="Logo" style="height: 40px; margin-right: 15px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));">
            </div>
        </div>
        
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="../dashboard/index.php" class="nav-link">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="index.php" class="nav-link active">
                    <i class="bi bi-calendar"></i> Citas
                </a>
            </li>
            <li>
                <a href="../patients/index.php" class="nav-link">
                    <i class="bi bi-people"></i> Pacientes
                </a>
            </li>
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
            <div class="d-flex justify-content-between align-items-center mb-4 animate__animated animate__fadeInDown">
                <div class="d-flex align-items-center">
                    <a href="index.php" class="btn btn-outline-secondary rounded-pill me-3 shadow-sm bg-white">
                        <i class="bi bi-arrow-left"></i> Regresar
                    </a>
                    <h2 class="fw-bold text-dark mb-0">Editar Cita</h2>
                </div>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger card-glass mb-4 animate__animated animate__shakeX">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="row justify-content-center">
                <div class="col-lg-8 animate__animated animate__fadeInUp animate__delay-1s">
                    <div class="card-glass p-0">
                        <div class="card-header-glass border-0 bg-primary bg-opacity-10 py-3">
                            <h5 class="mb-0 text-primary fw-bold"><i class="bi bi-info-circle me-2"></i>Información de la Cita #<?php echo $appointment['num_cita']; ?></h5>
                        </div>
                        <div class="card-body p-4">
                            <form action="edit_appointment.php?id=<?php echo $id_cita; ?>" method="POST">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label for="nombre_pac" class="form-label text-sm text-muted">Nombre del Paciente</label>
                                        <input type="text" class="form-control bg-light border-0" id="nombre_pac" name="nombre_pac" value="<?php echo htmlspecialchars($appointment['nombre_pac']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="apellido_pac" class="form-label text-sm text-muted">Apellido del Paciente</label>
                                        <input type="text" class="form-control bg-light border-0" id="apellido_pac" name="apellido_pac" value="<?php echo htmlspecialchars($appointment['apellido_pac']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="fecha_cita" class="form-label text-sm text-muted">Fecha de Cita</label>
                                        <input type="date" class="form-control bg-light border-0" id="fecha_cita" name="fecha_cita" value="<?php echo htmlspecialchars($appointment['fecha_cita']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="hora_cita" class="form-label text-sm text-muted">Hora de Cita</label>
                                        <input type="time" class="form-control bg-light border-0" id="hora_cita" name="hora_cita" value="<?php echo htmlspecialchars($appointment['hora_cita']); ?>" required>
                                    </div>
                                    <div class="col-md-12">
                                        <label for="telefono" class="form-label text-sm text-muted">Teléfono de Contacto</label>
                                        <div class="input-group">
                                            <span class="input-group-text border-0 bg-light text-muted"><i class="bi bi-telephone"></i></span>
                                            <input type="tel" class="form-control bg-light border-0 border-start-0" id="telefono" name="telefono" value="<?php echo htmlspecialchars($appointment['telefono'] ?? ''); ?>" placeholder="Opcional">
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <label for="id_doctor" class="form-label text-sm text-muted">Médico Asignado</label>
                                        <div class="input-group">
                                            <span class="input-group-text border-0 bg-light text-muted"><i class="bi bi-person-badge"></i></span>
                                            <select class="form-select bg-light border-0 border-start-0" id="id_doctor" name="id_doctor" required>
                                                <option value="">Seleccionar Médico...</option>
                                                <?php foreach ($doctors as $doc): ?>
                                                    <option value="<?php echo $doc['idUsuario']; ?>" <?php echo $appointment['id_doctor'] == $doc['idUsuario'] ? 'selected' : ''; ?>>
                                                        Dr(a). <?php echo htmlspecialchars($doc['nombre'] . ' ' . $doc['apellido']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-12 mt-4">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <a href="index.php" class="btn btn-light rounded-pill px-4">Cancelar</a>
                                            <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm">
                                                <i class="bi bi-save me-2"></i>Guardar Cambios
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<button class="mobile-nav-toggle" id="sidebarToggle">
    <i class="bi bi-list fs-4"></i>
</button>

<script src="../../assets/js/dashboard-reengineered.js"></script>
<?php include_once '../../includes/footer.php'; ?>