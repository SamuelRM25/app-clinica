<?php
// dashboard.php - Dashboard Minimalista del Centro Médico Herrera Saenz
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
    
    // Configurar filtros según tipo de usuario
    $is_doctor = $user_type === 'doc';
    $doctor_filter = $is_doctor ? " AND id_doctor = ?" : "";
    $today = date('Y-m-d');
    
    // ============ CONSULTAS ESTADÍSTICAS ============
    
    // 1. Citas de hoy
    $params = $is_doctor ? [$today, $user_id] : [$today];
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM citas WHERE fecha_cita = ?" . $doctor_filter);
    $stmt->execute($params);
    $today_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 2. Pacientes del año actual
    $current_year = date('Y');
    $year_start = $current_year . '-01-01';
    $year_end = $current_year . '-12-31';
    $year_params = $is_doctor ? [$year_start, $year_end, $user_id] : [$year_start, $year_end];
    
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT CONCAT(nombre_pac, ' ', apellido_pac)) as count 
        FROM citas 
        WHERE fecha_cita BETWEEN ? AND ?" . $doctor_filter
    );
    $stmt->execute($year_params);
    $year_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 3. Citas pendientes (futuras)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM citas WHERE fecha_cita > ?" . $doctor_filter);
    $stmt->execute($params);
    $pending_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 4. Consultas del mes actual
    $month_start = date('Y-m-01');
    $month_end = date('Y-m-t');
    $month_params = $is_doctor ? [$month_start, $month_end, $user_id] : [$month_start, $month_end];
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM citas 
        WHERE fecha_cita BETWEEN ? AND ?" . $doctor_filter
    );
    $stmt->execute($month_params);
    $month_consultations = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 5. Citas de hoy con detalles
    $stmt = $conn->prepare("
        SELECT id_cita, nombre_pac, apellido_pac, hora_cita, telefono 
        FROM citas 
        WHERE fecha_cita = ?" . $doctor_filter . "
        ORDER BY hora_cita
    ");
    $stmt->execute($params);
    $todays_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 6. Total de citas en el sistema
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM citas");
    $stmt->execute();
    $total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // ============ INVENTARIO ============
    
    // 7. Medicamentos en inventario
    $stmt = $conn->prepare("SELECT SUM(cantidad_med) as total FROM inventario WHERE cantidad_med > 0");
    $stmt->execute();
    $total_medications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // 8. Medicamentos próximos a caducar (30 días)
    $next_month = date('Y-m-d', strtotime('+30 days'));
    $stmt = $conn->prepare("
        SELECT id_inventario, nom_medicamento, fecha_vencimiento, cantidad_med 
        FROM inventario 
        WHERE fecha_vencimiento BETWEEN ? AND ? AND cantidad_med > 0
        ORDER BY fecha_vencimiento ASC
    ");
    $stmt->execute([$today, $next_month]);
    $expiring_medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 9. Medicamentos con stock bajo (< 5 unidades)
    $stmt = $conn->prepare("
        SELECT id_inventario, nom_medicamento, cantidad_med 
        FROM inventario 
        WHERE cantidad_med > 0 AND cantidad_med < 5
        ORDER BY cantidad_med ASC
    ");
    $stmt->execute();
    $low_stock_medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 10. Compras pendientes
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventario WHERE estado = 'Pendiente'");
    $stmt->execute();
    $pending_purchases = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Título de la página
    $page_title = "Dashboard - Centro Médico Herrera Saenz";
    
} catch (Exception $e) {
    // Manejo de errores
    error_log("Error en dashboard: " . $e->getMessage());
    die("Error al cargar el dashboard. Por favor, contacte al administrador.");
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
     * Dashboard Minimalista - Centro Médico Herrera Saenz
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
    
    /* ============ ESTADÍSTICAS PRINCIPALES ============ */
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
    
    .stat-change {
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }
    
    .stat-change.positive {
        color: var(--color-success);
    }
    
    .stat-change.negative {
        color: var(--color-error);
    }
    
    /* ============ SECCIÓN DE CITAS DE HOY ============ */
    .appointments-section {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-bottom: 2rem;
        animation: fadeIn 0.6s ease-out 0.3s both;
    }
    
    .section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--color-border);
    }
    
    .section-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--color-text);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .section-title-icon {
        color: var(--color-primary);
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
    
    /* Tabla de citas */
    .appointments-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .appointments-table th {
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
    
    .appointments-table td {
        padding: 1rem;
        border-bottom: 1px solid var(--color-border);
        color: var(--color-text);
        transition: background-color var(--transition-normal);
    }
    
    .appointments-table tbody tr:hover td {
        background: var(--color-border-light);
    }
    
    .appointments-table tbody tr:last-child td {
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
    
    .patient-contact {
        font-size: 0.875rem;
        color: var(--color-text-light);
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
    
    .action-buttons {
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
        background: var(--color-primary);
        color: white;
        border-color: var(--color-primary);
        transform: translateY(-2px);
    }
    
    .btn-icon.edit:hover {
        background: var(--color-info);
        border-color: var(--color-info);
    }
    
    .btn-icon.history:hover {
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
    
    /* ============ PANEL DE ALERTAS ============ */
    .alerts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        animation: fadeIn 0.6s ease-out 0.4s both;
    }
    
    .alert-card {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        transition: all var(--transition-normal);
    }
    
    .alert-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
    }
    
    .alert-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--color-border);
    }
    
    .alert-icon {
        width: 40px;
        height: 40px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        color: white;
        flex-shrink: 0;
    }
    
    .alert-icon.warning {
        background: linear-gradient(135deg, var(--color-warning), #d97706);
    }
    
    .alert-icon.danger {
        background: linear-gradient(135deg, var(--color-error), #dc2626);
    }
    
    .alert-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--color-text);
    }
    
    /* Lista de alertas */
    .alert-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .alert-item {
        padding: 1rem;
        border-bottom: 1px solid var(--color-border);
        transition: background-color var(--transition-normal);
    }
    
    .alert-item:hover {
        background: var(--color-border-light);
    }
    
    .alert-item:last-child {
        border-bottom: none;
    }
    
    .alert-item-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.5rem;
    }
    
    .alert-item-name {
        font-weight: 600;
        color: var(--color-text);
    }
    
    .alert-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .alert-badge.expired {
        background: var(--color-error);
        color: white;
    }
    
    .alert-badge.warning {
        background: var(--color-warning);
        color: var(--color-text);
    }
    
    .alert-badge.danger {
        background: var(--color-error);
        color: white;
    }
    
    .alert-item-details {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.875rem;
        color: var(--color-text-light);
    }
    
    /* Estado sin alertas */
    .no-alerts {
        text-align: center;
        padding: 2rem 1rem;
        color: var(--color-text-light);
    }
    
    .no-alerts-icon {
        font-size: 2.5rem;
        color: var(--color-success);
        margin-bottom: 1rem;
        opacity: 0.7;
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
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .alerts-grid {
            grid-template-columns: 1fr;
        }
        
        .appointments-table {
            display: block;
            overflow-x: auto;
        }
    }
    
    @media (max-width: 480px) {
        .stat-card {
            padding: 1.25rem;
        }
        
        .appointments-section {
            padding: 1.25rem;
        }
        
        .alert-card {
            padding: 1.25rem;
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
                    <a href="../dashboard/index.php" class="nav-link active">
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
            <!-- Notificación de compras pendientes -->
            <?php if ($pending_purchases > 0): ?>
            <div class="alert-card mb-4" style="border-left: 4px solid var(--color-warning);">
                <div class="alert-header">
                    <i class="bi bi-box-seam text-warning" style="font-size: 1.5rem;"></i>
                    <h3 class="alert-title">Compras Pendientes</h3>
                </div>
                <p class="text-muted">
                    Hay <strong><?php echo $pending_purchases; ?></strong> productos por recibir en inventario.
                    <a href="../inventory/index.php" class="text-primary text-decoration-none ms-1">
                        Revisar inventario <i class="bi bi-arrow-right"></i>
                    </a>
                </p>
            </div>
            <?php endif; ?>
            
            <!-- Bienvenida personalizada -->
            <div class="stat-card mb-4">
                <div class="stat-header">
                    <div>
                        <h2 id="greeting" class="stat-value" style="font-size: 1.75rem; margin-bottom: 0.5rem;">
                            <span id="greeting-text">Buenos días</span>, <?php echo htmlspecialchars($user_name); ?>
                        </h2>
                        <p class="text-muted">
                            <i class="bi bi-calendar-check me-1"></i> <?php echo date('d/m/Y'); ?>
                            <span class="mx-2">•</span>
                            <i class="bi bi-clock me-1"></i> <span id="current-time"><?php echo date('H:i'); ?></span>
                            <span class="mx-2">•</span>
                            <i class="bi bi-building me-1"></i> Centro Médico Herrera Saenz
                        </p>
                    </div>
                    <div class="d-none d-md-block">
                        <i class="bi bi-heart-pulse text-primary" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
            
            <!-- Estadísticas principales -->
            <div class="stats-grid">
                <!-- Citas de hoy -->
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Citas Hoy</div>
                            <div class="stat-value"><?php echo $today_appointments; ?></div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-arrow-up-right"></i>
                        <span>Programadas para hoy</span>
                    </div>
                </div>
                
                <!-- Pacientes del año -->
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Pacientes Año</div>
                            <div class="stat-value"><?php echo $year_patients; ?></div>
                        </div>
                        <div class="stat-icon success">
                            <i class="bi bi-people"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-person-plus"></i>
                        <span>Año <?php echo date('Y'); ?></span>
                    </div>
                </div>
                
                <!-- Citas pendientes -->
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Citas Pendientes</div>
                            <div class="stat-value"><?php echo $pending_appointments; ?></div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="bi bi-clock-history"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-calendar-plus"></i>
                        <span>Próximas citas</span>
                    </div>
                </div>
                
                <!-- Consultas del mes -->
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Consultas Mes</div>
                            <div class="stat-value"><?php echo $month_consultations; ?></div>
                        </div>
                        <div class="stat-icon info">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-calendar-month"></i>
                        <span>Mes actual</span>
                    </div>
                </div>
            </div>
            
            <!-- Sección de citas de hoy -->
            <section class="appointments-section">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-calendar-day section-title-icon"></i>
                        Citas de Hoy
                    </h3>
                    <a href="../appointments/create.php" class="action-btn">
                        <i class="bi bi-plus-lg"></i>
                        Nueva Cita
                    </a>
                </div>
                
                <?php if (count($todays_appointments) > 0): ?>
                    <div class="table-responsive">
                        <table class="appointments-table">
                            <thead>
                                <tr>
                                    <th>Paciente</th>
                                    <th>Hora</th>
                                    <th>Contacto</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($todays_appointments as $appointment): ?>
                                    <?php 
                                    $patient_name = htmlspecialchars($appointment['nombre_pac'] . ' ' . $appointment['apellido_pac']);
                                    $patient_initials = strtoupper(
                                        substr($appointment['nombre_pac'], 0, 1) . 
                                        substr($appointment['apellido_pac'], 0, 1)
                                    );
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
                                            <span class="time-badge">
                                                <i class="bi bi-clock"></i>
                                                <?php echo htmlspecialchars($appointment['hora_cita']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="patient-contact">
                                                <?php echo htmlspecialchars($appointment['telefono'] ?? 'No disponible'); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="../appointments/edit.php?id=<?php echo $appointment['id_cita']; ?>" 
                                                   class="btn-icon edit" 
                                                   title="Editar cita">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="#" 
                                                   class="btn-icon history check-patient" 
                                                   title="Ver historial"
                                                   data-nombre="<?php echo htmlspecialchars($appointment['nombre_pac']); ?>" 
                                                   data-apellido="<?php echo htmlspecialchars($appointment['apellido_pac']); ?>">
                                                    <i class="bi bi-file-medical"></i>
                                                </a>
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
                            <i class="bi bi-calendar-x"></i>
                        </div>
                        <h4 class="text-muted mb-2">No hay citas programadas para hoy</h4>
                        <p class="text-muted mb-3">Total de citas en sistema: <?php echo $total_appointments; ?></p>
                        <a href="../appointments/create.php" class="action-btn">
                            <i class="bi bi-plus-lg"></i>
                            Programar primera cita
                        </a>
                    </div>
                <?php endif; ?>
            </section>
            
            <!-- Panel de alertas -->
            <div class="alerts-grid">
                <!-- Medicamentos por caducar -->
                <div class="alert-card">
                    <div class="alert-header">
                        <div class="alert-icon warning">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <h3 class="alert-title">Caducidad Próxima</h3>
                    </div>
                    
                    <?php if (count($expiring_medications) > 0): ?>
                        <ul class="alert-list">
                            <?php foreach (array_slice($expiring_medications, 0, 5) as $medication): ?>
                                <?php 
                                $expiry_date = new DateTime($medication['fecha_vencimiento']);
                                $today = new DateTime();
                                $days_diff = $today->diff($expiry_date)->days;
                                $is_expired = $expiry_date < $today;
                                ?>
                                <li class="alert-item">
                                    <div class="alert-item-header">
                                        <span class="alert-item-name"><?php echo htmlspecialchars($medication['nom_medicamento']); ?></span>
                                        <span class="alert-badge <?php echo $is_expired ? 'expired' : 'warning'; ?>">
                                            <?php echo $is_expired ? 'Vencido' : $days_diff . ' días'; ?>
                                        </span>
                                    </div>
                                    <div class="alert-item-details">
                                        <span>Vence: <?php echo $expiry_date->format('d/m/Y'); ?></span>
                                        <span>Stock: <?php echo $medication['cantidad_med']; ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <?php if (count($expiring_medications) > 5): ?>
                            <div class="text-center mt-3">
                                <a href="../inventory/index.php?filter=expiring" class="text-primary text-decoration-none">
                                    Ver todas (<?php echo count($expiring_medications); ?>) <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-alerts">
                            <div class="no-alerts-icon">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <p class="text-muted mb-0">Sin medicamentos próximos a caducar</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Stock bajo -->
                <div class="alert-card">
                    <div class="alert-header">
                        <div class="alert-icon danger">
                            <i class="bi bi-arrow-down-circle"></i>
                        </div>
                        <h3 class="alert-title">Stock Bajo</h3>
                    </div>
                    
                    <?php if (count($low_stock_medications) > 0): ?>
                        <ul class="alert-list">
                            <?php foreach (array_slice($low_stock_medications, 0, 5) as $medication): ?>
                                <li class="alert-item">
                                    <div class="alert-item-header">
                                        <span class="alert-item-name"><?php echo htmlspecialchars($medication['nom_medicamento']); ?></span>
                                        <span class="alert-badge danger">
                                            <?php echo $medication['cantidad_med']; ?> unidades
                                        </span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <?php if (count($low_stock_medications) > 15): ?>
                            <div class="text-center mt-3">
                                <a href="../inventory/index.php?filter=low_stock" class="text-primary text-decoration-none">
                                    Ver todas (<?php echo count($low_stock_medications); ?>) <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-alerts">
                            <div class="no-alerts-icon">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <p class="text-muted mb-0">Inventario con stock suficiente</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Modal para nuevo paciente (mantenido del original) -->
    <div class="modal fade" id="newPatientModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Paciente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="newPatientForm" action="../patients/save_patient.php" method="POST">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label">Nombre</label>
                                <input type="text" class="form-control" name="nombre" id="modal-nombre" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Apellido</label>
                                <input type="text" class="form-control" name="apellido" id="modal-apellido" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Fecha Nacimiento</label>
                                <input type="date" class="form-control" name="fecha_nacimiento" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Género</label>
                                <select class="form-select" name="genero" required>
                                    <option value="">Seleccionar...</option>
                                    <option value="Masculino">Masculino</option>
                                    <option value="Femenino">Femenino</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" name="telefono">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Dirección</label>
                                <textarea class="form-control" name="direccion" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
    // Dashboard Minimalista - Centro Médico Herrera Saenz
    // JavaScript para funcionalidades del dashboard
    
    // Esperar a que el DOM esté completamente cargado
    document.addEventListener('DOMContentLoaded', function() {
        // ============ REFERENCIAS A ELEMENTOS ============
        const themeSwitch = document.getElementById('themeSwitch');
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarToggleIcon = document.getElementById('sidebarToggleIcon');
        const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
        const greetingElement = document.getElementById('greeting-text');
        const currentTimeElement = document.getElementById('current-time');
        const checkPatientButtons = document.querySelectorAll('.check-patient');
        
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
        
        // ============ SALUDO DINÁMICO ============
        
        // Actualizar saludo según hora del día
        function updateGreeting() {
            const hour = new Date().getHours();
            let greeting = '';
            
            if (hour < 12) {
                greeting = 'Buenos días';
            } else if (hour < 19) {
                greeting = 'Buenas tardes';
            } else {
                greeting = 'Buenas noches';
            }
            
            greetingElement.textContent = greeting;
        }
        
        // ============ RELOJ EN TIEMPO REAL ============
        
        // Actualizar hora actual
        function updateCurrentTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('es-GT', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            
            if (currentTimeElement) {
                currentTimeElement.textContent = timeString;
            }
        }
        
        // ============ FUNCIONALIDAD DE PACIENTES ============
        
        // Verificar si un paciente existe o crear uno nuevo
        function handleCheckPatient(event) {
            event.preventDefault();
            
            const button = event.currentTarget;
            const nombre = button.getAttribute('data-nombre');
            const apellido = button.getAttribute('data-apellido');
            
            // En un sistema real, aquí se haría una petición AJAX
            // Por simplicidad, mostramos el modal directamente
            if (nombre && apellido) {
                document.getElementById('modal-nombre').value = nombre;
                document.getElementById('modal-apellido').value = apellido;
                
                // Mostrar modal usando Bootstrap
                const modal = new bootstrap.Modal(document.getElementById('newPatientModal'));
                modal.show();
            }
        }
        
        // ============ ANIMACIONES AL CARGAR ============
        
        // Animar elementos al cargar la página
        function animateOnLoad() {
            const cards = document.querySelectorAll('.stat-card, .appointments-section, .alert-card');
            
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
        updateGreeting();
        updateCurrentTime();
        animateOnLoad();
        
        // Configurar intervalo para el reloj
        setInterval(updateCurrentTime, 60000); // Actualizar cada minuto
        
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
        
        // Botones de verificación de pacientes
        checkPatientButtons.forEach(button => {
            button.addEventListener('click', handleCheckPatient);
        });
        
        // Cerrar sidebar al cambiar tamaño de ventana (responsive)
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992 && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
        });
        
        // ============ NOTIFICACIONES PARA ADMIN ============
        
        <?php if ($user_type === 'admin'): ?>
        // Notificación de jornada diaria para administradores
        document.addEventListener('DOMContentLoaded', function() {
            const lastSummaryDate = localStorage.getItem('lastJornadaSummary');
            const today = new Date().toISOString().split('T')[0];
            const currentHour = new Date().getHours();
            
            // Mostrar notificación si es después de las 8 AM y no se ha mostrado hoy
            if (currentHour >= 8 && lastSummaryDate !== today) {
                setTimeout(() => {
                    // Crear notificación no intrusiva
                    const notification = document.createElement('div');
                    notification.className = 'alert-card mb-4';
                    notification.style.borderLeft = '4px solid var(--color-info)';
                    notification.innerHTML = `
                        <div class="alert-header">
                            <i class="bi bi-info-circle text-info" style="font-size: 1.5rem;"></i>
                            <h3 class="alert-title">Reporte Diario</h3>
                            <button type="button" class="btn-close" onclick="this.parentElement.parentElement.remove()"></button>
                        </div>
                        <p class="text-muted mb-3">¿Desea generar el reporte de la jornada anterior?</p>
                        <div class="d-flex gap-2">
                            <button class="action-btn" style="padding: 0.5rem 1rem;" onclick="window.open('../reports/export_jornada.php?date=${today}', '_blank'); localStorage.setItem('lastJornadaSummary', '${today}'); this.parentElement.parentElement.remove();">
                                <i class="bi bi-file-earmark-pdf"></i>
                                Generar Reporte
                            </button>
                            <button class="btn btn-outline-secondary" style="padding: 0.5rem 1rem;" onclick="localStorage.setItem('lastJornadaSummary', '${today}'); this.parentElement.parentElement.remove();">
                                Más tarde
                            </button>
                        </div>
                    `;
                    
                    // Insertar después del header
                    const mainContent = document.querySelector('.main-content');
                    if (mainContent) {
                        mainContent.insertBefore(notification, mainContent.firstChild);
                    }
                }, 2000); // Mostrar después de 2 segundos
            }
        });
        <?php endif; ?>
        
        // ============ CONSOLA DE DESARROLLO ============
        
        console.log('Dashboard Minimalista - Centro Médico Herrera Saenz');
        console.log('Versión: 3.0 - Diseño con Efecto Mármol y Modo Noche');
        console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
        console.log('Rol: <?php echo htmlspecialchars($user_type); ?>');
    });
    
    // Manejar envío del formulario de nuevo paciente
    document.getElementById('newPatientForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Aquí iría la lógica AJAX para guardar el paciente
        // Por ahora, simplemente redirigimos
        this.submit();
    });
    </script>
    
    <!-- Incluir footer -->
    <?php include_once '../../includes/footer.php'; ?>
</body>
</html>