<?php
// historial_procedimientos.php - Historial de Procedimientos Menores - Centro Médico Herrera Saenz
// Diseño Responsive, Barra Lateral Moderna, Efecto Mármol
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

// Título de la página
$page_title = "Historial de Procedimientos - Centro Médico Herrera Saenz";

// Configuración de paginación
$limit = 20; // Registros por página
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page > 1) ? ($page - 1) * $limit : 0;

try {
    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener información del usuario
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['tipoUsuario'];
    $user_name = $_SESSION['nombre'];
    $user_specialty = $_SESSION['especialidad'] ?? 'Profesional Médico';
    $id_hospital = (int) ($_SESSION['id_hospital'] ?? 0);

    // Obtener total de registros
    $stmt_count = $conn->prepare("SELECT COUNT(*) as total FROM procedimientos_menores WHERE id_hospital = ?");
    $stmt_count->execute([$id_hospital]);
    $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    $total_paginas = ceil($total_registros / $limit);

    // Obtener procedimientos paginados
    $stmt = $conn->prepare("
        SELECT id_procedimiento, nombre_paciente, procedimiento, cobro, fecha_procedimiento 
        FROM procedimientos_menores 
        WHERE id_hospital = ?
        ORDER BY fecha_procedimiento DESC 
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute([$id_hospital]);
    $procedimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Estadísticas adicionales
    $today = date('Y-m-d');
    $stmt_today = $conn->prepare("SELECT SUM(cobro) as total FROM procedimientos_menores WHERE DATE(fecha_procedimiento) = ? AND id_hospital = ?");
    $stmt_today->execute([$today, $id_hospital]);
    $today_revenue = $stmt_today->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_end = date('Y-m-d', strtotime('sunday this week'));
    $stmt_week = $conn->prepare("SELECT SUM(cobro) as total FROM procedimientos_menores WHERE DATE(fecha_procedimiento) BETWEEN ? AND ? AND id_hospital = ?");
    $stmt_week->execute([$week_start, $week_end, $id_hospital]);
    $week_revenue = $stmt_week->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

} catch (Exception $e) {
    // Manejo de errores
    error_log("Error en historial de procedimientos: " . $e->getMessage());
    $procedimientos = [];
    $total_paginas = 1;
    $today_revenue = 0;
    $week_revenue = 0;
    error_log('Error en historial_procedimientos: ' . $e->getMessage());
    $error_message = "Error al cargar el historial: Error del servidor.";
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Historial de Procedimientos Menores - Centro Médico Herrera Saenz">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <!-- logo -->
    <link rel="icon" type="image/png" href="../../assets/img/cmhs.png">

    <!-- Google Fonts - Inter (moderno y legible) -->
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- CSS Crítico (incrustado para máxima velocidad) -->
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

                <!-- logo -->
                <div class="brand-container">
                    <img src="../../assets/img/cmhs.png" alt="Centro Médico Herrera Saenz" class="brand-logo" width="40"
                        height="40">
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
                            <i class="bi bi-clock-history me-1"></i> Historial de Procedimientos
                        </p>
                    </div>
                    <div class="d-none d-md-block">
                        <i class="bi bi-clock-history text-primary" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>

            <!-- Estadísticas principales -->
            <?php if ($user_type === 'admin'): ?>
                    <div class="stats-grid">
                        <!-- Total de procedimientos -->
                        <div class="stat-card animate-in delay-1">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-title">Total Registros</div>
                                    <div class="stat-value"><?php echo $total_registros; ?></div>
                                </div>
                                <div class="stat-icon primary">
                                    <i class="bi bi-bandaid"></i>
                                </div>
                            </div>
                            <div class="stat-change positive">
                                <i class="bi bi-arrow-up-right"></i>
                                <span>Total en sistema</span>
                            </div>
                        </div>

                        <!-- Ingresos de hoy -->
                        <div class="stat-card animate-in delay-2">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-title">Ingresos Hoy</div>
                                    <div class="stat-value">Q<?php echo number_format($today_revenue, 2); ?></div>
                                </div>
                                <div class="stat-icon success">
                                    <i class="bi bi-currency-dollar"></i>
                                </div>
                            </div>
                            <div class="stat-change positive">
                                <i class="bi bi-cash-stack"></i>
                                <span>Recaudado hoy</span>
                            </div>
                        </div>

                        <!-- Ingresos de la semana -->
                        <div class="stat-card animate-in delay-3">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-title">Ingresos Semana</div>
                                    <div class="stat-value">Q<?php echo number_format($week_revenue, 2); ?></div>
                                </div>
                                <div class="stat-icon warning">
                                    <i class="bi bi-calendar-week"></i>
                                </div>
                            </div>
                            <div class="stat-change positive">
                                <i class="bi bi-calendar-range"></i>
                                <span>Esta semana</span>
                            </div>
                        </div>

                        <!-- Página actual -->
                        <div class="stat-card animate-in delay-4">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-title">Página Actual</div>
                                    <div class="stat-value"><?php echo $page; ?>/<?php echo $total_paginas; ?></div>
                                </div>
                                <div class="stat-icon info">
                                    <i class="bi bi-file-text"></i>
                                </div>
                            </div>
                            <div class="stat-change positive">
                                <i class="bi bi-book"></i>
                                <span>Mostrando <?php echo $limit; ?> por página</span>
                            </div>
                        </div>
                    </div>
            <?php endif; ?>

            <!-- Historial de procedimientos -->
            <section class="appointments-section animate-in delay-1">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-clock-history section-title-icon"></i>
                        Historial de Procedimientos
                    </h3>
                    <div class="d-flex gap-2">
                        <a href="index.php" class="action-btn secondary">
                            <i class="bi bi-arrow-left"></i>
                            <span>Regresar</span>
                        </a>
                        <?php if ($user_type === 'admin'): ?>
                                <button type="button" class="action-btn" id="btnGenerateReport">
                                    <i class="bi bi-file-earmark-pdf"></i>
                                    <span>Reporte PDF</span>
                                </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger border-0 mb-4" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                <?php endif; ?>

                <?php if (empty($procedimientos)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="bi bi-bandaid"></i>
                            </div>
                            <h4 class="text-muted mb-2">No se encontraron registros</h4>
                            <p class="text-muted mb-3">No hay procedimientos registrados en el sistema.</p>
                            <a href="index.php" class="action-btn">
                                <i class="bi bi-plus-lg"></i>
                                Registrar primer procedimiento
                            </a>
                        </div>
                <?php else: ?>
                        <div class="table-responsive">
                            <table class="appointments-table">
                                <thead>
                                    <tr>
                                        <th>Paciente</th>
                                        <th>Tipo de Procedimiento</th>
                                        <th>Cobro</th>
                                        <th>Fecha y Hora</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $prev_jornada = null;
                                    foreach ($procedimientos as $proc):
                                        // Calcular fecha de jornada (Si es antes de las 8am, pertenece al día anterior)
                                        $timestamp = strtotime($proc['fecha_procedimiento']);
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
                                        $patient_name = htmlspecialchars($proc['nombre_paciente']);
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
                                                    <span
                                                        class="procedure-type"><?php echo htmlspecialchars($proc['procedimiento']); ?></span>
                                                </td>
                                                <td>
                                                    <span class="time-badge bg-success text-white">
                                                        Q<?php echo number_format($proc['cobro'], 2); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="time-badge">
                                                        <i class="bi bi-clock"></i>
                                                        <?php echo date('h:i A', strtotime($proc['fecha_procedimiento'])); ?>
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
                                            <span class="page-link"><?php echo $page; ?></span>
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
                <?php endif; ?>
            </section>

            <!-- Resumen informativo -->
            <div class="stat-card animate-in delay-2">
                <div class="stat-header">
                    <div>
                        <div class="stat-title">Resumen del Sistema</div>
                        <div class="stat-value"><?php echo $total_registros; ?> Registros</div>
                        <div class="stat-change positive">
                            <i class="bi bi-database"></i>
                            <span>Base de datos activa</span>
                        </div>
                    </div>
                    <div class="stat-icon warning">
                        <i class="bi bi-bar-chart-line"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-muted mb-2">Mostrando página <?php echo $page; ?> de <?php echo $total_paginas; ?>
                        (<?php echo $limit; ?> registros por página)</p>
                    <p class="text-muted mb-2">Ingresos acumulados hoy:
                        <strong>Q<?php echo number_format($today_revenue, 2); ?></strong>
                    </p>
                    <p class="text-muted mb-0">Ingresos acumulados esta semana:
                        <strong>Q<?php echo number_format($week_revenue, 2); ?></strong>
                    </p>
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript Optimizado -->
    <script>
        // Historial de Procedimientos Reingenierizado - Centro Médico Herrera Saenz

        (function () {
            'use strict';

            // ==========================================================================
            // CONFIGURACIÓN Y CONSTANTES
            // ==========================================================================
            const CONFIG = {
                themeKey: 'dashboard-theme',
                sidebarKey: 'sidebar-collapsed',
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
                    this.setupReportHandler();
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

                setupReportHandler() {
                    if (!DOM.btnGenerateReport) return;

                    DOM.btnGenerateReport.addEventListener('click', () => {
                        this.generateReport();
                    });
                }

                generateReport() {
                    const btn = DOM.btnGenerateReport;

                    // Estado de carga
                    btn.disabled = true;
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generando...';

                    // Obtener fecha actual
                    const today = new Date().toISOString().split('T')[0];

                    // En un sistema real, aquí se haría una petición fetch para generar el reporte
                    // Simulamos la generación del reporte
                    setTimeout(() => {
                        // Crear PDF con jsPDF
                        this.createPDFReport(today);

                        // Restaurar botón
                        btn.disabled = false;
                        btn.innerHTML = originalText;

                        // Mostrar notificación
                        this.showNotification('Reporte generado exitosamente', 'success');
                    }, 2000);
                }

                createPDFReport(date) {
                    // En un sistema real, se usaría una biblioteca como jsPDF
                    // Aquí simulamos la descarga de un archivo

                    // Crear contenido del reporte
                    const reportContent = `
                    Reporte de Procedimientos Menores
                    Centro Médico Herrera Saenz
                    Fecha: ${date}
                    ========================================
                    
                    Total de procedimientos: <?php echo $total_registros; ?>
                    Ingresos hoy: Q<?php echo number_format($today_revenue, 2); ?>
                    Ingresos esta semana: Q<?php echo number_format($week_revenue, 2); ?>
                    
                    Últimos procedimientos:
                    ------------------------
                    <?php
                    $count = 0;
                    foreach ($procedimientos as $proc):
                        if ($count++ >= 10)
                            break;
                        echo date('d/m/Y H:i', strtotime($proc['fecha_procedimiento'])) . ' - ' .
                            htmlspecialchars($proc['nombre_paciente']) . ' - ' .
                            htmlspecialchars($proc['procedimiento']) . ' - Q' .
                            number_format($proc['cobro'], 2) . "\\n";
                    endforeach;
                    ?>
                    
                    Generado por: <?php echo htmlspecialchars($user_name); ?>
                    Fecha de generación: <?php echo date('d/m/Y H:i'); ?>
                `;

                    // Crear blob y descargar
                    const blob = new Blob([reportContent], { type: 'text/plain' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `reporte_procedimientos_${date}.txt`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
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
                    document.querySelectorAll('.stat-card, .appointments-section').forEach(el => {
                        observer.observe(el);
                    });
                }

                showNotification(message, type = 'info') {
                    // Crear elemento de notificación
                    const notification = document.createElement('div');
                    notification.className = `alert alert-${type} border-0 shadow-lg`;
                    notification.style.position = 'fixed';
                    notification.style.top = '20px';
                    notification.style.right = '20px';
                    notification.style.zIndex = '9999';
                    notification.style.minWidth = '300px';
                    notification.style.animation = 'fadeInUp 0.3s ease-out';
                    notification.innerHTML = `
                    <div class="d-flex align-items-center">
                        <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                        <div>${message}</div>
                        <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
                    </div>
                `;

                    // Agregar al documento
                    document.body.appendChild(notification);

                    // Remover automáticamente después de 5 segundos
                    setTimeout(() => {
                        if (notification.parentElement) {
                            notification.style.animation = 'fadeInUp 0.3s ease-out reverse';
                            setTimeout(() => notification.remove(), 300);
                        }
                    }, 5000);
                }
            }

            // ==========================================================================
            // OPTIMIZACIONES DE RENDIMIENTO
            // ==========================================================================
            class PerformanceOptimizer {
                constructor() {
                    this.setupLazyLoading();
                    this.setupServiceWorker();
                    this.setupAnalytics();
                }

                setupLazyLoading() {
                    if ('IntersectionObserver' in window) {
                        const lazyImages = document.querySelectorAll('img[data-src]');

                        const imageObserver = new IntersectionObserver((entries) => {
                            entries.forEach(entry => {
                                if (entry.isIntersecting) {
                                    const img = entry.target;
                                    img.src = img.dataset.src;
                                    img.removeAttribute('data-src');
                                    imageObserver.unobserve(img);
                                }
                            });
                        });

                        lazyImages.forEach(img => imageObserver.observe(img));
                    }
                }

                setupServiceWorker() {
                    if ('serviceWorker' in navigator) {
                        window.addEventListener('load', () => {
                            navigator.serviceWorker.register('/sw.js').catch(error => {
                                console.log('ServiceWorker registration failed:', error);
                            });
                        });
                    }
                }

                setupAnalytics() {
                    // Aquí iría la configuración de Google Analytics u otro sistema de análisis
                    console.log('Historial de Procedimientos cargado - Usuario: <?php echo htmlspecialchars($user_name); ?>');
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
                const performanceOptimizer = new PerformanceOptimizer();

                // Exponer APIs necesarias globalmente
                window.historialProcedimientos = {
                    theme: themeManager,
                    sidebar: sidebarManager,
                    components: dynamicComponents
                };

                // Log de inicialización
                console.log('Historial de Procedimientos v4.0 inicializado correctamente');
                console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
                console.log('Rol: <?php echo htmlspecialchars($user_type); ?>');
                console.log('Tema: ' + themeManager.theme);
                console.log('Sidebar: ' + (sidebarManager.isCollapsed ? 'colapsado' : 'expandido'));
                console.log('Total de registros: <?php echo $total_registros; ?>');
                console.log('Página actual: <?php echo $page; ?> de <?php echo $total_paginas; ?>');
            });

            // ==========================================================================
            // MANEJO DE ERRORES GLOBALES
            // ==========================================================================
            window.addEventListener('error', (event) => {
                console.error('Error en historial de procedimientos:', event.error);

                // En producción, enviar error al servidor
                if (window.location.hostname !== 'localhost') {
                    const errorData = {
                        message: event.message,
                        source: event.filename,
                        lineno: event.lineno,
                        colno: event.colno,
                        user: '<?php echo htmlspecialchars($user_name); ?>',
                        timestamp: new Date().toISOString(),
                        module: 'historial_procedimientos'
                    };

                    // Aquí iría una petición fetch para enviar el error al servidor
                    console.log('Error reportado:', errorData);
                }
            });

            // ==========================================================================
            // POLYFILLS PARA NAVEGADORES ANTIGUOS
            // ==========================================================================
            if (!NodeList.prototype.forEach) {
                NodeList.prototype.forEach = Array.prototype.forEach;
            }

            if (!Element.prototype.matches) {
                Element.prototype.matches =
                    Element.prototype.matchesSelector ||
                    Element.prototype.mozMatchesSelector ||
                    Element.prototype.msMatchesSelector ||
                    Element.prototype.oMatchesSelector ||
                    Element.prototype.webkitMatchesSelector ||
                    function (s) {
                        const matches = (this.document || this.ownerDocument).querySelectorAll(s);
                        let i = matches.length;
                        while (--i >= 0 && matches.item(i) !== this) { }
                        return i > -1;
                    };
            }

        })();

        // Estilos para spinner
        const style = document.createElement('style');
        style.textContent = `
        .spinner-border {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            vertical-align: text-bottom;
            border: 0.2em solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            animation: spinner-border .75s linear infinite;
        }
        @keyframes spinner-border {
            to { transform: rotate(360deg); }
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    `;
        document.head.appendChild(style);
    </script>
</body>

</html>