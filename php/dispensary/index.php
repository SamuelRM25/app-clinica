<?php
// inventory/index.php - Módulo de Ventas - Centro Médico Herrera Saenz
// Versión: 4.0 - Diseño Responsive con Sidebar Moderna y Efecto Mármol
session_start();

// Verificar sesión activa
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer zona horaria
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

    // Obtener items de inventario para venta, restando items reservados
    $stmt = $conn->prepare("
        SELECT i.id_inventario, i.codigo_barras, i.nom_medicamento, i.mol_medicamento, 
               i.presentacion_med, i.casa_farmaceutica, i.cantidad_med, i.stock_hospital,
               i.precio_venta, i.precio_hospital, i.precio_medico, i.precio_compra, i.fecha_vencimiento,
               (i.cantidad_med - COALESCE((SELECT SUM(cantidad) FROM reservas_inventario WHERE id_inventario = i.id_inventario), 0)) as disponible,
               ph.document_type, ph.document_number
        FROM inventario i
        LEFT JOIN purchase_items pi ON i.id_purchase_item = pi.id
        LEFT JOIN purchase_headers ph ON pi.purchase_header_id = ph.id
        WHERE i.cantidad_med > 0 AND i.estado != 'Pendiente'
        ORDER BY i.nom_medicamento
    ");
    $stmt->execute();
    $inventario = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener estadísticas para el dashboard
    // Ventas del día
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(total) as total FROM ventas WHERE DATE(fecha_venta) = ?");
    $stmt->execute([$today]);
    $today_sales = $stmt->fetch(PDO::FETCH_ASSOC);

    // Ventas del mes
    $month_start = date('Y-m-01');
    $month_end = date('Y-m-t');
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(total) as total FROM ventas WHERE fecha_venta BETWEEN ? AND ?");
    $stmt->execute([$month_start, $month_end]);
    $month_sales = $stmt->fetch(PDO::FETCH_ASSOC);

    // Total de ventas
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(total) as total FROM ventas");
    $stmt->execute();
    $total_sales = $stmt->fetch(PDO::FETCH_ASSOC);

    // Productos en inventario
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(cantidad_med) as total FROM inventario WHERE cantidad_med > 0");
    $stmt->execute();
    $inventory_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Información del usuario
    $user_name = $_SESSION['nombre'];
    $user_type = $_SESSION['tipoUsuario'];
    $user_specialty = $_SESSION['especialidad'] ?? 'Profesional Médico';

    // Título de la página
    $page_title = "Ventas - Centro Médico Herrera Saenz";

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Módulo de Ventas del Centro Médico Herrera Saenz - Sistema de gestión médica">
    <title><?php echo $page_title; ?></title>

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

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- CSS Crítico (incrustado - mismo que dashboard) -->
    <style>
        /* ==========================================================================
       VARIABLES CSS PARA TEMA DÍA/NOCHE (Mismo que dashboard)
       ========================================================================== */
        :root {
            /* Colores Modo Día (Escala Grises + Mármol) */
            --color-bg-day: #ffffff;
            --color-surface-day: #f8f9fa;
            --color-card-day: #ffffff;
            --color-text-day: #1a1a1a;
            --color-text-secondary-day: #6c757d;
            --color-border-day: #e9ecef;
            --color-primary-day: #0d6efd;
            --color-secondary-day: #6c757d;
            --color-success-day: #198754;
            --color-warning-day: #ffc107;
            --color-danger-day: #dc3545;
            --color-info-day: #0dcaf0;

            /* Colores Modo Noche (Tonalidades Azules) */
            --color-bg-night: #0f172a;
            --color-surface-night: #1e293b;
            --color-card-night: #1e293b;
            --color-text-night: #e2e8f0;
            --color-text-secondary-night: #94a3b8;
            --color-border-night: #2d3748;
            --color-primary-night: #3b82f6;
            --color-secondary-night: #64748b;
            --color-success-night: #10b981;
            --color-warning-night: #f59e0b;
            --color-danger-night: #ef4444;
            --color-info-night: #06b6d4;

            /* Versiones RGB para opacidad */
            --color-primary-rgb: 13, 110, 253;
            --color-success-rgb: 25, 135, 84;
            --color-warning-rgb: 255, 193, 7;
            --color-danger-rgb: 220, 53, 69;
            --color-info-rgb: 13, 202, 240;
            --color-card-rgb: 255, 255, 255;

            /* Efecto Mármol */
            --marble-color-1: rgba(255, 255, 255, 0.95);
            --marble-color-2: rgba(248, 249, 250, 0.8);
            --marble-pattern: linear-gradient(135deg, var(--marble-color-1) 25%, transparent 25%),
                linear-gradient(225deg, var(--marble-color-1) 25%, transparent 25%),
                linear-gradient(45deg, var(--marble-color-1) 25%, transparent 25%),
                linear-gradient(315deg, var(--marble-color-1) 25%, var(--marble-color-2) 25%);

            /* Transiciones */
            --transition-base: 300ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 500ms cubic-bezier(0.4, 0, 0.2, 1);

            /* Sombras */
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.12);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);

            /* Bordes */
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;

            /* Espaciado */
            --space-xs: 0.25rem;
            --space-sm: 0.5rem;
            --space-md: 1rem;
            --space-lg: 1.5rem;
            --space-xl: 2rem;

            /* Tipografía */
            --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            --font-size-xs: 0.75rem;
            --font-size-sm: 0.875rem;
            --font-size-base: 1rem;
            --font-size-lg: 1.125rem;
            --font-size-xl: 1.25rem;
            --font-size-2xl: 1.5rem;
            --font-size-3xl: 1.875rem;
            --font-size-4xl: 2.25rem;
        }

        /* ==========================================================================
       ESTILOS BASE Y RESET
       ========================================================================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            font-size: 16px;
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font-family);
            font-weight: 400;
            line-height: 1.6;
            overflow-x: hidden;
            transition: background-color var(--transition-base);
        }

        /* ==========================================================================
       TEMA DÍA (POR DEFECTO)
       ========================================================================== */
        [data-theme="light"] {
            --color-bg: var(--color-bg-day);
            --color-surface: var(--color-surface-day);
            --color-card: var(--color-card-day);
            --color-text: var(--color-text-day);
            --color-text-secondary: var(--color-text-secondary-day);
            --color-border: var(--color-border-day);
            --color-primary: var(--color-primary-day);
            --color-secondary: var(--color-secondary-day);
            --color-success: var(--color-success-day);
            --color-warning: var(--color-warning-day);
            --color-danger: var(--color-danger-day);
            --color-info: var(--color-info-day);

            --marble-color-1: rgba(255, 255, 255, 0.95);
            --marble-color-2: rgba(248, 249, 250, 0.8);
        }

        /* ==========================================================================
       TEMA NOCHE
       ========================================================================== */
        [data-theme="dark"] {
            --color-bg: var(--color-bg-night);
            --color-surface: var(--color-surface-night);
            --color-card: var(--color-card-night);
            --color-text: var(--color-text-night);
            --color-text-secondary: var(--color-text-secondary-night);
            --color-border: var(--color-border-night);
            --color-primary: var(--color-primary-night);
            --color-secondary: var(--color-secondary-night);
            --color-success: var(--color-success-night);
            --color-warning: var(--color-warning-night);
            --color-danger: var(--color-danger-night);
            --color-info: var(--color-info-night);

            --color-primary-rgb: 59, 130, 246;
            --color-success-rgb: 16, 185, 129;
            --color-warning-rgb: 245, 158, 11;
            --color-danger-rgb: 239, 68, 68;
            --color-info-rgb: 6, 182, 212;
            --color-card-rgb: 30, 41, 59;

            --marble-color-1: rgba(15, 23, 42, 0.95);
            --marble-color-2: rgba(30, 41, 59, 0.8);
        }

        /* ==========================================================================
       APLICACIÓN DE VARIABLES
       ========================================================================== */
        body {
            background-color: var(--color-bg);
            color: var(--color-text);
            min-height: 100vh;
            position: relative;
        }

        /* ==========================================================================
       EFECTO MÁRMOL (FONDO)
       ========================================================================== */
        .marble-effect {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: -1;
            background:
                radial-gradient(circle at 20% 80%, var(--marble-color-1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, var(--marble-color-2) 0%, transparent 50%),
                var(--color-bg);
            background-blend-mode: overlay;
            background-size: 200% 200%;
            animation: marbleFloat 20s ease-in-out infinite alternate;
            opacity: 0.7;
            pointer-events: none;
        }

        @keyframes marbleFloat {
            0% {
                background-position: 0% 0%;
            }

            100% {
                background-position: 100% 100%;
            }
        }

        /* ==========================================================================
       LAYOUT PRINCIPAL
       ========================================================================== */
        .dashboard-container {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            position: relative;
            width: 100%;
            transition: all var(--transition-base);
        }

        /* User Details (Footer replacement) */
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--color-primary), var(--color-info));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: var(--font-size-lg);
            flex-shrink: 0;
        }

        .user-details {
            flex: 1;
            min-width: 0;
            transition: opacity var(--transition-base);
        }

        .user-name {
            font-weight: 600;
            display: block;
            font-size: var(--font-size-sm);
            color: var(--color-text);
            line-height: 1.2;
        }

        .user-role {
            font-size: var(--font-size-xs);
            color: var(--color-text-secondary);
            display: block;
            line-height: 1.2;
        }

        /* ==========================================================================
       HEADER SUPERIOR
       ========================================================================== */
        .dashboard-header {
            position: sticky;
            top: 0;
            left: 0;
            right: 0;
            background-color: var(--color-card);
            border-bottom: 1px solid var(--color-border);
            z-index: 900;
            backdrop-filter: blur(10px);
            background-color: rgba(var(--color-card-rgb), 0.95);
            box-shadow: var(--shadow-sm);
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--space-md) var(--space-lg);
            gap: var(--space-lg);
        }

        .brand-container {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            margin-left: 0;
        }

        .brand-logo {
            height: 40px;
            width: auto;
            object-fit: contain;
        }

        .mobile-toggle {
            display: none;
        }

        .header-controls {
            display: flex;
            align-items: center;
            gap: var(--space-lg);
        }

        .theme-toggle {
            position: relative;
        }

        .theme-btn {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border: none;
            background: var(--color-surface);
            color: var(--color-text);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--transition-base);
            position: relative;
            overflow: hidden;
        }

        .theme-btn:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-md);
        }

        .theme-btn:active {
            transform: scale(0.95);
        }

        .theme-icon {
            position: absolute;
            font-size: 1.25rem;
            transition: all var(--transition-base);
        }

        .sun-icon {
            opacity: 1;
            transform: rotate(0);
        }

        .moon-icon {
            opacity: 0;
            transform: rotate(-90deg);
        }

        [data-theme="dark"] .sun-icon {
            opacity: 0;
            transform: rotate(90deg);
        }

        [data-theme="dark"] .moon-icon {
            opacity: 1;
            transform: rotate(0);
        }

        .header-user {
            display: flex;
            align-items: center;
            gap: var(--space-md);
        }

        .header-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--color-primary), var(--color-info));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: var(--font-size-lg);
        }

        .header-details {
            display: flex;
            flex-direction: column;
        }

        .header-name {
            font-weight: 600;
            font-size: var(--font-size-sm);
            color: var(--color-text);
        }

        .header-role {
            font-size: var(--font-size-xs);
            color: var(--color-text-secondary);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-sm) var(--space-md);
            background: var(--color-surface);
            color: var(--color-text);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            text-decoration: none;
            font-weight: 500;
            transition: all var(--transition-base);
        }

        .logout-btn:hover {
            background: var(--color-danger);
            color: white;
            border-color: var(--color-danger);
            transform: translateY(-2px);
        }

        /* ==========================================================================
       CONTENIDO PRINCIPAL
       ========================================================================== */
        .main-content {
            flex: 1;
            padding: var(--space-lg);
            transition: all var(--transition-base);
            min-height: 100vh;
            background-color: transparent;
            width: 100%;
        }

        /* ==========================================================================
       COMPONENTES DE DASHBOARD (Ventas)
       ========================================================================== */

        /* Tarjetas de estadísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: var(--space-lg);
            margin-bottom: var(--space-xl);
        }

        .stat-card {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            transition: all var(--transition-base);
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
            border-color: var(--color-primary);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--color-primary), var(--color-info));
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--space-md);
        }

        .stat-title {
            font-size: var(--font-size-sm);
            color: var(--color-text-secondary);
            font-weight: 500;
            margin-bottom: var(--space-xs);
        }

        .stat-value {
            font-size: var(--font-size-3xl);
            font-weight: 700;
            color: var(--color-text);
            line-height: 1;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.primary {
            background: rgba(var(--color-primary-rgb), 0.1);
            color: var(--color-primary);
        }

        .stat-icon.success {
            background: rgba(var(--color-success-rgb), 0.1);
            color: var(--color-success);
        }

        .stat-icon.warning {
            background: rgba(var(--color-warning-rgb), 0.1);
            color: var(--color-warning);
        }

        .stat-icon.info {
            background: rgba(var(--color-info-rgb), 0.1);
            color: var(--color-info);
        }

        /* Punto de Venta */
        .pos-container {
            display: grid;
            grid-template-columns: 1.5fr 450px;
            gap: var(--space-xl);
            margin-bottom: var(--space-xl);
        }

        @media (max-width: 1200px) {
            .pos-container {
                grid-template-columns: 1fr;
            }
        }

        /* Área de búsqueda */
        .pos-selection-area {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            transition: all var(--transition-base);
        }

        .pos-selection-area:hover {
            box-shadow: var(--shadow-lg);
        }

        .section-title {
            font-size: var(--font-size-xl);
            font-weight: 600;
            color: var(--color-text);
            margin-bottom: var(--space-lg);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .section-title-icon {
            color: var(--color-primary);
        }

        /* Selection header and mode buttons */
        .selection-header {
            margin-bottom: var(--space-lg);
        }

        .mode-toggles.btn-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .mode-toggles .btn {
            flex: 1;
            min-width: 100px;
            padding: 0.625rem 1rem;
            font-weight: 600;
            font-size: 0.9rem;
            border-radius: var(--radius-md);
            border: 2px solid;
            transition: all var(--transition-base);
        }

        .mode-toggles .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .mode-toggles .btn-primary {
            background: var(--color-primary);
            border-color: var(--color-primary);
            color: white;
        }

        .mode-toggles .btn-outline-primary {
            background: transparent;
            border-color: var(--color-primary);
            color: var(--color-primary);
        }

        .mode-toggles .btn-outline-primary:hover {
            background: rgba(var(--color-primary-rgb), 0.1);
        }

        .mode-toggles .btn-info {
            background: var(--color-info);
            border-color: var(--color-info);
        }

        .mode-toggles .btn-outline-info {
            background: transparent;
            border-color: var(--color-info);
            color: var(--color-info);
        }

        .mode-toggles .btn-outline-info:hover {
            background: rgba(var(--color-info-rgb), 0.1);
        }

        .mode-toggles .btn-success {
            background: var(--color-success);
            border-color: var(--color-success);
        }

        .mode-toggles .btn-outline-success {
            background: transparent;
            border-color: var(--color-success);
            color: var(--color-success);
        }

        .mode-toggles .btn-outline-success:hover {
            background: rgba(var(--color-success-rgb), 0.1);
        }

        .mode-toggles .btn-warning {
            background: var(--color-warning);
            border-color: var(--color-warning);
        }

        .mode-toggles .btn-outline-warning {
            background: transparent;
            border-color: var(--color-warning);
            color: var(--color-warning);
        }

        .mode-toggles .btn-outline-warning:hover {
            background: rgba(var(--color-warning-rgb), 0.1);
        }

        .search-container {
            position: relative;
            margin-bottom: var(--space-lg);
        }

        /* Enhanced Search Styling */
        .search-container .input-group {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            transition: all var(--transition-base);
            padding: 4px;
            /* Space for inner float */
            box-shadow: var(--shadow-sm);
        }

        .search-container .input-group:focus-within {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 4px rgba(var(--color-primary-rgb), 0.1);
            transform: translateY(-1px);
        }

        .search-container .input-group-text {
            background: transparent;
            border: none;
            color: var(--color-primary);
            font-size: 1.2rem;
            padding-left: var(--space-md);
        }

        .search-container .form-control {
            border: none;
            background: transparent;
            padding: var(--space-md);
            font-size: 1.05rem;
            color: var(--color-text);
            flex: 1;
            width: 100%;
        }

        .search-container .form-control:focus {
            box-shadow: none;
            background: transparent;
        }

        .search-container .form-control::placeholder {
            color: var(--color-text-secondary);
            opacity: 0.7;
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-xl);
            max-height: 400px;
            overflow-y: auto;
            z-index: 100;
            display: none;
            margin-top: 0.5rem;
        }

        .search-result-item {
            padding: 1rem;
            border-bottom: 1px solid var(--color-border);
            cursor: pointer;
            transition: all var(--transition-base);
            background: var(--color-card);
        }

        .search-result-item:hover {
            background: linear-gradient(135deg, rgba(var(--color-primary-rgb), 0.08), rgba(var(--color-info-rgb), 0.05));
            padding-left: 1.25rem;
            border-left: 4px solid var(--color-primary);
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-item .row {
            align-items: center;
        }

        .search-result-item .fw-bold {
            font-size: 1.1rem;
        }

        /* Detalles de selección */
        .selection-details {
            display: none;
            margin-top: var(--space-lg);
        }

        .selected-product {
            background: linear-gradient(135deg, rgba(var(--color-primary-rgb), 0.05), rgba(var(--color-info-rgb), 0.05));
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            border: 1px solid var(--color-border);
        }

        .selected-product-name {
            font-size: var(--font-size-xl);
            font-weight: 700;
            color: var(--color-text);
            margin: 0;
        }

        .selected-product-details {
            font-size: var(--font-size-sm);
            margin: 0;
        }

        /* Tarjetas de información del producto */
        .row.g-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
        }

        .row.g-2 .col-12 {
            grid-column: span 2;
        }

        .row.g-2 .col-6 {
            grid-column: span 1;
        }

        .p-2.bg-light {
            background: var(--color-surface);
            padding: 1rem;
            border-radius: var(--radius-md);
            border: 1px solid var(--color-border);
            transition: all var(--transition-base);
            box-shadow: var(--shadow-sm);
        }

        .p-2.bg-light:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--color-primary);
        }

        .p-2.bg-light .small {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
            display: block;
        }

        .p-2.bg-light .fw-bold {
            font-size: 1.25rem;
            font-weight: 700;
        }

        .p-2.bg-light .fw-bold.fs-5 {
            font-size: 1.75rem;
        }

        .selection-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-md);
            margin-top: var(--space-lg);
            padding-top: var(--space-lg);
            border-top: 2px dashed var(--color-border);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: var(--space-xs);
        }

        .form-label {
            font-size: var(--font-size-sm);
            font-weight: 500;
            color: var(--color-text);
        }

        .form-input {
            padding: var(--space-sm) var(--space-md);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            background: var(--color-card);
            color: var(--color-text);
            font-size: var(--font-size-base);
            transition: all var(--transition-base);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.25);
        }

        .add-button {
            grid-column: span 2;
            padding: var(--space-md);
            background: var(--color-primary);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-base);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-sm);
        }

        .add-button:hover {
            background: var(--color-primary);
            opacity: 0.9;
            transform: translateY(-2px);
        }

        /* Área del carrito */
        .pos-cart-area {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            transition: all var(--transition-base);
            display: flex;
            flex-direction: column;
        }

        .pos-cart-area:hover {
            box-shadow: var(--shadow-lg);
        }

        .cart-header {
            margin-bottom: var(--space-lg);
            padding-bottom: var(--space-md);
            border-bottom: 1px solid var(--color-border);
        }

        .cart-items {
            flex: 1;
            overflow-y: auto;
            margin-bottom: var(--space-lg);
            min-height: 300px;
        }

        .cart-items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .cart-items-table th {
            padding: var(--space-sm) var(--space-md);
            text-align: left;
            font-weight: 600;
            color: var(--color-text);
            border-bottom: 2px solid var(--color-border);
            font-size: var(--font-size-sm);
        }

        .cart-items-table td {
            padding: var(--space-md);
            border-bottom: 1px solid var(--color-border);
            vertical-align: middle;
        }

        .cart-item-product {
            display: flex;
            flex-direction: column;
            gap: var(--space-xs);
        }

        .cart-item-name {
            font-weight: 600;
            color: var(--color-text);
        }

        .cart-item-details {
            font-size: var(--font-size-sm);
            color: var(--color-text-secondary);
        }

        .remove-button {
            background: none;
            border: none;
            color: var(--color-danger);
            cursor: pointer;
            padding: var(--space-xs);
            border-radius: var(--radius-sm);
            transition: all var(--transition-base);
        }

        .remove-button:hover {
            background: rgba(var(--color-danger-rgb), 0.1);
        }

        .cart-footer {
            margin-top: auto;
        }

        .cart-total {
            background: var(--color-primary);
            color: white;
            padding: var(--space-lg);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-md);
            text-align: right;
        }

        .total-label {
            font-size: var(--font-size-sm);
            opacity: 0.9;
            margin-bottom: var(--space-xs);
        }

        .total-amount {
            font-size: var(--font-size-2xl);
            font-weight: 700;
        }

        .cart-actions {
            display: flex;
            gap: var(--space-md);
        }

        .checkout-button {
            flex: 1;
            padding: var(--space-md);
            background: var(--color-success);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-base);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-sm);
        }

        .checkout-button:hover {
            background: var(--color-success);
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .clear-button {
            padding: var(--space-md) var(--space-lg);
            background: var(--color-surface);
            color: var(--color-text);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-base);
        }

        .clear-button:hover {
            background: var(--color-surface);
            border-color: var(--color-danger);
            color: var(--color-danger);
        }

        /* Estados vacíos */
        .empty-state {
            text-align: center;
            padding: var(--space-xl);
            color: var(--color-text-secondary);
        }

        .empty-icon {
            font-size: 3rem;
            color: var(--color-border);
            margin-bottom: var(--space-md);
            opacity: 0.5;
        }

        /* ==========================================================================
       ANIMACIONES DE ENTRADA
       ========================================================================== */
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

        .animate-in {
            animation: fadeInUp 0.6s ease-out forwards;
        }

        .delay-1 {
            animation-delay: 0.1s;
        }

        .delay-2 {
            animation-delay: 0.2s;
        }

        .delay-3 {
            animation-delay: 0.3s;
        }

        .delay-4 {
            animation-delay: 0.4s;
        }

        /* ==========================================================================
       RESPONSIVE DESIGN
       ========================================================================== */
        @media (max-width: 991px) {
            .dashboard-container {
                width: 100%;
            }

            .main-content {
                padding: var(--space-md);
            }

            .mobile-toggle {
                display: none;
            }

            .header-content {
                padding: var(--space-md);
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--space-md);
            }

            .pos-container {
                grid-template-columns: 1fr;
                gap: var(--space-md);
            }
        }

        @media (max-width: 767px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .header-content {
                flex-wrap: wrap;
            }

            .header-controls {
                width: 100%;
                justify-content: space-between;
                margin-top: var(--space-md);
            }

            .selection-form {
                grid-template-columns: 1fr;
            }

            .add-button {
                grid-column: span 1;
            }

            .cart-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: var(--space-sm);
            }

            .stat-card {
                padding: var(--space-md);
            }

            .stat-value {
                font-size: var(--font-size-2xl);
            }

            .pos-selection-area,
            .pos-cart-area {
                padding: var(--space-md);
            }

            .section-title {
                font-size: var(--font-size-lg);
            }
        }

        /* ==========================================================================
       UTILIDADES
       ========================================================================== */
        .text-primary {
            color: var(--color-primary);
        }

        .text-success {
            color: var(--color-success);
        }

        .text-warning {
            color: var(--color-warning);
        }

        .text-danger {
            color: var(--color-danger);
        }

        .text-info {
            color: var(--color-info);
        }

        .text-muted {
            color: var(--color-text-secondary);
        }

        .bg-primary {
            background-color: var(--color-primary);
        }

        .bg-success {
            background-color: var(--color-success);
        }

        .bg-warning {
            background-color: var(--color-warning);
        }

        .bg-danger {
            background-color: var(--color-danger);
        }

        .bg-info {
            background-color: var(--color-info);
        }

        .mb-0 {
            margin-bottom: 0;
        }

        .mb-1 {
            margin-bottom: var(--space-xs);
        }

        .mb-2 {
            margin-bottom: var(--space-sm);
        }

        .mb-3 {
            margin-bottom: var(--space-md);
        }

        .mb-4 {
            margin-bottom: var(--space-lg);
        }

        .mb-5 {
            margin-bottom: var(--space-xl);
        }

        .mt-0 {
            margin-top: 0;
        }

        .mt-1 {
            margin-top: var(--space-xs);
        }

        .mt-2 {
            margin-top: var(--space-sm);
        }

        .mt-3 {
            margin-top: var(--space-md);
        }

        .mt-4 {
            margin-top: var(--space-lg);
        }

        .mt-5 {
            margin-top: var(--space-xl);
        }

        .d-none {
            display: none;
        }

        .d-block {
            display: block;
        }

        .d-flex {
            display: flex;
        }

        .gap-1 {
            gap: var(--space-xs);
        }

        .gap-2 {
            gap: var(--space-sm);
        }

        .gap-3 {
            gap: var(--space-md);
        }

        .gap-4 {
            gap: var(--space-lg);
        }

        .gap-5 {
            gap: var(--space-xl);
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-left {
            text-align: left;
        }

        .fw-bold {
            font-weight: 700;
        }

        .fw-semibold {
            font-weight: 600;
        }

        .fw-medium {
            font-weight: 500;
        }

        .fw-normal {
            font-weight: 400;
        }

        .fw-light {
            font-weight: 300;
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
                    <img src="../../assets/img/herrerasaenz.png" alt="Centro Médico Herrera Saenz" class="brand-logo">
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

                        <div class="d-flex justify-content-end mb-3">
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

                    <!-- Detalles de selección -->
                    <div class="selection-details" id="selectionDetails">
                        <div class="selected-product mb-4">
                            <h4 class="selected-product-name mb-2" id="selectedProductName">---</h4>
                            <p class="selected-product-details text-muted mb-3" id="selectedProductDetails">---</p>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <div class="p-2 bg-light rounded text-center">
                                        <div class="small text-muted">Disponible</div>
                                        <div class="fw-bold text-primary fs-5" id="availableStock">0</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-2 bg-light rounded text-center">
                                        <div class="small text-muted">Documento</div>
                                        <div class="fw-bold text-info" id="documentType">---</div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="p-2 bg-light rounded text-center">
                                        <div class="small text-muted">Fecha de Vencimiento</div>
                                        <div class="fw-bold" id="expiryDate" style="color: var(--color-warning);">---
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <form class="selection-form" id="addToCartForm">
                            <div class="form-group">
                                <label class="form-label">Precio Unitario</label>
                                <div class="d-flex align-items-center">
                                    <span class="me-2">Q</span>
                                    <input type="number" class="form-input" id="unitPrice" step="0.01" min="0" required
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

    <!-- JavaScript Optimizado (mismo que dashboard con funcionalidad POS) -->
    <script>
        // Dashboard Reingenierizado - Centro Médico Herrera Saenz
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

                            const expiryDate = item.fecha_vencimiento ? new Date(item.fecha_vencimiento).toLocaleDateString('es-GT', { day: '2-digit', month: '2-digit', year: '2-digit' }) : 'N/A';

                            resultItem.innerHTML = `
                                <div class="row g-0 align-items-center">
                                    <div class="col-5">
                                        <div style="font-weight: 600;">${item.nom_medicamento}</div>
                                        <div style="font-size: 0.75rem; color: var(--color-text-secondary);">
                                            ${item.mol_medicamento} • ${item.presentacion_med}
                                        </div>
                                        <div class="${stockClass}" style="font-size: 0.75rem; font-weight: 600;">
                                            ${stockAvailable} disp. ${currentMode === 'hospital' ? '(H)' : ''}
                                        </div>
                                    </div>
                                    <div class="col-2 text-center fw-bold">Q${price.toFixed(2)}</div>
                                    <div class="col-3 text-center small text-info">${item.document_number || (item.document_type || 'N/A')}</div>
                                    <div class="col-2 text-end small text-muted">${expiryDate}</div>
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
                    if (DOM.documentType) {
                        DOM.documentType.textContent = item.document_number || item.document_type || 'N/A';
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

                    // Permitir Enter en cantidad para agregar
                    DOM.quantity.addEventListener('keypress', (e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            this.addToCart();
                        }
                    });

                    // Vaciar carrito
                    DOM.clearCartBtn.addEventListener('click', () => this.clearCart());

                    // Procesar venta
                    DOM.checkoutBtn.addEventListener('click', () => this.processSale());
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

                    // Preparar datos de la venta
                    const saleData = {
                        nombre_cliente: DOM.clientName.value.trim(),
                        nit_cliente: document.getElementById('clientNIT').value.trim() || 'C/F',
                        tipo_pago: currentMode === 'transfer' ? 'Traslado' : DOM.paymentMethod.value,
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

                    // Mostrar carga
                    Swal.fire({
                        title: 'Procesando venta...',
                        text: 'Por favor espere',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });

                    try {
                        const response = await fetch('save_venta.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
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
                                // Abrir recibo en nueva pestaña
                                window.open(`print_receipt.php?id=${data.id_venta}`, '_blank');

                                // Recargar página
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

                resetSelection() {
                    selectedItem = null;
                    DOM.selectionDetails.style.display = 'none';
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
                console.log('Módulo de Ventas - Centro Médico Herrera Saenz');
                console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
                console.log('Productos disponibles: <?php echo count($inventario); ?>');
                console.log('Ventas hoy: <?php echo $today_sales['count'] ?? 0; ?>');
                console.log('Total ventas hoy: Q<?php echo number_format($today_sales['total'] ?? 0, 2); ?>');
            });
        })();
    </script>
</body>

</html>