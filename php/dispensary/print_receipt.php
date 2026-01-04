<?php
// inventory/print_receipt.php - Recibo de Venta - Centro Médico Herrera Saenz
// Versión: 4.0 - Diseño Responsive con Sidebar Moderna y Efecto Mármol
session_start();

// Verificar sesión activa
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

// Verificar si se proporciona ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de venta inválido");
}

$id_venta = $_GET['id'];

try {
    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();
    
    // Obtener datos de la venta
    $stmt = $conn->prepare("SELECT * FROM ventas WHERE id_venta = ?");
    $stmt->execute([$id_venta]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$venta) {
        die("Venta no encontrada");
    }
    
    // Obtener items de la venta
    $stmt = $conn->prepare("
        SELECT dv.*, i.nom_medicamento, i.mol_medicamento, i.presentacion_med
        FROM detalle_ventas dv
        JOIN inventario i ON dv.id_inventario = i.id_inventario
        WHERE dv.id_venta = ?
    ");
    $stmt->execute([$id_venta]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Información del usuario
    $user_name = $_SESSION['nombre'];
    $user_type = $_SESSION['tipoUsuario'];
    $user_specialty = $_SESSION['especialidad'] ?? 'Profesional Médico';
    
    // Estadísticas adicionales
    $stmt = $conn->prepare("SELECT COUNT(*) as total_ventas FROM ventas");
    $stmt->execute();
    $total_ventas = $stmt->fetch(PDO::FETCH_ASSOC)['total_ventas'] ?? 0;
    
    // Ventas del mes
    $month_start = date('Y-m-01');
    $month_end = date('Y-m-t');
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM ventas WHERE fecha_venta BETWEEN ? AND ?");
    $stmt->execute([$month_start, $month_end]);
    $month_sales = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Título de la página
    $page_title = "Recibo de Venta #" . str_pad($id_venta, 5, '0', STR_PAD_LEFT) . " - Centro Médico Herrera Saenz";
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Formatear fecha
$fecha = new DateTime($venta['fecha_venta']);
$fecha_formateada = $fecha->format('d/m/Y');
$hora_formateada = $fecha->format('H:i');
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Recibo de venta del Centro Médico Herrera Saenz - Sistema de gestión médica">
    <title><?php echo $page_title; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">
    
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- CSS Crítico (incrustado - mismo que dashboard) -->
    <style>
    /* ==========================================================================
       VARIABLES CSS PARA TEMA DÍA/NOCHE (Mismo que dashboard)
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
        
        /* Ancho Sidebar */
        --sidebar-width: 280px;
        --sidebar-collapsed-width: 100px;
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
        margin-left: var(--sidebar-width);
        transition: margin-left var(--transition-base);
        width: calc(100% - var(--sidebar-width));
    }
    
    /* ==========================================================================
       BARRA LATERAL MODERNA (Mismo que dashboard)
       ========================================================================== */
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        width: var(--sidebar-width);
        background-color: var(--color-card);
        border-right: 1px solid var(--color-border);
        z-index: 1000;
        display: flex;
        flex-direction: column;
        transition: all var(--transition-base);
        transform: translateX(0);
        box-shadow: var(--shadow-lg);
    }
    
    .sidebar.collapsed {
        width: var(--sidebar-collapsed-width);
    }
    
    .sidebar.collapsed .sidebar-header h2,
    .sidebar.collapsed .nav-link span:not(.badge),
    .sidebar.collapsed .user-info .user-details {
        opacity: 0;
        width: 0;
        height: 0;
        overflow: hidden;
        margin: 0;
        padding: 0;
    }
    
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 999;
        opacity: 0;
        transition: opacity var(--transition-base);
    }
    
    .sidebar-header {
        padding: var(--space-lg);
        border-bottom: 1px solid var(--color-border);
        display: flex;
        align-items: center;
        gap: var(--space-md);
    }
    
    .sidebar-logo {
        width: 40px;
        height: 40px;
        border-radius: var(--radius-md);
        overflow: hidden;
        flex-shrink: 0;
    }
    
    .sidebar-logo img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }
    
    .sidebar-header h2 {
        font-size: var(--font-size-xl);
        font-weight: 700;
        color: var(--color-primary);
        margin: 0;
        white-space: nowrap;
        transition: opacity var(--transition-base);
    }
    
    .sidebar-nav {
        flex: 1;
        padding: var(--space-md);
        overflow-y: auto;
    }
    
    .nav-list {
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: var(--space-xs);
    }
    
    .nav-item {
        position: relative;
    }
    
    .nav-link {
        display: flex;
        align-items: center;
        gap: var(--space-md);
        padding: var(--space-md);
        color: var(--color-text-secondary);
        text-decoration: none;
        border-radius: var(--radius-md);
        transition: all var(--transition-base);
        position: relative;
        overflow: hidden;
    }
    
    .nav-link:hover {
        background-color: var(--color-surface);
        color: var(--color-text);
        transform: translateX(4px);
    }
    
    .nav-link.active {
        background-color: var(--color-primary);
        color: white;
        box-shadow: var(--shadow-md);
    }
    
    .nav-link.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 60%;
        background-color: currentColor;
        border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
    }
    
    .nav-icon {
        font-size: 1.25rem;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .nav-text {
        font-weight: 500;
        white-space: nowrap;
        transition: opacity var(--transition-base);
    }
    
    .badge {
        margin-left: auto;
        font-size: var(--font-size-xs);
        padding: 0.25em 0.5em;
        min-width: 1.5rem;
        height: 1.5rem;
    }
    
    .sidebar-footer {
        padding: var(--space-md);
        border-top: 1px solid var(--color-border);
        display: flex;
        align-items: center;
        gap: var(--space-md);
    }
    
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
        background: none;
        border: none;
        color: var(--color-text);
        font-size: 1.5rem;
        cursor: pointer;
        padding: var(--space-xs);
        border-radius: var(--radius-sm);
    }
    
    .mobile-toggle:hover {
        background-color: var(--color-surface);
    }
    
    .header-controls {
        display: flex;
        align-items: center;
        gap: var(--space-lg);
    }
    
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
    
    .sidebar.collapsed ~ .dashboard-container {
        margin-left: var(--sidebar-collapsed-width);
        width: calc(100% - var(--sidebar-collapsed-width));
    }
    
    .sidebar-toggle {
        position: fixed;
        left: calc(var(--sidebar-width) - 12px);
        top: 50%;
        transform: translateY(-50%);
        width: 24px;
        height: 48px;
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-left: none;
        border-radius: 0 var(--radius-md) var(--radius-md) 0;
        color: var(--color-text);
        cursor: pointer;
        z-index: 1001;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all var(--transition-base);
        box-shadow: var(--shadow-md);
    }
    
    .sidebar-toggle:hover {
        background: var(--color-primary);
        color: white;
        transform: translateY(-50%) scale(1.1);
    }
    
    .sidebar.collapsed + .dashboard-container .sidebar-toggle {
        left: calc(var(--sidebar-collapsed-width) - 12px);
    }
    
    .sidebar.collapsed .sidebar-toggle i {
        transform: rotate(180deg);
    }
    
    /* ==========================================================================
       ESTILOS ESPECÍFICOS DEL RECIBO
       ========================================================================== */
    .receipt-container {
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: var(--space-xl);
        margin: var(--space-lg) auto;
        max-width: 210mm;
        position: relative;
        overflow: hidden;
        transition: all var(--transition-base);
        box-shadow: var(--shadow-lg);
    }
    
    .receipt-container:hover {
        box-shadow: var(--shadow-xl);
        transform: translateY(-4px);
    }
    
    .receipt-watermark {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-45deg);
        font-size: 120px;
        color: rgba(var(--color-text-rgb, 26, 26, 26), 0.03);
        pointer-events: none;
        z-index: 0;
        font-weight: 900;
        text-transform: uppercase;
        white-space: nowrap;
        opacity: 0.5;
    }
    
    .receipt-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 2px solid var(--color-primary);
        padding-bottom: var(--space-lg);
        margin-bottom: var(--space-xl);
        position: relative;
        z-index: 1;
    }
    
    .clinic-logo {
        height: 60px;
        width: auto;
        filter: drop-shadow(var(--shadow-md));
    }
    
    .clinic-info {
        text-align: right;
        font-size: var(--font-size-sm);
        line-height: 1.5;
        color: var(--color-text);
        font-weight: 500;
    }
    
    .receipt-info {
        background: rgba(var(--color-primary-rgb), 0.1);
        border: 1px solid rgba(var(--color-primary-rgb), 0.2);
        border-radius: var(--radius-md);
        padding: var(--space-lg);
        margin-bottom: var(--space-xl);
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: var(--space-md);
        position: relative;
        z-index: 1;
    }
    
    .info-item {
        display: flex;
        flex-direction: column;
    }
    
    .info-label {
        font-size: var(--font-size-xs);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--color-text-secondary);
        margin-bottom: var(--space-xs);
        font-weight: 600;
    }
    
    .info-value {
        font-size: var(--font-size-base);
        font-weight: 700;
        color: var(--color-text);
        line-height: 1.2;
    }
    
    .receipt-title {
        color: var(--color-primary);
        font-size: var(--font-size-2xl);
        font-weight: 700;
        margin-bottom: var(--space-lg);
        text-transform: uppercase;
        letter-spacing: 1px;
        border-left: 4px solid var(--color-primary);
        padding-left: var(--space-md);
        position: relative;
        z-index: 1;
    }
    
    .receipt-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: var(--space-xl);
        position: relative;
        z-index: 1;
    }
    
    .receipt-table thead {
        background: rgba(var(--color-primary-rgb), 0.05);
    }
    
    .receipt-table th {
        padding: var(--space-md);
        text-align: left;
        font-weight: 600;
        color: var(--color-text);
        border-bottom: 2px solid var(--color-border);
        font-size: var(--font-size-sm);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .receipt-table td {
        padding: var(--space-md);
        border-bottom: 1px solid var(--color-border);
        vertical-align: middle;
        color: var(--color-text);
    }
    
    .receipt-table tbody tr {
        transition: background-color var(--transition-base);
    }
    
    .receipt-table tbody tr:hover {
        background: rgba(var(--color-primary-rgb), 0.03);
    }
    
    .product-cell {
        display: flex;
        flex-direction: column;
        gap: var(--space-xs);
    }
    
    .product-name {
        font-weight: 600;
        color: var(--color-text);
    }
    
    .product-details {
        font-size: var(--font-size-sm);
        color: var(--color-text-secondary);
    }
    
    .total-section {
        display: flex;
        justify-content: flex-end;
        margin-top: var(--space-xl);
        padding-top: var(--space-xl);
        border-top: 2px solid var(--color-border);
        position: relative;
        z-index: 1;
    }
    
    .total-box {
        background: linear-gradient(135deg, var(--color-primary), var(--color-info));
        color: white;
        padding: var(--space-xl) var(--space-2xl);
        border-radius: var(--radius-lg);
        text-align: right;
        box-shadow: var(--shadow-lg);
        min-width: 300px;
    }
    
    .total-label {
        font-size: var(--font-size-sm);
        text-transform: uppercase;
        opacity: 0.9;
        margin-bottom: var(--space-xs);
        letter-spacing: 0.5px;
    }
    
    .total-amount {
        font-size: var(--font-size-3xl);
        font-weight: 800;
    }
    
    .receipt-footer {
        margin-top: var(--space-xl);
        padding-top: var(--space-xl);
        border-top: 1px solid var(--color-border);
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        position: relative;
        z-index: 1;
    }
    
    .legal-note {
        font-size: var(--font-size-xs);
        color: var(--color-text-secondary);
        max-width: 400px;
        line-height: 1.4;
    }
    
    .thank-you {
        text-align: right;
        font-style: italic;
        color: var(--color-primary);
    }
    
    .action-buttons {
        display: flex;
        gap: var(--space-md);
        margin-top: var(--space-xl);
        justify-content: center;
        position: relative;
        z-index: 1;
    }
    
    .action-btn {
        display: inline-flex;
        align-items: center;
        gap: var(--space-sm);
        padding: var(--space-md) var(--space-lg);
        border: none;
        border-radius: var(--radius-md);
        font-weight: 600;
        text-decoration: none;
        transition: all var(--transition-base);
        cursor: pointer;
        font-size: var(--font-size-base);
    }
    
    .action-btn.primary {
        background: var(--color-primary);
        color: white;
    }
    
    .action-btn.secondary {
        background: var(--color-surface);
        color: var(--color-text);
        border: 1px solid var(--color-border);
    }
    
    .action-btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    
    .action-btn.primary:hover {
        background: var(--color-primary);
        opacity: 0.9;
    }
    
    /* ==========================================================================
       ESTILOS DE IMPRESIÓN
       ========================================================================== */
    @media print {
        .no-print {
            display: none !important;
        }
        
        body {
            background: white !important;
            color: black !important;
            padding: 0 !important;
        }
        
        .dashboard-container {
            margin-left: 0 !important;
            width: 100% !important;
        }
        
        .main-content {
            padding: 0 !important;
            margin: 0 !important;
        }
        
        .receipt-container {
            box-shadow: none !important;
            border: none !important;
            padding: 0 !important;
            margin: 0 !important;
            max-width: none !important;
            width: 100% !important;
        }
        
        .receipt-watermark {
            opacity: 0.1 !important;
        }
        
        .action-buttons,
        .sidebar,
        .dashboard-header,
        .sidebar-toggle,
        .marble-effect {
            display: none !important;
        }
    }
    
    /* ==========================================================================
       RESPONSIVE DESIGN
       ========================================================================== */
    @media (max-width: 991px) {
        .sidebar {
            transform: translateX(-100%);
        }
        
        .sidebar.show {
            transform: translateX(0);
        }
        
        .dashboard-container {
            margin-left: 0;
            width: 100%;
        }
        
        .sidebar-overlay.show {
            display: block;
            opacity: 1;
        }
        
        .main-content {
            margin-left: 0;
            padding: var(--space-md);
        }
        
        .sidebar-toggle {
            display: none;
        }
        
        .mobile-toggle {
            display: block;
        }
        
        .header-content {
            padding: var(--space-md);
        }
        
        .receipt-header {
            flex-direction: column;
            gap: var(--space-lg);
            align-items: flex-start;
        }
        
        .clinic-info {
            text-align: left;
        }
        
        .receipt-info {
            grid-template-columns: 1fr;
        }
        
        .total-box {
            min-width: auto;
            width: 100%;
        }
        
        .receipt-footer {
            flex-direction: column;
            gap: var(--space-lg);
            align-items: flex-start;
        }
        
        .thank-you {
            text-align: left;
        }
    }
    
    @media (max-width: 767px) {
        .receipt-container {
            padding: var(--space-lg);
        }
        
        .receipt-table {
            font-size: var(--font-size-sm);
        }
        
        .receipt-table th,
        .receipt-table td {
            padding: var(--space-sm);
        }
        
        .receipt-title {
            font-size: var(--font-size-xl);
        }
        
        .total-amount {
            font-size: var(--font-size-2xl);
        }
    }
    
    @media (max-width: 480px) {
        .receipt-container {
            padding: var(--space-md);
        }
        
        .action-buttons {
            flex-direction: column;
        }
        
        .action-btn {
            width: 100%;
            justify-content: center;
        }
        
        .receipt-watermark {
            font-size: 80px;
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
    </style>
</head>
<body>
    <!-- Efecto de mármol animado -->
    <div class="marble-effect"></div>
    
    <!-- Overlay para sidebar móvil -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
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
                            <span class="header-role"><?php echo htmlspecialchars($user_specialty); ?></span>
                        </div>
                    </div>
                    
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
            <!-- Recibo de venta -->
            <div class="receipt-container animate-in">
                <!-- Marca de agua -->
                <div class="receipt-watermark">HERRERA SAENZ</div>
                
                <!-- Encabezado del recibo -->
                <header class="receipt-header">
                    <div>
                        <img src="../../assets/img/herrerasaenz.png" alt="Centro Médico Herrera Saenz" class="clinic-logo">
                    </div>
                    <div class="clinic-info">
                        7ma Av 7-25 Zona 1, Atrás del parqueo Hospital Antiguo. Huehuetenango<br>
                        Tel: (+502) 4195-8112<br>
                        Documento: Recibo de Venta
                    </div>
                </header>
                
                <!-- Información de la venta -->
                <section class="receipt-info">
                    <div class="info-item">
                        <span class="info-label">Cliente</span>
                        <span class="info-value"><?php echo htmlspecialchars($venta['nombre_cliente']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Fecha y Hora</span>
                        <span class="info-value"><?php echo $fecha_formateada . ' ' . $hora_formateada; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Método de Pago</span>
                        <span class="info-value"><?php echo htmlspecialchars($venta['tipo_pago']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">No. Recibo</span>
                        <span class="info-value">#VNT-<?php echo str_pad($id_venta, 5, '0', STR_PAD_LEFT); ?></span>
                    </div>
                </section>
                
                <!-- Detalles de la venta -->
                <h2 class="receipt-title">Detalle de la Venta</h2>
                <table class="receipt-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th style="text-align: center;">Cant.</th>
                            <th style="text-align: right;">Precio Unit.</th>
                            <th style="text-align: right;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <div class="product-cell">
                                        <div class="product-name"><?php echo htmlspecialchars($item['nom_medicamento']); ?></div>
                                        <div class="product-details">
                                            <?php echo htmlspecialchars($item['mol_medicamento']); ?> • <?php echo htmlspecialchars($item['presentacion_med']); ?>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align: center; font-weight: 600;"><?php echo $item['cantidad_vendida']; ?></td>
                                <td style="text-align: right;">Q<?php echo number_format($item['precio_unitario'], 2); ?></td>
                                <td style="text-align: right; font-weight: 600;">Q<?php echo number_format($item['cantidad_vendida'] * $item['precio_unitario'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Total -->
                <div class="total-section">
                    <div class="total-box">
                        <div class="total-label">Total General</div>
                        <div class="total-amount">Q<?php echo number_format($venta['total'], 2); ?></div>
                    </div>
                </div>
                
                <!-- Pie de página -->
                <footer class="receipt-footer">
                    <div class="legal-note">
                        <strong>Información Importante:</strong><br>
                        Este recibo es un comprobante de venta de productos médicos. 
                        Para cualquier aclaración, favor de presentar este documento original.
                        Documento generado por Centro Médico Herrera Saenz Management System.
                    </div>
                    <div class="thank-you">
                        <h4 style="margin: 0; font-size: 16px;">¡Gracias por su compra!</h4>
                        <p style="margin: 5px 0 0; font-size: 13px;">Que tenga un buen día.</p>
                    </div>
                </footer>
            </div>
            
            <!-- Botones de acción -->
            <div class="action-buttons animate-in delay-1">
                <a href="../sales/index.php" class="action-btn secondary no-print">
                    <i class="bi bi-arrow-left"></i>
                    Volver a Ventas
                </a>
                <button onclick="window.print()" class="action-btn primary no-print">
                    <i class="bi bi-printer-fill"></i>
                    Imprimir Recibo
                </button>
            </div>
        </main>
    </div>
    
    <!-- JavaScript Optimizado (mismo que dashboard) -->
    <script>
    // Dashboard Reingenierizado - Centro Médico Herrera Saenz
    
    (function() {
        'use strict';
        
        // ==========================================================================
        // CONFIGURACIÓN Y CONSTANTES
        // ==========================================================================
        const CONFIG = {
            themeKey: 'dashboard-theme',
            sidebarKey: 'sidebar-collapsed',
            greetingKey: 'last-jornada-summary',
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
            sidebar: document.getElementById('sidebar'),
            sidebarToggle: document.getElementById('sidebarToggle'),
            sidebarToggleIcon: document.getElementById('sidebarToggleIcon'),
            sidebarOverlay: document.getElementById('sidebarOverlay'),
            mobileSidebarToggle: document.getElementById('mobileSidebarToggle'),
            greetingElement: document.getElementById('greeting-text'),
            currentTimeElement: document.getElementById('current-time')
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
        // MANEJO DE BARRA LATERAL
        // ==========================================================================
        class SidebarManager {
            constructor() {
                this.isCollapsed = this.getInitialState();
                this.isMobile = window.innerWidth < 992;
                this.setupEventListeners();
                this.applyState();
            }
            
            getInitialState() {
                if (this.isMobile) return false;
                const savedState = localStorage.getItem(CONFIG.sidebarKey);
                return savedState === 'true';
            }
            
            applyState() {
                if (this.isCollapsed && !this.isMobile) {
                    DOM.sidebar.classList.add('collapsed');
                    if (DOM.sidebarToggleIcon) {
                        DOM.sidebarToggleIcon.classList.remove('bi-chevron-left');
                        DOM.sidebarToggleIcon.classList.add('bi-chevron-right');
                    }
                } else {
                    DOM.sidebar.classList.remove('collapsed');
                    if (DOM.sidebarToggleIcon) {
                        DOM.sidebarToggleIcon.classList.remove('bi-chevron-right');
                        DOM.sidebarToggleIcon.classList.add('bi-chevron-left');
                    }
                }
            }
            
            toggle() {
                if (this.isMobile) {
                    this.toggleMobile();
                } else {
                    this.toggleDesktop();
                }
            }
            
            toggleDesktop() {
                this.isCollapsed = !this.isCollapsed;
                this.applyState();
                localStorage.setItem(CONFIG.sidebarKey, this.isCollapsed);
            }
            
            toggleMobile() {
                const isShowing = DOM.sidebar.classList.toggle('show');
                
                if (isShowing) {
                    DOM.sidebarOverlay.classList.add('show');
                    DOM.body.style.overflow = 'hidden';
                } else {
                    DOM.sidebarOverlay.classList.remove('show');
                    DOM.body.style.overflow = '';
                }
            }
            
            closeMobile() {
                DOM.sidebar.classList.remove('show');
                DOM.sidebarOverlay.classList.remove('show');
                DOM.body.style.overflow = '';
            }
            
            setupEventListeners() {
                // Toggle escritorio
                if (DOM.sidebarToggle) {
                    DOM.sidebarToggle.addEventListener('click', () => this.toggle());
                }
                
                // Toggle móvil
                if (DOM.mobileSidebarToggle) {
                    DOM.mobileSidebarToggle.addEventListener('click', () => this.toggle());
                }
                
                // Overlay móvil
                if (DOM.sidebarOverlay) {
                    DOM.sidebarOverlay.addEventListener('click', () => this.closeMobile());
                }
                
                // Cerrar sidebar al hacer clic en enlace (móvil)
                const navLinks = DOM.sidebar.querySelectorAll('.nav-link');
                navLinks.forEach(link => {
                    link.addEventListener('click', () => {
                        if (this.isMobile) this.closeMobile();
                    });
                });
                
                // Escuchar cambios de tamaño
                window.addEventListener('resize', this.debounce(() => {
                    const wasMobile = this.isMobile;
                    this.isMobile = window.innerWidth < 992;
                    
                    if (wasMobile !== this.isMobile) {
                        if (!this.isMobile) this.closeMobile();
                        this.applyState();
                    }
                }, 250));
            }
            
            debounce(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }
        }
        
        // ==========================================================================
        // COMPONENTES DINÁMICOS
        // ==========================================================================
        class DynamicComponents {
            constructor() {
                this.setupGreeting();
                this.setupClock();
                this.setupAnimations();
            }
            
            setupGreeting() {
                if (!DOM.greetingElement) return;
                
                const hour = new Date().getHours();
                let greeting = '';
                
                if (hour < 12) {
                    greeting = 'Buenos días';
                } else if (hour < 19) {
                    greeting = 'Buenas tardes';
                } else {
                    greeting = 'Buenas noches';
                }
                
                DOM.greetingElement.textContent = greeting;
            }
            
            setupClock() {
                if (!DOM.currentTimeElement) return;
                
                const updateClock = () => {
                    const now = new Date();
                    const timeString = now.toLocaleTimeString('es-GT', { 
                        hour: '2-digit', 
                        minute: '2-digit',
                        hour12: false
                    });
                    DOM.currentTimeElement.textContent = timeString;
                };
                
                updateClock();
                setInterval(updateClock, 60000);
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
                document.querySelectorAll('.receipt-container, .action-buttons').forEach(el => {
                    observer.observe(el);
                });
            }
        }
        
        // ==========================================================================
        // INICIALIZACIÓN DE LA APLICACIÓN
        // ==========================================================================
        document.addEventListener('DOMContentLoaded', () => {
            // Inicializar componentes
            const themeManager = new ThemeManager();
            const sidebarManager = new SidebarManager();
            const dynamicComponents = new DynamicComponents();
            
            // Exponer APIs necesarias globalmente
            window.dashboard = {
                theme: themeManager,
                sidebar: sidebarManager,
                components: dynamicComponents
            };
            
            // Log de inicialización
            console.log('Recibo de Venta - Centro Médico Herrera Saenz');
            console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
            console.log('ID Venta: <?php echo $id_venta; ?>');
            console.log('Cliente: <?php echo htmlspecialchars($venta['nombre_cliente']); ?>');
            console.log('Total: Q<?php echo number_format($venta['total'], 2); ?>');
        });
    })();
    </script>
</body>
</html>