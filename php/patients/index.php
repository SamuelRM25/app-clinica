<?php
// patients/index.php - Módulo de Gestión de Pacientes
// Versión: 4.0 - Diseño Dashboard con Efecto Mármol y Modo Noche
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
require_once '../../includes/breadcrumbs.php';
require_once '../../includes/module_guard.php';

check_module_access('core'); // Pacientes siempre activo (módulo base)

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

    // Título de la página
    $page_title = "Gestión de Pacientes - Centro Médico RS";

    // Obtener parámetros de ordenamiento
    $sort = $_GET['sort'] ?? 'name';
    $order_clause = "p.nombre, p.apellido"; // Default

    if ($sort === 'recent') {
        $order_clause = "p.id_paciente DESC";
    }

    // Consulta optimizada según tipo de usuario
    $hid = hospital_id();
    if ($user_type === 'doc') {
        $stmt = $conn->prepare("
            SELECT DISTINCT p.*, 
                   COUNT(c.id_cita) as total_citas,
                   MAX(c.fecha_cita) as ultima_cita
            FROM pacientes p
            LEFT JOIN citas c ON (p.nombre = c.nombre_pac AND p.apellido = c.apellido_pac)
            WHERE p.id_hospital = ?
              AND (c.id_doctor = ? OR p.id_paciente IN (
                SELECT DISTINCT id_paciente FROM historial_clinico 
                WHERE medico_responsable LIKE ?
            ))
            GROUP BY p.id_paciente
            ORDER BY $order_clause
        ");
        $doctor_name = $_SESSION['nombre'] . ' ' . $_SESSION['apellido'];
        $stmt->execute([$hid, $user_id, '%' . $doctor_name . '%']);
    } else {
        $stmt = $conn->prepare("
            SELECT p.*, 
                   COUNT(c.id_cita) as total_citas,
                   MAX(c.fecha_cita) as ultima_cita
            FROM pacientes p
            LEFT JOIN citas c ON (p.nombre = c.nombre_pac AND p.apellido = c.apellido_pac)
            WHERE p.id_hospital = ?
            GROUP BY p.id_paciente
            ORDER BY $order_clause
        ");
        $stmt->execute([$hid]);
    }

    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener estadísticas
    $total_patients = count($patients);
    $patients_with_appointments = count(array_filter($patients, function ($p) {
        return $p['total_citas'] > 0;
    }));
    $patients_without_history = count(array_filter($patients, function ($p) {
        return !isset($p['ultima_cita']);
    }));
    $active_today = count(array_filter($patients, function ($p) {
        return isset($p['ultima_cita']) && $p['ultima_cita'] === date('Y-m-d');
    }));

    // Obtener médicos para el modal de citas rápidas
    $stmt_doctors = $conn->prepare("
        SELECT idUsuario, nombre, apellido 
        FROM usuarios 
        WHERE tipoUsuario = 'doc' AND id_hospital = ?
        ORDER BY nombre, apellido
    ");
    $stmt_doctors->execute([hospital_id()]);
    $doctors = $stmt_doctors->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Manejo de errores
    error_log("Error en módulo de pacientes: " . $e->getMessage());
    die("Error al cargar el módulo de pacientes. Por favor, contacte al administrador.");
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Gestión de Pacientes - Centro Médico RS">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">

    <!-- Google Fonts - Inter -->
<!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Seguridad y Protección de Código -->
    <script src="../../assets/js/security.js"></script>

    <!-- CSS Crítico (mismo que el dashboard) -->
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">

    <style>
        /* ===== SEARCH & FILTER BAR ===== */
        .search-and-filter-bar {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        /* search-input — inherits global .search-box but needs width:100% */
        .search-input {
            width: 100%;
            padding: 0.625rem 1rem 0.625rem 2.5rem;
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-lg);
            background: var(--color-surface);
            color: var(--color-text);
            font-size: var(--font-size-sm);
            font-family: var(--font-family);
            transition: border-color 0.2s, box-shadow 0.2s, width 0.3s;
            outline: none;
        }

        .search-input:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.13);
            background: var(--color-card);
        }

        .search-input::placeholder {
            color: var(--color-text-secondary);
            font-style: italic;
        }

        /* ===== SORT CONTROLS ===== */
        .sort-controls-wrapper {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sort-group {
            display: flex;
            gap: 0.25rem;
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: 0.25rem;
        }

        .sort-item {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: var(--radius-sm);
            color: var(--color-text-secondary);
            text-decoration: none;
            font-size: 1rem;
            transition: all 0.2s;
        }

        .sort-item:hover {
            background: rgba(var(--color-primary-rgb), 0.08);
            color: var(--color-primary);
        }

        .sort-item.active {
            background: var(--color-primary);
            color: white;
            box-shadow: 0 2px 8px rgba(var(--color-primary-rgb), 0.3);
        }

        /* ===== FILTER TAGS ===== */
        .filters-scroll-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 0.25rem;
        }

        .filters-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: nowrap;
            min-width: max-content;
        }

        .filter-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.45rem 1rem;
            border: 1.5px solid var(--color-border);
            border-radius: 50px;
            background: var(--color-surface);
            color: var(--color-text-secondary);
            font-size: var(--font-size-xs);
            font-weight: 600;
            font-family: var(--font-family);
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .filter-tag:hover {
            border-color: var(--color-primary);
            color: var(--color-primary);
            background: rgba(var(--color-primary-rgb), 0.05);
            transform: translateY(-1px);
        }

        .filter-tag.active {
            background: var(--color-primary);
            border-color: var(--color-primary);
            color: white;
            box-shadow: 0 3px 10px rgba(var(--color-primary-rgb), 0.3);
        }

        .filter-tag i {
            font-size: 0.8rem;
        }

        /* ===== PATIENT TABLE AVATAR ===== */
        .avatar-sm {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            color: white;
            flex-shrink: 0;
        }

        .bg-male   { background: linear-gradient(135deg, var(--color-primary), #3b82f6); }
        .bg-female { background: linear-gradient(135deg, #f43f5e, #ec4899); }

        /* Dark mode */
        [data-theme="dark"] .search-and-filter-bar {
            background: var(--color-card-night);
            border-color: var(--color-border-night);
        }

        [data-theme="dark"] .search-input {
            background: var(--color-surface-night);
            border-color: var(--color-border-night);
            color: var(--color-text-night);
        }

        [data-theme="dark"] .sort-group {
            background: var(--color-surface-night);
            border-color: var(--color-border-night);
        }

        [data-theme="dark"] .filter-tag {
            background: var(--color-surface-night);
            border-color: var(--color-border-night);
            color: var(--color-text-secondary-night);
        }
    </style>

</head>

<body>
    <!-- Efecto de mármol animado -->
    <div class="marble-effect"></div>

    <!-- Contenedor Principal -->
    <div class="dashboard-container">
        <!-- Header Superior -->
        <header class="dashboard-header">
            <div class="header-content">
                <!-- Logo -->
                <div class="brand-container">
                    <img src="../../assets/img/Logo.png" alt="Centro Médico RS" class="brand-logo" width="40" height="40">
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
                ['label' => 'Pacientes'],
            ]); ?>
            <!-- Bienvenida personalizada -->
            <div class="stat-card mb-4 animate-in">
                <div class="stat-header">
                    <div>
                        <h2 id="greeting" class="stat-value" style="font-size: 1.75rem; margin-bottom: 0.5rem;">
                            <span id="greeting-text">Buenos días</span>, <?php echo htmlspecialchars($user_name); ?>
                        </h2>
                        <p class="text-muted mb-0">
                            <i class="bi bi-calendar-check me-1"></i> <?php echo date('d/m/Y'); ?>
                            <span class="mx-2">•</span>
                            <i class="bi bi-clock me-1"></i> <span id="current-time"><?php echo date('H:i'); ?></span>
                            <span class="mx-2">•</span>
                            <i class="bi bi-building me-1"></i> Centro Médico RS
                        </p>
                    </div>
                    <div class="d-none d-md-block">
                        <i class="bi bi-people text-primary" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>

            <!-- Estadísticas principales -->
            <?php if ($user_type === 'admin'): ?>
                <div class="stats-grid">
                    <!-- Total de pacientes -->
                    <div class="stat-card animate-in delay-1" onclick="filterPatients('all')">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Total Pacientes</div>
                                <div class="stat-value"><?php echo $total_patients; ?></div>
                            </div>
                            <div class="stat-icon primary">
                                <i class="bi bi-people-fill"></i>
                            </div>
                        </div>
                        <div class="stat-change positive">
                            <i class="bi bi-arrow-up-right"></i>
                            <span>Registrados en sistema</span>
                        </div>
                    </div>

                    <!-- Pacientes con citas -->
                    <div class="stat-card animate-in delay-2" onclick="filterPatients('with_appointments')">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Con Citas</div>
                                <div class="stat-value"><?php echo $patients_with_appointments; ?></div>
                            </div>
                            <div class="stat-icon success">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                        </div>
                        <div class="stat-change positive">
                            <i class="bi bi-person-check"></i>
                            <span>Con historial de citas</span>
                        </div>
                    </div>

                    <!-- Pacientes sin historial -->
                    <div class="stat-card animate-in delay-3" onclick="filterPatients('without_history')">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Sin Historial</div>
                                <div class="stat-value"><?php echo $patients_without_history; ?></div>
                            </div>
                            <div class="stat-icon warning">
                                <i class="bi bi-person-x"></i>
                            </div>
                        </div>
                        <div class="stat-change positive">
                            <i class="bi bi-exclamation-triangle"></i>
                            <span>Requieren primera consulta</span>
                        </div>
                    </div>

                    <!-- Activos hoy -->
                    <div class="stat-card animate-in delay-4" onclick="filterPatients('active_today')">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Activos Hoy</div>
                                <div class="stat-value"><?php echo $active_today; ?></div>
                            </div>
                            <div class="stat-icon info">
                                <i class="bi bi-activity"></i>
                            </div>
                        </div>
                        <div class="stat-change positive">
                            <i class="bi bi-calendar-day"></i>
                            <span>Atendidos hoy</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Barra de búsqueda y acciones -->
            <section class="appointments-section animate-in delay-1">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-person-lines-fill section-title-icon"></i>
                        Gestión de Pacientes
                    </h3>
                    <button type="button" class="action-btn primary" id="newPatientButton">
                        <i class="bi bi-person-plus"></i>
                        Nuevo Paciente
                    </button>
                </div>

                <div class="search-and-filter-bar mt-4">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="search-box">
                                <i class="bi bi-search search-icon"></i>
                                <input type="text" id="searchInput" class="search-input"
                                    placeholder="Buscar por nombre, DPI, teléfono o correo..."
                                    aria-label="Buscar pacientes">
                            </div>
                        </div>
                        <div class="col-lg-6 d-flex justify-content-lg-end align-items-center gap-3">
                            <div class="sort-controls-wrapper">
                                <span class="small fw-bold text-muted text-uppercase me-2">Ordenar:</span>
                                <div class="sort-group">
                                    <a href="?sort=name" class="sort-item <?php echo $sort === 'name' || !isset($_GET['sort']) ? 'active' : ''; ?>" title="Orden Alfabético">
                                        <i class="bi bi-sort-alpha-down"></i>
                                    </a>
                                    <a href="?sort=recent" class="sort-item <?php echo $sort === 'recent' ? 'active' : ''; ?>" title="Más Recientes">
                                        <i class="bi bi-clock-history"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="filters-scroll-container mt-3">
                        <div class="filters-group" id="filtersContainer">
                            <button type="button" class="filter-tag active" onclick="filterPatients('all')">
                                <i class="bi bi-grid-fill me-1"></i> Todos
                            </button>
                            <button type="button" class="filter-tag" onclick="filterPatients('with_appointments')">
                                <i class="bi bi-calendar-check me-1"></i> Con Citas
                            </button>
                            <button type="button" class="filter-tag" onclick="filterPatients('without_history')">
                                <i class="bi bi-file-earmark-medical me-1"></i> Sin Historial
                            </button>
                            <button type="button" class="filter-tag" onclick="filterPatients('active_today')">
                                <i class="bi bi-activity me-1"></i> Activos Hoy
                            </button>
                            <button type="button" class="filter-tag" onclick="filterPatients('male')">
                                <i class="bi bi-gender-male me-1"></i> Masculino
                            </button>
                            <button type="button" class="filter-tag" onclick="filterPatients('female')">
                                <i class="bi bi-gender-female me-1"></i> Femenino
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Tabla de pacientes -->
            <section class="appointments-section animate-in delay-2">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-list-ul section-title-icon"></i>
                        Lista de Pacientes
                    </h3>
                    <div class="text-muted" id="patientCount">
                        Mostrando <?php echo $total_patients; ?> pacientes
                    </div>
                </div>

                <?php if (count($patients) > 0): ?>
                    <div class="table-responsive">
                        <table class="appointments-table" id="patientsTable">
                            <thead>
                                <tr>
                                    <th>Paciente</th>
                                    <th>Contacto</th>
                                    <th>Información</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="patientsTableBody">
                                <?php foreach ($patients as $patient):
                                    $edad = isset($patient['fecha_nacimiento']) ?
                                        (new DateTime())->diff(new DateTime($patient['fecha_nacimiento']))->y : 0;
                                    $patient_initials = strtoupper(
                                        substr($patient['nombre'] ?? '', 0, 1) .
                                        substr($patient['apellido'] ?? '', 0, 1)
                                    );
                                    $has_appointments = $patient['total_citas'] > 0;
                                    $has_history = isset($patient['ultima_cita']);
                                    $active_today = $has_history && $patient['ultima_cita'] === date('Y-m-d');
                                    ?>
                                    <tr class="patient-row" data-id="<?php echo $patient['id_paciente']; ?>"
                                        data-name="<?php echo htmlspecialchars(strtolower(($patient['nombre'] ?? '') . ' ' . ($patient['apellido'] ?? ''))); ?>"
                                        data-raw-nombre="<?php echo htmlspecialchars($patient['nombre'] ?? ''); ?>"
                                        data-raw-apellido="<?php echo htmlspecialchars($patient['apellido'] ?? ''); ?>"
                                        data-phone="<?php echo htmlspecialchars(strtolower($patient['telefono'] ?? '')); ?>"
                                        data-email="<?php echo htmlspecialchars(strtolower($patient['correo'] ?? '')); ?>"
                                        data-direction="<?php echo htmlspecialchars($patient['direccion'] ?? ''); ?>"
                                        data-dpi="<?php echo htmlspecialchars($patient['dpi'] ?? ''); ?>"
                                        data-has-appointments="<?php echo $has_appointments ? 'true' : 'false'; ?>"
                                        data-has-history="<?php echo $has_history ? 'true' : 'false'; ?>"
                                        data-active-today="<?php echo $active_today ? 'true' : 'false'; ?>"
                                        data-gender="<?php echo htmlspecialchars($patient['gender'] ?? ''); ?>"
                                        data-birth="<?php echo htmlspecialchars($patient['fecha_nacimiento'] ?? ''); ?>"
                                        data-notes="<?php echo htmlspecialchars($patient['notas'] ?? ''); ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div>
                                                    <div class="fw-bold text-dark">
                                                        <?php echo htmlspecialchars($patient['nombre'] . ' ' . $patient['apellido']); ?>
                                                    </div>
                                                    <div class="small text-muted">
                                                        <?php
                                                        if (isset($patient['fecha_nacimiento'])) {
                                                            $dob = new DateTime($patient['fecha_nacimiento']);
                                                            $diff = (new DateTime())->diff($dob);

                                                            if ($diff->y > 0) {
                                                                echo $diff->y . ' años';
                                                            } elseif ($diff->m > 0) {
                                                                echo $diff->m . ' meses';
                                                            } else {
                                                                echo $diff->d . ' días';
                                                            }
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                        • <?php echo htmlspecialchars($patient['genero']); ?>
                                                        <?php if (!empty($patient['dpi'])): ?> • <i class="bi bi-card-text"></i> <?php echo htmlspecialchars($patient['dpi']); ?><?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column gap-1">
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="bi bi-telephone text-muted" style="font-size: 0.875rem;"></i>
                                                    <span><?php echo htmlspecialchars($patient['telefono'] ?? 'No disponible'); ?></span>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="bi bi-envelope text-muted" style="font-size: 0.875rem;"></i>
                                                    <span><?php echo htmlspecialchars($patient['correo'] ?? 'No disponible'); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column gap-1">
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="bi bi-calendar3 text-muted" style="font-size: 0.875rem;"></i>
                                                    <span><?php echo htmlspecialchars($patient['fecha_nacimiento'] ?? 'N/A'); ?>
                                                        (<?php
                                                        if (isset($patient['fecha_nacimiento'])) {
                                                            if ($diff->y > 0) {
                                                                echo $diff->y . ' años';
                                                            } elseif ($diff->m > 0) {
                                                                echo $diff->m . ' meses';
                                                            } else {
                                                                echo $diff->d . ' días';
                                                            }
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                        ?>)</span>
                                                </div>
                                                <?php if (isset($patient['genero'])): ?>
                                                    <span class="gender-badge <?php
                                                    echo strtolower($patient['genero']) === 'masculino' ? 'gender-male' :
                                                        (strtolower($patient['genero']) === 'femenino' ? 'gender-female' : 'gender-other');
                                                    ?>">
                                                        <?php echo htmlspecialchars($patient['genero']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($active_today): ?>
                                                <span class="status-badge status-active">
                                                    <i class="bi bi-check-circle"></i>
                                                    Activo Hoy
                                                </span>
                                            <?php elseif ($has_history): ?>
                                                <span class="status-badge status-active">
                                                    <i class="bi bi-check-circle"></i>
                                                    Activo
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge status-inactive"
                                                    onclick="openNoteModal(<?php echo $patient['id_paciente']; ?>, '<?php echo htmlspecialchars(($patient['nombre'] ?? '') . ' ' . ($patient['apellido'] ?? '')); ?>', this.closest('tr').dataset.notes)"
                                                    style="cursor: pointer;" title="Registrar Nota">
                                                    <i class="bi bi-pencil-square"></i>
                                                    Externo
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($has_appointments): ?>
                                                <div class="text-muted" style="font-size: 0.75rem; margin-top: 0.25rem;">
                                                    <i class="bi bi-calendar-check"></i>
                                                    <?php echo $patient['total_citas']; ?> citas
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" class="btn-icon edit" title="Editar Información"
                                                    onclick="editPatient(this)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <?php if ($user_type === 'admin'): ?>
                                                    <a href="medical_history.php?id=<?php echo $patient['id_paciente']; ?>"
                                                        class="btn-icon history" title="Historial Clínico">
                                                        <i class="bi bi-clipboard2-pulse"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <button type="button" class="btn-icon appointment" title="Nueva Cita"
                                                    onclick="quickAppointment(<?php echo $patient['id_paciente']; ?>, '<?php echo htmlspecialchars($patient['nombre']); ?>', '<?php echo htmlspecialchars($patient['apellido']); ?>')">
                                                    <i class="bi bi-calendar-plus"></i>
                                                </button>
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
                            <i class="bi bi-people"></i>
                        </div>
                        <h4 class="text-muted mb-2">No se encontraron pacientes</h4>
                        <p class="text-muted mb-3">Comienza agregando tu primer paciente</p>
                        <button type="button" class="action-btn" id="emptyNewPatientButton">
                            <i class="bi bi-person-plus"></i>
                            Agregar Primer Paciente
                        </button>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <!-- Modal para nuevo paciente -->
    <div class="custom-modal-overlay" id="newPatientModal">
        <div class="custom-modal">
            <div class="custom-modal-header">
                <h3 class="custom-modal-title">
                    <i class="bi bi-person-plus"></i>
                    Nuevo Paciente
                </h3>
                <button type="button" class="custom-modal-close" onclick="closeModal('newPatientModal')">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <form id="newPatientForm" action="save_patient.php" method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" id="edit_id_paciente" name="id_paciente">
                <div class="custom-modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nombre" class="form-label">Nombres *</label>
                            <input type="text" id="nombre" name="nombre" class="form-input"
                                placeholder="Ej: Juan Antonio" required>
                        </div>

                        <div class="form-group">
                            <label for="apellido" class="form-label">Apellidos *</label>
                            <input type="text" id="apellido" name="apellido" class="form-input"
                                placeholder="Ej: Pérez Sosa" required>
                        </div>

                        <div class="form-group">
                            <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento *</label>
                            <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" class="form-input" required
                                onchange="calculateAge(this.value)">
                        </div>

                        <div class="form-group">
                            <label for="edad_display" class="form-label">Edad Actual</label>
                            <input type="text" id="edad_display" class="form-input" readonly
                                placeholder="Calculada automáticamente...">
                        </div>

                        <div class="form-group">
                            <label for="genero" class="form-label">Género *</label>
                            <select id="genero" name="genero" class="form-select" required>
                                <option value="">Seleccionar...</option>
                                <option value="Masculino">Masculino</option>
                                <option value="Femenino">Femenino</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="dpi" class="form-label">DPI</label>
                            <div class="input-group">
                                <i class="bi bi-card-text input-icon"></i>
                                <input type="text" id="dpi" name="dpi" class="form-input"
                                    placeholder="Ej: 1234567890123" maxlength="15">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <div class="input-group">
                                <i class="bi bi-telephone input-icon"></i>
                                <input type="tel" id="telefono" name="telefono" class="form-input"
                                    placeholder="Ej: 46232418">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="correo" class="form-label">Correo Electrónico</label>
                            <div class="input-group">
                                <i class="bi bi-envelope input-icon"></i>
                                <input type="email" id="correo" name="correo" class="form-input"
                                    placeholder="Ej: juan@gmail.com">
                            </div>
                        </div>

                        <div class="form-group" style="grid-column: span 2;">
                            <label for="direccion" class="form-label">Dirección</label>
                            <textarea id="direccion" name="direccion" class="form-textarea"
                                placeholder="Ej: Barrio San Juan, Nentón" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="custom-modal-footer">
                    <button type="button" class="btn-outline" onclick="closeModal('newPatientModal')">
                        Cancelar
                    </button>
                    <button type="submit" class="action-btn" id="modalSubmitBtn">
                        Guardar Paciente
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para cita rápida -->
    <div class="custom-modal-overlay" id="quickAppointmentModal">
        <div class="custom-modal">
            <div class="custom-modal-header">
                <h3 class="custom-modal-title">
                    <i class="bi bi-calendar-plus"></i>
                    Nueva Cita Rápida
                </h3>
                <button type="button" class="custom-modal-close" onclick="closeModal('quickAppointmentModal')">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <form id="quickAppointmentForm">
                <div class="custom-modal-body">
                    <div class="form-grid">
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Paciente</label>
                            <input type="text" id="quickPatientName" class="form-input" readonly>
                            <input type="hidden" id="quickPatientId" name="patient_id">
                        </div>

                        <div class="form-group">
                            <label for="quickDate" class="form-label">Fecha *</label>
                            <input type="date" id="quickDate" name="fecha_cita" class="form-input" required>
                        </div>

                        <div class="form-group">
                            <label for="quickTime" class="form-label">Hora *</label>
                            <input type="time" id="quickTime" name="hora_cita" class="form-input" required>
                        </div>

                        <div class="form-group" style="grid-column: span 2;">
                            <label for="quickDoctor" class="form-label">Médico *</label>
                            <select id="quickDoctor" name="id_doctor" class="form-select" required>
                                <option value="">Seleccionar Médico...</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['idUsuario']; ?>">
                                        Dr(a).
                                        <?php echo htmlspecialchars($doctor['nombre'] . ' ' . $doctor['apellido']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group" style="grid-column: span 2;">
                            <label for="quickReason" class="form-label">Motivo de Consulta</label>
                            <textarea id="quickReason" name="motivo_consulta" class="form-textarea"
                                placeholder="Describa el motivo de la consulta" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="custom-modal-footer">
                    <button type="button" class="btn-outline" onclick="closeModal('quickAppointmentModal')">
                        Cancelar
                    </button>
                    <button type="submit" class="action-btn">
                        Programar Cita
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para registro de nota rápida -->
    <div class="custom-modal-overlay" id="noteModal">
        <div class="custom-modal">
            <div class="custom-modal-header">
                <h3 class="custom-modal-title">
                    <i class="bi bi-journal-text"></i>
                    Registrar Nota de Paciente
                </h3>
                <button type="button" class="custom-modal-close" onclick="closeModal('noteModal')">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <form id="noteForm" action="save_quick_note.php" method="POST">
                <?php echo csrf_field(); ?>
                <div class="custom-modal-body">
                    <div class="form-grid">
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Paciente</label>
                            <input type="text" id="notePatientName" class="form-input" readonly>
                            <input type="hidden" id="notePatientId" name="id_paciente">
                        </div>

                        <div class="form-group" style="grid-column: span 2;">
                            <label for="nota" class="form-label">Nota / Observaciones *</label>
                            <textarea id="nota" name="nota" class="form-textarea"
                                placeholder="Escriba aquí la nota o registro sobre el paciente..." rows="5"
                                required></textarea>
                        </div>
                    </div>
                </div>
                <div class="custom-modal-footer">
                    <button type="button" class="btn-outline" onclick="closeModal('noteModal')">
                        Cancelar
                    </button>
                    <button type="submit" class="action-btn">
                        <i class="bi bi-save"></i>
                        Guardar Nota
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript Optimizado -->
    <script>
        // Dashboard Reingenierizado - Centro Médico RS
        (function () {
            'use strict';

            // ==========================================================================
            // CONFIGURACIÓN Y CONSTANTES
            // ==========================================================================
            const CONFIG = {
                themeKey: 'dashboard-theme',

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
                greetingElement: document.getElementById('greeting-text'),
                currentTimeElement: document.getElementById('current-time'),
                searchInput: document.getElementById('searchInput'),
                newPatientButton: document.getElementById('newPatientButton'),
                emptyNewPatientButton: document.getElementById('emptyNewPatientButton'),
                patientRows: document.querySelectorAll('.patient-row'),
                patientCount: document.getElementById('patientCount')
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
            // COMPONENTES DINÁMICOS
            // ==========================================================================
            class DynamicComponents {
                constructor() {
                    this.setupGreeting();
                    this.setupClock();
                    this.setupPatientSearch();
                    this.setupModals();
                    this.setupAnimations();
                    this.handleAutoOpen();
                }

                handleAutoOpen() {
                    const urlParams = new URLSearchParams(window.location.search);
                    if (urlParams.get('new') === 'true') {
                        const nombre = urlParams.get('nombre');
                        const apellido = urlParams.get('apellido');

                        if (nombre) document.getElementById('nombre').value = nombre;
                        if (apellido) document.getElementById('apellido').value = apellido;

                        setTimeout(() => {
                            this.openModal('newPatientModal');
                        }, 500);
                    }
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

                setupPatientSearch() {
                    if (!DOM.searchInput) return;

                    DOM.searchInput.addEventListener('input', () => this.searchPatients());
                }

                searchPatients() {
                    const searchTerm = DOM.searchInput.value.toLowerCase().trim();
                    let visibleCount = 0;

                    DOM.patientRows.forEach(row => {
                        const name = row.dataset.name || '';
                        const phone = row.dataset.phone || '';
                        const email = row.dataset.email || '';

                        const matches = name.includes(searchTerm) ||
                            phone.includes(searchTerm) ||
                            email.includes(searchTerm);

                        if (matches || searchTerm === '') {
                            row.style.display = '';
                            visibleCount++;
                        } else {
                            row.style.display = 'none';
                        }
                    });

                    if (DOM.patientCount) {
                        DOM.patientCount.textContent = `Mostrando ${visibleCount} de ${DOM.patientRows.length} pacientes`;
                    }
                }

                setupModals() {
                    if (DOM.newPatientButton) {
                        DOM.newPatientButton.addEventListener('click', () => this.openModal('newPatientModal'));
                    }

                    if (DOM.emptyNewPatientButton) {
                        DOM.emptyNewPatientButton.addEventListener('click', () => this.openModal('newPatientModal'));
                    }

                    document.addEventListener('click', (e) => {
                        if (e.target.classList.contains('custom-modal-overlay')) {
                            this.closeModal(e.target.id);
                        }
                    });
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

                    document.querySelectorAll('.stat-card, .appointments-section').forEach(el => {
                        observer.observe(el);
                    });
                }

                openModal(modalId) {
                    const modal = document.getElementById(modalId);
                    if (modal) {
                        modal.classList.add('active');
                        document.body.style.overflow = 'hidden';
                    }
                }

                closeModal(modalId) {
                    const modal = document.getElementById(modalId);
                    if (modal) {
                        modal.classList.remove('active');
                        document.body.style.overflow = '';
                        // Reset form if it was editing
                        if (modalId === 'newPatientModal') {
                            document.getElementById('edit_id_paciente').value = '';
                            document.querySelector('#newPatientModal .custom-modal-title').innerHTML = '<i class="bi bi-person-plus"></i> Nuevo Paciente';
                            document.getElementById('modalSubmitBtn').textContent = 'Guardar Paciente';
                            document.getElementById('newPatientForm').reset();
                            document.getElementById('edad_display').value = '';
                        }
                    }
                }
            }

            window.editPatient = function (btn) {
                const row = btn.closest('tr');
                const p = row.dataset;

                document.getElementById('edit_id_paciente').value = p.id;
                document.getElementById('nombre').value = p.rawNombre;
                document.getElementById('apellido').value = p.rawApellido;
                document.getElementById('fecha_nacimiento').value = p.birth;
                document.getElementById('genero').value = p.gender;
                document.getElementById('telefono').value = p.phone;
                document.getElementById('dpi').value = p.dpi;
                document.getElementById('correo').value = p.email;
                document.getElementById('direccion').value = p.direction;

                // Actualizar UI del modal
                document.querySelector('#newPatientModal .custom-modal-title').innerHTML = '<i class="bi bi-pencil-square"></i> Editar Paciente';
                document.getElementById('modalSubmitBtn').textContent = 'Actualizar Paciente';

                // Calcular edad
                if (p.birth) window.calculateAge(p.birth);

                // Abrir modal
                window.dashboard.components.openModal('newPatientModal');
            };

            // ==========================================================================
            // INICIALIZACIÓN DE LA APLICACIÓN
            // ==========================================================================
            document.addEventListener('DOMContentLoaded', () => {
                const themeManager = new ThemeManager();
                const dynamicComponents = new DynamicComponents();

                window.dashboard = {
                    theme: themeManager,
                    components: dynamicComponents
                };

                console.log('Módulo de Pacientes inicializado correctamente');
                console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
                console.log('Rol: <?php echo htmlspecialchars($user_type); ?>');
                console.log('Tema: ' + themeManager.theme);
            });

            // ==========================================================================
            // FUNCIONES GLOBALES PARA PACIENTES
            // ==========================================================================

            window.quickAppointment = function (patientId, nombre, apellido) {
                document.getElementById('quickPatientId').value = patientId;
                document.getElementById('quickPatientName').value = nombre + ' ' + apellido;

                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                document.getElementById('quickDate').valueAsDate = tomorrow;
                document.getElementById('quickTime').value = '09:00';

                document.getElementById('quickAppointmentModal').classList.add('active');
                document.body.style.overflow = 'hidden';
            };

            window.closeModal = function (modalId) {
                document.getElementById(modalId).classList.remove('active');
                document.body.style.overflow = '';
            };

            window.openNoteModal = function (patientId, patientName, existingNote = '') {
                document.getElementById('notePatientId').value = patientId;
                document.getElementById('notePatientName').value = patientName;
                document.getElementById('nota').value = existingNote || '';

                document.getElementById('noteModal').classList.add('active');
                document.body.style.overflow = 'hidden';

                setTimeout(() => {
                    document.getElementById('nota').focus();
                }, 300);
            };

            window.calculateAge = function (birthDate) {
                if (!birthDate) return;
                const today = new Date();
                const birth = new Date(birthDate);

                let years = today.getFullYear() - birth.getFullYear();
                let months = today.getMonth() - birth.getMonth();
                let days = today.getDate() - birth.getDate();

                if (days < 0) {
                    months--;
                    const prevMonth = new Date(today.getFullYear(), today.getMonth(), 0);
                    days += prevMonth.getDate();
                }

                if (months < 0) {
                    years--;
                    months += 12;
                }

                let display = '';
                if (years > 0) {
                    display = years + (years === 1 ? ' año' : ' años');
                } else if (months > 0) {
                    display = months + (months === 1 ? ' mes' : ' meses');
                } else {
                    display = days + (days === 1 ? ' día' : ' días');
                }

                document.getElementById('edad_display').value = display;
            };

            window.filterPatients = function (filterType) {
                const filterButtons = document.querySelectorAll('.filter-btn');
                filterButtons.forEach(btn => btn.classList.remove('active'));
                event.target.classList.add('active');

                let visibleCount = 0;

                document.querySelectorAll('.patient-row').forEach(row => {
                    const hasAppointments = row.dataset.hasAppointments === 'true';
                    const hasHistory = row.dataset.hasHistory === 'true';
                    const activeToday = row.dataset.activeToday === 'true';
                    const gender = row.dataset.gender || '';

                    let show = false;

                    switch (filterType) {
                        case 'all':
                            show = true;
                            break;
                        case 'with_appointments':
                            show = hasAppointments;
                            break;
                        case 'without_history':
                            show = !hasHistory;
                            break;
                        case 'active_today':
                            show = activeToday;
                            break;
                        case 'male':
                            show = gender.includes('masculino');
                            break;
                        case 'female':
                            show = gender.includes('femenino');
                            break;
                        default:
                            show = true;
                    }

                    if (show) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                document.getElementById('patientCount').textContent =
                    `Mostrando ${visibleCount} de ${document.querySelectorAll('.patient-row').length} pacientes`;
            };

            // ==========================================================================
            // MANEJO DE ERRORES GLOBALES
            // ==========================================================================
            window.addEventListener('error', (event) => {
                console.error('Error en módulo de pacientes:', event.error);
            });

        })();

        // Manejar envío del formulario de nuevo paciente
        document.getElementById('newPatientForm')?.addEventListener('submit', function (e) {
            e.preventDefault();

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Guardando...';
            submitBtn.disabled = true;

            setTimeout(() => {
                this.submit();
            }, 1000);
        });

        // Manejar envío del formulario de cita rápida
        document.getElementById('quickAppointmentForm')?.addEventListener('submit', function (e) {
            e.preventDefault();

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;

            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Programando...';
            submitBtn.disabled = true;

            setTimeout(() => {
                alert('Cita programada exitosamente (simulación)');
                closeModal('quickAppointmentModal');

                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                this.reset();
            }, 1500);
        });

        // Manejar envío del formulario de nota rápida
        document.getElementById('noteForm')?.addEventListener('submit', function (e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Guardando...';
            submitBtn.disabled = true;
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
    <?php flash_toast(); ?>
</body>

</html>