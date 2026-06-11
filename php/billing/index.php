<?php
// index.php - Módulo de Cobros - Centro Médico Herrera Saenz
// Diseño Responsive, Barra Lateral Moderna, Efecto Mármol
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

check_module_access('core'); // Cobros es módulo base

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

    // Obtener todos los pacientes para el dropdown
    $stmt = $conn->prepare("SELECT id_paciente, CONCAT(nombre, ' ', apellido) as nombre_completo FROM pacientes WHERE id_hospital = ? ORDER BY nombre");
    $stmt->execute([hospital_id()]);
    $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener doctores (usuarios tipo 'doc')
    $stmtDoc = $conn->prepare("SELECT idUsuario, nombre, apellido FROM usuarios WHERE tipoUsuario = 'doc' AND id_hospital = ? ORDER BY nombre");
    $stmtDoc->execute([hospital_id()]);
    $doctores = $stmtDoc->fetchAll(PDO::FETCH_ASSOC);

    // Registro unificado de cobros (todas las fuentes)
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = 25;
    $offset = ($page - 1) * $limit;
    $tipo_filtro = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';
    $hid = hospital_id();

    $tipos_cobro_disponibles = [
        '' => 'Todos',
        'Consulta' => 'Consulta',
        'Reconsulta' => 'Reconsulta',
        'Farmacia' => 'Farmacia',
        'Laboratorio' => 'Laboratorio',
        'Examen' => 'Examen',
        'Procedimiento' => 'Procedimiento',
        'Ultrasonido' => 'Ultrasonido',
        'Rayos X' => 'Rayos X',
        'Electrocardiograma' => 'Electrocardiograma',
    ];

    $union_sql = "
        SELECT 'cobro' AS fuente, c.in_cobro AS id_registro,
            CONCAT(p.nombre, ' ', p.apellido) AS nombre_paciente,
            CASE WHEN COALESCE(c.tipo_consulta, '') = 'Reconsulta' THEN 'Reconsulta' ELSE 'Consulta' END AS tipo_cobro,
            COALESCE(c.tipo_consulta, 'Consulta') AS detalle,
            c.cantidad_consulta AS monto, COALESCE(c.tipo_pago, 'Efectivo') AS tipo_pago, c.fecha_consulta AS fecha
        FROM cobros c
        INNER JOIN pacientes p ON c.paciente_cobro = p.id_paciente
        WHERE c.id_hospital = ?

        UNION ALL

        SELECT 'venta', v.id_venta, COALESCE(v.nombre_cliente, 'Cliente general'), 'Farmacia',
            'Venta de medicamentos', v.total, COALESCE(v.tipo_pago, 'Efectivo'), v.fecha_venta
        FROM ventas v
        WHERE v.id_hospital = ? AND v.tipo_pago != 'Traslado'

        UNION ALL

        SELECT 'examen', e.id_examen_realizado, e.nombre_paciente,
            CASE
                WHEN e.tipo_examen LIKE '%ultrasonido%' THEN 'Ultrasonido'
                WHEN e.tipo_examen LIKE '%rayos x%' OR e.tipo_examen LIKE '%rx%' THEN 'Rayos X'
                ELSE 'Laboratorio'
            END,
            COALESCE(e.tipo_examen, 'Examen de laboratorio'),
            e.cobro, COALESCE(e.tipo_pago, 'Efectivo'), e.fecha_examen
        FROM examenes_realizados e
        WHERE e.id_hospital = ?

        UNION ALL

        SELECT 'procedimiento', pm.id_procedimiento, pm.nombre_paciente, 'Procedimiento',
            COALESCE(pm.procedimiento, 'Procedimiento menor'),
            pm.cobro, COALESCE(pm.tipo_pago, 'Efectivo'), pm.fecha_procedimiento
        FROM procedimientos_menores pm
        WHERE pm.id_hospital = ?

        UNION ALL

        SELECT 'ultrasonido', u.id_ultrasonido, u.nombre_paciente, 'Ultrasonido',
            COALESCE(u.tipo_ultrasonido, 'Ultrasonido'),
            u.cobro, COALESCE(u.tipo_pago, 'Efectivo'), u.fecha_ultrasonido
        FROM ultrasonidos u
        WHERE u.id_hospital = ?

        UNION ALL

        SELECT 'rayos_x', r.id_rayos_x, r.nombre_paciente, 'Rayos X',
            COALESCE(r.tipo_estudio, 'Rayos X'),
            r.cobro, COALESCE(r.tipo_pago, 'Efectivo'), r.fecha_estudio
        FROM rayos_x r
        WHERE r.id_hospital = ?

        UNION ALL

        SELECT 'electro', el.id_electro,
            TRIM(CONCAT(COALESCE(p2.nombre, ''), ' ', COALESCE(p2.apellido, ''))),
            'Electrocardiograma', 'Electrocardiograma',
            el.precio, COALESCE(el.tipo_pago, 'Efectivo'),
            COALESCE(el.fecha_realizado, NOW())
        FROM electrocardiogramas el
        LEFT JOIN pacientes p2 ON el.id_paciente = p2.id_paciente
        WHERE el.id_hospital = ?
    ";

    $hospital_params = array_fill(0, 7, $hid);

    $count_sql = "SELECT COUNT(*) FROM ($union_sql) AS registro";
    $count_params = $hospital_params;
    if ($tipo_filtro !== '' && isset($tipos_cobro_disponibles[$tipo_filtro])) {
        $count_sql = "SELECT COUNT(*) FROM ($union_sql) AS registro WHERE tipo_cobro = ?";
        $count_params[] = $tipo_filtro;
    }
    $stmt = $conn->prepare($count_sql);
    $stmt->execute($count_params);
    $total_records = (int) $stmt->fetchColumn();
    $total_pages = max(1, (int) ceil($total_records / $limit));

    $data_sql = "SELECT * FROM ($union_sql) AS registro";
    $data_params = $hospital_params;
    if ($tipo_filtro !== '' && isset($tipos_cobro_disponibles[$tipo_filtro])) {
        $data_sql .= " WHERE tipo_cobro = ?";
        $data_params[] = $tipo_filtro;
    }
    // LIMIT/OFFSET como enteros en SQL (PDO con ? los envía como string y MySQL falla)
    $data_sql .= ' ORDER BY fecha DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

    $stmt = $conn->prepare($data_sql);
    $stmt->execute($data_params);
    $cobros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Título de la página
    $page_title = "Cobros - Centro Médico Herrera Saenz";

    // Obtener estadísticas rápidas
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM cobros WHERE DATE(fecha_consulta) = CURDATE() AND id_hospital = ?");
    $stmt->execute([hospital_id()]);
    $hoy_cobros = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stmt = $conn->prepare("SELECT SUM(cantidad_consulta) as total FROM cobros WHERE MONTH(fecha_consulta) = MONTH(CURDATE()) AND id_hospital = ?");
    $stmt->execute([hospital_id()]);
    $mes_cobros_consulta = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Total del mes sumando todas las fuentes (7-way UNION)
    $mes_total_sql = "SELECT COALESCE(SUM(monto), 0) as total FROM ($union_sql) AS registro WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())";
    $stmt = $conn->prepare($mes_total_sql);
    $stmt->execute($hospital_params);
    $mes_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

} catch (Exception $e) {
    error_log("Error en módulo de cobros: " . $e->getMessage());
    $error_detail = ($_SESSION['tipoUsuario'] ?? '') === 'admin'
        ? htmlspecialchars($e->getMessage())
        : '';
    die(
        "Error al cargar el módulo de cobros. Por favor, contacte al administrador."
        . ($error_detail ? "<br><small class=\"text-muted\">Detalles: {$error_detail}</small>" : '')
    );
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Módulo de Cobros - Centro Médico Herrera Saenz - Sistema de gestión de cobros médicos">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">

    <!-- logo -->
    <link rel="icon" type="image/png" href="../../assets/img/cmhs.png">

    <!-- Google Fonts - Inter (moderno y legible) -->
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Bootstrap CSS (Required for Modals) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Seguridad y Protección de Código -->
    <script src="../../assets/js/security.js"></script>

    <!-- CSS Crítico (incrustado para máxima velocidad) -->
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">

</head>

<body>
    <!-- Efecto de mármol animado -->
    <div class="marble-effect"></div>

    <!-- Contenedor Principal -->
    <div class="dashboard-container">
        <!-- Header Superior -->
        <header class="dashboard-header">
            <div class="header-content">
                <!-- logo -->
                <div class="brand-container">
                    <img src="../../assets/img/cmhs.png" alt="Centro Médico Herrera Saenz" class="brand-logo" width="40"
                        height="40">
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

        <!-- Contenido Principal -->
        <main class="main-content">
            <?php render_breadcrumbs([
                ['label' => 'Dashboard', 'url' => '../dashboard/index.php'],
                ['label' => 'Cobros'],
            ]); ?>
            <!-- Bienvenida personalizada -->
            <div class="stat-card mb-4 animate-in">
                <div class="stat-header">
                    <div>
                        <h2 class="stat-value" style="font-size: 1.75rem; margin-bottom: 0.5rem;">
                            <span id="greeting-text">Módulo de Cobros</span>
                        </h2>
                        <p class="text-muted mb-0">
                            <i class="bi bi-cash-coin me-1"></i> Gestión de recaudación y recibos médicos
                            <span class="mx-2">•</span>
                            <i class="bi bi-calendar-check me-1"></i> <?php echo date('d/m/Y'); ?>
                            <span class="mx-2">•</span>
                            <i class="bi bi-clock me-1"></i> <span id="current-time"><?php echo date('H:i'); ?></span>
                        </p>
                    </div>
                    <div class="d-none d-md-block">
                        <i class="bi bi-cash-coin text-primary" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>

            <!-- Estadísticas principales -->
            <div class="stats-grid">
                <!-- Cobros de hoy -->
                <div class="stat-card animate-in delay-1">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Cobros Hoy</div>
                            <div class="stat-value"><?php echo $hoy_cobros; ?></div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-arrow-up-right"></i>
                        <span>Registrados hoy</span>
                    </div>
                </div>

                <!-- Total del mes -->
                <div class="stat-card animate-in delay-2">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Total Mes</div>
                            <div class="stat-value">Q<?php echo number_format($mes_total, 2); ?></div>
                        </div>
                        <div class="stat-icon success">
                            <i class="bi bi-currency-dollar"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-graph-up"></i>
                        <span>Recaudación mensual</span>
                    </div>
                </div>

                <!-- Total cobros -->
                <div class="stat-card animate-in delay-3">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Total Cobros</div>
                            <div class="stat-value"><?php echo $total_records; ?></div>
                        </div>
                        <div class="stat-icon info">
                            <i class="bi bi-receipt"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-archive"></i>
                        <span>Registros totales</span>
                    </div>
                </div>

                <!-- Páginas -->
                <div class="stat-card animate-in delay-4">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Página</div>
                            <div class="stat-value"><?php echo $page; ?>/<?php echo $total_pages; ?></div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="bi bi-file-text"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-collection"></i>
                        <span>Paginación</span>
                    </div>
                </div>
            </div>

            <!-- Registro de Cobros (premium) -->
            <section class="billing-section billing-registry animate-in delay-1">
                <div class="billing-registry__head">
                    <div class="billing-registry__title-wrap">
                        <h3 class="billing-registry__title">
                            <span class="billing-registry__title-icon">
                                <i class="bi bi-receipt-cutoff"></i>
                            </span>
                            Registro de Cobros
                        </h3>
                        <p class="billing-registry__subtitle">
                            Historial unificado de consultas, farmacia, laboratorio y más
                            <span class="billing-registry__count">
                                <i class="bi bi-collection"></i>
                                <?php echo number_format($total_records); ?> registros
                            </span>
                            <?php if ($tipo_filtro !== ''): ?>
                                    <span class="billing-registry__count">
                                        <i class="bi bi-funnel"></i>
                                        <?php echo htmlspecialchars($tipo_filtro); ?>
                                    </span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="billing-registry__actions">
                        <button type="button" class="action-btn" data-bs-toggle="modal"
                            data-bs-target="#newBillingModal">
                            <i class="bi bi-plus-lg"></i>
                            Nuevo Cobro
                        </button>
                        <a href="export_cobros.php" class="action-btn secondary">
                            <i class="bi bi-download"></i>
                            Exportar
                        </a>
                    </div>
                </div>

                <div class="billing-registry__filters-wrap">
                    <div class="billing-filters" role="group" aria-label="Filtrar por tipo de cobro">
                        <?php foreach ($tipos_cobro_disponibles as $tipo_key => $tipo_label): ?>
                                <?php
                                $filter_url = '?page=1' . ($tipo_key !== '' ? '&tipo=' . urlencode($tipo_key) : '');
                                $is_active = ($tipo_filtro === $tipo_key) || ($tipo_filtro === '' && $tipo_key === '');
                                ?>
                                <a href="<?php echo htmlspecialchars($filter_url); ?>"
                                    class="billing-btn-chip<?php echo $is_active ? ' active' : ''; ?>"
                                    <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
                                    <?php if ($tipo_key !== ''): ?>
                                            <i class="bi <?php echo charge_type_icon($tipo_key); ?>"></i>
                                    <?php else: ?>
                                            <i class="bi bi-grid"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($tipo_label); ?>
                                </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (count($cobros) > 0): ?>
                        <div class="billing-registry__table-card billing-card">
                        <div class="billing-table-wrap">
                            <div class="table-responsive">
                            <table class="billing-table">
                                <thead>
                                    <tr>
                                        <th>Paciente / Cliente</th>
                                        <th>Tipo de cobro</th>
                                        <th>Método pago</th>
                                        <th>Monto</th>
                                        <th>Fecha</th>
                                        <th>Referencia</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cobros as $cobro): ?>
                                            <?php
                                            $patient_name = trim($cobro['nombre_paciente'] ?? '') ?: 'Sin nombre';
                                            $name_parts = preg_split('/\s+/', $patient_name, 2);
                                            $patient_initials = strtoupper(
                                                substr($name_parts[0], 0, 1) .
                                                (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : '')
                                            );
                                            $tipo_cobro = $cobro['tipo_cobro'] ?? 'Consulta';
                                            $badge_class = charge_type_badge_class($tipo_cobro);
                                            $fecha_row = strtotime($cobro['fecha']);
                                            $print_url = billing_print_url($cobro['fuente'], $cobro['id_registro']);
                                            $ref_prefix = strtoupper(substr($cobro['fuente'], 0, 3));
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="patient-cell">
                                                        <div class="patient-avatar">
                                                            <?php echo htmlspecialchars($patient_initials); ?>
                                                        </div>
                                                        <div class="patient-info">
                                                            <div class="patient-name">
                                                                <?php echo htmlspecialchars($patient_name); ?>
                                                            </div>
                                                            <?php if (!empty($cobro['detalle']) && $cobro['detalle'] !== $tipo_cobro): ?>
                                                                    <span class="charge-detail-text"
                                                                        title="<?php echo htmlspecialchars($cobro['detalle']); ?>">
                                                                        <?php echo htmlspecialchars($cobro['detalle']); ?>
                                                                    </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="charge-type-badge <?php echo $badge_class; ?>">
                                                        <i class="bi <?php echo charge_type_icon($tipo_cobro); ?>"></i>
                                                        <?php echo htmlspecialchars($tipo_cobro); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="payment-badge">
                                                        <i class="bi bi-wallet2"></i>
                                                        <?php echo htmlspecialchars($cobro['tipo_pago'] ?? 'Efectivo'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="amount-badge income">
                                                        Q<?php echo number_format((float) $cobro['monto'], 2); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="billing-date-cell">
                                                        <div class="billing-date-cell__day">
                                                            <?php echo date('d/m/Y', $fecha_row); ?>
                                                        </div>
                                                        <div class="billing-date-cell__time">
                                                            <i class="bi bi-clock"></i>
                                                            <?php echo date('h:i A', $fecha_row); ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="charge-id-badge">
                                                        #<?php echo htmlspecialchars($ref_prefix); ?>-<?php echo str_pad((int) $cobro['id_registro'], 5, '0', STR_PAD_LEFT); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <?php if ($print_url !== '#'): ?>
                                                                <a href="<?php echo htmlspecialchars($print_url); ?>" target="_blank"
                                                                    class="btn-icon print" title="Imprimir recibo">
                                                                    <i class="bi bi-printer"></i>
                                                                </a>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn-icon view view-details"
                                                            data-id="<?php echo (int) $cobro['id_registro']; ?>"
                                                            data-fuente="<?php echo htmlspecialchars($cobro['fuente']); ?>"
                                                            data-nombre="<?php echo htmlspecialchars($patient_name); ?>"
                                                            data-tipo="<?php echo htmlspecialchars($tipo_cobro); ?>"
                                                            data-detalle="<?php echo htmlspecialchars($cobro['detalle'] ?? ''); ?>"
                                                            data-monto="<?php echo (float) $cobro['monto']; ?>"
                                                            data-fecha="<?php echo date('d/m/Y h:i A', $fecha_row); ?>"
                                                            data-pago="<?php echo htmlspecialchars($cobro['tipo_pago'] ?? 'Efectivo'); ?>"
                                                            data-print="<?php echo htmlspecialchars($print_url); ?>"
                                                            title="Ver detalles">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn-icon delete"
                                                            data-id="<?php echo (int) $cobro['id_registro']; ?>"
                                                            data-fuente="<?php echo htmlspecialchars($cobro['fuente']); ?>"
                                                            title="Eliminar">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                    <?php endforeach;
                                    $cobros_page_total = array_sum(array_column($cobros, 'monto'));
                                    ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-light">
                                        <td colspan="4" class="text-end fw-bold ps-4">Total de esta página:</td>
                                        <td class="text-end fw-bold text-success fs-5">Q<?php echo number_format((float)$cobros_page_total, 2); ?></td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                            </div>
                        </div>
                        </div>

<div class="billing-registry__footer">
                            <div class="billing-registry__page-hint">
                                <i class="bi bi-file-earmark-text"></i>
                                Página <?php echo $page; ?> de <?php echo $total_pages; ?>
                                &nbsp;·&nbsp;
                                <span><?php echo count($cobros); ?> de <?php echo number_format($total_records); ?> registros</span>
                            </div>
                            <?php if ($total_pages > 1): ?>
                                    <nav aria-label="Paginación de cobros">
                                        <ul class="billing-pagination">
                                            <?php $pag_tipo_qs = $tipo_filtro !== '' ? '&tipo=' . urlencode($tipo_filtro) : ''; ?>
                                            <li class="billing-pagination__item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                                <?php if ($page <= 1): ?>
                                                        <span><i class="bi bi-chevron-left"></i></span>
                                                <?php else: ?>
                                                        <a href="?page=<?php echo $page - 1; ?><?php echo $pag_tipo_qs; ?>"
                                                            aria-label="Página anterior"><i class="bi bi-chevron-left"></i></a>
                                                <?php endif; ?>
                                            </li>

                                            <?php
                                            $range = 2;
                                            $start = max(1, $page - $range);
                                            $end = min($total_pages, $page + $range);

                                            if ($start > 1): ?>
                                                    <li class="billing-pagination__item">
                                                        <a href="?page=1<?php echo $pag_tipo_qs; ?>">1</a>
                                                    </li>
                                                    <?php if ($start > 2): ?>
                                                            <li class="billing-pagination__item disabled"><span>…</span></li>
                                                    <?php endif; ?>
                                            <?php endif; ?>

                                            <?php for ($i = $start; $i <= $end; $i++): ?>
                                                    <li class="billing-pagination__item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                                        <a href="?page=<?php echo $i; ?><?php echo $pag_tipo_qs; ?>"><?php echo $i; ?></a>
                                                    </li>
                                            <?php endfor; ?>

                                            <?php if ($end < $total_pages): ?>
                                                    <?php if ($end < $total_pages - 1): ?>
                                                            <li class="billing-pagination__item disabled"><span>…</span></li>
                                                    <?php endif; ?>
                                                    <li class="billing-pagination__item">
                                                        <a href="?page=<?php echo $total_pages; ?><?php echo $pag_tipo_qs; ?>"><?php echo $total_pages; ?></a>
                                                    </li>
                                            <?php endif; ?>

                                            <li class="billing-pagination__item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                                <?php if ($page >= $total_pages): ?>
                                                        <span><i class="bi bi-chevron-right"></i></span>
                                                <?php else: ?>
                                                        <a href="?page=<?php echo $page + 1; ?><?php echo $pag_tipo_qs; ?>"
                                                            aria-label="Página siguiente"><i class="bi bi-chevron-right"></i></a>
                                                <?php endif; ?>
                                            </li>
                                        </ul>
                                    </nav>
                            <?php endif; ?>
                        </div>

                <?php else: ?>
                        <div class="billing-registry__empty">
                            <div class="billing-registry__empty-icon">
                                <i class="bi bi-receipt"></i>
                            </div>
                            <h4>No hay cobros en este filtro</h4>
                            <p>Registra un nuevo cobro o cambia el tipo de servicio en los filtros superiores.</p>
                            <button type="button" class="action-btn" data-bs-toggle="modal" data-bs-target="#newBillingModal">
                                <i class="bi bi-plus-lg"></i>
                                Registrar cobro
                            </button>
                        </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <!-- Modal para nuevo cobro -->
    <div class="modal fade" id="newBillingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
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
                            <input type="text" name="paciente_nombre" class="form-control" list="datalistOptions"
                                id="paciente_input" placeholder="Nombre del paciente (o seleccione de la lista)..."
                                required autocomplete="off">
                            <datalist id="datalistOptions">
                                <?php foreach ($pacientes as $paciente): ?>
                                        <option data-id="<?php echo $paciente['id_paciente']; ?>"
                                            value="<?php echo htmlspecialchars($paciente['nombre_completo']); ?>">
                                    <?php endforeach; ?>
                            </datalist>
                            <input type="hidden" id="paciente" name="paciente">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Médico que atiende</label>
                            <select class="form-select" id="id_doctor" name="id_doctor" required>
                                <option value="">Seleccione un médico...</option>
                                <?php foreach ($doctores as $doctor): ?>
                                        <option value="<?php echo $doctor['idUsuario']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($doctor['nombre']); ?>"
                                            data-apellido="<?php echo htmlspecialchars($doctor['apellido']); ?>">
                                            Dr(a).
                                            <?php echo htmlspecialchars($doctor['nombre'] . ' ' . $doctor['apellido']); ?>
                                        </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Tipo de Consulta</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="tipo_consulta" id="billing_btn_consulta"
                                    value="Consulta" checked autocomplete="off">
                                <label class="btn btn-outline-success" for="billing_btn_consulta">Consulta</label>

                                <input type="radio" class="btn-check" name="tipo_consulta" id="billing_btn_reconsulta"
                                    value="Reconsulta" autocomplete="off">
                                <label class="btn btn-outline-success" for="billing_btn_reconsulta">Re-Consulta</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Monto a Cobrar (Q)</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-success text-white border-0">Q</span>
                                <input type="number" class="form-control border-success text-success fw-bold"
                                    id="cantidad" name="cantidad" min="0" step="0.01" placeholder="0.00" required>
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
    </div>

    <!-- JavaScript Optimizado -->
    <script>
        // Módulo de Cobros Reingenierizado - Centro Médico Herrera Saenz

        (function () {
            'use strict';

            // ==========================================================================
            // CONFIGURACIÓN Y CONSTANTES
            // ==========================================================================
            const CONFIG = {
                themeKey: 'dashboard-theme',
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
                greetingElement: document.getElementById('greeting-text'),
                currentTimeElement: document.getElementById('current-time'),
                saveBillingBtn: document.getElementById('saveBillingBtn'),
                newBillingForm: document.getElementById('newBillingForm')
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
            // COMPONENTES DINÁMICOS
            // ==========================================================================
            class DynamicComponents {
                constructor() {
                    this.setupGreeting();
                    this.setupClock();
                    this.setupBillingHandlers();
                    this.setupAnimations();
                    this.setupModalDetails();
                    this.setupDeleteHandlers();
                }

                setupGreeting() {
                    if (!DOM.greetingElement) return;

                    const hour = new Date().getHours();
                    let greeting = '';

                    if (hour < 12) {
                        greeting = 'Buenos días';
                    } else if (hour < 19) {
                        greeting = 'Buenas tardes';
                    } else {
                        greeting = 'Buenas noches';
                    }

                    DOM.greetingElement.textContent = greeting;
                }

                setupClock() {
                    if (!DOM.currentTimeElement) return;

                    const updateClock = () => {
                        const now = new Date();
                        const timeString = now.toLocaleTimeString('es-GT', {
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: false
                        });
                        DOM.currentTimeElement.textContent = timeString;
                    };

                    updateClock();
                    setInterval(updateClock, 60000);
                }

                setupBillingHandlers() {
                    const doctorSelect = document.getElementById('id_doctor');
                    const montoInput = document.getElementById('cantidad');
                    const tipoRadios = document.getElementsByName('tipo_consulta');

                    const calculatePrice = () => {
                        const doctorId = doctorSelect.value;
                        let type = 'Consulta';
                        tipoRadios.forEach(r => { if (r.checked) type = r.value; });

                        let price = 0;
                        const date = new Date();
                        const day = date.getDay();
                        const hour = date.getHours();

                        switch (doctorId) {
                            case '17': price = (type === 'Consulta') ? 200 : 150; break;
                            case '13': price = (type === 'Consulta') ? 250 : 150; break;
                            case '18': case '11': price = (type === 'Consulta') ? 200 : 100; break;
                            case '16':
                                if (type === 'Reconsulta') price = 150;
                                else {
                                    if (day >= 1 && day <= 5) {
                                        if (hour >= 8 && hour < 16) price = 250;
                                        else if (hour >= 16 && hour < 22) price = 300;
                                        else price = 400;
                                    } else if (day === 6) {
                                        if (hour < 13) price = 250;
                                        else if (hour >= 13 && hour < 22) price = 300;
                                        else price = 400;
                                    } else {
                                        if (hour >= 8 && hour < 20) price = 350;
                                        else price = 400;
                                    }
                                }
                                break;
                            default: price = (type === 'Consulta') ? 100 : 0; break;
                        }

                        // Overrides based on name
                        const selectedOption = doctorSelect.options[doctorSelect.selectedIndex];
                        if (selectedOption) {
                            const nombre = (selectedOption.getAttribute('data-nombre') || '').toLowerCase();
                            const apellido = (selectedOption.getAttribute('data-apellido') || '').toLowerCase();

                            // Dr. Estuardo Rivas - Q400 off-hours/weekends
                            if (nombre.includes('estuardo') && apellido.includes('rivas')) {
                                if (day === 0 || day === 6 || hour >= 16) {
                                    price = 400;
                                }
                            }

                            // Dra. Libny - Q300 off-hours/weekends
                            if (nombre.includes('libny')) {
                                if (day === 0 || day === 6 || hour >= 16) {
                                    price = 300;
                                }
                            }
                        }
                        montoInput.value = price;
                    };

                    doctorSelect?.addEventListener('change', calculatePrice);
                    tipoRadios.forEach(r => r.addEventListener('change', calculatePrice));

                    // Guardar nuevo cobro
                    if (DOM.saveBillingBtn) {
                        DOM.saveBillingBtn.addEventListener('click', async () => {
                            const form = DOM.newBillingForm;

                            // Sync patient ID from datalist
                            const patientInput = document.getElementById('paciente_input');
                            const patientHidden = document.getElementById('paciente');
                            const datalist = document.getElementById('datalistOptions');

                            // Reset ID
                            patientHidden.value = '';

                            // Find ID based on name value
                            if (patientInput && datalist) {
                                const val = patientInput.value;
                                const options = datalist.options;
                                for (let i = 0; i < options.length; i++) {
                                    if (options[i].value === val) {
                                        patientHidden.value = options[i].getAttribute('data-id');
                                        break;
                                    }
                                }
                            }

                            // If no ID found (custom name), it will be handled by the backend using patient_nombre
                            // Just ensure some text is present
                            if (patientInput.value.trim() === '') {
                                Swal.fire({ title: 'Campo requerido', text: 'Por favor ingrese el nombre del paciente.', icon: 'warning' });
                                return;
                            }

                            // Validar formulario
                            if (!form.checkValidity()) {
                                form.reportValidity();
                                return;
                            }

                            const formData = new FormData(form);
                            const data = Object.fromEntries(formData.entries());

                            // Mostrar indicador de carga
                            const originalText = DOM.saveBillingBtn.innerHTML;
                            DOM.saveBillingBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Guardando...';
                            DOM.saveBillingBtn.disabled = true;

                            try {
                                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                                const response = await fetch('save_billing.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                        'X-CSRF-TOKEN': csrfToken
                                    },
                                    body: new URLSearchParams(data)
                                });

                                const result = await response.json();

                                if (result.status === 'success') {
                                    // Mostrar notificación de éxito
                                    Swal.fire({
                                        title: '¡Éxito!',
                                        text: 'Cobro guardado correctamente',
                                        icon: 'success',
                                        confirmButtonColor: 'var(--color-primary)',
                                        background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                                        color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#e2e8f0' : '#1a1a1a'
                                    }).then(() => {
                                        // Cerrar modal y recargar
                                        const modal = bootstrap.Modal.getInstance(document.getElementById('newBillingModal'));
                                        modal.hide();
                                        window.location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        title: 'Error',
                                        text: result.message || 'Error al guardar el cobro',
                                        icon: 'error',
                                        confirmButtonColor: 'var(--color-primary)'
                                    });
                                }
                            } catch (error) {
                                console.error('Error:', error);
                                Swal.fire({
                                    title: 'Error',
                                    text: 'Error de conexión con el servidor',
                                    icon: 'error',
                                    confirmButtonColor: 'var(--color-primary)'
                                });
                            } finally {
                                DOM.saveBillingBtn.innerHTML = originalText;
                                DOM.saveBillingBtn.disabled = false;
                            }
                        });
                    }
                }

                setupModalDetails() {
                    document.querySelectorAll('.view-details').forEach(btn => {
                        btn.addEventListener('click', function () {
                            const nombre = this.getAttribute('data-nombre');
                            const monto = this.getAttribute('data-monto');
                            const fecha = this.getAttribute('data-fecha');
                            const tipo = this.getAttribute('data-tipo');
                            const detalle = this.getAttribute('data-detalle');
                            const pago = this.getAttribute('data-pago');
                            const printUrl = this.getAttribute('data-print');

                            Swal.fire({
                                title: 'Detalles del Cobro',
                                html: `
                                <div class="text-start">
                                    <p><strong>Tipo:</strong> ${tipo}</p>
                                    ${detalle ? `<p><strong>Detalle:</strong> ${detalle}</p>` : ''}
                                    <p><strong>Paciente / Cliente:</strong> ${nombre}</p>
                                    <p><strong>Monto:</strong> Q${parseFloat(monto).toFixed(2)}</p>
                                    <p><strong>Método de pago:</strong> ${pago}</p>
                                    <p><strong>Fecha:</strong> ${fecha}</p>
                                </div>
                            `,
                                icon: 'info',
                                showCancelButton: true,
                                confirmButtonText: printUrl && printUrl !== '#' ? 'Imprimir Recibo' : 'Cerrar',
                                cancelButtonText: 'Cerrar',
                                confirmButtonColor: 'var(--color-primary)',
                                background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                                color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#e2e8f0' : '#1a1a1a'
                            }).then((result) => {
                                if (result.isConfirmed && printUrl && printUrl !== '#') {
                                    window.open(printUrl, '_blank');
                                }
                            });
                        });
                    });
                }

                setupDeleteHandlers() {
                    document.querySelectorAll('.btn-icon.delete').forEach(btn => {
                        btn.addEventListener('click', async function () {
                            const id = this.getAttribute('data-id');
                            const fuente = this.getAttribute('data-fuente');

                            const result = await Swal.fire({
                                title: '¿Está seguro?',
                                text: 'Esta acción eliminará el registro de cobro permanentemente.',
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonText: 'Sí, eliminar',
                                cancelButtonText: 'Cancelar',
                                confirmButtonColor: '#dc3545'
                            });

                            if (!result.isConfirmed) return;

                            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

                            try {
                                const response = await fetch('delete_cobro.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ id: id, fuente: fuente, csrf_token: csrfToken })
                                });
                                const data = await response.json();
                                if (data.success) {
                                    Swal.fire({
                                        title: 'Eliminado',
                                        text: 'Registro eliminado correctamente',
                                        icon: 'success',
                                        timer: 1500,
                                        showConfirmButton: false
                                    }).then(() => location.reload());
                                } else {
                                    Swal.fire({ title: 'Error', text: data.message, icon: 'error' });
                                }
                            } catch (e) {
                                Swal.fire({ title: 'Error', text: 'Error de conexión con el servidor', icon: 'error' });
                            }
                        });
                    });
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
                    document.querySelectorAll('.stat-card, .billing-section').forEach(el => {
                        observer.observe(el);
                    });
                }
            }

            // ==========================================================================
            // OPTIMIZACIONES DE RENDIMIENTO
            // ==========================================================================
            class PerformanceOptimizer {
                constructor() {
                    this.setupAnalytics();
                }

                setupAnalytics() {
                    console.log('Módulo de Cobros cargado - Usuario: <?php echo htmlspecialchars($user_name); ?>');
                    console.log('Total cobros: <?php echo $total_records; ?>');
                    console.log('Recaudación mensual: Q<?php echo number_format($mes_total, 2); ?>');
                }
            }

            // ==========================================================================
            // INICIALIZACIÓN DE LA APLICACIÓN
            // ==========================================================================
            document.addEventListener('DOMContentLoaded', () => {
                // Inicializar componentes
                const themeManager = new ThemeManager();
                const dynamicComponents = new DynamicComponents();
                const performanceOptimizer = new PerformanceOptimizer();

                // Exponer APIs necesarias globalmente
                window.cobrosModule = {
                    theme: themeManager,
                    components: dynamicComponents
                };

                // Log de inicialización
                console.log('Módulo de Cobros CMS inicializado correctamente');
                console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
                console.log('Rol: <?php echo htmlspecialchars($user_type); ?>');
                console.log('Tema: ' + themeManager.theme);
            });

            // ==========================================================================
            // MANEJO DE ERRORES GLOBALES
            // ==========================================================================
            window.addEventListener('error', (event) => {
                console.error('Error en módulo de cobros:', event.error);

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

        // Estilos para spinner y billing premium
        const style = document.createElement('style');
        style.textContent = `
        .spin { animation: spin 1s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

        /* ── Billing Premium ── */
        body { background: #f5f7fb; }
        .billing-card {
            background: #fff; border: 1px solid #e9ecef; border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
        }
        .billing-table-wrap { padding: 0; width: 100%; }
        .billing-table { width: 100%; table-layout: auto; }
        .billing-table thead th {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #fff; font-size: 0.7rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.06em;
            padding: 0.9rem 1rem; border: none;
        }
        .billing-table tbody tr {
            border-bottom: 1px solid #f0f2f5; transition: background 0.15s;
        }
        .billing-table tbody tr:hover { background: rgba(37,99,235,0.03); }
        .billing-table tbody td { padding: 0.85rem 1rem; vertical-align: middle; font-size: 0.85rem; border: none; }

        /* Patient cell */
        .patient-cell { display: flex; align-items: center; gap: 0.75rem; }
        .patient-avatar {
            width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0;
            background: linear-gradient(135deg, #2563eb, #8b5cf6);
            color: #fff; font-weight: 700; font-size: 0.8rem;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 2px 8px rgba(37,99,235,0.25);
        }
        .patient-name { font-weight: 700; font-size: 0.92rem; color: #1a1d23; line-height: 1.2; }
        .charge-detail-text { font-size: 0.72rem; color: #6b7280; display: block; margin-top: 1px; }

        /* Badges de tipo */
        .charge-type-badge {
            display: inline-flex; align-items: center; gap: 0.3rem;
            padding: 0.3rem 0.7rem; border-radius: 50px; font-size: 0.72rem; font-weight: 700;
        }
        .charge-type-badge.consulta { background: rgba(37,99,235,0.1); color: #2563eb; }
        .charge-type-badge.reconsulta { background: rgba(99,102,241,0.1); color: #6366f1; }
        .charge-type-badge.farmacia { background: rgba(16,185,129,0.1); color: #10b981; }
        .charge-type-badge.laboratorio { background: rgba(6,182,212,0.1); color: #06b6d4; }
        .charge-type-badge.procedimiento { background: rgba(245,158,11,0.1); color: #f59e0b; }
        .charge-type-badge.rayosx { background: rgba(139,92,246,0.1); color: #8b5cf6; }
        .charge-type-badge.ultrasonido { background: rgba(236,72,153,0.1); color: #ec4899; }
        .charge-type-badge.electro { background: rgba(239,68,68,0.1); color: #ef4444; }

        /* Badge método pago */
        .payment-badge {
            display: inline-flex; align-items: center; gap: 0.3rem;
            padding: 0.25rem 0.6rem; border-radius: 8px;
            font-size: 0.72rem; font-weight: 600;
            background: rgba(37,99,235,0.07); color: #2563eb;
        }

        /* Monto */
        .amount-badge {
            display: inline-flex; align-items: center;
            padding: 0.3rem 0.75rem; border-radius: 10px;
            font-weight: 800; font-size: 0.88rem;
            background: rgba(16,185,129,0.1); color: #10b981;
        }

        /* Fecha */
        .billing-date-cell__day { font-weight: 700; font-size: 0.88rem; color: #1a1d23; }
        .billing-date-cell__time { font-size: 0.7rem; color: #9ca3af; margin-top: 1px; display: flex; align-items: center; gap: 0.25rem; }

        /* Referencia */
        .charge-id-badge {
            font-family: monospace; font-size: 0.75rem; font-weight: 700;
            background: #f3f4f6; color: #374151; padding: 0.2rem 0.5rem; border-radius: 6px;
            border: 1px solid #e5e7eb;
        }

        /* Acciones */
        .action-buttons { display: flex; gap: 0.35rem; }
        .btn-icon {
            width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e9ecef;
            background: transparent; color: #6b7280; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s; font-size: 0.85rem;
        }
        .btn-icon:hover { background: rgba(37,99,235,0.08); color: #2563eb; border-color: #2563eb; }
        .btn-icon.print:hover { background: rgba(16,185,129,0.08); color: #10b981; border-color: #10b981; }
        .btn-icon.delete:hover { background: rgba(220,53,69,0.08); color: #dc3545; border-color: #dc3545; }

        /* Zebra striping */
        .billing-table tbody tr:nth-child(even) { background: rgba(0,0,0,0.015); }
        .billing-table tbody tr:nth-child(even):hover { background: rgba(37,99,235,0.04); }

        .billing-registry__table-card { border-radius: 14px; overflow: hidden; padding: 0; border: none; }

        /* ── Filter Chips ── */
        .billing-filters { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
        .billing-btn-chip {
            display: inline-flex; align-items: center; gap: 0.4rem;
            padding: 0.5rem 1.1rem; border-radius: 50px;
            font-size: 0.78rem; font-weight: 600; border: 1px solid #e9ecef;
            background: #fff; color: #6b7280; cursor: pointer; text-decoration: none;
            transition: all 0.2s; box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }
        .billing-btn-chip:hover { background: #f0f4ff; color: #2563eb; border-color: #2563eb; box-shadow: 0 2px 8px rgba(37,99,235,0.15); }
        .billing-btn-chip.active { background: linear-gradient(135deg,#2563eb,#1d4ed8); color: #fff; border-color: #2563eb; box-shadow: 0 4px 12px rgba(37,99,235,0.3); }

        /* ── Pagination ── */
        .billing-registry__footer { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; padding: 1rem 0 0; }
        .billing-registry__page-hint { display: flex; align-items: center; gap: 0.4rem; font-size: 0.78rem; color: #6b7280; font-weight: 600; }
        .billing-pagination { display: flex; gap: 0.35rem; list-style: none; margin: 0; padding: 0; flex-wrap: wrap; justify-content: center; }
        .billing-pagination__item a,
        .billing-pagination__item > span {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 2.5rem; height: 2.5rem; padding: 0 0.7rem;
            border-radius: 10px; font-size: 0.82rem; font-weight: 700;
            text-decoration: none; color: #6b7280; background: #fff;
            border: 1px solid #e9ecef; box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            transition: all 0.2s;
        }
        .billing-pagination__item a:hover { background: #f0f4ff; color: #2563eb; border-color: #2563eb; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(37,99,235,0.15); }
        .billing-pagination__item.active a { background: linear-gradient(135deg,#2563eb,#1d4ed8); color: #fff; border-color: #2563eb; box-shadow: 0 4px 12px rgba(37,99,235,0.3); }
        .billing-pagination__item.disabled > span { opacity: 0.4; cursor: not-allowed; transform: none; box-shadow: none; }

        /* Modal */
        .modal-content { background-color: var(--color-card); color: var(--color-text); border: 1px solid var(--color-border); }
        .modal-header { border-bottom: 1px solid var(--color-border); }
        .modal-footer { border-top: 1px solid var(--color-border); }
        .btn-close { filter: var(--data-theme) === 'dark' ? 'invert(1)' : 'none'; }
        .form-control { background-color: var(--color-surface); color: var(--color-text); border: 1px solid var(--color-border); }
        .form-control:focus { background-color: var(--color-surface); color: var(--color-text); border-color: var(--color-primary); box-shadow: 0 0 0 0.25rem rgba(var(--color-primary-rgb), 0.25); }
        .input-group-text { background-color: var(--color-surface); color: var(--color-text); border: 1px solid var(--color-border); }
        `;
        document.head.appendChild(style);

        // Modales se inicializan automáticamente vía data-attributes en Bootstrap 5
        // Eliminamos la inicialización manual para evitar conflictos
    </script>

    <!-- jQuery (required for Bootstrap modals) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>