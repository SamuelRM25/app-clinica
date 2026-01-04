<?php
// laboratorio/index.php - Dashboard de Laboratorio
// Diseño Responsive, Barra Lateral Moderna, Efecto Mármol
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
    
    // ============ ESTADÍSTICAS DEL LABORATORIO ============
    
    // 1. Órdenes pendientes
    $stmt = $conn->query("SELECT COUNT(*) as total FROM ordenes_laboratorio WHERE estado = 'Pendiente'");
    $ordenes_pendientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // 2. Muestras recibidas
    $stmt = $conn->query("SELECT COUNT(*) as total FROM ordenes_laboratorio WHERE estado = 'Muestra_Recibida'");
    $muestras_recibidas = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // 3. Pendientes de validar (Pruebas en proceso pero no validadas)
    $stmt = $conn->query("SELECT COUNT(*) as total FROM orden_pruebas WHERE estado = 'En_Proceso'");
    $pendientes_validar = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // 4. Completadas hoy
    $stmt = $conn->query("SELECT COUNT(*) as total FROM ordenes_laboratorio WHERE DATE(fecha_orden) = CURDATE() AND estado = 'Completada'");
    $completadas_hoy = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    

    $total_appointments = 0;
    $active_hospitalizations = 0;
    $pending_purchases = 0;
    
    // 5. Total de órdenes del mes
    $month_start = date('Y-m-01');
    $month_end = date('Y-m-t');
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM ordenes_laboratorio 
        WHERE fecha_orden BETWEEN ? AND ?
    ");
    $stmt->execute([$month_start, $month_end]);
    $ordenes_mes = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // 6. Pruebas más solicitadas (top 5) - Usando fecha de la orden
    $stmt = $conn->query("
        SELECT cp.nombre_prueba, COUNT(op.id_orden_prueba) as cantidad
        FROM orden_pruebas op
        JOIN catalogo_pruebas cp ON op.id_prueba = cp.id_prueba
        JOIN ordenes_laboratorio ol ON op.id_orden = ol.id_orden
        WHERE MONTH(ol.fecha_orden) = MONTH(CURDATE())
        GROUP BY cp.id_prueba
        ORDER BY cantidad DESC
        LIMIT 5
    ");
    $pruebas_populares = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 7. Órdenes recientes
    $stmt = $conn->prepare("
        SELECT ol.*, 
               p.nombre, p.apellido, p.genero, p.fecha_nacimiento,
               u.nombre as doctor_nombre, u.apellido as doctor_apellido,
               COUNT(op.id_orden_prueba) as num_pruebas,
               TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) as edad
        FROM ordenes_laboratorio ol
        JOIN pacientes p ON ol.id_paciente = p.id_paciente
        LEFT JOIN usuarios u ON ol.id_doctor = u.idUsuario
        LEFT JOIN orden_pruebas op ON ol.id_orden = op.id_orden
        WHERE ol.estado IN ('Pendiente', 'Muestra_Recibida', 'En_Proceso', 'Completada')
        GROUP BY ol.id_orden
        ORDER BY 
            CASE 
                WHEN ol.estado = 'Pendiente' THEN 1
                WHEN ol.estado = 'Muestra_Recibida' THEN 2
                WHEN ol.estado = 'En_Proceso' THEN 3
                WHEN ol.estado = 'Completada' THEN 4
                ELSE 5
            END,
            ol.fecha_orden DESC
        LIMIT 20
    ");
    $stmt->execute();
    $ordenes_recientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 8. Órdenes con retraso (más de 2 días en estado Pendiente)
    $two_days_ago = date('Y-m-d', strtotime('-2 days'));
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM ordenes_laboratorio 
        WHERE estado = 'Pendiente' 
        AND DATE(fecha_orden) <= ?
    ");
    $stmt->execute([$two_days_ago]);
    $ordenes_retrasadas = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Título de la página
    $page_title = "Laboratorio - Centro Médico Herrera Saenz";
    
} catch (Exception $e) {
    // Manejo de errores
    error_log("Error en dashboard de laboratorio: " . $e->getMessage());
    die("Error al cargar el dashboard de laboratorio. Por favor, contacte al administrador.");
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Dashboard de Laboratorio - Centro Médico Herrera Saenz">
    <title><?php echo $page_title; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">
    
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Choices.js (para búsqueda en selects) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">
    
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
        
        /* Versiones RGB para opacidad */
        --color-primary-rgb: 13, 110, 253;
        --color-success-rgb: 25, 135, 84;
        --color-warning-rgb: 255, 193, 7;
        --color-danger-rgb: 220, 53, 69;
        --color-info-rgb: 13, 202, 240;
        --color-card-rgb: 255, 255, 255;
        
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
        
        --color-primary-rgb: 59, 130, 246;
        --color-success-rgb: 16, 185, 129;
        --color-warning-rgb: 245, 158, 11;
        --color-danger-rgb: 239, 68, 68;
        --color-info-rgb: 6, 182, 212;
        --color-card-rgb: 30, 41, 59;
        
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
        flex-direction: column;
        min-height: 100vh;
        position: relative;
        width: 100%;
        transition: all var(--transition-base);
    }
    
    /* User Avatar (Sidebar replacement) */
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
        transition: all var(--transition-base);
        min-height: 100vh;
        background-color: transparent;
        width: 100%;
    }
    

    
    /* ==========================================================================
       COMPONENTES DE DASHBOARD - ADAPTADOS PARA LABORATORIO
       ========================================================================== */
    
    /* Banner de bienvenida específico para laboratorio */
    .welcome-banner {
        background: linear-gradient(135deg, var(--color-primary), var(--color-info));
        color: white;
        border-radius: var(--radius-lg);
        padding: var(--space-xl);
        margin-bottom: var(--space-xl);
        position: relative;
        overflow: hidden;
    }
    
    .welcome-banner::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 300px;
        height: 300px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        transform: translate(30%, -30%);
    }
    
    .welcome-banner h1 {
        font-size: var(--font-size-3xl);
        font-weight: 700;
        margin-bottom: var(--space-sm);
    }
    
    .welcome-banner p {
        opacity: 0.9;
        font-size: var(--font-size-lg);
    }
    
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
        background: linear-gradient(90deg, var(--color-primary), var(--color-info));
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
    
    .stat-icon.danger {
        background: rgba(var(--color-danger-rgb), 0.1);
        color: var(--color-danger);
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
    
    /* Secciones */
    .appointments-section {
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: var(--space-lg);
        margin-bottom: var(--space-lg);
        transition: all var(--transition-base);
    }
    
    .appointments-section:hover {
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
        color: var(--color-primary);
    }
    
    .action-btn {
        display: inline-flex;
        align-items: center;
        gap: var(--space-sm);
        padding: var(--space-sm) var(--space-md);
        background: var(--color-primary);
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
        background: var(--color-primary);
        color: white;
        border-color: var(--color-primary);
    }
    
    /* Tablas específicas para laboratorio */
    .orders-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    
    .orders-table thead {
        background: var(--color-surface);
    }
    
    .orders-table th {
        padding: var(--space-md);
        text-align: left;
        font-weight: 600;
        color: var(--color-text);
        border-bottom: 2px solid var(--color-border);
        white-space: nowrap;
    }
    
    .orders-table td {
        padding: var(--space-md);
        border-bottom: 1px solid var(--color-border);
        vertical-align: middle;
    }
    
    .orders-table tbody tr {
        transition: all var(--transition-base);
    }
    
    .orders-table tbody tr:hover {
        background: var(--color-surface);
        transform: translateX(4px);
    }
    
    /* Badges de estado específicos para laboratorio */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.375rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .status-badge.pendiente {
        background: rgba(var(--color-warning-rgb), 0.1);
        color: var(--color-warning);
    }
    
    .status-badge.muestra {
        background: rgba(var(--color-info-rgb), 0.1);
        color: var(--color-info);
    }
    
    .status-badge.proceso {
        background: rgba(124, 144, 219, 0.2);
        color: #4f5b93;
    }
    
    .status-badge.completada {
        background: rgba(var(--color-success-rgb), 0.1);
        color: var(--color-success);
    }
    
    .status-badge.validada {
        background: rgba(var(--color-success-rgb), 0.2);
        color: var(--color-success);
    }
    
    /* Celdas personalizadas */
    .patient-cell {
        display: flex;
        align-items: center;
        gap: var(--space-md);
    }
    
    .patient-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--color-primary), var(--color-info));
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: var(--font-size-base);
        flex-shrink: 0;
    }
    
    .patient-info {
        min-width: 0;
    }
    
    .patient-name {
        font-weight: 600;
        color: var(--color-text);
        margin-bottom: 2px;
    }
    
    .patient-contact {
        color: var(--color-text-secondary);
        font-size: var(--font-size-sm);
    }
    
    /* Botones de acción */
    .action-buttons {
        display: flex;
        gap: var(--space-xs);
    }
    
    .btn-icon {
        width: 32px;
        height: 32px;
        border-radius: var(--radius-sm);
        border: 1px solid var(--color-border);
        background: var(--color-surface);
        color: var(--color-text);
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: all var(--transition-base);
    }
    
    .btn-icon:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-sm);
    }
    
    .btn-icon.edit:hover {
        background: var(--color-warning);
        color: white;
        border-color: var(--color-warning);
    }
    
    .btn-icon.pdf:hover {
        background: var(--color-danger);
        color: white;
        border-color: var(--color-danger);
    }
    
    .btn-icon.process:hover {
        background: var(--color-info);
        color: white;
        border-color: var(--color-info);
    }
    
    /* Grid de alertas */
    .alerts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: var(--space-lg);
        margin-bottom: var(--space-xl);
    }
    
    .alert-card {
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: var(--space-lg);
        transition: all var(--transition-base);
    }
    
    .alert-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    .alert-header {
        display: flex;
        align-items: center;
        gap: var(--space-md);
        margin-bottom: var(--space-lg);
    }
    
    .alert-icon {
        width: 40px;
        height: 40px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }
    
    .alert-icon.warning {
        background: rgba(var(--color-warning-rgb), 0.1);
        color: var(--color-warning);
    }
    
    .alert-icon.danger {
        background: rgba(var(--color-danger-rgb), 0.1);
        color: var(--color-danger);
    }
    
    .alert-icon.info {
        background: rgba(var(--color-info-rgb), 0.1);
        color: var(--color-info);
    }
    
    .alert-title {
        font-size: var(--font-size-lg);
        font-weight: 600;
        color: var(--color-text);
        margin: 0;
    }
    
    .alert-list {
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: var(--space-md);
    }
    
    .alert-item {
        padding: var(--space-md);
        background: var(--color-surface);
        border-radius: var(--radius-md);
        border-left: 4px solid var(--color-border);
        transition: all var(--transition-base);
    }
    
    .alert-item:hover {
        transform: translateX(4px);
        border-left-color: var(--color-warning);
    }
    
    .alert-item-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--space-xs);
    }
    
    .alert-item-name {
        font-weight: 500;
        color: var(--color-text);
    }
    
    .alert-badge {
        padding: 0.25em 0.5em;
        border-radius: var(--radius-sm);
        font-size: var(--font-size-xs);
        font-weight: 600;
    }
    
    .alert-badge.warning {
        background: rgba(var(--color-warning-rgb), 0.1);
        color: var(--color-warning);
    }
    
    .alert-badge.danger {
        background: rgba(var(--color-danger-rgb), 0.1);
        color: var(--color-danger);
    }
    
    .alert-item-details {
        display: flex;
        justify-content: space-between;
        font-size: var(--font-size-sm);
        color: var(--color-text-secondary);
    }
    
    /* Estado vacío */
    .empty-state {
        text-align: center;
        padding: var(--space-xl);
        color: var(--color-text-secondary);
    }
    
    .empty-icon {
        font-size: 3rem;
        color: var(--color-border);
        margin-bottom: var(--space-md);
        opacity: 0.5;
    }
    
    /* Panel de pruebas populares */
    .popular-tests {
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: var(--space-lg);
        margin-bottom: var(--space-lg);
    }
    
    .test-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: var(--space-md);
        border-bottom: 1px solid var(--color-border);
        transition: all var(--transition-base);
    }
    
    .test-item:last-child {
        border-bottom: none;
    }
    
    .test-item:hover {
        background: var(--color-surface);
        transform: translateX(4px);
    }
    
    .test-name {
        font-weight: 500;
        color: var(--color-text);
    }
    
    .test-count {
        font-weight: 600;
        color: var(--color-primary);
        background: rgba(var(--color-primary-rgb), 0.1);
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
    }
    
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
    
    /* Tablets y pantallas medianas */
    @media (max-width: 991px) {
        .dashboard-container {
            width: 100%;
        }
        
        .main-content {
            padding: var(--space-md);
        }
        
        .mobile-toggle {
            display: none;
        }
        
        .header-content {
            padding: var(--space-md);
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: var(--space-md);
        }
        
        .alerts-grid {
            grid-template-columns: 1fr;
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
        
        .welcome-banner {
            padding: var(--space-lg);
        }
        
        .welcome-banner h1 {
            font-size: var(--font-size-2xl);
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
        
        .orders-table {
            font-size: var(--font-size-sm);
        }
        
        .orders-table th,
        .orders-table td {
            padding: var(--space-sm);
        }
        
        .patient-cell {
            flex-direction: column;
            align-items: flex-start;
            gap: var(--space-xs);
        }
        
        .patient-avatar {
            width: 32px;
            height: 32px;
            font-size: var(--font-size-sm);
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
        
        .welcome-banner {
            text-align: center;
            padding: var(--space-lg);
        }
        
        .welcome-banner::before {
            display: none;
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
        
        .alert-card,
        .appointments-section {
            padding: var(--space-md);
        }
        
        .section-title {
            font-size: var(--font-size-base);
        }
        
        .action-buttons {
            flex-direction: column;
            gap: var(--space-xs);
        }
        
        .btn-icon {
            width: 28px;
            height: 28px;
            font-size: 0.875rem;
        }
        
        .status-badge {
            font-size: 0.65rem;
            padding: 0.25rem 0.5rem;
        }
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
        animation: fadeInUp 0.6s ease-out forwards;
    }
    
    .delay-1 { animation-delay: 0.1s; }
    .delay-2 { animation-delay: 0.2s; }
    .delay-3 { animation-delay: 0.3s; }
    .delay-4 { animation-delay: 0.4s; }
    
    /* ==========================================================================
       ESTADOS DE CARGA
       ========================================================================== */
    .loading {
        position: relative;
        overflow: hidden;
    }
    
    .loading::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
        animation: loading 1.5s infinite;
    }
    
    @keyframes loading {
        0% { left: -100%; }
        100% { left: 100%; }
    }
    
    /* ==========================================================================
       UTILIDADES
       ========================================================================== */
    .text-primary { color: var(--color-primary); }
    .text-success { color: var(--color-success); }
    .text-warning { color: var(--color-warning); }
    .text-danger { color: var(--color-danger); }
    .text-info { color: var(--color-info); }
    .text-muted { color: var(--color-text-secondary); }
    
    .bg-primary { background-color: var(--color-primary); }
    .bg-success { background-color: var(--color-success); }
    .bg-warning { background-color: var(--color-warning); }
    .bg-danger { background-color: var(--color-danger); }
    .bg-info { background-color: var(--color-info); }
    
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
    
    /* ==========================================================================
       PRINT STYLES
       ========================================================================== */
    @media print {
        .dashboard-header,
        .theme-btn,
        .logout-btn,
        .action-btn {
            display: none !important;
        }
        
        .main-content {
            margin-left: 0 !important;
            padding: 0 !important;
        }
        
        .marble-effect {
            display: none;
        }
        
        body {
            background: white !important;
            color: black !important;
        }
        
        .stat-card,
        .appointments-section,
        .alert-card {
            break-inside: avoid;
            border: 1px solid #ddd !important;
            box-shadow: none !important;
        }
    }
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
                <!-- Botón hamburguesa para móvil -->
                <button class="mobile-toggle" id="mobileSidebarToggle" aria-label="Abrir menú">
                    <i class="bi bi-list"></i>
                </button>
                
                <!-- Logo -->
                <div class="brand-container">
                    <img src="../../assets/img/herrerasaenz.png" alt="Centro Médico Herrera Saenz" class="brand-logo">
                </div>
                
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
                            <span class="header-role">Laboratorio</span>
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
            <!-- Banner de bienvenida -->
            <div class="welcome-banner animate-in">
                <h1>Laboratorio Clínico</h1>
                <p>Gestión de órdenes y resultados de laboratorio</p>
            </div>
            
            <!-- Alertas importantes -->
            <?php if ($ordenes_retrasadas > 0): ?>
            <div class="alert-card mb-4 animate-in delay-1">
                <div class="alert-header">
                    <div class="alert-icon warning">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <h3 class="alert-title">Órdenes con Retraso</h3>
                </div>
                <p class="text-muted mb-0">
                    Hay <strong><?php echo $ordenes_retrasadas; ?></strong> órdenes con más de 2 días en estado "Pendiente".
                    <a href="?filter=retraso" class="text-primary text-decoration-none ms-1">
                        Revisar <i class="bi bi-arrow-right"></i>
                    </a>
                </p>
            </div>
            <?php endif; ?>
            
            <!-- Estadísticas principales -->
            <div class="stats-grid">
                <!-- Órdenes pendientes -->
                <div class="stat-card animate-in delay-1">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Órdenes Pendientes</div>
                            <div class="stat-value"><?php echo $ordenes_pendientes; ?></div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="bi bi-clock-history"></i>
                        </div>
                    </div>
                    <div class="stat-change">
                        <i class="bi bi-calendar-week"></i>
                        <span>Esperando procesamiento</span>
                    </div>
                </div>
                
                <!-- Muestras recibidas -->
                <div class="stat-card animate-in delay-2">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Muestras Recibidas</div>
                            <div class="stat-value"><?php echo $muestras_recibidas; ?></div>
                        </div>
                        <div class="stat-icon info">
                            <i class="bi bi-droplet"></i>
                        </div>
                    </div>
                    <div class="stat-change">
                        <i class="bi bi-check-circle"></i>
                        <span>Listas para análisis</span>
                    </div>
                </div>
                
                <!-- Por validar -->
                <div class="stat-card animate-in delay-3">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Por Validar</div>
                            <div class="stat-value"><?php echo $pendientes_validar; ?></div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="bi bi-clipboard-check"></i>
                        </div>
                    </div>
                    <div class="stat-change">
                        <i class="bi bi-shield-check"></i>
                        <span>Esperando validación</span>
                    </div>
                </div>
                
                <!-- Completadas hoy -->
                <div class="stat-card animate-in delay-4">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Completadas Hoy</div>
                            <div class="stat-value"><?php echo $completadas_hoy; ?></div>
                        </div>
                        <div class="stat-icon success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-calendar-day"></i>
                        <span><?php echo date('d/m/Y'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Panel de dos columnas: Órdenes y Pruebas Populares -->
            <div class="row gap-4 mb-4">
                <!-- Órdenes Recientes -->
                <div class="col-lg-8">
                    <section class="appointments-section animate-in delay-1">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="bi bi-list-ul section-title-icon"></i>
                                Órdenes Activas
                            </h3>
                            <div class="d-flex gap-2">
                                <?php if ($user_type === 'admin'): ?>
                                <a href="catalogo_pruebas.php" class="action-btn secondary">
                                    <i class="bi bi-gear"></i>
                                    Catálogo
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($user_type === 'doc' || $user_type === 'admin'): ?>
                                <a href="crear_orden.php" class="action-btn">
                                    <i class="bi bi-plus-lg"></i>
                                    Nueva Orden
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (count($ordenes_recientes) > 0): ?>
                            <div class="table-responsive">
                                <table class="orders-table">
                                    <thead>
                                        <tr>
                                            <th>Orden #</th>
                                            <th>Paciente</th>
                                            <th>Doctor</th>
                                            <th>Fecha</th>
                                            <th>Pruebas</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ordenes_recientes as $orden): ?>
                                            <?php 
                                            $patient_name = htmlspecialchars($orden['nombre'] . ' ' . $orden['apellido']);
                                            $patient_initials = strtoupper(
                                                substr($orden['nombre'], 0, 1) . 
                                                substr($orden['apellido'], 0, 1)
                                            );
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($orden['numero_orden']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">ID: <?php echo $orden['id_orden']; ?></small>
                                                </td>
                                                <td>
                                                    <div class="patient-cell">
                                                        <div class="patient-avatar">
                                                            <?php echo $patient_initials; ?>
                                                        </div>
                                                        <div class="patient-info">
                                                            <div class="patient-name"><?php echo $patient_name; ?></div>
                                                            <div class="patient-contact">
                                                                <?php echo $orden['edad']; ?> años - <?php echo htmlspecialchars($orden['genero']); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($orden['doctor_nombre']): ?>
                                                        <small class="d-block">Dr. <?php echo htmlspecialchars($orden['doctor_nombre'] . ' ' . $orden['doctor_apellido']); ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo date('d/m/Y', strtotime($orden['fecha_orden'])); ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo date('H:i', strtotime($orden['fecha_orden'])); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $orden['num_pruebas']; ?></span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $estado_class = '';
                                                    $estado_text = '';
                                                    switch ($orden['estado']) {
                                                        case 'Pendiente':
                                                            $estado_class = 'pendiente';
                                                            $estado_text = 'Pendiente';
                                                            break;
                                                        case 'Muestra_Recibida':
                                                            $estado_class = 'muestra';
                                                            $estado_text = 'Muestra Recibida';
                                                            break;
                                                        case 'En_Proceso':
                                                            $estado_class = 'proceso';
                                                            $estado_text = 'En Proceso';
                                                            break;
                                                        case 'Completada':
                                                            $estado_class = 'completada';
                                                            $estado_text = 'Completada';
                                                            break;
                                                        case 'Validada':
                                                            $estado_class = 'validada';
                                                            $estado_text = 'Validada';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="status-badge <?php echo $estado_class; ?>">
                                                        <?php echo $estado_text; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <?php if ($orden['estado'] === 'Validada' || $orden['estado'] === 'Completada'): ?>
                                                            <a href="imprimir_resultados.php?id=<?php echo $orden['id_orden']; ?>" 
                                                               class="btn-icon pdf" 
                                                               title="Ver Resultados PDF"
                                                               target="_blank">
                                                                <i class="bi bi-file-earmark-pdf"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="procesar_orden.php?id=<?php echo $orden['id_orden']; ?>" 
                                                               class="btn-icon process" 
                                                               title="Procesar orden">
                                                                <i class="bi bi-pencil-square"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="ver_orden.php?id=<?php echo $orden['id_orden']; ?>" 
                                                           class="btn-icon" 
                                                           title="Ver detalles">
                                                            <i class="bi bi-eye"></i>
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
                                    <i class="bi bi-inbox"></i>
                                </div>
                                <h4 class="text-muted mb-2">No hay órdenes activas</h4>
                                <p class="text-muted mb-3">Las órdenes pendientes aparecerán aquí</p>
                                <a href="crear_orden.php" class="action-btn">
                                    <i class="bi bi-plus-lg"></i>
                                    Crear Primera Orden
                                </a>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
                
                <!-- Pruebas Populares y Acciones Rápidas -->
                <div class="col-lg-4">
                    <!-- Pruebas más solicitadas -->
                    <section class="popular-tests animate-in delay-2">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="bi bi-graph-up-arrow section-title-icon"></i>
                                Pruebas del Mes
                            </h3>
                        </div>
                        
                        <?php if (count($pruebas_populares) > 0): ?>
                            <div class="test-list">
                                <?php foreach ($pruebas_populares as $prueba): ?>
                                    <div class="test-item">
                                        <span class="test-name"><?php echo htmlspecialchars($prueba['nombre_prueba']); ?></span>
                                        <span class="test-count"><?php echo $prueba['cantidad']; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state py-3">
                                <i class="bi bi-bar-chart text-muted"></i>
                                <p class="text-muted mb-0 mt-2">No hay datos del mes</p>
                            </div>
                        <?php endif; ?>
                    </section>
                    
                    <!-- Acciones rápidas -->
                    <div class="stat-card mt-4 animate-in delay-3">
                        <div class="stat-header">
                            <h3 class="section-title mb-0">
                                <i class="bi bi-lightning-charge section-title-icon"></i>
                                Acciones Rápidas
                            </h3>
                        </div>
                        <div class="d-flex flex-column gap-2 mt-3">
                            <a href="buscar_paciente.php" class="action-btn secondary">
                                <i class="bi bi-search"></i>
                                Buscar Paciente
                            </a>
                            <a href="registrar_muestra.php" class="action-btn secondary">
                                <i class="bi bi-droplet"></i>
                                Registrar Muestra
                            </a>
                            <a href="reportes_diarios.php" class="action-btn secondary">
                                <i class="bi bi-file-earmark-text"></i>
                                Reporte Diario
                            </a>
                            <?php if ($user_type === 'admin'): ?>
                            <a href="configuracion.php" class="action-btn secondary">
                                <i class="bi bi-sliders"></i>
                                Configurar Laboratorio
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Resumen del mes -->
            <div class="stat-card animate-in delay-4">
                <div class="stat-header">
                    <div>
                        <div class="stat-title">Resumen del Mes</div>
                        <div class="stat-value"><?php echo $ordenes_mes; ?> Órdenes</div>
                    </div>
                    <div class="stat-icon primary">
                        <i class="bi bi-calendar-month"></i>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-3 text-center">
                        <div class="text-primary fw-bold fs-4"><?php echo $ordenes_pendientes; ?></div>
                        <div class="text-muted">Pendientes</div>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="text-info fw-bold fs-4"><?php echo $muestras_recibidas; ?></div>
                        <div class="text-muted">Muestras</div>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="text-warning fw-bold fs-4"><?php echo $pendientes_validar; ?></div>
                        <div class="text-muted">Por Validar</div>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="text-success fw-bold fs-4"><?php echo $completadas_hoy; ?></div>
                        <div class="text-muted">Hoy</div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- JavaScript Optimizado -->
    <script>
    // Dashboard de Laboratorio Reingenierizado
    
    (function() {
        'use strict';
        
        // ==========================================================================
        // CONFIGURACIÓN Y CONSTANTES
        // ==========================================================================
        const CONFIG = {
            themeKey: 'dashboard-theme',

            transitionDuration: 300,
            animationDelay: 100
        };
        
        // ==========================================================================
        // REFERENCIAS A ELEMENTOS DOM
        // ==========================================================================
        const DOM = {
            html: document.documentElement,
            body: document.body,
            themeSwitch: document.getElementById('themeSwitch')
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
                const savedTheme = localStorage.getItem(CONFIG.themeKey);
                if (savedTheme) return savedTheme;
                
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (prefersDark) return 'dark';
                
                return 'light';
            }
            
            applyTheme(theme) {
                DOM.html.setAttribute('data-theme', theme);
                localStorage.setItem(CONFIG.themeKey, theme);
                
                const metaTheme = document.querySelector('meta[name="theme-color"]');
                if (metaTheme) {
                    metaTheme.setAttribute('content', theme === 'dark' ? '#0f172a' : '#ffffff');
                }
            }
            
            toggleTheme() {
                const newTheme = this.theme === 'light' ? 'dark' : 'light';
                this.theme = newTheme;
                this.applyTheme(newTheme);
                
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
                
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                    if (!localStorage.getItem(CONFIG.themeKey)) {
                        this.theme = e.matches ? 'dark' : 'light';
                        this.applyTheme(this.theme);
                    }
                });
            }
        }
        
        // ==========================================================================
        // COMPONENTES DINÁMICOS
        // ==========================================================================
        class DynamicComponents {
            constructor() {
                this.setupAnimations();
                this.setupTableInteractions();
                this.setupQuickActions();
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
                
                document.querySelectorAll('.stat-card, .appointments-section, .alert-card, .popular-tests').forEach(el => {
                    observer.observe(el);
                });
            }
            
            setupTableInteractions() {
                const tableRows = document.querySelectorAll('.orders-table tbody tr');
                tableRows.forEach(row => {
                    row.addEventListener('click', (e) => {
                        // Solo si no se hizo clic en un botón de acción
                        if (!e.target.closest('.btn-icon') && !e.target.closest('a')) {
                            const orderId = row.querySelector('td:first-child small')?.textContent?.replace('ID: ', '');
                            if (orderId) {
                                window.location.href = `ver_orden.php?id=${orderId}`;
                            }
                        }
                    });
                });
            }
            
            setupQuickActions() {
                // Agregar efecto hover a las acciones rápidas
                const quickActions = document.querySelectorAll('.action-btn.secondary');
                quickActions.forEach(btn => {
                    btn.addEventListener('mouseenter', () => {
                        btn.style.transform = 'translateY(-2px)';
                    });
                    btn.addEventListener('mouseleave', () => {
                        btn.style.transform = 'translateY(0)';
                    });
                });
            }
        }
        
        // ==========================================================================
        // INICIALIZACIÓN DE LA APLICACIÓN
        // ==========================================================================
        document.addEventListener('DOMContentLoaded', () => {
            const themeManager = new ThemeManager();
            const dynamicComponents = new DynamicComponents();
            
            window.laboratoryDashboard = {
                theme: themeManager,
                components: dynamicComponents
            };
            
            console.log('Dashboard de Laboratorio inicializado');
            console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
            console.log('Tema: ' + themeManager.theme);
        });
        
        // ==========================================================================
        // POLYFILLS PARA NAVEGADORES ANTIGUOS
        // ==========================================================================
        if (!NodeList.prototype.forEach) {
            NodeList.prototype.forEach = Array.prototype.forEach;
        }
        
    })();
    
    // Efectos de carga para formularios
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Procesando...';
                submitBtn.disabled = true;
                
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 3000);
            }
        });
    });
    
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
        
        /* Efectos adicionales para laboratorio */
        .orders-table tbody tr {
            cursor: pointer;
        }
        
        .orders-table tbody tr:hover {
            background-color: rgba(var(--color-primary-rgb), 0.05);
        }
    `;
    document.head.appendChild(style);
    </script>
</body>
</html>