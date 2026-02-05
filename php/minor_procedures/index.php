<?php
// index.php - Procedimientos Menores - Centro Médico RS
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

try {
    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener información del usuario
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['tipoUsuario'];
    $user_name = $_SESSION['nombre'];
    $user_specialty = $_SESSION['especialidad'] ?? 'Profesional Médico';

    // ============ CONSULTAS ESTADÍSTICAS ============

    // 1. Procedimientos de hoy
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM procedimientos_menores WHERE DATE(fecha_procedimiento) = ?");
    $stmt->execute([$today]);
    $today_procedures = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 2. Ingresos de hoy
    $stmt = $conn->prepare("SELECT SUM(cobro) as total FROM procedimientos_menores WHERE DATE(fecha_procedimiento) = ?");
    $stmt->execute([$today]);
    $today_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 3. Procedimientos de esta semana
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_end = date('Y-m-d', strtotime('sunday this week'));
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM procedimientos_menores WHERE DATE(fecha_procedimiento) BETWEEN ? AND ?");
    $stmt->execute([$week_start, $week_end]);
    $week_procedures = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 4. Ingresos de esta semana
    $stmt = $conn->prepare("SELECT SUM(cobro) as total FROM procedimientos_menores WHERE DATE(fecha_procedimiento) BETWEEN ? AND ?");
    $stmt->execute([$week_start, $week_end]);
    $week_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 5. Procedimientos del mes actual
    $month_start = date('Y-m-01');
    $month_end = date('Y-m-t');
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM procedimientos_menores WHERE DATE(fecha_procedimiento) BETWEEN ? AND ?");
    $stmt->execute([$month_start, $month_end]);
    $month_procedures = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 6. Total de procedimientos en el sistema
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM procedimientos_menores");
    $stmt->execute();
    $total_procedures = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 7. Procedimientos recientes (últimos 5)
    $stmt = $conn->prepare("
        SELECT id_procedimiento, nombre_paciente, procedimiento, cobro, fecha_procedimiento 
        FROM procedimientos_menores 
        ORDER BY fecha_procedimiento DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 8. Obtener pacientes para el select
    $stmt_patients = $conn->prepare("
        SELECT id_paciente, 
               CONCAT(nombre, ' ', apellido) as nombre_completo,
               telefono,
               fecha_nacimiento
        FROM pacientes 
        ORDER BY nombre_completo ASC
    ");
    $stmt_patients->execute();
    $patients = $stmt_patients->fetchAll(PDO::FETCH_ASSOC);

    // Título de la página
    $page_title = "Procedimientos Menores - Centro Médico RS";

} catch (Exception $e) {
    // Manejo de errores
    error_log("Error en procedimientos menores: " . $e->getMessage());
    die("Error al cargar el módulo de procedimientos. Por favor, contacte al administrador.");
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Módulo de Procedimientos Menores - Centro Médico RS">
    <title><?php echo $page_title; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">

    <!-- Google Fonts - Inter (moderno y legible) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Choices.js (para búsqueda en selects) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">

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
       FORMULARIO ESPECÍFICO DE PROCEDIMIENTOS
       ========================================================================== */
        .form-container {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            margin-bottom: var(--space-lg);
            transition: all var(--transition-base);
        }

        .form-container:hover {
            box-shadow: var(--shadow-lg);
        }

        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-lg);
            padding-bottom: var(--space-md);
            border-bottom: 1px solid var(--color-border);
        }

        .form-title {
            font-size: var(--font-size-xl);
            font-weight: 600;
            color: var(--color-text);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .form-title-icon {
            color: var(--color-primary);
        }

        .form-group {
            margin-bottom: var(--space-lg);
        }

        .form-label {
            font-weight: 500;
            color: var(--color-text);
            margin-bottom: var(--space-sm);
            display: block;
            font-size: var(--font-size-sm);
        }

        .form-control,
        .form-select {
            width: 100%;
            padding: var(--space-md);
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            color: var(--color-text);
            font-size: var(--font-size-base);
            transition: all var(--transition-base);
        }

        .form-control:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.1);
        }

        .input-group {
            display: flex;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            overflow: hidden;
        }

        .input-group-text {
            background: var(--color-border);
            color: var(--color-text-secondary);
            padding: var(--space-md);
            border: none;
            font-size: var(--font-size-base);
        }

        .input-group .form-control {
            border: none;
            border-left: 1px solid var(--color-border);
            border-radius: 0;
        }

        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: var(--space-sm);
            margin-top: var(--space-sm);
        }

        .custom-checkbox {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-sm);
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            transition: all var(--transition-base);
        }

        .custom-checkbox:hover {
            background: var(--color-primary);
            color: white;
            border-color: var(--color-primary);
        }

        .custom-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .patient-info-card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: var(--space-md);
            margin-top: var(--space-sm);
            animation: fadeInUp 0.3s ease-out;
        }

        .patient-info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: var(--space-xs);
        }

        .patient-info-label {
            font-weight: 500;
            color: var(--color-text-light);
        }

        .patient-info-value {
            font-weight: 600;
            color: var(--color-text);
        }

        .additional-procedure {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: var(--space-md);
            margin-bottom: var(--space-md);
            animation: slideInUp 0.3s ease-out;
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

            .checkbox-grid {
                grid-template-columns: repeat(2, 1fr);
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

            .checkbox-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Choices.js Dark Mode Overrides */
        [data-theme="dark"] .choices__inner,
        [data-theme="dark"] .choices__input,
        [data-theme="dark"] .choices__list--dropdown,
        [data-theme="dark"] .choices__list[aria-expanded] .choices__list {
            background-color: var(--color-surface-night) !important;
            color: var(--color-text-night) !important;
            border-color: var(--color-border-night) !important;
        }

        [data-theme="dark"] .choices__list--dropdown .choices__item--selectable.is-highlighted,
        [data-theme="dark"] .choices__list[aria-expanded] .choices__item--selectable.is-highlighted {
            background-color: var(--color-border-night) !important;
        }

        [data-theme="dark"] .choices__input::placeholder {
            color: var(--color-text-secondary-night) !important;
            opacity: 0.7;
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
            .appointments-section,
            .form-container {
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
            .appointments-section,
            .alert-card,
            .form-container {
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

        <!-- Contenido Principal -->
        <main class="main-content">
            <?php if (isset($_GET['status']) && isset($_GET['message'])): ?>
                <div class="alert alert-<?php echo $_GET['status'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show mb-4 animate-in"
                    role="alert">
                    <div class="d-flex align-items-center">
                        <i
                            class="bi bi-<?php echo $_GET['status'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>-fill fs-4 me-2"></i>
                        <div>
                            <strong><?php echo $_GET['status'] === 'success' ? '¡Éxito!' : '¡Error!'; ?></strong>
                            <?php echo htmlspecialchars(urldecode($_GET['message'])); ?>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <script>
                    // Limpiar URL después de mostrar la alerta
                    if (history.replaceState) {
                        var url = new URL(window.location.href);
                        url.searchParams.delete('status');
                        url.searchParams.delete('message');
                        history.replaceState(null, '', url);
                    }
                </script>
            <?php endif; ?>

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
                            <i class="bi bi-bandaid me-1"></i> Procedimientos Menores
                        </p>
                    </div>
                    <div class="d-none d-md-block">
                        <i class="bi bi-bandaid-fill text-primary" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>

            <!-- Procedimientos recientes -->
            <section class="appointments-section animate-in delay-2">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-clock-history section-title-icon"></i>
                        Procedimientos Recientes
                    </h3>
                    <div class="d-flex gap-2">
                        <a href="historial_procedimientos.php" class="action-btn secondary">
                            <i class="bi bi-clock-history"></i>
                            Ver Historial
                        </a>
                        <button type="button" class="action-btn" onclick="refreshProcedures()">
                            <i class="bi bi-arrow-clockwise"></i>
                            Actualizar
                        </button>
                    </div>
                </div>

                <?php if (count($recent_procedures) > 0): ?>
                    <div class="table-responsive">
                        <table class="appointments-table">
                            <thead>
                                <tr>
                                    <th>Paciente</th>
                                    <th>Procedimiento</th>
                                    <th>Costo</th>
                                    <th>Fecha</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_procedures as $procedure): ?>
                                    <?php
                                    $patient_name = htmlspecialchars($procedure['nombre_paciente']);
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
                                            <div class="procedure-type">
                                                <?php echo htmlspecialchars($procedure['procedimiento']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="time-badge bg-success text-white">
                                                Q<?php echo number_format($procedure['cobro'], 2); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="time-badge">
                                                <i class="bi bi-clock"></i>
                                                <?php echo date('d/m/Y H:i', strtotime($procedure['fecha_procedimiento'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="#" class="btn-icon edit" title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="#" class="btn-icon history" title="Ver detalles">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 text-center">
                        <a href="historial_procedimientos.php" class="text-primary text-decoration-none">
                            Ver todos los procedimientos <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="bi bi-bandaid"></i>
                        </div>
                        <h4 class="text-muted mb-2">No hay procedimientos registrados</h4>
                        <p class="text-muted mb-3">Total en sistema: <?php echo $total_procedures; ?></p>
                        <p class="text-muted">Complete el formulario para registrar su primer procedimiento</p>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Estadísticas principales -->
            <?php if ($user_type === 'admin'): ?>
                <div class="stats-grid">
                    <!-- Procedimientos de hoy -->
                    <div class="stat-card animate-in delay-1">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Procedimientos Hoy</div>
                                <div class="stat-value"><?php echo $today_procedures; ?></div>
                            </div>
                            <div class="stat-icon primary">
                                <i class="bi bi-bandaid"></i>
                            </div>
                        </div>
                        <div class="stat-change positive">
                            <i class="bi bi-arrow-up-right"></i>
                            <span>Realizados hoy</span>
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
                            <span>Total recaudado</span>
                        </div>
                    </div>

                    <!-- Procedimientos de la semana -->
                    <div class="stat-card animate-in delay-3">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Esta Semana</div>
                                <div class="stat-value"><?php echo $week_procedures; ?></div>
                            </div>
                            <div class="stat-icon warning">
                                <i class="bi bi-calendar-week"></i>
                            </div>
                        </div>
                        <div class="stat-change positive">
                            <i class="bi bi-calendar-range"></i>
                            <span>Total de la semana</span>
                        </div>
                    </div>

                    <!-- Ingresos de la semana -->
                    <div class="stat-card animate-in delay-4">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Ingresos Semana</div>
                                <div class="stat-value">Q<?php echo number_format($week_revenue, 2); ?></div>
                            </div>
                            <div class="stat-icon info">
                                <i class="bi bi-graph-up-arrow"></i>
                            </div>
                        </div>
                        <div class="stat-change positive">
                            <i class="bi bi-calendar-month"></i>
                            <span>Acumulado semanal</span>
                        </div>
                    </div>
                </div>

                <!-- Procedimientos recientes -->
                <section class="appointments-section animate-in delay-2">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="bi bi-clock-history section-title-icon"></i>
                            Procedimientos Recientes
                        </h3>
                        <button type="button" class="action-btn" onclick="refreshProcedures()">
                            <i class="bi bi-arrow-clockwise"></i>
                            Actualizar
                        </button>
                    </div>

                    <?php if (count($recent_procedures) > 0): ?>
                        <div class="table-responsive">
                            <table class="appointments-table">
                                <thead>
                                    <tr>
                                        <th>Paciente</th>
                                        <th>Procedimiento</th>
                                        <th>Costo</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_procedures as $procedure): ?>
                                        <?php
                                        $patient_name = htmlspecialchars($procedure['nombre_paciente']);
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
                                                <div class="procedure-type">
                                                    <?php echo htmlspecialchars($procedure['procedimiento']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="time-badge bg-success text-white">
                                                    Q<?php echo number_format($procedure['cobro'], 2); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="time-badge">
                                                    <i class="bi bi-clock"></i>
                                                    <?php echo date('d/m/Y H:i', strtotime($procedure['fecha_procedimiento'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="#" class="btn-icon edit" title="Editar">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="#" class="btn-icon history" title="Ver detalles">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3 text-center">
                            <a href="historial_procedimientos.php" class="text-primary text-decoration-none">
                                Ver todos los procedimientos <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="bi bi-bandaid"></i>
                            </div>
                            <h4 class="text-muted mb-2">No hay procedimientos registrados</h4>
                            <p class="text-muted mb-3">Total en sistema: <?php echo $total_procedures; ?></p>
                            <p class="text-muted">Complete el formulario para registrar su primer procedimiento</p>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- Resumen mensual -->
                <div class="stat-card animate-in delay-3">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Resumen del Mes Actual</div>
                            <div class="stat-value"><?php echo $month_procedures; ?> Procedimientos</div>
                            <div class="stat-change positive">
                                <i class="bi bi-calendar-month"></i>
                                <span>Mes de <?php echo date('F'); ?></span>
                            </div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="bi bi-bar-chart-line"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <p class="text-muted mb-2">Total acumulado en sistema:
                            <strong><?php echo $total_procedures; ?></strong> procedimientos
                        </p>
                        <p class="text-muted mb-0">Sistema de procedimientos menores - Centro Médico RS</p>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Choices.js JS -->
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

    <!-- JavaScript Optimizado -->
    <script>
        /**
         * Procedimientos Menores v4.5 - Reingenierizado
         * Centro Médico RS
         */
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
        // REFERENCIAS A ELEMENTOS DOM (Único y Centralizado)
        // ==========================================================================
        const DOM = {
            html: document.documentElement,
            body: document.body,
            themeSwitch: document.getElementById('themeSwitch'),
            greetingElement: document.getElementById('greeting-text'),
            currentTimeElement: document.getElementById('current-time'),
            patientSelect: document.getElementById('id_paciente'),
            patientInfo: document.getElementById('paciente_info'),
            procedureForm: document.getElementById('procedureForm'),
            dynamicProceduresContainer: document.getElementById('dynamicProcedures'),
            btnAddProcedure: document.getElementById('btnAddProcedure'),
            dateInput: document.getElementById('fecha_procedimiento'),
            nombrePacienteInput: document.getElementById('nombre_paciente')
        };

        // ==========================================================================
        // MANEJO DE TEMAS
        // ==========================================================================
        class ThemeManager {
            constructor() {
                this.theme = this.getInitialTheme();
                this.applyTheme(this.theme);
                this.setupEventListeners();
            }

            getInitialTheme() {
                return localStorage.getItem(CONFIG.themeKey) ||
                    (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            }

            applyTheme(theme) {
                DOM.html.setAttribute('data-theme', theme);
                localStorage.setItem(CONFIG.themeKey, theme);
                const metaTheme = document.querySelector('meta[name="theme-color"]');
                if (metaTheme) metaTheme.setAttribute('content', theme === 'dark' ? '#0f172a' : '#ffffff');
            }

            toggleTheme() {
                this.theme = this.theme === 'light' ? 'dark' : 'light';
                this.applyTheme(this.theme);
                if (DOM.themeSwitch) {
                    DOM.themeSwitch.style.transform = 'rotate(180deg)';
                    setTimeout(() => DOM.themeSwitch.style.transform = 'rotate(0)', CONFIG.transitionDuration);
                }
            }

            setupEventListeners() {
                if (DOM.themeSwitch) DOM.themeSwitch.addEventListener('click', () => this.toggleTheme());
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                    if (!localStorage.getItem(CONFIG.themeKey)) this.applyTheme(e.matches ? 'dark' : 'light');
                });
            }
        }

        // ==========================================================================
        // COMPONENTES DINÁMICOS
        // ==========================================================================
        class DynamicComponents {
            constructor() {
                this.setupClock();
                this.setupPatientSearch();
                this.setupProcedureHandlers();
                this.setupFormHandlers();
                this.setupAnimations();
                this.updateGreeting();
            }

            updateGreeting() {
                if (!DOM.greetingElement) return;
                const hour = new Date().getHours();
                let greeting = 'Buenos días';
                if (hour >= 12 && hour < 19) greeting = 'Buenas tardes';
                else if (hour >= 19 || hour < 5) greeting = 'Buenas noches';
                DOM.greetingElement.textContent = greeting;
            }

            setupClock() {
                if (!DOM.currentTimeElement) return;
                const update = () => {
                    DOM.currentTimeElement.textContent = new Date().toLocaleTimeString('es-GT', {
                        hour: '2-digit', minute: '2-digit', hour12: false
                    });
                };
                update();
                setInterval(update, 60000);
            }

            setupPatientSearch() {
                if (!DOM.patientSelect || !DOM.patientInfo) return;

                const choices = new Choices(DOM.patientSelect, {
                    searchEnabled: true,
                    itemSelectText: '',
                    removeItemButton: true,
                    placeholder: true,
                    placeholderValue: 'Buscar paciente...',
                    noResultsText: 'No se encontraron resultados',
                    shouldSort: false,
                });

                const updateCard = (value) => {
                    if (!value) {
                        DOM.patientInfo.innerHTML = '<small class="text-muted">Seleccione un paciente para ver su información</small>';
                        if (DOM.nombrePacienteInput) DOM.nombrePacienteInput.value = '';
                        return;
                    }

                    const option = Array.from(DOM.patientSelect.options).find(opt => opt.value == value);
                    if (option) {
                        const nombre = option.dataset.nombre || option.text;
                        const initials = nombre.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
                        if (DOM.nombrePacienteInput) DOM.nombrePacienteInput.value = nombre;

                        DOM.patientInfo.innerHTML = `
                        <div class="d-flex align-items-center gap-3 animate-in">
                            <div class="patient-avatar-sm" style="width: 40px; height: 40px; border-radius: 50%; background: var(--color-primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.875rem;">
                                ${initials}
                            </div>
                            <div>
                                <div class="fw-bold" style="color: var(--color-text);">${nombre}</div>
                                <div class="text-muted small">
                                    <i class="bi bi-person me-1"></i> ${option.dataset.edad || 'N/A'} años • 
                                    <i class="bi bi-telephone me-1"></i> ${option.dataset.telefono || 'N/A'}
                                </div>
                            </div>
                        </div>
                    `;
                    }
                };

                DOM.patientSelect.addEventListener('addItem', (e) => updateCard(e.detail.value));
                DOM.patientSelect.addEventListener('removeItem', () => updateCard(''));
                DOM.patientSelect.addEventListener('change', function () { updateCard(this.value); });
            }

            setupProcedureHandlers() {
                if (!DOM.btnAddProcedure || !DOM.dynamicProceduresContainer) return;
                DOM.btnAddProcedure.addEventListener('click', () => {
                    const row = document.createElement('div');
                    row.className = 'input-group mb-2 animate-in';
                    row.innerHTML = `
                    <span class="input-group-text"><i class="bi bi-bandaid"></i></span>
                    <input class="form-control" name="procedimientos[]" placeholder="Especificar otro..." required>
                    <button type="button" class="btn btn-outline-danger" onclick="this.closest('.input-group').remove()"><i class="bi bi-trash"></i></button>
                `;
                    DOM.dynamicProceduresContainer.appendChild(row);
                    row.querySelector('input').focus();
                });
            }

            setupFormHandlers() {
                if (!DOM.dateInput) return;
                const now = new Date();
                const localDateTime = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
                DOM.dateInput.value = localDateTime;
            }

            setupAnimations() {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('animate-in');
                            observer.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.1 });
                document.querySelectorAll('.stat-card, .form-container, .appointments-section').forEach(el => observer.observe(el));
            }
        }

        // ==========================================================================
        // INICIALIZACIÓN GLOBAL
        // ==========================================================================
        document.addEventListener('DOMContentLoaded', () => {
            window.APP = {
                theme: new ThemeManager(),
                components: new DynamicComponents()
            };
        });

        // Helper global para eliminar procedimientos adicionales (si se usa inline)
        window.removeAdditionalProcedure = (btn) => {
            const row = btn.closest('.additional-procedure');
            row.style.opacity = '0';
            setTimeout(() => {
                row.remove();
                if (document.querySelectorAll('.additional-procedure').length === 0) {
                    document.getElementById('additionalProceduresSection').style.display = 'none';
                }
            }, 300);
        };

        // Helper global para recargar
        window.refreshProcedures = () => window.location.reload();

        // Estilos para spinner y animaciones
        (function () {
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
        })();
    </script>
</body>

</html>

<?php
// Función helper para calcular edad
function calculateAge($birthDate)
{
    if (!$birthDate)
        return 'N/A';
    try {
        $birth = new DateTime($birthDate);
        $today = new DateTime();
        return $today->diff($birth)->y;
    } catch (Exception $e) {
        return 'N/A';
    }
}
?>