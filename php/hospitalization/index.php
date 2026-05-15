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

    $stmt = $conn->prepare("SELECT permisos_modulos FROM usuarios WHERE idUsuario = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $user['permisos_modulos']) {
        $permisos = json_decode($user['permisos_modulos'], true);
        $has_access = ($permisos['hospitalization'] ?? false) || $user_type === 'admin';
    } else if ($user_type === 'admin') {
        $has_access = true;
    }

    if (!$has_access) {
        header("Location: ../dashboard/index.php");
        exit;
    }

    // ====================================
    // FETCH DASHBOARD DATA
    // ====================================

    // Total de camas
    $stmt_total_beds = $conn->query("SELECT COUNT(*) as total FROM camas");
    $total_beds = $stmt_total_beds->fetch(PDO::FETCH_ASSOC)['total'];

    // Camas ocupadas
    $stmt_occupied = $conn->query("SELECT COUNT(*) as total FROM camas WHERE estado = 'Ocupada'");
    $camas_ocupadas = $stmt_occupied->fetch(PDO::FETCH_ASSOC)['total'];

    // Camas disponibles
    $camas_disponibles = $total_beds - $camas_ocupadas;

    // Porcentaje de ocupación
    $porcentaje_ocupacion = $total_beds > 0 ? round(($camas_ocupadas / $total_beds) * 100, 1) : 0;

    // Total pacientes activos (hospitalizados)
    $stmt_active = $conn->query("SELECT COUNT(*) as total FROM encamamientos WHERE estado = 'Activo'");
    $pacientes_activos = $stmt_active->fetch(PDO::FETCH_ASSOC)['total'];

    // Ingresos hoy
    $stmt_today = $conn->prepare("SELECT COUNT(*) as total FROM encamamientos WHERE DATE(fecha_ingreso) = CURDATE()");
    $stmt_today->execute();
    $ingresos_hoy = $stmt_today->fetch(PDO::FETCH_ASSOC)['total'];

    // Altas hoy
    $stmt_altas = $conn->prepare("SELECT COUNT(*) as total FROM encamamientos WHERE DATE(fecha_alta) = CURDATE() AND estado IN ('Alta_Medica', 'Alta_Administrativa')");
    $stmt_altas->execute();
    $altas_hoy = $stmt_altas->fetch(PDO::FETCH_ASSOC)['total'];

    // Estancia promedio (últimos 30 días)
    $stmt_estancia = $conn->query("
        SELECT AVG(DATEDIFF(COALESCE(fecha_alta, NOW()), fecha_ingreso)) as promedio
        FROM encamamientos
        WHERE fecha_ingreso >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $estancia_promedio = round($stmt_estancia->fetch(PDO::FETCH_ASSOC)['promedio'] ?? 0, 1);

    // Lista de habitaciones con estado
    $stmt_rooms = $conn->query("
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
        GROUP BY h.id_habitacion
        ORDER BY h.piso, h.numero_habitacion
    ");
    $habitaciones = $stmt_rooms->fetchAll(PDO::FETCH_ASSOC);

    // Pacientes actualmente hospitalizados
    $stmt_patients = $conn->query("
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
        WHERE e.estado = 'Activo'
        ORDER BY e.fecha_ingreso DESC
    ");
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
</head>

<body>
    <!-- Efecto mármol -->
    <div class="marble-effect"></div>

    <div class="dashboard-container">
        <!-- Header -->
        <header class="dashboard-header">
            <div class="header-content">
                <div class="brand-container">
                    <img src="../../assets/img/Logo.png" alt="Centro Médico RS" class="brand-logo">
                </div>

                <div class="header-controls">
                    <!-- Theme Toggle -->
                    <div class="theme-toggle">
                        <button id="themeSwitch" class="theme-btn" aria-label="Cambiar tema">
                            <i class="bi bi-sun theme-icon sun-icon"></i>
                            <i class="bi bi-moon theme-icon moon-icon"></i>
                        </button>
                    </div>

                    <!-- User Info -->
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                            <span class="user-role"><?php echo htmlspecialchars($user_specialty); ?></span>
                        </div>
                    </div>

                    <!-- Back Button -->
                    <a href="../dashboard/index.php" class="action-btn secondary">
                        <i class="bi bi-arrow-left"></i>
                        Dashboard
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
                                    <th>Habitación / Cama</th>
                                    <th>Diagnóstico</th>
                                    <th>Médico</th>
                                    <th>Ingreso</th>
                                    <th>Días</th>
                                    <th>Signos Hoy</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pacientes_hospitalizados as $pac): ?>
                                    <tr>
                                        <td>
                                            <div class="patient-name"
                                                onclick="viewPatientDetails(<?php echo $pac['id_encamamiento']; ?>)">
                                                <?php echo htmlspecialchars($pac['nombre_paciente'] . ' ' . $pac['apellido_paciente']); ?>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($pac['genero']); ?> -
                                                <?php
                                                $edad = date_diff(date_create($pac['fecha_nacimiento']), date_create('today'))->y;
                                                echo $edad . ' años';
                                                ?>
                                            </small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($pac['numero_habitacion'] . ' - ' . $pac['numero_cama']); ?></strong><br>
                                            <small
                                                class="text-muted"><?php echo htmlspecialchars($pac['tipo_habitacion']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($pac['diagnostico_ingreso']); ?></td>
                                        <td>
                                            Dr(a).
                                            <?php echo htmlspecialchars($pac['nombre_doctor'] . ' ' . $pac['apellido_doctor']); ?>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($pac['fecha_ingreso'])); ?><br>
                                            <small
                                                class="text-muted"><?php echo date('h:i A', strtotime($pac['fecha_ingreso'])); ?></small>
                                        </td>
                                        <td><strong><?php echo $pac['dias_hospitalizado']; ?></strong> días</td>
                                        <td>
                                            <?php if ($pac['signos_hoy'] > 0): ?>
                                                <span class="badge badge-programado"><?php echo $pac['signos_hoy']; ?>
                                                    registros</span>
                                            <?php else: ?>
                                                <span class="badge badge-urgente">Pendiente</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary"
                                                onclick="viewPatientDetails(<?php echo $pac['id_encamamiento']; ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
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
                                $stmt_beds = $conn->prepare("SELECT numero_cama, estado FROM camas WHERE id_habitacion = ? ORDER BY numero_cama");
                                $stmt_beds->execute([$hab['id_habitacion']]);
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