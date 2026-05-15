<?php
// purchases/index.php - Módulo de Compras del Centro Médico RS
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

    // Verificar permisos (solo admin puede acceder a compras)
    if ($user_type !== 'admin') {
        header("Location: ../dashboard/index.php");
        exit;
    }

    // ============ ESTADÍSTICAS DE COMPRAS ============
    $today = date('Y-m-d');
    $current_month = date('Y-m');

    // 1. Compras del mes actual
    $stmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total FROM purchase_headers WHERE DATE_FORMAT(purchase_date, '%Y-%m') = ?");
    $stmt->execute([$current_month]);
    $month_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $month_purchases = $month_stats['count'] ?? 0;
    $month_total = $month_stats['total'] ?? 0;

    // 2. Compras pendientes de pago
    $stmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount - COALESCE(paid_amount, 0)), 0) as balance FROM purchase_headers WHERE (total_amount - COALESCE(paid_amount, 0)) > 0");
    $stmt->execute();
    $pending_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $pending_count = $pending_stats['count'] ?? 0;
    $total_balance = $pending_stats['balance'] ?? 0;

    // 3. Compras del día
    $stmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total FROM purchase_headers WHERE DATE(purchase_date) = ?");
    $stmt->execute([$today]);
    $today_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $today_purchases = $today_stats['count'] ?? 0;
    $today_total = $today_stats['total'] ?? 0;

    // 4. Proveedores con más compras
    $stmt = $conn->prepare("SELECT provider_name, COUNT(*) as count, SUM(total_amount) as total FROM purchase_headers GROUP BY provider_name ORDER BY total DESC LIMIT 5");
    $stmt->execute();
    $top_providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Últimas compras
    $stmt = $conn->prepare("SELECT ph.*, 
                           (ph.total_amount - COALESCE(ph.paid_amount, 0)) as balance,
                           (SELECT COUNT(*) FROM purchase_items WHERE purchase_header_id = ph.id) as items_count
                           FROM purchase_headers ph 
                           ORDER BY ph.purchase_date DESC, ph.created_at DESC 
                           LIMIT 10");
    $stmt->execute();
    $recent_purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Compras por confirmar (en inventario como pendientes)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventario WHERE estado = 'Pendiente'");
    $stmt->execute();
    $pending_inventory = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 7. Compras antiguas (de la tabla anterior)
    try {
        $stmt_old = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_compra), 0) as total FROM compras");
        $stmt_old->execute();
        $old_stats = $stmt_old->fetch(PDO::FETCH_ASSOC);
        $old_purchases = $old_stats['count'] ?? 0;
        $old_total = $old_stats['total'] ?? 0;
    } catch (Exception $e) {
        $old_purchases = 0;
        $old_total = 0;
    }

    // Título de la página
    $page_title = "Compras - Centro Médico RS";

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
        content="Módulo de Compras - Centro Médico RS - Gestión de compras de medicamentos e insumos">
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
            </div>

            <!-- Navegación por pestañas -->
            <div class="tabs-navigation mb-4">
                <button class="tab-btn active" data-tab="recent-purchases">
                    <i class="bi bi-cart-check me-2"></i>Compras Recientes
                </button>
                <button class="tab-btn" data-tab="pending-payments">
                    <i class="bi bi-clock-history me-2"></i>Pagos Pendientes
                </button>
                <button class="tab-btn" data-tab="old-purchases">
                    <i class="bi bi-archive me-2"></i>Compras Antiguas
                </button>
                <button class="tab-btn" data-tab="top-providers">
                    <i class="bi bi-building me-2"></i>Proveedores
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
                            <div class="search-box">
                                <i class="bi bi-search search-icon"></i>
                                <input type="text" id="searchRecent" placeholder="Buscar compra...">
                            </div>
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
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="bi bi-cart-x"></i>
                            </div>
                            <h4 class="text-muted mb-2">No hay compras registradas</h4>
                            <p class="text-muted mb-3">Comienza registrando tu primera compra</p>
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
                               ORDER BY ph.purchase_date ASC");
                        $stmt_pending->execute();
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

            <!-- Pestaña: Compras Antiguas -->
            <div class="tab-content" id="old-purchases-tab">
                <section class="appointments-section animate-in delay-3">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="bi bi-archive section-title-icon"></i>
                            Historial de Compras Antiguas
                        </h3>
                        <div class="search-box">
                            <i class="bi bi-search search-icon"></i>
                            <input type="text" id="searchOld" placeholder="Buscar por producto...">
                        </div>
                    </div>

                    <?php
                    try {
                        $stmt_old = $conn->prepare("SELECT * FROM compras ORDER BY fecha_compra DESC LIMIT 50");
                        $stmt_old->execute();
                        $old_purchases_list = $stmt_old->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Exception $e) {
                        $old_purchases_list = [];
                    }
                    ?>

                    <?php if (count($old_purchases_list) > 0): ?>
                        <div class="table-responsive">
                            <table class="appointments-table" id="tableOld">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Producto</th>
                                        <th>Presentación</th>
                                        <th>Casa Farm.</th>
                                        <th>Cant.</th>
                                        <th>Precio U.</th>
                                        <th>Total</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($old_purchases_list as $row): ?>
                                        <?php
                                        $statusClass = 'secondary';
                                        if ($row['estado_compra'] == 'Completo')
                                            $statusClass = 'success';
                                        if ($row['estado_compra'] == 'Pendiente')
                                            $statusClass = 'warning';
                                        if ($row['estado_compra'] == 'Abonado')
                                            $statusClass = 'info';
                                        ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($row['fecha_compra'])); ?></td>
                                            <td class="fw-bold"><?php echo htmlspecialchars($row['nombre_compra']); ?></td>
                                            <td><?php echo htmlspecialchars($row['presentacion_compra']); ?></td>
                                            <td><?php echo htmlspecialchars($row['casa_compra']); ?></td>
                                            <td class="text-center"><?php echo $row['cantidad_compra']; ?></td>
                                            <td>Q<?php echo number_format($row['precio_unidad'], 2); ?></td>
                                            <td class="fw-bold text-primary">
                                                Q<?php echo number_format($row['total_compra'], 2); ?></td>
                                            <td><span
                                                    class="badge badge-<?php echo $statusClass; ?>"><?php echo $row['estado_compra']; ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="bi bi-archive"></i>
                            </div>
                            <h4 class="text-muted mb-2">No hay registros antiguos</h4>
                            <p class="text-muted mb-3">Todos los registros están en el sistema actual</p>
                        </div>
                    <?php endif; ?>
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
        </main>
    </div>

    <!-- Modal para nueva compra -->
    <div class="modal fade" id="newPurchaseModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-bag-plus text-primary"></i>
                        Registrar Nueva Compra
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="purchaseForm">
                        <!-- Header Info -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label class="form-label">Fecha de Compra</label>
                                <input type="date" class="form-control" name="purchase_date" id="purchase_date"
                                    required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tipo de Documento</label>
                                <select class="form-select" name="document_type" id="document_type" required>
                                    <option value="Factura">Factura</option>
                                    <option value="Nota de Envío">Nota de Envío</option>
                                    <option value="Consumidor Final">Consumidor Final</option>
                                </select>
                            </div>
                            <div class="col-md-3">
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
                <div class="modal-footer">
                    <button type="button" class="action-btn secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="action-btn" id="savePurchaseBtn" onclick="savePurchase()">
                        <i class="bi bi-check-lg me-2"></i>Guardar Compra
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para pagos -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-cash-coin text-primary"></i>
                        Gestionar Pagos / Abonos
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
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

                                <button type="button" class="action-btn w-100" id="submitPaymentBtn"
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
    </div>

    <!-- Modal para detalles -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalles de Compra</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailsModalBody">
                    <div class="text-center">
                        <div class="spinner-border text-primary"></div>
                    </div>
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
        // Módulo de Compras Reingenierizado - Centro Médico RS

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

                    // Búsqueda en tabla de antiguas
                    const searchOld = document.getElementById('searchOld');
                    if (searchOld) {
                        searchOld.addEventListener('input', function () {
                            const searchTerm = this.value.toLowerCase();
                            const rows = document.querySelectorAll('#tableOld tbody tr');

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
            let purchaseItems = [];

            // Mostrar modal de nueva compra
            window.showNewPurchaseModal = function () {
                const modal = new bootstrap.Modal(document.getElementById('newPurchaseModal'));
                const hasDraft = window.purchaseDraftManager && window.purchaseDraftManager.hasDraft();

                if (hasDraft) {
                    // Si hay borrador, restaurarlo
                    window.purchaseDraftManager.restoreDraft();
                    window.purchaseDraftManager.showDraftNotification();
                    modal.show();
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
                purchaseItems = [];
                renderItems();

                // Mostrar modal
                modal.show();
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
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (window.purchaseDraftManager) {
                                window.purchaseDraftManager.clearDraft();
                            }

                            Swal.fire({
                                title: '¡Compra Registrada!',
                                text: 'La compra se ha registrado correctamente. Los productos se han agregado al inventario como pendientes.',
                                icon: 'success',
                                confirmButtonText: 'Aceptar'
                            }).then(() => {
                                // Limpiar items y cerrar modal
                                purchaseItems = [];
                                renderItems();
                                const modal = bootstrap.Modal.getInstance(document.getElementById('newPurchaseModal'));
                                modal.hide();
                                location.reload();
                            });
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
                            text: 'Ocurrió un error al procesar la solicitud',
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
                const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
                modal.show();

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
                                    </tr>
                                </thead>
                                <tbody>
                    `;

                            data.items.forEach(item => {
                                html += `
                            <tr>
                                <td>${item.product_name}</td>
                                <td>${item.presentation || 'N/A'}</td>
                                <td>${item.molecule || 'N/A'}</td>
                                <td class="text-center">${item.quantity}</td>
                                <td class="text-end">Q${parseFloat(item.unit_cost).toFixed(2)}</td>
                                <td class="text-end">Q${parseFloat(item.sale_price || 0).toFixed(2)}</td>
                                <td class="text-end">Q${parseFloat(item.subtotal).toFixed(2)}</td>
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
                const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
                modal.show();
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
                            <div class="badge bg-success mb-1">Pagado: Q${paid.toFixed(2)}</div><br>
                            <div class="badge ${balance > 0 ? 'bg-danger' : 'bg-success'}">Saldo: Q${balance.toFixed(2)}</div>
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
                                const modal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
                                modal.hide();
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

    </script>

    <!-- Inyectar script de mantenimiento de sesión activo (Global) -->
    <?php output_keep_alive_script(); ?>
</body>

</html>