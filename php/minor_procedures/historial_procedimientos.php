<?php
// historial_procedimientos.php - Historial de Procedimientos Menores - Centro Médico Herrera Saenz
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

// Establecer zona horaria
date_default_timezone_set('America/Guatemala');
verify_session();

// Título de la página
$page_title = "Historial de Procedimientos - Centro Médico Herrera Saenz";

// Configuración de paginación
$limit = 20; // Registros por página
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page > 1) ? ($page - 1) * $limit : 0;

try {
    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener información del usuario
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['tipoUsuario'];
    $user_name = $_SESSION['nombre'];
    $user_specialty = $_SESSION['especialidad'] ?? 'Profesional Médico';

    // Obtener total de registros
    $stmt_count = $conn->query("SELECT COUNT(*) as total FROM procedimientos_menores");
    $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    $total_paginas = ceil($total_registros / $limit);

    // Obtener procedimientos paginados
    $stmt = $conn->prepare("
        SELECT id_procedimiento, nombre_paciente, procedimiento, cobro, fecha_procedimiento 
        FROM procedimientos_menores 
        ORDER BY fecha_procedimiento DESC 
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $procedimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Estadísticas adicionales
    $today = date('Y-m-d');
    $stmt_today = $conn->prepare("SELECT SUM(cobro) as total FROM procedimientos_menores WHERE DATE(fecha_procedimiento) = ?");
    $stmt_today->execute([$today]);
    $today_revenue = $stmt_today->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_end = date('Y-m-d', strtotime('sunday this week'));
    $stmt_week = $conn->prepare("SELECT SUM(cobro) as total FROM procedimientos_menores WHERE DATE(fecha_procedimiento) BETWEEN ? AND ?");
    $stmt_week->execute([$week_start, $week_end]);
    $week_revenue = $stmt_week->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

} catch (Exception $e) {
    // Manejo de errores
    error_log("Error en historial de procedimientos: " . $e->getMessage());
    $procedimientos = [];
    $total_paginas = 1;
    $today_revenue = 0;
    $week_revenue = 0;
    $error_message = "Error al cargar el historial: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Historial de Procedimientos Menores - Centro Médico Herrera Saenz">
    <title><?php echo $page_title; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">

    <!-- Google Fonts - Inter (moderno y legible) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

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

            /* Ancho Sidebar */
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 100px;
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
            margin-left: 0;
            transition: margin-left var(--transition-base);
            width: 100%;
        }

        /* ==========================================================================
       BARRA LATERAL MODERNA
       ========================================================================== */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background-color: var(--color-card);
            border-right: 1px solid var(--color-border);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            transition: all var(--transition-base);
            transform: translateX(0);
            box-shadow: var(--shadow-lg);
        }

        /* Estado colapsado */
        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
            transform: translateX(0);
        }

        .sidebar.collapsed .sidebar-header h2,
        .sidebar.collapsed .nav-link span:not(.badge),
        .sidebar.collapsed .user-info .user-details {
            opacity: 0;
            width: 0;
            height: 0;
            overflow: hidden;
            margin: 0;
            padding: 0;
        }

        /* Overlay para móvil */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity var(--transition-base);
        }

        /* Header sidebar */
        .sidebar-header {
            padding: var(--space-lg);
            border-bottom: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            gap: var(--space-md);
        }

        .sidebar-logo {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            overflow: hidden;
            flex-shrink: 0;
        }

        .sidebar-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .sidebar-header h2 {
            font-size: var(--font-size-xl);
            font-weight: 700;
            color: var(--color-primary);
            margin: 0;
            white-space: nowrap;
            transition: opacity var(--transition-base);
        }

        /* Navegación */
        .sidebar-nav {
            flex: 1;
            padding: var(--space-md);
            overflow-y: auto;
        }

        .nav-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: var(--space-xs);
        }

        .nav-item {
            position: relative;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            padding: var(--space-md);
            color: var(--color-text-secondary);
            text-decoration: none;
            border-radius: var(--radius-md);
            transition: all var(--transition-base);
            position: relative;
            overflow: hidden;
        }

        .nav-link:hover {
            background-color: var(--color-surface);
            color: var(--color-text);
            transform: translateX(4px);
        }

        .nav-link.active {
            background-color: var(--color-primary);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 60%;
            background-color: currentColor;
            border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
        }

        .nav-icon {
            font-size: 1.25rem;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .nav-text {
            font-weight: 500;
            white-space: nowrap;
            transition: opacity var(--transition-base);
        }

        .badge {
            margin-left: auto;
            font-size: var(--font-size-xs);
            padding: 0.25em 0.5em;
            min-width: 1.5rem;
            height: 1.5rem;
        }

        /* Footer sidebar */
        .sidebar-footer {
            padding: var(--space-md);
            border-top: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            gap: var(--space-md);
        }

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
            background: none;
            border: none;
            color: var(--color-text);
            font-size: 1.5rem;
            cursor: pointer;
            padding: var(--space-xs);
            border-radius: var(--radius-sm);
        }

        .mobile-toggle:hover {
            background-color: var(--color-surface);
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

        .sidebar.collapsed~.dashboard-container {
            margin-left: var(--sidebar-collapsed-width);
            width: calc(100% - var(--sidebar-collapsed-width));
        }

        /* Botón toggle sidebar (escritorio) */
        .sidebar-toggle {
            position: fixed;
            /* Ajustado para estar dentro del container que tiene margen */
            left: -12px;
            /* Relativo al dashboard-container */
            top: 50%;
            transform: translateY(-50%);
            width: 24px;
            height: 48px;
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-left: none;
            border-radius: 0 var(--radius-md) var(--radius-md) 0;
            color: var(--color-text);
            cursor: pointer;
            z-index: 1001;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--transition-base);
            box-shadow: var(--shadow-md);
        }

        .sidebar-toggle:hover {
            background: var(--color-primary);
            color: white;
            transform: translateY(-50%) scale(1.1);
        }

        /* Cuando el sidebar está colapsado, el dashboard-container reduce su margen,
       y el botón se mueve con él porque está dentro y es relative o fixed dentro?
       Espera, el botón tiene position: fixed. Fixed es relativo al VIEWPORT.
       Entonces `left: -12px` NO SIRVE si es relativo al viewport.
       Debe ser relativo al container O calculado desde la izquierda.
    */
        .sidebar-toggle {
            left: calc(var(--sidebar-width) - 12px);
        }

        .sidebar.collapsed+.dashboard-container .sidebar-toggle {
            left: calc(var(--sidebar-collapsed-width) - 12px);
        }

        .sidebar.collapsed .sidebar-toggle i {
            transform: rotate(180deg);
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
        .appointments-section {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            margin-bottom: var(--space-lg);
            transition: all var(--transition-base);
        }

        .appointments-section:hover {
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

        .appointments-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .appointments-table thead {
            background: var(--color-surface);
        }

        .appointments-table th {
            padding: var(--space-md);
            text-align: left;
            font-weight: 600;
            color: var(--color-text);
            border-bottom: 2px solid var(--color-border);
            white-space: nowrap;
        }

        .appointments-table td {
            padding: var(--space-md);
            border-bottom: 1px solid var(--color-border);
            vertical-align: middle;
        }

        .appointments-table tbody tr {
            transition: all var(--transition-base);
        }

        .appointments-table tbody tr:hover {
            background: var(--color-surface);
            transform: translateX(4px);
        }

        /* Celdas personalizadas */
        .patient-cell {
            display: flex;
            align-items: center;
            gap: var(--space-md);
        }

        .patient-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--color-primary), var(--color-info));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: var(--font-size-base);
            flex-shrink: 0;
        }

        .patient-info {
            min-width: 0;
        }

        .patient-name {
            font-weight: 600;
            color: var(--color-text);
            margin-bottom: 2px;
        }

        .patient-contact {
            color: var(--color-text-secondary);
            font-size: var(--font-size-sm);
        }

        .time-badge {
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

        .btn-icon.edit:hover {
            background: var(--color-warning);
            color: white;
            border-color: var(--color-warning);
        }

        .btn-icon.history:hover {
            background: var(--color-info);
            color: white;
            border-color: var(--color-info);
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

        .alert-badge.expired {
            background: rgba(var(--color-danger-rgb), 0.1);
            color: var(--color-danger);
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
       PAGINACIÓN
       ========================================================================== */
        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: var(--space-lg);
        }

        .pagination {
            display: flex;
            gap: var(--space-xs);
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .page-item {
            display: inline-block;
        }

        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 0 var(--space-sm);
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            color: var(--color-text);
            font-weight: 500;
            text-decoration: none;
            transition: all var(--transition-base);
        }

        .page-link:hover {
            background: var(--color-primary);
            color: white;
            border-color: var(--color-primary);
        }

        .page-item.active .page-link {
            background: var(--color-primary);
            color: white;
            border-color: var(--color-primary);
        }

        /* Separador de jornada */
        .jornada-row {
            background: var(--color-surface);
        }

        .jornada-cell {
            padding: var(--space-sm) var(--space-md);
            font-weight: 600;
            color: var(--color-primary);
            font-size: var(--font-size-sm);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--color-primary);
            opacity: 0.8;
        }

        .jornada-icon {
            margin-right: var(--space-xs);
        }

        /* Modal de reportes */
        .modal-content {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
        }

        .modal-header {
            border-bottom: 1px solid var(--color-border);
            padding: var(--space-lg);
        }

        .modal-title {
            font-weight: 600;
            color: var(--color-text);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .modal-body {
            padding: var(--space-lg);
        }

        .modal-footer {
            border-top: 1px solid var(--color-border);
            padding: var(--space-lg);
        }

        .form-label {
            font-weight: 500;
            color: var(--color-text);
            margin-bottom: var(--space-sm);
            display: block;
        }

        .form-control {
            width: 100%;
            padding: var(--space-md);
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            color: var(--color-text);
            font-size: var(--font-size-base);
            transition: all var(--transition-base);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.1);
        }

        /* ==========================================================================
       RESPONSIVE DESIGN
       ========================================================================== */

        /* Pantallas grandes (TV, monitores 4K) */
        @media (min-width: 1600px) {
            :root {
                --sidebar-width: 320px;
                --sidebar-collapsed-width: 100px;
            }

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
            :root {
                --sidebar-width: 280px;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .dashboard-container {
                margin-left: 0;
                width: 100%;
            }

            .sidebar-overlay.show {
                display: block;
                opacity: 1;
            }

            .main-content {
                margin-left: 0;
                padding: var(--space-md);
            }

            .sidebar-toggle {
                display: none;
            }

            .mobile-toggle {
                display: block;
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
            :root {
                --sidebar-width: 100%;
            }

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

            .appointments-table {
                font-size: var(--font-size-sm);
            }

            .appointments-table th,
            .appointments-table td {
                padding: var(--space-sm);
            }

            .patient-cell {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--space-xs);
            }

            .patient-avatar {
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
            .appointments-section {
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

            .pagination {
                flex-wrap: wrap;
                justify-content: center;
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

            .sidebar,
            .dashboard-header,
            .sidebar-toggle,
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
            .appointments-section,
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

    <!-- Sidebar removed -->

    <!-- Contenedor Principal -->
    <div class="dashboard-container">
        <!-- Header Superior -->
        <header class="dashboard-header">
            <div class="header-content">
                <!-- Botón hamburguesa para móvil -->
                <button class="mobile-toggle" id="mobileSidebarToggle" aria-label="Abrir menú">
                    <i class="bi bi-list"></i>
                </button>

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

                    <!-- Botón de cerrar sesión -->
                    <a href="../auth/logout.php" class="logout-btn">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Salir</span>
                    </a>
                </div>
            </div>
        </header>

        <!-- Sidebar toggle removed -->

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
                            <i class="bi bi-calendar-check me-1"></i> <?php echo date('d/m/Y'); ?>
                            <span class="mx-2">•</span>
                            <i class="bi bi-clock me-1"></i> <span id="current-time"><?php echo date('H:i'); ?></span>
                            <span class="mx-2">•</span>
                            <i class="bi bi-clock-history me-1"></i> Historial de Procedimientos
                        </p>
                    </div>
                    <div class="d-none d-md-block">
                        <i class="bi bi-clock-history text-primary" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>

            <!-- Estadísticas principales -->
            <?php if ($user_type === 'admin'): ?>
                <div class="stats-grid">
                    <!-- Total de procedimientos -->
                    <div class="stat-card animate-in delay-1">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Total Registros</div>
                                <div class="stat-value"><?php echo $total_registros; ?></div>
                            </div>
                            <div class="stat-icon primary">
                                <i class="bi bi-bandaid"></i>
                            </div>
                        </div>
                        <div class="stat-change positive">
                            <i class="bi bi-arrow-up-right"></i>
                            <span>Total en sistema</span>
                        </div>
                    </div>

                    <!-- Ingresos de hoy -->
                    <div class="stat-card animate-in delay-2">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Ingresos Hoy</div>
                                <div class="stat-value">Q<?php echo number_format($today_revenue, 2); ?></div>
                            </div>
                            <div class="stat-icon success">
                                <i class="bi bi-currency-dollar"></i>
                            </div>
                        </div>
                        <div class="stat-change positive">
                            <i class="bi bi-cash-stack"></i>
                            <span>Recaudado hoy</span>
                        </div>
                    </div>

                    <!-- Ingresos de la semana -->
                    <div class="stat-card animate-in delay-3">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Ingresos Semana</div>
                                <div class="stat-value">Q<?php echo number_format($week_revenue, 2); ?></div>
                            </div>
                            <div class="stat-icon warning">
                                <i class="bi bi-calendar-week"></i>
                            </div>
                        </div>
                        <div class="stat-change positive">
                            <i class="bi bi-calendar-range"></i>
                            <span>Esta semana</span>
                        </div>
                    </div>

                    <!-- Página actual -->
                    <div class="stat-card animate-in delay-4">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Página Actual</div>
                                <div class="stat-value"><?php echo $page; ?>/<?php echo $total_paginas; ?></div>
                            </div>
                            <div class="stat-icon info">
                                <i class="bi bi-file-text"></i>
                            </div>
                        </div>
                        <div class="stat-change positive">
                            <i class="bi bi-book"></i>
                            <span>Mostrando <?php echo $limit; ?> por página</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Historial de procedimientos -->
            <section class="appointments-section animate-in delay-1">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-clock-history section-title-icon"></i>
                        Historial de Procedimientos
                    </h3>
                    <div class="d-flex gap-2">
                        <a href="index.php" class="action-btn secondary">
                            <i class="bi bi-arrow-left"></i>
                            <span>Regresar</span>
                        </a>
                        <?php if ($user_type === 'admin'): ?>
                            <button type="button" class="action-btn" id="btnGenerateReport">
                                <i class="bi bi-file-earmark-pdf"></i>
                                <span>Reporte PDF</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger border-0 mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($procedimientos)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="bi bi-bandaid"></i>
                        </div>
                        <h4 class="text-muted mb-2">No se encontraron registros</h4>
                        <p class="text-muted mb-3">No hay procedimientos registrados en el sistema.</p>
                        <a href="index.php" class="action-btn">
                            <i class="bi bi-plus-lg"></i>
                            Registrar primer procedimiento
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="appointments-table">
                            <thead>
                                <tr>
                                    <th>Paciente</th>
                                    <th>Tipo de Procedimiento</th>
                                    <th>Cobro</th>
                                    <th>Fecha y Hora</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $prev_jornada = null;
                                foreach ($procedimientos as $proc):
                                    // Calcular fecha de jornada (Si es antes de las 8am, pertenece al día anterior)
                                    $timestamp = strtotime($proc['fecha_procedimiento']);
                                    $hora = (int) date('H', $timestamp);
                                    $fecha_base = date('Y-m-d', $timestamp);

                                    if ($hora < 8) {
                                        $jornada_date = date('Y-m-d', strtotime('-1 day', $timestamp));
                                    } else {
                                        $jornada_date = $fecha_base;
                                    }

                                    // Mostrar divisor si cambia la jornada
                                    if ($jornada_date !== $prev_jornada):
                                        $display_date = date('d/m/Y', strtotime($jornada_date));
                                        // Formato amigable: Hoy, Ayer, o fecha
                                        if ($jornada_date == date('Y-m-d')) {
                                            $display_text = "Jornada de Hoy ($display_date)";
                                        } elseif ($jornada_date == date('Y-m-d', strtotime('-1 day'))) {
                                            $display_text = "Jornada de Ayer ($display_date)";
                                        } else {
                                            $display_text = "Jornada del " . $display_date;
                                        }
                                        ?>
                                        <tr class="jornada-row">
                                            <td colspan="4" class="jornada-cell">
                                                <i class="bi bi-calendar-range jornada-icon"></i>
                                                <?php echo $display_text; ?>
                                            </td>
                                        </tr>
                                        <?php
                                        $prev_jornada = $jornada_date;
                                    endif;

                                    // Obtener iniciales del paciente
                                    $patient_name = htmlspecialchars($proc['nombre_paciente']);
                                    $patient_initials = strtoupper(substr($patient_name, 0, 2));
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
                                            <span
                                                class="procedure-type"><?php echo htmlspecialchars($proc['procedimiento']); ?></span>
                                        </td>
                                        <td>
                                            <span class="time-badge bg-success text-white">
                                                Q<?php echo number_format($proc['cobro'], 2); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="time-badge">
                                                <i class="bi bi-clock"></i>
                                                <?php echo date('h:i A', strtotime($proc['fecha_procedimiento'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <?php if ($total_paginas > 1): ?>
                        <div class="pagination-container">
                            <ul class="pagination">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <li class="page-item active">
                                    <span class="page-link"><?php echo $page; ?></span>
                                </li>

                                <?php if ($page < $total_paginas): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

            <!-- Resumen informativo -->
            <div class="stat-card animate-in delay-2">
                <div class="stat-header">
                    <div>
                        <div class="stat-title">Resumen del Sistema</div>
                        <div class="stat-value"><?php echo $total_registros; ?> Registros</div>
                        <div class="stat-change positive">
                            <i class="bi bi-database"></i>
                            <span>Base de datos activa</span>
                        </div>
                    </div>
                    <div class="stat-icon warning">
                        <i class="bi bi-bar-chart-line"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-muted mb-2">Mostrando página <?php echo $page; ?> de <?php echo $total_paginas; ?>
                        (<?php echo $limit; ?> registros por página)</p>
                    <p class="text-muted mb-2">Ingresos acumulados hoy:
                        <strong>Q<?php echo number_format($today_revenue, 2); ?></strong>
                    </p>
                    <p class="text-muted mb-0">Ingresos acumulados esta semana:
                        <strong>Q<?php echo number_format($week_revenue, 2); ?></strong>
                    </p>
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript Optimizado -->
    <script>
        // Historial de Procedimientos Reingenierizado - Centro Médico Herrera Saenz

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
                greetingElement: document.getElementById('greeting-text'),
                currentTimeElement: document.getElementById('current-time'),
                btnGenerateReport: document.getElementById('btnGenerateReport')
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
            // COMPONENTES DINÁMICOS
            // ==========================================================================
            class DynamicComponents {
                constructor() {
                    this.setupGreeting();
                    this.setupClock();
                    this.setupReportHandler();
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

                setupReportHandler() {
                    if (!DOM.btnGenerateReport) return;

                    DOM.btnGenerateReport.addEventListener('click', () => {
                        this.generateReport();
                    });
                }

                generateReport() {
                    const btn = DOM.btnGenerateReport;

                    // Estado de carga
                    btn.disabled = true;
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generando...';

                    // Obtener fecha actual
                    const today = new Date().toISOString().split('T')[0];

                    // En un sistema real, aquí se haría una petición fetch para generar el reporte
                    // Simulamos la generación del reporte
                    setTimeout(() => {
                        // Crear PDF con jsPDF
                        this.createPDFReport(today);

                        // Restaurar botón
                        btn.disabled = false;
                        btn.innerHTML = originalText;

                        // Mostrar notificación
                        this.showNotification('Reporte generado exitosamente', 'success');
                    }, 2000);
                }

                createPDFReport(date) {
                    // En un sistema real, se usaría una biblioteca como jsPDF
                    // Aquí simulamos la descarga de un archivo

                    // Crear contenido del reporte
                    const reportContent = `
                    Reporte de Procedimientos Menores
                    Centro Médico Herrera Saenz
                    Fecha: ${date}
                    ========================================
                    
                    Total de procedimientos: <?php echo $total_registros; ?>
                    Ingresos hoy: Q<?php echo number_format($today_revenue, 2); ?>
                    Ingresos esta semana: Q<?php echo number_format($week_revenue, 2); ?>
                    
                    Últimos procedimientos:
                    ------------------------
                    <?php
                    $count = 0;
                    foreach ($procedimientos as $proc):
                        if ($count++ >= 10)
                            break;
                        echo date('d/m/Y H:i', strtotime($proc['fecha_procedimiento'])) . ' - ' .
                            htmlspecialchars($proc['nombre_paciente']) . ' - ' .
                            htmlspecialchars($proc['procedimiento']) . ' - Q' .
                            number_format($proc['cobro'], 2) . "\\n";
                    endforeach;
                    ?>
                    
                    Generado por: <?php echo htmlspecialchars($user_name); ?>
                    Fecha de generación: <?php echo date('d/m/Y H:i'); ?>
                `;

                    // Crear blob y descargar
                    const blob = new Blob([reportContent], { type: 'text/plain' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `reporte_procedimientos_${date}.txt`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
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
                    document.querySelectorAll('.stat-card, .appointments-section').forEach(el => {
                        observer.observe(el);
                    });
                }

                showNotification(message, type = 'info') {
                    // Crear elemento de notificación
                    const notification = document.createElement('div');
                    notification.className = `alert alert-${type} border-0 shadow-lg`;
                    notification.style.position = 'fixed';
                    notification.style.top = '20px';
                    notification.style.right = '20px';
                    notification.style.zIndex = '9999';
                    notification.style.minWidth = '300px';
                    notification.style.animation = 'fadeInUp 0.3s ease-out';
                    notification.innerHTML = `
                    <div class="d-flex align-items-center">
                        <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                        <div>${message}</div>
                        <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
                    </div>
                `;

                    // Agregar al documento
                    document.body.appendChild(notification);

                    // Remover automáticamente después de 5 segundos
                    setTimeout(() => {
                        if (notification.parentElement) {
                            notification.style.animation = 'fadeInUp 0.3s ease-out reverse';
                            setTimeout(() => notification.remove(), 300);
                        }
                    }, 5000);
                }
            }

            // ==========================================================================
            // OPTIMIZACIONES DE RENDIMIENTO
            // ==========================================================================
            class PerformanceOptimizer {
                constructor() {
                    this.setupLazyLoading();
                    this.setupServiceWorker();
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

                setupServiceWorker() {
                    if ('serviceWorker' in navigator) {
                        window.addEventListener('load', () => {
                            navigator.serviceWorker.register('/sw.js').catch(error => {
                                console.log('ServiceWorker registration failed:', error);
                            });
                        });
                    }
                }

                setupAnalytics() {
                    // Aquí iría la configuración de Google Analytics u otro sistema de análisis
                    console.log('Historial de Procedimientos cargado - Usuario: <?php echo htmlspecialchars($user_name); ?>');
                }
            }

            // ==========================================================================
            // INICIALIZACIÓN DE LA APLICACIÓN
            // ==========================================================================
            document.addEventListener('DOMContentLoaded', () => {
                // Inicializar componentes
                const themeManager = new ThemeManager();
                const sidebarManager = new SidebarManager();
                const dynamicComponents = new DynamicComponents();
                const performanceOptimizer = new PerformanceOptimizer();

                // Exponer APIs necesarias globalmente
                window.historialProcedimientos = {
                    theme: themeManager,
                    sidebar: sidebarManager,
                    components: dynamicComponents
                };

                // Log de inicialización
                console.log('Historial de Procedimientos v4.0 inicializado correctamente');
                console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
                console.log('Rol: <?php echo htmlspecialchars($user_type); ?>');
                console.log('Tema: ' + themeManager.theme);
                console.log('Sidebar: ' + (sidebarManager.isCollapsed ? 'colapsado' : 'expandido'));
                console.log('Total de registros: <?php echo $total_registros; ?>');
                console.log('Página actual: <?php echo $page; ?> de <?php echo $total_paginas; ?>');
            });

            // ==========================================================================
            // MANEJO DE ERRORES GLOBALES
            // ==========================================================================
            window.addEventListener('error', (event) => {
                console.error('Error en historial de procedimientos:', event.error);

                // En producción, enviar error al servidor
                if (window.location.hostname !== 'localhost') {
                    const errorData = {
                        message: event.message,
                        source: event.filename,
                        lineno: event.lineno,
                        colno: event.colno,
                        user: '<?php echo htmlspecialchars($user_name); ?>',
                        timestamp: new Date().toISOString(),
                        module: 'historial_procedimientos'
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
            display: inline-block;
            width: 1rem;
            height: 1rem;
            vertical-align: text-bottom;
            border: 0.2em solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            animation: spinner-border .75s linear infinite;
        }
        @keyframes spinner-border {
            to { transform: rotate(360deg); }
        }
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
    `;
        document.head.appendChild(style);
    </script>
</body>

</html>