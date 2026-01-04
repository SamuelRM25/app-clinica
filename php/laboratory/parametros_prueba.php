<?php
// laboratory/parametros_prueba.php - Configure parameters for a specific test
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

if ($_SESSION['tipoUsuario'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$id_prueba = $_GET['id'] ?? null;
if (!$id_prueba) {
    header("Location: catalogo_pruebas.php");
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get test details
    $stmt = $conn->prepare("SELECT * FROM catalogo_pruebas WHERE id_prueba = ?");
    $stmt->execute([$id_prueba]);
    $prueba = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$prueba) {
        header("Location: catalogo_pruebas.php");
        exit;
    }
    
    // Get current parameters
    $stmt = $conn->prepare("SELECT * FROM parametros_pruebas WHERE id_prueba = ? ORDER BY orden_visualizacion, id_parametro");
    $stmt->execute([$id_prueba]);
    $parametros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count parameters
    $total_parametros = count($parametros);
    $con_valores = array_filter($parametros, function($p) {
        return !empty($p['valor_ref_hombre_min']) || !empty($p['valor_ref_mujer_min']) || !empty($p['valor_ref_pediatrico_min']);
    });
    
    $page_title = "Parámetros: " . $prueba['nombre_prueba'];
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Configuración de Parámetros de Prueba - Laboratorio">
    <title><?php echo $page_title; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">
    
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- SortableJS para arrastrar y soltar -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    
    <!-- CSS Crítico (mismo que index.php) -->
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
    
    /* Botón de acción */
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
       COMPONENTES ESPECÍFICOS PARA PARÁMETROS
       ========================================================================== */
    
    /* Banner de información de la prueba */
    .test-info-banner {
        background: linear-gradient(135deg, rgba(var(--color-primary-rgb), 0.1), rgba(var(--color-info-rgb), 0.1));
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: var(--space-lg);
        margin-bottom: var(--space-xl);
        position: relative;
        overflow: hidden;
    }
    
    .test-info-banner::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 150px;
        height: 150px;
        background: linear-gradient(135deg, var(--color-primary), transparent);
        border-radius: 0 0 0 100%;
        opacity: 0.1;
    }
    
    .test-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: var(--space-md);
    }
    
    .test-title {
        font-size: var(--font-size-2xl);
        font-weight: 700;
        color: var(--color-primary);
        margin-bottom: var(--space-xs);
    }
    
    .test-code {
        font-size: var(--font-size-sm);
        color: var(--color-text-secondary);
        font-weight: 600;
        background: rgba(var(--color-primary-rgb), 0.1);
        padding: var(--space-xs) var(--space-sm);
        border-radius: var(--radius-sm);
        display: inline-block;
    }
    
    .test-stats {
        display: flex;
        gap: var(--space-lg);
        margin-top: var(--space-md);
    }
    
    .stat-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: var(--space-sm);
        min-width: 100px;
    }
    
    .stat-value {
        font-size: var(--font-size-2xl);
        font-weight: 700;
        color: var(--color-text);
        line-height: 1;
    }
    
    .stat-label {
        font-size: var(--font-size-xs);
        color: var(--color-text-secondary);
        text-align: center;
        margin-top: var(--space-xs);
    }
    
    /* Formulario de parámetros */
    .params-form-container {
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: var(--space-lg);
        margin-bottom: var(--space-xl);
        transition: all var(--transition-base);
    }
    
    .params-form-container:hover {
        box-shadow: var(--shadow-lg);
    }
    
    /* Fila de parámetro */
    .param-row {
        background: var(--color-surface);
        border: 2px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: var(--space-md);
        margin-bottom: var(--space-md);
        transition: all var(--transition-base);
        position: relative;
    }
    
    .param-row:hover {
        border-color: var(--color-primary);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    
    .param-row.dragging {
        opacity: 0.5;
        background: rgba(var(--color-primary-rgb), 0.05);
    }
    
    /* Mango para arrastrar */
    .drag-handle {
        cursor: move;
        color: var(--color-text-secondary);
        padding: var(--space-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: var(--radius-sm);
        transition: all var(--transition-base);
        user-select: none;
    }
    
    .drag-handle:hover {
        background: rgba(var(--color-primary-rgb), 0.1);
        color: var(--color-primary);
    }
    
    /* Grid de valores de referencia */
    .ref-values-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: var(--space-md);
        margin-top: var(--space-md);
        padding-top: var(--space-md);
        border-top: 2px dashed var(--color-border);
    }
    
    @media (min-width: 768px) {
        .ref-values-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    
    .ref-group {
        background: rgba(var(--color-card-rgb), 0.5);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: var(--space-md);
        transition: all var(--transition-base);
    }
    
    .ref-group:hover {
        border-color: var(--color-info);
        background: rgba(var(--color-info-rgb), 0.05);
    }
    
    .ref-title {
        font-size: var(--font-size-xs);
        font-weight: 700;
        color: var(--color-text-secondary);
        text-transform: uppercase;
        margin-bottom: var(--space-sm);
        display: flex;
        align-items: center;
        gap: var(--space-xs);
    }
    
    .ref-title.hombre {
        color: #3b82f6;
    }
    
    .ref-title.mujer {
        color: #ec4899;
    }
    
    .ref-title.pediatria {
        color: #10b981;
    }
    
    .input-range {
        display: flex;
        align-items: center;
        gap: var(--space-sm);
    }
    
    .input-range .form-control {
        flex: 1;
        text-align: center;
    }
    
    .range-separator {
        color: var(--color-text-secondary);
        font-weight: 500;
    }
    
    /* Botones de acción en filas */
    .param-actions {
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
        cursor: pointer;
    }
    
    .btn-icon:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-sm);
    }
    
    .btn-icon.remove:hover {
        background: var(--color-danger);
        color: white;
        border-color: var(--color-danger);
    }
    
    .btn-icon.drag:hover {
        background: var(--color-info);
        color: white;
        border-color: var(--color-info);
    }
    
    /* Campos de formulario */
    .form-group {
        margin-bottom: var(--space-md);
    }
    
    .form-label {
        display: block;
        font-weight: 500;
        color: var(--color-text);
        margin-bottom: var(--space-xs);
        font-size: var(--font-size-sm);
    }
    
    .form-control, .form-select {
        width: 100%;
        padding: var(--space-sm) var(--space-md);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        background: var(--color-card);
        color: var(--color-text);
        font-family: var(--font-family);
        font-size: var(--font-size-base);
        transition: all var(--transition-base);
    }
    
    .form-control:focus, .form-select:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.1);
    }
    
    .form-control-sm {
        padding: var(--space-xs) var(--space-sm);
        font-size: var(--font-size-sm);
    }
    
    /* Estado vacío */
    .empty-state {
        text-align: center;
        padding: var(--space-xl);
        color: var(--color-text-secondary);
        border: 2px dashed var(--color-border);
        border-radius: var(--radius-lg);
        background: var(--color-surface);
    }
    
    .empty-icon {
        font-size: 3rem;
        color: var(--color-border);
        margin-bottom: var(--space-md);
        opacity: 0.5;
    }
    
    /* ==========================================================================
       RESPONSIVE DESIGN
       ========================================================================== */
    @media (max-width: 991px) {
        .main-content {
            padding: var(--space-md);
        }
        
        .header-content {
            padding: var(--space-md);
        }
        
        .test-header {
            flex-direction: column;
            gap: var(--space-md);
        }
        
        .test-stats {
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .stat-item {
            min-width: 80px;
        }
    }
    
    @media (max-width: 767px) {
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
        
        .ref-values-grid {
            grid-template-columns: 1fr;
        }
        
        .input-range {
            flex-direction: column;
            gap: var(--space-xs);
        }
        
        .input-range .form-control {
            width: 100%;
        }
        
        .range-separator {
            display: none;
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
    
    .row {
        display: flex;
        flex-wrap: wrap;
        margin: calc(var(--space-sm) * -1);
    }
    
    .row > * {
        padding: var(--space-sm);
    }
    
    .col-md-5 { flex: 0 0 41.666667%; max-width: 41.666667%; }
    .col-md-3 { flex: 0 0 25%; max-width: 25%; }
    .col-md-1 { flex: 0 0 8.333333%; max-width: 8.333333%; }
    
    .w-100 { width: 100%; }
    
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-left { text-align: left; }
    
    .fw-bold { font-weight: 700; }
    .fw-semibold { font-weight: 600; }
    .fw-medium { font-weight: 500; }
    .fw-normal { font-weight: 400; }
    
    .flex-grow-1 { flex-grow: 1; }
    .flex-shrink-0 { flex-shrink: 0; }
    </style>
    
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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
                            <?php echo strtoupper(substr($_SESSION['nombre'], 0, 1)); ?>
                        </div>
                        <div class="header-details">
                            <span class="header-name"><?php echo htmlspecialchars($_SESSION['nombre']); ?></span>
                            <span class="header-role">Administrador de Laboratorio</span>
                        </div>
                    </div>
                    
                    <!-- Botón de volver -->
                    <a href="catalogo_pruebas.php" class="action-btn secondary">
                        <i class="bi bi-arrow-left"></i>
                        Volver al Catálogo
                    </a>
                </div>
            </div>
        </header>
        
        <!-- Contenido Principal -->
        <main class="main-content">
            <!-- Información de la prueba -->
            <div class="test-info-banner animate-in">
                <div class="test-header">
                    <div>
                        <h1 class="test-title">
                            <i class="bi bi-list-check"></i>
                            <?php echo htmlspecialchars($prueba['nombre_prueba']); ?>
                        </h1>
                        <div class="test-code"><?php echo htmlspecialchars($prueba['codigo_prueba']); ?></div>
                        <p class="text-muted mt-2"><?php echo htmlspecialchars($prueba['descripcion'] ?? 'Sin descripción'); ?></p>
                    </div>
                    <div class="test-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $total_parametros; ?></div>
                            <div class="stat-label">Parámetros</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo count($con_valores); ?></div>
                            <div class="stat-label">Con Valores</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo htmlspecialchars($prueba['muestra_requerida'] ?: 'N/A'); ?></div>
                            <div class="stat-label">Muestra</div>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i>
                            Arrastra y suelta para reordenar los parámetros
                        </small>
                    </div>
                    <div>
                        <small class="text-success">
                            <i class="bi bi-clock"></i>
                            Tiempo estimado: <?php echo $prueba['tiempo_procesamiento_horas']; ?> horas
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Formulario de parámetros -->
            <div class="params-form-container animate-in delay-1">
                <div class="section-header mb-4">
                    <h3 class="section-title">
                        <i class="bi bi-sliders section-title-icon"></i>
                        Configuración de Parámetros
                    </h3>
                    <button type="button" class="action-btn" onclick="addParamRow()">
                        <i class="bi bi-plus-lg"></i>
                        Agregar Parámetro
                    </button>
                </div>
                
                <form id="paramsForm" action="api/save_parameters.php" method="POST">
                    <input type="hidden" name="id_prueba" value="<?php echo $id_prueba; ?>">
                    <input type="hidden" name="param_order" id="paramOrder" value="">
                    
                    <div id="paramsContainer" class="mb-4">
                        <?php if (count($parametros) > 0): ?>
                            <?php foreach ($parametros as $idx => $param): ?>
                                <div class="param-row animate-in delay-<?php echo min($idx + 1, 4); ?>" 
                                     data-id="<?php echo $param['id_parametro']; ?>">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="drag-handle flex-shrink-0" title="Arrastrar para reordenar">
                                            <i class="bi bi-grip-vertical"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="row">
                                                <div class="col-md-5 mb-3">
                                                    <label class="form-label">Nombre del Parámetro *</label>
                                                    <input type="text" name="params[<?php echo $idx; ?>][nombre]" 
                                                           class="form-control" 
                                                           value="<?php echo htmlspecialchars($param['nombre_parametro']); ?>" 
                                                           required
                                                           placeholder="Ej: Glucosa, Hemoglobina, etc.">
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">Unidad de Medida</label>
                                                    <input type="text" name="params[<?php echo $idx; ?>][unidad]" 
                                                           class="form-control" 
                                                           value="<?php echo htmlspecialchars($param['unidad_medida']); ?>" 
                                                           placeholder="mg/dL, %, g/dL...">
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">Tipo de Dato</label>
                                                    <select name="params[<?php echo $idx; ?>][tipo]" class="form-select">
                                                        <option value="Numérico" <?php echo $param['tipo_dato'] === 'Numérico' ? 'selected' : ''; ?>>Numérico</option>
                                                        <option value="Texto" <?php echo $param['tipo_dato'] === 'Texto' ? 'selected' : ''; ?>>Texto</option>
                                                        <option value="Selección" <?php echo $param['tipo_dato'] === 'Selección' ? 'selected' : ''; ?>>Selección</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-1 mb-3 d-flex align-items-end">
                                                    <button type="button" class="btn-icon remove" 
                                                            onclick="removeParam(this)" 
                                                            title="Eliminar parámetro">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <input type="hidden" name="params[<?php echo $idx; ?>][id_parametro]" value="<?php echo $param['id_parametro']; ?>">
                                            
                                            <div class="ref-values-grid">
                                                <div class="ref-group">
                                                    <div class="ref-title hombre">
                                                        <i class="bi bi-gender-male"></i>
                                                        Hombres
                                                    </div>
                                                    <div class="input-range">
                                                        <input type="number" step="0.0001" name="params[<?php echo $idx; ?>][h_min]" 
                                                               class="form-control form-control-sm" 
                                                               value="<?php echo $param['valor_ref_hombre_min']; ?>" 
                                                               placeholder="Mínimo">
                                                        <span class="range-separator">-</span>
                                                        <input type="number" step="0.0001" name="params[<?php echo $idx; ?>][h_max]" 
                                                               class="form-control form-control-sm" 
                                                               value="<?php echo $param['valor_ref_hombre_max']; ?>" 
                                                               placeholder="Máximo">
                                                    </div>
                                                </div>
                                                <div class="ref-group">
                                                    <div class="ref-title mujer">
                                                        <i class="bi bi-gender-female"></i>
                                                        Mujeres
                                                    </div>
                                                    <div class="input-range">
                                                        <input type="number" step="0.0001" name="params[<?php echo $idx; ?>][m_min]" 
                                                               class="form-control form-control-sm" 
                                                               value="<?php echo $param['valor_ref_mujer_min']; ?>" 
                                                               placeholder="Mínimo">
                                                        <span class="range-separator">-</span>
                                                        <input type="number" step="0.0001" name="params[<?php echo $idx; ?>][m_max]" 
                                                               class="form-control form-control-sm" 
                                                               value="<?php echo $param['valor_ref_mujer_max']; ?>" 
                                                               placeholder="Máximo">
                                                    </div>
                                                </div>
                                                <div class="ref-group">
                                                    <div class="ref-title pediatria">
                                                        <i class="bi bi-emoji-smile"></i>
                                                        Pediatría
                                                    </div>
                                                    <div class="input-range">
                                                        <input type="number" step="0.0001" name="params[<?php echo $idx; ?>][p_min]" 
                                                               class="form-control form-control-sm" 
                                                               value="<?php echo $param['valor_ref_pediatrico_min']; ?>" 
                                                               placeholder="Mínimo">
                                                        <span class="range-separator">-</span>
                                                        <input type="number" step="0.0001" name="params[<?php echo $idx; ?>][p_max]" 
                                                               class="form-control form-control-sm" 
                                                               value="<?php echo $param['valor_ref_pediatrico_max']; ?>" 
                                                               placeholder="Máximo">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <i class="bi bi-list-check"></i>
                                </div>
                                <h4 class="text-muted mb-2">No hay parámetros configurados</h4>
                                <p class="text-muted mb-3">Comience agregando el primer parámetro para esta prueba</p>
                                <button type="button" class="action-btn" onclick="addParamRow()">
                                    <i class="bi bi-plus-lg"></i>
                                    Agregar Primer Parámetro
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mt-4 pt-4 border-top">
                        <div>
                            <small class="text-muted">
                                <i class="bi bi-lightbulb"></i>
                                Los parámetros se mostrarán en el orden que los organice aquí
                            </small>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="action-btn secondary" onclick="window.history.back()">
                                <i class="bi bi-x-circle"></i>
                                Cancelar
                            </button>
                            <button type="submit" class="action-btn">
                                <i class="bi bi-save"></i>
                                Guardar Cambios
                            </button>
                        </div>
                    </div>
                </form>
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
            themeSwitch: document.getElementById('themeSwitch'),
            paramsContainer: document.getElementById('paramsContainer'),
            paramsForm: document.getElementById('paramsForm'),
            paramOrder: document.getElementById('paramOrder')
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
        // GESTIÓN DE PARÁMETROS
        // ==========================================================================
        class ParametersManager {
            constructor() {
                this.paramCounter = <?php echo count($parametros); ?>;
                this.sortable = null;
                this.initSortable();
                this.setupFormSubmission();
            }
            
            initSortable() {
                if (DOM.paramsContainer && Sortable) {
                    this.sortable = Sortable.create(DOM.paramsContainer, {
                        animation: 150,
                        handle: '.drag-handle',
                        ghostClass: 'dragging',
                        onEnd: (evt) => {
                            this.updateParamOrder();
                        }
                    });
                }
            }
            
            updateParamOrder() {
                if (!DOM.paramOrder) return;
                
                const order = [];
                const paramRows = DOM.paramsContainer.querySelectorAll('.param-row');
                
                paramRows.forEach((row, index) => {
                    const paramId = row.getAttribute('data-id');
                    if (paramId) {
                        order.push(paramId);
                    }
                });
                
                DOM.paramOrder.value = order.join(',');
            }
            
            addParamRow() {
                if (!DOM.paramsContainer) return;
                
                // Remover estado vacío si existe
                const emptyState = DOM.paramsContainer.querySelector('.empty-state');
                if (emptyState) {
                    emptyState.remove();
                }
                
                const row = document.createElement('div');
                row.className = 'param-row animate-in';
                row.setAttribute('data-id', 'new_' + Date.now());
                
                const currentIndex = this.paramCounter;
                row.innerHTML = `
                    <div class="d-flex align-items-start gap-3">
                        <div class="drag-handle flex-shrink-0" title="Arrastrar para reordenar">
                            <i class="bi bi-grip-vertical"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="row">
                                <div class="col-md-5 mb-3">
                                    <label class="form-label">Nombre del Parámetro *</label>
                                    <input type="text" name="params[${currentIndex}][nombre]" 
                                           class="form-control" 
                                           required
                                           placeholder="Ej: Glucosa, Hemoglobina, etc.">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Unidad de Medida</label>
                                    <input type="text" name="params[${currentIndex}][unidad]" 
                                           class="form-control" 
                                           placeholder="mg/dL, %, g/dL...">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Tipo de Dato</label>
                                    <select name="params[${currentIndex}][tipo]" class="form-select">
                                        <option value="Numérico">Numérico</option>
                                        <option value="Texto">Texto</option>
                                        <option value="Selección">Selección</option>
                                    </select>
                                </div>
                                <div class="col-md-1 mb-3 d-flex align-items-end">
                                    <button type="button" class="btn-icon remove" 
                                            onclick="parametersManager.removeParam(this)" 
                                            title="Eliminar parámetro">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <input type="hidden" name="params[${currentIndex}][id_parametro]" value="">
                            
                            <div class="ref-values-grid">
                                <div class="ref-group">
                                    <div class="ref-title hombre">
                                        <i class="bi bi-gender-male"></i>
                                        Hombres
                                    </div>
                                    <div class="input-range">
                                        <input type="number" step="0.0001" name="params[${currentIndex}][h_min]" 
                                               class="form-control form-control-sm" 
                                               placeholder="Mínimo">
                                        <span class="range-separator">-</span>
                                        <input type="number" step="0.0001" name="params[${currentIndex}][h_max]" 
                                               class="form-control form-control-sm" 
                                               placeholder="Máximo">
                                    </div>
                                </div>
                                <div class="ref-group">
                                    <div class="ref-title mujer">
                                        <i class="bi bi-gender-female"></i>
                                        Mujeres
                                    </div>
                                    <div class="input-range">
                                        <input type="number" step="0.0001" name="params[${currentIndex}][m_min]" 
                                               class="form-control form-control-sm" 
                                               placeholder="Mínimo">
                                        <span class="range-separator">-</span>
                                        <input type="number" step="0.0001" name="params[${currentIndex}][m_max]" 
                                               class="form-control form-control-sm" 
                                               placeholder="Máximo">
                                    </div>
                                </div>
                                <div class="ref-group">
                                    <div class="ref-title pediatria">
                                        <i class="bi bi-emoji-smile"></i>
                                        Pediatría
                                    </div>
                                    <div class="input-range">
                                        <input type="number" step="0.0001" name="params[${currentIndex}][p_min]" 
                                               class="form-control form-control-sm" 
                                               placeholder="Mínimo">
                                        <span class="range-separator">-</span>
                                        <input type="number" step="0.0001" name="params[${currentIndex}][p_max]" 
                                               class="form-control form-control-sm" 
                                               placeholder="Máximo">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                DOM.paramsContainer.appendChild(row);
                this.paramCounter++;
                
                // Aplicar animación
                setTimeout(() => {
                    row.style.animation = 'fadeInUp 0.6s ease-out forwards';
                }, 10);
                
                // Actualizar orden
                this.updateParamOrder();
            }
            
            removeParam(button) {
                const row = button.closest('.param-row');
                if (!row) return;
                
                // Mostrar confirmación solo para parámetros existentes (no nuevos)
                const paramId = row.getAttribute('data-id');
                const isNew = paramId.startsWith('new_');
                
                if (!isNew) {
                    this.showDeleteConfirmation(row);
                } else {
                    row.remove();
                    this.checkEmptyState();
                    this.updateParamOrder();
                }
            }
            
            showDeleteConfirmation(row) {
                loadSweetAlert().then(() => {
                    Swal.fire({
                        title: '¿Eliminar parámetro?',
                        text: 'Esta acción no se puede deshacer',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, eliminar',
                        confirmButtonColor: '#dc3545',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            row.remove();
                            this.checkEmptyState();
                            this.updateParamOrder();
                            
                            Swal.fire({
                                icon: 'success',
                                title: 'Eliminado',
                                text: 'Parámetro eliminado correctamente',
                                timer: 1500,
                                showConfirmButton: false
                            });
                        }
                    });
                });
            }
            
            checkEmptyState() {
                if (!DOM.paramsContainer) return;
                
                const paramRows = DOM.paramsContainer.querySelectorAll('.param-row');
                if (paramRows.length === 0) {
                    const emptyState = document.createElement('div');
                    emptyState.className = 'empty-state';
                    emptyState.innerHTML = `
                        <div class="empty-icon">
                            <i class="bi bi-list-check"></i>
                        </div>
                        <h4 class="text-muted mb-2">No hay parámetros configurados</h4>
                        <p class="text-muted mb-3">Comience agregando el primer parámetro para esta prueba</p>
                        <button type="button" class="action-btn" onclick="parametersManager.addParamRow()">
                            <i class="bi bi-plus-lg"></i>
                            Agregar Primer Parámetro
                        </button>
                    `;
                    DOM.paramsContainer.appendChild(emptyState);
                }
            }
            
            setupFormSubmission() {
                if (!DOM.paramsForm) return;
                
                DOM.paramsForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.submitForm();
                });
            }
            
            submitForm() {
                // Actualizar orden antes de enviar
                this.updateParamOrder();
                
                // Validar que al menos haya un parámetro
                const paramRows = DOM.paramsContainer.querySelectorAll('.param-row');
                if (paramRows.length === 0) {
                    this.showError('Debe agregar al menos un parámetro');
                    return;
                }
                
                // Validar nombres de parámetros
                const paramNames = new Set();
                const inputs = DOM.paramsForm.querySelectorAll('input[name$="[nombre]"]');
                let hasEmptyName = false;
                
                inputs.forEach(input => {
                    const value = input.value.trim();
                    if (!value) {
                        hasEmptyName = true;
                        input.style.borderColor = 'var(--color-danger)';
                    } else {
                        input.style.borderColor = '';
                        if (paramNames.has(value.toLowerCase())) {
                            this.showError(`El nombre "${value}" está duplicado. Los nombres deben ser únicos.`);
                            input.style.borderColor = 'var(--color-danger)';
                            throw new Error('Duplicate name');
                        }
                        paramNames.add(value.toLowerCase());
                    }
                });
                
                if (hasEmptyName) {
                    this.showError('Todos los parámetros deben tener un nombre');
                    return;
                }
                
                // Mostrar carga
                loadSweetAlert().then(() => {
                    Swal.fire({
                        title: 'Guardando...',
                        text: 'Por favor espere mientras se guardan los cambios',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    const formData = new FormData(DOM.paramsForm);
                    
                    fetch(DOM.paramsForm.action, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Guardado!',
                                text: data.message,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'Error al guardar los parámetros'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de conexión',
                            text: 'No se pudo conectar con el servidor'
                        });
                    });
                });
            }
            
            showError(message) {
                loadSweetAlert().then(() => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de validación',
                        text: message,
                        confirmButtonColor: '#0d6efd'
                    });
                });
            }
        }
        
        // ==========================================================================
        // INICIALIZACIÓN DE LA APLICACIÓN
        // ==========================================================================
        document.addEventListener('DOMContentLoaded', () => {
            const themeManager = new ThemeManager();
            const parametersManager = new ParametersManager();
            
            window.parametersManager = parametersManager;
            
            console.log('Gestión de Parámetros inicializada');
            console.log('Prueba: <?php echo htmlspecialchars($prueba["nombre_prueba"]); ?>');
            console.log('Parámetros: <?php echo $total_parametros; ?>');
        });
        
        // ==========================================================================
        // FUNCIONES AUXILIARES
        // ==========================================================================
        function loadSweetAlert() {
            return new Promise((resolve) => {
                if (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') {
                    resolve();
                    return;
                }
                
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
                script.onload = resolve;
                document.head.appendChild(script);
            });
        }
        
        // ==========================================================================
        // POLYFILLS PARA NAVEGADORES ANTIGUOS
        // ==========================================================================
        if (!NodeList.prototype.forEach) {
            NodeList.prototype.forEach = Array.prototype.forEach;
        }
        
    })();
    
    // Estilos adicionales
    const additionalStyles = document.createElement('style');
    additionalStyles.textContent = `
        .swal2-popup {
            font-family: var(--font-family) !important;
        }
        
        .swal2-confirm {
            background-color: var(--color-primary) !important;
        }
        
        .swal2-cancel {
            background-color: var(--color-surface) !important;
            color: var(--color-text) !important;
            border: 1px solid var(--color-border) !important;
        }
        
        /* Efectos de arrastre */
        .sortable-ghost {
            opacity: 0.4;
            background: rgba(var(--color-primary-rgb), 0.1);
        }
        
        .sortable-chosen {
            background: rgba(var(--color-primary-rgb), 0.05);
            box-shadow: var(--shadow-md);
        }
        
        /* Animación para nuevos parámetros */
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .param-row:last-child {
            animation: slideInRight 0.4s ease-out;
        }
    `;
    document.head.appendChild(additionalStyles);
    </script>
</body>
</html>