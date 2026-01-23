<?php
// historial_examenes.php - Historial de Exámenes - Centro Médico Herrera Saenz
// Versión: 4.0 - Estilo Dashboard Principal
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

try {
    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener información del usuario
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['tipoUsuario'];
    $user_name = $_SESSION['nombre'];
    $user_specialty = $_SESSION['especialidad'] ?? 'Profesional Médico';

    // Título de la página
    $page_title = "Historial de Exámenes - Centro Médico Herrera Saenz";

    // Configuración de paginación
    $limit = 20; // Registros por página
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $offset = ($page > 1) ? ($page - 1) * $limit : 0;

    // Obtener total de registros
    $stmt_count = $conn->query("SELECT COUNT(*) as total FROM examenes_realizados");
    $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    $total_paginas = ceil($total_registros / $limit);

    // Obtener exámenes paginados
    $stmt = $conn->prepare("
        SELECT id_examen_realizado, nombre_paciente, tipo_examen, cobro, fecha_examen 
        FROM examenes_realizados 
        ORDER BY fecha_examen DESC 
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $examenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Manejo de errores
    $examenes = [];
    $total_paginas = 1;
    $total_registros = 0;
    $error_message = "Error al cargar el historial: " . $e->getMessage();
    error_log("Error en historial_examenes: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Historial de Exámenes - Centro Médico Herrera Saenz">
    <title><?php echo $page_title; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">

    <!-- Google Fonts - Inter (moderno y legible) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- CSS Crítico (incrustado para máxima velocidad) - IDÉNTICO AL DASHBOARD -->
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
       COMPONENTES DE HISTORIAL DE EXÁMENES
       ========================================================================== */

        /* Bienvenida personalizada */
        .welcome-card {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            margin-bottom: var(--space-lg);
            transition: all var(--transition-base);
            position: relative;
            overflow: hidden;
        }

        .welcome-card:hover {
            box-shadow: var(--shadow-lg);
        }

        .welcome-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--space-md);
        }

        /* Tabla de historial */
        .history-section {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            margin-bottom: var(--space-lg);
            transition: all var(--transition-base);
        }

        .history-section:hover {
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

        .history-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .history-table thead {
            background: var(--color-surface);
        }

        .history-table th {
            padding: var(--space-md);
            text-align: left;
            font-weight: 600;
            color: var(--color-text);
            border-bottom: 2px solid var(--color-border);
            white-space: nowrap;
        }

        .history-table td {
            padding: var(--space-md);
            border-bottom: 1px solid var(--color-border);
            vertical-align: middle;
        }

        .history-table tbody tr {
            transition: all var(--transition-base);
        }

        .history-table tbody tr:hover {
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
        }

        .jornada-icon {
            margin-right: var(--space-sm);
        }

        /* Badges */
        .amount-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
            padding: var(--space-xs) var(--space-sm);
            background: rgba(var(--color-success-rgb), 0.1);
            color: var(--color-success);
            border-radius: var(--radius-sm);
            font-size: var(--font-size-sm);
            font-weight: 600;
            border: 1px solid var(--color-success);
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

        /* Paginación */
        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: var(--space-xl);
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

        /* Modal para reportes */
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

        /* ==========================================================================
       RESPONSIVE DESIGN
       ========================================================================== */

        /* Pantallas grandes (TV, monitores 4K) */
        @media (min-width: 1600px) {
            :root {
                --sidebar-width: 320px;
                --sidebar-collapsed-width: 100px;
            }

            .main-content {
                max-width: 1800px;
                margin: 0 auto;
                padding: var(--space-xl);
            }
        }

        /* Escritorio estándar */
        @media (max-width: 1399px) {
            /* Ajustes generales */
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

            .history-table {
                font-size: var(--font-size-sm);
            }

            .history-table th,
            .history-table td {
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
        }

        /* Móviles pequeños */
        @media (max-width: 480px) {
            .main-content {
                padding: var(--space-sm);
            }

            .history-section {
                padding: var(--space-md);
            }

            .section-title {
                font-size: var(--font-size-base);
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

            .history-section {
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
            <div class="welcome-card animate-in">
                <div class="welcome-header">
                    <div>
                        <h2 id="greeting" style="font-size: 1.75rem; margin-bottom: 0.5rem;">
                            <span id="greeting-text">Historial de Exámenes</span>
                        </h2>
                        <p class="text-muted mb-0">
                            <i class="bi bi-calendar-check me-1"></i> <?php echo date('d/m/Y'); ?>
                            <span class="mx-2">•</span>
                            <i class="bi bi-clock me-1"></i> <span id="current-time"><?php echo date('H:i'); ?></span>
                            <span class="mx-2">•</span>
                            <i class="bi bi-building me-1"></i> Centro Médico Herrera Saenz
                        </p>
                    </div>
                    <div class="d-none d-md-block">
                        <i class="bi bi-clipboard2-data text-primary" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-muted mb-0">
                        Visualice y administre el historial completo de exámenes realizados en el centro médico.
                    </p>
                </div>
            </div>

            <!-- Sección de historial -->
            <section class="history-section animate-in delay-1">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-clock-history section-title-icon"></i>
                        Exámenes Realizados
                    </h3>
                    <div class="d-flex gap-2">
                        <a href="index.php" class="action-btn secondary">
                            <i class="bi bi-arrow-left"></i>
                            <span>Regresar</span>
                        </a>
                        <button type="button" class="action-btn" data-bs-toggle="modal" data-bs-target="#reportModal">
                            <i class="bi bi-file-earmark-pdf"></i>
                            <span>Reporte</span>
                        </button>
                    </div>
                </div>

                <!-- Mensaje de error -->
                <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger border-0 mb-4" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                <?php endif; ?>

                <?php if (empty($examenes)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="bi bi-clipboard-x"></i>
                            </div>
                            <h4 class="text-muted mb-2">No se encontraron registros</h4>
                            <p class="text-muted mb-3">No hay exámenes registrados en el sistema.</p>
                            <a href="index.php" class="action-btn">
                                <i class="bi bi-plus-lg"></i>
                                Registrar primer examen
                            </a>
                        </div>
                <?php else: ?>
                        <div class="table-responsive">
                            <table class="history-table">
                                <thead>
                                    <tr>
                                        <th>Paciente</th>
                                        <th>Tipo de Examen</th>
                                        <th>Cobro</th>
                                        <th>Fecha y Hora</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $prev_jornada = null;
                                    foreach ($examenes as $exam):
                                        // Calcular fecha de jornada (Si es antes de las 8am, pertenece al día anterior)
                                        $timestamp = strtotime($exam['fecha_examen']);
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
                                        $patient_name = htmlspecialchars($exam['nombre_paciente']);
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
                                                    <span class="fw-medium"><?php echo htmlspecialchars($exam['tipo_examen']); ?></span>
                                                </td>
                                                <td>
                                                    <span class="amount-badge">
                                                        Q<?php echo number_format($exam['cobro'], 2); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="time-badge">
                                                        <i class="bi bi-clock"></i>
                                                        <?php echo date('h:i A', strtotime($exam['fecha_examen'])); ?>
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
                                            <span class="page-link"><?php echo $page; ?> de <?php echo $total_paginas; ?></span>
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

                        <div class="text-center mt-4">
                            <p class="text-muted">Total de registros: <?php echo $total_registros; ?></p>
                        </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <!-- Modal para reportes -->
    <div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-file-earmark-pdf text-primary"></i>
                        Reporte por Jornada
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        La jornada comprende desde las <strong>08:00 AM</strong> hasta las <strong>05:00 PM</strong>
                        (jornada diurna) o desde las <strong>05:00 PM</strong> hasta las <strong>08:00 AM</strong> del
                        día siguiente (jornada nocturna).
                    </p>
                    <div class="mb-4">
                        <label class="form-label fw-medium">Seleccionar Fecha de Jornada</label>
                        <input type="date" class="form-control" id="reportDate">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="action-btn secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="action-btn" id="btnGenerateReport">
                        <i class="bi bi-download"></i>
                        Generar Reporte
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- jsPDF para generación de PDFs -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>

    <!-- SweetAlert2 para alertas -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- JavaScript Optimizado -->
    <script>
        // Dashboard Reingenierizado - Centro Médico Herrera Saenz

        (function () {
            'use strict';

            // ==========================================================================
            // CONFIGURACIÓN Y CONSTANTES
            // ==========================================================================
            const CONFIG = {
                themeKey: 'dashboard-theme',
                sidebarKey: 'sidebar-collapsed',
                greetingKey: 'last-jornada-summary',
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
                reportDate: document.getElementById('reportDate'),
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
                    this.setupReportGeneration();
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

                    DOM.greetingElement.textContent = greeting + ', ' + '<?php echo htmlspecialchars($user_name); ?>';
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

                setupReportGeneration() {
                    if (!DOM.btnGenerateReport || !DOM.reportDate) return;

                    // Configurar fecha por defecto (hoy)
                    const today = new Date().toISOString().split('T')[0];
                    DOM.reportDate.value = today;

                    DOM.btnGenerateReport.addEventListener('click', () => {
                        this.generateReport();
                    });
                }

                generateReport() {
                    const date = DOM.reportDate.value;
                    const btn = DOM.btnGenerateReport;

                    if (!date) {
                        Swal.fire('Error', 'Por favor seleccione una fecha', 'warning');
                        return;
                    }

                    // Estado de carga
                    btn.disabled = true;
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generando...';

                    fetch(`get_report_data.php?date=${date}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok ' + response.statusText);
                            }
                            return response.json();
                        })
                        .then(res => {
                            if (res.status === 'success') {
                                this.generatePDF(res);

                                // Cerrar modal
                                const modalElement = document.getElementById('reportModal');
                                const modalInstance = bootstrap.Modal.getInstance(modalElement);
                                if (modalInstance) {
                                    modalInstance.hide();
                                }

                                // Mostrar confirmación
                                Swal.fire({
                                    title: '¡Reporte Generado!',
                                    text: 'El reporte se ha descargado correctamente.',
                                    icon: 'success',
                                    confirmButtonText: 'Aceptar'
                                });
                            } else {
                                Swal.fire('Error', res.message || 'Error desconocido', 'error');
                            }
                        })
                        .catch(err => {
                            Swal.fire('Error', 'Hubo un problema: ' + err.message, 'error');
                        })
                        .finally(() => {
                            btn.disabled = false;
                            btn.innerHTML = originalText;
                        });
                }

                generatePDF(res) {
                    const { jsPDF } = window.jspdf;
                    const doc = new jsPDF();

                    // Colores
                    const primaryColor = [13, 110, 253]; // Azul primario

                    // Encabezado
                    doc.setFillColor(...primaryColor);
                    doc.rect(0, 0, 210, 40, 'F');

                    doc.setTextColor(255, 255, 255);
                    doc.setFontSize(22);
                    doc.setFont('helvetica', 'bold');
                    doc.text("Centro Médico Herrera Saenz", 105, 18, { align: 'center' });

                    doc.setFontSize(14);
                    doc.setFont('helvetica', 'normal');
                    doc.text("Reporte de Exámenes Clínicos", 105, 28, { align: 'center' });

                    // Información del reporte
                    doc.setTextColor(50, 50, 50);
                    doc.setFontSize(10);
                    doc.setFont('helvetica', 'bold');
                    doc.text("Información del Reporte:", 14, 50);

                    doc.setFont('helvetica', 'normal');
                    doc.text(`Jornada Reportada: ${res.metadata.jornada_start} - ${res.metadata.jornada_end}`, 14, 56);
                    doc.text(`Generado por: ${res.metadata.generated_by}`, 14, 62);
                    doc.text(`Fecha de Creación: ${res.metadata.generated_at}`, 14, 68);

                    // Tabla de datos
                    const tableBody = res.data.map(item => [
                        item.nombre_paciente,
                        item.tipo_examen,
                        new Date(item.fecha_examen).toLocaleString('es-GT', {
                            year: 'numeric',
                            month: '2-digit',
                            day: '2-digit',
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: true
                        }),
                        `Q${parseFloat(item.cobro).toFixed(2)}`
                    ]);

                    doc.autoTable({
                        startY: 75,
                        head: [['Paciente', 'Examen', 'Fecha y Hora', 'Cobro']],
                        body: tableBody,
                        theme: 'grid',
                        headStyles: {
                            fillColor: primaryColor,
                            textColor: [255, 255, 255],
                            fontStyle: 'bold'
                        },
                        columnStyles: {
                            0: { cellWidth: 50 },
                            1: { cellWidth: 50 },
                            2: { cellWidth: 45 },
                            3: { cellWidth: 25, halign: 'right' }
                        },
                        foot: [['', '', 'TOTAL ACUMULADO', `Q${res.total.toFixed(2)}`]],
                        footStyles: {
                            fillColor: [240, 240, 240],
                            textColor: [0, 0, 0],
                            fontStyle: 'bold',
                            halign: 'right'
                        }
                    });

                    // Guardar archivo
                    const fileName = `Reporte_Examenes_${DOM.reportDate.value}.pdf`;
                    doc.save(fileName);
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
                    document.querySelectorAll('.welcome-card, .history-section').forEach(el => {
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
                const sidebarManager = new SidebarManager();
                const dynamicComponents = new DynamicComponents();

                // Exponer APIs necesarias globalmente
                window.dashboard = {
                    theme: themeManager,
                    sidebar: sidebarManager,
                    components: dynamicComponents
                };

                // Log de inicialización
                console.log('Historial de Exámenes CMS v4.0 inicializado correctamente');
                console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
                console.log('Rol: <?php echo htmlspecialchars($user_type); ?>');
                console.log('Tema: ' + themeManager.theme);
                console.log('Sidebar: ' + (sidebarManager.isCollapsed ? 'colapsado' : 'expandido'));
                console.log('Total de registros: <?php echo $total_registros; ?>');
            });

            // ==========================================================================
            // MANEJO DE ERRORES GLOBALES
            // ==========================================================================
            window.addEventListener('error', (event) => {
                console.error('Error en historial de exámenes:', event.error);
            });

        })();
    </script>
</body>

</html>