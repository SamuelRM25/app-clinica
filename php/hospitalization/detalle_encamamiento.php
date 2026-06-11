<?php
// hospitalization/detalle_encamamiento.php - Vista Detallada de Paciente Hospitalizado
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';
require_once '../../includes/module_guard.php';
require_once '../../includes/breadcrumbs.php';

check_module_access('hospitalization');

$id_hospital = (int) ($_SESSION['id_hospital'] ?? 0);

verify_session();

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['tipoUsuario'];

// Get encamamiento ID
$id_encamamiento = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_encamamiento == 0) {
    header("Location: index.php");
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Auto-populate session username if missing (for already logged-in users)
    if (!isset($_SESSION['usuario'])) {
        $stmt_u = $conn->prepare("SELECT usuario FROM usuarios WHERE idUsuario = ? AND id_hospital = ?");
        $stmt_u->execute([$user_id, $id_hospital]);
        $u_row = $stmt_u->fetch(PDO::FETCH_ASSOC);
        if ($u_row) {
            $_SESSION['usuario'] = $u_row['usuario'];
        }
    }

    // Fetch encamamiento details
    $stmt_enc = $conn->prepare("
        SELECT 
            e.*,
            pac.nombre as nombre_paciente,
            pac.apellido as apellido_paciente,
            pac.fecha_nacimiento,
            pac.genero,
            pac.direccion,
            pac.telefono,
            hab.numero_habitacion,
            hab.tipo_habitacion,
            hab.tarifa_por_noche,
            c.numero_cama,
            u.nombre as doctor_nombre,
            u.apellido as doctor_apellido,
            u.especialidad,
            DATEDIFF(COALESCE(e.fecha_alta, CURDATE()), DATE(e.fecha_ingreso)) as dias_hospitalizado
        FROM encamamientos e
        INNER JOIN pacientes pac ON e.id_paciente = pac.id_paciente
        INNER JOIN camas c ON e.id_cama = c.id_cama
        INNER JOIN habitaciones hab ON c.id_habitacion = hab.id_habitacion
        LEFT JOIN usuarios u ON e.id_doctor = u.idUsuario
        WHERE e.id_encamamiento = ? AND e.id_hospital = ?
    ");
    $stmt_enc->execute([$id_encamamiento, $id_hospital]);
    $encamamiento = $stmt_enc->fetch(PDO::FETCH_ASSOC);

    if (!$encamamiento) {
        die("Encamamiento no encontrado");
    }

    // Calculate age
    $fecha_nac = new DateTime($encamamiento['fecha_nacimiento']);
    $hoy = new DateTime();
    $edad = $hoy->diff($fecha_nac)->y;

    // Fetch vital signs
    $stmt_signos = $conn->prepare("
        SELECT sv.*, u.nombre as registrado_nombre, u.apellido as registrado_apellido
        FROM signos_vitales sv
        LEFT JOIN usuarios u ON sv.registrado_por = u.idUsuario
        WHERE sv.id_encamamiento = ? AND sv.id_hospital = ?
        ORDER BY sv.fecha_registro DESC
        LIMIT 20
    ");
    $stmt_signos->execute([$id_encamamiento, $id_hospital]);
    $signos_vitales = $stmt_signos->fetchAll(PDO::FETCH_ASSOC);

    // Fetch medical evolutions
    $stmt_evol = $conn->prepare("
        SELECT em.*, u.nombre as doctor_nombre, u.apellido as doctor_apellido
        FROM evoluciones_medicas em
        INNER JOIN usuarios u ON em.id_doctor = u.idUsuario
        WHERE em.id_encamamiento = ? AND em.id_hospital = ?
        ORDER BY em.fecha_evolucion DESC
    ");
    $stmt_evol->execute([$id_encamamiento, $id_hospital]);
    $evoluciones = $stmt_evol->fetchAll(PDO::FETCH_ASSOC);

    // Fetch hospital account
    $stmt_cuenta = $conn->prepare("
        SELECT * FROM cuenta_hospitalaria WHERE id_encamamiento = ? AND id_hospital = ?
    ");
    $stmt_cuenta->execute([$id_encamamiento, $id_hospital]);
    $cuenta = $stmt_cuenta->fetch(PDO::FETCH_ASSOC);

    if ($cuenta) {
        $id_cuenta = $cuenta['id_cuenta'];

        // 1. AUTO-CHECK FOR MISSING NIGHTS
        // Get all existing room charges dates for this account
        $stmt_existing_nights = $conn->prepare("
            SELECT fecha_aplicacion FROM cargos_hospitalarios 
            WHERE id_cuenta = ? AND tipo_cargo = 'Habitación' AND id_hospital = ?
        ");
        $stmt_existing_nights->execute([$id_cuenta, $id_hospital]);
        $existing_nights = $stmt_existing_nights->fetchAll(PDO::FETCH_COLUMN);

        $fecha_ingreso = new DateTime($encamamiento['fecha_ingreso']);
        $fecha_hasta = $encamamiento['estado'] == 'Activo' ? new DateTime() : new DateTime($encamamiento['fecha_alta']);

        // Check if this is a "RETRASADO" admission
        $is_retrasado_record = (strpos($encamamiento['notas_ingreso'] ?? '', '[RETRASADO]') !== false);

        // We charge for the first day, and every midnight that passed
        // SKIP if it's a delayed record to avoid automatic charges for retrospective days
        $interval = new DateInterval('P1D');
        $date_period = new DatePeriod($fecha_ingreso, $interval, $fecha_hasta);

        $added_any = false;
        foreach ($date_period as $date) {
            if ($is_retrasado_record)
                break; // Skip the loop if it's a delayed record
            $date_str = $date->format('Y-m-d');
            if (!in_array($date_str, $existing_nights)) {
                // Charge is missing for this night
                $stmt_add_night = $conn->prepare("
                    INSERT INTO cargos_hospitalarios 
                    (id_cuenta, tipo_cargo, descripcion, cantidad, precio_unitario, fecha_cargo, fecha_aplicacion, registrado_por, id_hospital)
                    VALUES (?, 'Habitación', ?, 1, ?, NOW(), ?, ?, ?)
                ");
                $desc = "Habitación " . $encamamiento['numero_habitacion'] . " - Cama " . $encamamiento['numero_cama'] . " (Noche " . $date_str . ")";
                $stmt_add_night->execute([
                    $id_cuenta,
                    $desc,
                    $encamamiento['tarifa_por_noche'],
                    $date_str,
                    $user_id,
                    $id_hospital
                ]);
                $added_any = true;
            }
        }

        // 2. RECALCULATE SUBTOTALS
        // This ensures cuenta_hospitalaria is ALWAYS in sync with cargos_hospitalarios
        $stmt_sync = $conn->prepare("
            UPDATE cuenta_hospitalaria ch
            SET 
                subtotal_habitacion = (SELECT COALESCE(SUM(subtotal), 0) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo = 'Habitación' AND cancelado = FALSE),
                subtotal_medicamentos = (SELECT COALESCE(SUM(subtotal), 0) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo = 'Medicamento' AND cancelado = FALSE),
                subtotal_procedimientos = (SELECT COALESCE(SUM(subtotal), 0) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo = 'Procedimiento' AND cancelado = FALSE),
                subtotal_laboratorios = (SELECT COALESCE(SUM(subtotal), 0) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo = 'Laboratorio' AND cancelado = FALSE),
                subtotal_honorarios = (SELECT COALESCE(SUM(subtotal), 0) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo = 'Honorario' AND cancelado = FALSE),
                subtotal_otros = (SELECT COALESCE(SUM(subtotal), 0) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo NOT IN ('Habitación','Medicamento','Procedimiento','Laboratorio','Honorario') AND cancelado = FALSE),
                total_pagado = (SELECT COALESCE(SUM(monto), 0) FROM abonos_hospitalarios WHERE id_cuenta = ch.id_cuenta),
                monto_pagado = (SELECT COALESCE(SUM(monto), 0) FROM abonos_hospitalarios WHERE id_cuenta = ch.id_cuenta)
            WHERE ch.id_cuenta = ?
        ");
        $stmt_sync->execute([$id_cuenta]);

        // Fetch updated account data
        $stmt_cuenta->execute([$id_encamamiento, $id_hospital]);
        $cuenta = $stmt_cuenta->fetch(PDO::FETCH_ASSOC);

        // Fetch charges
        $stmt_cargos = $conn->prepare("
            SELECT ch.*, u.nombre as registrado_nombre
            FROM cargos_hospitalarios ch
            LEFT JOIN usuarios u ON ch.registrado_por = u.idUsuario
            WHERE ch.id_cuenta = ? AND ch.cancelado = FALSE AND ch.id_hospital = ?
            ORDER BY ch.fecha_cargo DESC
        ");
        $stmt_cargos->execute([$id_cuenta, $id_hospital]);
        $cargos = $stmt_cargos->fetchAll(PDO::FETCH_ASSOC);

        // Group charges by type
        $cargos_por_tipo = [
            'Habitación' => [],
            'Medicamento' => [],
            'Procedimiento' => [],
            'Laboratorio' => [],
            'Honorario' => [],
            'Insumo' => [],
            'Otro' => []
        ];

        foreach ($cargos as $cargo) {
            $tipo = $cargo['tipo_cargo'];
            if (!isset($cargos_por_tipo[$tipo]))
                $tipo = 'Otro';
            $cargos_por_tipo[$tipo][] = $cargo;
        }

        // Fetch Payments (Abonos)
        $stmt_abonos = $conn->prepare("
            SELECT a.*, u.nombre as u_nombre, u.apellido as u_apellido
            FROM abonos_hospitalarios a
            LEFT JOIN usuarios u ON a.registrado_por = u.idUsuario
            WHERE a.id_cuenta = ? AND a.id_hospital = ?
            ORDER BY a.fecha_abono DESC
        ");
        $stmt_abonos->execute([$id_cuenta, $id_hospital]);
        $abonos = $stmt_abonos->fetchAll(PDO::FETCH_ASSOC);

    } else {
        $cargos = [];
        $cargos_por_tipo = [];
        $abonos = [];
    }

} catch (Exception $e) {
    error_log('Error en hospitalization/detalle_encamamiento.php: ' . $e->getMessage());
    die("Error: " . htmlspecialchars($e->getMessage()));
}

output_keep_alive_script();
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paciente:
        <?php echo htmlspecialchars($encamamiento['nombre_paciente'] . ' ' . $encamamiento['apellido_paciente']); ?>
    </title>

    <link rel="icon" type="image/png" href="../../assets/img/cmhs.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
    <?php include '../../includes/theme_head.php'; ?>

    <style>
        /* ===== PATIENT HEADER CARD ===== */
        .patient-header-card {
            background: linear-gradient(135deg, var(--color-primary) 0%, rgba(var(--color-primary-rgb), 0.75) 100%);
            border-radius: var(--radius-xl);
            padding: 2rem;
            color: white;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .patient-header-card::after {
            content: '';
            position: absolute;
            top: -30%;
            right: -5%;
            width: 220px;
            height: 220px;
            background: rgba(255, 255, 255, 0.06);
            border-radius: 50%;
        }

        .patient-id-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.3rem 0.85rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }

        .patient-full-name {
            font-size: 1.75rem;
            font-weight: 800;
            margin: 0 0 0.25rem;
        }

        .patient-info-pills {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 0.75rem;
        }

        .patient-info-pill {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            background: rgba(255, 255, 255, 0.15);
            padding: 0.3rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
        }

        /* ===== SECTION CARDS ===== */
        .detail-card {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-xl);
            margin-bottom: 1.5rem;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .detail-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--color-border);
            background: rgba(var(--color-primary-rgb), 0.03);
        }

        .detail-card-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--color-text);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .detail-card-title i {
            color: var(--color-primary);
        }

        .detail-card-body {
            padding: 1.5rem;
        }

        /* ===== VITALS TABLE ===== */
        .vitals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .vitals-table th {
            padding: 0.75rem 1rem;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--color-text-secondary);
            background: var(--color-surface);
            border-bottom: 1px solid var(--color-border);
        }

        .vitals-table td {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid var(--color-border);
            color: var(--color-text);
            font-size: 0.875rem;
        }

        .vitals-table tbody tr:last-child td {
            border-bottom: none;
        }

        .vitals-table tbody tr:hover {
            background: rgba(var(--color-primary-rgb), 0.03);
        }

        /* ===== CHARGE ITEMS ===== */
        .charge-group-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--color-text-secondary);
            padding: 0.75rem 1rem;
            background: var(--color-surface);
            border-bottom: 1px solid var(--color-border);
        }

        .charge-item-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.875rem 1.25rem;
            border-bottom: 1px solid var(--color-border);
            gap: 1rem;
        }

        .charge-item-row:last-child {
            border-bottom: none;
        }

        .charge-item-name {
            font-size: 0.875rem;
            color: var(--color-text);
            font-weight: 500;
        }

        .charge-item-meta {
            font-size: 0.75rem;
            color: var(--color-text-secondary);
            margin-top: 1px;
        }

        .charge-item-price {
            font-weight: 700;
            color: var(--color-text);
            white-space: nowrap;
        }

        .charge-item-delete {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(var(--color-danger-rgb), 0.1);
            color: var(--color-danger);
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all 0.15s;
            flex-shrink: 0;
        }

        .charge-item-delete:hover {
            background: var(--color-danger);
            color: white;
        }

        /* ===== ACCOUNT SUMMARY ===== */
        .account-summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--color-border);
            font-size: 0.875rem;
        }

        .account-summary-row.total {
            padding-top: 1rem;
            border-bottom: none;
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--color-text);
        }

        .account-summary-row.total .amount {
            color: var(--color-primary);
        }

        .account-summary-label {
            color: var(--color-text-secondary);
        }

        .account-summary-amount {
            font-weight: 700;
        }

        /* ===== ADD CHARGE FORM ===== */
        .add-charge-grid {
            display: grid;
            grid-template-columns: 1fr 1fr auto auto;
            gap: 0.75rem;
            align-items: end;
        }

        @media (max-width: 768px) {
            .add-charge-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <style media="print">
        .no-print { display: none !important; }
        .tab-pane { display: block !important; opacity: 1 !important; }
        .tab-content { display: block !important; }
        .tab-pane { page-break-inside: avoid; }
        .data-table { page-break-inside: avoid; }
    </style>
    <style>
        /* Override global: forzar visibilidad del contenedor de tabs Bootstrap */
        .tab-content { display: block !important; }
        .tab-pane { display: none; }
        .tab-pane.show.active { display: block; }
    </style>
</head>

<body>
    <div class="marble-effect"></div>

    <header class="dashboard-header">
        <div class="header-content">
            <div class="brand-container">
                <img src="../../assets/img/cmhs.png" alt="logo" class="brand-logo" width="40" height="40">
            </div>
            <div class="header-controls">
                <a href="index.php" class="action-btn secondary">
                    <i class="bi bi-arrow-left"></i>
                    Volver
                </a>
                <?php if ($encamamiento['estado'] == 'Activo'): ?>
                        <button class="action-btn danger" onclick="procesarAlta()">
                            <i class="bi bi-door-open"></i>
                            Dar de Alta
                        </button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="main-content">
        <!-- Patient Header -->
        <div class="patient-header">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1 class="patient-title">
                        <?php echo htmlspecialchars($encamamiento['nombre_paciente'] . ' ' . $encamamiento['apellido_paciente']); ?>
                    </h1>
                    <span class="status-badge activo"><?php echo $encamamiento['estado']; ?></span>
                </div>
                <div class="text-end">
                    <div class="meta-label">Días Hospitalizado</div>
                    <div class="summary-value"><?php echo $encamamiento['dias_hospitalizado']; ?></div>
                </div>
            </div>

            <div class="patient-meta">
                <div class="meta-item">
                    <span class="meta-label">Edad / Sexo</span>
                    <span class="meta-value"><?php echo $edad; ?> años /
                        <?php echo htmlspecialchars($encamamiento['genero']); ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Habitación / Cama</span>
                    <span
                        class="meta-value"><?php echo htmlspecialchars($encamamiento['numero_habitacion'] . ' - ' . $encamamiento['numero_cama']); ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Médico Responsable</span>
                    <span class="meta-value">Dr(a).
                        <?php echo htmlspecialchars($encamamiento['doctor_nombre'] . ' ' . $encamamiento['doctor_apellido']); ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Fecha de Ingreso</span>
                    <span
                        class="meta-value"><?php echo date('d/m/Y H:i', strtotime($encamamiento['fecha_ingreso'])); ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Tipo de Ingreso</span>
                    <span class="meta-value"><?php echo htmlspecialchars($encamamiento['tipo_ingreso']); ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Diagnóstico</span>
                    <span
                        class="meta-value"><?php echo htmlspecialchars($encamamiento['diagnostico_ingreso']); ?></span>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs-container">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#signos-tab">
                        <i class="bi bi-heart-pulse"></i>
                        Signos Vitales
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#evol-tab">
                        <i class="bi bi-clipboard-pulse"></i>
                        Evoluciones Médicas
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#meds-tab">
                        <i class="bi bi-capsule"></i>
                        Medicamentos
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#cuenta-tab">
                        <i class="bi bi-currency-dollar"></i>
                        Cuenta Hospitalaria
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Tab: Signos Vitales -->
                <div class="tab-pane fade show active" id="signos-tab">
                    <div class="tab-header">
                        <h3 class="tab-title">Signos Vitales</h3>
                        <button class="action-btn" onclick="openSignosModal()">
                            <i class="bi bi-plus-circle"></i>
                            Registrar Signos
                        </button>
                    </div>

                    <?php if (count($signos_vitales) > 0): ?>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Fecha/Hora</th>
                                            <th>Temp (°C)</th>
                                            <th>PA (mmHg)</th>
                                            <th>Pulso</th>
                                            <th>FR</th>
                                            <th>SpO2 (%)</th>
                                            <th>Glucosa</th>
                                            <th>Registrado por</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($signos_vitales as $sv): ?>
                                                <tr>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($sv['fecha_registro'])); ?></td>
                                                    <td><?php echo $sv['temperatura'] ? number_format($sv['temperatura'], 1) : '-'; ?>
                                                    </td>
                                                    <td><?php echo ($sv['presion_sistolica'] && $sv['presion_diastolica']) ? $sv['presion_sistolica'] . '/' . $sv['presion_diastolica'] : '-'; ?>
                                                    </td>
                                                    <td><?php echo $sv['pulso'] ? $sv['pulso'] : '-'; ?></td>
                                                    <td><?php echo $sv['frecuencia_respiratoria'] ? $sv['frecuencia_respiratoria'] : '-'; ?>
                                                    </td>
                                                    <td><?php echo $sv['saturacion_oxigeno'] ? number_format($sv['saturacion_oxigeno'], 1) : '-'; ?>
                                                    </td>
                                                    <td><?php echo $sv['glucometria'] ? number_format($sv['glucometria'], 0) : '-'; ?>
                                                    </td>
                                                    <td><small><?php echo htmlspecialchars($sv['registrado_nombre'] . ' ' . $sv['registrado_apellido']); ?></small>
                                                    </td>
                                                </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                    <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon"><i class="bi bi-heart-pulse"></i></div>
                                <h4>No hay signos vitales registrados</h4>
                                <p>Haga clic en "Registrar Signos" para agregar el primer registro</p>
                            </div>
                    <?php endif; ?>
                </div>

                <!-- Tab: Evoluciones -->
                <div class="tab-pane fade" id="evol-tab">
                    <div class="tab-header">
                        <h3 class="tab-title">Evoluciones Médicas</h3>
                        <button class="action-btn" onclick="openEvolucionModal()">
                            <i class="bi bi-plus-circle"></i>
                            Nueva Evolución
                        </button>
                    </div>

                    <?php if (count($evoluciones) > 0): ?>
                            <div class="timeline">
                                <?php foreach ($evoluciones as $evol): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-header">
                                                <div>
                                                    <div class="timeline-date">
                                                        <?php echo date('d/m/Y H:i', strtotime($evol['fecha_evolucion'])); ?>
                                                    </div>
                                                    <div class="timeline-doctor">Dr(a).
                                                        <?php echo htmlspecialchars($evol['doctor_nombre'] . ' ' . $evol['doctor_apellido']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php if ($evol['subjetivo']): ?>
                                                    <div class="evolution-section">
                                                        <div class="evolution-label">Subjetivo:</div>
                                                        <div class="evolution-text"><?php echo nl2br(htmlspecialchars($evol['subjetivo'])); ?>
                                                        </div>
                                                    </div>
                                            <?php endif; ?>
                                            <?php if ($evol['objetivo']): ?>
                                                    <div class="evolution-section">
                                                        <div class="evolution-label">Objetivo:</div>
                                                        <div class="evolution-text"><?php echo nl2br(htmlspecialchars($evol['objetivo'])); ?>
                                                        </div>
                                                    </div>
                                            <?php endif; ?>
                                            <?php if ($evol['evaluacion']): ?>
                                                    <div class="evolution-section">
                                                        <div class="evolution-label">Evaluación:</div>
                                                        <div class="evolution-text"><?php echo nl2br(htmlspecialchars($evol['evaluacion'])); ?>
                                                        </div>
                                                    </div>
                                            <?php endif; ?>
                                            <?php if ($evol['plan_tratamiento']): ?>
                                                    <div class="evolution-section">
                                                        <div class="evolution-label">Plan de Tratamiento:</div>
                                                        <div class="evolution-text">
                                                            <?php echo nl2br(htmlspecialchars($evol['plan_tratamiento'])); ?>
                                                        </div>
                                                    </div>
                                            <?php endif; ?>
                                        </div>
                                <?php endforeach; ?>
                            </div>
                    <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon"><i class="bi bi-clipboard-pulse"></i></div>
                                <h4>No hay evoluciones registradas</h4>
                                <p>Haga clic en "Nueva Evolución" para agregar la primera nota</p>
                            </div>
                    <?php endif; ?>
                </div>

                <!-- Tab: Medicamentos -->
                <div class="tab-pane fade" id="meds-tab">
                    <div class="tab-header">
                        <h3 class="tab-title">Medicamentos Administrados</h3>
                    </div>
                    <?php if (isset($cargos_por_tipo['Medicamento']) && count($cargos_por_tipo['Medicamento']) > 0): ?>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Fecha/Hora</th>
                                            <th>Medicamento</th>
                                            <th>Cantidad</th>
                                            <th>Precio U.</th>
                                            <th>Subtotal</th>
                                            <th>Registrado por</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cargos_por_tipo['Medicamento'] as $med): ?>
                                                <tr>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($med['fecha_cargo'])); ?></td>
                                                    <td class="fw-bold"><?php echo htmlspecialchars($med['descripcion']); ?></td>
                                                    <td><?php echo $med['cantidad']; ?></td>
                                                    <td>Q<?php echo number_format($med['precio_unitario'], 2); ?></td>
                                                    <td class="fw-bold">Q<?php echo number_format($med['subtotal'], 2); ?></td>
                                                    <td><small><?php echo htmlspecialchars($med['registrado_nombre']); ?></small></td>
                                                </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                    <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon"><i class="bi bi-capsule"></i></div>
                                <h4>No hay medicamentos registrados</h4>
                                <p>Los medicamentos aparecen aquí cuando se agregan a la cuenta hospitalaria.</p>
                            </div>
                    <?php endif; ?>
                </div>

                <!-- Tab: Cuenta -->
                <div class="tab-pane fade" id="cuenta-tab">
                    <div class="tab-header">
                        <h3 class="tab-title">Cuenta Hospitalaria</h3>
                        <button class="action-btn secondary" onclick="printAccount()">
                            <i class="bi bi-printer"></i>
                            Imprimir Recibo
                        </button>
                        <button class="action-btn" onclick="openCargoModal()">
                            <i class="bi bi-plus-circle"></i>
                            Agregar Cargo
                        </button>
                    </div>

                    <?php echo '<!-- DEBUG: $cuenta=' . ($cuenta ? 'TRUTHY total='.$cuenta['total_general'] : 'FALSY') . ' id_hospital='.$id_hospital.' -->'; ?>
                    <?php if ($cuenta): ?>
                            <!-- Account Summary Grid -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <div class="stat-card p-3 h-100">
                                        <div class="small text-muted mb-1 text-uppercase fw-bold">Estancia</div>
                                        <div class="h4 mb-0">Q<?php echo number_format($cuenta['subtotal_habitacion'], 2); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card p-3 h-100">
                                        <div class="small text-muted mb-1 text-uppercase fw-bold">Farmacia</div>
                                        <div class="h4 mb-0">Q<?php echo number_format($cuenta['subtotal_medicamentos'], 2); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card p-3 h-100">
                                        <div class="small text-muted mb-1 text-uppercase fw-bold">Procedimientos</div>
                                        <div class="h4 mb-0">
                                            Q<?php echo number_format($cuenta['subtotal_procedimientos'], 2); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card p-3 h-100">
                                        <div class="small text-muted mb-1 text-uppercase fw-bold">Otros Cargos</div>
                                        <div class="h4 mb-0">
                                            Q<?php echo number_format($cuenta['subtotal_laboratorios'] + $cuenta['subtotal_honorarios'] + $cuenta['subtotal_otros'], 2); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="stat-card p-4 mb-4"
                                style="background: linear-gradient(135deg, var(--color-primary), #1e40af); color: white;">
                                <div class="row align-items-center">
                                    <div class="col-md-4 text-center border-end border-white border-opacity-25">
                                        <div class="small text-white text-opacity-75 text-uppercase fw-bold mb-1">Total General
                                        </div>
                                        <div class="h2 mb-0 fw-bold">Q<?php echo number_format($cuenta['total_general'], 2); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-center border-end border-white border-opacity-25">
                                        <div class="small text-white text-opacity-75 text-uppercase fw-bold mb-1">Pagos/Abonos
                                        </div>
                                        <div class="h2 mb-0 fw-bold">
                                            Q<?php echo number_format($cuenta['total_pagado'] ?? 0, 2); ?></div>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <div class="small text-white text-opacity-75 text-uppercase fw-bold mb-1">Saldo
                                            Pendiente</div>
                                        <div class="h2 mb-0 fw-bold">
                                            Q<?php echo number_format($cuenta['total_general'] - ($cuenta['total_pagado'] ?? 0), 2); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Abonos Section -->
                        <div class="charges-section">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4>Pagos Realizados (Abonos)</h4>
                                <button class="btn btn-sm btn-success" onclick="openAbonoModal()">
                                    <i class="bi bi-cash-coin"></i> Agregar Abono
                                </button>
                            </div>
                            <?php if (count($abonos) > 0): ?>
                                    <div class="table-responsive mb-4">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Método</th>
                                                    <th>Notas</th>
                                                    <th>Registrado Por</th>
                                                    <th>Monto</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($abonos as $abono): ?>
                                                        <tr>
                                                            <td><?php echo date('d/m/Y H:i', strtotime($abono['fecha_abono'])); ?></td>
                                                            <td><?php echo htmlspecialchars($abono['metodo_pago']); ?></td>
                                                            <td><?php echo htmlspecialchars($abono['notas']); ?></td>
                                                            <td><small><?php echo htmlspecialchars($abono['u_nombre'] . ' ' . $abono['u_apellido']); ?></small>
                                                            </td>
                                                            <td class="fw-bold text-success">Q<?php echo number_format($abono['monto'], 2); ?>
                                                            </td>
                                                            <td>
                                                                <button class="btn btn-sm btn-outline-secondary"
                                                                    onclick="printAbono(<?php echo $abono['id_abono']; ?>)">
                                                                    <i class="bi bi-printer"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                            <?php else: ?>
                                    <div class="alert alert-info mb-4">No se han registrado abonos.</div>
                            <?php endif; ?>
                        </div>

                        <!-- Detailed Charges -->
                        <div class="charges-section">
                            <h4 class="mb-3">Cargos Detallados</h4>

                            <?php foreach ($cargos_por_tipo as $tipo => $cargos_tipo): ?>
                                    <?php if (count($cargos_tipo) > 0): ?>
                                            <?php
                                            $subtotal_tipo = array_sum(array_column($cargos_tipo, 'subtotal'));
                                            ?>
                                            <div class="charges-category">
                                                <div class="category-title">
                                                    <span><?php echo $tipo; ?></span>
                                                    <span class="category-total">Q<?php echo number_format($subtotal_tipo, 2); ?></span>
                                                </div>
                                                <div class="table-responsive">
                                                    <table class="data-table">
                                                        <thead>
                                                            <tr>
                                                                <th>Fecha</th>
                                                                <th>Descripción</th>
                                                                <th>Cantidad</th>
                                                                <th>Precio Unit.</th>
                                                                <th>Subtotal</th>
                                                                <?php if (in_array($_SESSION['tipoUsuario'] ?? '', ['admin', 'doc'])): ?>
                                                                        <th>Acciones</th>
                                                                <?php endif; ?>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($cargos_tipo as $cargo): ?>
                                                                    <tr>
                                                                        <td><?php echo date('d/m/Y', strtotime($cargo['fecha_cargo'])); ?></td>
                                                                        <td><?php echo htmlspecialchars($cargo['descripcion']); ?></td>
                                                                        <td><?php echo number_format($cargo['cantidad'], 2); ?></td>
                                                                        <td>Q<?php echo number_format($cargo['precio_unitario'], 2); ?></td>
                                                                        <td>Q<?php echo number_format($cargo['subtotal'], 2); ?></td>
                                                                        <?php if (in_array($_SESSION['tipoUsuario'] ?? '', ['admin', 'doc'])): ?>
                                                                                <td>
                                                                                    <button class="btn btn-sm btn-outline-primary" title="Editar Cargo"
                                                                                        onclick='editCargo(<?php echo json_encode($cargo); ?>)'>
                                                                                        <i class="bi bi-pencil"></i>
                                                                                    </button>
                                                                                    <button class="btn btn-sm btn-outline-danger" title="Eliminar Cargo"
                                                                                        onclick='deleteCargo(<?php echo $cargo['id_cargo']; ?>)'>
                                                                                        <i class="bi bi-trash"></i>
                                                                                    </button>
                                                                                </td>
                                                                        <?php endif; ?>
                                                                    </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                    <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                <?php else: ?>
                        <div class="alert alert-warning">No se encontró cuenta hospitalaria.</div>
                <?php endif; ?>
            </div>
        </div>
        </div>
    </main>

    <!-- Receipt Print Area (Dedicated for formal printing) -->
    <div id="receipt-print-container" style="display: none;">
        <div class="receipt-header">
            <img src="../../assets/img/cmhs.png" alt="logo" class="receipt-logo" width="40" height="40">
            <div class="receipt-title">Centro Médico Herrera Saenz</div>
            <div>Estado de Cuenta Hospitalaria</div>
        </div>

        <div class="receipt-info">
            <div>
                <strong>Paciente:</strong>
                <?php echo htmlspecialchars($encamamiento['nombre_paciente'] . ' ' . $encamamiento['apellido_paciente']); ?><br>
                <strong>Edad/Sexo:</strong> <?php echo $edad; ?> años /
                <?php echo htmlspecialchars($encamamiento['genero']); ?><br>
                <strong>Habitación:</strong>
                <?php echo htmlspecialchars($encamamiento['numero_habitacion'] . ' - ' . $encamamiento['numero_cama']); ?>
            </div>
            <div style="text-align: right;">
                <strong>Fecha Emisión:</strong> <?php echo date('d/m/Y H:i'); ?><br>
                <strong>Ingreso:</strong> <?php echo date('d/m/Y', strtotime($encamamiento['fecha_ingreso'])); ?><br>
                <strong>Médico:</strong> Dr(a).
                <?php echo htmlspecialchars($encamamiento['doctor_nombre'] . ' ' . $encamamiento['doctor_apellido']); ?>
            </div>
        </div>

        <?php if ($cuenta): ?>
                <?php foreach ($cargos_por_tipo as $tipo => $cargos_tipo): ?>
                        <?php if (count($cargos_tipo) > 0): ?>
                                <div class="receipt-section-title"><?php echo $tipo; ?></div>
                                <table class="receipt-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 15%;">Fecha</th>
                                            <th>Descripción</th>
                                            <th style="width: 10%; text-align: center;">Cant.</th>
                                            <th style="width: 15%; text-align: right;">Precio U.</th>
                                            <th style="width: 15%; text-align: right;">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cargos_tipo as $cargo): ?>
                                                <tr>
                                                    <td><?php echo date('d/m/Y', strtotime($cargo['fecha_cargo'])); ?></td>
                                                    <td><?php echo htmlspecialchars($cargo['descripcion']); ?></td>
                                                    <td style="text-align: center;"><?php echo number_format($cargo['cantidad'] ?? 1, 0); ?></td>
                                                    <td style="text-align: right;">Q<?php echo number_format($cargo['precio_unitario'], 2); ?></td>
                                                    <td style="text-align: right;">Q<?php echo number_format($cargo['subtotal'], 2); ?></td>
                                                </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                        <?php endif; ?>
                <?php endforeach; ?>

                <div class="receipt-total-box">
                    <div class="receipt-total-row">
                        <span style="margin-right: 20px;">TOTAL GENERAL:</span>
                        <span>Q<?php echo number_format($cuenta['total_general'], 2); ?></span>
                    </div>
                    <?php
                    $saldo_pendiente_calc = $cuenta['total_general'] - ($cuenta['total_pagado'] ?? 0);
                    if ($saldo_pendiente_calc > 0): ?>
                            <div class="receipt-total-row" style="color: #d9534f; font-size: 10pt; margin-top: 5px;">
                                <span style="margin-right: 20px;">SALDO PENDIENTE:</span>
                                <span>Q<?php echo number_format($saldo_pendiente_calc, 2); ?></span>
                            </div>
                    <?php endif; ?>
                </div>
        <?php endif; ?>

        <div class="receipt-footer">
            <p>Gracias por confiar en Centro Médico Herrera Saenz</p>
            <p>Este documento es un estado de cuenta informativo.</p>
        </div>
    </div>

    <!-- Modals will be added via AJAX/JS -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Theme
        const savedTheme = localStorage.getItem('dashboard-theme');
        if (savedTheme) {
            document.documentElement.setAttribute('data-theme', savedTheme);
        }

        const id_encamamiento = <?php echo $id_encamamiento; ?>;

        // Helper to get local ISO string (YYYY-MM-DDTHH:mm)
        function getLocalISOTime() {
            const now = new Date();
            const offset = now.getTimezoneOffset() * 60000;
            const localISOTime = (new Date(now - offset)).toISOString().slice(0, 16);
            return localISOTime;
        }

        function openSignosModal() {
            // TODO: Implement modal form for vital signs
            Swal.fire({
                title: 'Registrar Signos Vitales',
                html: `
                <form id="signosForm" class="text-start">
                    <div class="mb-3">
                        <label class="form-label">Fecha/Hora</label>
                        <input type="datetime-local" class="form-control" name="fecha_registro" value="${getLocalISOTime()}" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Temperatura (°C)</label>
                            <input type="number" step="0.1" class="form-control" name="temperatura" placeholder="36.5">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Pulso (lpm)</label>
                            <input type="number" class="form-control" name="pulso" placeholder="80">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Presión Sistólica</label>
                            <input type="number" class="form-control" name="presion_sistolica" placeholder="120">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Presión Diastólica</label>
                            <input type="number" class="form-control" name="presion_diastolica" placeholder="80">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Frec. Respiratoria</label>
                            <input type="number" class="form-control" name="frecuencia_respiratoria" placeholder="20">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SpO2 (%)</label>
                            <input type="number" step="0.1" class="form-control" name="saturacion_oxigeno" placeholder="98">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Glucometría (mg/dL)</label>
                        <input type="number" class="form-control" name="glucometria" placeholder="95">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notas</label>
                        <textarea class="form-control" name="notas" rows="2"></textarea>
                    </div>
                </form>
            `,
                width: 700,
                showCancelButton: true,
                confirmButtonText: 'Guardar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#7c90db',
                preConfirm: () => {
                    const form = document.getElementById('signosForm');
                    const formData = new FormData(form);
                    formData.append('id_encamamiento', id_encamamiento);
                    formData.append('csrf_token', window.CSRF_TOKEN || '');

                    return fetch('api/save_signos.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status !== 'success') {
                                throw new Error(data.message);
                            }
                            return data;
                        })
                        .catch(error => {
                            Swal.showValidationMessage(error.message || 'Error del servidor');
                        });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('¡Éxito!', 'Signos vitales guardados', 'success').then(() => {
                        location.reload();
                    });
                }
            });
        }

        function openEvolucionModal() {
            Swal.fire({
                title: 'Nueva Evolución Médica',
                html: `
                <form id="evolucionForm" class="text-start">
                    <div class="mb-3">
                        <label class="form-label">Fecha/Hora</label>
                        <input type="datetime-local" class="form-control" name="fecha_evolucion" value="${getLocalISOTime()}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subjetivo (S)</label>
                        <textarea class="form-control" name="subjetivo" rows="2" placeholder="Síntomas reportados por el paciente..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Objetivo (O)</label>
                        <textarea class="form-control" name="objetivo" rows="2" placeholder="Hallazgos objetivos en examen físico..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Evaluación (A)</label>
                        <textarea class="form-control" name="evaluacion" rows="2" placeholder="Evaluación y diagnóstico..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Plan (P)</label>
                        <textarea class="form-control" name="plan_tratamiento" rows="3" placeholder="Plan de tratamiento..." required></textarea>
                    </div>
                </form>
            `,
                width: 800,
                showCancelButton: true,
                confirmButtonText: 'Guardar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#7c90db',
                preConfirm: () => {
                    const form = document.getElementById('evolucionForm');
                    const formData = new FormData(form);
                    formData.append('id_encamamiento', id_encamamiento);
                    formData.append('csrf_token', window.CSRF_TOKEN || '');

                    return fetch('api/save_evolucion.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status !== 'success') {
                                throw new Error(data.message);
                            }
                            return data;
                        })
                        .catch(error => {
                            Swal.showValidationMessage(error.message || 'Error del servidor');
                        });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('¡Éxito!', 'Evolución guardada', 'success').then(() => {
                        location.reload();
                    });
                }
            });
        }

        function openCargoModal() {
            Swal.fire({
                title: 'Agregar Cargos a la Cuenta',
                html: `
                <div class="text-start mb-3">
                    <p class="text-muted small">Agregue uno o más cargos a la cuenta del paciente.</p>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm" id="batchCargoTable">
                        <thead>
                            <tr>
                                <th style="width: 25%">Tipo</th>
                                <th style="width: 45%">Descripción</th>
                                <th style="width: 12%">Cant.</th>
                                <th style="width: 13%">Precio</th>
                                <th style="width: 5%"></th>
                            </tr>
                        </thead>
                        <tbody id="cargoRows">
                            <tr>
                                <td>
                                    <select class="form-select form-select-sm cargo-tipo" name="tipo_cargo[]" required>
                                        <option value="Medicamento">Medicamento</option>
                                        <option value="Procedimiento">Procedimiento</option>
                                        <option value="Laboratorio">Laboratorio</option>
                                        <option value="Honorario">Honorario</option>
                                        <option value="Insumo">Insumo</option>
                                        <option value="Otro">Otro</option>
                                    </select>
                                </td>
                                <td>
                                    <div class="desc-container">
                                        <input type="text" class="form-control form-control-sm cargo-desc" name="descripcion[]" required placeholder="Buscar medicamento...">
                                        <div class="search-results-inline" style="display:none; position:absolute; z-index:1000; background:white; border:1px solid #ddd; max-height:200px; overflow-y:auto; width:100%; box-shadow:0 2px 4px rgba(0,0,0,0.1);"></div>
                                        <input type="hidden" class="cargo-id-inventario" name="id_inventario[]">
                                    </div>
                                </td>
                                <td><input type="number" step="0.01" class="form-control form-control-sm cargo-cantidad" name="cantidad[]" value="1" required></td>
                                <td><input type="number" step="0.01" class="form-control form-control-sm cargo-precio" name="precio_unitario[]" required></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="text-start">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addCargoRowBtn">
                        <i class="bi bi-plus-lg"></i> Agregar otra fila
                    </button>
                </div>
            `,
                width: 900,
                showCancelButton: true,
                confirmButtonText: 'Guardar Cargos',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#7c90db',
                didOpen: () => {
                    setupCargoRows();

                    // Add row button
                    document.getElementById('addCargoRowBtn').addEventListener('click', addCargoRow);
                },
                preConfirm: () => {
                    const rows = document.querySelectorAll('#cargoRows tr');
                    const cargos = [];

                    rows.forEach(row => {
                        const tipo = row.querySelector('[name="tipo_cargo[]"]').value;
                        const desc = row.querySelector('[name="descripcion[]"]').value;
                        const cant = row.querySelector('[name="cantidad[]"]').value;
                        const price = row.querySelector('[name="precio_unitario[]"]').value;
                        const idInv = row.querySelector('.cargo-id-inventario').value;

                        if (desc && cant && price) {
                            cargos.push({
                                id_encamamiento: id_encamamiento,
                                tipo_cargo: tipo,
                                descripcion: desc,
                                cantidad: cant,
                                precio_unitario: price,
                                id_inventario: idInv
                            });
                        }
                    });

                    if (cargos.length === 0) {
                        Swal.showValidationMessage('Debe agregar al menos un cargo con descripción y precio');
                        return false;
                    }

                    const formData = new FormData();
                    cargos.forEach((cargo, index) => {
                        formData.append(`cargos[${index}][id_encamamiento]`, cargo.id_encamamiento);
                        formData.append(`cargos[${index}][tipo_cargo]`, cargo.tipo_cargo);
                        formData.append(`cargos[${index}][descripcion]`, cargo.descripcion);
                        formData.append(`cargos[${index}][cantidad]`, cargo.cantidad);
                        formData.append(`cargos[${index}][precio_unitario]`, cargo.precio_unitario);
                        formData.append(`cargos[${index}][id_inventario]`, cargo.id_inventario);
                    });

                    return fetch('api/add_cargo.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status !== 'success') {
                                throw new Error(data.message);
                            }
                            return data;
                        })
                        .catch(error => {
                            Swal.showValidationMessage(`Error: ${error}`);
                        });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('¡Éxito!', result.value.message, 'success').then(() => {
                        location.reload();
                    });
                }
            });
        }

        function setupCargoRows() {
            document.querySelectorAll('#cargoRows tr').forEach(row => {
                setupCargoRow(row);
            });
        }

        function setupCargoRow(row) {
            const tipoSelect = row.querySelector('.cargo-tipo');
            const descInput = row.querySelector('.cargo-desc');
            const precioInput = row.querySelector('.cargo-precio');
            const cantidadInput = row.querySelector('.cargo-cantidad');
            const resultsDiv = row.querySelector('.search-results-inline');

            // Handle tipo change
            tipoSelect.addEventListener('change', function () {
                if (this.value === 'Medicamento' || this.value === 'Insumo') {
                    descInput.placeholder = 'Buscar ' + this.value.toLowerCase() + '...';
                    descInput.value = '';
                    precioInput.value = '';
                    row.querySelector('.cargo-id-inventario').value = '';
                } else {
                    descInput.placeholder = 'Descripción del cargo';
                    resultsDiv.style.display = 'none';
                    row.querySelector('.cargo-id-inventario').value = '';
                }
            });

            // Handle description input for medication search
            descInput.addEventListener('input', function () {
                const tipo = tipoSelect.value;
                const term = this.value;

                if (tipo !== 'Medicamento' && tipo !== 'Insumo') {
                    resultsDiv.style.display = 'none';
                    return;
                }

                if (term.length < 3) {
                    resultsDiv.style.display = 'none';
                    return;
                }

                // Search medications/insumos
                fetch(`api/search_medications.php?q=${encodeURIComponent(term)}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.length === 0) {
                            resultsDiv.innerHTML = '<div class="p-2 text-muted small">No se encontraron resultados</div>';
                            resultsDiv.style.display = 'block';
                            return;
                        }

                        let html = '';
                        data.forEach(med => {
                            html += `
                                <div class="search-result-item p-2" style="cursor:pointer; border-bottom:1px solid #eee;" 
                                     data-name="${med.nom_medicamento}" 
                                     data-presentacion="${med.presentacion_med}"
                                     data-precio="${med.precio_hospital || 0}"
                                     data-id="${med.id_inventario}">
                                    <div class="fw-bold small">${med.nom_medicamento}</div>
                                    <div class="text-muted" style="font-size:0.75rem;">${med.mol_medicamento} - ${med.presentacion_med}</div>
                                    <div class="d-flex justify-content-between" style="font-size:0.75rem;">
                                        <span class="text-info">Hosp: ${med.stock_hospital || 0}</span>
                                        <span class="text-success">Farm: ${med.stock_farmacia || 0}</span>
                                        <span class="fw-bold">Q${parseFloat(med.precio_hospital || 0).toFixed(2)}</span>
                                    </div>
                                </div>
                            `;
                        });
                        resultsDiv.innerHTML = html;
                        resultsDiv.style.display = 'block';

                        // Add click handlers to results
                        resultsDiv.querySelectorAll('.search-result-item').forEach(item => {
                            item.addEventListener('click', function () {
                                const name = this.getAttribute('data-name');
                                const presentacion = this.getAttribute('data-presentacion');
                                const precio = this.getAttribute('data-precio');
                                const idInv = this.getAttribute('data-id');

                                descInput.value = `${name} (${presentacion})`;
                                precioInput.value = precio;
                                row.querySelector('.cargo-id-inventario').value = idInv;
                                resultsDiv.style.display = 'none';
                            });

                            // Hover effect
                            item.addEventListener('mouseenter', function () {
                                this.style.backgroundColor = '#f8f9fa';
                            });
                            item.addEventListener('mouseleave', function () {
                                this.style.backgroundColor = 'white';
                            });
                        });
                    })
                    .catch(err => {
                        console.error('Error searching medications:', err);
                    });
            });

            // Close results when clicking outside
            descInput.addEventListener('blur', function () {
                setTimeout(() => {
                    resultsDiv.style.display = 'none';
                }, 200);
            });
        }

        function addCargoRow() {
            const tbody = document.getElementById('cargoRows');
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td>
                    <select class="form-select form-select-sm cargo-tipo" name="tipo_cargo[]" required>
                        <option value="Medicamento">Medicamento</option>
                        <option value="Procedimiento">Procedimiento</option>
                        <option value="Laboratorio">Laboratorio</option>
                        <option value="Honorario">Honorario</option>
                        <option value="Insumo">Insumo</option>
                        <option value="Otro">Otro</option>
                    </select>
                </td>
                <td>
                    <div class="desc-container" style="position:relative;">
                        <input type="text" class="form-control form-control-sm cargo-desc" name="descripcion[]" required placeholder="Descripción del cargo">
                        <div class="search-results-inline" style="display:none; position:absolute; z-index:1000; background:white; border:1px solid #ddd; max-height:200px; overflow-y:auto; width:100%; box-shadow:0 2px 4px rgba(0,0,0,0.1);"></div>
                        <input type="hidden" class="cargo-id-inventario" name="id_inventario[]">
                    </div>
                </td>
                <td><input type="number" step="0.01" class="form-control form-control-sm cargo-cantidad" name="cantidad[]" value="1" required></td>
                <td><input type="number" step="0.01" class="form-control form-control-sm cargo-precio" name="precio_unitario[]" required></td>
                <td>
                    <button type="button" class="btn btn-link text-danger p-0 btn-remove-row">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(newRow);
            setupCargoRow(newRow);

            // Add remove handler
            newRow.querySelector('.btn-remove-row').addEventListener('click', function () {
                newRow.remove();
            });
        }

        function procesarAlta() {
            const totalGeneral = <?php echo $cuenta ? $cuenta['total_general'] : 0; ?>;
            const totalPagado = <?php echo $cuenta ? ($cuenta['total_pagado'] ?? 0) : 0; ?>;
            const saldoPendiente = totalGeneral - totalPagado;

            if (saldoPendiente > 0) {
                Swal.fire({
                    title: 'Cuenta Pendiente',
                    html: `El paciente tiene un saldo pendiente de <strong>Q${saldoPendiente.toFixed(2)}</strong>.<br>¿Desea continuar con el alta?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Continuar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#f87171'
                }).then((result) => {
                    if (result.isConfirmed) {
                        mostrarFormularioAlta();
                    }
                });
            } else {
                mostrarFormularioAlta();
            }
        }

        function mostrarFormularioAlta() {
            Swal.fire({
                title: 'Dar de Alta al Paciente',
                html: `
                <form id="altaForm" class="text-start">
                    <div class="mb-3">
                        <label class="form-label">Diagnóstico de Egreso</label>
                        <input type="text" class="form-control" name="diagnostico_egreso" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notas de Alta</label>
                        <textarea class="form-control" name="notas_alta" rows="3"></textarea>
                    </div>
                </form>
            `,
                width: 600,
                showCancelButton: true,
                confirmButtonText: 'Confirmar Alta',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#f87171',
                preConfirm: () => {
                    const form = document.getElementById('altaForm');
                    const formData = new FormData(form);
                    formData.append('id_encamamiento', id_encamamiento);

                    return fetch('api/procesar_alta.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status !== 'success') {
                                throw new Error(data.message);
                            }
                            return data;
                        })
                        .catch(error => {
                            Swal.showValidationMessage(`Error: ${error}`);
                        });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Alta Procesada',
                        text: 'El paciente ha sido dado de alta. ¿Desea imprimir el estado de cuenta final?',
                        icon: 'success',
                        showCancelButton: true,
                        confirmButtonText: 'Imprimir y Salir',
                        cancelButtonText: 'Solo Salir'
                    }).then((printResult) => {
                        if (printResult.isConfirmed) {
                            printAccount();
                            // Wait for print dialog to likely close or just delay redirect
                            setTimeout(() => {
                                window.location.href = 'index.php';
                            }, 3000);
                        } else {
                            window.location.href = 'index.php';
                        }
                    });
                }
            });
        }

        function openAbonoModal() {
            Swal.fire({
                title: 'Registrar Abono',
                html: `
                <form id="abonoForm" class="text-start">
                    <div class="mb-3">
                        <label class="form-label">Monto (Q)</label>
                        <input type="number" step="0.01" class="form-control" name="monto" required min="0.01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Método de Pago</label>
                        <select class="form-select" name="metodo_pago">
                            <option value="Efectivo">Efectivo</option>
                            <option value="Tarjeta">Tarjeta</option>
                            <option value="Transferencia">Transferencia</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notas</label>
                        <textarea class="form-control" name="notas" rows="2" placeholder="Opcional"></textarea>
                    </div>
                </form>
                `,
                showCancelButton: true,
                confirmButtonText: 'Registrar Pago',
                confirmButtonColor: '#34d399',
                preConfirm: () => {
                    const form = document.getElementById('abonoForm');
                    const formData = new FormData(form);
                    formData.append('id_encamamiento', id_encamamiento);

                    return fetch('api/save_abono.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status !== 'success') throw new Error(data.message);
                            return data;
                        })
                        .catch(error => Swal.showValidationMessage(error));
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Abono Registrado',
                        text: '¿Desea imprimir el recibo?',
                        icon: 'success',
                        showCancelButton: true,
                        confirmButtonText: 'Imprimir',
                        cancelButtonText: 'Cerrar'
                    }).then((printResult) => {
                        if (printResult.isConfirmed) {
                            printAbono(result.value.id_abono);
                        }
                        location.reload();
                    });
                }
            });
        }

        function printAbono(id) {
            window.open('print_abono.php?id=' + id, '_blank');
        }

        function editCargo(cargo) {
            Swal.fire({
                title: 'Editar Cargo',
                html: `
                <form id="editCargoForm" class="text-start">
                    <input type="hidden" name="id_cargo" value="${cargo.id_cargo}">
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <input type="text" class="form-control" name="descripcion" value="${cargo.descripcion}" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cantidad</label>
                            <input type="number" step="1" class="form-control" name="cantidad" value="${cargo.cantidad}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Precio Unitario (Q)</label>
                            <input type="number" step="1" class="form-control" name="precio_unitario" value="${cargo.precio_unitario}" required>
                        </div>
                    </div>
                </form>
                `,
                showCancelButton: true,
                confirmButtonText: 'Actualizar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#7c90db',
                preConfirm: () => {
                    const form = document.getElementById('editCargoForm');
                    const formData = new FormData(form);

                    return fetch('api/update_hospital_charge.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status !== 'success') {
                                throw new Error(data.message);
                            }
                            return data;
                        })
                        .catch(error => {
                            Swal.showValidationMessage(`Error: ${error}`);
                        });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('¡Actualizado!', 'El cargo ha sido actualizado correctamente.', 'success').then(() => {
                        location.reload();
                    });
                }
            });
        }

        function deleteCargo(id_cargo) {
            Swal.fire({
                title: '¿Eliminar Cargo?',
                text: "Esta acción marcará el cargo como cancelado y recalculará la cuenta.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

                    const formData = new FormData();
                    formData.append('id_cargo', id_cargo);
                    formData.append('id_encamamiento', id_encamamiento);

                    fetch('api/delete_charge.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire('¡Eliminado!', 'El cargo ha sido eliminado.', 'success').then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire('Error', data.message || 'Error al eliminar el cargo.', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire('Error', 'Hubo un problema de conexión.', 'error');
                        });
                }
            });
        }

        function printAccount() {
            window.print();
        }
    </script>
</body>

</html>