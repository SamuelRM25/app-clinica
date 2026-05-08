<?php
// index.php - Módulo de Reportes - Centro Médico Herrera Saenz
// Versión 4.0 - Integrado al Diseño del Dashboard Principal
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
    $user_specialty = $_SESSION['especialidad'] ?? 'Profesional Médico';

    // Obtener fechas para filtros (predeterminado: mes actual)
    // Obtener fecha para filtro (predeterminado: hoy) - Filtro por Día (Turno)
    $fecha_filtro = $_GET['fecha_filtro'] ?? date('Y-m-d');

    // Ajustar para rangos de jornada (08:00 AM del día seleccionado a 08:00 AM del día siguiente)
    $start_datetime = $fecha_filtro . ' 08:00:00';
    $end_datetime = date('Y-m-d', strtotime($fecha_filtro . ' +1 day')) . ' 07:59:59';

    // Variables para compatibilidad con lógica existente que use fecha_inicio/fin
    $fecha_inicio = $start_datetime;
    $fecha_fin = $end_datetime;

    // ============ CONSULTAS ESTADÍSTICAS PARA EL DASHBOARD ============

    // Configurar filtros según tipo de usuario
    $is_doctor = $user_type === 'doc';
    $doctor_filter = $is_doctor ? " AND id_doctor = ?" : "";
    $today = date('Y-m-d');

    // 1. Citas de hoy
    $params = $is_doctor ? [$today, $user_id] : [$today];
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM citas WHERE fecha_cita = ?" . $doctor_filter);
    $stmt->execute($params);
    $today_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 2. Total de citas en el sistema
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM citas");
    $stmt->execute();
    $total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 3. Hospitalizaciones Activas
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM encamamientos WHERE estado = 'Activo'");
    $stmt->execute();
    $active_hospitalizations = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 4. Compras pendientes
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventario WHERE estado = 'Pendiente'");
    $stmt->execute();
    $pending_purchases = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // ============ CÁLCULO DE MÉTRICAS DE REPORTES ============

    // 1. Ventas de medicamentos
    $stmt_sales = $conn->prepare("SELECT SUM(total) as total_sales FROM ventas WHERE fecha_venta BETWEEN ? AND ?");
    $stmt_sales->execute([$start_datetime, $end_datetime]);
    $total_sales_meds = $stmt_sales->fetch(PDO::FETCH_ASSOC)['total_sales'] ?? 0;

    // 2. Compras de medicamentos
    $stmt_purchases = $conn->prepare("SELECT SUM(total_amount) as total_purchases FROM purchase_headers WHERE purchase_date BETWEEN ? AND ?");
    $stmt_purchases->execute([$fecha_inicio, $fecha_fin]);
    $total_purchases_meds = $stmt_purchases->fetch(PDO::FETCH_ASSOC)['total_purchases'] ?? 0;

    // 3. Cálculo de Ganancia Real
    $stmt_actual_profit = $conn->prepare("
        SELECT 
            SUM(dv.cantidad_vendida * dv.precio_unitario) as revenue,
            SUM(dv.cantidad_vendida * COALESCE(pi.unit_cost, 0)) as cost
        FROM detalle_ventas dv
        JOIN ventas v ON dv.id_venta = v.id_venta
        JOIN inventario i ON dv.id_inventario = i.id_inventario
        LEFT JOIN purchase_items pi ON i.id_purchase_item = pi.id
        WHERE v.fecha_venta BETWEEN ? AND ?
        AND v.tipo_pago != 'Traslado'
        AND dv.precio_unitario > 0
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

    // 6. Cobros de consultas
    $stmt_billings = $conn->prepare("SELECT SUM(cantidad_consulta) FROM cobros WHERE fecha_consulta BETWEEN ? AND ?");
    $stmt_billings->execute([$fecha_inicio, $fecha_fin]);
    $total_billings = $stmt_billings->fetchColumn() ?: 0;

    // 6.b Hospitalizaciones (Cuentas de encamamientos dados de alta)
    $stmt_hosp = $conn->prepare("
        SELECT SUM(total_general) 
        FROM cuenta_hospitalaria ch 
        JOIN encamamientos e ON ch.id_encamamiento = e.id_encamamiento 
        WHERE e.fecha_alta BETWEEN ? AND ?
    ");
    $stmt_hosp->execute([$start_datetime, $end_datetime]);
    $total_hospitalization = $stmt_hosp->fetchColumn() ?: 0;

    // 7. Ingresos brutos totales
    $total_gross_revenue = $total_sales_meds + $total_procedures + $total_exams_revenue + $total_billings + $total_hospitalization;

    // 8. Utilidad Bruta
    $total_gross_profit = $total_gross_revenue - $sales_cost;

    // 9. Desempeño neto
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
        'Ventas' => (float) $total_sales_meds,
        'Consultas' => (float) $total_billings,
        'Procedimientos' => (float) $total_procedures,
        'Exámenes' => (float) $total_exams_revenue,
        'Hospitalización' => (float) $total_hospitalization
    ];

    // C. Top 5 Medicamentos más vendidos
    $stmt_top_meds = $conn->prepare("
        SELECT i.nom_medicamento as nombre_med, SUM(dv.cantidad_vendida) as total_vendido
        FROM detalle_ventas dv
        JOIN inventario i ON dv.id_inventario = i.id_inventario
        JOIN ventas v ON dv.id_venta = v.id_venta
        WHERE v.fecha_venta BETWEEN ? AND ?
        AND v.tipo_pago != 'Traslado'
        AND dv.precio_unitario > 0
        GROUP BY i.id_inventario
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

    // Título de la página
    $page_title = "Reportes - Centro Médico Herrera Saenz";

    // ============ REPORTE DE RENTABILIDAD DE FARMACIA ============

    // Obtener fechas para filtro de rentabilidad (predeterminado: mes actual)
    $profit_start = $_GET['profit_start'] ?? date('Y-m-01');
    $profit_end = $_GET['profit_end'] ?? date('Y-m-d');

    // Ajustar final del día para la fecha fin
    $profit_end_datetime = $profit_end . ' 23:59:59';
    $profit_start_datetime = $profit_start . ' 00:00:00';

    $stmt_profitability = $conn->prepare("
        SELECT 
            i.nom_medicamento,
            i.codigo_barras,
            SUM(dv.cantidad_vendida) as cantidad_total,
            SUM(dv.cantidad_vendida * dv.precio_unitario) as total_venta,
            SUM(dv.cantidad_vendida * COALESCE(pi.unit_cost, 0)) as total_costo
        FROM detalle_ventas dv
        JOIN ventas v ON dv.id_venta = v.id_venta
        JOIN inventario i ON dv.id_inventario = i.id_inventario
        LEFT JOIN purchase_items pi ON i.id_purchase_item = pi.id
        WHERE v.fecha_venta BETWEEN ? AND ?
        AND v.tipo_pago != 'Traslado'
        AND dv.precio_unitario > 0
        AND COALESCE(pi.unit_cost, 0) > 0
        GROUP BY i.id_inventario, i.nom_medicamento, i.codigo_barras
        ORDER BY total_venta DESC
    ");

    $stmt_profitability->execute([$profit_start_datetime, $profit_end_datetime]);
    $profitability_data = $stmt_profitability->fetchAll(PDO::FETCH_ASSOC);

    // Calcular totales generales del reporte
    $total_profit_revenue = 0;
    $total_profit_cost = 0;

    foreach ($profitability_data as $row) {
        $total_profit_revenue += $row['total_venta'];
        $total_profit_cost += $row['total_costo'];
    }

    $total_profit_amount = $total_profit_revenue - $total_profit_cost;
    $total_profit_margin = $total_profit_revenue > 0 ? ($total_profit_amount / $total_profit_revenue) * 100 : 0;

    // ============ REPORTE DETALLADO DE LABORATORIOS ============

    $labs_start_month = $_GET['labs_start'] ?? null;
    $labs_end_month = $_GET['labs_end'] ?? null;

    $labs_where = "";
    $labs_params = [];
    if ($labs_start_month && $labs_end_month) {
        $labs_where = "AND ol.fecha_orden BETWEEN ? AND ?";
        $labs_params = [$labs_start_month . '-01 00:00:00', date('Y-m-t 23:59:59', strtotime($labs_end_month . '-01'))];
    } elseif ($labs_start_month) {
        $labs_where = "AND ol.fecha_orden >= ?";
        $labs_params = [$labs_start_month . '-01 00:00:00'];
    } elseif ($labs_end_month) {
        $labs_where = "AND ol.fecha_orden <= ?";
        $labs_params = [date('Y-m-t 23:59:59', strtotime($labs_end_month . '-01'))];
    }

    $stmt_labs_detail = $conn->prepare("
        SELECT 
            p.nombre as paciente_nombre,
            p.apellido as paciente_apellido,
            cp.nombre_prueba,
            DATE(ol.fecha_orden) as fecha,
            TIME(ol.fecha_orden) as hora,
            cp.precio
        FROM ordenes_laboratorio ol
        JOIN orden_pruebas op ON ol.id_orden = op.id_orden
        JOIN catalogo_pruebas cp ON op.id_prueba = cp.id_prueba
        JOIN pacientes p ON ol.id_paciente = p.id_paciente
        WHERE op.estado != 'Devuelto'
        $labs_where
        ORDER BY ol.fecha_orden DESC
    ");
    $stmt_labs_detail->execute($labs_params);
    $labs_detail_data_raw = $stmt_labs_detail->fetchAll(PDO::FETCH_ASSOC);

    $total_labs_report = 0;
    $grouped_labs = [];
    foreach ($labs_detail_data_raw as $lab) {
        $total_labs_report += $lab['precio'];

        $timestamp = strtotime($lab['fecha']);
        // Formatear mes en español
        $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $mes_num = (int)date('m', $timestamp) - 1;
        $anio = date('Y', $timestamp);
        $mes_nombre = $meses[$mes_num] . ' ' . $anio;
        
        $dia_str = date('d/m/Y', $timestamp);
        $paciente_nombre = $lab['paciente_nombre'] . ' ' . $lab['paciente_apellido'];
        
        if (!isset($grouped_labs[$mes_nombre])) {
            $grouped_labs[$mes_nombre] = ['total' => 0, 'count' => 0, 'dias' => []];
        }
        $grouped_labs[$mes_nombre]['total'] += $lab['precio'];
        $grouped_labs[$mes_nombre]['count'] += 1;
        
        if (!isset($grouped_labs[$mes_nombre]['dias'][$dia_str])) {
            $grouped_labs[$mes_nombre]['dias'][$dia_str] = ['total' => 0, 'count' => 0, 'pacientes' => []];
        }
        $grouped_labs[$mes_nombre]['dias'][$dia_str]['total'] += $lab['precio'];
        $grouped_labs[$mes_nombre]['dias'][$dia_str]['count'] += 1;
        
        if (!isset($grouped_labs[$mes_nombre]['dias'][$dia_str]['pacientes'][$paciente_nombre])) {
            $grouped_labs[$mes_nombre]['dias'][$dia_str]['pacientes'][$paciente_nombre] = ['total' => 0, 'count' => 0, 'labs' => []];
        }
        $grouped_labs[$mes_nombre]['dias'][$dia_str]['pacientes'][$paciente_nombre]['total'] += $lab['precio'];
        $grouped_labs[$mes_nombre]['dias'][$dia_str]['pacientes'][$paciente_nombre]['count'] += 1;
        
        $grouped_labs[$mes_nombre]['dias'][$dia_str]['pacientes'][$paciente_nombre]['labs'][] = $lab;
    }


    // ============ REPORTE DE TRASLADOS (Acceso Restringido) ============
    $can_view_transfers = in_array($user_id, [1, 9, 10]);
    $transfers_data = [];
    $total_transfers_amount = 0;

    if ($can_view_transfers) {
        $stmt_transfers = $conn->prepare("
            SELECT 
                i.nom_medicamento,
                dv.cantidad_vendida,
                v.fecha_venta,
                v.nombre_cliente as destino,
                v.total as valor_traslado,
                CONCAT(u.nombre, ' ', u.apellido) as realizado_por
            FROM ventas v
            JOIN detalle_ventas dv ON v.id_venta = dv.id_venta
            JOIN inventario i ON dv.id_inventario = i.id_inventario
            LEFT JOIN usuarios u ON v.id_usuario = u.idUsuario
            WHERE v.tipo_pago = 'Traslado'
            AND v.fecha_venta BETWEEN ? AND ?
            ORDER BY v.fecha_venta DESC
        ");
        $stmt_transfers->execute([$profit_start_datetime, $profit_end_datetime]);
        $transfers_data = $stmt_transfers->fetchAll(PDO::FETCH_ASSOC);

        foreach ($transfers_data as $transfer) {
            $total_transfers_amount += $transfer['valor_traslado'];
        }
    }


} catch (PDOException $e) {
    // Error específico de base de datos
    error_log("Error DB en módulo de reportes: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    // Mostrar mensaje amigable al usuario
    $error_message = "Error al conectar con la base de datos. Por favor, contacte al administrador.";
    if ($_SESSION['tipoUsuario'] === 'admin') {
        $error_message .= "<br><small>Detalles técnicos: " . htmlspecialchars($e->getMessage()) . "</small>";
    }
    die($error_message);
} catch (Exception $e) {
    // Otros errores generales
    error_log("Error general en módulo de reportes: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    die("Error al cargar los reportes. Por favor, contacte al administrador.");
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Módulo de Reportes - Centro Médico Herrera Saenz - Sistema de gestión médica">
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

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
            background-size: cover;
            opacity: 0.3;
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
       LAYOUT PRINCIPAL
       ========================================================================== */
        .dashboard-container {
            display: flex;
            flex-direction: column;
            /* Apilar Header y Main verticalmente */
            min-height: 100vh;
            position: relative;
            width: 100%;
            /* Asegurar que no se desborde */
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
            transition: all var(--transition-base);
            min-height: 100vh;
            background-color: transparent;
            width: 100%;
        }


        /* Encabezado de página */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: var(--space-xl);
            animation: fadeInUp 0.6s ease-out forwards;
        }

        .page-title-section {
            display: flex;
            flex-direction: column;
        }

        .page-title {
            font-size: var(--font-size-2xl);
            font-weight: 700;
            color: var(--color-text);
            margin-bottom: var(--space-xs);
        }

        .page-subtitle {
            font-size: var(--font-size-sm);
            color: var(--color-text-secondary);
        }

        .page-actions {
            display: flex;
            gap: var(--space-sm);
            align-items: center;
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

        /* Panel de filtros */
        .filter-panel {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            margin-bottom: var(--space-lg);
            animation: fadeInUp 0.6s ease-out 0.1s both;
        }

        .filter-title {
            font-size: var(--font-size-lg);
            font-weight: 600;
            color: var(--color-text);
            margin-bottom: var(--space-md);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .filter-form {
            display: flex;
            gap: var(--space-md);
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: var(--space-xs);
            min-width: 180px;
            flex: 1;
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
            box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.1);
        }

        /* Tarjetas de estadísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: var(--space-lg);
            margin-bottom: var(--space-xl);
            animation: fadeInUp 0.6s ease-out 0.2s both;
        }

        .stat-card {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            transition: all var(--transition-base);
            position: relative;
            overflow: hidden;
            text-align: center;
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

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: white;
            margin: 0 auto var(--space-md);
        }

        .stat-icon.primary {
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
        }

        .stat-icon.success {
            background: linear-gradient(135deg, var(--color-success), #10b981);
        }

        .stat-icon.warning {
            background: linear-gradient(135deg, var(--color-warning), #d97706);
        }

        .stat-icon.info {
            background: linear-gradient(135deg, var(--color-info), #0ea5e9);
        }

        .stat-icon.danger {
            background: linear-gradient(135deg, var(--color-danger), #dc2626);
        }

        .stat-value {
            font-size: var(--font-size-3xl);
            font-weight: 700;
            color: var(--color-text);
            line-height: 1;
            margin-bottom: var(--space-xs);
        }

        .stat-label {
            font-size: var(--font-size-sm);
            font-weight: 500;
            color: var(--color-text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Secciones de contenido */
        .content-section {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            margin-bottom: var(--space-lg);
            animation: fadeInUp 0.6s ease-out 0.3s both;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-lg);
            padding-bottom: var(--space-md);
            border-bottom: 1px solid var(--color-border);
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

        /* Tablas de datos */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .data-table th {
            padding: var(--space-md);
            text-align: left;
            font-weight: 600;
            color: var(--color-text);
            border-bottom: 2px solid var(--color-border);
            white-space: nowrap;
            background: var(--color-surface);
        }

        .data-table td {
            padding: var(--space-md);
            border-bottom: 1px solid var(--color-border);
            vertical-align: middle;
        }

        .data-table tbody tr {
            transition: all var(--transition-base);
        }

        .data-table tbody tr:hover {
            background: var(--color-surface);
            transform: translateX(4px);
        }

        /* Badges para montos */
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

        /* Gráficos */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        /* ==========================================================================
       RESPONSIVE DESIGN
       ========================================================================== */

        /* Pantallas grandes (TV, monitores 4K) */
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

        /* Tablets y pantallas medianas */
        @media (max-width: 991px) {

            .dashboard-container {
                width: 100%;
            }

            .main-content {
                padding: var(--space-md);
            }

            .header-content {
                padding: var(--space-md);
            }

            .mobile-toggle {
                display: none;
            }

            .header-content {
                padding: var(--space-md);
            }

            .page-header {
                flex-direction: column;
                align-items: stretch;
                gap: var(--space-md);
            }

            .page-actions {
                width: 100%;
                justify-content: center;
            }

            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }

            .form-group {
                min-width: 100%;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--space-md);
            }
        }

        /* Móviles */
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

            .stat-card {
                padding: var(--space-md);
            }

            .stat-value {
                font-size: var(--font-size-2xl);
            }

            .content-section {
                padding: var(--space-md);
            }

            .data-table {
                font-size: var(--font-size-sm);
            }

            .data-table th,
            .data-table td {
                padding: var(--space-sm);
            }
        }

        /* Móviles pequeños */
        @media (max-width: 480px) {
            .main-content {
                padding: var(--space-sm);
            }

            .filter-panel {
                padding: var(--space-md);
            }

            .section-title {
                font-size: var(--font-size-lg);
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
    </style>

</head>

<body>
    <!-- Efecto de mármol animado -->
    <div class="marble-effect"></div>

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

        <main class="main-content">
            <!-- Encabezado de página -->
            <div class="page-header">
                <div class="page-title-section">
                    <h1 class="page-title">Centro de Reportes</h1>
                    <p class="page-subtitle">Análisis detallado y métricas de la clínica</p>
                </div>
                <div class="page-actions">
                    <?php if ($user_type === 'admin'): ?>
                        <a href="export_jornada.php" target="_blank" class="action-btn">
                            <i class="bi bi-download me-2"></i>
                            Exportar Jornada
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Panel de filtros -->
            <div class="filter-panel animate-in">
                <h3 class="filter-title">
                    <i class="bi bi-funnel"></i>
                    Filtrar por Día (Turno)
                </h3>
                <form method="GET" class="filter-form">
                    <div class="form-group">
                        <label for="fecha_filtro" class="form-label">Seleccionar Fecha de Turno</label>
                        <input type="date" class="form-control" id="fecha_filtro" name="fecha_filtro"
                            value="<?php echo htmlspecialchars($fecha_filtro); ?>" required>
                    </div>
                    <div class="form-group" style="min-width: auto;">
                        <button type="submit" class="action-btn" style="height: fit-content;">
                            <i class="bi bi-filter me-2"></i>
                            Ver Reporte del Turno
                        </button>
                    </div>
                </form>
                <div class="mt-3 text-sm text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    El periodo considera jornadas de <strong>08:00 AM</strong> a <strong>05:00 PM</strong> (jornada
                    diurna) y de <strong>05:00 PM</strong> a <strong>08:00 AM</strong> del día siguiente (jornada
                    nocturna).
                </div>
            </div>

            <!-- Estadísticas principales -->
            <div class="stats-grid">
                <!-- Pacientes registrados -->
                <div class="stat-card animate-in delay-1">
                    <div class="stat-icon primary">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_pacientes; ?></div>
                    <div class="stat-label">Pacientes Registrados</div>
                </div>

                <!-- Citas en período -->
                <div class="stat-card animate-in delay-2">
                    <div class="stat-icon success">
                        <i class="bi bi-calendar-event"></i>
                    </div>
                    <div class="stat-value"><?php echo $citas_count; ?></div>
                    <div class="stat-label">Citas en Periodo</div>
                </div>

                <!-- Exámenes realizados -->
                <div class="stat-card animate-in delay-3">
                    <div class="stat-icon info">
                        <i class="bi bi-clipboard2-pulse"></i>
                    </div>
                    <div class="stat-value"><?php echo $examenes_count; ?></div>
                    <div class="stat-label">Exámenes Realizados</div>
                </div>

                <!-- Medicamentos en stock -->
                <div class="stat-card animate-in delay-4">
                    <div class="stat-icon warning">
                        <i class="bi bi-capsule"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_medicamentos; ?></div>
                    <div class="stat-label">Medicamentos en Stock</div>
                </div>
            </div>

            <!-- Sección de contabilidad -->
            <div class="content-section animate-in delay-1">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-cash-coin section-title-icon"></i>
                        Contabilidad Detallada
                    </h3>
                    <span class="amount-badge <?php echo $total_gross_profit >= 0 ? 'income' : 'expense'; ?>">
                        <i
                            class="bi <?php echo $total_gross_profit >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right'; ?>"></i>
                        Q<?php echo number_format($total_gross_profit, 2); ?>
                    </span>
                </div>

                <div class="row g-4">
                    <!-- Ingresos -->
                    <div class="col-md-6">
                        <div class="content-section" style="padding: var(--space-md); margin: 0;">
                            <div class="section-header"
                                style="margin-bottom: var(--space-md); padding-bottom: var(--space-sm);">
                                <h4 class="section-title" style="font-size: var(--font-size-lg);">
                                    <i class="bi bi-arrow-down-right section-title-icon text-success"></i>
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
                                        <tr>
                                            <td>Hospitalización</td>
                                            <td class="text-end">
                                                <span class="amount-badge income">
                                                    Q<?php echo number_format($total_hospitalization, 2); ?>
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
                        <div class="content-section" style="padding: var(--space-md); margin: 0;">
                            <div class="section-header"
                                style="margin-bottom: var(--space-md); padding-bottom: var(--space-sm);">
                                <h4 class="section-title" style="font-size: var(--font-size-lg);">
                                    <i class="bi bi-arrow-up-right section-title-icon text-danger"></i>
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
                        <div class="content-section" style="padding: var(--space-md); margin: 0;">
                            <div class="section-header"
                                style="margin-bottom: var(--space-md); padding-bottom: var(--space-sm);">
                                <h4 class="section-title" style="font-size: var(--font-size-lg);">
                                    <i class="bi bi-graph-up-arrow section-title-icon text-primary"></i>
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
                                                <span
                                                    class="amount-badge <?php echo $total_gross_profit >= 0 ? 'income' : 'expense'; ?>">
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
                                                <span
                                                    class="amount-badge <?php echo $net_cash_flow >= 0 ? 'income' : 'expense'; ?>">
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

            <!-- Nuevo Reporte Detallado de Laboratorios -->
            <div class="content-section animate-in delay-4 mt-5">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-droplet-half section-title-icon" style="color: var(--color-info);"></i>
                        Reporte Detallado de Laboratorios
                    </h3>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <span class="amount-badge income">
                            Total: Q
                            <?php echo number_format($total_labs_report, 2); ?>
                        </span>
                        <?php if ($user_type === 'admin'): ?>
                            <a href="export_labs.php?start=<?php echo $profit_start; ?>&end=<?php echo $profit_end; ?>"
                                target="_blank" class="action-btn" style="background: var(--color-success)">
                                <i class="bi bi-file-earmark-excel me-2"></i>
                                Exportar Laboratorios
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <style>
                    .report-details {
                        border: 1px solid var(--color-border);
                        border-radius: var(--radius-md);
                        margin-bottom: var(--space-sm);
                        background: var(--color-card);
                        overflow: hidden;
                    }
                    .report-details > summary {
                        padding: var(--space-md);
                        background: var(--color-surface);
                        cursor: pointer;
                        font-weight: 600;
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        list-style: none; /* Hide default arrow */
                        user-select: none;
                        transition: background var(--transition-base);
                    }
                    .report-details > summary::-webkit-details-marker {
                        display: none;
                    }
                    .report-details > summary:hover {
                        background: var(--color-border);
                    }
                    .report-details-body {
                        padding: var(--space-md);
                        border-top: 1px solid var(--color-border);
                    }
                    .report-details.level-2 {
                        border-color: rgba(0,0,0,0.05);
                    }
                    .report-details.level-2 > summary {
                        background: rgba(var(--color-info-rgb), 0.05);
                        padding: var(--space-sm) var(--space-md);
                    }
                    .report-details.level-3 > summary {
                        background: rgba(var(--color-primary-rgb), 0.05);
                        padding: var(--space-sm) var(--space-md);
                    }
                    details > summary::before {
                        content: '\F282'; /* bi-chevron-down */
                        font-family: 'bootstrap-icons';
                        margin-right: 10px;
                        display: inline-block;
                        transition: transform 0.2s ease-in-out;
                    }
                    details[open] > summary::before {
                        transform: rotate(180deg);
                    }
                </style>

                <div class="custom-accordion-wrapper" id="labsAccordion">
                    <?php if (empty($grouped_labs)): ?>
                        <div class="text-center py-4 text-muted border rounded" style="background: var(--color-surface);">
                            <i class="bi bi-info-circle me-2"></i>
                            No se encontraron laboratorios realizados en este período.
                        </div>
                    <?php else: ?>
                        <?php 
                        // Obtener el nombre del mes actual para expandirlo por defecto
                        $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                        $mes_actual_nombre = $meses[(int)date('n') - 1] . ' ' . date('Y');
                        
                        $mes_id = 0; foreach ($grouped_labs as $mes_nombre => $mes_data): $mes_id++; 
                            $is_current_month = ($mes_nombre === $mes_actual_nombre);
                        ?>
                            <details class="report-details level-1" name="mes_accordion" <?php echo $is_current_month ? 'open' : ''; ?>>
                                <summary class="custom-summary">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-calendar3 me-2 text-primary"></i> 
                                        <span><?php echo $mes_nombre; ?></span>
                                        <span class="badge bg-primary ms-3 rounded-pill"><?php echo $mes_data['count']; ?> labs</span>
                                    </div>
                                    <span class="text-success">Q <?php echo number_format($mes_data['total'], 2); ?></span>
                                </summary>
                                <div class="report-details-body">
                                    <?php $dia_id = 0; foreach ($mes_data['dias'] as $dia_str => $dia_data): $dia_id++; ?>
                                        <details class="report-details level-2" name="dia_accordion_<?php echo $mes_id; ?>">
                                            <summary class="custom-summary">
                                                <div class="d-flex align-items-center">
                                                    <i class="bi bi-calendar-day me-2 text-info"></i> 
                                                    <span>Día: <?php echo $dia_str; ?></span>
                                                    <span class="badge bg-info text-dark ms-3 rounded-pill"><?php echo $dia_data['count']; ?> labs</span>
                                                </div>
                                                <span class="text-success fw-semibold">Q <?php echo number_format($dia_data['total'], 2); ?></span>
                                            </summary>
                                            <div class="report-details-body">
                                                <?php $pac_id = 0; foreach ($dia_data['pacientes'] as $paciente_nombre => $pac_data): $pac_id++; ?>
                                                    <details class="report-details level-3" name="paciente_accordion_<?php echo $mes_id . '_' . $dia_id; ?>">
                                                        <summary class="custom-summary">
                                                            <div class="d-flex align-items-center">
                                                                <i class="bi bi-person me-2 text-secondary"></i> 
                                                                <span><?php echo htmlspecialchars($paciente_nombre); ?></span>
                                                                <span class="badge bg-secondary ms-3 rounded-pill"><?php echo $pac_data['count']; ?> labs</span>
                                                            </div>
                                                            <span class="text-success fw-medium">Q <?php echo number_format($pac_data['total'], 2); ?></span>
                                                        </summary>
                                                        <div class="report-details-body p-0">
                                                            <div class="table-responsive">
                                                                <table class="table table-sm table-hover mb-0" style="font-size: 0.9rem; background: transparent; color: var(--color-text);">
                                                                    <thead style="background: var(--color-surface);">
                                                                        <tr>
                                                                            <th class="ps-3 border-0">Examen (Prueba)</th>
                                                                            <th class="border-0">Hora</th>
                                                                            <th class="text-end pe-3 border-0">Precio</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php foreach ($pac_data['labs'] as $lab): ?>
                                                                            <tr>
                                                                                <td class="ps-3 border-bottom" style="border-color: var(--color-border);">
                                                                                    <span class="badge" style="background: var(--color-surface); color: var(--color-text); border: 1px solid var(--color-border);">
                                                                                        <?php echo htmlspecialchars($lab['nombre_prueba']); ?>
                                                                                    </span>
                                                                                </td>
                                                                                <td class="border-bottom" style="border-color: var(--color-border);">
                                                                                    <small style="color: var(--color-text-secondary);"><?php echo date('h:i A', strtotime($lab['hora'])); ?></small>
                                                                                </td>
                                                                                <td class="text-end pe-3 fw-bold text-success border-bottom" style="border-color: var(--color-border);">
                                                                                    Q <?php echo number_format($lab['precio'], 2); ?>
                                                                                </td>
                                                                            </tr>
                                                                        <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </details>
                                                <?php endforeach; ?>
                                            </div>
                                        </details>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            </div>

            <!-- Nuevo Reporte de Traslados (Restringido) -->
            <?php if ($can_view_transfers): ?>
                <div class="content-section animate-in delay-4 mt-5">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="bi bi-arrow-left-right section-title-icon" style="color: var(--color-danger);"></i>
                            Reporte de Traslados (Dispensario)
                        </h3>
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <span class="amount-badge expense">
                                Valor Total: Q<?php echo number_format($total_transfers_amount, 2); ?>
                            </span>
                            <a href="export_transfers.php?start=<?php echo $profit_start; ?>&end=<?php echo $profit_end; ?>"
                                target="_blank" class="action-btn" style="background: var(--color-success)">
                                <i class="bi bi-file-earmark-excel me-2"></i>
                                Exportar Traslados
                            </a>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Medicamento</th>
                                    <th class="text-center">Cant.</th>
                                    <th>Destino / Paciente</th>
                                    <th>Realizado por</th>
                                    <th>Fecha y Hora</th>
                                    <th class="text-end">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transfers_data as $transfer): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($transfer['nom_medicamento']); ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border">
                                                <?php echo $transfer['cantidad_vendida']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($transfer['destino']); ?></td>
                                        <td><?php echo htmlspecialchars($transfer['realizado_por']); ?></td>
                                        <td>
                                            <?php echo date('d/m/Y h:i A', strtotime($transfer['fecha_venta'])); ?>
                                        </td>
                                        <td class="text-end fw-bold text-danger">
                                            Q<?php echo number_format($transfer['valor_traslado'], 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($transfers_data)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="bi bi-info-circle me-2"></i>
                                            No se encontraron traslados en este período.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            <div class="row g-4 animate-in delay-2">
                <!-- Procedimientos menores -->
                <div class="col-lg-6">
                    <div class="content-section" style="height: 100%;">
                        <div class="section-header">
                            <h4 class="section-title">
                                <i class="bi bi-bandaid section-title-icon"></i>
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
                    <div class="content-section" style="height: 100%;">
                        <div class="section-header">
                            <h4 class="section-title">
                                <i class="bi bi-clipboard2-pulse section-title-icon"></i>
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

            <!-- SECCIÓN BIG DATA - ANALÍTICA VISUAL -->
            <div class="content-section animate-in">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-bar-chart-line section-title-icon"></i>
                        Big Data Analytics - Inteligencia de Negocio
                    </h3>
                </div>

                <div class="row g-4 mb-4">
                    <!-- Gráfico de Tendencia -->
                    <div class="col-lg-8">
                        <div style="height: 300px;">
                            <canvas id="salesTrendChart"></canvas>
                        </div>
                    </div>

                    <!-- Gráfico de Distribución -->
                    <div class="col-lg-4">
                        <div style="height: 300px;">
                            <canvas id="revenueDistChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Top Medicamentos -->
                    <div class="col-md-6">
                        <h4 class="mb-3" style="color: var(--color-text-secondary);">Medicamentos más vendidos</h4>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
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
                                        <tr>
                                            <td colspan="2" class="text-center py-3">Sin datos en el periodo</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Resumen Quick Insights -->
                    <div class="col-md-6">
                        <h4 class="mb-3" style="color: var(--color-text-secondary);">Insights de Rendimiento</h4>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="p-3 border rounded" style="background: var(--color-surface);">
                                    <small class="text-muted d-block text-truncate">Margen Bruto Promedio</small>
                                    <span
                                        class="h4 mb-0"><?php echo $total_gross_revenue > 0 ? number_format(($total_gross_profit / $total_gross_revenue) * 100, 1) : '0'; ?>%</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 border rounded" style="background: var(--color-surface);">
                                    <small class="text-muted d-block text-truncate">Costo Méd. Vendidos</small>
                                    <span
                                        class="h4 mb-0 text-danger">Q<?php echo number_format($sales_cost, 2); ?></span>
                                </div>
                            </div>
                            <div class="col-12 mt-2">
                                <div class="p-3 border rounded" style="background: var(--color-surface);">
                                    <small class="text-muted d-block">Ganancia Estimada en Ventas</small>
                                    <span
                                        class="h4 mb-0 text-success">Q<?php echo number_format($actual_sales_margin, 2); ?></span>
                                    <p class="mb-0 text-muted small mt-1">Comparando costo de compra vs precio de venta
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN RENTABILIDAD DE FARMACIA -->
            <div class="content-section animate-in" style="margin-top: 2rem;">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-cash-stack section-title-icon"></i>
                        Rentabilidad Farmacia
                    </h3>
                    <div class="page-actions">
                        <a href="export_sales.php?start=<?php echo $profit_start; ?>&end=<?php echo $profit_end; ?>&format=csv"
                            target="_blank" class="action-btn secondary">
                            <i class="bi bi-filetype-csv"></i> CSV
                        </a>
                        <a href="export_sales.php?start=<?php echo $profit_start; ?>&end=<?php echo $profit_end; ?>&format=excel"
                            target="_blank" class="action-btn secondary">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                        </a>
                        <a href="export_sales.php?start=<?php echo $profit_start; ?>&end=<?php echo $profit_end; ?>&format=print"
                            target="_blank" class="action-btn secondary">
                            <i class="bi bi-printer"></i> PDF
                        </a>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="filter-panel">
                    <form method="GET" class="filter-form">
                        <div class="form-group">
                            <label class="form-label">Fecha Inicio</label>
                            <input type="date" name="profit_start" class="form-control"
                                value="<?php echo $profit_start; ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Fecha Fin</label>
                            <input type="date" name="profit_end" class="form-control"
                                value="<?php echo $profit_end; ?>">
                        </div>
                        <div class="form-group" style="flex: 0; min-width: 150px;">
                            <input type="hidden" name="fecha_filtro" value="<?php echo $fecha_filtro ?? ''; ?>">
                            <button type="submit" class="action-btn primary w-100">
                                <i class="bi bi-search"></i> Filtrar
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Resumen de Estadísticas -->
                <div class="stats-grid" style="margin-top: 2rem;">
                    <div class="stat-card">
                        <div class="stat-icon info">
                            <i class="bi bi-currency-dollar"></i>
                        </div>
                        <div class="stat-value">Q<?php echo number_format($total_profit_revenue, 2); ?></div>
                        <div class="stat-label">Ventas Totales</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="bi bi-cart-x"></i>
                        </div>
                        <div class="stat-value">Q<?php echo number_format($total_profit_cost, 2); ?></div>
                        <div class="stat-label">Costos Totales</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                        <div class="stat-value">Q<?php echo number_format($total_profit_amount, 2); ?></div>
                        <div class="stat-label">Ganancia Neta (<?php echo number_format($total_profit_margin, 1); ?>%)
                        </div>
                    </div>
                </div>

                <!-- Tabla de Detalles -->
                <div class="table-responsive" style="margin-top: 2rem;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Medicamento</th>
                                <th class="text-center">Cant.</th>
                                <th class="text-end">P. Venta</th>
                                <th class="text-end">Costo</th>
                                <th class="text-end">Total Venta</th>
                                <th class="text-end">Total Costo</th>
                                <th class="text-end">Ganancia</th>
                                <th class="text-end">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($profitability_data as $row):
                                $ganancia = $row['total_venta'] - $row['total_costo'];
                                $margen = $row['total_venta'] > 0 ? ($ganancia / $row['total_venta']) * 100 : 0;

                                $p_venta_unit = $row['cantidad_total'] > 0 ? $row['total_venta'] / $row['cantidad_total'] : 0;
                                $p_costo_unit = $row['cantidad_total'] > 0 ? $row['total_costo'] / $row['cantidad_total'] : 0;
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600;">
                                            <?php echo htmlspecialchars($row['nom_medicamento']); ?>
                                        </div>
                                        <?php if (!empty($row['codigo_barras'])): ?>
                                            <div style="font-size: 0.75rem; color: var(--color-text-secondary);">
                                                <i class="bi bi-upc"></i> <?php echo htmlspecialchars($row['codigo_barras']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="amount-badge"><?php echo $row['cantidad_total']; ?></span>
                                    </td>
                                    <td class="text-end text-muted">Q<?php echo number_format($p_venta_unit, 2); ?></td>
                                    <td class="text-end text-muted">Q<?php echo number_format($p_costo_unit, 2); ?></td>
                                    <td class="text-end" style="font-weight: 600;">
                                        Q<?php echo number_format($row['total_venta'], 2); ?></td>
                                    <td class="text-end text-muted">Q<?php echo number_format($row['total_costo'], 2); ?>
                                    </td>
                                    <td class="text-end">
                                        <span class="amount-badge <?php echo $ganancia >= 0 ? 'income' : 'expense'; ?>">
                                            Q<?php echo number_format($ganancia, 2); ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <span
                                            style="font-weight: 700; color: <?php echo $margen > 30 ? 'var(--color-success)' : ($margen > 15 ? 'var(--color-warning)' : 'var(--color-danger)'); ?>;">
                                            <?php echo number_format($margen, 0); ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($profitability_data)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <div style="opacity: 0.5; margin-bottom: 1rem;">
                                            <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                        </div>
                                        <div style="color: var(--color-text-secondary);">No se encontraron ventas en el
                                            rango seleccionado</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript Optimizado -->
    <script>
        // Módulo de Reportes - Centro Médico Herrera Saenz
        // JavaScript para funcionalidades del módulo de reportes

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
            // ANIMACIONES Y GRÁFICOS
            // ==========================================================================
            class AnimationManager {
                constructor() {
                    this.setupAnimations();
                    this.setupCharts();
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

                    document.querySelectorAll('.stat-card, .content-section, .filter-panel').forEach(el => {
                        observer.observe(el);
                    });
                }

                setupCharts() {
                    const isDarkMode = DOM.html.getAttribute('data-theme') === 'dark';
                    const textColor = isDarkMode ? '#94a3b8' : '#64748b';
                    const gridColor = isDarkMode ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';

                    // 1. Gráfico de Tendencia de Ventas
                    const trendCtx = document.getElementById('salesTrendChart');
                    if (trendCtx) {
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
                                        ticks: {
                                            color: textColor,
                                            font: { size: 10 },
                                            callback: v => 'Q' + v
                                        }
                                    }
                                }
                            }
                        });
                    }

                    // 2. Gráfico de Distribución de Ingresos
                    const distCtx = document.getElementById('revenueDistChart');
                    if (distCtx) {
                        const categoryData = <?php echo json_encode($category_data); ?>;

                        new Chart(distCtx, {
                            type: 'doughnut',
                            data: {
                                labels: Object.keys(categoryData),
                                datasets: [{
                                    data: Object.values(categoryData),
                                    backgroundColor: ['#7c90db', '#8dd7bf', '#f8b195', '#38bdf8', '#fbbf24'],
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
                                        labels: {
                                            color: textColor,
                                            padding: 20,
                                            font: { size: 11 }
                                        }
                                    }
                                },
                                cutout: '70%'
                            }
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
                const animationManager = new AnimationManager();

                // Exponer APIs necesarias globalmente
                window.dashboard = {
                    theme: themeManager,
                    animations: animationManager
                };

                // Log de inicialización
                console.log('Módulo de Reportes - CMS v4.0');
                console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
                console.log('Rol: <?php echo htmlspecialchars($user_type); ?>');
                console.log('Periodo: <?php echo $fecha_inicio; ?> - <?php echo $fecha_fin; ?>');
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