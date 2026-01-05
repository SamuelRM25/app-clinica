<?php
// inventory/index.php - Módulo de Inventario Reingenierizado
// Centro Médico Herrera Saenz - Sistema de Gestión Médica
// Versión: 4.0 - Mismo diseño que Dashboard Principal

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
    
    // ============ ESTADÍSTICAS DEL INVENTARIO ============
    
    // 1. Total de items en inventario
    $stmt = $conn->query("SELECT COUNT(*) as count FROM inventario");
    $total_items = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 2. Items agotados (stock = 0)
    $stmt = $conn->query("SELECT COUNT(*) as count FROM inventario WHERE cantidad_med = 0");
    $out_of_stock = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 3. Items con stock bajo (< 10 unidades)
    $stmt = $conn->query("SELECT COUNT(*) as count FROM inventario WHERE cantidad_med > 0 AND cantidad_med <= 10");
    $low_stock = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 4. Items próximos a caducar (30 días)
    $today = date('Y-m-d');
    $next_month = date('Y-m-d', strtotime('+30 days'));
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventario WHERE fecha_vencimiento BETWEEN ? AND ? AND cantidad_med > 0");
    $stmt->execute([$today, $next_month]);
    $expiring_soon = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 5. Items vencidos
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventario WHERE fecha_vencimiento < ? AND cantidad_med > 0");
    $stmt->execute([$today]);
    $expired = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 6. Items pendientes de recepción
    $stmt = $conn->query("SELECT COUNT(*) as count FROM inventario WHERE estado = 'Pendiente'");
    $pending_receipt = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    

    $total_appointments = 0;
    $active_hospitalizations = 0;
    $pending_purchases = $pending_receipt;
    
    // ============ INVENTARIO COMPLETO ============
    
    // Obtener todos los medicamentos para la tabla
    $stmt = $conn->query("SELECT * FROM inventario ORDER BY fecha_vencimiento ASC");
    $inventory_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    <meta name="description" content="Módulo de Inventario - Centro Médico Herrera Saenz - Sistema de gestión médica">
    <title><?php echo $page_title; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">
    
    <!-- Google Fonts - Inter (moderno y legible) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Bootstrap CSS (Required for Modals) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- SweetAlert2 -->
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
        flex-direction: column; /* Apilar Header y Main verticalmente */
        min-height: 100vh;
        position: relative;
        width: 100%; /* Asegurar que no se desborde */
        transition: all var(--transition-base);
    }
    
    /* User Details (Footer replacement) */
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
        /* Usar fallback sólido si rgba falla, pero definir rgb variables arriba */
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
       COMPONENTES DE DASHBOARD
       ========================================================================== */
    
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
    
    /* Tablas */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .appointments-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    
    .appointments-table thead {
        background: var(--color-surface);
    }
    
    .appointments-table th {
        padding: var(--space-md);
        text-align: left;
        font-weight: 600;
        color: var(--color-text);
        border-bottom: 2px solid var(--color-border);
        white-space: nowrap;
    }
    
    .appointments-table td {
        padding: var(--space-md);
        border-bottom: 1px solid var(--color-border);
        vertical-align: middle;
    }
    
    .appointments-table tbody tr {
        transition: all var(--transition-base);
    }
    
    .appointments-table tbody tr:hover {
        background: var(--color-surface);
        transform: translateX(4px);
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
    
    .time-badge {
        display: inline-flex;
        align-items: center;
        gap: var(--space-xs);
        padding: var(--space-xs) var(--space-sm);
        background: var(--color-surface);
        color: var(--color-text);
        border-radius: var(--radius-sm);
        font-size: var(--font-size-sm);
        font-weight: 500;
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
    
    .btn-icon.history:hover {
        background: var(--color-info);
        color: white;
        border-color: var(--color-info);
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
    
    .alert-badge.expired {
        background: rgba(var(--color-danger-rgb), 0.1);
        color: var(--color-danger);
    }
    
    .alert-item-details {
        display: flex;
        justify-content: space-between;
        font-size: var(--font-size-sm);
        color: var(--color-text-secondary);
    }
    
    .no-alerts {
        text-align: center;
        padding: var(--space-lg);
        color: var(--color-text-secondary);
    }
    
    .no-alerts-icon {
        font-size: 2rem;
        color: var(--color-success);
        margin-bottom: var(--space-md);
        opacity: 0.5;
    }
    
    /* ==========================================================================
       ESTILOS ESPECÍFICOS PARA INVENTARIO
       ========================================================================== */
    
    /* Barra de búsqueda y filtros */
    .search-container {
        margin-bottom: var(--space-lg);
    }
    
    .search-box {
        position: relative;
        margin-bottom: var(--space-md);
    }
    
    .search-input {
        width: 100%;
        padding: 0.75rem 1rem 0.75rem 3rem;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        background: var(--color-card);
        color: var(--color-text);
        font-size: var(--font-size-base);
        transition: all var(--transition-base);
    }
    
    .search-input:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.1);
    }
    
    .search-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--color-text-secondary);
    }
    
    .filter-tabs {
        display: flex;
        gap: var(--space-sm);
        flex-wrap: wrap;
        margin-bottom: var(--space-md);
    }
    
    .filter-tab {
        padding: 0.5rem 1rem;
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        color: var(--color-text);
        font-size: var(--font-size-sm);
        cursor: pointer;
        transition: all var(--transition-base);
    }
    
    .filter-tab:hover {
        background: var(--color-primary);
        color: white;
        border-color: var(--color-primary);
    }
    
    .filter-tab.active {
        background: var(--color-primary);
        color: white;
        border-color: var(--color-primary);
    }
    
    /* Indicadores de estado en tabla */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.25rem 0.5rem;
        border-radius: var(--radius-sm);
        font-size: var(--font-size-xs);
        font-weight: 500;
    }
    
    .status-good {
        background: rgba(var(--color-success-rgb), 0.1);
        color: var(--color-success);
        border: 1px solid rgba(var(--color-success-rgb), 0.2);
    }
    
    .status-warning {
        background: rgba(var(--color-warning-rgb), 0.1);
        color: var(--color-warning);
        border: 1px solid rgba(var(--color-warning-rgb), 0.2);
    }
    
    .status-danger {
        background: rgba(var(--color-danger-rgb), 0.1);
        color: var(--color-danger);
        border: 1px solid rgba(var(--color-danger-rgb), 0.2);
    }
    
    .status-info {
        background: rgba(var(--color-info-rgb), 0.1);
        color: var(--color-info);
        border: 1px solid rgba(var(--color-info-rgb), 0.2);
    }
    
    /* Botones específicos de inventario */
    .btn-icon.receive:hover {
        background: var(--color-success);
        color: white;
        border-color: var(--color-success);
    }
    
    .btn-icon.delete:hover {
        background: var(--color-danger);
        color: white;
        border-color: var(--color-danger);
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
        
        .filter-tabs {
            justify-content: center;
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
        
        .appointments-table {
            font-size: var(--font-size-sm);
        }
        
        .appointments-table th,
        .appointments-table td {
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
        
        .filter-tabs {
            flex-direction: column;
        }
        
        .filter-tab {
            width: 100%;
            text-align: center;
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
                            <span class="header-role"><?php echo htmlspecialchars($user_specialty); ?></span>
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
            <!-- Notificación de compras pendientes -->
            <?php if ($pending_receipt > 0): ?>
            <div class="alert-card mb-4 animate-in delay-1">
                <div class="alert-header">
                    <div class="alert-icon warning">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <h3 class="alert-title">Recepción Pendiente</h3>
                </div>
                <p class="text-muted mb-0">
                    Hay <strong><?php echo $pending_receipt; ?></strong> productos pendientes de recepción en inventario.
                </p>
            </div>
            <?php endif; ?>
            
            <!-- Bienvenida personalizada -->
            <div class="stat-card mb-4 animate-in">
                <div class="stat-header">
                    <div>
                        <h2 id="greeting" class="stat-value" style="font-size: 1.75rem; margin-bottom: 0.5rem;">
                            <span id="greeting-text">Gestión de Inventario</span>
                        </h2>
                        <p class="text-muted mb-0">
                            <i class="bi bi-box-seam me-1"></i> Control y administración de medicamentos e insumos médicos
                            <span class="mx-2">•</span>
                            <i class="bi bi-calendar-check me-1"></i> <?php echo date('d/m/Y'); ?>
                            <span class="mx-2">•</span>
                            <i class="bi bi-person me-1"></i> <?php echo htmlspecialchars($user_name); ?>
                        </p>
                    </div>
                    <div class="d-none d-md-block">
                        <i class="bi bi-box-seam text-primary" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
            
            <!-- Estadísticas principales -->
            <div class="stats-grid">
                <!-- Total de items -->
                <div class="stat-card animate-in delay-1">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Total de Items</div>
                            <div class="stat-value"><?php echo $total_items; ?></div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="bi bi-box-seam"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-arrow-up-right"></i>
                        <span>En inventario</span>
                    </div>
                </div>
                
                <!-- Agotados -->
                <div class="stat-card animate-in delay-2">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Agotados</div>
                            <div class="stat-value"><?php echo $out_of_stock; ?></div>
                        </div>
                        <div class="stat-icon danger">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-exclamation-triangle"></i>
                        <span>Sin stock disponible</span>
                    </div>
                </div>
                
                <!-- Stock bajo -->
                <div class="stat-card animate-in delay-3">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Stock Bajo</div>
                            <div class="stat-value"><?php echo $low_stock; ?></div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="bi bi-exclamation-circle"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-exclamation-circle"></i>
                        <span>Menos de 10 unidades</span>
                    </div>
                </div>
                
                <!-- Por vencer -->
                <div class="stat-card animate-in delay-4">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Por Vencer</div>
                            <div class="stat-value"><?php echo $expiring_soon; ?></div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="bi bi-clock-history"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-clock-history"></i>
                        <span>Próximos 30 días</span>
                    </div>
                </div>
            </div>
            
            <!-- Barra de búsqueda y acciones -->
            <div class="appointments-section animate-in delay-1">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-search section-title-icon"></i>
                        Buscar y Filtrar
                    </h3>
                    <div class="action-buttons">
                        <a href="generate_report.php" class="action-btn" style="background: var(--color-secondary);">
                            <i class="bi bi-file-earmark-spreadsheet"></i>
                            Exportar CSV
                        </a>
                        <button type="button" class="action-btn" data-bs-toggle="modal" data-bs-target="#addMedicineModal">
                            <i class="bi bi-plus-circle"></i>
                            Nuevo Medicamento
                        </button>
                    </div>
                </div>
                
                <div class="search-container">
                    <div class="search-box">
                        <i class="bi bi-search search-icon"></i>
                        <input type="text" class="search-input" id="searchInput" placeholder="Buscar por nombre, molécula o casa farmacéutica...">
                    </div>
                    
                    <div class="filter-tabs">
                        <button class="filter-tab active" data-filter="all">
                            <i class="bi bi-grid"></i>
                            Todos
                        </button>
                        <button class="filter-tab" data-filter="adequate">
                            <i class="bi bi-check-circle"></i>
                            En Stock
                        </button>
                        <button class="filter-tab" data-filter="low">
                            <i class="bi bi-exclamation-circle"></i>
                            Stock Bajo
                        </button>
                        <button class="filter-tab" data-filter="out">
                            <i class="bi bi-x-circle"></i>
                            Agotados
                        </button>
                        <button class="filter-tab" data-filter="expiring">
                            <i class="bi bi-clock-history"></i>
                            Por Vencer
                        </button>
                        <button class="filter-tab" data-filter="expired">
                            <i class="bi bi-calendar-x"></i>
                            Vencidos
                        </button>
                        <button class="filter-tab" data-filter="pending">
                            <i class="bi bi-box-arrow-in-down"></i>
                            Pendientes
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Tabla de inventario -->
            <section class="appointments-section animate-in delay-2">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-box-seam section-title-icon"></i>
                        Inventario de Medicamentos
                    </h3>
                    <div class="d-flex gap-2">
                        <div class="badge bg-primary d-flex align-items-center p-2">
                            <i class="bi bi-box-seam me-2"></i>
                            <?php echo $total_items; ?> Items
                        </div>
                    </div>
                </div>
                
                <?php if (count($inventory_items) > 0): ?>
                    <div class="table-responsive">
                        <table class="appointments-table" id="inventoryTable">
                            <thead>
                                <tr>
                                    <th>Medicamento</th>
                                    <th>Molécula</th>
                                    <th>Presentación</th>
                                    <th>Stock</th>
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
                                    $status_class = 'status-good';
                                    $status_icon = 'bi-check-circle';
                                    
                                    if ($estado === 'Pendiente') {
                                        $status_class = 'status-info';
                                        $status_icon = 'bi-box-arrow-in-down';
                                    } elseif ($cantidad == 0) {
                                        $status_class = 'status-danger';
                                        $status_icon = 'bi-x-circle';
                                    } elseif ($cantidad <= 10) {
                                        $status_class = 'status-warning';
                                        $status_icon = 'bi-exclamation-circle';
                                    }
                                    
                                    // Determinar estado de vencimiento
                                    $expiry_class = 'status-good';
                                    $expiry_text = 'Válido';
                                    
                                    if ($item['fecha_vencimiento']) {
                                        $expiry_date = new DateTime($item['fecha_vencimiento']);
                                        $today_dt = new DateTime();
                                        $days_diff = $today_dt->diff($expiry_date)->days;
                                        $is_expired = $expiry_date < $today_dt;
                                        
                                        if ($is_expired) {
                                            $expiry_class = 'status-danger';
                                            $expiry_text = 'Vencido';
                                        } elseif ($days_diff <= 30) {
                                            $expiry_class = 'status-warning';
                                            $expiry_text = $days_diff . ' días';
                                        }
                                    } else {
                                        if ($estado === 'Pendiente') {
                                            $expiry_text = 'Por definir';
                                        }
                                    }
                                    
                                    // Data attributes para filtrado
                                    $data_attrs = "data-stock='{$status_class}' data-expiry='{$expiry_class}'";
                                    ?>
                                    <tr <?php echo $data_attrs; ?>>
                                        <td>
                                            <div class="patient-cell">
                                                <div class="patient-avatar" style="background: var(--color-primary);">
                                                    <i class="bi bi-capsule"></i>
                                                </div>
                                                <div class="patient-info">
                                                    <div class="patient-name"><?php echo htmlspecialchars($item['nom_medicamento']); ?></div>
                                                    <div class="patient-contact">
                                                        <?php echo htmlspecialchars($item['casa_farmaceutica']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-muted"><?php echo htmlspecialchars($item['mol_medicamento']); ?></span>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($item['presentacion_med']); ?>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $status_class; ?>">
                                                <i class="bi <?php echo $status_icon; ?>"></i>
                                                <?php echo $item['cantidad_med']; ?> unidades
                                            </span>
                                            <?php if ($estado === 'Pendiente'): ?>
                                                <div class="mt-1">
                                                    <span class="status-badge status-info" style="font-size: 0.75rem;">
                                                        Pendiente de recepción
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($item['fecha_vencimiento']): ?>
                                                <div class="mb-1"><?php echo date('d/m/Y', strtotime($item['fecha_vencimiento'])); ?></div>
                                                <span class="status-badge <?php echo $expiry_class; ?>">
                                                    <?php echo $expiry_text; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
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
                        <button type="button" class="action-btn" data-bs-toggle="modal" data-bs-target="#addMedicineModal">
                            <i class="bi bi-plus-circle"></i>
                            Agregar primer medicamento
                        </button>
                    </div>
                <?php endif; ?>
            </section>
            
            <!-- Panel de alertas -->
            <div class="alerts-grid animate-in delay-3">
                <!-- Medicamentos por caducar -->
                <div class="alert-card">
                    <div class="alert-header">
                        <div class="alert-icon warning">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <h3 class="alert-title">Caducidad Próxima</h3>
                    </div>
                    
                    <?php 
                    // Obtener medicamentos próximos a caducar
                    $stmt = $conn->prepare("
                        SELECT id_inventario, nom_medicamento, fecha_vencimiento, cantidad_med 
                        FROM inventario 
                        WHERE fecha_vencimiento BETWEEN ? AND ? AND cantidad_med > 0
                        ORDER BY fecha_vencimiento ASC
                        LIMIT 5
                    ");
                    $stmt->execute([$today, $next_month]);
                    $expiring_medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    
                    <?php if (count($expiring_medications) > 0): ?>
                        <ul class="alert-list">
                            <?php foreach ($expiring_medications as $medication): ?>
                                <?php 
                                $expiry_date = new DateTime($medication['fecha_vencimiento']);
                                $today_dt = new DateTime();
                                $days_diff = $today_dt->diff($expiry_date)->days;
                                ?>
                                <li class="alert-item">
                                    <div class="alert-item-header">
                                        <span class="alert-item-name"><?php echo htmlspecialchars($medication['nom_medicamento']); ?></span>
                                        <span class="alert-badge warning">
                                            <?php echo $days_diff; ?> días
                                        </span>
                                    </div>
                                    <div class="alert-item-details">
                                        <span>Vence: <?php echo $expiry_date->format('d/m/Y'); ?></span>
                                        <span>Stock: <?php echo $medication['cantidad_med']; ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
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
                    
                    <?php 
                    // Obtener medicamentos con stock bajo
                    $stmt = $conn->query("
                        SELECT id_inventario, nom_medicamento, cantidad_med 
                        FROM inventario 
                        WHERE cantidad_med > 0 AND cantidad_med <= 10
                        ORDER BY cantidad_med ASC
                        LIMIT 5
                    ");
                    $low_stock_medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    
                    <?php if (count($low_stock_medications) > 0): ?>
                        <ul class="alert-list">
                            <?php foreach ($low_stock_medications as $medication): ?>
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
    
    <!-- JavaScript Optimizado -->
    <script>
    // Módulo de Inventario Reingenierizado - Centro Médico Herrera Saenz
    
    (function() {
        'use strict';
        
        // ==========================================================================
        // CONFIGURACIÓN Y CONSTANTES
        // ==========================================================================
        const CONFIG = {
            themeKey: 'dashboard-theme',

            inventoryKey: 'inventory-filters',
            transitionDuration: 300,
            animationDelay: 100
        };
        
        // ==========================================================================
        // REFERENCIAS A ELEMENTOS DOM
        // ==========================================================================
        const DOM = {
            html: document.documentElement,
            body: document.body,
            themeSwitch: document.getElementById('themeSwitch'),
            searchInput: document.getElementById('searchInput'),
            filterTabs: document.querySelectorAll('.filter-tab'),
            tableRows: document.querySelectorAll('#inventoryTable tbody tr'),
            editButtons: document.querySelectorAll('.btn-icon.edit'),
            deleteButtons: document.querySelectorAll('.btn-icon.delete')
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
                // 1. Verificar preferencia guardada
                const savedTheme = localStorage.getItem(CONFIG.themeKey);
                if (savedTheme) return savedTheme;
                
                // 2. Verificar preferencia del sistema
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (prefersDark) return 'dark';
                
                // 3. Tema por defecto (día)
                return 'light';
            }
            
            applyTheme(theme) {
                DOM.html.setAttribute('data-theme', theme);
                localStorage.setItem(CONFIG.themeKey, theme);
                
                // Actualizar meta tag para navegadores móviles
                const metaTheme = document.querySelector('meta[name="theme-color"]');
                if (metaTheme) {
                    metaTheme.setAttribute('content', theme === 'dark' ? '#0f172a' : '#ffffff');
                }
            }
            
            toggleTheme() {
                const newTheme = this.theme === 'light' ? 'dark' : 'light';
                this.theme = newTheme;
                this.applyTheme(newTheme);
                
                // Animación sutil en el botón
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
                
                // Escuchar cambios en preferencias del sistema
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                    if (!localStorage.getItem(CONFIG.themeKey)) {
                        this.theme = e.matches ? 'dark' : 'light';
                        this.applyTheme(this.theme);
                    }
                });
            }
        }
        
        // ==========================================================================
        // FUNCIONALIDADES DE INVENTARIO
        // ==========================================================================
        class InventoryManager {
            constructor() {
                this.currentFilter = 'all';
                this.searchTerm = '';
                this.setupEventListeners();
                this.loadSavedFilters();
            }
            
            setupEventListeners() {
                // Búsqueda
                if (DOM.searchInput) {
                    DOM.searchInput.addEventListener('input', (e) => {
                        this.searchTerm = e.target.value.toLowerCase();
                        this.applyFilters();
                    });
                }
                
                // Filtros
                if (DOM.filterTabs) {
                    DOM.filterTabs.forEach(tab => {
                        tab.addEventListener('click', (e) => {
                            // Remover clase active de todos
                            DOM.filterTabs.forEach(t => t.classList.remove('active'));
                            // Agregar clase active al actual
                            e.target.classList.add('active');
                            
                            this.currentFilter = e.target.getAttribute('data-filter');
                            this.saveFilters();
                            this.applyFilters();
                        });
                    });
                }
                
                // Botones de acción
                if (DOM.editButtons) {
                    DOM.editButtons.forEach(button => {
                        button.addEventListener('click', (e) => {
                            const id = e.target.closest('.btn-icon').getAttribute('data-id');
                            this.loadMedicineData(id);
                        });
                    });
                }
                
                if (DOM.deleteButtons) {
                    DOM.deleteButtons.forEach(button => {
                        button.addEventListener('click', (e) => {
                            const id = e.target.closest('.btn-icon').getAttribute('data-id');
                            this.deleteMedicine(id);
                        });
                    });
                }
            }
            
            applyFilters() {
                DOM.tableRows.forEach(row => {
                    const stockAttr = row.getAttribute('data-stock');
                    const expiryAttr = row.getAttribute('data-expiry');
                    const text = row.textContent.toLowerCase();
                    
                    let show = true;
                    
                    // Aplicar filtro por estado
                    if (this.currentFilter !== 'all') {
                        switch(this.currentFilter) {
                            case 'adequate':
                                show = stockAttr === 'status-good';
                                break;
                            case 'low':
                                show = stockAttr === 'status-warning';
                                break;
                            case 'out':
                                show = stockAttr === 'status-danger';
                                break;
                            case 'expiring':
                                show = expiryAttr === 'status-warning';
                                break;
                            case 'expired':
                                show = expiryAttr === 'status-danger';
                                break;
                            case 'pending':
                                show = stockAttr === 'status-info';
                                break;
                        }
                    }
                    
                    // Aplicar búsqueda
                    if (show && this.searchTerm) {
                        show = text.includes(this.searchTerm);
                    }
                    
                    row.style.display = show ? '' : 'none';
                });
            }
            
            loadMedicineData(id) {
                // En un sistema real, aquí se haría una petición AJAX
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
            }
            
            deleteMedicine(id) {
                if (confirm('¿Está seguro de eliminar este medicamento? Esta acción no se puede deshacer.')) {
                    window.location.href = `delete_medicine.php?id=${id}`;
                }
            }
            
            saveFilters() {
                const filters = {
                    currentFilter: this.currentFilter,
                    searchTerm: this.searchTerm
                };
                localStorage.setItem(CONFIG.inventoryKey, JSON.stringify(filters));
            }
            
            loadSavedFilters() {
                const savedFilters = localStorage.getItem(CONFIG.inventoryKey);
                if (savedFilters) {
                    const filters = JSON.parse(savedFilters);
                    this.currentFilter = filters.currentFilter || 'all';
                    this.searchTerm = filters.searchTerm || '';
                    
                    // Aplicar filtro guardado
                    if (DOM.searchInput && this.searchTerm) {
                        DOM.searchInput.value = this.searchTerm;
                    }
                    
                    if (DOM.filterTabs) {
                        DOM.filterTabs.forEach(tab => {
                            if (tab.getAttribute('data-filter') === this.currentFilter) {
                                tab.classList.add('active');
                            } else {
                                tab.classList.remove('active');
                            }
                        });
                    }
                    
                    this.applyFilters();
                }
            }
        }
        
        // ==========================================================================
        // FUNCIONALIDADES GLOBALES DEL INVENTARIO
        // ==========================================================================
        window.openReceiveModal = function(id, name) {
            document.getElementById('receive_id_inventario').value = id;
            document.getElementById('receive_nom_medicamento').value = name;
            
            // Establecer fecha de vencimiento predeterminada (1 año desde hoy)
            const defaultDate = new Date();
            defaultDate.setFullYear(defaultDate.getFullYear() + 1);
            document.getElementById('receive_fecha_vencimiento').valueAsDate = defaultDate;
            
            // Modales se inicializan automáticamente vía data-attributes
        };
        
        window.submitReceive = function() {
            const id = document.getElementById('receive_id_inventario').value;
            const expiryDate = document.getElementById('receive_fecha_vencimiento').value;
            
            if (!expiryDate) {
                alert('Por favor ingrese la fecha de vencimiento');
                return;
            }
            
            // En un sistema real, aquí se haría una petición AJAX
            window.location.href = `receive_item.php?id=${id}&expiry=${expiryDate}`;
        };
        
        // ==========================================================================
        // ANIMACIONES Y EFECTOS VISUALES
        // ==========================================================================
        class AnimationManager {
            constructor() {
                this.setupGreeting();
                this.setupAnimations();
            }
            
            setupGreeting() {
                const greetingElement = document.getElementById('greeting-text');
                if (!greetingElement) return;
                
                const hour = new Date().getHours();
                let greeting = '';
                
                if (hour < 12) {
                    greeting = 'Buenos días';
                } else if (hour < 19) {
                    greeting = 'Buenas tardes';
                } else {
                    greeting = 'Buenas noches';
                }
                
                greetingElement.textContent = greeting + ', Gestión de Inventario';
            }
            
            setupAnimations() {
                // Animar elementos al cargar
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
                
                // Observar elementos con clase de animación
                document.querySelectorAll('.stat-card, .appointments-section, .alert-card').forEach(el => {
                    observer.observe(el);
                });
            }
        }
        
        // ==========================================================================
        // VALIDACIÓN DE FORMULARIOS
        // ==========================================================================
        class FormValidator {
            constructor() {
                this.setupFormValidation();
            }
            
            setupFormValidation() {
                const addForm = document.getElementById('addMedicineForm');
                const editForm = document.getElementById('editMedicineForm');
                
                if (addForm) {
                    addForm.addEventListener('submit', (e) => this.validateMedicineForm(e, 'add'));
                }
                
                if (editForm) {
                    editForm.addEventListener('submit', (e) => this.validateMedicineForm(e, 'edit'));
                }
            }
            
            validateMedicineForm(e, formType) {
                const cantidad = document.getElementById(formType === 'add' ? 'cantidad_med' : 'edit_cantidad_med').value;
                if (cantidad < 0) {
                    e.preventDefault();
                    alert('La cantidad no puede ser negativa');
                    return false;
                }
                
                const fechaAdq = document.getElementById(formType === 'add' ? 'fecha_adquisicion' : 'edit_fecha_adquisicion').value;
                const fechaVen = document.getElementById(formType === 'add' ? 'fecha_vencimiento' : 'edit_fecha_vencimiento').value;
                
                if (new Date(fechaVen) < new Date(fechaAdq)) {
                    e.preventDefault();
                    alert('La fecha de vencimiento no puede ser anterior a la fecha de adquisición');
                    return false;
                }
                
                return true;
            }
        }
        
        // ==========================================================================
        // INICIALIZACIÓN DE LA APLICACIÓN
        // ==========================================================================
        document.addEventListener('DOMContentLoaded', () => {
            // Inicializar componentes
            const themeManager = new ThemeManager();
            const inventoryManager = new InventoryManager();
            const animationManager = new AnimationManager();
            const formValidator = new FormValidator();
            
            // Exponer APIs necesarias globalmente
            window.inventory = {
                theme: themeManager,
                manager: inventoryManager,
                animations: animationManager
            };
            
            // Establecer fecha actual como predeterminada en formularios
            const today = new Date().toISOString().split('T')[0];
            const fechaAdquisicion = document.getElementById('fecha_adquisicion');
            if (fechaAdquisicion) {
                fechaAdquisicion.value = today;
            }
            
            // Log de inicialización
            console.log('Módulo de Inventario v4.0 inicializado correctamente');
            console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
            console.log('Rol: <?php echo htmlspecialchars($user_type); ?>');
            console.log('Total de items: <?php echo $total_items; ?>');
        });
        
        // ==========================================================================
        // MANEJO DE ERRORES GLOBALES
        // ==========================================================================
        window.addEventListener('error', (event) => {
            console.error('Error en inventario:', event.error);
            
            // En producción, enviar error al servidor
            if (window.location.hostname !== 'localhost') {
                const errorData = {
                    message: event.message,
                    source: event.filename,
                    lineno: event.lineno,
                    colno: event.colno,
                    user: '<?php echo htmlspecialchars($user_name); ?>',
                    timestamp: new Date().toISOString()
                };
                
                console.log('Error reportado:', errorData);
            }
        });
        
    })();
    
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
    `;
    document.head.appendChild(style);
    </script>
    
    <!-- jQuery (required for Bootstrap modals) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>