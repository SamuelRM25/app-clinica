<?php
// export_jornada.php - Reporte de Jornada - Centro Médico Herrera Saenz
// Versión: 3.0 - Diseño Minimalista con Modo Noche y Efecto Mármol
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer zona horaria
date_default_timezone_set('America/Guatemala');
verify_session();

// Solo administradores pueden generar este reporte
$rol = $_SESSION['tipoUsuario'] ?? $_SESSION['rol'] ?? '';
if ($rol !== 'admin') {
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

    // Título de la página para HTML
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
    <title><?php echo $page_title; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">
    
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
    /* 
     * Reporte de Jornada - Centro Médico Herrera Saenz
     * Diseño: Fondo blanco, colores pastel, efecto mármol, modo noche
     * Versión: 3.0
     */
    
    /* Variables CSS para modo claro y oscuro */
    :root {
        /* Modo claro (predeterminado) - Colores pastel */
        --color-background: #f8fafc;
        --color-surface: #ffffff;
        --color-primary: #7c90db;
        --color-primary-light: #a3b1e8;
        --color-primary-dark: #5a6fca;
        --color-secondary: #8dd7bf;
        --color-secondary-light: #b2e6d5;
        --color-accent: #f8b195;
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
    .report-container {
        max-width: 900px;
        margin: 0 auto;
        padding: 2rem;
        animation: fadeIn 0.6s ease-out;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Tarjeta de reporte */
    .report-card {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 2rem;
        box-shadow: var(--shadow-lg);
        margin-bottom: 2rem;
        transition: transform var(--transition-normal);
    }
    
    .report-card:hover {
        transform: translateY(-4px);
    }
    
    /* Encabezado del reporte */
    .report-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid var(--color-border);
    }
    
    .report-title-section {
        display: flex;
        flex-direction: column;
    }
    
    .report-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--color-text);
        margin-bottom: 0.5rem;
    }
    
    .report-subtitle {
        font-size: 0.95rem;
        color: var(--color-text-light);
    }
    
    .report-actions {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    
    /* Botones de acción */
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
    
    .action-btn.secondary {
        background: transparent;
        color: var(--color-text);
        border: 1px solid var(--color-border);
    }
    
    .action-btn.secondary:hover {
        background: var(--color-border-light);
    }
    
    .action-btn.success {
        background: var(--color-success);
    }
    
    .action-btn.success:hover {
        background: #10b981;
    }
    
    /* Lista de métricas */
    .metrics-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .metric-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        border-radius: var(--radius-md);
        background: var(--color-border-light);
        transition: background-color var(--transition-normal);
    }
    
    .metric-item:hover {
        background: var(--color-border);
    }
    
    .metric-label {
        font-weight: 500;
        color: var(--color-text);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .metric-value {
        font-weight: 600;
        color: var(--color-text);
        font-size: 1.125rem;
    }
    
    /* Secciones destacadas */
    .highlight-section {
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    
    .highlight-section.income {
        background: linear-gradient(135deg, var(--color-success), #10b981);
        color: white;
    }
    
    .highlight-section.expense {
        background: linear-gradient(135deg, var(--color-error), #dc2626);
        color: white;
    }
    
    .highlight-section.net {
        background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
        color: white;
    }
    
    .highlight-title {
        font-size: 0.875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        opacity: 0.9;
        margin-bottom: 0.5rem;
    }
    
    .highlight-value {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1;
    }
    
    /* Filas de firmas */
    .signature-row {
        display: flex;
        justify-content: space-between;
        margin-top: 3rem;
        padding-top: 2rem;
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
        margin: 2rem auto 0.5rem;
    }
    
    /* Información de generación */
    .generation-info {
        text-align: center;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid var(--color-border);
        color: var(--color-text-light);
        font-size: 0.875rem;
    }
    
    /* Efecto de mármol animado */
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
    
    /* Responsive */
    @media (max-width: 768px) {
        .report-container {
            padding: 1rem;
        }
        
        .report-card {
            padding: 1.5rem;
        }
        
        .report-header {
            flex-direction: column;
            gap: 1rem;
        }
        
        .report-actions {
            width: 100%;
            justify-content: center;
        }
        
        .signature-row {
            flex-direction: column;
            gap: 2rem;
        }
        
        .signature-line {
            width: 150px;
        }
    }
    
    @media print {
        body {
            background: white;
        }
        
        body::before,
        .marble-effect,
        .report-actions {
            display: none !important;
        }
        
        .report-card {
            box-shadow: none;
            border: 1px solid #ddd;
        }
        
        .report-container {
            padding: 0;
            margin: 0;
            max-width: none;
        }
    }
    </style>
</head>
<body>
    <!-- Efecto de mármol animado -->
    <div class="marble-effect"></div>
    
    <div class="report-container">
        <!-- Tarjeta principal del reporte -->
        <div class="report-card">
            <!-- Encabezado -->
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
    </div>

    <script>
    // Funcionalidad básica para modo oscuro/claro
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar tema desde localStorage o preferencias del sistema
        const savedTheme = localStorage.getItem('dashboard-theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
        
        // Opcional: Botón para cambiar tema (si se quiere agregar en el futuro)
        console.log('Reporte de Jornada - Centro Médico Herrera Saenz');
        console.log('Fecha: <?php echo $date; ?>');
        console.log('Total Ingresos: Q<?php echo number_format($total_revenue, 2); ?>');
    });
    </script>
</body>
</html>