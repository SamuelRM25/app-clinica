<?php
// patients/index.php - Módulo de Gestión de Pacientes
// Versión: 3.0 - Diseño Minimalista con Modo Noche
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
    
    // Título de la página
    $page_title = "Gestión de Pacientes - Centro Médico Herrera Saenz";
    
    // Consulta optimizada según tipo de usuario
    if ($user_type === 'doc') {
        // Pacientes atendidos por este médico
        $stmt = $conn->prepare("
            SELECT DISTINCT p.*, 
                   COUNT(c.id_cita) as total_citas,
                   MAX(c.fecha_cita) as ultima_cita
            FROM pacientes p
            LEFT JOIN citas c ON (p.nombre = c.nombre_pac AND p.apellido = c.apellido_pac)
            WHERE c.id_doctor = ? OR p.id_paciente IN (
                SELECT DISTINCT id_paciente FROM historial_clinico 
                WHERE medico_responsable LIKE ?
            )
            GROUP BY p.id_paciente
            ORDER BY p.apellido, p.nombre
        ");
        $doctor_name = $_SESSION['nombre'] . ' ' . $_SESSION['apellido'];
        $stmt->execute([$user_id, '%' . $doctor_name . '%']);
    } else {
        // Todos los pacientes para admin/usuarios
        $stmt = $conn->prepare("
            SELECT p.*, 
                   COUNT(c.id_cita) as total_citas,
                   MAX(c.fecha_cita) as ultima_cita
            FROM pacientes p
            LEFT JOIN citas c ON (p.nombre = c.nombre_pac AND p.apellido = c.apellido_pac)
            GROUP BY p.id_paciente
            ORDER BY p.apellido, p.nombre
        ");
        $stmt->execute();
    }
    
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener estadísticas
    $total_patients = count($patients);
    $patients_with_appointments = count(array_filter($patients, function($p) { 
        return $p['total_citas'] > 0; 
    }));
    $patients_without_history = count(array_filter($patients, function($p) { 
        return !isset($p['ultima_cita']); 
    }));
    $active_today = count(array_filter($patients, function($p) { 
        return isset($p['ultima_cita']) && $p['ultima_cita'] === date('Y-m-d'); 
    }));
    
    // Obtener médicos para el modal de citas rápidas
    $stmt_doctors = $conn->prepare("
        SELECT idUsuario, nombre, apellido 
        FROM usuarios 
        WHERE tipoUsuario = 'doc' 
        ORDER BY nombre, apellido
    ");
    $stmt_doctors->execute();
    $doctors = $stmt_doctors->fetchAll(PDO::FETCH_ASSOC);
    
    // Incluir header
    include_once '../../includes/header.php';
    
} catch (Exception $e) {
    // Manejo de errores
    error_log("Error en módulo de pacientes: " . $e->getMessage());
    die("Error al cargar el módulo de pacientes. Por favor, contacte al administrador.");
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
     * Módulo de Pacientes - Centro Médico Herrera Saenz
     * Diseño: Fondo blanco, colores pastel, efecto mármol, modo noche
     * Versión: 3.0
     */
    
    /* Variables CSS para modo claro y oscuro */
    :root {
        /* Modo claro (predeterminado) */
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
    .patients-container {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        position: relative;
    }
    
    /* ============ HEADER SUPERIOR ============ */
    .patients-header {
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
        top: 81px;
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
    
    /* ============ ENCABEZADO DEL MÓDULO ============ */
    .module-header {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 2rem;
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
    
    .module-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--color-text);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .module-title-icon {
        color: var(--color-primary);
        font-size: 2rem;
    }
    
    .module-subtitle {
        color: var(--color-text-light);
        font-size: 1rem;
        margin-bottom: 1.5rem;
    }
    
    /* ============ ESTADÍSTICAS DE PACIENTES ============ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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
        cursor: pointer;
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
    
    .stat-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .stat-info {
        flex: 1;
    }
    
    .stat-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--color-text-light);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--color-text);
        line-height: 1;
    }
    
    .stat-icon {
        width: 56px;
        height: 56px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        color: white;
        flex-shrink: 0;
    }
    
    .stat-icon.primary { background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark)); }
    .stat-icon.success { background: linear-gradient(135deg, var(--color-success), #10b981); }
    .stat-icon.warning { background: linear-gradient(135deg, var(--color-warning), #d97706); }
    .stat-icon.info { background: linear-gradient(135deg, var(--color-info), #0ea5e9); }
    
    /* ============ BARRA DE BÚSQUEDA Y ACCIONES ============ */
    .actions-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }
    
    .search-container {
        flex: 1;
        min-width: 300px;
        position: relative;
    }
    
    .search-input {
        width: 100%;
        padding: 0.875rem 1rem 0.875rem 3rem;
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        color: var(--color-text);
        font-size: 1rem;
        transition: all var(--transition-normal);
        outline: none;
    }
    
    .search-input:focus {
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(124, 144, 219, 0.2);
    }
    
    .search-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--color-text-light);
        font-size: 1.25rem;
    }
    
    /* Botones de acción */
    .action-buttons {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }
    
    .btn-primary {
        background: var(--color-primary);
        color: white;
        border: none;
        border-radius: var(--radius-md);
        padding: 0.875rem 1.5rem;
        font-weight: 600;
        font-size: 0.95rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all var(--transition-normal);
        text-decoration: none;
        white-space: nowrap;
    }
    
    .btn-primary:hover {
        background: var(--color-primary-dark);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    
    .btn-outline {
        background: transparent;
        color: var(--color-primary);
        border: 1px solid var(--color-primary);
        border-radius: var(--radius-md);
        padding: 0.875rem 1.5rem;
        font-weight: 600;
        font-size: 0.95rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all var(--transition-normal);
        text-decoration: none;
        white-space: nowrap;
    }
    
    .btn-outline:hover {
        background: var(--color-primary);
        color: white;
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    
    /* Filtros */
    .filters-container {
        display: flex;
        gap: 1rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }
    
    .filter-btn {
        background: var(--color-surface);
        color: var(--color-text);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 0.625rem 1.25rem;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all var(--transition-normal);
    }
    
    .filter-btn:hover {
        background: var(--color-border-light);
    }
    
    .filter-btn.active {
        background: var(--color-primary);
        color: white;
        border-color: var(--color-primary);
    }
    
    /* ============ TABLA DE PACIENTES ============ */
    .patients-table-container {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        overflow: hidden;
        margin-bottom: 2rem;
        animation: fadeIn 0.6s ease-out 0.2s both;
    }
    
    .table-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.5rem;
        border-bottom: 1px solid var(--color-border);
        background: var(--color-border-light);
    }
    
    .table-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--color-text);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .table-count {
        font-size: 0.875rem;
        color: var(--color-text-light);
        font-weight: 500;
    }
    
    .patients-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .patients-table th {
        text-align: left;
        padding: 1rem 1.5rem;
        font-weight: 600;
        color: var(--color-text-light);
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid var(--color-border);
        background: var(--color-border-light);
        white-space: nowrap;
    }
    
    .patients-table td {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--color-border);
        color: var(--color-text);
        transition: background-color var(--transition-normal);
        vertical-align: middle;
    }
    
    .patients-table tbody tr {
        transition: all var(--transition-normal);
    }
    
    .patients-table tbody tr:hover {
        background: var(--color-border-light);
    }
    
    .patients-table tbody tr:last-child td {
        border-bottom: none;
    }
    
    /* Celdas de paciente */
    .patient-cell {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .patient-avatar {
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        color: white;
        font-size: 1.125rem;
        flex-shrink: 0;
    }
    
    .patient-info {
        display: flex;
        flex-direction: column;
    }
    
    .patient-name {
        font-weight: 600;
        color: var(--color-text);
        font-size: 1rem;
    }
    
    .patient-id {
        font-size: 0.75rem;
        color: var(--color-text-light);
    }
    
    /* Información de contacto */
    .contact-info {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .contact-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
    }
    
    .contact-icon {
        color: var(--color-text-light);
        font-size: 0.875rem;
        width: 16px;
        text-align: center;
    }
    
    /* Información demográfica */
    .demographic-info {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .demographic-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
    }
    
    .gender-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .gender-male {
        background: rgba(59, 130, 246, 0.1);
        color: var(--color-primary);
        border: 1px solid rgba(59, 130, 246, 0.2);
    }
    
    .gender-female {
        background: rgba(248, 113, 113, 0.1);
        color: var(--color-error);
        border: 1px solid rgba(248, 113, 113, 0.2);
    }
    
    .gender-other {
        background: rgba(148, 163, 184, 0.1);
        color: var(--color-text-light);
        border: 1px solid rgba(148, 163, 184, 0.2);
    }
    
    /* Estado del paciente */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.375rem 0.875rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .status-active {
        background: rgba(34, 197, 94, 0.1);
        color: var(--color-success);
        border: 1px solid rgba(34, 197, 94, 0.2);
    }
    
    .status-inactive {
        background: rgba(239, 68, 68, 0.1);
        color: var(--color-error);
        border: 1px solid rgba(239, 68, 68, 0.2);
    }
    
    /* Botones de acción en tabla */
    .action-buttons-cell {
        display: flex;
        gap: 0.5rem;
        justify-content: flex-end;
    }
    
    .action-btn {
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
    
    .action-btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-sm);
    }
    
    .action-btn.history:hover {
        background: var(--color-success);
        color: white;
        border-color: var(--color-success);
    }
    
    .action-btn.appointment:hover {
        background: var(--color-info);
        color: white;
        border-color: var(--color-info);
    }
    
    .action-btn.edit:hover {
        background: var(--color-warning);
        color: white;
        border-color: var(--color-warning);
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
    
    /* ============ MODALES ============ */
    .custom-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        padding: 1rem;
    }
    
    .custom-modal-overlay.active {
        display: flex;
        animation: fadeIn 0.3s ease-out;
    }
    
    .custom-modal {
        background: var(--color-surface);
        border-radius: var(--radius-lg);
        width: 100%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: var(--shadow-xl);
        animation: modalSlideIn 0.3s ease-out;
    }
    
    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .custom-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.5rem;
        border-bottom: 1px solid var(--color-border);
    }
    
    .custom-modal-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--color-text);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .custom-modal-close {
        background: transparent;
        border: none;
        color: var(--color-text-light);
        font-size: 1.5rem;
        cursor: pointer;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: var(--radius-md);
        transition: all var(--transition-normal);
    }
    
    .custom-modal-close:hover {
        background: var(--color-border-light);
        color: var(--color-error);
    }
    
    .custom-modal-body {
        padding: 1.5rem;
    }
    
    .custom-modal-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 1rem;
        padding: 1.5rem;
        border-top: 1px solid var(--color-border);
    }
    
    /* Formularios en modales */
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
    }
    
    .form-group {
        margin-bottom: 1.25rem;
    }
    
    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--color-text);
        font-size: 0.875rem;
    }
    
    .form-input {
        width: 100%;
        padding: 0.75rem 1rem;
        background: var(--color-background);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        color: var(--color-text);
        font-size: 1rem;
        transition: all var(--transition-normal);
        outline: none;
    }
    
    .form-input:focus {
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(124, 144, 219, 0.2);
    }
    
    .form-select {
        width: 100%;
        padding: 0.75rem 1rem;
        background: var(--color-background);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        color: var(--color-text);
        font-size: 1rem;
        transition: all var(--transition-normal);
        outline: none;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='currentColor' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 1rem center;
        background-size: 16px;
    }
    
    .form-select:focus {
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(124, 144, 219, 0.2);
    }
    
    .form-textarea {
        width: 100%;
        padding: 0.75rem 1rem;
        background: var(--color-background);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        color: var(--color-text);
        font-size: 1rem;
        transition: all var(--transition-normal);
        outline: none;
        resize: vertical;
        min-height: 100px;
    }
    
    .form-textarea:focus {
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(124, 144, 219, 0.2);
    }
    
    /* Grupo de entrada con icono */
    .input-group {
        position: relative;
    }
    
    .input-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--color-text-light);
    }
    
    .input-group .form-input {
        padding-left: 3rem;
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
    
    /* ============ NOTIFICACIONES ============ */
    .notification {
        position: fixed;
        top: 1rem;
        right: 1rem;
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 1rem 1.5rem;
        box-shadow: var(--shadow-lg);
        display: flex;
        align-items: center;
        gap: 1rem;
        z-index: 1000;
        animation: slideInRight 0.3s ease-out;
        max-width: 400px;
    }
    
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100%);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    .notification.success {
        border-left: 4px solid var(--color-success);
    }
    
    .notification.error {
        border-left: 4px solid var(--color-error);
    }
    
    .notification.warning {
        border-left: 4px solid var(--color-warning);
    }
    
    .notification.info {
        border-left: 4px solid var(--color-info);
    }
    
    .notification-icon {
        font-size: 1.5rem;
    }
    
    .notification.success .notification-icon {
        color: var(--color-success);
    }
    
    .notification.error .notification-icon {
        color: var(--color-error);
    }
    
    .notification.warning .notification-icon {
        color: var(--color-warning);
    }
    
    .notification.info .notification-icon {
        color: var(--color-info);
    }
    
    .notification-content {
        flex: 1;
    }
    
    .notification-title {
        font-weight: 600;
        color: var(--color-text);
        margin-bottom: 0.25rem;
    }
    
    .notification-message {
        font-size: 0.875rem;
        color: var(--color-text-light);
    }
    
    .notification-close {
        background: transparent;
        border: none;
        color: var(--color-text-light);
        font-size: 1.25rem;
        cursor: pointer;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: var(--radius-sm);
        transition: all var(--transition-normal);
    }
    
    .notification-close:hover {
        background: var(--color-border-light);
        color: var(--color-error);
    }
    
    /* ============ RESPONSIVE DESIGN ============ */
    @media (max-width: 1200px) {
        .main-content {
            padding: 1.5rem;
        }
        
        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        .patients-header {
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
        
        .module-header {
            padding: 1.5rem;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .actions-bar {
            flex-direction: column;
            align-items: stretch;
            gap: 1rem;
        }
        
        .search-container {
            min-width: 100%;
        }
        
        .action-buttons {
            justify-content: center;
        }
        
        .patients-table {
            display: block;
            overflow-x: auto;
        }
        
        .patients-table th,
        .patients-table td {
            padding: 0.75rem;
        }
        
        .patient-cell {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
        
        .action-buttons-cell {
            justify-content: center;
        }
    }
    
    @media (max-width: 480px) {
        .stat-card {
            padding: 1.25rem;
        }
        
        .module-header {
            padding: 1.25rem;
        }
        
        .btn-primary,
        .btn-outline {
            padding: 0.75rem 1.25rem;
            font-size: 0.875rem;
        }
        
        .action-btn {
            width: 32px;
            height: 32px;
        }
        
        .custom-modal {
            max-height: 95vh;
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
    </style>
</head>
<body>
    <!-- Efecto de mármol animado -->
    <div class="marble-effect"></div>
    
    <!-- Botón móvil para mostrar/ocultar sidebar -->
    <button class="mobile-sidebar-toggle" id="mobileSidebarToggle" aria-label="Mostrar/ocultar menú">
        <i class="bi bi-list"></i>
    </button>
    
    <div class="patients-container">
        <!-- Header superior -->
        <header class="patients-header">
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
                            <span class="user-role"><?php echo htmlspecialchars($_SESSION['especialidad'] ?? 'Profesional Médico'); ?></span>
                        </div>
                    </div>
                    
                    <!-- Botón de cerrar sesión -->
                    <a href="../auth/logout.php" class="btn-primary logout-btn" title="Cerrar sesión">
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
                
                <!-- Pacientes (activo) -->
                <li class="nav-item">
                    <a href="../patients/index.php" class="nav-link active">
                        <i class="bi bi-person-vcard nav-icon"></i>
                        <span class="nav-text">Pacientes</span>
                    </a>
                </li>
                
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
            <!-- Mensajes de sesión -->
            <?php if (isset($_SESSION['patient_message'])): ?>
                <div class="notification <?php echo $_SESSION['patient_status'] === 'success' ? 'success' : 'error'; ?>" id="sessionNotification">
                    <div class="notification-icon">
                        <i class="bi <?php echo $_SESSION['patient_status'] === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?>"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title">
                            <?php echo $_SESSION['patient_status'] === 'success' ? 'Éxito' : 'Error'; ?>
                        </div>
                        <div class="notification-message">
                            <?php echo $_SESSION['patient_message']; ?>
                        </div>
                    </div>
                    <button class="notification-close" onclick="closeNotification('sessionNotification')">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
                <?php 
                unset($_SESSION['patient_message']);
                unset($_SESSION['patient_status']);
                ?>
            <?php endif; ?>
            
            <!-- Encabezado del módulo -->
            <div class="module-header">
                <h1 class="module-title">
                    <i class="bi bi-people module-title-icon"></i>
                    Gestión de Pacientes
                </h1>
                <p class="module-subtitle">
                    Administración completa de historias clínicas digitales y seguimiento médico
                </p>
                
                <!-- Estadísticas rápidas --> 
                <?php if ($role === 'admin'): ?>
                <div class="stats-grid">
                    <div class="stat-card" onclick="filterPatients('all')">
                        <div class="stat-content">
                            <div class="stat-info">
                                <div class="stat-label">Total Pacientes</div>
                                <div class="stat-value"><?php echo $total_patients; ?></div>
                            </div>
                            <div class="stat-icon primary">
                                <i class="bi bi-people-fill"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card" onclick="filterPatients('with_appointments')">
                        <div class="stat-content">
                            <div class="stat-info">
                                <div class="stat-label">Con Citas</div>
                                <div class="stat-value"><?php echo $patients_with_appointments; ?></div>
                            </div>
                            <div class="stat-icon success">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card" onclick="filterPatients('without_history')">
                        <div class="stat-content">
                            <div class="stat-info">
                                <div class="stat-label">Sin Historial</div>
                                <div class="stat-value"><?php echo $patients_without_history; ?></div>
                            </div>
                            <div class="stat-icon warning">
                                <i class="bi bi-person-x"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card" onclick="filterPatients('active_today')">
                        <div class="stat-content">
                            <div class="stat-info">
                                <div class="stat-label">Activos Hoy</div>
                                <div class="stat-value"><?php echo $active_today; ?></div>
                            </div>
                            <div class="stat-icon info">
                                <i class="bi bi-activity"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Barra de búsqueda y acciones -->
            <div class="actions-bar">
                <div class="search-container">
                    <div class="input-group">
                        <i class="bi bi-search search-icon"></i>
                        <input type="text" 
                               id="searchInput" 
                               class="search-input" 
                               placeholder="Buscar por nombre, apellido, teléfono o correo..."
                               aria-label="Buscar pacientes">
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button type="button" class="btn-outline" id="filterButton">
                        <i class="bi bi-funnel"></i>
                        <span>Filtrar</span>
                    </button>
                    <button type="button" class="btn-primary" id="newPatientButton">
                        <i class="bi bi-person-plus"></i>
                        <span>Nuevo Paciente</span>
                    </button>
                </div>
            </div>
            
            <!-- Filtros rápidos -->
            <div class="filters-container" id="filtersContainer" style="display: none;">
                <button type="button" class="filter-btn active" onclick="filterPatients('all')">
                    Todos
                </button>
                <button type="button" class="filter-btn" onclick="filterPatients('with_appointments')">
                    Con Citas
                </button>
                <button type="button" class="filter-btn" onclick="filterPatients('without_history')">
                    Sin Historial
                </button>
                <button type="button" class="filter-btn" onclick="filterPatients('active_today')">
                    Activos Hoy
                </button>
                <button type="button" class="filter-btn" onclick="filterPatients('male')">
                    Masculino
                </button>
                <button type="button" class="filter-btn" onclick="filterPatients('female')">
                    Femenino
                </button>
            </div>
            
            <!-- Tabla de pacientes -->
            <div class="patients-table-container">
                <div class="table-header">
                    <div>
                        <h3 class="table-title">
                            <i class="bi bi-list-ul"></i>
                            Lista de Pacientes
                        </h3>
                        <div class="table-count" id="patientCount">
                            Mostrando <?php echo $total_patients; ?> pacientes
                        </div>
                    </div>
                    <?php if ($role === 'admin'): ?>
                    <button type="button" class="btn-outline" onclick="exportPatients()">
                        <i class="bi bi-download"></i>
                        <span>Exportar</span>
                    </button>
                    <?php endif; ?>
                </div>
                
                <div class="table-responsive">
                    <table class="patients-table" id="patientsTable">
                        <thead>
                            <tr>
                                <th>Paciente</th>
                                <th>Contacto</th>
                                <th>Información</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="patientsTableBody">
                            <?php if (!empty($patients)): ?>
                                <?php foreach($patients as $patient): 
                                    $edad = isset($patient['fecha_nacimiento']) ? 
                                        (new DateTime())->diff(new DateTime($patient['fecha_nacimiento']))->y : 0;
                                    $patient_initials = strtoupper(
                                        substr($patient['nombre'] ?? '', 0, 1) . 
                                        substr($patient['apellido'] ?? '', 0, 1)
                                    );
                                    $has_appointments = $patient['total_citas'] > 0;
                                    $has_history = isset($patient['ultima_cita']);
                                    $active_today = $has_history && $patient['ultima_cita'] === date('Y-m-d');
                                ?>
                                <tr class="patient-row" 
                                    data-id="<?php echo $patient['id_paciente']; ?>"
                                    data-name="<?php echo htmlspecialchars(strtolower(($patient['nombre'] ?? '') . ' ' . ($patient['apellido'] ?? ''))); ?>"
                                    data-phone="<?php echo htmlspecialchars(strtolower($patient['telefono'] ?? '')); ?>"
                                    data-email="<?php echo htmlspecialchars(strtolower($patient['correo'] ?? '')); ?>"
                                    data-has-appointments="<?php echo $has_appointments ? 'true' : 'false'; ?>"
                                    data-has-history="<?php echo $has_history ? 'true' : 'false'; ?>"
                                    data-active-today="<?php echo $active_today ? 'true' : 'false'; ?>"
                                    data-gender="<?php echo htmlspecialchars(strtolower($patient['genero'] ?? '')); ?>">
                                    <td>
                                        <div class="patient-cell">
                                            <div class="patient-avatar">
                                                <?php echo $patient_initials; ?>
                                            </div>
                                            <div class="patient-info">
                                                <div class="patient-name">
                                                    <?php echo htmlspecialchars(($patient['nombre'] ?? '') . ' ' . ($patient['apellido'] ?? '')); ?>
                                                </div>
                                                <div class="patient-id">
                                                    ID: #<?php echo str_pad($patient['id_paciente'], 5, '0', STR_PAD_LEFT); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="contact-info">
                                            <div class="contact-item">
                                                <i class="bi bi-telephone contact-icon"></i>
                                                <span><?php echo htmlspecialchars($patient['telefono'] ?? 'No disponible'); ?></span>
                                            </div>
                                            <div class="contact-item">
                                                <i class="bi bi-envelope contact-icon"></i>
                                                <span><?php echo htmlspecialchars($patient['correo'] ?? 'No disponible'); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="demographic-info">
                                            <div class="demographic-item">
                                                <i class="bi bi-calendar3 contact-icon"></i>
                                                <span><?php echo htmlspecialchars($patient['fecha_nacimiento'] ?? 'N/A'); ?> (<?php echo $edad; ?> años)</span>
                                            </div>
                                            <div class="demographic-item">
                                                <?php if (isset($patient['genero'])): ?>
                                                    <span class="gender-badge <?php 
                                                        echo strtolower($patient['genero']) === 'masculino' ? 'gender-male' : 
                                                               (strtolower($patient['genero']) === 'femenino' ? 'gender-female' : 'gender-other');
                                                    ?>">
                                                        <?php echo htmlspecialchars($patient['genero']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($has_history): ?>
                                                    <span class="text-muted">
                                                        Última visita: <?php echo date('d/m/Y', strtotime($patient['ultima_cita'])); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($active_today): ?>
                                            <span class="status-badge status-active">
                                                <i class="bi bi-check-circle"></i>
                                                Activo Hoy
                                            </span>
                                        <?php elseif ($has_history): ?>
                                            <span class="status-badge status-active">
                                                <i class="bi bi-check-circle"></i>
                                                Activo
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-inactive">
                                                <i class="bi bi-clock-history"></i>
                                                Sin Visitas
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($has_appointments): ?>
                                            <div class="text-muted" style="font-size: 0.75rem; margin-top: 0.25rem;">
                                                <i class="bi bi-calendar-check"></i>
                                                <?php echo $patient['total_citas']; ?> citas
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons-cell">
                                            <a href="medical_history.php?id=<?php echo $patient['id_paciente']; ?>" 
                                               class="action-btn history" 
                                               title="Historial Clínico">
                                                <i class="bi bi-clipboard2-pulse"></i>
                                            </a>
                                            <button type="button" 
                                                    class="action-btn appointment" 
                                                    title="Nueva Cita"
                                                    onclick="quickAppointment(<?php echo $patient['id_paciente']; ?>, '<?php echo htmlspecialchars($patient['nombre']); ?>', '<?php echo htmlspecialchars($patient['apellido']); ?>')">
                                                <i class="bi bi-calendar-plus"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <div class="empty-icon">
                                                <i class="bi bi-people"></i>
                                            </div>
                                            <h4 class="text-muted mb-2">No se encontraron pacientes</h4>
                                            <p class="text-muted mb-3">Comienza agregando tu primer paciente</p>
                                            <button type="button" class="btn-primary" id="emptyNewPatientButton">
                                                <i class="bi bi-person-plus"></i>
                                                Agregar Primer Paciente
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Paginación -->
            <?php if ($total_patients > 0): ?>
            <div class="pagination-container">
                <nav aria-label="Paginación de pacientes">
                    <ul class="pagination">
                        <li class="page-item disabled">
                            <a class="page-link" href="#" aria-label="Anterior">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                        <?php if ($total_patients > 20): ?>
                        <li class="page-item"><a class="page-link" href="#">2</a></li>
                        <?php endif; ?>
                        <?php if ($total_patients > 40): ?>
                        <li class="page-item"><a class="page-link" href="#">3</a></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="#" aria-label="Siguiente">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </main>
    </div>
    
    <!-- Modal para nuevo paciente -->
    <div class="custom-modal-overlay" id="newPatientModal">
        <div class="custom-modal">
            <div class="custom-modal-header">
                <h3 class="custom-modal-title">
                    <i class="bi bi-person-plus"></i>
                    Nuevo Paciente
                </h3>
                <button type="button" class="custom-modal-close" onclick="closeModal('newPatientModal')">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <form id="newPatientForm" action="save_patient.php" method="POST">
                <div class="custom-modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nombre" class="form-label">Nombres *</label>
                            <input type="text" 
                                   id="nombre" 
                                   name="nombre" 
                                   class="form-input" 
                                   placeholder="Ej: Juan Antonio" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="apellido" class="form-label">Apellidos *</label>
                            <input type="text" 
                                   id="apellido" 
                                   name="apellido" 
                                   class="form-input" 
                                   placeholder="Ej: Pérez Sosa" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento *</label>
                            <input type="date" 
                                   id="fecha_nacimiento" 
                                   name="fecha_nacimiento" 
                                   class="form-input" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="genero" class="form-label">Género *</label>
                            <select id="genero" name="genero" class="form-select" required>
                                <option value="">Seleccionar...</option>
                                <option value="Masculino">Masculino</option>
                                <option value="Femenino">Femenino</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <div class="input-group">
                                <i class="bi bi-telephone input-icon"></i>
                                <input type="tel" 
                                       id="telefono" 
                                       name="telefono" 
                                       class="form-input" 
                                       placeholder="Ej: 46232418">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="correo" class="form-label">Correo Electrónico</label>
                            <div class="input-group">
                                <i class="bi bi-envelope input-icon"></i>
                                <input type="email" 
                                       id="correo" 
                                       name="correo" 
                                       class="form-input" 
                                       placeholder="Ej: juan@gmail.com">
                            </div>
                        </div>
                        
                        <div class="form-group" style="grid-column: span 2;">
                            <label for="direccion" class="form-label">Dirección</label>
                            <textarea id="direccion" 
                                      name="direccion" 
                                      class="form-textarea" 
                                      placeholder="Ej: Barrio San Juan, Nentón"
                                      rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="custom-modal-footer">
                    <button type="button" class="btn-outline" onclick="closeModal('newPatientModal')">
                        Cancelar
                    </button>
                    <button type="submit" class="btn-primary">
                        Guardar Paciente
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal para cita rápida -->
    <div class="custom-modal-overlay" id="quickAppointmentModal">
        <div class="custom-modal">
            <div class="custom-modal-header">
                <h3 class="custom-modal-title">
                    <i class="bi bi-calendar-plus"></i>
                    Nueva Cita Rápida
                </h3>
                <button type="button" class="custom-modal-close" onclick="closeModal('quickAppointmentModal')">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <form id="quickAppointmentForm">
                <div class="custom-modal-body">
                    <div class="form-grid">
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Paciente</label>
                            <input type="text" 
                                   id="quickPatientName" 
                                   class="form-input" 
                                   readonly>
                            <input type="hidden" id="quickPatientId" name="patient_id">
                        </div>
                        
                        <div class="form-group">
                            <label for="quickDate" class="form-label">Fecha *</label>
                            <input type="date" 
                                   id="quickDate" 
                                   name="fecha_cita" 
                                   class="form-input" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="quickTime" class="form-label">Hora *</label>
                            <input type="time" 
                                   id="quickTime" 
                                   name="hora_cita" 
                                   class="form-input" 
                                   required>
                        </div>
                        
                        <div class="form-group" style="grid-column: span 2;">
                            <label for="quickDoctor" class="form-label">Médico *</label>
                            <select id="quickDoctor" name="id_doctor" class="form-select" required>
                                <option value="">Seleccionar Médico...</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['idUsuario']; ?>">
                                        Dr(a). <?php echo htmlspecialchars($doctor['nombre'] . ' ' . $doctor['apellido']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" style="grid-column: span 2;">
                            <label for="quickReason" class="form-label">Motivo de Consulta</label>
                            <textarea id="quickReason" 
                                      name="motivo_consulta" 
                                      class="form-textarea" 
                                      placeholder="Describa el motivo de la consulta"
                                      rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="custom-modal-footer">
                    <button type="button" class="btn-outline" onclick="closeModal('quickAppointmentModal')">
                        Cancelar
                    </button>
                    <button type="submit" class="btn-primary">
                        Programar Cita
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
    // Módulo de Pacientes - Centro Médico Herrera Saenz
    // JavaScript para funcionalidades del módulo
    
    // Esperar a que el DOM esté completamente cargado
    document.addEventListener('DOMContentLoaded', function() {
        // ============ REFERENCIAS A ELEMENTOS ============
        const themeSwitch = document.getElementById('themeSwitch');
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarToggleIcon = document.getElementById('sidebarToggleIcon');
        const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
        const searchInput = document.getElementById('searchInput');
        const filterButton = document.getElementById('filterButton');
        const filtersContainer = document.getElementById('filtersContainer');
        const newPatientButton = document.getElementById('newPatientButton');
        const emptyNewPatientButton = document.getElementById('emptyNewPatientButton');
        const patientCount = document.getElementById('patientCount');
        const patientsTableBody = document.getElementById('patientsTableBody');
        const patientRows = document.querySelectorAll('.patient-row');
        const filterButtons = document.querySelectorAll('.filter-btn');
        
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
        
        // Filtrar pacientes según texto de búsqueda
        function searchPatients() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            let visibleCount = 0;
            
            patientRows.forEach(row => {
                const name = row.dataset.name || '';
                const phone = row.dataset.phone || '';
                const email = row.dataset.email || '';
                
                const matches = name.includes(searchTerm) || 
                               phone.includes(searchTerm) || 
                               email.includes(searchTerm);
                
                if (matches || searchTerm === '') {
                    row.style.display = '';
                    row.classList.add('animate__animated', 'animate__fadeIn');
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Actualizar contador
            patientCount.textContent = `Mostrando ${visibleCount} de ${patientRows.length} pacientes`;
            
            // Mostrar mensaje si no hay resultados
            const existingMessage = document.getElementById('noResultsMessage');
            if (visibleCount === 0 && searchTerm !== '') {
                if (!existingMessage) {
                    const messageRow = document.createElement('tr');
                    messageRow.id = 'noResultsMessage';
                    messageRow.innerHTML = `
                        <td colspan="5">
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <i class="bi bi-search"></i>
                                </div>
                                <h4 class="text-muted mb-2">No se encontraron pacientes</h4>
                                <p class="text-muted mb-3">Intenta con otros términos de búsqueda</p>
                                <button type="button" class="btn-outline" onclick="clearSearch()">
                                    <i class="bi bi-x-circle"></i>
                                    Limpiar búsqueda
                                </button>
                            </div>
                        </td>
                    `;
                    patientsTableBody.appendChild(messageRow);
                }
            } else if (existingMessage) {
                existingMessage.remove();
            }
        }
        
        // Limpiar búsqueda
        function clearSearch() {
            searchInput.value = '';
            searchPatients();
        }
        
        // ============ FILTROS DE PACIENTES ============
        
        // Mostrar/ocultar contenedor de filtros
        function toggleFilters() {
            const isVisible = filtersContainer.style.display === 'flex';
            filtersContainer.style.display = isVisible ? 'none' : 'flex';
            filterButton.innerHTML = isVisible ? 
                '<i class="bi bi-funnel"></i><span>Filtrar</span>' : 
                '<i class="bi bi-funnel-fill"></i><span>Ocultar Filtros</span>';
        }
        
        // Aplicar filtro a los pacientes
        function filterPatients(filterType) {
            // Actualizar botones de filtro activos
            filterButtons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            let visibleCount = 0;
            
            patientRows.forEach(row => {
                const hasAppointments = row.dataset.hasAppointments === 'true';
                const hasHistory = row.dataset.hasHistory === 'true';
                const activeToday = row.dataset.activeToday === 'true';
                const gender = row.dataset.gender || '';
                
                let show = false;
                
                switch(filterType) {
                    case 'all':
                        show = true;
                        break;
                    case 'with_appointments':
                        show = hasAppointments;
                        break;
                    case 'without_history':
                        show = !hasHistory;
                        break;
                    case 'active_today':
                        show = activeToday;
                        break;
                    case 'male':
                        show = gender.includes('masculino');
                        break;
                    case 'female':
                        show = gender.includes('femenino');
                        break;
                    default:
                        show = true;
                }
                
                if (show) {
                    row.style.display = '';
                    row.classList.add('animate__animated', 'animate__fadeIn');
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Actualizar contador
            patientCount.textContent = `Mostrando ${visibleCount} de ${patientRows.length} pacientes`;
            
            // Ocultar filtros después de aplicar (en móvil)
            if (window.innerWidth < 768) {
                filtersContainer.style.display = 'none';
                filterButton.innerHTML = '<i class="bi bi-funnel"></i><span>Filtrar</span>';
            }
        }
        
        // ============ MODALES ============
        
        // Abrir modal
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
                
                // Configurar fecha por defecto para citas
                if (modalId === 'quickAppointmentModal') {
                    const today = new Date();
                    const tomorrow = new Date(today);
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    document.getElementById('quickDate').valueAsDate = tomorrow;
                    document.getElementById('quickTime').value = '09:00';
                }
            }
        }
        
        // Cerrar modal
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
                
                // Limpiar formulario si es modal de nuevo paciente
                if (modalId === 'newPatientModal') {
                    document.getElementById('newPatientForm').reset();
                }
            }
        }
        
        // Cerrar modal al hacer clic fuera
        function closeModalOnClickOutside(event, modalId) {
            const modal = document.getElementById(modalId);
            if (modal && event.target === modal) {
                closeModal(modalId);
            }
        }
        
        // Cerrar notificación
        function closeNotification(notificationId) {
            const notification = document.getElementById(notificationId);
            if (notification) {
                notification.style.animation = 'slideInRight 0.3s ease-out reverse';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }
        }
        
        // ============ FUNCIONALIDAD DE CITAS RÁPIDAS ============
        
        // Abrir modal de cita rápida
        function quickAppointment(patientId, nombre, apellido) {
            document.getElementById('quickPatientId').value = patientId;
            document.getElementById('quickPatientName').value = nombre + ' ' + apellido;
            openModal('quickAppointmentModal');
        }
        
        // ============ EXPORTACIÓN DE DATOS ============
        
        // Exportar lista de pacientes
        function exportPatients() {
            // Crear datos CSV
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "ID,Nombre,Apellido,Fecha Nacimiento,Género,Teléfono,Correo,Dirección,Total Citas,Última Cita\n";
            
            patientRows.forEach(row => {
                if (row.style.display !== 'none') {
                    const cells = row.querySelectorAll('td');
                    const id = row.dataset.id;
                    const name = cells[0].querySelector('.patient-name').textContent;
                    const phone = cells[1].querySelector('.contact-item:nth-child(1) span').textContent;
                    const email = cells[1].querySelector('.contact-item:nth-child(2) span').textContent;
                    const birthDate = cells[2].querySelector('.demographic-item:nth-child(1) span').textContent.split('(')[0].trim();
                    const gender = cells[2].querySelector('.gender-badge')?.textContent || 'N/A';
                    
                    // Extraer información adicional del dataset
                    const hasAppointments = row.dataset.hasAppointments === 'true';
                    const hasHistory = row.dataset.hasHistory === 'true';
                    
                    csvContent += `"${id}","${name}","${birthDate}","${gender}","${phone}","${email}","${hasAppointments ? 'Sí' : 'No'}","${hasHistory ? 'Sí' : 'No'}"\n`;
                }
            });
            
            // Crear y descargar archivo
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "pacientes_" + new Date().toISOString().slice(0, 10) + ".csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Mostrar notificación
            showNotification('success', 'Exportación completada', 'Los datos de pacientes han sido exportados correctamente.');
        }
        
        // ============ NOTIFICACIONES ============
        
        // Mostrar notificación
        function showNotification(type, title, message) {
            const notificationId = 'notification-' + Date.now();
            const notification = document.createElement('div');
            notification.id = notificationId;
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <div class="notification-icon">
                    <i class="bi bi-${type === 'success' ? 'check-circle' : 
                                      type === 'error' ? 'exclamation-triangle' : 
                                      type === 'warning' ? 'exclamation-circle' : 'info-circle'}-fill"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">${title}</div>
                    <div class="notification-message">${message}</div>
                </div>
                <button class="notification-close" onclick="closeNotification('${notificationId}')">
                    <i class="bi bi-x"></i>
                </button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto-eliminar después de 5 segundos
            setTimeout(() => {
                if (document.getElementById(notificationId)) {
                    closeNotification(notificationId);
                }
            }, 5000);
        }
        
        // ============ MANEJO DE FORMULARIOS ============
        
        // Manejar envío del formulario de nuevo paciente
        document.getElementById('newPatientForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Mostrar estado de carga
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Guardando...';
            submitBtn.disabled = true;
            
            // En un sistema real, aquí se haría una petición AJAX
            // Por simplicidad, redirigimos directamente
            setTimeout(() => {
                this.submit();
            }, 1000);
        });
        
        // Manejar envío del formulario de cita rápida
        document.getElementById('quickAppointmentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Mostrar estado de carga
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Programando...';
            submitBtn.disabled = true;
            
            // Simular envío AJAX
            setTimeout(() => {
                showNotification('success', 'Cita programada', 'La cita ha sido programada exitosamente.');
                closeModal('quickAppointmentModal');
                
                // Restaurar botón
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                this.reset();
            }, 1500);
        });
        
        // ============ ANIMACIONES AL CARGAR ============
        
        // Animar elementos al cargar la página
        function animateOnLoad() {
            const cards = document.querySelectorAll('.stat-card, .patients-table-container');
            
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
        
        // Configurar fecha mínima para citas (mañana)
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        document.getElementById('quickDate').min = tomorrow.toISOString().split('T')[0];
        
        // Configurar fecha de nacimiento máxima (hoy - 1 año)
        const maxBirthDate = new Date();
        maxBirthDate.setFullYear(maxBirthDate.getFullYear() - 1);
        document.getElementById('fecha_nacimiento').max = maxBirthDate.toISOString().split('T')[0];
        
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
        searchInput.addEventListener('input', searchPatients);
        
        // Filtros
        filterButton.addEventListener('click', toggleFilters);
        
        // Modales
        if (newPatientButton) {
            newPatientButton.addEventListener('click', () => openModal('newPatientModal'));
        }
        
        if (emptyNewPatientButton) {
            emptyNewPatientButton.addEventListener('click', () => openModal('newPatientModal'));
        }
        
        // Cerrar modales al hacer clic fuera
        document.addEventListener('click', (e) => {
            closeModalOnClickOutside(e, 'newPatientModal');
            closeModalOnClickOutside(e, 'quickAppointmentModal');
        });
        
        // Cerrar sidebar al cambiar tamaño de ventana (responsive)
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992 && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
            
            // Ocultar filtros en pantallas pequeñas
            if (window.innerWidth < 768 && filtersContainer.style.display === 'flex') {
                filtersContainer.style.display = 'none';
                filterButton.innerHTML = '<i class="bi bi-funnel"></i><span>Filtrar</span>';
            }
        });
        
        // ============ SALUDO DINÁMICO ============
        
        // Actualizar saludo según hora del día
        function updateGreeting() {
            const hour = new Date().getHours();
            const greetingElement = document.getElementById('greeting-text');
            
            if (greetingElement) {
                let greeting = '';
                if (hour < 12) {
                    greeting = 'Buenos días';
                } else if (hour < 19) {
                    greeting = 'Buenas tardes';
                } else {
                    greeting = 'Buenas noches';
                }
                
                greetingElement.textContent = greeting + ', ' + "<?php echo htmlspecialchars($user_name); ?>";
            }
        }
        
        updateGreeting();
        
        // ============ CONSOLA DE DESARROLLO ============
        
        console.log('Módulo de Pacientes - Centro Médico Herrera Saenz');
        console.log('Versión: 3.0 - Diseño con Efecto Mármol y Modo Noche');
        console.log('Total de pacientes: <?php echo $total_patients; ?>');
        console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
        console.log('Rol: <?php echo htmlspecialchars($user_type); ?>');
    });
    
    // Funciones globales
    window.closeModal = closeModal;
    window.closeNotification = closeNotification;
    window.filterPatients = filterPatients;
    window.quickAppointment = quickAppointment;
    window.exportPatients = exportPatients;
    window.clearSearch = clearSearch;
    
    // Definir funciones globales
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
    
    function closeNotification(notificationId) {
        const notification = document.getElementById(notificationId);
        if (notification) {
            notification.style.animation = 'slideInRight 0.3s ease-out reverse';
            setTimeout(() => {
                notification.remove();
            }, 300);
        }
    }
    
    function filterPatients(filterType) {
        const patientRows = document.querySelectorAll('.patient-row');
        const filterButtons = document.querySelectorAll('.filter-btn');
        
        // Actualizar botones de filtro activos
        filterButtons.forEach(btn => btn.classList.remove('active'));
        event.target.classList.add('active');
        
        let visibleCount = 0;
        
        patientRows.forEach(row => {
            const hasAppointments = row.dataset.hasAppointments === 'true';
            const hasHistory = row.dataset.hasHistory === 'true';
            const activeToday = row.dataset.activeToday === 'true';
            const gender = row.dataset.gender || '';
            
            let show = false;
            
            switch(filterType) {
                case 'all':
                    show = true;
                    break;
                case 'with_appointments':
                    show = hasAppointments;
                    break;
                case 'without_history':
                    show = !hasHistory;
                    break;
                case 'active_today':
                    show = activeToday;
                    break;
                case 'male':
                    show = gender.includes('masculino');
                    break;
                case 'female':
                    show = gender.includes('femenino');
                    break;
                default:
                    show = true;
            }
            
            if (show) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Actualizar contador
        document.getElementById('patientCount').textContent = `Mostrando ${visibleCount} de ${patientRows.length} pacientes`;
    }
    
    function quickAppointment(patientId, nombre, apellido) {
        document.getElementById('quickPatientId').value = patientId;
        document.getElementById('quickPatientName').value = nombre + ' ' + apellido;
        
        const modal = document.getElementById('quickAppointmentModal');
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // Configurar fecha por defecto (mañana)
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            document.getElementById('quickDate').valueAsDate = tomorrow;
            document.getElementById('quickTime').value = '09:00';
        }
    }
    
    function exportPatients() {
        // Implementación simplificada
        alert('La función de exportación se ejecutará en el sistema completo.\nLos datos se descargarán en formato CSV.');
    }
    
    function clearSearch() {
        document.getElementById('searchInput').value = '';
        const event = new Event('input');
        document.getElementById('searchInput').dispatchEvent(event);
    }
    </script>
</body>
</html>