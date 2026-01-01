<?php
// index.php - Procedimientos Menores - Centro Médico Herrera Saenz
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
verify_session();

// Título de la página
$page_title = "Procedimientos Menores - Centro Médico Herrera Saenz";

try {
    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener pacientes para el select
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

} catch (Exception $e) {
    $patients = [];
    $error_message = "Error de conexión: " . $e->getMessage();
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
    
    <!-- Google Fonts - Inter para modernidad y legibilidad -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
    /* 
     * Procedimientos Menores - Centro Médico Herrera Saenz
     * Diseño: Fondo blanco, colores pastel, efecto mármol, modo noche
     * Versión: 3.0
     */
    
    /* Variables CSS para modo claro y oscuro */
    :root {
        /* Modo claro (predeterminado) - Colores pastel */
        --color-background: #f8fafc;
        --color-surface: #ffffff;
        --color-primary: #7c90db;      /* Azul lavanda pastel */
        --color-primary-light: #a3b1e8;
        --color-primary-dark: #5a6fca;
        --color-secondary: #8dd7bf;    /* Verde menta pastel */
        --color-secondary-light: #b2e6d5;
        --color-accent: #f8b195;       /* Coral pastel */
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
    
    /* ============ ENCABEZADO DE PÁGINA ============ */
    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 2rem;
        animation: fadeIn 0.6s ease-out;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }
    
    .page-title-section {
        display: flex;
        flex-direction: column;
    }
    
    .page-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--color-text);
        margin-bottom: 0.25rem;
    }
    
    .page-subtitle {
        font-size: 0.95rem;
        color: var(--color-text-light);
    }
    
    .page-actions {
        display: flex;
        gap: 1rem;
        align-items: center;
    }
    
    /* Botones de acción */
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
        background: transparent;
        color: var(--color-text);
        border: 1px solid var(--color-border);
    }
    
    .action-btn.secondary:hover {
        background: var(--color-border-light);
    }
    
    /* ============ TARJETAS DE ESTADÍSTICAS ============ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        transition: all var(--transition-normal);
        position: relative;
        overflow: hidden;
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--color-primary), var(--color-secondary));
        opacity: 0.7;
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
        border-color: var(--color-primary-light);
    }
    
    .stat-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }
    
    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
    }
    
    .stat-icon.primary { background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark)); }
    .stat-icon.success { background: linear-gradient(135deg, var(--color-success), #10b981); }
    .stat-icon.warning { background: linear-gradient(135deg, var(--color-warning), #d97706); }
    .stat-icon.info { background: linear-gradient(135deg, var(--color-info), #0ea5e9); }
    
    .stat-title {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--color-text-light);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.25rem;
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--color-text);
        line-height: 1;
        margin-bottom: 0.5rem;
    }
    
    /* ============ FORMULARIO PRINCIPAL ============ */
    .form-container {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 2rem;
        margin-bottom: 2rem;
        animation: fadeIn 0.6s ease-out 0.2s both;
    }
    
    .form-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--color-border);
    }
    
    .form-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--color-text);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .form-title-icon {
        color: var(--color-primary);
    }
    
    /* Grupos de formulario */
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-label {
        font-weight: 500;
        color: var(--color-text);
        margin-bottom: 0.5rem;
        display: block;
        font-size: 0.875rem;
    }
    
    .form-control, .form-select {
        width: 100%;
        padding: 0.75rem 1rem;
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        color: var(--color-text);
        font-size: 0.95rem;
        transition: all var(--transition-normal);
    }
    
    .form-control:focus, .form-select:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px var(--color-primary-light);
        opacity: 0.3;
    }
    
    /* Grupo de entrada con icono */
    .input-group {
        display: flex;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        overflow: hidden;
    }
    
    .input-group-text {
        background: var(--color-border-light);
        color: var(--color-text-light);
        padding: 0.75rem 1rem;
        border: none;
        font-size: 0.95rem;
    }
    
    .input-group .form-control {
        border: none;
        border-left: 1px solid var(--color-border);
        border-radius: 0;
    }
    
    /* Información del paciente */
    .patient-info-card {
        background: var(--color-border-light);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 1rem;
        margin-top: 0.5rem;
        animation: fadeIn 0.3s ease;
    }
    
    .patient-info-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.25rem;
    }
    
    .patient-info-label {
        font-weight: 500;
        color: var(--color-text-light);
    }
    
    .patient-info-value {
        font-weight: 600;
        color: var(--color-text);
    }
    
    /* Procedimientos adicionales */
    .additional-procedure {
        background: var(--color-border-light);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 1rem;
        margin-bottom: 1rem;
        animation: slideDown 0.3s ease;
    }
    
    /* ============ TABLA DE PROCEDIMIENTOS RECIENTES ============ */
    .table-container {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-bottom: 2rem;
        animation: fadeIn 0.6s ease-out 0.3s both;
    }
    
    .table-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--color-border);
    }
    
    .table-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--color-text);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .table-title-icon {
        color: var(--color-primary);
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .data-table th {
        text-align: left;
        padding: 1rem;
        font-weight: 600;
        color: var(--color-text-light);
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid var(--color-border);
        background: var(--color-border-light);
    }
    
    .data-table td {
        padding: 1rem;
        border-bottom: 1px solid var(--color-border);
        color: var(--color-text);
        transition: background-color var(--transition-normal);
    }
    
    .data-table tbody tr:hover td {
        background: var(--color-border-light);
    }
    
    .data-table tbody tr:last-child td {
        border-bottom: none;
    }
    
    /* Estado vacío */
    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--color-text-light);
    }
    
    .empty-icon {
        font-size: 3rem;
        color: var(--color-border);
        margin-bottom: 1rem;
        opacity: 0.5;
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
    
    /* ============ RESPONSIVE DESIGN ============ */
    @media (max-width: 1200px) {
        .main-content {
            padding: 1.5rem;
        }
        
        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .page-actions {
            width: 100%;
            justify-content: flex-start;
        }
        
        .form-container {
            padding: 1.5rem;
        }
        
        .data-table {
            display: block;
            overflow-x: auto;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .form-container {
            padding: 1.25rem;
        }
        
        .action-btn {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }
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
    
    /* ============ ANIMACIONES ============ */
    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .animate-slide-in {
        animation: slideInUp 0.5s ease-out;
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
    
    .border-primary { border-color: var(--color-primary) !important; }
    .border-success { border-color: var(--color-success) !important; }
    .border-warning { border-color: var(--color-warning) !important; }
    .border-danger { border-color: var(--color-error) !important; }
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
                            <?php echo strtoupper(substr($_SESSION['nombre'], 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <span class="user-name"><?php echo htmlspecialchars($_SESSION['nombre']); ?></span>
                            <span class="user-role"><?php echo htmlspecialchars($_SESSION['especialidad'] ?? 'Profesional Médico'); ?></span>
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
                <?php $role = $_SESSION['tipoUsuario']; ?>
                
                <!-- Dashboard (siempre visible) -->
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
                    <a href="../appointments/index.php" class="nav-link">
                        <i class="bi bi-calendar-heart nav-icon"></i>
                        <span class="nav-text">Citas</span>
                    </a>
                </li>
                
                <!-- Procedimientos menores -->
                <li class="nav-item">
                    <a href="../minor_procedures/index.php" class="nav-link active">
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
            <!-- Encabezado de página -->
            <div class="page-header">
                <div class="page-title-section">
                    <h1 class="page-title">Procedimientos Menores</h1>
                    <p class="page-subtitle">Registro y gestión de procedimientos médicos menores</p>
                </div>
                <div class="page-actions">
                    <a href="historial_procedimientos.php" class="action-btn secondary">
                        <i class="bi bi-clock-history"></i>
                        <span>Ver Historial</span>
                    </a>
                </div>
            </div>
            
            <!-- Estadísticas rápidas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Procedimientos Hoy</div>
                            <div class="stat-value" id="todayProcedures">0</div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="bi bi-bandaid"></i>
                        </div>
                    </div>
                    <div class="text-muted small">Actualizado recientemente</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Esta Semana</div>
                            <div class="stat-value" id="weekProcedures">0</div>
                        </div>
                        <div class="stat-icon success">
                            <i class="bi bi-calendar-week"></i>
                        </div>
                    </div>
                    <div class="text-muted small">Total de la semana</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Ingresos Hoy</div>
                            <div class="stat-value" id="todayRevenue">Q0.00</div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="bi bi-currency-dollar"></i>
                        </div>
                    </div>
                    <div class="text-muted small">Total recaudado</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">En Proceso</div>
                            <div class="stat-value" id="activeProcedures">0</div>
                        </div>
                        <div class="stat-icon info">
                            <i class="bi bi-activity"></i>
                        </div>
                    </div>
                    <div class="text-muted small">Procedimientos activos</div>
                </div>
            </div>
            
            <!-- Formulario principal -->
            <div class="form-container">
                <div class="form-header">
                    <h3 class="form-title">
                        <i class="bi bi-clipboard-plus form-title-icon"></i>
                        Nuevo Procedimiento
                    </h3>
                </div>
                
                <form id="procedureForm" action="save_procedure.php" method="POST">
                    <!-- Paciente -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="id_paciente" class="form-label">Paciente *</label>
                            <select class="form-select" id="id_paciente" name="id_paciente" required>
                                <option value="">Seleccionar paciente...</option>
                                <?php foreach ($patients as $patient): 
                                    $age = $patient['fecha_nacimiento'] ? calculateAge($patient['fecha_nacimiento']) : 'N/A';
                                ?>
                                    <option value="<?php echo $patient['id_paciente']; ?>" 
                                            data-nombre="<?php echo htmlspecialchars($patient['nombre_completo']); ?>"
                                            data-telefono="<?php echo htmlspecialchars($patient['telefono']); ?>"
                                            data-edad="<?php echo $age; ?>">
                                        <?php echo htmlspecialchars($patient['nombre_completo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="nombre_paciente" id="nombre_paciente">
                        </div>
                        <div class="col-md-6">
                            <div class="form-label">Información del Paciente</div>
                            <div id="paciente_info" class="patient-info-card">
                                <small class="text-muted">Seleccione un paciente para ver su información</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Detalles del procedimiento -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <label class="form-label">Procedimientos Realizados *</label>
                            <div class="field-container">
                                <div class="checkbox-grid">
                                    <div class="custom-checkbox">
                                        <input type="checkbox" name="procedimientos[]" value="Sutura de herida" id="proc1">
                                        <label for="proc1">Sutura de herida</label>
                                    </div>
                                    <div class="custom-checkbox">
                                        <input type="checkbox" name="procedimientos[]" value="Curación de herida" id="proc2">
                                        <label for="proc2">Curación de herida</label>
                                    </div>
                                    <div class="custom-checkbox">
                                        <input type="checkbox" name="procedimientos[]" value="Extracción de uña encarnada" id="proc3">
                                        <label for="proc3">Extracción de uña encarnada</label>
                                    </div>
                                    <div class="custom-checkbox">
                                        <input type="checkbox" name="procedimientos[]" value="Drenaje de absceso" id="proc4">
                                        <label for="proc4">Drenaje de absceso</label>
                                    </div>
                                    <div class="custom-checkbox">
                                        <input type="checkbox" name="procedimientos[]" value="Retiro de puntos" id="proc5">
                                        <label for="proc5">Retiro de puntos</label>
                                    </div>
                                    <div class="custom-checkbox">
                                        <input type="checkbox" name="procedimientos[]" value="Infiltración" id="proc6">
                                        <label for="proc6">Infiltración</label>
                                    </div>
                                    <div class="custom-checkbox">
                                        <input type="checkbox" name="procedimientos[]" value="Nebulización" id="proc7">
                                        <label for="proc7">Nebulización</label>
                                    </div>
                                    <div class="custom-checkbox">
                                        <input type="checkbox" name="procedimientos[]" value="Lavado de oídos" id="proc8">
                                        <label for="proc8">Lavado de oídos</label>
                                    </div>
                                    <div class="custom-checkbox">
                                        <input type="checkbox" name="procedimientos[]" value="Cauterización" id="proc9">
                                        <label for="proc9">Cauterización</label>
                                    </div>
                                </div>
                                
                                <!-- Procedimientos dinámicos -->
                                <div id="dynamicProcedures" class="mt-3">
                                    <!-- Se agregarán procedimientos dinámicos aquí -->
                                </div>
                                
                                <!-- Botón para agregar procedimiento personalizado -->
                                <div class="mt-3">
                                    <button type="button" class="action-btn secondary" id="btnAddProcedure">
                                        <i class="bi bi-plus-lg"></i>
                                        <span>Agregar Procedimiento Personalizado</span>
                                    </button>
                                </div>
                                
                                <small class="text-muted mt-2 d-block">
                                    <i class="bi bi-info-circle"></i>
                                    Puede seleccionar varios procedimientos o agregar personalizados.
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Costo y fecha -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="cobro" class="form-label">Costo Total del Procedimiento *</label>
                            <div class="input-group">
                                <span class="input-group-text">Q</span>
                                <input type="number" class="form-control" id="cobro" name="cobro" step="0.01" min="0" placeholder="0.00" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="fecha_procedimiento" class="form-label">Fecha y Hora *</label>
                            <input type="datetime-local" class="form-control" id="fecha_procedimiento" name="fecha_procedimiento" required>
                        </div>
                    </div>
                    
                    <!-- Procedimientos adicionales -->
                    <div class="form-group" id="additionalProceduresSection" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <label class="form-label">Procedimientos Adicionales</label>
                            <button type="button" class="action-btn secondary" onclick="addAdditionalProcedure()">
                                <i class="bi bi-plus-circle"></i>
                                <span>Agregar</span>
                            </button>
                        </div>
                        <div id="additionalProceduresContainer"></div>
                    </div>
                    
                    <!-- Botones de acción -->
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="submit" class="action-btn">
                            <i class="bi bi-check-lg"></i>
                            <span>Registrar Procedimiento</span>
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Procedimientos recientes -->
            <div class="table-container">
                <div class="table-header">
                    <h3 class="table-title">
                        <i class="bi bi-clock-history table-title-icon"></i>
                        Procedimientos Recientes
                    </h3>
                    <button type="button" class="action-btn secondary" onclick="refreshProcedures()">
                        <i class="bi bi-arrow-clockwise"></i>
                        <span>Actualizar</span>
                    </button>
                </div>
                
                <div id="recentProcedures">
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <h4 class="text-muted mb-2">Cargando procedimientos...</h4>
                        <p class="text-muted">Obteniendo los procedimientos más recientes</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- JavaScript -->
    <script>
    // Procedimientos Menores - Centro Médico Herrera Saenz
    // JavaScript para funcionalidades del módulo
    
    // Esperar a que el DOM esté completamente cargado
    document.addEventListener('DOMContentLoaded', function() {
        // ============ REFERENCIAS A ELEMENTOS ============
        const themeSwitch = document.getElementById('themeSwitch');
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarToggleIcon = document.getElementById('sidebarToggleIcon');
        const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
        const patientSelect = document.getElementById('id_paciente');
        const patientInfo = document.getElementById('paciente_info');
        const dynamicProceduresContainer = document.getElementById('dynamicProcedures');
        const btnAddProcedure = document.getElementById('btnAddProcedure');
        const dateInput = document.getElementById('fecha_procedimiento');
        const form = document.getElementById('procedureForm');
        
        // ============ INICIALIZACIÓN ============
        
        // Establecer fecha y hora actual (hora local de Guatemala)
        const now = new Date();
        // Ajustar a hora local restando el offset de la zona horaria
        const offset = now.getTimezoneOffset() * 60000; // offset en milisegundos
        const localTime = new Date(now.getTime() - offset);
        const localDateTime = localTime.toISOString().slice(0, 16);
        if (dateInput) dateInput.value = localDateTime;
        
        // Ocultar procedimientos dinámicos inicialmente
        if (dynamicProceduresContainer) dynamicProceduresContainer.innerHTML = '';
        
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
        
        // ============ FUNCIONALIDAD DEL FORMULARIO ============
        
        // Manejar selección de paciente
        function handlePatientSelect() {
            const selectedOption = patientSelect.options[patientSelect.selectedIndex];
            
            if (selectedOption.value) {
                const nombre = selectedOption.dataset.nombre;
                const telefono = selectedOption.dataset.telefono;
                const edad = selectedOption.dataset.edad;
                
                // Actualizar campo oculto
                document.getElementById('nombre_paciente').value = nombre;
                
                // Mostrar información del paciente
                patientInfo.innerHTML = `
                    <div class="patient-info-item">
                        <span class="patient-info-label">Nombre:</span>
                        <span class="patient-info-value">${nombre}</span>
                    </div>
                    <div class="patient-info-item">
                        <span class="patient-info-label">Teléfono:</span>
                        <span class="patient-info-value">${telefono || 'No disponible'}</span>
                    </div>
                    <div class="patient-info-item">
                        <span class="patient-info-label">Edad:</span>
                        <span class="patient-info-value">${edad} años</span>
                    </div>
                `;
            } else {
                patientInfo.innerHTML = '<small class="text-muted">Seleccione un paciente para ver su información</small>';
            }
        }
        
        // ============ PROCEDIMIENTOS DINÁMICOS ============
        
        function addDynamicProcedure() {
            const procedureRow = document.createElement('div');
            procedureRow.className = 'input-group-custom mb-2 animate-slide-in';
            procedureRow.innerHTML = `
                <span class="input-group-text">
                    <i class="bi bi-bandaid"></i>
                </span>
                <input class="form-control" name="procedimientos[]" placeholder="Especificar otro procedimiento..." required>
                <button type="button" class="btn-remove remove-procedure-row">
                    <i class="bi bi-trash"></i>
                </button>
            `;
            
            dynamicProceduresContainer.appendChild(procedureRow);
            
            // Enfocar el nuevo campo
            const input = procedureRow.querySelector('input');
            input.focus();
        }
        
        function removeDynamicProcedure(event) {
            if (event.target.closest('.remove-procedure-row')) {
                const procedureRow = event.target.closest('.input-group-custom');
                procedureRow.style.opacity = '0';
                procedureRow.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    procedureRow.remove();
                }, 300);
            }
        }
        
        // ============ EVENT LISTENERS PARA DINÁMICOS ============
        if (btnAddProcedure) {
            btnAddProcedure.addEventListener('click', addDynamicProcedure);
        }
        
        if (dynamicProceduresContainer) {
            dynamicProceduresContainer.addEventListener('click', removeDynamicProcedure);
        }
        
        // ============ PROCEDIMIENTOS ADICIONALES ============
        
        let additionalProcedureCount = 0;
        
        function addAdditionalProcedure() {
            additionalProcedureCount++;
            
            const container = document.getElementById('additionalProceduresContainer');
            const section = document.getElementById('additionalProceduresSection');
            
            const procedureDiv = document.createElement('div');
            procedureDiv.className = 'additional-procedure animate-slide-in';
            procedureDiv.innerHTML = `
                <div class="row">
                    <div class="col-md-5 mb-3">
                        <label class="form-label">Procedimiento Adicional</label>
                        <select class="form-select" name="procedimientos_adicionales[]" required>
                            <option value="">Seleccionar procedimiento...</option>
                            <option value="Sutura de herida">Sutura de herida</option>
                            <option value="Curación de herida">Curación de herida</option>
                            <option value="Extracción de uña encarnada">Extracción de uña encarnada</option>
                            <option value="Drenaje de absceso">Drenaje de absceso</option>
                            <option value="Retiro de puntos">Retiro de puntos</option>
                        </select>
                    </div>
                    <div class="col-md-5 mb-3">
                        <label class="form-label">Costo Adicional</label>
                        <div class="input-group">
                            <span class="input-group-text">Q</span>
                            <input type="number" class="form-control" name="cobros_adicionales[]" step="0.01" min="0" placeholder="0.00" required>
                        </div>
                    </div>
                    <div class="col-md-2 mb-3 d-flex align-items-end">
                        <button type="button" class="action-btn secondary w-100" onclick="removeAdditionalProcedure(this)">
                            <i class="bi bi-trash"></i>
                            <span>Eliminar</span>
                        </button>
                    </div>
                </div>
            `;
            
            container.appendChild(procedureDiv);
            section.style.display = 'block';
        }
        
        function removeAdditionalProcedure(button) {
            const procedureDiv = button.closest('.additional-procedure');
            procedureDiv.style.animation = 'slideDown 0.3s ease reverse';
            
            setTimeout(() => {
                procedureDiv.remove();
                additionalProcedureCount--;
                
                if (additionalProcedureCount === 0) {
                    document.getElementById('additionalProceduresSection').style.display = 'none';
                }
            }, 300);
        }
        
        // ============ PROCESAMIENTO DEL FORMULARIO ============
        
        function handleFormSubmit(event) {
            event.preventDefault();
            
            // Validación básica
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                showNotification('Por favor complete todos los campos requeridos', 'error');
                return;
            }
            
            // Validar que al menos un procedimiento esté seleccionado o escrito
            const checkboxes = document.querySelectorAll('input[name="procedimientos[]"][type="checkbox"]:checked');
            const customInputs = document.querySelectorAll('input[name="procedimientos[]"][type="text"]');
            let hasSelection = checkboxes.length > 0;
            
            customInputs.forEach(input => {
                if (input.value.trim() !== '') {
                    hasSelection = true;
                }
            });
            
            if (!hasSelection) {
                showNotification('Por favor seleccione o agregue al menos un procedimiento', 'error');
                return;
            }
            
            // Mostrar estado de carga
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Registrando...';
            submitBtn.disabled = true;
            
            // Enviar formulario
            const formData = new FormData(form);
            
            fetch('save_procedure.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showNotification('Procedimiento registrado exitosamente', 'success');
                    clearForm();
                    refreshProcedures();
                    updateStats();
                } else {
                    showNotification(data.message || 'Error al registrar el procedimiento', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error de conexión con el servidor', 'error');
            })
            .finally(() => {
                // Restaurar botón
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        }
        
        // ============ ACTUALIZACIÓN DE DATOS ============
        
        // Actualizar estadísticas
        function updateStats() {
            // Simular actualización de estadísticas
            // En producción, esto haría una petición AJAX
            const stats = {
                today: Math.floor(Math.random() * 10) + 1,
                week: Math.floor(Math.random() * 50) + 10,
                revenue: (Math.random() * 1000).toFixed(2),
                active: Math.floor(Math.random() * 5)
            };
            
            // Actualizar elementos DOM
            const todayElement = document.getElementById('todayProcedures');
            const weekElement = document.getElementById('weekProcedures');
            const revenueElement = document.getElementById('todayRevenue');
            const activeElement = document.getElementById('activeProcedures');
            
            if (todayElement) todayElement.textContent = stats.today;
            if (weekElement) weekElement.textContent = stats.week;
            if (revenueElement) revenueElement.textContent = `Q${stats.revenue}`;
            if (activeElement) activeElement.textContent = stats.active;
        }
        
        // Refrescar procedimientos recientes
        function refreshProcedures() {
            const container = document.getElementById('recentProcedures');
            
            // Mostrar estado de carga
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <h4 class="text-muted mb-2">Cargando procedimientos...</h4>
                    <p class="text-muted">Obteniendo los procedimientos más recientes</p>
                </div>
            `;
            
            // Simular carga de datos
            // En producción, esto haría una petición AJAX
            setTimeout(() => {
                // Datos de ejemplo
                const procedures = [
                    {
                        id: 1,
                        nombre_paciente: 'Juan Pérez',
                        procedimiento: 'Sutura de herida',
                        descripcion: 'Sutura en mano derecha',
                        cobro: '150.00',
                        fecha_procedimiento: new Date().toISOString()
                    },
                    {
                        id: 2,
                        nombre_paciente: 'María González',
                        procedimiento: 'Curación de herida',
                        descripcion: 'Limpieza y curación',
                        cobro: '75.00',
                        fecha_procedimiento: new Date(Date.now() - 86400000).toISOString()
                    },
                    {
                        id: 3,
                        nombre_paciente: 'Carlos Rodríguez',
                        procedimiento: 'Extracción de uña encarnada',
                        descripcion: 'Extracción completa',
                        cobro: '200.00',
                        fecha_procedimiento: new Date(Date.now() - 172800000).toISOString()
                    }
                ];
                
                if (procedures.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="bi bi-clipboard-x"></i>
                            </div>
                            <h4 class="text-muted mb-2">No hay procedimientos recientes</h4>
                            <p class="text-muted">Registre su primer procedimiento</p>
                        </div>
                    `;
                    return;
                }
                
                // Construir tabla
                let html = `
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Paciente</th>
                                    <th>Procedimiento</th>
                                    <th>Costo</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                procedures.forEach(procedure => {
                    const fecha = new Date(procedure.fecha_procedimiento);
                    const fechaFormateada = fecha.toLocaleDateString('es-GT', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric'
                    });
                    
                    html += `
                        <tr>
                            <td>${procedure.nombre_paciente}</td>
                            <td>${procedure.procedimiento}</td>
                            <td>${procedure.descripcion}</td>
                            <td><span class="text-success fw-semibold">Q${procedure.cobro}</span></td>
                            <td>${fechaFormateada}</td>
                        </tr>
                    `;
                });
                
                html += `
                            </tbody>
                        </table>
                    </div>
                `;
                
                container.innerHTML = html;
                
            }, 1000); // Simular delay de red
        }
        
        // Mostrar notificación
        function showNotification(message, type = 'info') {
            // Crear elemento de notificación
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} border-0 shadow-lg`;
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.zIndex = '9999';
            notification.style.minWidth = '300px';
            notification.style.animation = 'slideDown 0.3s ease-out';
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
                    notification.style.animation = 'slideDown 0.3s ease-out reverse';
                    setTimeout(() => notification.remove(), 300);
                }
            }, 5000);
        }
        
        // Limpiar formulario
        function clearForm() {
            form.reset();
            
            // Restablecer fecha y hora actual
            const now = new Date();
            const offset = now.getTimezoneOffset() * 60000;
            const localTime = new Date(now.getTime() - offset);
            const localDateTime = localTime.toISOString().slice(0, 16);
            if (dateInput) dateInput.value = localDateTime;
            
            // Limpiar información del paciente
            patientInfo.innerHTML = '<small class="text-muted">Seleccione un paciente para ver su información</small>';
            
            // Limpiar procedimientos dinámicos
            if (dynamicProceduresContainer) {
                dynamicProceduresContainer.innerHTML = '';
            }
            
            // Limpiar procedimientos adicionales (si existían)
            const container = document.getElementById('additionalProceduresContainer');
            if (container) container.innerHTML = '';
            const section = document.getElementById('additionalProceduresSection');
            if (section) section.style.display = 'none';
            additionalProcedureCount = 0;
            
            // Remover clase de validación
            form.classList.remove('was-validated');
        }
        
        // Cargar plantilla de procedimiento
        function loadTemplate() {
            showNotification('Función de plantillas en desarrollo', 'info');
        }
        
        // ============ INICIALIZACIÓN DE COMPONENTES ============
        
        // Inicializar componentes
        initializeTheme();
        initializeSidebar();
        updateStats();
        refreshProcedures();
        
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
        
        // Formulario
        if (patientSelect) {
            patientSelect.addEventListener('change', handlePatientSelect);
        }
        
        if (form) {
            form.addEventListener('submit', handleFormSubmit);
        }
        
        // Cerrar sidebar al cambiar tamaño de ventana (responsive)
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992 && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
        });
        
        // ============ CONSOLA DE DESARROLLO ============
        
        console.log('Procedimientos Menores - Centro Médico Herrera Saenz');
        console.log('Versión: 3.0 - Diseño con Efecto Mármol y Modo Noche');
        console.log('Usuario: <?php echo htmlspecialchars($_SESSION['nombre']); ?>');
        console.log('Rol: <?php echo htmlspecialchars($_SESSION['tipoUsuario']); ?>');
    });
    
    // Hacer funciones disponibles globalmente
    window.clearForm = clearForm;
    window.loadTemplate = loadTemplate;
    window.addAdditionalProcedure = addAdditionalProcedure;
    window.removeAdditionalProcedure = removeAdditionalProcedure;
    window.refreshProcedures = refreshProcedures;
    window.updateStats = updateStats;
    </script>
</body>
</html>

<?php
// Función helper para calcular edad
function calculateAge($birthDate) {
    if (!$birthDate) return 'N/A';
    try {
        $birth = new DateTime($birthDate);
        $today = new DateTime();
        return $today->diff($birth)->y;
    } catch (Exception $e) {
        return 'N/A';
    }
}
?>