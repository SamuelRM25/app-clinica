<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';



// Establecer la zona horaria correcta
date_default_timezone_set('America/Guatemala');

verify_session();

// Only admins can manage the catalog
if ($_SESSION['tipoUsuario'] !== 'admin') {
    header("Location: index.php");
    exit;
}

try {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        $_SESSION['patient_message'] = "ID de paciente inválido";
        $_SESSION['patient_status'] = "danger";
        header("Location: index.php");
        exit;
    }

    $patient_id = $_GET['id'];

    $database = new Database();
    $conn = $database->getConnection();

    // Obtener información del paciente con estadísticas
    $stmt = $conn->prepare("SELECT p.*, 
                           COUNT(h.id_historial) as total_consultas,
                           MAX(h.fecha_consulta) as ultima_consulta
                           FROM pacientes p
                           LEFT JOIN historial_clinico h ON p.id_paciente = h.id_paciente
                           WHERE p.id_paciente = ?
                           GROUP BY p.id_paciente");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        $_SESSION['patient_message'] = "Paciente no encontrado";
        $_SESSION['patient_status'] = "danger";
        header("Location: index.php");
        exit;
    }

    // Obtener historial médico con información del doctor
    $stmt = $conn->prepare("SELECT h.*, 
                           u.nombre as doctor_nombre, 
                           u.apellido as doctor_apellido
                           FROM historial_clinico h
                           LEFT JOIN usuarios u ON h.medico_responsable = CONCAT(u.nombre, ' ', u.apellido)
                           WHERE h.id_paciente = ? 
                           ORDER BY h.fecha_consulta DESC, h.id_historial DESC");
    $stmt->execute([$patient_id]);
    $medical_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener total de pacientes para la barra lateral
    $stmtSummary = $conn->query("SELECT COUNT(*) as total FROM pacientes");
    $total_patients = $stmtSummary->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Obtener lista de doctores para el modal de nueva consulta
    $stmtDocs = $conn->prepare("SELECT idUsuario, nombre, apellido, especialidad FROM usuarios WHERE tipoUsuario = 'doc' ORDER BY nombre, apellido");
    $stmtDocs->execute();
    $doctors = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

    // Obtener resultados de laboratorio con archivos adjuntos
    // Buscamos tanto en ordenes_laboratorio como en los tests individuales (orden_pruebas)
    $stmtLab = $conn->prepare("
        SELECT ol.numero_orden, ol.fecha_orden, op.archivo_resultados, cp.nombre_prueba
        FROM ordenes_laboratorio ol
        JOIN orden_pruebas op ON ol.id_orden = op.id_orden
        JOIN catalogo_pruebas cp ON op.id_prueba = cp.id_prueba
        WHERE ol.id_paciente = ? AND op.archivo_resultados IS NOT NULL
        ORDER BY ol.fecha_orden DESC
    ");
    $stmtLab->execute([$patient_id]);
    $lab_results = $stmtLab->fetchAll(PDO::FETCH_ASSOC);

    // Calcular edad del paciente
    $edad = isset($patient['fecha_nacimiento']) ?
        (new DateTime())->diff(new DateTime($patient['fecha_nacimiento']))->y : 0;

    // Obtener información del usuario
    $user_name = $_SESSION['nombre'];
    $user_specialty = $_SESSION['especialidad'] ?? 'Profesional Médico';

    // Obtener catálogo completo de pruebas para Select2
    $stmtCat = $conn->query("SELECT id_prueba, nombre_prueba, categoria, precio FROM catalogo_pruebas ORDER BY categoria, nombre_prueba");
    $all_tests = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

    $page_title = "Historial Clínico - " . $patient['nombre'] . " " . $patient['apellido'] . " - Centro Médico Herrera Sáenz";

} catch (Exception $e) {
    error_log("Error en historial clínico: " . $e->getMessage());
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Historial Clínico - Centro Médico Herrera Sáenz">
    <title><?php echo $page_title; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">

    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <!-- CSS Crítico (mismo que el dashboard) -->
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
                <!-- Botón hamburguesa para móvil -->
                <button class="mobile-toggle" id="mobileSidebarToggle" aria-label="Abrir menú">
                    <i class="bi bi-list"></i>
                </button>

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
            <!-- Botón Volver -->
            <div class="mb-4 animate-in">
                <a href="index.php" class="btn btn-outline-primary d-inline-flex align-items-center gap-2">
                    <i class="bi bi-arrow-left"></i>
                    <span>Volver a Pacientes</span>
                </a>
            </div>
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
                        <i class="bi bi-file-medical text-primary" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>

            <!-- Información del paciente -->
            <div class="patient-info-card animate-in delay-1">
                <div class="patient-header">
                    <div class="patient-avatar-large">
                        <?php echo strtoupper(substr($patient['nombre'], 0, 1) . substr($patient['apellido'], 0, 1)); ?>
                    </div>
                    <div class="patient-details">
                        <h2 class="patient-name">
                            <?php echo htmlspecialchars($patient['nombre'] . ' ' . $patient['apellido']); ?>
                        </h2>
                        <div class="patient-meta">
                            <span>ID: #<?php echo str_pad($patient_id, 5, '0', STR_PAD_LEFT); ?></span>
                            <span><?php echo $edad; ?> años</span>
                            <span><?php echo htmlspecialchars($patient['genero']); ?></span>
                            <span><?php echo $patient['total_consultas']; ?> consultas</span>
                        </div>
                    </div>
                    <div class="action-buttons">
                        <a href="index.php" class="btn-icon" title="Volver a pacientes">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <a href="../hospitalization/ingresar_paciente.php?id_paciente=<?php echo $patient_id; ?>"
                            class="btn-icon" title="Ingresar paciente"
                            style="background-color: var(--color-success); color: white;">
                            <i class="bi bi-hospital"></i>
                        </a>
                    </div>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Fecha de Nacimiento</span>
                        <span
                            class="info-value"><?php echo date('d/m/Y', strtotime($patient['fecha_nacimiento'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Teléfono</span>
                        <span class="info-value"><?php echo htmlspecialchars($patient['telefono'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Correo Electrónico</span>
                        <span class="info-value"><?php echo htmlspecialchars($patient['correo'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Última Consulta</span>
                        <span
                            class="info-value"><?php echo $patient['ultima_consulta'] ? date('d/m/Y', strtotime($patient['ultima_consulta'])) : 'N/A'; ?></span>
                    </div>
                </div>
            </div>

            <!-- Historial médico -->
            <section class="appointments-section animate-in delay-3">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-clock-history section-title-icon"></i>
                        Historial de Consultas
                    </h3>
                    <button type="button" class="action-btn" data-bs-toggle="modal"
                        data-bs-target="#newMedicalRecordModal">
                        <i class="bi bi-plus-lg"></i>
                        Nueva Consulta
                    </button>
                </div>

                <?php if (count($medical_records) > 0): ?>
                    <div class="medical-timeline">
                        <!-- Sección de Resultados de Laboratorio -->
                        <?php if (!empty($lab_results)): ?>
                            <div class="card mb-4 border-0 shadow-sm" style="border-radius: var(--radius-lg);">
                                <div class="card-header bg-white py-3 border-0">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="icon-shape bg-info-subtle text-info rounded-3 p-2">
                                            <i class="bi bi-file-earmark-medical fs-5"></i>
                                        </div>
                                        <h5 class="mb-0 fw-bold text-dark">Resultados de Laboratorio</h5>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <?php foreach ($lab_results as $lab): ?>
                                            <div class="col-md-6 col-lg-4">
                                                <div
                                                    class="p-3 border rounded-3 position-relative hover-shadow transition-all bg-light">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <span class="badge bg-primary bg-opacity-10 text-primary">Lab
                                                            #<?php echo htmlspecialchars($lab['numero_orden']); ?></span>
                                                        <span
                                                            class="text-muted small"><?php echo date('d/M/Y', strtotime($lab['fecha_orden'])); ?></span>
                                                    </div>
                                                    <h6 class="mb-1 text-dark fw-semibold">
                                                        <?php echo htmlspecialchars($lab['nombre_prueba']); ?>
                                                    </h6>
                                                    <p class="text-muted small mb-2">Resultado Adjunto</p>

                                                    <div class="mt-2">
                                                        <a href="<?php echo htmlspecialchars($lab['archivo_resultados']); ?>"
                                                            target="_blank"
                                                            class="btn btn-sm btn-outline-primary w-100 stretched-link">
                                                            <i class="bi bi-eye me-1"></i> Ver Resultados
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($medical_records as $index => $record): ?>
                            <div class="timeline-item">
                                <div class="consultation-card">
                                    <div class="consultation-header" data-bs-toggle="collapse"
                                        data-bs-target="#collapseRecord<?php echo $record['id_historial']; ?>"
                                        aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>">
                                        <div class="consultation-date">
                                            <div class="date-day"><?php echo date('D', strtotime($record['fecha_consulta'])); ?>
                                            </div>
                                            <div class="date-number">
                                                <?php echo date('d', strtotime($record['fecha_consulta'])); ?>
                                            </div>
                                            <div class="date-day">
                                                <?php echo date('M/y', strtotime($record['fecha_consulta'])); ?>
                                            </div>
                                        </div>
                                        <div class="consultation-doctor">
                                            <div class="doctor-name">Dr.
                                                <?php echo htmlspecialchars($record['medico_responsable']); ?>
                                            </div>
                                            <div class="doctor-label">Médico responsable</div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="action-buttons">
                                                <?php if (!empty($record['receta_medica'])): ?>
                                                    <a href="print_prescription.php?id=<?php echo $record['id_historial']; ?>"
                                                        class="btn-icon print" title="Imprimir Receta" target="_blank">
                                                        <i class="bi bi-printer"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                            <i
                                                class="bi bi-chevron-down collapse-icon <?php echo $index === 0 ? 'rotate-180' : ''; ?>"></i>
                                        </div>
                                    </div>

                                    <div id="collapseRecord<?php echo $record['id_historial']; ?>"
                                        class="collapse <?php echo $index === 0 ? 'show' : ''; ?>">
                                        <div class="consultation-content">
                                            <div class="row g-4">
                                                <div class="col-md-7">
                                                    <div class="section-box">
                                                        <div class="section-title-small">
                                                            <i class="bi bi-chat-left-text"></i>
                                                            Motivo de Consulta
                                                        </div>
                                                        <div class="section-content">
                                                            <?php echo nl2br(htmlspecialchars($record['motivo_consulta'])); ?>
                                                        </div>
                                                    </div>

                                                    <div class="section-box">
                                                        <div class="section-title-small">
                                                            <i class="bi bi-list-check"></i>
                                                            Síntomas / Historia
                                                        </div>
                                                        <div class="section-content">
                                                            <?php echo nl2br(htmlspecialchars($record['sintomas'])); ?>
                                                        </div>
                                                    </div>

                                                    <?php if (!empty($record['examen_fisico'])): ?>
                                                        <div class="section-box">
                                                            <div class="section-title-small">
                                                                <i class="bi bi-heart-pulse"></i>
                                                                Examen Físico
                                                            </div>
                                                            <div class="section-content">
                                                                <?php echo nl2br(htmlspecialchars($record['examen_fisico'])); ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="col-md-5">
                                                    <div class="section-box" style="border-left-color: var(--color-warning);">
                                                        <div class="section-title-small" style="color: var(--color-warning);">
                                                            <i class="bi bi-clipboard-check"></i>
                                                            Diagnóstico
                                                        </div>
                                                        <div class="section-content">
                                                            <?php echo nl2br(htmlspecialchars($record['diagnostico'])); ?>
                                                        </div>
                                                    </div>

                                                    <div class="section-box" style="border-left-color: var(--color-success);">
                                                        <div class="section-title-small" style="color: var(--color-success);">
                                                            <i class="bi bi-prescription2"></i>
                                                            Tratamiento
                                                        </div>
                                                        <div class="section-content">
                                                            <?php echo nl2br(htmlspecialchars($record['tratamiento'])); ?>
                                                        </div>
                                                    </div>

                                                    <?php if (!empty($record['proxima_cita'])): ?>
                                                        <div class="section-box" style="border-left-color: var(--color-info);">
                                                            <div class="section-title-small" style="color: var(--color-info);">
                                                                <i class="bi bi-calendar-check"></i>
                                                                Próxima Cita
                                                            </div>
                                                            <div class="section-content">
                                                                <strong><?php echo date('d/m/Y', strtotime($record['proxima_cita'])); ?></strong>
                                                                <?php if (!empty($record['hora_proxima_cita'])): ?>
                                                                    <br><?php echo $record['hora_proxima_cita']; ?>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <?php if (!empty($record['receta_medica'])): ?>
                                                <div class="section-box mt-4" style="border-left-color: var(--color-primary);">
                                                    <div class="section-title-small" style="color: var(--color-primary);">
                                                        <i class="bi bi-prescription"></i>
                                                        Prescripción Médica
                                                    </div>
                                                    <div class="section-content"
                                                        style="font-family: 'Courier New', monospace; white-space: pre-wrap;">
                                                        <?php
                                                        $clean_receta = implode("\n", array_map('trim', explode("\n", $record['receta_medica'])));
                                                        echo nl2br(htmlspecialchars($clean_receta));
                                                        ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="bi bi-clipboard-x"></i>
                        </div>
                        <h4 class="text-muted mb-2">No hay registros médicos</h4>
                        <p class="text-muted mb-3">Este paciente aún no tiene consultas registradas</p>
                        <button type="button" class="action-btn" data-bs-toggle="modal"
                            data-bs-target="#newMedicalRecordModal">
                            <i class="bi bi-plus-circle"></i>
                            Crear primer registro
                        </button>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Estadísticas de consultas -->
            <div class="stats-grid mb-5 animate-in delay-2">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Total Consultas</div>
                            <div class="stat-value"><?php echo $patient['total_consultas']; ?></div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="bi bi-file-medical"></i>
                        </div>
                    </div>
                    <div class="text-muted">Historial completo</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Última Consulta</div>
                            <div class="stat-value">
                                <?php echo $patient['ultima_consulta'] ? date('d/m/Y', strtotime($patient['ultima_consulta'])) : 'N/A'; ?>
                            </div>
                        </div>
                        <div class="stat-icon success">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                    </div>
                    <div class="text-muted">Fecha más reciente</div>
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript Optimizado -->
    <!-- Modal para nuevo registro médico -->
    <div class="modal fade" id="newMedicalRecordModal" tabindex="-1" aria-labelledby="newMedicalRecordModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content"
                style="background: var(--color-card); border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden;">
                <div class="modal-header"
                    style="border-bottom: 1px solid var(--color-border); padding: var(--space-lg);">
                    <h5 class="modal-title" id="newMedicalRecordModalLabel"
                        style="font-weight: 600; display: flex; align-items: center; gap: var(--space-sm);">
                        <i class="bi bi-clipboard-plus text-primary"></i>
                        Nuevo Registro Clínico
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="newMedicalRecordForm" action="save_medical_record.php" method="POST">
                    <input type="hidden" name="id_paciente" value="<?php echo $patient_id; ?>">

                    <div class="modal-body" style="padding: var(--space-lg); max-height: 70vh; overflow-y: auto;">
                        <div class="row g-4">
                            <!-- Información de la consulta -->
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label for="motivo_consulta" class="form-label fw-semibold">Motivo de Consulta
                                        *</label>
                                    <textarea id="motivo_consulta" name="motivo_consulta" class="form-control" rows="3"
                                        required placeholder="Describa el motivo de la visita..."></textarea>
                                </div>
                                <div class="form-group mb-4">
                                    <label for="sintomas" class="form-label fw-semibold">Síntomas / Historia *</label>
                                    <textarea id="sintomas" name="sintomas" class="form-control" rows="3" required
                                        placeholder="Detalle los síntomas presentados..."></textarea>
                                </div>
                                <div class="form-group mb-4">
                                    <label for="examen_fisico" class="form-label fw-semibold">Examen Físico</label>
                                    <div class="row g-3">
                                        <div class="col-md-7">
                                            <textarea id="examen_fisico" name="examen_fisico" class="form-control" rows="8"
                                                placeholder="Hallazgos del examen físico..."></textarea>
                                            <input type="hidden" name="puntos_dolor" id="puntos_dolor">
                                        </div>
                                        <div class="col-md-5">
                                            <div id="human-body-map-container" class="border rounded p-2 bg-white">
                                                <!-- SVG se cargará aquí -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Diagnóstico y Tratamiento -->
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label for="diagnostico" class="form-label fw-semibold">Diagnóstico *</label>
                                    <textarea id="diagnostico" name="diagnostico" class="form-control" rows="3" required
                                        placeholder="Diagnóstico médico..."></textarea>
                                </div>
                                <div class="form-group mb-4">
                                    <label for="tratamiento" class="form-label fw-semibold">Tratamiento *</label>
                                    <textarea id="tratamiento" name="tratamiento" class="form-control" rows="3" required
                                        placeholder="Plan de tratamiento..."></textarea>
                                </div>
                                <div class="form-group mb-4">
                                    <label for="receta_medica" class="form-label fw-semibold">Receta Médica</label>
                                    <textarea id="receta_medica" name="receta_medica" class="form-control" rows="3"
                                        placeholder="Medicamentos y dosis..."></textarea>
                                </div>
                            </div>

                            <div class="col-12">
                                <hr class="my-2" style="border-color: var(--color-border);">
                            </div>

                            <!-- Antecedentes -->
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label for="antecedentes_personales" class="form-label fw-semibold">Antecedentes
                                        Personales</label>
                                    <textarea id="antecedentes_personales" name="antecedentes_personales"
                                        class="form-control" rows="2"
                                        placeholder="Alergias, cirugías, enfermedades previas..."></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label for="antecedentes_familiares" class="form-label fw-semibold">Antecedentes
                                        Familiares</label>
                                    <textarea id="antecedentes_familiares" name="antecedentes_familiares"
                                        class="form-control" rows="2"
                                        placeholder="Enfermedades hereditarias..."></textarea>
                                </div>
                            </div>

                            <!-- Exámenes -->
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label for="examenes_realizados" class="form-label fw-semibold mb-0">Exámenes
                                            Solicitados</label>
                                    </div>
                                    <select id="examenes_realizados" name="examenes_realizados[]" class="form-select select2-tests" multiple data-placeholder="Buscar pruebas de laboratorio...">
                                        <?php 
                                        $current_cat = '';
                                        foreach ($all_tests as $test): 
                                            if ($current_cat !== $test['categoria']):
                                                if ($current_cat !== '') echo '</optgroup>';
                                                $current_cat = $test['categoria'];
                                                echo '<optgroup label="' . htmlspecialchars($current_cat) . '">';
                                            endif;
                                        ?>
                                            <option value="<?php echo $test['id_prueba']; ?>">
                                                <?php echo htmlspecialchars($test['nombre_prueba']); ?> (Q<?php echo number_format($test['precio'], 2); ?>)
                                            </option>
                                        <?php endforeach; if ($current_cat !== '') echo '</optgroup>'; ?>
                                    </select>
                                    <small class="text-muted mt-1 d-block">Las pruebas seleccionadas generarán una orden de laboratorio automáticamente.</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label for="resultados_examenes" class="form-label fw-semibold">Resultados
                                        Importantes</label>
                                    <textarea id="resultados_examenes" name="resultados_examenes" class="form-control"
                                        rows="2"
                                        placeholder="Valores críticos o hallazgos relevantes...">pendiente de recibir</textarea>
                                </div>
                            </div>

                            <div class="col-12">
                                <hr class="my-2" style="border-color: var(--color-border);">
                            </div>

                            <!-- Próxima Cita -->
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label for="proxima_cita" class="form-label fw-semibold">Próxima Cita</label>
                                    <input type="date" id="proxima_cita" name="proxima_cita" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label for="hora_proxima_cita" class="form-label fw-semibold">Hora de Cita</label>
                                    <input type="time" id="hora_proxima_cita" name="hora_proxima_cita"
                                        class="form-control">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label for="medico_responsable" class="form-label fw-semibold">Médico Responsable
                                        *</label>
                                    <select id="medico_responsable" name="medico_responsable" class="form-select"
                                        required>
                                        <option value="">Seleccionar Médico...</option>
                                        <?php foreach ($doctors as $doctor): ?>
                                            <option
                                                value="<?php echo htmlspecialchars($doctor['nombre'] . ' ' . $doctor['apellido']); ?>"
                                                <?php echo ($doctor['nombre'] . ' ' . $doctor['apellido'] === $user_name) ? 'selected' : ''; ?>>
                                                Dr(a).
                                                <?php echo htmlspecialchars($doctor['nombre'] . ' ' . $doctor['apellido']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="especialidad_medico"
                                        value="<?php echo htmlspecialchars($user_specialty); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer"
                        style="border-top: 1px solid var(--color-border); padding: var(--space-lg);">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"
                            style="border-radius: var(--radius-md);">Cancelar</button>
                        <button type="submit" class="action-btn">
                            <i class="bi bi-save me-1"></i>
                            Guardar Registro
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para Orden de Laboratorio (Iframe) -->
    <div class="modal fade" id="labOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 95vw;">
            <div class="modal-content" style="height: 90vh;">
                <div class="modal-header">
                    <h5 class="modal-title">Orden de Laboratorio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <iframe id="labOrderFrame" src="" style="width: 100%; height: 100%; border: none;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <!-- Select2 JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../../assets/js/human_body_map.js"></script>

    <script>
        $(document).ready(function() {
            // Inicializar Select2
            $('.select2-tests').select2({
                theme: 'bootstrap-5',
                width: '100%',
                dropdownParent: $('#newMedicalRecordModal')
            });

            // Inicializar Mapa de Cuerpo
            HumanBodyMap.render('human-body-map-container', 'puntos_dolor');
        });

        function openLabOrderModal() {
            const patientId = <?php echo $patient_id; ?>;
            const frame = document.getElementById('labOrderFrame');
            frame.src = '../laboratory/crear_orden.php?id_paciente=' + patientId + '&embedded=1';
            new bootstrap.Modal(document.getElementById('labOrderModal')).show();
        }
    </script>
        // Dashboard Reingenierizado - Centro Médico RS
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
                currentTimeElement: document.getElementById('current-time')
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
                    if (DOM.sidebarToggle) {
                        DOM.sidebarToggle.addEventListener('click', () => this.toggle());
                    }

                    if (DOM.mobileSidebarToggle) {
                        DOM.mobileSidebarToggle.addEventListener('click', () => this.toggle());
                    }

                    if (DOM.sidebarOverlay) {
                        DOM.sidebarOverlay.addEventListener('click', () => this.closeMobile());
                    }

                    const navLinks = DOM.sidebar.querySelectorAll('.nav-link');
                    navLinks.forEach(link => {
                        link.addEventListener('click', () => {
                            if (this.isMobile) this.closeMobile();
                        });
                    });

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
            class DynamicComponents {
                constructor() {
                    this.setupGreeting();
                    this.setupClock();
                    this.setupAnimations();
                    this.setupCollapseIcons();
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

                    document.querySelectorAll('.stat-card, .appointments-section, .patient-info-card').forEach(el => {
                        observer.observe(el);
                    });
                }

                setupCollapseIcons() {
                    const collapsibleElements = document.querySelectorAll('.collapse');
                    collapsibleElements.forEach(el => {
                        el.addEventListener('show.bs.collapse', function () {
                            const icon = this.previousElementSibling?.querySelector('.collapse-icon');
                            if (icon) icon.classList.add('rotate-180');
                        });

                        el.addEventListener('hide.bs.collapse', function () {
                            const icon = this.previousElementSibling?.querySelector('.collapse-icon');
                            if (icon) icon.classList.remove('rotate-180');
                        });
                    });
                }
            }

            // ==========================================================================
            // INICIALIZACIÓN DE LA APLICACIÓN
            // ==========================================================================
            document.addEventListener('DOMContentLoaded', () => {
                const themeManager = new ThemeManager();
                const sidebarManager = new SidebarManager();
                const dynamicComponents = new DynamicComponents();

                window.dashboard = {
                    theme: themeManager,
                    sidebar: sidebarManager,
                    components: dynamicComponents
                };

                console.log('Historial Clínico inicializado correctamente');
                console.log('Paciente: <?php echo htmlspecialchars($patient["nombre"] . " " . $patient["apellido"]); ?>');
                console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
            });

            // ==========================================================================
            // MANEJO DE ERRORES GLOBALES
            // ==========================================================================
            window.addEventListener('error', (event) => {
                const errorMsg = event.error ? event.error : (event.message ? event.message : 'Error desconocido');
                console.error('Error en historial clínico:', errorMsg, {
                    filename: event.filename,
                    lineno: event.lineno,
                    colno: event.colno
                });
            });

            // Función global para abrir el modal de orden
            window.openLabOrderModal = function () {
                const patientId = '<?php echo $patient_id; ?>'; // PHP injection
                const frame = document.getElementById('labOrderFrame');
                const modal = new bootstrap.Modal(document.getElementById('labOrderModal'));

                // Cargar la página en el iframe
                frame.src = `../laboratory/crear_orden.php?id_paciente=${patientId}`;

                modal.show();
            };

        })();

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

    <!-- Bootstrap JS (para modales y collapse) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>