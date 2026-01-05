<?php
// laboratory/crear_orden.php - Create a new clinical laboratory order
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Fetch all available tests grouped by category for selection
    $stmt = $conn->query("SELECT id_prueba, codigo_prueba, nombre_prueba, categoria, notas, precio, tiempo_procesamiento_horas, muestra_requerida FROM catalogo_pruebas ORDER BY categoria, nombre_prueba");
    $catalogo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $pruebas_por_categoria = [];
    foreach ($catalogo as $prueba) {
        $pruebas_por_categoria[$prueba['categoria'] ?? 'Sin Categoría'][] = $prueba;
    }
    
    // Obtener doctores para el selector
    $doctors = $conn->query("SELECT idUsuario, nombre, apellido FROM usuarios WHERE tipoUsuario = 'doc' ORDER BY apellido")->fetchAll();
    
    $page_title = "Nueva Orden de Laboratorio";
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Crear Orden de Laboratorio - Centro Médico Herrera Saenz">
    <title><?php echo $page_title; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">
    
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
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
       COMPONENTES ESPECÍFICOS PARA CREAR ORDEN
       ========================================================================== */
    
    /* Banner de bienvenida */
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
    
    /* Layout del formulario */
    .order-form-container {
        display: grid;
        grid-template-columns: 1fr 350px;
        gap: var(--space-xl);
        margin-bottom: var(--space-xl);
    }
    
    @media (max-width: 991px) {
        .order-form-container {
            grid-template-columns: 1fr;
        }
    }
    
    /* Panel de información del paciente */
    .patient-info-card {
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: var(--space-lg);
        margin-bottom: var(--space-lg);
        transition: all var(--transition-base);
    }
    
    .patient-info-card:hover {
        box-shadow: var(--shadow-lg);
    }
    
    /* Panel de selección de pruebas */
    .tests-selection-card {
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: var(--space-lg);
        transition: all var(--transition-base);
    }
    
    .tests-selection-card:hover {
        box-shadow: var(--shadow-lg);
    }
    
    /* Panel de resumen */
    .order-summary-card {
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: var(--space-lg);
        position: sticky;
        top: 120px;
        height: fit-content;
        transition: all var(--transition-base);
    }
    
    .order-summary-card:hover {
        box-shadow: var(--shadow-lg);
    }
    
    /* Categorías de pruebas */
    .category-section {
        margin-bottom: var(--space-xl);
    }
    
    .category-title {
        font-size: var(--font-size-lg);
        font-weight: 600;
        color: var(--color-primary);
        border-bottom: 2px solid var(--color-border);
        padding-bottom: var(--space-sm);
        margin-bottom: var(--space-lg);
        display: flex;
        align-items: center;
        gap: var(--space-sm);
    }
    
    /* Grid de pruebas */
    .tests-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: var(--space-md);
    }
    
    /* Tarjetas de prueba seleccionable */
    .test-selection-card {
        border: 2px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: var(--space-md);
        cursor: pointer;
        transition: all var(--transition-base);
        display: flex;
        align-items: center;
        gap: var(--space-md);
    }
    
    .test-selection-card:hover {
        border-color: var(--color-primary);
        background: rgba(var(--color-primary-rgb), 0.05);
        transform: translateY(-2px);
    }
    
    .test-selection-card.selected {
        border-color: var(--color-primary);
        background: rgba(var(--color-primary-rgb), 0.1);
        box-shadow: var(--shadow-sm);
    }
    
    .test-selection-checkbox {
        width: 20px;
        height: 20px;
        border-radius: var(--radius-sm);
        border: 2px solid var(--color-border);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all var(--transition-base);
        flex-shrink: 0;
    }
    
    .test-selection-card.selected .test-selection-checkbox {
        background: var(--color-primary);
        border-color: var(--color-primary);
    }
    
    .test-selection-card.selected .test-selection-checkbox::after {
        content: '✓';
        color: white;
        font-size: var(--font-size-sm);
        font-weight: bold;
    }
    
    .test-selection-info {
        flex: 1;
        min-width: 0;
    }
    
    .test-selection-name {
        font-weight: 500;
        color: var(--color-text);
        margin-bottom: var(--space-xs);
        font-size: var(--font-size-sm);
    }
    
    .test-selection-details {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .test-selection-price {
        font-weight: 600;
        color: var(--color-success);
        font-size: var(--font-size-sm);
    }
    
    .test-selection-time {
        font-size: var(--font-size-xs);
        color: var(--color-text-secondary);
    }
    
    /* Lista de pruebas seleccionadas */
    .selected-tests-list {
        margin: var(--space-lg) 0;
        max-height: 300px;
        overflow-y: auto;
        padding-right: var(--space-sm);
    }
    
    .selected-test-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: var(--space-sm) 0;
        border-bottom: 1px solid var(--color-border);
        font-size: var(--font-size-sm);
    }
    
    .selected-test-item:last-child {
        border-bottom: none;
    }
    
    .remove-test {
        color: var(--color-danger);
        cursor: pointer;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: var(--radius-sm);
        transition: all var(--transition-base);
    }
    
    .remove-test:hover {
        background: var(--color-danger);
        color: white;
    }
    
    /* Total de la orden */
    .order-total {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: var(--font-size-xl);
        font-weight: 700;
        margin-top: var(--space-lg);
        padding-top: var(--space-lg);
        border-top: 2px solid var(--color-border);
    }
    
    .order-total-amount {
        color: var(--color-success);
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
    
    /* Select2 personalizado */
    .select2-container--default .select2-selection--single {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        background: var(--color-card);
        height: 42px;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: var(--color-text);
        line-height: 42px;
        padding-left: var(--space-md);
    }
    
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 42px;
    }
    
    .select2-dropdown {
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
    }
    
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: rgba(var(--color-primary-rgb), 0.1);
        color: var(--color-primary);
    }
    
    /* ==========================================================================
       RESPONSIVE DESIGN
       ========================================================================== */
    @media (max-width: 991px) {
        .order-form-container {
            grid-template-columns: 1fr;
        }
        
        .order-summary-card {
            position: static;
        }
        
        .main-content {
            padding: var(--space-md);
        }
        
        .welcome-banner {
            padding: var(--space-lg);
        }
        
        .welcome-banner h1 {
            font-size: var(--font-size-2xl);
        }
        
        .tests-grid {
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
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
        
        .tests-grid {
            grid-template-columns: 1fr;
        }
        
        .welcome-banner {
            text-align: center;
            padding: var(--space-lg);
        }
        
        .welcome-banner::before {
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
        margin: calc(var(--space-md) * -1);
    }
    
    .row > * {
        padding: var(--space-md);
    }
    
    .col-md-7 { flex: 0 0 58.333333%; max-width: 58.333333%; }
    .col-md-5 { flex: 0 0 41.666667%; max-width: 41.666667%; }
    .col-md-4 { flex: 0 0 33.333333%; max-width: 33.333333%; }
    .col-md-8 { flex: 0 0 66.666667%; max-width: 66.666667%; }
    
    .w-100 { width: 100%; }
    
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-left { text-align: left; }
    
    .fw-bold { font-weight: 700; }
    .fw-semibold { font-weight: 600; }
    .fw-medium { font-weight: 500; }
    .fw-normal { font-weight: 400; }
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
                            <span class="header-role">Crear Orden de Laboratorio</span>
                        </div>
                    </div>
                    
                    <!-- Botón de volver -->
                    <a href="index.php" class="action-btn secondary">
                        <i class="bi bi-arrow-left"></i>
                        Volver a Laboratorios
                    </a>
                </div>
            </div>
        </header>
        
        <!-- Contenido Principal -->
        <main class="main-content">
            <!-- Banner de bienvenida -->
            <div class="welcome-banner animate-in">
                <h1>Nueva Orden de Laboratorio</h1>
                <p>Complete la información para generar una nueva solicitud de pruebas</p>
            </div>
            
            <!-- Formulario de orden -->
            <form id="orderForm" action="api/create_order.php" method="POST">
                <div class="order-form-container">
                    <!-- Panel izquierdo: Información y selección de pruebas -->
                    <div>
                        <!-- Información del paciente -->
                        <div class="patient-info-card animate-in delay-1">
                            <h3 class="section-title mb-4">
                                <i class="bi bi-person-badge section-title-icon"></i>
                                Información del Paciente
                            </h3>
                            
                            <div class="row">
                                <div class="col-md-7">
                                    <div class="form-group">
                                        <label class="form-label">Paciente *</label>
                                        <select id="id_paciente" name="id_paciente" class="form-control" required>
                                            <option value="">Buscar paciente...</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label class="form-label">Doctor Solicitante</label>
                                        <select id="id_doctor" name="id_doctor" class="form-control">
                                            <option value="">Seleccionar doctor...</option>
                                            <?php foreach ($doctors as $doc): ?>
                                                <option value="<?php echo $doc['idUsuario']; ?>">
                                                    Dr. <?php echo htmlspecialchars($doc['nombre'] . ' ' . $doc['apellido']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="form-label">Prioridad</label>
                                        <select name="prioridad" class="form-control">
                                            <option value="Normal">Normal</option>
                                            <option value="Urgente">Urgente</option>
                                            <option value="Emergencia">Emergencia</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label class="form-label">Instrucciones Especiales</label>
                                        <input type="text" name="instrucciones" class="form-control" 
                                               placeholder="Ej: Ayuno de 8 horas, muestra en ayunas, etc.">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Selección de pruebas -->
                        <div class="tests-selection-card animate-in delay-2">
                            <div class="section-header mb-4">
                                <h3 class="section-title">
                                    <i class="bi bi-clipboard-check section-title-icon"></i>
                                    Selección de Pruebas
                                </h3>
                                <div class="text-muted">
                                    <?php echo count($catalogo); ?> pruebas disponibles
                                </div>
                            </div>
                            
                            <?php if (count($catalogo) > 0): ?>
                                <?php foreach ($pruebas_por_categoria as $categoria => $pruebas): ?>
                                    <div class="category-section">
                                        <h4 class="category-title">
                                            <i class="bi bi-folder2"></i>
                                            <?php echo htmlspecialchars($categoria); ?>
                                        </h4>
                                        
                                        <div class="tests-grid">
                                            <?php foreach ($pruebas as $prueba): ?>
                                                <div class="test-selection-card" 
                                                     onclick="toggleTest(this, <?php echo htmlspecialchars(json_encode($prueba)); ?>)"
                                                     data-id="<?php echo $prueba['id_prueba']; ?>">
                                                    <input type="checkbox" name="pruebas[]" 
                                                           value="<?php echo $prueba['id_prueba']; ?>" 
                                                           class="d-none">
                                                    <div class="test-selection-checkbox"></div>
                                                    <div class="test-selection-info">
                                                        <div class="test-selection-name">
                                                            <?php echo htmlspecialchars($prueba['nombre_prueba']); ?>
                                                        </div>
                                                        <div class="test-selection-details">
                                                            <span class="test-selection-price">
                                                                Q<?php echo number_format($prueba['precio'] ?? 0, 2); ?>
                                                            </span>
                                                            <span class="test-selection-time">
                                                                <i class="bi bi-clock"></i> <?php echo $prueba['tiempo_procesamiento_horas']; ?>h
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state text-center py-5">
                                    <i class="bi bi-clipboard-x empty-icon"></i>
                                    <h4 class="text-muted mb-2">No hay pruebas disponibles</h4>
                                    <p class="text-muted">Configure primero el catálogo de pruebas</p>
                                    <a href="catalogo_pruebas.php" class="action-btn secondary">
                                        <i class="bi bi-gear"></i>
                                        Ir al Catálogo
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Panel derecho: Resumen -->
                    <div class="order-summary-card animate-in delay-3">
                        <h3 class="section-title mb-4">
                            <i class="bi bi-receipt section-title-icon"></i>
                            Resumen de la Orden
                        </h3>
                        
                        <div class="selected-tests-list" id="selectedTestsList">
                            <div class="empty-state text-center py-4">
                                <i class="bi bi-cart empty-icon"></i>
                                <p class="text-muted mb-0">No hay pruebas seleccionadas</p>
                                <small class="text-muted">Haga clic en las pruebas para agregarlas</small>
                            </div>
                        </div>
                        
                        <div class="order-total">
                            <span>Total:</span>
                            <span id="orderTotal" class="order-total-amount">Q0.00</span>
                        </div>
                        
                        <div class="form-group mt-4">
                            <label class="form-label">Observaciones</label>
                            <textarea name="observaciones" class="form-control" rows="3" 
                                      placeholder="Observaciones adicionales..."></textarea>
                        </div>
                        
                        <button type="submit" class="action-btn w-100 mt-4 py-3">
                            <i class="bi bi-file-earmark-check"></i>
                            Generar Orden de Laboratorio
                        </button>
                        
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <i class="bi bi-info-circle"></i>
                                La orden será creada en estado "Pendiente"
                            </small>
                        </div>
                    </div>
                </div>
            </form>
        </main>
    </div>
    
    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <!-- JavaScript Optimizado (mismo que index.php) -->
    <script>
    // Dashboard de Laboratorio Reingenierizado
    
    (function() {
        'use strict';
        
        // ==========================================================================
        // CONFIGURACIÓN Y CONSTANTES
        // ========================================================================== */
        const CONFIG = {
            themeKey: 'dashboard-theme',
            transitionDuration: 300,
            animationDelay: 100
        };
        
        // ==========================================================================
        // REFERENCIAS A ELEMENTOS DOM
        // ========================================================================== */
        const DOM = {
            html: document.documentElement,
            body: document.body,
            themeSwitch: document.getElementById('themeSwitch')
        };
        
        // ==========================================================================
        // MANEJO DE TEMA (DÍA/NOCHE)
        // ========================================================================== */
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
        // ========================================================================== */
        class DynamicComponents {
            constructor() {
                this.setupAnimations();
                this.setupSelect2();
                this.setupFormValidation();
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
                
                document.querySelectorAll('.patient-info-card, .tests-selection-card, .order-summary-card').forEach(el => {
                    observer.observe(el);
                });
            }
            
            setupSelect2() {
                // Configurar Select2 para búsqueda de pacientes
                if ($('#id_paciente').length) {
                    $('#id_paciente').select2({
                        placeholder: 'Buscar paciente por nombre, apellido o DPI...',
                        allowClear: true,
                        minimumInputLength: 2,
                        language: {
                            inputTooShort: function() {
                                return 'Escriba al menos 2 caracteres para buscar';
                            },
                            searching: function() {
                                return 'Buscando pacientes...';
                            },
                            noResults: function() {
                                return 'No se encontraron pacientes';
                            }
                        },
                        ajax: {
                            url: 'api/search_patients.php',
                            dataType: 'json',
                            delay: 250,
                            data: function(params) {
                                return {
                                    q: params.term
                                };
                            },
                            processResults: function(data) {
                                if (!data.success) {
                                    console.error('Error al buscar pacientes:', data.message);
                                    return { results: [] };
                                }
                                
                                return {
                                    results: data.results.map(function(p) {
                                        return {
                                            id: p.id,
                                            text: p.label,
                                            data: p
                                        };
                                    })
                                };
                            },
                            cache: true
                        }
                    });
                    
                    $('#id_doctor').select2({
                        theme: 'default',
                        placeholder: 'Seleccionar doctor...',
                        allowClear: true
                    });
                }
            }
            
            setupFormValidation() {
                const form = document.getElementById('orderForm');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        const paciente = $('#id_paciente').val();
                        const selectedTests = window.selectedTests || [];
                        
                        if (!paciente) {
                            e.preventDefault();
                            showError('Debe seleccionar un paciente');
                            return;
                        }
                        
                        if (selectedTests.length === 0) {
                            e.preventDefault();
                            showError('Debe seleccionar al menos una prueba');
                            return;
                        }
                    });
                }
            }
        }
        
        // ==========================================================================
        // INICIALIZACIÓN DE LA APLICACIÓN
        // ========================================================================== */
        document.addEventListener('DOMContentLoaded', () => {
            const themeManager = new ThemeManager();
            const dynamicComponents = new DynamicComponents();
            
            window.orderDashboard = {
                theme: themeManager,
                components: dynamicComponents,
                selectedTests: []
            };
            
            console.log('Crear Orden inicializado');
        });
        
        // ==========================================================================
        // FUNCIONES AUXILIARES
        // ========================================================================== */
        function calcularEdad(fechaNacimiento) {
            const hoy = new Date();
            const nacimiento = new Date(fechaNacimiento);
            let edad = hoy.getFullYear() - nacimiento.getFullYear();
            const mes = hoy.getMonth() - nacimiento.getMonth();
            
            if (mes < 0 || (mes === 0 && hoy.getDate() < nacimiento.getDate())) {
                edad--;
            }
            return edad;
        }
        
        function showError(mensaje) {
            // Crear notificación de error
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert-error';
            errorDiv.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: var(--color-danger);
                color: white;
                padding: var(--space-md) var(--space-lg);
                border-radius: var(--radius-md);
                z-index: 9999;
                box-shadow: var(--shadow-lg);
                animation: slideIn 0.3s ease;
            `;
            errorDiv.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span>${mensaje}</span>
                </div>
            `;
            
            document.body.appendChild(errorDiv);
            
            setTimeout(() => {
                errorDiv.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => errorDiv.remove(), 300);
            }, 3000);
            
            // Agregar animaciones CSS si no existen
            if (!document.querySelector('#error-animations')) {
                const style = document.createElement('style');
                style.id = 'error-animations';
                style.textContent = `
                    @keyframes slideIn {
                        from { transform: translateX(100%); opacity: 0; }
                        to { transform: translateX(0); opacity: 1; }
                    }
                    @keyframes slideOut {
                        from { transform: translateX(0); opacity: 1; }
                        to { transform: translateX(100%); opacity: 0; }
                    }
                `;
                document.head.appendChild(style);
            }
        }
        
    })();
    
    // ==========================================================================
    // FUNCIONES ESPECÍFICAS PARA CREAR ORDEN
    // ========================================================================== */
    
    let selectedTests = [];
    
    function toggleTest(card, testData) {
        const checkbox = card.querySelector('input[type="checkbox"]');
        const index = selectedTests.findIndex(t => t.id_prueba === testData.id_prueba);
        
        if (index === -1) {
            // Agregar prueba
            selectedTests.push({
                ...testData,
                precio: parseFloat(testData.precio || testData.price || 0)
            });
            card.classList.add('selected');
            checkbox.checked = true;
        } else {
            // Remover prueba
            selectedTests.splice(index, 1);
            card.classList.remove('selected');
            checkbox.checked = false;
        }
        
        updateOrderSummary();
    }
    
    function updateOrderSummary() {
        const listContainer = document.getElementById('selectedTestsList');
        const totalElement = document.getElementById('orderTotal');
        
        if (selectedTests.length === 0) {
            listContainer.innerHTML = `
                <div class="empty-state text-center py-4">
                    <i class="bi bi-cart empty-icon"></i>
                    <p class="text-muted mb-0">No hay pruebas seleccionadas</p>
                    <small class="text-muted">Haga clic en las pruebas para agregarlas</small>
                </div>
            `;
            totalElement.textContent = 'Q0.00';
            return;
        }
        
        // Calcular total
        let total = 0;
        let html = '';
        
        selectedTests.forEach((test, index) => {
            total += test.precio;
            html += `
                <div class="selected-test-item">
                    <div class="test-name">${test.nombre_prueba}</div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="text-success fw-semibold">Q${test.precio.toFixed(2)}</span>
                        <i class="bi bi-x-circle remove-test" 
                           onclick="removeTest(${test.id_prueba})"
                           title="Remover prueba"></i>
                    </div>
                </div>
            `;
        });
        
        listContainer.innerHTML = html;
        totalElement.textContent = `Q${total.toFixed(2)}`;
    }
    
    function removeTest(testId) {
        const card = document.querySelector(`.test-selection-card[data-id="${testId}"]`);
        if (card) {
            const testData = selectedTests.find(t => t.id_prueba == testId);
            if (testData) {
                toggleTest(card, testData);
            }
        }
    }
    
    // SweetAlert2 para confirmación
    document.getElementById('orderForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (selectedTests.length === 0) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Debe seleccionar al menos una prueba',
                confirmButtonColor: '#0d6efd'
            });
            return;
        }
        
        const paciente = $('#id_paciente').val();
        if (!paciente) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Debe seleccionar un paciente',
                confirmButtonColor: '#0d6efd'
            });
            return;
        }
        
        Swal.fire({
            title: '¿Confirmar Orden?',
            html: `
                <div class="text-start">
                    <p>Se crear una orden con <strong>${selectedTests.length} pruebas</strong></p>
                    <p class="mb-0">Total: <strong class="text-success">${document.getElementById('orderTotal').textContent}</strong></p>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, generar orden',
            confirmButtonColor: '#0d6efd',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Mostrar carga
                Swal.fire({
                    title: 'Generando orden...',
                    text: 'Por favor espere',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Enviar formulario
                setTimeout(() => {
                    e.target.submit();
                }, 500);
            }
        });
    });
    
    // Cargar SweetAlert2 dinámicamente si es necesario
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
    
    // Estilos adicionales
    const additionalStyles = document.createElement('style');
    additionalStyles.textContent = `
        .alert-error {
            background: var(--color-danger);
            color: white;
            padding: var(--space-md) var(--space-lg);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-md);
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .swal2-popup {
            font-family: var(--font-family) !important;
        }
        
        .swal2-confirm {
            background-color: var(--color-primary) !important;
        }
    `;
    document.head.appendChild(additionalStyles);
    
    // Cargar SweetAlert2 al iniciar
    document.addEventListener('DOMContentLoaded', loadSweetAlert);
    </script>
</body>
</html>