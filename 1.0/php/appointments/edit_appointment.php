<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

// Check if appointment ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['appointment_message'] = "ID de cita no proporcionado";
    $_SESSION['appointment_status'] = "error";
    header("Location: index.php");
    exit;
}

$id_cita = $_GET['id'];
$database = new Database();
$conn = $database->getConnection();

// If form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        if (empty($_POST['nombre_pac']) || empty($_POST['apellido_pac']) || empty($_POST['fecha_cita']) || empty($_POST['hora_cita'])) {
            throw new Exception("Los campos de nombre, apellido, fecha y hora son obligatorios");
        }
        
        // Update appointment
        $sql = "UPDATE citas SET 
                nombre_pac = :nombre_pac,
                apellido_pac = :apellido_pac,
                fecha_cita = :fecha_cita,
                hora_cita = :hora_cita,
                telefono = :telefono
                WHERE id_cita = :id_cita";
                
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':nombre_pac', $_POST['nombre_pac']);
        $stmt->bindParam(':apellido_pac', $_POST['apellido_pac']);
        $stmt->bindParam(':fecha_cita', $_POST['fecha_cita']);
        $stmt->bindParam(':hora_cita', $_POST['hora_cita']);
        $stmt->bindParam(':telefono', $_POST['telefono']);
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
        $_SESSION['appointment_message'] = "Error: " . $e->getMessage();
        $_SESSION['appointment_status'] = "error";
    }
}

// Get appointment data
try {
    $stmt = $conn->prepare("SELECT * FROM citas WHERE id_cita = :id_cita");
    $stmt->bindParam(':id_cita', $id_cita);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        $_SESSION['appointment_message'] = "Cita no encontrada";
        $_SESSION['appointment_status'] = "error";
        header("Location: index.php");
        exit;
    }
    
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $_SESSION['appointment_message'] = "Error: " . $e->getMessage();
    $_SESSION['appointment_status'] = "error";
    header("Location: index.php");
    exit;
}

$page_title = "Editar Cita - Clínica";
include_once '../../includes/header.php';
?>

<div class="d-flex">
    <?php include_once '../../includes/sidebar.php'; ?>

    <div class="main-content flex-grow-1">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="d-flex align-items-center">
                    <a href="index.php" class="btn btn-outline-secondary me-3">
                        <i class="bi bi-arrow-left"></i> Regresar
                    </a>
                    <h2>Editar Cita</h2>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-8 mx-auto">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Información de la Cita</h5>
                        </div>
                        <div class="card-body">
                            <form action="edit_appointment.php?id=<?php echo $id_cita; ?>" method="POST">
                                <div class="mb-3">
                                    <label for="nombre_pac" class="form-label">Nombre del Paciente</label>
                                    <input type="text" class="form-control" id="nombre_pac" name="nombre_pac" value="<?php echo htmlspecialchars($appointment['nombre_pac']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="apellido_pac" class="form-label">Apellido del Paciente</label>
                                    <input type="text" class="form-control" id="apellido_pac" name="apellido_pac" value="<?php echo htmlspecialchars($appointment['apellido_pac']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="fecha_cita" class="form-label">Fecha de Cita</label>
                                    <input type="date" class="form-control" id="fecha_cita" name="fecha_cita" value="<?php echo htmlspecialchars($appointment['fecha_cita']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="hora_cita" class="form-label">Hora de Cita</label>
                                    <input type="time" class="form-control" id="hora_cita" name="hora_cita" value="<?php echo htmlspecialchars($appointment['hora_cita']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="telefono" class="form-label">Teléfono de Contacto</label>
                                    <input type="tel" class="form-control" id="telefono" name="telefono" value="<?php echo htmlspecialchars($appointment['telefono'] ?? ''); ?>" placeholder="Ingrese número de teléfono">
                                    <small class="text-muted">Opcional: Para contactar al paciente</small>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>