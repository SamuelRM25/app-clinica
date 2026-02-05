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

    // Obtener todas las ventas con paginación
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    // Obtener total de registros para paginación
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM ventas");
    $stmt->execute();
    $total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $limit);

    // Obtener datos de ventas con paginación
    $stmt = $conn->prepare("
        SELECT v.*, u.nombre as vendedor_nombre, u.apellido as vendedor_apellido
        FROM ventas v
        LEFT JOIN usuarios u ON v.id_usuario = u.idUsuario
        ORDER BY v.fecha_venta DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
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

    <!-- CSS Crítico (incrustado para máxima velocidad) -->
    <style>
        /* ==========================================================================
       VARIABLES CSS PARA TEMA DÍA/NOCHE
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
            /* Apilar Header y Main verticalmente */
            min-height: 100vh;
            position: relative;
            width: 100%;
            /* Asegurar que no se desborde */
            transition: all var(--transition-base);
        }

        /* User Avatar (Sidebar replacement) */
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
            /* Usar fallback sólido si rgba falla, pero definir rgb variables arriba */
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

        /* Control de tema */
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

        /* Información usuario en header */
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

        /* Botón de cerrar sesión */
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
            /* Margin-left movido al contenedor padre */
            transition: all var(--transition-base);
            min-height: 100vh;
            background-color: transparent;
            width: 100%;
        }



        /* ==========================================================================
       COMPONENTES DE DASHBOARD
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

        .stat-change {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            font-size: var(--font-size-sm);
            color: var(--color-text-secondary);
        }

        .stat-change.positive {
            color: var(--color-success);
        }

        /* Secciones */
        .sales-section {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            margin-bottom: var(--space-lg);
            transition: all var(--transition-base);
        }

        .sales-section:hover {
            box-shadow: var(--shadow-lg);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-lg);
            flex-wrap: wrap;
            gap: var(--space-md);
        }

        .section-title {
            font-size: var(--font-size-xl);
            font-weight: 600;
            color: var(--color-text);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .section-title-icon {
            color: var(--color-primary);
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-sm) var(--space-md);
            background: var(--color-primary);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 500;
            text-decoration: none;
            transition: all var(--transition-base);
            cursor: pointer;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            background: var(--color-primary);
            opacity: 0.9;
            color: white;
        }

        /* Tablas */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .sales-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .sales-table thead {
            background: var(--color-surface);
        }

        .sales-table th {
            padding: var(--space-md);
            text-align: left;
            font-weight: 600;
            color: var(--color-text);
            border-bottom: 2px solid var(--color-border);
            white-space: nowrap;
        }

        .sales-table td {
            padding: var(--space-md);
            border-bottom: 1px solid var(--color-border);
            vertical-align: middle;
        }

        .sales-table tbody tr {
            transition: all var(--transition-base);
        }

        .sales-table tbody tr:hover {
            background: var(--color-surface);
            transform: translateX(4px);
        }

        /* Celdas personalizadas */
        .sale-cell {
            display: flex;
            align-items: center;
            gap: var(--space-md);
        }

        .sale-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--color-success), var(--color-info));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: var(--font-size-base);
            flex-shrink: 0;
        }

        .sale-info {
            min-width: 0;
        }

        .sale-number {
            font-weight: 600;
            color: var(--color-text);
            margin-bottom: 2px;
        }

        .sale-time {
            color: var(--color-text-secondary);
            font-size: var(--font-size-sm);
        }

        .client-name {
            font-weight: 600;
            color: var(--color-text);
            margin-bottom: 2px;
        }

        .client-type {
            color: var(--color-text-secondary);
            font-size: var(--font-size-sm);
        }

        .payment-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
            padding: var(--space-xs) var(--space-sm);
            background: var(--color-surface);
            color: var(--color-text);
            border-radius: var(--radius-sm);
            font-size: var(--font-size-sm);
            font-weight: 500;
        }

        .amount-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
            padding: var(--space-xs) var(--space-sm);
            background: rgba(var(--color-success-rgb), 0.1);
            color: var(--color-success);
            border: 1px solid rgba(var(--color-success-rgb), 0.3);
            border-radius: var(--radius-sm);
            font-size: var(--font-size-sm);
            font-weight: 600;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
            padding: var(--space-xs) var(--space-sm);
            border-radius: var(--radius-sm);
            font-size: var(--font-size-sm);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.pagado {
            background: rgba(var(--color-success-rgb), 0.1);
            color: var(--color-success);
            border: 1px solid rgba(var(--color-success-rgb), 0.3);
        }

        .status-badge.pendiente {
            background: rgba(var(--color-warning-rgb), 0.1);
            color: var(--color-warning);
            border: 1px solid rgba(var(--color-warning-rgb), 0.3);
        }

        .status-badge.cancelado {
            background: rgba(var(--color-danger-rgb), 0.1);
            color: var(--color-danger);
            border: 1px solid rgba(var(--color-danger-rgb), 0.3);
        }

        /* Botones de acción */
        .action-buttons {
            display: flex;
            gap: var(--space-xs);
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--color-border);
            background: var(--color-surface);
            color: var(--color-text);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all var(--transition-base);
        }

        .btn-icon:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .btn-icon.view:hover {
            background: var(--color-info);
            color: white;
            border-color: var(--color-info);
        }

        .btn-icon.print:hover {
            background: var(--color-warning);
            color: white;
            border-color: var(--color-warning);
        }

        .btn-icon.edit:hover {
            background: var(--color-primary);
            color: white;
            border-color: var(--color-primary);
        }

        /* Estado vacío */
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

        /* Grid de alertas */
        .alerts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: var(--space-lg);
            margin-bottom: var(--space-xl);
        }

        .alert-card {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            transition: all var(--transition-base);
        }

        .alert-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .alert-header {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            margin-bottom: var(--space-lg);
        }

        .alert-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .alert-icon.warning {
            background: rgba(var(--color-warning-rgb), 0.1);
            color: var(--color-warning);
        }

        .alert-icon.danger {
            background: rgba(var(--color-danger-rgb), 0.1);
            color: var(--color-danger);
        }

        .alert-icon.info {
            background: rgba(var(--color-info-rgb), 0.1);
            color: var(--color-info);
        }

        .alert-title {
            font-size: var(--font-size-lg);
            font-weight: 600;
            color: var(--color-text);
            margin: 0;
        }

        .alert-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: var(--space-md);
        }

        .alert-item {
            padding: var(--space-md);
            background: var(--color-surface);
            border-radius: var(--radius-md);
            border-left: 4px solid var(--color-border);
            transition: all var(--transition-base);
        }

        .alert-item:hover {
            transform: translateX(4px);
            border-left-color: var(--color-warning);
        }

        .alert-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-xs);
        }

        .alert-item-name {
            font-weight: 500;
            color: var(--color-text);
        }

        .alert-badge {
            padding: 0.25em 0.5em;
            border-radius: var(--radius-sm);
            font-size: var(--font-size-xs);
            font-weight: 600;
        }

        .alert-badge.warning {
            background: rgba(var(--color-warning-rgb), 0.1);
            color: var(--color-warning);
        }

        .alert-badge.danger {
            background: rgba(var(--color-danger-rgb), 0.1);
            color: var(--color-danger);
        }

        .alert-badge.success {
            background: rgba(var(--color-success-rgb), 0.1);
            color: var(--color-success);
        }

        .alert-item-details {
            display: flex;
            justify-content: space-between;
            font-size: var(--font-size-sm);
            color: var(--color-text-secondary);
        }

        .no-alerts {
            text-align: center;
            padding: var(--space-lg);
            color: var(--color-text-secondary);
        }

        .no-alerts-icon {
            font-size: 2rem;
            color: var(--color-success);
            margin-bottom: var(--space-md);
            opacity: 0.5;
        }

        /* ==========================================================================
       RESPONSIVE DESIGN
       ========================================================================== */

        /* Pantallas grandes (TV, monitores 4K) */
        @media (min-width: 1600px) {
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
            }

            .main-content {
                max-width: 1800px;
                margin: 0 auto;
                padding: var(--space-xl);
            }
        }

        /* Escritorio estándar */
        @media (max-width: 1399px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            }
        }

        /* Tablets y pantallas medianas */
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

            .alerts-grid {
                grid-template-columns: 1fr;
            }

            .section-header {
                flex-direction: column;
                align-items: stretch;
                gap: var(--space-md);
            }

            .section-title {
                font-size: var(--font-size-lg);
            }

            .action-btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Móviles */
        @media (max-width: 767px) {

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .brand-logo {
                height: 32px;
            }

            .header-content {
                flex-wrap: wrap;
            }

            .header-controls {
                order: 3;
                width: 100%;
                justify-content: space-between;
                margin-top: var(--space-md);
            }

            .theme-btn {
                width: 40px;
                height: 40px;
            }

            .logout-btn span {
                display: none;
            }

            .logout-btn {
                padding: var(--space-sm);
            }

            .sales-table {
                font-size: var(--font-size-sm);
            }

            .sales-table th,
            .sales-table td {
                padding: var(--space-sm);
            }

            .sale-cell {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--space-xs);
            }

            .sale-avatar {
                width: 32px;
                height: 32px;
                font-size: var(--font-size-sm);
            }

            .stat-card {
                padding: var(--space-md);
            }

            .stat-value {
                font-size: var(--font-size-2xl);
            }

            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1.25rem;
            }
        }

        /* Móviles pequeños */
        @media (max-width: 480px) {
            .main-content {
                padding: var(--space-sm);
            }

            .stat-card {
                padding: var(--space-md);
            }

            .alert-card,
            .sales-section {
                padding: var(--space-md);
            }

            .section-title {
                font-size: var(--font-size-base);
            }

            .action-buttons {
                flex-direction: column;
                gap: var(--space-xs);
            }

            .btn-icon {
                width: 28px;
                height: 28px;
                font-size: 0.875rem;
            }
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
       ESTADOS DE CARGA
       ========================================================================== */
        .loading {
            position: relative;
            overflow: hidden;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% {
                left: -100%;
            }

            100% {
                left: 100%;
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

        /* ==========================================================================
       PRINT STYLES
       ========================================================================== */
        @media print {

            .dashboard-header,
            .theme-btn,
            .logout-btn,
            .action-btn {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }

            .marble-effect {
                display: none;
            }

            body {
                background: white !important;
                color: black !important;
            }

            .stat-card,
            .sales-section,
            .alert-card {
                break-inside: avoid;
                border: 1px solid #ddd !important;
                box-shadow: none !important;
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
                    <img src="../../assets/img/cmrs.png" alt="Centro Médico RS" class="brand-logo">
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
                                    <td>${item.nombre_medicamento || 'Producto'}</td>
                                    <td>${item.presentacion || 'N/A'}</td>
                                    <td class="text-center">${item.cantidad || 0}</td>
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