<?php
// index.php - Módulo de Reportes - Centro Médico RS
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
require_once '../../includes/multitenant.php';
require_once '../../includes/module_guard.php';
require_once '../../includes/breadcrumbs.php';

check_module_access('reports');



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
    $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

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
    $params = $is_doctor ? [$today, $id_hospital, $user_id] : [$today, $id_hospital];
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM citas WHERE fecha_cita = ? AND id_hospital = ?" . $doctor_filter);
    $stmt->execute($params);
    $today_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 2. Total de citas en el sistema
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM citas WHERE id_hospital = ?");
    $stmt->execute([$id_hospital]);
    $total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 3. Hospitalizaciones Activas
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM encamamientos WHERE estado = 'Activo' AND id_hospital = ?");
    $stmt->execute([$id_hospital]);
    $active_hospitalizations = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 4. Compras pendientes
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventario WHERE estado = 'Pendiente' AND id_hospital = ?");
    $stmt->execute([$id_hospital]);
    $pending_purchases = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // ============ CÁLCULO DE MÉTRICAS DE REPORTES ============

    // 1. Ventas de medicamentos
    $stmt_sales = $conn->prepare("SELECT SUM(total) as total_sales FROM ventas WHERE fecha_venta BETWEEN ? AND ? AND id_hospital = ?");
    $stmt_sales->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_sales_meds = $stmt_sales->fetch(PDO::FETCH_ASSOC)['total_sales'] ?? 0;

    // 2. Compras de medicamentos
    $stmt_purchases = $conn->prepare("SELECT SUM(total_amount) as total_purchases FROM purchase_headers WHERE purchase_date BETWEEN ? AND ? AND id_hospital = ?");
    $stmt_purchases->execute([$fecha_inicio, $fecha_fin, $id_hospital]);
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
        AND v.id_hospital = ?
    ");
    $stmt_actual_profit->execute([$start_datetime, $end_datetime, $id_hospital]);
    $profit_data = $stmt_actual_profit->fetch(PDO::FETCH_ASSOC);
    $sales_revenue = $profit_data['revenue'] ?? 0;
    $sales_cost = $profit_data['cost'] ?? 0;
    $actual_sales_margin = $sales_revenue - $sales_cost;

    // 4. Procedimientos menores
    $stmt_proc = $conn->prepare("SELECT SUM(cobro) FROM procedimientos_menores WHERE fecha_procedimiento BETWEEN ? AND ? AND id_hospital = ?");
    $stmt_proc->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_procedures = $stmt_proc->fetchColumn() ?: 0;

    // 5. Exámenes realizados
    $stmt_exams = $conn->prepare("SELECT SUM(cobro) FROM examenes_realizados WHERE fecha_examen BETWEEN ? AND ? AND id_hospital = ?");
    $stmt_exams->execute([$start_datetime, $end_datetime, $id_hospital]);
    $total_exams_revenue = $stmt_exams->fetchColumn() ?: 0;

    // 6. Cobros de consultas
    $stmt_billings = $conn->prepare("SELECT SUM(cantidad_consulta) FROM cobros WHERE fecha_consulta BETWEEN ? AND ? AND id_hospital = ?");
    $stmt_billings->execute([$fecha_inicio, $fecha_fin, $id_hospital]);
    $total_billings = $stmt_billings->fetchColumn() ?: 0;

    // 6.b Hospitalizaciones (Cuentas de encamamientos dados de alta)
    $stmt_hosp = $conn->prepare("
        SELECT SUM(total_general) 
        FROM cuenta_hospitalaria ch 
        JOIN encamamientos e ON ch.id_encamamiento = e.id_encamamiento 
        WHERE e.fecha_alta BETWEEN ? AND ?
        AND e.id_hospital = ?
    ");
    $stmt_hosp->execute([$start_datetime, $end_datetime, $id_hospital]);
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
        AND id_hospital = ?
        GROUP BY DATE(fecha_venta)
        ORDER BY fecha ASC
    ");
    $stmt_trend->execute([$end_datetime, $id_hospital]);
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
        AND v.id_hospital = ?
        GROUP BY i.id_inventario
        ORDER BY total_vendido DESC
        LIMIT 5
    ");
    $stmt_top_meds->execute([$start_datetime, $end_datetime, $id_hospital]);
    $top_meds_data = $stmt_top_meds->fetchAll(PDO::FETCH_ASSOC);

    // ============ MÉTRICAS ADICIONALES ============

    // Total de pacientes registrados
    $total_pacientes = $conn->prepare("SELECT COUNT(*) FROM pacientes WHERE id_hospital = ?");
    $total_pacientes->execute([$id_hospital]);
    $total_pacientes = $total_pacientes->fetchColumn();

    // Citas en el período
    $stmt_citas = $conn->prepare("SELECT COUNT(*) FROM citas WHERE fecha_cita BETWEEN ? AND ? AND id_hospital = ?");
    $stmt_citas->execute([$start_datetime, $end_datetime, $id_hospital]);
    $citas_count = $stmt_citas->fetchColumn();

    // Exámenes realizados en el período (conteo)
    $stmt_examenes_count = $conn->prepare("SELECT COUNT(*) FROM examenes_realizados WHERE fecha_examen BETWEEN ? AND ? AND id_hospital = ?");
    $stmt_examenes_count->execute([$start_datetime, $end_datetime, $id_hospital]);
    $examenes_count = $stmt_examenes_count->fetchColumn();

    // Medicamentos en stock
    $total_medicamentos = $conn->prepare("SELECT COUNT(*) FROM inventario WHERE cantidad_med > 0 AND id_hospital = ?");
    $total_medicamentos->execute([$id_hospital]);
    $total_medicamentos = $total_medicamentos->fetchColumn();

    // Título de la página
    $page_title = "Reportes - Centro Médico RS";

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
        AND v.id_hospital = ?
        GROUP BY i.id_inventario, i.nom_medicamento, i.codigo_barras
        ORDER BY total_venta DESC
    ");

    $stmt_profitability->execute([$profit_start_datetime, $profit_end_datetime, $id_hospital]);
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

    $labs_where .= " AND ol.id_hospital = ?";
    $labs_params[] = $id_hospital;

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
        $mes_num = (int) date('m', $timestamp) - 1;
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
                COALESCE(pi.unit_cost, i.precio_compra, 0) as precio_compra,
                CONCAT(u.nombre, ' ', u.apellido) as realizado_por
            FROM ventas v
            JOIN detalle_ventas dv ON v.id_venta = dv.id_venta
            JOIN inventario i ON dv.id_inventario = i.id_inventario
            LEFT JOIN purchase_items pi ON i.id_purchase_item = pi.id
            LEFT JOIN usuarios u ON v.id_usuario = u.idUsuario
            WHERE v.tipo_pago = 'Traslado'
            AND v.fecha_venta BETWEEN ? AND ?
            AND v.id_hospital = ?
            ORDER BY v.fecha_venta DESC
        ");
        $stmt_transfers->execute([$profit_start_datetime, $profit_end_datetime, $id_hospital]);
        $raw_transfers = $stmt_transfers->fetchAll(PDO::FETCH_ASSOC);

        $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        foreach ($raw_transfers as $transfer) {
            $total_transfers_amount += $transfer['valor_traslado'];
            
            $t_date = new DateTime($transfer['fecha_venta']);
            $mes_num = (int) date('m', $t_date->getTimestamp()) - 1;
            $anio = date('Y', $t_date->getTimestamp());
            $mes_nombre = $meses[$mes_num] . ' ' . $anio;
            $dia_str = $t_date->format('d/m/Y');
            $destino = $transfer['destino'] ?: 'Sin Destino';

            if (!isset($transfers_data[$mes_nombre])) {
                $transfers_data[$mes_nombre] = ['total' => 0, 'dias' => []];
            }
            if (!isset($transfers_data[$mes_nombre]['dias'][$dia_str])) {
                $transfers_data[$mes_nombre]['dias'][$dia_str] = ['total' => 0, 'destinos' => []];
            }
            if (!isset($transfers_data[$mes_nombre]['dias'][$dia_str]['destinos'][$destino])) {
                $transfers_data[$mes_nombre]['dias'][$dia_str]['destinos'][$destino] = ['total' => 0, 'items' => []];
            }

            $transfers_data[$mes_nombre]['total'] += $transfer['valor_traslado'];
            $transfers_data[$mes_nombre]['dias'][$dia_str]['total'] += $transfer['valor_traslado'];
            $transfers_data[$mes_nombre]['dias'][$dia_str]['destinos'][$destino]['total'] += $transfer['valor_traslado'];
            
            $transfers_data[$mes_nombre]['dias'][$dia_str]['destinos'][$destino]['items'][] = $transfer;
        }
    }


} catch (PDOException $e) {
    // Error específico de base de datos
    error_log("Error DB en módulo de reportes: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    // Mostrar mensaje amigable al usuario
    $error_message = "Error al conectar con la base de datos. Por favor, contacte al administrador.";
error_log('Error en reports/index.php: ' . $e->getMessage());
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
    <meta name="description" content="Módulo de Reportes - Centro Médico RS - Sistema de gestión médica">
    <title><?php echo htmlspecialchars($page_title); ?></title>

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

    <!-- Seguridad y Protección de Código -->
    <script src="../../assets/js/security.js"></script>

    <!-- CSS Crítico (incrustado para máxima velocidad) -->
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
    <?php include '../../includes/theme_head.php'; ?>

    <style>
        /* ===== FILTER PANEL ===== */
        .filter-panel {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-xl);
            padding: 1.75rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }
        .filter-header { margin-bottom: 1rem; }
        .filter-title {
            font-size: 1rem; font-weight: 700;
            color: var(--color-text); margin: 0 0 0.25rem;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .filter-form input[type="date"] {
            padding: 0.625rem 0.875rem;
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-md);
            background: var(--color-surface);
            color: var(--color-text);
            font-family: var(--font-family);
            font-size: 0.875rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            min-width: 180px;
        }
        .filter-form input[type="date"]:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.13);
        }

        /* ===== CONTENT SECTION ===== */
        .content-section {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
        }

        /* Override Bootstrap table styles to match our theme */
        .content-section .table { --bs-table-bg: var(--color-card); color: var(--color-text); }
        .content-section .table thead.bg-light { background: var(--color-surface) !important; }
        .content-section .table > :not(caption) > * > * { border-color: var(--color-border); color: var(--color-text); }
        .content-section .card { background: var(--color-card) !important; }
        .content-section .card-header { background: var(--color-surface) !important; border-color: var(--color-border) !important; }

        /* ===== AMOUNT BADGE ===== */
        .amount-badge {
            display: inline-flex; align-items: center; gap: 0.4rem;
            padding: 0.4rem 0.875rem; border-radius: 50px;
            font-size: 0.85rem; font-weight: 700;
        }
        .amount-badge.income {
            background: rgba(var(--color-success-rgb), 0.12);
            color: var(--color-success);
            border: 1px solid rgba(var(--color-success-rgb), 0.25);
        }
        .amount-badge.expense {
            background: rgba(var(--color-danger-rgb), 0.12);
            color: var(--color-danger);
            border: 1px solid rgba(var(--color-danger-rgb), 0.25);
        }

        /* ===== LABS ACCORDION ===== */
        .custom-accordion-wrapper { display: flex; flex-direction: column; gap: 0.75rem; }

        .report-details {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: box-shadow 0.2s;
        }
        .report-details[open] { box-shadow: var(--shadow-sm); }
        .report-details.level-2 { margin-top: 0.5rem; border-radius: var(--radius-md); background: var(--color-surface); }
        .report-details.level-3 { margin-top: 0.35rem; border-radius: var(--radius-sm); }

        .custom-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.25rem;
            cursor: pointer;
            user-select: none;
            list-style: none;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--color-text);
            transition: background 0.15s;
            gap: 1rem;
        }
        .custom-summary::-webkit-details-marker { display: none; }
        .custom-summary:hover { background: rgba(var(--color-primary-rgb), 0.05); }
        .report-details[open] > .custom-summary { border-bottom: 1px solid var(--color-border); }
        .report-details.level-2 .custom-summary { padding: 0.75rem 1rem; font-size: 0.85rem; }
        .report-details.level-3 .custom-summary { padding: 0.6rem 1rem; font-size: 0.8rem; }

        .report-details-body { padding: 0.75rem 1rem; }
        .report-details.level-2 .report-details-body { padding: 0.5rem 0.75rem; }

        /* Lab items table */
        .lab-items-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
        .lab-items-table th { color: var(--color-text-secondary); font-weight: 700; font-size: 0.72rem; text-transform: uppercase; padding: 0.5rem 0.75rem; border-bottom: 1px solid var(--color-border); }
        .lab-items-table td { padding: 0.5rem 0.75rem; border-bottom: 1px solid var(--color-border); color: var(--color-text); }
        .lab-items-table tr:last-child td { border-bottom: none; }

        /* ===== PROFITABILITY TABLE ===== */
        .profit-table { width: 100%; border-collapse: collapse; }
        .profit-table th { padding: 0.875rem 1.25rem; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--color-text-secondary); background: var(--color-surface); border-bottom: 1px solid var(--color-border); text-align: left; }
        .profit-table td { padding: 0.875rem 1.25rem; border-bottom: 1px solid var(--color-border); color: var(--color-text); font-size: 0.875rem; }
        .profit-table tbody tr:hover { background: rgba(var(--color-primary-rgb), 0.03); }
        .profit-table .text-success { color: var(--color-success) !important; font-weight: 700; }
        .profit-table .text-danger  { color: var(--color-danger) !important; }
        .profit-positive { color: var(--color-success); font-weight: 700; }
        .profit-negative { color: var(--color-danger); font-weight: 700; }

        /* Chart containers */
        .chart-container {
            position: relative;
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
        }
        .chart-title { font-size: 0.9rem; font-weight: 700; color: var(--color-text); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem; }

        /* ===== PREMIUM TABS ===== */
        .reports-tabs-container {
            margin-bottom: 2rem;
            position: relative;
        }
        .reports-tabs {
            display: flex;
            gap: 0.35rem;
            background: rgba(var(--color-card-rgb), 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid var(--color-border);
            padding: 0.35rem;
            border-radius: var(--radius-xl);
            overflow-x: auto;
            position: relative;
            scrollbar-width: none;
        }
        .reports-tabs::-webkit-scrollbar {
            display: none;
        }
        .reports-tab-btn {
            flex: 1;
            min-width: 150px;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-lg);
            border: none;
            background: transparent;
            color: var(--color-text-secondary);
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            white-space: nowrap;
            position: relative;
            z-index: 2;
        }
        .reports-tab-btn:hover {
            color: var(--color-primary);
            background: rgba(var(--color-primary-rgb), 0.05);
        }
        .reports-tab-btn.active {
            color: #ffffff;
            background: var(--color-primary);
            box-shadow: 0 4px 12px rgba(var(--color-primary-rgb), 0.25);
        }

        /* ===== TAB CONTENT ANIMATION ===== */
        .tab-content {
            display: none;
            opacity: 0;
            transform: translateY(10px);
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        .tab-content.active {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        /* ===== SHADOW AND GLOW STAT CARDS ===== */
        .stat-card {
            position: relative;
            border: 1px solid var(--color-border) !important;
            background: var(--color-card) !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        .stat-card::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            border-radius: inherit;
            pointer-events: none;
            box-shadow: inset 0 0 12px rgba(var(--color-primary-rgb), 0);
            transition: box-shadow 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg), 0 0 15px rgba(var(--color-primary-rgb), 0.12);
            border-color: rgba(var(--color-primary-rgb), 0.3) !important;
        }
        .stat-card:hover::after {
            box-shadow: inset 0 0 12px rgba(var(--color-primary-rgb), 0.05);
        }

        /* ===== ACCORDION PREMIUM OVERHAUL ===== */
        .report-details.level-1 {
            background: rgba(var(--color-card-rgb), 0.4);
            backdrop-filter: blur(8px);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            margin-bottom: 0.75rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .report-details.level-1:hover {
            border-color: rgba(var(--color-primary-rgb), 0.3);
            box-shadow: var(--shadow-sm);
        }
        .report-details.level-1[open] {
            border-color: rgba(var(--color-primary-rgb), 0.4);
            box-shadow: var(--shadow-md);
        }
        .report-details.level-1 > .custom-summary {
            border-left: 4px solid var(--color-primary);
        }
        .report-details.level-2 {
            border-left: 3px solid var(--color-info);
        }
        .report-details.level-3 {
            border-left: 2px solid var(--color-secondary);
        }

        .custom-summary {
            position: relative;
            padding: 1.1rem 2.5rem 1.1rem 1.5rem !important;
        }
        .custom-summary::after {
            content: '\F282'; /* bootstrap icon chevron-down */
            font-family: 'Bootstrap Icons';
            position: absolute;
            right: 1.5rem;
            top: 50%;
            transform: translateY(-50%) rotate(0deg);
            transition: transform 0.25s ease;
            font-size: 0.85rem;
            color: var(--color-text-secondary);
        }
        .report-details[open] > .custom-summary::after {
            transform: translateY(-50%) rotate(180deg);
        }

        /* ===== SEARCH WRAPPER FOR PHARMACY ===== */
        .search-wrapper {
            position: relative;
            width: 100%;
            max-width: 380px;
            margin-bottom: 1.25rem;
        }
        .search-input {
            width: 100%;
            padding: 0.625rem 1rem 0.625rem 2.5rem;
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-md);
            background: var(--color-surface);
            color: var(--color-text);
            font-size: 0.875rem;
            outline: none;
            transition: all 0.2s;
        }
        .search-input:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.15);
            background: var(--color-card);
        }
        .search-icon {
            position: absolute;
            left: 0.875rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--color-text-secondary);
            font-size: 0.95rem;
        }

        /* Hide tabs in print mode */
        @media print {
            .reports-tabs-container, .filter-panel, .chart-container, .page-actions, .theme-toggle, .btn-group {
                display: none !important;
            }
            .tab-content {
                display: block !important;
                opacity: 1 !important;
                transform: none !important;
            }
            body {
                background: white !important;
                color: black !important;
            }
            .content-section {
                border: none !important;
                box-shadow: none !important;
                background: transparent !important;
                page-break-inside: avoid;
            }
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
                    <img src="../../assets/img/Logo.png" alt="Centro Médico RS" class="brand-logo">
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
            <?php render_breadcrumbs([
                ['label' => 'Dashboard', 'url' => '../dashboard/index.php'],
                ['label' => 'Reportes'],
            ]); ?>
            <!-- Encabezado de página -->
            <div class="page-header">
                <div class="page-title-section">
                    <h1 class="page-title">Centro de Analítica</h1>
                    <p class="page-subtitle">Monitoreo en tiempo real y métricas estratégicas</p>
                </div>
                <div class="page-actions">
                    <?php if ($user_type === 'admin'): ?>
                            <a href="export_jornada.php" target="_blank" class="action-btn" onclick="showPdfLoading()">
                                <i class="bi bi-file-earmark-pdf me-2"></i>
                                Exportar Jornada
                            </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Panel de filtros -->
            <div class="filter-panel animate-in">
                <div class="filter-header mb-4">
                    <h3 class="filter-title">
                        <i class="bi bi-calendar3 text-primary"></i>
                        Parámetros del Reporte
                    </h3>
                    <p class="text-muted small">Seleccione la fecha de la jornada para auditar los movimientos</p>
                </div>
                <form method="GET" class="filter-form d-flex gap-3 align-items-end">
                    <div class="form-group flex-grow-1">
                        <label for="fecha_filtro" class="form-label fw-semibold">Fecha de Jornada</label>
                        <input type="date" class="form-control" id="fecha_filtro" name="fecha_filtro"
                            value="<?php echo htmlspecialchars($fecha_filtro); ?>" required>
                    </div>
                    <button type="submit" class="action-btn">
                        <i class="bi bi-search me-2"></i>
                        Generar Análisis
                    </button>
                </form>
            </div>

            <!-- Menú de Pestañas Premium (Tabs) -->
            <div class="reports-tabs-container animate-in">
                <div class="reports-tabs">
                    <button class="reports-tab-btn active" data-tab="overview">
                        <i class="bi bi-grid-1x2"></i> Métricas & BI
                    </button>
                    <button class="reports-tab-btn" data-tab="accounting">
                        <i class="bi bi-cash-coin"></i> Contabilidad & Ratios
                    </button>
                    <button class="reports-tab-btn" data-tab="pharmacy">
                        <i class="bi bi-capsule"></i> Ventas y Farmacia
                    </button>
                    <button class="reports-tab-btn" data-tab="labs">
                        <i class="bi bi-droplet-half"></i> Auditoría de Labs
                    </button>
                    <?php if ($can_view_transfers): ?>
                    <button class="reports-tab-btn" data-tab="transfers">
                        <i class="bi bi-arrow-left-right"></i> Dispensario
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB 1: OVERVIEW & BI -->
            <div id="tab-overview" class="tab-content active">
                <!-- Estadísticas principales -->
                <div class="stats-grid">
                    <!-- Pacientes registrados -->
                    <div class="stat-card animate-in">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Pacientes Registrados</div>
                                <div class="stat-value"><?php echo $total_pacientes; ?></div>
                            </div>
                            <div class="stat-icon primary">
                                <i class="bi bi-people"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Citas en período -->
                    <div class="stat-card animate-in delay-1">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Citas en Periodo</div>
                                <div class="stat-value"><?php echo $citas_count; ?></div>
                            </div>
                            <div class="stat-icon success">
                                <i class="bi bi-calendar-event"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Exámenes realizados -->
                    <div class="stat-card animate-in delay-2">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Exámenes Realizados</div>
                                <div class="stat-value"><?php echo $examenes_count; ?></div>
                            </div>
                            <div class="stat-icon info">
                                <i class="bi bi-clipboard2-pulse"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Medicamentos en stock -->
                    <div class="stat-card animate-in delay-3">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Medicamentos en Stock</div>
                                <div class="stat-value"><?php echo $total_medicamentos; ?></div>
                            </div>
                            <div class="stat-icon warning">
                                <i class="bi bi-capsule"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN BIG DATA - ANALÍTICA VISUAL -->
                <div class="content-section animate-in mt-4 p-4" style="background: var(--color-surface); border-radius: var(--radius-xl); border: 1px solid var(--color-border);">
                    <div class="section-header border-0 mb-4">
                        <h3 class="section-title h4">
                            <i class="bi bi-bar-chart-line text-primary me-2"></i>
                            Business Intelligence Analytics
                        </h3>
                    </div>

                    <div class="row g-4 mb-5">
                        <!-- Gráfico de Tendencia -->
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm p-3 h-100" style="background: var(--color-card); border-radius: var(--radius-lg);">
                                <h5 class="card-title text-muted small text-uppercase fw-bold mb-3">Tendencia de Ventas (30 días)</h5>
                                <div style="height: 300px;">
                                    <canvas id="salesTrendChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Gráfico de Distribución -->
                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm p-3 h-100" style="background: var(--color-card); border-radius: var(--radius-lg);">
                                <h5 class="card-title text-muted small text-uppercase fw-bold mb-3">Mix de Ingresos</h5>
                                <div style="height: 300px;">
                                    <canvas id="revenueDistChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <!-- Top Medicamentos -->
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100" style="background: var(--color-card); border-radius: var(--radius-lg);">
                                <div class="card-header bg-transparent border-0 p-3">
                                    <h5 class="card-title text-muted small text-uppercase fw-bold mb-0">Top Medicamentos Vendidos</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th class="ps-3 py-2 text-muted small">Producto</th>
                                                    <th class="pe-3 py-2 text-end text-muted small">Unidades</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($top_meds_data as $med): ?>
                                                        <tr>
                                                            <td class="ps-3 py-2 fw-medium"><?php echo htmlspecialchars($med['nombre_med']); ?></td>
                                                            <td class="pe-3 py-2 text-end fw-bold text-primary"><?php echo $med['total_vendido']; ?></td>
                                                        </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($top_meds_data)): ?>
                                                        <tr>
                                                            <td colspan="2" class="text-center py-4 text-muted small">Sin datos disponibles</td>
                                                        </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Resumen Quick Insights -->
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100 p-4" style="background: var(--color-card); border-radius: var(--radius-lg);">
                                <h5 class="card-title text-muted small text-uppercase fw-bold mb-4">Insights Estratégicos</h5>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="p-3 rounded-3" style="background: var(--color-surface); border: 1px solid var(--color-border);">
                                            <small class="text-muted d-block mb-1">Margen Operativo</small>
                                            <span class="h3 fw-bold mb-0 text-success"><?php echo $total_gross_revenue > 0 ? number_format(($total_gross_profit / $total_gross_revenue) * 100, 1) : '0'; ?>%</span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-3 rounded-3" style="background: var(--color-surface); border: 1px solid var(--color-border);">
                                            <small class="text-muted d-block mb-1">Costo de Ventas</small>
                                            <span class="h3 fw-bold mb-0 text-danger">Q<?php echo number_format($sales_cost, 2); ?></span>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="p-3 rounded-3 mt-2" style="background: var(--color-surface); border: 1px solid var(--color-border);">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <small class="text-muted fw-bold">Ganancia Farmacia Estimada</small>
                                                <span class="badge bg-success-subtle text-success">Rentable</span>
                                            </div>
                                            <span class="h2 fw-bold mb-0 text-primary">Q<?php echo number_format($actual_sales_margin, 2); ?></span>
                                            <p class="text-muted small mt-2 mb-0">Cálculo basado en FIFO: Precio Venta - Costo de Adquisición</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mt-2">
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
                                                    AND id_hospital = ?
                                                    ORDER BY fecha_procedimiento DESC 
                                                    LIMIT 5
                                                ");
                                        $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
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
                                                    AND id_hospital = ?
                                                    ORDER BY fecha_examen DESC 
                                                    LIMIT 5
                                                ");
                                        $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
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
            </div>

            <!-- TAB 2: CONTABILIDAD DETALLADA -->
            <div id="tab-accounting" class="tab-content">
                <div class="content-section animate-in">
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
                            <div class="card h-100 shadow-sm border-0" style="background: var(--color-card); border-radius: var(--radius-lg);">
                                <div class="card-header bg-transparent border-0 p-4">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="mb-0 fw-bold" style="color: var(--color-text);">
                                            <i class="bi bi-arrow-down-left-circle-fill text-success me-2"></i>
                                            Fuentes de Ingresos
                                        </h4>
                                        <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2 border border-success">
                                            Q<?php echo number_format($total_gross_revenue, 2); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th class="ps-4 py-3 text-muted small text-uppercase">Categoría</th>
                                                    <th class="pe-4 py-3 text-end text-muted small text-uppercase">Monto</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr class="border-bottom">
                                                    <td class="ps-4 py-3">Ventas Farmacia</td>
                                                    <td class="pe-4 py-3 text-end fw-semibold">Q<?php echo number_format($total_sales_meds, 2); ?></td>
                                                </tr>
                                                <tr class="border-bottom">
                                                    <td class="ps-4 py-3">Consultas Médicas</td>
                                                    <td class="pe-4 py-3 text-end fw-semibold">Q<?php echo number_format($total_billings, 2); ?></td>
                                                </tr>
                                                <tr class="border-bottom">
                                                    <td class="ps-4 py-3">Procedimientos</td>
                                                    <td class="pe-4 py-3 text-end fw-semibold">Q<?php echo number_format($total_procedures, 2); ?></td>
                                                </tr>
                                                <tr class="border-bottom">
                                                    <td class="ps-4 py-3">Servicios Laboratorio</td>
                                                    <td class="pe-4 py-3 text-end fw-semibold">Q<?php echo number_format($total_exams_revenue, 2); ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="ps-4 py-3">Hospitalización</td>
                                                    <td class="pe-4 py-3 text-end fw-semibold">Q<?php echo number_format($total_hospitalization, 2); ?></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Egresos -->
                        <div class="col-md-6">
                            <div class="card h-100 shadow-sm border-0" style="background: var(--color-card); border-radius: var(--radius-lg);">
                                <div class="card-header bg-transparent border-0 p-4">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="mb-0 fw-bold" style="color: var(--color-text);">
                                            <i class="bi bi-arrow-up-right-circle-fill text-danger me-2"></i>
                                            Egresos e Inversión
                                        </h4>
                                        <span class="badge rounded-pill bg-danger-subtle text-danger px-3 py-2 border border-danger">
                                            Q<?php echo number_format($total_purchases_meds, 2); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th class="ps-4 py-3 text-muted small text-uppercase">Concepto</th>
                                                    <th class="pe-4 py-3 text-end text-muted small text-uppercase">Monto</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr class="border-bottom">
                                                    <td class="ps-4 py-3">Adquisición Inventario</td>
                                                    <td class="pe-4 py-3 text-end fw-semibold">Q<?php echo number_format($total_purchases_meds, 2); ?></td>
                                                </tr>
                                                <tr>
                                                    <td colspan="2" class="text-center py-5">
                                                        <div class="text-muted small">
                                                            <i class="bi bi-info-circle me-1"></i>
                                                            No se registran otros egresos automáticos
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Resumen de desempeño -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card shadow-sm border-0" style="background: var(--color-card); border-radius: var(--radius-lg);">
                                <div class="card-header bg-transparent border-0 p-4">
                                    <h4 class="mb-0 fw-bold" style="color: var(--color-text);">
                                        <i class="bi bi-graph-up-arrow text-primary me-2"></i>
                                        Análisis de Rentabilidad Operativa
                                    </h4>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th class="ps-4 py-3 text-muted small text-uppercase">Métrica de Desempeño</th>
                                                    <th class="py-3 text-end text-muted small text-uppercase">Valor Nominal</th>
                                                    <th class="pe-4 py-3 text-end text-muted small text-uppercase">Impacto / Margen</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr class="border-bottom">
                                                    <td class="ps-4 py-3 fw-medium">Ingresos Brutos Acumulados</td>
                                                    <td class="text-end fw-bold text-success">Q<?php echo number_format($total_gross_revenue, 2); ?></td>
                                                    <td class="pe-4 text-end"><span class="badge bg-light text-dark border">100%</span></td>
                                                </tr>
                                                <tr class="border-bottom">
                                                    <td class="ps-4 py-3 fw-medium">Costo de Ventas (Farmacia)</td>
                                                    <td class="text-end text-danger">- Q<?php echo number_format($sales_cost, 2); ?></td>
                                                    <td class="pe-4 text-end text-muted small">
                                                        <?php echo $total_gross_revenue > 0 ? number_format(($sales_cost / $total_gross_revenue) * 100, 1) : '0'; ?>%
                                                    </td>
                                                </tr>
                                                <tr class="border-bottom" style="background: var(--color-surface);">
                                                    <td class="ps-4 py-3 fw-bold">Utilidad Bruta de Operación</td>
                                                    <td class="text-end fw-bold <?php echo $total_gross_profit >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                        Q<?php echo number_format($total_gross_profit, 2); ?>
                                                    </td>
                                                    <td class="pe-4 text-end">
                                                        <span class="badge <?php echo $total_gross_profit >= 0 ? 'bg-success' : 'bg-danger'; ?>">
                                                            <?php echo $total_gross_revenue > 0 ? number_format(($total_gross_profit / $total_gross_revenue) * 100, 1) : '0'; ?>%
                                                        </span>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="ps-4 py-3 fw-medium">Flujo de Efectivo Neto (Periodo)</td>
                                                    <td class="text-end fw-bold <?php echo $net_cash_flow >= 0 ? 'text-primary' : 'text-danger'; ?>">
                                                        Q<?php echo number_format($net_cash_flow, 2); ?>
                                                    </td>
                                                    <td class="pe-4 text-end text-muted small">Ingresos - Compras</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: VENTAS Y RENTABILIDAD DE FARMACIA -->
            <div id="tab-pharmacy" class="tab-content">
                <div class="content-section animate-in">
                    <div class="section-header align-items-end mb-4">
                        <div>
                            <h3 class="section-title h4 mb-1">
                                <i class="bi bi-capsule text-success me-2"></i>
                                Rendimiento de Inventario y Farmacia
                            </h3>
                            <p class="text-muted small mb-0">Análisis de márgenes y rotación de productos</p>
                        </div>
                        <div class="page-actions">
                            <div class="btn-group shadow-sm">
                                <a href="export_sales.php?start=<?php echo $profit_start; ?>&end=<?php echo $profit_end; ?>&format=csv"
                                    target="_blank" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-filetype-csv"></i> CSV
                                </a>
                                <a href="export_sales.php?start=<?php echo $profit_start; ?>&end=<?php echo $profit_end; ?>&format=excel"
                                    target="_blank" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                                </a>
                                <a href="export_sales.php?start=<?php echo $profit_start; ?>&end=<?php echo $profit_end; ?>&format=print"
                                    target="_blank" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-printer"></i> PDF
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Filtros de Rentabilidad -->
                    <div class="card border-0 shadow-sm mb-4" style="background: var(--color-surface); border-radius: var(--radius-lg);">
                        <div class="card-body p-3">
                            <form method="GET" class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted">Rango Inicial</label>
                                    <input type="date" name="profit_start" class="form-control form-control-sm" value="<?php echo $profit_start; ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted">Rango Final</label>
                                    <input type="date" name="profit_end" class="form-control form-control-sm" value="<?php echo $profit_end; ?>">
                                </div>
                                <div class="col-md-4">
                                    <input type="hidden" name="fecha_filtro" value="<?php echo $fecha_filtro ?? ''; ?>">
                                    <button type="submit" class="btn btn-primary btn-sm w-100 py-2">
                                        <i class="bi bi-funnel-fill me-2"></i> Aplicar Filtro Temporal
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Resumen de Estadísticas -->
                    <div class="stats-grid mb-4" style="margin-top: 2rem;">
                        <div class="stat-card">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-title">Ventas Totales</div>
                                    <div class="stat-value">Q<?php echo number_format($total_profit_revenue, 2); ?></div>
                                </div>
                                <div class="stat-icon info">
                                    <i class="bi bi-currency-dollar"></i>
                                </div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-title">Costos Totales</div>
                                    <div class="stat-value">Q<?php echo number_format($total_profit_cost, 2); ?></div>
                                </div>
                                <div class="stat-icon danger">
                                    <i class="bi bi-cart-x"></i>
                                </div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-title">Ganancia Neta</div>
                                    <div class="stat-value">Q<?php echo number_format($total_profit_amount, 2); ?></div>
                                    <div class="stat-label mt-1 fw-bold text-success"><?php echo number_format($total_profit_margin, 1); ?>% Margen</div>
                                </div>
                                <div class="stat-icon success">
                                    <i class="bi bi-graph-up-arrow"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Buscador dinámico en tiempo real para medicamentos -->
                    <div class="search-wrapper">
                        <i class="bi bi-search search-icon"></i>
                        <input type="search" id="pharmacySearch" class="search-input" placeholder="Buscar por nombre de medicamento o código de barras...">
                    </div>

                    <!-- Tabla de Detalles de Rentabilidad -->
                    <div class="card border-0 shadow-sm overflow-hidden" style="border-radius: var(--radius-lg);">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 profit-table">
                                <thead class="bg-light">
                                    <tr style="border-bottom: 2px solid var(--color-border);">
                                        <th class="ps-4 py-3 text-muted small text-uppercase">Medicamento / Producto</th>
                                        <th class="text-center py-3 text-muted small text-uppercase">Uds.</th>
                                        <th class="text-end py-3 text-muted small text-uppercase">P. Venta</th>
                                        <th class="text-end py-3 text-muted small text-uppercase">P. Costo</th>
                                        <th class="text-end py-3 text-muted small text-uppercase">Venta Total</th>
                                        <th class="text-end py-3 text-muted small text-uppercase">Ganancia</th>
                                        <th class="pe-4 py-3 text-center text-muted small text-uppercase">Margen %</th>
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
                                                <td class="ps-4 py-3">
                                                    <div class="fw-bold" style="color: var(--color-text);"><?php echo htmlspecialchars($row['nom_medicamento']); ?></div>
                                                    <?php if (!empty($row['codigo_barras'])): ?>
                                                            <div class="text-muted" style="font-size: 0.7rem;">
                                                                <i class="bi bi-upc-scan me-1"></i><?php echo htmlspecialchars($row['codigo_barras']); ?>
                                                            </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center py-3">
                                                    <span class="badge bg-light text-dark border"><?php echo $row['cantidad_total']; ?></span>
                                                </td>
                                                <td class="text-end py-3 text-muted">Q<?php echo number_format($p_venta_unit, 2); ?></td>
                                                <td class="text-end py-3 text-muted">Q<?php echo number_format($p_costo_unit, 2); ?></td>
                                                <td class="text-end py-3 fw-bold">Q<?php echo number_format($row['total_venta'], 2); ?></td>
                                                <td class="text-end py-3">
                                                    <span class="fw-bold <?php echo $ganancia >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                        Q<?php echo number_format($ganancia, 2); ?>
                                                    </span>
                                                </td>
                                                <td class="pe-4 py-3 text-center">
                                                    <?php
                                                    $margen_color = $margen > 30 ? 'bg-success' : ($margen > 15 ? 'bg-warning text-dark' : 'bg-danger');
                                                    ?>
                                                    <span class="badge <?php echo $margen_color; ?> rounded-pill px-2" style="min-width: 45px;">
                                                        <?php echo number_format($margen, 0); ?>%
                                                    </span>
                                                </td>
                                            </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($profitability_data)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-5">
                                                    <div class="text-muted opacity-50 mb-2">
                                                        <i class="bi bi-folder-x h1"></i>
                                                    </div>
                                                    <p class="text-muted">No se registran movimientos en el periodo</p>
                                                </td>
                                            </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 4: AUDITORÍA DE LABORATORIOS -->
            <div id="tab-labs" class="tab-content">
                <div class="content-section animate-in">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="bi bi-droplet-half section-title-icon" style="color: var(--color-info);"></i>
                            Reporte Detallado de Laboratorios
                        </h3>
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <span class="amount-badge income">
                                Total: Q<?php echo number_format($total_labs_report, 2); ?>
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
                                $mes_actual_nombre = $meses[(int) date('n') - 1] . ' ' . date('Y');

                                $mes_id = 0;
                                foreach ($grouped_labs as $mes_nombre => $mes_data):
                                    $mes_id++;
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
                                                <?php $dia_id = 0;
                                                foreach ($mes_data['dias'] as $dia_str => $dia_data):
                                                    $dia_id++; ?>
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
                                                                <?php $pac_id = 0;
                                                                foreach ($dia_data['pacientes'] as $paciente_nombre => $pac_data):
                                                                    $pac_id++; ?>
                                                                        <details class="report-details level-3"
                                                                            name="paciente_accordion_<?php echo $mes_id . '_' . $dia_id; ?>">
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
                                                                                    <table class="table table-sm table-hover mb-0"
                                                                                        style="font-size: 0.9rem; background: transparent; color: var(--color-text);">
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
                                                                                                        <td class="ps-3 border-bottom"
                                                                                                            style="border-color: var(--color-border);">
                                                                                                            <span class="badge"
                                                                                                                style="background: var(--color-surface); color: var(--color-text); border: 1px solid var(--color-border);">
                                                                                                                <?php echo htmlspecialchars($lab['nombre_prueba']); ?>
                                                                                                            </span>
                                                                                                        </td>
                                                                                                        <td class="border-bottom"
                                                                                                            style="border-color: var(--color-border);">
                                                                                                            <small
                                                                                                                style="color: var(--color-text-secondary);"><?php echo date('h:i A', strtotime($lab['hora'])); ?></small>
                                                                                                        </td>
                                                                                                        <td class="text-end pe-3 fw-bold text-success border-bottom"
                                                                                                            style="border-color: var(--color-border);">
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

            <!-- TAB 5: REPORTES DE TRASLADOS -->
            <?php if ($can_view_transfers): ?>
            <div id="tab-transfers" class="tab-content">
                <div class="content-section animate-in">
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

                    <?php if (!empty($transfers_data)): ?>
                        <div class="custom-accordion-wrapper">
                            <?php foreach ($transfers_data as $mes_nombre => $mes_data): ?>
                                <details class="report-details level-1" open>
                                    <summary class="custom-summary">
                                        <div class="d-flex align-items-center gap-3">
                                            <i class="bi bi-calendar3 text-primary"></i>
                                            <span><?php echo htmlspecialchars($mes_nombre); ?></span>
                                            <span class="badge bg-primary-subtle text-primary"><?php echo count($mes_data['dias']); ?> día(s)</span>
                                        </div>
                                        <span class="amount-badge expense">Q<?php echo number_format($mes_data['total'], 2); ?></span>
                                    </summary>
                                    <div class="report-details-body">
                                        <?php foreach ($mes_data['dias'] as $dia_str => $dia_data): ?>
                                            <details class="report-details level-2" open>
                                                <summary class="custom-summary">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <i class="bi bi-calendar-event text-info"></i>
                                                        <span><?php echo htmlspecialchars($dia_str); ?></span>
                                                        <span class="badge bg-info-subtle text-info"><?php echo count($dia_data['destinos']); ?> destino(s)</span>
                                                    </div>
                                                    <span class="amount-badge expense">Q<?php echo number_format($dia_data['total'], 2); ?></span>
                                                </summary>
                                                <div class="report-details-body">
                                                    <?php foreach ($dia_data['destinos'] as $destino => $dest_data): ?>
                                                        <details class="report-details level-3">
                                                            <summary class="custom-summary">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <i class="bi bi-person text-secondary"></i>
                                                                    <span><?php echo htmlspecialchars($destino); ?></span>
                                                                    <span class="badge bg-secondary-subtle text-secondary"><?php echo count($dest_data['items']); ?> item(s)</span>
                                                                </div>
                                                                <span class="amount-badge expense">Q<?php echo number_format($dest_data['total'], 2); ?></span>
                                                            </summary>
                                                            <div class="report-details-body">
                                                                <table class="lab-items-table">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Medicamento</th>
                                                                            <th class="text-center">Cant.</th>
                                                                            <th>Realizado por</th>
                                                                            <th class="text-end">Valor</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php foreach ($dest_data['items'] as $item): ?>
                                                                            <tr>
                                                                                <td><?php echo htmlspecialchars($item['nom_medicamento']); ?></td>
                                                                                <td class="text-center">
                                                                                    <span class="badge bg-light text-dark border"><?php echo $item['cantidad_vendida']; ?></span>
                                                                                </td>
                                                                                <td><?php echo htmlspecialchars($item['realizado_por']); ?></td>
                                                                                <td class="text-end fw-bold text-danger">Q<?php echo number_format($item['valor_traslado'], 2); ?></td>
                                                                            </tr>
                                                                        <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </details>
                                                    <?php endforeach; ?>
                                                </div>
                                            </details>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-info-circle fs-1 d-block mb-3 opacity-50"></i>
                            <p>No se encontraron traslados en este período.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
    </main>
    </div>

    <!-- JavaScript Optimizado -->
    <script>
        // Módulo de Reportes - Centro Médico RS
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

                    // Notificar actualización de gráficos
                    if (window.dashboard && window.dashboard.animations) {
                        window.dashboard.animations.updateCharts();
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
                            if (window.dashboard && window.dashboard.animations) {
                                window.dashboard.animations.updateCharts();
                            }
                        }
                    });
                }
            }

            // ==========================================================================
            // DELEGADO DE PESTAÑAS (TAB MANAGER)
            // ==========================================================================
            class TabManager {
                constructor() {
                    this.tabs = document.querySelectorAll('.reports-tab-btn');
                    this.contents = document.querySelectorAll('.tab-content');
                    this.activeTab = localStorage.getItem('reports-active-tab') || 'overview';
                    this.init();
                }

                init() {
                    if (this.tabs.length === 0) return;

                    // Escuchar clics en las pestañas
                    this.tabs.forEach(btn => {
                        btn.addEventListener('click', () => {
                            const tabId = btn.getAttribute('data-tab');
                            this.switchTab(tabId);
                        });
                    });

                    // Cargar pestaña inicial
                    this.switchTab(this.activeTab, true);
                }

                switchTab(tabId, immediate = false) {
                    const targetBtn = document.querySelector(`.reports-tab-btn[data-tab="${tabId}"]`);
                    const targetContent = document.getElementById(`tab-${tabId}`);
                    
                    if (!targetBtn || !targetContent) {
                        // Fallback si no existe la pestaña activa guardada (ej. si no tiene permisos para transfers)
                        if (tabId !== 'overview') {
                            this.switchTab('overview', immediate);
                        }
                        return;
                    }

                    this.activeTab = tabId;
                    localStorage.setItem('reports-active-tab', tabId);

                    // Actualizar botones
                    this.tabs.forEach(btn => btn.classList.remove('active'));
                    targetBtn.classList.add('active');

                    if (immediate) {
                        this.contents.forEach(content => {
                            content.classList.remove('active');
                            content.style.display = 'none';
                            content.style.opacity = '0';
                            content.style.transform = 'translateY(10px)';
                        });
                        targetContent.style.display = 'block';
                        // Forzar reflow
                        targetContent.offsetHeight;
                        targetContent.classList.add('active');
                        targetContent.style.opacity = '1';
                        targetContent.style.transform = 'translateY(0)';
                    } else {
                        // Animación suave de salida/entrada
                        const activeContents = Array.from(this.contents).filter(c => c.classList.contains('active'));
                        
                        if (activeContents.length > 0 && activeContents[0] !== targetContent) {
                            activeContents.forEach(content => {
                                content.style.opacity = '0';
                                content.style.transform = 'translateY(10px)';
                                setTimeout(() => {
                                    content.classList.remove('active');
                                    content.style.display = 'none';
                                    
                                    // Entrada de la nueva pestaña
                                    targetContent.style.display = 'block';
                                    setTimeout(() => {
                                        targetContent.classList.add('active');
                                        targetContent.style.opacity = '1';
                                        targetContent.style.transform = 'translateY(0)';
                                    }, 20);
                                }, 150);
                            });
                        } else if (activeContents.length === 0) {
                            targetContent.style.display = 'block';
                            setTimeout(() => {
                                targetContent.classList.add('active');
                                targetContent.style.opacity = '1';
                                targetContent.style.transform = 'translateY(0)';
                            }, 20);
                        }
                    }
                }
            }

            // ==========================================================================
            // ANIMACIONES Y GRÁFICOS (CHART.JS DE ALTA FIDELIDAD)
            // ==========================================================================
            class AnimationManager {
                constructor() {
                    this.trendChartInstance = null;
                    this.distChartInstance = null;
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
                    const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
                    const textColor = isDarkMode ? '#94a3b8' : '#64748b';
                    const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.05)' : 'rgba(15, 23, 42, 0.05)';

                    // 1. Gráfico de Tendencia de Ventas (Con Gradiente Lineal dinámico)
                    const trendCtx = document.getElementById('salesTrendChart');
                    if (trendCtx) {
                        const salesTrendData = <?php echo json_encode($sales_trend_data); ?>;
                        const ctx = trendCtx.getContext('2d');
                        
                        // Crear gradiente vertical premium (de color de marca a transparente)
                        const gradient = ctx.createLinearGradient(0, 0, 0, 240);
                        gradient.addColorStop(0, 'rgba(124, 144, 219, 0.45)');
                        gradient.addColorStop(0.5, 'rgba(124, 144, 219, 0.15)');
                        gradient.addColorStop(1, 'rgba(124, 144, 219, 0.00)');

                        if (this.trendChartInstance) {
                            this.trendChartInstance.destroy();
                        }

                        this.trendChartInstance = new Chart(trendCtx, {
                            type: 'line',
                            data: {
                                labels: salesTrendData.map(d => d.fecha),
                                datasets: [{
                                    label: 'Ventas Diarias',
                                    data: salesTrendData.map(d => d.total),
                                    borderColor: '#7c90db',
                                    backgroundColor: gradient,
                                    borderWidth: 3,
                                    fill: true,
                                    tension: 0.4,
                                    pointRadius: 4,
                                    pointHoverRadius: 7,
                                    pointBackgroundColor: '#7c90db',
                                    pointHoverBackgroundColor: '#ffffff',
                                    pointHoverBorderColor: '#7c90db',
                                    pointHoverBorderWidth: 3
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { 
                                    legend: { display: false },
                                    tooltip: {
                                        backgroundColor: isDarkMode ? 'rgba(15, 23, 42, 0.9)' : 'rgba(255, 255, 255, 0.9)',
                                        titleColor: isDarkMode ? '#ffffff' : '#0f172a',
                                        bodyColor: isDarkMode ? '#e2e8f0' : '#334155',
                                        borderColor: '#7c90db',
                                        borderWidth: 1,
                                        padding: 12,
                                        cornerRadius: 8,
                                        displayColors: false,
                                        titleFont: { family: 'Outfit, sans-serif', weight: 'bold' },
                                        bodyFont: { family: 'Outfit, sans-serif' },
                                        callbacks: {
                                            label: function(context) {
                                                return 'Total Venta: Q' + context.parsed.y.toLocaleString('es-GT', { minimumFractionDigits: 2 });
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    x: {
                                        grid: { display: false },
                                        ticks: { color: textColor, font: { size: 10, family: 'Outfit, sans-serif' } }
                                    },
                                    y: {
                                        grid: { color: gridColor },
                                        ticks: {
                                            color: textColor,
                                            font: { size: 10, family: 'Outfit, sans-serif' },
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

                        if (this.distChartInstance) {
                            this.distChartInstance.destroy();
                        }

                        this.distChartInstance = new Chart(distCtx, {
                            type: 'doughnut',
                            data: {
                                labels: Object.keys(categoryData),
                                datasets: [{
                                    data: Object.values(categoryData),
                                    backgroundColor: ['#7c90db', '#8dd7bf', '#f8b195', '#38bdf8', '#fbbf24'],
                                    borderWidth: isDarkMode ? 2 : 0,
                                    borderColor: isDarkMode ? '#1e293b' : '#ffffff',
                                    hoverOffset: 12
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
                                            padding: 15,
                                            font: { size: 11, family: 'Outfit, sans-serif' }
                                        }
                                    },
                                    tooltip: {
                                        backgroundColor: isDarkMode ? 'rgba(15, 23, 42, 0.9)' : 'rgba(255, 255, 255, 0.9)',
                                        titleColor: isDarkMode ? '#ffffff' : '#0f172a',
                                        bodyColor: isDarkMode ? '#e2e8f0' : '#334155',
                                        borderColor: '#7c90db',
                                        borderWidth: 1,
                                        padding: 12,
                                        cornerRadius: 8,
                                        titleFont: { family: 'Outfit, sans-serif', weight: 'bold' },
                                        bodyFont: { family: 'Outfit, sans-serif' },
                                        callbacks: {
                                            label: function(context) {
                                                const value = context.raw;
                                                return ` ${context.label}: Q${value.toLocaleString('es-GT', { minimumFractionDigits: 2 })}`;
                                            }
                                        }
                                    }
                                },
                                cutout: '72%'
                            }
                        });
                    }
                }

                updateCharts() {
                    this.setupCharts();
                }
            }

            // ==========================================================================
            // FILTRO DE BÚSQUEDA EN TIEMPO REAL (FARMACIA)
            // ==========================================================================
            class PharmacySearch {
                constructor() {
                    this.input = document.getElementById('pharmacySearch');
                    this.rows = document.querySelectorAll('.profit-table tbody tr');
                    this.init();
                }

                init() {
                    if (!this.input) return;

                    this.input.addEventListener('input', (e) => {
                        const query = e.target.value.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
                        
                        this.rows.forEach(row => {
                            const nameEl = row.querySelector('.fw-bold');
                            const barcodeEl = row.querySelector('.text-muted');
                            
                            const nameText = nameEl ? nameEl.textContent.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "") : '';
                            const barcodeText = barcodeEl ? barcodeEl.textContent.toLowerCase() : '';
                            
                            if (nameText.includes(query) || barcodeText.includes(query)) {
                                row.style.display = '';
                            } else {
                                row.style.display = 'none';
                            }
                        });
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
                const tabManager = new TabManager();
                const pharmacySearch = new PharmacySearch();

                // Exponer APIs necesarias globalmente
                window.dashboard = {
                    theme: themeManager,
                    animations: animationManager,
                    tabs: tabManager,
                    search: pharmacySearch
                };

                // Log de inicialización
                console.log('Módulo de Reportes - CMS v4.0 (Premium Overhauled)');
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
    <script>
    function showPdfLoading() { Swal.fire({ title: 'Generando reporte...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } }); }
    </script>
</body>

</html>