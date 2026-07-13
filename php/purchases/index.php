<?php
// purchases/index.php - Módulo de Compras del Centro Médico Herrera Saenz
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

check_module_access('purchases');

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
    $user_specialty = $_SESSION['especialidad'] ?? 'Administrador';
    $id_hospital = (int) ($_SESSION['id_hospital'] ?? 0);

    // Verificar permisos (solo admin puede acceder a compras)
    if ($user_type !== 'admin') {
        header("Location: ../dashboard/index.php");
        exit;
    }

    // ============ ESTADÍSTICAS DE COMPRAS ============
    $today = date('Y-m-d');
    $current_month = date('Y-m');

    // 1. Compras del mes actual
    $stmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total FROM purchase_headers WHERE DATE_FORMAT(purchase_date, '%Y-%m') = ? AND id_hospital = ?");
    $stmt->execute([$current_month, $id_hospital]);
    $month_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $month_purchases = $month_stats['count'] ?? 0;
    $month_total = $month_stats['total'] ?? 0;

    // 2. Compras pendientes de pago
    $stmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount - COALESCE(paid_amount, 0)), 0) as balance FROM purchase_headers WHERE (total_amount - COALESCE(paid_amount, 0)) > 0 AND id_hospital = ?");
    $stmt->execute([$id_hospital]);
    $pending_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $pending_count = $pending_stats['count'] ?? 0;
    $total_balance = $pending_stats['balance'] ?? 0;

    // 3. Compras del día
    $stmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total FROM purchase_headers WHERE DATE(purchase_date) = ? AND id_hospital = ?");
    $stmt->execute([$today, $id_hospital]);
    $today_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $today_purchases = $today_stats['count'] ?? 0;
    $today_total = $today_stats['total'] ?? 0;

    // 4. Proveedores con más compras
    $stmt = $conn->prepare("SELECT provider_name, COUNT(*) as count, SUM(total_amount) as total FROM purchase_headers WHERE id_hospital = ? GROUP BY provider_name ORDER BY total DESC LIMIT 5");
    $stmt->execute([$id_hospital]);
    $top_providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Últimas compras (paginadas, 100 por página, con búsqueda opcional)
    $search_query = trim($_GET['q'] ?? '');
    $per_page = 100;
    $where_extra = '';
    $params_count = [$id_hospital];
    $params_list  = [$id_hospital];

    if ($search_query !== '') {
        $where_extra = ' AND (ph.provider_name LIKE ? OR ph.document_number LIKE ? OR ph.document_type LIKE ?)';
        $like = '%' . $search_query . '%';
        $params_count[] = $like;
        $params_count[] = $like;
        $params_count[] = $like;
        $params_list[]  = $like;
        $params_list[]  = $like;
        $params_list[]  = $like;
    }

    // Total para paginación
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM purchase_headers ph WHERE ph.id_hospital = ?" . $where_extra);
    $stmt->execute($params_count);
    $total_records = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    $total_pages = max(1, (int) ceil($total_records / $per_page));

    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    if ($page < 1) $page = 1;
    if ($page > $total_pages) $page = $total_pages;
    $offset = ($page - 1) * $per_page;

    $stmt = $conn->prepare("SELECT ph.*,
                           (ph.total_amount - COALESCE(ph.paid_amount, 0)) as balance,
                           COUNT(pi.id) AS items_count,
                           SUM(CASE WHEN pi.status = 'Recibido' THEN 1 ELSE 0 END) AS received_count,
                           SUM(CASE WHEN pi.status = 'Pendiente' THEN 1 ELSE 0 END) AS pending_count
                           FROM purchase_headers ph
                           LEFT JOIN purchase_items pi ON pi.purchase_header_id = ph.id AND pi.id_hospital = ph.id_hospital
                           WHERE ph.id_hospital = ?" . $where_extra . "
                           GROUP BY ph.id
                           ORDER BY ph.purchase_date DESC, ph.created_at DESC
                           LIMIT " . (int)$per_page . " OFFSET " . (int)$offset);
    $stmt->execute($params_list);
    $recent_purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5b. Detectar compras con traslados históricos
    $stmt_trans = $conn->prepare("
        SELECT DISTINCT pi.purchase_header_id
        FROM purchase_items pi
        JOIN inventario i ON i.id_purchase_item = pi.id
        JOIN detalle_ventas dv ON dv.id_inventario = i.id_inventario
        JOIN ventas v ON v.id_venta = dv.id_venta AND v.tipo_pago = 'Traslado'
        WHERE pi.id_hospital = ?
    ");
    $stmt_trans->execute([$id_hospital]);
    $purchase_ids_with_transfers = $stmt_trans->fetchAll(PDO::FETCH_COLUMN);

    // 6. Compras por confirmar (en inventario como pendientes)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventario WHERE estado = 'Pendiente' AND id_hospital = ?");
    $stmt->execute([$id_hospital]);
    $pending_inventory = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 7. Compras antiguas (de la tabla anterior)
    try {
        $stmt_old = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_compra), 0) as total FROM compras WHERE id_hospital = ?");
        $stmt_old->execute([$id_hospital]);
        $old_stats = $stmt_old->fetch(PDO::FETCH_ASSOC);
        $old_purchases = $old_stats['count'] ?? 0;
        $old_total = $old_stats['total'] ?? 0;
    } catch (Exception $e) {
        $old_purchases = 0;
        $old_total = 0;
    }

    // Título de la página
    $page_title = "Compras - Centro Médico Herrera Saenz";

} catch (Exception $e) {
    // Manejo de errores
    error_log("Error en módulo de compras: " . $e->getMessage());
    die("Error al cargar el módulo de compras. Por favor, contacte al administrador.");
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="Módulo de Compras - Centro Médico Herrera Saenz - Gestión de compras de medicamentos e insumos">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">

    <!-- logo -->
    <link rel="icon" type="image/png" href="../../assets/img/cmhs.png">

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

    <!-- Estilos para pestaña Contabilidad -->
    <style>
        /* Filtros de período */
        .accounting-filters .btn-group .btn {
            font-size: 0.85rem;
            font-weight: 500;
        }
        .accounting-filters .btn-group .btn.active {
            background: var(--color-primary);
            color: #fff;
            border-color: var(--color-primary);
        }

        /* Grid de KPIs */
        .accounting-kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
        }
        .accounting-kpi {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.25rem;
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .accounting-kpi:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .accounting-kpi__icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .accounting-kpi--primary .accounting-kpi__icon {
            background: rgba(var(--color-primary-rgb), 0.12);
            color: var(--color-primary);
        }
        .accounting-kpi--success .accounting-kpi__icon {
            background: rgba(34, 197, 94, 0.12);
            color: #16a34a;
        }
        .accounting-kpi--warning .accounting-kpi__icon {
            background: rgba(245, 158, 11, 0.12);
            color: #d97706;
        }
        .accounting-kpi--info .accounting-kpi__icon {
            background: rgba(59, 130, 246, 0.12);
            color: #2563eb;
        }
        .accounting-kpi__body { flex: 1; min-width: 0; }
        .accounting-kpi__label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--color-text-secondary);
        }
        .accounting-kpi__value {
            font-size: 1.5rem;
            font-weight: 800;
            margin: 0.25rem 0;
            color: var(--color-text);
            line-height: 1.1;
        }
        .accounting-kpi__meta {
            font-size: 0.78rem;
            color: var(--color-text-secondary);
        }

        /* Sección */
        .accounting-section {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
        }
        .accounting-section__title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--color-text);
        }

        /* Tabla */
        .accounting-table-wrap {
            overflow: auto;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
        }
        .accounting-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
        }
        .accounting-table thead th {
            background: var(--color-surface);
            padding: 0.75rem 1rem;
            text-align: left;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--color-text-secondary);
            border-bottom: 2px solid var(--color-border);
            white-space: nowrap;
        }
        .accounting-table tbody td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--color-border);
            color: var(--color-text);
        }
        .accounting-table tbody tr:last-child td { border-bottom: none; }
        .accounting-table tbody tr:hover {
            background: rgba(var(--color-primary-rgb), 0.03);
        }
        .accounting-table .text-end { text-align: right; }
        .accounting-table .text-center { text-align: center; }
        .accounting-table tfoot td {
            background: rgba(var(--color-primary-rgb), 0.05);
            font-weight: 700;
            padding: 0.85rem 1rem;
            border-top: 2px solid var(--color-primary);
        }

        /* Barras de progreso */
        .acct-progress {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .acct-progress__bar {
            flex: 1;
            height: 8px;
            background: var(--color-border);
            border-radius: 4px;
            overflow: hidden;
        }
        .acct-progress__fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        .acct-progress__fill--high   { background: #16a34a; }
        .acct-progress__fill--medium { background: #d97706; }
        .acct-progress__fill--low    { background: #dc2626; }
        .acct-progress__pct {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--color-text-secondary);
            min-width: 40px;
            text-align: right;
        }

        /* Badge estado */
        .acct-badge {
            display: inline-block;
            padding: 0.2rem 0.55rem;
            border-radius: var(--radius-sm);
            font-size: 0.72rem;
            font-weight: 700;
        }
        .acct-badge--success { background: rgba(34,197,94,0.15); color: #16a34a; }
        .acct-badge--warning { background: rgba(245,158,11,0.15); color: #d97706; }
        .acct-badge--danger  { background: rgba(220,38,38,0.15); color: #dc2626; }
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
                ['label' => 'Compras'],
            ]); ?>
            <!-- Bienvenida personalizada -->
            <div class="stat-card mb-4 animate-in">
                <div class="stat-header">
                    <div>
                        <h2 id="greeting" class="stat-value" style="font-size: 1.75rem; margin-bottom: 0.5rem;">
                            <span id="greeting-text">Buenos días</span>, <?php echo htmlspecialchars($user_name); ?>
                        </h2>
                        <p class="text-muted mb-0">
                            <i class="bi bi-cart-check me-1"></i> Módulo de Compras
                            <span class="mx-2">•</span>
                            <i class="bi bi-calendar-check me-1"></i> <?php echo date('d/m/Y'); ?>
                            <span class="mx-2">•</span>
                            <i class="bi bi-clock me-1"></i> <span id="current-time"><?php echo date('H:i'); ?></span>
                        </p>
                    </div>
                    <div class="d-none d-md-block">
                        <i class="bi bi-cart-check text-primary" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>

            <!-- Estadísticas principales -->
            <div class="stats-grid">
                <!-- Compras del mes -->
                <div class="stat-card animate-in delay-1">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Compras del Mes</div>
                            <div class="stat-value"><?php echo $month_purchases; ?></div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="bi bi-calendar-month"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-currency-exchange"></i>
                        <span>Total: Q<?php echo number_format($month_total, 2); ?></span>
                    </div>
                </div>

                <!-- Compras pendientes -->
                <div class="stat-card animate-in delay-2">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Pendientes de Pago</div>
                            <div class="stat-value"><?php echo $pending_count; ?></div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="bi bi-clock-history"></i>
                        </div>
                    </div>
                    <div class="stat-change">
                        <i class="bi bi-cash-coin"></i>
                        <span>Saldo: Q<?php echo number_format($total_balance, 2); ?></span>
                    </div>
                </div>

                <!-- Compras de hoy -->
                <div class="stat-card animate-in delay-3">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Compras Hoy</div>
                            <div class="stat-value"><?php echo $today_purchases; ?></div>
                        </div>
                        <div class="stat-icon success">
                            <i class="bi bi-cart-check"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-arrow-up-right"></i>
                        <span>Total: Q<?php echo number_format($today_total, 2); ?></span>
                    </div>
                </div>

                <!-- Compras antiguas -->
                <div class="stat-card animate-in delay-4">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Registros Antiguos</div>
                            <div class="stat-value"><?php echo $old_purchases; ?></div>
                        </div>
                        <div class="stat-icon info">
                            <i class="bi bi-archive"></i>
                        </div>
                    </div>
                    <div class="stat-change">
                        <i class="bi bi-currency-exchange"></i>
                        <span>Total: Q<?php echo number_format($old_total, 2); ?></span>
                    </div>
                </div>

                <!-- Pendientes de recepción (items en inventario aún no recibidos) -->
                <div class="stat-card animate-in delay-5">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Pendientes de Recepción</div>
                            <div class="stat-value"><?php echo (int) $pending_inventory; ?></div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                    </div>
                    <div class="stat-change">
                        <i class="bi bi-box-seam"></i>
                        <span>Items en inventario esperando recepción</span>
                    </div>
                </div>
            </div>

            <!-- Navegación por pestañas -->
            <div class="tabs-navigation mb-4">
                <button class="tab-btn active" data-tab="recent-purchases">
                    <i class="bi bi-cart-check me-2"></i>Compras Recientes
                </button>
                <button class="tab-btn" data-tab="pending-payments">
                    <i class="bi bi-clock-history me-2"></i>Pagos Pendientes
                </button>
                <button class="tab-btn" data-tab="paid-payments">
                    <i class="bi bi-check-circle me-2"></i>Pagos Realizados
                </button>
                <button class="tab-btn" data-tab="accounting">
                    <i class="bi bi-bar-chart-line me-2"></i>Contabilidad
                </button>
                <button class="tab-btn" data-tab="top-providers">
                    <i class="bi bi-building me-2"></i>Proveedores
                </button>
                <button class="tab-btn" data-tab="gastos">
                    <i class="bi bi-wallet2 me-2"></i>Gastos
                </button>
            </div>

            <!-- Pestaña: Compras Recientes -->
            <div class="tab-content active" id="recent-purchases-tab">
                <section class="appointments-section animate-in delay-1">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="bi bi-clock-history section-title-icon"></i>
                            Compras Recientes
                        </h3>
                        <div class="d-flex gap-2">
                            <form method="GET" action="index.php" class="d-flex gap-2" id="searchFormRecent">
                                <div class="search-box">
                                    <i class="bi bi-search search-icon"></i>
                                    <input type="text" name="q" id="searchRecent" placeholder="Buscar compra..."
                                        value="<?php echo htmlspecialchars($search_query); ?>">
                                </div>
                                <?php if ($search_query !== ''): ?>
                                    <a href="index.php" class="action-btn" style="background: var(--color-text-muted);" title="Limpiar búsqueda">
                                        <i class="bi bi-x-lg"></i>
                                    </a>
                                <?php endif; ?>
                            </form>
                            <a href="export_purchases.php" class="action-btn" style="background: var(--color-success);">
                                <i class="bi bi-file-earmark-spreadsheet"></i>
                                Excel
                            </a>
                            <a href="export_purchases_pdf.php" target="_blank" class="action-btn"
                                style="background: var(--color-danger);">
                                <i class="bi bi-file-earmark-pdf"></i>
                                PDF
                            </a>
                            <button class="action-btn" onclick="showNewPurchaseModal()">
                                <i class="bi bi-plus-lg"></i>
                                Nueva Compra
                            </button>
                        </div>
                    </div>

                    <?php if (count($recent_purchases) > 0): ?>
                            <div class="table-responsive">
                                <table class="appointments-table" id="tableRecent">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Proveedor</th>
                                            <th>Documento</th>
                                            <th>Total</th>
                                            <th>Pagado</th>
                                            <th>Saldo</th>
                                            <th>Recepción</th>
                                            <th style="width:40px; text-align:center;" title="Traslados históricos">?</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_purchases as $purchase): ?>
                                                <?php
                                                $balance = $purchase['balance'];
                                                $paid = $purchase['total_amount'] - $balance;
                                                ?>
                                                <tr>
                                                    <td>
                                                        <?php echo date('d/m/Y', strtotime($purchase['purchase_date'])); ?>
                                                        <br>
                                                        <small class="text-muted"><?php echo $purchase['items_count']; ?> items</small>
                                                    </td>
                                                    <td>
                                                        <div class="patient-cell">
                                                            <div class="patient-avatar" style="background: var(--color-info);">
                                                                <?php echo strtoupper(substr($purchase['provider_name'], 0, 1)); ?>
                                                            </div>
                                                            <div class="patient-info">
                                                                <div class="patient-name">
                                                                    <?php echo htmlspecialchars($purchase['provider_name']); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-secondary">
                                                            <?php echo htmlspecialchars($purchase['document_type']); ?>
                                                            <?php echo $purchase['document_number'] ? '#' . $purchase['document_number'] : ''; ?>
                                                        </span>
                                                    </td>
                                                    <td class="fw-bold">Q<?php echo number_format($purchase['total_amount'], 2); ?></td>
                                                    <td class="text-success">Q<?php echo number_format($paid, 2); ?></td>
                                                    <td>
                                                        <?php if ($balance > 0): ?>
                                                                <span
                                                                    class="badge badge-danger">Q<?php echo number_format($balance, 2); ?></span>
                                                        <?php else: ?>
                                                                <span class="badge badge-success">Pagado</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $items_total = (int) ($purchase['items_count'] ?? 0);
                                                        $received    = (int) ($purchase['received_count'] ?? 0);
                                                        $pending_ct  = (int) ($purchase['pending_count'] ?? 0);
                                                        if ($items_total === 0): ?>
                                                            <span class="badge badge-info">Sin items</span>
                                                        <?php elseif ($pending_ct === 0): ?>
                                                            <span class="badge badge-success" title="Todos los items recibidos">
                                                                <i class="bi bi-check-circle me-1"></i>Recibido
                                                            </span>
                                                        <?php elseif ($received === 0): ?>
                                                            <span class="badge badge-warning" title="<?php echo $items_total; ?> item(s) pendiente(s)">
                                                                <i class="bi bi-clock-history me-1"></i>Pendiente
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge badge-warning" title="<?php echo $received; ?> de <?php echo $items_total; ?> recibidos">
                                                                <i class="bi bi-clock-history me-1"></i><?php echo $received; ?>/<?php echo $items_total; ?> recibidos
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if (in_array($purchase['id'], $purchase_ids_with_transfers)): ?>
                                                            <i class="bi bi-question-circle text-warning"
                                                               style="cursor:pointer; font-size:1.2rem;"
                                                               title="Ver traslados históricos"
                                                               onclick="showTransferAdjustment(<?php echo $purchase['id']; ?>)">
                                                            </i>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <a href="#" class="btn-icon history" title="Ver detalles"
                                                                onclick="viewPurchaseDetails(<?php echo $purchase['id']; ?>)">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                            <?php if ($balance > 0): ?>
                                                                    <a href="#" class="btn-icon edit" title="Registrar pago"
                                                                        onclick="openPaymentModal(<?php echo $purchase['id']; ?>)">
                                                                        <i class="bi bi-cash-coin"></i>
                                                                    </a>
                                                            <?php endif; ?>
                                                            <button type="button" class="btn-icon delete" title="Eliminar compra"
                                                                onclick="deletePurchase(<?php echo $purchase['id']; ?>)">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($total_pages > 1): ?>
                                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                                    <small class="text-muted">
                                        <?php if ($search_query !== ''): ?>
                                            Mostrando <?php echo count($recent_purchases); ?> de <?php echo $total_records; ?> resultados
                                            para "<strong><?php echo htmlspecialchars($search_query); ?></strong>"
                                            (página <?php echo $page; ?> de <?php echo $total_pages; ?>)
                                        <?php else: ?>
                                            Mostrando <?php echo count($recent_purchases); ?> de <?php echo $total_records; ?> compras
                                            (página <?php echo $page; ?> de <?php echo $total_pages; ?>)
                                        <?php endif; ?>
                                    </small>
                                    <nav>
                                        <ul class="pagination pagination-sm mb-0">
                                            <?php
                                            $base_url = '?page=%d';
                                            if ($search_query !== '') {
                                                $base_url = '?q=' . urlencode($search_query) . '&page=%d';
                                            }
                                            ?>
                                            <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="<?php echo sprintf($base_url, $page - 1); ?>" aria-label="Anterior">
                                                        <i class="bi bi-chevron-left"></i>
                                                    </a>
                                                </li>
                                            <?php else: ?>
                                                <li class="page-item disabled">
                                                    <span class="page-link"><i class="bi bi-chevron-left"></i></span>
                                                </li>
                                            <?php endif; ?>

                                            <?php
                                            $start = max(1, $page - 2);
                                            $end   = min($total_pages, $page + 2);
                                            if ($start > 1) {
                                                echo '<li class="page-item"><a class="page-link" href="' . sprintf($base_url, 1) . '">1</a></li>';
                                                if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                            }
                                            for ($i = $start; $i <= $end; $i++): ?>
                                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                    <?php if ($i == $page): ?>
                                                        <span class="page-link"><?php echo $i; ?></span>
                                                    <?php else: ?>
                                                        <a class="page-link" href="<?php echo sprintf($base_url, $i); ?>"><?php echo $i; ?></a>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endfor;
                                            if ($end < $total_pages) {
                                                if ($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                                echo '<li class="page-item"><a class="page-link" href="' . sprintf($base_url, $total_pages) . '">' . $total_pages . '</a></li>';
                                            }
                                            ?>

                                            <?php if ($page < $total_pages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="<?php echo sprintf($base_url, $page + 1); ?>" aria-label="Siguiente">
                                                        <i class="bi bi-chevron-right"></i>
                                                    </a>
                                                </li>
                                            <?php else: ?>
                                                <li class="page-item disabled">
                                                    <span class="page-link"><i class="bi bi-chevron-right"></i></span>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                </div>
                            <?php elseif ($search_query !== ''): ?>
                                <div class="text-muted small mt-3">
                                    <?php echo $total_records; ?> resultado(s) para "<strong><?php echo htmlspecialchars($search_query); ?></strong>".
                                    <a href="index.php" class="ms-2">Limpiar búsqueda</a>
                                </div>
                            <?php endif; ?>
                    <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <i class="bi bi-cart-x"></i>
                                </div>
                                <?php if ($search_query !== ''): ?>
                                    <h4 class="text-muted mb-2">No se encontraron compras que coincidan con "<strong><?php echo htmlspecialchars($search_query); ?></strong>"</h4>
                                    <a href="index.php" class="action-btn primary">Limpiar búsqueda</a>
                                <?php else: ?>
                                    <h4 class="text-muted mb-2">No hay compras registradas</h4>
                                    <p class="text-muted mb-3">Comienza registrando tu primera compra</p>
                                <?php endif; ?>
                                <button class="action-btn" onclick="showNewPurchaseModal()">
                                    <i class="bi bi-plus-lg"></i>
                                    Nueva Compra
                                </button>
                            </div>
                    <?php endif; ?>
                </section>
            </div>

            <!-- Pestaña: Pagos Pendientes -->
            <div class="tab-content" id="pending-payments-tab">
                <section class="appointments-section animate-in delay-2">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="bi bi-clock-history text-warning section-title-icon"></i>
                            Compras con Saldo Pendiente
                        </h3>
                        <div class="search-box">
                            <i class="bi bi-search search-icon"></i>
                            <input type="text" id="searchPending" placeholder="Buscar proveedor...">
                        </div>
                    </div>

                    <?php
                    // Obtener compras pendientes
                    try {
                        $stmt_pending = $conn->prepare("SELECT ph.*, 
                               (ph.total_amount - COALESCE(ph.paid_amount, 0)) as balance
                               FROM purchase_headers ph 
                               WHERE (ph.total_amount - COALESCE(ph.paid_amount, 0)) > 0
                               AND ph.id_hospital = ?
                               ORDER BY ph.purchase_date ASC");
                        $stmt_pending->execute([$id_hospital]);
                        $pending_purchases = $stmt_pending->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Exception $e) {
                        $pending_purchases = [];
                    }
                    ?>

                    <?php if (count($pending_purchases) > 0): ?>
                            <div class="table-responsive">
                                <table class="appointments-table" id="tablePending">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Proveedor</th>
                                            <th>Documento</th>
                                            <th>Total</th>
                                            <th>Pagado</th>
                                            <th>Saldo</th>
                                            <th>Días</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pending_purchases as $purchase): ?>
                                                <?php
                                                $balance = $purchase['balance'];
                                                $paid = $purchase['total_amount'] - $balance;
                                                $purchase_date = new DateTime($purchase['purchase_date']);
                                                $today = new DateTime();
                                                $days_diff = $today->diff($purchase_date)->days;
                                                ?>
                                                <tr>
                                                    <td><?php echo date('d/m/Y', strtotime($purchase['purchase_date'])); ?></td>
                                                    <td class="fw-bold"><?php echo htmlspecialchars($purchase['provider_name']); ?></td>
                                                    <td>
                                                        <span class="badge badge-secondary">
                                                            <?php echo htmlspecialchars($purchase['document_type']); ?>
                                                            <?php echo $purchase['document_number'] ? '#' . $purchase['document_number'] : ''; ?>
                                                        </span>
                                                    </td>
                                                    <td class="fw-bold">Q<?php echo number_format($purchase['total_amount'], 2); ?></td>
                                                    <td class="text-success">Q<?php echo number_format($paid, 2); ?></td>
                                                    <td class="fw-bold text-danger">Q<?php echo number_format($balance, 2); ?></td>
                                                    <td>
                                                        <span
                                                            class="badge <?php echo $days_diff > 30 ? 'badge-danger' : ($days_diff > 15 ? 'badge-warning' : 'badge-info'); ?>">
                                                            <?php echo $days_diff; ?> días
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <a href="#" class="btn-icon edit" title="Registrar pago"
                                                                onclick="openPaymentModal(<?php echo $purchase['id']; ?>)">
                                                                <i class="bi bi-cash-coin"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3 text-center">
                                <p class="text-muted mb-2">
                                    Total pendiente: <strong
                                        class="text-danger">Q<?php echo number_format($total_balance, 2); ?></strong>
                                    en <strong><?php echo $pending_count; ?></strong> compras
                                </p>
                            </div>
                    <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <i class="bi bi-check-circle text-success"></i>
                                </div>
                                <h4 class="text-muted mb-2">¡Excelente gestión!</h4>
                                <p class="text-muted mb-3">Todas las compras están completamente pagadas</p>
                            </div>
                    <?php endif; ?>
                </section>
            </div>

            <!-- Pestaña: Pagos Realizados -->
            <div class="tab-content" id="paid-payments-tab">
                <section class="appointments-section animate-in delay-2">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="bi bi-check-circle text-success section-title-icon"></i>
                            Compras Completamente Pagadas
                        </h3>
                        <div class="search-box">
                            <i class="bi bi-search search-icon"></i>
                            <input type="text" id="searchPaid" placeholder="Buscar proveedor...">
                        </div>
                    </div>

                    <?php
                    try {
                        $stmt_paid = $conn->prepare("
                            SELECT * FROM purchase_headers
                            WHERE payment_status = 'Pagado'
                              AND id_hospital = ?
                            ORDER BY purchase_date DESC, created_at DESC
                        ");
                        $stmt_paid->execute([$id_hospital]);
                        $paid_purchases = $stmt_paid->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Exception $e) {
                        $paid_purchases = [];
                    }
                    ?>

                    <?php if (count($paid_purchases) > 0): ?>
                        <div class="table-responsive">
                            <table class="appointments-table" id="tablePaid">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Proveedor</th>
                                        <th>Documento</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-end">Pagado</th>
                                        <th>Tipo Pago</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($paid_purchases as $purchase): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($purchase['purchase_date'])); ?></td>
                                            <td class="fw-bold"><?php echo htmlspecialchars($purchase['provider_name']); ?></td>
                                            <td>
                                                <span class="badge badge-secondary">
                                                    <?php echo htmlspecialchars($purchase['document_type']); ?>
                                                    <?php echo $purchase['document_number'] ? '#' . $purchase['document_number'] : ''; ?>
                                                </span>
                                            </td>
                                            <td class="text-end fw-bold">Q<?php echo number_format($purchase['total_amount'], 2); ?></td>
                                            <td class="text-end text-success">Q<?php echo number_format($purchase['paid_amount'], 2); ?></td>
                                            <td>
                                                <span class="badge <?php echo $purchase['tipo_pago'] === 'Contado' ? 'badge-primary' : 'badge-warning'; ?>">
                                                    <?php echo htmlspecialchars($purchase['tipo_pago']); ?>
                                                </span>
                                            </td>
                                            <td><span class="badge badge-success">Pagado</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3 text-center">
                            <p class="text-muted mb-2">
                                Total pagado: <strong class="text-success">Q<?php
                                    $total_paid = array_sum(array_column($paid_purchases, 'total_amount'));
                                    echo number_format($total_paid, 2);
                                ?></strong> en <strong><?php echo count($paid_purchases); ?></strong> compras
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="bi bi-inbox text-muted"></i>
                            </div>
                            <h4 class="text-muted mb-2">Sin pagos realizados</h4>
                            <p class="text-muted mb-3">Aún no hay compras marcadas como pagadas</p>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <!-- Pestaña: Contabilidad -->
            <div class="tab-content" id="accounting-tab">
                <section class="appointments-section animate-in">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="bi bi-bar-chart-line text-primary section-title-icon"></i>
                            Contabilidad de Compras
                        </h3>
                    </div>

                    <!-- Filtros de período -->
                    <div class="accounting-filters mb-4">
                        <div class="btn-group" role="group" aria-label="Filtros de período">
                            <button type="button" class="btn btn-outline-primary active" data-accounting-period="current_month">
                                <i class="bi bi-calendar-month me-1"></i> Mes Actual
                            </button>
                            <button type="button" class="btn btn-outline-primary" data-accounting-period="previous_month">
                                <i class="bi bi-calendar-minus me-1"></i> Mes Anterior
                            </button>
                            <button type="button" class="btn btn-outline-primary" data-accounting-period="specific_month">
                                <i class="bi bi-calendar3 me-1"></i> Específico
                            </button>
                            <button type="button" class="btn btn-outline-primary" data-accounting-period="all">
                                <i class="bi bi-infinity me-1"></i> General
                            </button>
                        </div>
                        <div class="mt-2" id="specificMonthContainer" style="display:none;">
                            <input type="month" id="specificMonthInput" class="form-control form-control-sm" style="max-width: 200px;" value="<?php echo date('Y-m'); ?>">
                        </div>
                        <small class="text-muted mt-2 d-block">
                            <i class="bi bi-info-circle"></i> Período: <strong id="accountingPeriodLabel"><?php echo date('Y-m'); ?></strong>
                        </small>
                    </div>

                    <!-- Loading -->
                    <div id="accountingLoading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="text-muted mt-2">Cargando contabilidad...</p>
                    </div>

                    <!-- Contenido principal (oculto hasta cargar) -->
                    <div id="accountingContent" style="display:none;">
                        <!-- KPIs -->
                        <div class="accounting-kpi-grid mb-4">
                            <div class="accounting-kpi accounting-kpi--primary">
                                <div class="accounting-kpi__icon"><i class="bi bi-cart-plus"></i></div>
                                <div class="accounting-kpi__body">
                                    <div class="accounting-kpi__label">Total Comprado</div>
                                    <div class="accounting-kpi__value" id="kpiCompradoTotal">Q 0.00</div>
                                    <div class="accounting-kpi__meta"><span id="kpiCompradoNum">0</span> compras</div>
                                </div>
                            </div>
                            <div class="accounting-kpi accounting-kpi--success">
                                <div class="accounting-kpi__icon"><i class="bi bi-cash-coin"></i></div>
                                <div class="accounting-kpi__body">
                                    <div class="accounting-kpi__label">Total Pagado</div>
                                    <div class="accounting-kpi__value" id="kpiPagadoTotal">Q 0.00</div>
                                    <div class="accounting-kpi__meta"><span id="kpiPagadoNum">0</span> pagos realizados</div>
                                </div>
                            </div>
                            <div class="accounting-kpi accounting-kpi--warning">
                                <div class="accounting-kpi__icon"><i class="bi bi-exclamation-circle"></i></div>
                                <div class="accounting-kpi__body">
                                    <div class="accounting-kpi__label">Saldo Pendiente</div>
                                    <div class="accounting-kpi__value" id="kpiPendienteSaldo">Q 0.00</div>
                                    <div class="accounting-kpi__meta"><span id="kpiPendienteNum">0</span> compras pendientes</div>
                                </div>
                            </div>
                            <div class="accounting-kpi accounting-kpi--info">
                                <div class="accounting-kpi__icon"><i class="bi bi-building"></i></div>
                                <div class="accounting-kpi__body">
                                    <div class="accounting-kpi__label">Proveedores</div>
                                    <div class="accounting-kpi__value" id="kpiProveedores">0</div>
                                    <div class="accounting-kpi__meta">proveedores únicos</div>
                                </div>
                            </div>
                        </div>

                        <!-- Detalle por proveedor -->
                        <div class="accounting-section mb-4">
                            <h4 class="accounting-section__title"><i class="bi bi-building me-2"></i>Detalle por Proveedor</h4>
                            <div class="accounting-table-wrap" data-tarifa-table>
                                <table class="accounting-table">
                                    <thead>
                                        <tr>
                                            <th>Proveedor</th>
                                            <th class="text-center"># Compras</th>
                                            <th class="text-end">Total Comprado</th>
                                            <th class="text-end">Total Pagado</th>
                                            <th class="text-end">Saldo</th>
                                            <th style="width: 180px;">% Pagado</th>
                                        </tr>
                                    </thead>
                                    <tbody id="accountingProveedorBody">
                                        <tr><td colspan="6" class="text-center text-muted py-4">Sin datos para el período</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Resumen últimos 12 meses -->
                        <div class="accounting-section">
                            <h4 class="accounting-section__title"><i class="bi bi-calendar3 me-2"></i>Resumen Últimos 12 Meses</h4>
                            <div class="accounting-table-wrap" data-tarifa-table>
                                <table class="accounting-table">
                                    <thead>
                                        <tr>
                                            <th>Mes</th>
                                            <th class="text-center"># Compras</th>
                                            <th class="text-end">Total Comprado</th>
                                            <th class="text-center"># Pagos</th>
                                            <th class="text-end">Total Pagado</th>
                                            <th class="text-end">Diferencia</th>
                                        </tr>
                                    </thead>
                                    <tbody id="accountingMensualBody">
                                        <tr><td colspan="6" class="text-center text-muted py-4">Sin datos</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Pestaña: Top Proveedores -->
            <div class="tab-content" id="top-providers-tab">
                <div class="providers-grid animate-in delay-4">
                    <div class="provider-card">
                        <div class="provider-header">
                            <div class="provider-icon">
                                <i class="bi bi-trophy"></i>
                            </div>
                            <h3 class="provider-title">Proveedores Principales</h3>
                        </div>

                        <?php if (count($top_providers) > 0): ?>
                                <ul class="provider-list">
                                    <?php foreach ($top_providers as $provider): ?>
                                            <li class="provider-item">
                                                <div class="provider-item-header">
                                                    <span
                                                        class="provider-item-name"><?php echo htmlspecialchars($provider['provider_name']); ?></span>
                                                    <span class="provider-badge success">
                                                        <?php echo $provider['count']; ?> compras
                                                    </span>
                                                </div>
                                                <div class="provider-item-details">
                                                    <span>Total invertido:</span>
                                                    <span class="fw-bold">Q<?php echo number_format($provider['total'], 2); ?></span>
                                                </div>
                                            </li>
                                    <?php endforeach; ?>
                                </ul>
                        <?php else: ?>
                                <div class="no-alerts">
                                    <div class="no-alerts-icon">
                                        <i class="bi bi-building"></i>
                                    </div>
                                    <p class="text-muted mb-0">No hay datos de proveedores</p>
                                </div>
                        <?php endif; ?>
                    </div>

                    <!-- Card de acciones rápidas -->
                    <div class="provider-card">
                        <div class="provider-header">
                            <div class="provider-icon"
                                style="background: rgba(var(--color-primary-rgb), 0.1); color: var(--color-primary);">
                                <i class="bi bi-lightning"></i>
                            </div>
                            <h3 class="provider-title">Acciones Rápidas</h3>
                        </div>

                        <div class="provider-list">
                            <div class="provider-item" style="cursor: pointer;" onclick="showNewPurchaseModal()">
                                <div class="provider-item-header">
                                    <span class="provider-item-name">Nueva Compra</span>
                                    <i class="bi bi-plus-circle text-primary"></i>
                                </div>
                                <div class="provider-item-details">
                                    <span>Registrar una nueva compra de medicamentos</span>
                                </div>
                            </div>

                            <div class="provider-item" style="cursor: pointer;" onclick="showPendingPurchases()">
                                <div class="provider-item-header">
                                    <span class="provider-item-name">Ver Pendientes</span>
                                    <i class="bi bi-clock-history text-warning"></i>
                                </div>
                                <div class="provider-item-details">
                                    <span>Compras con saldo pendiente de pago</span>
                                </div>
                            </div>

                            <div class="provider-item" style="cursor: pointer;"
                                onclick="window.open('../reports/compras_mensual.php', '_blank')">
                                <div class="provider-item-header">
                                    <span class="provider-item-name">Reporte Mensual</span>
                                    <i class="bi bi-file-earmark-pdf text-danger"></i>
                                </div>
                                <div class="provider-item-details">
                                    <span>Generar reporte de compras del mes</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestaña: Gastos -->
            <div class="tab-content" id="gastos-tab">
                <section class="appointments-section animate-in">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="bi bi-wallet2 section-title-icon" style="color:var(--color-danger);"></i>
                            Gastos Generales del Hospital
                        </h3>
                        <div class="d-flex gap-2 flex-wrap">
                            <div class="search-box">
                                <i class="bi bi-calendar-range search-icon"></i>
                                <input type="month" id="gastosMonth" class="form-control form-control-sm"
                                    value="<?php echo date('Y-m'); ?>"
                                    onchange="loadGastos()"
                                    style="padding-left:2.25rem;max-width:200px;">
                            </div>
                            <button class="action-btn" onclick="showNewGastoModal()">
                                <i class="bi bi-plus-lg me-2"></i>Nuevo Gasto
                            </button>
                            <button class="action-btn action-btn-outline" onclick="showDeletedGastos()">
                                <i class="bi bi-archive me-2"></i>Ver Eliminados
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="appointments-table" id="gastosTable">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Descripción</th>
                                    <th>Cant.</th>
                                    <th class="text-end">Subtotal</th>
                                    <th class="text-end">Total</th>
                                    <th>Registrado por</th>
                                    <th style="width:50px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr id="gastosLoadingRow">
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="bi bi-arrow-clockwise spin me-2"></i>Cargando gastos...
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="4" class="text-end fw-bold">Total Gastos:</td>
                                    <td class="fw-bold text-danger" id="gastosTotalFooter">Q0.00</td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <!-- Modal para nuevo gasto -->
    <div class="custom-modal-overlay" id="newGastoModal">
        <div class="custom-modal" style="max-width:600px;">
            <div class="custom-modal-header">
                <h5 class="custom-modal-title">
                    <i class="bi bi-wallet2 text-danger me-2"></i>
                    Registrar Nuevo Gasto
                </h5>
                <button type="button" class="custom-modal-close"
                    onclick="document.getElementById('newGastoModal').classList.remove('active')">&times;</button>
            </div>
            <div class="custom-modal-body">
                <form id="gastoForm">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control" id="gasto_descripcion" rows="2"
                                placeholder="Ej. Insumos para baños, materiales de limpieza, etc." required></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Fecha</label>
                            <input type="date" class="form-control" id="gasto_fecha"
                                value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cantidad</label>
                            <input type="number" class="form-control" id="gasto_cantidad" min="1" value="1"
                                onchange="calcularGastoTotal()" onkeyup="calcularGastoTotal()">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Subtotal (Q)</label>
                            <input type="number" class="form-control" id="gasto_subtotal" min="0" step="0.01"
                                onchange="calcularGastoTotal()" onkeyup="calcularGastoTotal()">
                        </div>
                        <div class="col-md-4 offset-md-8">
                            <label class="form-label fw-bold">Total (Q)</label>
                            <input type="number" class="form-control" id="gasto_total" min="0" step="0.01" readonly
                                style="font-weight:700;background:var(--color-surface);">
                        </div>
                    </div>
                </form>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="action-btn secondary"
                    onclick="document.getElementById('newGastoModal').classList.remove('active')">Cancelar</button>
                <button type="button" class="action-btn primary" id="saveGastoBtn" onclick="saveGasto()">
                    <i class="bi bi-check-lg me-2"></i>Guardar Gasto
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de gastos eliminados -->
    <div class="custom-modal-overlay" id="deletedGastosModal">
        <div class="custom-modal modal-lg">
            <div class="custom-modal-header">
                <h5 class="custom-modal-title">
                    <i class="bi bi-archive text-secondary me-2"></i>
                    Gastos Eliminados
                </h5>
                <button type="button" class="custom-modal-close"
                    onclick="this.closest('.custom-modal-overlay').classList.remove('active')">&times;</button>
            </div>
            <div class="custom-modal-body">
                <p class="text-muted mb-3">Estos gastos se eliminarán definitivamente al final del mes.</p>
                <div class="table-responsive">
                    <table class="appointments-table" id="deletedGastosTable">
                        <thead>
                            <tr>
                                <th>Fecha eliminación</th>
                                <th>Descripción</th>
                                <th>Cant.</th>
                                <th class="text-end">Total</th>
                                <th>Eliminado por</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="bi bi-arrow-clockwise spin me-2"></i>Cargando...
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="3" class="text-end fw-bold">Total Eliminados:</td>
                                <td class="fw-bold text-danger" id="deletedGastosTotalFooter">Q0.00</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="action-btn secondary"
                    onclick="document.getElementById('deletedGastosModal').classList.remove('active')">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- Modal para nueva compra -->
    <div class="custom-modal-overlay" id="newPurchaseModal">
        <div class="custom-modal modal-xl">
            <div class="custom-modal-header">
                <h5 class="custom-modal-title">
                    <i class="bi bi-bag-plus text-primary me-2"></i>
                    Registrar Nueva Compra
                </h5>
                <button type="button" class="custom-modal-close"
                    onclick="this.closest('.custom-modal-overlay').classList.remove('active')">&times;</button>
            </div>
            <div class="custom-modal-body">
                <form id="purchaseForm">
                    <!-- Header Info -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label">Fecha de Compra</label>
                            <input type="date" class="form-control" name="purchase_date" id="purchase_date" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Tipo de Documento</label>
                            <select class="form-select" name="document_type" id="document_type" required>
                                <option value="Factura">Factura</option>
                                <option value="Nota de Envío">Nota de Envío</option>
                                <option value="Consumidor Final">Consumidor Final</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Tipo de Pago</label>
                            <select class="form-select" name="tipo_pago" id="purchase_tipo_pago" required>
                                <option value="Contado">Al Contado</option>
                                <option value="Credito 30" selected>Crédito 30</option>
                                <option value="Credito 60">Crédito 60</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">No. Documento</label>
                            <input type="text" class="form-control" name="document_number" id="document_number"
                                placeholder="Ej. A-12345">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Casa Farmacéutica / Proveedor</label>
                            <input type="text" class="form-control" name="provider_name" id="provider_name"
                                placeholder="Nombre de la casa farmacéutica">
                        </div>
                    </div>

                    <hr class="opacity-25">

                    <!-- Add Item Section -->
                    <h6 class="fw-bold mb-3">Agregar Productos</h6>
                    <div class="card bg-light border-0 mb-4">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label small">Producto/Medicamento</label>
                                    <input type="text" class="form-control form-control-sm" id="item_name"
                                        placeholder="Nombre">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">Presentación</label>
                                    <input type="text" class="form-control form-control-sm" id="item_presentation"
                                        placeholder="Ej. Tableta">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">Molécula</label>
                                    <input type="text" class="form-control form-control-sm" id="item_molecule"
                                        placeholder="Componente">
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label small">Cant.</label>
                                    <input type="number" class="form-control form-control-sm" id="item_qty" min="1"
                                        value="1">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">Costo (Q)</label>
                                    <input type="number" class="form-control form-control-sm" id="item_cost" min="0"
                                        step="0.01">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">Precio Venta (Q)</label>
                                    <input type="number" class="form-control form-control-sm" id="item_sale_price"
                                        min="0" step="0.01">
                                </div>
                                <div class="col-md-12 d-flex justify-content-end mt-3">
                                    <button type="button" class="action-btn btn-sm" onclick="addItem()">
                                        <i class="bi bi-plus-lg me-2"></i>Agregar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Items List -->
                    <div class="table-responsive mb-3" style="max-height: 300px;">
                        <table class="table table-sm table-bordered" id="itemsTable">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Producto</th>
                                    <th>Presentación</th>
                                    <th>Cant.</th>
                                    <th>Costo U.</th>
                                    <th>Precio Venta</th>
                                    <th>Subtotal</th>
                                    <th style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Items will be added here -->
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="4" class="text-end fw-bold">Total Compra:</td>
                                    <td class="fw-bold text-primary">Q<span id="totalAmount">0.00</span></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </form>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="action-btn secondary"
                    onclick="document.getElementById('newPurchaseModal').classList.remove('active')">Cancelar</button>
                <button type="button" class="action-btn primary" id="savePurchaseBtn" onclick="savePurchase()">
                    <i class="bi bi-check-lg me-2"></i>Guardar Compra
                </button>
            </div>
        </div>
    </div>

    <!-- Modal para pagos -->
    <div class="custom-modal-overlay" id="paymentModal">
        <div class="custom-modal modal-lg">
            <div class="custom-modal-header">
                <h5 class="custom-modal-title">
                    <i class="bi bi-cash-coin text-primary me-2"></i>
                    Gestionar Pagos / Abonos
                </h5>
                <button type="button" class="custom-modal-close"
                    onclick="this.closest('.custom-modal-overlay').classList.remove('active')">&times;</button>
            </div>
            <div class="custom-modal-body">
                <div id="paymentHeaderInfo"
                    class="alert alert-info d-flex justify-content-between align-items-center mb-4">
                    <!-- Loaded dynamically -->
                    <span>Cargando información...</span>
                </div>

                <div class="row">
                    <div class="col-md-5 border-end">
                        <h6 class="fw-bold mb-3">Registrar Nuevo Abono</h6>
                        <form id="paymentForm">
                            <input type="hidden" id="pay_purchase_id" name="purchase_id">

                            <div class="mb-3">
                                <label class="form-label">Fecha</label>
                                <input type="date" class="form-control" name="payment_date" id="pay_date" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Monto (Q)</label>
                                <input type="number" class="form-control" name="amount" id="pay_amount" step="0.01"
                                    min="0.01" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Método de Pago</label>
                                <select class="form-select" name="payment_method" id="pay_method">
                                    <option value="Efectivo">Efectivo</option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="Transferencia">Transferencia</option>
                                    <option value="Depósito">Depósito</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Notas</label>
                                <textarea class="form-control" name="notes" id="pay_notes" rows="2"></textarea>
                            </div>

                            <button type="button" class="action-btn primary w-100" id="submitPaymentBtn"
                                onclick="submitPayment()">
                                <i class="bi bi-check-circle me-2"></i>Registrar Pago
                            </button>
                        </form>
                    </div>

                    <div class="col-md-7">
                        <h6 class="fw-bold mb-3">Historial de Pagos</h6>
                        <div class="table-responsive" style="max-height: 300px;">
                            <table class="table table-sm table-hover" id="paymentsHistoryTable">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Método</th>
                                        <th>Monto</th>
                                        <th>Notas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Loaded dynamically -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para detalles -->
    <div class="custom-modal-overlay" id="detailsModal">
        <div class="custom-modal modal-lg">
            <div class="custom-modal-header">
                <h5 class="custom-modal-title">
                    <i class="bi bi-eye text-primary me-2"></i>
                    Detalles de Compra
                </h5>
                <button type="button" class="custom-modal-close"
                    onclick="this.closest('.custom-modal-overlay').classList.remove('active')">&times;</button>
            </div>
            <div class="custom-modal-body" id="detailsModalBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-2 text-muted">Cargando detalles...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para ajuste de traslados históricos -->
    <div class="custom-modal-overlay" id="transferAdjustmentModal">
        <div class="custom-modal modal-lg">
            <div class="custom-modal-header">
                <h5 class="custom-modal-title">
                    <i class="bi bi-arrow-left-right text-warning me-2"></i>
                    Ajuste de Traslados Históricos
                </h5>
                <button type="button" class="custom-modal-close"
                    onclick="this.closest('.custom-modal-overlay').classList.remove('active')">&times;</button>
            </div>
            <div class="custom-modal-body" id="transferAdjustmentBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-warning"></div>
                    <p class="mt-2 text-muted">Cargando traslados...</p>
                </div>
            </div>
            <div class="custom-modal-footer" id="transferAdjustmentFooter">
                <button type="button" class="action-btn secondary"
                    onclick="document.getElementById('transferAdjustmentModal').classList.remove('active')">Cerrar</button>
                <button type="button" class="action-btn success" id="btnApplyAdjustment" style="display:none"
                    onclick="applyTransferAdjustment()">
                    <i class="bi bi-check-lg me-2"></i>Aplicar Ajuste
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
        // Módulo de Compras Reingenierizado - Centro Médico Herrera Saenz

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
                tabButtons: document.querySelectorAll('.tab-btn'),
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
            // MANEJO DE PESTAÑAS
            // ==========================================================================
            class TabManager {
                constructor() {
                    this.setupEventListeners();
                }

                switchTab(tabId) {
                    // Remover clase active de todos los botones y contenidos
                    DOM.tabButtons.forEach(btn => btn.classList.remove('active'));
                    DOM.tabContents.forEach(content => content.classList.remove('active'));

                    // Agregar clase active al botón clickeado
                    const activeButton = document.querySelector(`[data-tab="${tabId}"]`);
                    if (activeButton) {
                        activeButton.classList.add('active');
                    }

                    // Mostrar el contenido correspondiente
                    const activeContent = document.getElementById(`${tabId}-tab`);
                    if (activeContent) {
                        activeContent.classList.add('active');
                    }

                    // Guardar pestaña activa
                    localStorage.setItem('purchases-active-tab', tabId);

                    // Auto-cargar gastos cuando se active esa pestaña
                    if (tabId === 'gastos' && typeof window.loadGastos === 'function') {
                        setTimeout(window.loadGastos, 100);
                    }

                    // Auto-cargar contabilidad cuando se active esa pestaña
                    if (tabId === 'accounting' && typeof window.loadContabilidad === 'function') {
                        setTimeout(window.loadContabilidad, 100);
                    }
                }

                setupEventListeners() {
                    DOM.tabButtons.forEach(button => {
                        button.addEventListener('click', () => {
                            const tabId = button.getAttribute('data-tab');
                            this.switchTab(tabId);
                        });
                    });

                    // Restaurar pestaña activa
                    const savedTab = localStorage.getItem('purchases-active-tab');
                    if (savedTab) {
                        this.switchTab(savedTab);
                    }
                }
            }

            // ==========================================================================
            // COMPONENTES DINÁMICOS DE COMPRAS
            // ==========================================================================
            class PurchasesManager {
                constructor() {
                    this.purchaseItems = [];
                    this.setupGreeting();
                    this.setupClock();
                    this.setupSearch();
                    this.setupAnimations();
                    this.initializeDate();
                    // El mantenimiento de sesión ahora se maneja globalmente
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

                setupSearch() {
                    // Búsqueda en tabla de compras recientes
                    const searchRecent = document.getElementById('searchRecent');
                    if (searchRecent) {
                        searchRecent.addEventListener('input', function () {
                            const searchTerm = this.value.toLowerCase();
                            const rows = document.querySelectorAll('#tableRecent tbody tr');

                            rows.forEach(row => {
                                const text = row.textContent.toLowerCase();
                                row.style.display = text.includes(searchTerm) ? '' : 'none';
                            });
                        });
                    }

                    // Búsqueda en tabla de pendientes
                    const searchPending = document.getElementById('searchPending');
                    if (searchPending) {
                        searchPending.addEventListener('input', function () {
                            const searchTerm = this.value.toLowerCase();
                            const rows = document.querySelectorAll('#tablePending tbody tr');

                            rows.forEach(row => {
                                const text = row.textContent.toLowerCase();
                                row.style.display = text.includes(searchTerm) ? '' : 'none';
                            });
                        });
                    }

                    // Búsqueda en tabla de pagos realizados
                    const searchPaid = document.getElementById('searchPaid');
                    if (searchPaid) {
                        searchPaid.addEventListener('input', function () {
                            const searchTerm = this.value.toLowerCase();
                            const rows = document.querySelectorAll('#tablePaid tbody tr');

                            rows.forEach(row => {
                                const text = row.textContent.toLowerCase();
                                row.style.display = text.includes(searchTerm) ? '' : 'none';
                            });
                        });
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
                    document.querySelectorAll('.stat-card, .appointments-section, .provider-card').forEach(el => {
                        observer.observe(el);
                    });
                }

                initializeDate() {
                    const purchaseDate = document.getElementById('purchase_date');
                    if (purchaseDate) {
                        const today = new Date();
                        const formattedDate = today.toISOString().split('T')[0];
                        purchaseDate.value = formattedDate;
                    }

                    const payDate = document.getElementById('pay_date');
                    if (payDate) {
                        const today = new Date();
                        const formattedDate = today.toISOString().split('T')[0];
                        payDate.value = formattedDate;
                    }
                }

                // Mostrar compras pendientes
                showPendingPurchases() {
                    const tabManager = new TabManager();
                    tabManager.switchTab('pending-payments');
                }

            }

            // ==========================================================================
            // FUNCIONALIDADES ESPECÍFICAS DE COMPRAS
            // ==========================================================================

            // Variables globales para funcionalidad de compras
            window.purchaseItems = [];

            // Mostrar modal de nueva compra
            window.showNewPurchaseModal = function () {
                const modal = document.getElementById('newPurchaseModal');
                const hasDraft = window.purchaseDraftManager && window.purchaseDraftManager.hasDraft();

                if (hasDraft) {
                    // Si hay borrador, restaurarlo
                    window.purchaseDraftManager.restoreDraft();
                    window.purchaseDraftManager.showDraftNotification();
                    modal.classList.add('active');
                    return;
                }

                // SI NO HAY BORRADOR: Flujo norma de reset
                // Resetear formulario
                const form = document.getElementById('purchaseForm');
                if (form) form.reset();

                // Establecer fecha actual
                const purchaseDate = document.getElementById('purchase_date');
                if (purchaseDate) {
                    const today = new Date();
                    const formattedDate = today.toISOString().split('T')[0];
                    purchaseDate.value = formattedDate;
                }

                // Limpiar items
                window.purchaseItems = [];
                renderItems();

                // Mostrar modal
                document.getElementById('newPurchaseModal').classList.add('active');
            };

            // Mostrar compras pendientes
            window.showPendingPurchases = function () {
                const tabManager = new TabManager();
                tabManager.switchTab('pending-payments');
            };

            // Agregar item a la lista de compra
            window.addItem = function () {
                const name = document.getElementById('item_name').value.trim();
                const qty = parseFloat(document.getElementById('item_qty').value);
                const cost = parseFloat(document.getElementById('item_cost').value);
                const salePrice = parseFloat(document.getElementById('item_sale_price').value);

                // Validar campos obligatorios
                if (!name || !qty || isNaN(cost) || isNaN(salePrice)) {
                    Swal.fire({
                        title: 'Campos incompletos',
                        text: 'Por favor complete todos los campos del producto',
                        icon: 'warning',
                        confirmButtonText: 'Entendido'
                    });
                    return;
                }

                // Crear objeto item
                const item = {
                    id: Date.now(), // ID temporal
                    name: name,
                    presentation: document.getElementById('item_presentation').value.trim(),
                    molecule: document.getElementById('item_molecule').value.trim(),
                    qty: qty,
                    cost: cost,
                    sale_price: salePrice,
                    subtotal: qty * cost
                };

                // Agregar a la lista
                purchaseItems.push(item);
                renderItems();

                // Limpiar campos de entrada
                document.getElementById('item_name').value = '';
                document.getElementById('item_presentation').value = '';
                document.getElementById('item_molecule').value = '';
                document.getElementById('item_qty').value = '1';
                document.getElementById('item_cost').value = '';
                document.getElementById('item_sale_price').value = '';
                document.getElementById('item_name').focus();
            };

            // Remover item de la lista
            window.removeItem = function (id) {
                purchaseItems = purchaseItems.filter(item => item.id !== id);
                renderItems();
            };

            // Renderizar items en la tabla
            window.renderItems = function () {
                const tbody = document.querySelector('#itemsTable tbody');
                if (!tbody) return;

                tbody.innerHTML = '';

                let total = 0;

                // Agregar cada item a la tabla
                purchaseItems.forEach(item => {
                    total += item.subtotal;

                    const row = document.createElement('tr');
                    row.innerHTML = `
                    <td>
                        <div class="fw-bold">${item.name}</div>
                        <small class="text-muted">${item.molecule || 'Sin molécula especificada'}</small>
                    </td>
                    <td>${item.presentation || 'N/A'}</td>
                    <td class="text-center">${item.qty}</td>
                    <td class="text-end">Q${item.cost.toFixed(2)}</td>
                    <td class="text-end">Q${item.sale_price.toFixed(2)}</td>
                    <td class="text-end">Q${item.subtotal.toFixed(2)}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeItem(${item.id})" title="Eliminar">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                `;
                    tbody.appendChild(row);
                });

                // Actualizar total
                const totalAmount = document.getElementById('totalAmount');
                if (totalAmount) {
                    totalAmount.textContent = total.toFixed(2);
                }

                // Guardar borrador automáticamente al cambiar la lista
                // (Solo si el draftManager ya está inicializado)
                if (window.purchaseDraftManager) {
                    window.purchaseDraftManager.saveDraft();
                }
            }

            // Guardar compra
            window.savePurchase = function () {
                // Validar que haya items
                if (purchaseItems.length === 0) {
                    Swal.fire({
                        title: 'Compra vacía',
                        text: 'Debe agregar al menos un producto a la compra',
                        icon: 'warning',
                        confirmButtonText: 'Entendido'
                    });
                    return;
                }

                // Validar proveedor
                const providerName = document.getElementById('provider_name').value.trim();
                if (!providerName) {
                    Swal.fire({
                        title: 'Proveedor requerido',
                        text: 'Debe especificar un proveedor o casa farmacéutica',
                        icon: 'warning',
                        confirmButtonText: 'Entendido'
                    });
                    return;
                }

                // Deshabilitar botón para evitar duplicados
                const saveBtn = document.getElementById('savePurchaseBtn');
                if (saveBtn) {
                    saveBtn.disabled = true;
                    saveBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin me-2"></i>Guardando...';
                }

                // Preparar datos del encabezado
                const header = {
                    purchase_date: document.getElementById('purchase_date').value,
                    document_type: document.getElementById('document_type').value,
                    tipo_pago: document.getElementById('purchase_tipo_pago').value,
                    document_number: document.getElementById('document_number').value,
                    provider_name: providerName,
                    total_amount: parseFloat(document.getElementById('totalAmount').textContent)
                };

                // Preparar payload completo
                const payload = {
                    header: header,
                    items: purchaseItems
                };

                // Enviar datos al servidor
                fetch('save_purchase.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('HTTP ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            if (window.purchaseDraftManager) {
                                window.purchaseDraftManager.clearDraft();
                            }

                            // Cerrar modal PRIMERO
                            document.getElementById('newPurchaseModal').classList.remove('active');
                            window.purchaseItems = [];
                            if (window.purchaseDraftManager) {
                                window.purchaseDraftManager.clearDraft();
                            }
                            renderItems();

                            // Mostrar toast de éxito DESPUÉS de cerrar modal
                            setTimeout(() => {
                                Swal.fire({
                                    title: '¡Compra Registrada!',
                                    text: 'La compra se ha registrado correctamente. Los productos se han agregado al inventario como pendientes.',
                                    icon: 'success',
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    location.reload();
                                });
                            }, 300);
                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: data.message || 'Error al guardar la compra',
                                icon: 'error',
                                confirmButtonText: 'Entendido'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            title: 'Error de conexión',
                            text: 'Ocurrió un error al procesar la solicitud: ' + error.message,
                            icon: 'error',
                            confirmButtonText: 'Entendido'
                        });
                    })
                    .finally(() => {
                        // Rehabilitar botón en caso de error o éxito
                        if (saveBtn) {
                            saveBtn.disabled = false;
                            saveBtn.innerHTML = '<i class="bi bi-check-lg me-2"></i>Guardar Compra';
                        }
                    });
            };

            // Ver detalles de compra
            window.viewPurchaseDetails = function (id) {
                document.getElementById('detailsModal').classList.add('active');

                // Mostrar spinner mientras carga
                document.getElementById('detailsModalBody').innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-2 text-muted">Cargando detalles...</p>
                </div>
            `;

                // Obtener datos del servidor
                fetch('get_purchase_details.php?id=' + id)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Error en la respuesta del servidor');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            const h = data.header;
                            let html = `
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <p class="mb-2"><strong>Proveedor:</strong> ${h.provider_name}</p>
                                <p class="mb-2"><strong>Documento:</strong> ${h.document_type} ${h.document_number || 'N/A'}</p>
                                <p class="mb-2"><strong>Tipo de Pago:</strong> ${h.tipo_pago || 'Credito 30'}</p>
                                <p class="mb-0"><strong>Fecha:</strong> ${h.purchase_date}</p>
                            </div>
                            <div class="col-md-6 text-end">
                                <p class="mb-2"><strong>Total Compra:</strong> Q${parseFloat(h.total_amount).toFixed(2)}</p>
                                <p class="mb-2"><strong>Pagado:</strong> Q${parseFloat(h.paid_amount || 0).toFixed(2)}</p>
                                <p class="mb-0"><strong>Saldo:</strong> Q${parseFloat(h.total_amount - (h.paid_amount || 0)).toFixed(2)}</p>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Producto</th>
                                        <th>Presentación</th>
                                        <th>Molécula</th>
                                        <th>Cant.</th>
                                        <th>Costo U.</th>
                                        <th>Precio Venta</th>
                                        <th>Subtotal</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;

                            data.items.forEach(item => {
                                const itemStatus = (item.status || 'Pendiente');
                                const badge = itemStatus === 'Recibido'
                                    ? '<span class="badge badge-success"><i class="bi bi-check-circle me-1"></i>Recibido</span>'
                                    : '<span class="badge badge-warning"><i class="bi bi-clock-history me-1"></i>Pendiente</span>';
                                html += `
                            <tr>
                                <td>${item.product_name}</td>
                                <td>${item.presentation || 'N/A'}</td>
                                <td>${item.molecule || 'N/A'}</td>
                                <td class="text-center">${item.quantity}</td>
                                <td class="text-end">Q${parseFloat(item.unit_cost).toFixed(2)}</td>
                                <td class="text-end">Q${parseFloat(item.sale_price || 0).toFixed(2)}</td>
                                <td class="text-end">Q${parseFloat(item.subtotal).toFixed(2)}</td>
                                <td class="text-center">${badge}</td>
                            </tr>
                        `;
                            });

                            html += `
                                </tbody>
                            </table>
                        </div>
                    `;

                            document.getElementById('detailsModalBody').innerHTML = html;
                        } else {
                            document.getElementById('detailsModalBody').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            ${data.message || 'Error al cargar los detalles de la compra'}
                        </div>
                    `;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        document.getElementById('detailsModalBody').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Error de conexión al cargar los detalles
                    </div>
                `;
                    });
            };

            // Variables para el ajuste de traslados
            let currentTransferPurchaseId = null;

            // Mostrar modal de ajuste de traslados históricos
            window.showTransferAdjustment = function (purchaseId) {
                currentTransferPurchaseId = purchaseId;
                const modal = document.getElementById('transferAdjustmentModal');
                const body = document.getElementById('transferAdjustmentBody');
                const footer = document.getElementById('transferAdjustmentFooter');
                const btnApply = document.getElementById('btnApplyAdjustment');

                modal.classList.add('active');
                btnApply.style.display = 'none';
                body.innerHTML = `
                    <div class="text-center py-4">
                        <div class="spinner-border text-warning"></div>
                        <p class="mt-2 text-muted">Cargando traslados...</p>
                    </div>
                `;

                fetch('api/get_transfer_details.php?purchase_header_id=' + purchaseId)
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) {
                            body.innerHTML = `<div class="alert alert-danger">${data.error || 'Error al cargar'}</div>`;
                            return;
                        }

                        if (!data.transfers || data.transfers.length === 0) {
                            body.innerHTML = `<div class="alert alert-info">No se encontraron traslados históricos para esta compra.</div>`;
                            return;
                        }

                        let html = `
                            <div class="d-flex justify-content-between mb-3">
                                <span class="badge bg-info">${data.transfers.length} traslado(s)</span>
                                <span class="badge bg-warning">${data.total_qty} unidades transferidas</span>
                                <span class="badge bg-danger">Q${data.total_valor.toFixed(2)} valor total</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Medicamento</th>
                                            <th>Cant.</th>
                                            <th>Valor</th>
                                            <th>Destino</th>
                                            <th>Usuario</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;

                        data.transfers.forEach(t => {
                            const fecha = t.fecha_venta ? new Date(t.fecha_venta).toLocaleDateString('es-GT') : '-';
                            const canAdjust = t.can_adjust && !data.already_applied;
                            const estado = data.already_applied
                                ? '<span class="badge badge-success"><i class="bi bi-check-circle me-1"></i>Ajustado</span>'
                                : (t.can_adjust
                                    ? '<span class="badge badge-warning"><i class="bi bi-clock-history me-1"></i>Pendiente</span>'
                                    : '<span class="badge badge-secondary"><i class="bi bi-x-circle me-1"></i>Sin stock</span>');
                            html += `
                                <tr>
                                    <td>${fecha}</td>
                                    <td>${t.nom_medicamento}</td>
                                    <td class="text-center">${t.cantidad_vendida}</td>
                                    <td class="text-end">Q${parseFloat(t.valor).toFixed(2)}</td>
                                    <td>${t.destino || '-'}</td>
                                    <td>${t.usuario || '-'}</td>
                                    <td class="text-center">${estado}</td>
                                </tr>
                            `;
                        });

                        html += `
                                    </tbody>
                                </table>
                            </div>
                        `;

                        body.innerHTML = html;

                        if (!data.already_applied) {
                            btnApply.style.display = 'inline-block';
                        }
                    })
                    .catch(error => {
                        body.innerHTML = `<div class="alert alert-danger">Error de conexión: ${error.message}</div>`;
                    });
            };

            // Aplicar ajuste de traslados históricos
            window.applyTransferAdjustment = function () {
                if (!currentTransferPurchaseId) return;

                const btnApply = document.getElementById('btnApplyAdjustment');
                btnApply.disabled = true;
                btnApply.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Aplicando...';

                fetch('api/apply_transfer_adjustment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ purchase_header_id: currentTransferPurchaseId })
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Ajuste aplicado',
                                text: data.message || 'Compra ajustada correctamente',
                                timer: 2000,
                                showConfirmButton: true
                            }).then(() => {
                                document.getElementById('transferAdjustmentModal').classList.remove('active');
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.error || 'Error al aplicar el ajuste'
                            });
                            btnApply.disabled = false;
                            btnApply.innerHTML = '<i class="bi bi-check-lg me-2"></i>Aplicar Ajuste';
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de conexión',
                            text: error.message
                        });
                        btnApply.disabled = false;
                        btnApply.innerHTML = '<i class="bi bi-check-lg me-2"></i>Aplicar Ajuste';
                    });
            };

            // Eliminar compra (nuevo sistema)
            window.deletePurchase = async function (id) {
                const result = await Swal.fire({
                    title: '¿Está seguro?',
                    text: 'Esta acción eliminará la compra y todos los registros relacionados.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#dc3545'
                });

                if (!result.isConfirmed) return;

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

                try {
                    const response = await fetch('delete_purchase_new.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id, csrf_token: csrfToken })
                    });
                    const data = await response.json();
                    if (data.success) {
                        Swal.fire({
                            title: 'Eliminado',
                            text: 'Compra eliminada correctamente',
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({ title: 'Error', text: data.message, icon: 'error' });
                    }
                } catch (e) {
                    Swal.fire({ title: 'Error', text: 'Error de conexión con el servidor', icon: 'error' });
                }
            };

            // Abrir modal de pagos
            window.openPaymentModal = function (id) {
                // Establecer ID de compra
                document.getElementById('pay_purchase_id').value = id;

                // Establecer fecha actual
                document.getElementById('pay_date').valueAsDate = new Date();

                // Limpiar campos
                document.getElementById('pay_amount').value = '';
                document.getElementById('pay_notes').value = '';

                // Cargar información de pagos
                loadPayments(id);

                // Mostrar modal
                document.getElementById('paymentModal').classList.add('active');
            };

            // Cargar información de pagos
            function loadPayments(id) {
                // Mostrar estado de carga
                document.getElementById('paymentHeaderInfo').innerHTML = `
                <div class="text-center w-100">
                    <div class="spinner-border spinner-border-sm text-primary"></div>
                    <span class="ms-2">Cargando información...</span>
                </div>
            `;

                document.querySelector('#paymentsHistoryTable tbody').innerHTML = `
                <tr>
                    <td colspan="4" class="text-center text-muted">
                        <div class="spinner-border spinner-border-sm"></div>
                        Cargando historial...
                    </td>
                </tr>
            `;

                // Obtener datos del servidor
                fetch('get_payments.php?id=' + id)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Error en la respuesta del servidor');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            const h = data.header;
                            const total = parseFloat(h.total_amount);
                            const paid = parseFloat(h.paid_amount || 0);
                            const balance = total - paid;

                            // Actualizar información del encabezado
                            const infoHtml = `
                        <div>
                            <strong>${h.document_type} ${h.document_number || ''}</strong><br>
                            <small class="text-muted">${h.provider_name}</small>
                        </div>
                        <div class="text-end">
                            <div class="badge badge-success mb-1">Pagado: Q${paid.toFixed(2)}</div><br>
                            <div class="badge ${balance > 0 ? 'badge-danger' : 'badge-success'}">Saldo: Q${balance.toFixed(2)}</div>
                        </div>
                    `;
                            document.getElementById('paymentHeaderInfo').innerHTML = infoHtml;

                            // Establecer monto sugerido (saldo pendiente)
                            if (!document.getElementById('pay_amount').value && balance > 0) {
                                document.getElementById('pay_amount').value = balance.toFixed(2);
                            }

                            // Actualizar historial de pagos
                            const tbody = document.querySelector('#paymentsHistoryTable tbody');
                            tbody.innerHTML = '';

                            if (data.payments.length === 0) {
                                tbody.innerHTML = `
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">
                                    No hay pagos registrados
                                </td>
                            </tr>
                        `;
                            } else {
                                data.payments.forEach(p => {
                                    const row = document.createElement('tr');
                                    row.innerHTML = `
                                <td>${p.payment_date}</td>
                                <td>${p.payment_method}</td>
                                <td class="fw-bold text-success">Q${parseFloat(p.amount).toFixed(2)}</td>
                                <td><small>${p.notes || '-'}</small></td>
                            `;
                                    tbody.appendChild(row);
                                });
                            }
                        } else {
                            document.getElementById('paymentHeaderInfo').innerHTML = `
                        <div class="alert alert-danger w-100 mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            ${data.message}
                        </div>
                    `;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        document.getElementById('paymentHeaderInfo').innerHTML = `
                    <div class="alert alert-danger w-100 mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Error de conexión al cargar la información
                    </div>
                `;
                    });
            };

            // Enviar pago
            window.submitPayment = function () {
                const form = document.getElementById('paymentForm');
                const formData = new FormData(form);

                // Validar monto
                const amount = parseFloat(formData.get('amount'));
                if (amount <= 0) {
                    Swal.fire({
                        title: 'Monto inválido',
                        text: 'El monto debe ser mayor a cero',
                        icon: 'warning',
                        confirmButtonText: 'Entendido'
                    });
                    return;
                }

                // Deshabilitar botón para evitar duplicados
                const payBtn = document.getElementById('submitPaymentBtn');
                if (payBtn) {
                    payBtn.disabled = true;
                    payBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin me-2"></i>Procesando...';
                }

                // Enviar pago al servidor
                fetch('save_payment.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: '¡Pago Registrado!',
                                text: 'El abono se ha registrado correctamente',
                                icon: 'success',
                                confirmButtonText: 'Aceptar',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                // Cerrar modal y recargar página
                                document.getElementById('paymentModal').classList.remove('active');
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: data.message || 'Error al registrar el pago',
                                icon: 'error',
                                confirmButtonText: 'Entendido'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            title: 'Error de conexión',
                            text: 'Ocurrió un error al procesar el pago',
                            icon: 'error',
                            confirmButtonText: 'Entendido'
                        });
                    });
            };

            // ==========================================================================
            // GESTOR DE BORRADORES (AUTO-SAVE) PARA COMPRAS
            // ==========================================================================
            class PurchaseDraftManager {
                constructor(formId, storageKey) {
                    this.form = document.getElementById(formId);
                    this.storageKey = storageKey;
                    this.ignoreFields = ['password', 'file', 'hidden'];

                    if (this.form) {
                        this.setupEventListeners();
                    }
                }

                setupEventListeners() {
                    // Escuchar cambios en inputs del formulario
                    this.form.addEventListener('input', (e) => {
                        this.saveDraft();
                    });

                    this.form.addEventListener('change', (e) => {
                        this.saveDraft();
                    });
                }

                saveDraft() {
                    // 1. Guardar campos del formulario
                    const formData = {};
                    const elements = this.form.elements;

                    for (let i = 0; i < elements.length; i++) {
                        const el = elements[i];
                        // Ignorar campos de "Agregar item" para no ensuciar el draft con valores temporales
                        if (!el.name ||
                            this.ignoreFields.includes(el.type) ||
                            el.id.startsWith('item_')) continue;

                        if (el.type === 'checkbox' || el.type === 'radio') {
                            if (el.checked) {
                                formData[el.name] = el.value;
                            }
                        } else {
                            formData[el.name] = el.value;
                        }
                    }

                    // 2. Guardar lista de items (global purchaseItems)
                    // Nota: purchaseItems es una variable global definida en este script

                    const draftData = {
                        form: formData,
                        items: window.purchaseItems || []
                    };

                    localStorage.setItem(this.storageKey, JSON.stringify(draftData));
                }

                hasDraft() {
                    return localStorage.getItem(this.storageKey) !== null;
                }

                restoreDraft() {
                    const savedData = localStorage.getItem(this.storageKey);
                    if (!savedData) return false;

                    try {
                        const data = JSON.parse(savedData);

                        // 1. Restaurar formulario
                        const formData = data.form;
                        for (const name in formData) {
                            if (this.form.elements[name]) {
                                const el = this.form.elements[name];
                                if (el instanceof RadioNodeList) {
                                    for (let i = 0; i < el.length; i++) {
                                        if (el[i].value === formData[name]) el[i].checked = true;
                                    }
                                } else if (el.type === 'checkbox') {
                                    el.checked = true;
                                } else {
                                    el.value = formData[name];
                                }
                            }
                        }

                        // 2. Restaurar items
                        if (data.items && Array.isArray(data.items)) {
                            window.purchaseItems = data.items;
                            // Llamar renderItems global
                            if (typeof window.renderItems === 'function') {
                                window.renderItems();
                            } else {
                                // Fallback si renderItems no está expuesto (aunque debería estarlo por ser función global o de clase)
                                // En este script, renderItems es una función interna, necesitamos exponerla o moverla.
                                // La modificaremos más abajo para asegurar acceso.
                            }
                        }

                        return true;

                    } catch (e) {
                        console.error('Error al restaurar borrador de compra:', e);
                        return false;
                    }
                }

                clearDraft() {
                    localStorage.removeItem(this.storageKey);
                }

                showDraftNotification() {
                    if (!document.getElementById('draftToast')) {
                        const toastContainer = document.createElement('div');
                        toastContainer.className = 'position-fixed bottom-0 end-0 p-3';
                        toastContainer.style.zIndex = '1100';
                        toastContainer.innerHTML = `
                            <div id="draftToast" class="toast align-items-center text-white bg-primary border-0" role="alert" aria-live="assertive" aria-atomic="true">
                                <div class="d-flex">
                                    <div class="toast-body">
                                        <i class="bi bi-save me-2"></i>
                                        Borrador de compra recuperado
                                    </div>
                                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                                </div>
                            </div>
                        `;
                        document.body.appendChild(toastContainer);
                    }

                    const toast = new bootstrap.Toast(document.getElementById('draftToast'));
                    toast.show();
                }
            }

            // ==========================================================================
            // FUNCIONES DE GASTOS
            // ==========================================================================

            window.loadGastos = function () {
                const monthEl = document.getElementById('gastosMonth');
                const month = monthEl ? monthEl.value : '<?php echo date('Y-m'); ?>';
                const start = month + '-01';
                const parts = month.split('-');
                const lastDay = new Date(parseInt(parts[0]), parseInt(parts[1]), 0).getDate();
                const end = month + '-' + String(lastDay).padStart(2, '0');

                const tbody = document.querySelector('#gastosTable tbody');
                if (!tbody) return;
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-arrow-clockwise spin me-2"></i>Cargando gastos...</td></tr>';

                fetch('get_gastos.php?fecha_inicio=' + encodeURIComponent(start) + '&fecha_fin=' + encodeURIComponent(end))
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (!data.success) {
                            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">' + (data.message || 'Error al cargar') + '</td></tr>';
                            return;
                        }
                        renderGastosTable(data.rows || []);
                    })
                    .catch(function(err) {
                        console.error('Error loading gastos:', err);
                        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Error: ' + (err.message || 'Error de conexión') + '</td></tr>';
                    });
            };

            window.escapeHtml = function (text) {
                if (!text) return '';
                var div = document.createElement('div');
                div.appendChild(document.createTextNode(text));
                return div.innerHTML;
            };

            function renderGastosTable(rows) {
                const tbody = document.querySelector('#gastosTable tbody');
                const footer = document.getElementById('gastosTotalFooter');
                if (!tbody) return;

                if (rows.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>No hay gastos registrados en este mes</td></tr>';
                    if (footer) footer.textContent = 'Q0.00';
                    return;
                }

                let html = '';
                let totalGeneral = 0;
                rows.forEach(function(g) {
                    totalGeneral += g.total;
                    html += '<tr>' +
                        '<td>' + g.fecha + '</td>' +
                        '<td>' + escapeHtml(g.descripcion) + '</td>' +
                        '<td class="text-center">' + g.cantidad + '</td>' +
                        '<td class="text-end">Q' + Number(g.subtotal).toFixed(2) + '</td>' +
                        '<td class="text-end fw-bold text-danger">Q' + Number(g.total).toFixed(2) + '</td>' +
                        '<td>' + escapeHtml(g.registrado_por || '—') + '</td>' +
                        '<td class="text-center">' +
                        '<button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="deleteGasto(' + g.id + ')" title="Eliminar gasto">' +
                        '<i class="bi bi-trash"></i></button></td>' +
                        '</tr>';
                });
                tbody.innerHTML = html;
                if (footer) footer.textContent = 'Q' + totalGeneral.toFixed(2);
            }

            window.showNewGastoModal = function () {
                document.getElementById('gasto_descripcion').value = '';
                document.getElementById('gasto_fecha').value = '<?php echo date('Y-m-d'); ?>';
                document.getElementById('gasto_cantidad').value = '1';
                document.getElementById('gasto_subtotal').value = '';
                document.getElementById('gasto_total').value = '0.00';
                document.getElementById('newGastoModal').classList.add('active');
                document.getElementById('gasto_descripcion').focus();
            };

            window.calcularGastoTotal = function () {
                const cant = parseFloat(document.getElementById('gasto_cantidad').value) || 0;
                const sub = parseFloat(document.getElementById('gasto_subtotal').value) || 0;
                document.getElementById('gasto_total').value = (cant * sub).toFixed(2);
            };

            window.saveGasto = function () {
                const descripcion = document.getElementById('gasto_descripcion').value.trim();
                const fecha = document.getElementById('gasto_fecha').value;
                const cantidad = parseInt(document.getElementById('gasto_cantidad').value) || 1;
                const subtotal = parseFloat(document.getElementById('gasto_subtotal').value) || 0;
                const total = parseFloat(document.getElementById('gasto_total').value) || (cantidad * subtotal);

                if (!descripcion) {
                    Swal.fire({ title: 'Descripción requerida', text: 'Por favor ingrese una descripción del gasto', icon: 'warning', confirmButtonText: 'Entendido' });
                    return;
                }
                if (subtotal <= 0) {
                    Swal.fire({ title: 'Subtotal inválido', text: 'El subtotal debe ser mayor a 0', icon: 'warning', confirmButtonText: 'Entendido' });
                    return;
                }

                const btn = document.getElementById('saveGastoBtn');
                if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-arrow-clockwise spin me-2"></i>Guardando...'; }

                const payload = { descripcion: descripcion, cantidad: cantidad, subtotal: subtotal, total: total, fecha: fecha };

                fetch('save_gasto.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        document.getElementById('newGastoModal').classList.remove('active');
                        Swal.fire({ title: 'Gasto Registrado', text: 'El gasto se ha registrado correctamente', icon: 'success', timer: 1500, showConfirmButton: false });
                        loadGastos();
                    } else {
                        Swal.fire({ title: 'Error', text: data.message || 'Error al guardar el gasto', icon: 'error', confirmButtonText: 'Entendido' });
                    }
                })
                .catch(function(err) {
                    console.error('Error:', err);
                    Swal.fire({ title: 'Error de conexión', text: 'Ocurrió un error al procesar la solicitud', icon: 'error', confirmButtonText: 'Entendido' });
                })
                .finally(function() {
                    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg me-2"></i>Guardar Gasto'; }
                });
            };

            window.deleteGasto = function (id) {
                Swal.fire({
                    title: 'Eliminar gasto',
                    html: '<p class="text-muted mb-3">Esta acción moverá el gasto a la papelera. Los gastos eliminados se borran definitivamente al final de cada mes.</p>',
                    input: 'textarea',
                    inputLabel: 'Motivo de eliminación',
                    inputPlaceholder: 'Describa por qué elimina este gasto...',
                    inputAttributes: { required: true },
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    confirmButtonText: '<i class="bi bi-trash me-1"></i>Eliminar',
                    cancelButtonText: 'Cancelar',
                    preConfirm: function(motivo) {
                        if (!motivo || !motivo.trim()) {
                            Swal.showValidationMessage('Debe ingresar un motivo');
                            return false;
                        }
                        return motivo.trim();
                    }
                }).then(function(result) {
                    if (!result.isConfirmed || !result.value) return;
                    const motivo = result.value;
                    fetch('delete_gasto.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id, motivo: motivo })
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            Swal.fire({ title: 'Eliminado', text: 'El gasto se movió a la papelera', icon: 'success', timer: 1500, showConfirmButton: false });
                            loadGastos();
                        } else {
                            Swal.fire({ title: 'Error', text: data.message || 'Error al eliminar', icon: 'error', confirmButtonText: 'Entendido' });
                        }
                    })
                    .catch(function(err) {
                        console.error('Error:', err);
                        Swal.fire({ title: 'Error de conexión', icon: 'error', confirmButtonText: 'Entendido' });
                    });
                });
            };

            // ==========================================================================
            // FUNCIONES DE GASTOS ELIMINADOS
            // ==========================================================================

            window.showDeletedGastos = function () {
                document.getElementById('deletedGastosModal').classList.add('active');
                loadDeletedGastos();
            };

            window.loadDeletedGastos = function () {
                const tbody = document.querySelector('#deletedGastosTable tbody');
                if (!tbody) return;
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-arrow-clockwise spin me-2"></i>Cargando...</td></tr>';

                const now = new Date();
                const start = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-01';
                const end = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate()).padStart(2, '0');

                fetch('get_gastos_eliminados.php?fecha_inicio=' + encodeURIComponent(start) + '&fecha_fin=' + encodeURIComponent(end))
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (!data.success) {
                            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">' + (data.message || 'Error al cargar') + '</td></tr>';
                            return;
                        }
                        renderDeletedGastosTable(data.rows || []);
                    })
                    .catch(function(err) {
                        console.error('Error loading deleted gastos:', err);
                        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Error: ' + (err.message || 'Error de conexión') + '</td></tr>';
                    });
            };

            function renderDeletedGastosTable(rows) {
                const tbody = document.querySelector('#deletedGastosTable tbody');
                const footer = document.getElementById('deletedGastosTotalFooter');
                if (!tbody) return;

                if (rows.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-check-circle me-2 text-success"></i>No hay gastos eliminados este mes</td></tr>';
                    if (footer) footer.textContent = 'Q0.00';
                    return;
                }

                let html = '';
                let totalGeneral = 0;
                rows.forEach(function(g) {
                    totalGeneral += g.total;
                    html += '<tr>' +
                        '<td>' + g.fecha_eliminacion + '</td>' +
                        '<td>' + escapeHtml(g.descripcion) + '</td>' +
                        '<td class="text-center">' + g.cantidad + '</td>' +
                        '<td class="text-end fw-bold text-danger">Q' + Number(g.total).toFixed(2) + '</td>' +
                        '<td>' + escapeHtml(g.eliminado_por_nombre || '—') + '</td>' +
                        '<td><span class="text-muted fst-italic small">' + escapeHtml(g.motivo_eliminacion) + '</span></td>' +
                        '</tr>';
                });
                tbody.innerHTML = html;
                if (footer) footer.textContent = 'Q' + totalGeneral.toFixed(2);
            }

            // ==========================================================================
            // INICIALIZACIÓN DE LA APLICACIÓN
            // ==========================================================================
            document.addEventListener('DOMContentLoaded', () => {
                // Inicializar componentes
                const themeManager = new ThemeManager();
                const tabManager = new TabManager();
                const purchasesManager = new PurchasesManager();

                // Inicializar gestor de borradores (hacerlo accesible globalmente)
                window.purchaseDraftManager = new PurchaseDraftManager('purchaseForm', 'purchases_new_draft');

                // Exponer APIs necesarias globalmente
                window.purchasesApp = {
                    theme: themeManager,
                    tabs: tabManager,
                    purchases: purchasesManager
                };

                // Log de inicialización
                console.log('Módulo de Compras CMS v4.0 inicializado correctamente');
                console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
                console.log('Rol: <?php echo htmlspecialchars($user_type); ?>');
                console.log('Tema: ' + themeManager.theme);
            });

            // ==========================================================================
            // MANEJO DE ERRORES GLOBALES
            // ==========================================================================
            window.addEventListener('error', (event) => {
                console.error('Error en módulo de compras:', event.error);
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

        // ==========================================================================
        // CONTABILIDAD DE COMPRAS
        // ==========================================================================
        let contabilidadPeriodo = 'current_month';

        function formatQ(n) {
            return 'Q ' + Number(n || 0).toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function escapeHtmlAccounting(s) {
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(String(s == null ? '' : s)));
            return div.innerHTML;
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[data-accounting-period]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('[data-accounting-period]').forEach(function(b) { b.classList.remove('active'); });
                    btn.classList.add('active');
                    contabilidadPeriodo = btn.getAttribute('data-accounting-period');
                    var specificContainer = document.getElementById('specificMonthContainer');
                    if (specificContainer) {
                        specificContainer.style.display = (contabilidadPeriodo === 'specific_month') ? '' : 'none';
                    }
                    loadContabilidad();
                });
            });
            var monthInput = document.getElementById('specificMonthInput');
            if (monthInput) {
                monthInput.addEventListener('change', function() {
                    if (contabilidadPeriodo === 'specific_month') loadContabilidad();
                });
            }
        });

        window.loadContabilidad = function() {
            var loading = document.getElementById('accountingLoading');
            var content = document.getElementById('accountingContent');
            if (loading) loading.style.display = '';
            if (content) content.style.display = 'none';

            var params = 'periodo=' + encodeURIComponent(contabilidadPeriodo);
            if (contabilidadPeriodo === 'specific_month') {
                var mi = document.getElementById('specificMonthInput');
                if (mi) params += '&mes=' + encodeURIComponent(mi.value || '');
            }

            fetch('api/api_get_accounting.php?' + params)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (loading) loading.style.display = 'none';
                    if (!data || !data.success) {
                        if (content) {
                            content.style.display = '';
                            content.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>' +
                                (data && data.error ? data.error : 'Error al cargar contabilidad') + '</div>';
                        }
                        return;
                    }
                    renderContabilidad(data);
                })
                .catch(function(err) {
                    if (loading) loading.style.display = 'none';
                    if (content) {
                        content.style.display = '';
                        content.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>Error de red: ' + err.message + '</div>';
                    }
                });
        };

        function renderContabilidad(data) {
            var label = document.getElementById('accountingPeriodLabel');
            if (label) label.textContent = data.label;

            // KPIs
            var k = data.kpis;
            document.getElementById('kpiCompradoTotal').textContent = formatQ(k.comprado.total);
            document.getElementById('kpiCompradoNum').textContent = k.comprado.num;
            document.getElementById('kpiPagadoTotal').textContent = formatQ(k.pagado.total);
            document.getElementById('kpiPagadoNum').textContent = k.pagado.num;
            document.getElementById('kpiPendienteSaldo').textContent = formatQ(k.pendiente.saldo);
            document.getElementById('kpiPendienteNum').textContent = k.pendiente.num;
            document.getElementById('kpiProveedores').textContent = k.proveedores;

            // Por proveedor
            var provBody = document.getElementById('accountingProveedorBody');
            if (data.por_proveedor.length === 0) {
                provBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4"><em>No hay compras en este período</em></td></tr>';
            } else {
                var html = '';
                var tCompras = 0, tComprado = 0, tPagado = 0, tSaldo = 0;
                data.por_proveedor.forEach(function(p) {
                    var pct = p.pct_pagado;
                    var cls = pct >= 75 ? 'high' : (pct >= 50 ? 'medium' : 'low');
                    html += '<tr>' +
                        '<td><strong>' + escapeHtmlAccounting(p.proveedor) + '</strong></td>' +
                        '<td class="text-center">' + p.num_compras + '</td>' +
                        '<td class="text-end">' + formatQ(p.total_comprado) + '</td>' +
                        '<td class="text-end text-success">' + formatQ(p.total_pagado) + '</td>' +
                        '<td class="text-end ' + (p.saldo > 0 ? 'text-danger' : 'text-muted') + '">' + formatQ(p.saldo) + '</td>' +
                        '<td><div class="acct-progress">' +
                            '<div class="acct-progress__bar"><div class="acct-progress__fill acct-progress__fill--' + cls + '" style="width:' + pct + '%"></div></div>' +
                            '<div class="acct-progress__pct">' + pct + '%</div>' +
                        '</div></td>' +
                    '</tr>';
                    tCompras += p.num_compras;
                    tComprado += p.total_comprado;
                    tPagado += p.total_pagado;
                    tSaldo += p.saldo;
                });
                var tPct = tComprado > 0 ? Math.round((tPagado / tComprado) * 1000) / 10 : 0;
                var tCls = tPct >= 75 ? 'high' : (tPct >= 50 ? 'medium' : 'low');
                html += '<tr>' +
                    '<td><strong>TOTAL</strong></td>' +
                    '<td class="text-center"><strong>' + tCompras + '</strong></td>' +
                    '<td class="text-end"><strong>' + formatQ(tComprado) + '</strong></td>' +
                    '<td class="text-end text-success"><strong>' + formatQ(tPagado) + '</strong></td>' +
                    '<td class="text-end ' + (tSaldo > 0 ? 'text-danger' : 'text-muted') + '"><strong>' + formatQ(tSaldo) + '</strong></td>' +
                    '<td><div class="acct-progress">' +
                        '<div class="acct-progress__bar"><div class="acct-progress__fill acct-progress__fill--' + tCls + '" style="width:' + tPct + '%"></div></div>' +
                        '<div class="acct-progress__pct"><strong>' + tPct + '%</strong></div>' +
                    '</div></td>' +
                '</tr>';
                provBody.innerHTML = html;
            }

            // Resumen mensual
            var menBody = document.getElementById('accountingMensualBody');
            if (!data.resumen_mensual || data.resumen_mensual.length === 0) {
                menBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4"><em>Sin datos</em></td></tr>';
            } else {
                var mhtml = '';
                data.resumen_mensual.forEach(function(m) {
                    var diff = m.total_comprado - m.total_pagado;
                    var mesLabel = m.mes;
                    var parts = m.mes.split('-');
                    if (parts.length === 2) {
                        var meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
                        mesLabel = meses[parseInt(parts[1], 10) - 1] + ' ' + parts[0];
                    }
                    var badge = diff <= 0
                        ? '<span class="acct-badge acct-badge--success">Pagado</span>'
                        : (m.total_pagado === 0
                            ? '<span class="acct-badge acct-badge--danger">Sin pagos</span>'
                            : '<span class="acct-badge acct-badge--warning">' + formatQ(diff) + '</span>');
                    mhtml += '<tr>' +
                        '<td><strong>' + escapeHtmlAccounting(mesLabel) + '</strong></td>' +
                        '<td class="text-center">' + m.num_compras + '</td>' +
                        '<td class="text-end">' + formatQ(m.total_comprado) + '</td>' +
                        '<td class="text-center">' + m.num_pagos + '</td>' +
                        '<td class="text-end text-success">' + formatQ(m.total_pagado) + '</td>' +
                        '<td class="text-end">' + badge + '</td>' +
                    '</tr>';
                });
                menBody.innerHTML = mhtml;
            }

            var content = document.getElementById('accountingContent');
            if (content) content.style.display = '';
        }

    </script>

    <!-- Inyectar script de mantenimiento de sesión activo (Global) -->
    <?php output_keep_alive_script(); ?>
    <?php flash_toast(); ?>
</body>

</html>