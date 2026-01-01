<?php
// purchases/index.php - Módulo de Compras del Centro Médico Herrera Saenz
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

// Establecer zona horaria
date_default_timezone_set('America/Guatemala');

// Título de la página
$page_title = "Compras - Centro Médico Herrera Saenz";

try {
    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();
    
    // Obtener información del usuario
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['tipoUsuario'];
    $user_name = $_SESSION['nombre'];
    $user_specialty = $_SESSION['especialidad'] ?? 'Profesional Médico';
    
    // Verificar permisos (solo admin puede acceder a compras)
    if ($user_type !== 'admin') {
        header("Location: ../dashboard/index.php");
        exit;
    }
    
} catch (Exception $e) {
    // Manejo de errores
    error_log("Error en módulo de compras: " . $e->getMessage());
    die("Error al cargar el módulo de compras. Por favor, contacte al administrador.");
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
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
    /* 
     * Módulo de Compras - Centro Médico Herrera Saenz
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
    
    /* ============ TAB NAVIGATION ============ */
    .tab-navigation {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 2rem;
        border-bottom: 1px solid var(--color-border);
        padding-bottom: 0.5rem;
    }
    
    .tab-btn {
        padding: 0.75rem 1.5rem;
        background: transparent;
        border: none;
        color: var(--color-text-light);
        font-weight: 500;
        font-size: 0.95rem;
        cursor: pointer;
        border-radius: var(--radius-md);
        transition: all var(--transition-normal);
        position: relative;
    }
    
    .tab-btn:hover {
        color: var(--color-text);
        background: var(--color-border);
    }
    
    .tab-btn.active {
        color: var(--color-primary);
        background: var(--color-primary-light);
    }
    
    .tab-btn.active::after {
        content: '';
        position: absolute;
        bottom: -0.5rem;
        left: 0;
        right: 0;
        height: 2px;
        background: var(--color-primary);
    }
    
    /* ============ TAB CONTENT ============ */
    .tab-content {
        display: none;
        animation: fadeIn 0.5s ease;
    }
    
    .tab-content.active {
        display: block;
    }
    
    /* ============ CARD STYLES ============ */
    .card {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        transition: all var(--transition-normal);
        animation: fadeIn 0.6s ease-out;
    }
    
    .card:hover {
        box-shadow: var(--shadow-lg);
    }
    
    .card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--color-border);
    }
    
    .card-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--color-text);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .card-title-icon {
        color: var(--color-primary);
    }
    
    /* ============ TABLES ============ */
    .table-container {
        overflow-x: auto;
        border-radius: var(--radius-md);
        border: 1px solid var(--color-border);
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 800px;
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
    
    /* Badges */
    .badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .badge-success {
        background: var(--color-success);
        color: white;
    }
    
    .badge-warning {
        background: var(--color-warning);
        color: var(--color-text);
    }
    
    .badge-danger {
        background: var(--color-error);
        color: white;
    }
    
    .badge-info {
        background: var(--color-info);
        color: white;
    }
    
    .badge-secondary {
        background: var(--color-text-light);
        color: white;
    }
    
    /* Amount badges */
    .amount-badge {
        background: var(--color-border-light);
        color: var(--color-success);
        padding: 0.375rem 0.75rem;
        border-radius: var(--radius-md);
        font-size: 0.875rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        border: 1px solid var(--color-success);
    }
    
    /* Search box */
    .search-box {
        position: relative;
        width: 300px;
    }
    
    .search-box .search-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--color-text-light);
    }
    
    .search-box input {
        width: 100%;
        padding: 0.75rem 1rem 0.75rem 3rem;
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        color: var(--color-text);
        font-size: 0.95rem;
        transition: all var(--transition-normal);
    }
    
    .search-box input:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px var(--color-primary-light);
    }
    
    /* ============ MODAL STYLES ============ */
    .modal-content {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
    }
    
    .modal-header {
        border-bottom: 1px solid var(--color-border);
        padding: 1.5rem;
    }
    
    .modal-title {
        font-weight: 600;
        color: var(--color-text);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .modal-body {
        padding: 1.5rem;
    }
    
    .modal-footer {
        border-top: 1px solid var(--color-border);
        padding: 1.5rem;
    }
    
    /* Form styles */
    .form-label {
        font-weight: 500;
        color: var(--color-text);
        margin-bottom: 0.5rem;
        display: block;
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
        
        .search-box {
            width: 100%;
        }
    }
    
    @media (max-width: 480px) {
        .card {
            padding: 1.25rem;
        }
        
        .action-btn {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }
        
        .tab-btn {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
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
                    <a href="../purchases/index.php" class="nav-link active">
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
                    <h1 class="page-title">Gestión de Compras</h1>
                    <p class="page-subtitle">Registro y control de compras de medicamentos e insumos</p>
                </div>
                <div class="page-actions">
                    <button type="button" class="action-btn" onclick="showNewPurchaseModal()">
                        <i class="bi bi-plus-lg"></i>
                        <span>Nueva Compra</span>
                    </button>
                </div>
            </div>
            
            <!-- Navegación por pestañas -->
            <div class="tab-navigation">
                <button class="tab-btn active" data-tab="new-purchases">
                    <i class="bi bi-cart-check me-2"></i>Nuevas Compras
                </button>
                <button class="tab-btn" data-tab="old-purchases">
                    <i class="bi bi-archive me-2"></i>Compras Antiguas
                </button>
            </div>
            
            <!-- Contenido de pestaña: Nuevas Compras -->
            <div class="tab-content active" id="new-purchases-tab">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="bi bi-clock-history card-title-icon"></i>
                            Historial de Compras Recientes
                        </h3>
                        <div class="search-box">
                            <i class="bi bi-search search-icon"></i>
                            <input type="text" id="searchNew" placeholder="Buscar compra...">
                        </div>
                    </div>
                    
                    <div class="table-container">
                        <table class="data-table" id="tableNew">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Documento</th>
                                    <th>Proveedor</th>
                                    <th>Total</th>
                                    <th>Pagado</th>
                                    <th>Saldo / Pagar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $stmt = $conn->query("SELECT * FROM purchase_headers ORDER BY purchase_date DESC LIMIT 50");
                                    while ($row = $stmt->fetch()) {
                                        $paid = $row['paid_amount'] ?? 0;
                                        $balance = $row['total_amount'] - $paid;
                                        ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($row['purchase_date'])); ?></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="badge badge-secondary">
                                                        <?php echo htmlspecialchars($row['document_type']); ?>
                                                        <?php echo $row['document_number'] ? '#'.$row['document_number'] : ''; ?>
                                                    </span>
                                                    <button class="btn btn-sm btn-link text-primary p-0" onclick="viewPurchaseDetails(<?php echo $row['id']; ?>)" title="Ver Detalles">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['provider_name']); ?></td>
                                            <td class="fw-bold">Q<?php echo number_format($row['total_amount'], 2); ?></td>
                                            <td class="text-success">Q<?php echo number_format($paid, 2); ?></td>
                                            <td>
                                                <button class="btn btn-sm <?php echo $balance > 0 ? 'btn-outline-danger' : 'btn-outline-success'; ?> fw-bold w-100" onclick="openPaymentModal(<?php echo $row['id']; ?>)" title="Click para abonar">
                                                    Q<?php echo number_format($balance, 2); ?>
                                                    <i class="bi bi-cash-coin ms-1"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                } catch (PDOException $e) {
                                    echo "<tr><td colspan='6' class='text-center text-muted'>No hay compras registradas en el nuevo sistema.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Contenido de pestaña: Compras Antiguas -->
            <div class="tab-content" id="old-purchases-tab">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">
                                <i class="bi bi-archive card-title-icon"></i>
                                Historial de Compras Antiguas
                            </h3>
                            <p class="text-muted small mb-0">Registros anteriores a la actualización del sistema</p>
                        </div>
                        <div class="search-box">
                            <i class="bi bi-search search-icon"></i>
                            <input type="text" id="searchOld" placeholder="Buscar por producto...">
                        </div>
                    </div>
                    
                    <div class="table-container" style="max-height: 600px;">
                        <table class="data-table" id="tableOld">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Producto</th>
                                    <th>Presentación</th>
                                    <th>Casa Farm.</th>
                                    <th>Cant.</th>
                                    <th>Precio U.</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $stmtOld = $conn->query("SELECT * FROM compras ORDER BY fecha_compra DESC LIMIT 100");
                                    while ($row = $stmtOld->fetch()) {
                                        $statusClass = 'secondary';
                                        if ($row['estado_compra'] == 'Completo') $statusClass = 'success';
                                        if ($row['estado_compra'] == 'Pendiente') $statusClass = 'warning';
                                        if ($row['estado_compra'] == 'Abonado') $statusClass = 'info';
                                        ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($row['fecha_compra'])); ?></td>
                                            <td class="fw-bold"><?php echo htmlspecialchars($row['nombre_compra']); ?></td>
                                            <td><?php echo htmlspecialchars($row['presentacion_compra']); ?></td>
                                            <td><?php echo htmlspecialchars($row['casa_compra']); ?></td>
                                            <td><?php echo $row['cantidad_compra']; ?></td>
                                            <td>Q<?php echo number_format($row['precio_unidad'], 2); ?></td>
                                            <td class="fw-bold text-primary">Q<?php echo number_format($row['total_compra'], 2); ?></td>
                                            <td><span class="badge badge-<?php echo $statusClass; ?>"><?php echo $row['estado_compra']; ?></span></td>
                                        </tr>
                                        <?php
                                    }
                                } catch (PDOException $e) {
                                    echo "<tr><td colspan='8' class='text-center text-muted'>No se encontraron registros antiguos.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Modal para nueva compra -->
    <div class="modal fade" id="newPurchaseModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-bag-plus text-primary"></i>
                        Registrar Nueva Compra
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="purchaseForm">
                        <!-- Header Info -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label class="form-label">Fecha de Compra</label>
                                <input type="date" class="form-control" name="purchase_date" id="purchase_date" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tipo de Documento</label>
                                <select class="form-select" name="document_type" id="document_type" required>
                                    <option value="Factura">Factura</option>
                                    <option value="Nota de Envío">Nota de Envío</option>
                                    <option value="Consumidor Final">Consumidor Final</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">No. Documento</label>
                                <input type="text" class="form-control" name="document_number" id="document_number" placeholder="Ej. A-12345">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Casa Farmacéutica / Proveedor</label>
                                <input type="text" class="form-control" name="provider_name" id="provider_name" placeholder="Nombre de la casa farmacéutica">
                            </div>
                        </div>
                        
                        <hr class="opacity-25">
                        
                        <!-- Add Item Section -->
                        <h6 class="fw-bold mb-3">Agregar Productos</h6>
                        <div class="card bg-light border-0 mb-4">
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label small">Producto/Medicamento</label>
                                        <input type="text" class="form-control form-control-sm" id="item_name" placeholder="Nombre">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">Presentación</label>
                                        <input type="text" class="form-control form-control-sm" id="item_presentation" placeholder="Ej. Tableta">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">Molécula</label>
                                        <input type="text" class="form-control form-control-sm" id="item_molecule" placeholder="Componente">
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label small">Cant.</label>
                                        <input type="number" class="form-control form-control-sm" id="item_qty" min="1" value="1">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">Costo (Q)</label>
                                        <input type="number" class="form-control form-control-sm" id="item_cost" min="0" step="0.01">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">Precio Venta (Q)</label>
                                        <input type="number" class="form-control form-control-sm" id="item_sale_price" min="0" step="0.01">
                                    </div>
                                    <div class="col-md-12 d-flex justify-content-end mt-3">
                                        <button type="button" class="action-btn btn-sm" onclick="addItem()">
                                            <i class="bi bi-plus-lg me-2"></i>Agregar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Items List -->
                        <div class="table-responsive mb-3" style="max-height: 300px;">
                            <table class="table table-sm table-bordered" id="itemsTable">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Producto</th>
                                        <th>Presentación</th>
                                        <th>Cant.</th>
                                        <th>Costo U.</th>
                                        <th>Precio Venta</th>
                                        <th>Subtotal</th>
                                        <th style="width: 50px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Items will be added here -->
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="4" class="text-end fw-bold">Total Compra:</td>
                                        <td class="fw-bold text-primary">Q<span id="totalAmount">0.00</span></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="action-btn secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="action-btn" onclick="savePurchase()">
                        <i class="bi bi-check-lg me-2"></i>Guardar Compra
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para pagos -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-cash-coin text-primary"></i>
                        Gestionar Pagos / Abonos
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="paymentHeaderInfo" class="alert alert-info d-flex justify-content-between align-items-center mb-4">
                        <!-- Loaded dynamically -->
                        <span>Cargando información...</span>
                    </div>

                    <div class="row">
                        <div class="col-md-5 border-end">
                            <h6 class="fw-bold mb-3">Registrar Nuevo Abono</h6>
                            <form id="paymentForm">
                                <input type="hidden" id="pay_purchase_id" name="purchase_id">
                                
                                <div class="mb-3">
                                    <label class="form-label">Fecha</label>
                                    <input type="date" class="form-control" name="payment_date" id="pay_date" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Monto (Q)</label>
                                    <input type="number" class="form-control" name="amount" id="pay_amount" step="0.01" min="0.01" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Método de Pago</label>
                                    <select class="form-select" name="payment_method" id="pay_method">
                                        <option value="Efectivo">Efectivo</option>
                                        <option value="Cheque">Cheque</option>
                                        <option value="Transferencia">Transferencia</option>
                                        <option value="Depósito">Depósito</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Notas</label>
                                    <textarea class="form-control" name="notes" id="pay_notes" rows="2"></textarea>
                                </div>
                                
                                <button type="button" class="action-btn w-100" onclick="submitPayment()">
                                    <i class="bi bi-check-circle me-2"></i>Registrar Pago
                                </button>
                            </form>
                        </div>
                        
                        <div class="col-md-7">
                            <h6 class="fw-bold mb-3">Historial de Pagos</h6>
                            <div class="table-responsive" style="max-height: 300px;">
                                <table class="table table-sm table-hover" id="paymentsHistoryTable">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Método</th>
                                            <th>Monto</th>
                                            <th>Notas</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Loaded dynamically -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para detalles -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalles de Compra</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailsModalBody">
                    <div class="text-center"><div class="spinner-border text-primary"></div></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    // Módulo de Compras - Centro Médico Herrera Saenz
    // JavaScript para funcionalidades del módulo de compras
    
    // Esperar a que el DOM esté completamente cargado
    document.addEventListener('DOMContentLoaded', function() {
        // ============ REFERENCIAS A ELEMENTOS ============
        const themeSwitch = document.getElementById('themeSwitch');
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarToggleIcon = document.getElementById('sidebarToggleIcon');
        const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
        const tabButtons = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');
        
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
        
        // ============ FUNCIONALIDAD DE PESTAÑAS ============
        
        // Cambiar entre pestañas
        function switchTab(tabId) {
            // Remover clase active de todos los botones y contenidos
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Agregar clase active al botón clickeado
            const activeButton = document.querySelector(`[data-tab="${tabId}"]`);
            if (activeButton) {
                activeButton.classList.add('active');
            }
            
            // Mostrar el contenido correspondiente
            const activeContent = document.getElementById(`${tabId}-tab`);
            if (activeContent) {
                activeContent.classList.add('active');
            }
        }
        
        // ============ FUNCIONALIDAD DE COMPRAS ============
        
        let purchaseItems = [];
        
        // Inicializar fecha de compra a hoy
        document.getElementById('purchase_date').valueAsDate = new Date();
        
        // Mostrar modal de nueva compra
        window.showNewPurchaseModal = function() {
            // Resetear formulario
            document.getElementById('purchaseForm').reset();
            document.getElementById('purchase_date').valueAsDate = new Date();
            purchaseItems = [];
            renderItems();
            
            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('newPurchaseModal'));
            modal.show();
        };
        
        // Agregar item a la lista de compra
        window.addItem = function() {
            const name = document.getElementById('item_name').value.trim();
            const qty = parseFloat(document.getElementById('item_qty').value);
            const cost = parseFloat(document.getElementById('item_cost').value);
            const salePrice = parseFloat(document.getElementById('item_sale_price').value);
            
            // Validar campos obligatorios
            if (!name || !qty || isNaN(cost) || isNaN(salePrice)) {
                Swal.fire({
                    title: 'Campos incompletos',
                    text: 'Por favor complete todos los campos del producto',
                    icon: 'warning',
                    confirmButtonText: 'Entendido'
                });
                return;
            }
            
            // Crear objeto item
            const item = {
                id: Date.now(), // ID temporal
                name: name,
                presentation: document.getElementById('item_presentation').value.trim(),
                molecule: document.getElementById('item_molecule').value.trim(),
                qty: qty,
                cost: cost,
                sale_price: salePrice,
                subtotal: qty * cost
            };
            
            // Agregar a la lista
            purchaseItems.push(item);
            renderItems();
            
            // Limpiar campos de entrada
            document.getElementById('item_name').value = '';
            document.getElementById('item_presentation').value = '';
            document.getElementById('item_molecule').value = '';
            document.getElementById('item_qty').value = '1';
            document.getElementById('item_cost').value = '';
            document.getElementById('item_sale_price').value = '';
            document.getElementById('item_name').focus();
        };
        
        // Remover item de la lista
        window.removeItem = function(id) {
            purchaseItems = purchaseItems.filter(item => item.id !== id);
            renderItems();
        };
        
        // Renderizar items en la tabla
        function renderItems() {
            const tbody = document.querySelector('#itemsTable tbody');
            tbody.innerHTML = '';
            
            let total = 0;
            
            // Agregar cada item a la tabla
            purchaseItems.forEach(item => {
                total += item.subtotal;
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <div class="fw-bold">${item.name}</div>
                        <small class="text-muted">${item.molecule || 'Sin molécula especificada'}</small>
                    </td>
                    <td>${item.presentation || 'N/A'}</td>
                    <td class="text-center">${item.qty}</td>
                    <td class="text-end">Q${item.cost.toFixed(2)}</td>
                    <td class="text-end">Q${item.sale_price.toFixed(2)}</td>
                    <td class="text-end">Q${item.subtotal.toFixed(2)}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeItem(${item.id})" title="Eliminar">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
            
            // Actualizar total
            document.getElementById('totalAmount').textContent = total.toFixed(2);
        }
        
        // Guardar compra
        window.savePurchase = function() {
            // Validar que haya items
            if (purchaseItems.length === 0) {
                Swal.fire({
                    title: 'Compra vacía',
                    text: 'Debe agregar al menos un producto a la compra',
                    icon: 'warning',
                    confirmButtonText: 'Entendido'
                });
                return;
            }
            
            // Validar proveedor
            const providerName = document.getElementById('provider_name').value.trim();
            if (!providerName) {
                Swal.fire({
                    title: 'Proveedor requerido',
                    text: 'Debe especificar un proveedor o casa farmacéutica',
                    icon: 'warning',
                    confirmButtonText: 'Entendido'
                });
                return;
            }
            
            // Preparar datos del encabezado
            const header = {
                purchase_date: document.getElementById('purchase_date').value,
                document_type: document.getElementById('document_type').value,
                document_number: document.getElementById('document_number').value,
                provider_name: providerName,
                total_amount: parseFloat(document.getElementById('totalAmount').textContent)
            };
            
            // Preparar payload completo
            const payload = {
                header: header,
                items: purchaseItems
            };
            
            // Enviar datos al servidor
            fetch('save_purchase.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: '¡Compra Registrada!',
                        text: 'La compra se ha registrado correctamente. Los productos se han agregado al inventario como pendientes.',
                        icon: 'success',
                        confirmButtonText: 'Aceptar'
                    }).then(() => {
                        // Recargar página para mostrar los cambios
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message || 'Error al guardar la compra',
                        icon: 'error',
                        confirmButtonText: 'Entendido'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error de conexión',
                    text: 'Ocurrió un error al procesar la solicitud',
                    icon: 'error',
                    confirmButtonText: 'Entendido'
                });
            });
        };
        
        // Ver detalles de compra
        window.viewPurchaseDetails = function(id) {
            const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
            modal.show();
            
            // Mostrar spinner mientras carga
            document.getElementById('detailsModalBody').innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-2 text-muted">Cargando detalles...</p>
                </div>
            `;
            
            // Obtener datos del servidor
            fetch('get_purchase_details.php?id=' + id)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const h = data.header;
                    let html = `
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <p class="mb-2"><strong>Proveedor:</strong> ${h.provider_name}</p>
                                <p class="mb-2"><strong>Documento:</strong> ${h.document_type} ${h.document_number || 'N/A'}</p>
                                <p class="mb-0"><strong>Fecha:</strong> ${h.purchase_date}</p>
                            </div>
                            <div class="col-md-6 text-end">
                                <p class="mb-2"><strong>Total Compra:</strong> Q${parseFloat(h.total_amount).toFixed(2)}</p>
                                <p class="mb-2"><strong>Pagado:</strong> Q${parseFloat(h.paid_amount || 0).toFixed(2)}</p>
                                <p class="mb-0"><strong>Saldo:</strong> Q${parseFloat(h.total_amount - (h.paid_amount || 0)).toFixed(2)}</p>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Producto</th>
                                        <th>Presentación</th>
                                        <th>Molécula</th>
                                        <th>Cant.</th>
                                        <th>Costo U.</th>
                                        <th>Precio Venta</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    
                    data.items.forEach(item => {
                        html += `
                            <tr>
                                <td>${item.product_name}</td>
                                <td>${item.presentation || 'N/A'}</td>
                                <td>${item.molecule || 'N/A'}</td>
                                <td class="text-center">${item.quantity}</td>
                                <td class="text-end">Q${parseFloat(item.unit_cost).toFixed(2)}</td>
                                <td class="text-end">Q${parseFloat(item.sale_price || 0).toFixed(2)}</td>
                                <td class="text-end">Q${parseFloat(item.subtotal).toFixed(2)}</td>
                            </tr>
                        `;
                    });
                    
                    html += `
                                </tbody>
                            </table>
                        </div>
                    `;
                    
                    document.getElementById('detailsModalBody').innerHTML = html;
                } else {
                    document.getElementById('detailsModalBody').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            ${data.message || 'Error al cargar los detalles de la compra'}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('detailsModalBody').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Error de conexión al cargar los detalles
                    </div>
                `;
            });
        };
        
        // Abrir modal de pagos
        window.openPaymentModal = function(id) {
            // Establecer ID de compra
            document.getElementById('pay_purchase_id').value = id;
            
            // Establecer fecha actual
            document.getElementById('pay_date').valueAsDate = new Date();
            
            // Limpiar campos
            document.getElementById('pay_amount').value = '';
            document.getElementById('pay_notes').value = '';
            
            // Cargar información de pagos
            loadPayments(id);
            
            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
            modal.show();
        };
        
        // Cargar información de pagos
        function loadPayments(id) {
            // Mostrar estado de carga
            document.getElementById('paymentHeaderInfo').innerHTML = `
                <div class="text-center w-100">
                    <div class="spinner-border spinner-border-sm text-primary"></div>
                    <span class="ms-2">Cargando información...</span>
                </div>
            `;
            
            document.querySelector('#paymentsHistoryTable tbody').innerHTML = `
                <tr>
                    <td colspan="4" class="text-center text-muted">
                        <div class="spinner-border spinner-border-sm"></div>
                        Cargando historial...
                    </td>
                </tr>
            `;
            
            // Obtener datos del servidor
            fetch('get_payments.php?id=' + id)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const h = data.header;
                    const total = parseFloat(h.total_amount);
                    const paid = parseFloat(h.paid_amount || 0);
                    const balance = total - paid;
                    
                    // Actualizar información del encabezado
                    const infoHtml = `
                        <div>
                            <strong>${h.document_type} ${h.document_number || ''}</strong><br>
                            <small class="text-muted">${h.provider_name}</small>
                        </div>
                        <div class="text-end">
                            <div class="badge bg-success mb-1">Pagado: Q${paid.toFixed(2)}</div><br>
                            <div class="badge ${balance > 0 ? 'bg-danger' : 'bg-success'}">Saldo: Q${balance.toFixed(2)}</div>
                        </div>
                    `;
                    document.getElementById('paymentHeaderInfo').innerHTML = infoHtml;
                    
                    // Establecer monto sugerido (saldo pendiente)
                    if (!document.getElementById('pay_amount').value && balance > 0) {
                        document.getElementById('pay_amount').value = balance.toFixed(2);
                    }
                    
                    // Actualizar historial de pagos
                    const tbody = document.querySelector('#paymentsHistoryTable tbody');
                    tbody.innerHTML = '';
                    
                    if (data.payments.length === 0) {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">
                                    No hay pagos registrados
                                </td>
                            </tr>
                        `;
                    } else {
                        data.payments.forEach(p => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${p.payment_date}</td>
                                <td>${p.payment_method}</td>
                                <td class="fw-bold text-success">Q${parseFloat(p.amount).toFixed(2)}</td>
                                <td><small>${p.notes || '-'}</small></td>
                            `;
                            tbody.appendChild(row);
                        });
                    }
                } else {
                    document.getElementById('paymentHeaderInfo').innerHTML = `
                        <div class="alert alert-danger w-100 mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            ${data.message}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('paymentHeaderInfo').innerHTML = `
                    <div class="alert alert-danger w-100 mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Error de conexión al cargar la información
                    </div>
                `;
            });
        };
        
        // Enviar pago
        window.submitPayment = function() {
            const form = document.getElementById('paymentForm');
            const formData = new FormData(form);
            
            // Validar monto
            const amount = parseFloat(formData.get('amount'));
            if (amount <= 0) {
                Swal.fire({
                    title: 'Monto inválido',
                    text: 'El monto debe ser mayor a cero',
                    icon: 'warning',
                    confirmButtonText: 'Entendido'
                });
                return;
            }
            
            // Enviar pago al servidor
            fetch('save_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: '¡Pago Registrado!',
                        text: 'El abono se ha registrado correctamente',
                        icon: 'success',
                        confirmButtonText: 'Aceptar',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        // Recargar página para actualizar datos
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message || 'Error al registrar el pago',
                        icon: 'error',
                        confirmButtonText: 'Entendido'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error de conexión',
                    text: 'Ocurrió un error al procesar el pago',
                    icon: 'error',
                    confirmButtonText: 'Entendido'
                });
            });
        };
        
        // ============ FUNCIONALIDAD DE BÚSQUEDA ============
        
        // Búsqueda en tabla de nuevas compras
        document.getElementById('searchNew').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#tableNew tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        // Búsqueda en tabla de compras antiguas
        document.getElementById('searchOld').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#tableOld tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        // ============ INICIALIZACIÓN ============
        
        // Inicializar componentes
        initializeTheme();
        initializeSidebar();
        
        // Configurar event listeners para pestañas
        tabButtons.forEach(button => {
            button.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                switchTab(tabId);
            });
        });
        
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
        
        // Cerrar sidebar al cambiar tamaño de ventana (responsive)
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992 && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
        });
        
        // ============ CONSOLA DE DESARROLLO ============
        
        console.log('Módulo de Compras - Centro Médico Herrera Saenz');
        console.log('Versión: 3.0 - Diseño con Efecto Mármol y Modo Noche');
        console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
        console.log('Rol: <?php echo htmlspecialchars($user_type); ?>');
    });
    </script>
</body>
</html>