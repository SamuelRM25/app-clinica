<?php
// purchases/index.php - Módulo de Compras del Centro Médico Herrera Saenz
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
    $user_specialty = $_SESSION['especialidad'] ?? 'Administrador';
    
    // Verificar permisos (solo admin puede acceder a compras)
    if ($user_type !== 'admin') {
        header("Location: ../dashboard/index.php");
        exit;
    }
    
    // ============ ESTADÍSTICAS DE COMPRAS ============
    $today = date('Y-m-d');
    $current_month = date('Y-m');
    
    // 1. Compras del mes actual
    $stmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total FROM purchase_headers WHERE DATE_FORMAT(purchase_date, '%Y-%m') = ?");
    $stmt->execute([$current_month]);
    $month_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $month_purchases = $month_stats['count'] ?? 0;
    $month_total = $month_stats['total'] ?? 0;
    
    // 2. Compras pendientes de pago
    $stmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount - COALESCE(paid_amount, 0)), 0) as balance FROM purchase_headers WHERE (total_amount - COALESCE(paid_amount, 0)) > 0");
    $stmt->execute();
    $pending_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $pending_count = $pending_stats['count'] ?? 0;
    $total_balance = $pending_stats['balance'] ?? 0;
    
    // 3. Compras del día
    $stmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total FROM purchase_headers WHERE DATE(purchase_date) = ?");
    $stmt->execute([$today]);
    $today_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $today_purchases = $today_stats['count'] ?? 0;
    $today_total = $today_stats['total'] ?? 0;
    
    // 4. Proveedores con más compras
    $stmt = $conn->prepare("SELECT provider_name, COUNT(*) as count, SUM(total_amount) as total FROM purchase_headers GROUP BY provider_name ORDER BY total DESC LIMIT 5");
    $stmt->execute();
    $top_providers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 5. Últimas compras
    $stmt = $conn->prepare("SELECT ph.*, 
                           (ph.total_amount - COALESCE(ph.paid_amount, 0)) as balance,
                           (SELECT COUNT(*) FROM purchase_items WHERE purchase_header_id = ph.id) as items_count
                           FROM purchase_headers ph 
                           ORDER BY ph.purchase_date DESC, ph.created_at DESC 
                           LIMIT 10");
    $stmt->execute();
    $recent_purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 6. Compras por confirmar (en inventario como pendientes)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventario WHERE estado = 'Pendiente'");
    $stmt->execute();
    $pending_inventory = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 7. Compras antiguas (de la tabla anterior)
    try {
        $stmt_old = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_compra), 0) as total FROM compras");
        $stmt_old->execute();
        $old_stats = $stmt_old->fetch(PDO::FETCH_ASSOC);
        $old_purchases = $old_stats['count'] ?? 0;
        $old_total = $old_stats['total'] ?? 0;
    } catch (Exception $e) {
        $old_purchases = 0;
        $old_total = 0;
    }
    
    // Título de la página
    $page_title = "Compras - Centro Médico Herrera Saenz";
    
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
    <meta name="description" content="Módulo de Compras - Centro Médico Herrera Saenz - Gestión de compras de medicamentos e insumos">
    <title><?php echo $page_title; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">
    
    <!-- Google Fonts - Inter (moderno y legible) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
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
        /* Margin-left movido al contenedor padre */
        transition: all var(--transition-base);
        min-height: 100vh;
        background-color: transparent;
        width: 100%;
    }
    
    /* ==========================================================================
       COMPONENTES DE COMPRAS
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
    
    /* Grid de proveedores */
    .providers-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: var(--space-lg);
        margin-bottom: var(--space-xl);
    }
    
    .provider-card {
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: var(--space-lg);
        transition: all var(--transition-base);
    }
    
    .provider-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    .provider-header {
        display: flex;
        align-items: center;
        gap: var(--space-md);
        margin-bottom: var(--space-lg);
    }
    
    .provider-icon {
        width: 40px;
        height: 40px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        background: rgba(var(--color-info-rgb), 0.1);
        color: var(--color-info);
    }
    
    .provider-title {
        font-size: var(--font-size-lg);
        font-weight: 600;
        color: var(--color-text);
        margin: 0;
    }
    
    .provider-list {
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: var(--space-md);
    }
    
    .provider-item {
        padding: var(--space-md);
        background: var(--color-surface);
        border-radius: var(--radius-md);
        border-left: 4px solid var(--color-info);
        transition: all var(--transition-base);
    }
    
    .provider-item:hover {
        transform: translateX(4px);
        border-left-color: var(--color-primary);
    }
    
    .provider-item-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--space-xs);
    }
    
    .provider-item-name {
        font-weight: 500;
        color: var(--color-text);
    }
    
    .provider-badge {
        padding: 0.25em 0.5em;
        border-radius: var(--radius-sm);
        font-size: var(--font-size-xs);
        font-weight: 600;
    }
    
    .provider-badge.success {
        background: rgba(var(--color-success-rgb), 0.1);
        color: var(--color-success);
    }
    
    .provider-item-details {
        display: flex;
        justify-content: space-between;
        font-size: var(--font-size-sm);
        color: var(--color-text-secondary);
    }
    
    /* Tabs de navegación */
    .tabs-navigation {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 2rem;
        border-bottom: 1px solid var(--color-border);
        padding-bottom: 0.5rem;
        overflow-x: auto;
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
        white-space: nowrap;
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
    
    .tab-content {
        display: none;
        animation: fadeIn 0.5s ease;
    }
    
    .tab-content.active {
        display: block;
    }
    
    /* Modal styles */
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
    
    /* ==========================================================================
       RESPONSIVE DESIGN
       ========================================================================== */
    
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
        
        .providers-grid {
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
        
        .search-box {
            width: 100%;
        }
    }
    
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
    }
    
    /* Móviles pequeños */
    @media (max-width: 480px) {
        .main-content {
            padding: var(--space-sm);
        }
        
        .stat-card {
            padding: var(--space-md);
        }
        
        .provider-card,
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
        .provider-card {
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
            <!-- Bienvenida personalizada -->
            <div class="stat-card mb-4 animate-in">
                <div class="stat-header">
                    <div>
                        <h2 id="greeting" class="stat-value" style="font-size: 1.75rem; margin-bottom: 0.5rem;">
                            <span id="greeting-text">Buenos días</span>, <?php echo htmlspecialchars($user_name); ?>
                        </h2>
                        <p class="text-muted mb-0">
                            <i class="bi bi-cart-check me-1"></i> Módulo de Compras
                            <span class="mx-2">•</span>
                            <i class="bi bi-calendar-check me-1"></i> <?php echo date('d/m/Y'); ?>
                            <span class="mx-2">•</span>
                            <i class="bi bi-clock me-1"></i> <span id="current-time"><?php echo date('H:i'); ?></span>
                        </p>
                    </div>
                    <div class="d-none d-md-block">
                        <i class="bi bi-cart-check text-primary" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
            
            <!-- Estadísticas principales -->
            <div class="stats-grid">
                <!-- Compras del mes -->
                <div class="stat-card animate-in delay-1">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Compras del Mes</div>
                            <div class="stat-value"><?php echo $month_purchases; ?></div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="bi bi-calendar-month"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-currency-exchange"></i>
                        <span>Total: Q<?php echo number_format($month_total, 2); ?></span>
                    </div>
                </div>
                
                <!-- Compras pendientes -->
                <div class="stat-card animate-in delay-2">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Pendientes de Pago</div>
                            <div class="stat-value"><?php echo $pending_count; ?></div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="bi bi-clock-history"></i>
                        </div>
                    </div>
                    <div class="stat-change">
                        <i class="bi bi-cash-coin"></i>
                        <span>Saldo: Q<?php echo number_format($total_balance, 2); ?></span>
                    </div>
                </div>
                
                <!-- Compras de hoy -->
                <div class="stat-card animate-in delay-3">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Compras Hoy</div>
                            <div class="stat-value"><?php echo $today_purchases; ?></div>
                        </div>
                        <div class="stat-icon success">
                            <i class="bi bi-cart-check"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-arrow-up-right"></i>
                        <span>Total: Q<?php echo number_format($today_total, 2); ?></span>
                    </div>
                </div>
                
                <!-- Compras antiguas -->
                <div class="stat-card animate-in delay-4">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Registros Antiguos</div>
                            <div class="stat-value"><?php echo $old_purchases; ?></div>
                        </div>
                        <div class="stat-icon info">
                            <i class="bi bi-archive"></i>
                        </div>
                    </div>
                    <div class="stat-change">
                        <i class="bi bi-currency-exchange"></i>
                        <span>Total: Q<?php echo number_format($old_total, 2); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Navegación por pestañas -->
            <div class="tabs-navigation mb-4">
                <button class="tab-btn active" data-tab="recent-purchases">
                    <i class="bi bi-cart-check me-2"></i>Compras Recientes
                </button>
                <button class="tab-btn" data-tab="pending-payments">
                    <i class="bi bi-clock-history me-2"></i>Pagos Pendientes
                </button>
                <button class="tab-btn" data-tab="old-purchases">
                    <i class="bi bi-archive me-2"></i>Compras Antiguas
                </button>
                <button class="tab-btn" data-tab="top-providers">
                    <i class="bi bi-building me-2"></i>Proveedores
                </button>
            </div>
            
            <!-- Pestaña: Compras Recientes -->
            <div class="tab-content active" id="recent-purchases-tab">
                <section class="appointments-section animate-in delay-1">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="bi bi-clock-history section-title-icon"></i>
                            Compras Recientes
                        </h3>
                        <div class="d-flex gap-2">
                            <div class="search-box">
                                <i class="bi bi-search search-icon"></i>
                                <input type="text" id="searchRecent" placeholder="Buscar compra...">
                            </div>
                            <button class="action-btn" onclick="showNewPurchaseModal()">
                                <i class="bi bi-plus-lg"></i>
                                Nueva Compra
                            </button>
                        </div>
                    </div>
                    
                    <?php if (count($recent_purchases) > 0): ?>
                        <div class="table-responsive">
                            <table class="appointments-table" id="tableRecent">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Proveedor</th>
                                        <th>Documento</th>
                                        <th>Total</th>
                                        <th>Pagado</th>
                                        <th>Saldo</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_purchases as $purchase): ?>
                                        <?php 
                                        $balance = $purchase['balance'];
                                        $paid = $purchase['total_amount'] - $balance;
                                        ?>
                                        <tr>
                                            <td>
                                                <?php echo date('d/m/Y', strtotime($purchase['purchase_date'])); ?>
                                                <br>
                                                <small class="text-muted"><?php echo $purchase['items_count']; ?> items</small>
                                            </td>
                                            <td>
                                                <div class="patient-cell">
                                                    <div class="patient-avatar" style="background: var(--color-info);">
                                                        <?php echo strtoupper(substr($purchase['provider_name'], 0, 1)); ?>
                                                    </div>
                                                    <div class="patient-info">
                                                        <div class="patient-name"><?php echo htmlspecialchars($purchase['provider_name']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-secondary">
                                                    <?php echo htmlspecialchars($purchase['document_type']); ?>
                                                    <?php echo $purchase['document_number'] ? '#'.$purchase['document_number'] : ''; ?>
                                                </span>
                                            </td>
                                            <td class="fw-bold">Q<?php echo number_format($purchase['total_amount'], 2); ?></td>
                                            <td class="text-success">Q<?php echo number_format($paid, 2); ?></td>
                                            <td>
                                                <?php if ($balance > 0): ?>
                                                    <span class="badge badge-danger">Q<?php echo number_format($balance, 2); ?></span>
                                                <?php else: ?>
                                                    <span class="badge badge-success">Pagado</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="#" class="btn-icon history" title="Ver detalles" onclick="viewPurchaseDetails(<?php echo $purchase['id']; ?>)">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if ($balance > 0): ?>
                                                    <a href="#" class="btn-icon edit" title="Registrar pago" onclick="openPaymentModal(<?php echo $purchase['id']; ?>)">
                                                        <i class="bi bi-cash-coin"></i>
                                                    </a>
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
                                <i class="bi bi-cart-x"></i>
                            </div>
                            <h4 class="text-muted mb-2">No hay compras registradas</h4>
                            <p class="text-muted mb-3">Comienza registrando tu primera compra</p>
                            <button class="action-btn" onclick="showNewPurchaseModal()">
                                <i class="bi bi-plus-lg"></i>
                                Nueva Compra
                            </button>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
            
            <!-- Pestaña: Pagos Pendientes -->
            <div class="tab-content" id="pending-payments-tab">
                <section class="appointments-section animate-in delay-2">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="bi bi-clock-history text-warning section-title-icon"></i>
                            Compras con Saldo Pendiente
                        </h3>
                        <div class="search-box">
                            <i class="bi bi-search search-icon"></i>
                            <input type="text" id="searchPending" placeholder="Buscar proveedor...">
                        </div>
                    </div>
                    
                    <?php 
                    // Obtener compras pendientes
                    try {
                        $stmt_pending = $conn->prepare("SELECT ph.*, 
                               (ph.total_amount - COALESCE(ph.paid_amount, 0)) as balance
                               FROM purchase_headers ph 
                               WHERE (ph.total_amount - COALESCE(ph.paid_amount, 0)) > 0
                               ORDER BY ph.purchase_date ASC");
                        $stmt_pending->execute();
                        $pending_purchases = $stmt_pending->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Exception $e) {
                        $pending_purchases = [];
                    }
                    ?>
                    
                    <?php if (count($pending_purchases) > 0): ?>
                        <div class="table-responsive">
                            <table class="appointments-table" id="tablePending">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Proveedor</th>
                                        <th>Documento</th>
                                        <th>Total</th>
                                        <th>Pagado</th>
                                        <th>Saldo</th>
                                        <th>Días</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_purchases as $purchase): ?>
                                        <?php 
                                        $balance = $purchase['balance'];
                                        $paid = $purchase['total_amount'] - $balance;
                                        $purchase_date = new DateTime($purchase['purchase_date']);
                                        $today = new DateTime();
                                        $days_diff = $today->diff($purchase_date)->days;
                                        ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($purchase['purchase_date'])); ?></td>
                                            <td class="fw-bold"><?php echo htmlspecialchars($purchase['provider_name']); ?></td>
                                            <td>
                                                <span class="badge badge-secondary">
                                                    <?php echo htmlspecialchars($purchase['document_type']); ?>
                                                    <?php echo $purchase['document_number'] ? '#'.$purchase['document_number'] : ''; ?>
                                                </span>
                                            </td>
                                            <td class="fw-bold">Q<?php echo number_format($purchase['total_amount'], 2); ?></td>
                                            <td class="text-success">Q<?php echo number_format($paid, 2); ?></td>
                                            <td class="fw-bold text-danger">Q<?php echo number_format($balance, 2); ?></td>
                                            <td>
                                                <span class="badge <?php echo $days_diff > 30 ? 'badge-danger' : ($days_diff > 15 ? 'badge-warning' : 'badge-info'); ?>">
                                                    <?php echo $days_diff; ?> días
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="#" class="btn-icon edit" title="Registrar pago" onclick="openPaymentModal(<?php echo $purchase['id']; ?>)">
                                                        <i class="bi bi-cash-coin"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3 text-center">
                            <p class="text-muted mb-2">
                                Total pendiente: <strong class="text-danger">Q<?php echo number_format($total_balance, 2); ?></strong>
                                en <strong><?php echo $pending_count; ?></strong> compras
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="bi bi-check-circle text-success"></i>
                            </div>
                            <h4 class="text-muted mb-2">¡Excelente gestión!</h4>
                            <p class="text-muted mb-3">Todas las compras están completamente pagadas</p>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
            
            <!-- Pestaña: Compras Antiguas -->
            <div class="tab-content" id="old-purchases-tab">
                <section class="appointments-section animate-in delay-3">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="bi bi-archive section-title-icon"></i>
                            Historial de Compras Antiguas
                        </h3>
                        <div class="search-box">
                            <i class="bi bi-search search-icon"></i>
                            <input type="text" id="searchOld" placeholder="Buscar por producto...">
                        </div>
                    </div>
                    
                    <?php
                    try {
                        $stmt_old = $conn->prepare("SELECT * FROM compras ORDER BY fecha_compra DESC LIMIT 50");
                        $stmt_old->execute();
                        $old_purchases_list = $stmt_old->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Exception $e) {
                        $old_purchases_list = [];
                    }
                    ?>
                    
                    <?php if (count($old_purchases_list) > 0): ?>
                        <div class="table-responsive">
                            <table class="appointments-table" id="tableOld">
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
                                    <?php foreach ($old_purchases_list as $row): ?>
                                        <?php
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
                                            <td class="text-center"><?php echo $row['cantidad_compra']; ?></td>
                                            <td>Q<?php echo number_format($row['precio_unidad'], 2); ?></td>
                                            <td class="fw-bold text-primary">Q<?php echo number_format($row['total_compra'], 2); ?></td>
                                            <td><span class="badge badge-<?php echo $statusClass; ?>"><?php echo $row['estado_compra']; ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="bi bi-archive"></i>
                            </div>
                            <h4 class="text-muted mb-2">No hay registros antiguos</h4>
                            <p class="text-muted mb-3">Todos los registros están en el sistema actual</p>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
            
            <!-- Pestaña: Top Proveedores -->
            <div class="tab-content" id="top-providers-tab">
                <div class="providers-grid animate-in delay-4">
                    <div class="provider-card">
                        <div class="provider-header">
                            <div class="provider-icon">
                                <i class="bi bi-trophy"></i>
                            </div>
                            <h3 class="provider-title">Proveedores Principales</h3>
                        </div>
                        
                        <?php if (count($top_providers) > 0): ?>
                            <ul class="provider-list">
                                <?php foreach ($top_providers as $provider): ?>
                                    <li class="provider-item">
                                        <div class="provider-item-header">
                                            <span class="provider-item-name"><?php echo htmlspecialchars($provider['provider_name']); ?></span>
                                            <span class="provider-badge success">
                                                <?php echo $provider['count']; ?> compras
                                            </span>
                                        </div>
                                        <div class="provider-item-details">
                                            <span>Total invertido:</span>
                                            <span class="fw-bold">Q<?php echo number_format($provider['total'], 2); ?></span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="no-alerts">
                                <div class="no-alerts-icon">
                                    <i class="bi bi-building"></i>
                                </div>
                                <p class="text-muted mb-0">No hay datos de proveedores</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Card de acciones rápidas -->
                    <div class="provider-card">
                        <div class="provider-header">
                            <div class="provider-icon" style="background: rgba(var(--color-primary-rgb), 0.1); color: var(--color-primary);">
                                <i class="bi bi-lightning"></i>
                            </div>
                            <h3 class="provider-title">Acciones Rápidas</h3>
                        </div>
                        
                        <div class="provider-list">
                            <div class="provider-item" style="cursor: pointer;" onclick="showNewPurchaseModal()">
                                <div class="provider-item-header">
                                    <span class="provider-item-name">Nueva Compra</span>
                                    <i class="bi bi-plus-circle text-primary"></i>
                                </div>
                                <div class="provider-item-details">
                                    <span>Registrar una nueva compra de medicamentos</span>
                                </div>
                            </div>
                            
                            <div class="provider-item" style="cursor: pointer;" onclick="showPendingPurchases()">
                                <div class="provider-item-header">
                                    <span class="provider-item-name">Ver Pendientes</span>
                                    <i class="bi bi-clock-history text-warning"></i>
                                </div>
                                <div class="provider-item-details">
                                    <span>Compras con saldo pendiente de pago</span>
                                </div>
                            </div>
                            
                            <div class="provider-item" style="cursor: pointer;" onclick="window.open('../reports/compras_mensual.php', '_blank')">
                                <div class="provider-item-header">
                                    <span class="provider-item-name">Reporte Mensual</span>
                                    <i class="bi bi-file-earmark-pdf text-danger"></i>
                                </div>
                                <div class="provider-item-details">
                                    <span>Generar reporte de compras del mes</span>
                                </div>
                            </div>
                        </div>
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
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript Optimizado -->
    <script>
    // Módulo de Compras Reingenierizado - Centro Médico Herrera Saenz
    
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
            themeSwitch: document.getElementById('themeSwitch'),
            greetingElement: document.getElementById('greeting-text'),
            currentTimeElement: document.getElementById('current-time'),
            tabButtons: document.querySelectorAll('.tab-btn'),
            tabContents: document.querySelectorAll('.tab-content')
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
        // MANEJO DE PESTAÑAS
        // ==========================================================================
        class TabManager {
            constructor() {
                this.setupEventListeners();
            }
            
            switchTab(tabId) {
                // Remover clase active de todos los botones y contenidos
                DOM.tabButtons.forEach(btn => btn.classList.remove('active'));
                DOM.tabContents.forEach(content => content.classList.remove('active'));
                
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
                
                // Guardar pestaña activa
                localStorage.setItem('purchases-active-tab', tabId);
            }
            
            setupEventListeners() {
                DOM.tabButtons.forEach(button => {
                    button.addEventListener('click', () => {
                        const tabId = button.getAttribute('data-tab');
                        this.switchTab(tabId);
                    });
                });
                
                // Restaurar pestaña activa
                const savedTab = localStorage.getItem('purchases-active-tab');
                if (savedTab) {
                    this.switchTab(savedTab);
                }
            }
        }
        
        // ==========================================================================
        // COMPONENTES DINÁMICOS DE COMPRAS
        // ==========================================================================
        class PurchasesManager {
            constructor() {
                this.purchaseItems = [];
                this.setupGreeting();
                this.setupClock();
                this.setupSearch();
                this.setupAnimations();
                this.initializeDate();
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
            
            setupSearch() {
                // Búsqueda en tabla de compras recientes
                const searchRecent = document.getElementById('searchRecent');
                if (searchRecent) {
                    searchRecent.addEventListener('input', function() {
                        const searchTerm = this.value.toLowerCase();
                        const rows = document.querySelectorAll('#tableRecent tbody tr');
                        
                        rows.forEach(row => {
                            const text = row.textContent.toLowerCase();
                            row.style.display = text.includes(searchTerm) ? '' : 'none';
                        });
                    });
                }
                
                // Búsqueda en tabla de pendientes
                const searchPending = document.getElementById('searchPending');
                if (searchPending) {
                    searchPending.addEventListener('input', function() {
                        const searchTerm = this.value.toLowerCase();
                        const rows = document.querySelectorAll('#tablePending tbody tr');
                        
                        rows.forEach(row => {
                            const text = row.textContent.toLowerCase();
                            row.style.display = text.includes(searchTerm) ? '' : 'none';
                        });
                    });
                }
                
                // Búsqueda en tabla de antiguas
                const searchOld = document.getElementById('searchOld');
                if (searchOld) {
                    searchOld.addEventListener('input', function() {
                        const searchTerm = this.value.toLowerCase();
                        const rows = document.querySelectorAll('#tableOld tbody tr');
                        
                        rows.forEach(row => {
                            const text = row.textContent.toLowerCase();
                            row.style.display = text.includes(searchTerm) ? '' : 'none';
                        });
                    });
                }
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
                document.querySelectorAll('.stat-card, .appointments-section, .provider-card').forEach(el => {
                    observer.observe(el);
                });
            }
            
            initializeDate() {
                const purchaseDate = document.getElementById('purchase_date');
                if (purchaseDate) {
                    const today = new Date();
                    const formattedDate = today.toISOString().split('T')[0];
                    purchaseDate.value = formattedDate;
                }
                
                const payDate = document.getElementById('pay_date');
                if (payDate) {
                    const today = new Date();
                    const formattedDate = today.toISOString().split('T')[0];
                    payDate.value = formattedDate;
                }
            }
            
            // Mostrar compras pendientes
            showPendingPurchases() {
                const tabManager = new TabManager();
                tabManager.switchTab('pending-payments');
            }
        }
        
        // ==========================================================================
        // FUNCIONALIDADES ESPECÍFICAS DE COMPRAS
        // ==========================================================================
        
        // Variables globales para funcionalidad de compras
        let purchaseItems = [];
        
        // Mostrar modal de nueva compra
        window.showNewPurchaseModal = function() {
            // Resetear formulario
            const form = document.getElementById('purchaseForm');
            if (form) form.reset();
            
            // Establecer fecha actual
            const purchaseDate = document.getElementById('purchase_date');
            if (purchaseDate) {
                const today = new Date();
                const formattedDate = today.toISOString().split('T')[0];
                purchaseDate.value = formattedDate;
            }
            
            // Limpiar items
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
            if (!tbody) return;
            
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
            const totalAmount = document.getElementById('totalAmount');
            if (totalAmount) {
                totalAmount.textContent = total.toFixed(2);
            }
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
                        // Cerrar modal y recargar página
                        const modal = bootstrap.Modal.getInstance(document.getElementById('newPurchaseModal'));
                        modal.hide();
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
                        // Cerrar modal y recargar página
                        const modal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
                        modal.hide();
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
        
        // ==========================================================================
        // INICIALIZACIÓN DE LA APLICACIÓN
        // ==========================================================================
        document.addEventListener('DOMContentLoaded', () => {
            // Inicializar componentes
            const themeManager = new ThemeManager();
            const tabManager = new TabManager();
            const purchasesManager = new PurchasesManager();
            
            // Exponer APIs necesarias globalmente
            window.purchasesApp = {
                theme: themeManager,
                tabs: tabManager,
                purchases: purchasesManager
            };
            
            // Log de inicialización
            console.log('Módulo de Compras CMS v4.0 inicializado correctamente');
            console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
            console.log('Rol: <?php echo htmlspecialchars($user_type); ?>');
            console.log('Tema: ' + themeManager.theme);
        });
        
        // ==========================================================================
        // MANEJO DE ERRORES GLOBALES
        // ==========================================================================
        window.addEventListener('error', (event) => {
            console.error('Error en módulo de compras:', event.error);
        });
        
        // ==========================================================================
        // POLYFILLS PARA NAVEGADORES ANTIGUOS
        // ==========================================================================
        if (!NodeList.prototype.forEach) {
            NodeList.prototype.forEach = Array.prototype.forEach;
        }
        
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
</body>
</html>