<?php
// dashboard.php - Dashboard Centro Médico Herrera Saenz
// Diseño Responsive, Barra Lateral Moderna, Efecto Mármol
session_start();

error_log("DASHBOARD DEBUG: session_id = " . session_id() . ", user_id = " . ($_SESSION['user_id'] ?? 'not set'));

// Verificar sesión activa
if (!isset($_SESSION['user_id'])) {
    error_log("DASHBOARD DEBUG: Session not set. Redirecting to index.php");
    header("Location: ../../index.php");
    exit;
}

// Incluir configuraciones y funciones
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

$id_hospital = (int) ($_SESSION['id_hospital'] ?? 0);

// Calcular base path del proyecto para PWA Service Worker
// Ej: /GitHub/app-clinica/php/dashboard/index.php → /GitHub/app-clinica/
$script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$pos = strpos($script_dir, '/php/');
$pwa_base_path = ($pos !== false ? substr($script_dir, 0, $pos) : '') . '/';

// Establecer zona horaria
date_default_timezone_set('America/Guatemala');
verify_session();

try {
    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();

    // Sincronizar módulos desde BD (actualiza $_SESSION['hospital_modulos'] siempre)
    get_hospital_config($conn, $_SESSION['id_hospital'] ?? 1);

    // Verificar suscripción (usa datos ya refrescados)
    if (!check_subscription_status()) {
        $subscription_warning = true;
    }

    // Obtener información del usuario
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['tipoUsuario'];
    $user_name = $_SESSION['nombre'];
    $user_specialty = $_SESSION['especialidad'] ?? 'Profesional Médico';

    // Configurar filtros según tipo de usuario
    $is_doctor = $user_type === 'doc';
    $doctor_filter = $is_doctor ? " AND id_doctor = ?" : "";
    $today = date('Y-m-d');

    // ============ CONSULTAS ESTADÍSTICAS ============

    // Obtener Pacientes (para Cobros y Laboratorio)
    $stmt = $conn->prepare("SELECT id_paciente, CONCAT(nombre, ' ', apellido) as nombre_completo FROM pacientes WHERE id_hospital = ? ORDER BY nombre");
    $stmt->execute([$id_hospital]);
    $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener Doctores (para Cobros y Laboratorio)
    $stmtDoc = $conn->prepare("SELECT idUsuario, nombre, apellido FROM usuarios WHERE tipoUsuario = 'doc' AND id_hospital = ? ORDER BY nombre");
    $stmtDoc->execute([$id_hospital]);
    $doctores = $stmtDoc->fetchAll(PDO::FETCH_ASSOC);

    // Obtener catálogo de Pruebas (para Laboratorio)
    $stmtCat = $conn->prepare("SELECT id_prueba, codigo_prueba, nombre_prueba, categoria, precio FROM catalogo_pruebas WHERE id_hospital = ? ORDER BY categoria, nombre_prueba");
    $stmtCat->execute([$id_hospital]);
    $catalogo = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

    $pruebas_por_categoria = [];
    foreach ($catalogo as $prueba) {
        $pruebas_por_categoria[$prueba['categoria'] ?? 'Sin Categoría'][] = $prueba;
    }

    // 1. Citas de hoy
    $params = $is_doctor ? [$today, $id_hospital, $user_id] : [$today, $id_hospital];
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM citas WHERE fecha_cita = ? AND id_hospital = ?" . $doctor_filter);
    $stmt->execute($params);
    $today_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 2. Pacientes del año actual
    $current_year = date('Y');
    $year_start = $current_year . '-01-01';
    $year_end = $current_year . '-12-31';
    $year_params = $is_doctor ? [$year_start, $year_end, $id_hospital, $user_id] : [$year_start, $year_end, $id_hospital];

    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT CONCAT(nombre_pac, ' ', apellido_pac)) as count 
        FROM citas 
        WHERE fecha_cita BETWEEN ? AND ? AND id_hospital = ?" . $doctor_filter
    );
    $stmt->execute($year_params);
    $year_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 3. Citas pendientes (futuras)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM citas WHERE fecha_cita > ? AND id_hospital = ?" . $doctor_filter);
    $stmt->execute($params);
    $pending_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 4. Consultas del mes actual
    $month_start = date('Y-m-01');
    $month_end = date('Y-m-t');
    $month_params = $is_doctor ? [$month_start, $month_end, $id_hospital, $user_id] : [$month_start, $month_end, $id_hospital];

    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM citas 
        WHERE fecha_cita BETWEEN ? AND ? AND id_hospital = ?" . $doctor_filter
    );
    $stmt->execute($month_params);
    $month_consultations = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 5. Citas de hoy con detalles
    $stmt = $conn->prepare("
        SELECT id_cita, nombre_pac, apellido_pac, hora_cita, telefono 
        FROM citas 
        WHERE fecha_cita = ? AND id_hospital = ?" . $doctor_filter . "
        ORDER BY hora_cita
    ");
    $stmt->execute($params);
    $todays_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Total de citas en el sistema
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM citas WHERE id_hospital = ?");
    $stmt->execute([$id_hospital]);
    $total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // ============ INVENTARIO ============

    // 7. Medicamentos en inventario
    $stmt = $conn->prepare("SELECT SUM(cantidad_med) as total FROM inventario WHERE cantidad_med > 0 AND id_hospital = ?");
    $stmt->execute([$id_hospital]);
    $total_medications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 8. Medicamentos próximos a caducar (Configurable)
    $expiring_days = isset($_COOKIE['config_expiring_days']) ? (int) $_COOKIE['config_expiring_days'] : 180; // 6 months default
    $next_month = date('Y-m-d', strtotime("+$expiring_days days"));
    $stmt = $conn->prepare("
        SELECT id_inventario, nom_medicamento, fecha_vencimiento, cantidad_med 
        FROM inventario 
        WHERE fecha_vencimiento BETWEEN ? AND ? AND cantidad_med > 0 AND id_hospital = ?
        ORDER BY fecha_vencimiento ASC
    ");
    $stmt->execute([$today, $next_month, $id_hospital]);
    $expiring_medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 9. Medicamentos con stock bajo (Configurable)
    $low_stock_limit = isset($_COOKIE['config_low_stock']) ? (int) $_COOKIE['config_low_stock'] : 5;
    $stmt = $conn->prepare("
        SELECT id_inventario, nom_medicamento, cantidad_med 
        FROM inventario 
        WHERE cantidad_med > 0 AND cantidad_med <= ? AND id_hospital = ?
        ORDER BY cantidad_med ASC
    ");
    $stmt->execute([$low_stock_limit, $id_hospital]);
    $low_stock_medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 10. Compras pendientes
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventario WHERE estado = 'Pendiente' AND id_hospital = ?");
    $stmt->execute([$id_hospital]);
    $pending_purchases = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // ============ HOSPITALIZACIÓN ============

    // 11. Hospitalizaciones Activas
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM encamamientos WHERE estado = 'Activo' AND id_hospital = ?");
    $stmt->execute([$id_hospital]);
    $active_hospitalizations = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 12. Camas Disponibles
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM camas WHERE id_hospital = ?");
    $stmt->execute([$id_hospital]);
    $total_beds = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    $available_beds_count = $total_beds - $active_hospitalizations;

    // 13. Últimos ingresos hospitalarios
    $stmt = $conn->prepare("
        SELECT e.id_encamamiento, p.nombre, p.apellido, h.numero_habitacion, e.fecha_ingreso, e.diagnostico_ingreso
        FROM encamamientos e
        JOIN pacientes p ON e.id_paciente = p.id_paciente
        JOIN camas c ON e.id_cama = c.id_cama
        JOIN habitaciones hab ON c.id_habitacion = hab.id_habitacion
        JOIN habitaciones h ON c.id_habitacion = h.id_habitacion 
        WHERE e.estado = 'Activo' AND e.id_hospital = ?
        ORDER BY e.fecha_ingreso DESC
        LIMIT 5
    ");
    $stmt->execute([$id_hospital]);
    $hospitalized_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Título de la página
    $page_title = "Dashboard - Centro Médico Herrera Saenz";

    // ============ WIDGET SETTINGS ============
    $hospital_id = $_SESSION['id_hospital'] ?? 1;
    $stmt = $conn->prepare("SELECT widget_id, is_enabled FROM widget_settings WHERE id_hospital = ?");
    $stmt->execute([$hospital_id]);
    $widget_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Default values if not set
    $show_quick_actions = isset($widget_settings['widget-quick-actions']) ? (int) $widget_settings['widget-quick-actions'] : 1;
    $show_stats = isset($widget_settings['widget-stats']) ? (int) $widget_settings['widget-stats'] : 1;
    $show_appointments = isset($widget_settings['widget-appointments']) ? (int) $widget_settings['widget-appointments'] : 1;
    $show_hospitalized = isset($widget_settings['widget-hospitalized']) ? (int) $widget_settings['widget-hospitalized'] : 1;
    $show_alerts = isset($widget_settings['widget-alerts']) ? (int) $widget_settings['widget-alerts'] : 1;
    $show_revenue = isset($widget_settings['widget-revenue']) ? (int) $widget_settings['widget-revenue'] : 1;
    $show_inventory = isset($widget_settings['widget-inventory']) ? (int) $widget_settings['widget-inventory'] : 1;
    $show_patients = isset($widget_settings['widget-patients']) ? (int) $widget_settings['widget-patients'] : 1;
    $show_calendar = isset($widget_settings['widget-calendar']) ? (int) $widget_settings['widget-calendar'] : 1;
    $show_labs = isset($widget_settings['widget-labs']) ? (int) $widget_settings['widget-labs'] : 1;

    // ============ DATA FOR ADDITIONAL WIDGETS ============
    // 1. Revenue data
    $revenue_ventas = 0;
    $revenue_proc = 0;
    $revenue_exams = 0;
    $revenue_consults = 0;
    $revenue_hosp = 0;

    try {
        $stmt = $conn->prepare("SELECT SUM(total) FROM ventas WHERE MONTH(fecha_venta) = MONTH(CURDATE()) AND YEAR(fecha_venta) = YEAR(CURDATE()) AND id_hospital = ?");
        $stmt->execute([$hospital_id]);
        $revenue_ventas = (float) $stmt->fetchColumn() ?: 0;
    } catch (\Exception $e) {
    }

    try {
        $stmt = $conn->prepare("SELECT SUM(cobro) FROM procedimientos_menores WHERE MONTH(fecha_procedimiento) = MONTH(CURDATE()) AND YEAR(fecha_procedimiento) = YEAR(CURDATE()) AND id_hospital = ?");
        $stmt->execute([$id_hospital]);
        $revenue_proc = (float) $stmt->fetchColumn() ?: 0;
    } catch (\Exception $e) {
    }

    try {
        $stmt = $conn->prepare("SELECT SUM(cobro) FROM examenes_realizados WHERE MONTH(fecha_examen) = MONTH(CURDATE()) AND YEAR(fecha_examen) = YEAR(CURDATE()) AND id_hospital = ?");
        $stmt->execute([$id_hospital]);
        $revenue_exams = (float) $stmt->fetchColumn() ?: 0;
    } catch (\Exception $e) {
    }

    try {
        $stmt = $conn->prepare("SELECT SUM(cantidad_consulta) FROM cobros WHERE MONTH(fecha_consulta) = MONTH(CURDATE()) AND YEAR(fecha_consulta) = YEAR(CURDATE()) AND id_hospital = ?");
        $stmt->execute([$id_hospital]);
        $revenue_consults = (float) $stmt->fetchColumn() ?: 0;
    } catch (\Exception $e) {
    }

    try {
        $stmt = $conn->prepare("SELECT SUM(total_general) FROM cuenta_hospitalaria ch JOIN encamamientos e ON ch.id_encamamiento = e.id_encamamiento WHERE MONTH(e.fecha_alta) = MONTH(CURDATE()) AND YEAR(e.fecha_alta) = YEAR(CURDATE()) AND e.id_hospital = ?");
        $stmt->execute([$id_hospital]);
        $revenue_hosp = (float) $stmt->fetchColumn() ?: 0;
    } catch (\Exception $e) {
    }

    $total_monthly_revenue = $revenue_ventas + $revenue_proc + $revenue_exams + $revenue_consults + $revenue_hosp;

    // 2. Patients widget data
    $total_patients_count = 0;
    $latest_patients = [];
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM pacientes WHERE id_hospital = ?");
        $stmt->execute([$hospital_id]);
        $total_patients_count = (int) $stmt->fetchColumn() ?: 0;

        $stmt = $conn->prepare("SELECT id_paciente, nombre, apellido, nit, telefono, fecha_registro FROM pacientes WHERE id_hospital = ? ORDER BY id_paciente DESC LIMIT 5");
        $stmt->execute([$hospital_id]);
        $latest_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e) {
    }

    // 3. Laboratory widget data
    $pending_labs_count = 0;
    $completed_labs_count = 0;
    $latest_lab_orders = [];
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM ordenes_laboratorio WHERE estado = 'Pendiente' AND id_hospital = ?");
        $stmt->execute([$hospital_id]);
        $pending_labs_count = (int) $stmt->fetchColumn() ?: 0;

        $stmt = $conn->prepare("SELECT COUNT(*) FROM ordenes_laboratorio WHERE estado = 'Completada' AND id_hospital = ?");
        $stmt->execute([$hospital_id]);
        $completed_labs_count = (int) $stmt->fetchColumn() ?: 0;

        $stmt = $conn->prepare("SELECT ol.id_orden, ol.numero_orden, ol.estado, ol.fecha_orden, p.nombre, p.apellido FROM ordenes_laboratorio ol JOIN pacientes p ON ol.id_paciente = p.id_paciente WHERE ol.id_hospital = ? ORDER BY ol.id_orden DESC LIMIT 5");
        $stmt->execute([$hospital_id]);
        $latest_lab_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e) {
    }

} catch (Exception $e) {
    // Manejo de errores — registra el detalle completo en el log del servidor
    error_log("Error en dashboard: " . $e->getMessage() . " | File: " . $e->getFile() . ":" . $e->getLine() . " | Trace: " . $e->getTraceAsString());
    die("Error al cargar el dashboard. Por favor, contacte al administrador. (Código: " . substr(md5($e->getMessage()), 0, 6) . ")");
}


// Código de autorización para corte de turno (configurable vía variable de entorno)
$shift_auth_code = getenv('SHIFT_AUTH_CODE') ?: getenv('AUTH_CODE') ?: 'logo';
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Dashboard del Centro Médico Herrera Saenz - Sistema de gestión médica">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <!-- logo -->
    <link rel="icon" type="image/png" href="../../assets/img/cmhs.png">

    <!-- PWA -->
    <link rel="manifest" href="../../manifest.json">
    <meta name="pwa-base" content="<?php echo htmlspecialchars($pwa_base_path); ?>">
    <meta name="theme-color" content="#0d6efd">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="CMHS">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="apple-touch-icon" href="../../assets/img/icon-192.png">

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" media="print"
        onload="this.media='all'">
    <script defer src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Bootstrap CSS y JS Bundle -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" media="print"
        onload="this.media='all'">
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css"
        media="print" onload="this.media='all'">

    <meta name="csrf-token" content="<?php echo csrf_token(); ?>">
    <!-- Seguridad y Protección de Código -->
    <!-- <script defer src="../../assets/js/security.js"></script> -->

    <!-- CSS Crítico -->
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css?v=20250602">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/pwa-install.css?v=1">
    <!-- Responsive fixes for mobile -->
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
        }
        html {
            overflow-x: hidden;
        }
        body {
            overflow-x: hidden;
            max-width: 100vw;
        }
        .dashboard-container {
            max-width: 100%;
        }
        .main-content {
            max-width: 100%;
        }
        .mobile-toggle {
            display: none;
        }
        @media (max-width: 991px) {
            .mobile-toggle {
                display: block;
            }
            .sidebar-toggle {
                display: none;
            }
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .dashboard-container {
                margin-left: 0 !important;
                width: 100% !important;
                max-width: 100vw;
            }
            .main-content {
                margin-left: 0 !important;
                padding: 0.75rem;
                max-width: 100vw;
                overflow-x: hidden;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }
            .alerts-grid {
                grid-template-columns: 1fr;
            }
            .section-header {
                flex-direction: column;
                align-items: stretch;
                gap: 0.5rem;
            }
            .action-btn {
                width: 100%;
                justify-content: center;
            }
            .header-content {
                padding: 0.5rem 0.75rem;
            }
            .header-user .header-details {
                display: none;
            }
            .header-avatar {
                width: 32px;
                height: 32px;
                font-size: 0.85rem;
            }
            .brand-logo {
                height: 28px;
            }
            .shift-cut-btn-container {
                position: static !important;
                margin-top: 0.5rem;
                text-align: center;
            }
            .shift-cut-btn-container .btn {
                width: 100%;
                max-width: 100%;
                font-size: 0.85rem;
                padding: 0.5rem 1rem !important;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .appointments-table {
                min-width: 400px;
            }
            .table-responsive {
                -webkit-overflow-scrolling: touch;
                overflow-x: auto;
            }
            .modal-dialog {
                margin: 0.5rem;
            }
            .modal-dialog.modal-xl {
                margin: 0.25rem;
            }
            .modal-dialog.modal-premium {
                width: 100% !important;
                max-width: 1280px !important;
                margin: 1rem auto !important;
            }
            .modal-dialog.modal-premium .modal-content {
                max-height: calc(100vh - 2rem);
                display: flex;
                flex-direction: column;
            }
            .modal-dialog.modal-premium .modal-body {
                flex: 1;
                overflow: hidden;
                padding: 0;
                display: flex;
                flex-direction: column;
            }
            .modal-dialog.modal-premium .lab-modal-body .bg-white.p-4 {
                flex: 1;
                overflow-y: auto;
            }
            .modal-dialog.modal-premium .d-flex.h-100 {
                flex-direction: row !important;
                min-height: 70vh !important;
            }
            .modal-dialog.modal-premium .lab-summary-panel {
                min-width: 320px !important;
                max-width: 320px !important;
                flex-shrink: 0;
                border-left: 1px solid var(--color-border) !important;
                border-top: none !important;
                height: 100%;
                overflow-y: auto;
            }
            @media (max-width: 1599.98px) {
                .modal-dialog.modal-premium {
                    max-width: calc(100vw - 1rem) !important;
                    margin: 0.5rem auto !important;
                }
                .modal-dialog.modal-premium .lab-summary-panel {
                    min-width: 100% !important;
                    max-width: 100% !important;
                    border-left: none !important;
                    border-top: 1px solid var(--color-border) !important;
                }
                .modal-dialog.modal-premium .d-flex.h-100 {
                    flex-direction: column !important;
                    min-height: auto !important;
                }
            }
            .btn-group.w-100 {
                flex-wrap: wrap;
            }
            .btn-group.w-100 .btn {
                font-size: 0.75rem;
                padding: 0.35rem 0.5rem;
            }
            .btn-group.w-100 .btn i {
                display: none;
            }
            .row.g-3.p-3,
            .row.g-3.mb-4.p-3 {
                padding: 0.5rem !important;
                margin-left: 0;
                margin-right: 0;
            }
            .row.g-3.p-3 > [class*="col-"],
            .row.g-3.mb-4.p-3 > [class*="col-"] {
                padding-left: 0.25rem;
                padding-right: 0.25rem;
            }
            .stat-card {
                padding: 0.75rem;
            }
            .stat-value {
                font-size: 1.5rem;
            }
            .stat-icon {
                width: 36px;
                height: 36px;
                font-size: 1.15rem;
            }
            .appointments-section,
            .billing-section {
                padding: 1rem;
            }
            .appointments-table th,
            .appointments-table td {
                padding: 0.5rem;
                font-size: 0.8rem;
            }
            .patient-avatar {
                width: 30px;
                height: 30px;
                font-size: 0.8rem;
            }
            #greeting {
                font-size: 1.1rem !important;
            }
            .greeting-meta {
                font-size: 0.75rem;
                display: flex;
                flex-wrap: wrap;
                gap: 0.25rem;
            }
            .greeting-meta .mx-2 {
                margin-left: 0.25rem !important;
                margin-right: 0.25rem !important;
            }
            .section-title {
                font-size: 1rem;
            }
            .section-header .badge {
                font-size: 0.7rem;
                padding: 0.25rem 0.5rem;
                align-self: flex-start;
            }
            .alert-card {
                padding: 0.75rem;
            }
            .alert-item {
                padding: 0.5rem;
            }
            .stat-card.border-start {
                padding: 0.75rem;
            }
            .stat-card.border-start .stat-value {
                font-size: 1rem;
            }
            .card.p-2,
            .card.card-body.p-2 {
                padding: 0.5rem !important;
            }
            .form-control,
            .form-select {
                font-size: 0.8rem;
                padding: 0.45rem 0.6rem;
            }
            .form-label {
                font-size: 0.75rem;
            }
            .modal-body.p-4 {
                padding: 0.75rem !important;
            }
            .modal-header {
                padding: 0.75rem;
            }
            .modal-footer {
                padding: 0.75rem;
            }
            .empty-state {
                padding: 1.5rem 0.75rem;
            }
            .accordion-button {
                font-size: 0.85rem;
                padding: 0.6rem;
            }
            .test-card-v2 {
                padding: 0.4rem !important;
            }
            .test-card-v2 .fw-semibold {
                font-size: 0.75rem;
            }
            .d-flex.gap-2 {
                flex-wrap: wrap;
            }
            input[type="text"].form-control,
            input[type="number"].form-control,
            input[type="date"].form-control,
            select.form-select {
                max-width: 100%;
            }
            .table {
                max-width: 100%;
            }
            img {
                max-width: 100%;
                height: auto;
            }
        }
        @media (max-width: 767px) {
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            .stat-card {
                padding: 0.75rem;
            }
            .stat-value {
                font-size: 1.35rem;
            }
            .section-title {
                font-size: 0.95rem;
            }
            .badge.p-2.fs-6 {
                font-size: 0.8rem !important;
                padding: 0.25rem 0.5rem !important;
            }
            .main-content {
                padding: 0.5rem;
            }
            #greeting {
                font-size: 1rem !important;
            }
            .appointments-table th,
            .appointments-table td {
                padding: 0.4rem;
                font-size: 0.75rem;
            }
            .logout-btn span {
                display: none;
            }
            .logout-btn {
                padding: 0.4rem;
            }
            .theme-btn {
                width: 36px;
                height: 36px;
            }
            .brand-container {
                flex-shrink: 0;
            }
            .header-controls {
                gap: 0.3rem;
            }
        }
    </style>
    <!-- Theme Loader: aplica el tema guardado antes del primer paint -->
    <?php include '../../includes/theme_head.php'; ?>
    <noscript>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    </noscript>
</head>

<body>
    <!-- Efecto de mármol animado -->
    <div class="marble-effect"></div>

    <!-- Overlay para sidebar móvil -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Barra Lateral Moderna -->
    <aside class="sidebar" id="sidebar">

        <!-- Navegación -->
        <nav class="sidebar-nav">
            <ul class="nav-list">
                <?php
                $role_scope = trim($user_specialty ?? '');
                $allowed_modules = [];
                if ($role_scope === 'farmacia') {
                    $allowed_modules = ['inventory', 'pharmacy'];
                } elseif ($role_scope === 'recepcion') {
                    $allowed_modules = ['appointments', 'patients', 'pharmacy', 'laboratory', 'finances'];
                } elseif ($role_scope === 'enfermeria') {
                    $allowed_modules = ['pharmacy', 'inventory', 'finances'];
                }
                $is_restricted = !empty($allowed_modules);
                ?>
                
                <li class="nav-item">
                    <a href="index.php" class="nav-link active">
                        <i class="bi bi-speedometer2 nav-icon"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>

                <!-- Punto de Venta (Dispensario) -->
                <?php if ($user_type === 'admin' || $user_type === 'user'): ?> 
                <?php if (!$is_restricted || in_array('pharmacy', $allowed_modules)): ?>
                <li class="nav-item">
                    <?php if (is_module_active('pharmacy')): ?>
                            <a href="../dispensary/index.php" class="nav-link">
                                <span class="nav-icon" style="font-weight: 900; font-family: serif; font-size: 1.5rem;">Q</span>
                                <span class="nav-text">Punto de Venta</span>
                            </a>
                    <?php else: ?>
                            <a href="javascript:void(0)" class="nav-link locked"
                                onclick="lockedModule('Punto de Venta / Farmacia')">
                                <span class="nav-icon" style="font-weight: 900; font-family: serif; font-size: 1.5rem;">Q</span>
                                <span class="nav-text">Punto de Venta</span>
                            </a>
                    <?php endif; ?>
                </li>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($user_type === 'admin' || $user_type === 'user'): ?>
                <?php if (!$is_restricted || in_array('appointments', $allowed_modules)): ?>
                <li class="nav-item">
                    <a href="../appointments/index.php" class="nav-link">
                        <i class="bi bi-calendar-check nav-icon"></i>
                        <span class="nav-text">Citas</span>
                        <span class="badge bg-primary"><?php echo $total_appointments; ?></span>
                    </a>
                </li>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($user_type === 'admin' || $user_type === 'user'): ?>
                <?php if (!$is_restricted || in_array('patients', $allowed_modules)): ?>
                <li class="nav-item">
                    <a href="../patients/index.php" class="nav-link">
                        <i class="bi bi-people nav-icon"></i>
                        <span class="nav-text">Pacientes</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php endif; ?>

                <!-- Hospitalización -->
                <?php if ($user_type === 'admin'): ?>
                <?php if ($user_type === 'admin' || !$is_restricted || in_array('hospitalization', $allowed_modules)): ?>
                <li class="nav-item">
                    <?php if (is_module_active('hospitalization')): ?>
                            <a href="../hospitalization/index.php" class="nav-link">
                                <i class="bi bi-hospital nav-icon"></i>
                                <span class="nav-text">Hospitalización</span>
                                <span class="badge bg-info"><?php echo $active_hospitalizations; ?></span>
                            </a>
                    <?php else: ?>
                            <a href="javascript:void(0)" class="nav-link locked" onclick="lockedModule('Hospitalización')">
                                <i class="bi bi-hospital nav-icon"></i>
                                <span class="nav-text">Hospitalización</span>
                            </a>
                    <?php endif; ?>
                </li>
                <?php endif; ?>
                <?php endif; ?>

                <!-- Quirófano -->
                <?php if ($user_type === 'admin'): ?>
                <li class="nav-item">
                    <?php if (is_module_active('surgery')): ?>
                            <a href="../surgery/index.php" class="nav-link">
                                <i class="bi bi-bandaid nav-icon"></i>
                                <span class="nav-text">Quirófano</span>
                            </a>
                    <?php else: ?>
                            <a href="javascript:void(0)" class="nav-link locked" onclick="lockedModule('Quirófano')">
                                <i class="bi bi-bandaid nav-icon"></i>
                                <span class="nav-text">Quirófano</span>
                            </a>
                    <?php endif; ?>
                </li>
                <?php endif; ?>

                <!-- Laboratorio -->
                <?php if ($user_type === 'admin' || $user_type === 'user'): ?>
                <?php if (!$is_restricted || in_array('laboratory', $allowed_modules)): ?>
                <li class="nav-item">
                    <?php if (is_module_active('laboratory')): ?>
                            <a href="../laboratory/index.php" class="nav-link">
                                <i class="bi bi-flask nav-icon"></i>
                                <span class="nav-text">Laboratorio</span>
                            </a>
                    <?php else: ?>
                            <a href="javascript:void(0)" class="nav-link locked" onclick="lockedModule('Laboratorio Clínico')">
                                <i class="bi bi-flask nav-icon"></i>
                                <span class="nav-text">Laboratorio</span>
                            </a>
                    <?php endif; ?>
                </li>
                <?php endif; ?>
                <?php endif; ?>

                <!-- Inventario -->
                <?php if ($user_type === 'admin' || $user_type === 'user'): ?>
                <?php if (!$is_restricted || in_array('inventory', $allowed_modules)): ?>
                <li class="nav-item">
                    <?php if (is_module_active('inventory')): ?>
                            <a href="../inventory/index.php" class="nav-link">
                                <i class="bi bi-box-seam nav-icon"></i>
                                <span class="nav-text">Inventario</span>
                                <?php if ($pending_purchases > 0): ?>
                                        <span class="badge bg-warning"><?php echo $pending_purchases; ?></span>
                                <?php endif; ?>
                            </a>
                    <?php else: ?>
                            <a href="javascript:void(0)" class="nav-link locked" onclick="lockedModule('Inventario')">
                                <i class="bi bi-box-seam nav-icon"></i>
                                <span class="nav-text">Inventario</span>
                            </a>
                    <?php endif; ?>
                </li>
                <?php endif; ?>
                <?php endif; ?>

                <!-- Otros Módulos -->
                <?php if ($user_type === 'admin'): ?>
                <li class="nav-item">
                    <?php if (is_module_active('imaging')): ?>
                            <a href="../minor_procedures/index.php" class="nav-link">
                                <i class="bi bi-bandaid nav-icon"></i>
                                <span class="nav-text">Procedimientos</span>
                            </a>
                    <?php else: ?>
                            <a href="javascript:void(0)" class="nav-link locked"
                                onclick="lockedModule('Procedimientos Menores')">
                                <i class="bi bi-bandaid nav-icon"></i>
                                <span class="nav-text">Procedimientos</span>
                            </a>
                    <?php endif; ?>
                </li>
                <?php endif; ?>

                <?php if ($user_type === 'admin'): ?>
                <li class="nav-item">
                    <?php if (is_module_active('imaging')): ?>
                            <a href="../examinations/index.php" class="nav-link">
                                <i class="bi bi-file-earmark-medical nav-icon"></i>
                                <span class="nav-text">Exámenes</span>
                            </a>
                    <?php else: ?>
                            <a href="javascript:void(0)" class="nav-link locked"
                                onclick="lockedModule('Exámenes Especializados')">
                                <i class="bi bi-file-earmark-medical nav-icon"></i>
                                <span class="nav-text">Exámenes</span>
                            </a>
                    <?php endif; ?>
                </li>
                <?php endif; ?>

                <?php if ($user_type === 'admin'): ?>
                <?php if (!$is_restricted || in_array('purchases', $allowed_modules)): ?>
                <li class="nav-item">
                    <?php if (is_module_active('purchases')): ?>
                            <a href="../purchases/index.php" class="nav-link">
                                <i class="bi bi-cart nav-icon"></i>
                                <span class="nav-text">Compras</span>
                            </a>
                    <?php else: ?>
                            <a href="javascript:void(0)" class="nav-link locked" onclick="lockedModule('Gestión de Compras')">
                                <i class="bi bi-cart nav-icon"></i>
                                <span class="nav-text">Compras</span>
                            </a>
                    <?php endif; ?>
                </li>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($user_type === 'admin'): ?>
                <?php if (!$is_restricted || in_array('sales', $allowed_modules)): ?>
                <li class="nav-item">
                    <?php if (is_module_active('sales')): ?>
                            <a href="../sales/index.php" class="nav-link">
                                <i class="bi bi-receipt nav-icon"></i>
                                <span class="nav-text">Ventas</span>
                            </a>
                    <?php else: ?>
                            <a href="javascript:void(0)" class="nav-link locked" onclick="lockedModule('Ventas y Facturación')">
                                <i class="bi bi-receipt nav-icon"></i>
                                <span class="nav-text">Ventas</span>
                            </a>
                    <?php endif; ?>
                </li>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($user_type === 'admin'): ?>
                <?php if (!$is_restricted || in_array('finances', $allowed_modules)): ?>
                <li class="nav-item">
                    <?php if (is_module_active('finances')): ?>
                            <a href="../billing/index.php" class="nav-link">
                                <i class="bi bi-cash-coin nav-icon"></i>
                                <span class="nav-text">Cobros</span>
                            </a>
                    <?php else: ?>
                            <a href="javascript:void(0)" class="nav-link locked" onclick="lockedModule('Gestión de Finanzas')">
                                <i class="bi bi-cash-coin nav-icon"></i>
                                <span class="nav-text">Cobros</span>
                            </a>
                    <?php endif; ?>
                </li>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($user_type === 'admin'): ?>
                <?php if (!$is_restricted || in_array('reports', $allowed_modules)): ?>
                <li class="nav-item">
                    <?php if (is_module_active('reports')): ?>
                            <a href="../reports/index.php" class="nav-link">
                                <i class="bi bi-graph-up nav-icon"></i>
                                <span class="nav-text">Reportes</span>
                            </a>
                    <?php else: ?>
                            <a href="javascript:void(0)" class="nav-link locked"
                                onclick="lockedModule('Reportes Estadísticos')">
                                <i class="bi bi-graph-up nav-icon"></i>
                                <span class="nav-text">Reportes</span>
                            </a>
                    <?php endif; ?>
                </li>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($user_type === 'admin'): ?>
                <li class="nav-item">
                    <a href="../settings/subscription.php" class="nav-link">
                        <i class="bi bi-credit-card nav-icon"></i>
                        <span class="nav-text">Suscripción</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../settings/index.php" class="nav-link">
                        <i class="bi bi-gear nav-icon"></i>
                        <span class="nav-text">Configuración</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="widgets_config.php" class="nav-link">
                        <i class="bi bi-layout-three-columns nav-icon"></i>
                        <span class="nav-text">Widgets</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
    </aside>    

    <!-- Contenedor Principal -->
    <div class="dashboard-container">
        <?php if (isset($subscription_warning) && $subscription_warning): ?>
                <div class="alert alert-warning alert-dismissible fade show m-3" role="alert" style="z-index: 1000;">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Atención:</strong> Su suscripción ha vencido o el hospital está inactivo. Contacte al administrador.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
        <?php endif; ?>
        <!-- Header Superior -->
        <header class="dashboard-header">
            <div class="header-content">
                <!-- Botón hamburguesa para móvil -->
                <button class="mobile-toggle" id="mobileSidebarToggle" aria-label="Abrir menú">
                    <i class="bi bi-list"></i>
                </button>

                <!-- logo -->
                <div class="brand-container">
                    <img src="../../assets/img/herrerasaenz.png" alt="Centro Médico Herrera Saenz" class="brand-logo" width="40" height="40" fetchpriority="high"
                        height="40">
                </div>

                <!-- Controles -->
                <div class="header-controls">
                    <!-- Botón PWA: Instalar App -->
                    <button id="installAppBtn" class="install-app-btn" type="button" style="display: none;"
                        aria-label="Instalar aplicación en este dispositivo"
                        title="Instalar app en pantalla de inicio">
                        <i class="bi bi-download"></i>
                        <span class="install-label-full">Instalar App</span>
                        <span class="install-label-short">Instalar</span>
                    </button>

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

                    <!-- Botón de Configuración del Dashboard -->
                    <button class="theme-btn ms-2" onclick="openDashboardConfig()" aria-label="Configurar Dashboard"
                        title="Personalizar Widgets">
                        <i class="bi bi-sliders theme-icon" style="color: var(--color-primary);"></i>
                    </button>
                </div>
            </div>
            <!-- Botón Corte de Turno + Historial -->
            <?php if ($user_type === 'admin'): ?>
                    <div class="shift-cut-btn-container" style="position: absolute; right: 2rem; bottom: -3.5rem; display: flex; gap: 0.5rem;">
                        <button type="button" class="btn btn-info shadow-sm border-0 px-4 py-2 fw-bold"
                            style="border-radius: 50px; background: linear-gradient(135deg, #0dcaf0, #0d6efd); color: #fff;"
                            onclick="openHistoryModal()">
                            <i class="bi bi-clock-history me-2"></i>
                            Historial
                        </button>
                        <button type="button" class="btn btn-warning shadow-sm border-0 px-4 py-2 fw-bold"
                            style="border-radius: 50px; background: linear-gradient(135deg, #ffc107, #ff9800); color: #fff;"
                            onclick="verifyShiftCode()">
                            <i class="bi bi-receipt-cutoff me-2"></i>
                            Corte de Turno
                        </button>
                    </div>
            <?php endif; ?>
        </header>

        <!-- Modal Corte de Turno -->
        <div class="modal fade" id="shiftCutModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-premium">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-warning text-white border-0">
                        <h5 class="modal-title fw-bold">
                            <i class="bi bi-receipt-cutoff me-2"></i>Resumen de Corte de Turno
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label for="shiftDate" class="form-label fw-semibold">Fecha del Turno</label>
                                <input type="date" class="form-control" id="shiftDate"
                                    value="<?php echo date('Y-m-d'); ?>" onchange="loadShiftData()">
                            </div>
                            <div class="col-md-4">
                                <label for="shiftType" class="form-label fw-semibold">Jornada</label>
                                <select class="form-select" id="shiftType" onchange="loadShiftData()">
                                    <option value="morning">Mañana (08:00 AM - 05:00 PM)</option>
                                    <option value="night">Tarde/Noche (05:00 PM - 08:00 AM)</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <div class="w-100 text-end">
                                    <span class="badge bg-warning text-dark fs-6 p-2" id="shiftPeriodLabel">--</span>
                                </div>
                            </div>
                        </div>

                        <div id="shiftLoading" class="text-center py-5">
                            <div class="spinner-grow text-warning" role="status" style="width: 3rem; height: 3rem;">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <p class="mt-3 text-muted tracking-tight">Calculando totales y desgloses...</p>
                        </div>

                        <div id="shiftContent" style="display: none;">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle border-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Categoría</th>
                                            <th class="text-center">Efectivo</th>
                                            <th class="text-center">Tarjeta</th>
                                            <th class="text-center">Transf.</th>
                                            <th class="text-end">Total</th>
                                            <th class="text-end">Costo</th>
                                            <th class="text-end">Ganancia</th>
                                        </tr>
                                    </thead>
                                    <tbody id="shiftTableBody">
                                        <!-- Data will be injected here -->
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-dark">
                                            <th class="fw-bold">TOTAL GENERAL</th>
                                            <td class="text-center"><span id="cut-total-cash">0.00</span></td>
                                            <td class="text-center"><span id="cut-total-card">0.00</span></td>
                                            <td class="text-center"><span id="cut-total-transfer">0.00</span></td>
                                            <td class="text-end fw-bold fs-5">Q<span id="cut-grand-total">0.00</span></td>
                                            <td class="text-end fw-bold fs-5">Q<span id="cut-grand-cost">0.00</span></td>
                                            <td class="text-end fw-bold fs-5">Q<span id="cut-grand-profit">0.00</span></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <div id="consultationBreakdown" class="mt-4" style="display:none;">
                                <h6 class="fw-bold text-muted border-bottom pb-2 mb-3">Detalle de Consultas por Médico
                                </h6>
                                <div id="doctorsList"></div>
                            </div>

                            <!-- Nueva sección: Detalle completo de cobros -->
                            <div class="mt-4">
                                <button class="btn btn-outline-warning w-100 fw-bold" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#shiftDetailCollapse"
                                    aria-expanded="false" aria-controls="shiftDetailCollapse"
                                    onclick="loadShiftDetail(this)">
                                    <i class="bi bi-list-ul me-2"></i>
                                    <span id="shiftDetailBtnLabel">Ver detalle de cobros</span>
                                </button>
                                <div class="collapse mt-3" id="shiftDetailCollapse">
                                    <div id="shiftDetailContent" class="border rounded p-3 bg-light">
                                        <div class="text-center text-muted py-3">
                                            <i class="bi bi-hourglass-split"></i> Cargando...
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cerrar</button>
                        <button type="button" class="btn btn-warning px-4 text-white" onclick="printShiftCutReport()">
                            <i class="bi bi-printer me-2"></i>Imprimir Reporte
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Historial del Turno -->
        <div class="modal fade" id="historyModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-premium">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-info text-white border-0">
                        <h5 class="modal-title fw-bold">
                            <i class="bi bi-clock-history me-2"></i>Historial del Turno Actual
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label for="historyDate" class="form-label fw-semibold">Fecha</label>
                                <input type="date" class="form-control" id="historyDate"
                                    value="<?php echo date('Y-m-d'); ?>" onchange="loadHistoryData()">
                            </div>
                            <div class="col-md-4">
                                <label for="historyShift" class="form-label fw-semibold">Jornada</label>
                                <select class="form-select" id="historyShift" onchange="loadHistoryData()">
                                    <option value="morning">Mañana (08:00 AM - 05:00 PM)</option>
                                    <option value="night">Tarde/Noche (05:00 PM - 08:00 AM)</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <div class="w-100 text-end">
                                    <span class="badge bg-info fs-6 p-2" id="historyPeriodLabel">--</span>
                                </div>
                            </div>
                        </div>

                        <div id="historyLoading" class="text-center py-5">
                            <div class="spinner-grow text-info" role="status" style="width: 3rem; height: 3rem;">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <p class="mt-3 text-muted">Cargando cobros del turno...</p>
                        </div>

                        <div id="historyContent" style="display: none;">
                            <div class="alert alert-info border-0 mb-3" role="alert">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-info-circle me-2"></i>
                                        <strong id="historySummary">0 cobros</strong> en este turno
                                        — <strong>Total: Q<span id="historyGrandTotal">0.00</span></strong>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="reprintAllHistory()">
                                        <i class="bi bi-printer me-1"></i>Reimprimir Todos
                                    </button>
                                </div>
                            </div>

                            <div class="accordion" id="historyAccordion">
                                <!-- Sections injected by JS -->
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Detalle Farmacia -->
        <div class="modal fade" id="pharmacyDetailsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-premium modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold">
                            <i class="bi bi-capsule me-2"></i>Detalle de Ventas - Farmacia
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0" id="pharmacyModalBody">
                        <!-- Content injected by JS -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Escape HTML to prevent XSS in dynamically rendered content
            function escHtml(str) {
                if (!str) return '';
                const div = document.createElement('div');
                div.textContent = str;
                return div.innerHTML;
            }

            // Get CSRF token from meta tag
            function csrfToken() {
                return document.querySelector('meta[name="csrf-token"]')?.content || '';
            }

            // Wrapper for POST fetch that includes CSRF token
            function apiPost(url, body) {
                const csrf = csrfToken();
                if (body instanceof URLSearchParams) {
                    body.append('csrf_token', csrf);
                    return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
                } else if (body instanceof FormData) {
                    body.append('csrf_token', csrf);
                    return fetch(url, { method: 'POST', body });
                } else {
                    return fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                        body: JSON.stringify(body)
                    });
                }
            }

            // Funciones para el Corte de Turno
            function openPharmacyModal() {
                const pharmacyData = window.currentShiftData?.pharmacy;
                if (!pharmacyData) return;

                const modalBody = document.getElementById('pharmacyModalBody');
                if (pharmacyData.details && pharmacyData.details.length > 0) {
                    let html = `
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Hora</th>
                                        <th>Cliente</th>
                                        <th>Medicamento</th>
                                        <th class="text-center">Cant.</th>
                                        <th class="text-end">P. Unit.</th>
                                        <th>Pago</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>`;

                    pharmacyData.details.forEach(item => {
                        const cant = parseFloat(item.cantidad) || 0;
                        const pu = parseFloat(item.precio_unitario) || 0;
                        html += `
                            <tr>
                                <td><code>${escHtml(item.hora)}</code></td>
                                <td class="fw-medium">${escHtml(item.cliente || 'Consumidor Final')}</td>
                                <td>
                                    <div class="fw-medium">${escHtml(item.nom_medicamento || '—')}</div>
                                    <small class="text-muted">${escHtml(item.presentacion_med || item.mol_medicamento || '')}</small>
                                </td>
                                <td class="text-center">${cant}</td>
                                <td class="text-end">Q${pu.toFixed(2)}</td>
                                <td><span class="badge bg-light text-dark border">${escHtml(item.tipo_pago)}</span></td>
                                <td class="text-end fw-bold">Q${parseFloat(item.monto).toFixed(2)}</td>
                            </tr>
                        `;
                    });

                    // Footer con totales
                    const total = parseFloat(pharmacyData.total) || 0;
                    html += `</tbody><tfoot>
                        <tr class="table-dark">
                            <th colspan="6" class="text-end">TOTAL FARMACIA (${pharmacyData.details.length} items):</th>
                            <th class="text-end">Q${total.toFixed(2)}</th>
                        </tr>
                    </tfoot></table></div>`;
                    modalBody.innerHTML = html;
                } else {
                    modalBody.innerHTML = '<div class="alert alert-info">No hay ventas de farmacia registradas en este turno.</div>';
                }

                const modal = new bootstrap.Modal(document.getElementById('pharmacyDetailsModal'));
                modal.show();
            }

            // =================================================================
            // Historial del Turno (Acordeón por categoría con reimprimir)
            // =================================================================
            function openHistoryModal() {
                const modal = new bootstrap.Modal(document.getElementById('historyModal'));
                modal.show();
                loadHistoryData();
            }

            async function loadHistoryData() {
                const date = document.getElementById('historyDate').value;
                const shift = document.getElementById('historyShift').value;
                document.getElementById('historyLoading').style.display = 'block';
                document.getElementById('historyContent').style.display = 'none';

                try {
                    const response = await fetch(`get_history_data.php?date=${date}&shift=${shift}`, {
                        headers: { 'X-Requested-With': 'fetch' }
                    });
                    const result = await response.json();
                    if (!result.success) {
                        throw new Error(result.error || 'Error al cargar el historial');
                    }
                    renderHistoryData(result.data);
                } catch (e) {
                    Swal.fire('Error', e.message || 'No se pudo cargar el historial', 'error');
                } finally {
                    document.getElementById('historyLoading').style.display = 'none';
                    document.getElementById('historyContent').style.display = 'block';
                }
            }

            function renderHistoryData(data) {
                const periodLabel = `${data.period.shift === 'morning' ? 'Mañana' : 'Noche'}: ${data.period.start} → ${data.period.end}`;
                document.getElementById('historyPeriodLabel').textContent = periodLabel;
                document.getElementById('historySummary').textContent = `${data.count} cobros`;
                document.getElementById('historyGrandTotal').textContent = data.grand_total.toFixed(2);

                const categories = [
                    { key: 'consultations', label: 'Consultas',     icon: 'bi-clipboard2-pulse', color: 'success' },
                    { key: 'pharmacy',      label: 'Farmacia',      icon: 'bi-capsule',          color: 'primary' },
                    { key: 'laboratory',    label: 'Laboratorio',   icon: 'bi-virus',            color: 'info' },
                    { key: 'ultrasound',    label: 'Ultrasonidos',  icon: 'bi-soundwave',        color: 'warning' },
                    { key: 'xray',          label: 'Rayos X',       icon: 'bi-broadcast',        color: 'secondary' },
                    { key: 'electro',       label: 'Electrocardiogramas', icon: 'bi-heart-pulse', color: 'danger' },
                    { key: 'procedures',    label: 'Procedimientos Menores', icon: 'bi-bandaid',   color: 'dark' }
                ];

                const accordion = document.getElementById('historyAccordion');
                accordion.innerHTML = '';

                let index = 0;
                categories.forEach(cat => {
                    const rows = data[cat.key] || [];
                    const total = data.totals[cat.key] || 0;
                    if (rows.length === 0 && total === 0) {
                        return; // skip empty categories
                    }
                    const collapseId = `history-collapse-${cat.key}`;
                    const headerId  = `history-header-${cat.key}`;
                    const isFirst   = (index === 0);
                    const rowsHtml  = rows.length === 0
                        ? '<div class="text-muted text-center py-3"><i class="bi bi-info-circle"></i> Sin cobros en esta categoría</div>'
                        : renderHistoryRows(rows);

                    const html = `
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="${headerId}">
                            <button class="accordion-button ${isFirst ? '' : 'collapsed'}" type="button"
                                data-bs-toggle="collapse" data-bs-target="#${collapseId}"
                                aria-expanded="${isFirst}" aria-controls="${collapseId}">
                                <i class="${cat.icon} me-2"></i>
                                <strong class="me-2">${cat.label}</strong>
                                <span class="badge bg-${cat.color} me-2">${rows.length} cobros</span>
                                <span class="text-muted ms-auto">Q${total.toFixed(2)}</span>
                            </button>
                        </h2>
                        <div id="${collapseId}" class="accordion-collapse collapse ${isFirst ? 'show' : ''}"
                            aria-labelledby="${headerId}" data-bs-parent="#historyAccordion">
                            <div class="accordion-body p-0">
                                ${rowsHtml}
                            </div>
                        </div>
                    </div>`;
                    accordion.insertAdjacentHTML('beforeend', html);
                    index++;
                });

                if (index === 0) {
                    accordion.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-inbox"></i> No hay cobros en este turno.</div>';
                }
            }

            function renderHistoryRows(rows) {
                let html = `<div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 80px;">Hora</th>
                            <th>Paciente</th>
                            <th>Doctor</th>
                            <th>Detalle</th>
                            <th style="width: 110px;">Pago</th>
                            <th class="text-end" style="width: 100px;">Monto</th>
                            <th class="text-center" style="width: 90px;">Acción</th>
                        </tr>
                    </thead><tbody>`;
                rows.forEach(r => {
                    const pago = (r.tipo_pago || 'Efectivo');
                    const pagoBadge = pago === 'Efectivo' ? 'success' : (pago === 'Tarjeta' ? 'primary' : 'info');
                    const paciente = r.paciente || '<em class="text-muted">N/A</em>';
                    const doctor    = r.doctor    || '<em class="text-muted">N/A</em>';
                    const detalle   = r.detalle   || '<em class="text-muted">N/A</em>';
                    const printUrl  = r.print_url || '#';
                    const printBtn  = (printUrl && printUrl !== '#')
                        ? `<button type="button" class="btn btn-sm btn-outline-info" onclick="window.open('${printUrl}', '_blank')" title="Reimprimir comprobante">
                                <i class="bi bi-printer"></i>
                            </button>`
                        : '<span class="text-muted small">N/D</span>';
                    html += `<tr>
                        <td><code>${r.hora || ''}</code></td>
                        <td>${paciente}</td>
                        <td>${doctor}</td>
                        <td><small>${detalle}</small></td>
                        <td><span class="badge bg-${pagoBadge}">${pago}</span></td>
                        <td class="text-end fw-bold">Q${(parseFloat(r.monto) || 0).toFixed(2)}</td>
                        <td class="text-center">${printBtn}</td>
                    </tr>`;
                });
                html += '</tbody></table></div>';
                return html;
            }

            function reprintAllHistory() {
                const printBtns = document.querySelectorAll('#historyContent .btn-outline-info[title="Reimprimir comprobante"]');
                if (printBtns.length === 0) {
                    Swal.fire('Sin comprobantes', 'No hay comprobantes para reimprimir en este turno.', 'info');
                    return;
                }
                Swal.fire({
                    title: '¿Reimprimir todos?',
                    text: `Se abrirán ${printBtns.length} ventanas con los comprobantes.`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, reimprimir',
                    cancelButtonText: 'Cancelar'
                }).then((r) => {
                    if (!r.isConfirmed) return;
                    printBtns.forEach((btn, i) => {
                        setTimeout(() => btn.click(), i * 500);
                    });
                });
            }

            async function loadShiftDetail(btn) {
                const collapse = document.getElementById('shiftDetailCollapse');
                const isExpanded = collapse.classList.contains('show');
                const label = document.getElementById('shiftDetailBtnLabel');
                const content = document.getElementById('shiftDetailContent');

                if (!isExpanded) {
                    label.textContent = 'Ocultar detalle de cobros';
                    if (content.dataset.loaded === '1') return; // already loaded
                    content.innerHTML = '<div class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm"></div> Cargando detalle...</div>';
                    const date = document.getElementById('shiftDate').value;
                    const shift = document.getElementById('shiftType').value;
                    try {
                        const response = await fetch(`get_history_data.php?date=${date}&shift=${shift}`, {
                            headers: { 'X-Requested-With': 'fetch' }
                        });
                        const result = await response.json();
                        if (!result.success) throw new Error(result.error || 'Error');
                        content.innerHTML = renderShiftDetailContent(result.data);
                        content.dataset.loaded = '1';
                    } catch (e) {
                        content.innerHTML = '<div class="text-danger text-center py-3">' + (e.message || 'Error') + '</div>';
                    }
                } else {
                    label.textContent = 'Ver detalle de cobros';
                }
            }

            function renderShiftDetailContent(data) {
                const allRows = [
                    ...data.consultations.map(r => ({...r, _cat: 'Consultas'})),
                    ...data.pharmacy.map(r => ({...r, _cat: 'Farmacia'})),
                    ...data.laboratory.map(r => ({...r, _cat: 'Laboratorio'})),
                    ...data.ultrasound.map(r => ({...r, _cat: 'Ultrasonidos'})),
                    ...data.xray.map(r => ({...r, _cat: 'Rayos X'})),
                    ...data.electro.map(r => ({...r, _cat: 'Electrocardiogramas'})),
                    ...data.procedures.map(r => ({...r, _cat: 'Procedimientos'}))
                ];
                allRows.sort((a, b) => (a.fecha_full || '').localeCompare(b.fecha_full || ''));

                if (allRows.length === 0) {
                    return '<div class="text-center text-muted py-3"><i class="bi bi-inbox"></i> No hay cobros en este turno.</div>';
                }

                let html = `<div class="table-responsive"><table class="table table-sm table-striped mb-0 align-middle">
                    <thead class="table-warning">
                        <tr>
                            <th style="width: 90px;">Hora</th>
                            <th style="width: 110px;">Categoría</th>
                            <th>Paciente</th>
                            <th>Doctor</th>
                            <th>Detalle / Tipo</th>
                            <th style="width: 90px;">Pago</th>
                            <th class="text-end" style="width: 100px;">Monto</th>
                            <th class="text-center" style="width: 80px;">Reimp.</th>
                        </tr>
                    </thead><tbody>`;
                allRows.forEach(r => {
                    const pagoBadge = (r.tipo_pago === 'Efectivo') ? 'success' : ((r.tipo_pago === 'Tarjeta') ? 'primary' : 'info');
                    const printBtn = (r.print_url && r.print_url !== '#')
                        ? `<button type="button" class="btn btn-sm btn-outline-warning" onclick="window.open('${r.print_url}', '_blank')" title="Reimprimir"><i class="bi bi-printer"></i></button>`
                        : '';
                    html += `<tr>
                        <td><code>${r.hora || ''}</code></td>
                        <td><small class="text-muted">${r._cat}</small></td>
                        <td>${r.paciente || '<em class="text-muted">N/A</em>'}</td>
                        <td>${r.doctor || '<em class="text-muted">N/A</em>'}</td>
                        <td><small>${r.detalle || 'N/A'}</small></td>
                        <td><span class="badge bg-${pagoBadge}">${r.tipo_pago || 'Efectivo'}</span></td>
                        <td class="text-end fw-bold">Q${(parseFloat(r.monto) || 0).toFixed(2)}</td>
                        <td class="text-center">${printBtn}</td>
                    </tr>`;
                });
                html += `</tbody><tfoot>
                    <tr class="table-dark">
                        <th colspan="6" class="text-end">TOTAL DETALLE (${allRows.length} cobros):</th>
                        <th class="text-end fs-6">Q${data.grand_total.toFixed(2)}</th>
                        <th></th>
                    </tr>
                </tfoot></table></div>`;
                return html;
            }

            function printShiftCutReport() {
                const modal = document.getElementById('shiftCutModal');
                const content = document.getElementById('shiftContent');
                if (!content || content.style.display === 'none') {
                    Swal.fire('Sin datos', 'No hay datos de corte para imprimir.', 'info');
                    return;
                }
                const printContents = modal.querySelector('.modal-content').innerHTML;
                const win = window.open('', '_blank', 'width=1200,height=900');
                win.document.write(`
                    <!DOCTYPE html><html><head>
                    <title>Corte de Turno</title>
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
                    <style>
                        body { padding: 20px; font-family: -apple-system, sans-serif; }
                        @media print { body { padding: 0; } }
                    </style>
                    </head><body>${printContents}</body></html>
                `);
                win.document.close();
                setTimeout(() => { win.print(); }, 400);
            }

            async function verifyShiftCode() {
                const { value: code } = await Swal.fire({
                    title: 'Código de Seguridad',
                    text: 'Ingrese el código para autorizar el corte de turno',
                    input: 'password',
                    confirmButtonColor: '#ffc107',
                    inputPlaceholder: 'Ingrese su código',
                    inputAttributes: {
                        autocapitalize: 'off',
                        autocorrect: 'off'
                    }
                });

                if (code === '<?php echo $shift_auth_code; ?>') {
                    openShiftCutModal();
                } else if (code) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Código Incorrecto',
                        text: 'El código ingresado no es válido para esta operación.',
                        confirmButtonColor: '#ffc107'
                    });
                }
            }

            function openShiftCutModal() {
                const modal = new bootstrap.Modal(document.getElementById('shiftCutModal'));
                modal.show();
                loadShiftData();
            }

            function loadShiftData() {
                const date = document.getElementById('shiftDate').value;
                const shift = document.getElementById('shiftType').value;
                const loading = document.getElementById('shiftLoading');
                const content = document.getElementById('shiftContent');
                const tableBody = document.getElementById('shiftTableBody');

                loading.style.display = 'block';
                content.style.display = 'none';

                fetch(`get_shift_cut_data.php?date=${date}&shift=${shift}`)
                    .then(async response => {
                        const contentType = response.headers.get("content-type");
                        if (contentType && contentType.indexOf("application/json") !== -1) {
                            return response.json();
                        } else {
                            const text = await response.text();
                            throw new Error("Respuesta no válida del servidor: " + text.substring(0, 100));
                        }
                    })
                    .then(data => {
                        if (data.success) {
                            const d = data.data;
                            window.currentShiftData = d; // Store for modal access

                            // Helper para renderizar tabla de detalles
                            const renderDetailsTable = (details, typeId) => {
                                if (!details || details.length === 0) return '<div class="text-muted small fst-italic p-2">No hay transacciones</div>';

                                let html = `
                                        <div class="table-responsive mt-2">
                                            <table class="table table-sm table-bordered mb-0" style="font-size: 0.85rem;">
                                                <thead class="bg-light">
                                                    <tr>
                                                        <th>Hora</th>
                                                        <th>Paciente/Cliente</th>
                                                        <th>Tipo Pago</th>
                                                        <th class="text-end">Monto</th>
                                                    </tr>
                                                </thead>
                                                <tbody>`;

                                const methodConfig = {
                                    'Efectivo': { color: 'text-success', icon: 'bi-cash-coin' },
                                    'Tarjeta': { color: 'text-primary', icon: 'bi-credit-card' },
                                    'Transferencia': { color: 'text-info', icon: 'bi-bank' },
                                    'Traslado': { color: 'text-warning', icon: 'bi-arrow-left-right' }
                                };

                                details.forEach(item => {
                                    const mConfig = methodConfig[item.tipo_pago] || { color: 'text-secondary', icon: 'bi-circle' };
                                    html += `
                                            <tr>
                                                <td>${escHtml(item.hora)}</td>
                                                <td>
                                                    <div class="fw-medium">${escHtml(item.paciente || item.cliente || 'Desconocido')}</div>
                                                    ${item.detalle ? `<div class="text-muted mt-1" style="font-size: 0.75rem;"><i class="bi bi-box-seam me-1"></i>${escHtml(item.detalle)}</div>` : ''}
                                                </td>
                                                <td><i class="bi ${mConfig.icon} ${mConfig.color}"></i> ${escHtml(item.tipo_pago)}</td>
                                                <td class="text-end fw-bold">Q${parseFloat(item.monto || item.cobro || 0).toFixed(2)}</td>
                                            </tr>`;
                                });

                                html += `</tbody></table></div>`;
                                return html;
                            };

                            // Build main table
                            const categories = [
                                { id: 'pharmacy', label: 'Farmacia', data: d.pharmacy, icon: 'bi-capsule text-primary' },
                                { id: 'consultations', label: 'Consultas', data: d.consultations, icon: 'bi-person-video text-success' },
                                { id: 'laboratory', label: 'Laboratorio', data: d.laboratory, icon: 'bi-eyedropper text-danger' },
                                { id: 'procedures', label: 'Procedimientos', data: d.procedures, icon: 'bi-bandaid text-warning' },
                                { id: 'ultrasound', label: 'Ultrasonidos', data: d.ultrasound, icon: 'bi-wifi text-info' },
                                { id: 'xray', label: 'Rayos X', data: d.xray, icon: 'bi-file-medical text-secondary' },
                                { id: 'electro', label: 'Electrocardiogramas', data: d.electro, icon: 'bi-heart-pulse text-danger' },
                                { id: 'hospitalization', label: 'Hospitalización', data: d.hospitalization, icon: 'bi-hospital text-danger' }
                            ];

                            tableBody.innerHTML = '';

                            categories.forEach(cat => {
                                if (!cat.data) return;
                                const total = parseFloat(cat.data.total || 0);
                                const collapseId = `collapse-${cat.id}`;

                                let actionBtn = '';
                                if (cat.id === 'pharmacy') {
                                    actionBtn = `<button class="btn btn-sm btn-link text-decoration-none p-0 mt-1" type="button" 
                                        onclick="openPharmacyModal()">
                                        <i class="bi bi-eye"></i> Ver detalles (Modal)
                                    </button>`;
                                } else {
                                    actionBtn = `<button class="btn btn-sm btn-link text-decoration-none p-0 mt-1" type="button" 
                                        data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="false">
                                        <i class="bi bi-eye"></i> Ver detalles
                                    </button>`;
                                }

                                const row = document.createElement('tr');
                                const catCost = parseFloat(cat.data.cost || 0);
                                const catProfit = total - catCost;
                                row.innerHTML = `
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="bi ${cat.icon} fs-5 me-2"></i>
                                                <span class="fw-bold">${cat.label}</span>
                                            </div>
                                            ${actionBtn}
                                            <div class="collapse mt-2" id="${collapseId}">
                                                <div class="card card-body p-2 bg-light border-0">
                                                    ${renderDetailsTable(cat.data.details, cat.id)}
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end">Q${(cat.data.breakdown?.Efectivo || 0).toFixed(2)}</td>
                                        <td class="text-end">Q${(cat.data.breakdown?.Tarjeta || 0).toFixed(2)}</td>
                                        <td class="text-end">Q${(cat.data.breakdown?.Transferencia || 0).toFixed(2)}</td>
                                        <td class="text-end fw-bold bg-light">Q${total.toFixed(2)}</td>
                                        <td class="text-end bg-light">${catCost > 0 ? 'Q' + catCost.toFixed(2) : '<span class="text-muted small">N/D</span>'}</td>
                                        <td class="text-end fw-bold bg-light">${catCost > 0 ? 'Q' + catProfit.toFixed(2) : '<span class="text-muted small">N/D</span>'}</td>
                                    `;
                                tableBody.appendChild(row);
                            });

                            // Update Tables Footer (Grand Totals)
                            const grandTotal = d.grand_total || 0;
                            let totalCash = 0, totalCard = 0, totalTransfer = 0;
                            categories.forEach(cat => {
                                if (cat.data) {
                                    totalCash += cat.data.breakdown?.Efectivo || 0;
                                    totalCard += cat.data.breakdown?.Tarjeta || 0;
                                    totalTransfer += cat.data.breakdown?.Transferencia || 0;
                                }
                            });

                            const safeSetText = (id, val) => {
                                const el = document.getElementById(id);
                                if (el) el.textContent = val;
                            };

                            safeSetText('totalCash', `Q${totalCash.toFixed(2)}`);
                            safeSetText('totalCard', `Q${totalCard.toFixed(2)}`);
                            safeSetText('totalTransfer', `Q${totalTransfer.toFixed(2)}`);
                            safeSetText('totalGlobal', `Q${grandTotal.toFixed(2)}`);
                            // Also update specific IDs from the new modal-premium layout
                            safeSetText('cut-grand-total', grandTotal.toFixed(2));
                            safeSetText('cut-total-cash', totalCash.toFixed(2));
                            safeSetText('cut-total-card', totalCard.toFixed(2));
                            safeSetText('cut-total-transfer', totalTransfer.toFixed(2));
                            // Cost / profit (from tarifas_servicios JOIN, served by get_shift_cut_data.php)
                            const grandCost = parseFloat(d.grand_cost) || 0;
                            const grandProfit = parseFloat(d.grand_profit) || (grandTotal - grandCost);
                            safeSetText('cut-grand-cost', grandCost.toFixed(2));
                            safeSetText('cut-grand-profit', grandProfit.toFixed(2));
                            // Period label badge
                            if (d.period) {
                                const periodLabel = `${d.period.shift === 'morning' ? 'Mañana' : 'Noche'}: ${d.period.start} → ${d.period.end}`;
                                safeSetText('shiftPeriodLabel', periodLabel);
                            }

                            // Doctors Breakdown
                            const breakdownContainer = document.getElementById('consultationBreakdown');
                            if (d.consultations.by_doctor && d.consultations.by_doctor.length > 0) {
                                if (breakdownContainer) {
                                    let breakdownHtml = `
                                            <div class="card border-0 shadow-sm mt-4">
                                                <div class="card-header bg-success text-white">
                                                    <h6 class="mb-0"><i class="bi bi-people me-2"></i>Desglose por Médico</h6>
                                                </div>
                                                <div class="card-body p-0">
                                                    <table class="table table-striped mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th>Médico</th>
                                                                <th class="text-end">Efectivo</th>
                                                                <th class="text-end">Tarjeta</th>
                                                                <th class="text-end">Transferencia</th>
                                                                <th class="text-end">Total</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>`;

                                    d.consultations.by_doctor.forEach(doc => {
                                        breakdownHtml += `
                                                <tr>
                                                    <td><i class="bi bi-person-badge fs-6 me-2 text-muted"></i>${escHtml(doc.doctor)}</td>
                                                    <td class="text-end">Q${(doc.breakdown.Efectivo || 0).toFixed(2)}</td>
                                                    <td class="text-end">Q${(doc.breakdown.Tarjeta || 0).toFixed(2)}</td>
                                                    <td class="text-end">Q${(doc.breakdown.Transferencia || 0).toFixed(2)}</td>
                                                    <td class="text-end fw-bold">Q${doc.total.toFixed(2)}</td>
                                                </tr>`;
                                    });

                                    breakdownHtml += '</tbody></table></div></div>';
                                    breakdownContainer.innerHTML = breakdownHtml;
                                    breakdownContainer.style.display = 'block';
                                }

                                // Update legacy container if it exists
                                const legDocs = document.getElementById('doctorsList');
                                if (legDocs) {
                                    // Keeping backward compatibility if cleaner implementation didn't fully replace DOM structure
                                    legDocs.innerHTML = '';
                                }
                            } else {
                                if (breakdownContainer) breakdownContainer.style.display = 'none';
                            }

                            loading.style.display = 'none';
                            content.style.display = 'block';
                        } else {
                            Swal.fire('Error', 'Error al cargar datos: ' + (data.error || 'Desconocido'), 'error');
                            loading.style.display = 'none';
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        Swal.fire('Error', 'Error de conexión', 'error');
                        loading.style.display = 'none';
                    });
            }
        </script>

        <!-- Botón para colapsar/expandir sidebar (solo escritorio) -->
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Colapsar/expandir menú">
            <i class="bi bi-chevron-left" id="sidebarToggleIcon"></i>
        </button>

        <!-- Contenido Principal -->
        <main class="main-content">
            <!-- Notificación de compras pendientes -->
            <?php if ($pending_purchases > 0 && $user_type === 'user'): ?>
                    <div class="alert-card mb-4 animate-in delay-1">
                        <div class="alert-header">
                            <div class="alert-icon warning">
                                <i class="bi bi-box-seam"></i>
                            </div>
                            <h3 class="alert-title">Compras Pendientes</h3>
                        </div>
                        <p class="text-muted mb-0">
                            Hay <strong><?php echo $pending_purchases; ?></strong> productos por recibir en inventario.
                            <a href="../inventory/index.php" class="text-primary text-decoration-none ms-1">
                                Revisar inventario <i class="bi bi-arrow-right"></i>
                            </a>
                        </p>
                    </div>
            <?php endif; ?>

            <!-- Bienvenida personalizada -->
            <div class="stat-card mb-4 animate-in">
                <div class="stat-header">
                    <div>
                        <h2 id="greeting" class="stat-value" style="font-size: 1.75rem; margin-bottom: 0.5rem;">
                            <span id="greeting-text">Buenos días</span>, <?php echo htmlspecialchars($user_name); ?>
                        </h2>
                        <p class="text-muted mb-0 greeting-meta">
                            <i class="bi bi-calendar-check me-1"></i> <?php echo date('d/m/Y'); ?>
                            <i class="bi bi-clock mx-2"></i> <span id="current-time"><?php echo date('H:i'); ?></span>
                            <i class="bi bi-building mx-2"></i> Centro Médico Herrera Saenz
                        </p>
                    </div>
                    <div class="d-none d-md-block">
                        <i class="bi bi-heart-pulse text-primary" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>

            <!-- Acciones Rápidas -->
            <?php if ($user_type === 'user' || $user_type === 'admin' || $user_type === 'doc' && $show_quick_actions): ?>
                    <div class="stats-grid mb-4 animate-in delay-1" id="widget-quick-actions">
                        <a href="#" class="stat-card" data-bs-toggle="modal" data-bs-target="#newBillingModal"
                            style="text-decoration: none; border-left: 4px solid var(--color-success);">
                            <div class="stat-header mb-0">
                                <div>
                                    <div class="stat-title text-success fw-bold">Cobros</div>
                                    <div class="stat-value" style="font-size: 1.25rem;">Registrar Cobro</div>
                                </div>
                                <div class="stat-icon success">
                                    <i class="bi bi-cash-coin"></i>
                                </div>
                            </div>
                        </a>
                        <a href="#" class="stat-card" data-bs-toggle="modal" data-bs-target="#electroBillingModal"
                            style="text-decoration: none; border-left: 4px solid var(--color-danger);">
                            <div class="stat-header mb-0">
                                <div>
                                    <div class="stat-title text-danger fw-bold">Electro</div>
                                    <div class="stat-value" style="font-size: 1.25rem;">Cobrar Electro</div>
                                </div>
                                <div class="stat-icon danger">
                                    <i class="bi bi-heart-pulse"></i>
                                </div>
                            </div>
                        </a>
                        <a href="#" class="stat-card" data-bs-toggle="modal" data-bs-target="#newLabOrderModal"
                            style="text-decoration: none; border-left: 4px solid var(--color-info);">
                            <div class="stat-header mb-0">
                                <div>
                                    <div class="stat-title text-info fw-bold">Laboratorio</div>
                                    <div class="stat-value" style="font-size: 1.25rem;">Nueva Orden</div>
                                </div>
                                <div class="stat-icon info">
                                    <i class="bi bi-virus"></i>
                                </div>
                            </div>
                        </a>
                        <a href="#" class="stat-card" data-bs-toggle="modal" data-bs-target="#procedureBillingModal"
                            style="text-decoration: none; border-left: 4px solid var(--color-warning);">
                            <div class="stat-header mb-0">
                                <div>
                                    <div class="stat-title text-warning fw-bold">Procedimientos</div>
                                    <div class="stat-value" style="font-size: 1.25rem;">Cobro Proc.</div>
                                </div>
                                <div class="stat-icon warning">
                                    <i class="bi bi-bandaid"></i>
                                </div>
                            </div>
                        </a>
                        <a href="#" class="stat-card" data-bs-toggle="modal" data-bs-target="#xrayBillingModal"
                            style="text-decoration: none; border-left: 4px solid var(--color-secondary);">
                            <div class="stat-header mb-0">
                                <div>
                                    <div class="stat-title text-secondary fw-bold">Rayos X</div>
                                    <div class="stat-value" style="font-size: 1.25rem;">Cobro RX</div>
                                </div>
                                <div class="stat-icon secondary">
                                    <i class="bi bi-file-medical"></i>
                                </div>
                            </div>
                        </a>
                        <a href="#" class="stat-card" data-bs-toggle="modal" data-bs-target="#ultrasoundBillingModal"
                            style="text-decoration: none; border-left: 4px solid var(--color-primary);">
                            <div class="stat-header mb-0">
                                <div>
                                    <div class="stat-title text-primary fw-bold">Ultrasonido</div>
                                    <div class="stat-value" style="font-size: 1.25rem;">Cobro US</div>
                                </div>
                                <div class="stat-icon primary">
                                    <i class="bi bi-activity"></i>
                                </div>
                            </div>
                        </a>
                    </div>
            <?php endif; ?>

            <!-- Estadísticas principales -->
            <?php if ($user_type === 'admin' && $show_stats): ?>
                    <div class="stats-grid" id="widget-stats">
                        <!-- Citas de hoy -->
                        <div class="stat-card animate-in delay-1">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-title">Citas Hoy</div>
                                    <div class="stat-value"><?php echo $today_appointments; ?></div>
                                </div>
                                <div class="stat-icon primary">
                                    <i class="bi bi-calendar-check"></i>
                                </div>
                            </div>
                            <div class="stat-change positive">
                                <i class="bi bi-arrow-up-right"></i>
                                <span>Programadas para hoy</span>
                            </div>
                        </div>

                        <!-- Pacientes del año -->
                        <div class="stat-card animate-in delay-2">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-title">Pacientes Año</div>
                                    <div class="stat-value"><?php echo $year_patients; ?></div>
                                </div>
                                <div class="stat-icon success">
                                    <i class="bi bi-people"></i>
                                </div>
                            </div>
                            <div class="stat-change positive">
                                <i class="bi bi-person-plus"></i>
                                <span>Año <?php echo date('Y'); ?></span>
                            </div>
                        </div>

                        <!-- Citas pendientes -->
                        <div class="stat-card animate-in delay-3">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-title">Citas Pendientes</div>
                                    <div class="stat-value"><?php echo $pending_appointments; ?></div>
                                </div>
                                <div class="stat-icon warning">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                            </div>
                            <div class="stat-change positive">
                                <i class="bi bi-calendar-plus"></i>
                                <span>Próximas citas</span>
                            </div>
                        </div>

                        <!-- Consultas del mes -->
                        <div class="stat-card animate-in delay-4">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-title">Consultas Mes</div>
                                    <div class="stat-value"><?php echo $month_consultations; ?></div>
                                </div>
                                <div class="stat-icon info">
                                    <i class="bi bi-graph-up-arrow"></i>
                                </div>
                            </div>
                            <div class="stat-change positive">
                                <i class="bi bi-calendar-month"></i>
                                <span>Mes actual</span>
                            </div>
                        </div>
                    </div>
            <?php endif; ?>

            <!-- Sección de citas de hoy -->
            <?php if ($show_appointments): ?>
                    <section class="appointments-section animate-in delay-1" id="widget-appointments">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="bi bi-calendar-day section-title-icon"></i>
                                Citas de Hoy
                            </h3>
                            <a href="../appointments/index.php" class="action-btn">
                                <i class="bi bi-plus-lg"></i>
                                Nueva Cita
                            </a>
                        </div>

                        <?php if (count($todays_appointments) > 0): ?>
                                <div class="table-responsive">
                                    <table class="appointments-table">
                                        <thead>
                                            <tr>
                                                <th>Paciente</th>
                                                <th>Hora</th>
                                                <th>Contacto</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($todays_appointments as $appointment): ?>
                                                    <?php
                                                    $patient_name = htmlspecialchars($appointment['nombre_pac'] . ' ' . $appointment['apellido_pac']);
                                                    $patient_initials = strtoupper(
                                                        substr($appointment['nombre_pac'], 0, 1) .
                                                        substr($appointment['apellido_pac'], 0, 1)
                                                    );
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <div class="patient-cell">
                                                                <div class="patient-avatar">
                                                                    <?php echo $patient_initials; ?>
                                                                </div>
                                                                <div class="patient-info">
                                                                    <div class="patient-name"><?php echo $patient_name; ?></div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="time-badge">
                                                                <i class="bi bi-clock"></i>
                                                                <?php echo htmlspecialchars($appointment['hora_cita']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="patient-contact">
                                                                <?php echo htmlspecialchars($appointment['telefono'] ?? 'No disponible'); ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="action-buttons">
                                                                <a href="#" class="btn-icon history check-patient" title="Ver historial"
                                                                    data-nombre="<?php echo htmlspecialchars($appointment['nombre_pac']); ?>"
                                                                    data-apellido="<?php echo htmlspecialchars($appointment['apellido_pac']); ?>">
                                                                    <i class="bi bi-file-medical"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                        <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="bi bi-calendar-x"></i>
                                    </div>
                                    <h4 class="text-muted mb-2">No hay citas programadas para hoy</h4>
                                    <p class="text-muted mb-3">Total de citas en sistema: <?php echo $total_appointments; ?></p>
                                </div>
                        <?php endif; ?>
                    </section>
            <?php endif; ?>

            <?php if ($user_type === 'admin' && $show_hospitalized): ?>
                    <!-- Sección de Hospitalización -->
                    <section class="appointments-section animate-in delay-2" id="widget-hospitalized">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="bi bi-hospital text-primary section-title-icon"></i>
                                Pacientes Hospitalizados
                            </h3>
                            <div class="d-flex gap-2">
                                <div class="badge bg-primary d-flex align-items-center p-2">
                                    <i class="bi bi-people-fill me-2"></i>
                                    <?php echo $active_hospitalizations; ?> Activos
                                </div>
                                <div class="badge bg-success d-flex align-items-center p-2">
                                    <i class="bi bi-hospital-fill me-2"></i>
                                    <?php echo $available_beds_count; ?> Camas Disp.
                                </div>
                            </div>
                        </div>

                        <?php if (count($hospitalized_patients) > 0): ?>
                                <div class="table-responsive">
                                    <table class="appointments-table">
                                        <thead>
                                            <tr>
                                                <th>Paciente</th>
                                                <th>Habitación</th>
                                                <th>Ingreso</th>
                                                <th>Diagnóstico</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($hospitalized_patients as $hosp): ?>
                                                    <?php
                                                    $patient_name = htmlspecialchars($hosp['nombre'] . ' ' . $hosp['apellido']);
                                                    $patient_initials = strtoupper(
                                                        substr($hosp['nombre'], 0, 1) .
                                                        substr($hosp['apellido'], 0, 1)
                                                    );
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <div class="patient-cell">
                                                                <div class="patient-avatar" style="background: var(--color-secondary);">
                                                                    <?php echo $patient_initials; ?>
                                                                </div>
                                                                <div class="patient-info">
                                                                    <div class="patient-name"><?php echo $patient_name; ?></div>
                                                                    <small class="text-muted">ID:
                                                                        #<?php echo $hosp['id_encamamiento']; ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-info text-white">
                                                                Hab. <?php echo htmlspecialchars($hosp['numero_habitacion']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php echo date('d/m/Y', strtotime($hosp['fecha_ingreso'])); ?>
                                                            <br>
                                                            <small
                                                                class="text-muted"><?php echo date('H:i', strtotime($hosp['fecha_ingreso'])); ?></small>
                                                        </td>
                                                        <td>
                                                            <small class="d-block text-truncate" style="max-width: 150px;">
                                                                <?php echo htmlspecialchars($hosp['diagnostico_ingreso']); ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <a href="../hospitalization/detalle_encamamiento.php?id=<?php echo $hosp['id_encamamiento']; ?>"
                                                                class="btn-icon" title="Ver detalles"
                                                                style="color: var(--color-primary); border-color: var(--color-primary);">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-3 text-center">
                                    <a href="../hospitalization/index.php" class="text-primary text-decoration-none">
                                        Ver todos los pacientes hospitalizados <i class="bi bi-arrow-right"></i>
                                    </a>
                                </div>
                        <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="bi bi-hospital"></i>
                                    </div>
                                    <h4 class="text-muted mb-2">No hay hospitalizaciones activas</h4>
                                    <p class="text-muted mb-3">Todas las camas están disponibles</p>
                                    <a href="../hospitalization/ingresar_paciente.php" class="action-btn">
                                        <i class="bi bi-plus-lg"></i>
                                        Ingresar Paciente
                                    </a>
                                </div>
                        <?php endif; ?>
                    </section>
            <?php endif; ?>

            <!-- Panel de alertas -->
            <?php if ($show_alerts): ?>
                    <div class="alerts-grid animate-in delay-3" id="widget-alerts">
                        <!-- Medicamentos por caducar -->
                        <div class="alert-card">
                            <div class="alert-header">
                                <div class="alert-icon warning">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </div>
                                <h3 class="alert-title">Caducidad Próxima</h3>
                            </div>

                            <?php if (count($expiring_medications) > 0): ?>
                                    <ul class="alert-list">
                                        <?php foreach (array_slice($expiring_medications, 0, 5) as $medication): ?>
                                                <?php
                                                $expiry_date = new DateTime($medication['fecha_vencimiento']);
                                                $today = new DateTime();
                                                $days_diff = $today->diff($expiry_date)->days;
                                                $is_expired = $expiry_date < $today;
                                                ?>
                                                <li class="alert-item">
                                                    <div class="alert-item-header">
                                                        <span
                                                            class="alert-item-name"><?php echo htmlspecialchars($medication['nom_medicamento']); ?></span>
                                                        <span class="alert-badge <?php echo $is_expired ? 'expired' : 'warning'; ?>">
                                                            <?php echo $is_expired ? 'Vencido' : $days_diff . ' días'; ?>
                                                        </span>
                                                    </div>
                                                    <div class="alert-item-details">
                                                        <span>Vence: <?php echo $expiry_date->format('d/m/Y'); ?></span>
                                                        <span>Stock: <?php echo $medication['cantidad_med']; ?></span>
                                                    </div>
                                                </li>
                                        <?php endforeach; ?>
                                    </ul>

                                    <?php if (count($expiring_medications) > 5): ?>
                                            <div class="text-center mt-3">
                                                <a href="../inventory/index.php?filter=expiring" class="text-primary text-decoration-none">
                                                    Ver todas (<?php echo count($expiring_medications); ?>) <i class="bi bi-arrow-right"></i>
                                                </a>
                                            </div>
                                    <?php endif; ?>
                            <?php else: ?>
                                    <div class="no-alerts">
                                        <div class="no-alerts-icon">
                                            <i class="bi bi-check-circle"></i>
                                        </div>
                                        <p class="text-muted mb-0">Sin medicamentos próximos a caducar</p>
                                    </div>
                            <?php endif; ?>
                        </div>

                        <!-- Stock bajo -->
                        <div class="alert-card">
                            <div class="alert-header">
                                <div class="alert-icon danger">
                                    <i class="bi bi-arrow-down-circle"></i>
                                </div>
                                <h3 class="alert-title">Stock Bajo</h3>
                            </div>

                            <?php if (count($low_stock_medications) > 0): ?>
                                    <ul class="alert-list">
                                        <?php foreach (array_slice($low_stock_medications, 0, 5) as $medication): ?>
                                                <li class="alert-item">
                                                    <div class="alert-item-header">
                                                        <span
                                                            class="alert-item-name"><?php echo htmlspecialchars($medication['nom_medicamento']); ?></span>
                                                        <span class="alert-badge danger">
                                                            <?php echo $medication['cantidad_med']; ?> unidades
                                                        </span>
                                                    </div>
                                                </li>
                                        <?php endforeach; ?>
                                    </ul>

                                    <?php if (count($low_stock_medications) > 5): ?>
                                            <div class="text-center mt-3">
                                                <a href="../inventory/index.php?filter=low_stock" class="text-primary text-decoration-none">
                                                    Ver todas (<?php echo count($low_stock_medications); ?>) <i class="bi bi-arrow-right"></i>
                                                </a>
                                            </div>
                                    <?php endif; ?>
                            <?php else: ?>
                                    <div class="no-alerts">
                                        <div class="no-alerts-icon">
                                            <i class="bi bi-check-circle"></i>
                                        </div>
                                        <p class="text-muted mb-0">Inventario con stock suficiente</p>
                                    </div>
                            <?php endif; ?>
                        </div>
                    </div>
            <?php endif; ?>

            <!-- ============ NEW WIDGETS ============ -->

            <!-- 1. WIDGET REVENUE (Ingresos del Mes) -->
            <?php if ($show_revenue): ?>
                    <section class="appointments-section animate-in delay-3" id="widget-revenue">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="bi bi-currency-dollar text-success section-title-icon"></i>
                                Ingresos Generales del Mes (Estimado)
                            </h3>
                            <div class="badge bg-success p-2 fs-6">
                                Total: Q<?php echo number_format($total_monthly_revenue, 2); ?>
                            </div>
                        </div>

                        <div class="row g-3 p-3">
                            <div class="col-md-4">
                                <div class="stat-card border-start border-success border-4 shadow-sm h-100 mb-0">
                                    <div class="stat-header">
                                        <div>
                                            <div class="stat-title text-success">Ventas de Farmacia</div>
                                            <div class="stat-value text-success" style="font-size: 1.25rem;">
                                                Q<?php echo number_format($revenue_ventas, 2); ?></div>
                                        </div>
                                        <div class="stat-icon success">
                                            <i class="bi bi-capsule"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card border-start border-primary border-4 shadow-sm h-100 mb-0">
                                    <div class="stat-header">
                                        <div>
                                            <div class="stat-title text-primary">Consultas Médicas</div>
                                            <div class="stat-value text-primary" style="font-size: 1.25rem;">
                                                Q<?php echo number_format($revenue_consults, 2); ?></div>
                                        </div>
                                        <div class="stat-icon primary">
                                            <i class="bi bi-people-fill"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card border-start border-warning border-4 shadow-sm h-100 mb-0">
                                    <div class="stat-header">
                                        <div>
                                            <div class="stat-title text-warning">Procedimientos Menores</div>
                                            <div class="stat-value text-warning" style="font-size: 1.25rem;">
                                                Q<?php echo number_format($revenue_proc, 2); ?></div>
                                        </div>
                                        <div class="stat-icon warning">
                                            <i class="bi bi-bandaid-fill"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mt-3">
                                <div class="stat-card border-start border-info border-4 shadow-sm h-100 mb-0">
                                    <div class="stat-header">
                                        <div>
                                            <div class="stat-title text-info">Exámenes de Laboratorio</div>
                                            <div class="stat-value text-info" style="font-size: 1.25rem;">
                                                Q<?php echo number_format($revenue_exams, 2); ?></div>
                                        </div>
                                        <div class="stat-icon info">
                                            <i class="bi bi-droplet-fill"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mt-3">
                                <div class="stat-card border-start border-secondary border-4 shadow-sm h-100 mb-0">
                                    <div class="stat-header">
                                        <div>
                                            <div class="stat-title text-secondary">Cuentas de Hospitalización</div>
                                            <div class="stat-value text-secondary" style="font-size: 1.25rem;">
                                                Q<?php echo number_format($revenue_hosp, 2); ?></div>
                                        </div>
                                        <div class="stat-icon secondary">
                                            <i class="bi bi-hospital"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="text-center mt-3 p-2">
                            <a href="../reports/index.php" class="text-primary text-decoration-none fw-bold">
                                Ir al Centro de Analítica <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </section>
            <?php endif; ?>

            <!-- 2. WIDGET INVENTORY (Resumen de Inventario) -->
            <?php if ($show_inventory): ?>
                    <section class="appointments-section animate-in delay-3" id="widget-inventory">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="bi bi-box-seam text-info section-title-icon"></i>
                                Monitoreo de Inventario
                            </h3>
                        </div>
                        <div class="row g-3 p-3">
                            <div class="col-md-4">
                                <div class="stat-card text-center shadow-sm">
                                    <h4 class="text-muted">Total de Productos</h4>
                                    <div class="fs-2 fw-bold text-dark mt-2"><?php echo $total_medications; ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card text-center shadow-sm">
                                    <h4 class="text-muted">Próximos a Vencer</h4>
                                    <div class="fs-2 fw-bold text-warning mt-2"><?php echo count($expiring_medications); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card text-center shadow-sm">
                                    <h4 class="text-muted">Stock Bajo</h4>
                                    <div class="fs-2 fw-bold text-danger mt-2"><?php echo count($low_stock_medications); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="text-center mt-2 p-2">
                            <a href="../inventory/index.php" class="text-primary text-decoration-none fw-bold">
                                Gestionar Inventario <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </section>
            <?php endif; ?>

            <!-- 3. WIDGET PATIENTS (Pacientes Registrados) -->
            <?php if ($show_patients): ?>
                    <section class="appointments-section animate-in delay-3" id="widget-patients">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="bi bi-people text-primary section-title-icon"></i>
                                Últimos Pacientes Registrados
                            </h3>
                            <div class="badge bg-primary p-2">
                                Total en Sistema: <?php echo $total_patients_count; ?>
                            </div>
                        </div>
                        <?php if (count($latest_patients) > 0): ?>
                                <div class="table-responsive p-3">
                                    <table class="appointments-table">
                                        <thead>
                                            <tr>
                                                <th>Nombre del Paciente</th>
                                                <th>NIT</th>
                                                <th>Teléfono</th>
                                                <th>Registro</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($latest_patients as $pat): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="patient-avatar me-2"
                                                                    style="background: var(--color-primary); color: white; width:35px; height:35px; border-radius:50%; display:flex; align-items:center; justify-content:center;">
                                                                    <?php echo strtoupper(substr($pat['nombre'], 0, 1) . substr($pat['apellido'], 0, 1)); ?>
                                                                </div>
                                                                <span
                                                                    class="fw-bold"><?php echo htmlspecialchars($pat['nombre'] . ' ' . $pat['apellido']); ?></span>
                                                            </div>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($pat['nit'] ?: 'C/F'); ?></td>
                                                        <td><?php echo htmlspecialchars($pat['telefono'] ?: 'No asignado'); ?></td>
                                                        <td><small
                                                                class="text-muted"><?php echo date('d/m/Y', strtotime($pat['fecha_registro'])); ?></small>
                                                        </td>
                                                    </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                        <?php else: ?>
                                <div class="empty-state p-4">
                                    <p class="text-muted">No hay pacientes registrados aún.</p>
                                </div>
                        <?php endif; ?>
                    </section>
            <?php endif; ?>

            <!-- 4. WIDGET CALENDAR (Calendario y Horarios) -->
            <?php if ($show_calendar): ?>
                    <section class="appointments-section animate-in delay-3" id="widget-calendar">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="bi bi-calendar3 text-primary section-title-icon"></i>
                                Agenda Semanal de Citas
                            </h3>
                            <button class="btn btn-sm btn-link text-primary" onclick="refreshWeeklyAppointments()" title="Actualizar">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                        <div class="p-3" id="weeklyAppointmentsContainer">
                            <div class="text-center py-3" id="weeklyAppointmentsLoader">
                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                <span class="ms-2 text-muted">Cargando citas...</span>
                            </div>
                            <div id="weeklyAppointmentsList" style="display:none;"></div>
                            <div id="weeklyAppointmentsEmpty" class="text-center py-2" style="display:none;">
                                <i class="bi bi-calendar-x text-muted" style="font-size: 2rem;"></i>
                                <p class="text-muted mt-2 mb-0">No hay citas programadas esta semana</p>
                            </div>
                        </div>
                        <div class="text-center mt-1 p-2">
                            <a href="../appointments/index.php" class="text-primary text-decoration-none fw-bold">
                                Ir al Módulo de Citas <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </section>
            <?php endif; ?>

            <!-- 5. WIDGET LABS (Órdenes de Laboratorio) -->
            <?php if ($show_labs): ?>
                    <section class="appointments-section animate-in delay-3" id="widget-labs">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="bi bi-droplet-half text-info section-title-icon"></i>
                                Órdenes de Laboratorio Recientes
                            </h3>
                            <div class="d-flex gap-2">
                                <span class="badge bg-warning text-dark p-2">Pendientes:
                                    <?php echo $pending_labs_count; ?></span>
                                <span class="badge bg-success text-white p-2">Completadas:
                                    <?php echo $completed_labs_count; ?></span>
                            </div>
                        </div>
                        <?php if (count($latest_lab_orders) > 0): ?>
                                <div class="table-responsive p-3">
                                    <table class="appointments-table">
                                        <thead>
                                            <tr>
                                                <th>Orden #</th>
                                                <th>Paciente</th>
                                                <th>Estado</th>
                                                <th>Fecha</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($latest_lab_orders as $lab): ?>
                                                    <?php
                                                    $lab_status_class = 'bg-warning';
                                                    if ($lab['estado'] === 'Completada')
                                                        $lab_status_class = 'bg-success';
                                                    if ($lab['estado'] === 'Cancelada')
                                                        $lab_status_class = 'bg-danger';
                                                    ?>
                                                    <tr>
                                                        <td><strong><?php echo htmlspecialchars($lab['numero_orden']); ?></strong></td>
                                                        <td><?php echo htmlspecialchars($lab['nombre'] . ' ' . $lab['apellido']); ?></td>
                                                        <td>
                                                            <span class="badge <?php echo $lab_status_class; ?> text-white">
                                                                <?php echo htmlspecialchars($lab['estado']); ?>
                                                            </span>
                                                        </td>
                                                        <td><small
                                                                class="text-muted"><?php echo date('d/m/Y H:i', strtotime($lab['fecha_orden'])); ?></small>
                                                        </td>
                                                    </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                        <?php else: ?>
                                <div class="empty-state p-4">
                                    <p class="text-muted">No hay órdenes de laboratorio en el sistema.</p>
                                </div>
                        <?php endif; ?>
                        <div class="text-center mt-1 p-2">
                            <a href="../laboratory/index.php" class="text-primary text-decoration-none fw-bold">
                                Ir al Módulo de Laboratorio <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </section>
            <?php endif; ?>
        </main>
    </div>

    <!-- Modal para nuevo cobro (Billing) -->
    <div class="modal fade" id="newBillingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-premium modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-cash-coin me-2"></i>
                        Nuevo Cobro
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="newBillingForm">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Paciente</label>
                            <div class="position-relative">
                                <input type="text" name="paciente_nombre" class="form-control"
                                    id="billing_paciente_input"
                                    placeholder="Escriba para buscar paciente..." required autocomplete="off">
                                <div id="billingPatientDropdown" class="patient-search-dropdown position-absolute w-100 mt-1 d-none" style="z-index: 1060; max-height: 250px; overflow-y: auto;"></div>
                            </div>
                            <input type="hidden" id="billing_paciente" name="paciente">
                            <small id="billingPatientHint" class="form-text text-muted d-none">Escriba al menos 1 carácter para buscar</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Médico que atiende</label>
                            <select class="form-select" id="billing_id_doctor" name="id_doctor" required>
                                <option value="">Seleccione un médico...</option>
                                <?php foreach ($doctores as $doctor): ?>
                                        <option value="<?php echo $doctor['idUsuario']; ?>">
                                            Dr(a).
                                            <?php echo htmlspecialchars($doctor['nombre'] . ' ' . $doctor['apellido']); ?>
                                        </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Tipo de Consulta <span id="consultationHistoryBadge"
                                    class="badge bg-info ms-2 d-none"></span></label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="tipo_consulta" id="btn_consulta"
                                    value="Consulta" checked autocomplete="off">
                                <label class="btn btn-outline-success" for="btn_consulta">Consulta</label>

                                <input type="radio" class="btn-check" name="tipo_consulta" id="btn_reconsulta"
                                    value="Reconsulta" autocomplete="off">
                                <label class="btn btn-outline-success" for="btn_reconsulta">Re-Consulta</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Monto a Cobrar (Q)</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-success text-white border-0">Q</span>
                                <input type="number" class="form-control border-success text-success fw-bold"
                                    id="billing_monto" name="cantidad" min="0" step="0.01" placeholder="0.00" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Tipo de Pago</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="tipo_pago" id="pago_efectivo"
                                    value="Efectivo" checked autocomplete="off">
                                <label class="btn btn-outline-success" for="pago_efectivo">
                                    <i class="bi bi-cash me-1"></i>Efectivo
                                </label>

                                <input type="radio" class="btn-check" name="tipo_pago" id="pago_transferencia"
                                    value="Transferencia" autocomplete="off">
                                <label class="btn btn-outline-success" for="pago_transferencia">
                                    <i class="bi bi-bank me-1"></i>
                                </label>

                                <input type="radio" class="btn-check" name="tipo_pago" id="pago_tarjeta" value="Tarjeta"
                                    autocomplete="off">
                                <label class="btn btn-outline-success" for="pago_tarjeta">
                                    <i class="bi bi-credit-card me-1"></i>Tarjeta
                                </label>
                            </div>
                        </div>

                        <div class="small text-muted mb-0">
                            <i class="bi bi-info-circle me-1"></i> El monto se calcula automáticamente al seleccionar
                            médico y tipo.
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success px-4" id="saveBillingBtn">
                        <i class="bi bi-check-lg me-1"></i>Guardar Cobro
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Nueva Orden de Laboratorio -->
    <div class="modal fade" id="newLabOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-premium modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white py-3">
                    <h5 class="modal-title d-flex align-items-center">
                        <div class="icon-shape bg-white bg-opacity-20 rounded-3 p-2 me-3">
                            <i class="bi bi-virus fs-4"></i>
                        </div>
                        <div>
                            <span class="d-block fw-bold">Nueva Orden de Laboratorio</span>
                            <small class="text-white text-opacity-75 fw-normal">Seleccione pruebas para el
                                paciente</small>
                        </div>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body lab-modal-body">
                    <div class="d-flex h-100 flex-column flex-lg-row">
                        <!-- Panel Izquierdo: Selección -->
                        <div class="p-4 flex-grow-1 overflow-auto bg-white">
                            <form id="newLabOrderForm">
                                <!-- Datos del Paciente -->
                                <div class="row g-3 mb-4 p-3 bg-light rounded-3 border">
                                    <div class="col-md-6">
                                        <label
                                            class="form-label fw-bold small text-uppercase text-muted">Paciente</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white border-end-0"><i
                                                    class="bi bi-person text-primary"></i></span>
                                            <div class="position-relative flex-grow-1">
                                                <input class="form-control border-start-0 ps-0"
                                                    id="lab_paciente_input" placeholder="Buscar por nombre..." required
                                                    autocomplete="off">
                                                <div id="labPatientDropdown" class="patient-search-dropdown position-absolute w-100 mt-1 d-none"
                                                    style="z-index: 1060; max-height: 250px; overflow-y: auto;"></div>
                                            </div>
                                        </div>
                                        <input type="hidden" id="lab_id_paciente" name="id_paciente">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small text-uppercase text-muted">Doctor
                                            Referente</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white border-end-0"><i
                                                    class="bi bi-person-badge text-primary"></i></span>
                                            <select class="form-select border-start-0 ps-0" id="lab_id_doctor"
                                                name="id_doctor" required>
                                                <option value="">Seleccionar doctor...</option>
                                                <?php foreach ($doctores as $doctor): ?>
                                                        <option value="<?php echo $doctor['idUsuario']; ?>">
                                                            Dr(a).
                                                            <?php echo htmlspecialchars($doctor['nombre'] . ' ' . $doctor['apellido']); ?>
                                                        </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label fw-bold small text-uppercase text-muted">Indicaciones u
                                            Observaciones</label>
                                        <textarea class="form-control" name="observaciones" rows="1"
                                            placeholder="Nota para el analista..."></textarea>
                                    </div>
                                </div>

                                <!-- Buscador de Pruebas -->
                                <div class="sticky-top bg-white py-2 mb-3">
                                    <div class="input-group shadow-sm">
                                        <span class="input-group-text bg-white border-end-0"><i
                                                class="bi bi-search text-primary"></i></span>
                                        <input type="text" id="labTestSearch"
                                            class="form-control border-start-0 ps-0 py-2"
                                            placeholder="Filtrar pruebas por nombre o categoría...">
                                    </div>
                                </div>

                                <!-- Listado de Pruebas -->
                                <div class="accordion accordion-flush" id="testsAccordion">
                                    <?php foreach ($pruebas_por_categoria as $categoria => $pruebas): ?>
                                            <?php $catID = 'cat_v2_' . md5($categoria); ?>
                                            <div class="accordion-item border rounded-3 mb-2 category-container"
                                                data-category="<?php echo htmlspecialchars($categoria); ?>">
                                                <h2 class="accordion-header" id="heading_<?php echo $catID; ?>">
                                                    <button class="accordion-button rounded-3 fw-bold" type="button"
                                                        data-bs-toggle="collapse"
                                                        data-bs-target="#collapse_<?php echo $catID; ?>" aria-expanded="true">
                                                        <i class="bi bi-tags me-2 text-primary"></i>
                                                        <?php echo htmlspecialchars($categoria); ?>
                                                        <span
                                                            class="badge bg-light text-primary ms-2 border"><?php echo count($pruebas); ?></span>
                                                    </button>
                                                </h2>
                                                <div id="collapse_<?php echo $catID; ?>"
                                                    class="accordion-collapse collapse show" data-bs-parent="#testsAccordion">
                                                    <div class="accordion-body p-2">
                                                        <div class="row g-2">
                                                            <?php foreach ($pruebas as $prueba): ?>
                                                                    <div class="col-md-6 test-item"
                                                                        data-name="<?php echo strtolower(htmlspecialchars($prueba['nombre_prueba'])); ?>">
                                                                        <div
                                                                            class="test-card-v2 p-2 border rounded-3 position-relative transition-all d-flex align-items-center gap-3 h-100 hover-shadow cursor-pointer">
                                                                            <div class="check-indicator">
                                                                                <input
                                                                                    class="form-check-input test-checkbox stretched-link"
                                                                                    type="checkbox" name="pruebas[]"
                                                                                    value="<?php echo $prueba['id_prueba']; ?>"
                                                                                    id="test_v2_<?php echo $prueba['id_prueba']; ?>"
                                                                                    data-price="<?php echo $prueba['precio']; ?>"
                                                                                    data-name="<?php echo htmlspecialchars($prueba['nombre_prueba']); ?>">
                                                                            </div>
                                                                            <div class="flex-grow-1">
                                                                                <div class="fw-semibold small lh-1 mb-1">
                                                                                    <?php echo htmlspecialchars($prueba['nombre_prueba']); ?>
                                                                                </div>
                                                                                <div class="text-success fw-bold small">
                                                                                    Q<?php echo number_format($prueba['precio'], 2); ?>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                    <?php endforeach; ?>
                                </div>
                            </form>
                        </div>

                        <!-- Panel Derecho: Resumen -->
                        <div class="bg-light border-start p-4 d-flex flex-column lab-summary-panel">
                            <div class="flex-grow-1">
                                <h6 class="fw-bold d-flex justify-content-between align-items-center mb-3">
                                    <span>Resumen de Selección</span>
                                    <span class="badge bg-primary rounded-pill pruebas-count">0</span>
                                </h6>
                                <div id="selectedTestsList" class="mb-3 custom-scrollbar"
                                    style="max-height: 400px; overflow-y: auto;">
                                    <div class="text-center py-5 text-muted empty-summary">
                                        <i class="bi bi-cart-x fs-1 opacity-25"></i>
                                        <p class="mt-2 small">No hay pruebas seleccionadas</p>
                                    </div>
                                </div>
                            </div>

                            <div class="border-top pt-3 bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted small fw-bold text-uppercase">Subtotal:</span>
                                    <span class="fw-bold text-dark" id="orderSubtotal">Q0.00</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-bold text-uppercase">Total a Pagar:</span>
                                        <button type="button" class="btn btn-sm btn-outline-warning" id="toggleEPSBtn"
                                            title="Activar modo EPS (Editar precios)">
                                            <i class="bi bi-pencil-square"></i> EPS
                                        </button>
                                    </div>
                                    <span class="fs-3 fw-bold text-primary" id="orderTotal">Q0.00</span>
                                </div>

                                <!-- Payment Method Selection -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold small text-uppercase text-muted">Método de
                                        Pago</label>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="order_tipo_pago"
                                            id="order_pago_efectivo" value="Efectivo" checked autocomplete="off">
                                        <label class="btn btn-outline-primary"
                                            for="order_pago_efectivo">Efectivo</label>

                                        <input type="radio" class="btn-check" name="order_tipo_pago"
                                            id="order_pago_transferencia" value="Transferencia" autocomplete="off">
                                        <label class="btn btn-outline-primary"
                                            for="order_pago_transferencia">Transf.</label>

                                        <input type="radio" class="btn-check" name="order_tipo_pago"
                                            id="order_pago_tarjeta" value="Tarjeta" autocomplete="off">
                                        <label class="btn btn-outline-primary" for="order_pago_tarjeta">Tarjeta</label>
                                    </div>
                                    <small class="text-muted d-block mt-2">Sólo aplica si el paciente no está
                                        hospitalizado.</small>
                                </div>

                                <!-- Laboratorio Externo (obligatorio) -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold small text-uppercase text-muted">Laboratorio <span class="text-danger">*</span></label>
                                    <select class="form-select" id="lab_laboratorio_externo" name="laboratorio_externo" required>
                                        <option value="">Seleccione laboratorio...</option>
                                        <option value="Medialab">Medialab</option>
                                        <option value="La Esperanza">La Esperanza</option>
                                    </select>
                                    <small class="text-muted d-block mt-1"><i class="bi bi-info-circle"></i> Indica a qué laboratorio se enviarán las muestras. No se imprime en el ticket.</small>
                                </div>
                            </div>
                            <button type="button"
                                class="btn btn-primary w-100 py-3 rounded-3 shadow-sm d-flex justify-content-center align-items-center gap-2"
                                id="saveLabOrderBtn" disabled>
                                <i class="bi bi-printer fs-5"></i>
                                <span class="fw-bold">Generar Orden</span>
                            </button>
                            <p class="text-center small text-muted mt-2">
                                <i class="bi bi-shield-check me-1"></i> Se generará cobro automático
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <script>
        function toggleLabCheckbox(id) {
            const cb = document.getElementById(id);
            if (cb) {
                cb.checked = !cb.checked;
                cb.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    </script>

    <!-- JavaScript Optimizado -->
    <script>
        // Dashboard Reingenierizado - Centro Médico Herrera Saenz

        (function () {
            'use strict';

            // ==========================================================================
            // CONFIGURACIÓN Y CONSTANTES
            // ==========================================================================
            const CONFIG = {
                themeKey: 'dashboard-theme',
                sidebarKey: 'sidebar-collapsed',
                greetingKey: 'last-jornada-summary',
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
                greetingElement: document.getElementById('greeting-text'),
                currentTimeElement: document.getElementById('current-time'),
                checkPatientButtons: document.querySelectorAll('.check-patient')
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
            // COMPONENTES DINÁMICOS
            // ==========================================================================
            // ===== PATIENT SEARCH ENGINE (reusable for all modals) =====
            window.setupPatientSearch = function(inputId, dropdownId, hiddenId) {
                const input = document.getElementById(inputId);
                const dropdown = document.getElementById(dropdownId);
                const hidden = document.getElementById(hiddenId);
                if (!input || !dropdown || !hidden) return;

                let searchTimeout = null;
                let highlightedIndex = -1;

                function hideDropdown() {
                    dropdown.classList.add('d-none');
                    highlightedIndex = -1;
                }

                input.addEventListener('input', () => {
                    clearTimeout(searchTimeout);
                    const query = input.value.trim();

                    if (query.length < 1) {
                        hideDropdown();
                        dropdown.innerHTML = '';
                        hidden.value = '';
                        return;
                    }

                    searchTimeout = setTimeout(async () => {
                        try {
                            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                            const response = await fetch(`api/search_patients.php?q=${encodeURIComponent(query)}&limit=10`, {
                                headers: { 'X-CSRF-TOKEN': csrfToken }
                            });
                            const data = await response.json();

                            if (data.status === 'success' && data.patients.length > 0) {
                                dropdown.innerHTML = data.patients.map((p, idx) => `
                                    <div class="patient-search-item" data-id="${p.id_paciente}" data-name="${p.nombre_completo}" data-index="${idx}">
                                        <div class="patient-name">${p.nombre_completo}</div>
                                        <div class="patient-meta">DPI: ${p.dpi || 'N/A'} &bull; Edad: ${p.edad || '?'}</div>
                                    </div>
                                `).join('');
                                dropdown.classList.remove('d-none');
                                highlightedIndex = -1;
                            } else {
                                dropdown.innerHTML = '<div class="patient-search-item disabled" style="color:#999;cursor:default;">Sin resultados</div>';
                                dropdown.classList.remove('d-none');
                            }
                        } catch (e) {
                            console.error('Error searching patients:', e);
                            hideDropdown();
                        }
                    }, 200);
                });

                dropdown.addEventListener('click', (e) => {
                    const item = e.target.closest('.patient-search-item[data-id]');
                    if (item) {
                        input.value = item.dataset.name;
                        hidden.value = item.dataset.id;
                        hideDropdown();
                        dropdown.innerHTML = '';
                    }
                });

                input.addEventListener('keydown', (e) => {
                    const items = dropdown.querySelectorAll('.patient-search-item[data-id]');
                    if (!items.length) return;

                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        highlightedIndex = Math.min(highlightedIndex + 1, items.length - 1);
                        items.forEach((el, i) => el.classList.toggle('highlighted', i === highlightedIndex));
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        highlightedIndex = Math.max(highlightedIndex - 1, 0);
                        items.forEach((el, i) => el.classList.toggle('highlighted', i === highlightedIndex));
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        const highlighted = dropdown.querySelector('.highlighted');
                        if (highlighted) {
                            highlighted.click();
                        }
                    } else if (e.key === 'Escape') {
                        hideDropdown();
                    }
                });

                document.addEventListener('click', (e) => {
                    if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                        hideDropdown();
                    }
                });
            };

            class DynamicComponents {
                constructor() {
                    this.setupGreeting();
                    this.setupClock();
                    this.setupPatientHandlers();
                    this.setupBillingHandlers();
                    this.setupElectroHandlers();
                    this.setupLabOrderHandlers();
                    this.setupXrayHandlers();
                    this.setupUltrasoundHandlers();
                    this.setupAnimations();
                }

                setupGreeting() {
                    const el = document.getElementById('greeting-text');
                    if (!el) return;
                    const hour = new Date().getHours();
                    let greeting = hour < 12 ? 'Buenos días' : (hour < 19 ? 'Buenas tardes' : 'Buenas noches');
                    el.textContent = greeting;
                }

                setupClock() {
                    const el = document.getElementById('current-time');
                    if (!el) return;
                    const update = () => {
                        el.textContent = new Date().toLocaleTimeString('es-GT', { hour: '2-digit', minute: '2-digit', hour12: false });
                    };
                    update();
                    setInterval(update, 60000);
                }

                setupPatientHandlers() {
                    document.querySelectorAll('.check-patient').forEach(btn => {
                        btn.addEventListener('click', async (e) => {
                            e.preventDefault();
                            const nombre = btn.getAttribute('data-nombre');
                            const apellido = btn.getAttribute('data-apellido');
                            if (!nombre || !apellido) return;

                            const icon = btn.querySelector('i');
                            const originalClass = icon ? icon.className : '';
                            if (icon) icon.className = 'bi bi-arrow-clockwise spin';
                            btn.style.pointerEvents = 'none';

                            try {
                                const response = await fetch(`../patients/check_patient.php?nombre=${encodeURIComponent(nombre)}&apellido=${encodeURIComponent(apellido)}`);
                                const data = await response.json();

                                if (data.status === 'success' && data.exists) {
                                    window.location.href = `../patients/medical_history.php?id=${data.id}`;
                                } else {
                                    Swal.fire({
                                        title: 'Paciente no encontrado',
                                        text: `El paciente ${nombre} ${apellido} no está registrado. ¿Desea registrarlo?`,
                                        icon: 'question',
                                        showCancelButton: true,
                                        confirmButtonText: 'Sí, registrar',
                                        cancelButtonText: 'Cancelar',
                                        confirmButtonColor: 'var(--color-primary)',
                                        background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                                        color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#e2e8f0' : '#1a1a1a'
                                    }).then((result) => {
                                        if (result.isConfirmed) {
                                            window.location.href = `../patients/index.php?new=true&nombre=${encodeURIComponent(nombre)}&apellido=${encodeURIComponent(apellido)}`;
                                        }
                                    });
                                }
                            } catch (error) {
                                Swal.fire('Error', 'Problema al conectar con el servidor', 'error');
                            } finally {
                                if (icon) icon.className = originalClass;
                                btn.style.pointerEvents = '';
                            }
                        });
                    });
                }

                setupBillingHandlers() {
                    const doctorSelect = document.getElementById('billing_id_doctor');
                    const montoInput = document.getElementById('billing_monto');
                    const tipoRadios = document.getElementsByName('tipo_consulta');

                    if (!doctorSelect || !montoInput) return;

                    const calculatePrice = () => {
                        const doctorId = doctorSelect.value;
                        let type = 'Consulta';
                        tipoRadios.forEach(r => { if (r.checked) type = r.value; });

                        let price = 0;
                        const date = new Date();
                        const day = date.getDay();
                        const hour = date.getHours();

                        const isHabil = day >= 1 && day <= 6 && !(day === 6 && hour >= 13);

                        if (type === 'Consulta') {
                            if (tarifas.consulta[doctorId]) {
                                price = isHabil ? tarifas.consulta[doctorId].normal : tarifas.consulta[doctorId].inutil;
                            }
                        } else {
                            if (tarifas.reconsulta[doctorId]) {
                                price = isHabil ? tarifas.reconsulta[doctorId].normal : tarifas.reconsulta[doctorId].inutil;
                            }
                        }

                        if (price === 0) {
                            price = 0;
                        }
                        montoInput.value = price;
                    };

                    doctorSelect.addEventListener('change', calculatePrice);
                    tipoRadios.forEach(r => r.addEventListener('change', calculatePrice));
                    calculatePrice();

                    // Real-time patient search for billing modal
                    setupPatientSearch('billing_paciente_input', 'billingPatientDropdown', 'billing_paciente');

                    const saveBtn = document.getElementById('saveBillingBtn');
                    if (saveBtn) {
                        saveBtn.addEventListener('click', async () => {
                            if (saveBtn.disabled) return;
                            const form = document.getElementById('newBillingForm');
                            const patientInput = document.getElementById('billing_paciente_input');
                            const patientHidden = document.getElementById('billing_paciente');

                            if (!form || !patientInput) return;

                            const val = patientInput.value.trim();
                            if (!val || !patientHidden.value) {
                                Swal.fire('Aviso', 'Debe seleccionar un paciente de la lista', 'warning');
                                return;
                            }

                            if (!form.checkValidity()) {
                                form.reportValidity();
                                return;
                            }

                            const originalText = saveBtn.innerHTML;
                            saveBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Guardando...';
                            saveBtn.disabled = true;

                            try {
                                const response = await apiPost('../billing/save_billing.php', new URLSearchParams(new FormData(form)));
                                const result = await response.json();
                                if (result.status === 'success') {
                                    Swal.fire({
                                        title: 'Éxito',
                                        text: 'Cobro guardado correctamente',
                                        icon: 'success',
                                        timer: 1500,
                                        showConfirmButton: false
                                    }).then(() => {
                                        if (result.id_cobro) {
                                            window.open(`../billing/print_billing.php?id=${result.id_cobro}`, '_blank');
                                        }
                                        location.reload();
                                    });
                                } else {
                                    throw new Error(result.message);
                                }
                            } catch (error) {
                                Swal.fire('Error', error.message || 'Error de conexión', 'error');
                            } finally {
                                saveBtn.innerHTML = originalText;
                                saveBtn.disabled = false;
                            }
                        });
                    }
                }

                setupElectroHandlers() {
                    const saveBtn = document.getElementById('saveElectroBtn');
                    if (saveBtn) {
                        saveBtn.addEventListener('click', async () => {
                            if (saveBtn.disabled) return;
                            const form = document.getElementById('electroBillingForm');
                            const patientHidden = document.getElementById('electro_paciente');

                            if (!form || !patientHidden) return;

                            if (!patientHidden.value) {
                                Swal.fire('Aviso', 'Seleccione un paciente válido', 'warning');
                                return;
                            }

                            if (!form.checkValidity()) {
                                form.reportValidity();
                                return;
                            }

                            const originalText = saveBtn.innerHTML;
                            saveBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Procesando...';
                            saveBtn.disabled = true;

                            try {
                                const response = await apiPost('api/save_electro.php', new URLSearchParams(new FormData(form)));
                                const result = await response.json();
                                if (result.status === 'success') {
                                    Swal.fire({
                                        title: 'Éxito',
                                        text: 'Electrocardiograma registrado',
                                        icon: 'success',
                                        timer: 1500,
                                        showConfirmButton: false
                                    }).then(() => {
                                        if (result.id) {
                                            window.open(`../billing/print_electro.php?id=${result.id}`, '_blank');
                                        }
                                        setTimeout(() => location.reload(), 500);
                                    });
                                } else {
                                    throw new Error(result.message);
                                }
                            } catch (error) {
                                Swal.fire('Error', error.message || 'Error de conexión', 'error');
                            } finally {
                                saveBtn.innerHTML = originalText;
                                saveBtn.disabled = false;
                            }
                        });
                    }

                    setupPatientSearch('electro_paciente_input', 'electroPatientDropdown', 'electro_paciente');
                }

                setupLabOrderHandlers() {
                    const checkboxes = document.querySelectorAll('.test-checkbox');
                    const selectedList = document.getElementById('selectedTestsList');
                    const subtotalElement = document.getElementById('orderSubtotal');
                    const totalElement = document.getElementById('orderTotal');
                    const countElements = document.querySelectorAll('.pruebas-count');
                    const saveBtn = document.getElementById('saveLabOrderBtn');
                    const searchInput = document.getElementById('labTestSearch');

                    const epsBtn = document.getElementById('toggleEPSBtn');
                    let isEPSMode = false;

                    if (!selectedList) return;

                    const updateSummary = () => {
                        const emptySummary = selectedList.querySelector('.empty-summary');
                        const fragment = document.createDocumentFragment();
                        let total = 0, count = 0;

                        checkboxes.forEach(cb => {
                            const card = cb.closest('.test-card-v2');
                            if (cb.checked) {
                                count++;
                                const price = parseFloat(cb.getAttribute('data-price'));
                                total += price;
                                if (card) card.classList.add('active');

                                const item = document.createElement('div');
                                item.className = 'd-flex justify-content-between align-items-center p-2 mb-2 bg-white border rounded shadow-sm animate-in';
                                item.innerHTML = `
                                    <div class="small w-100">
                                        <div class="fw-bold text-dark">${cb.getAttribute('data-name')}</div>
                                        ${isEPSMode
                                        ? `<input type="number" class="form-control form-control-sm mt-1 eps-price-input" value="${price.toFixed(2)}" step="0.01" min="0" data-id="${cb.value}">`
                                        : `<div class="text-primary fw-bold">Q${price.toFixed(2)}</div>`
                                    }
                                    </div>
                                    <button type="button" class="btn btn-link text-danger p-0 ms-2" onclick="toggleLabCheckbox('${cb.id}')">
                                        <i class="bi bi-trash"></i>
                                    </button>`;
                                fragment.appendChild(item);
                            } else {
                                if (card) card.classList.remove('active');
                            }
                        });

                        selectedList.innerHTML = '';
                        if (count > 0) {
                            selectedList.appendChild(fragment);
                        } else {
                            selectedList.innerHTML = `
                                <div class="text-center py-5 text-muted empty-summary">
                                    <i class="bi bi-cart-x fs-1 opacity-25"></i>
                                    <p class="mt-2 small">No hay pruebas seleccionadas</p>
                                </div>`;
                        }

                        const totalStr = `Q${total.toFixed(2)}`;
                        if (subtotalElement) subtotalElement.textContent = totalStr;
                        if (totalElement) totalElement.textContent = totalStr;
                        countElements.forEach(el => el.textContent = count);
                        if (saveBtn) saveBtn.disabled = (count === 0);
                    };

                    const toggleEPS = () => {
                        isEPSMode = !isEPSMode;
                        epsBtn.classList.toggle('active', isEPSMode);
                        epsBtn.classList.toggle('btn-warning', isEPSMode);
                        epsBtn.classList.toggle('btn-outline-warning', !isEPSMode);
                        updateSummary();
                    };

                    if (epsBtn) {
                        epsBtn.addEventListener('click', toggleEPS);
                    }

                    // Delegación de eventos para inputs de precio en modo EPS
                    if (selectedList) {
                        selectedList.addEventListener('input', (e) => {
                            if (e.target.classList.contains('eps-price-input')) {
                                let total = 0;
                                document.querySelectorAll('.eps-price-input').forEach(input => {
                                    total += parseFloat(input.value) || 0;
                                });
                                const totalStr = `Q${total.toFixed(2)}`;
                                if (subtotalElement) subtotalElement.textContent = totalStr;
                                if (totalElement) totalElement.textContent = totalStr;
                            }
                        });
                    }

                    checkboxes.forEach(cb => {
                        cb.addEventListener('change', updateSummary);
                    });

                    if (saveBtn) {
                        saveBtn.addEventListener('click', async () => {
                            const form = document.getElementById('newLabOrderForm');
                            const patientHidden = document.getElementById('lab_id_paciente');

                            if (!form || !patientHidden) return;

                            if (!patientHidden.value) {
                                Swal.fire('Aviso', 'Seleccione un paciente de la lista', 'warning');
                                return;
                            }

                            if (!document.getElementById('lab_id_doctor').value) {
                                Swal.fire('Aviso', 'Seleccione un doctor referente', 'warning');
                                return;
                            }

                            const labExtSel = document.getElementById('lab_laboratorio_externo');
                            if (!labExtSel || !labExtSel.value) {
                                Swal.fire('Aviso', 'Seleccione el laboratorio (Medialab o La Esperanza)', 'warning');
                                return;
                            }

                            const pruebas = [];
                            document.querySelectorAll('.test-checkbox:checked').forEach(cb => pruebas.push(cb.value));

                            if (pruebas.length === 0) {
                                Swal.fire('Aviso', 'Seleccione al menos una prueba', 'warning');
                                return;
                            }

                            const data = {
                                id_paciente: patientHidden.value,
                                id_doctor: document.getElementById('lab_id_doctor').value,
                                observaciones: form.observaciones.value,
                                pruebas: pruebas,
                                tipo_pago: document.querySelector('input[name="order_tipo_pago"]:checked')?.value || 'Efectivo',
                                laboratorio_externo: labExtSel.value,
                                is_eps: isEPSMode,
                                custom_prices: {}
                            };

                            if (isEPSMode) {
                                document.querySelectorAll('.eps-price-input').forEach(input => {
                                    const id = input.getAttribute('data-id');
                                    const val = parseFloat(input.value) || 0; // Si está vacío o es 0, se guarda como 0
                                    data.custom_prices[id] = val;
                                });
                            }

                            const originalText = saveBtn.innerHTML;
                            saveBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Procesando...';
                            saveBtn.disabled = true;

                            try {
                                const response = await apiPost('../laboratory/save_order.php', data);
                                const result = await response.json();
                                if (result.status === 'success') {
                                    Swal.fire({
                                        title: '¡Orden Creada!',
                                        text: 'La orden y el cobro se han generado correctamente.',
                                        icon: 'success',
                                        showConfirmButton: false,
                                        timer: 1500
                                    }).then(() => {
                                        if (result.id_pago) {
                                            const printUrl = `../laboratory/print_lab_receipt.php?id=${result.id_pago}`;
                                            window.open(printUrl, '_blank');
                                        } else if (result.id_orden) {
                                            const printUrl = `../laboratory/print_lab_order.php?id=${result.id_orden}`;
                                            window.open(printUrl, '_blank');
                                        }
                                        location.reload();
                                    });
                                } else {
                                    throw new Error(result.message);
                                }
                            } catch (e) {
                                Swal.fire('Error', e.message || 'Error al guardar orden', 'error');
                            } finally {
                                saveBtn.innerHTML = originalText;
                                saveBtn.disabled = false;
                            }
                        });
                    }

                    if (searchInput) {
                        searchInput.addEventListener('input', function () {
                            const term = this.value.toLowerCase().trim();
                            const items = document.querySelectorAll('.test-item');
                            const categories = document.querySelectorAll('.category-container');

                            items.forEach(item => {
                                const name = item.getAttribute('data-name');
                                item.classList.toggle('d-none', !name.includes(term));
                            });

                            categories.forEach(cat => {
                                const visibleItems = cat.querySelectorAll('.test-item:not(.d-none)');
                                cat.classList.toggle('d-none', visibleItems.length === 0);
                            });
                        });
                    }

                    setupPatientSearch('lab_paciente_input', 'labPatientDropdown', 'lab_id_paciente');
                }

                setupUltrasoundHandlers() {
                    const select = document.getElementById('ultrasoundSelect');
                    const amountInput = document.getElementById('ultrasound_amount');
                    const saveBtn = document.getElementById('saveUltrasoundBtn');

                    if (!select || !amountInput) return;

// Update price on select or radio change
                    const updatePrice = () => {
                        const selectedType = select.value;
                        const rateType = document.querySelector('input[name="us_rate_type"]:checked').value;
                        const amountInput = document.getElementById('ultrasound_amount');

                        let price = 0;

                        if (selectedType && tarifas.ultrasonido[selectedType]) {
                            const tipo = tarifas.ultrasonido[selectedType];
                            switch (rateType) {
                                case 'inhabil':
                                    price = tipo.inhabil || 0;
                                    break;
                                case 'radio':
                                    price = tipo.radio || 0;
                                    break;
                                case 'iradio':
                                    price = tipo.iradio || 0;
                                    break;
                                default:
                                    price = tipo.normal || 0;
                            }
                            amountInput.value = price > 0 ? price.toFixed(2) : '';
                            amountInput.readOnly = true;
                            amountInput.placeholder = '';
                        } else {
                            amountInput.readOnly = false;
                            amountInput.placeholder = 'Ingrese monto...';
                        }
                    };

                    select.addEventListener('change', updatePrice);
                    document.querySelectorAll('.us-rate-type').forEach(radio => {
                        radio.addEventListener('change', updatePrice);
                    });

                    if (saveBtn) {
                        saveBtn.addEventListener('click', async () => {
                            if (saveBtn.disabled) return;

                            const form = document.getElementById('ultrasoundBillingForm');
                            const patientHidden = document.getElementById('ultrasound_patient_id');

                            if (!form || !patientHidden) return;

                            if (!patientHidden.value) {
                                Swal.fire('Aviso', 'Seleccione un paciente válido', 'warning');
                                return;
                            }

                            if (!form.checkValidity()) {
                                form.reportValidity();
                                return;
                            }

                            const originalText = saveBtn.innerHTML;
                            saveBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Guardando...';
                            saveBtn.disabled = true;

                            const formData = new FormData(form);
                            // Ensure name is passed if backend needs it, usually ID is enough but safe_us_charge might use it
                            // My implementation uses ID to fetch name from DB

                            try {
                                const response = await apiPost('api/save_us_charge.php', formData);
                                const result = await response.json();
                                if (result.status === 'success') {
                                    Swal.fire({
                                        title: 'Éxito',
                                        text: 'Ultrasonido registrado',
                                        icon: 'success',
                                        timer: 1500,
                                        showConfirmButton: false
                                    }).then(() => {
                                        if (result.id) {
                                            window.open(`../ultrasonidos/print_us_receipt.php?id=${result.id}`, '_blank');
                                        }
                                        setTimeout(() => location.reload(), 500);
                                    });
                                } else {
                                    throw new Error(result.message);
                                }
                            } catch (e) {
                                Swal.fire('Error', e.message || 'Error de conexión', 'error');
                            } finally {
                                saveBtn.innerHTML = originalText;
                                saveBtn.disabled = false;
                            }
                        });
                    }

                    setupPatientSearch('ultrasound_patient_input', 'ultrasoundPatientDropdown', 'ultrasound_patient_id');
                }

                setupXrayHandlers() {
                    const saveBtn = document.getElementById('saveXrayBtn');
                    if (saveBtn) {
                        saveBtn.addEventListener('click', async () => {
                            if (saveBtn.disabled) return;

                            const form = document.getElementById('xrayBillingForm');
                            const patientHidden = document.getElementById('xray_patient_id');

                            if (!form || !patientHidden) return;

                            if (!patientHidden.value) {
                                Swal.fire('Aviso', 'Seleccione un paciente válido', 'warning');
                                return;
                            }

                            if (!form.checkValidity()) {
                                form.reportValidity();
                                return;
                            }

                            const originalText = saveBtn.innerHTML;
                            saveBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Guardando...';
                            saveBtn.disabled = true;

                            const formData = new FormData(form);

                            try {
                                const response = await apiPost('api/save_rx_charge.php', formData);
                                const result = await response.json();
                                if (result.status === 'success') {
                                    Swal.fire({
                                        title: 'Éxito',
                                        text: 'Rayos X registrado',
                                        icon: 'success',
                                        timer: 1500,
                                        showConfirmButton: false
                                    }).then(() => {
                                        if (result.id) {
                                            window.open(`../rayos_x/print_rx_receipt.php?id=${result.id}`, '_blank');
                                        }
                                        setTimeout(() => location.reload(), 500);
                                    });
                                } else {
                                    throw new Error(result.message);
                                }
                            } catch (e) {
                                Swal.fire('Error', e.message || 'Error de conexión', 'error');
                            } finally {
                                saveBtn.innerHTML = originalText;
                                saveBtn.disabled = false;
                            }
                        });
                    }

                    setupPatientSearch('xray_patient_input', 'xrayPatientDropdown', 'xray_patient_id');
                }

                setupAnimations() {
                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                entry.target.classList.add('animate-in');
                                observer.unobserve(entry.target);
                            }
                        });
                    }, { threshold: 0.1 });

                    document.querySelectorAll('.stat-card, .appointments-section, .alert-card').forEach(el => observer.observe(el));
                }
            }

            // ==========================================================================
            // OPTIMIZACIONES DE RENDIMIENTO
            // ==========================================================================
            class PerformanceOptimizer {
                constructor() {
                    this.setupLazyLoading();
                    this.setupAnalytics();
                }

                setupLazyLoading() {
                    if ('IntersectionObserver' in window) {
                        const lazyImages = document.querySelectorAll('img[data-src]');

                        const imageObserver = new IntersectionObserver((entries) => {
                            entries.forEach(entry => {
                                if (entry.isIntersecting) {
                                    const img = entry.target;
                                    img.src = img.dataset.src;
                                    img.removeAttribute('data-src');
                                    imageObserver.unobserve(img);
                                }
                            });
                        });

                        lazyImages.forEach(img => imageObserver.observe(img));
                    }
                }

                setupAnalytics() {
                    // Aquí iría la configuración de Google Analytics u otro sistema de análisis
                    console.log('Dashboard cargado - Usuario: <?php echo htmlspecialchars($user_name); ?>');
                }
            }

            // ==========================================================================
            // INICIALIZACIÓN DE LA APLICACIÓN
            // ==========================================================================
            document.addEventListener('DOMContentLoaded', () => {
                // Inicializar componentes
                const themeManager = new ThemeManager();
                const sidebarManager = new SidebarManager();
                const dynamicComponents = new DynamicComponents();
                const performanceOptimizer = new PerformanceOptimizer();

                // Exponer APIs necesarias globalmente
                window.dashboard = {
                    theme: themeManager,
                    sidebar: sidebarManager,
                    components: dynamicComponents
                };

                // Log de inicialización
                console.log('Dashboard CMS v4.0 inicializado correctamente');
                console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
                console.log('Rol: <?php echo htmlspecialchars($user_type); ?>');
                console.log('Tema: ' + themeManager.theme);
                console.log('Sidebar: ' + (sidebarManager.isCollapsed ? 'colapsado' : 'expandido'));
            });

            // ==========================================================================
            // MANEJO DE ERRORES GLOBALES
            // ==========================================================================
            window.addEventListener('error', (event) => {
                console.error('Error en dashboard:', event.error);

                // En producción, enviar error al servidor
                if (window.location.hostname !== 'localhost') {
                    const errorData = {
                        message: event.message,
                        source: event.filename,
                        lineno: event.lineno,
                        colno: event.colno,
                        user: '<?php echo htmlspecialchars($user_name); ?>',
                        timestamp: new Date().toISOString()
                    };

                    // Aquí iría una petición fetch para enviar el error al servidor
                    console.log('Error reportado:', errorData);
                }
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
                    function (s) {
                        const matches = (this.document || this.ownerDocument).querySelectorAll(s);
                        let i = matches.length;
                        while (--i >= 0 && matches.item(i) !== this) { }
                        return i > -1;
                    };
            }

        })();

        // Manejar envío del formulario de nuevo paciente
        document.getElementById('newPatientForm')?.addEventListener('submit', function (e) {
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

    <!-- Inyectar script de mantenimiento de sesión activo (Global) -->
    <?php output_keep_alive_script(); ?>
    <!-- Modal Cobro Procedimientos -->
    <div class="modal fade" id="procedureBillingModal" tabindex="-1">
        <div class="modal-dialog modal-premium modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-bandaid me-2"></i>Cobro de Procedimiento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="procedureBillingForm">
                        <div class="mb-3">
                            <label class="form-label">Paciente</label>
                            <div class="position-relative">
                                <input class="form-control" id="procedure_patient_input"
                                    placeholder="Buscar paciente..." required autocomplete="off">
                                <div id="procedurePatientDropdown" class="patient-search-dropdown position-absolute w-100 mt-1 d-none"
                                    style="z-index: 1060; max-height: 250px; overflow-y: auto;"></div>
                            </div>
                            <input type="hidden" id="procedure_patient_id" name="patient_id">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Procedimiento</label>
                            <select class="form-select" id="procedureSelect" name="procedure" required
                                onchange="updateProcedurePrice()">
                                <option value="">Seleccione...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Horario</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="schedule_type" id="scheduleHabil"
                                    value="habil" checked onchange="updateProcedurePrice()">
                                <label class="btn btn-outline-primary" for="scheduleHabil">Hábil</label>

                                <input type="radio" class="btn-check" name="schedule_type" id="scheduleInhabil"
                                    value="inhabil" onchange="updateProcedurePrice()">
                                <label class="btn btn-outline-primary" for="scheduleInhabil">Inhábil</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Precio (Q)</label>
                            <input type="number" class="form-control" name="amount" id="procedurePrice" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Tipo de Pago</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="tipo_pago" id="proc_pago_efectivo"
                                    value="Efectivo" checked autocomplete="off">
                                <label class="btn btn-outline-primary" for="proc_pago_efectivo">
                                    <i class="bi bi-cash me-1"></i>Efectivo
                                </label>

                                <input type="radio" class="btn-check" name="tipo_pago" id="proc_pago_transferencia"
                                    value="Transferencia" autocomplete="off">
                                <label class="btn btn-outline-primary" for="proc_pago_transferencia">
                                    <i class="bi bi-bank me-1"></i>Transferencia
                                </label>

                                <input type="radio" class="btn-check" name="tipo_pago" id="proc_pago_tarjeta"
                                    value="Tarjeta" autocomplete="off">
                                <label class="btn btn-outline-primary" for="proc_pago_tarjeta">
                                    <i class="bi bi-credit-card me-1"></i>Tarjeta
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="submitProcedureBilling()">Cobrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Ultrasonido -->
    <div class="modal fade" id="ultrasoundBillingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-premium modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-activity me-2"></i>Cobro de Ultrasonido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="ultrasoundBillingForm">
                        <div class="mb-3">
                            <label class="form-label">Paciente</label>
                            <div class="position-relative">
                                <input class="form-control" id="ultrasound_patient_input"
                                    placeholder="Buscar paciente..." required autocomplete="off">
                                <div id="ultrasoundPatientDropdown" class="patient-search-dropdown position-absolute w-100 mt-1 d-none"
                                    style="z-index: 1060; max-height: 250px; overflow-y: auto;"></div>
                            </div>
                            <input type="hidden" id="ultrasound_patient_id" name="patient_id">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo de Ultrasonido</label>
                            <select class="form-select" id="ultrasoundSelect" name="ultrasound_type" required>
                                <option value="">Seleccione...</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase text-muted">Horario / Tarifa</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check us-rate-type" name="us_rate_type"
                                    id="us_rate_normal" value="normal" checked>
                                <label class="btn btn-outline-secondary" for="us_rate_normal">Normal</label>

                                <input type="radio" class="btn-check us-rate-type" name="us_rate_type"
                                    id="us_rate_inhabil" value="inhabil">
                                <label class="btn btn-outline-secondary" for="us_rate_inhabil">Inhabil</label>

                                <input type="radio" class="btn-check us-rate-type" name="us_rate_type"
                                    id="us_rate_radio" value="radio">
                                <label class="btn btn-outline-secondary" for="us_rate_radio">Radiólogo</label>

                                <input type="radio" class="btn-check us-rate-type" name="us_rate_type"
                                    id="us_rate_iradio" value="iradio">
                                <label class="btn btn-outline-secondary" for="us_rate_iradio">Inh. Rad.</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Monto a Cobrar (Q)</label>
                            <input type="number" class="form-control" id="ultrasound_amount" name="amount" readonly
                                step="0.01" placeholder="0.00">
                            <small class="text-muted">El monto se actualiza al seleccionar el tipo y tarifa</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo de Pago</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="tipo_pago" id="ultrasound_pago_efectivo"
                                    value="Efectivo" checked autocomplete="off">
                                <label class="btn btn-outline-info" for="ultrasound_pago_efectivo">
                                    <i class="bi bi-cash me-1"></i>Efectivo
                                </label>
                                <input type="radio" class="btn-check" name="tipo_pago"
                                    id="ultrasound_pago_transferencia" value="Transferencia" autocomplete="off">
                                <label class="btn btn-outline-info" for="ultrasound_pago_transferencia">
                                    <i class="bi bi-bank me-1"></i>Transferencia
                                </label>
                                <input type="radio" class="btn-check" name="tipo_pago" id="ultrasound_pago_tarjeta"
                                    value="Tarjeta" autocomplete="off">
                                <label class="btn btn-outline-info" for="ultrasound_pago_tarjeta">
                                    <i class="bi bi-credit-card me-1"></i>Tarjeta
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-info" id="saveUltrasoundBtn">Guardar Cobro</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Electrocardiograma -->
    <div class="modal fade" id="electroBillingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-premium modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-heart-pulse me-2"></i>Cobro de Electrocardiograma</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="electroBillingForm">
                        <div class="mb-3">
                            <label class="form-label">Paciente</label>
                            <div class="position-relative">
                                <input class="form-control" id="electro_paciente_input"
                                    placeholder="Buscar paciente..." required autocomplete="off">
                                <div id="electroPatientDropdown" class="patient-search-dropdown position-absolute w-100 mt-1 d-none"
                                    style="z-index: 1060; max-height: 250px; overflow-y: auto;"></div>
                            </div>
                            <input type="hidden" id="electro_paciente" name="id_paciente">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Doctor (Opcional)</label>
                            <select class="form-select" id="electro_doctor" name="id_doctor">
                                <option value="">Seleccione Doctor</option>
                                <?php foreach ($doctores as $doc): ?>
                                        <option value="<?php echo $doc['idUsuario']; ?>">
                                            <?php echo htmlspecialchars($doc['nombre'] . ' ' . $doc['apellido']); ?>
                                        </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Horario</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="electro_horario" id="electro_habil"
                                    value="habil" onchange="updateElectroPrice()" checked autocomplete="off">
                                <label class="btn btn-outline-danger" for="electro_habil">Normal</label>

                                <input type="radio" class="btn-check" name="electro_horario" id="electro_inhabil"
                                    value="inhabil" onchange="updateElectroPrice()" autocomplete="off">
                                <label class="btn btn-outline-danger" for="electro_inhabil">Inhábil/Finde</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Monto (Q)</label>
                            <input type="number" class="form-control" id="electro_precio" name="precio" value=""
                                required step="0.01" placeholder="0.00">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo de Pago</label>
                            <select class="form-select" name="tipo_pago">
                                <option value="Efectivo">Efectivo</option>
                                <option value="Tarjeta">Tarjeta</option>
                                <option value="Transferencia">Transferencia</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="saveElectroBtn">Guardar Cobro</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Rayos X -->
    <div class="modal fade" id="xrayBillingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-premium modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-file-medical me-2"></i>Cobro de Rayos X</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="xrayBillingForm">
                        <div class="mb-3">
                            <label class="form-label">Paciente</label>
                            <div class="position-relative">
                                <input class="form-control" id="xray_patient_input"
                                    placeholder="Buscar paciente..." required autocomplete="off">
                                <div id="xrayPatientDropdown" class="patient-search-dropdown position-absolute w-100 mt-1 d-none"
                                    style="z-index: 1060; max-height: 250px; overflow-y: auto;"></div>
                            </div>
                            <input type="hidden" id="xray_patient_id" name="patient_id">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Número de Regiones</label>
                            <select class="form-select" id="xray_regions" onchange="updateXrayPrice()">
                                <option value="1">1 Región</option>
                                <option value="2">2 Regiones</option>
                                <option value="3">3 Regiones</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo de Estudio (Rayos X)</label>
                            <input type="text" class="form-control" name="xray_type" required
                                placeholder="Ej: Torax, Mano, etc.">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Monto a Cobrar (Q)</label>
                            <input type="number" class="form-control" id="xray_amount" name="amount" required
                                step="0.01" placeholder="0.00">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo de Pago</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="tipo_pago" id="xray_pago_efectivo"
                                    value="Efectivo" checked autocomplete="off">
                                <label class="btn btn-outline-secondary" for="xray_pago_efectivo">
                                    <i class="bi bi-cash me-1"></i>Efectivo
                                </label>
                                <input type="radio" class="btn-check" name="tipo_pago" id="xray_pago_transferencia"
                                    value="Transferencia" autocomplete="off">
                                <label class="btn btn-outline-secondary" for="xray_pago_transferencia">
                                    <i class="bi bi-bank me-1"></i>Transferencia
                                </label>
                                <input type="radio" class="btn-check" name="tipo_pago" id="xray_pago_tarjeta"
                                    value="Tarjeta" autocomplete="off">
                                <label class="btn btn-outline-secondary" for="xray_pago_tarjeta">
                                    <i class="bi bi-credit-card me-1"></i>Tarjeta
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <div class="me-auto small text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        <span id="xrayPriceHint">Seleccione región para ver precio</span>
                    </div>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-secondary" id="saveXrayBtn">Guardar Cobro</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let tarifas = {
            consulta: {},
            reconsulta: {},
            electrocardiograma: {},
            procedimiento: {},
            rayos_x: {},
            ultrasonido: {}
        };

        async function loadTarifas() {
            try {
                const response = await fetch('../settings/api/get_tarifas.php');
                if (!response.ok) {
                    console.warn('Could not load tarifas, using defaults');
                    return;
                }
                const res = await response.json();
                if (res.success && res.tarifas) {
                    const t = res.tarifas;

                    if (Array.isArray(t.consulta) && t.consulta.length > 0) {
                        const consultaObj = {};
                        t.consulta.forEach(item => {
                            if (item.id_medico) consultaObj[item.id_medico] = { normal: item.precio_normal, inutil: item.precio_inhabil };
                        });
                        tarifas.consulta = consultaObj;
                    }
                    if (Array.isArray(t.reconsulta) && t.reconsulta.length > 0) {
                        const reconsObj = {};
                        t.reconsulta.forEach(item => {
                            if (item.id_medico) reconsObj[item.id_medico] = { normal: item.precio_normal, inutil: item.precio_inhabil };
                        });
                        tarifas.reconsulta = reconsObj;
                    }
                    if (t.electrocardiograma && t.electrocardiograma.precio_normal) {
                        tarifas.electrocardiograma = { normal: t.electrocardiograma.precio_normal, inutil: t.electrocardiograma.precio_inhabil };
                    }
                    if (Array.isArray(t.procedimiento) && t.procedimiento.length > 0) {
                        const procObj = {};
                        t.procedimiento.forEach(item => {
                            if (item.nombre_servicio) procObj[item.nombre_servicio] = { normal: item.precio_normal, inutil: item.precio_inhabil };
                        });
                        tarifas.procedimiento = procObj;
                    }
                    if (Array.isArray(t.rayos_x) && t.rayos_x.length > 0) {
                        const rxObj = {};
                        t.rayos_x.forEach(item => {
                            if (item.region_count) rxObj[item.region_count] = { normal: item.precio_normal, inutil: item.precio_inhabil };
                        });
                        tarifas.rayos_x = rxObj;
                    }
                    if (Array.isArray(t.ultrasonido) && t.ultrasonido.length > 0) {
                        const usObj = {};
                        t.ultrasonido.forEach(item => {
                            if (item.nombre_servicio) usObj[item.nombre_servicio] = { normal: item.precio_normal, inutil: item.precio_inhabil, radio: item.precio_radio || 0, iradio: item.precio_inhabil };
                        });
                        tarifas.ultrasonido = usObj;
                    }

                    populateDropdownFromTarifas('procedureSelect', 'procedimiento');
                    populateDropdownFromTarifas('ultrasoundSelect', 'ultrasonido');
                }
            } catch (error) {
                console.warn('Using default tarifas');
            }
        }

        function populateDropdownFromTarifas(selectId, tarifaCategory, sort = true) {
            const select = document.getElementById(selectId);
            if (!select) return;
            const names = Object.keys(tarifas[tarifaCategory] || {});
            if (names.length === 0) return;
            const currentVal = select.value;
            select.innerHTML = '<option value="">Seleccione...</option>';
            (sort ? names.sort() : names).forEach(name => {
                const opt = document.createElement('option');
                opt.value = name;
                opt.textContent = name;
                select.appendChild(opt);
            });
            select.value = currentVal;
        }

document.addEventListener('DOMContentLoaded', loadTarifas);

        document.getElementById('xrayBillingModal')?.addEventListener('shown.bs.modal', updateXrayPrice);
        document.getElementById('electroBillingModal')?.addEventListener('shown.bs.modal', updateElectroPrice);
        document.getElementById('procedureBillingModal')?.addEventListener('shown.bs.modal', updateProcedurePrice);
        document.getElementById('ultrasoundBillingModal')?.addEventListener('shown.bs.modal', function() {
            const select = document.getElementById('ultrasoundSelect');
            if (select) select.dispatchEvent(new Event('change'));
        });

        // Initialize patient search for procedure billing modal
        if (document.getElementById('procedure_patient_input')) {
            setupPatientSearch('procedure_patient_input', 'procedurePatientDropdown', 'procedure_patient_id');
        }

        function updateElectroPrice() {
            const isHabil = document.getElementById('electro_habil').checked;
            const priceField = document.getElementById('electro_precio');
            if (tarifas.electrocardiograma && tarifas.electrocardiograma.normal) {
                priceField.value = isHabil ? tarifas.electrocardiograma.normal.toFixed(2) : tarifas.electrocardiograma.inutil.toFixed(2);
            } else {
                priceField.value = '';
            }
        }

        function updateProcedurePrice() {
            const procedure = document.getElementById('procedureSelect').value;
            const isHabil = document.getElementById('scheduleHabil').checked;
            const priceField = document.getElementById('procedurePrice');

            if (procedure && tarifas.procedimiento[procedure]) {
                const price = isHabil ? tarifas.procedimiento[procedure].normal : tarifas.procedimiento[procedure].inutil;
                priceField.value = price.toFixed(2);
            } else {
                priceField.value = '';
            }
        }

        function updateXrayPrice() {
            const regions = parseInt(document.getElementById('xray_regions').value) || 1;
            const priceField = document.getElementById('xray_amount');
            const date = new Date();
            const day = date.getDay();
            const hour = date.getHours();
            const isHabil = day >= 1 && day <= 6 && !(day === 6 && hour >= 13);

            if (tarifas.rayos_x && tarifas.rayos_x[regions]) {
                const price = isHabil ? tarifas.rayos_x[regions].normal : tarifas.rayos_x[regions].inutil;
                priceField.value = price.toFixed(2);
            } else {
                priceField.value = '';
            }
        }

        function submitProcedureBilling() {
            const form = document.getElementById('procedureBillingForm');
            const patientHidden = document.getElementById('procedure_patient_id');
            const procedure = document.getElementById('procedureSelect').value;

            if (!patientHidden.value) {
                Swal.fire('Aviso', 'Por favor seleccione un paciente válido de la lista', 'warning');
                return;
            }

            if (!procedure) {
                Swal.fire('Aviso', 'Por favor seleccione un procedimiento', 'warning');
                return;
            }

            const formData = new FormData(form);

            apiPost('api/save_procedure_charge.php', formData)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire('Éxito', 'Cobro registrado exitosamente', 'success').then(() => {
                            window.open('print_procedure_receipt.php?id=' + data.id, '_blank');
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', data.message || 'Error desconocido', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'Error al procesar el cobro', 'error');
                });
        }

        function lockedModule(moduleName) {
            Swal.fire({
                title: 'Módulo Bloqueado',
                text: 'El módulo "' + moduleName + '" no está incluido en su suscripción actual o no ha sido adquirido.',
                icon: 'lock',
                showCancelButton: true,
                confirmButtonText: 'Contactar Ventas',
                cancelButtonText: 'Cerrar',
                confirmButtonColor: '#0d6efd'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.open('https://wa.me/tu_numero_whatsapp', '_blank');
                }
            });
        }
    </script>
    <!-- Modal Configuración del Dashboard -->
    <div class="modal fade" id="dashboardConfigModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-sliders me-2"></i>Configuración de Widgets
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6 class="fw-bold mb-3 border-bottom pb-2">Widgets Visibles</h6>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="toggle-quick-actions" checked>
                        <label class="form-check-label" for="toggle-quick-actions">Acciones Rápidas (Cobros,
                            etc.)</label>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="toggle-stats" checked>
                        <label class="form-check-label" for="toggle-stats">Estadísticas Principales</label>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="toggle-appointments" checked>
                        <label class="form-check-label" for="toggle-appointments">Citas de Hoy</label>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="toggle-hospitalized" checked>
                        <label class="form-check-label" for="toggle-hospitalized">Pacientes Hospitalizados</label>
                    </div>
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" id="toggle-alerts" checked>
                        <label class="form-check-label" for="toggle-alerts">Panel de Alertas (Inventario)</label>
                    </div>

                    <h6 class="fw-bold mb-3 border-bottom pb-2">Parámetros de Alertas</h6>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Días límite para Vencimiento (Medicamentos)</label>
                        <input type="number" class="form-control" id="config-expiring-days" value="180">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Cantidad límite para Stock Bajo</label>
                        <input type="number" class="form-control" id="config-low-stock" value="5">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary px-4" onclick="saveDashboardConfig()">Guardar y
                        Aplicar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function setCookie(name, value, days) {
            var expires = "";
            if (days) {
                var date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = "; expires=" + date.toUTCString();
            }
            document.cookie = name + "=" + (value || "") + expires + "; path=/";
        }

        // Apply widget visibility on load without reloading
        function applyWidgetVisibility() {
            const saved = localStorage.getItem('dashboard_widgets_config');
            if (saved) {
                const config = JSON.parse(saved);
                for (const [id, visible] of Object.entries(config)) {
                    const el = document.getElementById(id);
                    if (el) {
                        el.style.display = visible ? '' : 'none';
                    }
                }
            } else {
                const legacy = JSON.parse(localStorage.getItem('dashboard-widgets') || '{}');
                const toggleWidget = (id, show) => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.style.display = show !== false ? '' : 'none';
                    }
                };
                toggleWidget('widget-quick-actions', legacy['quick-actions']);
                toggleWidget('widget-stats', legacy['stats']);
                toggleWidget('widget-appointments', legacy['appointments']);
                toggleWidget('widget-hospitalized', legacy['hospitalized']);
                toggleWidget('widget-alerts', legacy['alerts']);
            }
        }

        // Run on load
        document.addEventListener("DOMContentLoaded", applyWidgetVisibility);

        function openDashboardConfig() {
            const widgets = [
                { id: 'widget-quick-actions', name: 'Acciones Rápidas' },
                { id: 'widget-stats', name: 'Estadísticas Principales' },
                { id: 'widget-appointments', name: 'Citas de Hoy' },
                { id: 'widget-hospitalized', name: 'Pacientes Hospitalizados' },
                { id: 'widget-alerts', name: 'Panel de Alertas' },
                { id: 'widget-revenue', name: 'Ingresos Mensuales' },
                { id: 'widget-inventory', name: 'Monitoreo de Inventario' },
                { id: 'widget-patients', name: 'Últimos Pacientes Registrados' },
                { id: 'widget-calendar', name: 'Agenda Semanal' },
                { id: 'widget-labs', name: 'Órdenes de Laboratorio' }
            ];

            let html = '<div class="text-start p-2" style="max-height: 400px; overflow-y: auto;">';
            widgets.forEach(w => {
                const el = document.getElementById(w.id);
                if (el) {
                    const isVisible = el.style.display !== 'none';
                    html += `
                    <div class="form-check form-switch mb-3">
                         <input class="form-check-input widget-toggle fs-5" type="checkbox" role="switch" id="toggle-${w.id}" value="${w.id}" ${isVisible ? 'checked' : ''}>
                         <label class="form-check-label ms-2 mt-1 fw-bold" for="toggle-${w.id}">${w.name}</label>
                    </div>`;
                }
            });
            html += '</div>';

            Swal.fire({
                title: '<i class="bi bi-sliders text-primary me-2"></i>Personalizar Dashboard',
                html: html,
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-check-lg me-1"></i>Guardar Cambios',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: 'var(--color-primary)',
                cancelButtonColor: 'var(--color-secondary)',
                preConfirm: () => {
                    const toggles = document.querySelectorAll('.widget-toggle');
                    const config = {};
                    toggles.forEach(t => {
                        config[t.value] = t.checked;
                    });
                    return config;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const config = result.value;

                    // Guardar en LocalStorage para actualización reactiva al instante
                    localStorage.setItem('dashboard_widgets_config', JSON.stringify(config));

                    // Sincronizar legacy config para backwards compatibility
                    const legacy = {
                        'quick-actions': config['widget-quick-actions'] !== false,
                        'stats': config['widget-stats'] !== false,
                        'appointments': config['widget-appointments'] !== false,
                        'hospitalized': config['widget-hospitalized'] !== false,
                        'alerts': config['widget-alerts'] !== false
                    };
                    localStorage.setItem('dashboard-widgets', JSON.stringify(legacy));

                    applyWidgetVisibility();

                    // Enviar al servidor mediante AJAX para persistencia en BD
                    apiPost('api/update_widget_visibility.php', { config: config })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: '¡Guardado en el Servidor!',
                                    text: 'Tu dashboard personalizado se ha sincronizado correctamente.',
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                            } else {
                                console.error('Error al guardar en BD:', data.error);
                            }
                        })
                        .catch(err => {
                            console.error('Error de red:', err);
                        });
                }
            });
        }

        function saveDashboardConfig() {
            // Keep this for backward compatibility if old buttons reference it
            openDashboardConfig();
        }

        // ==========================================
        // Prevención de doble submit en formularios
        // ==========================================
        document.addEventListener('submit', function (e) {
            const form = e.target;
            if (form.closest('.modal')) {
                const btn = form.querySelector('button[type="submit"]');
                if (btn) {
                    if (btn.disabled) {
                        e.preventDefault();
                        return;
                    }
                    btn.disabled = true;
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Procesando...';

                    setTimeout(() => {
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = originalText;
                        }
                    }, 10000);
                }
            }
        });

        async function refreshWeeklyAppointments() {
            const loader = document.getElementById('weeklyAppointmentsLoader');
            const list = document.getElementById('weeklyAppointmentsList');
            const empty = document.getElementById('weeklyAppointmentsEmpty');
            if (!loader || !list || !empty) return;

            loader.style.display = 'block';
            list.style.display = 'none';
            empty.style.display = 'none';

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await fetch('api/get_weekly_appointments.php', {
                    headers: { 'X-CSRF-TOKEN': csrfToken }
                });

                // Check if response is JSON before parsing
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    // If not JSON, check response status and show appropriate error
                    if (response.status === 401 || response.status === 403) {
                        console.warn('Weekly appointments: Unauthorized');
                    } else {
                        console.warn('Weekly appointments: Non-JSON response', response.status);
                    }
                    loader.style.display = 'none';
                    empty.style.display = 'block';
                    return;
                }

                const data = await response.json();

                loader.style.display = 'none';

                if (data.status === 'success' && data.appointments && data.appointments.length > 0) {
                    const weekLabel = `${data.week_start} al ${data.week_end}`;
                    let html = `<div class="mb-2 text-muted small">Semana: ${weekLabel}</div>`;
                    html += '<div class="table-responsive"><table class="table table-sm table-hover small mb-0">';
                    html += '<thead><tr><th>Fecha</th><th>Hora</th><th>Paciente</th><th>Doctor</th><th>Estado</th></tr></thead><tbody>';
                    data.appointments.forEach(apt => {
                        const fecha = apt.fecha_cita ? apt.fecha_cita.split(' ')[0].split('-').reverse().join('/') : 'N/A';
                        const hora = apt.hora_cita ? apt.hora_cita.substring(0, 5) : 'N/A';
                        const paciente = apt.paciente_nombre ? `${apt.paciente_nombre} ${apt.paciente_apellido || ''}` : 'N/A';
                        const doctor = apt.doctor_nombre ? `Dr(a). ${apt.doctor_nombre} ${apt.doctor_apellido || ''}` : 'N/A';
                        const estadoClass = apt.estado === 'Completada' ? 'bg-success text-white' : apt.estado === 'Cancelada' ? 'bg-danger text-white' : 'bg-warning text-dark';
                        html += `<tr><td>${fecha}</td><td>${hora}</td><td>${paciente}</td><td>${doctor}</td><td><span class="badge ${estadoClass}">${apt.estado || 'Pendiente'}</span></td></tr>`;
                    });
                    html += '</tbody></table></div>';
                    list.innerHTML = html;
                    list.style.display = 'block';
                } else {
                    empty.style.display = 'block';
                }
            } catch (e) {
                console.error('Error loading weekly appointments:', e);
                loader.style.display = 'none';
                empty.style.display = 'block';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            refreshWeeklyAppointments();
        });
    </script>

    <!-- PWA Install Prompt + Service Worker -->
    <script src="../../assets/js/install-prompt.js?v=1"></script>
</body>

</html>