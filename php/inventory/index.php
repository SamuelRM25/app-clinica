<?php
// inventory/index.php - Módulo de Inventario Reingenierizado
// Centro Médico RS - Sistema de Gestión Médica
// Versión: 4.0 - Mismo diseño que Dashboard Principal

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

check_module_access('inventory');



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

    // Permiso de gestión: Usuario jrivas_farmacia (ID 6) y administradores
    // Los demás usuarios solo tienen permiso de lectura
    $can_manage_inventory = ($user_type === 'admin' || in_array($user_id, [1, 6])); // Admin or specific users

    // ============ ESTADÍSTICAS DEL INVENTARIO ============

    // 1. Total de items en inventario
    $stmt = $conn->query("SELECT COUNT(*) as count FROM inventario");
    $total_items = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 2. Items agotados (stock = 0)
    $stmt = $conn->query("SELECT COUNT(*) as count FROM inventario WHERE cantidad_med = 0");
    $out_of_stock = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 3. Items con stock bajo (< 10 unidades)
    $stmt = $conn->query("SELECT COUNT(*) as count FROM inventario WHERE cantidad_med > 0 AND cantidad_med <= 10");
    $low_stock = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 4. Items próximos a caducar (6 meses)
    $today = date('Y-m-d');
    $next_month = date('Y-m-d', strtotime('+6 months'));
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventario WHERE fecha_vencimiento BETWEEN ? AND ? AND cantidad_med > 0");
    $stmt->execute([$today, $next_month]);
    $expiring_soon = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 5. Items vencidos
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventario WHERE fecha_vencimiento < ? AND cantidad_med > 0");
    $stmt->execute([$today]);
    $expired = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 6. Items pendientes de recepción
    $stmt = $conn->query("SELECT COUNT(*) as count FROM inventario WHERE estado = 'Pendiente'");
    $pending_receipt = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 7. Valor total del inventario en base a precio de compra y precio de venta
    // Se utiliza COALESCE con purchase_items para los medicamentos que tienen precio_compra en 0
    $stmt = $conn->query("
        SELECT 
            SUM(i.cantidad_med * COALESCE(NULLIF(i.precio_compra, 0), p.unit_cost, 0)) as total_valor_compra, 
            SUM(i.cantidad_med * i.precio_venta) as total_valor_venta 
        FROM inventario i
        LEFT JOIN purchase_items p ON i.id_purchase_item = p.id
        WHERE i.cantidad_med > 0
    ");
    $result_val = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_value = $result_val['total_valor_compra'] ?? 0;
    $total_value_venta = $result_val['total_valor_venta'] ?? 0;

    $total_appointments = 0;
    $active_hospitalizations = 0;
    $pending_purchases = $pending_receipt;

    // ============ INVENTARIO COMPLETO ============

    // Obtener todos los medicamentos para la tabla
    $stmt = $conn->query("SELECT * FROM inventario ORDER BY fecha_vencimiento ASC");
    $inventory_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Título de la página
    $page_title = "Inventario - Centro Médico RS";

} catch (Exception $e) {
    // Manejo de errores
    error_log("Error en inventario: " . $e->getMessage());
    die("Error al cargar el inventario. Por favor, contacte al administrador.");
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Módulo de Inventario - Centro Médico RS - Sistema de gestión médica">
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

    <!-- Seguridad y Protección de Código -->
    <script src="../../assets/js/security.js"></script>

    <!-- SweetAlert2 -->
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
            <!-- Notificación de compras pendientes -->
            <?php if ($pending_purchases > 0 && $user_type === 'user'): ?>
                    <div class="alert-card mb-4 animate-in delay-1">
                        <div class="alert-header">
                            <div class="alert-icon warning">
                                <i class="bi bi-box-seam"></i>
                            </div>
                            <h3 class="alert-title">Recepción Pendiente</h3>
                        </div>
                        <p class="text-muted mb-0">
                            Hay <strong><?php echo $pending_receipt; ?></strong> productos pendientes de recepción en
                            inventario.
                        </p>
                    </div>
            <?php endif; ?>

            <!-- Bienvenida personalizada -->
            <div class="stat-card mb-4 animate-in">
                <div class="stat-header">
                    <div>
                        <h2 id="greeting" class="stat-value" style="font-size: 1.75rem; margin-bottom: 0.5rem;">
                            <span id="greeting-text">Gestión de Inventario</span>
                        </h2>
                        <p class="text-muted mb-0">
                            <i class="bi bi-box-seam me-1"></i> Control y administración de medicamentos e insumos
                            médicos
                            <span class="mx-2">•</span>
                            <i class="bi bi-calendar-check me-1"></i> <?php echo date('d/m/Y'); ?>
                            <span class="mx-2">•</span>
                            <i class="bi bi-person me-1"></i> <?php echo htmlspecialchars($user_name); ?>
                        </p>
                    </div>
                    <div class="d-none d-md-block">
                        <i class="bi bi-box-seam text-primary" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>

            <!-- Estadísticas principales -->
            <?php if ($user_type === 'admin'): ?>
                    <div class="stats-grid">
                        <!-- Valor Total en Inventario -->
                        <div class="stat-card animate-in delay-0" id="inventoryValueCard" style="cursor: pointer;"
                            onclick="toggleInventoryValue()">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-title" id="inventoryValueTitle">Valor en Inventario (Compra)</div>
                                    <div class="stat-value" id="inventoryValueAmount">Q
                                        <?php echo number_format($total_value, 2); ?>
                                    </div>
                                </div>
                                <div class="stat-icon success">
                                    <i class="bi bi-cash-stack"></i>
                                </div>
                            </div>
                            <div class="stat-change positive">
                                <i class="bi bi-info-circle"></i>
                                <span id="inventoryValueSubtitle">Haz clic para ver el precio de venta</span>
                            </div>
                        </div>

                        <!-- Total de items -->
                        <div class="stat-card animate-in delay-1">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-title">Total de Items</div>
                                    <div class="stat-value"><?php echo $total_items; ?></div>
                                </div>
                                <div class="stat-icon primary">
                                    <i class="bi bi-box-seam"></i>
                                </div>
                            </div>
                            <div class="stat-change positive">
                                <i class="bi bi-arrow-up-right"></i>
                                <span>En inventario</span>
                            </div>
                        </div>

                        <!-- Agotados -->
                        <div class="stat-card animate-in delay-2"
                            onclick="document.querySelector('[data-filter=\'out\']').click(); document.getElementById('searchInput').scrollIntoView({behavior: 'smooth'})"
                            style="cursor: pointer;">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-title">Agotados</div>
                                    <div class="stat-value"><?php echo $out_of_stock; ?></div>
                                </div>
                                <div class="stat-icon danger">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </div>
                            </div>
                            <div class="stat-change positive">
                                <i class="bi bi-exclamation-triangle"></i>
                                <span>Sin stock disponible</span>
                            </div>
                        </div>

                        <!-- Stock bajo -->
                        <div class="stat-card animate-in delay-3">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-title">Stock Bajo</div>
                                    <div class="stat-value"><?php echo $low_stock; ?></div>
                                </div>
                                <div class="stat-icon warning">
                                    <i class="bi bi-exclamation-circle"></i>
                                </div>
                            </div>
                            <div class="stat-change positive">
                                <i class="bi bi-exclamation-circle"></i>
                                <span>Menos de 10 unidades</span>
                            </div>
                        </div>

                        <!-- Por vencer -->
                        <div class="stat-card animate-in delay-4">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-title">Por Vencer</div>
                                    <div class="stat-value"><?php echo $expiring_soon; ?></div>
                                </div>
                                <div class="stat-icon warning">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                            </div>
                            <div class="stat-change positive">
                                <i class="bi bi-clock-history"></i>
                                <span>Próximos 30 días</span>
                            </div>
                        </div>
                    </div>
            <?php endif; ?>

            <!-- Barra de búsqueda y acciones -->
            <div class="appointments-section animate-in delay-1">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-search section-title-icon"></i>
                        Buscar y Filtrar
                    </h3>
                    <div class="action-buttons">
                        <a href="export_full_inventory.php" class="action-btn"
                            style="background: var(--color-success);">
                            <i class="bi bi-file-earmark-excel"></i>
                            Excel
                        </a>
                        <a href="export_inventory_pdf.php" target="_blank" class="action-btn"
                            style="background: var(--color-danger);">
                            <i class="bi bi-file-earmark-pdf"></i>
                            PDF
                        </a>
                        <a href="generate_report.php" class="action-btn" style="background: var(--color-secondary);">
                            <i class="bi bi-file-earmark-text"></i>
                            Resumen CSV
                        </a>
                        <?php if ($user_type === 'admin'): ?>
                                <a href="insumos.php" class="action-btn" style="background: var(--color-info);">
                                    <i class="bi bi-box-fill"></i>
                                    Descarga de Insumos
                                </a>
                                <button type="button" class="action-btn" style="background: var(--color-warning);"
                                    data-bs-toggle="modal" data-bs-target="#insumosReportModal">
                                    <i class="bi bi-file-earmark-bar-graph"></i>
                                    Reporte de Insumos
                                </button>
                        <?php endif; ?>
                        <?php if ($can_manage_inventory): ?>
                                <a href="hospital_medications.php" class="action-btn" style="background: var(--color-primary);">
                                    <i class="bi bi-hospital"></i>
                                    Meds. Hospitalarios
                                </a>
                                <button type="button" class="action-btn" data-bs-toggle="modal"
                                    data-bs-target="#addMedicineModal">
                                    <i class="bi bi-plus-circle"></i>
                                    Nuevo Medicamento
                                </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="search-container">
                    <div class="search-box">
                        <i class="bi bi-search search-icon"></i>
                        <input type="text" class="search-input" id="searchInput"
                            placeholder="Buscar por nombre, molécula o casa farmacéutica...">
                    </div>

                    <div class="filter-tabs">
                        <button class="filter-tab active" data-filter="all">
                            <i class="bi bi-grid"></i>
                            Todos
                        </button>
                        <button class="filter-tab" data-filter="adequate">
                            <i class="bi bi-check-circle"></i>
                            En Stock
                        </button>
                        <button class="filter-tab" data-filter="low">
                            <i class="bi bi-exclamation-circle"></i>
                            Stock Bajo
                        </button>
                        <button class="filter-tab" data-filter="out">
                            <i class="bi bi-x-circle"></i>
                            Agotados
                        </button>
                        <button class="filter-tab" data-filter="expiring">
                            <i class="bi bi-clock-history"></i>
                            Por Vencer
                        </button>
                        <button class="filter-tab" data-filter="expired">
                            <i class="bi bi-calendar-x"></i>
                            Vencidos
                        </button>
                        <button class="filter-tab" data-filter="pending">
                            <i class="bi bi-box-arrow-in-down"></i>
                            Pendientes
                        </button>
                    </div>
                </div>
            </div>

            <!-- Verificador de Precios -->
            <?php if ($user_type === 'user'): ?>
                    <div class="appointments-section animate-in delay-1 mb-4">
                        <div class="section-header mb-0">
                            <h3 class="section-title">
                                <i class="bi bi-upc-scan section-title-icon"></i>
                                Verificador de Precios
                            </h3>
                        </div>
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0">
                                        <i class="bi bi-upc"></i>
                                    </span>
                                    <input type="text" class="form-control border-start-0 ps-0" id="barcodeVerifier"
                                        placeholder="Escanee el código de barras aquí..." autocomplete="off">
                                    <button class="btn btn-outline-primary" type="button" id="clearVerifier">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                                <small class="text-muted mt-1 d-block">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Haga clic en el campo y escanee el producto
                                </small>
                            </div>
                            <div class="col-md-6">
                                <div id="verifierResult" class="d-none">
                                    <div class="alert alert-success d-flex align-items-center mb-0" role="alert">
                                        <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                                        <div>
                                            <h5 class="alert-heading mb-1" id="verifierName">Nombre del Producto</h5>
                                            <div class="d-flex gap-3">
                                                <span class="badge bg-primary fs-6" id="verifierPrice">Q0.00</span>
                                                <span class="badge bg-info text-dark" id="verifierStock">Stock: 0</span>
                                                <span class="text-muted small" id="verifierMeta">Detalles...</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div id="verifierError" class="d-none">
                                    <div class="alert alert-danger d-flex align-items-center mb-0" role="alert">
                                        <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                                        <div>
                                            Producto no encontrado
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
            <?php endif; ?>

            <!-- Tabla de inventario -->
            <section class="appointments-section animate-in delay-2">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-box-seam section-title-icon"></i>
                        Inventario de Medicamentos
                    </h3>
                    <div class="d-flex gap-2">
                        <div class="badge bg-primary d-flex align-items-center p-2">
                            <i class="bi bi-box-seam me-2"></i>
                            <?php echo $total_items; ?> Items
                        </div>
                    </div>
                </div>

                <?php if (count($inventory_items) > 0): ?>
                        <div class="table-responsive">
                            <table class="appointments-table" id="inventoryTable">
                                <thead>
                                    <tr>
                                        <th>Medicamento</th>
                                        <th>Molécula</th>
                                        <th>Presentación</th>
                                        <th>Precios (Q)</th>
                                        <th>Stock (Unds)</th>
                                        <th>Vencimiento</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inventory_items as $item): ?>
                                            <?php
                                            // Determinar estado del stock
                                            $estado = $item['estado'] ?? 'Disponible';
                                            $cantidad = $item['cantidad_med'];
                                            $status_class = 'status-good';
                                            $status_icon = 'bi-check-circle';

                                            if ($estado === 'Pendiente') {
                                                $status_class = 'status-info';
                                                $status_icon = 'bi-box-arrow-in-down';
                                            } elseif ($cantidad == 0) {
                                                $status_class = 'status-danger';
                                                $status_icon = 'bi-x-circle';
                                            } elseif ($cantidad <= 10) {
                                                $status_class = 'status-warning';
                                                $status_icon = 'bi-exclamation-circle';
                                            }

                                            // Determinar estado de vencimiento
                                            $expiry_class = 'status-good';
                                            $expiry_text = 'Válido';

                                            if ($item['fecha_vencimiento']) {
                                                $expiry_date = new DateTime($item['fecha_vencimiento']);
                                                $today_dt = new DateTime();
                                                $days_diff = $today_dt->diff($expiry_date)->days;
                                                $is_expired = $expiry_date < $today_dt;

                                                if ($is_expired) {
                                                    $expiry_class = 'status-danger';
                                                    $expiry_text = 'Vencido';
                                                } else {
                                                    // Calcula 6 meses basados en que un mes en promedio son 30 días
                                                    $six_months = 30 * 6;
                                                    if ($days_diff <= $six_months) {
                                                        $expiry_class = 'status-warning';

                                                        if ($days_diff >= 30) {
                                                            $months = floor($days_diff / 30);
                                                            $expiry_text = $months . ' mes' . ($months > 1 ? 'es' : '');
                                                        } else {
                                                            $expiry_text = $days_diff . ' días';
                                                        }
                                                    }
                                                }
                                            } else {
                                                if ($estado === 'Pendiente') {
                                                    $expiry_text = 'Por definir';
                                                }
                                            }

                                            // Data attributes para filtrado
                                            $barcode = strtolower($item['codigo_barras'] ?? '');
                                            $data_attrs = "data-stock='{$status_class}' data-expiry='{$expiry_class}' data-barcode='{$barcode}'";
                                            ?>
                                            <tr <?php echo $data_attrs; ?>>
                                                <td>
                                                    <div class="patient-cell">
                                                        <div class="patient-avatar" style="background: var(--color-primary);">
                                                            <i class="bi bi-capsule"></i>
                                                        </div>
                                                        <div class="patient-info">
                                                            <div class="patient-name">
                                                                <?php echo htmlspecialchars($item['nom_medicamento']); ?>
                                                            </div>
                                                            <div class="patient-contact">
                                                                <?php echo htmlspecialchars($item['casa_farmaceutica']); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span
                                                        class="text-muted"><?php echo htmlspecialchars($item['mol_medicamento']); ?></span>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($item['presentacion_med']); ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-column gap-1" style="font-size: 0.85rem;">
                                                        <span class="text-primary fw-bold">V:
                                                            Q<?php echo number_format($item['precio_venta'] ?? 0, 2); ?></span>
                                                        <span class="text-info">H:
                                                            Q<?php echo number_format($item['precio_hospital'] ?? 0, 2); ?></span>
                                                        <span class="text-success">M:
                                                            Q<?php echo number_format($item['precio_medico'] ?? 0, 2); ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-column gap-1">
                                                        <span class="status-badge <?php echo $status_class; ?>">
                                                            <i class="bi bi-shop me-1"></i>Farm: <?php echo $item['cantidad_med']; ?>
                                                        </span>
                                                        <span class="status-badge status-info">
                                                            <i class="bi bi-hospital me-1"></i>Hosp:
                                                            <?php echo $item['stock_hospital'] ?? 0; ?>
                                                        </span>
                                                    </div>
                                                    <?php if ($estado === 'Pendiente'): ?>
                                                            <div class="mt-1">
                                                                <span class="status-badge status-info" style="font-size: 0.75rem;">
                                                                    Pendiente de recepción
                                                                </span>
                                                            </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($item['fecha_vencimiento']): ?>
                                                            <div class="mb-1">
                                                                <?php echo date('d/m/Y', strtotime($item['fecha_vencimiento'])); ?>
                                                            </div>
                                                            <span class="status-badge <?php echo $expiry_class; ?>">
                                                                <?php echo $expiry_text; ?>
                                                            </span>
                                                    <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <?php if ($estado === 'Pendiente'): ?>
                                                                <button type="button" class="btn-icon receive"
                                                                    onclick="openReceiveModal(<?php echo $item['id_inventario']; ?>, '<?php echo htmlspecialchars($item['nom_medicamento']); ?>', '<?php echo htmlspecialchars($item['codigo_barras'] ?? ''); ?>')"
                                                                    data-bs-toggle="modal" data-bs-target="#receiveMedicineModal"
                                                                    title="Recibir producto">
                                                                    <i class="bi bi-box-arrow-in-down"></i>
                                                                </button>
                                                        <?php else: ?>
                                                                <?php if ($can_manage_inventory): ?>
                                                                        <button type="button" class="btn-icon edit"
                                                                            data-id="<?php echo $item['id_inventario']; ?>" data-bs-toggle="modal"
                                                                            data-bs-target="#editMedicineModal" title="Editar">
                                                                            <i class="bi bi-pencil"></i>
                                                                        </button>
                                                                        <button type="button" class="btn-icon delete"
                                                                            data-id="<?php echo $item['id_inventario']; ?>" title="Eliminar">
                                                                            <i class="bi bi-trash"></i>
                                                                        </button>
                                                                <?php endif; ?>
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
                                <i class="bi bi-box"></i>
                            </div>
                            <h4 class="text-muted mb-2">No hay medicamentos en el inventario</h4>
                            <p class="text-muted mb-3">Comience agregando nuevos medicamentos al sistema</p>
                            <?php if ($can_manage_inventory): ?>
                                    <button type="button" class="action-btn" data-bs-toggle="modal" data-bs-target="#addMedicineModal">
                                        <i class="bi bi-plus-circle"></i>
                                        Agregar primer medicamento
                                    </button>
                            <?php endif; ?>
                        </div>
                <?php endif; ?>
            </section>

            <!-- Panel de alertas -->
            <div class="alerts-grid animate-in delay-3">
                <!-- Medicamentos por caducar -->
                <div class="alert-card">
                    <div class="alert-header">
                        <div class="alert-icon warning">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <h3 class="alert-title">Caducidad Próxima</h3>
                    </div>

                    <?php
                    // Obtener medicamentos próximos a caducar
                    $stmt = $conn->prepare("
                        SELECT id_inventario, nom_medicamento, fecha_vencimiento, cantidad_med 
                        FROM inventario 
                        WHERE fecha_vencimiento BETWEEN ? AND ? AND cantidad_med > 0
                        ORDER BY fecha_vencimiento ASC
                        LIMIT 5
                    ");
                    $stmt->execute([$today, $next_month]);
                    $expiring_medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>

                    <?php if (count($expiring_medications) > 0): ?>
                            <ul class="alert-list">
                                <?php foreach ($expiring_medications as $medication): ?>
                                        <?php
                                        $expiry_date = new DateTime($medication['fecha_vencimiento']);
                                        $today_dt = new DateTime();
                                        $days_diff = $today_dt->diff($expiry_date)->days;
                                        ?>
                                        <li class="alert-item">
                                            <div class="alert-item-header">
                                                <span
                                                    class="alert-item-name"><?php echo htmlspecialchars($medication['nom_medicamento']); ?></span>
                                                <span class="alert-badge warning">
                                                    <?php echo $days_diff; ?> días
                                                </span>
                                            </div>
                                            <div class="alert-item-details">
                                                <span>Vence: <?php echo $expiry_date->format('d/m/Y'); ?></span>
                                                <span>Stock: <?php echo $medication['cantidad_med']; ?></span>
                                            </div>
                                        </li>
                                <?php endforeach; ?>
                            </ul>
                    <?php else: ?>
                            <div class="no-alerts">
                                <div class="no-alerts-icon">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <p class="text-muted mb-0">Sin medicamentos próximos a caducar</p>
                            </div>
                    <?php endif; ?>
                </div>

                <!-- Stock bajo -->
                <div class="alert-card">
                    <div class="alert-header">
                        <div class="alert-icon danger">
                            <i class="bi bi-arrow-down-circle"></i>
                        </div>
                        <h3 class="alert-title">Stock Bajo</h3>
                    </div>

                    <?php
                    // Obtener medicamentos con stock bajo
                    $stmt = $conn->query("
                        SELECT id_inventario, nom_medicamento, cantidad_med 
                        FROM inventario 
                        WHERE cantidad_med > 0 AND cantidad_med <= 10
                        ORDER BY cantidad_med ASC
                        LIMIT 5
                    ");
                    $low_stock_medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>

                    <?php if (count($low_stock_medications) > 0): ?>
                            <ul class="alert-list">
                                <?php foreach ($low_stock_medications as $medication): ?>
                                        <li class="alert-item">
                                            <div class="alert-item-header">
                                                <span
                                                    class="alert-item-name"><?php echo htmlspecialchars($medication['nom_medicamento']); ?></span>
                                                <span class="alert-badge danger">
                                                    <?php echo $medication['cantidad_med']; ?> unidades
                                                </span>
                                            </div>
                                        </li>
                                <?php endforeach; ?>
                            </ul>
                    <?php else: ?>
                            <div class="no-alerts">
                                <div class="no-alerts-icon">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <p class="text-muted mb-0">Inventario con stock suficiente</p>
                            </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal para agregar medicamento -->
    <div class="modal fade" id="addMedicineModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle me-2"></i>
                        Agregar Medicamento
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addMedicineForm" action="save_medicine.php" method="POST">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="codigo_barras" class="form-label">Código de Barras</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-upc"></i></span>
                                    <input type="text" class="form-control" id="codigo_barras" name="codigo_barras"
                                        placeholder="Escanee o escriba...">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="nom_medicamento" class="form-label">Nombre del Medicamento</label>
                                <input type="text" class="form-control" id="nom_medicamento" name="nom_medicamento"
                                    required>
                            </div>
                            <div class="col-md-6">
                                <label for="mol_medicamento" class="form-label">Molécula</label>
                                <input type="text" class="form-control" id="mol_medicamento" name="mol_medicamento"
                                    required>
                            </div>
                            <div class="col-md-6">
                                <label for="presentacion_med" class="form-label">Presentación</label>
                                <input type="text" class="form-control" id="presentacion_med" name="presentacion_med"
                                    required>
                            </div>
                            <div class="col-md-6">
                                <label for="casa_farmaceutica" class="form-label">Casa Farmacéutica</label>
                                <input type="text" class="form-control" id="casa_farmaceutica" name="casa_farmaceutica"
                                    required>
                            </div>
                            <div class="col-md-3">
                                <label for="cantidad_med" class="form-label">Stock Farmacia</label>
                                <input type="number" class="form-control" id="cantidad_med" name="cantidad_med" min="0"
                                    required>
                            </div>
                            <div class="col-md-3">
                                <label for="stock_hospital" class="form-label">Stock Hospital</label>
                                <input type="number" class="form-control" id="stock_hospital" name="stock_hospital"
                                    min="0" value="0" required>
                            </div>
                            <div class="col-md-3">
                                <label for="precio_compra" class="form-label">Precio Compra</label>
                                <div class="input-group">
                                    <span class="input-group-text">Q</span>
                                    <input type="number" class="form-control" id="precio_compra" name="precio_compra"
                                        min="0" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="precio_venta" class="form-label">Precio Venta</label>
                                <div class="input-group">
                                    <span class="input-group-text">Q</span>
                                    <input type="number" class="form-control" id="precio_venta" name="precio_venta"
                                        min="0" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="precio_hospital" class="form-label">Precio Hosp.</label>
                                <div class="input-group">
                                    <span class="input-group-text">Q</span>
                                    <input type="number" class="form-control" id="precio_hospital"
                                        name="precio_hospital" min="0" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="precio_medico" class="form-label">Precio Méd.</label>
                                <div class="input-group">
                                    <span class="input-group-text">Q</span>
                                    <input type="number" class="form-control" id="precio_medico" name="precio_medico"
                                        min="0" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="fecha_adquisicion" class="form-label">Fecha de Adquisición</label>
                                <input type="date" class="form-control" id="fecha_adquisicion" name="fecha_adquisicion"
                                    required>
                            </div>
                            <div class="col-md-4">
                                <label for="fecha_vencimiento" class="form-label">Fecha de Vencimiento</label>
                                <input type="date" class="form-control" id="fecha_vencimiento" name="fecha_vencimiento"
                                    required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>
                            Guardar Medicamento
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para editar medicamento -->
    <div class="modal fade" id="editMedicineModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil me-2"></i>
                        Editar Medicamento
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editMedicineForm" action="update_medicine.php" method="POST">
                    <input type="hidden" name="id_inventario" id="edit_id_inventario">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="edit_codigo_barras" class="form-label">Código de Barras</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-upc"></i></span>
                                    <input type="text" class="form-control" id="edit_codigo_barras"
                                        name="codigo_barras">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_nom_medicamento" class="form-label">Nombre del Medicamento</label>
                                <input type="text" class="form-control" id="edit_nom_medicamento" name="nom_medicamento"
                                    required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_mol_medicamento" class="form-label">Molécula</label>
                                <input type="text" class="form-control" id="edit_mol_medicamento" name="mol_medicamento"
                                    required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_presentacion_med" class="form-label">Presentación</label>
                                <input type="text" class="form-control" id="edit_presentacion_med"
                                    name="presentacion_med" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_casa_farmaceutica" class="form-label">Casa Farmacéutica</label>
                                <input type="text" class="form-control" id="edit_casa_farmaceutica"
                                    name="casa_farmaceutica" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Stock Total</label>
                                <input type="number" class="form-control bg-light" id="edit_total_stock" readonly>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_cantidad_med" class="form-label">Stock Farmacia</label>
                                <input type="number" class="form-control" id="edit_cantidad_med" name="cantidad_med"
                                    min="0" required oninput="updateStockDistribution('pharmacy')">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_stock_hospital" class="form-label">Stock Hospital</label>
                                <input type="number" class="form-control" id="edit_stock_hospital" name="stock_hospital"
                                    min="0" required oninput="updateStockDistribution('hospital')">
                            </div>
                            <div class="col-md-3">
                                <label for="edit_precio_compra" class="form-label">Precio Compra</label>
                                <div class="input-group">
                                    <span class="input-group-text">Q</span>
                                    <input type="number" class="form-control" id="edit_precio_compra"
                                        name="precio_compra" min="0" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="edit_precio_venta" class="form-label">Precio Venta</label>
                                <div class="input-group">
                                    <span class="input-group-text">Q</span>
                                    <input type="number" class="form-control" id="edit_precio_venta" name="precio_venta"
                                        min="0" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="edit_precio_hospital" class="form-label">Precio Hosp.</label>
                                <div class="input-group">
                                    <span class="input-group-text">Q</span>
                                    <input type="number" class="form-control" id="edit_precio_hospital"
                                        name="precio_hospital" min="0" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="edit_precio_medico" class="form-label">Precio Méd.</label>
                                <div class="input-group">
                                    <span class="input-group-text">Q</span>
                                    <input type="number" class="form-control" id="edit_precio_medico"
                                        name="precio_medico" min="0" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_fecha_adquisicion" class="form-label">Fecha Adquisición</label>
                                <input type="date" class="form-control" id="edit_fecha_adquisicion"
                                    name="fecha_adquisicion" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_fecha_vencimiento" class="form-label">Fecha Vencimiento</label>
                                <input type="date" class="form-control" id="edit_fecha_vencimiento"
                                    name="fecha_vencimiento" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>
                            Actualizar Medicamento
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para recibir medicamento -->
    <div class="modal fade" id="receiveMedicineModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-box-arrow-in-down me-2"></i>
                        Recibir Medicamento
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Medicamento</label>
                        <input type="text" class="form-control" id="receive_nom_medicamento" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="receive_codigo_barras" class="form-label">Código de Barras</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-upc"></i></span>
                            <input type="text" class="form-control" id="receive_codigo_barras"
                                placeholder="Confirmar/Escanear">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="receive_fecha_vencimiento" class="form-label">Fecha de Vencimiento</label>
                        <input type="date" class="form-control" id="receive_fecha_vencimiento" required>
                    </div>
                    <div class="mb-3">
                        <label for="receive_documento_referencia" class="form-label">Factura / Nota de Envío</label>
                        <input type="text" class="form-control" id="receive_documento_referencia"
                            placeholder="Opcional">
                    </div>
                    <input type="hidden" id="receive_id_inventario">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="submitReceive()">
                        <i class="bi bi-check-circle me-2"></i>
                        Confirmar Recepción
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Optimizado -->
    <script>
        // Módulo de Inventario Reingenierizado - Centro Médico RS

        (function () {
            'use strict';

            // Función Global para distribución de stock
            window.updateStockDistribution = function (source) {
                const totalEl = document.getElementById('edit_total_stock');
                const pharmacyEl = document.getElementById('edit_cantidad_med');
                const hospitalEl = document.getElementById('edit_stock_hospital');

                if (!totalEl || !pharmacyEl || !hospitalEl) return;

                const total = parseInt(totalEl.value) || 0;
                let pharmacy = parseInt(pharmacyEl.value) || 0;
                let hospital = parseInt(hospitalEl.value) || 0;

                if (source === 'pharmacy') {
                    // Si cambio farmacia, el resto va a hospital
                    if (pharmacy > total) {
                        pharmacy = total;
                        pharmacyEl.value = total;
                    }
                    hospital = total - pharmacy;
                    hospitalEl.value = hospital;
                } else if (source === 'hospital') {
                    // Si cambio hospital, el resto va a farmacia
                    if (hospital > total) {
                        hospital = total;
                        hospitalEl.value = total;
                    }
                    pharmacy = total - hospital;
                    pharmacyEl.value = pharmacy;
                }
            };

            // ==========================================================================
            // CONFIGURACIÓN Y CONSTANTES
            // ==========================================================================
            const CONFIG = {
                themeKey: 'dashboard-theme',

                inventoryKey: 'inventory-filters',
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
                searchInput: document.getElementById('searchInput'),
                filterTabs: document.querySelectorAll('.filter-tab'),
                tableRows: document.querySelectorAll('#inventoryTable tbody tr'),
                editButtons: document.querySelectorAll('.btn-icon.edit'),
                deleteButtons: document.querySelectorAll('.btn-icon.delete')
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
            // FUNCIONALIDADES DE INVENTARIO
            // ==========================================================================
            class InventoryManager {
                constructor() {
                    this.currentFilter = 'all';
                    this.searchTerm = '';
                    this.setupEventListeners();
                    this.loadSavedFilters();
                }

                setupEventListeners() {
                    // Búsqueda
                    if (DOM.searchInput) {
                        DOM.searchInput.addEventListener('input', (e) => {
                            this.searchTerm = e.target.value.toLowerCase();
                            this.applyFilters();
                        });
                    }

                    // Filtros
                    if (DOM.filterTabs) {
                        DOM.filterTabs.forEach(tab => {
                            tab.addEventListener('click', (e) => {
                                // Remover clase active de todos
                                DOM.filterTabs.forEach(t => t.classList.remove('active'));
                                // Agregar clase active al actual
                                e.target.classList.add('active');

                                this.currentFilter = e.target.getAttribute('data-filter');
                                this.saveFilters();
                                this.applyFilters();
                            });
                        });
                    }

                    // Botones de acción
                    if (DOM.editButtons) {
                        DOM.editButtons.forEach(button => {
                            button.addEventListener('click', (e) => {
                                const id = e.target.closest('.btn-icon').getAttribute('data-id');
                                this.loadMedicineData(id);
                            });
                        });
                    }

                    if (DOM.deleteButtons) {
                        DOM.deleteButtons.forEach(button => {
                            button.addEventListener('click', (e) => {
                                const id = e.target.closest('.btn-icon').getAttribute('data-id');
                                this.deleteMedicine(id);
                            });
                        });
                    }
                }

                applyFilters() {
                    DOM.tableRows.forEach(row => {
                        const stockAttr = row.getAttribute('data-stock');
                        const expiryAttr = row.getAttribute('data-expiry');
                        const text = row.textContent.toLowerCase();

                        let show = true;

                        // Aplicar filtro por estado
                        if (this.currentFilter !== 'all') {
                            switch (this.currentFilter) {
                                case 'adequate':
                                    show = stockAttr === 'status-good';
                                    break;
                                case 'low':
                                    show = stockAttr === 'status-warning';
                                    break;
                                case 'out':
                                    show = stockAttr === 'status-danger';
                                    break;
                                case 'expiring':
                                    show = expiryAttr === 'status-warning';
                                    break;
                                case 'expired':
                                    show = expiryAttr === 'status-danger';
                                    break;
                                case 'pending':
                                    show = stockAttr === 'status-info';
                                    break;
                            }
                        }

                        // Aplicar búsqueda
                        if (show && this.searchTerm) {
                            const barcode = row.getAttribute('data-barcode') || '';
                            show = text.includes(this.searchTerm) || barcode.includes(this.searchTerm);
                        }

                        row.style.display = show ? '' : 'none';
                    });
                }

                loadMedicineData(id) {
                    fetch(`get_medicine.php?id=${id}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.error) {
                                console.error(data.error);
                                return;
                            }

                            const pharmacyStock = parseInt(data.cantidad_med || 0);
                            const hospitalStock = parseInt(data.stock_hospital || 0);
                            const total = pharmacyStock + hospitalStock;

                            document.getElementById('edit_id_inventario').value = data.id_inventario;
                            document.getElementById('edit_codigo_barras').value = data.codigo_barras || '';
                            document.getElementById('edit_nom_medicamento').value = data.nom_medicamento;
                            document.getElementById('edit_mol_medicamento').value = data.mol_medicamento;
                            document.getElementById('edit_presentacion_med').value = data.presentacion_med;
                            document.getElementById('edit_casa_farmaceutica').value = data.casa_farmaceutica;

                            document.getElementById('edit_total_stock').value = total;
                            document.getElementById('edit_cantidad_med').value = pharmacyStock;
                            document.getElementById('edit_stock_hospital').value = hospitalStock;

                            document.getElementById('edit_precio_compra').value = data.precio_compra || 0;
                            document.getElementById('edit_precio_venta').value = data.precio_venta || 0;
                            document.getElementById('edit_precio_hospital').value = data.precio_hospital || 0;
                            document.getElementById('edit_precio_medico').value = data.precio_medico || 0;
                            document.getElementById('edit_fecha_adquisicion').value = data.fecha_adquisicion;
                            document.getElementById('edit_fecha_vencimiento').value = data.fecha_vencimiento;
                        })
                        .catch(error => {
                            console.error('Error al cargar datos:', error);
                        });
                }

                deleteMedicine(id) {
                    if (confirm('¿Está seguro de eliminar este medicamento? Esta acción no se puede deshacer.')) {
                        window.location.href = `delete_medicine.php?id=${id}`;
                    }
                }

                saveFilters() {
                    const filters = {
                        currentFilter: this.currentFilter,
                        searchTerm: this.searchTerm
                    };
                    localStorage.setItem(CONFIG.inventoryKey, JSON.stringify(filters));
                }

                loadSavedFilters() {
                    const savedFilters = localStorage.getItem(CONFIG.inventoryKey);
                    if (savedFilters) {
                        const filters = JSON.parse(savedFilters);
                        this.currentFilter = filters.currentFilter || 'all';
                        this.searchTerm = filters.searchTerm || '';

                        // Aplicar filtro guardado
                        if (DOM.searchInput && this.searchTerm) {
                            DOM.searchInput.value = this.searchTerm;
                        }

                        if (DOM.filterTabs) {
                            DOM.filterTabs.forEach(tab => {
                                if (tab.getAttribute('data-filter') === this.currentFilter) {
                                    tab.classList.add('active');
                                } else {
                                    tab.classList.remove('active');
                                }
                            });
                        }

                        this.applyFilters();
                    }
                }
            }

            // ==========================================================================
            // VERIFICADOR DE PRECIOS
            // ==========================================================================
            class VerifierManager {
                constructor() {
                    this.input = document.getElementById('barcodeVerifier');
                    this.resultDiv = document.getElementById('verifierResult');
                    this.errorDiv = document.getElementById('verifierError');
                    this.clearBtn = document.getElementById('clearVerifier');

                    if (this.input) {
                        this.setupEventListeners();
                    }
                }

                setupEventListeners() {
                    let timeout = null;

                    // Input handling
                    this.input.addEventListener('input', (e) => {
                        const code = e.target.value.trim();

                        if (!code) {
                            this.hideState();
                            return;
                        }

                        clearTimeout(timeout);
                        timeout = setTimeout(() => {
                            this.verifyProduct(code);
                        }, 200);
                    });

                    // Clear button
                    this.clearBtn.addEventListener('click', () => {
                        this.input.value = '';
                        this.hideState();
                        this.input.focus();
                    });

                    // Enter key
                    this.input.addEventListener('keypress', (e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            this.verifyProduct(this.input.value.trim());
                        }
                    });
                }

                hideState() {
                    this.resultDiv.classList.add('d-none');
                    this.errorDiv.classList.add('d-none');
                }

                verifyProduct(code) {
                    if (!code) return;

                    let foundItem = null;
                    const rows = document.querySelectorAll('#inventoryTable tbody tr');

                    for (let row of rows) {
                        const rowBarcode = row.getAttribute('data-barcode');
                        if (rowBarcode === code || rowBarcode === code.toLowerCase()) {
                            const name = row.querySelector('.patient-name').textContent.trim();
                            // Price is in the 4th column (index 3) now
                            const priceText = row.children[3].textContent.trim();
                            const stockText = row.querySelector('.status-badge').textContent.trim();

                            // Try to get meta safely
                            const contactElem = row.querySelector('.patient-contact');
                            const molElem = row.children[1].querySelector('span'); // Mol is in 2nd column

                            const meta = (contactElem ? contactElem.textContent.trim() : '') + ' • ' + (molElem ? molElem.textContent.trim() : '');

                            foundItem = { name, price: priceText, stock: stockText, meta };
                            break;
                        }
                    }

                    if (foundItem) {
                        document.getElementById('verifierName').textContent = foundItem.name;
                        document.getElementById('verifierPrice').textContent = foundItem.price;
                        document.getElementById('verifierStock').textContent = foundItem.stock;
                        document.getElementById('verifierMeta').textContent = foundItem.meta;

                        this.resultDiv.classList.remove('d-none');
                        this.errorDiv.classList.add('d-none');

                        this.input.select();
                    } else {
                        this.resultDiv.classList.add('d-none');
                        this.errorDiv.classList.remove('d-none');
                        this.input.select();
                    }
                }
            }

            // ==========================================================================
            // FUNCIONALIDADES GLOBALES DEL INVENTARIO
            // ==========================================================================
            window.openReceiveModal = function (id, name, barcode) {
                document.getElementById('receive_id_inventario').value = id;
                document.getElementById('receive_nom_medicamento').value = name;
                document.getElementById('receive_codigo_barras').value = barcode || '';

                // Limpiar campo de documento
                const docField = document.getElementById('receive_documento_referencia');
                if (docField) docField.value = '';

                // Establecer fecha de vencimiento predeterminada (1 año desde hoy)
                const defaultDate = new Date();
                defaultDate.setFullYear(defaultDate.getFullYear() + 1);
                document.getElementById('receive_fecha_vencimiento').valueAsDate = defaultDate;

                // Modales se inicializan automáticamente vía data-attributes
            };

            window.submitReceive = function () {
                const id = document.getElementById('receive_id_inventario').value;
                const expiryDate = document.getElementById('receive_fecha_vencimiento').value;
                const referenceDoc = document.getElementById('receive_documento_referencia')?.value || '';

                const barcode = document.getElementById('receive_codigo_barras').value;

                if (!expiryDate) {
                    alert('Por favor ingrese la fecha de vencimiento');
                    return;
                }

                // Mostrar estado de carga
                const btn = document.querySelector('#receiveMedicineModal .btn-success');
                const originalHtml = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';

                fetch('receive_item.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id_inventario: id,
                        fecha_vencimiento: expiryDate,
                        documento_referencia: referenceDoc,
                        codigo_barras: barcode
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            alert('Error: ' + data.message);
                            btn.disabled = false;
                            btn.innerHTML = originalHtml;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error de conexión con el servidor');
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                    });
            };

            // ==========================================================================
            // ANIMACIONES Y EFECTOS VISUALES
            // ==========================================================================
            class AnimationManager {
                constructor() {
                    this.setupGreeting();
                    this.setupAnimations();
                }

                setupGreeting() {
                    const greetingElement = document.getElementById('greeting-text');
                    if (!greetingElement) return;

                    const hour = new Date().getHours();
                    let greeting = '';

                    if (hour < 12) {
                        greeting = 'Buenos días';
                    } else if (hour < 19) {
                        greeting = 'Buenas tardes';
                    } else {
                        greeting = 'Buenas noches';
                    }

                    greetingElement.textContent = greeting + ', Gestión de Inventario';
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
                    document.querySelectorAll('.stat-card, .appointments-section, .alert-card').forEach(el => {
                        observer.observe(el);
                    });
                }
            }

            // ==========================================================================
            // VALIDACIÓN DE FORMULARIOS
            // ==========================================================================
            class FormValidator {
                constructor() {
                    this.setupFormValidation();
                }

                setupFormValidation() {
                    const addForm = document.getElementById('addMedicineForm');
                    const editForm = document.getElementById('editMedicineForm');

                    if (addForm) {
                        addForm.addEventListener('submit', (e) => this.validateMedicineForm(e, 'add'));
                    }

                    if (editForm) {
                        editForm.addEventListener('submit', (e) => this.validateMedicineForm(e, 'edit'));
                    }
                }

                validateMedicineForm(e, formType) {
                    const cantidad = document.getElementById(formType === 'add' ? 'cantidad_med' : 'edit_cantidad_med').value;
                    if (cantidad < 0) {
                        e.preventDefault();
                        alert('La cantidad no puede ser negativa');
                        return false;
                    }

                    const fechaAdq = document.getElementById(formType === 'add' ? 'fecha_adquisicion' : 'edit_fecha_adquisicion').value;
                    const fechaVen = document.getElementById(formType === 'add' ? 'fecha_vencimiento' : 'edit_fecha_vencimiento').value;

                    if (new Date(fechaVen) < new Date(fechaAdq)) {
                        e.preventDefault();
                        alert('La fecha de vencimiento no puede ser anterior a la fecha de adquisición');
                        return false;
                    }

                    return true;
                }
            }

            // ==========================================================================
            // GESTOR DE BORRADORES (AUTO-SAVE)
            // ==========================================================================
            class FormDraftManager {
                constructor(formId, storageKey) {
                    this.form = document.getElementById(formId);
                    this.storageKey = storageKey;
                    this.ignoreFields = ['password', 'file', 'hidden'];

                    if (this.form) {
                        this.setupEventListeners();
                        this.restoreDraft();
                    }
                }

                setupEventListeners() {
                    // Escuchar cambios en inputs
                    this.form.addEventListener('input', (e) => {
                        this.saveDraft();
                    });

                    this.form.addEventListener('change', (e) => {
                        this.saveDraft();
                    });

                    // Limpiar al enviar exitosamente
                    this.form.addEventListener('submit', () => {
                        // Esperar un momento para asegurar que no hubo error de validación
                        // En un caso real, esto debería llamarse solo si el submit es exitoso
                        // Pero como es un form POST normal, se recargará la página
                        this.clearDraft();
                    });
                }

                saveDraft() {
                    const formData = {};
                    const elements = this.form.elements;

                    for (let i = 0; i < elements.length; i++) {
                        const el = elements[i];

                        if (!el.name || this.ignoreFields.includes(el.type)) continue;

                        if (el.type === 'checkbox' || el.type === 'radio') {
                            if (el.checked) {
                                formData[el.name] = el.value;
                            }
                        } else {
                            formData[el.name] = el.value;
                        }
                    }

                    localStorage.setItem(this.storageKey, JSON.stringify(formData));
                }

                restoreDraft() {
                    const savedData = localStorage.getItem(this.storageKey);
                    if (!savedData) return;

                    try {
                        const formData = JSON.parse(savedData);
                        const elements = this.form.elements;
                        let hasData = false;

                        for (const name in formData) {
                            if (this.form.elements[name]) {
                                const el = this.form.elements[name];

                                // Manejar diferentes tipos de inputs
                                if (el instanceof RadioNodeList) {
                                    for (let i = 0; i < el.length; i++) {
                                        if (el[i].value === formData[name]) {
                                            el[i].checked = true;
                                        }
                                    }
                                } else if (el.type === 'checkbox') {
                                    el.checked = true;
                                } else {
                                    el.value = formData[name];
                                }
                                hasData = true;
                            }
                        }

                        if (hasData) {
                            this.showDraftNotification();
                        }
                    } catch (e) {
                        console.error('Error al restaurar borrador:', e);
                    }
                }

                clearDraft() {
                    localStorage.removeItem(this.storageKey);
                }

                showDraftNotification() {
                    // Crear notificación toast si no existe
                    if (!document.getElementById('draftToast')) {
                        const toastContainer = document.createElement('div');
                        toastContainer.className = 'position-fixed bottom-0 end-0 p-3';
                        toastContainer.style.zIndex = '1100';
                        toastContainer.innerHTML = `
                            <div id="draftToast" class="toast align-items-center text-white bg-primary border-0" role="alert" aria-live="assertive" aria-atomic="true">
                                <div class="d-flex">
                                    <div class="toast-body">
                                        <i class="bi bi-save me-2"></i>
                                        Borrador recuperado automáticamente
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
                // Limpiar la barra de búsqueda (Requerimiento 1)
                const searchInput = document.getElementById('searchInput');
                if (searchInput) {
                    searchInput.value = '';
                }

                // Inicializar componentes
                const themeManager = new ThemeManager();
                const inventoryManager = new InventoryManager();
                const animationManager = new AnimationManager();
                const formValidator = new FormValidator();
                const verifierManager = new VerifierManager();

                // Inicializar gestor de borradores para nuevo medicamento
                const draftManager = new FormDraftManager('addMedicineForm', 'inventory_new_medicine_draft');

                // Exponer APIs necesarias globalmente
                window.inventory = {
                    theme: themeManager,
                    manager: inventoryManager,
                    animations: animationManager
                };

                // Establecer fecha actual como predeterminada en formularios
                const today = new Date().toISOString().split('T')[0];
                const fechaAdquisicion = document.getElementById('fecha_adquisicion');
                if (fechaAdquisicion) {
                    fechaAdquisicion.value = today;
                }

                // Log de inicialización
                console.log('Módulo de Inventario v4.0 inicializado correctamente');
                console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
                console.log('Rol: <?php echo htmlspecialchars($user_type); ?>');
                console.log('Total de items: <?php echo $total_items; ?>');
            });

            // ==========================================================================
            // MANEJO DE ERRORES GLOBALES
            // ==========================================================================
            window.addEventListener('error', (event) => {
                console.error('Error en inventario:', event.error);

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

    <!-- Insumos Report Modal -->
    <div class="modal fade" id="insumosReportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-bar-graph me-2"></i>Reporte Insumos
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form action="report_insumos.php" method="GET" target="_blank">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Fecha</label>
                            <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>"
                                required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted text-uppercase">Jornada</label>
                            <select name="shift" class="form-select">
                                <option value="morning">Mañana (08:00 AM - 05:00 PM)</option>
                                <option value="night">Noche (05:00 PM - 08:00 AM)</option>
                            </select>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-warning fw-bold text-white">
                                <i class="bi bi-printer me-2"></i>Generar Reporte
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery (required for Bootstrap modals) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Lógica para alternar el valor del inventario
        let showPurchasePrice = true;
        const valueCompra = <?php echo json_encode(number_format($total_value, 2)); ?>;
        const valueVenta = <?php echo json_encode(number_format($total_value_venta, 2)); ?>;

        function toggleInventoryValue() {
            showPurchasePrice = !showPurchasePrice;
            const titleEl = document.getElementById('inventoryValueTitle');
            const amountEl = document.getElementById('inventoryValueAmount');
            const subtitleEl = document.getElementById('inventoryValueSubtitle');
            const iconContainer = document.querySelector('#inventoryValueCard .stat-icon');

            if (showPurchasePrice) {
                titleEl.textContent = 'Valor en Inventario (Compra)';
                amountEl.textContent = 'Q ' + valueCompra;
                subtitleEl.textContent = 'Haz clic para ver el precio de venta';
                iconContainer.classList.replace('primary', 'success');
            } else {
                titleEl.textContent = 'Valor en Inventario (Venta)';
                amountEl.textContent = 'Q ' + valueVenta;
                subtitleEl.textContent = 'Haz clic para ver el precio de compra';
                iconContainer.classList.replace('success', 'primary');
            }
        }
    </script>
</body>

</html>