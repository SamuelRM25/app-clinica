<?php
// index.php - Módulo de Reportes - Centro Médico Herrera Saenz
// Versión: 3.0 - Diseño Minimalista con Modo Noche y Efecto Mármol
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer zona horaria
date_default_timezone_set('America/Guatemala');
verify_session();

// Obtener rol de usuario
$user_type = $_SESSION['tipoUsuario'];
$user_name = $_SESSION['nombre'];
$user_specialty = $_SESSION['especialidad'] ?? 'Profesional Médico';

// Obtener fechas para filtros (predeterminado: mes actual)
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');

    // Ajustar para rangos de jornada
    // Jornada 1: 08:00 AM a 05:00 PM (17:00)
    // Jornada 2: 05:00 PM (17:00) a 08:00 AM del día siguiente
    $start_datetime = $fecha_inicio . ' 08:00:00';
$end_datetime = date('Y-m-d', strtotime($fecha_fin . ' +1 day')) . ' 07:59:59';

try {
    $database = new Database();
    $conn = $database->getConnection();

    // ============ CÁLCULO DE MÉTRICAS PRINCIPALES ============

    // 1. Ventas de medicamentos (ingresos brutos por ventas)
    $stmt_sales = $conn->prepare("SELECT SUM(total) as total_sales FROM ventas WHERE fecha_venta BETWEEN ? AND ?");
    $stmt_sales->execute([$start_datetime, $end_datetime]);
    $total_sales_meds = $stmt_sales->fetch(PDO::FETCH_ASSOC)['total_sales'] ?? 0;

    // 2. Compras de medicamentos (egresos - dinero gastado en reabastecimiento)
    $stmt_purchases = $conn->prepare("SELECT SUM(total_amount) as total_purchases FROM purchase_headers WHERE purchase_date BETWEEN ? AND ?");
    $stmt_purchases->execute([$fecha_inicio, $fecha_fin]);
    $total_purchases_meds = $stmt_purchases->fetch(PDO::FETCH_ASSOC)['total_purchases'] ?? 0;

    // 3. Cálculo de Ganancia Real sobre lo vendido (Precio Venta - Precio Costo)
    $stmt_actual_profit = $conn->prepare("
        SELECT 
            SUM(dv.cantidad * dv.precio_unitario) as revenue,
            SUM(dv.cantidad * i.costo_med) as cost
        FROM detalle_ventas dv
        JOIN ventas v ON dv.id_venta = v.id_venta
        JOIN inventario i ON dv.id_medicamento = i.id_med
        WHERE v.fecha_venta BETWEEN ? AND ?
    ");
    $stmt_actual_profit->execute([$start_datetime, $end_datetime]);
    $profit_data = $stmt_actual_profit->fetch(PDO::FETCH_ASSOC);
    $sales_revenue = $profit_data['revenue'] ?? 0;
    $sales_cost = $profit_data['cost'] ?? 0;
    $actual_sales_margin = $sales_revenue - $sales_cost;

    // 4. Procedimientos menores
    $stmt_proc = $conn->prepare("SELECT SUM(cobro) FROM procedimientos_menores WHERE fecha_procedimiento BETWEEN ? AND ?");
    $stmt_proc->execute([$start_datetime, $end_datetime]);
    $total_procedures = $stmt_proc->fetchColumn() ?: 0;

    // 5. Exámenes realizados
    $stmt_exams = $conn->prepare("SELECT SUM(cobro) FROM examenes_realizados WHERE fecha_examen BETWEEN ? AND ?");
    $stmt_exams->execute([$start_datetime, $end_datetime]);
    $total_exams_revenue = $stmt_exams->fetchColumn() ?: 0;

    // 6. Cobros de consultas (Ajustado a rango jornada si es posible, de lo contrario fecha_consulta)
    $stmt_billings = $conn->prepare("SELECT SUM(cantidad_consulta) FROM cobros WHERE fecha_consulta BETWEEN ? AND ?");
    $stmt_billings->execute([$fecha_inicio, $fecha_fin]);
    $total_billings = $stmt_billings->fetchColumn() ?: 0;

    // 7. Ingresos brutos totales
    $total_gross_revenue = $total_sales_meds + $total_procedures + $total_exams_revenue + $total_billings;

    // 8. Utilidad Bruta (Total Ingresos - Costo de lo Vendido)
    $total_gross_profit = $total_gross_revenue - $sales_cost;

    // 9. Desempeño neto (Ingresos Totales - Compras Totales) - Flujo de Caja
    $net_cash_flow = $total_gross_revenue - $total_purchases_meds;

    // ============ MÉTRICAS 'BIG DATA' PARA GRÁFICOS ============

    // A. Tendencia de Ventas Diarias (Últimos 30 días)
    $stmt_trend = $conn->prepare("
        SELECT DATE(fecha_venta) as fecha, SUM(total) as total 
        FROM ventas 
        WHERE fecha_venta >= DATE_SUB(?, INTERVAL 30 DAY)
        GROUP BY DATE(fecha_venta)
        ORDER BY fecha ASC
    ");
    $stmt_trend->execute([$end_datetime]);
    $sales_trend_data = $stmt_trend->fetchAll(PDO::FETCH_ASSOC);

    // B. Distribución de Ingresos por Categoría
    $category_data = [
        'Ventas' => (float)$total_sales_meds,
        'Consultas' => (float)$total_billings,
        'Procedimientos' => (float)$total_procedures,
        'Exámenes' => (float)$total_exams_revenue
    ];

    // C. Top 5 Medicamentos más vendidos
    $stmt_top_meds = $conn->prepare("
        SELECT i.nombre_med, SUM(dv.cantidad) as total_vendido
        FROM detalle_ventas dv
        JOIN inventario i ON dv.id_medicamento = i.id_med
        JOIN ventas v ON dv.id_venta = v.id_venta
        WHERE v.fecha_venta BETWEEN ? AND ?
        GROUP BY i.id_med
        ORDER BY total_vendido DESC
        LIMIT 5
    ");
    $stmt_top_meds->execute([$start_datetime, $end_datetime]);
    $top_meds_data = $stmt_top_meds->fetchAll(PDO::FETCH_ASSOC);

    // ============ MÉTRICAS ADICIONALES ============

    // Total de pacientes registrados
    $total_pacientes = $conn->query("SELECT COUNT(*) FROM pacientes")->fetchColumn();

    // Citas en el período
    $stmt_citas = $conn->prepare("SELECT COUNT(*) FROM citas WHERE fecha_cita BETWEEN ? AND ?");
    $stmt_citas->execute([$start_datetime, $end_datetime]);
    $citas_count = $stmt_citas->fetchColumn();

    // Exámenes realizados en el período (conteo)
    $stmt_examenes_count = $conn->prepare("SELECT COUNT(*) FROM examenes_realizados WHERE fecha_examen BETWEEN ? AND ?");
    $stmt_examenes_count->execute([$start_datetime, $end_datetime]);
    $examenes_count = $stmt_examenes_count->fetchColumn();

    // Medicamentos en stock
    $total_medicamentos = $conn->query("SELECT COUNT(*) FROM inventario WHERE cantidad_med > 0")->fetchColumn();

} catch (Exception $e) {
    die("Error: No se pudo conectar a la base de datos");
}

// Título de la página
$page_title = "Reportes - Centro Médico Herrera Saenz";
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
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
    /* 
     * Módulo de Reportes - Centro Médico Herrera Saenz
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
    .dashboard-container {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        position: relative;
    }
    
    /* ============ HEADER SUPERIOR ============ */
    .dashboard-header {
        background: var(--color-surface);
        border-bottom: 1px solid var(--color-border);
        padding: 1rem 2rem;
        position: sticky;
        top: 0;
        z-index: 100;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        animation: slideDown 0.4s ease-out;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .header-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        max-width: 1400px;
        margin: 0 auto;
    }
    
    /* Logo y marca */
    .brand-container {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .brand-logo {
        height: 48px;
        width: auto;
        filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        transition: transform var(--transition-normal);
    }
    
    .brand-logo:hover {
        transform: scale(1.05);
    }
    
    .brand-text {
        display: flex;
        flex-direction: column;
    }
    
    .clinic-name {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--color-text);
        letter-spacing: -0.5px;
        line-height: 1.2;
    }
    
    .clinic-subname {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--color-primary);
        letter-spacing: 0.5px;
    }
    
    /* Control de tema y usuario */
    .header-controls {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }
    
    /* Botón de cambio de tema */
    .theme-toggle {
        position: relative;
    }
    
    .theme-btn {
        background: transparent;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        width: 44px;
        height: 44px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all var(--transition-normal);
        color: var(--color-text);
        position: relative;
        overflow: hidden;
    }
    
    .theme-btn:hover {
        background: var(--color-primary-light);
        color: white;
        border-color: var(--color-primary);
        transform: rotate(15deg);
    }
    
    .theme-icon {
        width: 20px;
        height: 20px;
        transition: opacity var(--transition-normal), transform var(--transition-normal);
    }
    
    .sun-icon {
        color: var(--color-warning);
    }
    
    .moon-icon {
        color: var(--color-primary-light);
    }
    
    [data-theme="light"] .moon-icon {
        display: none;
    }
    
    [data-theme="dark"] .sun-icon {
        display: none;
    }
    
    /* Información del usuario */
    .user-info {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.5rem;
        border-radius: var(--radius-md);
        transition: background-color var(--transition-normal);
    }
    
    .user-info:hover {
        background: var(--color-border-light);
    }
    
    .user-avatar {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        color: white;
        font-size: 16px;
        flex-shrink: 0;
    }
    
    .user-details {
        display: flex;
        flex-direction: column;
    }
    
    .user-name {
        font-weight: 600;
        color: var(--color-text);
        font-size: 0.95rem;
    }
    
    .user-role {
        font-size: 0.8rem;
        color: var(--color-text-light);
    }
    
    /* ============ BARRA LATERAL ============ */
    .sidebar {
        width: 260px;
        background: var(--color-surface);
        border-right: 1px solid var(--color-border);
        position: fixed;
        top: 81px; /* Altura del header */
        left: 0;
        bottom: 0;
        z-index: 90;
        padding: 1.5rem;
        overflow-y: auto;
        transition: transform var(--transition-normal), width var(--transition-normal);
        animation: slideInLeft 0.5s ease-out;
    }
    
    @keyframes slideInLeft {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    .sidebar.collapsed {
        width: 80px;
    }
    
    .sidebar.collapsed .nav-text {
        display: none;
    }
    
    /* Navegación */
    .nav-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .nav-item {
        margin-bottom: 0.5rem;
    }
    
    .nav-link {
        display: flex;
        align-items: center;
        padding: 0.875rem 1rem;
        color: var(--color-text);
        text-decoration: none;
        border-radius: var(--radius-md);
        transition: all var(--transition-normal);
        font-weight: 500;
        position: relative;
        overflow: hidden;
    }
    
    .nav-link:hover {
        background: var(--color-border-light);
        color: var(--color-primary);
        transform: translateX(4px);
    }
    
    .sidebar.collapsed .nav-link:hover {
        transform: scale(1.05);
    }
    
    .nav-link.active {
        background: var(--color-primary);
        color: white;
        box-shadow: var(--shadow-md);
    }
    
    .nav-icon {
        font-size: 1.25rem;
        margin-right: 1rem;
        width: 24px;
        text-align: center;
        flex-shrink: 0;
    }
    
    .sidebar.collapsed .nav-icon {
        margin-right: 0;
        font-size: 1.35rem;
    }
    
    .nav-text {
        font-size: 0.95rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    /* Contenido principal */
    .main-content {
        margin-left: 260px;
        padding: 2rem;
        min-height: calc(100vh - 81px);
        transition: margin-left var(--transition-normal);
        max-width: 1400px;
        margin-right: auto;
        margin-left: auto;
        width: calc(100% - 260px);
    }
    
    .sidebar.collapsed ~ .main-content {
        margin-left: 80px;
        width: calc(100% - 80px);
    }
    
    /* ============ ENCABEZADO DE PÁGINA ============ */
    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 2rem;
        animation: fadeIn 0.6s ease-out;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }
    
    .page-title-section {
        display: flex;
        flex-direction: column;
    }
    
    .page-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--color-text);
        margin-bottom: 0.25rem;
    }
    
    .page-subtitle {
        font-size: 0.95rem;
        color: var(--color-text-light);
    }
    
    .page-actions {
        display: flex;
        gap: 1rem;
        align-items: center;
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
    
    /* ============ ESTADÍSTICAS PRINCIPALES ============ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
        animation: fadeIn 0.6s ease-out 0.2s both;
    }
    
    .stat-card {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        transition: all var(--transition-normal);
        position: relative;
        overflow: hidden;
        text-align: center;
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--color-primary), var(--color-secondary));
        opacity: 0.7;
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
        border-color: var(--color-primary-light);
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: var(--radius-lg);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        color: white;
        margin: 0 auto 1rem;
    }
    
    .stat-icon.primary { background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark)); }
    .stat-icon.success { background: linear-gradient(135deg, var(--color-success), #10b981); }
    .stat-icon.warning { background: linear-gradient(135deg, var(--color-warning), #d97706); }
    .stat-icon.info { background: linear-gradient(135deg, var(--color-info), #0ea5e9); }
    .stat-icon.danger { background: linear-gradient(135deg, var(--color-error), #dc2626); }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--color-text);
        line-height: 1;
        margin-bottom: 0.5rem;
    }
    
    .stat-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--color-text-light);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    /* ============ PANEL DE FILTROS ============ */
    .filter-panel {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-bottom: 2rem;
        animation: fadeIn 0.6s ease-out 0.3s both;
    }
    
    .filter-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--color-text);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .filter-form {
        display: flex;
        gap: 1rem;
        align-items: flex-end;
        flex-wrap: wrap;
    }
    
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        min-width: 180px;
        flex: 1;
    }
    
    .form-label {
        font-weight: 500;
        color: var(--color-text);
        font-size: 0.875rem;
    }
    
    .form-control {
        padding: 0.75rem 1rem;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        font-size: 0.95rem;
        background: var(--color-surface);
        color: var(--color-text);
        transition: all var(--transition-normal);
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px var(--color-primary-light);
    }
    
    /* ============ SECCIÓN DE CONTABILIDAD ============ */
    .accounting-section {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-bottom: 2rem;
        animation: fadeIn 0.6s ease-out 0.4s both;
    }
    
    .section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--color-border);
    }
    
    .section-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--color-text);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .section-title-icon {
        color: var(--color-primary);
    }
    
    /* Tablas de datos */
    .table-container {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-bottom: 2rem;
        animation: fadeIn 0.6s ease-out 0.5s both;
        overflow: hidden;
    }
    
    .table-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--color-border);
    }
    
    .table-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--color-text);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .table-title-icon {
        color: var(--color-primary);
    }
    
    .table-responsive {
        width: 100%;
        overflow-x: auto;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .data-table th {
        text-align: left;
        padding: 1rem;
        font-weight: 600;
        color: var(--color-text-light);
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid var(--color-border);
        background: var(--color-border-light);
    }
    
    .data-table td {
        padding: 1rem;
        border-bottom: 1px solid var(--color-border);
        color: var(--color-text);
        transition: background-color var(--transition-normal);
    }
    
    .data-table tbody tr:hover td {
        background: var(--color-border-light);
    }
    
    .data-table tbody tr:last-child td {
        border-bottom: none;
    }
    
    /* Badges para montos */
    .amount-badge {
        background: var(--color-border-light);
        padding: 0.375rem 0.75rem;
        border-radius: var(--radius-md);
        font-size: 0.875rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        border: 1px solid transparent;
    }
    
    .amount-badge.income {
        background: rgba(52, 211, 153, 0.1);
        color: #059669; /* Darker green for contrast */
        border-color: rgba(52, 211, 153, 0.2);
    }
    
    .amount-badge.expense {
        background: rgba(248, 113, 113, 0.1);
        color: #dc2626; /* Darker red for contrast */
        border-color: rgba(248, 113, 113, 0.2);
    }
    
    /* ============ BOTÓN TOGGLE SIDEBAR ============ */
    .sidebar-toggle {
        position: fixed;
        bottom: 2rem;
        left: 280px;
        width: 40px;
        height: 40px;
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 95;
        transition: all var(--transition-normal);
        box-shadow: var(--shadow-md);
        color: var(--color-text);
    }
    
    .sidebar-toggle:hover {
        background: var(--color-primary);
        color: white;
        border-color: var(--color-primary);
        transform: scale(1.1);
    }
    
    .sidebar.collapsed ~ .sidebar-toggle {
        left: 100px;
    }
    
    /* ============ MODAL DE EXPORTACIÓN ============ */
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
    
    /* ============ RESPONSIVE DESIGN ============ */
    @media (max-width: 1200px) {
        .main-content {
            padding: 1.5rem;
        }
        
        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }
    }
    
    @media (max-width: 992px) {
        .sidebar {
            transform: translateX(-100%);
            width: 280px;
        }
        
        .sidebar.show {
            transform: translateX(0);
        }
        
        .main-content {
            margin-left: 0;
            width: 100%;
        }
        
        .sidebar-toggle {
            display: none;
        }
        
        /* Botón móvil para mostrar sidebar */
        .mobile-sidebar-toggle {
            display: block;
            position: fixed;
            top: 1.5rem;
            left: 1.5rem;
            z-index: 101;
            width: 44px;
            height: 44px;
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            color: var(--color-text);
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: var(--shadow-md);
        }
    }
    
    @media (min-width: 993px) {
        .mobile-sidebar-toggle {
            display: none;
        }
    }
    
    @media (max-width: 768px) {
        .dashboard-header {
            padding: 1rem;
        }
        
        .header-content {
            flex-direction: column;
            gap: 1rem;
            align-items: flex-start;
        }
        
        .header-controls {
            width: 100%;
            justify-content: space-between;
        }
        
        .main-content {
            padding: 1rem;
        }
        
        .page-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .page-actions {
            width: 100%;
            justify-content: flex-start;
        }
        
        .filter-form {
            flex-direction: column;
            align-items: stretch;
        }
        
        .form-group {
            min-width: 100%;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .data-table {
            display: block;
            overflow-x: auto;
        }
    }
    
    @media (max-width: 480px) {
        .stat-card {
            padding: 1.25rem;
        }
        
        .filter-panel {
            padding: 1.25rem;
        }
        
        .accounting-section {
            padding: 1.25rem;
        }
        
        .table-container {
            padding: 1.25rem;
        }
        
        .action-btn {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }
    }
    
    /* ============ EFECTOS DE MÁRMOL ANIMADOS ============ */
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
    </style>
</head>
<body>
    <!-- Efecto de mármol animado -->
    <div class="marble-effect"></div>
    
    <!-- Botón móvil para mostrar/ocultar sidebar -->
    <button class="mobile-sidebar-toggle" id="mobileSidebarToggle" aria-label="Mostrar/ocultar menú">
        <i class="bi bi-list"></i>
    </button>
    
    <div class="dashboard-container">
        <!-- Header superior -->
        <header class="dashboard-header">
            <div class="header-content">
                <!-- Logo y marca -->
                <div class="brand-container">
                    <img src="../../assets/img/herrerasaenz.png" alt="Centro Médico Herrera Saenz" class="brand-logo">
                </div>
                
                <!-- Controles del header -->
                <div class="header-controls">
                    <!-- Control de tema -->
                    <div class="theme-toggle">
                        <button id="themeSwitch" class="theme-btn" aria-label="Cambiar tema claro/oscuro">
                            <i class="bi bi-sun theme-icon sun-icon"></i>
                            <i class="bi bi-moon theme-icon moon-icon"></i>
                        </button>
                    </div>
                    
                    <!-- Información del usuario -->
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                            <span class="user-role"><?php echo htmlspecialchars($user_specialty); ?></span>
                        </div>
                    </div>
                    
                    <!-- Botón de cerrar sesión -->
                    <a href="../auth/logout.php" class="action-btn logout-btn" title="Cerrar sesión">
                        <i class="bi bi-box-arrow-right"></i>
                        <span class="d-none d-md-inline">Salir</span>
                    </a>
                </div>
            </div>
        </header>
        
        <!-- Sidebar de navegación -->
        <nav class="sidebar" id="sidebar">
            <ul class="nav-menu">
                <?php $role = $user_type; ?>
                
                <!-- Dashboard (siempre visible) -->
                <li class="nav-item">
                    <a href="../dashboard/index.php" class="nav-link">
                        <i class="bi bi-grid-1x2-fill nav-icon"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                
                <!-- Pacientes (todos los roles) -->
                <?php if (in_array($role, ['admin', 'doc', 'user'])): ?>
                <li class="nav-item">
                    <a href="../patients/index.php" class="nav-link">
                        <i class="bi bi-person-vcard nav-icon"></i>
                        <span class="nav-text">Pacientes</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- Citas (admin y user) -->
                <?php if (in_array($role, ['admin', 'user'])): ?>
                <li class="nav-item">
                    <a href="../appointments/index.php" class="nav-link">
                        <i class="bi bi-calendar-heart nav-icon"></i>
                        <span class="nav-text">Citas</span>
                    </a>
                </li>
                
                <!-- Procedimientos menores -->
                <li class="nav-item">
                    <a href="../minor_procedures/index.php" class="nav-link">
                        <i class="bi bi-bandaid nav-icon"></i>
                        <span class="nav-text">Proc. Menores</span>
                    </a>
                </li>
                
                <!-- Exámenes -->
                <li class="nav-item">
                    <a href="../examinations/index.php" class="nav-link">
                        <i class="bi bi-clipboard2-pulse nav-icon"></i>
                        <span class="nav-text">Exámenes</span>
                    </a>
                </li>
                
                <!-- Dispensario -->
                <li class="nav-item">
                    <a href="../dispensary/index.php" class="nav-link">
                        <i class="bi bi-capsule nav-icon"></i>
                        <span class="nav-text">Dispensario</span>
                    </a>
                </li>
                
                <!-- Inventario -->
                <li class="nav-item">
                    <a href="../inventory/index.php" class="nav-link">
                        <i class="bi bi-box-seam nav-icon"></i>
                        <span class="nav-text">Inventario</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- Compras, Ventas, Reportes (solo admin) -->
                <?php if ($role === 'admin'): ?>
                <li class="nav-item">
                    <a href="../purchases/index.php" class="nav-link">
                        <i class="bi bi-cart-check nav-icon"></i>
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
                    <a href="../reports/index.php" class="nav-link active">
                        <i class="bi bi-graph-up-arrow nav-icon"></i>
                        <span class="nav-text">Reportes</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- Cobros (admin y user) -->
                <?php if (in_array($role, ['admin', 'user'])): ?>
                <li class="nav-item">
                    <a href="../billing/index.php" class="nav-link">
                        <i class="bi bi-credit-card-2-front nav-icon"></i>
                        <span class="nav-text">Cobros</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        
        <!-- Botón para colapsar/expandir sidebar (escritorio) -->
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Colapsar/expandir menú">
            <i class="bi bi-chevron-left" id="sidebarToggleIcon"></i>
        </button>
        
        <!-- Contenido principal -->
        <main class="main-content">
            <!-- Encabezado de página -->
            <div class="page-header">
                <div class="page-title-section">
                    <h1 class="page-title">Centro de Reportes</h1>
                    <p class="page-subtitle">Análisis detallado y métricas de la clínica</p>
                </div>
                <div class="page-actions">
                    <?php if ($role === 'admin'): ?>
                    <button type="button" class="action-btn" data-bs-toggle="modal" data-bs-target="#exportModal">
                        <i class="bi bi-download me-2"></i>
                        Exportar Jornada
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Panel de filtros -->
            <div class="filter-panel">
                <h3 class="filter-title">
                    <i class="bi bi-funnel"></i>
                    Filtros de Periodo
                </h3>
                <form method="GET" class="filter-form">
                    <div class="form-group">
                        <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                        <input type="date" 
                               class="form-control" 
                               id="fecha_inicio" 
                               name="fecha_inicio" 
                               value="<?php echo htmlspecialchars($fecha_inicio); ?>"
                               required>
                    </div>
                    <div class="form-group">
                        <label for="fecha_fin" class="form-label">Fecha Fin</label>
                        <input type="date" 
                               class="form-control" 
                               id="fecha_fin" 
                               name="fecha_fin" 
                               value="<?php echo htmlspecialchars($fecha_fin); ?>"
                               required>
                    </div>
                    <div class="form-group" style="min-width: auto;">
                        <button type="submit" class="action-btn" style="height: fit-content;">
                            <i class="bi bi-filter me-2"></i>
                            Aplicar Filtros
                        </button>
                    </div>
                </form>
                <div class="mt-3 text-sm text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    El periodo considera jornadas de <strong>08:00 AM</strong> a <strong>05:00 PM</strong> (jornada diurna) y de <strong>05:00 PM</strong> a <strong>08:00 AM</strong> del día siguiente (jornada nocturna).
                </div>
            </div>
            
            <!-- Estadísticas principales -->
            <div class="stats-grid">
                <!-- Pacientes registrados -->
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_pacientes; ?></div>
                    <div class="stat-label">Pacientes Registrados</div>
                </div>
                
                <!-- Citas en período -->
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="bi bi-calendar-event"></i>
                    </div>
                    <div class="stat-value"><?php echo $citas_count; ?></div>
                    <div class="stat-label">Citas en Periodo</div>
                </div>
                
                <!-- Exámenes realizados -->
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="bi bi-clipboard2-pulse"></i>
                    </div>
                    <div class="stat-value"><?php echo $examenes_count; ?></div>
                    <div class="stat-label">Exámenes Realizados</div>
                </div>
                
                <!-- Medicamentos en stock -->
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="bi bi-capsule"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_medicamentos; ?></div>
                    <div class="stat-label">Medicamentos en Stock</div>
                </div>
            </div>
            
            <!-- SECCIÓN BIG DATA - ANALÍTICA VISUAL (Nueva) -->
            <div class="table-container mb-4">
                <div class="table-header">
                    <h4 class="table-title">
                        <i class="bi bi-bar-chart-line table-title-icon text-primary"></i>
                        Big Data Analytics - Inteligencia de Negocio
                    </h4>
                </div>
                
                <div class="row g-4 mb-4">
                    <!-- Gráfico de Tendencia -->
                    <div class="col-lg-8">
                        <div class="card border-0 bg-transparent shadow-none">
                            <div class="card-body p-0">
                                <h5 class="card-title text-muted mb-3">Tendencia de Ingresos (Últimos 30 días)</h5>
                                <div style="height: 300px; position: relative;">
                                    <canvas id="salesTrendChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Gráfico de Distribución -->
                    <div class="col-lg-4">
                        <div class="card border-0 bg-transparent shadow-none">
                            <div class="card-body p-0">
                                <h5 class="card-title text-muted mb-3">Distribución de Ingresos</h5>
                                <div style="height: 300px; position: relative;">
                                    <canvas id="revenueDistChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row g-4 mt-2">
                    <!-- Top Medicamentos -->
                    <div class="col-md-6">
                        <h5 class="card-title text-muted mb-3">Medicamentos más vendidos</h5>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Medicamento</th>
                                        <th class="text-end">Cantidad</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_meds_data as $med): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($med['nombre_med']); ?></td>
                                        <td class="text-end font-weight-bold"><?php echo $med['total_vendido']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($top_meds_data)): ?>
                                    <tr><td colspan="2" class="text-center py-3">Sin datos en el periodo</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Resumen Quick Insights -->
                    <div class="col-md-6">
                        <h5 class="card-title text-muted mb-3">Insights de Rendimiento</h5>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="p-3 border rounded bg-light">
                                    <small class="text-muted d-block text-truncate">Margen Bruto Promedio</small>
                                    <span class="h4 mb-0"><?php echo $total_gross_revenue > 0 ? number_format(($total_gross_profit / $total_gross_revenue) * 100, 1) : '0'; ?>%</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 border rounded bg-light">
                                    <small class="text-muted d-block text-truncate">Costo Méd. Vendidos</small>
                                    <span class="h4 mb-0 text-danger">Q<?php echo number_format($sales_cost, 2); ?></span>
                                </div>
                            </div>
                            <div class="col-12 mt-2">
                                <div class="p-3 border rounded bg-light">
                                    <small class="text-muted d-block">Ganancia Estimada en Ventas</small>
                                    <span class="h4 mb-0 text-success">Q<?php echo number_format($actual_sales_margin, 2); ?></span>
                                    <p class="mb-0 text-muted small mt-1">Comparando costo de compra vs precio de venta</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección de contabilidad -->
            <div class="accounting-section">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-cash-coin section-title-icon"></i>
                        Contabilidad Detallada
                    </h3>
                    <span class="amount-badge <?php echo $total_gross_profit >= 0 ? 'income' : 'expense'; ?>">
                        <i class="bi <?php echo $total_gross_profit >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right'; ?>"></i>
                        Q<?php echo number_format($total_gross_profit, 2); ?>
                    </span>
                </div>
                
                <div class="row g-4">
                    <!-- Ingresos -->
                    <div class="col-md-6">
                        <div class="table-container">
                            <div class="table-header">
                                <h4 class="table-title">
                                    <i class="bi bi-arrow-down-right table-title-icon text-success"></i>
                                    Ingresos Totales
                                </h4>
                                <span class="amount-badge income">
                                    Q<?php echo number_format($total_gross_revenue, 2); ?>
                                </span>
                            </div>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Concepto</th>
                                            <th class="text-end">Monto</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Ventas de Medicamentos</td>
                                            <td class="text-end">
                                                <span class="amount-badge income">
                                                    Q<?php echo number_format($total_sales_meds, 2); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Cobros de Consultas</td>
                                            <td class="text-end">
                                                <span class="amount-badge income">
                                                    Q<?php echo number_format($total_billings, 2); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Procedimientos Menores</td>
                                            <td class="text-end">
                                                <span class="amount-badge income">
                                                    Q<?php echo number_format($total_procedures, 2); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Exámenes Realizados</td>
                                            <td class="text-end">
                                                <span class="amount-badge income">
                                                    Q<?php echo number_format($total_exams_revenue, 2); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Egresos -->
                    <div class="col-md-6">
                        <div class="table-container">
                            <div class="table-header">
                                <h4 class="table-title">
                                    <i class="bi bi-arrow-up-right table-title-icon text-danger"></i>
                                    Egresos Totales
                                </h4>
                                <span class="amount-badge expense">
                                    Q<?php echo number_format($total_purchases_meds, 2); ?>
                                </span>
                            </div>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Concepto</th>
                                            <th class="text-end">Monto</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Compras de Medicamentos</td>
                                            <td class="text-end">
                                                <span class="amount-badge expense">
                                                    Q<?php echo number_format($total_purchases_meds, 2); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" class="text-muted text-center py-3">
                                                <small>Otros gastos no registrados en el sistema</small>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Resumen de desempeño -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="table-container">
                            <div class="table-header">
                                <h4 class="table-title">
                                    <i class="bi bi-graph-up-arrow table-title-icon text-primary"></i>
                                    Resumen de Desempeño
                                </h4>
                            </div>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Indicador</th>
                                            <th class="text-end">Valor</th>
                                            <th class="text-end">Porcentaje</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Ingresos Brutos</td>
                                            <td class="text-end">
                                                <span class="amount-badge income">
                                                    Q<?php echo number_format($total_gross_revenue, 2); ?>
                                                </span>
                                            </td>
                                            <td class="text-end text-muted">100%</td>
                                        </tr>
                                        <tr>
                                            <td>Egreso Real (Inversión compras)</td>
                                            <td class="text-end">
                                                <span class="amount-badge expense">
                                                    Q<?php echo number_format($total_purchases_meds, 2); ?>
                                                </span>
                                            </td>
                                            <td class="text-end text-muted">
                                                -
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Utilidad Bruta Operativa</strong></td>
                                            <td class="text-end">
                                                <span class="amount-badge <?php echo $total_gross_profit >= 0 ? 'income' : 'expense'; ?>">
                                                    <strong>Q<?php echo number_format($total_gross_profit, 2); ?></strong>
                                                </span>
                                            </td>
                                            <td class="text-end text-muted">
                                                <?php echo $total_gross_revenue > 0 ? number_format(($total_gross_profit / $total_gross_revenue) * 100, 1) : '0'; ?>%
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Flujo de Caja Neto (Periodo)</td>
                                            <td class="text-end">
                                                <span class="amount-badge <?php echo $net_cash_flow >= 0 ? 'income' : 'expense'; ?>">
                                                    Q<?php echo number_format($net_cash_flow, 2); ?>
                                                </span>
                                            </td>
                                            <td class="text-end text-muted">
                                                <small>Ingresos - Compras</small>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sección de datos detallados -->
            <div class="row g-4">
                <!-- Procedimientos menores -->
                <div class="col-lg-6">
                    <div class="table-container">
                        <div class="table-header">
                            <h4 class="table-title">
                                <i class="bi bi-bandaid table-title-icon"></i>
                                Procedimientos Menores Recientes
                            </h4>
                            <span class="amount-badge income">
                                Total: Q<?php echo number_format($total_procedures, 2); ?>
                            </span>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Paciente</th>
                                        <th class="text-end">Cobro</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $conn->prepare("
                                        SELECT fecha_procedimiento, nombre_paciente, cobro 
                                        FROM procedimientos_menores 
                                        WHERE fecha_procedimiento BETWEEN ? AND ? 
                                        ORDER BY fecha_procedimiento DESC 
                                        LIMIT 5
                                    ");
                                    $stmt->execute([$start_datetime, $end_datetime]);
                                    $hasProc = false;
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $hasProc = true;
                                        echo "<tr>
                                            <td>" . date('d/m/y', strtotime($row['fecha_procedimiento'])) . "</td>
                                            <td>" . htmlspecialchars($row['nombre_paciente']) . "</td>
                                            <td class='text-end'>
                                                <span class='amount-badge income'>
                                                    Q" . number_format($row['cobro'], 2) . "
                                                </span>
                                            </td>
                                        </tr>";
                                    }
                                    if (!$hasProc) {
                                        echo "<tr><td colspan='3' class='text-center text-muted py-4'>No hay procedimientos en este período</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Exámenes realizados -->
                <div class="col-lg-6">
                    <div class="table-container">
                        <div class="table-header">
                            <h4 class="table-title">
                                <i class="bi bi-clipboard2-pulse table-title-icon"></i>
                                Exámenes Recientes
                            </h4>
                            <span class="amount-badge income">
                                Total: Q<?php echo number_format($total_exams_revenue, 2); ?>
                            </span>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Paciente</th>
                                        <th class="text-end">Cobro</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $conn->prepare("
                                        SELECT fecha_examen, nombre_paciente, cobro 
                                        FROM examenes_realizados 
                                        WHERE fecha_examen BETWEEN ? AND ? 
                                        ORDER BY fecha_examen DESC 
                                        LIMIT 5
                                    ");
                                    $stmt->execute([$start_datetime, $end_datetime]);
                                    $hasExam = false;
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $hasExam = true;
                                        echo "<tr>
                                            <td>" . date('d/m/y', strtotime($row['fecha_examen'])) . "</td>
                                            <td>" . htmlspecialchars($row['nombre_paciente']) . "</td>
                                            <td class='text-end'>
                                                <span class='amount-badge income'>
                                                    Q" . number_format($row['cobro'], 2) . "
                                                </span>
                                            </td>
                                        </tr>";
                                    }
                                    if (!$hasExam) {
                                        echo "<tr><td colspan='3' class='text-center text-muted py-4'>No hay exámenes en este período</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Modal para exportar jornada -->
    <div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-download text-primary me-2"></i>
                        Exportar Reporte de Jornada
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-4">
                        <label class="form-label">Seleccionar Fecha de Jornada</label>
                        <input type="date" 
                               class="form-control" 
                               id="exportDate" 
                               value="<?php echo date('Y-m-d'); ?>">
                        <div class="form-text text-muted mt-2">
                            <i class="bi bi-info-circle me-1"></i>
                            La jornada comprende de <strong>08:00 AM</strong> a <strong>05:00 PM</strong> (jornada diurna) o de <strong>05:00 PM</strong> a <strong>08:00 AM</strong> del día siguiente (jornada nocturna).
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Formato de Exportación</label>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-primary text-start" onclick="exportReport('html')">
                                <i class="bi bi-eye me-2"></i>
                                Vista Previa (HTML)
                            </button>
                            <button type="button" class="btn btn-outline-success text-start" onclick="exportReport('csv')">
                                <i class="bi bi-file-earmark-spreadsheet me-2"></i>
                                Descargar CSV
                            </button>
                            <button type="button" class="btn btn-outline-success text-start" onclick="exportReport('excel')">
                                <i class="bi bi-file-earmark-excel me-2"></i>
                                Descargar Excel
                            </button>
                            <button type="button" class="btn btn-outline-primary text-start" onclick="exportReport('word')">
                                <i class="bi bi-file-earmark-word me-2"></i>
                                Descargar Word
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="action-btn secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Módulo de Reportes - Centro Médico Herrera Saenz
    // JavaScript para funcionalidades del módulo de reportes
    
    // Esperar a que el DOM esté completamente cargado
    document.addEventListener('DOMContentLoaded', function() {
        // ============ REFERENCIAS A ELEMENTOS ============
        const themeSwitch = document.getElementById('themeSwitch');
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarToggleIcon = document.getElementById('sidebarToggleIcon');
        const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
        
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
        
        // Cambiar entre modo claro y oscuro
        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            // Aplicar nuevo tema
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('dashboard-theme', newTheme);
            
            // Animación sutil en el botón
            themeSwitch.style.transform = 'rotate(180deg)';
            setTimeout(() => {
                themeSwitch.style.transform = 'rotate(0)';
            }, 300);
        }
        
        // ============ FUNCIONALIDAD DEL SIDEBAR ============
        
        // Restaurar estado del sidebar desde localStorage
        function initializeSidebar() {
            const sidebarCollapsed = localStorage.getItem('sidebar-collapsed');
            
            if (sidebarCollapsed === 'true') {
                sidebar.classList.add('collapsed');
                sidebarToggleIcon.classList.remove('bi-chevron-left');
                sidebarToggleIcon.classList.add('bi-chevron-right');
            }
        }
        
        // Colapsar/expandir sidebar
        function toggleSidebar() {
            const isCollapsed = sidebar.classList.toggle('collapsed');
            
            // Cambiar icono
            if (isCollapsed) {
                sidebarToggleIcon.classList.remove('bi-chevron-left');
                sidebarToggleIcon.classList.add('bi-chevron-right');
            } else {
                sidebarToggleIcon.classList.remove('bi-chevron-right');
                sidebarToggleIcon.classList.add('bi-chevron-left');
            }
            
            // Guardar estado
            localStorage.setItem('sidebar-collapsed', isCollapsed);
        }
        
        // Mostrar/ocultar sidebar en móvil
        function toggleMobileSidebar() {
            sidebar.classList.toggle('show');
            
            // Cerrar sidebar al hacer clic fuera en móvil
            if (sidebar.classList.contains('show')) {
                document.addEventListener('click', closeSidebarOnClickOutside);
            } else {
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
        }
        
        // Cerrar sidebar al hacer clic fuera (solo móvil)
        function closeSidebarOnClickOutside(event) {
            if (!sidebar.contains(event.target) && 
                !mobileSidebarToggle.contains(event.target) && 
                sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
        }
        
        // ============ FUNCIONALIDAD DE EXPORTACIÓN ============
        
        // Exportar reporte en diferentes formatos
        window.exportReport = function(format) {
            const date = document.getElementById('exportDate').value;
            const url = `export_jornada.php?date=${date}&format=${format}`;
            
            if (format === 'html') {
                window.open(url, '_blank');
            } else {
                window.location.href = url;
            }
            
            // Cerrar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('exportModal'));
            modal.hide();
        }
        
        // ============ INICIALIZACIÓN ============
        
        // Inicializar componentes
        initializeTheme();
        initializeSidebar();
        
        // ============ EVENT LISTENERS ============
        
        // Tema
        themeSwitch.addEventListener('click', toggleTheme);
        
        // Sidebar (escritorio)
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', toggleSidebar);
        }
        
        // Sidebar (móvil)
        if (mobileSidebarToggle) {
            mobileSidebarToggle.addEventListener('click', toggleMobileSidebar);
        }
        
        // Cerrar sidebar al cambiar tamaño de ventana (responsive)
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992 && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
        });
        
        // ============ GRÁFICOS BIG DATA (Chart.js) ============
        
        const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
        const textColor = isDarkMode ? '#94a3b8' : '#64748b';
        const gridColor = isDarkMode ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';

        // 1. Gráfico de Tendencia de Ventas
        const trendCtx = document.getElementById('salesTrendChart').getContext('2d');
        const salesTrendData = <?php echo json_encode($sales_trend_data); ?>;
        
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: salesTrendData.map(d => d.fecha),
                datasets: [{
                    label: 'Ventas Diarias',
                    data: salesTrendData.map(d => d.total),
                    borderColor: '#7c90db',
                    backgroundColor: 'rgba(124, 144, 219, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#7c90db'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: textColor, font: { size: 10 } }
                    },
                    y: {
                        grid: { color: gridColor },
                        ticks: { color: textColor, font: { size: 10 }, callback: v => 'Q' + v }
                    }
                }
            }
        });

        // 2. Gráfico de Distribución de Ingresos
        const distCtx = document.getElementById('revenueDistChart').getContext('2d');
        const categoryData = <?php echo json_encode($category_data); ?>;
        
        new Chart(distCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(categoryData),
                datasets: [{
                    data: Object.values(categoryData),
                    backgroundColor: ['#7c90db', '#8dd7bf', '#f8b195', '#38bdf8'],
                    borderWidth: 0,
                    hoverOffset: 15
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: textColor, padding: 20, font: { size: 11 } }
                    }
                },
                cutout: '70%'
            }
        });
        
        // ============ CONSOLA DE DESARROLLO ============
        
        console.log('Módulo de Reportes - Centro Médico Herrera Saenz');
        console.log('Versión: 3.0 - Diseño con Efecto Mármol y Modo Noche');
        console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
        console.log('Rol: <?php echo htmlspecialchars($user_type); ?>');
        console.log('Periodo: <?php echo $fecha_inicio; ?> - <?php echo $fecha_fin; ?>');
    });
    </script>
</body>
</html>