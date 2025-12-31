<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Obtener la fecha actual en formato Y-m-d
    $today = date('Y-m-d');
    
    // Consultar las citas programadas para hoy
    $stmt = $conn->prepare("
        SELECT c.*, CONCAT(p.nombre, ' ', p.apellido) as nombre_paciente, p.nombre, p.apellido
        FROM citas c
        LEFT JOIN pacientes p ON c.paciente_cita = p.id_paciente
        WHERE DATE(c.fecha_cita) = ?
        ORDER BY c.hora_cita ASC
    ");
    $stmt->execute([$today]);
    $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $page_title = "Citas Programadas para Hoy";
    include_once '../../includes/header.php';
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<div class="d-flex">
    <?php include_once '../../includes/sidebar.php'; ?>

    <div class="main-content flex-grow-1">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="d-flex align-items-center">
                    <a href="../dashboard/index.php" class="btn btn-outline-secondary me-3">
                        <i class="bi bi-arrow-left"></i> Regresar
                    </a>
                    <h2>Citas Programadas para Hoy</h2>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newAppointmentModal">
                    <i class="bi bi-plus-circle me-2"></i>Nueva Cita
                </button>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Paciente</th>
                                    <th>Hora</th>
                                    <th>Teléfono</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($citas)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">No hay citas programadas para hoy</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($citas as $cita): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($cita['nombre_paciente']); ?></td>
                                        <td><?php echo htmlspecialchars(date('H:i:s', strtotime($cita['hora_cita']))); ?></td>
                                        <td><?php echo htmlspecialchars($cita['telefono'] ?? 'N/A'); ?></td>
                                        <td>
                                            <a href="#" class="btn btn-sm btn-success check-patient" 
                                               data-nombre="<?php echo htmlspecialchars($cita['nombre']); ?>" 
                                               data-apellido="<?php echo htmlspecialchars($cita['apellido']); ?>" 
                                               title="Historial Clínico">
                                                <i class="bi bi-clipboard2-pulse"></i>
                                            </a>
                                            <a href="../appointments/edit_appointment.php?id=<?php echo $cita['id_cita']; ?>" class="btn btn-sm btn-info" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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

<?php include_once '../../includes/footer.php'; ?>

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