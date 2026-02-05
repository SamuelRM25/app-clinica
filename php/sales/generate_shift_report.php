<?php
// sales/generate_shift_report.php - Reporte de Ventas por Jornada - Centro Médico RS
// Reingenierizado con Diseño Dashboard Moderno
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
    $ventas_pagadas = 0;
    $ventas_pendientes = 0;
    $ventas_canceladas = 0;

    foreach ($ventas as $venta) {
        // Conteo por estado
        switch ($venta['estado']) {
            case 'Pagado':
                $ventas_pagadas++;
                break;
            case 'Pendiente':
                $ventas_pendientes++;
                break;
            case 'Cancelado':
                $ventas_canceladas++;
                break;
        }

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
                : 'Sistema';

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

// Obtener información del usuario
$user_name = $_SESSION['nombre'];
$user_specialty = $_SESSION['especialidad'] ?? 'Profesional Médico';

// Título de la página
$page_title = "Reporte de Ventas por Jornada - Centro Médico RS";
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Reporte de Ventas por Jornada - Centro Médico RS">
    <title><?php echo $page_title; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">

    <!-- Google Fonts - Inter (moderno y legible) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@700&display=swap"
        rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

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
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.12);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);

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
            --font-family-display: 'Playfair Display', serif;
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
            padding: var(--space-lg);
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
            0% {
                background-position: 0% 0%;
            }

            100% {
                background-position: 100% 100%;
            }
        }

        /* ==========================================================================
       CONTENEDOR PRINCIPAL DEL REPORTE
       ========================================================================== */
        .report-container {
            max-width: 1200px;
            margin: 0 auto;
            animation: fadeInUp 0.6s ease-out;
        }

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

        /* ==========================================================================
       ENCABEZADO DEL REPORTE
       ========================================================================== */
        .report-header {
            text-align: center;
            margin-bottom: var(--space-xl);
            padding-bottom: var(--space-lg);
            border-bottom: 2px solid var(--color-primary);
            position: relative;
        }

        .clinic-name {
            font-family: var(--font-family-display);
            font-size: var(--font-size-3xl);
            font-weight: 700;
            color: var(--color-primary);
            margin-bottom: var(--space-sm);
            letter-spacing: -0.5px;
        }

        .report-title {
            font-size: var(--font-size-2xl);
            font-weight: 600;
            color: var(--color-text);
            margin-bottom: var(--space-md);
        }

        .period-info {
            display: inline-flex;
            align-items: center;
            gap: var(--space-sm);
            background: var(--color-surface);
            color: var(--color-text-secondary);
            padding: var(--space-sm) var(--space-lg);
            border-radius: var(--radius-lg);
            font-size: var(--font-size-sm);
            border: 1px solid var(--color-border);
        }

        .period-info i {
            color: var(--color-primary);
        }

        /* ==========================================================================
       BOTONES DE ACCIÓN
       ========================================================================== */
        .action-buttons {
            position: fixed;
            top: var(--space-lg);
            right: var(--space-lg);
            z-index: 100;
            display: flex;
            gap: var(--space-sm);
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
            box-shadow: var(--shadow-md);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
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
            background: var(--color-border);
        }

        /* ==========================================================================
       RESUMEN ESTADÍSTICO
       ========================================================================== */
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-lg);
            margin-bottom: var(--space-xl);
        }

        .summary-card {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            transition: all var(--transition-base);
            position: relative;
            overflow: hidden;
        }

        .summary-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
            border-color: var(--color-primary);
        }

        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--color-primary), var(--color-info));
        }

        .summary-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--space-md);
        }

        .summary-title {
            font-size: var(--font-size-sm);
            color: var(--color-text-secondary);
            font-weight: 500;
            margin-bottom: var(--space-xs);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .summary-value {
            font-size: var(--font-size-3xl);
            font-weight: 700;
            color: var(--color-text);
            line-height: 1;
        }

        .summary-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .summary-icon.primary {
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary));
        }

        .summary-icon.success {
            background: linear-gradient(135deg, var(--color-success), #10b981);
        }

        .summary-icon.info {
            background: linear-gradient(135deg, var(--color-info), #0ea5e9);
        }

        .summary-icon.warning {
            background: linear-gradient(135deg, var(--color-warning), #d97706);
        }

        .summary-details {
            list-style: none;
            margin-top: var(--space-md);
        }

        .summary-details li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--space-xs) 0;
            border-bottom: 1px solid var(--color-border);
        }

        .summary-details li:last-child {
            border-bottom: none;
        }

        /* ==========================================================================
       TABLA DE TRANSACCIONES
       ========================================================================== */
        .transactions-section {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            margin-bottom: var(--space-xl);
            transition: all var(--transition-base);
        }

        .transactions-section:hover {
            box-shadow: var(--shadow-lg);
        }

        .section-title {
            font-size: var(--font-size-xl);
            font-weight: 600;
            color: var(--color-text);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            margin-bottom: var(--space-lg);
            padding-bottom: var(--space-md);
            border-bottom: 1px solid var(--color-border);
        }

        .section-title-icon {
            color: var(--color-primary);
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .transactions-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .transactions-table thead {
            background: var(--color-surface);
        }

        .transactions-table th {
            text-align: left;
            padding: var(--space-md);
            font-weight: 600;
            color: var(--color-text);
            border-bottom: 2px solid var(--color-border);
            white-space: nowrap;
            font-size: var(--font-size-sm);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .transactions-table td {
            padding: var(--space-md);
            border-bottom: 1px solid var(--color-border);
            vertical-align: middle;
        }

        .transactions-table tbody tr {
            transition: all var(--transition-base);
        }

        .transactions-table tbody tr:hover {
            background: var(--color-surface);
            transform: translateX(4px);
        }

        /* Celdas especializadas */
        .transaction-cell {
            display: flex;
            align-items: center;
            gap: var(--space-md);
        }

        .transaction-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--color-success), var(--color-info));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: var(--font-size-sm);
            flex-shrink: 0;
        }

        .transaction-info {
            min-width: 0;
        }

        .transaction-id {
            font-weight: 600;
            color: var(--color-text);
            margin-bottom: 2px;
        }

        .transaction-time {
            color: var(--color-text-secondary);
            font-size: var(--font-size-xs);
        }

        .client-name {
            font-weight: 600;
            color: var(--color-text);
        }

        .vendedor-name {
            color: var(--color-text-secondary);
            font-size: var(--font-size-sm);
        }

        .payment-badge {
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

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
            padding: var(--space-xs) var(--space-sm);
            border-radius: var(--radius-sm);
            font-size: var(--font-size-sm);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.pagado {
            background: rgba(var(--color-success-rgb), 0.1);
            color: var(--color-success);
            border: 1px solid rgba(var(--color-success-rgb), 0.3);
        }

        .status-badge.pendiente {
            background: rgba(var(--color-warning-rgb), 0.1);
            color: var(--color-warning);
            border: 1px solid rgba(var(--color-warning-rgb), 0.3);
        }

        .status-badge.cancelado {
            background: rgba(var(--color-danger-rgb), 0.1);
            color: var(--color-danger);
            border: 1px solid rgba(var(--color-danger-rgb), 0.3);
        }

        .amount-cell {
            text-align: right;
            font-weight: 600;
            color: var(--color-success);
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

        /* ==========================================================================
       RESUMEN FINAL
       ========================================================================== */
        .final-summary {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            margin-bottom: var(--space-xl);
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--space-lg);
        }

        .summary-item {
            text-align: center;
            padding: var(--space-md);
            border-radius: var(--radius-md);
            background: var(--color-surface);
        }

        .summary-item-label {
            font-size: var(--font-size-sm);
            color: var(--color-text-secondary);
            margin-bottom: var(--space-xs);
        }

        .summary-item-value {
            font-size: var(--font-size-xl);
            font-weight: 700;
            color: var(--color-text);
        }

        /* ==========================================================================
       FIRMAS Y PIE DE PÁGINA
       ========================================================================== */
        .signature-section {
            margin-top: var(--space-xl);
            padding-top: var(--space-lg);
            border-top: 1px solid var(--color-border);
        }

        .signature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--space-xl);
            margin-top: var(--space-lg);
        }

        .signature-box {
            text-align: center;
        }

        .signature-line {
            border-top: 2px solid var(--color-text);
            width: 200px;
            margin: var(--space-md) auto var(--space-sm);
        }

        .signature-label {
            font-size: var(--font-size-sm);
            color: var(--color-text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .footer-info {
            text-align: center;
            margin-top: var(--space-xl);
            padding-top: var(--space-lg);
            border-top: 1px solid var(--color-border);
            color: var(--color-text-secondary);
            font-size: var(--font-size-sm);
        }

        /* ==========================================================================
       ESTILOS DE IMPRESIÓN
       ========================================================================== */
        @media print {
            body {
                background: white !important;
                color: black !important;
                padding: 0 !important;
                font-size: 12pt;
            }

            .marble-effect {
                display: none !important;
            }

            .action-buttons {
                display: none !important;
            }

            .report-container {
                max-width: 100% !important;
                margin: 0 !important;
                box-shadow: none !important;
            }

            .report-header,
            .stats-summary,
            .transactions-section,
            .final-summary,
            .signature-section {
                break-inside: avoid;
                border: 1px solid #ddd !important;
                box-shadow: none !important;
                margin-bottom: 20pt !important;
            }

            .summary-card:hover,
            .transactions-section:hover {
                transform: none !important;
                box-shadow: none !important;
            }

            .summary-card::before,
            .transactions-table tbody tr:hover {
                display: none !important;
            }

            .transactions-table {
                font-size: 10pt;
            }

            .transactions-table th,
            .transactions-table td {
                padding: 6pt 8pt;
            }

            .clinic-name {
                color: black !important;
                font-size: 16pt;
            }

            .report-title {
                font-size: 14pt;
            }

            .summary-value {
                font-size: 16pt;
            }

            .summary-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 1rem !important;
            }
        }

        /* ==========================================================================
       RESPONSIVE DESIGN
       ========================================================================== */

        /* Pantallas grandes */
        @media (min-width: 1600px) {
            .report-container {
                max-width: 1400px;
            }

            .stats-summary {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        /* Escritorio estándar */
        @media (max-width: 1399px) {
            .stats-summary {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            }
        }

        /* Tablets */
        @media (max-width: 991px) {
            body {
                padding: var(--space-md);
            }

            .action-buttons {
                position: static;
                justify-content: center;
                margin-bottom: var(--space-lg);
                flex-wrap: wrap;
            }

            .clinic-name {
                font-size: var(--font-size-2xl);
            }

            .report-title {
                font-size: var(--font-size-xl);
            }

            .stats-summary {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--space-md);
            }

            .summary-card {
                padding: var(--space-md);
            }

            .summary-value {
                font-size: var(--font-size-2xl);
            }
        }

        /* Móviles */
        @media (max-width: 767px) {
            body {
                padding: var(--space-sm);
            }

            .stats-summary {
                grid-template-columns: 1fr;
            }

            .clinic-name {
                font-size: var(--font-size-xl);
            }

            .report-title {
                font-size: var(--font-size-lg);
            }

            .period-info {
                flex-direction: column;
                gap: var(--space-xs);
                padding: var(--space-sm);
            }

            .action-buttons {
                flex-direction: column;
                align-items: stretch;
            }

            .action-btn {
                justify-content: center;
            }

            .transactions-section {
                padding: var(--space-md);
            }

            .transactions-table {
                font-size: var(--font-size-sm);
            }

            .transactions-table th,
            .transactions-table td {
                padding: var(--space-sm);
            }

            .transaction-cell {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--space-xs);
            }

            .transaction-avatar {
                width: 32px;
                height: 32px;
                font-size: var(--font-size-xs);
            }

            .signature-grid {
                grid-template-columns: 1fr;
                gap: var(--space-lg);
            }
        }

        /* Móviles pequeños */
        @media (max-width: 480px) {
            .summary-card {
                padding: var(--space-md);
            }

            .section-title {
                font-size: var(--font-size-base);
            }

            .summary-details li {
                font-size: var(--font-size-sm);
            }
        }

        /* ==========================================================================
       ANIMACIONES
       ========================================================================== */
        .animate-in {
            animation: fadeInUp 0.6s ease-out forwards;
        }

        .delay-1 {
            animation-delay: 0.1s;
        }

        .delay-2 {
            animation-delay: 0.2s;
        }

        .delay-3 {
            animation-delay: 0.3s;
        }

        .delay-4 {
            animation-delay: 0.4s;
        }

        /* ==========================================================================
       UTILIDADES
       ========================================================================== */
        .text-primary {
            color: var(--color-primary);
        }

        .text-success {
            color: var(--color-success);
        }

        .text-warning {
            color: var(--color-warning);
        }

        .text-danger {
            color: var(--color-danger);
        }

        .text-info {
            color: var(--color-info);
        }

        .text-muted {
            color: var(--color-text-secondary);
        }

        .bg-primary {
            background-color: var(--color-primary);
        }

        .bg-success {
            background-color: var(--color-success);
        }

        .bg-warning {
            background-color: var(--color-warning);
        }

        .bg-danger {
            background-color: var(--color-danger);
        }

        .bg-info {
            background-color: var(--color-info);
        }

        .mb-0 {
            margin-bottom: 0;
        }

        .mb-1 {
            margin-bottom: var(--space-xs);
        }

        .mb-2 {
            margin-bottom: var(--space-sm);
        }

        .mb-3 {
            margin-bottom: var(--space-md);
        }

        .mb-4 {
            margin-bottom: var(--space-lg);
        }

        .mb-5 {
            margin-bottom: var(--space-xl);
        }

        .mt-0 {
            margin-top: 0;
        }

        .mt-1 {
            margin-top: var(--space-xs);
        }

        .mt-2 {
            margin-top: var(--space-sm);
        }

        .mt-3 {
            margin-top: var(--space-md);
        }

        .mt-4 {
            margin-top: var(--space-lg);
        }

        .mt-5 {
            margin-top: var(--space-xl);
        }

        .d-none {
            display: none;
        }

        .d-block {
            display: block;
        }

        .d-flex {
            display: flex;
        }

        .gap-1 {
            gap: var(--space-xs);
        }

        .gap-2 {
            gap: var(--space-sm);
        }

        .gap-3 {
            gap: var(--space-md);
        }

        .gap-4 {
            gap: var(--space-lg);
        }

        .gap-5 {
            gap: var(--space-xl);
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-left {
            text-align: left;
        }

        .fw-bold {
            font-weight: 700;
        }

        .fw-semibold {
            font-weight: 600;
        }

        .fw-medium {
            font-weight: 500;
        }

        .fw-normal {
            font-weight: 400;
        }

        .fw-light {
            font-weight: 300;
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
        <button onclick="window.close()" class="action-btn secondary">
            <i class="bi bi-x-circle"></i>
            Cerrar Ventana
        </button>
    </div>

    <!-- Contenedor del reporte -->
    <div class="report-container">
        <!-- Encabezado -->
        <div class="report-header animate-in">
            <h1 class="clinic-name">Centro Médico RS</h1>
            <h2 class="report-title">Reporte de Ventas por Jornada</h2>
            <div class="period-info">
                <i class="bi bi-calendar-range"></i>
                <span><?php echo date('d/m/Y h:i A', strtotime($start_date)); ?></span>
                <i class="bi bi-arrow-right"></i>
                <span><?php echo date('d/m/Y h:i A', strtotime($end_date)); ?></span>
            </div>
        </div>

        <!-- Resumen estadístico -->
        <div class="stats-summary animate-in delay-1">
            <!-- Total vendido -->
            <div class="summary-card">
                <div class="summary-header">
                    <div>
                        <div class="summary-title">Total Vendido</div>
                        <div class="summary-value">Q<?php echo number_format($total_sales, 2); ?></div>
                    </div>
                    <div class="summary-icon primary">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                </div>
                <ul class="summary-details">
                    <li>
                        <span>Transacciones:</span>
                        <span class="fw-semibold"><?php echo count($ventas); ?></span>
                    </li>
                    <li>
                        <span>Promedio por venta:</span>
                        <span
                            class="fw-semibold">Q<?php echo count($ventas) > 0 ? number_format($total_sales / count($ventas), 2) : '0.00'; ?></span>
                    </li>
                </ul>
            </div>

            <!-- Métodos de pago -->
            <div class="summary-card">
                <div class="summary-header">
                    <div>
                        <div class="summary-title">Métodos de Pago</div>
                        <div class="summary-value"><?php echo count($payment_methods); ?></div>
                    </div>
                    <div class="summary-icon success">
                        <i class="bi bi-credit-card"></i>
                    </div>
                </div>
                <ul class="summary-details">
                    <?php foreach ($payment_methods as $method => $amount): ?>
                        <li>
                            <span><?php echo $method; ?>:</span>
                            <span class="fw-semibold text-success">Q<?php echo number_format($amount, 2); ?></span>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($payment_methods)): ?>
                        <li class="text-muted fst-italic">Sin registros</li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Ventas por usuario -->
            <div class="summary-card">
                <div class="summary-header">
                    <div>
                        <div class="summary-title">Ventas por Vendedor</div>
                        <div class="summary-value"><?php echo count($sales_by_user); ?></div>
                    </div>
                    <div class="summary-icon info">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
                <ul class="summary-details">
                    <?php foreach ($sales_by_user as $user => $data): ?>
                        <li>
                            <span class="text-truncate" title="<?php echo $user; ?>"><?php echo $user; ?>:</span>
                            <span class="fw-semibold"><?php echo $data['count']; ?> ventas</span>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($sales_by_user)): ?>
                        <li class="text-muted fst-italic">Sin registros</li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Estado de ventas -->
            <div class="summary-card">
                <div class="summary-header">
                    <div>
                        <div class="summary-title">Estado de Ventas</div>
                        <div class="summary-value"><?php echo count($ventas); ?></div>
                    </div>
                    <div class="summary-icon warning">
                        <i class="bi bi-clipboard-check"></i>
                    </div>
                </div>
                <ul class="summary-details">
                    <li>
                        <span>Pagadas:</span>
                        <span class="fw-semibold text-success"><?php echo $ventas_pagadas; ?></span>
                    </li>
                    <li>
                        <span>Pendientes:</span>
                        <span class="fw-semibold text-warning"><?php echo $ventas_pendientes; ?></span>
                    </li>
                    <li>
                        <span>Canceladas:</span>
                        <span class="fw-semibold text-danger"><?php echo $ventas_canceladas; ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Detalle de transacciones -->
        <section class="transactions-section animate-in delay-2">
            <h3 class="section-title">
                <i class="bi bi-receipt section-title-icon"></i>
                Detalle de Transacciones
            </h3>

            <div class="table-responsive">
                <?php if (count($ventas) > 0): ?>
                    <table class="transactions-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Venta</th>
                                <th>Cliente</th>
                                <th>Vendedor</th>
                                <th>Método Pago</th>
                                <th>Estado</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ventas as $index => $venta): ?>
                                <?php
                                $fecha_venta = new DateTime($venta['fecha_venta']);
                                $hora_venta = $fecha_venta->format('h:i A');
                                $fecha_formateada = $fecha_venta->format('d/m/Y');
                                $vendedor_nombre = ($venta['nombre_vendedor'] && $venta['apellido_vendedor'])
                                    ? $venta['nombre_vendedor'] . ' ' . substr($venta['apellido_vendedor'], 0, 1) . '.'
                                    : 'Sistema';
                                ?>
                                <tr>
                                    <td class="text-muted"><?php echo $index + 1; ?></td>
                                    <td>
                                        <div class="transaction-cell">
                                            <div class="transaction-avatar">
                                                <?php echo strtoupper(substr($venta['nombre_cliente'] ?? 'C', 0, 1)); ?>
                                            </div>
                                            <div class="transaction-info">
                                                <div class="transaction-id">
                                                    #VTA-<?php echo str_pad($venta['id_venta'], 5, '0', STR_PAD_LEFT); ?></div>
                                                <div class="transaction-time"><?php echo $hora_venta; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="client-name"><?php echo htmlspecialchars($venta['nombre_cliente']); ?></div>
                                    </td>
                                    <td>
                                        <div class="vendedor-name"><?php echo $vendedor_nombre; ?></div>
                                    </td>
                                    <td>
                                        <span class="payment-badge">
                                            <i class="bi bi-credit-card"></i>
                                            <?php echo htmlspecialchars($venta['tipo_pago']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = match ($venta['estado']) {
                                            'Pagado' => 'pagado',
                                            'Pendiente' => 'pendiente',
                                            'Cancelado' => 'cancelado',
                                            default => 'pendiente'
                                        };
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars($venta['estado']); ?>
                                        </span>
                                    </td>
                                    <td class="amount-cell">
                                        Q<?php echo number_format($venta['total'], 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="6" class="text-right fw-bold">Total General:</td>
                                <td class="amount-cell fw-bold">Q<?php echo number_format($total_sales, 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="bi bi-inbox"></i>
                        </div>
                        <h4 class="text-muted mb-2">No se encontraron ventas en este período</h4>
                        <p class="text-muted mb-3">El reporte está vacío para la fecha seleccionada.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Resumen final -->
        <div class="final-summary animate-in delay-3">
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-item-label">Total Transacciones</div>
                    <div class="summary-item-value"><?php echo count($ventas); ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-item-label">Ventas Válidas</div>
                    <div class="summary-item-value"><?php echo $ventas_pagadas + $ventas_pendientes; ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-item-label">Ventas Canceladas</div>
                    <div class="summary-item-value text-danger"><?php echo $ventas_canceladas; ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-item-label">Total Recaudado</div>
                    <div class="summary-item-value text-success">Q<?php echo number_format($total_sales, 2); ?></div>
                </div>
            </div>
        </div>

        <!-- Firmas -->
        <div class="signature-section animate-in delay-4">
            <div class="signature-grid">
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-label">Firma Cajero</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-label">Firma Administración</div>
                </div>
            </div>
        </div>

        <!-- Pie de página -->
        <div class="footer-info animate-in delay-4">
            <p class="mb-2">
                Reporte generado el <strong><?php echo date('d/m/Y h:i A'); ?></strong>
                por <strong><?php echo htmlspecialchars($user_name); ?></strong> -
                <?php echo htmlspecialchars($user_specialty); ?>
            </p>
            <p class="text-muted">
                Centro Médico RS - Sistema de Gestión Médica v4.0
            </p>
        </div>
    </div>

    <!-- JavaScript Optimizado -->
    <script>
        // Reporte de Ventas por Jornada Reingenierizado

        (function () {
            'use strict';

            // ==========================================================================
            // CONFIGURACIÓN Y CONSTANTES
            // ==========================================================================
            const CONFIG = {
                themeKey: 'dashboard-theme',
                transitionDuration: 300
            };

            // ==========================================================================
            // REFERENCIAS A ELEMENTOS DOM
            // ==========================================================================
            const DOM = {
                html: document.documentElement,
                body: document.body,
                printButton: document.querySelector('[onclick="window.print()"]'),
                closeButton: document.querySelector('[onclick="window.close()"]')
            };

            // ==========================================================================
            // MANEJO DE TEMA (DÍA/NOCHE)
            // ==========================================================================
            class ThemeManager {
                constructor() {
                    this.theme = this.getInitialTheme();
                    this.applyTheme(this.theme);
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
                }
            }

            // ==========================================================================
            // ANIMACIONES Y EFECTOS
            // ==========================================================================
            class AnimationManager {
                constructor() {
                    this.setupPrintButton();
                    this.setupCloseButton();
                    this.animateElements();
                }

                setupPrintButton() {
                    if (DOM.printButton) {
                        DOM.printButton.addEventListener('click', (e) => {
                            e.preventDefault();

                            // Animación en el botón
                            DOM.printButton.style.transform = 'scale(0.95)';
                            setTimeout(() => {
                                DOM.printButton.style.transform = '';
                            }, 200);

                            // Retardo para permitir animación antes de imprimir
                            setTimeout(() => {
                                window.print();
                            }, 300);
                        });
                    }
                }

                setupCloseButton() {
                    if (DOM.closeButton) {
                        DOM.closeButton.addEventListener('click', (e) => {
                            e.preventDefault();

                            // Animación en el botón
                            DOM.closeButton.style.transform = 'scale(0.95)';
                            setTimeout(() => {
                                DOM.closeButton.style.transform = '';
                            }, 200);

                            // Confirmar antes de cerrar si hay datos importantes
                            if (<?php echo count($ventas); ?> > 0) {
                                if (confirm('¿Está seguro que desea cerrar el reporte?')) {
                                    window.close();
                                }
                            } else {
                                window.close();
                            }
                        });
                    }
                }

                animateElements() {
                    // Añadir clases de animación a elementos
                    const elements = document.querySelectorAll('.summary-card, .transactions-section, .final-summary, .signature-section');
                    elements.forEach((el, index) => {
                        el.style.animationDelay = `${(index + 1) * 0.1}s`;
                    });
                }
            }

            // ==========================================================================
            // INICIALIZACIÓN DE LA APLICACIÓN
            // ==========================================================================
            document.addEventListener('DOMContentLoaded', () => {
                // Inicializar componentes
                const themeManager = new ThemeManager();
                const animationManager = new AnimationManager();

                // Log de inicialización
                console.log('Reporte de Ventas por Jornada v4.0');
                console.log('Período: <?php echo $selected_date; ?>');
                console.log('Total transacciones: <?php echo count($ventas); ?>');
                console.log('Total vendido: Q<?php echo number_format($total_sales, 2); ?>');
                console.log('Generado por: <?php echo htmlspecialchars($user_name); ?>');
            });

            // ==========================================================================
            // MANEJO DE IMPRESIÓN
            // ==========================================================================
            window.addEventListener('beforeprint', () => {
                // Cambiar a tema claro para impresión
                DOM.html.setAttribute('data-theme', 'light');

                // Ocultar botones de acción
                const actionButtons = document.querySelector('.action-buttons');
                if (actionButtons) {
                    actionButtons.classList.add('d-none');
                }
            });

            window.addEventListener('afterprint', () => {
                // Restaurar tema original
                const themeManager = new ThemeManager();

                // Mostrar botones de acción
                const actionButtons = document.querySelector('.action-buttons');
                if (actionButtons) {
                    actionButtons.classList.remove('d-none');
                }
            });

        })();

        // Estilos adicionales para mejorar la impresión
        const printStyles = document.createElement('style');
        printStyles.textContent = `
        @media print {
            @page {
                margin: 0.5in;
                size: letter;
            }
            
            body {
                font-size: 11pt !important;
                line-height: 1.3 !important;
            }
            
            .report-header {
                margin-bottom: 15pt !important;
            }
            
            .clinic-name {
                font-size: 16pt !important;
            }
            
            .report-title {
                font-size: 14pt !important;
            }
            
            .summary-value {
                font-size: 14pt !important;
            }
            
            .transactions-table {
                font-size: 9pt !important;
            }
            
            .transaction-avatar {
                display: none !important;
            }
            
            .signature-line {
                margin-top: 30pt !important;
            }
        }
    `;
        document.head.appendChild(printStyles);
    </script>
</body>

</html>