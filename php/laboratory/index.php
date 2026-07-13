<?php
// laboratorio/index.php - Dashboard de Laboratorio
// Diseño Responsive, Barra Lateral Moderna, Efecto Mármol
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';
require_once '../../includes/module_guard.php';
require_once '../../includes/breadcrumbs.php';

check_module_access('laboratory');

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

    // ============ ESTADÍSTICAS DEL LABORATORIO ============

    // 1. Órdenes pendientes
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM ordenes_laboratorio WHERE estado = 'Pendiente' AND id_hospital = ?");
    $stmt->execute([hospital_id()]);
    $ordenes_pendientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 2. Muestras recibidas
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM ordenes_laboratorio WHERE estado = 'Muestra_Recibida' AND id_hospital = ?");
    $stmt->execute([hospital_id()]);
    $muestras_recibidas = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 3. Pendientes de validar
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM orden_pruebas op JOIN ordenes_laboratorio ol ON op.id_orden = ol.id_orden WHERE op.estado = 'En_Proceso' AND ol.id_hospital = ?");
    $stmt->execute([hospital_id()]);
    $pendientes_validar = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 4. Completadas hoy
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM ordenes_laboratorio WHERE DATE(fecha_orden) = CURDATE() AND estado = 'Completada' AND id_hospital = ?");
    $stmt->execute([hospital_id()]);
    $completadas_hoy = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $total_appointments = 0;
    $active_hospitalizations = 0;
    $pending_purchases = 0;

    // 5. Total de órdenes del mes
    $month_start = date('Y-m-01');
    $month_end = date('Y-m-t');
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM ordenes_laboratorio 
        WHERE fecha_orden BETWEEN ? AND ?
          AND id_hospital = ?
    ");
    $stmt->execute([$month_start, $month_end, hospital_id()]);
    $ordenes_mes = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 6. Pruebas más solicitadas (top 5) - Usando fecha de la orden
    $stmt = $conn->prepare("
        SELECT cp.nombre_prueba, COUNT(op.id_orden_prueba) as cantidad
        FROM orden_pruebas op
        JOIN catalogo_pruebas cp ON op.id_prueba = cp.id_prueba
        JOIN ordenes_laboratorio ol ON op.id_orden = ol.id_orden
        WHERE MONTH(ol.fecha_orden) = MONTH(CURDATE())
          AND ol.id_hospital = ?
        GROUP BY cp.id_prueba
        ORDER BY cantidad DESC
        LIMIT 5
    ");
    $stmt->execute([hospital_id()]);
    $pruebas_populares = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. Órdenes recientes
    $stmt = $conn->prepare("
        SELECT ol.*,
               p.nombre, p.apellido, p.genero, p.fecha_nacimiento,
               u.nombre as doctor_nombre, u.apellido as doctor_apellido,
               COUNT(op.id_orden_prueba) as num_pruebas,
               TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) as edad,
               er.id_examen_realizado
        FROM ordenes_laboratorio ol
        JOIN pacientes p ON ol.id_paciente = p.id_paciente
        LEFT JOIN usuarios u ON ol.id_doctor = u.idUsuario
        LEFT JOIN orden_pruebas op ON ol.id_orden = op.id_orden
        LEFT JOIN examenes_realizados er ON ol.id_orden = er.id_orden
        WHERE ol.estado IN ('Pendiente', 'Muestra_Recibida', 'En_Proceso', 'Completada', 'Validada')
          AND ol.id_hospital = ?
        GROUP BY ol.id_orden
        ORDER BY
            CASE
                WHEN ol.estado = 'Pendiente' THEN 1
                WHEN ol.estado = 'Muestra_Recibida' THEN 2
                WHEN ol.estado = 'En_Proceso' THEN 3
                WHEN ol.estado = 'Completada' THEN 4
                WHEN ol.estado = 'Validada' THEN 5
                ELSE 6
            END,
            ol.fecha_orden DESC
    ");
    $stmt->execute([hospital_id()]);
    $ordenes_recientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 8. Órdenes con retraso (más de 2 días en estado Pendiente)
    $two_days_ago = date('Y-m-d', strtotime('-2 days'));
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM ordenes_laboratorio
        WHERE estado = 'Pendiente'
          AND DATE(fecha_orden) <= ?
          AND id_hospital = ?
    ");
    $stmt->execute([$two_days_ago, hospital_id()]);
    $ordenes_retrasadas = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 9. Conteo de órdenes del mes agrupadas por laboratorio externo
    $stmt = $conn->prepare("
        SELECT laboratorio_externo, COUNT(*) as total
        FROM ordenes_laboratorio
        WHERE fecha_orden BETWEEN ? AND ?
          AND id_hospital = ?
          AND laboratorio_externo IS NOT NULL
        GROUP BY laboratorio_externo
        ORDER BY laboratorio_externo
    ");
    $stmt->execute([$month_start, $month_end, hospital_id()]);
    $labs_por_mes = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $labs_por_mes[$row['laboratorio_externo']] = (int)$row['total'];
    }

    // Título de la página
    $page_title = "Laboratorio - Centro Médico Herrera Saenz";

} catch (Exception $e) {
    error_log("Error en dashboard de laboratorio: " . $e->getMessage());
    error_log('Error en laboratory/index.php: ' . $e->getMessage());
    $error_msg = "Error al cargar el dashboard de laboratorio. Por favor, contacte al administrador.";
    if (isset($_SESSION['tipoUsuario']) && $_SESSION['tipoUsuario'] === 'admin') {
        $error_msg .= "<br><small>Detalles técnicos: " . htmlspecialchars($e->getMessage()) . "</small>";
    }
    die($error_msg);
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Dashboard de Laboratorio - Centro Médico Herrera Saenz">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <!-- logo -->
    <link rel="icon" type="image/png" href="../../assets/img/cmhs.png">

    <!-- Google Fonts - Inter -->
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Choices.js (para búsqueda en selects) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">

    <!-- Seguridad y Protección de Código -->
    <script src="../../assets/js/security.js"></script>

    <!-- CSS Crítico (incrustado para máxima velocidad) -->
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
    <?php include '../../includes/theme_head.php'; ?>
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
                            <span class="header-role">Laboratorio</span>
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
                ['label' => 'Laboratorio'],
            ]); ?>
            <!-- Banner de bienvenida -->
            <div class="welcome-banner animate-in">
                <h1>Laboratorio Clínico</h1>
                <p>Gestión de órdenes y resultados de laboratorio</p>
            </div>

            <!-- Alertas importantes -->
            <?php if ($ordenes_retrasadas > 0): ?>
                    <div class="alert-card mb-4 animate-in delay-1">
                        <div class="alert-header">
                            <div class="alert-icon warning">
                                <i class="bi bi-exclamation-triangle"></i>
                            </div>
                            <h3 class="alert-title">Órdenes con Retraso</h3>
                        </div>
                        <p class="text-muted mb-0">
                            Hay <strong><?php echo $ordenes_retrasadas; ?></strong> órdenes con más de 2 días en estado
                            "Pendiente".
                            <a href="?filter=retraso" class="text-primary text-decoration-none ms-1">
                                Revisar <i class="bi bi-arrow-right"></i>
                            </a>
                        </p>
                    </div>
            <?php endif; ?>

            <!-- Estadísticas principales -->
            <div class="stats-grid">
                <!-- Órdenes pendientes -->
                <div class="stat-card animate-in delay-1">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Órdenes Pendientes</div>
                            <div class="stat-value"><?php echo $ordenes_pendientes; ?></div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="bi bi-clock-history"></i>
                        </div>
                    </div>
                    <div class="stat-change">
                        <i class="bi bi-calendar-week"></i>
                        <span>Esperando procesamiento</span>
                    </div>
                </div>

                <!-- Muestras recibidas -->
                <div class="stat-card animate-in delay-2">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Muestras Recibidas</div>
                            <div class="stat-value"><?php echo $muestras_recibidas; ?></div>
                        </div>
                        <div class="stat-icon info">
                            <i class="bi bi-droplet"></i>
                        </div>
                    </div>
                    <div class="stat-change">
                        <i class="bi bi-check-circle"></i>
                        <span>Listas para análisis</span>
                    </div>
                </div>

                <!-- Por validar -->
                <div class="stat-card animate-in delay-3">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Por Validar</div>
                            <div class="stat-value"><?php echo $pendientes_validar; ?></div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="bi bi-clipboard-check"></i>
                        </div>
                    </div>
                    <div class="stat-change">
                        <i class="bi bi-shield-check"></i>
                        <span>Esperando validación</span>
                    </div>
                </div>

                <!-- Completadas hoy -->
                <div class="stat-card animate-in delay-4">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Completadas Hoy</div>
                            <div class="stat-value"><?php echo $completadas_hoy; ?></div>
                        </div>
                        <div class="stat-icon success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-calendar-day"></i>
                        <span><?php echo date('d/m/Y'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Panel de dos columnas: Órdenes y Pruebas Populares -->
            <div class="row gap-4 mb-4">
                <!-- Órdenes Recientes -->
                <div class="col-lg-8">
                    <section class="appointments-section animate-in delay-1">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="bi bi-list-ul section-title-icon"></i>
                                Órdenes Activas
                            </h3>
                            <div class="d-flex gap-2">
                                <a href="catalogo_pruebas.php" class="action-btn secondary">
                                    <i class="bi bi-gear"></i>
                                    catálogo
                                </a>

                                <?php if ($user_type === 'user' || $user_type === 'admin' || $user_type === 'doc'): ?>
                                        <a href="crear_orden.php" class="action-btn">
                                            <i class="bi bi-plus-lg"></i>
                                            Nueva Orden
                                        </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (count($ordenes_recientes) > 0): ?>
                                <div class="table-responsive">
                                    <table class="orders-table">
                                        <thead>
                                            <tr>
                                                <th>Orden #</th>
                                                <th>Paciente</th>
                                                <th>Doctor</th>
                                                <th>Fecha</th>
                                                <th>Pruebas</th>
                                                <th>Laboratorio</th>
                                                <th>Estado</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($ordenes_recientes as $orden): ?>
                                                    <?php
                                                    $patient_name = htmlspecialchars($orden['nombre'] . ' ' . $orden['apellido']);
                                                    $patient_initials = strtoupper(
                                                        substr($orden['nombre'], 0, 1) .
                                                        substr($orden['apellido'], 0, 1)
                                                    );
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($orden['numero_orden']); ?></strong>
                                                            <br>
                                                            <small class="text-muted">ID: <?php echo $orden['id_orden']; ?></small>
                                                        </td>
                                                        <td>
                                                            <div class="patient-cell">
                                                                <div class="patient-avatar">
                                                                    <?php echo $patient_initials; ?>
                                                                </div>
                                                                <div class="patient-info">
                                                                    <div class="patient-name"><?php echo $patient_name; ?></div>
                                                                    <div class="patient-contact">
                                                                        <?php echo $orden['edad']; ?> años -
                                                                        <?php echo htmlspecialchars($orden['genero']); ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php if ($orden['doctor_nombre']): ?>
                                                                    <small class="d-block">Dr.
                                                                        <?php echo htmlspecialchars($orden['doctor_nombre'] . ' ' . $orden['doctor_apellido']); ?></small>
                                                            <?php else: ?>
                                                                    <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php echo date('d/m/Y', strtotime($orden['fecha_orden'])); ?>
                                                            <br>
                                                            <small
                                                                class="text-muted"><?php echo date('H:i', strtotime($orden['fecha_orden'])); ?></small>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-info"><?php echo $orden['num_pruebas']; ?></span>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($orden['laboratorio_externo'])): ?>
                                                                <span class="badge bg-info-subtle text-info border border-info-subtle">
                                                                    <i class="bi bi-building me-1"></i>
                                                                    <?php echo htmlspecialchars($orden['laboratorio_externo']); ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-muted">—</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $estado_class = '';
                                                            $estado_text = '';
                                                            switch ($orden['estado']) {
                                                                case 'Pendiente':
                                                                    $estado_class = 'pendiente';
                                                                    $estado_text = 'Pendiente';
                                                                    break;
                                                                case 'Muestra_Recibida':
                                                                    $estado_class = 'muestra';
                                                                    $estado_text = 'Muestra Recibida';
                                                                    break;
                                                                case 'En_Proceso':
                                                                    $estado_class = 'proceso';
                                                                    $estado_text = 'En Proceso';
                                                                    break;
                                                                case 'Completada':
                                                                    $estado_class = 'completada';
                                                                    $estado_text = 'Completada';
                                                                    break;
                                                                case 'Validada':
                                                                    $estado_class = 'validada';
                                                                    $estado_text = 'Validada';
                                                                    break;
                                                            }
                                                            ?>
                                                            <span class="status-badge <?php echo $estado_class; ?>">
                                                                <?php echo $estado_text; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="action-buttons">
                                                                <?php if ($orden['estado'] === 'Validada' || $orden['estado'] === 'Completada'): ?>
                                                                        <a href="imprimir_resultados.php?id=<?php echo $orden['id_orden']; ?>"
                                                                            class="btn-icon pdf" title="Ver Resultados PDF" target="_blank">
                                                                            <i class="bi bi-file-earmark-pdf"></i>
                                                                        </a>
                                                                        <!-- Imprimir Ticket -->
                                                                        <a href="print_lab_receipt.php?id=<?php echo $orden['id_examen_realizado']; ?>"
                                                                            class="btn-icon bg-info text-white border-0" title="Imprimir Ticket"
                                                                            target="_blank">
                                                                            <i class="bi bi-receipt"></i>
                                                                        </a>
                                                                        <!-- Botón Devolución -->
                                                                        <button type="button" class="btn-icon bg-danger text-white border-0"
                                                                            title="Devolución"
                                                                            onclick="iniciarDevolucion(<?php echo $orden['id_orden']; ?>)">
                                                                            <i class="bi bi-arrow-return-left"></i>
                                                                        </button>
                                                                <?php else: ?>
                                                                        <a href="procesar_orden.php?id=<?php echo $orden['id_orden']; ?>"
                                                                            class="btn-icon process" title="Procesar orden">
                                                                            <i class="bi bi-pencil-square"></i>
                                                                        </a>
                                                                <?php endif; ?>
                                                                <a href="ver_orden.php?id=<?php echo $orden['id_orden']; ?>"
                                                                    class="btn-icon" title="Ver detalles">
                                                                    <i class="bi bi-eye"></i>
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
                                        <i class="bi bi-inbox"></i>
                                    </div>
                                    <h4 class="text-muted mb-2">No hay órdenes activas</h4>
                                    <p class="text-muted mb-3">Las órdenes pendientes aparecerán aquí</p>
                                    <a href="crear_orden.php" class="action-btn">
                                        <i class="bi bi-plus-lg"></i>
                                        Crear Primera Orden
                                    </a>
                                </div>
                        <?php endif; ?>
                    </section>
                </div>

                <!-- Pruebas Populares y Acciones Rápidas -->
                <div class="col-lg-4">
                    <!-- Pruebas más solicitadas -->
                    <section class="popular-tests animate-in delay-2">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="bi bi-graph-up-arrow section-title-icon"></i>
                                Pruebas del Mes
                            </h3>
                        </div>

                        <?php if (count($pruebas_populares) > 0): ?>
                                <div class="test-list">
                                    <?php foreach ($pruebas_populares as $prueba): ?>
                                            <div class="test-item">
                                                <span class="test-name"><?php echo htmlspecialchars($prueba['nombre_prueba']); ?></span>
                                                <span class="test-count"><?php echo $prueba['cantidad']; ?></span>
                                            </div>
                                    <?php endforeach; ?>
                                </div>
                        <?php else: ?>
                                <div class="empty-state py-3">
                                    <i class="bi bi-bar-chart text-muted"></i>
                                    <p class="text-muted mb-0 mt-2">No hay datos del mes</p>
                                </div>
                        <?php endif; ?>
                    </section>
                </div>
            </div>

            <!-- Resumen del mes -->
            <div class="stat-card animate-in delay-4">
                <div class="stat-header">
                    <div>
                        <div class="stat-title">Resumen del Mes</div>
                        <div class="stat-value"><?php echo $ordenes_mes; ?> Órdenes</div>
                    </div>
                    <div class="stat-icon primary">
                        <i class="bi bi-calendar-month"></i>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-3 text-center">
                        <div class="text-primary fw-bold fs-4"><?php echo $ordenes_pendientes; ?></div>
                        <div class="text-muted">Pendientes</div>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="text-info fw-bold fs-4"><?php echo $muestras_recibidas; ?></div>
                        <div class="text-muted">Muestras</div>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="text-warning fw-bold fs-4"><?php echo $pendientes_validar; ?></div>
                        <div class="text-muted">Por Validar</div>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="text-success fw-bold fs-4"><?php echo $completadas_hoy; ?></div>
                        <div class="text-muted">Hoy</div>
                    </div>
                </div>
                <?php if (!empty($labs_por_mes)): ?>
                    <hr class="my-3">
                    <h6 class="fw-bold text-muted text-uppercase small mb-3">
                        <i class="bi bi-building me-1"></i> Laboratorios realizados este mes
                    </h6>
                    <div class="d-flex gap-3 flex-wrap">
                        <?php foreach ($labs_por_mes as $lab_nombre => $lab_count): ?>
                            <div class="d-flex align-items-center gap-2 px-3 py-2 border rounded bg-light">
                                <i class="bi bi-building text-info"></i>
                                <span class="fw-semibold"><?php echo htmlspecialchars($lab_nombre); ?></span>
                                <span class="badge bg-info rounded-pill"><?php echo $lab_count; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal Devolución Laboratorio -->
    <div class="custom-modal-overlay" id="modalDevolucionLab">
        <div class="custom-modal">
            <div class="custom-modal-header">
                <h5 class="custom-modal-title">
                    <i class="bi bi-arrow-return-left text-danger"></i> Devolución de Orden <span
                        id="lblNumOrdenDev"></span>
                </h5>
                <button type="button" class="custom-modal-close" onclick="cerrarModalDevolucion()">&times;</button>
            </div>
            <div class="custom-modal-body">
                <form id="formDevolucionLab">
                    <input type="hidden" id="dev_id_orden" name="id_orden">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Seleccione Pruebas a Devolver</label>
                        <div id="listaPruebasDevolucion" class="custom-scrollbar"
                            style="max-height: 150px; overflow-y: auto; border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-sm);">
                            <!-- Checkboxes generados dinámicamente -->
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="dev_monto" class="form-label fw-semibold">Monto a Devolver (Q)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="dev_monto" name="monto"
                            required>
                    </div>

                    <div class="mb-3">
                        <label for="dev_motivo" class="form-label fw-semibold">Motivo de Devolución</label>
                        <textarea class="form-control" id="dev_motivo" name="motivo" rows="2" required></textarea>
                    </div>
                </form>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="action-btn secondary" onclick="cerrarModalDevolucion()">Cancelar</button>
                <button type="button" class="action-btn bg-danger text-white border-0"
                    onclick="procesarDevolucionLab()">
                    <i class="bi bi-check-circle"></i> Confirmar Devolución
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript Optimizado -->
    <script>
        // Dashboard de Laboratorio Reingenierizado

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
            // COMPONENTES DINÁMICOS
            // ==========================================================================
            class DynamicComponents {
                constructor() {
                    this.setupAnimations();
                    this.setupTableInteractions();
                    this.setupQuickActions();
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

                    document.querySelectorAll('.stat-card, .appointments-section, .alert-card, .popular-tests').forEach(el => {
                        observer.observe(el);
                    });
                }

                setupTableInteractions() {
                    const tableRows = document.querySelectorAll('.orders-table tbody tr');
                    tableRows.forEach(row => {
                        row.addEventListener('click', (e) => {
                            // Solo si no se hizo clic en un botón de acción
                            if (!e.target.closest('.btn-icon') && !e.target.closest('a')) {
                                const orderId = row.querySelector('td:first-child small')?.textContent?.replace('ID: ', '');
                                if (orderId) {
                                    window.location.href = `ver_orden.php?id=${orderId}`;
                                }
                            }
                        });
                    });
                }

                setupQuickActions() {
                    // Agregar efecto hover a las acciones rápidas
                    const quickActions = document.querySelectorAll('.action-btn.secondary');
                    quickActions.forEach(btn => {
                        btn.addEventListener('mouseenter', () => {
                            btn.style.transform = 'translateY(-2px)';
                        });
                        btn.addEventListener('mouseleave', () => {
                            btn.style.transform = 'translateY(0)';
                        });
                    });
                }
            }

            // ==========================================================================
            // INICIALIZACIÓN DE LA APLICACIÓN
            // ==========================================================================
            document.addEventListener('DOMContentLoaded', () => {
                const themeManager = new ThemeManager();
                const dynamicComponents = new DynamicComponents();

                window.laboratoryDashboard = {
                    theme: themeManager,
                    components: dynamicComponents
                };

                console.log('Dashboard de Laboratorio inicializado');
                console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
                console.log('Tema: ' + themeManager.theme);
            });

            // ==========================================================================
            // POLYFILLS PARA NAVEGADORES ANTIGUOS
            // ==========================================================================
            if (!NodeList.prototype.forEach) {
                NodeList.prototype.forEach = Array.prototype.forEach;
            }

        })();

        // Efectos de carga para formularios
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function (e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Procesando...';
                    submitBtn.disabled = true;

                    setTimeout(() => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }, 3000);
                }
            });
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
        
        /* Efectos adicionales para laboratorio */
        .orders-table tbody tr {
            cursor: pointer;
        }
        
        .orders-table tbody tr:hover {
            background-color: rgba(var(--color-primary-rgb), 0.05);
        }
    `;
        document.head.appendChild(style);

        // --- Lógica Devoluciones ---
        function iniciarDevolucion(id_orden) {
            // Evitar propagación a la fila
            const evt = window.event;
            if (evt) evt.stopPropagation();

            document.getElementById('dev_id_orden').value = id_orden;
            document.getElementById('dev_monto').value = '';
            document.getElementById('dev_motivo').value = '';
            document.getElementById('lblNumOrdenDev').textContent = '#' + id_orden;

            const contPruebas = document.getElementById('listaPruebasDevolucion');
            contPruebas.innerHTML = '<div class="text-center text-muted py-2"><i class="bi bi-arrow-clockwise spin"></i> Cargando pruebas...</div>';
            document.getElementById('modalDevolucionLab').classList.add('active');

            // Cargar pruebas de la orden
            fetch(`api/get_order_details.php?id=${id_orden}`)
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success' && data.pruebas) {
                        contPruebas.innerHTML = '';
                        let totalCalculado = 0;
                        data.pruebas.forEach(p => {
                            // Ignorar las ya devueltas
                            if (p.estado === 'Devuelto') return;

                            totalCalculado += parseFloat(p.precio) || 0;
                            const div = document.createElement('div');
                            div.className = 'form-check mb-1';
                            div.innerHTML = `
                                <input class="form-check-input chk-dev-prueba" type="checkbox" value="${p.id_orden_prueba}" data-precio="${p.precio}" id="chkDev_${p.id_orden_prueba}" checked onchange="recalcularMontoDevolucion()">
                                <label class="form-check-label w-100 d-flex justify-content-between" for="chkDev_${p.id_orden_prueba}" style="cursor:pointer; user-select:none;">
                                    <span>${p.nombre_prueba}</span>
                                    <span class="text-muted">Q${parseFloat(p.precio).toFixed(2)}</span>
                                </label>
                            `;
                            contPruebas.appendChild(div);
                        });

                        if (contPruebas.innerHTML === '') {
                            contPruebas.innerHTML = '<div class="text-muted py-2">No hay pruebas disponibles para devolver en esta orden.</div>';
                            document.getElementById('dev_monto').value = 0;
                        } else {
                            document.getElementById('dev_monto').value = totalCalculado.toFixed(2);
                        }
                    } else {
                        contPruebas.innerHTML = `<div class="text-danger py-2">Error: ${data.message || 'No se pudieron cargar las pruebas'}</div>`;
                    }
                })
                .catch(err => {
                    contPruebas.innerHTML = '<div class="text-danger py-2">Error de conexión al obtener pruebas.</div>';
                });
        }

        function recalcularMontoDevolucion() {
            let total = 0;
            document.querySelectorAll('.chk-dev-prueba:checked').forEach(chk => {
                total += parseFloat(chk.getAttribute('data-precio') || 0);
            });
            document.getElementById('dev_monto').value = total.toFixed(2);
        }

        function cerrarModalDevolucion() {
            document.getElementById('modalDevolucionLab').classList.remove('active');
        }

        function procesarDevolucionLab() {
            const id_orden = document.getElementById('dev_id_orden').value;
            const monto = parseFloat(document.getElementById('dev_monto').value);
            const motivo = document.getElementById('dev_motivo').value.trim();

            const checks = document.querySelectorAll('.chk-dev-prueba:checked');
            const pruebasIds = Array.from(checks).map(chk => chk.value);

            if (pruebasIds.length === 0) {
                Swal.fire("Error", "Debe seleccionar al menos una prueba para devolver.", "error");
                return;
            }

            if (isNaN(monto) || monto <= 0) {
                Swal.fire("Error", "Debe especificar un monto válido a devolver.", "error");
                return;
            }

            if (!motivo) {
                Swal.fire("Error", "Debe detallar un motivo para la devolución.", "error");
                return;
            }

            Swal.fire({
                title: '¿Confirmar Devolución?',
                text: `Se registrará una devolución de Q${monto.toFixed(2)} por las pruebas seleccionadas.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, aplicar devolución',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    cerrarModalDevolucion();
                    Swal.fire({ title: 'Procesando...', text: 'Por favor espere', allowOutsideClick: false, didOpen: () => { Swal.showLoading() } });

                    fetch('api/process_refund.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            id_orden: id_orden,
                            monto: monto,
                            motivo: motivo,
                            pruebas: pruebasIds
                        })
                    })
                        .then(r => r.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire("¡Éxito!", data.message, "success").then(() => {
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire("Error", data.message || "No se pudo procesar la devolución", "error");
                            }
                        })
                        .catch(err => {
                            console.error("Fetch error:", err);
                            Swal.fire("Error", "Ocurrió un error de comunicación con el servidor.", "error");
                        });
                }
            });
        }
    </script>
</body>

</html>