<?php
// hospitalization/index.php - Dashboard Principal de Encamamiento - Centro Médico RS
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

check_module_access('hospitalization');

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

verify_session();

// Set timezone
date_default_timezone_set('America/Guatemala');

// Verificar permisos
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['tipoUsuario'];
$user_name = $_SESSION['nombre'];
$user_specialty = $_SESSION['especialidad'] ?? 'Personal';

// Check permissions from JSON
$has_access = false;
try {
    $database = new Database();
    $conn = $database->getConnection();

    if ($user_type === 'admin') {
        $has_access = true;
    } else {
        $has_access = true; // Por ahora, permitimos acceso a todos los usuarios logueados según requerimiento
    }

    if (!$has_access) {
        header("Location: ../dashboard/index.php");
        exit;
    }

    // ====================================
    // FETCH DASHBOARD DATA
    // ====================================

    // Total de camas
    $stmt_total_beds = $conn->prepare("SELECT COUNT(*) as total FROM camas WHERE id_hospital = ?");
    $stmt_total_beds->execute([$id_hospital]);
    $total_beds = $stmt_total_beds->fetch(PDO::FETCH_ASSOC)['total'];

    // Camas ocupadas
    $stmt_occupied = $conn->prepare("SELECT COUNT(*) as total FROM camas WHERE estado = 'Ocupada' AND id_hospital = ?");
    $stmt_occupied->execute([$id_hospital]);
    $camas_ocupadas = $stmt_occupied->fetch(PDO::FETCH_ASSOC)['total'];

    // Camas disponibles
    $camas_disponibles = $total_beds - $camas_ocupadas;

    // Porcentaje de ocupación
    $porcentaje_ocupacion = $total_beds > 0 ? round(($camas_ocupadas / $total_beds) * 100, 1) : 0;

    // Total pacientes activos (hospitalizados)
    $stmt_active = $conn->prepare("SELECT COUNT(*) as total FROM encamamientos WHERE estado = 'Activo' AND id_hospital = ?");
    $stmt_active->execute([$id_hospital]);
    $pacientes_activos = $stmt_active->fetch(PDO::FETCH_ASSOC)['total'];

    // Ingresos hoy
    $stmt_today = $conn->prepare("SELECT COUNT(*) as total FROM encamamientos WHERE DATE(fecha_ingreso) = CURDATE() AND id_hospital = ?");
    $stmt_today->execute([$id_hospital]);
    $ingresos_hoy = $stmt_today->fetch(PDO::FETCH_ASSOC)['total'];

    // Altas hoy
    $stmt_altas = $conn->prepare("SELECT COUNT(*) as total FROM encamamientos WHERE DATE(fecha_alta) = CURDATE() AND estado IN ('Alta_Medica', 'Alta_Administrativa') AND id_hospital = ?");
    $stmt_altas->execute([$id_hospital]);
    $altas_hoy = $stmt_altas->fetch(PDO::FETCH_ASSOC)['total'];

    // Estancia promedio (últimos 30 días)
    $stmt_estancia = $conn->prepare("
        SELECT AVG(DATEDIFF(COALESCE(fecha_alta, NOW()), fecha_ingreso)) as promedio
        FROM encamamientos
        WHERE fecha_ingreso >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        AND id_hospital = ?
    ");
    $stmt_estancia->execute([$id_hospital]);
    $estancia_promedio = round($stmt_estancia->fetch(PDO::FETCH_ASSOC)['promedio'] ?? 0, 1);

    // Lista de habitaciones con estado
    $stmt_rooms = $conn->prepare("
        SELECT 
            h.id_habitacion,
            h.numero_habitacion,
            h.tipo_habitacion,
            h.piso,
            h.tarifa_por_noche,
            h.capacidad_maxima,
            COUNT(c.id_cama) as total_camas,
            SUM(CASE WHEN c.estado = 'Ocupada' THEN 1 ELSE 0 END) as camas_ocupadas,
            h.estado as estado_habitacion
        FROM habitaciones h
        LEFT JOIN camas c ON h.id_habitacion = c.id_habitacion
        WHERE h.id_hospital = ?
        GROUP BY h.id_habitacion
        ORDER BY h.piso, h.numero_habitacion
    ");
    $stmt_rooms->execute([$id_hospital]);
    $habitaciones = $stmt_rooms->fetchAll(PDO::FETCH_ASSOC);

    // Pacientes actualmente hospitalizados
    $stmt_patients = $conn->prepare("
        SELECT 
            e.id_encamamiento,
            e.id_paciente,
            e.fecha_ingreso,
            e.diagnostico_ingreso,
            e.tipo_ingreso,
            pac.nombre as nombre_paciente,
            pac.apellido as apellido_paciente,
            pac.fecha_nacimiento,
            pac.genero,
            hab.numero_habitacion,
            hab.tipo_habitacion,
            c.numero_cama,
            u.nombre as nombre_doctor,
            u.apellido as apellido_doctor,
            DATEDIFF(CURDATE(), DATE(e.fecha_ingreso)) as dias_hospitalizado,
            (SELECT COUNT(*) FROM signos_vitales WHERE id_encamamiento = e.id_encamamiento AND DATE(fecha_registro) = CURDATE()) as signos_hoy
        FROM encamamientos e
        INNER JOIN pacientes pac ON e.id_paciente = pac.id_paciente
        INNER JOIN camas c ON e.id_cama = c.id_cama
        INNER JOIN habitaciones hab ON c.id_habitacion = hab.id_habitacion
        LEFT JOIN usuarios u ON e.id_doctor = u.idUsuario
        WHERE e.estado = 'Activo' AND e.id_hospital = ?
        ORDER BY e.fecha_ingreso DESC
    ");
    $stmt_patients->execute([$id_hospital]);
    $pacientes_hospitalizados = $stmt_patients->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

$page_title = "Gestión de Hospitalización - Centro Médico RS";
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@700&display=swap"
        rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Seguridad y Protección de Código -->
    <script src="../../assets/js/security.js"></script>

    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
    <?php include '../../includes/theme_head.php'; ?>

    <style>
        /* ===== PAGE HEADER ===== */
        .hosp-page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .hosp-page-title {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--color-text);
            margin: 0 0 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .hosp-page-title i { color: var(--color-primary); }
        .hosp-page-subtitle { color: var(--color-text-secondary); font-size: 0.875rem; margin: 0; }
        .hosp-page-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; }

        /* ===== PATIENT TABLE ===== */
        .hosp-table-wrapper {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        .hosp-table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--color-border);
            background: rgba(var(--color-primary-rgb), 0.03);
        }
        .hosp-table-header h3 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--color-text);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .hosp-table {
            width: 100%;
            border-collapse: collapse;
        }
        .hosp-table thead tr {
            background: var(--color-surface);
        }
        .hosp-table th {
            padding: 0.875rem 1.25rem;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--color-text-secondary);
            text-align: left;
            border-bottom: 1px solid var(--color-border);
            white-space: nowrap;
        }
        .hosp-table td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--color-border);
            color: var(--color-text);
            font-size: 0.875rem;
            vertical-align: middle;
        }
        .hosp-table tbody tr:last-child td { border-bottom: none; }
        .hosp-table tbody tr:hover { background: rgba(var(--color-primary-rgb), 0.03); }

        .patient-avatar {
            width: 40px; height: 40px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.9rem; color: white; flex-shrink: 0;
        }
        .patient-name { font-weight: 600; color: var(--color-text); }
        .patient-meta { font-size: 0.75rem; color: var(--color-text-secondary); margin-top: 1px; }

        /* ===== ROOM MAP CARDS ===== */
        .room-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1rem;
        }
        .room-card {
            background: var(--color-card);
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
        }
        .room-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 4px; height: 100%;
            background: var(--room-accent, var(--color-border));
        }
        .room-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .room-card.available { --room-accent: var(--color-success); }
        .room-card.full      { --room-accent: var(--color-danger); }
        .room-card.partial   { --room-accent: var(--color-warning); }
        .room-card.maintenance { --room-accent: var(--color-text-secondary); }

        .room-number {
            font-size: 1.5rem; font-weight: 800;
            color: var(--color-text); line-height: 1;
        }
        .room-status-pill {
            display: inline-flex; align-items: center; gap: 0.3rem;
            padding: 0.25rem 0.7rem; border-radius: 50px;
            font-size: 0.7rem; font-weight: 700;
        }
        .room-status-pill.available { background: rgba(var(--color-success-rgb), 0.12); color: var(--color-success); }
        .room-status-pill.full      { background: rgba(var(--color-danger-rgb), 0.12);  color: var(--color-danger);  }
        .room-status-pill.partial   { background: rgba(var(--color-warning-rgb), 0.12); color: var(--color-warning); }
        .room-meta { font-size: 0.78rem; color: var(--color-text-secondary); margin-top: 0.35rem; }

        .room-beds { display: flex; gap: 0.5rem; margin-top: 0.75rem; flex-wrap: wrap; }
        .bed-dot {
            width: 32px; height: 32px;
            border-radius: 6px; display: flex; align-items: center; justify-content: center;
            font-size: 0.7rem; font-weight: 700;
        }
        .bed-dot.free     { background: rgba(var(--color-success-rgb), 0.1); color: var(--color-success); border: 1.5px solid var(--color-success); }
        .bed-dot.occupied { background: var(--color-danger); color: white; }

        /* ===== STATUS BADGES ===== */
        .hosp-badge {
            display: inline-flex; align-items: center; gap: 0.3rem;
            padding: 0.3rem 0.75rem; border-radius: 50px;
            font-size: 0.72rem; font-weight: 700;
        }
        .hosp-badge.active   { background: rgba(var(--color-success-rgb), 0.12); color: var(--color-success); }
        .hosp-badge.pending  { background: rgba(var(--color-warning-rgb), 0.12); color: var(--color-warning); }
        .hosp-badge.discharged { background: rgba(var(--color-text-secondary), 0.1); color: var(--color-text-secondary); }

        /* ===== TABS ===== */
        .hosp-tabs { display: flex; gap: 0.25rem; background: var(--color-surface); padding: 0.25rem; border-radius: var(--radius-md); border: 1px solid var(--color-border); margin-bottom: 1.5rem; }
        .hosp-tab-btn {
            flex: 1; padding: 0.625rem 1rem; border-radius: calc(var(--radius-md) - 2px);
            border: none; background: transparent; color: var(--color-text-secondary);
            font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 0.5rem;
        }
        .hosp-tab-btn:hover { background: rgba(var(--color-primary-rgb), 0.08); color: var(--color-primary); }
        .hosp-tab-btn.active { background: var(--color-primary); color: white; box-shadow: 0 2px 10px rgba(var(--color-primary-rgb), 0.35); }
    </style>
</head>

<body>
    <!-- Efecto mármol -->
    <div class="marble-effect"></div>

    <div class="dashboard-container">
        <!-- Header -->
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

        <!-- Main Content -->
        <main class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">
                    <i class="bi bi-hospital text-primary"></i>
                    Gestión de Hospitalización
                </h1>
                <p class="page-subtitle">Control de camas, pacientes hospitalizados y seguimiento médico</p>

                <div class="page-actions">
                    <button class="action-btn secondary" onclick="openDischargesModal()">
                        <i class="bi bi-file-earmark-spreadsheet"></i>
                        Reporte de Altas
                    </button>
                    <button class="action-btn" onclick="window.location.href='ingresar_paciente.php'">
                        <i class="bi bi-person-plus-fill"></i>
                        Ingresar Paciente
                    </button>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $pacientes_activos; ?></div>
                            <div class="stat-label">Pacientes Hospitalizados</div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="bi bi-people-fill"></i>
                        </div>
                    </div>
                    <div class="stat-footer">
                        <i class="bi bi-arrow-up"></i>
                        <?php echo $ingresos_hoy; ?> ingresos hoy
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $camas_disponibles; ?> / <?php echo $total_beds; ?></div>
                            <div class="stat-label">Camas Disponibles</div>
                        </div>
                        <div class="stat-icon success">
                            <i class="bi bi-hospital"></i>
                        </div>
                    </div>
                    <div class="stat-footer">
                        <i class="bi bi-circle-fill" style="font-size: 0.5rem; color: var(--color-success);"></i>
                        <?php echo $porcentaje_ocupacion; ?>% ocupación
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $altas_hoy; ?></div>
                            <div class="stat-label">Altas Hoy</div>
                        </div>
                        <div class="stat-icon info">
                            <i class="bi bi-door-open"></i>
                        </div>
                    </div>
                    <div class="stat-footer">
                        <i class="bi bi-calendar-check"></i>
                        Total del día
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $estancia_promedio; ?></div>
                            <div class="stat-label">Días Promedio</div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="bi bi-clock-history"></i>
                        </div>
                    </div>
                    <div class="stat-footer">
                        <i class="bi bi-info-circle"></i>
                        Estancia promedio
                    </div>
                </div>
            </div>

            <!-- Active Patients Table -->
            <div class="patients-container">
                <h2 class="section-title">
                    <i class="bi bi-person-lines-fill"></i>
                    Pacientes Actualmente Hospitalizados
                </h2>

                <?php if (count($pacientes_hospitalizados) > 0): ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Paciente</th>
                                    <th>Ubicación</th>
                                    <th>Médico Responsable</th>
                                    <th>Ingreso</th>
                                    <th class="text-center">Estado Signos</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pacientes_hospitalizados as $pac): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="patient-avatar-small">
                                                    <?php echo strtoupper(substr($pac['nombre_paciente'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark">
                                                        <?php echo htmlspecialchars($pac['nombre_paciente'] . ' ' . $pac['apellido_paciente']); ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($pac['genero']); ?> • 
                                                        <?php echo date_diff(date_create($pac['fecha_nacimiento']), date_create('today'))->y; ?> años
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="badge bg-light text-dark border p-2">
                                                <i class="bi bi-door-open me-1"></i> Hab. <?php echo htmlspecialchars($pac['numero_habitacion']); ?>
                                                <span class="mx-1">|</span>
                                                <i class="bi bi-bed me-1"></i> Cama <?php echo htmlspecialchars($pac['numero_cama']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi bi-person-badge text-primary"></i>
                                                Dr(a). <?php echo htmlspecialchars($pac['nombre_doctor'] . ' ' . $pac['apellido_doctor']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-medium"><?php echo date('d/m/Y', strtotime($pac['fecha_ingreso'])); ?></div>
                                            <small class="text-muted"><?php echo $pac['dias_hospitalizado']; ?> días de estancia</small>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($pac['signos_hoy'] > 0): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                                                    <i class="bi bi-check-circle me-1"></i> <?php echo $pac['signos_hoy']; ?> Registros
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 animate-pulse">
                                                    <i class="bi bi-exclamation-triangle me-1"></i> Pendiente
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="detalle_encamamiento.php?id=<?php echo $pac['id_encamamiento']; ?>" 
                                               class="action-btn sm" title="Ver Expediente">
                                                <i class="bi bi-folder2-open"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="bi bi-hospital"></i>
                        </div>
                        <h3>No hay pacientes hospitalizados actualmente</h3>
                        <p>Los pacientes ingresados aparecerán aquí</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Bed Map -->
            <div class="bed-map-container">
                <h2 class="section-title">
                    <i class="bi bi-grid-3x3"></i>
                    Mapa de Habitaciones y Camas
                </h2>

                <div class="rooms-grid">
                    <?php foreach ($habitaciones as $hab): ?>
                        <?php
                        $estado_class = 'available';
                        $badge_text = 'Disponible';
                        $badge_class = 'disponible';

                        if ($hab['camas_ocupadas'] > 0) {
                            if ($hab['camas_ocupadas'] >= $hab['total_camas']) {
                                $estado_class = 'full';
                                $badge_text = 'Llena';
                                $badge_class = 'llena';
                            } else {
                                $estado_class = 'occupied';
                                $badge_text = 'Ocupada';
                                $badge_class = 'ocupada';
                            }
                        }
                        ?>
                        <div class="room-card <?php echo $estado_class; ?>"
                            onclick="viewRoomDetails(<?php echo $hab['id_habitacion']; ?>)">
                            <div class="room-header">
                                <span class="room-number"><?php echo htmlspecialchars($hab['numero_habitacion']); ?></span>
                                <span class="room-badge <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
                            </div>
                            <div class="room-type">
                                <?php echo htmlspecialchars($hab['tipo_habitacion']); ?> - Piso
                                <?php echo htmlspecialchars($hab['piso']); ?>
                            </div>
                            <div class="room-type">
                                Q<?php echo number_format($hab['tarifa_por_noche'], 2); ?> / noche
                            </div>
                            <div class="room-beds">
                                <?php
                                $stmt_beds = $conn->prepare("SELECT numero_cama, estado FROM camas WHERE id_habitacion = ? AND id_hospital = ? ORDER BY numero_cama");
                                $stmt_beds->execute([$hab['id_habitacion'], $id_hospital]);
                                $beds = $stmt_beds->fetchAll(PDO::FETCH_ASSOC);

                                foreach ($beds as $bed):
                                    $bed_estado = strtolower($bed['estado']);
                                    ?>
                                    <div class="bed-indicator <?php echo $bed_estado; ?>"
                                        title="Cama <?php echo $bed['numero_cama']; ?> - <?php echo $bed['estado']; ?>">
                                        <?php echo htmlspecialchars($bed['numero_cama']); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Reporte de Altas -->
    <div class="modal fade" id="dischargesModal" tabindex="-1" aria-labelledby="dischargesModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content"
                style="background: var(--color-surface); color: var(--color-text); border: 1px solid var(--color-border);">
                <div class="modal-header" style="border-bottom: 1px solid var(--color-border);">
                    <h5 class="modal-title" id="dischargesModalLabel">
                        <i class="bi bi-file-earmark-spreadsheet text-primary"></i>
                        Reporte de Altas y Facturación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                        style="filter: var(--bs-reboot-close-filter);"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Fecha Inicio</label>
                            <input type="date" class="form-control" id="report_start_date"
                                value="<?php echo date('Y-m-01'); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Fecha Fin</label>
                            <input type="date" class="form-control" id="report_end_date"
                                value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button class="action-btn w-100" onclick="generateDischargesReport()">
                                <i class="bi bi-search"></i> Generar Reporte
                            </button>
                        </div>
                    </div>

                    <div id="report_results_container" style="display: none;">
                        <div class="table-responsive">
                            <table class="data-table" id="discharges_report_table">
                                <thead>
                                    <tr>
                                        <th>Fecha Alta</th>
                                        <th>Paciente</th>
                                        <th>Tipo Ingreso</th>
                                        <th>Médico</th>
                                        <th>Días</th>
                                        <th class="text-end">Total Generado</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="report_table_body">
                                    <!-- Results will be injected here -->
                                </tbody>
                                <tfoot>
                                    <tr style="border-top: 2px solid var(--color-border); font-weight: bold;">
                                        <td colspan="5" class="text-end">TOTAL GENERAL:</td>
                                        <td class="text-end" id="report_total_amount">Q0.00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Theme management
        document.addEventListener('DOMContentLoaded', function () {
            const themeSwitch = document.getElementById('themeSwitch');

            // Initialize theme
            function initializeTheme() {
                const savedTheme = localStorage.getItem('dashboard-theme');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else {
                    document.documentElement.setAttribute('data-theme', 'light');
                }
            }

            // Toggle theme
            function toggleTheme() {
                const currentTheme = document.documentElement.getAttribute('data-theme');
                const newTheme = currentTheme === 'light' ? 'dark' : 'light';

                document.documentElement.setAttribute('data-theme', newTheme);
                localStorage.setItem('dashboard-theme', newTheme);

                themeSwitch.style.transform = 'rotate(180deg)';
                setTimeout(() => { themeSwitch.style.transform = 'rotate(0)'; }, 300);
            }

            initializeTheme();
            themeSwitch.addEventListener('click', toggleTheme);
        });

        // Navigation functions
        function viewRoomDetails(roomId) {
            console.log('View room details:', roomId);
            // TODO: Implement modal or navigation to room details
        }

        function viewPatientDetails(encamamentoId) {
            window.location.href = 'detalle_encamamiento.php?id=' + encamamentoId;
        }

        const dischargesModal = new bootstrap.Modal(document.getElementById('dischargesModal'));

        function openDischargesModal() {
            dischargesModal.show();
        }

        async function generateDischargesReport() {
            const start = document.getElementById('report_start_date').value;
            const end = document.getElementById('report_end_date').value;
            const container = document.getElementById('report_results_container');
            const tbody = document.getElementById('report_table_body');
            const totalElem = document.getElementById('report_total_amount');

            if (!start || !end) {
                Swal.fire('Error', 'Debe seleccionar ambas fechas', 'error');
                return;
            }

            try {
                const response = await fetch(`api/get_discharges_report.php?start=${start}&end=${end}`);
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Error al obtener el reporte');
                }

                tbody.innerHTML = '';
                let totalGeneral = 0;

                if (data.report.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4">No se encontraron altas en este periodo</td></tr>';
                } else {
                    data.report.forEach(row => {
                        const total = parseFloat(row.total_general) || 0;
                        totalGeneral += total;

                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                        <td>${new Date(row.fecha_alta).toLocaleDateString()}</td>
                        <td><strong>${row.nombre_paciente} ${row.apellido_paciente}</strong></td>
                        <td><span class="badge ${getTipoBadgeClass(row.tipo_ingreso)}">${row.tipo_ingreso}</span></td>
                        <td>Dr(a). ${row.nombre_doctor} ${row.apellido_doctor}</td>
                        <td>${row.dias_hospitalizado}</td>
                        <td class="text-end">Q${total.toLocaleString('es-GT', { minimumFractionDigits: 2 })}</td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-info" onclick="viewPatientDetails(${row.id_encamamiento})">
                                <i class="bi bi-eye"></i>
                            </button>
                        </td>
                    `;
                        tbody.appendChild(tr);
                    });
                }

                totalElem.innerText = `Q${totalGeneral.toLocaleString('es-GT', { minimumFractionDigits: 2 })}`;
                container.style.display = 'block';

            } catch (error) {
                Swal.fire('Error', error.message, 'error');
            }
        }

        function getTipoBadgeClass(tipo) {
            switch (tipo) {
                case 'Urgente': return 'badge-urgente';
                case 'Programado': return 'badge-programado';
                case 'Referido': return 'badge-referido';
                default: return 'bg-secondary';
            }
        }

        console.log('Hospitalización Dashboard - CMHS v3.0');
    </script>
</body>

</html>