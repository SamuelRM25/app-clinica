<?php
// laboratory/procesar_orden.php - Clinical results entry interface
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
    
    // Obtener información del usuario para el header
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['tipoUsuario'];
    $user_name = $_SESSION['nombre'];
    $user_specialty = $_SESSION['especialidad'] ?? 'Profesional de Laboratorio';
    
    $id_orden = $_GET['id'] ?? null;
    if (!$id_orden) {
        header("Location: index.php");
        exit;
    }
    
    // 1. Get order details with patient info
    $stmt = $conn->prepare("
        SELECT ol.*, p.nombre, p.apellido, p.genero, p.fecha_nacimiento,
               u.nombre as doctor_nombre, u.apellido as doctor_apellido
        FROM ordenes_laboratorio ol
        JOIN pacientes p ON ol.id_paciente = p.id_paciente
        LEFT JOIN usuarios u ON ol.id_doctor = u.idUsuario
        WHERE ol.id_orden = ?
    ");
    $stmt->execute([$id_orden]);
    $orden = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$orden) {
        header("Location: index.php");
        exit;
    }
    
    // 2. Get tests in this order with their parameters
    $stmt = $conn->prepare("
        SELECT op.*, cp.nombre_prueba, cp.codigo_prueba
        FROM orden_pruebas op
        JOIN catalogo_pruebas cp ON op.id_prueba = cp.id_prueba
        WHERE op.id_orden = ?
    ");
    $stmt->execute([$id_orden]);
    $pruebas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate patient age for reference values
    $edad = date_diff(date_create($orden['fecha_nacimiento']), date_create('today'))->y;
    $genero = $orden['genero'];
    
    $page_title = "Procesar Orden #" . $orden['numero_orden'] . " - Centro Médico Herrera Saenz";
    
} catch (Exception $e) {
    // Manejo de errores
    error_log("Error en procesar_orden: " . $e->getMessage());
    die("Error al cargar la orden. Por favor, contacte al administrador.");
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Procesar Orden de Laboratorio - Centro Médico Herrera Saenz">
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
    
    <!-- CSS Crítico (mismo que el dashboard) -->
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
    }
    
    /* ==========================================================================
       HEADER SUPERIOR
       ========================================================================== */
    .dashboard-header {
        position: sticky;
        top: 0;
        left: 0;
        right: 0;
        background-color: rgba(var(--color-card-rgb), 0.95);
        border-bottom: 1px solid var(--color-border);
        z-index: 900;
        backdrop-filter: blur(10px);
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
    
    /* Botón de regresar */
    .back-btn {
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
    
    .back-btn:hover {
        background: var(--color-primary);
        color: white;
        border-color: var(--color-primary);
        transform: translateY(-2px);
    }
    
    /* ==========================================================================
       CONTENIDO PRINCIPAL
       ========================================================================== */
    .main-content {
        flex: 1;
        padding: var(--space-lg);
        background-color: transparent;
        width: 100%;
        max-width: 1400px;
        margin: 0 auto;
    }
    
    /* ==========================================================================
       ESTILOS ESPECÍFICOS PARA PROCESAR ORDEN
       ========================================================================== */
    .patient-header-card {
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: var(--space-lg);
        margin-bottom: var(--space-xl);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: var(--space-lg);
        box-shadow: var(--shadow-md);
    }
    
    .test-processing-section {
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: var(--space-lg);
        margin-bottom: var(--space-lg);
        box-shadow: var(--shadow-sm);
        transition: all var(--transition-base);
    }
    
    .test-processing-section:hover {
        box-shadow: var(--shadow-lg);
    }
    
    .test-title-bar {
        background: var(--color-surface);
        padding: var(--space-md) var(--space-lg);
        border-radius: var(--radius-md);
        margin-bottom: var(--space-lg);
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-left: 4px solid var(--color-info);
    }
    
    .parameter-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-bottom: var(--space-lg);
    }
    
    .parameter-table thead {
        background: var(--color-surface);
    }
    
    .parameter-table th {
        padding: var(--space-md);
        text-align: left;
        font-weight: 600;
        color: var(--color-text);
        border-bottom: 2px solid var(--color-border);
        white-space: nowrap;
        font-size: var(--font-size-sm);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .parameter-table td {
        padding: var(--space-md);
        border-bottom: 1px solid var(--color-border);
        vertical-align: middle;
    }
    
    .parameter-table tbody tr {
        transition: all var(--transition-base);
    }
    
    .parameter-table tbody tr:hover {
        background: var(--color-surface);
    }
    
    .result-input {
        width: 120px;
        padding: var(--space-sm) var(--space-md);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        background: var(--color-bg);
        color: var(--color-text);
        font-weight: 600;
        font-size: var(--font-size-sm);
        transition: all var(--transition-base);
        text-align: center;
    }
    
    .result-input:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.1);
    }
    
    .flag-indicator {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: 800;
        color: white;
    }
    
    .flag-normal { background: var(--color-success); }
    .flag-high { background: var(--color-danger); }
    .flag-low { background: var(--color-info); }
    .flag-critical { background: #7f1d1d; }
    
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
        opacity: 0.9;
    }
    
    .action-btn.success {
        background: var(--color-success);
    }
    
    .badge {
        display: inline-flex;
        align-items: center;
        gap: var(--space-xs);
        padding: 0.35em 0.65em;
        font-size: 0.75em;
        font-weight: 700;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: 0.375rem;
    }
    
    .bg-primary { background-color: var(--color-primary); }
    .bg-success { background-color: var(--color-success); }
    .bg-warning { background-color: var(--color-warning); }
    .bg-danger { background-color: var(--color-danger); }
    .bg-info { background-color: var(--color-info); }
    
    .text-muted { color: var(--color-text-secondary); }
    .small { font-size: var(--font-size-sm); }
    .mb-0 { margin-bottom: 0; }
    .mb-1 { margin-bottom: var(--space-xs); }
    .mb-2 { margin-bottom: var(--space-sm); }
    .mb-3 { margin-bottom: var(--space-md); }
    .mb-4 { margin-bottom: var(--space-lg); }
    .mb-5 { margin-bottom: var(--space-xl); }
    
    .sticky-bottom {
        position: sticky;
        bottom: 0;
        background: var(--color-card);
        border-top: 1px solid var(--color-border);
        padding: var(--space-lg);
        margin-top: var(--space-xl);
        z-index: 100;
        box-shadow: 0 -4px 6px -1px rgba(0,0,0,0.1);
    }
    
    .text-end { text-align: right; }
    .text-center { text-align: center; }
    
    .d-flex { display: flex; }
    .gap-2 { gap: var(--space-sm); }
    .justify-content-end { justify-content: flex-end; }
    
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
    
    /* ==========================================================================
       RESPONSIVE DESIGN
       ========================================================================== */
    @media (max-width: 991px) {
        .header-content {
            padding: var(--space-md);
            flex-wrap: wrap;
        }
        
        .header-controls {
            order: 3;
            width: 100%;
            justify-content: space-between;
            margin-top: var(--space-md);
        }
        
        .main-content {
            padding: var(--space-md);
        }
        
        .patient-header-card {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .test-title-bar {
            flex-direction: column;
            align-items: flex-start;
            gap: var(--space-md);
        }
        
        .parameter-table {
            font-size: var(--font-size-sm);
        }
        
        .parameter-table th,
        .parameter-table td {
            padding: var(--space-sm);
        }
    }
    
    @media (max-width: 767px) {
        .header-controls {
            flex-wrap: wrap;
        }
        
        .back-btn span {
            display: none;
        }
        
        .back-btn {
            padding: var(--space-sm);
        }
        
        .theme-btn {
            width: 40px;
            height: 40px;
        }
    }
    
    @media (max-width: 480px) {
        .main-content {
            padding: var(--space-sm);
        }
        
        .patient-header-card,
        .test-processing-section {
            padding: var(--space-md);
        }
        
        .result-input {
            width: 100px;
        }
        
        .sticky-bottom {
            padding: var(--space-md);
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
                    
                    <!-- Botón para regresar a laboratory -->
                    <a href="index.php" class="back-btn">
                        <i class="bi bi-arrow-left"></i>
                        <span>Volver a Laboratorio</span>
                    </a>
                </div>
            </div>
        </header>
        
        <!-- Contenido Principal -->
        <main class="main-content">
            <!-- Tarjeta de información del paciente -->
            <div class="patient-header-card animate-in">
                <div>
                    <h2 class="mb-2"><?php echo htmlspecialchars($orden['nombre'] . ' ' . $orden['apellido']); ?></h2>
                    <p class="text-muted mb-0">
                        <?php echo $edad; ?> años - <?php echo $genero; ?> | 
                        Orden: <strong><?php echo $orden['numero_orden']; ?></strong> | 
                        Fecha: <?php echo date('d/m/Y H:i', strtotime($orden['fecha_orden'])); ?>
                    </p>
                </div>
                <div class="text-end">
                    <div class="badge <?php echo $orden['prioridad'] === 'Rutina' ? 'bg-info' : 'bg-danger'; ?> mb-2">
                        Prioridad: <?php echo $orden['prioridad']; ?>
                    </div>
                    <p class="small text-muted mb-0">
                        <i class="bi bi-person-badge me-1"></i>
                        Dr. <?php echo htmlspecialchars($orden['doctor_nombre'] . ' ' . $orden['doctor_apellido']); ?>
                    </p>
                </div>
            </div>
            
            <!-- Formulario para ingresar resultados -->
            <form id="resultsForm" action="api/save_results.php" method="POST">
                <input type="hidden" name="id_orden" value="<?php echo $id_orden; ?>">
                
                <?php foreach ($pruebas as $prueba): ?>
                    <div class="test-processing-section animate-in delay-1" data-id-orden-prueba="<?php echo $prueba['id_orden_prueba']; ?>">
                        <div class="test-title-bar">
                            <h4 class="mb-0">
                                <i class="bi bi-virus text-primary me-2"></i>
                                <?php echo htmlspecialchars($prueba['nombre_prueba']); ?>
                            </h4>
                            <div>
                                <?php if ($prueba['estado'] === 'Pendiente'): ?>
                                    <button type="button" class="action-btn" onclick="receiveSample(<?php echo $prueba['id_orden_prueba']; ?>)">
                                        <i class="bi bi-droplet-fill"></i> Recibir Muestra
                                    </button>
                                <?php else: ?>
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle"></i> Muestra Recibida
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($prueba['estado'] !== 'Pendiente'): ?>
                            <div class="table-responsive">
                                <table class="parameter-table">
                                    <thead>
                                        <tr>
                                            <th>Parámetro</th>
                                            <th>Resultado</th>
                                            <th>Unidad</th>
                                            <th>Referencia</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $stmt_params = $conn->prepare("
                                            SELECT pp.*, rl.valor_resultado, rl.fuera_rango
                                            FROM parametros_pruebas pp
                                            LEFT JOIN resultados_laboratorio rl ON pp.id_parametro = rl.id_parametro AND rl.id_orden_prueba = ?
                                            WHERE pp.id_prueba = ?
                                            ORDER BY pp.orden_visualizacion
                                        ");
                                        $stmt_params->execute([$prueba['id_orden_prueba'], $prueba['id_prueba']]);
                                        $p_list = $stmt_params->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        foreach ($p_list as $p):
                                            // Determine reference range for this patient
                                            $min = 0; $max = 0;
                                            if ($edad <= 12) {
                                                $min = $p['valor_ref_pediatrico_min']; $max = $p['valor_ref_pediatrico_max'];
                                            } elseif ($genero === 'Masculino') {
                                                $min = $p['valor_ref_hombre_min']; $max = $p['valor_ref_hombre_max'];
                                            } else {
                                                $min = $p['valor_ref_mujer_min']; $max = $p['valor_ref_mujer_max'];
                                            }
                                            $ref_text = ($min !== null && $max !== null) ? "$min - $max" : "N/A";
                                        ?>
                                            <tr>
                                                <td width="30%"><?php echo htmlspecialchars($p['nombre_parametro']); ?></td>
                                                <td width="20%">
                                                    <input type="text" 
                                                           name="results[<?php echo $prueba['id_orden_prueba']; ?>][<?php echo $p['id_parametro']; ?>]" 
                                                           class="result-input" 
                                                           value="<?php echo htmlspecialchars($p['valor_resultado'] ?? ''); ?>"
                                                           data-min="<?php echo $min; ?>" 
                                                           data-max="<?php echo $max; ?>"
                                                           onchange="validateRange(this)">
                                                </td>
                                                <td width="15%"><small class="text-muted"><?php echo htmlspecialchars($p['unidad_medida']); ?></small></td>
                                                <td width="20%"><small><?php echo $ref_text; ?></small></td>
                                                <td width="15%" class="flag-container">
                                                    <!-- Flag logic via JS -->
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <i class="bi bi-droplet"></i>
                                </div>
                                <h4 class="text-muted mb-2">Esperando muestra</h4>
                                <p class="text-muted mb-3">Debe marcar la muestra como recibida para ingresar resultados</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <div class="sticky-bottom">
                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" class="action-btn">
                            <i class="bi bi-save"></i> Guardar Resultados
                        </button>
                        <button type="button" class="action-btn success" onclick="validateAndFinalize()">
                            <i class="bi bi-check-all"></i> Validar y Finalizar Orden
                        </button>
                    </div>
                </div>
            </form>
        </main>
    </div>
    
    <!-- JavaScript Optimizado -->
    <script>
    // Sistema de tema
    document.addEventListener('DOMContentLoaded', function() {
        'use strict';
        
        const CONFIG = {
            themeKey: 'dashboard-theme'
        };
        
        // ==========================================================================
        // REFERENCIAS A ELEMENTOS DOM
        // ==========================================================================
        const DOM = {
            html: document.documentElement,
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
                    }, 300);
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
        // FUNCIONES ESPECÍFICAS DE LA PÁGINA
        // ==========================================================================
        class LaboratoryFunctions {
            constructor() {
                this.setupRangeValidation();
                this.setupAnimations();
            }
            
            setupRangeValidation() {
                // Inicializar flags si hay valores
                document.querySelectorAll('.result-input').forEach(input => {
                    if (input.value) this.validateRange(input);
                });
            }
            
            validateRange(input) {
                const val = parseFloat(input.value);
                const min = parseFloat(input.dataset.min);
                const max = parseFloat(input.dataset.max);
                const container = input.closest('tr').querySelector('.flag-container');
                
                if (isNaN(val) || isNaN(min) || isNaN(max)) {
                    container.innerHTML = '';
                    return;
                }
                
                let flag = '';
                if (val < min) {
                    flag = '<span class="flag-indicator flag-low" title="Bajo">L</span>';
                } else if (val > max) {
                    flag = '<span class="flag-indicator flag-high" title="Alto">H</span>';
                } else {
                    flag = '<span class="flag-indicator flag-normal" title="Normal">N</span>';
                }
                
                container.innerHTML = flag;
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
                document.querySelectorAll('.patient-header-card, .test-processing-section').forEach(el => {
                    observer.observe(el);
                });
            }
        }
        
        // ==========================================================================
        // INICIALIZACIÓN
        // ==========================================================================
        const themeManager = new ThemeManager();
        const labFunctions = new LaboratoryFunctions();
        
        // Exponer funciones globalmente
        window.laboratory = {
            theme: themeManager,
            functions: labFunctions
        };
        
        // Log de inicialización
        console.log('Laboratorio - Procesar Orden inicializado');
    });
    
    // Funciones globales para botones
    function receiveSample(id_orden_prueba) {
        Swal.fire({
            title: '¿Confirmar Recepción?',
            text: 'Se marcará la muestra como recibida para esta prueba',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Sí, recibir',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: 'var(--color-primary)',
            background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
            color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#e2e8f0' : '#1a1a1a'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `api/sample_reception.php?id=${id_orden_prueba}&id_orden=<?php echo $id_orden; ?>`;
            }
        });
    }

    function validateAndFinalize() {
        Swal.fire({
            title: '¿Validar y Finalizar?',
            text: 'Una vez validada, la orden no podrá ser modificada y los resultados estarán disponibles para el doctor.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, validar todo',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: 'var(--color-success)',
            background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
            color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#e2e8f0' : '#1a1a1a'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `api/validate_order.php?id=<?php echo $id_orden; ?>`;
            }
        });
    }

    function validateRange(input) {
        window.laboratory.functions.validateRange(input);
    }
    
    // Manejo de envío del formulario
    document.getElementById('resultsForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Mostrar indicador de carga
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Guardando...';
        submitBtn.disabled = true;
        
        // Simular envío asíncrono
        setTimeout(() => {
            // En un sistema real, aquí se haría una petición fetch
            this.submit();
        }, 1000);
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
    `;
    document.head.appendChild(style);
    </script>
</body>
</html>