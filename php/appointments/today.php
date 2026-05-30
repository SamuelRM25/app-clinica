<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

$id_hospital = (int) ($_SESSION['id_hospital'] ?? 0);

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
        WHERE DATE(c.fecha_cita) = ? AND c.id_hospital = ?
        ORDER BY c.hora_cita ASC
    ");
    $stmt->execute([$today, $id_hospital]);
    $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $page_title = "Citas Programadas para Hoy";
    include_once '../../includes/header.php';
} catch (Exception $e) {
    error_log('Error en appointments/today.php: ' . $e->getMessage());
    die("Error: " . 'Error del servidor.');
}
?>

<div class="marble-effect"></div>
<div class="dashboard-container">
    <!-- Header Superior -->
    <header class="dashboard-header">
        <div class="header-content">
            <!-- logo -->
            <div class="brand-container">
                <img src="../../assets/img/cmhs.png" alt="Centro Médico Herrera Saenz" class="brand-logo" width="40" height="40">
            </div>

            <!-- Controles -->
            <div class="header-controls">
                <!-- Control de tema -->
                <div class="theme-toggle">
                    <button id="themeSwitch" class="theme-btn" aria-label="Cambiar tema claro/oscuro">
                        <i class="bi bi-sun theme-icon sun-icon"></i>
                        <i class="bi bi-moon theme-icon moon-icon"></i>
                    </button>
                </div>

                <!-- Información del usuario -->
                <div class="header-user">
                    <div class="header-avatar">
                        <?php echo isset($_SESSION['nombre']) ? htmlspecialchars(strtoupper(substr($_SESSION['nombre'], 0, 1))) : 'U'; ?>
                    </div>
                    <div class="header-details">
                        <span
                            class="header-name"><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></span>
                        <span class="header-role">Agenda</span>
                    </div>
                </div>

                <!-- Back Button -->
                <a href="../dashboard/index.php" class="action-btn secondary">
                    <i class="bi bi-arrow-left"></i>
                    Dashboard
                </a>

                <!-- Botón de cerrar sesión -->
                <a href="../auth/logout.php" class="logout-btn">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Salir</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Contenido Principal -->
    <main class="main-content">
        <div class="container-fluid">
            <!-- Sección de Citas -->
            <section class="calendar-section animate-in">
                <div class="section-header d-flex justify-content-between align-items-center mb-4">
                    <h3 class="section-title">
                        <i class="bi bi-calendar-check section-title-icon"></i>
                        Citas Programadas para Hoy
                    </h3>
                    <div class="d-flex gap-2">
                        <a href="index.php" class="action-btn secondary">
                            <i class="bi bi-calendar3"></i>
                            Calendario Completo
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
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
                                    <td colspan="4" class="text-center py-4 text-muted">No hay citas programadas para hoy
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($citas as $cita): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($cita['nombre_paciente']); ?></td>
                                        <td>
                                            <span
                                                class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                                <i class="bi bi-clock me-1"></i>
                                                <?php echo htmlspecialchars(date('H:i', strtotime($cita['hora_cita']))); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($cita['telefono']) && $cita['telefono'] !== 'N/A'): ?>
                                                <a href="tel:<?php echo htmlspecialchars($cita['telefono']); ?>"
                                                    class="text-decoration-none text-muted">
                                                    <i class="bi bi-telephone me-1"></i>
                                                    <?php echo htmlspecialchars($cita['telefono']); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="#" class="action-btn sm success check-patient"
                                                    data-nombre="<?php echo htmlspecialchars($cita['nombre']); ?>"
                                                    data-apellido="<?php echo htmlspecialchars($cita['apellido']); ?>"
                                                    title="Historial Clínico">
                                                    <i class="bi bi-clipboard2-pulse"></i>
                                                </a>
                                                <a href="index.php" class="action-btn sm info" title="Ver en Calendario">
                                                    <i class="bi bi-calendar-event"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
</div>

<!-- Modal para Nuevo Paciente -->
<div class="modal fade" id="newPatientModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person-plus me-2"></i>Nuevo Paciente
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="newPatientForm" action="../patients/save_patient.php" method="POST">
                <?php echo csrf_field(); ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" class="form-control" name="nombre" id="modal-nombre" required
                            placeholder="Ej. Juan">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Apellido</label>
                        <input type="text" class="form-control" name="apellido" id="modal-apellido" required
                            placeholder="Ej. Pérez">
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
                        <input type="text" class="form-control" name="direccion" placeholder="Ej. Ciudad de Guatemala">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Teléfono</label>
                        <input type="tel" class="form-control" name="telefono" placeholder="Ej. 5555-5555">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correo</label>
                        <input type="email" class="form-control" name="correo" placeholder="Ej. juan@gmail.com">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="action-btn secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="action-btn">Guardar Paciente</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Manejar el clic en el botón de historial clínico
        const checkPatientButtons = document.querySelectorAll('.check-patient');

        checkPatientButtons.forEach(button => {
            button.addEventListener('click', function (e) {
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