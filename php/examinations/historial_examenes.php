<?php
// historial_examenes.php - Historial de Exámenes - Centro Médico RS
// Versión: 4.0 - Estilo Dashboard Principal
session_start();

// Verificar sesión activa
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Incluir configuraciones y funciones
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';



// Establecer zona horaria
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

    // Título de la página
    $page_title = "Historial de Exámenes - Centro Médico RS";

    // Configuración de paginación
    $limit = 20; // Registros por página
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $offset = ($page > 1) ? ($page - 1) * $limit : 0;

    // Obtener total de registros
    $id_hospital = $_SESSION['id_hospital'] ?? 0;

    $stmt_count = $conn->prepare("SELECT COUNT(*) as total FROM examenes_realizados WHERE id_hospital = ?");
    $stmt_count->execute([$id_hospital]);
    $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    $total_paginas = ceil($total_registros / $limit);

    $stmt = $conn->prepare("
        SELECT id_examen_realizado, nombre_paciente, tipo_examen, cobro, fecha_examen 
        FROM examenes_realizados 
        WHERE id_hospital = ?
        ORDER BY fecha_examen DESC 
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindParam(1, $id_hospital, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $examenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Manejo de errores
    $examenes = [];
    $total_paginas = 1;
    $total_registros = 0;
    $error_message = "Error al cargar el historial: " . $e->getMessage();
    error_log("Error en historial_examenes: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Historial de Exámenes - Centro Médico RS">
    <title><?php echo $page_title; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">

    <!-- Google Fonts - Inter (moderno y legible) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- CSS Crítico (incrustado para máxima velocidad) - IDÉNTICO AL DASHBOARD -->
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">

</head>

<body>
    <!-- Efecto de mármol animado -->
    <div class="marble-effect"></div>

    <!-- Sidebar removed -->

    <!-- Contenedor Principal -->
    <div class="dashboard-container">
        <!-- Header Superior -->
        <header class="dashboard-header">
            <div class="header-content">
                <!-- Botón hamburguesa para móvil -->
                <button class="mobile-toggle" id="mobileSidebarToggle" aria-label="Abrir menú">
                    <i class="bi bi-list"></i>
                </button>

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

                    <!-- Botón de cerrar sesión -->
                    <a href="../auth/logout.php" class="logout-btn">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Salir</span>
                    </a>
                </div>
            </div>
        </header>

        <!-- Sidebar toggle removed -->

        <!-- Contenido Principal -->
        <main class="main-content">
            <!-- Bienvenida personalizada -->
            <div class="welcome-card animate-in">
                <div class="welcome-header">
                    <div>
                        <h2 id="greeting" style="font-size: 1.75rem; margin-bottom: 0.5rem;">
                            <span id="greeting-text">Historial de Exámenes</span>
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
                        <i class="bi bi-clipboard2-data text-primary" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-muted mb-0">
                        Visualice y administre el historial completo de exámenes realizados en el centro médico.
                    </p>
                </div>
            </div>

            <!-- Sección de historial -->
            <section class="history-section animate-in delay-1">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-clock-history section-title-icon"></i>
                        Exámenes Realizados
                    </h3>
                    <div class="d-flex gap-2">
                        <a href="index.php" class="action-btn secondary">
                            <i class="bi bi-arrow-left"></i>
                            <span>Regresar</span>
                        </a>
                        <button type="button" class="action-btn" data-bs-toggle="modal" data-bs-target="#reportModal">
                            <i class="bi bi-file-earmark-pdf"></i>
                            <span>Reporte</span>
                        </button>
                    </div>
                </div>

                <!-- Mensaje de error -->
                <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger border-0 mb-4" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                <?php endif; ?>

                <?php if (empty($examenes)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="bi bi-clipboard-x"></i>
                            </div>
                            <h4 class="text-muted mb-2">No se encontraron registros</h4>
                            <p class="text-muted mb-3">No hay exámenes registrados en el sistema.</p>
                            <a href="index.php" class="action-btn">
                                <i class="bi bi-plus-lg"></i>
                                Registrar primer examen
                            </a>
                        </div>
                <?php else: ?>
                        <div class="table-responsive">
                            <table class="history-table">
                                <thead>
                                    <tr>
                                        <th>Paciente</th>
                                        <th>Tipo de Examen</th>
                                        <th>Cobro</th>
                                        <th>Fecha y Hora</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $prev_jornada = null;
                                    foreach ($examenes as $exam):
                                        // Calcular fecha de jornada (Si es antes de las 8am, pertenece al día anterior)
                                        $timestamp = strtotime($exam['fecha_examen']);
                                        $hora = (int) date('H', $timestamp);
                                        $fecha_base = date('Y-m-d', $timestamp);

                                        if ($hora < 8) {
                                            $jornada_date = date('Y-m-d', strtotime('-1 day', $timestamp));
                                        } else {
                                            $jornada_date = $fecha_base;
                                        }

                                        // Mostrar divisor si cambia la jornada
                                        if ($jornada_date !== $prev_jornada):
                                            $display_date = date('d/m/Y', strtotime($jornada_date));
                                            // Formato amigable: Hoy, Ayer, o fecha
                                            if ($jornada_date == date('Y-m-d')) {
                                                $display_text = "Jornada de Hoy ($display_date)";
                                            } elseif ($jornada_date == date('Y-m-d', strtotime('-1 day'))) {
                                                $display_text = "Jornada de Ayer ($display_date)";
                                            } else {
                                                $display_text = "Jornada del " . $display_date;
                                            }
                                            ?>
                                                    <tr class="jornada-row">
                                                        <td colspan="4" class="jornada-cell">
                                                            <i class="bi bi-calendar-range jornada-icon"></i>
                                                            <?php echo $display_text; ?>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                    $prev_jornada = $jornada_date;
                                        endif;

                                        // Obtener iniciales del paciente
                                        $patient_name = htmlspecialchars($exam['nombre_paciente']);
                                        $patient_initials = strtoupper(substr($patient_name, 0, 2));
                                        ?>
                                            <tr>
                                                <td>
                                                    <div class="patient-cell">
                                                        <div class="patient-avatar">
                                                            <?php echo $patient_initials; ?>
                                                        </div>
                                                        <div class="patient-info">
                                                            <div class="patient-name"><?php echo $patient_name; ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="fw-medium"><?php echo htmlspecialchars($exam['tipo_examen']); ?></span>
                                                </td>
                                                <td>
                                                    <span class="amount-badge">
                                                        Q<?php echo number_format($exam['cobro'], 2); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="time-badge">
                                                        <i class="bi bi-clock"></i>
                                                        <?php echo date('h:i A', strtotime($exam['fecha_examen'])); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        <?php if ($total_paginas > 1): ?>
                                <div class="pagination-container">
                                    <ul class="pagination">
                                        <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                                        <i class="bi bi-chevron-left"></i>
                                                    </a>
                                                </li>
                                        <?php endif; ?>

                                        <li class="page-item active">
                                            <span class="page-link"><?php echo $page; ?> de <?php echo $total_paginas; ?></span>
                                        </li>

                                        <?php if ($page < $total_paginas): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                                                        <i class="bi bi-chevron-right"></i>
                                                    </a>
                                                </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                        <?php endif; ?>

                        <div class="text-center mt-4">
                            <p class="text-muted">Total de registros: <?php echo $total_registros; ?></p>
                        </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <!-- Modal para reportes -->
    <div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-file-earmark-pdf text-primary"></i>
                        Reporte por Jornada
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        La jornada comprende desde las <strong>08:00 AM</strong> hasta las <strong>05:00 PM</strong>
                        (jornada diurna) o desde las <strong>05:00 PM</strong> hasta las <strong>08:00 AM</strong> del
                        día siguiente (jornada nocturna).
                    </p>
                    <div class="mb-4">
                        <label class="form-label fw-medium">Seleccionar Fecha de Jornada</label>
                        <input type="date" class="form-control" id="reportDate">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="action-btn secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="action-btn" id="btnGenerateReport">
                        <i class="bi bi-download"></i>
                        Generar Reporte
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- jsPDF para generación de PDFs -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>

    <!-- SweetAlert2 para alertas -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- JavaScript Optimizado -->
    <script>
        // Dashboard Reingenierizado - Centro Médico RS

        (function () {
            'use strict';

            // ==========================================================================
            // CONFIGURACIÓN Y CONSTANTES
            // ==========================================================================
            const CONFIG = {
                themeKey: 'dashboard-theme',
                sidebarKey: 'sidebar-collapsed',
                greetingKey: 'last-jornada-summary',
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
                sidebar: document.getElementById('sidebar'),
                sidebarToggle: document.getElementById('sidebarToggle'),
                sidebarToggleIcon: document.getElementById('sidebarToggleIcon'),
                sidebarOverlay: document.getElementById('sidebarOverlay'),
                mobileSidebarToggle: document.getElementById('mobileSidebarToggle'),
                greetingElement: document.getElementById('greeting-text'),
                currentTimeElement: document.getElementById('current-time'),
                reportDate: document.getElementById('reportDate'),
                btnGenerateReport: document.getElementById('btnGenerateReport')
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
            }

            // ==========================================================================
            // MANEJO DE BARRA LATERAL
            // ==========================================================================
            class SidebarManager {
                constructor() {
                    this.isCollapsed = this.getInitialState();
                    this.isMobile = window.innerWidth < 992;
                    this.setupEventListeners();
                    this.applyState();
                }

                getInitialState() {
                    if (this.isMobile) return false;
                    const savedState = localStorage.getItem(CONFIG.sidebarKey);
                    return savedState === 'true';
                }

                applyState() {
                    if (this.isCollapsed && !this.isMobile) {
                        DOM.sidebar.classList.add('collapsed');
                        if (DOM.sidebarToggleIcon) {
                            DOM.sidebarToggleIcon.classList.remove('bi-chevron-left');
                            DOM.sidebarToggleIcon.classList.add('bi-chevron-right');
                        }
                    } else {
                        DOM.sidebar.classList.remove('collapsed');
                        if (DOM.sidebarToggleIcon) {
                            DOM.sidebarToggleIcon.classList.remove('bi-chevron-right');
                            DOM.sidebarToggleIcon.classList.add('bi-chevron-left');
                        }
                    }
                }

                toggle() {
                    if (this.isMobile) {
                        this.toggleMobile();
                    } else {
                        this.toggleDesktop();
                    }
                }

                toggleDesktop() {
                    this.isCollapsed = !this.isCollapsed;
                    this.applyState();
                    localStorage.setItem(CONFIG.sidebarKey, this.isCollapsed);
                }

                toggleMobile() {
                    const isShowing = DOM.sidebar.classList.toggle('show');

                    if (isShowing) {
                        DOM.sidebarOverlay.classList.add('show');
                        DOM.body.style.overflow = 'hidden';
                    } else {
                        DOM.sidebarOverlay.classList.remove('show');
                        DOM.body.style.overflow = '';
                    }
                }

                closeMobile() {
                    DOM.sidebar.classList.remove('show');
                    DOM.sidebarOverlay.classList.remove('show');
                    DOM.body.style.overflow = '';
                }

                setupEventListeners() {
                    // Toggle escritorio
                    if (DOM.sidebarToggle) {
                        DOM.sidebarToggle.addEventListener('click', () => this.toggle());
                    }

                    // Toggle móvil
                    if (DOM.mobileSidebarToggle) {
                        DOM.mobileSidebarToggle.addEventListener('click', () => this.toggle());
                    }

                    // Overlay móvil
                    if (DOM.sidebarOverlay) {
                        DOM.sidebarOverlay.addEventListener('click', () => this.closeMobile());
                    }

                    // Cerrar sidebar al hacer clic en enlace (móvil)
                    const navLinks = DOM.sidebar.querySelectorAll('.nav-link');
                    navLinks.forEach(link => {
                        link.addEventListener('click', () => {
                            if (this.isMobile) this.closeMobile();
                        });
                    });

                    // Escuchar cambios de tamaño
                    window.addEventListener('resize', this.debounce(() => {
                        const wasMobile = this.isMobile;
                        this.isMobile = window.innerWidth < 992;

                        if (wasMobile !== this.isMobile) {
                            if (!this.isMobile) this.closeMobile();
                            this.applyState();
                        }
                    }, 250));
                }

                debounce(func, wait) {
                    let timeout;
                    return function executedFunction(...args) {
                        const later = () => {
                            clearTimeout(timeout);
                            func(...args);
                        };
                        clearTimeout(timeout);
                        timeout = setTimeout(later, wait);
                    };
                }
            }

            // ==========================================================================
            // COMPONENTES DINÁMICOS
            // ==========================================================================
            class DynamicComponents {
                constructor() {
                    this.setupGreeting();
                    this.setupClock();
                    this.setupReportGeneration();
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

                    DOM.greetingElement.textContent = greeting + ', ' + '<?php echo htmlspecialchars($user_name); ?>';
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

                setupReportGeneration() {
                    if (!DOM.btnGenerateReport || !DOM.reportDate) return;

                    // Configurar fecha por defecto (hoy)
                    const today = new Date().toISOString().split('T')[0];
                    DOM.reportDate.value = today;

                    DOM.btnGenerateReport.addEventListener('click', () => {
                        this.generateReport();
                    });
                }

                generateReport() {
                    const date = DOM.reportDate.value;
                    const btn = DOM.btnGenerateReport;

                    if (!date) {
                        Swal.fire('Error', 'Por favor seleccione una fecha', 'warning');
                        return;
                    }

                    // Estado de carga
                    btn.disabled = true;
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generando...';

                    fetch(`get_report_data.php?date=${date}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok ' + response.statusText);
                            }
                            return response.json();
                        })
                        .then(res => {
                            if (res.status === 'success') {
                                this.generatePDF(res);

                                // Cerrar modal
                                const modalElement = document.getElementById('reportModal');
                                const modalInstance = bootstrap.Modal.getInstance(modalElement);
                                if (modalInstance) {
                                    modalInstance.hide();
                                }

                                // Mostrar confirmación
                                Swal.fire({
                                    title: '¡Reporte Generado!',
                                    text: 'El reporte se ha descargado correctamente.',
                                    icon: 'success',
                                    confirmButtonText: 'Aceptar'
                                });
                            } else {
                                Swal.fire('Error', res.message || 'Error desconocido', 'error');
                            }
                        })
                        .catch(err => {
                            Swal.fire('Error', 'Hubo un problema: ' + err.message, 'error');
                        })
                        .finally(() => {
                            btn.disabled = false;
                            btn.innerHTML = originalText;
                        });
                }

                generatePDF(res) {
                    const { jsPDF } = window.jspdf;
                    const doc = new jsPDF();

                    // Colores
                    const primaryColor = [13, 110, 253]; // Azul primario

                    // Encabezado
                    doc.setFillColor(...primaryColor);
                    doc.rect(0, 0, 210, 40, 'F');

                    doc.setTextColor(255, 255, 255);
                    doc.setFontSize(22);
                    doc.setFont('helvetica', 'bold');
                    doc.text("Centro Médico RS", 105, 18, { align: 'center' });

                    doc.setFontSize(14);
                    doc.setFont('helvetica', 'normal');
                    doc.text("Reporte de Exámenes Clínicos", 105, 28, { align: 'center' });

                    // Información del reporte
                    doc.setTextColor(50, 50, 50);
                    doc.setFontSize(10);
                    doc.setFont('helvetica', 'bold');
                    doc.text("Información del Reporte:", 14, 50);

                    doc.setFont('helvetica', 'normal');
                    doc.text(`Jornada Reportada: ${res.metadata.jornada_start} - ${res.metadata.jornada_end}`, 14, 56);
                    doc.text(`Generado por: ${res.metadata.generated_by}`, 14, 62);
                    doc.text(`Fecha de Creación: ${res.metadata.generated_at}`, 14, 68);

                    // Tabla de datos
                    const tableBody = res.data.map(item => [
                        item.nombre_paciente,
                        item.tipo_examen,
                        new Date(item.fecha_examen).toLocaleString('es-GT', {
                            year: 'numeric',
                            month: '2-digit',
                            day: '2-digit',
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: true
                        }),
                        `Q${parseFloat(item.cobro).toFixed(2)}`
                    ]);

                    doc.autoTable({
                        startY: 75,
                        head: [['Paciente', 'Examen', 'Fecha y Hora', 'Cobro']],
                        body: tableBody,
                        theme: 'grid',
                        headStyles: {
                            fillColor: primaryColor,
                            textColor: [255, 255, 255],
                            fontStyle: 'bold'
                        },
                        columnStyles: {
                            0: { cellWidth: 50 },
                            1: { cellWidth: 50 },
                            2: { cellWidth: 45 },
                            3: { cellWidth: 25, halign: 'right' }
                        },
                        foot: [['', '', 'TOTAL ACUMULADO', `Q${res.total.toFixed(2)}`]],
                        footStyles: {
                            fillColor: [240, 240, 240],
                            textColor: [0, 0, 0],
                            fontStyle: 'bold',
                            halign: 'right'
                        }
                    });

                    // Guardar archivo
                    const fileName = `Reporte_Examenes_${DOM.reportDate.value}.pdf`;
                    doc.save(fileName);
                }

                setupAnimations() {
                    // Animar elementos al cargar
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

                    // Observar elementos con clase de animación
                    document.querySelectorAll('.welcome-card, .history-section').forEach(el => {
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
                const sidebarManager = new SidebarManager();
                const dynamicComponents = new DynamicComponents();

                // Exponer APIs necesarias globalmente
                window.dashboard = {
                    theme: themeManager,
                    sidebar: sidebarManager,
                    components: dynamicComponents
                };

                // Log de inicialización
                console.log('Historial de Exámenes CMS v4.0 inicializado correctamente');
                console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
                console.log('Rol: <?php echo htmlspecialchars($user_type); ?>');
                console.log('Tema: ' + themeManager.theme);
                console.log('Sidebar: ' + (sidebarManager.isCollapsed ? 'colapsado' : 'expandido'));
                console.log('Total de registros: <?php echo $total_registros; ?>');
            });

            // ==========================================================================
            // MANEJO DE ERRORES GLOBALES
            // ==========================================================================
            window.addEventListener('error', (event) => {
                console.error('Error en historial de exámenes:', event.error);
            });

        })();
    </script>
</body>

</html>