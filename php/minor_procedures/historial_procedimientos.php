<?php
// historial_procedimientos.php - Historial de Procedimientos Menores - Centro Médico Herrera Saenz
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
$page_title = "Historial de Procedimientos - Centro Médico Herrera Saenz";

// Configuración de paginación
$limit = 20; // Registros por página
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page > 1) ? ($page - 1) * $limit : 0;

try {
    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();
    
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
    
} catch (Exception $e) {
    // Manejo de errores
    $procedimientos = [];
    $total_paginas = 1;
    $error_message = "Error al cargar el historial: " . $e->getMessage();
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
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    
    <style>
    /* 
     * Historial de Procedimientos - Centro Médico Herrera Saenz
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
    
    /* ============ TABLA DE HISTORIAL ============ */
    .table-container {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-bottom: 2rem;
        animation: fadeIn 0.6s ease-out 0.2s both;
        overflow: hidden;
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
    
    .table-responsive {
        width: 100%;
        overflow-x: auto;
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
    
    .patient-cell {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .patient-avatar {
        width: 36px;
        height: 36px;
        background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        color: white;
        font-size: 14px;
        flex-shrink: 0;
    }
    
    .patient-info {
        display: flex;
        flex-direction: column;
    }
    
    .patient-name {
        font-weight: 600;
        color: var(--color-text);
    }
    
    .procedure-type {
        font-weight: 500;
        color: var(--color-text);
    }
    
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
        opacity: 0.3;
    }
    
    .time-badge {
        background: var(--color-border-light);
        color: var(--color-text);
        padding: 0.375rem 0.75rem;
        border-radius: var(--radius-md);
        font-size: 0.875rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        border: 1px solid var(--color-border);
    }
    
    /* Separador de jornada */
    .jornada-row {
        background: var(--color-border-light);
    }
    
    .jornada-cell {
        padding: 0.75rem 1rem;
        font-weight: 600;
        color: var(--color-primary);
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid var(--color-primary);
        opacity: 0.8;
    }
    
    .jornada-icon {
        margin-right: 0.5rem;
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
    
    /* ============ PAGINACIÓN ============ */
    .pagination-container {
        display: flex;
        justify-content: center;
        margin-top: 2rem;
    }
    
    .pagination {
        display: flex;
        gap: 0.5rem;
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
        padding: 0 0.75rem;
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        color: var(--color-text);
        font-weight: 500;
        text-decoration: none;
        transition: all var(--transition-normal);
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
    
    /* ============ MODAL DE REPORTES ============ */
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
        opacity: 0.3;
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
        
        .data-table {
            display: block;
            overflow-x: auto;
        }
    }
    
    @media (max-width: 480px) {
        .table-container {
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
                    <h1 class="page-title">Historial de Procedimientos</h1>
                    <p class="page-subtitle">Visualice actividades y genere reportes</p>
                </div>
                <div class="page-actions">
                    <a href="index.php" class="action-btn secondary">
                        <i class="bi bi-arrow-left"></i>
                        <span>Regresar</span>
                    </a>
                    <button type="button" class="action-btn" data-bs-toggle="modal" data-bs-target="#reportModal">
                        <i class="bi bi-file-earmark-pdf-fill"></i>
                        <span>Generar Reporte</span>
                    </button>
                </div>
            </div>
            
            <!-- Mensaje de error -->
            <?php if (!empty($error_message)): ?>
                <div class="table-container">
                    <div class="alert alert-danger border-0" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Tabla de historial -->
            <div class="table-container">
                <div class="table-header">
                    <h3 class="table-title">
                        <i class="bi bi-clock-history table-title-icon"></i>
                        Procedimientos Realizados
                    </h3>
                    <div class="text-muted">
                        Total: <?php echo $total_registros; ?> registros
                    </div>
                </div>
                
                <div class="table-responsive">
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
                        <table class="data-table">
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
                                    $hora = (int)date('H', $timestamp);
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
                                            <span class="procedure-type"><?php echo htmlspecialchars($proc['procedimiento']); ?></span>
                                        </td>
                                        <td>
                                            <span class="amount-badge">
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
                    <?php endif; ?>
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
            </div>
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
                        La jornada comprende desde las <strong>08:00 AM</strong> hasta las <strong>05:00 PM</strong> (jornada diurna) o desde las <strong>05:00 PM</strong> hasta las <strong>08:00 AM</strong> del día siguiente (jornada nocturna).
                    </p>
                    <div class="form-group mb-4">
                        <label class="form-label">Seleccionar Fecha de Jornada</label>
                        <input type="text" class="form-control" id="reportDate" placeholder="Seleccionar fecha...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="action-btn secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="action-btn" id="btnGenerateReport">
                        <i class="bi bi-download"></i>
                        Generar y Descargar
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
    // Historial de Procedimientos - Centro Médico Herrera Saenz
    // JavaScript para funcionalidades del historial
    
    // Esperar a que el DOM esté completamente cargado
    document.addEventListener('DOMContentLoaded', function() {
        // ============ REFERENCIAS A ELEMENTOS ============
        const themeSwitch = document.getElementById('themeSwitch');
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarToggleIcon = document.getElementById('sidebarToggleIcon');
        const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
        const reportDateInput = document.getElementById('reportDate');
        const btnGenerateReport = document.getElementById('btnGenerateReport');
        
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
        
        // ============ FUNCIONALIDAD DEL REPORTE ============
        
        // Inicializar datepicker
        function initializeDatepicker() {
            flatpickr(reportDateInput, {
                locale: "es",
                dateFormat: "Y-m-d",
                defaultDate: "today",
                allowInput: true
            });
        }
        
        // Generar reporte PDF
        function generateReport() {
            const date = reportDateInput.value;
            const btn = btnGenerateReport;
            
            if (!date) {
                Swal.fire('Error', 'Por favor seleccione una fecha', 'warning');
                return;
            }
            
            // Estado de carga
            btn.disabled = true;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generando...';
            
            console.log('Iniciando generacion de reporte para fecha:', date);
            
            fetch(`get_report_data.php?date=${date}`)
                .then(response => {
                    console.log('Respuesta del servidor recibida', response);
                    if (!response.ok) {
                        throw new Error('Network response was not ok ' + response.statusText);
                    }
                    return response.text().then(text => {
                        console.log('Texto crudo de respuesta:', text);
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('Error al parsear JSON:', e);
                            throw new Error('La respuesta del servidor no es un JSON válido: ' + text.substring(0, 100));
                        }
                    });
                })
                .then(res => {
                    console.log('Datos procesados:', res);
                    if (res.status === 'success') {
                        try {
                            generatePDF(res);
                            console.log('PDF generado correctamente');
                            
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
                        } catch (pdfError) {
                            console.error('Error generando PDF:', pdfError);
                            Swal.fire('Error', 'Error al crear el archivo PDF: ' + pdfError.message, 'error');
                        }
                    } else {
                        console.error('Error reportado por el backend:', res);
                        Swal.fire('Error', res.message || 'Error desconocido', 'error');
                    }
                })
                .catch(err => {
                    console.error('Capturado error en fetch:', err);
                    Swal.fire('Error', 'Hubo un problema: ' + err.message, 'error');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
        }
        
        // Función auxiliar para obtener el día siguiente
        function getNextDay(dateString) {
            const date = new Date(dateString);
            date.setDate(date.getDate() + 1);
            return date.toISOString().split('T')[0];
        }
        
        // Generar PDF con jsPDF
        function generatePDF(res) {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            // Colores
            const primaryColor = [124, 144, 219]; // Azul lavanda pastel
            
            // Encabezado
            doc.setFillColor(...primaryColor);
            doc.rect(0, 0, 210, 40, 'F');
            
            doc.setTextColor(255, 255, 255);
            doc.setFontSize(22);
            doc.setFont('helvetica', 'bold');
            doc.text("Centro Médico Herrera Saenz", 105, 18, { align: 'center' });
            
            doc.setFontSize(14);
            doc.setFont('helvetica', 'normal');
            doc.text("Reporte de Procedimientos Menores", 105, 28, { align: 'center' });
            
            // Información del reporte
            doc.setTextColor(50, 50, 50);
            doc.setFontSize(10);
            doc.setFont('helvetica', 'bold');
            doc.text("Información del Reporte:", 14, 50);
            
            doc.setFont('helvetica', 'normal');
            doc.text(`Jornada Reportada: ${res.metadata.jornada_start} - ${res.metadata.jornada_end}`, 14, 56);
            doc.text(`Generado por: ${res.metadata.generated_by}`, 14, 62);
            
            // Tabla de datos
            const tableBody = res.data.map(item => [
                item.nombre_paciente,
                item.procedimiento,
                new Date(item.fecha_procedimiento).toLocaleString('es-GT', {
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
                head: [['Paciente', 'Procedimiento', 'Fecha y Hora', 'Cobro']],
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
            const fileName = `Reporte_Procedimientos_${reportDateInput.value}.pdf`;
            doc.save(fileName);
        }
        
        // ============ INICIALIZACIÓN ============
        
        // Inicializar componentes
        initializeTheme();
        initializeSidebar();
        initializeDatepicker();
        
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
        
        // Generar reporte
        if (btnGenerateReport) {
            btnGenerateReport.addEventListener('click', generateReport);
        }
        
        // Cerrar sidebar al cambiar tamaño de ventana (responsive)
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992 && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
        });
        
        // ============ CONSOLA DE DESARROLLO ============
        
        console.log('Historial de Procedimientos - Centro Médico Herrera Saenz');
        console.log('Versión: 3.0 - Diseño con Efecto Mármol y Modo Noche');
        console.log('Usuario: <?php echo htmlspecialchars($_SESSION['nombre']); ?>');
        console.log('Rol: <?php echo htmlspecialchars($_SESSION['tipoUsuario']); ?>');
    });
    </script>
</body>
</html>