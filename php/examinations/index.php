<?php
// index.php - Registro de Exámenes - Centro Médico Herrera Saenz
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
$page_title = "Registro de Exámenes - Centro Médico Herrera Saenz";

try {
    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();
    
    // Obtener pacientes para el selector
    $stmt_patients = $conn->prepare("SELECT id_paciente, CONCAT(nombre, ' ', apellido) as nombre_completo FROM pacientes ORDER BY nombre_completo ASC");
    $stmt_patients->execute();
    $patients = $stmt_patients->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    // Manejo de errores
    $patients = [];
    $error_message = "Error al cargar pacientes: " . $e->getMessage();
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
    
    <!-- Choices.js CSS para select mejorado -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">
    
    <style>
    /* 
     * Registro de Exámenes - Centro Médico Herrera Saenz
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
    
    .action-btn.info {
        background: var(--color-info);
    }
    
    .action-btn.info:hover {
        background: var(--color-info);
        opacity: 0.8;
    }
    
    /* ============ FORMULARIO DE REGISTRO ============ */
    .form-container {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 2rem;
        margin-bottom: 2rem;
        animation: fadeIn 0.6s ease-out 0.2s both;
        box-shadow: var(--shadow-md);
    }
    
    .form-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
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
    
    /* Secciones del formulario */
    .form-section {
        margin-bottom: 2.5rem;
    }
    
    .section-header {
        display: flex;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    
    .section-number {
        width: 28px;
        height: 28px;
        background: var(--color-primary);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.875rem;
        margin-right: 1rem;
        flex-shrink: 0;
    }
    
    .section-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--color-text);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    /* Contenedor de campos */
    .field-container {
        background: var(--color-border-light);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 1.5rem;
        transition: all var(--transition-normal);
    }
    
    .field-container:hover {
        border-color: var(--color-primary-light);
    }
    
    /* Estilos para Choices.js */
    .choices {
        margin-bottom: 0;
    }
    
    .choices__inner {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 0.75rem 1rem;
        min-height: 48px;
        transition: all var(--transition-normal);
    }
    
    .choices__inner:hover {
        border-color: var(--color-primary-light);
    }
    
    .choices__list--dropdown {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-lg);
        z-index: 1000;
    }
    
    .choices__list--dropdown .choices__item--selectable {
        padding: 0.75rem 1rem;
    }
    
    .choices__list--dropdown .choices__item--selectable:hover {
        background: var(--color-border-light);
    }
    
    /* Checkboxes personalizados */
    .checkbox-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 0.75rem;
    }
    
    .custom-checkbox {
        display: flex;
        align-items: center;
        padding: 0.75rem 1rem;
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        transition: all var(--transition-normal);
        cursor: pointer;
    }
    
    .custom-checkbox:hover {
        background: var(--color-border-light);
        border-color: var(--color-primary-light);
        transform: translateY(-2px);
    }
    
    .custom-checkbox input[type="checkbox"] {
        margin-right: 0.75rem;
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    
    .custom-checkbox label {
        cursor: pointer;
        font-weight: 500;
        color: var(--color-text);
        flex-grow: 1;
    }
    
    /* Campos de entrada personalizados */
    .input-group-custom {
        display: flex;
        align-items: center;
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        overflow: hidden;
        transition: all var(--transition-normal);
    }
    
    .input-group-custom:hover {
        border-color: var(--color-primary-light);
    }
    
    .input-group-custom .input-group-text {
        background: var(--color-border-light);
        border: none;
        color: var(--color-text);
        padding: 0.75rem 1rem;
        font-weight: 500;
    }
    
    .input-group-custom .form-control {
        border: none;
        background: transparent;
        color: var(--color-text);
        padding: 0.75rem 1rem;
        flex-grow: 1;
    }
    
    .input-group-custom .form-control:focus {
        outline: none;
        box-shadow: none;
    }
    
    .input-group-custom .btn-remove {
        background: transparent;
        border: none;
        color: var(--color-error);
        padding: 0.75rem 1rem;
        cursor: pointer;
        transition: all var(--transition-normal);
    }
    
    .input-group-custom .btn-remove:hover {
        background: var(--color-error);
        color: white;
    }
    
    /* Campo de costo */
    .cost-field {
        max-width: 300px;
    }
    
    .cost-input {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 1rem 1.5rem;
        display: flex;
        align-items: center;
        transition: all var(--transition-normal);
    }
    
    .cost-input:hover {
        border-color: var(--color-primary-light);
    }
    
    .cost-input .currency {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--color-success);
        margin-right: 0.75rem;
    }
    
    .cost-input input {
        border: none;
        background: transparent;
        color: var(--color-text);
        font-size: 1.5rem;
        font-weight: 700;
        flex-grow: 1;
        width: 100%;
    }
    
    .cost-input input:focus {
        outline: none;
    }
    
    /* Botón de envío */
    .submit-btn {
        background: var(--color-primary);
        color: white;
        border: none;
        border-radius: var(--radius-md);
        padding: 1rem 2rem;
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        transition: all var(--transition-normal);
        width: 100%;
        margin-top: 2rem;
    }
    
    .submit-btn:hover {
        background: var(--color-primary-dark);
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    /* Alertas */
    .alert {
        padding: 1rem 1.5rem;
        border-radius: var(--radius-md);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        animation: slideInRight 0.4s ease-out;
    }
    
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    .alert-success {
        background: var(--color-success);
        opacity: 0.1;
        color: var(--color-text);
        border-left: 4px solid var(--color-success);
    }
    
    .alert-error {
        background: var(--color-error);
        opacity: 0.1;
        color: var(--color-text);
        border-left: 4px solid var(--color-error);
    }
    
    .alert-icon {
        font-size: 1.25rem;
        flex-shrink: 0;
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
        
        .checkbox-grid {
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
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
        
        .checkbox-grid {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 480px) {
        .form-container {
            padding: 1.25rem;
        }
        
        .action-btn {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }
        
        .cost-input {
            padding: 0.75rem 1rem;
        }
        
        .cost-input input {
            font-size: 1.25rem;
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
                    <a href="../minor_procedures/index.php" class="nav-link">
                        <i class="bi bi-bandaid nav-icon"></i>
                        <span class="nav-text">Proc. Menores</span>
                    </a>
                </li>
                
                <!-- Exámenes -->
                <li class="nav-item">
                    <a href="../examinations/index.php" class="nav-link active">
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
                    <h1 class="page-title">Exámenes Clínicos</h1>
                    <p class="page-subtitle">Registre nuevos exámenes realizados</p>
                </div>
                <div class="page-actions">
                    <?php if ($role === 'admin'): ?>
                    <a href="historial_examenes.php" class="action-btn info">
                        <i class="bi bi-clock-history"></i>
                        <span>Ver Historial</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Alertas de estado -->
            <?php if (isset($_GET['status'])): ?>
                <?php if ($_GET['status'] == 'success'): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill alert-icon text-success"></i>
                        <div>
                            <h6 class="fw-bold mb-0">¡Registro Exitoso!</h6>
                            <p class="mb-0 small"><?php echo htmlspecialchars($_GET['message'] ?? 'Examen registrado correctamente'); ?></p>
                        </div>
                    </div>
                <?php elseif ($_GET['status'] == 'error'): ?>
                    <div class="alert alert-error">
                        <i class="bi bi-exclamation-triangle-fill alert-icon text-error"></i>
                        <div>
                            <h6 class="fw-bold mb-0">Error en el Registro</h6>
                            <p class="mb-0 small"><?php echo htmlspecialchars($_GET['message'] ?? 'Ocurrió un error al registrar el examen'); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- Formulario de registro -->
            <div class="form-container">
                <div class="form-header">
                    <h3 class="form-title">
                        <i class="bi bi-pencil-square form-title-icon"></i>
                        Nuevo Registro de Examen
                    </h3>
                </div>
                
                <form action="save_exam.php" method="POST" id="examForm">
                    <!-- Paso 1: Seleccionar paciente -->
                    <div class="form-section">
                        <div class="section-header">
                            <div class="section-number">1</div>
                            <h4 class="section-title">Seleccionar Paciente</h4>
                        </div>
                        <div class="field-container">
                            <select id="id_paciente" name="id_paciente" required>
                                <option value="">Buscar paciente...</option>
                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?php echo $patient['id_paciente']; ?>">
                                        <?php echo htmlspecialchars($patient['nombre_completo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="nombre_paciente" id="nombre_paciente">
                        </div>
                    </div>
                    
                    <!-- Paso 2: Elegir exámenes -->
                    <div class="form-section">
                        <div class="section-header">
                            <div class="section-number">2</div>
                            <h4 class="section-title">Elegir Exámenes</h4>
                        </div>
                        <div class="field-container">
                            <div class="checkbox-grid">
                                <div class="custom-checkbox">
                                    <input type="checkbox" name="examenes[]" value="Electrocardiograma (ECG)" id="ex1">
                                    <label for="ex1">Electrocardiograma (ECG)</label>
                                </div>
                                <div class="custom-checkbox">
                                    <input type="checkbox" name="examenes[]" value="Ultrasonido" id="ex2">
                                    <label for="ex2">Ultrasonido</label>
                                </div>
                                <div class="custom-checkbox">
                                    <input type="checkbox" name="examenes[]" value="Radiografía" id="ex3">
                                    <label for="ex3">Radiografía</label>
                                </div>
                                <div class="custom-checkbox">
                                    <input type="checkbox" name="examenes[]" value="Examen general de orina" id="ex4">
                                    <label for="ex4">Examen general de orina</label>
                                </div>
                                <div class="custom-checkbox">
                                    <input type="checkbox" name="examenes[]" value="Hematología completa" id="ex5">
                                    <label for="ex5">Hematología completa</label>
                                </div>
                                <div class="custom-checkbox">
                                    <input type="checkbox" name="examenes[]" value="Prueba de Papanicolaou" id="ex6">
                                    <label for="ex6">Prueba de Papanicolaou</label>
                                </div>
                            </div>
                            
                            <!-- Exámenes dinámicos -->
                            <div id="dynamicExams" class="mt-3">
                                <!-- Se agregarán exámenes dinámicos aquí -->
                            </div>
                            
                            <!-- Botón para agregar examen personalizado -->
                            <div class="mt-3">
                                <button type="button" class="action-btn secondary" id="btnAddExam">
                                    <i class="bi bi-plus-lg"></i>
                                    <span>Agregar Examen Personalizado</span>
                                </button>
                            </div>
                            
                            <small class="text-muted mt-2 d-block">
                                <i class="bi bi-info-circle"></i>
                                Puede seleccionar varios exámenes o agregar personalizados.
                            </small>
                        </div>
                    </div>
                    
                    <!-- Paso 3: Especificar costo -->
                    <div class="form-section">
                        <div class="section-header">
                            <div class="section-number">3</div>
                            <h4 class="section-title">Finalizar y Cobrar</h4>
                        </div>
                        <div class="field-container cost-field">
                            <div class="cost-input">
                                <span class="currency">Q</span>
                                <input type="number" id="cobro" name="cobro" step="0.01" min="0" required placeholder="0.00">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Botón de envío -->
                    <button type="submit" class="submit-btn">
                        <i class="bi bi-save"></i>
                        <span>Guardar Registro</span>
                    </button>
                </form>
            </div>
        </main>
    </div>
    
    <!-- Datos para lista de exámenes -->
    <datalist id="examsList">
        <option value="Perfil lipídico">
        <option value="Glucosa en ayunas">
        <option value="Prueba de embarazo">
        <option value="Antígeno prostático">
        <option value="TSH (Tiroides)">
        <option value="Creatinina">
        <option value="Ácido úrico">
        <option value="Hemoglobina glicosilada">
        <option value="Prueba de función hepática">
        <option value="Cultivo de orina">
    </datalist>
    
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
    
    <script>
    // Registro de Exámenes - Centro Médico Herrera Saenz
    // JavaScript para funcionalidades del formulario
    
    // Esperar a que el DOM esté completamente cargado
    document.addEventListener('DOMContentLoaded', function() {
        // ============ REFERENCIAS A ELEMENTOS ============
        const themeSwitch = document.getElementById('themeSwitch');
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarToggleIcon = document.getElementById('sidebarToggleIcon');
        const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
        const pacienteSelect = document.getElementById('id_paciente');
        const nombrePacienteInput = document.getElementById('nombre_paciente');
        const dynamicExamsContainer = document.getElementById('dynamicExams');
        const btnAddExam = document.getElementById('btnAddExam');
        const examForm = document.getElementById('examForm');
        
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
        
        // Inicializar Choices.js para el selector de pacientes
        function initializePatientSelector() {
            const choices = new Choices(pacienteSelect, {
                searchEnabled: true,
                itemSelectText: '',
                removeItemButton: true,
                placeholder: true,
                placeholderValue: 'Buscar paciente...',
                noResultsText: 'No se encontraron resultados',
                shouldSort: false,
            });
            
            // Actualizar campo oculto cuando se selecciona un paciente
            pacienteSelect.addEventListener('addItem', function(event) {
                nombrePacienteInput.value = event.detail.label;
            });
            
            pacienteSelect.addEventListener('removeItem', function() {
                nombrePacienteInput.value = '';
            });
        }
        
        // Agregar examen personalizado
        function addCustomExam() {
            const examRow = document.createElement('div');
            examRow.className = 'input-group-custom mb-2';
            examRow.innerHTML = `
                <span class="input-group-text">
                    <i class="bi bi-file-earmark-medical"></i>
                </span>
                <input class="form-control" list="examsList" name="examenes[]" placeholder="Especificar otro examen..." required>
                <button type="button" class="btn-remove remove-exam-row">
                    <i class="bi bi-trash"></i>
                </button>
            `;
            
            dynamicExamsContainer.appendChild(examRow);
            
            // Enfocar el nuevo campo
            const input = examRow.querySelector('input');
            input.focus();
            
            // Animación de entrada
            examRow.style.opacity = '0';
            examRow.style.transform = 'translateY(-10px)';
            setTimeout(() => {
                examRow.style.transition = 'all 0.3s ease';
                examRow.style.opacity = '1';
                examRow.style.transform = 'translateY(0)';
            }, 10);
        }
        
        // Eliminar examen personalizado
        function removeCustomExam(event) {
            if (event.target.closest('.remove-exam-row')) {
                const examRow = event.target.closest('.input-group-custom');
                examRow.style.opacity = '0';
                examRow.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    examRow.remove();
                }, 300);
            }
        }
        
        // Validar formulario antes de enviar
        function validateForm(event) {
            // Validar que se haya seleccionado un paciente
            if (!pacienteSelect.value) {
                event.preventDefault();
                showAlert('Por favor seleccione un paciente', 'error');
                return false;
            }
            
            // Validar que se haya seleccionado al menos un examen
            const examenes = document.querySelectorAll('input[name="examenes[]"]:checked');
            const customExams = document.querySelectorAll('input[name="examenes[]"][type="text"]');
            let hasExams = examenes.length > 0;
            
            // Verificar exámenes personalizados
            customExams.forEach(input => {
                if (input.value.trim() !== '') {
                    hasExams = true;
                }
            });
            
            if (!hasExams) {
                event.preventDefault();
                showAlert('Por favor seleccione o agregue al menos un examen', 'error');
                return false;
            }
            
            // Validar que se haya especificado un costo
            const costo = document.getElementById('cobro').value;
            if (!costo || parseFloat(costo) <= 0) {
                event.preventDefault();
                showAlert('Por favor especifique un costo válido', 'error');
                return false;
            }
            
            return true;
        }
        
        // Mostrar alerta temporal
        function showAlert(message, type) {
            // Crear elemento de alerta
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.innerHTML = `
                <i class="bi bi-${type === 'error' ? 'exclamation-triangle-fill' : 'info-circle-fill'} alert-icon text-${type}"></i>
                <div>
                    <p class="mb-0 small">${message}</p>
                </div>
            `;
            
            // Insertar antes del formulario
            const formContainer = document.querySelector('.form-container');
            formContainer.insertBefore(alert, formContainer.firstChild);
            
            // Eliminar alerta después de 5 segundos
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateX(20px)';
                setTimeout(() => {
                    alert.remove();
                }, 300);
            }, 5000);
        }
        
        // ============ INICIALIZACIÓN ============
        
        // Inicializar componentes
        initializeTheme();
        initializeSidebar();
        initializePatientSelector();
        
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
        
        // Agregar examen personalizado
        if (btnAddExam) {
            btnAddExam.addEventListener('click', addCustomExam);
        }
        
        // Eliminar examen personalizado (delegación de eventos)
        if (dynamicExamsContainer) {
            dynamicExamsContainer.addEventListener('click', removeCustomExam);
        }
        
        // Validar formulario al enviar
        if (examForm) {
            examForm.addEventListener('submit', validateForm);
        }
        
        // Cerrar sidebar al cambiar tamaño de ventana (responsive)
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992 && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
        });
        
        // ============ CONSOLA DE DESARROLLO ============
        
        console.log('Registro de Exámenes - Centro Médico Herrera Saenz');
        console.log('Versión: 3.0 - Diseño con Efecto Mármol y Modo Noche');
        console.log('Usuario: <?php echo htmlspecialchars($_SESSION['nombre']); ?>');
        console.log('Rol: <?php echo htmlspecialchars($_SESSION['tipoUsuario']); ?>');
        console.log('Pacientes cargados: <?php echo count($patients); ?>');
    });
    </script>
</body>
</html>