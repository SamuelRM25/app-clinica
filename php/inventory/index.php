<?php
// inventory/index.php - Módulo de Inventario Reingenierizado
// Centro Médico Herrera Saenz - Sistema de Gestión Médica
// Versión: 3.0 - Diseño Minimalista con Modo Noche y Efecto Mármol

session_start();
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
    
    // Obtener estadísticas del inventario
    $stats_query = "SELECT 
        COUNT(*) as total_items,
        SUM(CASE WHEN cantidad_med = 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(CASE WHEN cantidad_med > 0 AND cantidad_med <= 10 THEN 1 ELSE 0 END) as low_stock,
        SUM(CASE WHEN DATEDIFF(fecha_vencimiento, NOW()) <= 30 AND fecha_vencimiento >= NOW() THEN 1 ELSE 0 END) as expiring_soon,
        SUM(CASE WHEN fecha_vencimiento < NOW() THEN 1 ELSE 0 END) as expired,
        SUM(CASE WHEN estado = 'Pendiente' THEN 1 ELSE 0 END) as pending_receipt
    FROM inventario";
    $stats_stmt = $conn->query($stats_query);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener todos los medicamentos para la tabla
    $inventory_stmt = $conn->query("SELECT * FROM inventario ORDER BY fecha_vencimiento ASC");
    $inventory_items = $inventory_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Título de la página
    $page_title = "Inventario - Centro Médico Herrera Saenz";
    
} catch (Exception $e) {
    // Manejo de errores
    error_log("Error en inventario: " . $e->getMessage());
    die("Error al cargar el inventario. Por favor, contacte al administrador.");
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
    
    <!-- Incluir header -->
    <?php include_once '../../includes/header.php'; ?>
    
    <style>
    /* 
     * Módulo de Inventario Minimalista - Centro Médico Herrera Saenz
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
    
    .brand-text {
        display: flex;
        flex-direction: column;
    }
    
    .clinic-name {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--color-text);
        letter-spacing: -0.5px;
        line-height: 1.2;
    }
    
    .clinic-subname {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--color-primary);
        letter-spacing: 0.5px;
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
    
    /* ============ ESTADÍSTICAS DEL INVENTARIO ============ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.5rem;
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
    .stat-icon.danger { background: linear-gradient(135deg, var(--color-error), #dc2626); }
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
    
    .stat-subtitle {
        font-size: 0.875rem;
        color: var(--color-text-muted);
    }
    
    /* ============ BARRA DE ACCIONES ============ */
    .action-bar {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-bottom: 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
        animation: fadeIn 0.6s ease-out 0.3s both;
    }
    
    .page-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--color-text);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .page-title-icon {
        color: var(--color-primary);
    }
    
    .action-buttons {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }
    
    .btn {
        padding: 0.625rem 1.25rem;
        border-radius: var(--radius-md);
        font-weight: 500;
        font-size: 0.875rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all var(--transition-normal);
        border: none;
        text-decoration: none;
    }
    
    .btn-primary {
        background: var(--color-primary);
        color: white;
    }
    
    .btn-primary:hover {
        background: var(--color-primary-dark);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    
    .btn-secondary {
        background: var(--color-surface);
        color: var(--color-text);
        border: 1px solid var(--color-border);
    }
    
    .btn-secondary:hover {
        background: var(--color-border);
        transform: translateY(-2px);
    }
    
    .btn-success {
        background: var(--color-success);
        color: white;
    }
    
    .btn-success:hover {
        background: #10b981;
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    
    /* ============ FILTROS Y BÚSQUEDA ============ */
    .filters-section {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-bottom: 2rem;
        animation: fadeIn 0.6s ease-out 0.4s both;
    }
    
    .search-box {
        position: relative;
        margin-bottom: 1.5rem;
    }
    
    .search-input {
        width: 100%;
        padding: 0.875rem 1rem 0.875rem 3rem;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        background: var(--color-surface);
        color: var(--color-text);
        font-size: 0.95rem;
        transition: all var(--transition-normal);
    }
    
    .search-input:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px var(--color-primary-light);
        opacity: 0.3;
    }
    
    .search-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--color-text-light);
        font-size: 1.25rem;
    }
    
    .filter-pills {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    
    .filter-pill {
        padding: 0.5rem 1rem;
        border-radius: 50px;
        border: 1px solid var(--color-border);
        background: var(--color-surface);
        color: var(--color-text);
        cursor: pointer;
        transition: all var(--transition-normal);
        font-size: 0.875rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .filter-pill:hover {
        background: var(--color-border);
        transform: translateY(-2px);
    }
    
    .filter-pill.active {
        background: var(--color-primary);
        color: white;
        border-color: var(--color-primary);
    }
    
    /* ============ TABLA DE INVENTARIO ============ */
    .inventory-section {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        animation: fadeIn 0.6s ease-out 0.5s both;
    }
    
    .inventory-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .inventory-table th {
        text-align: left;
        padding: 1rem;
        font-weight: 600;
        color: var(--color-text-light);
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid var(--color-border);
        background: var(--color-border-light);
    }
    
    .inventory-table td {
        padding: 1rem;
        border-bottom: 1px solid var(--color-border);
        color: var(--color-text);
        transition: background-color var(--transition-normal);
        vertical-align: middle;
    }
    
    .inventory-table tbody tr:hover td {
        background: var(--color-border-light);
    }
    
    .inventory-table tbody tr:last-child td {
        border-bottom: none;
    }
    
    /* Indicadores de estado */
    .stock-indicator {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.375rem 0.75rem;
        border-radius: var(--radius-md);
        font-size: 0.875rem;
        font-weight: 500;
    }
    
    .stock-adequate {
        background: rgba(52, 211, 153, 0.1);
        color: var(--color-success);
        border: 1px solid rgba(52, 211, 153, 0.2);
    }
    
    .stock-low {
        background: rgba(251, 191, 36, 0.1);
        color: var(--color-warning);
        border: 1px solid rgba(251, 191, 36, 0.2);
    }
    
    .stock-out {
        background: rgba(248, 113, 113, 0.1);
        color: var(--color-error);
        border: 1px solid rgba(248, 113, 113, 0.2);
    }
    
    .stock-pending {
        background: rgba(148, 163, 184, 0.1);
        color: var(--color-text-muted);
        border: 1px solid rgba(148, 163, 184, 0.2);
    }
    
    .expiry-indicator {
        display: inline-block;
        padding: 0.375rem 0.75rem;
        border-radius: var(--radius-md);
        font-size: 0.875rem;
        font-weight: 500;
    }
    
    .expiry-valid {
        background: rgba(52, 211, 153, 0.1);
        color: var(--color-success);
        border: 1px solid rgba(52, 211, 153, 0.2);
    }
    
    .expiry-expiring {
        background: rgba(251, 191, 36, 0.1);
        color: var(--color-warning);
        border: 1px solid rgba(251, 191, 36, 0.2);
    }
    
    .expiry-expired {
        background: rgba(248, 113, 113, 0.1);
        color: var(--color-error);
        border: 1px solid rgba(248, 113, 113, 0.2);
    }
    
    /* Botones de acción en tabla */
    .table-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .btn-icon {
        width: 36px;
        height: 36px;
        border-radius: var(--radius-md);
        border: 1px solid var(--color-border);
        background: transparent;
        color: var(--color-text);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all var(--transition-normal);
        text-decoration: none;
    }
    
    .btn-icon:hover {
        transform: translateY(-2px);
    }
    
    .btn-icon.edit:hover {
        background: var(--color-info);
        color: white;
        border-color: var(--color-info);
    }
    
    .btn-icon.delete:hover {
        background: var(--color-error);
        color: white;
        border-color: var(--color-error);
    }
    
    .btn-icon.receive:hover {
        background: var(--color-success);
        color: white;
        border-color: var(--color-success);
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
    
    /* ============ MODALES ============ */
    .modal-content {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        color: var(--color-text);
    }
    
    .modal-header {
        border-bottom: 1px solid var(--color-border);
        background: var(--color-primary);
        color: white;
        border-radius: var(--radius-lg) var(--radius-lg) 0 0;
    }
    
    .modal-header .btn-close {
        filter: invert(1);
    }
    
    .modal-body {
        padding: 1.5rem;
    }
    
    .form-label {
        font-weight: 500;
        color: var(--color-text);
        margin-bottom: 0.5rem;
        display: block;
    }
    
    .form-control {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        background: var(--color-surface);
        color: var(--color-text);
        font-size: 0.95rem;
        transition: all var(--transition-normal);
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px var(--color-primary-light);
        opacity: 0.3;
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
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .action-bar {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .action-buttons {
            width: 100%;
            justify-content: space-between;
        }
        
        .inventory-table {
            display: block;
            overflow-x: auto;
        }
        
        .table-actions {
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .btn-icon {
            width: 32px;
            height: 32px;
        }
    }
    
    @media (max-width: 480px) {
        .stat-card {
            padding: 1.25rem;
        }
        
        .filters-section {
            padding: 1.25rem;
        }
        
        .inventory-section {
            padding: 1.25rem;
        }
        
        .filter-pills {
            justify-content: center;
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
                    <a href="../auth/logout.php" class="btn btn-primary logout-btn" title="Cerrar sesión">
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
                    <a href="../inventory/index.php" class="nav-link active">
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
            <?php if (isset($_SESSION['inventory_message'])): ?>
                <div class="notification alert alert-<?php echo $_SESSION['inventory_status'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['inventory_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php 
                unset($_SESSION['inventory_message']);
                unset($_SESSION['inventory_status']);
                ?>
            <?php endif; ?>
            
            <!-- Barra de acciones -->
            <div class="action-bar">
                <div>
                    <h1 class="page-title">
                        <i class="bi bi-box-seam page-title-icon"></i>
                        Gestión de Inventario
                    </h1>
                    <p class="text-muted mb-0">Control y administración de medicamentos e insumos médicos</p>
                </div>
                <div class="action-buttons">
                    <a href="generate_report.php" class="btn btn-secondary">
                        <i class="bi bi-file-earmark-spreadsheet"></i>
                        Exportar CSV
                    </a>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMedicineModal">
                        <i class="bi bi-plus-circle"></i>
                        Agregar Medicamento
                    </button>
                </div>
            </div>
            
            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Total de Items</div>
                            <div class="stat-value"><?php echo $stats['total_items']; ?></div>
                            <div class="stat-subtitle">En inventario</div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="bi bi-box-seam"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Agotados</div>
                            <div class="stat-value"><?php echo $stats['out_of_stock']; ?></div>
                            <div class="stat-subtitle">Sin stock disponible</div>
                        </div>
                        <div class="stat-icon danger">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Stock Bajo</div>
                            <div class="stat-value"><?php echo $stats['low_stock']; ?></div>
                            <div class="stat-subtitle">Menos de 10 unidades</div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="bi bi-exclamation-circle"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Por Vencer</div>
                            <div class="stat-value"><?php echo $stats['expiring_soon']; ?></div>
                            <div class="stat-subtitle">Próximos 30 días</div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="bi bi-clock-history"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filtros y búsqueda -->
            <div class="filters-section">
                <div class="search-box">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" class="search-input" id="searchInput" placeholder="Buscar por nombre, molécula o casa farmacéutica...">
                </div>
                
                <div class="filter-pills">
                    <button class="filter-pill active" data-filter="all">
                        <i class="bi bi-grid"></i>
                        Todos
                    </button>
                    <button class="filter-pill" data-filter="adequate">
                        <i class="bi bi-check-circle"></i>
                        En Stock
                    </button>
                    <button class="filter-pill" data-filter="low">
                        <i class="bi bi-exclamation-circle"></i>
                        Stock Bajo
                    </button>
                    <button class="filter-pill" data-filter="out">
                        <i class="bi bi-x-circle"></i>
                        Agotados
                    </button>
                    <button class="filter-pill" data-filter="expiring">
                        <i class="bi bi-clock-history"></i>
                        Por Vencer
                    </button>
                    <button class="filter-pill" data-filter="expired">
                        <i class="bi bi-calendar-x"></i>
                        Vencidos
                    </button>
                    <button class="filter-pill" data-filter="pending">
                        <i class="bi bi-box-arrow-in-down"></i>
                        Pendientes
                    </button>
                </div>
            </div>
            
            <!-- Tabla de inventario -->
            <div class="inventory-section">
                <?php if (count($inventory_items) > 0): ?>
                    <div class="table-responsive">
                        <table class="inventory-table" id="inventoryTable">
                            <thead>
                                <tr>
                                    <th>Medicamento</th>
                                    <th>Molécula</th>
                                    <th>Presentación</th>
                                    <th>Casa Farmacéutica</th>
                                    <th>Stock</th>
                                    <th>Adquisición</th>
                                    <th>Vencimiento</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inventory_items as $item): ?>
                                    <?php 
                                    // Determinar estado del stock
                                    $estado = $item['estado'] ?? 'Disponible';
                                    $cantidad = $item['cantidad_med'];
                                    $stock_class = 'stock-adequate';
                                    $stock_text = 'Adecuado';
                                    
                                    if ($estado === 'Pendiente') {
                                        $stock_class = 'stock-pending';
                                        $stock_text = 'Pendiente';
                                    } elseif ($cantidad == 0) {
                                        $stock_class = 'stock-out';
                                        $stock_text = 'Agotado';
                                    } elseif ($cantidad <= 10) {
                                        $stock_class = 'stock-low';
                                        $stock_text = 'Bajo';
                                    }
                                    
                                    // Determinar estado de vencimiento
                                    $expiry_class = 'expiry-valid';
                                    $expiry_text = 'Válido';
                                    
                                    if ($item['fecha_vencimiento']) {
                                        $expiry_date = new DateTime($item['fecha_vencimiento']);
                                        $today = new DateTime();
                                        $days_diff = $today->diff($expiry_date)->days;
                                        $is_expired = $expiry_date < $today;
                                        
                                        if ($is_expired) {
                                            $expiry_class = 'expiry-expired';
                                            $expiry_text = 'Vencido';
                                        } elseif ($days_diff <= 30) {
                                            $expiry_class = 'expiry-expiring';
                                            $expiry_text = $days_diff . ' días';
                                        }
                                    } else {
                                        if ($estado === 'Pendiente') {
                                            $expiry_text = 'Por definir';
                                        }
                                    }
                                    
                                    // Data attributes para filtrado
                                    $data_attrs = "data-stock='{$stock_class}' data-expiry='{$expiry_class}'";
                                    ?>
                                    <tr <?php echo $data_attrs; ?>>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['nom_medicamento']); ?></strong>
                                            <?php if ($estado === 'Pendiente'): ?>
                                                <div class="mt-1">
                                                    <span class="stock-pending" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">
                                                        Pendiente de recepción
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['mol_medicamento']); ?></td>
                                        <td><?php echo htmlspecialchars($item['presentacion_med']); ?></td>
                                        <td><?php echo htmlspecialchars($item['casa_farmaceutica']); ?></td>
                                        <td>
                                            <span class="stock-indicator <?php echo $stock_class; ?>">
                                                <i class="bi bi-box"></i>
                                                <?php echo $item['cantidad_med']; ?> unidades
                                            </span>
                                        </td>
                                        <td><?php echo $item['fecha_adquisicion'] ? date('d/m/Y', strtotime($item['fecha_adquisicion'])) : 'N/A'; ?></td>
                                        <td>
                                            <?php if ($item['fecha_vencimiento']): ?>
                                                <div class="mb-1"><?php echo date('d/m/Y', strtotime($item['fecha_vencimiento'])); ?></div>
                                                <span class="expiry-indicator <?php echo $expiry_class; ?>">
                                                    <?php echo $expiry_text; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="table-actions">
                                                <?php if ($estado === 'Pendiente'): ?>
                                                    <button type="button" class="btn-icon receive" 
                                                            onclick="openReceiveModal(<?php echo $item['id_inventario']; ?>, '<?php echo htmlspecialchars($item['nom_medicamento']); ?>')"
                                                            title="Recibir producto">
                                                        <i class="bi bi-box-arrow-in-down"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn-icon edit" 
                                                            data-id="<?php echo $item['id_inventario']; ?>"
                                                            data-bs-toggle="modal" data-bs-target="#editMedicineModal"
                                                            title="Editar">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" class="btn-icon delete"
                                                            data-id="<?php echo $item['id_inventario']; ?>"
                                                            title="Eliminar">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="bi bi-box"></i>
                        </div>
                        <h4 class="text-muted mb-2">No hay medicamentos en el inventario</h4>
                        <p class="text-muted mb-3">Comience agregando nuevos medicamentos al sistema</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMedicineModal">
                            <i class="bi bi-plus-circle"></i>
                            Agregar primer medicamento
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Modal para agregar medicamento -->
    <div class="modal fade" id="addMedicineModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle me-2"></i>
                        Agregar Medicamento
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addMedicineForm" action="save_medicine.php" method="POST">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="nom_medicamento" class="form-label">Nombre del Medicamento</label>
                                <input type="text" class="form-control" id="nom_medicamento" name="nom_medicamento" required>
                            </div>
                            <div class="col-md-6">
                                <label for="mol_medicamento" class="form-label">Molécula</label>
                                <input type="text" class="form-control" id="mol_medicamento" name="mol_medicamento" required>
                            </div>
                            <div class="col-md-6">
                                <label for="presentacion_med" class="form-label">Presentación</label>
                                <input type="text" class="form-control" id="presentacion_med" name="presentacion_med" required>
                            </div>
                            <div class="col-md-6">
                                <label for="casa_farmaceutica" class="form-label">Casa Farmacéutica</label>
                                <input type="text" class="form-control" id="casa_farmaceutica" name="casa_farmaceutica" required>
                            </div>
                            <div class="col-md-4">
                                <label for="cantidad_med" class="form-label">Cantidad</label>
                                <input type="number" class="form-control" id="cantidad_med" name="cantidad_med" min="0" required>
                            </div>
                            <div class="col-md-4">
                                <label for="fecha_adquisicion" class="form-label">Fecha de Adquisición</label>
                                <input type="date" class="form-control" id="fecha_adquisicion" name="fecha_adquisicion" required>
                            </div>
                            <div class="col-md-4">
                                <label for="fecha_vencimiento" class="form-label">Fecha de Vencimiento</label>
                                <input type="date" class="form-control" id="fecha_vencimiento" name="fecha_vencimiento" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>
                            Guardar Medicamento
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal para editar medicamento -->
    <div class="modal fade" id="editMedicineModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil me-2"></i>
                        Editar Medicamento
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editMedicineForm" action="update_medicine.php" method="POST">
                    <input type="hidden" name="id_inventario" id="edit_id_inventario">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="edit_nom_medicamento" class="form-label">Nombre del Medicamento</label>
                                <input type="text" class="form-control" id="edit_nom_medicamento" name="nom_medicamento" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_mol_medicamento" class="form-label">Molécula</label>
                                <input type="text" class="form-control" id="edit_mol_medicamento" name="mol_medicamento" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_presentacion_med" class="form-label">Presentación</label>
                                <input type="text" class="form-control" id="edit_presentacion_med" name="presentacion_med" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_casa_farmaceutica" class="form-label">Casa Farmacéutica</label>
                                <input type="text" class="form-control" id="edit_casa_farmaceutica" name="casa_farmaceutica" required>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_cantidad_med" class="form-label">Cantidad</label>
                                <input type="number" class="form-control" id="edit_cantidad_med" name="cantidad_med" min="0" required>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_fecha_adquisicion" class="form-label">Fecha de Adquisición</label>
                                <input type="date" class="form-control" id="edit_fecha_adquisicion" name="fecha_adquisicion" required>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_fecha_vencimiento" class="form-label">Fecha de Vencimiento</label>
                                <input type="date" class="form-control" id="edit_fecha_vencimiento" name="fecha_vencimiento" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>
                            Actualizar Medicamento
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal para recibir medicamento -->
    <div class="modal fade" id="receiveMedicineModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-box-arrow-in-down me-2"></i>
                        Recibir Medicamento
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Medicamento</label>
                        <input type="text" class="form-control" id="receive_nom_medicamento" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="receive_fecha_vencimiento" class="form-label">Fecha de Vencimiento</label>
                        <input type="date" class="form-control" id="receive_fecha_vencimiento" required>
                    </div>
                    <input type="hidden" id="receive_id_inventario">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="submitReceive()">
                        <i class="bi bi-check-circle me-2"></i>
                        Confirmar Recepción
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
    // Módulo de Inventario - Centro Médico Herrera Saenz
    // JavaScript para funcionalidades del inventario
    
    document.addEventListener('DOMContentLoaded', function() {
        // ============ REFERENCIAS A ELEMENTOS ============
        const themeSwitch = document.getElementById('themeSwitch');
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarToggleIcon = document.getElementById('sidebarToggleIcon');
        const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
        const searchInput = document.getElementById('searchInput');
        const filterPills = document.querySelectorAll('.filter-pill');
        const tableRows = document.querySelectorAll('#inventoryTable tbody tr');
        const editButtons = document.querySelectorAll('.btn-icon.edit');
        const deleteButtons = document.querySelectorAll('.btn-icon.delete');
        
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
        
        // ============ BÚSQUEDA Y FILTRADO ============
        
        // Función para buscar en la tabla
        function searchTable() {
            const searchTerm = searchInput.value.toLowerCase();
            
            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        }
        
        // Función para aplicar filtros
        function applyFilter(filter) {
            tableRows.forEach(row => {
                const stockAttr = row.getAttribute('data-stock');
                const expiryAttr = row.getAttribute('data-expiry');
                let show = false;
                
                switch(filter) {
                    case 'all':
                        show = true;
                        break;
                    case 'adequate':
                        show = stockAttr === 'stock-adequate';
                        break;
                    case 'low':
                        show = stockAttr === 'stock-low';
                        break;
                    case 'out':
                        show = stockAttr === 'stock-out';
                        break;
                    case 'expiring':
                        show = expiryAttr === 'expiry-expiring';
                        break;
                    case 'expired':
                        show = expiryAttr === 'expiry-expired';
                        break;
                    case 'pending':
                        show = stockAttr === 'stock-pending';
                        break;
                }
                
                // Combinar con búsqueda si hay término
                if (show && searchInput.value) {
                    const text = row.textContent.toLowerCase();
                    const searchTerm = searchInput.value.toLowerCase();
                    show = text.includes(searchTerm);
                }
                
                row.style.display = show ? '' : 'none';
            });
        }
        
        // ============ FUNCIONALIDAD DE MEDICAMENTOS ============
        
        // Abrir modal para recibir medicamento
        window.openReceiveModal = function(id, name) {
            document.getElementById('receive_id_inventario').value = id;
            document.getElementById('receive_nom_medicamento').value = name;
            
            // Establecer fecha de vencimiento predeterminada (1 año desde hoy)
            const defaultDate = new Date();
            defaultDate.setFullYear(defaultDate.getFullYear() + 1);
            document.getElementById('receive_fecha_vencimiento').valueAsDate = defaultDate;
            
            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('receiveMedicineModal'));
            modal.show();
        };
        
        // Enviar recepción de medicamento
        window.submitReceive = function() {
            const id = document.getElementById('receive_id_inventario').value;
            const expiryDate = document.getElementById('receive_fecha_vencimiento').value;
            
            if (!expiryDate) {
                alert('Por favor ingrese la fecha de vencimiento');
                return;
            }
            
            // En un sistema real, aquí se haría una petición AJAX
            // Por simplicidad, redirigimos a un script PHP
            window.location.href = `receive_item.php?id=${id}&expiry=${expiryDate}`;
        };
        
        // Configurar botones de edición
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                
                // En un sistema real, aquí se haría una petición AJAX para obtener los datos
                // Por ahora, mostramos un mensaje
                console.log(`Editar medicamento con ID: ${id}`);
                
                // Simular carga de datos (en un caso real, esto sería una petición AJAX)
                fetch(`get_medicine.php?id=${id}`)
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('edit_id_inventario').value = data.id_inventario;
                        document.getElementById('edit_nom_medicamento').value = data.nom_medicamento;
                        document.getElementById('edit_mol_medicamento').value = data.mol_medicamento;
                        document.getElementById('edit_presentacion_med').value = data.presentacion_med;
                        document.getElementById('edit_casa_farmaceutica').value = data.casa_farmaceutica;
                        document.getElementById('edit_cantidad_med').value = data.cantidad_med;
                        document.getElementById('edit_fecha_adquisicion').value = data.fecha_adquisicion;
                        document.getElementById('edit_fecha_vencimiento').value = data.fecha_vencimiento;
                    })
                    .catch(error => {
                        console.error('Error al cargar datos:', error);
                    });
            });
        });
        
        // Configurar botones de eliminación
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                
                if (confirm('¿Está seguro de eliminar este medicamento? Esta acción no se puede deshacer.')) {
                    window.location.href = `delete_medicine.php?id=${id}`;
                }
            });
        });
        
        // ============ ANIMACIONES AL CARGAR ============
        
        // Animar elementos al cargar la página
        function animateOnLoad() {
            const cards = document.querySelectorAll('.stat-card, .action-bar, .filters-section, .inventory-section');
            
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
            
            // Animar filas de la tabla
            tableRows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateX(-20px)';
                
                setTimeout(() => {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateX(0)';
                }, index * 50);
            });
        }
        
        // ============ INICIALIZACIÓN ============
        
        // Inicializar componentes
        initializeTheme();
        initializeSidebar();
        animateOnLoad();
        
        // Establecer fecha actual como predeterminada en formularios
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('fecha_adquisicion').value = today;
        
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
        
        // Búsqueda
        if (searchInput) {
            searchInput.addEventListener('input', searchTable);
        }
        
        // Filtros
        filterPills.forEach(pill => {
            pill.addEventListener('click', function() {
                // Remover clase active de todos
                filterPills.forEach(p => p.classList.remove('active'));
                // Agregar clase active al actual
                this.classList.add('active');
                
                const filter = this.getAttribute('data-filter');
                applyFilter(filter);
            });
        });
        
        // Cerrar sidebar al cambiar tamaño de ventana (responsive)
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992 && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
        });
        
        // ============ CONSOLA DE DESARROLLO ============
        
        console.log('Módulo de Inventario - Centro Médico Herrera Saenz');
        console.log('Versión: 3.0 - Diseño con Efecto Mármol y Modo Noche');
        console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
        console.log('Rol: <?php echo htmlspecialchars($user_type); ?>');
        console.log('Total de items: <?php echo $stats['total_items']; ?>');
    });
    
    // Manejar envío del formulario de agregar medicamento
    document.getElementById('addMedicineForm')?.addEventListener('submit', function(e) {
        // Validación básica
        const cantidad = document.getElementById('cantidad_med').value;
        if (cantidad < 0) {
            e.preventDefault();
            alert('La cantidad no puede ser negativa');
            return false;
        }
        
        const fechaAdq = document.getElementById('fecha_adquisicion').value;
        const fechaVen = document.getElementById('fecha_vencimiento').value;
        
        if (new Date(fechaVen) < new Date(fechaAdq)) {
            e.preventDefault();
            alert('La fecha de vencimiento no puede ser anterior a la fecha de adquisición');
            return false;
        }
    });
    
    // Manejar envío del formulario de editar medicamento
    document.getElementById('editMedicineForm')?.addEventListener('submit', function(e) {
        // Validación básica
        const cantidad = document.getElementById('edit_cantidad_med').value;
        if (cantidad < 0) {
            e.preventDefault();
            alert('La cantidad no puede ser negativa');
            return false;
        }
        
        const fechaAdq = document.getElementById('edit_fecha_adquisicion').value;
        const fechaVen = document.getElementById('edit_fecha_vencimiento').value;
        
        if (new Date(fechaVen) < new Date(fechaAdq)) {
            e.preventDefault();
            alert('La fecha de vencimiento no puede ser anterior a la fecha de adquisición');
            return false;
        }
    });
    </script>
    
    <!-- Incluir footer -->
    <?php include_once '../../includes/footer.php'; ?>
</body>
</html>