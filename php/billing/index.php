<?php
// index.php - Módulo de Cobros - Centro Médico Herrera Saenz
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
    
    // Obtener todos los pacientes para el dropdown
    $stmt = $conn->prepare("SELECT id_paciente, CONCAT(nombre, ' ', apellido) as nombre_completo FROM pacientes ORDER BY nombre");
    $stmt->execute();
    $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener cobros con paginación
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 25;
    $offset = ($page - 1) * $limit;
    
    // Obtener total para paginación
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM cobros");
    $stmt->execute();
    $total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $limit);
    
    // Obtener datos de cobros con nombre de paciente
    $stmt = $conn->prepare("
        SELECT c.*, CONCAT(p.nombre, ' ', p.apellido) as nombre_paciente 
        FROM cobros c
        JOIN pacientes p ON c.paciente_cobro = p.id_paciente
        ORDER BY c.fecha_consulta DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $cobros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Título de la página
    $page_title = "Cobros - Centro Médico Herrera Saenz";
    
} catch (Exception $e) {
    // Manejo de errores
    error_log("Error en módulo de cobros: " . $e->getMessage());
    die("Error al cargar el módulo de cobros. Por favor, contacte al administrador.");
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
    
    <!-- Incluir header -->
    <?php include_once '../../includes/header.php'; ?>
    
    <style>
    /* 
     * Módulo de Cobros Minimalista - Centro Médico Herrera Saenz
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
    
    /* ============ TABLA DE COBROS ============ */
    .billing-container {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-bottom: 2rem;
        animation: fadeIn 0.6s ease-out 0.3s both;
    }
    
    .billing-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 0.5rem;
    }
    
    .billing-table thead th {
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
    
    .billing-table th:first-child {
        border-radius: var(--radius-md) 0 0 var(--radius-md);
    }
    
    .billing-table th:last-child {
        border-radius: 0 var(--radius-md) var(--radius-md) 0;
    }
    
    .billing-table tbody tr {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        transition: all var(--transition-normal);
    }
    
    .billing-table tbody tr:hover {
        background: var(--color-border-light);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    
    .billing-table td {
        padding: 1rem;
        border-top: 1px solid var(--color-border);
        border-bottom: 1px solid var(--color-border);
        color: var(--color-text);
    }
    
    .billing-table td:first-child {
        border-left: 1px solid var(--color-border);
        border-radius: var(--radius-md) 0 0 var(--radius-md);
    }
    
    .billing-table td:last-child {
        border-right: 1px solid var(--color-border);
        border-radius: 0 var(--radius-md) var(--radius-md) 0;
    }
    
    /* Celdas específicas */
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
    
    .amount-cell {
        font-weight: 700;
        color: var(--color-text);
    }
    
    .date-cell {
        color: var(--color-text-light);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .action-buttons {
        display: flex;
        gap: 0.5rem;
        justify-content: flex-end;
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
        background: var(--color-primary);
        color: white;
        border-color: var(--color-primary);
        transform: translateY(-2px);
    }
    
    .btn-icon.view:hover {
        background: var(--color-info);
        border-color: var(--color-info);
    }
    
    .btn-icon.print:hover {
        background: var(--color-success);
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
    
    /* ============ PAGINACIÓN ============ */
    .pagination {
        display: flex;
        list-style: none;
        padding: 0;
        margin: 2rem 0 0 0;
        justify-content: center;
        gap: 0.5rem;
    }
    
    .page-item {
        margin: 0;
    }
    
    .page-link {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        padding: 0 0.75rem;
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        color: var(--color-text);
        text-decoration: none;
        font-weight: 500;
        font-size: 0.875rem;
        transition: all var(--transition-normal);
    }
    
    .page-link:hover {
        background: var(--color-border-light);
        border-color: var(--color-primary-light);
    }
    
    .page-item.active .page-link {
        background: var(--color-primary);
        color: white;
        border-color: var(--color-primary);
    }
    
    .page-item.disabled .page-link {
        opacity: 0.5;
        cursor: not-allowed;
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
        box-shadow: 0 0 0 3px rgba(124, 144, 219, 0.25);
    }
    
    .search-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: var(--shadow-lg);
        margin-top: 0.25rem;
        display: none;
    }
    
    .search-results.active {
        display: block;
    }
    
    .result-item {
        padding: 0.75rem 1rem;
        cursor: pointer;
        transition: background-color var(--transition-fast);
        border-bottom: 1px solid var(--color-border);
    }
    
    .result-item:last-child {
        border-bottom: none;
    }
    
    .result-item:hover {
        background: var(--color-border-light);
    }
    
    .selected-patient {
        background: rgba(124, 144, 219, 0.1);
        border-radius: var(--radius-md);
        padding: 1rem;
        margin-top: 1rem;
        border: 1px solid var(--color-primary);
    }
    
    .modal-footer {
        border-top: 1px solid var(--color-border);
        padding: 1.5rem;
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
    }
    
    /* ============ DETALLES DEL COBRO ============ */
    .billing-details {
        background: var(--color-border-light);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    
    .detail-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--color-border);
    }
    
    .detail-row:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }
    
    .detail-label {
        color: var(--color-text-light);
        font-weight: 500;
        font-size: 0.875rem;
    }
    
    .detail-value {
        color: var(--color-text);
        font-weight: 600;
        font-size: 1rem;
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
        
        .billing-container {
            padding: 1rem;
        }
        
        .billing-table {
            display: block;
            overflow-x: auto;
        }
        
        .billing-table th,
        .billing-table td {
            white-space: nowrap;
        }
        
        .action-buttons {
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .btn-icon {
            width: 32px;
            height: 32px;
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
        
        .modal-dialog {
            margin: 0.5rem;
        }
        
        .modal-body {
            padding: 1rem;
        }
        
        .pagination {
            flex-wrap: wrap;
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
                    <a href="../billing/index.php" class="nav-link active">
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
            <!-- Cabecera de página -->
            <div class="page-header">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h1 class="page-title">
                            <i class="bi bi-credit-card-2-front text-primary"></i>
                            Gestión de Cobros
                        </h1>
                        <p class="page-subtitle">Administración de recaudación y recibos médicos</p>
                    </div>
                    <div class="page-actions">
                        <button type="button" class="action-btn" data-bs-toggle="modal" data-bs-target="#newBillingModal">
                            <i class="bi bi-plus-lg"></i>
                            Nuevo Cobro
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Contenedor de tabla de cobros -->
            <div class="billing-container">
                <?php if (count($cobros) > 0): ?>
                    <div class="table-responsive">
                        <table class="billing-table">
                            <thead>
                                <tr>
                                    <th>Paciente</th>
                                    <th>Monto</th>
                                    <th>Fecha</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cobros as $cobro): ?>
                                    <tr>
                                        <td>
                                            <div class="patient-cell">
                                                <div class="patient-avatar">
                                                    <?php 
                                                    $nombres = explode(' ', $cobro['nombre_paciente']);
                                                    $iniciales = '';
                                                    foreach ($nombres as $nombre) {
                                                        $iniciales .= strtoupper(substr($nombre, 0, 1));
                                                        if (strlen($iniciales) >= 2) break;
                                                    }
                                                    echo $iniciales;
                                                    ?>
                                                </div>
                                                <div class="patient-info">
                                                    <div class="patient-name"><?php echo htmlspecialchars($cobro['nombre_paciente']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="amount-cell">Q<?php echo number_format($cobro['cantidad_consulta'], 2); ?></span>
                                        </td>
                                        <td>
                                            <div class="date-cell">
                                                <i class="bi bi-calendar-event"></i>
                                                <?php echo date('d/m/Y', strtotime($cobro['fecha_consulta'])); ?>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <div class="action-buttons">
                                                <button type="button" class="btn-icon view view-details" 
                                                        data-bs-toggle="modal" data-bs-target="#viewDetailsModal"
                                                        data-id="<?php echo $cobro['in_cobro']; ?>"
                                                        title="Ver detalles">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <a href="print_receipt.php?id=<?php echo $cobro['in_cobro']; ?>" 
                                                   target="_blank" class="btn-icon print" title="Imprimir recibo">
                                                    <i class="bi bi-printer"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Paginación -->
                    <?php if ($total_pages > 1): ?>
                    <nav>
                        <ul class="pagination">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php 
                            // Paginación inteligente - muestra máximo 5 números de página
                            $range = 2;
                            $start = max(1, $page - $range);
                            $end = min($total_pages, $page + $range);
                            
                            // Mostrar primera página
                            if ($start > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                                if ($start > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            
                            // Mostrar rango de páginas
                            for ($i = $start; $i <= $end; $i++): 
                            ?>
                                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php 
                            endfor;
                            
                            // Mostrar última página
                            if ($end < $total_pages) {
                                if ($end < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '">' . $total_pages . '</a></li>';
                            }
                            ?>
                            
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                        <h4 class="text-muted mb-2">No hay cobros registrados</h4>
                        <p class="text-muted mb-3">Comience registrando un nuevo cobro</p>
                        <button type="button" class="action-btn" data-bs-toggle="modal" data-bs-target="#newBillingModal">
                            <i class="bi bi-plus-lg"></i>
                            Registrar primer cobro
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Modal para nuevo cobro -->
    <div class="modal fade" id="newBillingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle text-primary"></i>
                        Nuevo Cobro
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="newBillingForm">
                        <div class="form-group">
                            <label class="form-label">Paciente</label>
                            <div class="position-relative">
                                <input type="text" 
                                       class="form-control" 
                                       id="paciente_search" 
                                       placeholder="Buscar paciente..." 
                                       autocomplete="off">
                                <div id="pacienteResults" class="search-results"></div>
                            </div>
                            <input type="hidden" id="paciente" name="paciente" required>
                            <div id="selectedPatient" class="selected-patient d-none">
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="font-weight-bold text-primary" id="patientName"></span>
                                    <button type="button" class="btn btn-sm btn-link text-danger p-0" id="clearPatient">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Cantidad a Cobrar (Q)</label>
                            <div class="input-group">
                                <span class="input-group-text">Q</span>
                                <input type="number" 
                                       class="form-control" 
                                       id="cantidad" 
                                       name="cantidad" 
                                       min="0.01" 
                                       step="0.01" 
                                       placeholder="0.00" 
                                       required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Fecha de Consulta</label>
                            <input type="date" 
                                   class="form-control" 
                                   id="fecha_consulta" 
                                   name="fecha_consulta" 
                                   value="<?php echo date('Y-m-d'); ?>" 
                                   required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="action-btn secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="action-btn" id="saveBillingBtn">Guardar Cobro</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para ver detalles -->
    <div class="modal fade" id="viewDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-receipt text-primary"></i>
                        Detalles del Cobro
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="billing-details">
                        <div class="detail-row">
                            <span class="detail-label">Paciente</span>
                            <span class="detail-value" id="modal-paciente"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Monto</span>
                            <span class="detail-value" id="modal-cantidad"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Fecha de Consulta</span>
                            <span class="detail-value" id="modal-fecha"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">ID de Cobro</span>
                            <span class="detail-value" id="modal-id"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="action-btn secondary" data-bs-dismiss="modal">Cerrar</button>
                    <a href="#" class="action-btn" id="modal-print-btn" target="_blank">
                        <i class="bi bi-printer me-2"></i>
                        Imprimir Recibo
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
    // Módulo de Cobros - Centro Médico Herrera Saenz
    // JavaScript para funcionalidades del módulo de cobros
    
    // Esperar a que el DOM esté completamente cargado
    document.addEventListener('DOMContentLoaded', function() {
        // ============ REFERENCIAS A ELEMENTOS ============
        const themeSwitch = document.getElementById('themeSwitch');
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarToggleIcon = document.getElementById('sidebarToggleIcon');
        const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
        
        // Elementos del formulario de búsqueda de pacientes
        const pacienteSearch = document.getElementById('paciente_search');
        const pacienteResults = document.getElementById('pacienteResults');
        const pacienteInput = document.getElementById('paciente');
        const selectedPatient = document.getElementById('selectedPatient');
        const patientName = document.getElementById('patientName');
        const clearPatient = document.getElementById('clearPatient');
        
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
        
        // ============ BÚSQUEDA DE PACIENTES ============
        
        // Buscar pacientes mientras se escribe
        function handlePatientSearch() {
            const searchTerm = pacienteSearch.value.trim();
            
            if (searchTerm.length < 2) {
                pacienteResults.innerHTML = '';
                pacienteResults.classList.remove('active');
                return;
            }
            
            fetch(`search_patients.php?term=${encodeURIComponent(searchTerm)}`)
                .then(response => {
                    if (!response.ok) throw new Error('Error en la respuesta');
                    return response.json();
                })
                .then(data => {
                    pacienteResults.innerHTML = '';
                    
                    if (data.length === 0) {
                        const noResults = document.createElement('div');
                        noResults.className = 'result-item';
                        noResults.textContent = 'No se encontraron pacientes';
                        pacienteResults.appendChild(noResults);
                    } else {
                        data.forEach(patient => {
                            const item = document.createElement('div');
                            item.className = 'result-item';
                            item.innerHTML = `
                                <i class="bi bi-person me-2"></i> ${patient.nombre_completo}
                            `;
                            item.addEventListener('click', () => selectPatient(patient.id_paciente, patient.nombre_completo));
                            pacienteResults.appendChild(item);
                        });
                    }
                    
                    pacienteResults.classList.add('active');
                })
                .catch(error => {
                    console.error('Error en la búsqueda:', error);
                    pacienteResults.innerHTML = '<div class="result-item text-danger">Error en la búsqueda</div>';
                    pacienteResults.classList.add('active');
                });
        }
        
        // Seleccionar paciente de los resultados
        function selectPatient(id, name) {
            pacienteInput.value = id;
            patientName.textContent = name;
            selectedPatient.classList.remove('d-none');
            pacienteSearch.value = '';
            pacienteResults.innerHTML = '';
            pacienteResults.classList.remove('active');
        }
        
        // Limpiar paciente seleccionado
        function clearSelectedPatient() {
            pacienteInput.value = '';
            patientName.textContent = '';
            selectedPatient.classList.add('d-none');
            pacienteSearch.focus();
        }
        
        // ============ GUARDAR COBRO ============
        
        // Guardar nuevo cobro
        function saveBilling() {
            const form = document.getElementById('newBillingForm');
            
            // Validar formulario
            if (!pacienteInput.value) {
                alert('Por favor seleccione un paciente');
                return;
            }
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            const data = {
                paciente: pacienteInput.value,
                cantidad: document.getElementById('cantidad').value,
                fecha_consulta: document.getElementById('fecha_consulta').value
            };
            
            const saveBtn = document.getElementById('saveBillingBtn');
            const originalText = saveBtn.innerHTML;
            
            // Mostrar estado de carga
            saveBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Procesando...';
            saveBtn.disabled = true;
            
            fetch('save_billing.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Mostrar notificación
                    showNotification('Cobro guardado correctamente', 'success');
                    
                    // Cerrar modal y recargar página después de un breve retraso
                    setTimeout(() => {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('newBillingModal'));
                        modal.hide();
                        
                        // Resetear formulario
                        form.reset();
                        clearSelectedPatient();
                        
                        // Recargar página para mostrar el nuevo cobro
                        window.location.reload();
                    }, 1500);
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error al guardar el cobro', 'error');
            })
            .finally(() => {
                // Restaurar botón
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
            });
        }
        
        // ============ DETALLES DEL COBRO ============
        
        // Cargar detalles del cobro para el modal
        function loadBillingDetails(id) {
            fetch(`get_billing_details.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        document.getElementById('modal-paciente').textContent = data.cobro.nombre_paciente;
                        document.getElementById('modal-cantidad').textContent = 'Q' + parseFloat(data.cobro.cantidad_consulta).toFixed(2);
                        document.getElementById('modal-fecha').textContent = data.cobro.fecha_formateada;
                        document.getElementById('modal-id').textContent = '#REC-' + String(data.cobro.in_cobro).padStart(5, '0');
                        
                        // Actualizar enlace de impresión
                        const printBtn = document.getElementById('modal-print-btn');
                        printBtn.href = `print_receipt.php?id=${id}`;
                    } else {
                        showNotification('Error al cargar los detalles', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error al cargar los detalles', 'error');
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
            const cards = document.querySelectorAll('.billing-container, .page-header');
            
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
        
        // Búsqueda de pacientes
        if (pacienteSearch) {
            pacienteSearch.addEventListener('input', handlePatientSearch);
            
            // Cerrar resultados al hacer clic fuera
            document.addEventListener('click', function(e) {
                if (!pacienteSearch.contains(e.target) && !pacienteResults.contains(e.target)) {
                    pacienteResults.classList.remove('active');
                }
            });
        }
        
        // Limpiar paciente seleccionado
        if (clearPatient) {
            clearPatient.addEventListener('click', clearSelectedPatient);
        }
        
        // Guardar cobro
        if (document.getElementById('saveBillingBtn')) {
            document.getElementById('saveBillingBtn').addEventListener('click', saveBilling);
        }
        
        // Modal de detalles
        document.querySelectorAll('.view-details').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                loadBillingDetails(id);
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
        
        console.log('Módulo de Cobros - Centro Médico Herrera Saenz');
        console.log('Versión: 3.0 - Diseño Minimalista con Efecto Mármol');
        console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
        console.log('Rol: <?php echo htmlspecialchars($user_type); ?>');
    });
    </script>
    
    <!-- Incluir footer -->
    <?php include_once '../../includes/footer.php'; ?>
</body>
</html>