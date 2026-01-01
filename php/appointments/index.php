<?php
// index.php - Calendario de Citas - Centro Médico Herrera Saenz
// Versión: 3.0 - Diseño Minimalista con Modo Noche y Efecto Mármol
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
    <title><?php echo $page_title; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">
    
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- FullCalendar CSS -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet'>
    
    <!-- Incluir header -->
    <?php include_once '../../includes/header.php'; ?>
    
    <style>
    /* 
     * Calendario de Citas Minimalista - Centro Médico Herrera Saenz
     * Diseño: Fondo blanco, colores pastel, efecto mármol, modo noche
     * Versión: 3.0
     */
    
    /* Variables CSS para modo claro y oscuro (consistentes con dashboard) */
    :root {
        /* Modo claro (predeterminado) - Colores pastel */
        --color-background: #f8fafc;
        --color-surface: #ffffff;
        --color-primary: #7c90db;
        --color-primary-light: #a3b1e8;
        --color-primary-dark: #5a6fca;
        --color-secondary: #8dd7bf;
        --color-secondary-light: #b2e6d5;
        --color-accent: #f8b195;
        --color-text: #1e293b;
        --color-text-light: #64748b;
        --color-text-muted: #94a3b8;
        --color-border: #e2e8f0;
        --color-border-light: #f1f5f9;
        --color-error: #f87171;
        --color-warning: #fbbf24;
        --color-success: #34d399;
        --color-info: #38bdf8;
        
        /* Efecto mármol */
        --marble-bg: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        --marble-pattern: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23e2e8f0' fill-opacity='0.2' fill-rule='evenodd'/%3E%3C/svg%3E");
        
        /* Sombras sutiles */
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.08);
        --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        
        /* Bordes redondeados */
        --radius-sm: 8px;
        --radius-md: 12px;
        --radius-lg: 16px;
        --radius-xl: 20px;
        
        /* Transiciones */
        --transition-fast: 150ms ease;
        --transition-normal: 250ms ease;
        --transition-slow: 350ms ease;
    }
    
    /* Variables para modo oscuro */
    [data-theme="dark"] {
        --color-background: #0f172a;
        --color-surface: #1e293b;
        --color-primary: #7c90db;
        --color-primary-light: #a3b1e8;
        --color-primary-dark: #5a6fca;
        --color-secondary: #8dd7bf;
        --color-secondary-light: #b2e6d5;
        --color-accent: #f8b195;
        --color-text: #f1f5f9;
        --color-text-light: #cbd5e1;
        --color-text-muted: #94a3b8;
        --color-border: #334155;
        --color-border-light: #1e293b;
        --color-error: #f87171;
        --color-warning: #fbbf24;
        --color-success: #34d399;
        --color-info: #38bdf8;
        
        /* Efecto mármol oscuro */
        --marble-bg: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        --marble-pattern: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23334155' fill-opacity='0.2' fill-rule='evenodd'/%3E%3C/svg%3E");
        
        /* Sombras más sutiles en modo oscuro */
        --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.2);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.4);
        --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
    }
    
    /* Reset y estilos base */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }
    
    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        background: var(--color-background);
        color: var(--color-text);
        min-height: 100vh;
        transition: background-color var(--transition-normal), color var(--transition-normal);
        line-height: 1.5;
        position: relative;
        overflow-x: hidden;
    }
    
    /* Fondo con efecto mármol sutil */
    body::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-image: var(--marble-pattern), var(--marble-bg);
        background-size: 300px, cover;
        background-attachment: fixed;
        z-index: -1;
        opacity: 0.8;
    }
    
    /* Contenedor principal */
    .dashboard-container {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        position: relative;
    }
    
    /* ============ HEADER SUPERIOR ============ */
    .dashboard-header {
        background: var(--color-surface);
        border-bottom: 1px solid var(--color-border);
        padding: 1rem 2rem;
        position: sticky;
        top: 0;
        z-index: 100;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        animation: slideDown 0.4s ease-out;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .header-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        max-width: 1400px;
        margin: 0 auto;
    }
    
    /* Logo y marca */
    .brand-container {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .brand-logo {
        height: 48px;
        width: auto;
        filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        transition: transform var(--transition-normal);
    }
    
    .brand-logo:hover {
        transform: scale(1.05);
    }
    
    /* Control de tema y usuario */
    .header-controls {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }
    
    /* Botón de cambio de tema */
    .theme-toggle {
        position: relative;
    }
    
    .theme-btn {
        background: transparent;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        width: 44px;
        height: 44px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all var(--transition-normal);
        color: var(--color-text);
        position: relative;
        overflow: hidden;
    }
    
    .theme-btn:hover {
        background: var(--color-primary-light);
        color: white;
        border-color: var(--color-primary);
        transform: rotate(15deg);
    }
    
    .theme-icon {
        width: 20px;
        height: 20px;
        transition: opacity var(--transition-normal), transform var(--transition-normal);
    }
    
    .sun-icon {
        color: var(--color-warning);
    }
    
    .moon-icon {
        color: var(--color-primary-light);
    }
    
    [data-theme="light"] .moon-icon {
        display: none;
    }
    
    [data-theme="dark"] .sun-icon {
        display: none;
    }
    
    /* Información del usuario */
    .user-info {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.5rem;
        border-radius: var(--radius-md);
        transition: background-color var(--transition-normal);
    }
    
    .user-info:hover {
        background: var(--color-border-light);
    }
    
    .user-avatar {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        color: white;
        font-size: 16px;
        flex-shrink: 0;
    }
    
    .user-details {
        display: flex;
        flex-direction: column;
    }
    
    .user-name {
        font-weight: 600;
        color: var(--color-text);
        font-size: 0.95rem;
    }
    
    .user-role {
        font-size: 0.8rem;
        color: var(--color-text-light);
    }
    
    /* ============ BARRA LATERAL ============ */
    .sidebar {
        width: 260px;
        background: var(--color-surface);
        border-right: 1px solid var(--color-border);
        position: fixed;
        top: 81px; /* Altura del header */
        left: 0;
        bottom: 0;
        z-index: 90;
        padding: 1.5rem;
        overflow-y: auto;
        transition: transform var(--transition-normal), width var(--transition-normal);
        animation: slideInLeft 0.5s ease-out;
    }
    
    @keyframes slideInLeft {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    .sidebar.collapsed {
        width: 80px;
    }
    
    .sidebar.collapsed .nav-text {
        display: none;
    }
    
    /* Navegación */
    .nav-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .nav-item {
        margin-bottom: 0.5rem;
    }
    
    .nav-link {
        display: flex;
        align-items: center;
        padding: 0.875rem 1rem;
        color: var(--color-text);
        text-decoration: none;
        border-radius: var(--radius-md);
        transition: all var(--transition-normal);
        font-weight: 500;
        position: relative;
        overflow: hidden;
    }
    
    .nav-link:hover {
        background: var(--color-border-light);
        color: var(--color-primary);
        transform: translateX(4px);
    }
    
    .sidebar.collapsed .nav-link:hover {
        transform: scale(1.05);
    }
    
    .nav-link.active {
        background: var(--color-primary);
        color: white;
        box-shadow: var(--shadow-md);
    }
    
    .nav-icon {
        font-size: 1.25rem;
        margin-right: 1rem;
        width: 24px;
        text-align: center;
        flex-shrink: 0;
    }
    
    .sidebar.collapsed .nav-icon {
        margin-right: 0;
        font-size: 1.35rem;
    }
    
    .nav-text {
        font-size: 0.95rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    /* Contenido principal */
    .main-content {
        margin-left: 260px;
        padding: 2rem;
        min-height: calc(100vh - 81px);
        transition: margin-left var(--transition-normal);
        max-width: 1400px;
        margin-right: auto;
        margin-left: auto;
        width: calc(100% - 260px);
    }
    
    .sidebar.collapsed ~ .main-content {
        margin-left: 80px;
        width: calc(100% - 80px);
    }
    
    /* ============ CABECERA DE LA PÁGINA ============ */
    .page-header {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
        animation: fadeIn 0.6s ease-out 0.2s both;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }
    
    .page-title {
        font-size: 1.75rem;
        font-weight: 600;
        color: var(--color-text);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .page-subtitle {
        color: var(--color-text-light);
        font-size: 0.95rem;
    }
    
    .page-actions {
        display: flex;
        gap: 1rem;
        margin-top: 1rem;
    }
    
    .action-btn {
        background: var(--color-primary);
        color: white;
        border: none;
        border-radius: var(--radius-md);
        padding: 0.625rem 1.25rem;
        font-weight: 500;
        font-size: 0.875rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all var(--transition-normal);
        text-decoration: none;
    }
    
    .action-btn:hover {
        background: var(--color-primary-dark);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    
    .action-btn.secondary {
        background: var(--color-surface);
        color: var(--color-text);
        border: 1px solid var(--color-border);
    }
    
    .action-btn.secondary:hover {
        background: var(--color-border-light);
        border-color: var(--color-primary-light);
    }
    
    /* ============ CALENDARIO ============ */
    .calendar-container {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-bottom: 2rem;
        animation: fadeIn 0.6s ease-out 0.3s both;
        min-height: 600px;
    }
    
    /* Personalización de FullCalendar */
    .fc {
        --fc-page-bg-color: transparent;
        --fc-neutral-bg-color: var(--color-border-light);
        --fc-neutral-text-color: var(--color-text-light);
        --fc-border-color: var(--color-border);
        --fc-button-bg-color: var(--color-surface);
        --fc-button-border-color: var(--color-border);
        --fc-button-hover-bg-color: var(--color-border-light);
        --fc-button-hover-border-color: var(--color-primary-light);
        --fc-button-active-bg-color: var(--color-primary);
        --fc-button-active-border-color: var(--color-primary);
        --fc-event-bg-color: var(--color-primary);
        --fc-event-border-color: var(--color-primary);
        --fc-event-text-color: white;
        --fc-today-bg-color: var(--color-primary-light);
        --fc-now-indicator-color: var(--color-accent);
    }
    
    .fc .fc-toolbar-title {
        color: var(--color-text);
        font-weight: 600;
        font-size: 1.25rem;
    }
    
    .fc .fc-button {
        border-radius: var(--radius-sm);
        padding: 0.375rem 0.75rem;
        font-weight: 500;
        font-size: 0.875rem;
        transition: all var(--transition-normal);
    }
    
    .fc .fc-button:hover {
        transform: translateY(-2px);
    }
    
    .fc .fc-button-primary:not(:disabled).fc-button-active,
    .fc .fc-button-primary:not(:disabled):active {
        background-color: var(--color-primary);
        border-color: var(--color-primary);
    }
    
    .fc .fc-daygrid-day {
        border-radius: var(--radius-sm);
        transition: background-color var(--transition-normal);
    }
    
    .fc .fc-daygrid-day:hover {
        background-color: var(--color-border-light);
    }
    
    .fc .fc-day-today {
        background-color: var(--color-primary-light);
    }
    
    .fc .fc-event {
        border-radius: var(--radius-sm);
        border: none;
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all var(--transition-normal);
    }
    
    .fc .fc-event:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    
    .fc .fc-event-primary {
        background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
    }
    
    .fc .fc-event-secondary {
        background: linear-gradient(135deg, var(--color-secondary), var(--color-secondary-light));
    }
    
    .fc .fc-event-accent {
        background: linear-gradient(135deg, var(--color-accent), #f5926e);
    }
    
    /* ============ MODALES ============ */
    .modal-content {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        color: var(--color-text);
    }
    
    .modal-header {
        border-bottom: 1px solid var(--color-border);
        padding: 1.5rem;
    }
    
    .modal-title {
        color: var(--color-text);
        font-weight: 600;
        font-size: 1.25rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .modal-body {
        padding: 1.5rem;
    }
    
    /* Formularios */
    .form-group {
        margin-bottom: 1.25rem;
    }
    
    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--color-text);
        font-weight: 500;
        font-size: 0.875rem;
    }
    
    .form-control {
        width: 100%;
        padding: 0.75rem 1rem;
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        color: var(--color-text);
        font-size: 0.95rem;
        transition: all var(--transition-normal);
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px var(--color-primary-light);
    }
    
    .form-select {
        width: 100%;
        padding: 0.75rem 1rem;
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        color: var(--color-text);
        font-size: 0.95rem;
        transition: all var(--transition-normal);
        cursor: pointer;
    }
    
    .form-select:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px var(--color-primary-light);
    }
    
    .modal-footer {
        border-top: 1px solid var(--color-border);
        padding: 1.5rem;
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
    }
    
    /* ============ MENÚ CONTEXTUAL ============ */
    .context-menu {
        display: none;
        position: absolute;
        z-index: 1000;
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-xl);
        min-width: 180px;
        animation: fadeIn 0.2s ease-out;
    }
    
    .context-item {
        padding: 0.75rem 1rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: var(--color-text);
        cursor: pointer;
        transition: all var(--transition-fast);
        font-size: 0.875rem;
    }
    
    .context-item:hover {
        background: var(--color-border-light);
    }
    
    .context-item.danger {
        color: var(--color-error);
    }
    
    .context-item.danger:hover {
        background: var(--color-error);
        opacity: 0.1;
    }
    
    /* ============ BOTÓN TOGGLE SIDEBAR ============ */
    .sidebar-toggle {
        position: fixed;
        bottom: 2rem;
        left: 280px;
        width: 40px;
        height: 40px;
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 95;
        transition: all var(--transition-normal);
        box-shadow: var(--shadow-md);
        color: var(--color-text);
    }
    
    .sidebar-toggle:hover {
        background: var(--color-primary);
        color: white;
        border-color: var(--color-primary);
        transform: scale(1.1);
    }
    
    .sidebar.collapsed ~ .sidebar-toggle {
        left: 100px;
    }
    
    /* ============ EFECTOS DE MÁRMOL ANIMADOS ============ */
    .marble-effect {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        pointer-events: none;
        z-index: -1;
        opacity: 0.3;
        background-image: 
            radial-gradient(circle at 20% 30%, rgba(124, 144, 219, 0.05) 0%, transparent 30%),
            radial-gradient(circle at 80% 70%, rgba(141, 215, 191, 0.05) 0%, transparent 30%),
            radial-gradient(circle at 40% 80%, rgba(248, 177, 149, 0.05) 0%, transparent 30%);
        animation: marbleFloat 20s ease-in-out infinite;
    }
    
    @keyframes marbleFloat {
        0%, 100% {
            transform: translate(0, 0) rotate(0deg);
        }
        25% {
            transform: translate(10px, 5px) rotate(0.5deg);
        }
        50% {
            transform: translate(5px, 10px) rotate(-0.5deg);
        }
        75% {
            transform: translate(-5px, 5px) rotate(0.3deg);
        }
    }
    
    /* ============ RESPONSIVE DESIGN ============ */
    @media (max-width: 1200px) {
        .main-content {
            padding: 1.5rem;
        }
    }
    
    @media (max-width: 992px) {
        .sidebar {
            transform: translateX(-100%);
            width: 280px;
        }
        
        .sidebar.show {
            transform: translateX(0);
        }
        
        .main-content {
            margin-left: 0;
            width: 100%;
        }
        
        .sidebar-toggle {
            display: none;
        }
        
        /* Botón móvil para mostrar sidebar */
        .mobile-sidebar-toggle {
            display: block;
            position: fixed;
            top: 1.5rem;
            left: 1.5rem;
            z-index: 101;
            width: 44px;
            height: 44px;
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            color: var(--color-text);
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: var(--shadow-md);
        }
    }
    
    @media (min-width: 993px) {
        .mobile-sidebar-toggle {
            display: none;
        }
    }
    
    @media (max-width: 768px) {
        .dashboard-header {
            padding: 1rem;
        }
        
        .header-content {
            flex-direction: column;
            gap: 1rem;
            align-items: flex-start;
        }
        
        .header-controls {
            width: 100%;
            justify-content: space-between;
        }
        
        .main-content {
            padding: 1rem;
        }
        
        .page-header {
            padding: 1.25rem;
        }
        
        .page-title {
            font-size: 1.5rem;
        }
        
        .calendar-container {
            padding: 1rem;
        }
        
        .fc .fc-toolbar {
            flex-direction: column;
            gap: 1rem;
        }
        
        .fc .fc-toolbar-title {
            font-size: 1.125rem;
        }
    }
    
    @media (max-width: 480px) {
        .page-actions {
            flex-direction: column;
        }
        
        .action-btn {
            width: 100%;
            justify-content: center;
        }
    }
    
    /* ============ PREFERENCIAS DE MOVIMIENTO REDUCIDO ============ */
    @media (prefers-reduced-motion: reduce) {
        *,
        *::before,
        *::after {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
        }
        
        .marble-effect {
            display: none;
        }
    }
    
    /* ============ MEJORAS DE ACCESIBILIDAD ============ */
    @media (prefers-contrast: high) {
        :root {
            --color-text: #000000;
            --color-text-light: #333333;
            --color-border: #000000;
        }
        
        [data-theme="dark"] {
            --color-text: #ffffff;
            --color-text-light: #cccccc;
            --color-border: #ffffff;
        }
    }
    
    /* ============ UTILIDADES ============ */
    .text-primary { color: var(--color-primary) !important; }
    .text-success { color: var(--color-success) !important; }
    .text-warning { color: var(--color-warning) !important; }
    .text-danger { color: var(--color-error) !important; }
    .text-info { color: var(--color-info) !important; }
    .text-muted { color: var(--color-text-light) !important; }
    
    .bg-primary { background: var(--color-primary) !important; }
    .bg-success { background: var(--color-success) !important; }
    .bg-warning { background: var(--color-warning) !important; }
    .bg-danger { background: var(--color-error) !important; }
    .bg-info { background: var(--color-info) !important; }
    
    .mb-3 { margin-bottom: 1rem !important; }
    .mb-4 { margin-bottom: 1.5rem !important; }
    .mt-3 { margin-top: 1rem !important; }
    .mt-4 { margin-top: 1.5rem !important; }
    .ms-2 { margin-left: 0.5rem !important; }
    .me-2 { margin-right: 0.5rem !important; }
    .gap-2 { gap: 0.5rem !important; }
    .gap-3 { gap: 1rem !important; }
    
    .d-flex { display: flex !important; }
    .d-none { display: none !important; }
    .align-items-center { align-items: center !important; }
    .justify-content-between { justify-content: space-between !important; }
    .justify-content-end { justify-content: flex-end !important; }
    .flex-column { flex-direction: column !important; }
    </style>
</head>
<body>
    <!-- Efecto de mármol animado -->
    <div class="marble-effect"></div>
    
    <!-- Botón móvil para mostrar/ocultar sidebar -->
    <button class="mobile-sidebar-toggle" id="mobileSidebarToggle" aria-label="Mostrar/ocultar menú">
        <i class="bi bi-list"></i>
    </button>
    
    <div class="dashboard-container">
        <!-- Header superior -->
        <header class="dashboard-header">
            <div class="header-content">
                <!-- Logo y marca -->
                <div class="brand-container">
                    <img src="../../assets/img/herrerasaenz.png" alt="Centro Médico Herrera Saenz" class="brand-logo">
                </div>
                
                <!-- Controles del header -->
                <div class="header-controls">
                    <!-- Control de tema -->
                    <div class="theme-toggle">
                        <button id="themeSwitch" class="theme-btn" aria-label="Cambiar tema claro/oscuro">
                            <i class="bi bi-sun theme-icon sun-icon"></i>
                            <i class="bi bi-moon theme-icon moon-icon"></i>
                        </button>
                    </div>
                    
                    <!-- Información del usuario -->
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                            <span class="user-role"><?php echo htmlspecialchars($user_specialty); ?></span>
                        </div>
                    </div>
                    
                    <!-- Botón de cerrar sesión -->
                    <a href="../auth/logout.php" class="action-btn logout-btn" title="Cerrar sesión">
                        <i class="bi bi-box-arrow-right"></i>
                        <span class="d-none d-md-inline">Salir</span>
                    </a>
                </div>
            </div>
        </header>
        
        <!-- Sidebar de navegación -->
        <nav class="sidebar" id="sidebar">
            <ul class="nav-menu">
                <?php $role = $user_type; ?>
                
                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="../dashboard/index.php" class="nav-link">
                        <i class="bi bi-grid-1x2-fill nav-icon"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                
                <!-- Pacientes (todos los roles) -->
                <?php if (in_array($role, ['admin', 'doc', 'user'])): ?>
                <li class="nav-item">
                    <a href="../patients/index.php" class="nav-link">
                        <i class="bi bi-person-vcard nav-icon"></i>
                        <span class="nav-text">Pacientes</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- Citas (admin y user) -->
                <?php if (in_array($role, ['admin', 'user'])): ?>
                <li class="nav-item">
                    <a href="../appointments/index.php" class="nav-link active">
                        <i class="bi bi-calendar-heart nav-icon"></i>
                        <span class="nav-text">Citas</span>
                    </a>
                </li>
                
                <!-- Procedimientos menores -->
                <li class="nav-item">
                    <a href="../minor_procedures/index.php" class="nav-link">
                        <i class="bi bi-bandaid nav-icon"></i>
                        <span class="nav-text">Proc. Menores</span>
                    </a>
                </li>
                
                <!-- Exámenes -->
                <li class="nav-item">
                    <a href="../examinations/index.php" class="nav-link">
                        <i class="bi bi-clipboard2-pulse nav-icon"></i>
                        <span class="nav-text">Exámenes</span>
                    </a>
                </li>
                
                <!-- Dispensario -->
                <li class="nav-item">
                    <a href="../dispensary/index.php" class="nav-link">
                        <i class="bi bi-capsule nav-icon"></i>
                        <span class="nav-text">Dispensario</span>
                    </a>
                </li>
                
                <!-- Inventario -->
                <li class="nav-item">
                    <a href="../inventory/index.php" class="nav-link">
                        <i class="bi bi-box-seam nav-icon"></i>
                        <span class="nav-text">Inventario</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- Compras, Ventas, Reportes (solo admin) -->
                <?php if ($role === 'admin'): ?>
                <li class="nav-item">
                    <a href="../purchases/index.php" class="nav-link">
                        <i class="bi bi-cart-check nav-icon"></i>
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
                    <a href="../reports/index.php" class="nav-link">
                        <i class="bi bi-graph-up-arrow nav-icon"></i>
                        <span class="nav-text">Reportes</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- Cobros (admin y user) -->
                <?php if (in_array($role, ['admin', 'user'])): ?>
                <li class="nav-item">
                    <a href="../billing/index.php" class="nav-link">
                        <i class="bi bi-credit-card-2-front nav-icon"></i>
                        <span class="nav-text">Cobros</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        
        <!-- Botón para colapsar/expandir sidebar (escritorio) -->
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Colapsar/expandir menú">
            <i class="bi bi-chevron-left" id="sidebarToggleIcon"></i>
        </button>
        
        <!-- Contenido principal -->
        <main class="main-content">
            <!-- Notificaciones -->
            <?php if (isset($_SESSION['appointment_message'])): ?>
            <div class="page-header mb-4" style="border-left: 4px solid <?php echo $_SESSION['appointment_status'] === 'success' ? 'var(--color-success)' : 'var(--color-error)'; ?>;">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi <?php echo $_SESSION['appointment_status'] === 'success' ? 'bi-check-circle-fill text-success' : 'bi-exclamation-triangle-fill text-danger'; ?>" style="font-size: 1.5rem;"></i>
                        <div>
                            <h3 class="page-title" style="font-size: 1rem; margin-bottom: 0;">
                                <?php echo $_SESSION['appointment_message']; ?>
                            </h3>
                        </div>
                    </div>
                    <button type="button" class="btn-close" onclick="this.parentElement.parentElement.remove()"></button>
                </div>
            </div>
            <?php 
            unset($_SESSION['appointment_message']);
            unset($_SESSION['appointment_status']);
            ?>
            <?php endif; ?>
            
            <!-- Cabecera de página -->
            <div class="page-header">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h1 class="page-title">
                            <i class="bi bi-calendar-heart text-primary"></i>
                            Calendario de Citas
                        </h1>
                        <p class="page-subtitle">Gestión de agenda médica y programación de citas</p>
                    </div>
                    <div class="page-actions">
                        <button type="button" class="action-btn" data-bs-toggle="modal" data-bs-target="#newAppointmentModal">
                            <i class="bi bi-plus-lg"></i>
                            Nueva Cita
                        </button>
                        <button type="button" class="action-btn secondary" onclick="calendar.changeView('dayGridMonth')">
                            <i class="bi bi-calendar-month"></i>
                            Mes
                        </button>
                        <button type="button" class="action-btn secondary" onclick="calendar.changeView('timeGridWeek')">
                            <i class="bi bi-calendar-week"></i>
                            Semana
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Contenedor del calendario -->
            <div class="calendar-container">
                <div id="calendar"></div>
            </div>
        </main>
    </div>
    
    <!-- Modal para nueva cita -->
    <div class="modal fade" id="newAppointmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-calendar-plus text-primary"></i>
                        Programar Nueva Cita
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="appointmentForm" action="save_appointment.php" method="POST">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nombre del Paciente</label>
                                <input type="text" class="form-control" name="nombre_pac" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Apellido del Paciente</label>
                                <input type="text" class="form-control" name="apellido_pac" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fecha de la Cita</label>
                                <input type="date" class="form-control" name="fecha_cita" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Hora de la Cita</label>
                                <input type="time" class="form-control" name="hora_cita" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Teléfono de Contacto</label>
                                <input type="tel" class="form-control" name="telefono">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Médico Asignado</label>
                                <select class="form-select" name="id_doctor" required>
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
                    <div class="modal-footer">
                        <button type="button" class="action-btn secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="action-btn">Programar Cita</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal para editar cita -->
    <div class="modal fade" id="editAppointmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil-square text-primary"></i>
                        Editar Cita
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editAppointmentForm" action="update_appointment.php" method="POST">
                    <input type="hidden" name="id_cita" id="edit_id_cita">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nombre del Paciente</label>
                                <input type="text" class="form-control" name="nombre_pac" id="edit_nombre_pac" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Apellido del Paciente</label>
                                <input type="text" class="form-control" name="apellido_pac" id="edit_apellido_pac" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fecha de la Cita</label>
                                <input type="date" class="form-control" name="fecha_cita" id="edit_fecha_cita" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Hora de la Cita</label>
                                <input type="time" class="form-control" name="hora_cita" id="edit_hora_cita" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Teléfono de Contacto</label>
                                <input type="tel" class="form-control" name="telefono" id="edit_telefono">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Médico Asignado</label>
                                <select class="form-select" name="id_doctor" id="edit_id_doctor" required>
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
                    <div class="modal-footer">
                        <button type="button" class="action-btn secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="action-btn">Actualizar Cita</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Menú contextual -->
    <div id="contextMenu" class="context-menu">
        <div class="context-item" id="contextEdit">
            <i class="bi bi-pencil text-primary"></i>
            <span>Editar cita</span>
        </div>
        <div class="context-item danger" id="contextDelete">
            <i class="bi bi-trash text-danger"></i>
            <span>Eliminar cita</span>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/es.js'></script>
    <script>
    // Calendario de Citas - Centro Médico Herrera Saenz
    // JavaScript para funcionalidades del calendario
    
    // Esperar a que el DOM esté completamente cargado
    document.addEventListener('DOMContentLoaded', function() {
        // ============ REFERENCIAS A ELEMENTOS ============
        const themeSwitch = document.getElementById('themeSwitch');
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarToggleIcon = document.getElementById('sidebarToggleIcon');
        const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
        const contextMenu = document.getElementById('contextMenu');
        const contextEdit = document.getElementById('contextEdit');
        const contextDelete = document.getElementById('contextDelete');
        
        // ============ FUNCIONALIDAD DEL TEMA ============
        
        // Inicializar tema desde localStorage o preferencias del sistema
        function initializeTheme() {
            const savedTheme = localStorage.getItem('dashboard-theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        }
        
        // Cambiar entre modo claro y oscuro
        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            // Aplicar nuevo tema
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('dashboard-theme', newTheme);
            
            // Animación sutil en el botón
            themeSwitch.style.transform = 'rotate(180deg)';
            setTimeout(() => {
                themeSwitch.style.transform = 'rotate(0)';
            }, 300);
        }
        
        // ============ FUNCIONALIDAD DEL SIDEBAR ============
        
        // Restaurar estado del sidebar desde localStorage
        function initializeSidebar() {
            const sidebarCollapsed = localStorage.getItem('sidebar-collapsed');
            
            if (sidebarCollapsed === 'true') {
                sidebar.classList.add('collapsed');
                sidebarToggleIcon.classList.remove('bi-chevron-left');
                sidebarToggleIcon.classList.add('bi-chevron-right');
            }
        }
        
        // Colapsar/expandir sidebar
        function toggleSidebar() {
            const isCollapsed = sidebar.classList.toggle('collapsed');
            
            // Cambiar icono
            if (isCollapsed) {
                sidebarToggleIcon.classList.remove('bi-chevron-left');
                sidebarToggleIcon.classList.add('bi-chevron-right');
            } else {
                sidebarToggleIcon.classList.remove('bi-chevron-right');
                sidebarToggleIcon.classList.add('bi-chevron-left');
            }
            
            // Guardar estado
            localStorage.setItem('sidebar-collapsed', isCollapsed);
        }
        
        // Mostrar/ocultar sidebar en móvil
        function toggleMobileSidebar() {
            sidebar.classList.toggle('show');
            
            // Cerrar sidebar al hacer clic fuera en móvil
            if (sidebar.classList.contains('show')) {
                document.addEventListener('click', closeSidebarOnClickOutside);
            } else {
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
        }
        
        // Cerrar sidebar al hacer clic fuera (solo móvil)
        function closeSidebarOnClickOutside(event) {
            if (!sidebar.contains(event.target) && 
                !mobileSidebarToggle.contains(event.target) && 
                sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
        }
        
        // ============ CALENDARIO FULLCALENDAR ============
        
        // Variable global para el calendario
        let calendar;
        let currentEvent = null;
        
        // Inicializar calendario
        function initializeCalendar() {
            const calendarEl = document.getElementById('calendar');
            
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
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
                dateClick: function(info) {
                    // Prellenar fecha en el modal de nueva cita
                    document.querySelector('#newAppointmentModal input[name="fecha_cita"]').value = info.dateStr;
                    
                    // Mostrar modal
                    const modal = new bootstrap.Modal(document.getElementById('newAppointmentModal'));
                    modal.show();
                },
                
                // Manejar clic en evento (Click izquierdo para detalles rápidos o editar)
                eventClick: function(info) {
                    info.jsEvent.preventDefault();
                    // Opcionalmente podemos hacer algo aquí, pero el usuario pidió click derecho específicamente
                },
                
                // Redimensionar calendario al cambiar tamaño de ventana
                windowResize: function(view) {
                    if (window.innerWidth < 768) {
                        calendar.changeView('dayGridMonth');
                    }
                },
                
                // Estilizar eventos
                eventDidMount: function(info) {
                    // Agregar clase según el médico
                    const doctorId = info.event.extendedProps.id_doctor;
                    const colorClasses = ['fc-event-primary', 'fc-event-secondary', 'fc-event-accent'];
                    const colorClass = colorClasses[doctorId % colorClasses.length];
                    info.el.classList.add(colorClass);
                    
                    // Agregar tooltip
                    info.el.title = `Paciente: ${info.event.title}\nHora: ${info.event.start.toLocaleTimeString('es-GT', { hour: '2-digit', minute: '2-digit' })}`;

                    // Manejar click derecho
                    info.el.addEventListener('contextmenu', function(e) {
                        e.preventDefault();
                        currentEvent = info.event;
                        showContextMenu(e.pageX, e.pageY);
                        return false;
                    });
                }
            });
            
            calendar.render();
        }
        
        // ============ MENÚ CONTEXTUAL ============
        
        // Mostrar menú contextual
        function showContextMenu(x, y) {
            contextMenu.style.display = 'block';
            contextMenu.style.left = x + 'px';
            contextMenu.style.top = y + 'px';
            
            // Ajustar posición si sale de la ventana
            const menuRect = contextMenu.getBoundingClientRect();
            const windowWidth = window.innerWidth;
            const windowHeight = window.innerHeight;
            
            if (menuRect.right > windowWidth) {
                contextMenu.style.left = (x - menuRect.width) + 'px';
            }
            
            if (menuRect.bottom > windowHeight) {
                contextMenu.style.top = (y - menuRect.height) + 'px';
            }
        }
        
        // Ocultar menú contextual
        function hideContextMenu() {
            contextMenu.style.display = 'none';
            // No reseteamos currentEvent aquí para permitir que las acciones (edit/delete) lo usen
        }
        
        // Editar evento desde menú contextual
        function editCurrentEvent() {
            if (!currentEvent) return;
            
            // Obtener detalles del evento
            fetch('get_appointment_details.php?id=' + currentEvent.id)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Rellenar formulario de edición
                        document.getElementById('edit_id_cita').value = data.id_cita;
                        document.getElementById('edit_nombre_pac').value = data.nombre_pac;
                        document.getElementById('edit_apellido_pac').value = data.apellido_pac;
                        document.getElementById('edit_fecha_cita').value = data.fecha_cita;
                        document.getElementById('edit_hora_cita').value = data.hora_cita;
                        document.getElementById('edit_telefono').value = data.telefono || '';
                        document.getElementById('edit_id_doctor').value = data.id_doctor;
                        
                        // Mostrar modal
                        const modal = new bootstrap.Modal(document.getElementById('editAppointmentModal'));
                        modal.show();
                    } else {
                        Swal.fire('Error', 'No se pudieron cargar los detalles: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'Error al cargar los detalles de la cita', 'error');
                });
            
            hideContextMenu();
        }
        
        // Eliminar evento desde menú contextual
        function deleteCurrentEvent() {
            if (!currentEvent) return;

            // Ocultar menú contextual primero
            hideContextMenu();
            
            Swal.fire({
                title: '¿Está seguro de eliminar esta cita?',
                text: "Esta acción no se puede deshacer",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: 'var(--color-primary)',
                cancelButtonColor: 'var(--color-error)',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('delete_appointment.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ id: currentEvent.id })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            calendar.refetchEvents();
                            Swal.fire({
                                title: '¡Eliminado!',
                                text: 'La cita ha sido eliminada correctamente.',
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire('Error', 'No se pudo procesar la solicitud', 'error');
                    });
                }
            });
        }
        
        // ============ NOTIFICACIONES ============
        
        // Mostrar notificación
        function showNotification(message, type = 'info') {
            // Crear elemento de notificación
            const notification = document.createElement('div');
            notification.className = `page-header mb-4`;
            notification.style.borderLeft = `4px solid var(--color-${type})`;
            notification.style.animation = 'slideDown 0.4s ease-out';
            
            notification.innerHTML = `
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi ${type === 'success' ? 'bi-check-circle-fill text-success' : type === 'error' ? 'bi-exclamation-triangle-fill text-danger' : 'bi-info-circle-fill text-info'}" style="font-size: 1.5rem;"></i>
                        <div>
                            <h3 class="page-title" style="font-size: 1rem; margin-bottom: 0;">
                                ${message}
                            </h3>
                        </div>
                    </div>
                    <button type="button" class="btn-close" onclick="this.parentElement.parentElement.remove()"></button>
                </div>
            `;
            
            // Insertar después del header
            const mainContent = document.querySelector('.main-content');
            const pageHeader = document.querySelector('.page-header');
            mainContent.insertBefore(notification, pageHeader.nextSibling);
            
            // Auto-eliminar después de 5 segundos
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }
        
        // ============ ANIMACIONES AL CARGAR ============
        
        // Animar elementos al cargar la página
        function animateOnLoad() {
            const cards = document.querySelectorAll('.calendar-container, .page-header');
            
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        }
        
        // ============ INICIALIZACIÓN ============
        
        // Inicializar componentes
        initializeTheme();
        initializeSidebar();
        initializeCalendar();
        animateOnLoad();
        
        // ============ EVENT LISTENERS ============
        
        // Tema
        themeSwitch.addEventListener('click', toggleTheme);
        
        // Sidebar (escritorio)
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', toggleSidebar);
        }
        
        // Sidebar (móvil)
        if (mobileSidebarToggle) {
            mobileSidebarToggle.addEventListener('click', toggleMobileSidebar);
        }
        
        // Menú contextual
        document.addEventListener('click', function(e) {
            if (!contextMenu.contains(e.target)) {
                hideContextMenu();
            }
        });
        
        contextEdit.addEventListener('click', editCurrentEvent);
        contextDelete.addEventListener('click', deleteCurrentEvent);
        
        // Cerrar sidebar al cambiar tamaño de ventana (responsive)
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992 && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
        });
        
        // ============ MANEJO DE FORMULARIOS ============
        
        // Formulario de nueva cita
        document.getElementById('appointmentForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Mostrar estado de carga
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Procesando...';
            submitBtn.disabled = true;
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Cerrar modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('newAppointmentModal'));
                    modal.hide();
                    
                    // Resetear formulario
                    this.reset();
                    
                    // Recargar eventos del calendario
                    calendar.refetchEvents();
                    
                    // Mostrar notificación
                    showNotification('Cita programada correctamente', 'success');
                } else {
                    showNotification('Error al programar cita: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error al programar cita', 'error');
            })
            .finally(() => {
                // Restaurar botón
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
        
        // Formulario de edición de cita
        document.getElementById('editAppointmentForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Mostrar estado de carga
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Actualizando...';
            submitBtn.disabled = true;
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Cerrar modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editAppointmentModal'));
                    modal.hide();
                    
                    // Recargar eventos del calendario
                    calendar.refetchEvents();
                    
                    // Mostrar notificación
                    showNotification('Cita actualizada correctamente', 'success');
                } else {
                    showNotification('Error al actualizar cita: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error al actualizar cita', 'error');
            })
            .finally(() => {
                // Restaurar botón
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
        
        // ============ CONSOLA DE DESARROLLO ============
        
        console.log('Calendario de Citas - Centro Médico Herrera Saenz');
        console.log('Versión: 3.0 - Diseño Minimalista con Efecto Mármol');
        console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
        console.log('Rol: <?php echo htmlspecialchars($user_type); ?>');
    });
    </script>
    
    <!-- Incluir footer -->
    <?php include_once '../../includes/footer.php'; ?>
</body>
</html>