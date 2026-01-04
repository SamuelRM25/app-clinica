<?php
// print_receipt.php - Recibo de Cobro - Centro Médico Herrera Saenz
// Diseño Responsive, Barra Lateral Moderna, Efecto Mármol
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
    die("ID de cobro inválido");
}

$id_cobro = $_GET['id'];

try {
    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();
    
    // Obtener datos del cobro con información del paciente
    $stmt = $conn->prepare("
        SELECT c.*, CONCAT(p.nombre, ' ', p.apellido) as nombre_paciente, 
               p.id_paciente, p.fecha_nacimiento, p.genero, p.telefono, p.direccion
        FROM cobros c
        JOIN pacientes p ON c.paciente_cobro = p.id_paciente
        WHERE c.in_cobro = ?
    ");
    $stmt->execute([$id_cobro]);
    $cobro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cobro) {
        die("Cobro no encontrado");
    }
    
    // Obtener información del usuario para el dashboard
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['tipoUsuario'];
    $user_name = $_SESSION['nombre'];
    $user_specialty = $_SESSION['especialidad'] ?? 'Profesional Médico';
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Formatear fecha
$fecha = new DateTime($cobro['fecha_consulta']);
$fecha_formateada = $fecha->format('d/m/Y');

// Calcular edad
if ($cobro['fecha_nacimiento']) {
    $fecha_nac = new DateTime($cobro['fecha_nacimiento']);
    $hoy = new DateTime();
    $edad = $hoy->diff($fecha_nac)->y;
} else {
    $edad = 'N/A';
}

// Procesar envío del formulario para programar cita
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'schedule' && 
        isset($_POST['fecha_cita']) && isset($_POST['hora_cita'])) {
        try {
            // Necesitamos un ID de doctor. Usamos el primer doctor/admin disponible
            $stmt_doc = $conn->query("SELECT id_usuario FROM usuarios WHERE tipoUsuario IN ('admin', 'doc') LIMIT 1");
            $default_doc = $stmt_doc->fetch(PDO::FETCH_ASSOC);
            $id_doctor = $default_doc['id_usuario'] ?? 1;

            $stmt = $conn->prepare("
                INSERT INTO citas (id_paciente, id_doctor, fecha_cita, hora_cita, estado, motivo) 
                VALUES (?, ?, ?, ?, 'Pendiente', 'Seguimiento de consulta')
            ");
            $stmt->execute([
                $cobro['id_paciente'],
                $id_doctor,
                $_POST['fecha_cita'],
                $_POST['hora_cita']
            ]);
            
            $mensaje = '<div class="alert-card mb-4 animate-in" style="border-left: 4px solid var(--color-success);">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 1.25rem;"></i>
                    <span>Nueva cita agendada correctamente.</span>
                </div>
            </div>';
        } catch (Exception $e) {
            $mensaje = '<div class="alert-card mb-4 animate-in" style="border-left: 4px solid var(--color-danger);">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 1.25rem;"></i>
                    <span>Error al agendar la cita: ' . htmlspecialchars($e->getMessage()) . '</span>
                </div>
            </div>';
        }
    }
}

// Título de la página
$page_title = "Recibo de Cobro #" . str_pad($id_cobro, 5, '0', STR_PAD_LEFT) . " - Centro Médico Herrera Saenz";
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Recibo de Cobro - Centro Médico Herrera Saenz - Comprobante de pago médico">
    <title><?php echo $page_title; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">
    
    <!-- Google Fonts - Inter (moderno y legible) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    
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
        --font-family-heading: 'Playfair Display', serif;
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
        
        /* Ancho recibo */
        --receipt-width: 210mm;
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
       BARRA LATERAL MODERNA
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
    
    /* Estado colapsado */
    .sidebar.collapsed {
        width: var(--sidebar-collapsed-width);
        transform: translateX(0);
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
    
    /* Overlay para móvil */
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
    
    /* Header sidebar */
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
    
    /* Navegación */
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
    
    /* Footer sidebar */
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
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    
    .sidebar.collapsed ~ .dashboard-container {
        margin-left: var(--sidebar-collapsed-width);
        width: calc(100% - var(--sidebar-collapsed-width));
    }
    
    /* Botón toggle sidebar (escritorio) */
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
       RECIBO DE COBRO
       ========================================================================== */
    .receipt-container {
        width: var(--receipt-width);
        min-height: 297mm;
        background-color: white;
        padding: 40px;
        box-shadow: var(--shadow-xl);
        border-radius: var(--radius-lg);
        margin-bottom: var(--space-xl);
        position: relative;
        overflow: hidden;
        border: 1px solid var(--color-border);
        color: #1a1a1a; /* Fijo para impresión */
    }
    
    /* Marca de agua */
    .watermark {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-45deg);
        font-size: 120px;
        color: rgba(0, 0, 0, 0.03);
        pointer-events: none;
        z-index: 0;
        font-weight: 900;
        text-transform: uppercase;
        white-space: nowrap;
        font-family: var(--font-family-heading);
        opacity: 0.5;
    }
    
    /* Encabezado del recibo */
    .receipt-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 2px solid var(--color-primary);
        padding-bottom: 20px;
        margin-bottom: 30px;
        position: relative;
        z-index: 1;
    }
    
    .clinic-logo {
        height: 60px;
        width: auto;
    }
    
    .clinic-name {
        font-family: var(--font-family-heading);
        color: var(--color-primary);
        font-size: 24px;
        font-weight: 700;
        margin: 0;
    }
    
    .clinic-info {
        text-align: right;
        font-size: 11px;
        line-height: 1.5;
        color: var(--color-text-secondary);
        font-weight: 500;
    }
    
    /* Información del paciente */
    .patient-info-section {
        background-color: rgba(var(--color-primary-rgb), 0.05);
        border: 1px solid rgba(var(--color-primary-rgb), 0.2);
        border-radius: var(--radius-md);
        padding: 20px;
        margin-bottom: 30px;
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
        position: relative;
        z-index: 1;
    }
    
    .info-item {
        display: flex;
        flex-direction: column;
    }
    
    .info-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--color-text-secondary);
        margin-bottom: 4px;
        font-weight: 700;
    }
    
    .info-value {
        font-size: 13px;
        font-weight: 700;
        color: var(--color-text);
        line-height: 1.2;
    }
    
    /* Contenido principal del recibo */
    .receipt-content {
        flex-grow: 1;
        position: relative;
        z-index: 1;
    }
    
    .receipt-title {
        color: var(--color-primary);
        font-size: 18px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 20px;
        border-left: 4px solid var(--color-primary);
        padding-left: 15px;
    }
    
    /* Tabla de detalles */
    .receipt-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 30px;
    }
    
    .receipt-table th {
        text-align: left;
        font-size: 12px;
        color: var(--color-text-secondary);
        padding-bottom: 10px;
        border-bottom: 1px solid var(--color-border);
    }
    
    .receipt-table td {
        padding: 15px 0;
        font-size: 14px;
        border-bottom: 1px solid var(--color-border);
        color: var(--color-text);
        font-weight: 500;
    }
    
    .receipt-table td:last-child {
        text-align: right;
        font-weight: 600;
    }
    
    /* Sección total */
    .total-section {
        display: flex;
        justify-content: flex-end;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 2px solid var(--color-border);
    }
    
    .total-box {
        background: linear-gradient(135deg, var(--color-primary), var(--color-info));
        color: white;
        padding: 20px 30px;
        border-radius: var(--radius-md);
        text-align: right;
        box-shadow: var(--shadow-md);
    }
    
    .total-label {
        font-size: 11px;
        text-transform: uppercase;
        opacity: 0.9;
        margin-bottom: 5px;
    }
    
    .total-amount {
        font-size: 28px;
        font-weight: 800;
    }
    
    /* Pie de página del recibo */
    .receipt-footer {
        margin-top: auto;
        border-top: 1px solid var(--color-border);
        padding-top: 30px;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        position: relative;
        z-index: 1;
    }
    
    .legal-note {
        font-size: 9px;
        color: var(--color-text-secondary);
        max-width: 300px;
        line-height: 1.4;
    }
    
    .thank-you {
        text-align: right;
        font-family: var(--font-family-heading);
        font-style: italic;
        color: var(--color-primary);
    }
    
    /* ==========================================================================
       PANEL DE ACCIONES
       ========================================================================== */
    .action-panel {
        width: var(--receipt-width);
        background: var(--color-card);
        padding: 25px;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        border: 1px solid var(--color-border);
        margin-bottom: var(--space-xl);
    }
    
    .action-title {
        font-size: var(--font-size-xl);
        font-weight: 600;
        color: var(--color-text);
        margin-bottom: var(--space-lg);
        display: flex;
        align-items: center;
        gap: var(--space-sm);
    }
    
    /* Pestañas de acción */
    .action-tabs {
        display: flex;
        gap: var(--space-sm);
        margin-bottom: var(--space-lg);
        border-bottom: 1px solid var(--color-border);
        padding-bottom: var(--space-sm);
    }
    
    .tab-button {
        padding: var(--space-sm) var(--space-md);
        background: transparent;
        border: none;
        color: var(--color-text-secondary);
        font-weight: 500;
        font-size: var(--font-size-sm);
        cursor: pointer;
        border-radius: var(--radius-md);
        transition: all var(--transition-base);
        position: relative;
    }
    
    .tab-button:hover {
        color: var(--color-text);
        background: var(--color-surface);
    }
    
    .tab-button.active {
        color: var(--color-primary);
        background: rgba(var(--color-primary-rgb), 0.1);
    }
    
    .tab-button.active::after {
        content: '';
        position: absolute;
        bottom: calc(-1 * var(--space-sm));
        left: 0;
        right: 0;
        height: 2px;
        background: var(--color-primary);
    }
    
    /* Contenido de pestañas */
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
        animation: fadeIn 0.5s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    /* Botones de acción */
    .action-buttons {
        display: flex;
        gap: var(--space-md);
        justify-content: center;
        margin-top: var(--space-lg);
    }
    
    .action-button {
        padding: var(--space-sm) var(--space-lg);
        border-radius: var(--radius-md);
        font-weight: 600;
        font-size: var(--font-size-base);
        cursor: pointer;
        transition: all var(--transition-base);
        border: none;
        display: inline-flex;
        align-items: center;
        gap: var(--space-sm);
        text-decoration: none;
    }
    
    .action-button.primary {
        background: var(--color-primary);
        color: white;
    }
    
    .action-button.primary:hover {
        background: var(--color-primary);
        opacity: 0.9;
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    
    .action-button.secondary {
        background: var(--color-surface);
        color: var(--color-text);
        border: 1px solid var(--color-border);
    }
    
    .action-button.secondary:hover {
        background: var(--color-surface);
        border-color: var(--color-primary);
    }
    
    /* Formulario para agendar cita */
    .appointment-form {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: var(--space-md);
        margin-bottom: var(--space-lg);
    }
    
    .form-group {
        display: flex;
        flex-direction: column;
        gap: var(--space-xs);
    }
    
    .form-label {
        font-weight: 500;
        color: var(--color-text);
        font-size: var(--font-size-sm);
    }
    
    .form-control {
        padding: var(--space-sm) var(--space-md);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        font-size: var(--font-size-base);
        background: var(--color-surface);
        color: var(--color-text);
        transition: all var(--transition-base);
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.25);
    }
    
    /* ==========================================================================
       ALERTAS Y NOTIFICACIONES
       ========================================================================== */
    .alert-card {
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: var(--space-md);
        margin-bottom: var(--space-md);
        transition: all var(--transition-base);
    }
    
    /* ==========================================================================
       BOTÓN DE REGRESO
       ========================================================================== */
    .back-button {
        align-self: flex-start;
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: var(--space-sm) var(--space-md);
        color: var(--color-text);
        text-decoration: none;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: var(--space-sm);
        transition: all var(--transition-base);
        margin-bottom: var(--space-lg);
    }
    
    .back-button:hover {
        background: var(--color-primary);
        color: white;
        border-color: var(--color-primary);
        transform: translateY(-2px);
    }
    
    /* ==========================================================================
       RESPONSIVE DESIGN
       ========================================================================== */
    
    /* Tablets y pantallas medianas */
    @media (max-width: 991px) {
        :root {
            --sidebar-width: 280px;
        }
        
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
        
        .receipt-container,
        .action-panel {
            width: 100%;
            max-width: 100%;
        }
        
        .patient-info-section {
            grid-template-columns: 1fr;
        }
        
        .appointment-form {
            grid-template-columns: 1fr;
        }
        
        .action-buttons {
            flex-direction: column;
        }
        
        .action-button {
            width: 100%;
            justify-content: center;
        }
        
        .watermark {
            font-size: 80px;
        }
    }
    
    /* Móviles */
    @media (max-width: 767px) {
        :root {
            --sidebar-width: 100%;
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
        
        .receipt-container {
            padding: var(--space-md);
        }
        
        .clinic-header {
            flex-direction: column;
            gap: var(--space-md);
            align-items: flex-start;
        }
        
        .clinic-info {
            text-align: left;
        }
        
        .action-tabs {
            flex-direction: column;
        }
        
        .tab-button {
            width: 100%;
            text-align: left;
        }
    }
    
    /* Móviles pequeños */
    @media (max-width: 480px) {
        .main-content {
            padding: var(--space-sm);
        }
        
        .receipt-container {
            padding: var(--space-sm);
        }
        
        .action-panel {
            padding: var(--space-md);
        }
        
        .total-amount {
            font-size: var(--font-size-2xl);
        }
        
        .watermark {
            font-size: 60px;
        }
    }
    
    /* ==========================================================================
       ESTILOS DE IMPRESIÓN
       ========================================================================== */
    @media print {
        .sidebar,
        .dashboard-header,
        .sidebar-toggle,
        .theme-btn,
        .logout-btn,
        .action-button,
        .mobile-toggle,
        .back-button,
        .action-panel,
        .watermark,
        .marble-effect {
            display: none !important;
        }
        
        body {
            background: white !important;
            color: black !important;
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
            border-radius: 0 !important;
            margin: 0 !important;
            padding: 20mm !important;
            width: 210mm !important;
            min-height: 297mm !important;
            page-break-after: always;
        }
        
        /* Asegurar que el texto sea negro para impresión */
        .receipt-container,
        .receipt-container * {
            color: #000000 !important;
            border-color: #cccccc !important;
        }
        
        .total-box {
            background: #f0f0f0 !important;
            color: #000000 !important;
        }
        
        .patient-info-section {
            background: #f9f9f9 !important;
            border-color: #dddddd !important;
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
    .d-inline-flex { display: inline-flex; }
    
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
            <!-- Botón de regreso -->
            <a href="index.php" class="back-button no-print animate-in">
                <i class="bi bi-arrow-left"></i>
                Volver a Cobros
            </a>
            
            <!-- Notificación -->
            <?php if (!empty($mensaje)): ?>
                <?php echo $mensaje; ?>
            <?php endif; ?>
            
            <!-- Recibo de cobro -->
            <div class="receipt-container animate-in delay-1">
                <!-- Marca de agua -->
                <div class="watermark">HERRERA SAENZ</div>
                
                <!-- Encabezado de la clínica -->
                <header class="receipt-header">
                    <div class="logo-section">
                        <img src="../../assets/img/herrerasaenz.png" alt="Centro Médico Herrera Saenz" class="clinic-logo">
                    </div>
                    <div class="clinic-info">
                        7ma Av 7-25 Zona 1, Atrás del parqueo Hospital Antiguo. Huehuetenango<br>
                        Tel: (+502) 4195-8112<br>
                    </div>
                </header>
                
                <!-- Información del paciente -->
                <section class="patient-info-section">
                    <div class="info-item">
                        <span class="info-label">Paciente</span>
                        <span class="info-value"><?php echo htmlspecialchars($cobro['nombre_paciente']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Fecha</span>
                        <span class="info-value"><?php echo $fecha_formateada; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Edad / Género</span>
                        <span class="info-value"><?php echo $edad; ?> años / <?php echo htmlspecialchars($cobro['genero'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">ID de Cobro</span>
                        <span class="info-value">#REC-<?php echo str_pad($id_cobro, 5, '0', STR_PAD_LEFT); ?></span>
                    </div>
                </section>
                
                <!-- Contenido principal -->
                <main class="receipt-content">
                    <h2 class="receipt-title">Detalle de Recaudación</h2>
                    <table class="receipt-table">
                        <thead>
                            <tr>
                                <th style="width: 70%;">Descripción</th>
                                <th style="width: 30%; text-align: right;">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Consulta Médica General</td>
                                <td>Q<?php echo number_format($cobro['cantidad_consulta'], 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <div class="total-section">
                        <div class="total-box">
                            <div class="total-label">Total a Pagar</div>
                            <div class="total-amount">Q<?php echo number_format($cobro['cantidad_consulta'], 2); ?></div>
                        </div>
                    </div>
                </main>
                
                <!-- Pie de página -->
                <footer class="receipt-footer">
                    <div class="legal-note">
                        <strong>Información Importante:</strong><br>
                        Este recibo es un comprobante de pago por servicios médicos prestados. 
                        Para cualquier aclaración, favor de presentar este documento original.
                        Documento generado por Centro Médico Herrera Saenz Management System.
                    </div>
                    <div class="thank-you">
                        <h4 style="margin: 0; font-size: 16px;">¡Gracias por su preferencia!</h4>
                        <p style="margin: 5px 0 0; font-size: 13px;">Recupérese pronto.</p>
                    </div>
                </footer>
            </div>
        </main>
    </div>
    
    <!-- JavaScript Optimizado -->
    <script>
    // Recibo de Cobro Reingenierizado - Centro Médico Herrera Saenz
    
    (function() {
        'use strict';
        
        // ==========================================================================
        // CONFIGURACIÓN Y CONSTANTES
        // ==========================================================================
        const CONFIG = {
            themeKey: 'dashboard-theme',
            sidebarKey: 'sidebar-collapsed',
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
            tabButtons: document.querySelectorAll('.tab-button'),
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
        // MANEJO DE PESTAÑAS
        // ==========================================================================
        class TabManager {
            constructor() {
                this.setupEventListeners();
            }
            
            setupEventListeners() {
                if (DOM.tabButtons) {
                    DOM.tabButtons.forEach(button => {
                        button.addEventListener('click', () => this.switchTab(button));
                    });
                }
            }
            
            switchTab(activeButton) {
                const tabId = activeButton.getAttribute('data-tab');
                
                // Remover clase active de todos los botones y contenidos
                DOM.tabButtons.forEach(btn => btn.classList.remove('active'));
                DOM.tabContents.forEach(content => content.classList.remove('active'));
                
                // Agregar clase active al botón clickeado
                activeButton.classList.add('active');
                
                // Mostrar el contenido correspondiente
                document.getElementById(`${tabId}-tab`).classList.add('active');
            }
        }
        
        // ==========================================================================
        // FUNCIONALIDADES DE IMPRESIÓN
        // ==========================================================================
        class PrintManager {
            constructor() {
                this.setupPrintButton();
            }
            
            setupPrintButton() {
                // Asegurar que el botón de impresión funcione
                const printButtons = document.querySelectorAll('button[onclick*="print"]');
                printButtons.forEach(button => {
                    button.addEventListener('click', () => this.printReceipt());
                });
            }
            
            printReceipt() {
                // Mostrar mensaje de preparación
                Swal.fire({
                    title: 'Preparando impresión',
                    text: 'El recibo se está preparando para imprimir...',
                    icon: 'info',
                    showConfirmButton: false,
                    timer: 1500,
                    background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                    color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#e2e8f0' : '#1a1a1a'
                }).then(() => {
                    window.print();
                });
            }
        }
        
        // ==========================================================================
        // VALIDACIÓN DE FORMULARIO
        // ==========================================================================
        class FormValidator {
            constructor() {
                this.setupFormValidation();
            }
            
            setupFormValidation() {
                const appointmentForm = document.querySelector('.appointment-form');
                if (appointmentForm) {
                    appointmentForm.addEventListener('submit', (e) => this.validateForm(e));
                }
            }
            
            validateForm(e) {
                const fechaInput = document.getElementById('fecha_cita');
                const horaInput = document.getElementById('hora_cita');
                
                // Validar que la fecha sea hoy o en el futuro
                const today = new Date().toISOString().split('T')[0];
                if (fechaInput.value < today) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Fecha inválida',
                        text: 'La fecha de la cita debe ser hoy o en el futuro.',
                        icon: 'warning',
                        confirmButtonColor: 'var(--color-primary)',
                        background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                        color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#e2e8f0' : '#1a1a1a'
                    });
                    fechaInput.focus();
                    return false;
                }
                
                // Validar que la hora esté en horario laboral (ejemplo: 8:00 - 18:00)
                const hora = parseInt(horaInput.value.split(':')[0]);
                if (hora < 8 || hora > 18) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Hora inválida',
                        text: 'El horario de atención es de 8:00 a 18:00 horas.',
                        icon: 'warning',
                        confirmButtonColor: 'var(--color-primary)',
                        background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                        color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#e2e8f0' : '#1a1a1a'
                    });
                    horaInput.focus();
                    return false;
                }
                
                // Si todo está bien, mostrar confirmación
                Swal.fire({
                    title: 'Confirmar cita',
                    html: `¿Desea agendar la cita para el <strong>${fechaInput.value}</strong> a las <strong>${horaInput.value}</strong>?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, agendar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: 'var(--color-primary)',
                    background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                    color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#e2e8f0' : '#1a1a1a'
                }).then((result) => {
                    if (!result.isConfirmed) {
                        e.preventDefault();
                    }
                });
            }
        }
        
        // ==========================================================================
        // ANIMACIONES Y EFECTOS VISUALES
        // ==========================================================================
        class AnimationManager {
            constructor() {
                this.setupAnimations();
                this.setupReceiptEffects();
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
                document.querySelectorAll('.receipt-container, .action-panel').forEach(el => {
                    observer.observe(el);
                });
            }
            
            setupReceiptEffects() {
                // Efecto de elevación al pasar el mouse sobre el recibo
                const receipt = document.querySelector('.receipt-container');
                if (receipt) {
                    receipt.addEventListener('mouseenter', () => {
                        receipt.style.transform = 'translateY(-10px)';
                        receipt.style.boxShadow = 'var(--shadow-xl)';
                    });
                    
                    receipt.addEventListener('mouseleave', () => {
                        receipt.style.transform = 'translateY(0)';
                        receipt.style.boxShadow = 'var(--shadow-lg)';
                    });
                }
            }
        }
        
        // ==========================================================================
        // INICIALIZACIÓN DE LA APLICACIÓN
        // ==========================================================================
        document.addEventListener('DOMContentLoaded', () => {
            // Inicializar componentes
            const themeManager = new ThemeManager();
            const sidebarManager = new SidebarManager();
            const tabManager = new TabManager();
            const printManager = new PrintManager();
            const formValidator = new FormValidator();
            const animationManager = new AnimationManager();
            
            // Exponer APIs necesarias globalmente
            window.receiptModule = {
                theme: themeManager,
                sidebar: sidebarManager,
                tabs: tabManager,
                print: printManager,
                forms: formValidator
            };
            
            // Configurar fecha mínima para el formulario de cita
            const fechaCitaInput = document.getElementById('fecha_cita');
            if (fechaCitaInput) {
                fechaCitaInput.min = new Date().toISOString().split('T')[0];
            }
            
            // Configurar hora por defecto (próxima hora disponible)
            const horaCitaInput = document.getElementById('hora_cita');
            if (horaCitaInput) {
                const now = new Date();
                const nextHour = now.getHours() + 1;
                horaCitaInput.value = `${nextHour.toString().padStart(2, '0')}:00`;
            }
            
            // Log de inicialización
            console.log('Recibo de Cobro CMS inicializado correctamente');
            console.log('ID de Cobro: <?php echo $id_cobro; ?>');
            console.log('Paciente: <?php echo htmlspecialchars($cobro['nombre_paciente']); ?>');
            console.log('Monto: Q<?php echo number_format($cobro['cantidad_consulta'], 2); ?>');
            console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
            console.log('Tema: ' + themeManager.theme);
        });
        
        // ==========================================================================
        // POLYFILLS PARA NAVEGADORES ANTIGUOS
        // ==========================================================================
        if (!NodeList.prototype.forEach) {
            NodeList.prototype.forEach = Array.prototype.forEach;
        }
        
        if (!Element.prototype.matches) {
            Element.prototype.matches = 
                Element.prototype.matchesSelector || 
                Element.prototype.mozMatchesSelector ||
                Element.prototype.msMatchesSelector || 
                Element.prototype.oMatchesSelector || 
                Element.prototype.webkitMatchesSelector ||
                function(s) {
                    const matches = (this.document || this.ownerDocument).querySelectorAll(s);
                    let i = matches.length;
                    while (--i >= 0 && matches.item(i) !== this) {}
                    return i > -1;
                };
        }
        
    })();
    
    // Función global para imprimir
    window.printReceipt = function() {
        window.print();
    };
    </script>
</body>
</html>