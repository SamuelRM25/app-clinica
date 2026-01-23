<?php
// index.php - Calendario de Citas - Centro Médico Herrera Saenz
// Versión: 4.0 - Diseño Responsive, Barra Lateral Moderna, Efecto Mármol
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
    
    // Obtener doctores para el dropdown
    $stmtDocs = $conn->prepare("SELECT idUsuario, nombre, apellido FROM usuarios WHERE tipoUsuario = 'doc' ORDER BY nombre, apellido");
    $stmtDocs->execute();
    $doctors = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);
    
    // Estadísticas para la barra lateral
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM citas");
    $stmt->execute();
    $total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Citas de hoy
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM citas WHERE fecha_cita = ?");
    $stmt->execute([$today]);
    $today_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Título de la página
    $page_title = "Calendario de Citas - Centro Médico Herrera Saenz";
    
} catch (Exception $e) {
    // Manejo de errores
    error_log("Error en calendario de citas: " . $e->getMessage());
    die("Error al cargar el calendario. Por favor, contacte al administrador.");
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Calendario de Citas del Centro Médico Herrera Saenz - Sistema de gestión de agenda médica">
    <title><?php echo $page_title; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">
    
    <!-- Google Fonts - Inter (moderno y legible) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
<!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- FullCalendar CSS -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet'>
    
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
        --color-calendar-day: #7c90db;
        
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
        --color-calendar-night: #8dd7bf;
        
        /* Versiones RGB para opacidad */
        --color-primary-rgb: 13, 110, 253;
        --color-success-rgb: 25, 135, 84;
        --color-warning-rgb: 255, 193, 7;
        --color-danger-rgb: 220, 53, 69;
        --color-info-rgb: 13, 202, 240;
        --color-card-rgb: 255, 255, 255;
        --color-calendar-rgb: 124, 144, 219;
        
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
        --shadow-sm: 0 1px 3px rgba(0,0,0,0.12);
        --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
        --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
        --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1);
        
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
        --color-calendar: var(--color-calendar-day);
        
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
        --color-calendar: var(--color-calendar-night);
        
        --color-primary-rgb: 59, 130, 246;
        --color-success-rgb: 16, 185, 129;
        --color-warning-rgb: 245, 158, 11;
        --color-danger-rgb: 239, 68, 68;
        --color-info-rgb: 6, 182, 212;
        --color-card-rgb: 30, 41, 59;
        --color-calendar-rgb: 141, 215, 191;
        
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
        0% { background-position: 0% 0%; }
        100% { background-position: 100% 100%; }
    }
    
    /* ==========================================================================
       LAYOUT PRINCIPAL
       ========================================================================== */
    .dashboard-container {
        display: flex;
        flex-direction: column; /* Apilar Header y Main verticalmente */
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
       COMPONENTES DE CALENDARIO
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
        background: linear-gradient(90deg, var(--color-calendar), var(--color-info));
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
    
    .stat-icon.calendar {
        background: rgba(var(--color-calendar-rgb), 0.1);
        color: var(--color-calendar);
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
    
    /* Sección principal del calendario */
    .calendar-section {
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: var(--space-lg);
        margin-bottom: var(--space-lg);
        transition: all var(--transition-base);
        min-height: 600px;
    }
    
    .calendar-section:hover {
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
        color: var(--color-calendar);
    }
    
    .action-btn {
        display: inline-flex;
        align-items: center;
        gap: var(--space-sm);
        padding: var(--space-sm) var(--space-md);
        background: var(--color-calendar);
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
    
    .action-btn.secondary {
        background: var(--color-surface);
        color: var(--color-text);
        border: 1px solid var(--color-border);
    }
    
    .action-btn.secondary:hover {
        background: var(--color-surface);
        border-color: var(--color-calendar);
        color: var(--color-calendar);
    }
    
    /* Personalización de FullCalendar */
    #calendar {
        --fc-page-bg-color: transparent;
        --fc-neutral-bg-color: var(--color-surface);
        --fc-neutral-text-color: var(--color-text-secondary);
        --fc-border-color: var(--color-border);
        --fc-button-bg-color: var(--color-surface);
        --fc-button-border-color: var(--color-border);
        --fc-button-hover-bg-color: var(--color-surface);
        --fc-button-hover-border-color: var(--color-calendar);
        --fc-button-active-bg-color: var(--color-calendar);
        --fc-button-active-border-color: var(--color-calendar);
        --fc-event-bg-color: var(--color-calendar);
        --fc-event-border-color: var(--color-calendar);
        --fc-event-text-color: white;
        --fc-today-bg-color: rgba(var(--color-calendar-rgb), 0.1);
        --fc-now-indicator-color: var(--color-warning);
    }
    
    .fc {
        font-family: var(--font-family);
    }
    
    .fc .fc-toolbar-title {
        color: var(--color-text);
        font-weight: 600;
        font-size: var(--font-size-lg);
    }
    
    .fc .fc-button {
        border-radius: var(--radius-sm);
        padding: var(--space-sm) var(--space-md);
        font-weight: 500;
        font-size: var(--font-size-sm);
        transition: all var(--transition-base);
        border: 1px solid var(--color-border);
        background: var(--color-surface);
        color: var(--color-text);
    }
    
    .fc .fc-button:hover {
        background: var(--color-surface);
        border-color: var(--color-calendar);
        color: var(--color-calendar);
        transform: translateY(-2px);
    }
    
    .fc .fc-button-primary:not(:disabled).fc-button-active,
    .fc .fc-button-primary:not(:disabled):active {
        background-color: var(--color-calendar);
        border-color: var(--color-calendar);
        color: white;
    }
    
    .fc .fc-daygrid-day {
        border-radius: var(--radius-sm);
        transition: background-color var(--transition-base);
    }
    
    .fc .fc-daygrid-day:hover {
        background-color: var(--color-surface);
    }
    
    .fc .fc-day-today {
        background-color: rgba(var(--color-calendar-rgb), 0.1) !important;
    }
    
    .fc .fc-event {
        border-radius: var(--radius-sm);
        border: none;
        padding: var(--space-xs) var(--space-sm);
        font-size: var(--font-size-sm);
        font-weight: 500;
        cursor: pointer;
        transition: all var(--transition-base);
    }
    
    .fc .fc-event:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
        filter: brightness(1.1);
    }
    
    .fc .fc-event-primary {
        background: linear-gradient(135deg, var(--color-calendar), var(--color-primary));
    }
    
    .fc .fc-event-secondary {
        background: linear-gradient(135deg, var(--color-secondary), var(--color-info));
    }
    
    .fc .fc-event-success {
        background: linear-gradient(135deg, var(--color-success), var(--color-info));
    }
    
    .fc .fc-event-warning {
        background: linear-gradient(135deg, var(--color-warning), var(--color-danger));
    }
    
    /* Modales Estilo Premium */
    .modal-content {
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-xl);
        color: var(--color-text);
        box-shadow: var(--shadow-xl);
        overflow: hidden;
        backdrop-filter: blur(10px);
        background-color: rgba(var(--color-card-rgb), 0.98);
        border: 1px solid rgba(var(--color-calendar-rgb), 0.2);
    }
    
    .modal-header {
        border-bottom: 1px solid var(--color-border);
        padding: var(--space-lg) var(--space-xl);
        background: linear-gradient(to right, rgba(var(--color-calendar-rgb), 0.05), transparent);
    }
    
    .modal-title {
        color: var(--color-text);
        font-weight: 700;
        font-size: var(--font-size-xl);
        display: flex;
        align-items: center;
        gap: var(--space-md);
    }
    
    .modal-title i {
        font-size: 1.5rem;
        background: linear-gradient(135deg, var(--color-calendar), var(--color-primary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    
    .modal-body {
        padding: var(--space-xl);
    }
    
    .modal-footer {
        border-top: 1px solid var(--color-border);
        padding: var(--space-lg) var(--space-xl);
        display: flex;
        justify-content: flex-end;
        gap: var(--space-md);
        background: rgba(var(--color-surface-rgb), 0.3);
    }
    
    /* Formularios en Modales */
    .form-group-custom {
        margin-bottom: var(--space-lg);
        position: relative;
    }
    
    .form-label {
        display: block;
        margin-bottom: var(--space-xs);
        color: var(--color-text-secondary);
        font-weight: 600;
        font-size: var(--font-size-xs);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        transition: all var(--transition-base);
    }
    
    .form-control, .form-select {
        width: 100%;
        padding: 0.75rem 1rem;
        background: var(--color-surface);
        border: 1.5px solid var(--color-border);
        border-radius: var(--radius-md);
        color: var(--color-text);
        font-size: var(--font-size-sm);
        transition: all var(--transition-base);
    }
    
    .form-control:focus, .form-select:focus {
        outline: none;
        border-color: var(--color-calendar);
        background: var(--color-card);
        box-shadow: 0 0 0 4px rgba(var(--color-calendar-rgb), 0.15);
        transform: translateY(-1px);
    }

    .form-control::placeholder {
        color: var(--color-text-secondary);
        opacity: 0.5;
    }

    /* Input icons wrapper (opcional para el futuro) */
    .input-icon-wrapper {
        position: relative;
    }

    .input-icon-wrapper i {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--color-text-secondary);
        pointer-events: none;
    }

    .input-icon-wrapper .form-control {
        padding-left: 2.75rem;
    }

    /* Mejora de botones en modales */
    .action-btn {
        padding: 0.625rem 1.5rem;
        font-weight: 600;
        letter-spacing: 0.3px;
    }

    .action-btn.secondary {
        background: transparent;
        color: var(--color-text-secondary);
        border: 1.5px solid var(--color-border);
    }

    .action-btn.secondary:hover {
        background: var(--color-surface);
        border-color: var(--color-text-secondary);
        color: var(--color-text);
    }
    
    /* Menú contextual */
    .context-menu {
        display: none;
        position: absolute;
        z-index: 1100;
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-xl);
        min-width: 180px;
        animation: fadeIn 0.2s ease-out;
    }
    
    .context-item {
        padding: var(--space-md);
        display: flex;
        align-items: center;
        gap: var(--space-sm);
        color: var(--color-text);
        cursor: pointer;
        transition: all var(--transition-base);
        font-size: var(--font-size-sm);
    }
    
    .context-item:hover {
        background: var(--color-surface);
    }
    
    .context-item.danger {
        color: var(--color-danger);
    }
    
    .context-item.danger:hover {
        background: rgba(var(--color-danger-rgb), 0.1);
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
        animation: fadeInUp 0.6s ease-ou    t forwards;
    }
    
    .delay-1 { animation-delay: 0.1s; }
    .delay-2 { animation-delay: 0.2s; }
    .delay-3 { animation-delay: 0.3s; }
    .delay-4 { animation-delay: 0.4s; }
    
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
    
        .header-content {
            padding: var(--space-md);
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: var(--space-md);
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
        
        .calendar-section {
            padding: var(--space-md);
        }
        
        .fc .fc-toolbar {
            flex-direction: column;
            gap: var(--space-md);
        }
        
        .fc .fc-toolbar-title {
            font-size: var(--font-size-base);
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
        
        .calendar-section {
            padding: var(--space-md);
        }
        
        .section-title {
            font-size: var(--font-size-base);
        }
    }
    
    /* ==========================================================================
       UTILIDADES
       ========================================================================== */
    .text-primary { color: var(--color-primary); }
    .text-success { color: var(--color-success); }
    .text-warning { color: var(--color-warning); }
    .text-danger { color: var(--color-danger); }
    .text-info { color: var(--color-info); }
    .text-calendar { color: var(--color-calendar); }
    .text-muted { color: var(--color-text-secondary); }
    
    .bg-primary { background-color: var(--color-primary); }
    .bg-success { background-color: var(--color-success); }
    .bg-warning { background-color: var(--color-warning); }
    .bg-danger { background-color: var(--color-danger); }
    .bg-info { background-color: var(--color-info); }
    .bg-calendar { background-color: var(--color-calendar); }
    
    .mb-0 { margin-bottom: 0; }
    .mb-1 { margin-bottom: var(--space-xs); }
    .mb-2 { margin-bottom: var(--space-sm); }
    .mb-3 { margin-bottom: var(--space-md); }
    .mb-4 { margin-bottom: var(--space-lg); }
    .mb-5 { margin-bottom: var(--space-xl); }
    
    .mt-0 { margin-top: 0; }
    .mt-1 { margin-top: var(--space-xs); }
    .mt-2 { margin-top: var(--space-sm); }
    .mt-3 { margin-top: var(--space-md); }
    .mt-4 { margin-top: var(--space-lg); }
    .mt-5 { margin-top: var(--space-xl); }
    
    .d-none { display: none; }
    .d-block { display: block; }
    .d-flex { display: flex; }
    
    .gap-1 { gap: var(--space-xs); }
    .gap-2 { gap: var(--space-sm); }
    .gap-3 { gap: var(--space-md); }
    .gap-4 { gap: var(--space-lg); }
    .gap-5 { gap: var(--space-xl); }
    
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-left { text-align: left; }
    
    .fw-bold { font-weight: 700; }
    .fw-semibold { font-weight: 600; }
    .fw-medium { font-weight: 500; }
    .fw-normal { font-weight: 400; }
    .fw-light { font-weight: 300; }
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
 
            <!-- Estadísticas principales -->
             <?php if ($user_type === 'admin'): ?>
            <div class="stats-grid">
                <!-- Citas de hoy -->
                <div class="stat-card animate-in delay-1">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Citas Hoy</div>
                            <div class="stat-value"><?php echo $today_appointments; ?></div>
                        </div>
                        <div class="stat-icon calendar">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-arrow-up-right"></i>
                        <span>Programadas para hoy</span>
                    </div>
                </div>
                
                <!-- Citas totales -->
                <div class="stat-card animate-in delay-2">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Citas Totales</div>
                            <div class="stat-value"><?php echo $total_appointments; ?></div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="bi bi-calendar-week"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-calendar-plus"></i>
                        <span>En el sistema</span>
                    </div>
                </div>
                
                <!-- Doctores disponibles -->
                <div class="stat-card animate-in delay-3">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Doctores</div>
                            <div class="stat-value"><?php echo count($doctors); ?></div>
                        </div>
                        <div class="stat-icon success">
                            <i class="bi bi-person-badge"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-person-plus"></i>
                        <span>Disponibles</span>
                    </div>
                </div>
                
                <!-- Horario -->
                <div class="stat-card animate-in delay-4">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Horario</div>
                            <div class="stat-value">8-20h</div>
                        </div>
                        <div class="stat-icon info">
                            <i class="bi bi-clock"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-clock-history"></i>
                        <span>Lunes a Sábado</span>
                    </div>
                </div>
            </div>  
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
                            <i class="bi bi-building me-1"></i> Centro Médico Herrera Saenz
                        </p>
                    </div>
                    <div class="d-none d-md-block">
                        <i class="bi bi-calendar-heart text-calendar" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
            
            <!-- Sección principal del calendario -->
            <section class="calendar-section animate-in delay-1">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-calendar-heart section-title-icon"></i>
                        Calendario de Citas
                    </h3>
                    <div class="d-flex gap-2">
                        <button type="button" class="action-btn" data-bs-toggle="modal" data-bs-target="#newAppointmentModal">
                            <i class="bi bi-plus-lg"></i>
                            Nueva Cita
                        </button>
                    </div>
                </div>
                
                <!-- Contenedor del calendario -->
                <div id="calendar"></div>
            </section>
        </main>
    </div>
    
    <!-- Modal para nueva cita -->
    <div class="modal fade" id="newAppointmentModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-calendar-plus"></i>
                        Programar Nueva Cita
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="appointmentForm" action="save_appointment.php" method="POST">
                    <div class="modal-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">Nombre del Paciente</label>
                                <div class="input-icon-wrapper">
                                    <i class="bi bi-person"></i>
                                    <input type="text" class="form-control" name="nombre_pac" placeholder="Ej. Juan" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Apellido del Paciente</label>
                                <div class="input-icon-wrapper">
                                    <i class="bi bi-person"></i>
                                    <input type="text" class="form-control" name="apellido_pac" placeholder="Ej. Pérez" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fecha de la Cita</label>
                                <div class="input-icon-wrapper">
                                    <i class="bi bi-calendar-event"></i>
                                    <input type="date" class="form-control" name="fecha_cita" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Hora de la Cita</label>
                                <div class="input-icon-wrapper">
                                    <i class="bi bi-clock"></i>
                                    <input type="time" class="form-control" name="hora_cita" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Teléfono de Contacto</label>
                                <div class="input-icon-wrapper">
                                    <i class="bi bi-telephone"></i>
                                    <input type="tel" class="form-control" name="telefono" placeholder="Ej. 5555-5555">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Médico Asignado</label>
                                <div class="input-icon-wrapper">
                                    <i class="bi bi-person-badge"></i>
                                    <select class="form-select ps-5" name="id_doctor" required>
                                        <option value="">Seleccionar médico...</option>
                                        <?php foreach ($doctors as $doc): ?>
                                            <option value="<?php echo $doc['idUsuario']; ?>">
                                                Dr(a). <?php echo htmlspecialchars($doc['nombre'] . ' ' . $doc['apellido']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="action-btn secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="action-btn">
                            <i class="bi bi-check2-circle me-1"></i>
                            Programar Cita
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal para editar cita -->
    <div class="modal fade" id="editAppointmentModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil-square"></i>
                        Editar Cita
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editAppointmentForm" action="update_appointment.php" method="POST">
                    <input type="hidden" name="id_cita" id="edit_id_cita">
                    <div class="modal-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">Nombre del Paciente</label>
                                <div class="input-icon-wrapper">
                                    <i class="bi bi-person"></i>
                                    <input type="text" class="form-control" name="nombre_pac" id="edit_nombre_pac" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Apellido del Paciente</label>
                                <div class="input-icon-wrapper">
                                    <i class="bi bi-person"></i>
                                    <input type="text" class="form-control" name="apellido_pac" id="edit_apellido_pac" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fecha de la Cita</label>
                                <div class="input-icon-wrapper">
                                    <i class="bi bi-calendar-event"></i>
                                    <input type="date" class="form-control" name="fecha_cita" id="edit_fecha_cita" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Hora de la Cita</label>
                                <div class="input-icon-wrapper">
                                    <i class="bi bi-clock"></i>
                                    <input type="time" class="form-control" name="hora_cita" id="edit_hora_cita" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Teléfono de Contacto</label>
                                <div class="input-icon-wrapper">
                                    <i class="bi bi-telephone"></i>
                                    <input type="tel" class="form-control" name="telefono" id="edit_telefono">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Médico Asignado</label>
                                <div class="input-icon-wrapper">
                                    <i class="bi bi-person-badge"></i>
                                    <select class="form-select ps-5" name="id_doctor" id="edit_id_doctor" required>
                                        <option value="">Seleccionar médico...</option>
                                        <?php foreach ($doctors as $doc): ?>
                                            <option value="<?php echo $doc['idUsuario']; ?>">
                                                Dr(a). <?php echo htmlspecialchars($doc['nombre'] . ' ' . $doc['apellido']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="action-btn secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="action-btn">
                            <i class="bi bi-arrow-repeat me-1"></i>
                            Actualizar Cita
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Menú contextual -->
    <div id="contextMenu" class="context-menu">
        <div class="context-item" id="contextEdit">
            <i class="bi bi-pencil text-calendar"></i>
            <span>Editar cita</span>
        </div>
        <div class="context-item danger" id="contextDelete">
            <i class="bi bi-trash text-danger"></i>
            <span>Eliminar cita</span>
        </div>
    </div>
    
    <!-- JavaScript Optimizado -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/es.js'></script>
    <script>
    // Calendario de Citas Reingenierizado - Centro Médico Herrera Saenz
    
    (function() {
        'use strict';
        
        // ==========================================================================
        // CONFIGURACIÓN Y CONSTANTES
        // ==========================================================================
        const CONFIG = {
            themeKey: 'dashboard-theme',

            calendarViewKey: 'calendar-view',
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
            calendar: document.getElementById('calendar'),
            contextMenu: document.getElementById('contextMenu'),
            contextEdit: document.getElementById('contextEdit'),
            contextDelete: document.getElementById('contextDelete')
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
                
                // Si el calendario ya está inicializado, forzar redibujado
                if (window.calendar) {
                    setTimeout(() => window.calendar.render(), 100);
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
        // CALENDARIO FULLCALENDAR
        // ==========================================================================
        class CalendarManager {
            constructor() {
                this.calendar = null;
                this.currentEvent = null;
                this.initialize();
            }
            
            initialize() {
                if (!DOM.calendar) return;
                
                // Obtener vista guardada o usar por defecto
                const savedView = localStorage.getItem(CONFIG.calendarViewKey) || 'dayGridMonth';
                
                this.calendar = new FullCalendar.Calendar(DOM.calendar, {
                    initialView: savedView,
                    locale: 'es',
                    themeSystem: 'standard',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                    },
                    buttonText: {
                        today: 'Hoy',
                        month: 'Mes',
                        week: 'Semana',
                        day: 'Día',
                        list: 'Lista'
                    },
                    firstDay: 1, // Lunes
                    navLinks: true,
                    editable: true,
                    selectable: true,
                    nowIndicator: true,
                    dayMaxEvents: 3,
                    height: 'auto',
                    slotMinTime: '08:00:00',
                    slotMaxTime: '20:00:00',
                    businessHours: {
                        daysOfWeek: [1, 2, 3, 4, 5, 6], // Lunes a Sábado
                        startTime: '08:00',
                        endTime: '20:00'
                    },
                    
                    // Cargar eventos
                    events: 'get_appointments.php',
                    
                    // Manejar clic en fecha
                    dateClick: (info) => {
                        // Prellenar fecha en el modal de nueva cita
                        document.querySelector('#newAppointmentModal input[name="fecha_cita"]').value = info.dateStr;
                        
                        // Mostrar modal
                        const modal = new bootstrap.Modal(document.getElementById('newAppointmentModal'));
                        modal.show();
                    },
                    
                    // Manejar cambio de vista
                    viewDidMount: (view) => {
                        localStorage.setItem(CONFIG.calendarViewKey, view.view.type);
                    },
                    
                    // Estilizar eventos
                    eventDidMount: (info) => {
                        // Agregar clase según el tipo de evento
                        const eventType = info.event.extendedProps.tipo || 'primary';
                        info.el.classList.add(`fc-event-${eventType}`);
                        
                        // Agregar tooltip
                        const title = info.event.title;
                        const time = info.event.start ? 
                            info.event.start.toLocaleTimeString('es-GT', { hour: '2-digit', minute: '2-digit' }) : '';
                        const doctor = info.event.extendedProps.doctor || '';
                        
                        info.el.title = `${title}\n${time}\n${doctor}`;

                        // Manejar click derecho
                        info.el.addEventListener('contextmenu', (e) => {
                            e.preventDefault();
                            this.currentEvent = info.event;
                            this.showContextMenu(e.pageX, e.pageY);
                            return false;
                        });
                    }
                });
                
                this.calendar.render();
                
                // Exponer calendario globalmente
                window.calendar = this.calendar;
            }
            
            refresh() {
                if (this.calendar) {
                    this.calendar.refetchEvents();
                }
            }
            
            showContextMenu(x, y) {
                DOM.contextMenu.style.display = 'block';
                DOM.contextMenu.style.left = x + 'px';
                DOM.contextMenu.style.top = y + 'px';
                
                // Ajustar posición si sale de la ventana
                const menuRect = DOM.contextMenu.getBoundingClientRect();
                const windowWidth = window.innerWidth;
                const windowHeight = window.innerHeight;
                
                if (menuRect.right > windowWidth) {
                    DOM.contextMenu.style.left = (x - menuRect.width) + 'px';
                }
                
                if (menuRect.bottom > windowHeight) {
                    DOM.contextMenu.style.top = (y - menuRect.height) + 'px';
                }
            }
            
            hideContextMenu() {
                DOM.contextMenu.style.display = 'none';
            }
            
            editCurrentEvent() {
                if (!this.currentEvent) return;
                
                fetch('get_appointment_details.php?id=' + this.currentEvent.id)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            document.getElementById('edit_id_cita').value = data.id_cita;
                            document.getElementById('edit_nombre_pac').value = data.nombre_pac;
                            document.getElementById('edit_apellido_pac').value = data.apellido_pac;
                            document.getElementById('edit_fecha_cita').value = data.fecha_cita;
                            document.getElementById('edit_hora_cita').value = data.hora_cita;
                            document.getElementById('edit_telefono').value = data.telefono || '';
                            document.getElementById('edit_id_doctor').value = data.id_doctor;
                            
                            const modal = new bootstrap.Modal(document.getElementById('editAppointmentModal'));
                            modal.show();
                        } else {
                            this.showNotification('Error al cargar detalles: ' + data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        this.showNotification('Error al cargar los detalles de la cita', 'error');
                    });
                
                this.hideContextMenu();
            }
            
            deleteCurrentEvent() {
                if (!this.currentEvent) return;

                this.hideContextMenu();
                
                Swal.fire({
                    title: '¿Eliminar cita?',
                    text: "Esta acción no se puede deshacer",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: 'var(--color-danger)',
                    cancelButtonColor: 'var(--color-secondary)',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                    background: 'var(--color-card)',
                    color: 'var(--color-text)'
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch('delete_appointment.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ id: this.currentEvent.id })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                this.refresh();
                                this.showNotification('Cita eliminada correctamente', 'success');
                            } else {
                                this.showNotification('Error: ' + data.message, 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            this.showNotification('No se pudo procesar la solicitud', 'error');
                        });
                    }
                });
            }
            
            showNotification(message, type = 'info') {
                const icon = {
                    success: 'bi-check-circle-fill',
                    error: 'bi-exclamation-triangle-fill',
                    warning: 'bi-exclamation-circle-fill',
                    info: 'bi-info-circle-fill'
                }[type];
                
                const color = {
                    success: 'var(--color-success)',
                    error: 'var(--color-danger)',
                    warning: 'var(--color-warning)',
                    info: 'var(--color-info)'
                }[type];
                
                const notification = document.createElement('div');
                notification.className = 'stat-card mb-4 animate-in';
                notification.style.borderLeft = `4px solid ${color}`;
                notification.style.animation = 'fadeInUp 0.4s ease-out';
                
                notification.innerHTML = `
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi ${icon}" style="color: ${color}; font-size: 1.25rem;"></i>
                            <div>
                                <p class="mb-0">${message}</p>
                            </div>
                        </div>
                        <button type="button" class="btn-close" onclick="this.parentElement.parentElement.remove()"></button>
                    </div>
                `;
                
                const mainContent = document.querySelector('.main-content');
                const firstChild = mainContent.firstChild;
                mainContent.insertBefore(notification, firstChild);
                
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.style.opacity = '0';
                        notification.style.transform = 'translateY(-10px)';
                        setTimeout(() => notification.remove(), 300);
                    }
                }, 5000);
            }
        }
        
        // ==========================================================================
        // COMPONENTES DINÁMICOS
        // ==========================================================================
        class DynamicComponents {
            constructor() {
                this.setupGreeting();
                this.setupClock();
                this.setupFormHandlers();
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
            
            setupFormHandlers() {
                // Formulario de nueva cita
                const appointmentForm = document.getElementById('appointmentForm');
                if (appointmentForm) {
                    appointmentForm.addEventListener('submit', (e) => {
                        e.preventDefault();
                        this.handleFormSubmit(appointmentForm, 'save_appointment.php', 'Cita programada correctamente');
                    });
                }
                
                // Formulario de edición de cita
                const editAppointmentForm = document.getElementById('editAppointmentForm');
                if (editAppointmentForm) {
                    editAppointmentForm.addEventListener('submit', (e) => {
                        e.preventDefault();
                        this.handleFormSubmit(editAppointmentForm, 'update_appointment.php', 'Cita actualizada correctamente');
                    });
                }
            }
            
            handleFormSubmit(form, action, successMessage) {
                const formData = new FormData(form);
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split spin me-2"></i>Procesando...';
                submitBtn.disabled = true;
                
                fetch(action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const modalId = form.id === 'appointmentForm' ? 'newAppointmentModal' : 'editAppointmentModal';
                        const modal = bootstrap.Modal.getInstance(document.getElementById(modalId));
                        modal.hide();
                        
                        form.reset();
                        
                        if (window.calendarManager) {
                            window.calendarManager.refresh();
                            window.calendarManager.showNotification(successMessage, 'success');
                        }
                    } else {
                        window.calendarManager.showNotification('Error: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    window.calendarManager.showNotification('Error al procesar la solicitud', 'error');
                })
                .finally(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
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
                
                document.querySelectorAll('.stat-card, .calendar-section').forEach(el => {
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
            const calendarManager = new CalendarManager();
            const dynamicComponents = new DynamicComponents();
            
            // Configurar menú contextual
            if (DOM.contextEdit && DOM.contextDelete) {
                DOM.contextEdit.addEventListener('click', () => calendarManager.editCurrentEvent());
                DOM.contextDelete.addEventListener('click', () => calendarManager.deleteCurrentEvent());
                
                document.addEventListener('click', (e) => {
                    if (!DOM.contextMenu.contains(e.target)) {
                        calendarManager.hideContextMenu();
                    }
                });
            }
            
            // Exponer APIs necesarias globalmente
            window.app = {
                theme: themeManager,
                calendar: calendarManager,
                components: dynamicComponents
            };
            
            window.calendarManager = calendarManager;
            
            // Log de inicialización
            console.log('Calendario de Citas - CMS v4.0 inicializado correctamente');
            console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
            console.log('Rol: <?php echo htmlspecialchars($user_type); ?>');
        });
        
        // ==========================================================================
        // MANEJO DE ERRORES GLOBALES
        // ==========================================================================
        window.addEventListener('error', (event) => {
            console.error('Error en calendario de citas:', event.error);
            
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
</body>
</html>