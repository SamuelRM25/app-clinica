<?php
// laboratory/ver_orden.php - Read-only view of a laboratory order
session_start();

// Verificar sesión activa
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';
require_once '../../includes/module_guard.php';

$id_hospital = hospital_id();

date_default_timezone_set('America/Guatemala');
verify_session();

try {
    $database = new Database();
    $conn = $database->getConnection();

    $id_orden = $_GET['id'] ?? null;
    if (!$id_orden) {
        header("Location: index.php");
        exit;
    }

    // Obtener información de la orden y paciente
    $stmt = $conn->prepare("
        SELECT ol.*, p.nombre, p.apellido, p.genero, p.fecha_nacimiento,
               u.nombre as doctor_nombre, u.apellido as doctor_apellido
        FROM ordenes_laboratorio ol
        JOIN pacientes p ON ol.id_paciente = p.id_paciente
        LEFT JOIN usuarios u ON ol.id_doctor = u.idUsuario
        WHERE ol.id_orden = ? AND ol.id_hospital = ?
    ");
    $stmt->execute([$id_orden, $id_hospital]);
    $orden = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$orden) {
        throw new Exception("Orden no encontrada");
    }

    // Obtener pruebas de la orden
    $stmt = $conn->prepare("
        SELECT op.*, cp.nombre_prueba, cp.codigo_prueba, cp.precio
        FROM orden_pruebas op
        JOIN ordenes_laboratorio ol ON op.id_orden = ol.id_orden
        JOIN catalogo_pruebas cp ON op.id_prueba = cp.id_prueba
        WHERE op.id_orden = ? AND ol.id_hospital = ?
    ");
    $stmt->execute([$id_orden, $id_hospital]);
    $pruebas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener resultados si existen
    // Note: resultados_laboratorio links via id_orden_prueba, not id_orden
    $stmt = $conn->prepare("
        SELECT rl.* FROM resultados_laboratorio rl
        INNER JOIN orden_pruebas op ON rl.id_orden_prueba = op.id_orden_prueba
        INNER JOIN ordenes_laboratorio ol ON op.id_orden = ol.id_orden
        WHERE op.id_orden = ? AND ol.id_hospital = ?
    ");
    $stmt->execute([$id_orden, $id_hospital]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get uploaded result files from archivos_resultados_laboratorio
    $stmt_files = $conn->prepare("
        SELECT id_archivo, nombre_archivo, tipo_contenido, categoria, fecha_carga
        FROM archivos_resultados_laboratorio
        WHERE id_orden = ? AND id_hospital = ?
        ORDER BY fecha_carga DESC
    ");
    $stmt_files->execute([$id_orden, $id_hospital]);
    $archivos_resultados = $stmt_files->fetchAll(PDO::FETCH_ASSOC);

    $page_title = "Ver Orden #" . $orden['numero_orden'];

} catch (Exception $e) {
    error_log('Error en laboratory/ver_orden.php: ' . $e->getMessage());
    die("Error: " . 'Error del servidor.');
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="icon" type="image/png" href="../../assets/img/cmhs.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
</head>

<body>
    <div class="dashboard-container">
        <!-- Header Superior -->
        <header class="dashboard-header">
            <div class="header-content">
                <div class="brand-container">
                    <img src="../../assets/img/cmhs.png" alt="logo" class="brand-logo" width="40" height="40">
                </div>
                <div class="header-controls">
                    <a href="index.php" class="action-btn secondary">
                        <i class="bi bi-arrow-left"></i> Volver a Órdenes
                    </a>
                </div>
            </div>
        </header>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1 class="page-title" style="display: flex; align-items: center; gap: 0.5rem;">
                        Orden #<?php echo htmlspecialchars($orden['numero_orden']); ?>
                        <span
                            class="badge <?php echo $orden['estado'] == 'Completada' ? 'bg-success' : 'bg-warning'; ?>"
                            style="font-size: 0.9rem; padding: 0.4rem 0.8rem; border-radius: var(--radius-full);">
                            <?php echo $orden['estado']; ?>
                        </span>
                    </h1>
                    <p class="page-subtitle">Detalles de la orden de laboratorio</p>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-6">
                    <div class="content-section h-100" style="margin:0;">
                        <h3 class="section-title"><i class="bi bi-person section-title-icon text-primary"></i>
                            Información del Paciente</h3>
                        <div class="table-responsive">
                            <table class="table table-borderless">
                                <tbody>
                                    <tr>
                                        <td class="text-muted" style="width: 40%;">Paciente</td>
                                        <td class="fw-bold fs-5 text-dark">
                                            <?php echo htmlspecialchars($orden['nombre'] . ' ' . $orden['apellido']); ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Fecha de Nacimiento</td>
                                        <td class="fw-medium text-dark">
                                            <?php echo date('d/m/Y', strtotime($orden['fecha_nacimiento'])); ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Doctor Solicitante</td>
                                        <td class="fw-medium text-dark">
                                            <?php echo $orden['doctor_nombre'] ? "Dr. {$orden['doctor_nombre']} {$orden['doctor_apellido']}" : 'N/A'; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Fecha de Orden</td>
                                        <td class="fw-medium text-dark">
                                            <?php echo date('d/m/Y H:i', strtotime($orden['fecha_orden'])); ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Laboratorio Externo</td>
                                        <td class="fw-medium">
                                            <?php if (!empty($orden['laboratorio_externo'])): ?>
                                                <span class="badge bg-info-subtle text-info border border-info-subtle" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">
                                                    <i class="bi bi-building me-1"></i>
                                                    <?php echo htmlspecialchars($orden['laboratorio_externo']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted"><em>No especificado</em></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="content-section h-100" style="margin:0;">
                        <h3 class="section-title"><i class="bi bi-list-check section-title-icon text-info"></i> Pruebas
                            Solicitadas</h3>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Prueba</th>
                                        <th>Estado</th>
                                        <th class="text-end">Precio</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $total = 0;
                                    foreach ($pruebas as $prueba):
                                        $precio = isset($prueba['precio']) ? $prueba['precio'] : 0;
                                        $total += $precio;
                                        ?>
                                        <tr>
                                            <td><span
                                                    class="badge bg-light text-dark border"><?php echo htmlspecialchars($prueba['codigo_prueba']); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($prueba['nombre_prueba']); ?></td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $prueba['estado']; ?></span>
                                            </td>
                                            <td class="text-end fw-medium"><?php echo 'Q' . number_format($precio, 2); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr style="background-color: var(--color-bg);">
                                        <td colspan="3" class="text-end fw-bold">TOTAL:</td>
                                        <td class="text-end fw-bold text-primary fs-5">
                                            <?php echo 'Q' . number_format($total, 2); ?>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($orden['observaciones'])): ?>
                <div class="content-section mb-4">
                    <h3 class="section-title"><i class="bi bi-journal-text section-title-icon text-warning"></i>
                        Observaciones</h3>
                    <div class="p-3 bg-light rounded" style="border-left: 4px solid var(--color-warning);">
                        <p class="mb-0 text-dark"><?php echo nl2br(htmlspecialchars($orden['observaciones'])); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($orden['archivo_resultados'])): ?>
                <div class="content-section mb-4">
                    <h3 class="section-title"><i class="bi bi-paperclip section-title-icon text-success"></i> Resultados
                        Adjuntos</h3>

                    <div class="d-flex align-items-center p-3 border rounded mb-3 bg-light">
                        <div class="me-3">
                            <i class="bi bi-file-earmark-pdf" style="font-size: 2.5rem; color: var(--color-danger)"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-bold text-dark fs-5">Archivo de Resultados</div>
                            <div class="text-muted small">Adjunto procesado por el laboratorio</div>
                        </div>
                        <div>
                            <a href="<?php echo htmlspecialchars($orden['archivo_resultados']); ?>" target="_blank"
                                class="action-btn">
                                <i class="bi bi-download me-2"></i> Ver / Descargar
                            </a>
                        </div>
                    </div>

                    <?php
                    $ext = pathinfo($orden['archivo_resultados'], PATHINFO_EXTENSION);
                    if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif'])):
                        ?>
                        <div class="text-center mt-4">
                            <img src="<?php echo htmlspecialchars($orden['archivo_resultados']); ?>" loading="lazy"
                                class="img-fluid rounded shadow-sm border" style="max-height: 500px;">
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($archivos_resultados)): ?>
                <div class="content-section mb-4">
                    <h3 class="section-title"><i class="bi bi-files section-title-icon text-success"></i> Archivos de Resultados</h3>
                    <?php foreach ($archivos_resultados as $archivo): ?>
                        <div class="d-flex align-items-center p-3 border rounded mb-2 bg-light">
                            <div class="me-3">
                                <i class="bi <?php echo in_array(strtolower(pathinfo($archivo['nombre_archivo'], PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif']) ? 'bi-file-earmark-image' : 'bi-file-earmark-pdf'; ?>" style="font-size: 2rem; color: var(--color-info)"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($archivo['nombre_archivo']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($archivo['categoria'] ?? ''); ?> - <?php echo date('d/m/Y H:i', strtotime($archivo['fecha_carga'])); ?></div>
                            </div>
                            <div>
                                <a href="api/get_result_file.php?id=<?php echo $archivo['id_archivo']; ?>" target="_blank" class="action-btn">
                                    <i class="bi bi-eye me-2"></i> Ver
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>

</html>