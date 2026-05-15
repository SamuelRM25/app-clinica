<?php
// sales/index.php - Módulo de Ventas - Centro Médico RS
// Reingenierizado con Diseño Dashboard Moderno
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

check_module_access('sales');


verify_session();

// Establecer zona horaria
date_default_timezone_set('America/Guatemala');

// Obtener información del usuario
$user_name = $_SESSION['nombre'];
$user_type = $_SESSION['tipoUsuario'];
$user_specialty = $_SESSION['especialidad'] ?? 'Profesional Médico';

try {
    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener total de registros para paginación
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM ventas");
    $stmt->execute();
    $total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Obtener todas las ventas con paginación
    $limit = 50;
    $total_pages = max(1, ceil($total_records / $limit));

    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    if ($page < 1)
        $page = 1;
    if ($page > $total_pages && $total_records > 0)
        $page = $total_pages;

    $offset = ($page - 1) * $limit;

    // Obtener datos de ventas con paginación
    // Usamos los valores directamente tras castear a int para máxima compatibilidad con LIMIT
    $limit_int = (int) $limit;
    $offset_int = (int) $offset;

    $stmt = $conn->prepare("
        SELECT v.*, u.nombre as vendedor_nombre, u.apellido as vendedor_apellido
        FROM ventas v
        LEFT JOIN usuarios u ON v.id_usuario = u.idUsuario
        ORDER BY v.fecha_venta DESC 
        LIMIT $limit_int OFFSET $offset_int
    ");
    $stmt->execute();
    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular estadísticas rápidas
    $stmt = $conn->query("SELECT SUM(total) as total_hoy FROM ventas WHERE DATE(fecha_venta) = CURDATE() AND estado = 'Pagado'");
    $total_hoy = $stmt->fetch(PDO::FETCH_ASSOC)['total_hoy'] ?? 0;

    $stmt = $conn->query("SELECT COUNT(*) as ventas_hoy FROM ventas WHERE DATE(fecha_venta) = CURDATE()");
    $ventas_hoy = $stmt->fetch(PDO::FETCH_ASSOC)['ventas_hoy'] ?? 0;

    // Obtener ventas del mes
    $stmt = $conn->query("SELECT SUM(total) as total_mes FROM ventas WHERE MONTH(fecha_venta) = MONTH(CURDATE()) AND YEAR(fecha_venta) = YEAR(CURDATE()) AND estado = 'Pagado'");
    $total_mes = $stmt->fetch(PDO::FETCH_ASSOC)['total_mes'] ?? 0;

    // Obtener ventas pendientes
    $stmt = $conn->query("SELECT COUNT(*) as pendientes FROM ventas WHERE estado = 'Pendiente'");
    $pendientes = $stmt->fetch(PDO::FETCH_ASSOC)['pendientes'] ?? 0;

    // Título de la página
    $page_title = "Ventas - Centro Médico RS";

} catch (Exception $e) {
    // Manejo de errores
    $ventas = [];
    $total_records = 0;
    $total_pages = 1;
    $total_hoy = 0;
    $ventas_hoy = 0;
    $total_mes = 0;
    $pendientes = 0;
    $error_message = "Error al cargar ventas: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Módulo de Ventas - Centro Médico RS - Sistema de gestión médica">
    <title><?php echo $page_title; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">

    <!-- Google Fonts - Inter (moderno y legible) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

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

        <main class="main-content">
            <!-- Notificación de ventas pendientes -->
            <?php if ($pendientes > 0): ?>
                <div class="alert-card mb-4 animate-in delay-1">
                    <div class="alert-header">
                        <div class="alert-icon warning">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <h3 class="alert-title">Ventas Pendientes</h3>
                    </div>
                    <p class="text-muted mb-0">
                        Hay <strong><?php echo $pendientes; ?></strong> ventas pendientes de pago.
                        <a href="#pendientes" class="text-primary text-decoration-none ms-1">
                            Revisar ahora <i class="bi bi-arrow-right"></i>
                        </a>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Bienvenida personalizada -->
            <div class="stat-card mb-4 animate-in">
                <div class="stat-header">
                    <div>
                        <h2 id="greeting" class="stat-value" style="font-size: 1.75rem; margin-bottom: 0.5rem;">
                            <span id="greeting-text">Ventas</span>, <?php echo htmlspecialchars($user_name); ?>
                        </h2>
                        <p class="text-muted mb-0">
                            <i class="bi bi-calendar-check me-1"></i> <?php echo date('d/m/Y'); ?>
                            <span class="mx-2">•</span>
                            <i class="bi bi-clock me-1"></i> <span id="current-time"><?php echo date('H:i'); ?></span>
                            <span class="mx-2">•</span>
                            <i class="bi bi-cash-coin me-1"></i> Gestión de transacciones
                        </p>
                    </div>
                    <div class="d-none d-md-block">
                        <i class="bi bi-receipt text-primary" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>

            <!-- Estadísticas principales -->
            <div class="stats-grid">
                <!-- Ventas de hoy -->
                <div class="stat-card animate-in delay-1">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Ventas Hoy</div>
                            <div class="stat-value"><?php echo $ventas_hoy; ?></div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="bi bi-cart-check"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-arrow-up-right"></i>
                        <span>Transacciones del día</span>
                    </div>
                </div>

                <!-- Total recaudado hoy -->
                <div class="stat-card animate-in delay-2">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Recaudado Hoy</div>
                            <div class="stat-value">Q<?php echo number_format($total_hoy, 2); ?></div>
                        </div>
                        <div class="stat-icon success">
                            <i class="bi bi-currency-dollar"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-graph-up-arrow"></i>
                        <span>Total del día</span>
                    </div>
                </div>

                <!-- Ventas del mes -->
                <div class="stat-card animate-in delay-3">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Ventas Mes</div>
                            <div class="stat-value">Q<?php echo number_format($total_mes, 2); ?></div>
                        </div>
                        <div class="stat-icon info">
                            <i class="bi bi-calendar-month"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-calendar"></i>
                        <span>Mes <?php echo date('F'); ?></span>
                    </div>
                </div>

                <!-- Ventas pendientes -->
                <div class="stat-card animate-in delay-4">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Pendientes</div>
                            <div class="stat-value"><?php echo $pendientes; ?></div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="bi bi-clock-history"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-exclamation-triangle"></i>
                        <span>Por cobrar</span>
                    </div>
                </div>
            </div>

            <!-- Sección de ventas -->
            <section class="sales-section animate-in delay-1">
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-receipt section-title-icon"></i>
                        Historial de Ventas
                    </h3>
                    <div class="d-flex gap-2">
                        <a href="../dispensary/index.php" class="action-btn">
                            <i class="bi bi-plus-lg"></i>
                            Nueva Venta
                        </a>
                        <button type="button" class="action-btn secondary" data-bs-toggle="modal"
                            data-bs-target="#reportModal">
                            <i class="bi bi-file-earmark-bar-graph"></i>
                            Reporte
                        </button>
                    </div>
                </div>

                <?php if (count($ventas) > 0): ?>
                    <div class="table-responsive">
                        <table class="sales-table">
                            <thead>
                                <tr>
                                    <th>Venta</th>
                                    <th>Cliente</th>
                                    <th>Vendedor</th>
                                    <th>Método Pago</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ventas as $venta): ?>
                                    <?php
                                    $fecha_venta = new DateTime($venta['fecha_venta']);
                                    $hora_venta = $fecha_venta->format('h:i A');
                                    $fecha_formateada = $fecha_venta->format('d/m/Y');
                                    $vendedor_nombre = $venta['vendedor_nombre'] ? htmlspecialchars($venta['vendedor_nombre'] . ' ' . substr($venta['vendedor_apellido'], 0, 1) . '.') : 'Sistema';
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="sale-cell">
                                                <div class="sale-avatar">
                                                    <?php echo strtoupper(substr($venta['nombre_cliente'] ?? 'C', 0, 1)); ?>
                                                </div>
                                                <div class="sale-info">
                                                    <div class="sale-number">
                                                        #VTA-<?php echo str_pad($venta['id_venta'], 5, '0', STR_PAD_LEFT); ?>
                                                    </div>
                                                    <div class="sale-time"><?php echo $hora_venta; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="client-name"><?php echo htmlspecialchars($venta['nombre_cliente']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="client-type"><?php echo $vendedor_nombre; ?></div>
                                        </td>
                                        <td>
                                            <span class="payment-badge">
                                                <i class="bi bi-credit-card"></i>
                                                <?php echo htmlspecialchars($venta['tipo_pago']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="amount-badge">
                                                Q<?php echo number_format($venta['total'], 2); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = match (strtolower($venta['estado'])) {
                                                'pagado' => 'pagado',
                                                'pendiente' => 'pendiente',
                                                'cancelado' => 'cancelado',
                                                default => 'pendiente'
                                            };
                                            ?>
                                            <span class="status-badge <?php echo $status_class; ?>">
                                                <?php echo htmlspecialchars($venta['estado']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" class="btn-icon view view-details" title="Ver detalles"
                                                    data-id="<?php echo $venta['id_venta']; ?>">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <a href="../dispensary/print_receipt.php?id=<?php echo $venta['id_venta']; ?>"
                                                    target="_blank" class="btn-icon print" title="Imprimir recibo">
                                                    <i class="bi bi-printer"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <?php if ($total_pages > 1): ?>
                        <div class="d-flex justify-content-center mt-4">
                            <nav>
                                <ul class="pagination">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                                <i class="bi bi-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php
                                    $start = max(1, $page - 2);
                                    $end = min($total_pages, $page + 2);

                                    for ($i = $start; $i <= $end; $i++):
                                        ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                                                <i class="bi bi-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="bi bi-receipt"></i>
                        </div>
                        <h4 class="text-muted mb-2">No hay ventas registradas</h4>
                        <p class="text-muted mb-3">Total de ventas en sistema: <?php echo $total_records; ?></p>
                        <a href="../dispensary/index.php" class="action-btn">
                            <i class="bi bi-plus-lg"></i>
                            Registrar primera venta
                        </a>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <!-- Modal para ver detalles de venta -->
    <div class="modal fade" id="viewDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-receipt text-primary"></i>
                        Detalles de Venta
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="modal-loading" class="text-center py-4">
                        <div class="spinner-border text-primary"></div>
                        <p class="mt-2 text-muted">Cargando detalles...</p>
                    </div>
                    <div id="modal-content" style="display: none;">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <p class="text-muted small mb-1">Cliente</p>
                                <p class="fw-bold" id="modal-cliente">---</p>
                            </div>
                            <div class="col-md-6">
                                <p class="text-muted small mb-1">Fecha y Hora</p>
                                <p class="fw-bold" id="modal-fecha">---</p>
                            </div>
                            <div class="col-md-6">
                                <p class="text-muted small mb-1">Método de Pago</p>
                                <p class="fw-bold" id="modal-tipo-pago">---</p>
                            </div>
                            <div class="col-md-6">
                                <p class="text-muted small mb-1">Estado</p>
                                <p class="fw-bold" id="modal-estado">---</p>
                            </div>
                        </div>

                        <h6 class="fw-bold mb-3">Productos Adquiridos</h6>
                        <div class="table-responsive">
                            <table class="table table-sm" id="modal-items">
                                <thead>
                                    <tr>
                                        <th>Medicamento</th>
                                        <th>Presentación</th>
                                        <th class="text-center">Cantidad</th>
                                        <th class="text-end">Precio Unitario</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Los ítems se cargarán dinámicamente -->
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="4" class="text-end">Total:</th>
                                        <th class="text-end" id="modal-total">---</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="action-btn secondary" data-bs-dismiss="modal">Cerrar</button>
                    <a href="#" class="action-btn" id="modal-print-btn" target="_blank">
                        <i class="bi bi-printer"></i>
                        Imprimir Recibo
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para reporte por jornada -->
    <div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-file-earmark-bar-graph text-success"></i>
                        Reporte por Jornada
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        La jornada comprende desde las <strong>08:00 AM</strong> de la fecha seleccionada hasta las
                        <strong>08:00 AM</strong> del día siguiente.
                    </p>
                    <div class="form-group mb-4">
                        <label class="form-label">Seleccionar Fecha de Inicio</label>
                        <input type="date" class="form-control" id="reportDate" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="action-btn secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="action-btn success" id="btnGenerateReport">
                        <i class="bi bi-file-earmark-pdf"></i>
                        Generar Reporte
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->

    <!-- jQuery (required for Bootstrap modals) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JavaScript Optimizado -->
    <script>
        // Módulo de Ventas Reingenierizado - Centro Médico RS

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
                viewDetailsButtons: document.querySelectorAll('.view-details'),
                btnGenerateReport: document.getElementById('btnGenerateReport'),
                reportDateInput: document.getElementById('reportDate')
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
                    this.setupSalesHandlers();
                    this.setupAnimations();
                    this.setupReportGenerator();
                }

                setupGreeting() {
                    if (!DOM.greetingElement) return;

                    const hour = new Date().getHours();
                    let greeting = 'Ventas';

                    // Podemos mantener solo "Ventas" o agregar un saludo
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

                setupSalesHandlers() {
                    DOM.viewDetailsButtons.forEach(btn => {
                        btn.addEventListener('click', async (e) => {
                            e.preventDefault();
                            const saleId = btn.getAttribute('data-id');

                            if (!saleId) return;

                            // Mostrar modal
                            const modal = new bootstrap.Modal(document.getElementById('viewDetailsModal'));
                            modal.show();

                            // Cargar datos de la venta
                            this.loadSaleDetails(saleId);
                        });
                    });
                }

                async loadSaleDetails(saleId) {
                    const loading = document.getElementById('modal-loading');
                    const content = document.getElementById('modal-content');
                    const modalBody = document.querySelector('#viewDetailsModal .modal-body');

                    // Mostrar loading
                    loading.style.display = 'block';
                    content.style.display = 'none';

                    try {
                        const response = await fetch(`get_sale_details.php?id=${saleId}`);
                        const data = await response.json();

                        if (data.status === 'success') {
                            // Actualizar información principal
                            document.getElementById('modal-cliente').textContent = data.venta.nombre_cliente || 'No especificado';

                            // Formatear fecha
                            const fechaVenta = new Date(data.venta.fecha_venta);
                            const fechaFormateada = fechaVenta.toLocaleDateString('es-GT', {
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            });
                            document.getElementById('modal-fecha').textContent = fechaFormateada;

                            document.getElementById('modal-tipo-pago').textContent = data.venta.tipo_pago || 'No especificado';
                            document.getElementById('modal-estado').textContent = data.venta.estado || 'No especificado';

                            // Actualizar total
                            document.getElementById('modal-total').textContent = `Q${parseFloat(data.venta.total || 0).toFixed(2)}`;

                            // Actualizar enlace de impresión
                            const printBtn = document.getElementById('modal-print-btn');
                            printBtn.href = `../dispensary/print_receipt.php?id=${saleId}`;

                            // Actualizar tabla de ítems
                            const itemsTable = document.querySelector('#modal-items tbody');
                            itemsTable.innerHTML = '';

                            if (data.items && data.items.length > 0) {
                                data.items.forEach(item => {
                                    const row = document.createElement('tr');
                                    row.innerHTML = `
                                    <td>${item.nom_medicamento || 'Producto'}</td>
                                    <td>${item.presentacion_med || 'N/A'}</td>
                                    <td class="text-center">${item.cantidad_vendida || 0}</td>
                                    <td class="text-end">Q${parseFloat(item.precio_unitario || 0).toFixed(2)}</td>
                                    <td class="text-end">Q${parseFloat(item.subtotal || 0).toFixed(2)}</td>
                                `;
                                    itemsTable.appendChild(row);
                                });
                            } else {
                                itemsTable.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No hay ítems registrados</td></tr>';
                            }

                            // Mostrar contenido
                            loading.style.display = 'none';
                            content.style.display = 'block';
                        } else {
                            throw new Error(data.message || 'Error al cargar los datos');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        loading.innerHTML = `
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Error al cargar los detalles de la venta: ${error.message}
                        </div>
                    `;
                    }
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
                    document.querySelectorAll('.stat-card, .sales-section, .alert-card').forEach(el => {
                        observer.observe(el);
                    });
                }

                setupReportGenerator() {
                    if (DOM.btnGenerateReport) {
                        DOM.btnGenerateReport.addEventListener('click', () => {
                            const date = DOM.reportDateInput ? DOM.reportDateInput.value : '<?php echo date("Y-m-d"); ?>';

                            if (!date) {
                                Swal.fire({
                                    title: 'Error',
                                    text: 'Por favor seleccione una fecha',
                                    icon: 'error',
                                    confirmButtonColor: 'var(--color-primary)',
                                    background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                                    color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#e2e8f0' : '#1a1a1a'
                                });
                                return;
                            }

                            // Abrir reporte en nueva pestaña
                            window.open(`generate_shift_report.php?date=${date}`, '_blank');
                            // Modal se cierra automáticamente si se usa el atributo data-bs-dismiss
                            window.location.reload();
                        });
                    }
                }
            }

            // ==========================================================================
            // OPTIMIZACIONES DE RENDIMIENTO
            // ==========================================================================
            class PerformanceOptimizer {
                constructor() {
                    this.setupLazyLoading();
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

                setupAnalytics() {
                    console.log('Módulo de Ventas cargado - Usuario: <?php echo htmlspecialchars($user_name); ?>');
                    console.log('Total de ventas: <?php echo $total_records; ?>');
                    console.log('Ventas pendientes: <?php echo $pendientes; ?>');
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
                window.salesModule = {
                    theme: themeManager,
                    components: dynamicComponents
                };

                // Log de inicialización
                console.log('Módulo de Ventas v4.0 inicializado correctamente');
                console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
                console.log('Rol: <?php echo htmlspecialchars($user_type); ?>');
                console.log('Tema: ' + themeManager.theme);
            });

            // ==========================================================================
            // MANEJO DE ERRORES GLOBALES
            // ==========================================================================
            window.addEventListener('error', (event) => {
                console.error('Error en módulo de ventas:', event.error);

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
            width: 3rem;
            height: 3rem;
        }
        .page-link {
            color: var(--color-primary);
            background-color: var(--color-card);
            border: 1px solid var(--color-border);
        }
        .page-link:hover {
            background-color: var(--color-surface);
            border-color: var(--color-border);
        }
        .page-item.active .page-link {
            background-color: var(--color-primary);
            border-color: var(--color-primary);
            color: white;
        }
        .modal-content {
            background-color: var(--color-card);
            color: var(--color-text);
            border: 1px solid var(--color-border);
        }
        .modal-header, .modal-footer {
            border-color: var(--color-border);
        }
        .btn-close {
            filter: invert(0.5);
        }
        [data-theme="dark"] .btn-close {
            filter: invert(1);
        }
    `;
        document.head.appendChild(style);
    </script>
</body>

</html>