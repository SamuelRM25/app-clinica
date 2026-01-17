<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer la zona horaria correcta
date_default_timezone_set('America/Guatemala');

verify_session();

try {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        $_SESSION['patient_message'] = "ID de paciente inválido";
        $_SESSION['patient_status'] = "danger";
        header("Location: index.php");
        exit;
    }

    $patient_id = $_GET['id'];

    $database = new Database();
    $conn = $database->getConnection();

    // Obtener información del paciente con estadísticas
    $stmt = $conn->prepare("SELECT p.*, 
                           COUNT(h.id_historial) as total_consultas,
                           MAX(h.fecha_consulta) as ultima_consulta
                           FROM pacientes p
                           LEFT JOIN historial_clinico h ON p.id_paciente = h.id_paciente
                           WHERE p.id_paciente = ?
                           GROUP BY p.id_paciente");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        $_SESSION['patient_message'] = "Paciente no encontrado";
        $_SESSION['patient_status'] = "danger";
        header("Location: index.php");
        exit;
    }

    // Obtener historial médico con información del doctor
    $stmt = $conn->prepare("SELECT h.*, 
                           u.nombre as doctor_nombre, 
                           u.apellido as doctor_apellido
                           FROM historial_clinico h
                           LEFT JOIN usuarios u ON h.medico_responsable = CONCAT(u.nombre, ' ', u.apellido)
                           WHERE h.id_paciente = ? 
                           ORDER BY h.fecha_consulta DESC, h.id_historial DESC");
    $stmt->execute([$patient_id]);
    $medical_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener total de pacientes para la barra lateral
    $stmtSummary = $conn->query("SELECT COUNT(*) as total FROM pacientes");
    $total_patients = $stmtSummary->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Obtener lista de doctores para el modal de nueva consulta
    $stmtDocs = $conn->prepare("SELECT idUsuario, nombre, apellido, especialidad FROM usuarios WHERE tipoUsuario = 'doc' ORDER BY nombre, apellido");
    $stmtDocs->execute();
    $doctors = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

    // Calcular edad del paciente
    $edad = isset($patient['fecha_nacimiento']) ?
        (new DateTime())->diff(new DateTime($patient['fecha_nacimiento']))->y : 0;

    // Obtener información del usuario
    $user_name = $_SESSION['nombre'];
    $user_specialty = $_SESSION['especialidad'] ?? 'Profesional Médico';

    $page_title = "Historial Clínico - " . $patient['nombre'] . " " . $patient['apellido'] . " - Centro Médico Herrera Sáenz";

} catch (Exception $e) {
    error_log("Error en historial clínico: " . $e->getMessage());
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Historial Clínico - Centro Médico Herrera Sáenz">
    <title><?php echo $page_title; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">

    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- CSS Crítico (mismo que el dashboard) -->
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
            /* Asegurado para evitar overcrowding */
        }

        /* Scrollbar personalizado para sidebar */
        .sidebar-nav::-webkit-scrollbar {
            width: 5px;
        }

        .sidebar-nav::-webkit-scrollbar-thumb {
            background: rgba(var(--color-primary-rgb), 0.2);
            border-radius: 10px;
        }

        [data-theme="dark"] .sidebar-nav::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
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
            margin-left: var(--sidebar-width);
            transition: margin-left var(--transition-base);
            width: calc(100% - var(--sidebar-width));
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
            list-style: none !important;
            display: flex !important;
            flex-direction: column !important;
            gap: var(--space-xs) !important;
            padding: 0 !important;
            margin: 0 !important;
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
            left: calc(var(--sidebar-width) - 12px);
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

        .sidebar.collapsed+.dashboard-container .sidebar-toggle {
            left: calc(var(--sidebar-collapsed-width) - 12px);
        }

        .sidebar.collapsed .sidebar-toggle i {
            transform: rotate(180deg);
        }

        /* ==========================================================================
       COMPONENTES ESPECÍFICOS HISTORIAL CLÍNICO
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

        /* Información del paciente */
        .patient-info-card {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            margin-bottom: var(--space-lg);
        }

        .patient-header {
            display: flex;
            align-items: center;
            gap: var(--space-lg);
            margin-bottom: var(--space-lg);
        }

        .patient-avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--color-primary), var(--color-info));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: var(--font-size-2xl);
            flex-shrink: 0;
        }

        .patient-details {
            flex: 1;
        }

        .patient-name {
            font-size: var(--font-size-xl);
            font-weight: 600;
            color: var(--color-text);
            margin-bottom: var(--space-xs);
        }

        .patient-meta {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-md);
            color: var(--color-text-secondary);
            font-size: var(--font-size-sm);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--space-md);
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: var(--font-size-xs);
            color: var(--color-text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: var(--space-xs);
        }

        .info-value {
            font-size: var(--font-size-base);
            font-weight: 500;
            color: var(--color-text);
        }

        /* Timeline de historial médico */
        .medical-timeline {
            position: relative;
            padding-left: 2rem;
        }

        .medical-timeline::before {
            content: '';
            position: absolute;
            left: 1rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(180deg, var(--color-primary), var(--color-info));
            border-radius: 2px;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 2rem;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -1.5rem;
            top: 1rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--color-primary);
            border: 3px solid var(--color-card);
            box-shadow: 0 0 0 4px rgba(var(--color-primary-rgb), 0.2);
            z-index: 1;
        }

        /* Tarjeta de consulta */
        .consultation-card {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: all var(--transition-base);
        }

        .consultation-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .consultation-header {
            padding: var(--space-lg);
            border-bottom: 1px solid var(--color-border);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color var(--transition-base);
        }

        .consultation-header:hover {
            background: rgba(var(--color-primary-rgb), 0.05);
        }

        .consultation-date {
            background: rgba(var(--color-primary-rgb), 0.1);
            padding: var(--space-sm) var(--space-md);
            border-radius: var(--radius-md);
            text-align: center;
            min-width: 100px;
        }

        .date-day {
            font-size: var(--font-size-xs);
            color: var(--color-text-secondary);
        }

        .date-number {
            font-size: var(--font-size-xl);
            font-weight: 600;
            color: var(--color-primary);
        }

        .consultation-doctor {
            flex: 1;
            margin-left: var(--space-md);
        }

        .doctor-name {
            font-weight: 500;
            color: var(--color-text);
            font-size: var(--font-size-base);
        }

        .doctor-label {
            font-size: var(--font-size-xs);
            color: var(--color-text-secondary);
        }

        .collapse-icon {
            transition: transform var(--transition-base);
        }

        .rotate-180 {
            transform: rotate(180deg);
        }

        /* Contenido de la consulta */
        .consultation-content {
            padding: var(--space-lg);
        }

        .section-box {
            background: rgba(var(--color-primary-rgb), 0.05);
            border-left: 4px solid var(--color-primary);
            padding: var(--space-lg);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-lg);
        }

        .section-title-small {
            font-size: var(--font-size-sm);
            font-weight: 600;
            color: var(--color-text-secondary);
            text-transform: uppercase;
            margin-bottom: var(--space-sm);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .section-content {
            font-size: var(--font-size-base);
            line-height: 1.6;
            color: var(--color-text);
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

        .btn-icon.history:hover {
            background: var(--color-success);
            color: white;
            border-color: var(--color-success);
        }

        .btn-icon.edit:hover {
            background: var(--color-warning);
            color: white;
            border-color: var(--color-warning);
        }

        .btn-icon.print:hover {
            background: var(--color-info);
            color: white;
            border-color: var(--color-info);
        }

        /* ==========================================================================
       RESPONSIVE DESIGN
       ========================================================================== */

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

            .patient-header {
                flex-direction: column;
                text-align: center;
            }

            .info-grid {
                grid-template-columns: 1fr;
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

            .consultation-header {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--space-md);
            }

            .consultation-date {
                align-self: flex-start;
            }

            .section-header {
                flex-direction: column;
                align-items: stretch;
                gap: var(--space-md);
            }

            .action-btn {
                width: 100%;
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

    <!-- Overlay para sidebar móvil -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Barra Lateral Moderna -->
    <aside class="sidebar" id="sidebar">
        <!-- Navegación -->
        <nav class="sidebar-nav">
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="../dashboard/index.php" class="nav-link">
                        <i class="bi bi-speedometer2 nav-icon"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../appointments/index.php" class="nav-link">
                        <i class="bi bi-calendar-check nav-icon"></i>
                        <span class="nav-text">Citas</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../patients/index.php" class="nav-link active">
                        <i class="bi bi-people nav-icon"></i>
                        <span class="nav-text">Pacientes</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../hospitalization/index.php" class="nav-link">
                        <i class="bi bi-hospital nav-icon"></i>
                        <span class="nav-text">Hospitalización</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../minor_procedures/index.php" class="nav-link">
                        <i class="bi bi-bandaid nav-icon"></i>
                        <span class="nav-text">Procedimientos</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../examinations/index.php" class="nav-link">
                        <i class="bi bi-file-earmark-medical nav-icon"></i>
                        <span class="nav-text">Exámenes</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../laboratory/index.php" class="nav-link">
                        <i class="bi bi-virus nav-icon"></i>
                        <span class="nav-text">Laboratorio</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../inventory/index.php" class="nav-link">
                        <i class="bi bi-box-seam nav-icon"></i>
                        <span class="nav-text">Inventario</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../purchases/index.php" class="nav-link">
                        <i class="bi bi-cart nav-icon"></i>
                        <span class="nav-text">Compras</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../sales/index.php" class="nav-link">
                        <i class="bi bi-receipt nav-icon"></i>
                        <span class="nav-text">Ventas</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../billing/index.php" class="nav-link">
                        <i class="bi bi-cash-coin nav-icon"></i>
                        <span class="nav-text">Cobros</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../dispensary/index.php" class="nav-link">
                        <i class="bi bi-capsule nav-icon"></i>
                        <span class="nav-text">Dispensario</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../reports/index.php" class="nav-link">
                        <i class="bi bi-graph-up nav-icon"></i>
                        <span class="nav-text">Reportes</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../settings/index.php" class="nav-link">
                        <i class="bi bi-gear nav-icon"></i>
                        <span class="nav-text">Configuración</span>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

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

        <!-- Botón para colapsar/expandir sidebar (solo escritorio) -->
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Colapsar/expandir menú">
            <i class="bi bi-chevron-left" id="sidebarToggleIcon"></i>
        </button>

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
                            <i class="bi bi-building me-1"></i> Centro Médico Herrera Saenz
                        </p>
                    </div>
                    <div class="d-none d-md-block">
                        <i class="bi bi-file-medical text-primary" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>

            <!-- Información del paciente -->
            <div class="patient-info-card animate-in delay-1">
                <div class="patient-header">
                    <div class="patient-avatar-large">
                        <?php echo strtoupper(substr($patient['nombre'], 0, 1) . substr($patient['apellido'], 0, 1)); ?>
                    </div>
                    <div class="patient-details">
                        <h2 class="patient-name">
                            <?php echo htmlspecialchars($patient['nombre'] . ' ' . $patient['apellido']); ?>
                        </h2>
                        <div class="patient-meta">
                            <span>ID: #<?php echo str_pad($patient_id, 5, '0', STR_PAD_LEFT); ?></span>
                            <span><?php echo $edad; ?> años</span>
                            <span><?php echo htmlspecialchars($patient['genero']); ?></span>
                            <span><?php echo $patient['total_consultas']; ?> consultas</span>
                        </div>
                    </div>
                    <div class="action-buttons">
                        <a href="index.php" class="btn-icon" title="Volver a pacientes">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <a href="../hospitalization/ingresar_paciente.php?id_paciente=<?php echo $patient_id; ?>"
                            class="btn-icon" title="Ingresar paciente"
                            style="background-color: var(--color-success); color: white;">
                            <i class="bi bi-hospital"></i>
                        </a>
                    </div>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Fecha de Nacimiento</span>
                        <span
                            class="info-value"><?php echo date('d/m/Y', strtotime($patient['fecha_nacimiento'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Teléfono</span>
                        <span class="info-value"><?php echo htmlspecialchars($patient['telefono'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Correo Electrónico</span>
                        <span class="info-value"><?php echo htmlspecialchars($patient['correo'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Última Consulta</span>
                        <span
                            class="info-value"><?php echo $patient['ultima_consulta'] ? date('d/m/Y', strtotime($patient['ultima_consulta'])) : 'N/A'; ?></span>
                    </div>
                </div>
            </div>

            <!-- Historial médico -->
            <section class="appointments-section animate-in delay-3">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-clock-history section-title-icon"></i>
                        Historial de Consultas
                    </h3>
                    <button type="button" class="action-btn" data-bs-toggle="modal"
                        data-bs-target="#newMedicalRecordModal">
                        <i class="bi bi-plus-lg"></i>
                        Nueva Consulta
                    </button>
                </div>

                <?php if (count($medical_records) > 0): ?>
                    <div class="medical-timeline">
                        <?php foreach ($medical_records as $index => $record): ?>
                            <div class="timeline-item">
                                <div class="consultation-card">
                                    <div class="consultation-header" data-bs-toggle="collapse"
                                        data-bs-target="#collapseRecord<?php echo $record['id_historial']; ?>"
                                        aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>">
                                        <div class="consultation-date">
                                            <div class="date-day"><?php echo date('D', strtotime($record['fecha_consulta'])); ?>
                                            </div>
                                            <div class="date-number">
                                                <?php echo date('d', strtotime($record['fecha_consulta'])); ?>
                                            </div>
                                            <div class="date-day">
                                                <?php echo date('M/y', strtotime($record['fecha_consulta'])); ?>
                                            </div>
                                        </div>
                                        <div class="consultation-doctor">
                                            <div class="doctor-name">Dr.
                                                <?php echo htmlspecialchars($record['medico_responsable']); ?>
                                            </div>
                                            <div class="doctor-label">Médico responsable</div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="action-buttons">
                                                <?php if (!empty($record['receta_medica'])): ?>
                                                    <a href="print_prescription.php?id=<?php echo $record['id_historial']; ?>"
                                                        class="btn-icon print" title="Imprimir Receta" target="_blank">
                                                        <i class="bi bi-printer"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                            <i
                                                class="bi bi-chevron-down collapse-icon <?php echo $index === 0 ? 'rotate-180' : ''; ?>"></i>
                                        </div>
                                    </div>

                                    <div id="collapseRecord<?php echo $record['id_historial']; ?>"
                                        class="collapse <?php echo $index === 0 ? 'show' : ''; ?>">
                                        <div class="consultation-content">
                                            <div class="row g-4">
                                                <div class="col-md-7">
                                                    <div class="section-box">
                                                        <div class="section-title-small">
                                                            <i class="bi bi-chat-left-text"></i>
                                                            Motivo de Consulta
                                                        </div>
                                                        <div class="section-content">
                                                            <?php echo nl2br(htmlspecialchars($record['motivo_consulta'])); ?>
                                                        </div>
                                                    </div>

                                                    <div class="section-box">
                                                        <div class="section-title-small">
                                                            <i class="bi bi-list-check"></i>
                                                            Síntomas / Historia
                                                        </div>
                                                        <div class="section-content">
                                                            <?php echo nl2br(htmlspecialchars($record['sintomas'])); ?>
                                                        </div>
                                                    </div>

                                                    <?php if (!empty($record['examen_fisico'])): ?>
                                                        <div class="section-box">
                                                            <div class="section-title-small">
                                                                <i class="bi bi-heart-pulse"></i>
                                                                Examen Físico
                                                            </div>
                                                            <div class="section-content">
                                                                <?php echo nl2br(htmlspecialchars($record['examen_fisico'])); ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="col-md-5">
                                                    <div class="section-box" style="border-left-color: var(--color-warning);">
                                                        <div class="section-title-small" style="color: var(--color-warning);">
                                                            <i class="bi bi-clipboard-check"></i>
                                                            Diagnóstico
                                                        </div>
                                                        <div class="section-content">
                                                            <?php echo nl2br(htmlspecialchars($record['diagnostico'])); ?>
                                                        </div>
                                                    </div>

                                                    <div class="section-box" style="border-left-color: var(--color-success);">
                                                        <div class="section-title-small" style="color: var(--color-success);">
                                                            <i class="bi bi-prescription2"></i>
                                                            Tratamiento
                                                        </div>
                                                        <div class="section-content">
                                                            <?php echo nl2br(htmlspecialchars($record['tratamiento'])); ?>
                                                        </div>
                                                    </div>

                                                    <?php if (!empty($record['proxima_cita'])): ?>
                                                        <div class="section-box" style="border-left-color: var(--color-info);">
                                                            <div class="section-title-small" style="color: var(--color-info);">
                                                                <i class="bi bi-calendar-check"></i>
                                                                Próxima Cita
                                                            </div>
                                                            <div class="section-content">
                                                                <strong><?php echo date('d/m/Y', strtotime($record['proxima_cita'])); ?></strong>
                                                                <?php if (!empty($record['hora_proxima_cita'])): ?>
                                                                    <br><?php echo $record['hora_proxima_cita']; ?>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <?php if (!empty($record['receta_medica'])): ?>
                                                <div class="section-box mt-4" style="border-left-color: var(--color-primary);">
                                                    <div class="section-title-small" style="color: var(--color-primary);">
                                                        <i class="bi bi-prescription"></i>
                                                        Prescripción Médica
                                                    </div>
                                                    <div class="section-content"
                                                        style="font-family: 'Courier New', monospace; white-space: pre-wrap;">
                                                        <?php
                                                        $clean_receta = implode("\n", array_map('trim', explode("\n", $record['receta_medica'])));
                                                        echo nl2br(htmlspecialchars($clean_receta));
                                                        ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="bi bi-clipboard-x"></i>
                        </div>
                        <h4 class="text-muted mb-2">No hay registros médicos</h4>
                        <p class="text-muted mb-3">Este paciente aún no tiene consultas registradas</p>
                        <button type="button" class="action-btn" data-bs-toggle="modal"
                            data-bs-target="#newMedicalRecordModal">
                            <i class="bi bi-plus-circle"></i>
                            Crear primer registro
                        </button>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Estadísticas de consultas -->
            <div class="stats-grid mb-5 animate-in delay-2">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Total Consultas</div>
                            <div class="stat-value"><?php echo $patient['total_consultas']; ?></div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="bi bi-file-medical"></i>
                        </div>
                    </div>
                    <div class="text-muted">Historial completo</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Última Consulta</div>
                            <div class="stat-value">
                                <?php echo $patient['ultima_consulta'] ? date('d/m/Y', strtotime($patient['ultima_consulta'])) : 'N/A'; ?>
                            </div>
                        </div>
                        <div class="stat-icon success">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                    </div>
                    <div class="text-muted">Fecha más reciente</div>
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript Optimizado -->
    <!-- Modal para nuevo registro médico -->
    <div class="modal fade" id="newMedicalRecordModal" tabindex="-1" aria-labelledby="newMedicalRecordModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content"
                style="background: var(--color-card); border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden;">
                <div class="modal-header"
                    style="border-bottom: 1px solid var(--color-border); padding: var(--space-lg);">
                    <h5 class="modal-title" id="newMedicalRecordModalLabel"
                        style="font-weight: 600; display: flex; align-items: center; gap: var(--space-sm);">
                        <i class="bi bi-clipboard-plus text-primary"></i>
                        Nuevo Registro Clínico
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="newMedicalRecordForm" action="save_medical_record.php" method="POST">
                    <input type="hidden" name="id_paciente" value="<?php echo $patient_id; ?>">

                    <div class="modal-body" style="padding: var(--space-lg); max-height: 70vh; overflow-y: auto;">
                        <div class="row g-4">
                            <!-- Información de la consulta -->
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label for="motivo_consulta" class="form-label fw-semibold">Motivo de Consulta
                                        *</label>
                                    <textarea id="motivo_consulta" name="motivo_consulta" class="form-control" rows="3"
                                        required placeholder="Describa el motivo de la visita..."></textarea>
                                </div>
                                <div class="form-group mb-4">
                                    <label for="sintomas" class="form-label fw-semibold">Síntomas / Historia *</label>
                                    <textarea id="sintomas" name="sintomas" class="form-control" rows="3" required
                                        placeholder="Detalle los síntomas presentados..."></textarea>
                                </div>
                                <div class="form-group mb-4">
                                    <label for="examen_fisico" class="form-label fw-semibold">Examen Físico</label>
                                    <textarea id="examen_fisico" name="examen_fisico" class="form-control" rows="3"
                                        placeholder="Hallazgos del examen físico..."></textarea>
                                </div>
                            </div>

                            <!-- Diagnóstico y Tratamiento -->
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label for="diagnostico" class="form-label fw-semibold">Diagnóstico *</label>
                                    <textarea id="diagnostico" name="diagnostico" class="form-control" rows="3" required
                                        placeholder="Diagnóstico médico..."></textarea>
                                </div>
                                <div class="form-group mb-4">
                                    <label for="tratamiento" class="form-label fw-semibold">Tratamiento *</label>
                                    <textarea id="tratamiento" name="tratamiento" class="form-control" rows="3" required
                                        placeholder="Plan de tratamiento..."></textarea>
                                </div>
                                <div class="form-group mb-4">
                                    <label for="receta_medica" class="form-label fw-semibold">Receta Médica</label>
                                    <textarea id="receta_medica" name="receta_medica" class="form-control" rows="3"
                                        placeholder="Medicamentos y dosis..."></textarea>
                                </div>
                            </div>

                            <div class="col-12">
                                <hr class="my-2" style="border-color: var(--color-border);">
                            </div>

                            <!-- Antecedentes -->
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label for="antecedentes_personales" class="form-label fw-semibold">Antecedentes
                                        Personales</label>
                                    <textarea id="antecedentes_personales" name="antecedentes_personales"
                                        class="form-control" rows="2"
                                        placeholder="Alergias, cirugías, enfermedades previas..."></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label for="antecedentes_familiares" class="form-label fw-semibold">Antecedentes
                                        Familiares</label>
                                    <textarea id="antecedentes_familiares" name="antecedentes_familiares"
                                        class="form-control" rows="2"
                                        placeholder="Enfermedades hereditarias..."></textarea>
                                </div>
                            </div>

                            <!-- Exámenes -->
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label for="examenes_realizados" class="form-label fw-semibold mb-0">Exámenes
                                            Solicitados</label>
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                            onclick="openLabOrderModal()">
                                            <i class="bi bi-clipboard-plus"></i> Ordenar Laboratorio
                                        </button>
                                    </div>
                                    <textarea id="examenes_realizados" name="examenes_realizados" class="form-control"
                                        rows="2" placeholder="Laboratorios, RX, ultrasonidos..."></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label for="resultados_examenes" class="form-label fw-semibold">Resultados
                                        Importantes</label>
                                    <textarea id="resultados_examenes" name="resultados_examenes" class="form-control"
                                        rows="2" placeholder="Valores críticos o hallazgos relevantes..."></textarea>
                                </div>
                            </div>

                            <div class="col-12">
                                <hr class="my-2" style="border-color: var(--color-border);">
                            </div>

                            <!-- Próxima Cita -->
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label for="proxima_cita" class="form-label fw-semibold">Próxima Cita</label>
                                    <input type="date" id="proxima_cita" name="proxima_cita" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label for="hora_proxima_cita" class="form-label fw-semibold">Hora de Cita</label>
                                    <input type="time" id="hora_proxima_cita" name="hora_proxima_cita"
                                        class="form-control">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label for="medico_responsable" class="form-label fw-semibold">Médico Responsable
                                        *</label>
                                    <select id="medico_responsable" name="medico_responsable" class="form-select"
                                        required>
                                        <option value="">Seleccionar Médico...</option>
                                        <?php foreach ($doctors as $doctor): ?>
                                            <option
                                                value="<?php echo htmlspecialchars($doctor['nombre'] . ' ' . $doctor['apellido']); ?>"
                                                <?php echo ($doctor['nombre'] . ' ' . $doctor['apellido'] === $user_name) ? 'selected' : ''; ?>>
                                                Dr(a).
                                                <?php echo htmlspecialchars($doctor['nombre'] . ' ' . $doctor['apellido']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="especialidad_medico"
                                        value="<?php echo htmlspecialchars($user_specialty); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer"
                        style="border-top: 1px solid var(--color-border); padding: var(--space-lg);">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"
                            style="border-radius: var(--radius-md);">Cancelar</button>
                        <button type="submit" class="action-btn">
                            <i class="bi bi-save me-1"></i>
                            Guardar Registro
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para Orden de Laboratorio (Iframe) -->
    <div class="modal fade" id="labOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 95vw;">
            <div class="modal-content" style="height: 90vh;">
                <div class="modal-header">
                    <h5 class="modal-title">Orden de Laboratorio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <iframe id="labOrderFrame" src="" style="width: 100%; height: 100%; border: none;"></iframe>
                </div>
            </div>
        </div>
    </div>

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
                currentTimeElement: document.getElementById('current-time')
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
                    if (DOM.sidebarToggle) {
                        DOM.sidebarToggle.addEventListener('click', () => this.toggle());
                    }

                    if (DOM.mobileSidebarToggle) {
                        DOM.mobileSidebarToggle.addEventListener('click', () => this.toggle());
                    }

                    if (DOM.sidebarOverlay) {
                        DOM.sidebarOverlay.addEventListener('click', () => this.closeMobile());
                    }

                    const navLinks = DOM.sidebar.querySelectorAll('.nav-link');
                    navLinks.forEach(link => {
                        link.addEventListener('click', () => {
                            if (this.isMobile) this.closeMobile();
                        });
                    });

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
                    this.setupAnimations();
                    this.setupCollapseIcons();
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

                    document.querySelectorAll('.stat-card, .appointments-section, .patient-info-card').forEach(el => {
                        observer.observe(el);
                    });
                }

                setupCollapseIcons() {
                    const collapsibleElements = document.querySelectorAll('.collapse');
                    collapsibleElements.forEach(el => {
                        el.addEventListener('show.bs.collapse', function () {
                            const icon = this.previousElementSibling?.querySelector('.collapse-icon');
                            if (icon) icon.classList.add('rotate-180');
                        });

                        el.addEventListener('hide.bs.collapse', function () {
                            const icon = this.previousElementSibling?.querySelector('.collapse-icon');
                            if (icon) icon.classList.remove('rotate-180');
                        });
                    });
                }
            }

            // ==========================================================================
            // INICIALIZACIÓN DE LA APLICACIÓN
            // ==========================================================================
            document.addEventListener('DOMContentLoaded', () => {
                const themeManager = new ThemeManager();
                const sidebarManager = new SidebarManager();
                const dynamicComponents = new DynamicComponents();

                window.dashboard = {
                    theme: themeManager,
                    sidebar: sidebarManager,
                    components: dynamicComponents
                };

                console.log('Historial Clínico inicializado correctamente');
                console.log('Paciente: <?php echo htmlspecialchars($patient["nombre"] . " " . $patient["apellido"]); ?>');
                console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
            });

            // ==========================================================================
            // MANEJO DE ERRORES GLOBALES
            // ==========================================================================
            window.addEventListener('error', (event) => {
                const errorMsg = event.error ? event.error : (event.message ? event.message : 'Error desconocido');
                console.error('Error en historial clínico:', errorMsg, {
                    filename: event.filename,
                    lineno: event.lineno,
                    colno: event.colno
                });
            });

            // Función global para abrir el modal de orden
            window.openLabOrderModal = function () {
                const patientId = '<?php echo $patient_id; ?>'; // PHP injection
                const frame = document.getElementById('labOrderFrame');
                const modal = new bootstrap.Modal(document.getElementById('labOrderModal'));

                // Cargar la página en el iframe
                frame.src = `../laboratory/crear_orden.php?id_paciente=${patientId}`;

                modal.show();
            };

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

    <!-- Bootstrap JS (para modales y collapse) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>