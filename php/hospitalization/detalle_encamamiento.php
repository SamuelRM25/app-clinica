<?php
// hospitalization/detalle_encamamiento.php - Vista Detallada de Paciente Hospitalizado
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
verify_session();
date_default_timezone_set('America/Guatemala');

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
        WHERE e.id_encamamiento = ?
    ");
    $stmt_enc->execute([$id_encamamiento]);
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
        WHERE sv.id_encamamiento = ?
        ORDER BY sv.fecha_registro DESC
        LIMIT 20
    ");
    $stmt_signos->execute([$id_encamamiento]);
    $signos_vitales = $stmt_signos->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch medical evolutions
    $stmt_evol = $conn->prepare("
        SELECT em.*, u.nombre as doctor_nombre, u.apellido as doctor_apellido
        FROM evoluciones_medicas em
        INNER JOIN usuarios u ON em.id_doctor = u.idUsuario
        WHERE em.id_encamamiento = ?
        ORDER BY em.fecha_evolucion DESC
    ");
    $stmt_evol->execute([$id_encamamiento]);
    $evoluciones = $stmt_evol->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch hospital account
    $stmt_cuenta = $conn->prepare("
        SELECT * FROM cuenta_hospitalaria WHERE id_encamamiento = ?
    ");
    $stmt_cuenta->execute([$id_encamamiento]);
    $cuenta = $stmt_cuenta->fetch(PDO::FETCH_ASSOC);
    
    // Fetch charges
    if ($cuenta) {
        $stmt_cargos = $conn->prepare("
            SELECT ch.*, u.nombre as registrado_nombre
            FROM cargos_hospitalarios ch
            LEFT JOIN usuarios u ON ch.registrado_por = u.idUsuario
            WHERE ch.id_cuenta = ? AND ch.cancelado = FALSE
            ORDER BY ch.fecha_cargo DESC
        ");
        $stmt_cargos->execute([$cuenta['id_cuenta']]);
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
            $cargos_por_tipo[$cargo['tipo_cargo']][] = $cargo;
        }
    } else {
        $cargos = [];
        $cargos_por_tipo = [];
    }
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paciente: <?php echo htmlspecialchars($encamamiento['nombre_paciente'] . ' ' . $encamamiento['apellido_paciente']); ?></title>
    
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* Reuse styles from index.php */
        :root {
            --color-background: #f8fafc;
            --color-surface: #ffffff;
            --color-primary: #7c90db;
            --color-primary-light: #a3b1e8;
            --color-primary-dark: #5a6fca;
            --color-secondary: #8dd7bf;
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
            
            --marble-bg: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.08);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
        }
        
        [data-theme="dark"] {
            --color-background: #0f172a;
            --color-surface: #1e293b;
            --color-text: #f1f5f9;
            --color-text-light: #cbd5e1;
            --color-text-muted: #94a3b8;
            --color-border: #334155;
            --color-border-light: #1e293b;
            --marble-bg: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--color-background);
            color: var(--color-text);
            min-height: 100vh;
            line-height: 1.6;
        }
        
        .marble-effect {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
            z-index: -1;
            opacity: 0.4;
            background-image: 
                radial-gradient(circle at 20% 30%, rgba(124, 144, 219, 0.08) 0%, transparent 30%),
                radial-gradient(circle at 80% 70%, rgba(141, 215, 191, 0.08) 0%, transparent 30%);
        }
        
        .dashboard-header {
            background: var(--color-surface);
           border-bottom: 1px solid var(--color-border);
            padding: 1rem 2rem;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2rem;
        }
        
        .brand-logo {
            height: 45px;
            width: auto;
        }
        
        .header-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .action-btn {
            background: var(--color-primary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-md);
            font-weight: 500;
            font-size: 0.95rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .action-btn:hover {
            background: var(--color-primary-dark);
            transform: translateY(-2px);
        }
        
        .action-btn.secondary {
            background: var(--color-border);
            color: var(--color-text);
        }
        
        .action-btn.danger {
            background: var(--color-error);
        }
        
        .main-content {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .patient-header {
            background: var(--color-surface);
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--color-border);
        }
        
        .patient-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--color-text);
            margin-bottom: 1rem;
        }
        
        .patient-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .meta-item {
            display: flex;
            flex-direction: column;
        }
        
        .meta-label {
            font-size: 0.85rem;
            color: var(--color-text-muted);
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .meta-value {
            font-size: 1rem;
            color: var(--color-text);
            font-weight: 600;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.activo {
            background: rgba(52, 211, 153, 0.15);
            color: var(--color-success);
        }
        
        .tabs-container {
            background: var(--color-surface);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--color-border);
            overflow: hidden;
        }
        
        .nav-tabs {
            border-bottom: 2px solid var(--color-border);
            padding: 0 1rem;
            background: var(--color-border-light);
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: var(--color-text-light);
            padding: 1rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--color-primary);
            border-bottom-color: var(--color-primary-light);
        }
        
        .nav-tabs .nav-link.active {
            color: var(--color-primary);
            background: transparent;
            border-bottom-color: var(--color-primary);
        }
        
        .tab-content {
            padding: 2rem;
        }
        
        .tab-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .tab-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--color-text);
        }
        
        /* Vital Signs Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table thead {
            background: var(--color-border-light);
            border-bottom: 2px solid var(--color-border);
        }
        
        .data-table th {
            padding: 0.75rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--color-text-light);
            text-transform: uppercase;
        }
        
        .data-table td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--color-border);
            color: var(--color-text);
        }
        
        .data-table tbody tr:hover {
            background: var(--color-border-light);
        }
        
        /* Evolution Timeline */
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 0.5rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--color-border);
        }
        
        .timeline-item {
            position: relative;
            padding: 1.5rem;
            background: var(--color-background);
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            border: 1px solid var(--color-border);
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -1.75rem;
            top: 1.5rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--color-primary);
            border: 3px solid var(--color-surface);
        }
        
        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--color-border);
        }
        
        .timeline-date {
            font-weight: 600;
            color: var(--color-primary);
        }
        
        .timeline-doctor {
            font-size: 0.9rem;
            color: var(--color-text-light);
        }
        
        .evolution-section {
            margin-bottom: 1rem;
        }
        
        .evolution-label {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--color-text-light);
            margin-bottom: 0.25rem;
        }
        
        .evolution-text {
            color: var(--color-text);
            line-height: 1.6;
        }
        
        /* Account Summary */
        .account-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .summary-card {
            background: var(--color-background);
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            border: 2px solid var(--color-border);
        }
        
        .summary-label {
            font-size: 0.85rem;
            color: var(--color-text-muted);
            margin-bottom: 0.5rem;
        }
        
        .summary-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--color-text);
        }
        
        .summary-card.total {
            background: rgba(124, 144, 219, 0.1);
            border-color: var(--color-primary);
        }
        
        .summary-card.total .summary-value {
            color: var(--color-primary);
        }
        
        .charges-section {
            margin-bottom: 2rem;
        }
        
        .charges-category {
            margin-bottom: 2rem;
        }
        
        .category-title {
            font-weight: 600;
            color: var(--color-text);
            padding: 0.75rem;
            background: var(--color-border-light);
            border-radius: var(--radius-md);
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
        }
        
        .category-total {
            color: var(--color-primary);
            font-weight: 700;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            color: var(--color-text-light);
        }
        
        .empty-icon {
            font-size: 3rem;
            color: var(--color-border);
            margin-bottom: 1rem;
        }
        
        /* Modals */
        .modal-content {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
        }
        
        .modal-header {
            border-bottom: 1px solid var(--color-border);
            background: var(--color-border-light);
        }
        
        .modal-title {
            color: var(--color-text);
            font-weight: 600;
        }
        
        .form-label {
            font-weight: 500;
            color: var(--color-text);
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: 0.75rem;
            background: var(--color-surface);
            color: var(--color-text);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(124, 144, 219, 0.1);
            outline: none;
        }
        
        [data-theme="dark"] .modal-content {
            background: var(--color-surface);
        }
        
        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select {
            background: var(--color-background);
            color: var(--color-text);
        }
    </style>
</head>
<body>
    <div class="marble-effect"></div>
    
    <header class="dashboard-header">
        <div class="header-content">
            <div class="brand-container">
                <img src="../../assets/img/herrerasaenz.png" alt="CMHS" class="brand-logo">
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
                    <span class="meta-value"><?php echo $edad; ?> años / <?php echo htmlspecialchars($encamamiento['genero']); ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Habitación / Cama</span>
                    <span class="meta-value"><?php echo htmlspecialchars($encamamiento['numero_habitacion'] . ' - ' . $encamamiento['numero_cama']); ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Médico Responsable</span>
                    <span class="meta-value">Dr(a). <?php echo htmlspecialchars($encamamiento['doctor_nombre'] . ' ' . $encamamiento['doctor_apellido']); ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Fecha de Ingreso</span>
                    <span class="meta-value"><?php echo date('d/m/Y H:i', strtotime($encamamiento['fecha_ingreso'])); ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Tipo de Ingreso</span>
                    <span class="meta-value"><?php echo htmlspecialchars($encamamiento['tipo_ingreso']); ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Diagnóstico</span>
                    <span class="meta-value"><?php echo htmlspecialchars($encamamiento['diagnostico_ingreso']); ?></span>
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
                                    <td><?php echo $sv['temperatura'] ? number_format($sv['temperatura'], 1) : '-'; ?></td>
                                    <td><?php echo ($sv['presion_sistolica'] && $sv['presion_diastolica']) ? $sv['presion_sistolica'] . '/' . $sv['presion_diastolica'] : '-'; ?></td>
                                    <td><?php echo $sv['pulso'] ? $sv['pulso'] : '-'; ?></td>
                                    <td><?php echo $sv['frecuencia_respiratoria'] ? $sv['frecuencia_respiratoria'] : '-'; ?></td>
                                    <td><?php echo $sv['saturacion_oxigeno'] ? number_format($sv['saturacion_oxigeno'], 1) : '-'; ?></td>
                                    <td><?php echo $sv['glucometria'] ? number_format($sv['glucometria'], 0) : '-'; ?></td>
                                    <td><small><?php echo htmlspecialchars($sv['registrado_nombre'] . ' ' . $sv['registrado_apellido']); ?></small></td>
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
                                    <div class="timeline-date"><?php echo date('d/m/Y H:i', strtotime($evol['fecha_evolucion'])); ?></div>
                                    <div class="timeline-doctor">Dr(a). <?php echo htmlspecialchars($evol['doctor_nombre'] . ' ' . $evol['doctor_apellido']); ?></div>
                                </div>
                            </div>
                            <?php if ($evol['subjetivo']): ?>
                            <div class="evolution-section">
                                <div class="evolution-label">Subjetivo:</div>
                                <div class="evolution-text"><?php echo nl2br(htmlspecialchars($evol['subjetivo'])); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($evol['objetivo']): ?>
                            <div class="evolution-section">
                                <div class="evolution-label">Objetivo:</div>
                                <div class="evolution-text"><?php echo nl2br(htmlspecialchars($evol['objetivo'])); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($evol['evaluacion']): ?>
                            <div class="evolution-section">
                                <div class="evolution-label">Evaluación:</div>
                                <div class="evolution-text"><?php echo nl2br(htmlspecialchars($evol['evaluacion'])); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($evol['plan_tratamiento']): ?>
                            <div class="evolution-section">
                                <div class="evolution-label">Plan de Tratamiento:</div>
                                <div class="evolution-text"><?php echo nl2br(htmlspecialchars($evol['plan_tratamiento'])); ?></div>
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
                
                <!-- Tab: Cuenta -->
                <div class="tab-pane fade" id="cuenta-tab">
                    <div class="tab-header">
                        <h3 class="tab-title">Cuenta Hospitalaria</h3>
                        <button class="action-btn" onclick="openCargoModal()">
                            <i class="bi bi-plus-circle"></i>
                            Agregar Cargo
                        </button>
                    </div>
                    
                    <?php if ($cuenta): ?>
                    <!-- Account Summary -->
                    <div class="account-summary">
                        <div class="summary-card">
                            <div class="summary-label">Habitación</div>
                            <div class="summary-value">Q<?php echo number_format($cuenta['subtotal_habitacion'], 2); ?></div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-label">Medicamentos</div>
                            <div class="summary-value">Q<?php echo number_format($cuenta['subtotal_medicamentos'], 2); ?></div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-label">Procedimientos</div>
                            <div class="summary-value">Q<?php echo number_format($cuenta['subtotal_procedimientos'], 2); ?></div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-label">Laboratorios</div>
                            <div class="summary-value">Q<?php echo number_format($cuenta['subtotal_laboratorios'], 2); ?></div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-label">Honorarios</div>
                            <div class="summary-value">Q<?php echo number_format($cuenta['subtotal_honorarios'], 2); ?></div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-label">Otros</div>
                            <div class="summary-value">Q<?php echo number_format($cuenta['subtotal_otros'], 2); ?></div>
                        </div>
                        <div class="summary-card total">
                            <div class="summary-label">Total General</div>
                            <div class="summary-value">Q<?php echo number_format($cuenta['total_general'], 2); ?></div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-label">Saldo Pendiente</div>
                            <div class="summary-value">Q<?php echo number_format($cuenta['saldo_pendiente'], 2); ?></div>
                        </div>
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
    
    function openSignosModal() {
        // TODO: Implement modal form for vital signs
        Swal.fire({
            title: 'Registrar Signos Vitales',
            html: `
                <form id="signosForm" class="text-start">
                    <div class="mb-3">
                        <label class="form-label">Fecha/Hora</label>
                        <input type="datetime-local" class="form-control" name="fecha_registro" value="${new Date().toISOString().slice(0,16)}" required>
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
                    Swal.showValidationMessage(`Error: ${error}`);
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
                        <input type="datetime-local" class="form-control" name="fecha_evolucion" value="${new Date().toISOString().slice(0,16)}" required>
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
                    Swal.showValidationMessage(`Error: ${error}`);
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
            title: 'Agregar Cargo',
            html: `
                <form id="cargoForm" class="text-start">
                    <div class="mb-3">
                        <label class="form-label">Tipo de Cargo</label>
                        <select class="form-select" name="tipo_cargo" required>
                            <option value="Medicamento">Medicamento</option>
                            <option value="Procedimiento">Procedimiento</option>
                            <option value="Laboratorio">Laboratorio</option>
                            <option value="Honorario">Honorario Médico</option>
                            <option value="Insumo">Insumo</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <input type="text" class="form-control" name="descripcion" required placeholder="Descripción del cargo">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cantidad</label>
                            <input type="number" step="0.01" class="form-control" name="cantidad" value="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Precio Unitario</label>
                            <input type="number" step="0.01" class="form-control" name="precio_unitario" required>
                        </div>
                    </div>
                </form>
            `,
            width: 600,
            showCancelButton: true,
            confirmButtonText: 'Guardar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#7c90db',
            preConfirm: () => {
                const form = document.getElementById('cargoForm');
                const formData = new FormData(form);
                formData.append('id_encamamiento', id_encamamiento);
                
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
                Swal.fire('¡Éxito!', 'Cargo agregado', 'success').then(() => {
                    location.reload();
                });
            }
        });
    }
    
    function procesarAlta() {
        const saldoPendiente = <?php echo $cuenta ? $cuenta['saldo_pendiente'] : 0; ?>;
        
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
                Swal.fire('Alta Procesada', 'El paciente ha sido dado de alta', 'success').then(() => {
                    window.location.href = 'index.php';
                });
            }
        });
    }
    </script>
</body>
</html>
