<?php
// print_receipt.php - Recibo de Cobro - Centro Médico RS
// Diseño Responsive, Barra Lateral Moderna, Efecto Mármol
session_start();

// Verificar sesión activa
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';



verify_session();

// Verificar si se proporciona ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de cobro inválido");
}

$id_cobro = $_GET['id'];

try {
    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener datos del cobro con información del paciente
    $id_hospital = $_SESSION['id_hospital'] ?? 0;

    $stmt = $conn->prepare("
        SELECT c.*, CONCAT(p.nombre, ' ', p.apellido) as nombre_paciente, 
               p.id_paciente, p.fecha_nacimiento, p.genero, p.telefono, p.direccion
        FROM cobros c
        JOIN pacientes p ON c.paciente_cobro = p.id_paciente
        WHERE c.in_cobro = ? AND c.id_hospital = ?
    ");
    $stmt->execute([$id_cobro, $id_hospital]);
    $cobro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cobro) {
        die("Cobro no encontrado");
    }

    // Obtener información del usuario para el dashboard
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['tipoUsuario'];
    $user_name = $_SESSION['nombre'];
    $user_specialty = $_SESSION['especialidad'] ?? 'Profesional Médico';

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Formatear fecha
$fecha = new DateTime($cobro['fecha_consulta']);
$fecha_formateada = $fecha->format('d/m/Y');

// Calcular edad
if ($cobro['fecha_nacimiento']) {
    $fecha_nac = new DateTime($cobro['fecha_nacimiento']);
    $hoy = new DateTime();
    $edad = $hoy->diff($fecha_nac)->y;
} else {
    $edad = 'N/A';
}

// Procesar envío del formulario para programar cita
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        isset($_POST['action']) && $_POST['action'] === 'schedule' &&
        isset($_POST['fecha_cita']) && isset($_POST['hora_cita'])
    ) {
        try {
            // Necesitamos un ID de doctor. Usamos el primer doctor/admin disponible
            $stmt_doc = $conn->query("SELECT id_usuario FROM usuarios WHERE tipoUsuario IN ('admin', 'doc') LIMIT 1");
            $default_doc = $stmt_doc->fetch(PDO::FETCH_ASSOC);
            $id_doctor = $default_doc['id_usuario'] ?? 1;

            $stmt = $conn->prepare("
                INSERT INTO citas (id_paciente, id_doctor, fecha_cita, hora_cita, estado, motivo) 
                VALUES (?, ?, ?, ?, 'Pendiente', 'Seguimiento de consulta')
            ");
            $stmt->execute([
                $cobro['id_paciente'],
                $id_doctor,
                $_POST['fecha_cita'],
                $_POST['hora_cita']
            ]);

            $mensaje = '<div class="alert-card mb-4 animate-in" style="border-left: 4px solid var(--color-success);">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 1.25rem;"></i>
                    <span>Nueva cita agendada correctamente.</span>
                </div>
            </div>';
        } catch (Exception $e) {
            $mensaje = '<div class="alert-card mb-4 animate-in" style="border-left: 4px solid var(--color-danger);">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 1.25rem;"></i>
                    <span>Error al agendar la cita: ' . htmlspecialchars($e->getMessage()) . '</span>
                </div>
            </div>';
        }
    }
}

// Título de la página
$page_title = "Recibo de Cobro #" . str_pad($id_cobro, 5, '0', STR_PAD_LEFT) . " - Centro Médico RS";
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Recibo de Cobro - Centro Médico RS - Comprobante de pago médico">
    <title><?php echo $page_title; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">

    <!-- Google Fonts - Inter (moderno y legible) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@700&display=swap"
        rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- CSS Crítico (incrustado para máxima velocidad) -->
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
</head>

<body>
    <!-- Efecto de mármol animado -->
    <div class="marble-effect"></div>

    <!-- Overlay para sidebar móvil -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

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
            <!-- Botón de regreso -->
            <a href="index.php" class="back-button no-print animate-in">
                <i class="bi bi-arrow-left"></i>
                Volver a Cobros
            </a>

            <!-- Notificación -->
            <?php if (!empty($mensaje)): ?>
                    <?php echo $mensaje; ?>
            <?php endif; ?>

            <!-- Recibo de cobro -->
            <div class="receipt-container animate-in delay-1">
                <!-- Marca de agua -->
                <div class="watermark">HERRERA SAENZ</div>

                <!-- Encabezado de la clínica -->
                <header class="receipt-header">
                    <div class="logo-section">
                        <img src="../../assets/img/Logo.png" alt="Centro Médico RS" class="clinic-logo">
                    </div>
                    <div class="clinic-info">
                        Dirección de prueba<br>
                        Tel: (+502) 4195-8112<br>
                    </div>
                </header>

                <!-- Información del paciente -->
                <section class="patient-info-section">
                    <div class="info-item">
                        <span class="info-label">Paciente</span>
                        <span class="info-value"><?php echo htmlspecialchars($cobro['nombre_paciente']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Fecha</span>
                        <span class="info-value"><?php echo $fecha_formateada; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Edad / Género</span>
                        <span class="info-value"><?php echo $edad; ?> años /
                            <?php echo htmlspecialchars($cobro['genero'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">ID de Cobro</span>
                        <span class="info-value">#REC-<?php echo str_pad($id_cobro, 5, '0', STR_PAD_LEFT); ?></span>
                    </div>
                </section>

                <!-- Contenido principal -->
                <main class="receipt-content">
                    <h2 class="receipt-title">Detalle de Recaudación</h2>
                    <table class="receipt-table">
                        <thead>
                            <tr>
                                <th style="width: 70%;">Descripción</th>
                                <th style="width: 30%; text-align: right;">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Consulta Médica General</td>
                                <td>Q<?php echo number_format($cobro['cantidad_consulta'], 2); ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="total-section">
                        <div class="total-box">
                            <div class="total-label">Total a Pagar</div>
                            <div class="total-amount">Q<?php echo number_format($cobro['cantidad_consulta'], 2); ?>
                            </div>
                        </div>
                    </div>
                </main>

                <!-- Pie de página -->
                <footer class="receipt-footer">
                    <div class="legal-note">
                        <strong>Información Importante:</strong><br>
                        Este recibo es un comprobante de pago por servicios médicos prestados.
                        Para cualquier aclaración, favor de presentar este documento original.
                        Documento generado por Centro Médico RS Management System.
                    </div>
                    <div class="thank-you">
                        <h4 style="margin: 0; font-size: 16px;">¡Gracias por su preferencia!</h4>
                        <p style="margin: 5px 0 0; font-size: 13px;">Recupérese pronto.</p>
                    </div>
                </footer>
            </div>
        </main>
    </div>

    <!-- JavaScript Optimizado -->
    <script>
        // Recibo de Cobro Reingenierizado - Centro Médico RS

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
                tabButtons: document.querySelectorAll('.tab-button'),
                tabContents: document.querySelectorAll('.tab-content')
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
            // MANEJO DE PESTAÑAS
            // ==========================================================================
            class TabManager {
                constructor() {
                    this.setupEventListeners();
                }

                setupEventListeners() {
                    if (DOM.tabButtons) {
                        DOM.tabButtons.forEach(button => {
                            button.addEventListener('click', () => this.switchTab(button));
                        });
                    }
                }

                switchTab(activeButton) {
                    const tabId = activeButton.getAttribute('data-tab');

                    // Remover clase active de todos los botones y contenidos
                    DOM.tabButtons.forEach(btn => btn.classList.remove('active'));
                    DOM.tabContents.forEach(content => content.classList.remove('active'));

                    // Agregar clase active al botón clickeado
                    activeButton.classList.add('active');

                    // Mostrar el contenido correspondiente
                    document.getElementById(`${tabId}-tab`).classList.add('active');
                }
            }

            // ==========================================================================
            // FUNCIONALIDADES DE IMPRESIÓN
            // ==========================================================================
            class PrintManager {
                constructor() {
                    this.setupPrintButton();
                }

                setupPrintButton() {
                    // Asegurar que el botón de impresión funcione
                    const printButtons = document.querySelectorAll('button[onclick*="print"]');
                    printButtons.forEach(button => {
                        button.addEventListener('click', () => this.printReceipt());
                    });
                }

                printReceipt() {
                    // Mostrar mensaje de preparación
                    Swal.fire({
                        title: 'Preparando impresión',
                        text: 'El recibo se está preparando para imprimir...',
                        icon: 'info',
                        showConfirmButton: false,
                        timer: 1500,
                        background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                        color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#e2e8f0' : '#1a1a1a'
                    }).then(() => {
                        window.print();
                    });
                }
            }

            // ==========================================================================
            // VALIDACIÓN DE FORMULARIO
            // ==========================================================================
            class FormValidator {
                constructor() {
                    this.setupFormValidation();
                }

                setupFormValidation() {
                    const appointmentForm = document.querySelector('.appointment-form');
                    if (appointmentForm) {
                        appointmentForm.addEventListener('submit', (e) => this.validateForm(e));
                    }
                }

                validateForm(e) {
                    const fechaInput = document.getElementById('fecha_cita');
                    const horaInput = document.getElementById('hora_cita');

                    // Validar que la fecha sea hoy o en el futuro
                    const today = new Date().toISOString().split('T')[0];
                    if (fechaInput.value < today) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Fecha inválida',
                            text: 'La fecha de la cita debe ser hoy o en el futuro.',
                            icon: 'warning',
                            confirmButtonColor: 'var(--color-primary)',
                            background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                            color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#e2e8f0' : '#1a1a1a'
                        });
                        fechaInput.focus();
                        return false;
                    }

                    // Validar que la hora esté en horario laboral (ejemplo: 8:00 - 18:00)
                    const hora = parseInt(horaInput.value.split(':')[0]);
                    if (hora < 8 || hora > 18) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Hora inválida',
                            text: 'El horario de atención es de 8:00 a 18:00 horas.',
                            icon: 'warning',
                            confirmButtonColor: 'var(--color-primary)',
                            background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                            color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#e2e8f0' : '#1a1a1a'
                        });
                        horaInput.focus();
                        return false;
                    }

                    // Si todo está bien, mostrar confirmación
                    Swal.fire({
                        title: 'Confirmar cita',
                        html: `¿Desea agendar la cita para el <strong>${fechaInput.value}</strong> a las <strong>${horaInput.value}</strong>?`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, agendar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: 'var(--color-primary)',
                        background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                        color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#e2e8f0' : '#1a1a1a'
                    }).then((result) => {
                        if (!result.isConfirmed) {
                            e.preventDefault();
                        }
                    });
                }
            }

            // ==========================================================================
            // ANIMACIONES Y EFECTOS VISUALES
            // ==========================================================================
            class AnimationManager {
                constructor() {
                    this.setupAnimations();
                    this.setupReceiptEffects();
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
                    document.querySelectorAll('.receipt-container, .action-panel').forEach(el => {
                        observer.observe(el);
                    });
                }

                setupReceiptEffects() {
                    // Efecto de elevación al pasar el mouse sobre el recibo
                    const receipt = document.querySelector('.receipt-container');
                    if (receipt) {
                        receipt.addEventListener('mouseenter', () => {
                            receipt.style.transform = 'translateY(-10px)';
                            receipt.style.boxShadow = 'var(--shadow-xl)';
                        });

                        receipt.addEventListener('mouseleave', () => {
                            receipt.style.transform = 'translateY(0)';
                            receipt.style.boxShadow = 'var(--shadow-lg)';
                        });
                    }
                }
            }

            // ==========================================================================
            // INICIALIZACIÓN DE LA APLICACIÓN
            // ==========================================================================
            document.addEventListener('DOMContentLoaded', () => {
                // Inicializar componentes
                const themeManager = new ThemeManager();
                const sidebarManager = new SidebarManager();
                const tabManager = new TabManager();
                const printManager = new PrintManager();
                const formValidator = new FormValidator();
                const animationManager = new AnimationManager();

                // Exponer APIs necesarias globalmente
                window.receiptModule = {
                    theme: themeManager,
                    sidebar: sidebarManager,
                    tabs: tabManager,
                    print: printManager,
                    forms: formValidator
                };

                // Configurar fecha mínima para el formulario de cita
                const fechaCitaInput = document.getElementById('fecha_cita');
                if (fechaCitaInput) {
                    fechaCitaInput.min = new Date().toISOString().split('T')[0];
                }

                // Configurar hora por defecto (próxima hora disponible)
                const horaCitaInput = document.getElementById('hora_cita');
                if (horaCitaInput) {
                    const now = new Date();
                    const nextHour = now.getHours() + 1;
                    horaCitaInput.value = `${nextHour.toString().padStart(2, '0')}:00`;
                }

                // Log de inicialización
                console.log('Recibo de Cobro CMS inicializado correctamente');
                console.log('ID de Cobro: <?php echo $id_cobro; ?>');
                console.log('Paciente: <?php echo htmlspecialchars($cobro['nombre_paciente']); ?>');
                console.log('Monto: Q<?php echo number_format($cobro['cantidad_consulta'], 2); ?>');
                console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
                console.log('Tema: ' + themeManager.theme);
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

        // Función global para imprimir
        window.printReceipt = function () {
            window.print();
        };
    </script>
</body>

</html>