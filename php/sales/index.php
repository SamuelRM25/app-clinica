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
$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

try {
    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener total de registros para paginación
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM ventas WHERE id_hospital = ?");
    $stmt->execute([$id_hospital]);
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
        WHERE v.id_hospital = ?
        ORDER BY v.fecha_venta DESC 
        LIMIT $limit_int OFFSET $offset_int
    ");
    $stmt->execute([$id_hospital]);
    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular estadísticas rápidas
    $stmt = $conn->prepare("SELECT SUM(total) as total_hoy FROM ventas WHERE DATE(fecha_venta) = CURDATE() AND estado = 'Pagado' AND id_hospital = ?");
    $stmt->execute([$id_hospital]);
    $total_hoy = $stmt->fetch(PDO::FETCH_ASSOC)['total_hoy'] ?? 0;

    $stmt = $conn->prepare("SELECT COUNT(*) as ventas_hoy FROM ventas WHERE DATE(fecha_venta) = CURDATE() AND id_hospital = ?");
    $stmt->execute([$id_hospital]);
    $ventas_hoy = $stmt->fetch(PDO::FETCH_ASSOC)['ventas_hoy'] ?? 0;

    // Obtener ventas del mes
    $stmt = $conn->prepare("SELECT SUM(total) as total_mes FROM ventas WHERE MONTH(fecha_venta) = MONTH(CURDATE()) AND YEAR(fecha_venta) = YEAR(CURDATE()) AND estado = 'Pagado' AND id_hospital = ?");
    $stmt->execute([$id_hospital]);
    $total_mes = $stmt->fetch(PDO::FETCH_ASSOC)['total_mes'] ?? 0;

    // Obtener ventas pendientes
    $stmt = $conn->prepare("SELECT COUNT(*) as pendientes FROM ventas WHERE estado = 'Pendiente' AND id_hospital = ?");
    $stmt->execute([$id_hospital]);
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
    <title><?php echo htmlspecialchars($page_title); ?></title>

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

    <!-- Estilos de Personalización Propia y Premium para Ventas -->
    <style>
        /* ===== STATS CARDS GLOW EFFECTS ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.3s ease, border-color 0.3s ease !important;
            border: 1px solid var(--color-border) !important;
            background: var(--color-card) !important;
            border-radius: var(--radius-lg) !important;
            box-shadow: var(--shadow-sm) !important;
            cursor: pointer;
        }
        .stat-card:hover {
            transform: translateY(-6px);
        }
        /* Neon/RGB glows on hover depending on active card color */
        .stat-card:nth-child(1):hover {
            border-color: rgba(var(--color-primary-rgb), 0.65) !important;
            box-shadow: 0 12px 30px rgba(var(--color-primary-rgb), 0.18), 0 0 15px rgba(var(--color-primary-rgb), 0.08) !important;
        }
        .stat-card:nth-child(2):hover {
            border-color: rgba(var(--color-success-rgb), 0.65) !important;
            box-shadow: 0 12px 30px rgba(var(--color-success-rgb), 0.18), 0 0 15px rgba(var(--color-success-rgb), 0.08) !important;
        }
        .stat-card:nth-child(3):hover {
            border-color: rgba(var(--color-info-rgb), 0.65) !important;
            box-shadow: 0 12px 30px rgba(var(--color-info-rgb), 0.18), 0 0 15px rgba(var(--color-info-rgb), 0.08) !important;
        }
        .stat-card:nth-child(4):hover {
            border-color: rgba(var(--color-warning-rgb), 0.65) !important;
            box-shadow: 0 12px 30px rgba(var(--color-warning-rgb), 0.18), 0 0 15px rgba(var(--color-warning-rgb), 0.08) !important;
        }

        /* ===== SALES TABLE & STICKY HEADER ===== */
        .sales-table {
            width: 100%;
            border-collapse: collapse;
            background: transparent;
        }
        .sales-table th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: var(--color-surface) !important;
            backdrop-filter: blur(12px) !important;
            -webkit-backdrop-filter: blur(12px) !important;
            border-bottom: 2px solid var(--color-border) !important;
            padding: 1.1rem 1.25rem !important;
            font-weight: 600;
            color: var(--color-text-secondary) !important;
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.06em;
        }
        .sales-table td {
            padding: 1.1rem 1.25rem !important;
            border-bottom: 1px solid var(--color-border) !important;
            color: var(--color-text);
            background: transparent;
            transition: background-color 0.2s ease;
        }
        .sales-table tbody tr:hover td {
            background-color: rgba(var(--color-primary-rgb), 0.04) !important;
        }

        /* ===== GLOWING CRISTAL BADGES ===== */
        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.35rem 0.8rem;
            font-size: 0.72rem;
            font-weight: 700;
            border-radius: var(--radius-sm);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-width: 1px;
            border-style: solid;
            transition: all 0.3s ease;
        }
        .status-badge.pagado {
            background: rgba(var(--color-success-rgb), 0.15) !important;
            color: var(--color-success) !important;
            border-color: rgba(var(--color-success-rgb), 0.35) !important;
            box-shadow: 0 2px 10px rgba(var(--color-success-rgb), 0.12), inset 0 1px 0 rgba(255,255,255,0.1);
        }
        .status-badge.pendiente {
            background: rgba(var(--color-warning-rgb), 0.15) !important;
            color: var(--color-warning) !important;
            border-color: rgba(var(--color-warning-rgb), 0.35) !important;
            box-shadow: 0 2px 10px rgba(var(--color-warning-rgb), 0.12), inset 0 1px 0 rgba(255,255,255,0.1);
        }
        .status-badge.cancelado {
            background: rgba(var(--color-danger-rgb), 0.15) !important;
            color: var(--color-danger) !important;
            border-color: rgba(var(--color-danger-rgb), 0.35) !important;
            box-shadow: 0 2px 10px rgba(var(--color-danger-rgb), 0.12), inset 0 1px 0 rgba(255,255,255,0.1);
        }

        /* ===== SLIDING DRAWER BACKDROP & DRAWER ===== */
        .sales-drawer-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 1050;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.38s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.38s;
        }
        .sales-drawer-backdrop.open {
            opacity: 1;
            visibility: visible;
        }
        .sales-drawer {
            position: fixed;
            top: 0;
            right: -480px;
            width: 100%;
            max-width: 480px;
            height: 100%;
            background: var(--color-card);
            border-left: 1px solid var(--color-border);
            box-shadow: -10px 0 35px rgba(0, 0, 0, 0.18);
            z-index: 1051;
            display: flex;
            flex-direction: column;
            transition: transform 0.38s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .sales-drawer-backdrop.open .sales-drawer {
            transform: translateX(-480px);
        }
        [data-theme="dark"] .sales-drawer {
            background: #0f172a; /* Dark background matching global system */
        }
        .sales-drawer-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .sales-drawer-title {
            font-size: 1.15rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            color: var(--color-text);
            font-family: 'Inter', sans-serif;
        }
        .sales-drawer-close {
            background: transparent;
            border: none;
            font-size: 1.6rem;
            color: var(--color-text-secondary);
            cursor: pointer;
            transition: color 0.2s, transform 0.2s;
            line-height: 1;
            padding: 0 0.5rem;
        }
        .sales-drawer-close:hover {
            color: var(--color-text);
            transform: scale(1.1);
        }
        .sales-drawer-body {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
        }
        .sales-drawer-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--color-border);
            display: flex;
            gap: 1rem;
            background: rgba(var(--color-border-rgb), 0.1);
        }

        /* ===== INVOICE PREMIUM DESIGN ===== */
        .invoice-wrapper {
            font-family: 'Inter', sans-serif;
            color: var(--color-text);
        }
        .invoice-header {
            border-bottom: 2px dashed var(--color-border);
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .invoice-brand {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--color-primary);
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
        }
        .invoice-no {
            font-family: monospace;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--color-text-secondary);
            background: rgba(var(--color-primary-rgb), 0.08);
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-sm);
        }
        .invoice-section-title {
            font-size: 0.72rem;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 0.08em;
            color: var(--color-text-secondary);
            margin-bottom: 0.5rem;
            border-bottom: 1px solid rgba(var(--color-border-rgb), 0.5);
            padding-bottom: 0.25rem;
        }
        .invoice-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .invoice-info-block p {
            margin: 0;
            font-size: 0.85rem;
        }
        .invoice-table {
            width: 100%;
            margin-bottom: 1.5rem;
        }
        .invoice-table th {
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--color-text-secondary);
            padding: 0.6rem 0;
            border-bottom: 1px solid var(--color-border);
        }
        .invoice-table td {
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(var(--color-border-rgb), 0.4);
            font-size: 0.85rem;
        }
        .invoice-total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 0;
            border-top: 2px dashed var(--color-border);
            margin-top: 1.5rem;
        }
        .invoice-total-label {
            font-weight: 800;
            font-size: 1.05rem;
            color: var(--color-text);
        }
        .invoice-total-val {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--color-primary);
        }

        /* ===== PRINT STYLE FOR DRAWER ACCENT ===== */
        @media print {
            .sales-drawer-backdrop {
                display: none !important;
            }
        }

        @media (max-width: 576px) {
            .sales-drawer {
                max-width: 100%;
                right: -100%;
            }
            .sales-drawer-backdrop.open .sales-drawer {
                transform: translateX(-100%);
            }
        }
    </style>
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

    <!-- Sliding Right Drawer para detalles de venta -->
    <div class="sales-drawer-backdrop" id="viewDetailsDrawerBackdrop">
        <div class="sales-drawer">
            <div class="sales-drawer-header">
                <h5 class="sales-drawer-title">
                    <i class="bi bi-receipt text-primary me-2"></i>
                    Detalle de Venta
                </h5>
                <button type="button" class="sales-drawer-close" id="closeDrawerBtn">&times;</button>
            </div>
            <div class="sales-drawer-body">
                <!-- Cargando -->
                <div id="drawer-loading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted">Cargando detalles de la venta...</p>
                </div>

                <!-- Contenido Premium de Factura -->
                <div id="drawer-content" class="invoice-wrapper" style="display: none;">
                    <div class="invoice-header text-center">
                        <div class="invoice-brand">CENTRO MÉDICO RS</div>
                        <div class="text-muted small">Servicios de Salud Premium</div>
                        <div class="invoice-no mt-2" id="drawer-sale-no">#VTA-00000</div>
                    </div>

                    <div class="invoice-grid">
                        <div class="invoice-info-block">
                            <div class="invoice-section-title">Cliente</div>
                            <p class="fw-bold mb-1" id="drawer-cliente">---</p>
                            <p class="small text-muted" id="drawer-fecha">---</p>
                        </div>
                        <div class="invoice-info-block text-end">
                            <div class="invoice-section-title">Pago y Estado</div>
                            <p class="fw-bold mb-1" id="drawer-tipo-pago">---</p>
                            <div id="drawer-estado-badge" style="display: inline-block; margin-top: 0.25rem;">---</div>
                        </div>
                    </div>

                    <div class="invoice-section-title">Productos Adquiridos</div>
                    <div class="table-responsive">
                        <table class="invoice-table" id="drawer-items">
                            <thead>
                                <tr>
                                    <th class="text-start">Concepto</th>
                                    <th class="text-center" style="width: 60px;">Cant.</th>
                                    <th class="text-end" style="width: 90px;">Precio</th>
                                    <th class="text-end" style="width: 100px;">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Dinámico -->
                            </tbody>
                        </table>
                    </div>

                    <div class="invoice-total-row">
                        <span class="invoice-total-label">Total a Pagar</span>
                        <span class="invoice-total-val" id="drawer-total">Q0.00</span>
                    </div>
                </div>
            </div>
            <div class="sales-drawer-footer">
                <button type="button" class="action-btn secondary w-100" id="closeDrawerBtn2">Cerrar</button>
                <a href="#" class="action-btn primary w-100 text-center" id="drawer-print-btn" target="_blank">
                    <i class="bi bi-printer me-2"></i>
                    Imprimir Recibo
                </a>
            </div>
        </div>
    </div>

    <!-- Modal para reporte por jornada -->
    <div class="custom-modal-overlay" id="reportModal">
        <div class="custom-modal modal-sm">
            <div class="custom-modal-header">
                <h5 class="custom-modal-title">
                    <i class="bi bi-file-earmark-bar-graph text-success me-2"></i>
                    Reporte por Jornada
                </h5>
                <button type="button" class="custom-modal-close" onclick="this.closest('.custom-modal-overlay').classList.remove('active')">&times;</button>
            </div>
            <div class="custom-modal-body">
                <p class="text-muted small mb-3">
                    La jornada comprende desde las <strong>08:00 AM</strong> de la fecha seleccionada hasta las
                    <strong>08:00 AM</strong> del día siguiente.
                </p>
                <div class="form-group mb-4">
                    <label class="form-label">Seleccionar Fecha de Inicio</label>
                    <input type="date" class="form-control" id="reportDate" value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="action-btn secondary" onclick="document.getElementById('reportModal').classList.remove('active')">Cancelar</button>
                <button type="button" class="action-btn success primary" id="btnGenerateReport">
                    <i class="bi bi-file-earmark-pdf me-2"></i>
                    Generar Reporte
                </button>
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
                    this.setupReturnHandlers();
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

                            // Mostrar drawer
                            const drawerBackdrop = document.getElementById('viewDetailsDrawerBackdrop');
                            if (drawerBackdrop) {
                                drawerBackdrop.classList.add('open');
                            }

                            // Cargar datos de la venta
                            this.loadSaleDetails(saleId);
                        });
                    });

                    // Eventos para cerrar drawer
                    const closeBtns = [
                        document.getElementById('closeDrawerBtn'),
                        document.getElementById('closeDrawerBtn2'),
                        document.getElementById('viewDetailsDrawerBackdrop')
                    ];

                    closeBtns.forEach(btn => {
                        if (btn) {
                            btn.addEventListener('click', (e) => {
                                // Si es el backdrop, solo cerrar si hizo click directamente en él
                                if (e.target === btn || btn.id !== 'viewDetailsDrawerBackdrop') {
                                    document.getElementById('viewDetailsDrawerBackdrop').classList.remove('open');
                                }
                            });
                        }
                    });
                }

                async loadSaleDetails(saleId) {
                    const loading = document.getElementById('drawer-loading');
                    const content = document.getElementById('drawer-content');

                    // Mostrar loading
                    loading.style.display = 'block';
                    content.style.display = 'none';

                    try {
                        const response = await fetch(`get_sale_details.php?id=${saleId}`);
                        const data = await response.json();

                        if (data.status === 'success') {
                            // Actualizar información principal
                            document.getElementById('drawer-sale-no').textContent = `#VTA-${String(saleId).padStart(5, '0')}`;
                            document.getElementById('drawer-cliente').textContent = data.venta.nombre_cliente || 'Consumidor Final';

                            // Formatear fecha
                            const fechaVenta = new Date(data.venta.fecha_venta);
                            const fechaFormateada = fechaVenta.toLocaleDateString('es-GT', {
                                year: 'numeric',
                                month: 'short',
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            });
                            document.getElementById('drawer-fecha').textContent = fechaFormateada;

                            document.getElementById('drawer-tipo-pago').textContent = data.venta.tipo_pago || 'Efectivo';
                            
                            // Estado de pago en badge brillante
                            const estado = data.venta.estado || 'Pendiente';
                            const estadoBadge = document.getElementById('drawer-estado-badge');
                            if (estadoBadge) {
                                estadoBadge.className = `status-badge ${estado.toLowerCase()}`;
                                estadoBadge.textContent = estado;
                            }

                            // Actualizar total
                            document.getElementById('drawer-total').textContent = `Q${parseFloat(data.venta.total || 0).toFixed(2)}`;

                            // Actualizar enlace de impresión
                            const printBtn = document.getElementById('drawer-print-btn');
                            if (printBtn) {
                                printBtn.href = `../dispensary/print_receipt.php?id=${saleId}`;
                            }

                            // Actualizar tabla de ítems
                            const itemsTable = document.querySelector('#drawer-items tbody');
                            itemsTable.innerHTML = '';

                            if (data.items && data.items.length > 0) {
                                data.items.forEach(item => {
                                    const row = document.createElement('tr');
                                    row.innerHTML = `
                                        <td class="text-start">
                                            <div class="fw-bold" style="color: var(--color-text);">${item.nom_medicamento || 'Producto'}</div>
                                            <div class="text-muted small">${item.presentacion_med || 'N/A'}</div>
                                        </td>
                                        <td class="text-center">${item.cantidad_vendida || 0}</td>
                                        <td class="text-end">Q${parseFloat(item.precio_unitario || 0).toFixed(2)}</td>
                                        <td class="text-end fw-bold" style="color: var(--color-text);">Q${parseFloat(item.subtotal || 0).toFixed(2)}</td>
                                    `;
                                    itemsTable.appendChild(row);
                                });
                            } else {
                                itemsTable.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No hay ítems registrados</td></tr>';
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
                            <div class="alert alert-danger mx-3 my-4" role="alert">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Error al cargar detalles de la venta: ${error.message}
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

                            window.open(`generate_shift_report.php?date=${date}`, '_blank');
                            window.location.reload();
                        });
                    }
                }

                setupReturnHandlers() {
                    document.querySelectorAll('.return-sale').forEach(btn => {
                        btn.addEventListener('click', async () => {
                            const saleId = btn.getAttribute('data-id');
                            if (!saleId) return;

                            const result = await Swal.fire({
                                title: '¿Devolver Venta?',
                                text: 'Esta acción devolverá el stock al inventario y cambiará el estado a "Devuelto".',
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#dc3545',
                                cancelButtonColor: '#6b7280',
                                confirmButtonText: 'Sí, devolver',
                                cancelButtonText: 'Cancelar'
                            });

                            if (!result.isConfirmed) return;

                            Swal.fire({
                                title: 'Procesando...',
                                didOpen: () => Swal.showLoading()
                            });

                            try {
                                const response = await fetch('return_sale.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: `id_venta=${saleId}`
                                });
                                const data = await response.json();

                                if (data.success) {
                                    Swal.fire({
                                        title: 'Listo',
                                        text: 'Venta devuelta correctamente',
                                        icon: 'success',
                                        timer: 1500,
                                        showConfirmButton: false
                                    }).then(() => location.reload());
                                } else {
                                    Swal.fire('Error', data.message || 'No se pudo devolver la venta', 'error');
                                }
                            } catch (error) {
                                Swal.fire('Error', 'Error de conexión', 'error');
                            }
                        });
                    });
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