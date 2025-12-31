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
    if (!$conn) {
        throw new Exception("Failed to establish database connection");
    }
    
    // Fetch doctors for the dropdown
    $stmtDocs = $conn->prepare("SELECT idUsuario, nombre, apellido FROM usuarios WHERE tipoUsuario = 'doc' ORDER BY nombre, apellido");
    $stmtDocs->execute();
    $doctors = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);
    
    $page_title = "Calendario de Citas";
    include_once '../../includes/header.php';
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!-- Inject Dashboard & Custom Styles -->
<link rel="stylesheet" href="../../assets/css/dashboard-reengineered.css">

<link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet'>

<style>
    /* Context Menu Glass Style */
    .context-menu {
        display: none;
        position: absolute;
        z-index: 10000;
        min-width: 180px;
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(12px) saturate(180%);
        -webkit-backdrop-filter: blur(12px) saturate(180%);
        border: 1px solid rgba(255, 255, 255, 0.4);
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        padding: 8px 0;
        animate-duration: 0.2s;
    }

    .context-menu-item {
        padding: 8px 16px;
        display: flex;
        align-items: center;
        color: #4a4a4a;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 0.9rem;
    }

    .context-menu-item:hover {
        background: rgba(58, 86, 183, 0.1);
        color: #3a56b7;
    }

    .context-menu-item i {
        margin-right: 12px;
        font-size: 1.1rem;
    }

    .context-menu-item.text-danger:hover {
        background: rgba(255, 107, 107, 0.1);
        color: #ff6b6b !important;
    }

    /* Style for events to indicate context menu is available */
    .fc-event {
        cursor: context-menu !important;
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
            <li><a href="../appointments/index.php" class="nav-link active"><i class="bi bi-calendar"></i> Citas</a></li>
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
            
            <?php if (isset($_SESSION['appointment_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['appointment_status'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show card-glass border-0" role="alert">
                    <i class="bi <?php echo $_SESSION['appointment_status'] === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?> me-2"></i>
                    <?php echo $_SESSION['appointment_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php 
                unset($_SESSION['appointment_message']);
                unset($_SESSION['appointment_status']);
                ?>
            <?php endif; ?>

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4">
                <div class="mb-3 mb-md-0">
                    <h2 class="fw-bold text-dark mb-1">Calendario de Citas</h2>
                    <p class="text-muted mb-0">Gestione la agenda de pacientes</p>
                </div>
                <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#newAppointmentModal">
                    <i class="bi bi-plus-lg me-2"></i> Nueva Cita
                </button>
            </div>

            <div>
                <div id="calendar" class="fc-theme-standard"></div>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Toggle Button -->
<button class="mobile-nav-toggle" id="sidebarToggle">
    <i class="bi bi-list fs-4"></i>
</button>


<!-- New Appointment Modal (Glass Style) -->
<div class="modal fade" id="newAppointmentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-primary">Agendar Nueva Cita</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="appointmentForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nombre_pac" class="form-label text-sm text-muted">Nombre</label>
                            <input type="text" class="form-control bg-light border-0" id="nombre_pac" name="nombre_pac" required>
                        </div>
                        <div class="col-md-6">
                            <label for="apellido_pac" class="form-label text-sm text-muted">Apellido</label>
                            <input type="text" class="form-control bg-light border-0" id="apellido_pac" name="apellido_pac" required>
                        </div>
                        <div class="col-md-6">
                            <label for="date" class="form-label text-sm text-muted">Fecha</label>
                            <input type="date" class="form-control bg-light border-0" id="date" name="fecha_cita" required>
                        </div>
                        <div class="col-md-6">
                            <label for="time" class="form-label text-sm text-muted">Hora</label>
                            <input type="time" class="form-control bg-light border-0" id="time" name="hora_cita" required>
                        </div>
                        <div class="col-12">
                            <label for="telefono" class="form-label text-sm text-muted">Teléfono</label>
                            <div class="input-group">
                                <span class="input-group-text border-0 bg-light text-muted"><i class="bi bi-telephone"></i></span>
                                <input type="tel" class="form-control bg-light border-0 border-start-0" id="telefono" name="telefono" placeholder="Opcional">
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="id_doctor" class="form-label text-sm text-muted">Asignar a Médico</label>
                            <div class="input-group">
                                <span class="input-group-text border-0 bg-light text-muted"><i class="bi bi-person-badge"></i></span>
                                <select class="form-select bg-light border-0 border-start-0" id="id_doctor" name="id_doctor" required>
                                    <option value="">Seleccionar Médico...</option>
                                    <?php foreach ($doctors as $doc): ?>
                                        <option value="<?php echo $doc['idUsuario']; ?>">
                                            Dr(a). <?php echo htmlspecialchars($doc['nombre'] . ' ' . $doc['apellido']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Guardar Cita</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Appointment Modal (Glass Style) -->
<div class="modal fade" id="editAppointmentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-primary">Editar Cita</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editAppointmentForm">
                <input type="hidden" name="id_cita" id="edit_id_cita">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edit_nombre_pac" class="form-label text-sm text-muted">Nombre</label>
                            <input type="text" class="form-control bg-light border-0" id="edit_nombre_pac" name="nombre_pac" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_apellido_pac" class="form-label text-sm text-muted">Apellido</label>
                            <input type="text" class="form-control bg-light border-0" id="edit_apellido_pac" name="apellido_pac" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_date" class="form-label text-sm text-muted">Fecha</label>
                            <input type="date" class="form-control bg-light border-0" id="edit_date" name="fecha_cita" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_time" class="form-label text-sm text-muted">Hora</label>
                            <input type="time" class="form-control bg-light border-0" id="edit_time" name="hora_cita" required>
                        </div>
                        <div class="col-12">
                            <label for="edit_telefono" class="form-label text-sm text-muted">Teléfono</label>
                            <div class="input-group">
                                <span class="input-group-text border-0 bg-light text-muted"><i class="bi bi-telephone"></i></span>
                                <input type="tel" class="form-control bg-light border-0 border-start-0" id="edit_telefono" name="telefono" placeholder="Opcional">
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="edit_id_doctor" class="form-label text-sm text-muted">Asignar a Médico</label>
                            <div class="input-group">
                                <span class="input-group-text border-0 bg-light text-muted"><i class="bi bi-person-badge"></i></span>
                                <select class="form-select bg-light border-0 border-start-0" id="edit_id_doctor" name="id_doctor" required>
                                    <option value="">Seleccionar Médico...</option>
                                    <?php foreach ($doctors as $doc): ?>
                                        <option value="<?php echo $doc['idUsuario']; ?>">
                                            Dr(a). <?php echo htmlspecialchars($doc['nombre'] . ' ' . $doc['apellido']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Actualizar Cita</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Quick Patient Registration Modal -->
<div class="modal fade" id="quickPatientModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="background: rgba(255, 255, 255, 0.98);">
            <div class="modal-header border-0 bg-warning bg-opacity-10">
                <h5 class="modal-title fw-bold text-warning-emphasis"><i class="bi bi-person-plus-fill me-2"></i>Paciente Nuevo Detectado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="quickPatientForm">
                <div class="modal-body">
                    <div class="alert alert-warning border-0 d-flex align-items-center mb-3">
                        <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                        <small>El paciente no existe. Por favor complete su registro.</small>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-sm text-muted">Nombre</label>
                            <input type="text" class="form-control" name="nombre" id="qp_nombre" readonly required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-sm text-muted">Apellido</label>
                            <input type="text" class="form-control" name="apellido" id="qp_apellido" readonly required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-sm text-muted">Fecha Nacimiento</label>
                            <input type="date" class="form-control" name="fecha_nacimiento" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-sm text-muted">Género</label>
                            <select class="form-select" name="genero" required>
                                <option value="">Seleccionar...</option>
                                <option value="Masculino">Masculino</option>
                                <option value="Femenino">Femenino</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-sm text-muted">Teléfono</label>
                            <input type="tel" class="form-control" name="telefono" id="qp_telefono">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-sm text-muted">Dirección</label>
                            <textarea class="form-control" name="direccion" id="qp_direccion" rows="2" placeholder="Dirección completa..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                     <button type="button" class="btn btn-light" data-bs-dismiss="modal">Omitir Registro</button>
                    <button type="submit" class="btn btn-warning text-white fw-bold px-4">Registrar Paciente</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Context Menu HTML -->
<div id="calendarContextMenu" class="context-menu animate__animated animate__fadeIn">
    <div class="context-menu-item" id="cm-edit">
        <i class="bi bi-pencil text-primary"></i>
        <span>Editar Cita</span>
    </div>
    <div class="context-menu-item text-danger" id="cm-delete">
        <i class="bi bi-trash"></i>
        <span>Eliminar Cita</span>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>

<!-- Scripts -->
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/es.js'></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../../assets/js/dashboard-reengineered.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: '75vh',
        expandRows: true, // Expand rows to fill height
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        locale: 'es',
        themeSystem: 'standard',
        events: 'get_appointments.php',
        eventTimeFormat: {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        },
        eventDisplay: 'block',
        eventClick: function(info) {
            fetch('get_appointment_details.php?id=' + info.event.id)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Populate Edit Modal
                        document.getElementById('edit_id_cita').value = data.id_cita;
                        document.getElementById('edit_nombre_pac').value = data.nombre_pac;
                        document.getElementById('edit_apellido_pac').value = data.apellido_pac;
                        document.getElementById('edit_date').value = data.fecha_cita;
                        document.getElementById('edit_time').value = data.hora_cita;
                        document.getElementById('edit_telefono').value = data.telefono;
                        document.getElementById('edit_id_doctor').value = data.id_doctor;
                        
                        editAppointmentModal.show();
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                });
        },
        eventDidMount: function(info) {
            info.el.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                showContextMenu(e.pageX, e.pageY, info.event);
            });
        }
    });
    calendar.render();

    // Context Menu Logic
    const contextMenu = document.getElementById('calendarContextMenu');
    let currentEvent = null;

    function showContextMenu(x, y, event) {
        currentEvent = event;
        contextMenu.style.display = 'block';
        contextMenu.style.left = x + 'px';
        contextMenu.style.top = y + 'px';
        
        // Ensure menu stays within viewport
        const menuWidth = contextMenu.offsetWidth;
        const menuHeight = contextMenu.offsetHeight;
        const windowWidth = window.innerWidth;
        const windowHeight = window.innerHeight;

        if ((x + menuWidth) > windowWidth) contextMenu.style.left = (x - menuWidth) + 'px';
        if ((y + menuHeight) > windowHeight) contextMenu.style.top = (y - menuHeight) + 'px';
    }

    function hideContextMenu() {
        contextMenu.style.display = 'none';
        currentEvent = null;
    }

    document.addEventListener('click', function(e) {
        if (!contextMenu.contains(e.target)) {
            hideContextMenu();
        }
    });

    document.getElementById('cm-edit').addEventListener('click', function() {
        if (currentEvent) {
            // Simulate normal click logic
            const info = { event: currentEvent };
            calendar.getOption('eventClick')(info);
        }
        hideContextMenu();
    });

    document.getElementById('cm-delete').addEventListener('click', function() {
        if (currentEvent) {
            const eventId = currentEvent.id;
            const eventTitle = currentEvent.title;
            
            Swal.fire({
                title: '¿Eliminar cita?',
                text: `Vas a eliminar la cita de: ${eventTitle}. Esta acción no se puede deshacer.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ff6b6b',
                cancelButtonColor: '#3a56b7',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                customClass: {
                    popup: 'card-glass'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('delete_appointment.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: eventId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire({
                                title: '¡Eliminada!',
                                text: 'La cita ha sido eliminada correctamente.',
                                icon: 'success',
                                timer: 1500,
                                showConfirmButton: false
                            });
                            currentEvent.remove();
                        } else {
                            Swal.fire('Error', data.message || 'No se pudo eliminar la cita.', 'error');
                        }
                    });
                }
            });
        }
        hideContextMenu();
    });

    // Logic for Appointment Form Submission
    const appointmentModal = new bootstrap.Modal(document.getElementById('newAppointmentModal'));
    const editAppointmentModal = new bootstrap.Modal(document.getElementById('editAppointmentModal'));
    const quickPatientModal = new bootstrap.Modal(document.getElementById('quickPatientModal'));
    
    document.getElementById('appointmentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('save_appointment.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                appointmentModal.hide();
                calendar.refetchEvents();
                this.reset();
                
                // Show temporary success message
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000,
                    timerProgressBar: true
                });

                Toast.fire({
                    icon: 'success',
                    title: 'Cita agendada correctamente'
                });

                // Check if patient exists
                if (!data.patient_exists) {
                    setTimeout(() => {
                        // Pre-fill quick patient form
                        document.getElementById('qp_nombre').value = data.patient_data.nombre;
                        document.getElementById('qp_apellido').value = data.patient_data.apellido;
                        document.getElementById('qp_telefono').value = data.patient_data.telefono;
                        document.getElementById('qp_direccion').value = '';
                        
                        quickPatientModal.show();
                    }, 500);
                }
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'Hubo un problema al procesar la solicitud', 'error');
        });
    });

    // Logic for Edit Appointment Form Submission
    document.getElementById('editAppointmentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('update_appointment.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                editAppointmentModal.hide();
                calendar.refetchEvents();
                
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000,
                    timerProgressBar: true
                });

                Toast.fire({
                    icon: 'success',
                    title: 'Cita actualizada correctamente'
                });
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'Hubo un problema al procesar la solicitud', 'error');
        });
    });

    // Logic for Quick Patient Form Submission
    document.getElementById('quickPatientForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('save_patient_ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                quickPatientModal.hide();
                Swal.fire({
                    icon: 'success',
                    title: '¡Registrado!',
                    text: 'El paciente ha sido agregado a la base de datos.',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        });
    });
});
</script>