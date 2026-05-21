<?php
// index.php - Calendario de Citas - Centro Médico RS
// Versión: 4.0 - Diseño Responsive, Barra Lateral Moderna, Efecto Mármol
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';
require_once '../../includes/module_guard.php';

check_module_access('core'); // Citas es módulo base

date_default_timezone_set('America/Guatemala');
verify_session();

try {
    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener información del usuario
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['tipoUsuario'];
    $user_name = $_SESSION['nombre'];
    $user_specialty = $_SESSION['especialidad'] ?? 'Profesional Médico';

    // Obtener doctores para el dropdown
    $stmtDocs = $conn->prepare("SELECT idUsuario, nombre, apellido FROM usuarios WHERE tipoUsuario = 'doc' AND id_hospital = ? ORDER BY nombre, apellido");
    $stmtDocs->execute([hospital_id()]);
    $doctors = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

    // Estadísticas para la barra lateral
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM citas WHERE id_hospital = ?");
    $stmt->execute([hospital_id()]);
    $total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Citas de hoy
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM citas WHERE fecha_cita = ? AND id_hospital = ?");
    $stmt->execute([$today, hospital_id()]);
    $today_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Título de la página
    $page_title = "Calendario de Citas - Centro Médico RS";

} catch (Exception $e) {
    // Manejo de errores
    error_log("Error en calendario de citas: " . $e->getMessage());
    die("Error al cargar el calendario. Por favor, contacte al administrador.");
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Calendario de Citas del Centro Médico RS - Sistema de gestión de agenda médica">
    <title><?php echo $page_title; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">

    <!-- Google Fonts - Inter (moderno y legible) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- FullCalendar CSS -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet'>

    <!-- Seguridad y Protección de Código -->
    <script src="../../assets/js/security.js"></script>

    <!-- CSS Crítico (incrustado para máxima velocidad) -->
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .select2-container--default .select2-selection--single {
            border: 1.5px solid var(--color-border);
            border-radius: 0.75rem;
            min-height: 48px;
            display: flex;
            align-items: center;
            background: var(--color-card);
            color: var(--color-text);
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: var(--color-text);
            line-height: 48px;
            padding-left: 1rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 48px;
        }
        .select2-dropdown {
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            background: var(--color-card);
            box-shadow: 0 10px 25px rgba(0,0,0,0.12);
            overflow: hidden;
        }
        .select2-container--default .select2-search--dropdown .select2-search__field {
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: 0.5rem 0.75rem;
            background: var(--color-surface);
            color: var(--color-text);
            font-family: var(--font-family);
        }
        .select2-container--default .select2-results__option--highlighted {
            background: var(--color-primary);
        }
        .select2-container--default .select2-results__option {
            padding: 0.625rem 1rem;
            color: var(--color-text);
        }
        .reconsulta-toggle-container {
            background: rgba(var(--color-primary-rgb), 0.05);
            padding: 1rem 1.25rem;
            border-radius: 0.75rem;
            border: 1.5px dashed var(--color-primary);
            transition: background 0.2s;
        }
        .reconsulta-toggle-container:has(#reconsultaToggle:checked) {
            background: rgba(var(--color-primary-rgb), 0.1);
            border-style: solid;
        }
        .input-icon-wrapper {
            position: relative;
        }
        .input-icon-wrapper > i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--color-text-secondary);
            pointer-events: none;
            z-index: 2;
        }
        .input-icon-wrapper .ps-5 {
            padding-left: 2.75rem !important;
        }
    </style>

</head>

<body>
    <!-- Efecto de mármol animado -->
    <div class="marble-effect"></div>

    <!-- Contenedor Principal -->
    <div class="dashboard-container">
        <!-- Header Superior -->
        <header class="dashboard-header">
            <div class="header-content">
                <!-- Logo -->
                <div class="brand-container">
                    <img src="../../assets/img/Logo.png" alt="Centro Médico RS" class="brand-logo">
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
                            <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                        </div>
                        <div class="header-details">
                            <span class="header-name"><?php echo htmlspecialchars($user_name); ?></span>
                            <span class="header-role"><?php echo htmlspecialchars($user_specialty); ?></span>
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

            <!-- Estadísticas principales -->
            <?php if ($user_type === 'admin'): ?>
                <div class="stats-grid">
                    <!-- Citas de hoy -->
                    <div class="stat-card animate-in delay-1">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Citas Hoy</div>
                                <div class="stat-value"><?php echo $today_appointments; ?></div>
                            </div>
                            <div class="stat-icon calendar">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                        </div>
                        <div class="stat-change positive">
                            <i class="bi bi-arrow-up-right"></i>
                            <span>Programadas para hoy</span>
                        </div>
                    </div>

                    <!-- Citas totales -->
                    <div class="stat-card animate-in delay-2">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Citas Totales</div>
                                <div class="stat-value"><?php echo $total_appointments; ?></div>
                            </div>
                            <div class="stat-icon primary">
                                <i class="bi bi-calendar-week"></i>
                            </div>
                        </div>
                        <div class="stat-change positive">
                            <i class="bi bi-calendar-plus"></i>
                            <span>En el sistema</span>
                        </div>
                    </div>

                    <!-- Doctores disponibles -->
                    <div class="stat-card animate-in delay-3">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Doctores</div>
                                <div class="stat-value"><?php echo count($doctors); ?></div>
                            </div>
                            <div class="stat-icon success">
                                <i class="bi bi-person-badge"></i>
                            </div>
                        </div>
                        <div class="stat-change positive">
                            <i class="bi bi-person-plus"></i>
                            <span>Disponibles</span>
                        </div>
                    </div>

                    <!-- Horario -->
                    <div class="stat-card animate-in delay-4">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Horario</div>
                                <div class="stat-value">8-20h</div>
                            </div>
                            <div class="stat-icon info">
                                <i class="bi bi-clock"></i>
                            </div>
                        </div>
                        <div class="stat-change positive">
                            <i class="bi bi-clock-history"></i>
                            <span>Lunes a Sábado</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Bienvenida personalizada -->
            <div class="stat-card mb-4 animate-in">
                <div class="stat-header">
                    <div>
                        <h2 id="greeting" class="stat-value" style="font-size: 1.75rem; margin-bottom: 0.5rem;">
                            <span id="greeting-text">Buenos días</span>, <?php echo htmlspecialchars($user_name); ?>
                        </h2>
                        <p class="text-muted mb-0">
                            <i class="bi bi-calendar-check me-1"></i> <?php echo date('d/m/Y'); ?>
                            <span class="mx-2">•</span>
                            <i class="bi bi-clock me-1"></i> <span id="current-time"><?php echo date('H:i'); ?></span>
                            <span class="mx-2">•</span>
                            <i class="bi bi-building me-1"></i> Centro Médico RS
                        </p>
                    </div>
                    <div class="d-none d-md-block">
                        <i class="bi bi-calendar-heart text-calendar" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>

            <!-- Sección principal del calendario -->
            <section class="calendar-section animate-in delay-1">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-calendar-heart section-title-icon"></i>
                        Calendario de Citas
                    </h3>
                    <div class="d-flex gap-2">
                        <button type="button" class="action-btn" data-bs-toggle="modal"
                            data-bs-target="#newAppointmentModal">
                            <i class="bi bi-plus-lg"></i>
                            Nueva Cita
                        </button>
                    </div>
                </div>

                <!-- Contenedor del calendario -->
                <div id="calendar"></div>
            </section>
        </main>
    </div>

    <!-- Modal para nueva cita -->
    <div class="modal fade" id="newAppointmentModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-calendar-plus"></i>
                        Programar Nueva Cita
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="appointmentForm" action="save_appointment.php" method="POST">
                    <div class="modal-body">
                        <div class="reconsulta-toggle-container mb-4 d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 fw-bold">¿Es Reconsulta?</h6>
                                <small class="text-muted">Active para buscar un paciente ya registrado</small>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="reconsultaToggle" style="width: 3rem; height: 1.5rem;">
                            </div>
                        </div>

                        <div class="row g-4">
                            <!-- Buscador de pacientes existentes (Oculto por defecto) -->
                            <div class="col-12" id="existingPatientSection" style="display: none;">
                                <label class="form-label">Buscar Paciente Registrado</label>
                                <div class="input-icon-wrapper">
                                    <i class="bi bi-search"></i>
                                    <select class="form-select ps-5" id="patientSearch" style="width: 100%;">
                                        <option value="">Escriba nombre, apellido o DPI...</option>
                                    </select>
                                </div>
                                <input type="hidden" name="id_paciente" id="selectedPatientId">
                            </div>

                            <div class="col-md-6 name-field">
                                <label class="form-label">Nombre del Paciente</label>
                                <div class="input-icon-wrapper">
                                    <i class="bi bi-person"></i>
                                    <input type="text" class="form-control" name="nombre_pac" id="nombre_pac" placeholder="Ej. Juan"
                                        required>
                                </div>
                            </div>
                            <div class="col-md-6 name-field">
                                <label class="form-label">Apellido del Paciente</label>
                                <div class="input-icon-wrapper">
                                    <i class="bi bi-person"></i>
                                    <input type="text" class="form-control" name="apellido_pac" id="apellido_pac" placeholder="Ej. Pérez"
                                        required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fecha de la Cita</label>
                                <div class="input-icon-wrapper">
                                    <i class="bi bi-calendar-event"></i>
                                    <input type="date" class="form-control" name="fecha_cita" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Hora de la Cita</label>
                                <div class="input-icon-wrapper">
                                    <i class="bi bi-clock"></i>
                                    <input type="time" class="form-control" name="hora_cita" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Teléfono de Contacto</label>
                                <div class="input-icon-wrapper">
                                    <i class="bi bi-telephone"></i>
                                    <input type="tel" class="form-control" name="telefono" placeholder="Ej. 5555-5555">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Médico Asignado</label>
                                <div class="input-icon-wrapper">
                                    <i class="bi bi-person-badge"></i>
                                    <select class="form-select ps-5" name="id_doctor" required>
                                        <option value="">Seleccionar médico...</option>
                                        <?php foreach ($doctors as $doc): ?>
                                            <option value="<?php echo $doc['idUsuario']; ?>">
                                                Dr(a).
                                                <?php echo htmlspecialchars($doc['nombre'] . ' ' . $doc['apellido']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="action-btn secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="action-btn">
                            <i class="bi bi-check2-circle me-1"></i>
                            Programar Cita
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para editar cita -->
    <div class="modal fade" id="editAppointmentModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil-square"></i>
                        Editar Cita
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editAppointmentForm" action="update_appointment.php" method="POST">
                    <input type="hidden" name="id_cita" id="edit_id_cita">
                    <div class="modal-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">Nombre del Paciente</label>
                                <div class="input-icon-wrapper">
                                    <i class="bi bi-person"></i>
                                    <input type="text" class="form-control" name="nombre_pac" id="edit_nombre_pac"
                                        required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Apellido del Paciente</label>
                                <div class="input-icon-wrapper">
                                    <i class="bi bi-person"></i>
                                    <input type="text" class="form-control" name="apellido_pac" id="edit_apellido_pac"
                                        required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fecha de la Cita</label>
                                <div class="input-icon-wrapper">
                                    <i class="bi bi-calendar-event"></i>
                                    <input type="date" class="form-control" name="fecha_cita" id="edit_fecha_cita"
                                        required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Hora de la Cita</label>
                                <div class="input-icon-wrapper">
                                    <i class="bi bi-clock"></i>
                                    <input type="time" class="form-control" name="hora_cita" id="edit_hora_cita"
                                        required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Teléfono de Contacto</label>
                                <div class="input-icon-wrapper">
                                    <i class="bi bi-telephone"></i>
                                    <input type="tel" class="form-control" name="telefono" id="edit_telefono">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Médico Asignado</label>
                                <div class="input-icon-wrapper">
                                    <i class="bi bi-person-badge"></i>
                                    <select class="form-select ps-5" name="id_doctor" id="edit_id_doctor" required>
                                        <option value="">Seleccionar médico...</option>
                                        <?php foreach ($doctors as $doc): ?>
                                            <option value="<?php echo $doc['idUsuario']; ?>">
                                                Dr(a).
                                                <?php echo htmlspecialchars($doc['nombre'] . ' ' . $doc['apellido']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="action-btn secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="action-btn">
                            <i class="bi bi-arrow-repeat me-1"></i>
                            Actualizar Cita
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Menú contextual -->
    <div id="contextMenu" class="context-menu">
        <div class="context-item" id="contextNew">
            <i class="bi bi-calendar-plus text-success"></i>
            <span>Nueva Cita</span>
        </div>
        <div class="context-item" id="contextHistory">
            <i class="bi bi-journal-medical text-info"></i>
            <span>Ver Historial</span>
        </div>
        <div class="context-item" id="contextEdit">
            <i class="bi bi-pencil text-calendar"></i>
            <span>Editar cita</span>
        </div>
        <div class="context-item danger" id="contextDelete">
            <i class="bi bi-trash text-danger"></i>
            <span>Eliminar cita</span>
        </div>
    </div>

    <!-- JavaScript Optimizado -->
    <!-- jQuery & Select2 JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/es.js'></script>
    <script>
        // Calendario de Citas Reingenierizado - Centro Médico RS

        (function () {
            'use strict';

            // ==========================================================================
            // CONFIGURACIÓN Y CONSTANTES
            // ==========================================================================
            const CONFIG = {
                themeKey: 'dashboard-theme',

                calendarViewKey: 'calendar-view',
                transitionDuration: 300,
                animationDelay: 100
            };

            // ==========================================================================
            // REFERENCIAS A ELEMENTOS DOM
            // ==========================================================================
            const DOM = {
                html: document.documentElement,
                body: document.body,
                themeSwitch: document.getElementById('themeSwitch'),
                greetingElement: document.getElementById('greeting-text'),
                currentTimeElement: document.getElementById('current-time'),
                calendar: document.getElementById('calendar'),
                contextMenu: document.getElementById('contextMenu'),
                contextNew: document.getElementById('contextNew'),
                contextHistory: document.getElementById('contextHistory'),
                contextEdit: document.getElementById('contextEdit'),
                contextDelete: document.getElementById('contextDelete')
            };

            // ==========================================================================
            // MANEJO DE TEMA (DÍA/NOCHE)
            // ==========================================================================
            class ThemeManager {
                constructor() {
                    this.theme = this.getInitialTheme();
                    this.applyTheme(this.theme);
                    this.setupEventListeners();
                }

                getInitialTheme() {
                    // 1. Verificar preferencia guardada
                    const savedTheme = localStorage.getItem(CONFIG.themeKey);
                    if (savedTheme) return savedTheme;

                    // 2. Verificar preferencia del sistema
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    if (prefersDark) return 'dark';

                    // 3. Tema por defecto (día)
                    return 'light';
                }

                applyTheme(theme) {
                    DOM.html.setAttribute('data-theme', theme);
                    localStorage.setItem(CONFIG.themeKey, theme);

                    // Actualizar meta tag para navegadores móviles
                    const metaTheme = document.querySelector('meta[name="theme-color"]');
                    if (metaTheme) {
                        metaTheme.setAttribute('content', theme === 'dark' ? '#0f172a' : '#ffffff');
                    }

                    // Si el calendario ya está inicializado, forzar redibujado
                    if (window.calendar) {
                        setTimeout(() => window.calendar.render(), 100);
                    }
                }

                toggleTheme() {
                    const newTheme = this.theme === 'light' ? 'dark' : 'light';
                    this.theme = newTheme;
                    this.applyTheme(newTheme);

                    // Animación sutil en el botón
                    if (DOM.themeSwitch) {
                        DOM.themeSwitch.style.transform = 'rotate(180deg)';
                        setTimeout(() => {
                            DOM.themeSwitch.style.transform = 'rotate(0)';
                        }, CONFIG.transitionDuration);
                    }
                }

                setupEventListeners() {
                    // Reconsulta Toggle Logic
                    const reconsultaToggle = document.getElementById('reconsultaToggle');
                    const existingPatientSection = document.getElementById('existingPatientSection');
                    const nameFields = document.querySelectorAll('.name-field');
                    const nombreInput = document.getElementById('nombre_pac');
                    const apellidoInput = document.getElementById('apellido_pac');

                    if (reconsultaToggle) {
                        reconsultaToggle.addEventListener('change', (e) => {
                            if (e.target.checked) {
                                existingPatientSection.style.display = 'block';
                                nameFields.forEach(f => f.style.display = 'none');
                                nombreInput.removeAttribute('required');
                                apellidoInput.removeAttribute('required');
                                // Inicializar Select2 si no está inicializado
                                this.initPatientSearch();
                            } else {
                                existingPatientSection.style.display = 'none';
                                nameFields.forEach(f => f.style.display = 'block');
                                nombreInput.setAttribute('required', '');
                                apellidoInput.setAttribute('required', '');
                                document.getElementById('selectedPatientId').value = '';
                            }
                        });
                    }

                    if (DOM.themeSwitch) {
                        DOM.themeSwitch.addEventListener('click', () => this.toggleTheme());
                    }

                    // Escuchar cambios en preferencias del sistema
                    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                        if (!localStorage.getItem(CONFIG.themeKey)) {
                            this.theme = e.matches ? 'dark' : 'light';
                            this.applyTheme(this.theme);
                        }
                    });
                }

                initPatientSearch() {
                    const $search = $('#patientSearch');
                    if ($search.hasClass('select2-hidden-accessible')) return;

                    $search.select2({
                        theme: 'default',
                        dropdownParent: $('#newAppointmentModal'),
                        placeholder: 'Escriba nombre, apellido o DPI...',
                        minimumInputLength: 2,
                        language: {
                            searching: () => 'Buscando...',
                            noResults: () => 'No se encontraron pacientes',
                            inputTooShort: () => 'Ingrese al menos 2 caracteres',
                            errorLoading: () => 'Error al cargar resultados'
                        },
                        ajax: {
                            url: '../patients/search_patients.php',
                            dataType: 'json',
                            delay: 300,
                            data: function (params) {
                                return { q: params.term };
                            },
                            processResults: function (data) {
                                if (!Array.isArray(data)) return { results: [] };
                                return {
                                    results: data.map(p => ({
                                        id: p.id_paciente,
                                        text: `${p.nombre} ${p.apellido}${p.dpi ? ' — ' + p.dpi : ''}`,
                                        nombre: p.nombre,
                                        apellido: p.apellido
                                    }))
                                };
                            },
                            cache: true,
                            error: function() {
                                console.warn('Error searching patients');
                            }
                        }
                    }).on('select2:select', function (e) {
                        const data = e.params.data;
                        document.getElementById('selectedPatientId').value = data.id;
                        document.getElementById('nombre_pac').value = data.nombre;
                        document.getElementById('apellido_pac').value = data.apellido;
                    });
                }
            }

            // ==========================================================================
            // CALENDARIO FULLCALENDAR
            // ==========================================================================
            class CalendarManager {
                constructor() {
                    this.calendar = null;
                    this.currentEvent = null;
                    this.currentDateStr = null; // Guardar la fecha/hora para la nueva cita
                    this.initialize();
                }

                initialize() {
                    if (!DOM.calendar) return;

                    // Obtener vista guardada o usar por defecto
                    const savedView = localStorage.getItem(CONFIG.calendarViewKey) || 'dayGridMonth';

                    this.calendar = new FullCalendar.Calendar(DOM.calendar, {
                        initialView: savedView,
                        locale: 'es',
                        themeSystem: 'standard',
                        headerToolbar: {
                            left: 'prev,next today',
                            center: 'title',
                            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                        },
                        buttonText: {
                            today: 'Hoy',
                            month: 'Mes',
                            week: 'Semana',
                            day: 'Día',
                            list: 'Lista'
                        },
                        firstDay: 1, // Lunes
                        navLinks: true,
                        editable: true,
                        selectable: true,
                        nowIndicator: true,
                        dayMaxEvents: 3,
                        height: 'auto',
                        slotMinTime: '08:00:00',
                        slotMaxTime: '20:00:00',
                        businessHours: {
                            daysOfWeek: [1, 2, 3, 4, 5, 6], // Lunes a Sábado
                            startTime: '08:00',
                            endTime: '20:00'
                        },

                        // Cargar eventos
                        events: 'get_appointments.php',

                        // Manejar clic en fecha
                        dateClick: (info) => {
                            // Prellenar fecha en el modal de nueva cita
                            document.querySelector('#newAppointmentModal input[name="fecha_cita"]').value = info.dateStr;

                            // Mostrar modal
                            const modal = new bootstrap.Modal(document.getElementById('newAppointmentModal'));
                            modal.show();
                        },

                        // Manejar cambio de vista
                        viewDidMount: (view) => {
                            localStorage.setItem(CONFIG.calendarViewKey, view.view.type);
                        },

                        // Estilizar eventos
                        eventDidMount: (info) => {
                            // Agregar clase según el tipo de evento
                            const eventType = info.event.extendedProps.tipo || 'primary';
                            info.el.classList.add(`fc-event-${eventType}`);

                            // Agregar tooltip
                            const title = info.event.title;
                            const time = info.event.start ?
                                info.event.start.toLocaleTimeString('es-GT', { hour: '2-digit', minute: '2-digit' }) : '';
                            const doctor = info.event.extendedProps.doctor || '';

                            info.el.title = `${title}\n${time}\n${doctor}`;

                            // Manejar click derecho
                            info.el.addEventListener('contextmenu', (e) => {
                                e.preventDefault();
                                this.currentEvent = info.event;
                                this.showContextMenu(e.pageX, e.pageY);
                                return false;
                            });
                        }
                    });

                    this.calendar.render();

                    // Manejar click derecho en espacios vacíos del calendario
                    DOM.calendar.addEventListener('contextmenu', (e) => {
                        // Buscar el elemento de fecha/hora más cercano
                        const cell = e.target.closest('.fc-daygrid-day, .fc-timegrid-slot, .fc-timegrid-col');
                        if (cell) {
                            e.preventDefault();
                            let dateStr = cell.getAttribute('data-date');
                            
                            if (dateStr) {
                                this.currentEvent = null; // No hay evento seleccionado
                                this.currentDateStr = dateStr;
                                this.showContextMenu(e.pageX, e.pageY);
                            }
                        }
                    });

                    // Exponer calendario globalmente
                    window.calendar = this.calendar;
                }

                refresh() {
                    if (this.calendar) {
                        this.calendar.refetchEvents();
                    }
                }

                showContextMenu(x, y) {
                    DOM.contextMenu.style.display = 'block';
                    DOM.contextMenu.style.left = x + 'px';
                    DOM.contextMenu.style.top = y + 'px';

                    // Mostrar/ocultar opciones según el contexto
                    if (this.currentEvent) {
                        // Click derecho en un evento
                        if(DOM.contextNew) DOM.contextNew.style.display = 'none';
                        if(DOM.contextHistory) DOM.contextHistory.style.display = 'flex';
                        if(DOM.contextEdit) DOM.contextEdit.style.display = 'flex';
                        if(DOM.contextDelete) DOM.contextDelete.style.display = 'flex';
                    } else {
                        // Click derecho en un espacio vacío
                        if(DOM.contextNew) DOM.contextNew.style.display = 'flex';
                        if(DOM.contextHistory) DOM.contextHistory.style.display = 'none';
                        if(DOM.contextEdit) DOM.contextEdit.style.display = 'none';
                        if(DOM.contextDelete) DOM.contextDelete.style.display = 'none';
                    }

                    // Ajustar posición si sale de la ventana
                    const menuRect = DOM.contextMenu.getBoundingClientRect();
                    const windowWidth = window.innerWidth;
                    const windowHeight = window.innerHeight;

                    if (menuRect.right > windowWidth) {
                        DOM.contextMenu.style.left = (x - menuRect.width) + 'px';
                    }

                    if (menuRect.bottom > windowHeight) {
                        DOM.contextMenu.style.top = (y - menuRect.height) + 'px';
                    }
                }

                hideContextMenu() {
                    DOM.contextMenu.style.display = 'none';
                }

                newAppointmentFromContext() {
                    this.hideContextMenu();
                    if (this.currentDateStr) {
                        // Prellenar fecha y hora
                        const dateParts = this.currentDateStr.split('T');
                        document.querySelector('#newAppointmentModal input[name="fecha_cita"]').value = dateParts[0];
                        if(dateParts[1]) {
                            document.querySelector('#newAppointmentModal input[name="hora_cita"]').value = dateParts[1].substring(0,5);
                        } else {
                            document.querySelector('#newAppointmentModal input[name="hora_cita"]').value = '';
                        }
                    }
                    const modal = new bootstrap.Modal(document.getElementById('newAppointmentModal'));
                    modal.show();
                }

                editCurrentEvent() {
                    if (!this.currentEvent) return;

                    fetch('get_appointment_details.php?id=' + this.currentEvent.id)
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                document.getElementById('edit_id_cita').value = data.id_cita;
                                document.getElementById('edit_nombre_pac').value = data.nombre_pac;
                                document.getElementById('edit_apellido_pac').value = data.apellido_pac;
                                document.getElementById('edit_fecha_cita').value = data.fecha_cita;
                                document.getElementById('edit_hora_cita').value = data.hora_cita;
                                document.getElementById('edit_telefono').value = data.telefono || '';
                                document.getElementById('edit_id_doctor').value = data.id_doctor;

                                const modal = new bootstrap.Modal(document.getElementById('editAppointmentModal'));
                                modal.show();
                            } else {
                                this.showNotification('Error al cargar detalles: ' + data.message, 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            this.showNotification('Error al cargar los detalles de la cita', 'error');
                        });

                    this.hideContextMenu();
                }

                deleteCurrentEvent() {
                    if (!this.currentEvent) return;

                    this.hideContextMenu();

                    Swal.fire({
                        title: '¿Eliminar cita?',
                        text: "Esta acción no se puede deshacer",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: 'var(--color-danger)',
                        cancelButtonColor: 'var(--color-secondary)',
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar',
                        background: 'var(--color-card)',
                        color: 'var(--color-text)'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            fetch('delete_appointment.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({ id: this.currentEvent.id })
                            })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.status === 'success') {
                                        this.refresh();
                                        this.showNotification('Cita eliminada correctamente', 'success');
                                    } else {
                                        this.showNotification('Error: ' + data.message, 'error');
                                    }
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    this.showNotification('No se pudo procesar la solicitud', 'error');
                                });
                        }
                    });
                }

                viewPatientHistory() {
                    if (!this.currentEvent) return;
                    this.hideContextMenu();

                    const patientId = this.currentEvent.extendedProps.id_paciente;
                    if (patientId) {
                        window.location.href = `../patients/medical_history.php?id=${patientId}`;
                    } else {
                        this.showNotification('No hay expediente asociado a esta cita', 'warning');
                    }
                }

                showNotification(message, type = 'info') {
                    const icon = {
                        success: 'bi-check-circle-fill',
                        error: 'bi-exclamation-triangle-fill',
                        warning: 'bi-exclamation-circle-fill',
                        info: 'bi-info-circle-fill'
                    }[type];

                    const color = {
                        success: 'var(--color-success)',
                        error: 'var(--color-danger)',
                        warning: 'var(--color-warning)',
                        info: 'var(--color-info)'
                    }[type];

                    const notification = document.createElement('div');
                    notification.className = 'stat-card mb-4 animate-in';
                    notification.style.borderLeft = `4px solid ${color}`;
                    notification.style.animation = 'fadeInUp 0.4s ease-out';

                    notification.innerHTML = `
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi ${icon}" style="color: ${color}; font-size: 1.25rem;"></i>
                            <div>
                                <p class="mb-0">${message}</p>
                            </div>
                        </div>
                        <button type="button" class="btn-close" onclick="this.parentElement.parentElement.remove()"></button>
                    </div>
                `;

                    const mainContent = document.querySelector('.main-content');
                    const firstChild = mainContent.firstChild;
                    mainContent.insertBefore(notification, firstChild);

                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.style.opacity = '0';
                            notification.style.transform = 'translateY(-10px)';
                            setTimeout(() => notification.remove(), 300);
                        }
                    }, 5000);
                }
            }

            // ==========================================================================
            // COMPONENTES DINÁMICOS
            // ==========================================================================
            class DynamicComponents {
                constructor() {
                    this.setupGreeting();
                    this.setupClock();
                    this.setupFormHandlers();
                    this.setupAnimations();
                }

                setupGreeting() {
                    if (!DOM.greetingElement) return;

                    const hour = new Date().getHours();
                    let greeting = '';

                    if (hour < 12) {
                        greeting = 'Buenos días';
                    } else if (hour < 19) {
                        greeting = 'Buenas tardes';
                    } else {
                        greeting = 'Buenas noches';
                    }

                    DOM.greetingElement.textContent = greeting;
                }

                setupClock() {
                    if (!DOM.currentTimeElement) return;

                    const updateClock = () => {
                        const now = new Date();
                        const timeString = now.toLocaleTimeString('es-GT', {
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: false
                        });
                        DOM.currentTimeElement.textContent = timeString;
                    };

                    updateClock();
                    setInterval(updateClock, 60000);
                }

                setupFormHandlers() {
                    // Formulario de nueva cita
                    const appointmentForm = document.getElementById('appointmentForm');
                    if (appointmentForm) {
                        appointmentForm.addEventListener('submit', (e) => {
                            e.preventDefault();
                            this.handleFormSubmit(appointmentForm, 'save_appointment.php', 'Cita programada correctamente');
                        });
                    }

                    // Formulario de edición de cita
                    const editAppointmentForm = document.getElementById('editAppointmentForm');
                    if (editAppointmentForm) {
                        editAppointmentForm.addEventListener('submit', (e) => {
                            e.preventDefault();
                            this.handleFormSubmit(editAppointmentForm, 'update_appointment.php', 'Cita actualizada correctamente');
                        });
                    }
                }

                handleFormSubmit(form, action, successMessage) {
                    const formData = new FormData(form);
                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;

                    submitBtn.innerHTML = '<i class="bi bi-hourglass-split spin me-2"></i>Procesando...';
                    submitBtn.disabled = true;

                    fetch(action, {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                const modalId = form.id === 'appointmentForm' ? 'newAppointmentModal' : 'editAppointmentModal';
                                const modal = bootstrap.Modal.getInstance(document.getElementById(modalId));
                                modal.hide();

                                form.reset();

                                if (window.calendarManager) {
                                    window.calendarManager.refresh();
                                    window.calendarManager.showNotification(successMessage, 'success');
                                }
                            } else {
                                window.calendarManager.showNotification('Error: ' + data.message, 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            window.calendarManager.showNotification('Error al procesar la solicitud', 'error');
                        })
                        .finally(() => {
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        });
                }

                setupAnimations() {
                    const observerOptions = {
                        root: null,
                        rootMargin: '0px',
                        threshold: 0.1
                    };

                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                entry.target.classList.add('animate-in');
                                observer.unobserve(entry.target);
                            }
                        });
                    }, observerOptions);

                    document.querySelectorAll('.stat-card, .calendar-section').forEach(el => {
                        observer.observe(el);
                    });
                }
            }

            // ==========================================================================
            // INICIALIZACIÓN DE LA APLICACIÓN
            // ==========================================================================
            document.addEventListener('DOMContentLoaded', () => {
                // Inicializar componentes
                const themeManager = new ThemeManager();
                const calendarManager = new CalendarManager();
                const dynamicComponents = new DynamicComponents();

                // Configurar menú contextual
                if (DOM.contextEdit && DOM.contextDelete) {
                    DOM.contextNew?.addEventListener('click', () => calendarManager.newAppointmentFromContext());
                    DOM.contextHistory?.addEventListener('click', () => calendarManager.viewPatientHistory()); // Bind history action
                    DOM.contextEdit.addEventListener('click', () => calendarManager.editCurrentEvent());
                    DOM.contextDelete.addEventListener('click', () => calendarManager.deleteCurrentEvent());

                    document.addEventListener('click', (e) => {
                        if (!DOM.contextMenu.contains(e.target)) {
                            calendarManager.hideContextMenu();
                        }
                    });
                }

                // Exponer APIs necesarias globalmente
                window.app = {
                    theme: themeManager,
                    calendar: calendarManager,
                    components: dynamicComponents
                };

                window.calendarManager = calendarManager;

                // Log de inicialización
                console.log('Calendario de Citas - CMS v4.0 inicializado correctamente');
                console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
                console.log('Rol: <?php echo htmlspecialchars($user_type); ?>');
            });

            // ==========================================================================
            // MANEJO DE ERRORES GLOBALES
            // ==========================================================================
            window.addEventListener('error', (event) => {
                console.error('Error en calendario de citas:', event.error);

                if (window.location.hostname !== 'localhost') {
                    const errorData = {
                        message: event.message,
                        source: event.filename,
                        lineno: event.lineno,
                        colno: event.colno,
                        user: '<?php echo htmlspecialchars($user_name); ?>',
                        timestamp: new Date().toISOString()
                    };

                    console.log('Error reportado:', errorData);
                }
            });

            // ==========================================================================
            // POLYFILLS PARA NAVEGADORES ANTIGUOS
            // ==========================================================================
            if (!NodeList.prototype.forEach) {
                NodeList.prototype.forEach = Array.prototype.forEach;
            }

        })();

        // Estilos para spinner
        const style = document.createElement('style');
        style.textContent = `
        .spin {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    `;
        document.head.appendChild(style);
    </script>
</body>

</html>