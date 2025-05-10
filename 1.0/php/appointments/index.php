<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer la zona horaria correcta
date_default_timezone_set('America/Mexico_City'); // Ajusta esto a tu zona horaria local

verify_session();

try {
    $database = new Database();
    if (!($conn = $database->getConnection())) {
        throw new Exception("Failed to establish database connection");
    }
    
    $page_title = "Calendario de Citas";
    include_once '../../includes/header.php';
} catch (Exception $e) {
    die("Error: No se pudo conectar a la base de datos");
}
?>

<div class="d-flex">
    <?php include_once '../../includes/sidebar.php'; ?>

    <div class="main-content flex-grow-1">
        <div class="container-fluid">
            <!-- Add this after your container-fluid div starts -->
            <?php if (isset($_SESSION['appointment_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['appointment_status'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['appointment_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php 
                // Clear the message after displaying it
                unset($_SESSION['appointment_message']);
                unset($_SESSION['appointment_status']);
                ?>
            <?php endif; ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="d-flex align-items-center">
                    <a href="../dashboard/index.php" class="btn btn-outline-secondary me-3">
                        <i class="bi bi-arrow-left"></i> Regresar
                    </a>
                    <h2>Calendario de Citas</h2>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newAppointmentModal">
                    <i class="bi bi-plus-circle me-2"></i> Nueva Cita
                </button>
            </div>

            <!-- New Appointment Modal -->
            <div class="modal fade" id="newAppointmentModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Nueva Cita</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form id="appointmentForm" action="save_appointment.php" method="POST">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label for="nombre_pac" class="form-label">Nombre del Paciente</label>
                                    <input type="text" class="form-control" id="nombre_pac" name="nombre_pac" required>
                                </div>
                                <div class="mb-3">
                                    <label for="apellido_pac" class="form-label">Apellido del Paciente</label>
                                    <input type="text" class="form-control" id="apellido_pac" name="apellido_pac" required>
                                </div>
                                <div class="mb-3">
                                    <label for="date" class="form-label">Fecha</label>
                                    <input type="date" class="form-control" id="date" name="fecha_cita" required>
                                </div>
                                <div class="mb-3">
                                    <label for="time" class="form-label">Hora</label>
                                    <input type="time" class="form-control" id="time" name="hora_cita" required>
                                </div>
                                <div class="mb-3">
                                    <label for="telefono" class="form-label">Teléfono</label>
                                    <input type="tel" class="form-control" id="telefono" name="telefono" placeholder="Ingrese número de teléfono">
                                    <small class="text-muted">Opcional: Para contactar al paciente</small>
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

            <div class="card">
                <div class="card-body">
                    <div id="calendar"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>

<!-- Add SweetAlert2 library -->
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet'>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        locale: 'es',
        events: 'get_appointments.php',
        eventTimeFormat: {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        },
        eventClick: function(info) {
            // Get additional appointment details
            fetch('get_appointment_details.php?id=' + info.event.id)
                .then(response => response.json())
                .then(data => {
                    let phoneInfo = data.telefono ? `<p><strong>Teléfono:</strong> ${data.telefono}</p>` : '';
                    
                    Swal.fire({
                        title: 'Detalles de la Cita',
                        html: `
                            <p><strong>Paciente:</strong> ${info.event.title}</p>
                            <p><strong>Fecha:</strong> ${info.event.start.toLocaleDateString()}</p>
                            <p><strong>Hora:</strong> ${info.event.start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</p>
                            ${phoneInfo}
                        `,
                        icon: 'info',
                        showCancelButton: true,
                        showDenyButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        denyButtonColor: '#198754',
                        confirmButtonText: 'Eliminar',
                        cancelButtonText: 'Cerrar',
                        denyButtonText: 'Editar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Send delete request
                            fetch('delete_appointment.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    id: info.event.id
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === 'success') {
                                    Swal.fire(
                                        'Eliminada!',
                                        'La cita ha sido eliminada.',
                                        'success'
                                    );
                                    info.event.remove(); // Remove from calendar
                                } else {
                                    Swal.fire(
                                        'Error!',
                                        'No se pudo eliminar la cita.',
                                        'error'
                                    );
                                }
                            });
                        } else if (result.isDenied) {
                            // Redirect to edit page
                            window.location.href = 'edit_appointment.php?id=' + info.event.id;
                        }
                    });
                });
        }
    });
    calendar.render();
});
</script>