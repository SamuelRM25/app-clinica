<?php
// index.php - Módulo de Cobros - Centro Médico RS
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
require_once '../../includes/module_guard.php';
require_once '../../includes/breadcrumbs.php';

check_module_access('core'); // Cobros es módulo base

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

    // Obtener todos los pacientes para el dropdown
    $stmt = $conn->prepare("SELECT id_paciente, CONCAT(nombre, ' ', apellido) as nombre_completo FROM pacientes WHERE id_hospital = ? ORDER BY nombre");
    $stmt->execute([hospital_id()]);
    $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener doctores (usuarios tipo 'doc')
    $stmtDoc = $conn->prepare("SELECT idUsuario, nombre, apellido FROM usuarios WHERE tipoUsuario = 'doc' AND id_hospital = ? ORDER BY nombre");
    $stmtDoc->execute([hospital_id()]);
    $doctores = $stmtDoc->fetchAll(PDO::FETCH_ASSOC);

    // Obtener cobros con paginación
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $limit = 25;
    $offset = ($page - 1) * $limit;

    // Obtener total para paginación
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM cobros WHERE id_hospital = ?");
    $stmt->execute([hospital_id()]);
    $total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $limit);

    // Obtener datos de cobros con nombre de paciente
    $stmt = $conn->prepare("
        SELECT c.*, CONCAT(p.nombre, ' ', p.apellido) as nombre_paciente 
        FROM cobros c
        JOIN pacientes p ON c.paciente_cobro = p.id_paciente
        WHERE c.id_hospital = ?
        ORDER BY c.fecha_consulta DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, hospital_id(), PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $cobros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Título de la página
    $page_title = "Cobros - Centro Médico RS";

    // Obtener estadísticas rápidas
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM cobros WHERE DATE(fecha_consulta) = CURDATE() AND id_hospital = ?");
    $stmt->execute([hospital_id()]);
    $hoy_cobros = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stmt = $conn->prepare("SELECT SUM(cantidad_consulta) as total FROM cobros WHERE MONTH(fecha_consulta) = MONTH(CURDATE()) AND id_hospital = ?");
    $stmt->execute([hospital_id()]);
    $mes_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

} catch (Exception $e) {
    // Manejo de errores
    error_log("Error en módulo de cobros: " . $e->getMessage());
    die("Error al cargar el módulo de cobros. Por favor, contacte al administrador.");
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Módulo de Cobros - Centro Médico RS - Sistema de gestión de cobros médicos">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">

    <!-- Google Fonts - Inter (moderno y legible) -->
<!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Bootstrap CSS (Required for Modals) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Seguridad y Protección de Código -->
    <script src="../../assets/js/security.js"></script>

    <!-- CSS Crítico (incrustado para máxima velocidad) -->
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
                ['label' => 'Cobros'],
            ]); ?>
            <!-- Bienvenida personalizada -->
            <div class="stat-card mb-4 animate-in">
                <div class="stat-header">
                    <div>
                        <h2 class="stat-value" style="font-size: 1.75rem; margin-bottom: 0.5rem;">
                            <span id="greeting-text">Módulo de Cobros</span>
                        </h2>
                        <p class="text-muted mb-0">
                            <i class="bi bi-cash-coin me-1"></i> Gestión de recaudación y recibos médicos
                            <span class="mx-2">•</span>
                            <i class="bi bi-calendar-check me-1"></i> <?php echo date('d/m/Y'); ?>
                            <span class="mx-2">•</span>
                            <i class="bi bi-clock me-1"></i> <span id="current-time"><?php echo date('H:i'); ?></span>
                        </p>
                    </div>
                    <div class="d-none d-md-block">
                        <i class="bi bi-cash-coin text-primary" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>

            <!-- Estadísticas principales -->
            <div class="stats-grid">
                <!-- Cobros de hoy -->
                <div class="stat-card animate-in delay-1">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Cobros Hoy</div>
                            <div class="stat-value"><?php echo $hoy_cobros; ?></div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-arrow-up-right"></i>
                        <span>Registrados hoy</span>
                    </div>
                </div>

                <!-- Total del mes -->
                <div class="stat-card animate-in delay-2">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Total Mes</div>
                            <div class="stat-value">Q<?php echo number_format($mes_total, 2); ?></div>
                        </div>
                        <div class="stat-icon success">
                            <i class="bi bi-currency-dollar"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-graph-up"></i>
                        <span>Recaudación mensual</span>
                    </div>
                </div>

                <!-- Total cobros -->
                <div class="stat-card animate-in delay-3">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Total Cobros</div>
                            <div class="stat-value"><?php echo $total_records; ?></div>
                        </div>
                        <div class="stat-icon info">
                            <i class="bi bi-receipt"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-archive"></i>
                        <span>Registros totales</span>
                    </div>
                </div>

                <!-- Páginas -->
                <div class="stat-card animate-in delay-4">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Página</div>
                            <div class="stat-value"><?php echo $page; ?>/<?php echo $total_pages; ?></div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="bi bi-file-text"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-collection"></i>
                        <span>Paginación</span>
                    </div>
                </div>
            </div>

            <!-- Sección de cobros -->
            <section class="billing-section animate-in delay-1">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-receipt-cutoff section-title-icon"></i>
                        Registro de Cobros
                    </h3>
                    <div class="d-flex gap-2">
                        <button type="button" class="action-btn" data-bs-toggle="modal"
                            data-bs-target="#newBillingModal">
                            <i class="bi bi-plus-lg"></i>
                            Nuevo Cobro
                        </button>
                        <a href="export_cobros.php" class="action-btn secondary">
                            <i class="bi bi-download"></i>
                            Exportar
                        </a>
                    </div>
                </div>

                <?php if (count($cobros) > 0): ?>
                        <div class="table-responsive">
                            <table class="billing-table">
                                <thead>
                                    <tr>
                                        <th>Paciente</th>
                                        <th>Monto</th>
                                        <th>Fecha</th>
                                        <th>ID Cobro</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cobros as $cobro): ?>
                                            <?php
                                            $patient_name = htmlspecialchars($cobro['nombre_paciente']);
                                            $patient_initials = strtoupper(
                                                substr(explode(' ', $cobro['nombre_paciente'])[0], 0, 1) .
                                                (isset(explode(' ', $cobro['nombre_paciente'])[1]) ? substr(explode(' ', $cobro['nombre_paciente'])[1], 0, 1) : '')
                                            );
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
                                                    <span class="amount-badge">
                                                        <i class="bi bi-currency-dollar"></i>
                                                        Q<?php echo number_format($cobro['cantidad_consulta'], 2); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo date('d/m/Y', strtotime($cobro['fecha_consulta'])); ?>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge bg-secondary">#<?php echo str_pad($cobro['in_cobro'], 5, '0', STR_PAD_LEFT); ?></span>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="print_receipt.php?id=<?php echo $cobro['in_cobro']; ?>" target="_blank"
                                                            class="btn-icon print" title="Imprimir recibo">
                                                            <i class="bi bi-printer"></i>
                                                        </a>
                                                        <button type="button" class="btn-icon view view-details"
                                                            data-id="<?php echo $cobro['in_cobro']; ?>"
                                                            data-nombre="<?php echo htmlspecialchars($cobro['nombre_paciente']); ?>"
                                                            data-monto="<?php echo $cobro['cantidad_consulta']; ?>"
                                                            data-fecha="<?php echo date('d/m/Y', strtotime($cobro['fecha_consulta'])); ?>"
                                                            title="Ver detalles">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        <?php if ($total_pages > 1): ?>
                                <nav class="mt-4">
                                    <ul class="pagination justify-content-center">
                                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                                <i class="bi bi-chevron-left"></i>
                                            </a>
                                        </li>

                                        <?php
                                        $range = 2;
                                        $start = max(1, $page - $range);
                                        $end = min($total_pages, $page + $range);

                                        if ($start > 1): ?>
                                                <li class="page-item"><a class="page-link" href="?page=1">1</a></li>
                                                <?php if ($start > 2): ?>
                                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                                <?php endif; ?>
                                        <?php endif; ?>

                                        <?php for ($i = $start; $i <= $end; $i++): ?>
                                                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                                </li>
                                        <?php endfor; ?>

                                        <?php if ($end < $total_pages): ?>
                                                <?php if ($end < $total_pages - 1): ?>
                                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                                <?php endif; ?>
                                                <li class="page-item"><a class="page-link"
                                                        href="?page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a></li>
                                        <?php endif; ?>

                                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                                                <i class="bi bi-chevron-right"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                        <?php endif; ?>

                <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="bi bi-cash-coin"></i>
                            </div>
                            <h4 class="text-muted mb-2">No hay cobros registrados</h4>
                            <p class="text-muted mb-3">Comience registrando un nuevo cobro</p>
                            <button type="button" class="action-btn" data-bs-toggle="modal" data-bs-target="#newBillingModal">
                                <i class="bi bi-plus-lg"></i>
                                Registrar primer cobro
                            </button>
                        </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <!-- Modal para nuevo cobro -->
    <div class="modal fade" id="newBillingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-cash-coin me-2"></i>
                        Nuevo Cobro
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="newBillingForm">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Paciente</label>
                            <input type="text" name="paciente_nombre" class="form-control" list="datalistOptions"
                                id="paciente_input" placeholder="Nombre del paciente (o seleccione de la lista)..."
                                required autocomplete="off">
                            <datalist id="datalistOptions">
                                <?php foreach ($pacientes as $paciente): ?>
                                        <option data-id="<?php echo $paciente['id_paciente']; ?>"
                                            value="<?php echo htmlspecialchars($paciente['nombre_completo']); ?>">
                                    <?php endforeach; ?>
                            </datalist>
                            <input type="hidden" id="paciente" name="paciente">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Médico que atiende</label>
                            <select class="form-select" id="id_doctor" name="id_doctor" required>
                                <option value="">Seleccione un médico...</option>
                                <?php foreach ($doctores as $doctor): ?>
                                        <option value="<?php echo $doctor['idUsuario']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($doctor['nombre']); ?>"
                                            data-apellido="<?php echo htmlspecialchars($doctor['apellido']); ?>">
                                            Dr(a).
                                            <?php echo htmlspecialchars($doctor['nombre'] . ' ' . $doctor['apellido']); ?>
                                        </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Tipo de Consulta</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="tipo_consulta" id="billing_btn_consulta"
                                    value="Consulta" checked autocomplete="off">
                                <label class="btn btn-outline-success" for="billing_btn_consulta">Consulta</label>

                                <input type="radio" class="btn-check" name="tipo_consulta" id="billing_btn_reconsulta"
                                    value="Reconsulta" autocomplete="off">
                                <label class="btn btn-outline-success" for="billing_btn_reconsulta">Re-Consulta</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Monto a Cobrar (Q)</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-success text-white border-0">Q</span>
                                <input type="number" class="form-control border-success text-success fw-bold"
                                    id="cantidad" name="cantidad" min="0" step="0.01" placeholder="0.00" required>
                            </div>
                        </div>

                        <div class="small text-muted mb-0">
                            <i class="bi bi-info-circle me-1"></i> El monto se calcula automáticamente al seleccionar
                            médico y tipo.
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success px-4" id="saveBillingBtn">
                        <i class="bi bi-check-lg me-1"></i>Guardar Cobro
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Optimizado -->
    <script>
        // Módulo de Cobros Reingenierizado - Centro Médico RS

        (function () {
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
            // REFERENCIAS A ELEMENTOS DOM
            // ==========================================================================
            const DOM = {
                html: document.documentElement,
                body: document.body,
                themeSwitch: document.getElementById('themeSwitch'),
                greetingElement: document.getElementById('greeting-text'),
                currentTimeElement: document.getElementById('current-time'),
                saveBillingBtn: document.getElementById('saveBillingBtn'),
                newBillingForm: document.getElementById('newBillingForm')
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
            // COMPONENTES DINÁMICOS
            // ==========================================================================
            class DynamicComponents {
                constructor() {
                    this.setupGreeting();
                    this.setupClock();
                    this.setupBillingHandlers();
                    this.setupAnimations();
                    this.setupModalDetails();
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

                setupBillingHandlers() {
                    const doctorSelect = document.getElementById('id_doctor');
                    const montoInput = document.getElementById('cantidad');
                    const tipoRadios = document.getElementsByName('tipo_consulta');

                    const calculatePrice = () => {
                        const doctorId = doctorSelect.value;
                        let type = 'Consulta';
                        tipoRadios.forEach(r => { if (r.checked) type = r.value; });

                        let price = 0;
                        const date = new Date();
                        const day = date.getDay();
                        const hour = date.getHours();

                        switch (doctorId) {
                            case '17': price = (type === 'Consulta') ? 200 : 150; break;
                            case '13': price = (type === 'Consulta') ? 250 : 150; break;
                            case '18': case '11': price = (type === 'Consulta') ? 200 : 100; break;
                            case '16':
                                if (type === 'Reconsulta') price = 150;
                                else {
                                    if (day >= 1 && day <= 5) {
                                        if (hour >= 8 && hour < 16) price = 250;
                                        else if (hour >= 16 && hour < 22) price = 300;
                                        else price = 400;
                                    } else if (day === 6) {
                                        if (hour < 13) price = 250;
                                        else if (hour >= 13 && hour < 22) price = 300;
                                        else price = 400;
                                    } else {
                                        if (hour >= 8 && hour < 20) price = 350;
                                        else price = 400;
                                    }
                                }
                                break;
                            default: price = (type === 'Consulta') ? 100 : 0; break;
                        }

                        // Overrides based on name
                        const selectedOption = doctorSelect.options[doctorSelect.selectedIndex];
                        if (selectedOption) {
                            const nombre = (selectedOption.getAttribute('data-nombre') || '').toLowerCase();
                            const apellido = (selectedOption.getAttribute('data-apellido') || '').toLowerCase();

                            // Dr. Estuardo Rivas - Q400 off-hours/weekends
                            if (nombre.includes('estuardo') && apellido.includes('rivas')) {
                                if (day === 0 || day === 6 || hour >= 16) {
                                    price = 400;
                                }
                            }

                            // Dra. Libny - Q300 off-hours/weekends
                            if (nombre.includes('libny')) {
                                if (day === 0 || day === 6 || hour >= 16) {
                                    price = 300;
                                }
                            }
                        }
                        montoInput.value = price;
                    };

                    doctorSelect?.addEventListener('change', calculatePrice);
                    tipoRadios.forEach(r => r.addEventListener('change', calculatePrice));

                    // Guardar nuevo cobro
                    if (DOM.saveBillingBtn) {
                        DOM.saveBillingBtn.addEventListener('click', async () => {
                            const form = DOM.newBillingForm;

                            // Sync patient ID from datalist
                            const patientInput = document.getElementById('paciente_input');
                            const patientHidden = document.getElementById('paciente');
                            const datalist = document.getElementById('datalistOptions');

                            // Reset ID
                            patientHidden.value = '';

                            // Find ID based on name value
                            if (patientInput && datalist) {
                                const val = patientInput.value;
                                const options = datalist.options;
                                for (let i = 0; i < options.length; i++) {
                                    if (options[i].value === val) {
                                        patientHidden.value = options[i].getAttribute('data-id');
                                        break;
                                    }
                                }
                            }

                            // If no ID found (custom name), it will be handled by the backend using patient_nombre
                            // Just ensure some text is present
                            if (patientInput.value.trim() === '') {
                                Swal.fire({ title: 'Campo requerido', text: 'Por favor ingrese el nombre del paciente.', icon: 'warning' });
                                return;
                            }

                            // Validar formulario
                            if (!form.checkValidity()) {
                                form.reportValidity();
                                return;
                            }

                            const formData = new FormData(form);
                            const data = Object.fromEntries(formData.entries());

                            // Mostrar indicador de carga
                            const originalText = DOM.saveBillingBtn.innerHTML;
                            DOM.saveBillingBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Guardando...';
                            DOM.saveBillingBtn.disabled = true;

                            try {
                                const response = await fetch('save_billing.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: new URLSearchParams(data)
                                });

                                const result = await response.json();

                                if (result.status === 'success') {
                                    // Mostrar notificación de éxito
                                    Swal.fire({
                                        title: '¡Éxito!',
                                        text: 'Cobro guardado correctamente',
                                        icon: 'success',
                                        confirmButtonColor: 'var(--color-primary)',
                                        background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                                        color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#e2e8f0' : '#1a1a1a'
                                    }).then(() => {
                                        // Cerrar modal y recargar
                                        const modal = bootstrap.Modal.getInstance(document.getElementById('newBillingModal'));
                                        modal.hide();
                                        window.location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        title: 'Error',
                                        text: result.message || 'Error al guardar el cobro',
                                        icon: 'error',
                                        confirmButtonColor: 'var(--color-primary)'
                                    });
                                }
                            } catch (error) {
                                console.error('Error:', error);
                                Swal.fire({
                                    title: 'Error',
                                    text: 'Error de conexión con el servidor',
                                    icon: 'error',
                                    confirmButtonColor: 'var(--color-primary)'
                                });
                            } finally {
                                DOM.saveBillingBtn.innerHTML = originalText;
                                DOM.saveBillingBtn.disabled = false;
                            }
                        });
                    }
                }

                setupModalDetails() {
                    // Mostrar detalles en modal
                    document.querySelectorAll('.view-details').forEach(btn => {
                        btn.addEventListener('click', function () {
                            const id = this.getAttribute('data-id');
                            const nombre = this.getAttribute('data-nombre');
                            const monto = this.getAttribute('data-monto');
                            const fecha = this.getAttribute('data-fecha');

                            Swal.fire({
                                title: 'Detalles del Cobro',
                                html: `
                                <div class="text-start">
                                    <p><strong>ID:</strong> #${id.toString().padStart(5, '0')}</p>
                                    <p><strong>Paciente:</strong> ${nombre}</p>
                                    <p><strong>Monto:</strong> Q${parseFloat(monto).toFixed(2)}</p>
                                    <p><strong>Fecha:</strong> ${fecha}</p>
                                </div>
                            `,
                                icon: 'info',
                                showCancelButton: true,
                                confirmButtonText: 'Imprimir Recibo',
                                cancelButtonText: 'Cerrar',
                                confirmButtonColor: 'var(--color-primary)',
                                background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                                color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#e2e8f0' : '#1a1a1a'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.open(`print_receipt.php?id=${id}`, '_blank');
                                }
                            });
                        });
                    });
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
                    document.querySelectorAll('.stat-card, .billing-section').forEach(el => {
                        observer.observe(el);
                    });
                }
            }

            // ==========================================================================
            // OPTIMIZACIONES DE RENDIMIENTO
            // ==========================================================================
            class PerformanceOptimizer {
                constructor() {
                    this.setupAnalytics();
                }

                setupAnalytics() {
                    console.log('Módulo de Cobros cargado - Usuario: <?php echo htmlspecialchars($user_name); ?>');
                    console.log('Total cobros: <?php echo $total_records; ?>');
                    console.log('Recaudación mensual: Q<?php echo number_format($mes_total, 2); ?>');
                }
            }

            // ==========================================================================
            // INICIALIZACIÓN DE LA APLICACIÓN
            // ==========================================================================
            document.addEventListener('DOMContentLoaded', () => {
                // Inicializar componentes
                const themeManager = new ThemeManager();
                const dynamicComponents = new DynamicComponents();
                const performanceOptimizer = new PerformanceOptimizer();

                // Exponer APIs necesarias globalmente
                window.cobrosModule = {
                    theme: themeManager,
                    components: dynamicComponents
                };

                // Log de inicialización
                console.log('Módulo de Cobros CMS inicializado correctamente');
                console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
                console.log('Rol: <?php echo htmlspecialchars($user_type); ?>');
                console.log('Tema: ' + themeManager.theme);
            });

            // ==========================================================================
            // MANEJO DE ERRORES GLOBALES
            // ==========================================================================
            window.addEventListener('error', (event) => {
                console.error('Error en módulo de cobros:', event.error);

                // En producción, enviar error al servidor
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
        .spin {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Estilos para modal */
        .modal-content {
            background-color: var(--color-card);
            color: var(--color-text);
            border: 1px solid var(--color-border);
        }
        
        .modal-header {
            border-bottom: 1px solid var(--color-border);
        }
        
        .modal-footer {
            border-top: 1px solid var(--color-border);
        }
        
        .btn-close {
            filter: var(--data-theme) === 'dark' ? 'invert(1)' : 'none';
        }
        
        .form-control {
            background-color: var(--color-surface);
            color: var(--color-text);
            border: 1px solid var(--color-border);
        }
        
        .form-control:focus {
            background-color: var(--color-surface);
            color: var(--color-text);
            border-color: var(--color-primary);
            box-shadow: 0 0 0 0.25rem rgba(var(--color-primary-rgb), 0.25);
        }
        
        .input-group-text {
            background-color: var(--color-surface);
            color: var(--color-text);
            border: 1px solid var(--color-border);
        }
    `;
        document.head.appendChild(style);

        // Modales se inicializan automáticamente vía data-attributes en Bootstrap 5
        // Eliminamos la inicialización manual para evitar conflictos
    </script>

    <!-- jQuery (required for Bootstrap modals) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>