<?php
// export_jornada.php - Reporte de Jornada - Centro Médico Herrera Saenz
// Versión 4.0 - Integrado al Diseño del Dashboard Principal
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer zona horaria
date_default_timezone_set('America/Guatemala');
verify_session();

// Obtener información del usuario
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['tipoUsuario'];
$user_name = $_SESSION['nombre'];
$user_specialty = $_SESSION['especialidad'] ?? 'Profesional Médico';

// Solo administradores pueden generar este reporte
if ($user_type !== 'admin') {
    die("Acceso denegado.");
}

// Obtener parámetros de fecha y formato
$date = $_GET['date'] ?? date('Y-m-d');
$format = $_GET['format'] ?? 'html'; // html, csv, excel, word

// Calcular rango de jornada
// Jornada 1: 08:00 AM a 05:00 PM (17:00)
// Jornada 2: 05:00 PM (17:00) a 08:00 AM del día siguiente
$start_time = $date . ' 08:00:00';
$end_time = $date . ' 17:00:00';

try {
    $database = new Database();
    $conn = $database->getConnection();

    // ============ CÁLCULO DE MÉTRICAS ============

    // 1. Total de pacientes atendidos
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT historial_id) FROM citas WHERE fecha_cita BETWEEN ? AND ?");
    $stmt->execute([$start_time, $end_time]);
    $total_patients = $stmt->fetchColumn() ?: 0;

    // 2. Procedimientos menores
    $stmt = $conn->prepare("SELECT SUM(cobro) FROM procedimientos_menores WHERE fecha_procedimiento BETWEEN ? AND ?");
    $stmt->execute([$start_time, $end_time]);
    $total_procedures = $stmt->fetchColumn() ?: 0;

    // 3. Exámenes realizados
    $stmt = $conn->prepare("SELECT SUM(cobro) FROM examenes_realizados WHERE fecha_examen BETWEEN ? AND ?");
    $stmt->execute([$start_time, $end_time]);
    $total_exams = $stmt->fetchColumn() ?: 0;

    // 4. Compras de medicamentos
    $stmt = $conn->prepare("SELECT SUM(total_amount) FROM purchase_headers WHERE purchase_date BETWEEN ? AND ?");
    $stmt->execute([$date, date('Y-m-d', strtotime($date . ' +1 day'))]);
    $total_purchases = $stmt->fetchColumn() ?: 0;

    // 5. Ventas de medicamentos
    $stmt = $conn->prepare("SELECT SUM(total) FROM ventas WHERE fecha_venta BETWEEN ? AND ?");
    $stmt->execute([$start_time, $end_time]);
    $total_sales = $stmt->fetchColumn() ?: 0;

    // 6. Cobros de consultas
    $stmt = $conn->prepare("SELECT SUM(cantidad_consulta) FROM cobros WHERE fecha_consulta = ?");
    $stmt->execute([$date]);
    $total_billings = $stmt->fetchColumn() ?: 0;

    // 7. Ingresos totales
    $total_revenue = $total_sales + $total_procedures + $total_exams + $total_billings;

    // 8. Desempeño neto
    $net_performance = $total_revenue - $total_purchases;

    // ============ PREPARAR DATOS PARA EXPORTACIÓN ============

    // Exportación CSV
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="reporte_jornada_' . $date . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Concepto', 'Monto / Cantidad']);
        fputcsv($output, ['Fecha', $date]);
        fputcsv($output, ['Pacientes Atendidos', $total_patients]);
        fputcsv($output, ['Ventas Medicamentos', number_format($total_sales, 2)]);
        fputcsv($output, ['Cobros Realizados', number_format($total_billings, 2)]);
        fputcsv($output, ['Procedimientos Menores', number_format($total_procedures, 2)]);
        fputcsv($output, ['Exámenes Médicos', number_format($total_exams, 2)]);
        fputcsv($output, ['Total Compras', number_format($total_purchases, 2)]);
        fputcsv($output, ['Total Ingresos', number_format($total_revenue, 2)]);
        fputcsv($output, ['Desempeño Neto', number_format($net_performance, 2)]);
        fclose($output);
        exit;
    }

    // Exportación Excel o Word
    if ($format === 'excel' || $format === 'word') {
        $ext = ($format === 'excel' ? ".xls" : ".doc");
        header("Content-Type: application/vnd.ms-" . ($format === 'excel' ? "excel" : "word"));
        header("Content-Disposition: attachment; filename=\"reporte_jornada_$date$ext\"");
        echo "
        <table border='1'>
            <tr><th colspan='2'><h1>Reporte de Jornada</h1></th></tr>
            <tr><td><b>Fecha:</b></td><td>$date</td></tr>
            <tr><td><b>Pacientes Atendidos:</b></td><td>$total_patients</td></tr>
            <tr><td><b>Ventas Medicamentos:</b></td><td>Q".number_format($total_sales, 2)."</td></tr>
            <tr><td><b>Cobros Realizados:</b></td><td>Q".number_format($total_billings, 2)."</td></tr>
            <tr><td><b>Procedimientos Menores:</b></td><td>Q".number_format($total_procedures, 2)."</td></tr>
            <tr><td><b>Exámenes Médicos:</b></td><td>Q".number_format($total_exams, 2)."</td></tr>
            <tr><td><b>Total Ingresos:</b></td><td><b>Q".number_format($total_revenue, 2)."</b></td></tr>
            <tr><td><b>Total Compras:</b></td><td>Q".number_format($total_purchases, 2)."</td></tr>
            <tr><td><b>Desempeño Neto:</b></td><td><b>Q".number_format($net_performance, 2)."</b></td></tr>
        </table>";
        exit;
    }

    // ============ CONSULTAS ADICIONALES PARA EL DASHBOARD ============
    
    // Citas de hoy
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM citas WHERE fecha_cita = ?");
    $stmt->execute([date('Y-m-d')]);
    $today_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Total de citas en el sistema
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM citas");
    $stmt->execute();
    $total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Hospitalizaciones Activas
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM encamamientos WHERE estado = 'Activo'");
    $stmt->execute();
    $active_hospitalizations = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Compras pendientes
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventario WHERE estado = 'Pendiente'");
    $stmt->execute();
    $pending_purchases = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Preparar mensaje para WhatsApp
    $wa_text = "*REPORTE DE JORNADA*\n";
    $wa_text .= "*Fecha:* " . date('d/m/Y', strtotime($date)) . "\n";
    $wa_text .= "--------------------------\n";
    $wa_text .= "*Pacientes:* " . $total_patients . "\n";
    $wa_text .= "*Ventas Meds:* Q" . number_format($total_sales, 2) . "\n";
    $wa_text .= "*Cobros Inf:* Q" . number_format($total_billings, 2) . "\n";
    $wa_text .= "*Proc. Menores:* Q" . number_format($total_procedures, 2) . "\n";
    $wa_text .= "*Exámenes:* Q" . number_format($total_exams, 2) . "\n";
    $wa_text .= "--------------------------\n";
    $wa_text .= "*TOTAL INGRESOS:* Q" . number_format($total_revenue, 2) . "\n";
    $wa_text .= "*TOTAL COMPRAS:* Q" . number_format($total_purchases, 2) . "\n";
    $wa_url = "https://wa.me/50239029076?text=" . urlencode($wa_text);

    // Título de la página
    $page_title = "Reporte de Jornada - $date - Centro Médico Herrera Saenz";
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Reporte de Jornada - Centro Médico Herrera Saenz - Sistema de gestión médica">
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
        flex-direction: column; /* Apilar Header y Main verticalmente */
        min-height: 100vh;
        position: relative;
        margin-left: var(--sidebar-width); /* Mover todo el contenido a la derecha del sidebar */
        transition: margin-left var(--transition-base);
        width: calc(100% - var(--sidebar-width)); /* Asegurar que no se desborde */
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
        /* Margin-left movido al contenedor padre */
        transition: all var(--transition-base);
        min-height: 100vh;
        background-color: transparent;
        width: 100%;
    }
    
    .sidebar.collapsed ~ .dashboard-container {
        margin-left: var(--sidebar-collapsed-width);
        width: calc(100% - var(--sidebar-collapsed-width));
    }
    
    /* Botón toggle sidebar (escritorio) */
    .sidebar-toggle {
        position: fixed;
        /* Ajustado para estar dentro del container que tiene margen */
        left: -12px; /* Relativo al dashboard-container */
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
    
    /* Cuando el sidebar está colapsado, el dashboard-container reduce su margen,
       y el botón se mueve con él porque está dentro y es relative o fixed dentro?
       Espera, el botón tiene position: fixed. Fixed es relativo al VIEWPORT.
       Entonces `left: -12px` NO SIRVE si es relativo al viewport.
       Debe ser relativo al container O calculado desde la izquierda.
    */
    .sidebar-toggle {
        left: calc(var(--sidebar-width) - 12px);
    }

    .sidebar.collapsed + .dashboard-container .sidebar-toggle {
        left: calc(var(--sidebar-collapsed-width) - 12px);
    }
    
    .sidebar.collapsed .sidebar-toggle i {
        transform: rotate(180deg);
    }
    
    /* ==========================================================================
       COMPONENTES DE REPORTE
       ========================================================================== */
    
    /* Tarjeta de reporte */
    .report-card {
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: var(--space-lg);
        margin-bottom: var(--space-lg);
        transition: all var(--transition-base);
        position: relative;
        overflow: hidden;
    }
    
    .report-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-xl);
        border-color: var(--color-primary);
    }
    
    .report-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--color-primary), var(--color-info));
    }
    
    .report-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: var(--space-lg);
        padding-bottom: var(--space-md);
        border-bottom: 1px solid var(--color-border);
    }
    
    .report-title-section {
        flex: 1;
    }
    
    .report-title {
        font-size: var(--font-size-xl);
        font-weight: 700;
        color: var(--color-text);
        margin-bottom: var(--space-xs);
    }
    
    .report-subtitle {
        font-size: var(--font-size-sm);
        color: var(--color-text-secondary);
    }
    
    .report-actions {
        display: flex;
        gap: var(--space-sm);
        flex-wrap: wrap;
    }
    
    /* Botones de acción */
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
        white-space: nowrap;
    }
    
    .action-btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
        background: var(--color-primary);
        opacity: 0.9;
        color: white;
    }
    
    .action-btn.secondary {
        background: transparent;
        color: var(--color-text);
        border: 1px solid var(--color-border);
    }
    
    .action-btn.secondary:hover {
        background: var(--color-surface);
        color: var(--color-text);
    }
    
    .action-btn.success {
        background: var(--color-success);
    }
    
    .action-btn.success:hover {
        background: var(--color-success);
        opacity: 0.9;
    }
    
    /* Lista de métricas */
    .metrics-list {
        display: flex;
        flex-direction: column;
        gap: var(--space-md);
        margin-bottom: var(--space-lg);
    }
    
    .metric-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: var(--space-md);
        border-radius: var(--radius-md);
        background: var(--color-surface);
        transition: background-color var(--transition-base);
    }
    
    .metric-item:hover {
        background: var(--color-border);
    }
    
    .metric-label {
        font-weight: 500;
        color: var(--color-text);
        display: flex;
        align-items: center;
        gap: var(--space-sm);
    }
    
    .metric-value {
        font-weight: 700;
        color: var(--color-text);
        font-size: var(--font-size-lg);
    }
    
    /* Secciones destacadas */
    .highlight-section {
        border-radius: var(--radius-lg);
        padding: var(--space-lg);
        margin-bottom: var(--space-lg);
        color: white;
    }
    
    .highlight-section.income {
        background: linear-gradient(135deg, var(--color-success), #10b981);
    }
    
    .highlight-section.expense {
        background: linear-gradient(135deg, var(--color-danger), #dc2626);
    }
    
    .highlight-section.net {
        background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
    }
    
    .highlight-title {
        font-size: var(--font-size-sm);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        opacity: 0.9;
        margin-bottom: var(--space-xs);
    }
    
    .highlight-value {
        font-size: var(--font-size-3xl);
        font-weight: 700;
        line-height: 1;
    }
    
    /* Firmas */
    .signature-row {
        display: flex;
        justify-content: space-between;
        margin-top: var(--space-xl);
        padding-top: var(--space-lg);
        border-top: 1px solid var(--color-border);
    }
    
    .signature-item {
        text-align: center;
        flex: 1;
    }
    
    .signature-line {
        width: 200px;
        height: 1px;
        background: var(--color-border);
        margin: var(--space-lg) auto var(--space-sm);
    }
    
    /* Información de generación */
    .generation-info {
        text-align: center;
        margin-top: var(--space-lg);
        padding-top: var(--space-md);
        border-top: 1px solid var(--color-border);
        color: var(--color-text-secondary);
        font-size: var(--font-size-sm);
    }
    
    /* Badges */
    .amount-badge {
        background: var(--color-surface);
        padding: var(--space-xs) var(--space-sm);
        border-radius: var(--radius-md);
        font-size: var(--font-size-sm);
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: var(--space-xs);
    }
    
    .amount-badge.income {
        background: rgba(var(--color-success-rgb), 0.1);
        color: var(--color-success);
    }
    
    .amount-badge.expense {
        background: rgba(var(--color-danger-rgb), 0.1);
        color: var(--color-danger);
    }
    
    /* ==========================================================================
       RESPONSIVE DESIGN
       ========================================================================== */
    
    /* Pantallas grandes (TV, monitores 4K) */
    @media (min-width: 1600px) {
        :root {
            --sidebar-width: 320px;
            --sidebar-collapsed-width: 100px;
        }
        
        .main-content {
            max-width: 1800px;
            margin: 0 auto;
            padding: var(--space-xl);
        }
    }
    
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
        
        .report-header {
            flex-direction: column;
            gap: var(--space-md);
        }
        
        .report-actions {
            width: 100%;
            justify-content: center;
        }
        
        .signature-row {
            flex-direction: column;
            gap: var(--space-xl);
        }
        
        .signature-line {
            width: 150px;
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
        
        .report-card {
            padding: var(--space-md);
        }
        
        .metric-item {
            flex-direction: column;
            align-items: flex-start;
            gap: var(--space-xs);
        }
        
        .metric-value {
            font-size: var(--font-size-xl);
        }
    }
    
    /* Móviles pequeños */
    @media (max-width: 480px) {
        .main-content {
            padding: var(--space-sm);
        }
        
        .report-card {
            padding: var(--space-md);
        }
        
        .highlight-section {
            padding: var(--space-md);
        }
        
        .highlight-value {
            font-size: var(--font-size-2xl);
        }
        
        .action-btn {
            width: 100%;
            justify-content: center;
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
       PRINT STYLES
       ========================================================================== */
    @media print {
        .sidebar,
        .dashboard-header,
        .sidebar-toggle,
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
        
        .report-card {
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
    
    <!-- Overlay para sidebar móvil -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Barra Lateral Moderna -->
    <aside class="sidebar" id="sidebar">
        <!-- Header sidebar -->
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <img src="../../assets/img/Logo.png" alt="Logo CMS">
            </div>
            <h2>CMS Reportes</h2>
        </div>
        
        <!-- Navegación -->
        <nav class="sidebar-nav">
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="../dashboard/index.php" class="nav-link">
                        <i class="bi bi-speedometer2 nav-icon"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../appointments/index.php" class="nav-link">
                        <i class="bi bi-calendar-check nav-icon"></i>
                        <span class="nav-text">Citas</span>
                        <span class="badge bg-primary"><?php echo $total_appointments; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../patients/index.php" class="nav-link">
                        <i class="bi bi-people nav-icon"></i>
                        <span class="nav-text">Pacientes</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../hospitalization/index.php" class="nav-link">
                        <i class="bi bi-hospital nav-icon"></i>
                        <span class="nav-text">Hospitalización</span>
                        <span class="badge bg-info"><?php echo $active_hospitalizations; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../minor_procedures/index.php" class="nav-link">
                        <i class="bi bi-bandaid nav-icon"></i>
                        <span class="nav-text">Procedimientos</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../examinations/index.php" class="nav-link">
                        <i class="bi bi-file-earmark-medical nav-icon"></i>
                        <span class="nav-text">Exámenes</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../laboratory/index.php" class="nav-link">
                        <i class="bi bi-virus nav-icon"></i>
                        <span class="nav-text">Laboratorio</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../inventory/index.php" class="nav-link">
                        <i class="bi bi-box-seam nav-icon"></i>
                        <span class="nav-text">Inventario</span>
                        <?php if ($pending_purchases > 0): ?>
                        <span class="badge bg-warning"><?php echo $pending_purchases; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../purchases/index.php" class="nav-link">
                        <i class="bi bi-cart nav-icon"></i>
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
                    <a href="../billing/index.php" class="nav-link">
                        <i class="bi bi-cash-coin nav-icon"></i>
                        <span class="nav-text">Cobros</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../dispensary/index.php" class="nav-link">
                        <i class="bi bi-capsule nav-icon"></i>
                        <span class="nav-text">Dispensario</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="index.php" class="nav-link active">
                        <i class="bi bi-graph-up nav-icon"></i>
                        <span class="nav-text">Reportes</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../settings/index.php" class="nav-link">
                        <i class="bi bi-gear nav-icon"></i>
                        <span class="nav-text">Configuración</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <!-- Footer sidebar -->
        <div class="sidebar-footer">
            <div class="user-avatar">
                <?php echo strtoupper(substr($user_name, 0, 1)); ?>
            </div>
            <div class="user-details">
                <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                <span class="user-role"><?php echo htmlspecialchars($user_specialty); ?></span>
            </div>
        </div>
    </aside>
    
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
        
        <!-- Botón para colapsar/expandir sidebar (solo escritorio) -->
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Colapsar/expandir menú">
            <i class="bi bi-chevron-left" id="sidebarToggleIcon"></i>
        </button>
        
        <!-- Contenido Principal -->
        <main class="main-content">
            <!-- Tarjeta de reporte -->
            <div class="report-card animate-in">
                <!-- Encabezado del reporte -->
                <div class="report-header">
                    <div class="report-title-section">
                        <h1 class="report-title">Reporte Diario de Jornada</h1>
                        <p class="report-subtitle">
                            Período: <?php echo date('d/m/Y 08:00 AM', strtotime($start_time)); ?> - 
                            <?php echo date('d/m/Y 05:00 PM', strtotime($end_time)); ?>
                        </p>
                    </div>
                    <div class="report-actions">
                        <button onclick="window.print()" class="action-btn secondary">
                            <i class="bi bi-printer"></i>
                            <span>Imprimir</span>
                        </button>
                        <a href="<?php echo $wa_url; ?>" target="_blank" class="action-btn success">
                            <i class="bi bi-whatsapp"></i>
                            <span>WhatsApp</span>
                        </a>
                    </div>
                </div>
                
                <!-- Métricas principales -->
                <div class="metrics-list">
                    <div class="metric-item">
                        <span class="metric-label">
                            <i class="bi bi-people"></i>
                            Total Pacientes Atendidos
                        </span>
                        <span class="metric-value"><?php echo $total_patients; ?></span>
                    </div>
                    
                    <div class="metric-item">
                        <span class="metric-label">
                            <i class="bi bi-capsule"></i>
                            Ventas de Medicamentos
                        </span>
                        <span class="metric-value text-success">Q<?php echo number_format($total_sales, 2); ?></span>
                    </div>
                    
                    <div class="metric-item">
                        <span class="metric-label">
                            <i class="bi bi-cash-coin"></i>
                            Cobros Realizados
                        </span>
                        <span class="metric-value text-primary">Q<?php echo number_format($total_billings, 2); ?></span>
                    </div>
                    
                    <div class="metric-item">
                        <span class="metric-label">
                            <i class="bi bi-bandaid"></i>
                            Procedimientos Menores
                        </span>
                        <span class="metric-value text-info">Q<?php echo number_format($total_procedures, 2); ?></span>
                    </div>
                    
                    <div class="metric-item">
                        <span class="metric-label">
                            <i class="bi bi-clipboard2-pulse"></i>
                            Exámenes Médicos
                        </span>
                        <span class="metric-value text-info">Q<?php echo number_format($total_exams, 2); ?></span>
                    </div>
                </div>
                
                <!-- Secciones destacadas -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="highlight-section income">
                            <div class="highlight-title">Total Ingresos Brutos</div>
                            <div class="highlight-value">Q<?php echo number_format($total_revenue, 2); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="highlight-section expense">
                            <div class="highlight-title">Total Compras (Egresos)</div>
                            <div class="highlight-value">Q<?php echo number_format($total_purchases, 2); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Desempeño neto -->
                <div class="highlight-section net">
                    <div class="highlight-title">Desempeño Neto</div>
                    <div class="highlight-value">Q<?php echo number_format($net_performance, 2); ?></div>
                    <div class="mt-2 opacity-75">
                        <?php if ($net_performance >= 0): ?>
                            <i class="bi bi-arrow-up-right"></i> Resultado positivo
                        <?php else: ?>
                            <i class="bi bi-arrow-down-right"></i> Resultado negativo
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Firmas -->
                <div class="signature-row">
                    <div class="signature-item">
                        <div class="signature-line"></div>
                        <div class="text-muted mt-2">Firma Administrador</div>
                    </div>
                    <div class="signature-item">
                        <div class="signature-line"></div>
                        <div class="text-muted mt-2">Firma Responsable</div>
                    </div>
                </div>
                
                <!-- Información de generación -->
                <div class="generation-info">
                    Generado automáticamente por Centro Médico Herrera Saenz Management System - 
                    <?php echo date('d/m/Y H:i'); ?>
                </div>
            </div>
            
            <!-- Acciones adicionales -->
            <div class="report-card animate-in delay-1">
                <h3 class="report-title mb-4">Exportar Reporte</h3>
                <div class="row g-3">
                    <div class="col-md-6">
                        <a href="export_jornada.php?date=<?php echo $date; ?>&format=csv" class="action-btn secondary w-100">
                            <i class="bi bi-file-earmark-spreadsheet"></i>
                            <span>Descargar CSV</span>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="export_jornada.php?date=<?php echo $date; ?>&format=excel" class="action-btn secondary w-100">
                            <i class="bi bi-file-earmark-excel"></i>
                            <span>Descargar Excel</span>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="export_jornada.php?date=<?php echo $date; ?>&format=word" class="action-btn secondary w-100">
                            <i class="bi bi-file-earmark-word"></i>
                            <span>Descargar Word</span>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="index.php" class="action-btn w-100">
                            <i class="bi bi-arrow-left"></i>
                            <span>Volver a Reportes</span>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- JavaScript Optimizado -->
    <script>
    (function() {
        'use strict';
        
        // ==========================================================================
        // CONFIGURACIÓN Y CONSTANTES
        // ==========================================================================
        const CONFIG = {
            themeKey: 'dashboard-theme',
            sidebarKey: 'sidebar-collapsed',
            transitionDuration: 300
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
            mobileSidebarToggle: document.getElementById('mobileSidebarToggle')
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
        // ANIMACIONES
        // ==========================================================================
        class AnimationManager {
            constructor() {
                this.setupAnimations();
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
                
                document.querySelectorAll('.report-card').forEach(el => {
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
            const animationManager = new AnimationManager();
            
            // Exponer APIs necesarias globalmente
            window.dashboard = {
                theme: themeManager,
                sidebar: sidebarManager
            };
            
            // Log de inicialización
            console.log('Reporte de Jornada - CMS v4.0');
            console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
            console.log('Fecha del reporte: <?php echo $date; ?>');
        });
        
        // ==========================================================================
        // POLYFILLS
        // ==========================================================================
        if (!NodeList.prototype.forEach) {
            NodeList.prototype.forEach = Array.prototype.forEach;
        }
        
    })();
    </script>
</body>
</html>