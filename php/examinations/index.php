<?php
// index.php - Registro de Exámenes - Centro Médico RS
// Versión: 4.5 - Estilo Dashboard Principal con Estadísticas
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
require_once '../../includes/module_guard.php';
require_once '../../includes/breadcrumbs.php';

check_module_access('imaging');

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

    // ============ CONSULTAS ESTADÍSTICAS PARA EXÁMENES ============

    // 1. Exámenes de hoy
    $id_hospital = $_SESSION['id_hospital'] ?? 0;

    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM examenes_realizados WHERE DATE(fecha_examen) = ? AND id_hospital = ?");
    $stmt->execute([$today, $id_hospital]);
    $today_exams = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $conn->prepare("SELECT SUM(cobro) as total FROM examenes_realizados WHERE DATE(fecha_examen) = ? AND id_hospital = ?");
    $stmt->execute([$today, $id_hospital]);
    $today_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_end = date('Y-m-d', strtotime('sunday this week'));
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM examenes_realizados WHERE DATE(fecha_examen) BETWEEN ? AND ? AND id_hospital = ?");
    $stmt->execute([$week_start, $week_end, $id_hospital]);
    $week_exams = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $conn->prepare("SELECT SUM(cobro) as total FROM examenes_realizados WHERE DATE(fecha_examen) BETWEEN ? AND ? AND id_hospital = ?");
    $stmt->execute([$week_start, $week_end, $id_hospital]);
    $week_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $month_start = date('Y-m-01');
    $month_end = date('Y-m-t');
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM examenes_realizados WHERE DATE(fecha_examen) BETWEEN ? AND ? AND id_hospital = ?");
    $stmt->execute([$month_start, $month_end, $id_hospital]);
    $month_exams = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM examenes_realizados WHERE id_hospital = ?");
    $stmt->execute([$id_hospital]);
    $total_exams = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $conn->prepare("
        SELECT id_examen_realizado, nombre_paciente, tipo_examen, cobro, fecha_examen 
        FROM examenes_realizados 
        WHERE id_hospital = ?
        ORDER BY fecha_examen DESC 
        LIMIT 5
    ");
    $stmt->execute([$id_hospital]);
    $recent_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt_patients = $conn->prepare("
        SELECT id_paciente, 
               CONCAT(nombre, ' ', apellido) as nombre_completo,
               telefono,
               fecha_nacimiento
        FROM pacientes 
        WHERE id_hospital = ?
        ORDER BY nombre_completo ASC
    ");
    $stmt_patients->execute([$id_hospital]);
    $patients = $stmt_patients->fetchAll(PDO::FETCH_ASSOC);

    // Título de la página
    $page_title = "Registro de Exámenes - Centro Médico RS";

} catch (Exception $e) {
    // Manejo de errores
    error_log("Error en registro de exámenes: " . $e->getMessage());
    die("Error al cargar el módulo de exámenes. Por favor, contacte al administrador.");
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <!-- META TAGS IDÉNTICOS AL DASHBOARD -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Registro de Exámenes - Centro Médico RS">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <!-- FAVICON Y FUENTES IDÉNTICAS -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Choices.js (para búsqueda en selects) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">

    <!-- Seguridad y Protección de Código -->
    <script src="../../assets/js/security.js"></script>

    <!-- CSS COMPLETAMENTE IDÉNTICO AL DASHBOARD (NO CAMBIAR NADA) -->
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
</head>

<body>
    <!-- Efecto de mármol animado -->
    <div class="marble-effect"></div>

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
                    <img src="../../assets/img/Logo.png" alt="Centro Médico RS" class="brand-logo" width="40" height="40">
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
            <?php render_breadcrumbs([
                ['label' => 'Dashboard', 'url' => '../dashboard/index.php'],
                ['label' => 'Exámenes'],
            ]); ?>
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
                            <i class="bi bi-clipboard2-pulse me-1"></i> Registro de Exámenes
                        </p>
                    </div>
                    <div class="d-none d-md-block">
                        <i class="bi bi-clipboard2-pulse-fill text-primary" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>

            <!-- Estadísticas principales -->
            <?php if ($user_type === 'admin'): ?>
                    <div class="stats-grid">
                        <!-- Exámenes de hoy -->
                        <div class="stat-card animate-in delay-1">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-title">Exámenes Hoy</div>
                                    <div class="stat-value"><?php echo $today_exams; ?></div>
                                </div>
                                <div class="stat-icon primary">
                                    <i class="bi bi-clipboard2-pulse"></i>
                                </div>
                            </div>
                            <div class="stat-change positive">
                                <i class="bi bi-arrow-up-right"></i>
                                <span>Realizados hoy</span>
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
                                <span>Total recaudado</span>
                            </div>
                        </div>

                        <!-- Exámenes de la semana -->
                        <div class="stat-card animate-in delay-3">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-title">Esta Semana</div>
                                    <div class="stat-value"><?php echo $week_exams; ?></div>
                                </div>
                                <div class="stat-icon warning">
                                    <i class="bi bi-calendar-week"></i>
                                </div>
                            </div>
                            <div class="stat-change positive">
                                <i class="bi bi-calendar-range"></i>
                                <span>Total de la semana</span>
                            </div>
                        </div>

                        <!-- Ingresos de la semana -->
                        <div class="stat-card animate-in delay-4">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-title">Ingresos Semana</div>
                                    <div class="stat-value">Q<?php echo number_format($week_revenue, 2); ?></div>
                                </div>
                                <div class="stat-icon info">
                                    <i class="bi bi-graph-up-arrow"></i>
                                </div>
                            </div>
                            <div class="stat-change positive">
                                <i class="bi bi-calendar-month"></i>
                                <span>Acumulado semanal</span>
                            </div>
                        </div>
                    </div>
            <?php endif; ?>

            <!-- Exámenes recientes -->
            <section class="appointments-section animate-in delay-2">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-clock-history section-title-icon"></i>
                        Exámenes Recientes
                    </h3>
                    <div class="d-flex gap-2">
                        <a href="historial_examenes.php" class="action-btn secondary">
                            <i class="bi bi-clock-history"></i>
                            Ver Historial
                        </a>
                        <button type="button" class="action-btn" onclick="refreshExams()">
                            <i class="bi bi-arrow-clockwise"></i>
                            Actualizar
                        </button>
                    </div>
                </div>
                Actualizar
                </button>
    </div>

    <?php if (count($recent_exams) > 0): ?>
            <div class="table-responsive">
                <table class="appointments-table">
                    <thead>
                        <tr>
                            <th>Paciente</th>
                            <th>Tipo de Examen</th>
                            <th>Costo</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_exams as $exam): ?>
                                <?php
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
                                        <div class="procedure-type">
                                            <?php echo htmlspecialchars($exam['tipo_examen']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="time-badge bg-success text-white">
                                            Q<?php echo number_format($exam['cobro'], 2); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="time-badge">
                                            <i class="bi bi-clock"></i>
                                            <?php echo date('d/m/Y H:i', strtotime($exam['fecha_examen'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="#" class="btn-icon edit" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="#" class="btn-icon history" title="Ver detalles">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-3 text-center">
                <a href="historial_examenes.php" class="text-primary text-decoration-none">
                    Ver todos los exámenes <i class="bi bi-arrow-right"></i>
                </a>
            </div>
    <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="bi bi-clipboard2-pulse"></i>
                </div>
                <h4 class="text-muted mb-2">No hay exámenes registrados</h4>
                <p class="text-muted mb-3">Total en sistema: <?php echo $total_exams; ?></p>
                <p class="text-muted">Complete el formulario para registrar su primer examen</p>
            </div>
    <?php endif; ?>
    </section>

    <!-- Resumen mensual -->
    <div class="stat-card animate-in delay-3">
        <div class="stat-header">
            <div>
                <div class="stat-title">Resumen del Mes Actual</div>
                <div class="stat-value"><?php echo $month_exams; ?> Exámenes</div>
                <div class="stat-change positive">
                    <i class="bi bi-calendar-month"></i>
                    <span>Mes de <?php echo date('F'); ?></span>
                </div>
            </div>
            <div class="stat-icon warning">
                <i class="bi bi-bar-chart-line"></i>
            </div>
        </div>
        <div class="mt-3">
            <p class="text-muted mb-2">Total acumulado en sistema: <strong><?php echo $total_exams; ?></strong> exámenes
            </p>
            <p class="text-muted mb-0">Sistema de registro de exámenes - Centro Médico RS</p>
        </div>
    </div>
    </main>
    </div>

    <!-- Choices.js JS -->
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

    <!-- JavaScript Optimizado -->
    <script>
        /**
         * Registro de Exámenes v4.5 - Reingenierizado
         * Centro Médico RS
         */
        'use strict';

        // ==========================================================================
        // CONFIGURACIÓN Y CONSTANTES
        // ==========================================================================
        const CONFIG = {
            themeKey: 'dashboard-theme',
            transitionDuration: 300,
            animationDelay: 100
        };

        // ==========================================================================
        // REFERENCIAS A ELEMENTOS DOM (Único y Centralizado)
        // ==========================================================================
        const DOM = {
            html: document.documentElement,
            body: document.body,
            themeSwitch: document.getElementById('themeSwitch'),
            greetingElement: document.getElementById('greeting-text'),
            currentTimeElement: document.getElementById('current-time'),
            patientSelect: document.getElementById('id_paciente'),
            patientInfo: document.getElementById('paciente_info'),
            examForm: document.getElementById('examForm'),
            dynamicExamsContainer: document.getElementById('dynamicExams'),
            btnAddExam: document.getElementById('btnAddExam'),
            dateInput: document.getElementById('fecha_examen'),
            nombrePacienteInput: document.getElementById('nombre_paciente')
        };

        // ==========================================================================
        // MANEJO DE TEMAS
        // ==========================================================================
        class ThemeManager {
            constructor() {
                this.theme = this.getInitialTheme();
                this.applyTheme(this.theme);
                this.setupEventListeners();
            }

            getInitialTheme() {
                return localStorage.getItem(CONFIG.themeKey) ||
                    (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            }

            applyTheme(theme) {
                DOM.html.setAttribute('data-theme', theme);
                localStorage.setItem(CONFIG.themeKey, theme);
                const metaTheme = document.querySelector('meta[name="theme-color"]');
                if (metaTheme) metaTheme.setAttribute('content', theme === 'dark' ? '#0f172a' : '#ffffff');
            }

            toggleTheme() {
                this.theme = this.theme === 'light' ? 'dark' : 'light';
                this.applyTheme(this.theme);
                if (DOM.themeSwitch) {
                    DOM.themeSwitch.style.transform = 'rotate(180deg)';
                    setTimeout(() => DOM.themeSwitch.style.transform = 'rotate(0)', CONFIG.transitionDuration);
                }
            }

            setupEventListeners() {
                if (DOM.themeSwitch) DOM.themeSwitch.addEventListener('click', () => this.toggleTheme());
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                    if (!localStorage.getItem(CONFIG.themeKey)) this.applyTheme(e.matches ? 'dark' : 'light');
                });
            }
        }

        // ==========================================================================
        // COMPONENTES DINÁMICOS
        // ==========================================================================
        class DynamicComponents {
            constructor() {
                this.setupClock();
                this.setupPatientSearch();
                this.setupExamHandlers();
                this.setupFormHandlers();
                this.setupAnimations();
                this.updateGreeting();
            }

            updateGreeting() {
                if (!DOM.greetingElement) return;
                const hour = new Date().getHours();
                let greeting = 'Buenos días';
                if (hour >= 12 && hour < 19) greeting = 'Buenas tardes';
                else if (hour >= 19 || hour < 5) greeting = 'Buenas noches';
                DOM.greetingElement.textContent = greeting;
            }

            setupClock() {
                if (!DOM.currentTimeElement) return;
                const update = () => {
                    DOM.currentTimeElement.textContent = new Date().toLocaleTimeString('es-GT', {
                        hour: '2-digit', minute: '2-digit', hour12: false
                    });
                };
                update();
                setInterval(update, 60000);
            }

            setupPatientSearch() {
                if (!DOM.patientSelect || !DOM.patientInfo) return;

                const choices = new Choices(DOM.patientSelect, {
                    searchEnabled: true,
                    itemSelectText: '',
                    removeItemButton: true,
                    placeholder: true,
                    placeholderValue: 'Buscar paciente...',
                    noResultsText: 'No se encontraron resultados',
                    shouldSort: false,
                });

                const updateCard = (value) => {
                    if (!value) {
                        DOM.patientInfo.innerHTML = '<small class="text-muted">Seleccione un paciente para ver su información</small>';
                        if (DOM.nombrePacienteInput) DOM.nombrePacienteInput.value = '';
                        return;
                    }

                    const option = Array.from(DOM.patientSelect.options).find(opt => opt.value == value);
                    if (option) {
                        const nombre = option.dataset.nombre || option.text;
                        const initials = nombre.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
                        if (DOM.nombrePacienteInput) DOM.nombrePacienteInput.value = nombre;

                        DOM.patientInfo.innerHTML = `
                        <div class="d-flex align-items-center gap-3 animate-in">
                            <div class="patient-avatar-sm" style="width: 40px; height: 40px; border-radius: 50%; background: var(--color-primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.875rem;">
                                ${initials}
                            </div>
                            <div>
                                <div class="fw-bold" style="color: var(--color-text);">${nombre}</div>
                                <div class="text-muted small">
                                    <i class="bi bi-person me-1"></i> ${option.dataset.edad || 'N/A'} años • 
                                    <i class="bi bi-telephone me-1"></i> ${option.dataset.telefono || 'N/A'}
                                </div>
                            </div>
                        </div>
                    `;
                    }
                };

                DOM.patientSelect.addEventListener('addItem', (e) => updateCard(e.detail.value));
                DOM.patientSelect.addEventListener('removeItem', () => updateCard(''));
                DOM.patientSelect.addEventListener('change', function () { updateCard(this.value); });
            }

            setupExamHandlers() {
                if (!DOM.btnAddExam || !DOM.dynamicExamsContainer) return;
                DOM.btnAddExam.addEventListener('click', () => {
                    const row = document.createElement('div');
                    row.className = 'input-group mb-2 animate-in';
                    row.innerHTML = `
                    <span class="input-group-text"><i class="bi bi-clipboard2-pulse"></i></span>
                    <input class="form-control" name="tipo_examen[]" placeholder="Especificar otro examen..." required>
                    <button type="button" class="btn btn-outline-danger" onclick="this.closest('.input-group').remove()"><i class="bi bi-trash"></i></button>
                `;
                    DOM.dynamicExamsContainer.appendChild(row);
                    row.querySelector('input').focus();
                });
            }

            setupFormHandlers() {
                if (!DOM.dateInput) return;
                const now = new Date();
                const localDateTime = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
                DOM.dateInput.value = localDateTime;
            }

            setupAnimations() {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('animate-in');
                            observer.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.1 });
                document.querySelectorAll('.stat-card, .form-container, .appointments-section').forEach(el => observer.observe(el));
            }
        }

        // ==========================================================================
        // INICIALIZACIÓN GLOBAL
        // ==========================================================================
        document.addEventListener('DOMContentLoaded', () => {
            window.APP = {
                theme: new ThemeManager(),
                components: new DynamicComponents()
            };
        });

        // Helper global para recargar
        window.refreshExams = () => window.location.reload();

        // Estilos para spinner y animaciones
        (function () {
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
        })();
    </script>
</body>

</html>

<?php
// Función helper para calcular edad
function calculateAge($birthDate)
{
    if (!$birthDate)
        return 'N/A';
    try {
        $birth = new DateTime($birthDate);
        $today = new DateTime();
        return $today->diff($birth)->y;
    } catch (Exception $e) {
        return 'N/A';
    }
}
?>