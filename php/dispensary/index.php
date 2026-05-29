<?php
// inventory/index.php - Módulo de Ventas - Centro Médico RS
// Versión: 4.0 - Diseño Responsive con Sidebar Moderna y Efecto Mármol
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';
require_once '../../includes/module_guard.php';

check_module_access('pharmacy');

date_default_timezone_set('America/Guatemala');
verify_session();

try {
    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();

    // Crear tabla de reservas si no existe
    $conn->exec("CREATE TABLE IF NOT EXISTS reservas_inventario (
        id_reserva INT AUTO_INCREMENT PRIMARY KEY,
        id_inventario INT NOT NULL,
        cantidad INT NOT NULL,
        session_id VARCHAR(255) NOT NULL,
        fecha_reserva TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (id_inventario),
        INDEX (session_id)
    )");

    // Limpiar reservas antiguas (> 60 minutos)
    $conn->exec("DELETE FROM reservas_inventario WHERE fecha_reserva < (NOW() - INTERVAL 1 HOUR)");

    // Obtener items de inventario para venta, filtrando por hospital
    $stmt = $conn->prepare("
        SELECT i.id_inventario, i.codigo_barras, i.nom_medicamento, i.mol_medicamento, 
               i.presentacion_med, i.casa_farmaceutica, i.cantidad_med, i.stock_hospital,
               i.precio_venta, i.precio_hospital, i.precio_medico, i.precio_compra, i.fecha_vencimiento,
               (i.cantidad_med - COALESCE((SELECT SUM(cantidad) FROM reservas_inventario WHERE id_inventario = i.id_inventario), 0)) as disponible,
               ph.document_type, ph.document_number
        FROM inventario i
        LEFT JOIN purchase_items pi ON i.id_purchase_item = pi.id
        LEFT JOIN purchase_headers ph ON pi.purchase_header_id = ph.id
        WHERE i.cantidad_med > 0 AND i.estado != 'Pendiente' AND i.id_hospital = ?
        ORDER BY i.nom_medicamento
    ");
    $stmt->execute([hospital_id()]);
    $inventario = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener estadísticas para el dashboard
    // Ventas del día
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(total) as total FROM ventas WHERE DATE(fecha_venta) = ? AND id_hospital = ?");
    $stmt->execute([$today, hospital_id()]);
    $today_sales = $stmt->fetch(PDO::FETCH_ASSOC);

    // Ventas del mes
    $month_start = date('Y-m-01');
    $month_end = date('Y-m-t');
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(total) as total FROM ventas WHERE fecha_venta BETWEEN ? AND ? AND id_hospital = ?");
    $stmt->execute([$month_start, $month_end, hospital_id()]);
    $month_sales = $stmt->fetch(PDO::FETCH_ASSOC);

    // Total de ventas
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(total) as total FROM ventas WHERE id_hospital = ?");
    $stmt->execute([hospital_id()]);
    $total_sales = $stmt->fetch(PDO::FETCH_ASSOC);

    // Productos en inventario
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(cantidad_med) as total FROM inventario WHERE cantidad_med > 0 AND id_hospital = ?");
    $stmt->execute([hospital_id()]);
    $inventory_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Información del usuario
    $user_name = $_SESSION['nombre'];
    $user_type = $_SESSION['tipoUsuario'];
    $user_specialty = $_SESSION['especialidad'] ?? 'Profesional Médico';

    // Título de la página
    $page_title = "Ventas - Centro Médico RS";

} catch (Exception $e) {
    error_log('Error en dispensary/index.php: ' . $e->getMessage());
    die("Error: " . 'Error del servidor.');
}


?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Módulo de Ventas del Centro Médico RS - Sistema de gestión médica">
    <meta name="csrf-token" content="<?php echo csrf_token(); ?>">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">

    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- FullCalendar -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet'>

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Seguridad y Protección de Código -->
    <script src="../../assets/js/security.js"></script>

    <!-- CSS Crítico (incrustado - mismo que dashboard) -->
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">

    <style>
        :root {
            --pos-sidebar-width: 400px;
            --pos-grid-gap: 1.5rem;
        }

        .pos-container {
            display: grid;
            grid-template-columns: 1fr var(--pos-sidebar-width);
            gap: var(--pos-grid-gap);
            margin-top: 1rem;
        }

        @media (max-width: 1200px) {
            .pos-container {
                grid-template-columns: 1fr;
            }
            :root {
                --pos-sidebar-width: 100%;
            }
        }

        /* Search Area Enhancements */
        .pos-selection-area {
            background: var(--color-card);
            border-radius: var(--radius-xl);
            padding: 2rem;
            border: 1px solid var(--color-border);
            box-shadow: var(--shadow-sm);
        }

        .search-container {
            position: relative;
            z-index: 100;
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: 0 0 var(--radius-lg) var(--radius-lg);
            box-shadow: var(--shadow-lg);
            max-height: 400px;
            overflow-y: auto;
            margin-top: -1px;
            backdrop-filter: blur(10px);
            background: rgba(var(--color-card-rgb), 0.95);
        }

        .search-result-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--color-border);
            cursor: pointer;
            transition: all 0.2s;
        }

        .search-result-item:hover {
            background: rgba(var(--color-primary-rgb), 0.05);
            padding-left: 1.75rem;
        }

        /* Cart Area Enhancements */
        .pos-cart-area {
            background: var(--color-card);
            border-radius: var(--radius-xl);
            border: 1px solid var(--color-border);
            display: flex;
            flex-direction: column;
            height: fit-content;
            position: sticky;
            top: 2rem;
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .cart-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--color-border);
            background: rgba(var(--color-primary-rgb), 0.02);
        }

        .cart-items {
            flex: 1;
            max-height: 50vh;
            overflow-y: auto;
            padding: 1rem;
        }

        .cart-footer {
            padding: 1.5rem;
            background: var(--color-surface);
            border-top: 1px solid var(--color-border);
        }

        .cart-total {
            background: var(--color-primary);
            color: white;
            padding: 1.25rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(var(--color-primary-rgb), 0.3);
        }

        .total-amount {
            font-size: 1.75rem;
            font-weight: 700;
        }

        /* Product Detail Card */
        .selection-details {
            margin-top: 2rem;
            padding: 2rem;
            background: var(--color-surface);
            border-radius: var(--radius-lg);
            border: 1px solid var(--color-border);
            animation: slideUp 0.4s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .cart-items-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 0.5rem;
        }

        .cart-items-table th {
            padding: 0.75rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--color-text-muted);
            font-weight: 700;
        }

        .cart-item-row {
            background: var(--color-surface);
            transition: transform 0.2s;
        }

        .cart-item-row:hover {
            transform: scale(1.01);
        }

        .cart-item-row td {
            padding: 1rem 0.75rem;
            border-top: 1px solid var(--color-border);
            border-bottom: 1px solid var(--color-border);
        }

        .cart-item-row td:first-child {
            border-left: 1px solid var(--color-border);
            border-radius: var(--radius-md) 0 0 var(--radius-md);
        }

        .cart-item-row td:last-child {
            border-right: 1px solid var(--color-border);
            border-radius: 0 var(--radius-md) var(--radius-md) 0;
        }

        /* Mode Toggles */
        .mode-toggles .btn {
            border-radius: var(--radius-md);
            font-weight: 600;
            padding: 0.6rem 1.2rem;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            transition: all 0.3s;
        }

        .mode-toggles .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        /* Empty State */
        .empty-state {
            padding: 3rem 1rem;
            text-align: center;
            opacity: 0.6;
        }

        .empty-icon {
            font-size: 4rem;
            color: var(--color-text-muted);
            margin-bottom: 1rem;
        }

        /* ===== FORM INPUTS (POS) ===== */
        .form-input {
            width: 100%;
            padding: 0.625rem 0.875rem;
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-md);
            background: var(--color-card);
            color: var(--color-text);
            font-size: var(--font-size-sm);
            font-family: var(--font-family);
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
            outline: none;
            appearance: none;
            -webkit-appearance: none;
        }

        .form-input:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.13);
            background: var(--color-card);
        }

        .form-input::placeholder {
            color: var(--color-text-secondary);
            font-style: italic;
        }

        .form-input[readonly] {
            background: var(--color-surface);
            color: var(--color-text-secondary);
            cursor: not-allowed;
            border-style: dashed;
        }

        select.form-input {
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.875rem center;
            padding-right: 2.25rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .form-label {
            font-size: var(--font-size-xs);
            font-weight: 700;
            color: var(--color-text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ===== SELECTION FORM ===== */
        .selection-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        /* Price prefix */
        .selection-form .d-flex.align-items-center {
            background: var(--color-card);
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-md);
            overflow: hidden;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .selection-form .d-flex.align-items-center:focus-within {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.13);
        }

        .selection-form .d-flex.align-items-center > span {
            padding: 0.625rem 0.75rem;
            background: var(--color-surface);
            color: var(--color-text-secondary);
            font-weight: 700;
            font-size: var(--font-size-sm);
            border-right: 1px solid var(--color-border);
            flex-shrink: 0;
        }

        .selection-form .d-flex.align-items-center > input {
            border: none !important;
            box-shadow: none !important;
            border-radius: 0 !important;
            background: transparent !important;
        }

        /* ===== ADD TO CART BUTTON ===== */
        .add-button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            width: 100%;
            padding: 0.875rem 1.5rem;
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark, #0a58ca));
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: var(--font-size-sm);
            font-family: var(--font-family);
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 4px 14px rgba(var(--color-primary-rgb), 0.3);
            letter-spacing: 0.3px;
        }

        .add-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(var(--color-primary-rgb), 0.4);
            filter: brightness(1.05);
        }

        .add-button:active {
            transform: translateY(0);
        }

        .add-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* ===== CLIENT FORM (cart header) ===== */
        .client-form {
            display: flex;
            flex-direction: column;
            gap: 0.875rem;
        }

        .client-form .row {
            display: flex;
            gap: 0.75rem;
        }

        .client-form .row .form-group {
            flex: 1;
        }

        /* clear & checkout buttons */
        .cart-actions {
            display: flex;
            gap: 0.75rem;
        }

        .clear-button {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background: var(--color-surface);
            color: var(--color-danger);
            border: 1.5px solid var(--color-danger);
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: var(--font-size-sm);
            font-family: var(--font-family);
            cursor: pointer;
            transition: all 0.2s;
        }

        .clear-button:hover {
            background: var(--color-danger);
            color: white;
            transform: translateY(-1px);
        }

        .checkout-button {
            flex: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.875rem 1.25rem;
            background: linear-gradient(135deg, var(--color-success), #059669);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 700;
            font-size: var(--font-size-sm);
            font-family: var(--font-family);
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 4px 14px rgba(var(--color-success-rgb), 0.3);
            letter-spacing: 0.3px;
        }

        .checkout-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(var(--color-success-rgb), 0.4);
            filter: brightness(1.05);
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
            <!-- Bienvenida personalizada -->
            <div class="stat-card mb-4 animate-in">
                <div class="stat-header">
                    <div>
                        <h2 id="greeting" style="font-size: 1.75rem; margin-bottom: 0.5rem;">
                            <span id="greeting-text">Buenos días</span>, <?php echo htmlspecialchars($user_name); ?>
                        </h2>
                        <p class="text-muted mb-0">
                            <i class="bi bi-receipt me-1"></i> Módulo de Ventas
                            <span class="mx-2">•</span>
                            <i class="bi bi-calendar-check me-1"></i> <?php echo date('d/m/Y'); ?>
                            <span class="mx-2">•</span>
                            <i class="bi bi-clock me-1"></i> <span id="current-time"><?php echo date('H:i'); ?></span>
                        </p>
                    </div>
                    <div class="d-none d-md-block">
                        <i class="bi bi-cart4 text-primary" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>

            <!-- Estadísticas principales -->
            <div class="stats-grid">
                <!-- Ventas del día -->
                <div class="stat-card animate-in delay-1">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Ventas Hoy</div>
                            <div class="stat-value"><?php echo $today_sales['count'] ?? 0; ?></div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="bi bi-cart-check"></i>
                        </div>
                    </div>
                    <div class="text-muted">
                        Total: Q<?php echo number_format($today_sales['total'] ?? 0, 2); ?>
                    </div>
                </div>

                <!-- Ventas del mes -->
                <div class="stat-card animate-in delay-2">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Ventas Mes</div>
                            <div class="stat-value"><?php echo $month_sales['count'] ?? 0; ?></div>
                        </div>
                        <div class="stat-icon success">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                    </div>
                    <div class="text-muted">
                        Total: Q<?php echo number_format($month_sales['total'] ?? 0, 2); ?>
                    </div>
                </div>

                <!-- Total ventas -->
                <div class="stat-card animate-in delay-3">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Total Ventas</div>
                            <div class="stat-value"><?php echo $total_sales['count'] ?? 0; ?></div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                    </div>
                    <div class="text-muted">
                        Total: Q<?php echo number_format($total_sales['total'] ?? 0, 2); ?>
                    </div>
                </div>

                <!-- Productos en inventario -->
                <div class="stat-card animate-in delay-4">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Productos</div>
                            <div class="stat-value"><?php echo $inventory_stats['count'] ?? 0; ?></div>
                        </div>
                        <div class="stat-icon info">
                            <i class="bi bi-box-seam"></i>
                        </div>
                    </div>
                    <div class="text-muted">
                        Unidades: <?php echo $inventory_stats['total'] ?? 0; ?>
                    </div>
                </div>
            </div>

            <!-- Punto de Venta -->
            <div class="pos-container">
                <!-- Columna Izquierda: Búsqueda y Selección -->
                <div class="pos-left-column">
                    <!-- Panel de Búsqueda -->
                    <div class="pos-selection-area animate-in">
                        <div class="selection-header">
                            <h2 class="section-title">
                                <i class="bi bi-search section-title-icon"></i>
                                Buscar Producto
                            </h2>
                            <div class="mode-toggles btn-group mb-3">
                                <button class="btn btn-primary active" id="btnModePublic"
                                    onclick="window.dashboard.pos.setMode('public')">
                                    <i class="bi bi-shop me-1"></i> Público
                                </button>
                                <button class="btn btn-outline-info" id="btnModeHospital"
                                    onclick="window.dashboard.pos.requestAuth('hospital')">
                                    <i class="bi bi-hospital me-1"></i> Hospitalario
                                </button>
                                <button class="btn btn-outline-success" id="btnModeMedical"
                                    onclick="window.dashboard.pos.requestAuth('medical')">
                                    <i class="bi bi-person-badge me-1"></i> Médico
                                </button>
                                <button class="btn btn-outline-warning" id="btnModeSpecial"
                                    onclick="window.dashboard.pos.requestAuth('special')">
                                    <i class="bi bi-tag me-1"></i> Precio Esp
                                </button>
                                <button class="btn btn-outline-danger" id="btnModeTransfer"
                                    onclick="window.dashboard.pos.requestAuth('transfer')">
                                    <i class="bi bi-arrow-left-right me-1"></i> Traslado
                                </button>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mb-3 gap-2">
                            <button class="btn btn-info btn-sm fw-bold shadow-sm"
                                onclick="window.dashboard.pos.openHistory()">
                                <i class="bi bi-clock-history me-1"></i> Historial
                            </button>
                            <button class="btn btn-outline-danger btn-sm fw-bold shadow-sm"
                                onclick="window.dashboard.pos.openHistory('Traslado')">
                                <i class="bi bi-arrow-left-right me-1"></i> Historial Traslados
                            </button>
                            <button class="btn btn-warning btn-sm fw-bold shadow-sm"
                                onclick="window.dashboard.pos.openShiftReport()">
                                <i class="bi bi-receipt-cutoff me-1"></i> Corte de Jornada
                            </button>
                        </div>

                        <!-- Búsqueda -->
                        <div class="search-container">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" class="form-control border-start-0 ps-2" id="searchMedication"
                                    placeholder="Escanee código o busque por nombre/molécula..." autocomplete="off">
                            </div>

                            <div class="search-results-header mt-2 px-2 d-none" id="searchResultsHeader">
                                <div class="row g-0 fw-bold small text-muted text-uppercase">
                                    <div class="col-5">Medicamento</div>
                                    <div class="col-2 text-center">Precio</div>
                                    <div class="col-3 text-center">Doc/Env</div>
                                    <div class="col-2 text-end">Vence</div>
                                </div>
                            </div>
                            <div class="search-results" id="searchResults"></div>
                        </div>
                    </div>

                    <div class="selection-details shadow-sm border-0" id="selectionDetails">
                        <div class="selected-product mb-4">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h4 class="selected-product-name fw-bold text-primary mb-1" id="selectedProductName">---</h4>
                                    <p class="selected-product-details text-muted small" id="selectedProductDetails">---</p>
                                </div>
                                <div class="badge bg-primary-subtle text-primary border p-2" id="documentTypeDisplay">---</div>
                            </div>

                            <div class="row g-3 mt-2">
                                <div class="col-md-6">
                                    <div class="stat-card p-3 text-center border">
                                        <div class="small text-muted text-uppercase fw-bold mb-1">Stock Disponible</div>
                                        <div class="h4 mb-0 fw-bold" id="availableStock">0</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="stat-card p-3 text-center border">
                                        <div class="small text-muted text-uppercase fw-bold mb-1">Vencimiento</div>
                                        <div class="h4 mb-0 fw-bold" id="expiryDate">---</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <form class="selection-form" id="addToCartForm">
                            <div class="form-group">
                                <label class="form-label">Precio Unitario</label>
                                <div class="d-flex align-items-center">
                                    <span class="me-2">Q</span>
                                    <input type="number" class="form-input" id="unitPrice" step="1" min="0" required
                                        style="flex: 1;" readonly>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Cantidad</label>
                                <input type="number" class="form-input" id="quantity" min="1" value="1" required>
                            </div>

                            <button type="button" class="add-button" id="addToCartBtn">
                                <i class="bi bi-cart-plus"></i>
                                Agregar al Carrito
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Panel derecho: Carrito de compras -->
                <div class="pos-cart-area animate-in delay-1">
                    <!-- Encabezado del carrito -->
                    <div class="cart-header">
                        <h3 class="section-title">
                            <i class="bi bi-cart4 section-title-icon"></i>
                            Carrito de Ventas
                        </h3>

                        <!-- Datos del cliente -->
                        <div class="client-form mt-3">
                            <div class="form-group">
                                <label class="form-label">Nombre del Cliente</label>
                                <input type="text" class="form-input" id="clientName"
                                    placeholder="Nombre completo del cliente..." autocomplete="off">
                            </div>

                            <div class="form-group mt-2">
                                <label class="form-label">NIT</label>
                                <input type="text" class="form-input" id="clientNIT" value="C/F"
                                    placeholder="NIT o C/F...">
                            </div>

                            <div class="form-group mt-2">
                                <label class="form-label">Método de Pago</label>
                                <select class="form-input" id="paymentMethod">
                                    <option value="Efectivo">Efectivo</option>
                                    <option value="Tarjeta">Tarjeta</option>
                                    <option value="Transferencia">Transferencia</option>
                                </select>
                            </div>

                            <div class="row mt-2" style="display: flex; gap: 10px;">
                                <div class="form-group" style="flex: 1;">
                                    <label class="form-label">Tipo de Documento</label>
                                    <select class="form-input" id="documentType">
                                        <option value="Nota de Envío">Nota de Envío</option>
                                        <option value="Factura">Factura</option>
                                    </select>
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label class="form-label">N° Correlativo</label>
                                    <input type="text" class="form-input" id="documentNumber" placeholder="0001...">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lista de items -->
                    <div class="cart-items">
                        <div class="empty-state" id="emptyCart">
                            <div class="empty-icon">
                                <i class="bi bi-cart-x"></i>
                            </div>
                            <h4 class="text-muted mb-2">Carrito Vacío</h4>
                            <p class="text-muted mb-3">Busque y agregue productos para realizar una venta.</p>
                        </div>

                        <table class="cart-items-table" id="cartTable" style="display: none;">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th style="text-align: center;">Cant.</th>
                                    <th style="text-align: right;">Subtotal</th>
                                    <th style="width: 40px;"></th>
                                </tr>
                            </thead>
                            <tbody id="cartItemsBody">
                                <!-- Items se insertarán aquí dinámicamente -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Total y acciones -->
                    <div class="cart-footer">
                        <div class="cart-total">
                            <div class="total-label">Total a Pagar</div>
                            <div class="total-amount" id="cartTotal">Q0.00</div>
                        </div>

                        <div class="cart-actions">
                            <button class="clear-button" id="clearCartBtn">
                                <i class="bi bi-trash"></i>
                                Vaciar Carrito
                            </button>

                            <button class="checkout-button" id="checkoutBtn">
                                <i class="bi bi-printer-fill"></i>
                                Procesar Venta
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- History Modal -->
    <div class="modal fade" id="historyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem;">
                <div class="modal-header bg-info text-white py-3">
                    <h5 class="modal-title fw-bold"><i class="bi bi-clock-history me-2"></i>Historial de Ventas (Turno
                        Actual)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <ul class="nav nav-tabs nav-fill" id="historyTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="list-tab" data-bs-toggle="tab"
                                data-bs-target="#list-pane" type="button" role="tab">
                                <i class="bi bi-list-ul me-2"></i>Lista
                            </button>
                        </li>
                        <li class="nav-item d-none" id="detail-tab-container" role="presentation">
                            <button class="nav-link" id="detail-tab" data-bs-toggle="tab" data-bs-target="#detail-pane"
                                type="button" role="tab" onclick="window.dashboard.pos.loadTransferDetails()">
                                <i class="bi bi-box-seam me-2"></i>Detalle por Insumo
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="calendar-tab" data-bs-toggle="tab"
                                data-bs-target="#calendar-pane" type="button" role="tab"
                                onclick="window.dashboard.pos.initHistoryCalendar()">
                                <i class="bi bi-calendar3 me-2"></i>Calendario
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content" id="historyTabContent">
                        <div class="tab-pane fade show active" id="list-pane" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="ps-4">Hora</th>
                                            <th>Cliente</th>
                                            <th class="text-end">Total</th>
                                            <th class="text-center pe-4">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="historyTableBody">
                                        <!-- Data will be injected here -->
                                    </tbody>
                                </table>
                            </div>
                            <div id="historyLoading" class="text-center py-5">
                                <div class="spinner-border text-info" role="status"></div>
                                <p class="mt-2 text-muted">Cargando historial...</p>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="detail-pane" role="tabpanel">
                            <div class="p-3 border-bottom bg-light">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted mb-1">Fecha Inicio</label>
                                        <input type="date" class="form-control form-control-sm" id="transferStartDate"
                                            value="<?php echo date('Y-m-d'); ?>"
                                            onchange="window.dashboard.pos.loadTransferDetails()">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted mb-1">Fecha Fin</label>
                                        <input type="date" class="form-control form-control-sm" id="transferEndDate"
                                            value="<?php echo date('Y-m-d'); ?>"
                                            onchange="window.dashboard.pos.loadTransferDetails()">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted mb-1">Buscar Producto</label>
                                        <input type="text" class="form-control form-control-sm" id="transferSearch"
                                            placeholder="Nombre de insumo..."
                                            oninput="window.dashboard.pos.loadTransferDetails()">
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="bg-light sticky-top">
                                        <tr>
                                            <th class="ps-3">Fecha</th>
                                            <th>Producto</th>
                                            <th>Realizado Por</th>
                                            <th>A Quién</th>
                                            <th class="text-end pe-3">Cant.</th>
                                        </tr>
                                    </thead>
                                    <tbody id="transferDetailsBody">
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-3">Seleccione un rango de
                                                fechas</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="calendar-pane" role="tabpanel">
                            <div id="historyCalendar" class="p-3" style="min-height: 500px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Auth Modal -->
    <div class="modal fade" id="authModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Autorización Requerida</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="authCodeInput" class="form-label">Código de Acceso</label>
                        <input type="password" class="form-control" id="authCodeInput" placeholder="Ingrese código">
                    </div>
                    <button type="button" class="btn btn-primary w-100"
                        onclick="window.dashboard.pos.verifyAuth()">Verificar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- FullCalendar JS -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/es.js'></script>

    <!-- JavaScript Optimizado (mismo que dashboard con funcionalidad POS) -->
    <script>
        // Dashboard Reingenierizado - Centro Médico RS
        // Módulo de Ventas - Punto de Venta

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
                // UI Elements
                html: document.documentElement,
                themeBtn: document.getElementById('themeBtn'),
                logoutBtn: document.getElementById('logoutBtn'),
                greetingElement: document.getElementById('greeting'),
                currentTimeElement: document.getElementById('currentTime'),

                // POS Elements
                searchMedication: document.getElementById('searchMedication'),
                searchResults: document.getElementById('searchResults'),
                selectionDetails: document.getElementById('selectionDetails'),
                selectedProductName: document.getElementById('selectedProductName'),
                selectedProductDetails: document.getElementById('selectedProductDetails'),
                availableStock: document.getElementById('availableStock'),
                documentType: document.getElementById('documentType'),
                expiryDate: document.getElementById('expiryDate'),
                unitPrice: document.getElementById('unitPrice'),
                quantity: document.getElementById('quantity'),
                addToCartBtn: document.getElementById('addToCartBtn'),
                clientName: document.getElementById('clientName'),
                paymentMethod: document.getElementById('paymentMethod'),
                documentType: document.getElementById('documentType'),
                documentNumber: document.getElementById('documentNumber'),
                emptyCart: document.getElementById('emptyCart'),
                cartTable: document.getElementById('cartTable'),
                cartItemsBody: document.getElementById('cartItemsBody'),
                cartTotal: document.getElementById('cartTotal'),
                clearCartBtn: document.getElementById('clearCartBtn'),
                checkoutBtn: document.getElementById('checkoutBtn')
            };

            // ==========================================================================
            // DATOS GLOBALES
            // ==========================================================================
            let cartItems = [];
            let currentInventory = <?php echo json_encode($inventario); ?>;
            let selectedItem = null;
            let currentMode = 'public'; // public, hospital, medical

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
                    const savedTheme = localStorage.getItem(CONFIG.themeKey);
                    if (savedTheme) return savedTheme;

                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    if (prefersDark) return 'dark';

                    return 'light';
                }

                applyTheme(theme) {
                    DOM.html.setAttribute('data-theme', theme);
                    localStorage.setItem(CONFIG.themeKey, theme);

                    const metaTheme = document.querySelector('meta[name="theme-color"]');
                    if (metaTheme) {
                        metaTheme.setAttribute('content', theme === 'dark' ? '#0f172a' : '#ffffff');
                    }
                }

                toggleTheme() {
                    const newTheme = this.theme === 'light' ? 'dark' : 'light';
                    this.theme = newTheme;
                    this.applyTheme(newTheme);

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
                    this.setupPOS();
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

                setupPOS() {
                    this.setupSearch();
                    this.setupCart();
                    this.setupAuth();
                }

                setupAuth() {
                    const input = document.getElementById('authCodeInput');
                    if (input) {
                        input.addEventListener('keypress', (e) => {
                            if (e.key === 'Enter') this.verifyAuth();
                        });
                    }
                }

                requestAuth(mode) {
                    this.pendingMode = mode;
                    const modal = new bootstrap.Modal(document.getElementById('authModal'));
                    document.getElementById('authCodeInput').value = '';
                    modal.show();
                    setTimeout(() => document.getElementById('authCodeInput').focus(), 500);
                }

                verifyAuth() {
                    const code = document.getElementById('authCodeInput').value;
                    const btn = document.querySelector('#authModal .btn-primary');
                    const originalText = btn.innerHTML;

                    if (!code) {
                        this.showAlert('Ingrese el código', 'warning');
                        return;
                    }

                    btn.disabled = true;
                    btn.innerHTML = 'Verificando...';

                    fetch('check_auth.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ code: code })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                bootstrap.Modal.getInstance(document.getElementById('authModal')).hide();
                                this.setMode(this.pendingMode);
                                this.showAlert('Modo habilitado correctamente', 'success');
                            } else {
                                this.showAlert(data.message || 'Código incorrecto', 'error');
                                document.getElementById('authCodeInput').value = '';
                                document.getElementById('authCodeInput').focus();
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            this.showAlert('Error de conexión', 'error');
                        })
                        .finally(() => {
                            btn.disabled = false;
                            btn.innerHTML = originalText;
                        });
                }

                setMode(mode) {
                    currentMode = mode;

                    // Update buttons
                    document.getElementById('btnModePublic').className = `btn ${mode === 'public' ? 'btn-primary' : 'btn-outline-primary'}`;
                    document.getElementById('btnModeHospital').className = `btn ${mode === 'hospital' ? 'btn-info text-white' : 'btn-outline-info'}`;
                    document.getElementById('btnModeMedical').className = `btn ${mode === 'medical' ? 'btn-success' : 'btn-outline-success'}`;
                    document.getElementById('btnModeSpecial').className = `btn ${mode === 'special' ? 'btn-warning text-white' : 'btn-outline-warning'}`;
                    document.getElementById('btnModeTransfer').className = `btn ${mode === 'transfer' ? 'btn-danger text-white' : 'btn-outline-danger'}`;

                    // Toggle price editing
                    if (mode === 'special') {
                        DOM.unitPrice.removeAttribute('readonly');
                    } else {
                        DOM.unitPrice.setAttribute('readonly', true);
                    }

                    // Update UI if item selected
                    if (selectedItem) {
                        this.selectProduct(selectedItem);
                    }
                    // Clear search to avoid confusion
                    DOM.searchMedication.value = '';
                    DOM.searchResults.style.display = 'none';
                }

                setupSearch() {
                    // Búsqueda en tiempo real
                    DOM.searchMedication.addEventListener('input', () => {
                        this.performSearch(DOM.searchMedication.value);
                    });

                    // Cerrar resultados al hacer clic fuera
                    document.addEventListener('click', (event) => {
                        if (!DOM.searchMedication.contains(event.target) && !DOM.searchResults.contains(event.target)) {
                            DOM.searchResults.style.display = 'none';
                        }
                    });
                }

                performSearch(searchTerm) {
                    DOM.searchResults.innerHTML = '';

                    if (searchTerm.length < 2) {
                        DOM.searchResults.style.display = 'none';
                        document.getElementById('searchResultsHeader').classList.add('d-none');
                        return;
                    }

                    const term = searchTerm.toLowerCase();
                    const results = currentInventory.filter(item =>
                        item.nom_medicamento.toLowerCase().includes(term) ||
                        item.mol_medicamento.toLowerCase().includes(term) ||
                        (item.codigo_barras && item.codigo_barras.toLowerCase().includes(term))
                    ).slice(0, 10);

                    // Check for exact barcode match
                    const exactBarcodeMatch = currentInventory.find(item =>
                        item.codigo_barras && item.codigo_barras.toLowerCase() === term
                    );

                    if (exactBarcodeMatch) {
                        this.selectProduct(exactBarcodeMatch);
                        DOM.searchResults.style.display = 'none';
                        DOM.searchMedication.value = ''; // Clear after scan
                        return;
                    }

                    if (results.length > 0) {
                        DOM.searchResults.style.display = 'block';
                        document.getElementById('searchResultsHeader').classList.remove('d-none');

                        results.forEach(item => {
                            const resultItem = document.createElement('div');
                            resultItem.className = 'search-result-item';

                            // Determinar clase de stock
                            let stockAvailable = item.disponible;
                            if (currentMode === 'hospital') {
                                stockAvailable = item.stock_hospital || 0;
                            }

                            let stockClass = 'text-success';
                            if (stockAvailable <= 0) stockClass = 'text-danger';
                            else if (stockAvailable <= 5) stockClass = 'text-warning';

                            // Get price based on mode
                            let price = parseFloat(item.precio_venta) || 0;
                            if (currentMode === 'hospital') price = parseFloat(item.precio_hospital) || 0;
                            if (currentMode === 'medical') price = parseFloat(item.precio_medico) || 0;
                            if (currentMode === 'special') price = parseFloat(item.precio_compra) || 0;
                            if (currentMode === 'transfer') price = 0;

                            const expiryDateObj = item.fecha_vencimiento ? new Date(item.fecha_vencimiento) : null;
                            const today = new Date();
                            const daysToExpiry = expiryDateObj ? Math.floor((expiryDateObj - today) / (1000 * 60 * 60 * 24)) : 999;
                            const expiryDate = expiryDateObj ? expiryDateObj.toLocaleDateString('es-GT', { day: '2-digit', month: '2-digit', year: '2-digit' }) : 'N/A';

                            resultItem.innerHTML = `
                                <div class="row g-3 align-items-center">
                                    <div class="col-6">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="bg-primary bg-opacity-10 text-primary rounded p-2" style="width: 45px; height: 45px; display: flex; align-items: center; justify-content: center;">
                                                <i class="bi bi-capsule fs-4"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark">${item.nom_medicamento}</div>
                                                <div class="small text-muted">${item.mol_medicamento} • ${item.presentacion_med}</div>
                                                <div class="${stockClass} small fw-bold mt-1">
                                                    <i class="bi bi-box-seam me-1"></i> ${stockAvailable} disponibles
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-2 text-center">
                                        <div class="small text-muted">Precio</div>
                                        <div class="fw-bold text-primary fs-5">Q${price.toFixed(2)}</div>
                                    </div>
                                    <div class="col-2 text-center">
                                        <div class="small text-muted">Lote/Doc</div>
                                        <div class="badge bg-light text-dark border">${item.document_number || (item.document_type || 'N/A')}</div>
                                    </div>
                                    <div class="col-2 text-end">
                                        <div class="small text-muted">Vence</div>
                                        <div class="fw-bold ${daysToExpiry < 90 ? 'text-warning' : 'text-success'}">${expiryDate}</div>
                                    </div>
                                </div>
                            `;

                            resultItem.addEventListener('click', () => this.selectProduct(item));
                            DOM.searchResults.appendChild(resultItem);
                        });
                    } else {
                        DOM.searchResults.style.display = 'block';
                        document.getElementById('searchResultsHeader').classList.add('d-none');
                        DOM.searchResults.innerHTML = '<div class="search-result-item text-center text-muted">No se encontraron resultados</div>';
                    }
                }

                selectProduct(item) {
                    selectedItem = item;

                    // Actualizar interfaz
                    DOM.selectedProductName.textContent = `${item.nom_medicamento} (${item.presentacion_med})`;
                    DOM.selectedProductDetails.textContent = `${item.mol_medicamento} • ${item.casa_farmaceutica}`;
                    // Determine stock based on mode
                    let stock = item.disponible;
                    if (currentMode === 'hospital') stock = item.stock_hospital || 0;

                    DOM.availableStock.textContent = stock;

                    // Display document type
                    const docDisplay = document.getElementById('documentTypeDisplay');
                    if (docDisplay) {
                        docDisplay.textContent = item.document_number || item.document_type || 'N/A';
                    }

                    // Display expiry date
                    if (DOM.expiryDate && item.fecha_vencimiento) {
                        const expiryDate = new Date(item.fecha_vencimiento);
                        const options = { day: '2-digit', month: '2-digit', year: 'numeric' };
                        DOM.expiryDate.textContent = expiryDate.toLocaleDateString('es-GT', options);

                        // Color code based on expiry
                        const today = new Date();
                        const daysToExpiry = Math.floor((expiryDate - today) / (1000 * 60 * 60 * 24));
                        if (daysToExpiry < 0) {
                            DOM.expiryDate.style.color = 'var(--color-danger)';
                        } else if (daysToExpiry < 90) {
                            DOM.expiryDate.style.color = 'var(--color-warning)';
                        } else {
                            DOM.expiryDate.style.color = 'var(--color-success)';
                        }
                    } else {
                        DOM.expiryDate.textContent = 'N/A';
                        DOM.expiryDate.style.color = 'var(--color-text-secondary)';
                    }

                    DOM.quantity.max = stock;
                    DOM.quantity.value = 1;

                    // Obtener precio de venta según modo
                    let price = parseFloat(item.precio_venta) || 0;
                    if (currentMode === 'hospital') price = parseFloat(item.precio_hospital) || 0;
                    if (currentMode === 'medical') price = parseFloat(item.precio_medico) || 0;
                    if (currentMode === 'special') price = parseFloat(item.precio_compra) || 0;
                    if (currentMode === 'transfer') price = 0;

                    DOM.unitPrice.value = price.toFixed(2);

                    // Mostrar detalles de selección
                    DOM.selectionDetails.style.display = 'block';

                    // Mostrar formulario solo cuando hay producto seleccionado
                    const form = document.getElementById('addToCartForm');
                    if (form) form.style.display = 'block';

                    DOM.searchResults.style.display = 'none';
                    DOM.searchMedication.value = item.nom_medicamento;

                    // Enfocar en cantidad
                    DOM.quantity.focus();
                }

                async getSalePrice(idInventario) {
                    try {
                        const response = await fetch(`get_precio.php?id_inventario=${idInventario}`);
                        const data = await response.json();
                        return data.status === 'success' ? parseFloat(data.precio_venta) : 0;
                    } catch (error) {
                        console.error('Error al obtener precio:', error);
                        return 0;
                    }
                }

                async reserveStock(idInventario, cantidad) {
                    try {
                        await fetch('reserve_item.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id_inventario: idInventario, cantidad: cantidad })
                        });
                    } catch (error) {
                        console.error('Error al reservar stock:', error);
                    }
                }

                async releaseStock(idInventario) {
                    try {
                        await fetch('release_item.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id_inventario: idInventario })
                        });
                    } catch (error) {
                        console.error('Error al liberar stock:', error);
                    }
                }

                setupCart() {
                    // Agregar al carrito
                    DOM.addToCartBtn.addEventListener('click', () => this.addToCart());

                    // Permitir Enter en cantidad
                    DOM.quantity.addEventListener('keypress', (e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            // En modos especiales, mover foco a precio en lugar de agregar
                            if (currentMode === 'public') {
                                this.addToCart();
                            } else {
                                DOM.unitPrice.focus();
                                DOM.unitPrice.select();
                            }
                        }
                    });

                    // Permitir Enter en precio (para modos especiales)
                    DOM.unitPrice.addEventListener('keypress', (e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            this.addToCart();
                        }
                    });

                    // Vaciar carrito
                    DOM.clearCartBtn.addEventListener('click', () => this.clearCart());

                    // Procesar venta
                    DOM.checkoutBtn.addEventListener('click', () => this.processSale());

                    // Cotización
                    DOM.quoteBtn.addEventListener('click', () => this.processQuote());
                }

                addToCart() {
                    if (!selectedItem) return;

                    const price = parseFloat(DOM.unitPrice.value);
                    const qty = parseInt(DOM.quantity.value);
                    const stock = parseInt(DOM.availableStock.textContent);

                    // Validaciones (Allow 0 price only for transfer)
                    if (isNaN(price) || (price <= 0 && currentMode !== 'transfer')) {
                        this.showAlert('Precio inválido', 'error');
                        return;
                    }

                    if (isNaN(qty) || qty <= 0 || qty > stock) {
                        this.showAlert('Cantidad inválida o insuficiente stock', 'error');
                        return;
                    }

                    // Verificar si ya está en el carrito
                    const existingIndex = cartItems.findIndex(item => item.id === selectedItem.id_inventario);

                    if (existingIndex !== -1) {
                        const newQty = cartItems[existingIndex].quantity + qty;
                        if (newQty > stock) {
                            this.showAlert('La cantidad total excede el stock disponible', 'error');
                            return;
                        }
                        cartItems[existingIndex].quantity = newQty;
                        cartItems[existingIndex].subtotal = newQty * price;
                    } else {
                        cartItems.push({
                            id: selectedItem.id_inventario,
                            name: selectedItem.nom_medicamento,
                            details: `${selectedItem.mol_medicamento} • ${selectedItem.presentacion_med}`,
                            price: price,
                            quantity: qty,
                            subtotal: price * qty
                        });
                    }

                    // Actualizar interfaz del carrito
                    this.updateCartDisplay();

                    // Reservar stock
                    this.reserveStock(selectedItem.id_inventario,
                        cartItems.find(item => item.id === selectedItem.id_inventario).quantity);

                    // Resetear selección
                    this.resetSelection();

                    // Mostrar confirmación
                    this.showAlert('Producto agregado al carrito', 'success');
                }

                updateCartDisplay() {
                    DOM.cartItemsBody.innerHTML = '';

                    if (cartItems.length === 0) {
                        DOM.emptyCart.style.display = 'flex';
                        DOM.cartTable.style.display = 'none';
                        DOM.cartTotal.textContent = 'Q0.00';
                    } else {
                        DOM.emptyCart.style.display = 'none';
                        DOM.cartTable.style.display = 'table';

                        let total = 0;

                        cartItems.forEach((item, index) => {
                            total += item.subtotal;

                            const row = document.createElement('tr');
                            row.innerHTML = `
                            <td>
                                <div class="cart-item-product">
                                    <div class="cart-item-name">${item.name}</div>
                                    <div class="cart-item-details">${item.details}</div>
                                </div>
                            </td>
                            <td style="text-align: center; font-weight: 600;">${item.quantity}</td>
                            <td style="text-align: right; font-weight: 600;">Q${item.subtotal.toFixed(2)}</td>
                            <td>
                                <button class="remove-button" data-index="${index}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        `;

                            DOM.cartItemsBody.appendChild(row);
                        });

                        // Actualizar total
                        DOM.cartTotal.textContent = `Q${total.toFixed(2)}`;

                        // Agregar event listeners a botones de eliminar
                        document.querySelectorAll('.remove-button').forEach(button => {
                            button.addEventListener('click', function () {
                                const index = parseInt(this.getAttribute('data-index'));
                                DynamicComponents.prototype.removeFromCart(index);
                            });
                        });
                    }
                }

                removeFromCart(index) {
                    const removedItem = cartItems[index];

                    // Liberar stock
                    this.releaseStock(removedItem.id);

                    // Remover del array
                    cartItems.splice(index, 1);

                    // Actualizar display
                    this.updateCartDisplay();

                    this.showAlert('Producto removido del carrito', 'info');
                }

                clearCart() {
                    if (cartItems.length === 0) return;

                    // Liberar todo el stock reservado
                    cartItems.forEach(item => {
                        this.releaseStock(item.id);
                    });

                    // Limpiar array
                    cartItems = [];

                    // Actualizar display
                    this.updateCartDisplay();

                    this.showAlert('Carrito vaciado', 'info');
                }

                async processSale() {
                    if (cartItems.length === 0) {
                        this.showAlert('El carrito está vacío', 'error');
                        return;
                    }

                    if (!DOM.clientName.value.trim()) {
                        this.showAlert('Ingrese el nombre del cliente', 'error');
                        DOM.clientName.focus();
                        return;
                    }

                    const saleData = {
                        nombre_cliente: DOM.clientName.value.trim(),
                        nit_cliente: document.getElementById('clientNIT').value.trim() || 'C/F',
                        tipo_pago: currentMode === 'transfer' ? 'Traslado' : DOM.paymentMethod.value,
                        document_type: DOM.documentType ? DOM.documentType.value : '',
                        document_number: DOM.documentNumber ? DOM.documentNumber.value.trim() : '',
                        total: cartItems.reduce((sum, item) => sum + item.subtotal, 0),
                        estado: currentMode === 'transfer' ? 'Pagado' : 'Pagado',
                        items: cartItems.map(item => ({
                            id_inventario: item.id,
                            nombre: item.name,
                            cantidad: item.quantity,
                            precio_unitario: item.price,
                            subtotal: item.subtotal
                        }))
                    };

                    Swal.fire({
                        title: 'Procesando venta...',
                        text: 'Por favor espere',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });

                    try {
                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                        const response = await fetch('save_venta.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                            body: JSON.stringify(saleData)
                        });

                        const data = await response.json();

                        if (data.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Venta completada!',
                                text: 'Redirigiendo al comprobante...',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                window.open(`print_receipt.php?id=${data.id_venta}`, '_blank');
                                setTimeout(() => {
                                    location.reload();
                                }, 1000);
                            });
                        } else {
                            this.showAlert(data.message || 'Error al procesar la venta', 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.showAlert('Error de conexión con el servidor', 'error');
                    }
                }

                processQuote() {
                    if (cartItems.length === 0) {
                        this.showAlert('El carrito está vacío', 'warning');
                        return;
                    }

                    const quoteData = {
                        clientName: DOM.clientName.value.trim() || 'C/F',
                        clientNIT: document.getElementById('clientNIT').value.trim() || 'C/F',
                        total: cartItems.reduce((sum, item) => sum + item.subtotal, 0),
                        items: cartItems.map(item => ({
                            nom_medicamento: item.name,
                            presentacion_med: item.details,
                            cantidad: item.quantity,
                            precio_unitario: item.price
                        }))
                    };

                    sessionStorage.setItem('quoteData', JSON.stringify(quoteData));
                    window.open('print_quote.php', '_blank');
                }

                resetSelection() {
                    selectedItem = null;
                    DOM.selectionDetails.style.display = 'none';
                    const form = document.getElementById('addToCartForm');
                    if (form) form.style.display = 'none';
                    DOM.searchMedication.value = '';
                    DOM.searchResults.style.display = 'none';
                    DOM.unitPrice.value = '';
                    DOM.quantity.value = 1;
                    DOM.availableStock.textContent = '0';
                }

                showAlert(message, type = 'info') {
                    const colors = {
                        success: '#198754',
                        error: '#dc3545',
                        warning: '#ffc107',
                        info: '#0dcaf0'
                    };

                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: type,
                        title: message,
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        background: 'var(--color-card)',
                        color: 'var(--color-text)'
                    });
                }

                openShiftReport() {
                    Swal.fire({
                        title: '¿Generar Corte de Jornada?',
                        text: "Se generará un reporte PDF con las ventas del turno actual.",
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#ffc107',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Sí, generar PDF',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.open('export_shift_pdf.php', '_blank');
                        }
                    });
                }

                initHistoryCalendar() {
                    const calendarEl = document.getElementById('historyCalendar');
                    if (!calendarEl) return;

                    // If already initialized, just refetch
                    if (this.calendar) {
                        this.calendar.refetchEvents();
                        setTimeout(() => this.calendar.updateSize(), 200);
                        return;
                    }

                    this.calendar = new FullCalendar.Calendar(calendarEl, {
                        initialView: 'dayGridMonth',
                        locale: 'es',
                        headerToolbar: {
                            left: 'prev,next today',
                            center: 'title',
                            right: 'dayGridMonth,timeGridWeek'
                        },
                        events: 'get_transfer_events.php',
                        eventClick: (info) => {
                            window.open(`print_receipt.php?id=${info.event.id}`, '_blank');
                        },
                        themeSystem: 'bootstrap5',
                        height: 500
                    });

                    this.calendar.render();
                    setTimeout(() => this.calendar.updateSize(), 200);
                }

                async openHistory(type = '') {
                    const modalEl = document.getElementById('historyModal');
                    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                    const tbody = document.getElementById('historyTableBody');
                    const loading = document.getElementById('historyLoading');
                    const title = document.querySelector('#historyModal .modal-title');

                    // Reset to list tab
                    const listTab = document.getElementById('list-tab');
                    if (listTab) {
                        bootstrap.Tab.getOrCreateInstance(listTab).show();
                    }

                    const detailTabContainer = document.getElementById('detail-tab-container');
                    if (detailTabContainer) {
                        if (type === 'Traslado') {
                            detailTabContainer.classList.remove('d-none');
                        } else {
                            detailTabContainer.classList.add('d-none');
                        }
                    }

                    tbody.innerHTML = '';
                    loading.style.display = 'block';

                    if (title) {
                        title.innerHTML = type === 'Traslado'
                            ? '<i class="bi bi-arrow-left-right me-2"></i>Historial de Traslados (Turno Actual)'
                            : '<i class="bi bi-clock-history me-2"></i>Historial de Ventas (Turno Actual)';
                    }

                    modal.show();

                    try {
                        const response = await fetch(`get_recent_sales.php${type ? '?type=' + type : ''}`);
                        const data = await response.json();

                        loading.style.display = 'none';

                    if (data.status === 'success' && data.sales.length > 0) {
                        let totalJornada = 0;
                        data.sales.forEach(sale => {
                            const total = parseFloat(sale.total) || 0;
                            totalJornada += total;
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td class="ps-4 small text-muted">${sale.hora}</td>
                                <td>
                                    <div class="fw-bold">${sale.nombre_cliente}</div>
                                    ${sale.tipo_pago ? `<span class="badge bg-light text-dark border">${sale.tipo_pago}</span>` : ''}
                                </td>
                                <td class="text-end fw-bold">Q${total.toFixed(2)}</td>
                                <td class="text-center pe-4">
                                    <button class="btn btn-light btn-sm shadow-sm" onclick="window.open('print_receipt.php?id=${sale.id_venta}', '_blank')">
                                        <i class="bi bi-printer"></i>
                                    </button>
                                </td>
                            `;
                            tbody.appendChild(row);
                        });
                        const totalEl = document.getElementById('historyTotalSum');
                        if (totalEl) totalEl.textContent = `Q${totalJornada.toFixed(2)}`;
                    } else {
                        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No hay registros en este turno.</td></tr>';
                        const totalEl = document.getElementById('historyTotalSum');
                        if (totalEl) totalEl.textContent = 'Q0.00';
                    }
                    } catch (error) {
                        console.error('Error loading history:', error);
                        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-danger">Error al cargar el historial.</td></tr>';
                    }
                }

                async loadTransferDetails() {
                    const tbody = document.getElementById('transferDetailsBody');
                    const startDate = document.getElementById('transferStartDate').value;
                    const endDate = document.getElementById('transferEndDate').value;
                    const search = document.getElementById('transferSearch').value;

                    if (!tbody) return;

                    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4"><div class="spinner-border text-info spinner-border-sm" role="status"></div></td></tr>';

                    try {
                        const url = new URL('get_transfer_details.php', window.location.href);
                        url.searchParams.append('start', startDate);
                        url.searchParams.append('end', endDate);
                        if (search) url.searchParams.append('q', search);

                        const response = await fetch(url);
                        const data = await response.json();

                        tbody.innerHTML = '';

                        if (data.status === 'success' && data.details.length > 0) {
                            data.details.forEach(item => {
                                const row = document.createElement('tr');
                                // Formatear fecha para remover segundos o dejarla amigable si es necesario, 
                                // O simplemente mostrar el valor directo que viene de 'fecha_venta'
                                const fechaCompleta = new Date(item.fecha_venta);
                                const fechaTexto = fechaCompleta.toLocaleString('es-GT', { dateStyle: 'short', timeStyle: 'short' }) || item.fecha_venta;

                                row.innerHTML = `
                                    <td class="ps-3 small text-muted">${fechaTexto}</td>
                                    <td>
                                        <div class="fw-bold">${item.nom_medicamento}</div>
                                        <small class="text-muted">${item.mol_medicamento || ''}</small>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><i class="bi bi-person me-1"></i>${item.realizado_por || 'Sistema'}</span></td>
                                    <td class="fw-medium">${item.nombre_cliente}</td>
                                    <td class="text-end pe-3 fw-bold">${item.cantidad_vendida}</td>
                                `;
                                tbody.appendChild(row);
                            });
                        } else {
                            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No hay traslados en este periodo.</td></tr>';
                        }
                    } catch (error) {
                        console.error('Error loading transfer details:', error);
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-danger">Error al cargar detalles.</td></tr>';
                    }
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

                    document.querySelectorAll('.stat-card, .pos-selection-area, .pos-cart-area').forEach(el => {
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
                const dynamicComponents = new DynamicComponents();

                // Exponer APIs necesarias globalmente
                window.dashboard = {
                    theme: themeManager,
                    components: dynamicComponents,
                    pos: dynamicComponents
                };

                // Log de inicialización
                console.log('Módulo de Ventas - Centro Médico RS');
                console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
                console.log('Productos disponibles: <?php echo count($inventario); ?>');
                console.log('Ventas hoy: <?php echo $today_sales['count'] ?? 0; ?>');
                console.log('Total ventas hoy: Q<?php echo number_format($today_sales['total'] ?? 0, 2); ?>');
            });
        })();
    </script>
</body>

</html>