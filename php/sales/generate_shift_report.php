<?php
// sales/generate_shift_report.php - Reporte de Ventas por Jornada - Centro Médico Herrera Saenz
// Versión: 3.0 - Diseño Minimalista con Modo Noche y Efecto Mármol
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

date_default_timezone_set('America/Guatemala');
verify_session();

if (!isset($_GET['date'])) {
    die("Fecha no especificada.");
}

$selected_date = $_GET['date'];
// Jornada 1: 08:00 AM a 05:00 PM (17:00)
// Jornada 2: 05:00 PM (17:00) a 08:00 AM del día siguiente
$start_date = $selected_date . ' 08:00:00';
$end_date = $selected_date . ' 17:00:00';

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener ventas en el rango
    $query = "
        SELECT v.*, u.nombre as nombre_vendedor, u.apellido as apellido_vendedor
        FROM ventas v
        LEFT JOIN usuarios u ON v.id_usuario = u.idUsuario
        WHERE v.fecha_venta >= ? AND v.fecha_venta < ?
        ORDER BY v.fecha_venta ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$start_date, $end_date]);
    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular totales
    $total_sales = 0;
    $payment_methods = [];
    $sales_by_user = [];
    
    foreach ($ventas as $venta) {
        if ($venta['estado'] !== 'Cancelado') { // Solo contar ventas válidas
            $total_sales += $venta['total'];
            
            // Métodos de pago
            $method = $venta['tipo_pago'];
            if (!isset($payment_methods[$method])) {
                $payment_methods[$method] = 0;
            }
            $payment_methods[$method] += $venta['total'];
            
            // Ventas por usuario
            $user_name = ($venta['nombre_vendedor'] && $venta['apellido_vendedor']) 
                ? $venta['nombre_vendedor'] . ' ' . $venta['apellido_vendedor'] 
                : 'Desconocido / Sistema';
                
            if (!isset($sales_by_user[$user_name])) {
                $sales_by_user[$user_name] = ['count' => 0, 'total' => 0];
            }
            $sales_by_user[$user_name]['count']++;
            $sales_by_user[$user_name]['total'] += $venta['total'];
        }
    }

} catch (Exception $e) {
    die("Error al generar reporte: " . $e->getMessage());
}

// Título de la página
$page_title = "Reporte de Ventas por Jornada - Centro Médico Herrera Saenz";
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">
    
    <!-- Google Fonts - Inter para modernidad y legibilidad -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
    /* 
     * Reporte de Ventas por Jornada - Centro Médico Herrera Saenz
     * Diseño: Fondo blanco, colores pastel, efecto mármol, modo noche
     * Versión: 3.0
     */
    
    /* Variables CSS para modo claro y oscuro */
    :root {
        /* Modo claro (predeterminado) - Colores pastel */
        --color-background: #f8fafc;
        --color-surface: #ffffff;
        --color-primary: #7c90db;      /* Azul lavanda pastel */
        --color-primary-light: #a3b1e8;
        --color-primary-dark: #5a6fca;
        --color-secondary: #8dd7bf;    /* Verde menta pastel */
        --color-secondary-light: #b2e6d5;
        --color-accent: #f8b195;       /* Coral pastel */
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
        --marble-pattern: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23334155' fill-opacity='0.2' fill-rule='evenodd'/%3E%3C/svg%3E");
        
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
    
    /* Contenedor principal para impresión */
    .report-container {
        background: var(--color-surface);
        max-width: 1000px;
        margin: 30px auto;
        padding: 40px;
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow-xl);
        border: 1px solid var(--color-border);
        animation: slideUp 0.6s ease-out;
    }
    
    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Encabezado del reporte */
    .header-section {
        border-bottom: 2px solid var(--color-primary);
        padding-bottom: 20px;
        margin-bottom: 30px;
        text-align: center;
    }
    
    .clinic-name {
        font-family: 'Playfair Display', serif;
        color: var(--color-primary);
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 5px;
    }
    
    .report-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--color-text);
        margin-bottom: 10px;
    }
    
    .period-info {
        display: inline-block;
        background: var(--color-border-light);
        color: var(--color-text-light);
        padding: 10px 20px;
        border-radius: var(--radius-md);
        font-size: 0.95rem;
        margin-top: 10px;
    }
    
    /* Tarjetas de resumen */
    .summary-card {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 20px;
        height: 100%;
        transition: all var(--transition-normal);
    }
    
    .summary-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
    }
    
    .summary-card.border-primary {
        border-left: 4px solid var(--color-primary);
    }
    
    .summary-card.border-success {
        border-left: 4px solid var(--color-success);
    }
    
    .summary-card.border-info {
        border-left: 4px solid var(--color-info);
    }
    
    /* Tabla de transacciones */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        margin: 30px 0;
    }
    
    .data-table th {
        text-align: left;
        padding: 15px;
        font-weight: 600;
        color: var(--color-text-light);
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid var(--color-border);
        background: var(--color-border-light);
    }
    
    .data-table td {
        padding: 15px;
        border-bottom: 1px solid var(--color-border);
        color: var(--color-text);
    }
    
    .data-table tbody tr:hover {
        background: var(--color-border-light);
    }
    
    /* Badges y estados */
    .badge {
        padding: 5px 12px;
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
    
    .badge-secondary {
        background: var(--color-border);
        color: var(--color-text);
    }
    
    /* Botones de acción (no imprimir) */
    .action-buttons {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 100;
        display: flex;
        gap: 10px;
    }
    
    .action-btn {
        background: var(--color-primary);
        color: white;
        border: none;
        border-radius: var(--radius-md);
        padding: 12px 24px;
        font-weight: 500;
        font-size: 0.95rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all var(--transition-normal);
        text-decoration: none;
        box-shadow: var(--shadow-md);
    }
    
    .action-btn:hover {
        background: var(--color-primary-dark);
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    /* Estado vacío */
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--color-text-light);
    }
    
    .empty-icon {
        font-size: 3rem;
        color: var(--color-border);
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
    /* Firma y pie de página */
    .signature-section {
        margin-top: 50px;
        padding-top: 30px;
        border-top: 1px solid var(--color-border);
    }
    
    .signature-line {
        border-top: 1px solid var(--color-text);
        width: 200px;
        margin: 20px auto 5px;
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
    
    /* Estilos para impresión */
    @media print {
        body {
            background: white !important;
            color: black !important;
        }
        
        body::before,
        .marble-effect {
            display: none !important;
        }
        
        .report-container {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
            margin: 0 !important;
            padding: 20px !important;
            max-width: 100% !important;
        }
        
        .action-buttons {
            display: none !important;
        }
        
        .summary-card {
            break-inside: avoid;
        }
        
        .data-table {
            font-size: 12px;
        }
        
        .data-table th,
        .data-table td {
            padding: 8px 10px;
        }
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .report-container {
            padding: 20px;
            margin: 15px;
        }
        
        .header-section {
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .clinic-name {
            font-size: 22px;
        }
        
        .report-title {
            font-size: 1.25rem;
        }
        
        .action-buttons {
            position: static;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .data-table {
            font-size: 14px;
        }
    }
    </style>
</head>
<body>
    <!-- Efecto de mármol animado -->
    <div class="marble-effect"></div>
    
    <!-- Botones de acción (no se imprimen) -->
    <div class="action-buttons no-print">
        <button onclick="window.print()" class="action-btn">
            <i class="bi bi-printer"></i>
            Imprimir Reporte
        </button>
        <button onclick="window.close()" class="action-btn" style="background: var(--color-text-light);">
            <i class="bi bi-x-circle"></i>
            Cerrar
        </button>
    </div>
    
    <!-- Contenedor del reporte -->
    <div class="report-container">
        <!-- Encabezado -->
        <div class="header-section">
            <h1 class="clinic-name">Centro Médico Herrera Saenz</h1>
            <h2 class="report-title">Reporte de Ventas por Jornada</h2>
            <div class="period-info">
                <i class="bi bi-calendar-range me-2"></i>
                <?php echo date('d/m/Y h:i A', strtotime($start_date)); ?> 
                <span class="mx-2">➔</span> 
                <?php echo date('d/m/Y h:i A', strtotime($end_date)); ?>
            </div>
        </div>
        
        <!-- Resumen estadístico -->
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="summary-card border-primary">
                    <p class="text-uppercase text-muted text-xs mb-1">Total Ventas</p>
                    <h3 class="fw-bold text-dark mb-0">Q<?php echo number_format($total_sales, 2); ?></h3>
                    <small class="text-success fw-medium"><?php echo count($ventas); ?> transacciones</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="summary-card border-success">
                    <p class="text-uppercase text-muted text-xs mb-2">Métodos de Pago</p>
                    <ul class="list-unstyled mb-0 text-sm">
                        <?php foreach ($payment_methods as $method => $amount): ?>
                        <li class="d-flex justify-content-between mb-1">
                            <span><?php echo $method; ?>:</span>
                            <span class="fw-bold">Q<?php echo number_format($amount, 2); ?></span>
                        </li>
                        <?php endforeach; ?>
                        <?php if (empty($payment_methods)): ?>
                        <li class="text-muted fst-italic">Sin registros</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <div class="col-md-4">
                <div class="summary-card border-info">
                    <p class="text-uppercase text-muted text-xs mb-2">Ventas por Usuario</p>
                    <ul class="list-unstyled mb-0 text-sm">
                        <?php foreach ($sales_by_user as $user => $data): ?>
                        <li class="d-flex justify-content-between mb-1">
                            <span class="text-truncate" style="max-width: 120px;" title="<?php echo $user; ?>"><?php echo $user; ?>:</span>
                            <span class="fw-bold">Q<?php echo number_format($data['total'], 2); ?></span>
                        </li>
                        <?php endforeach; ?>
                        <?php if (empty($sales_by_user)): ?>
                        <li class="text-muted fst-italic">Sin registros</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Detalle de transacciones -->
        <h5 class="fw-bold mb-3 border-bottom pb-2">Detalle de Transacciones</h5>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 50px;">#</th>
                        <th>Fecha y Hora</th>
                        <th>Cliente</th>
                        <th>Vendedor</th>
                        <th>Método Pago</th>
                        <th>Estado</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($ventas) > 0): ?>
                        <?php foreach ($ventas as $index => $venta): ?>
                        <tr>
                            <td class="text-center text-muted"><?php echo $index + 1; ?></td>
                            <td>
                                <div class="fw-medium"><?php echo date('d/m/Y', strtotime($venta['fecha_venta'])); ?></div>
                                <small class="text-muted"><?php echo date('h:i A', strtotime($venta['fecha_venta'])); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($venta['nombre_cliente']); ?></td>
                            <td>
                                <?php 
                                    echo ($venta['nombre_vendedor']) 
                                        ? htmlspecialchars($venta['nombre_vendedor'] . ' ' . substr($venta['apellido_vendedor'], 0, 1) . '.') 
                                        : '<span class="text-muted fst-italic">Sistema</span>'; 
                                ?>
                            </td>
                            <td>
                                <span class="badge badge-secondary">
                                    <?php echo htmlspecialchars($venta['tipo_pago']); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $statusClass = match($venta['estado']) {
                                    'Pagado' => 'badge-success',
                                    'Pendiente' => 'badge-warning',
                                    'Cancelado' => 'badge-danger',
                                    default => 'badge-secondary'
                                };
                                ?>
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo htmlspecialchars($venta['estado']); ?>
                                </span>
                            </td>
                            <td class="text-end fw-bold text-dark">Q<?php echo number_format($venta['total'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="table-light">
                            <td colspan="6" class="text-end fw-bold">Total General:</td>
                            <td class="text-end fw-bold text-primary">Q<?php echo number_format($total_sales, 2); ?></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="bi bi-inbox"></i>
                                    </div>
                                    <h5>No se encontraron ventas en este período</h5>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Firmas -->
        <div class="signature-section">
            <div class="row">
                <div class="col-6 text-center">
                    <div class="signature-line"></div>
                    <small class="text-muted text-uppercase">Firma Cajero</small>
                </div>
                <div class="col-6 text-center">
                    <div class="signature-line"></div>
                    <small class="text-muted text-uppercase">Firma Administración</small>
                </div>
            </div>
        </div>
        
        <!-- Pie de página -->
        <div class="text-center mt-5 pt-3 border-top">
            <small class="text-muted">
                Generado el <?php echo date('d/m/Y h:i A'); ?> por <?php echo htmlspecialchars($_SESSION['nombre']); ?>
            </small>
        </div>
    </div>
    
    <script>
    // Reporte de Ventas por Jornada - Centro Médico Herrera Saenz
    // JavaScript para funcionalidades del reporte
    
    // Esperar a que el DOM esté completamente cargado
    document.addEventListener('DOMContentLoaded', function() {
        // ============ FUNCIONALIDAD DEL TEMA ============
        
        // Inicializar tema desde localStorage o preferencias del sistema
        function initializeTheme() {
            const savedTheme = localStorage.getItem('dashboard-theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        }
        
        // Inicializar tema
        initializeTheme();
        
        // ============ CONSOLA DE DESARROLLO ============
        
        console.log('Reporte de Ventas por Jornada - Centro Médico Herrera Saenz');
        console.log('Versión: 3.0 - Diseño con Efecto Mármol y Modo Noche');
        console.log('Período: <?php echo $selected_date; ?>');
        console.log('Total de transacciones: <?php echo count($ventas); ?>');
        console.log('Total vendido: Q<?php echo number_format($total_sales, 2); ?>');
    });
    </script>
</body>
</html>