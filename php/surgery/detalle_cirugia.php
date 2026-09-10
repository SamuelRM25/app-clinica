<?php
// surgery/detalle_cirugia.php - Vista detalle de una cirugía
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit; }
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';
require_once '../../includes/module_guard.php';
require_once '../../includes/breadcrumbs.php';

check_module_access('surgery');
$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);
verify_session();
date_default_timezone_set('America/Guatemala');

$user_type = $_SESSION['tipoUsuario'];
$csrf_token = $_SESSION['csrf_token'] ?? '';
$id_cirugia = (int)($_GET['id'] ?? 0);

if (!$id_cirugia) { header('Location: index.php'); exit; }

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("
        SELECT c.*,
                COALESCE(CONCAT(p.nombre, ' ', p.apellido), CONCAT(c.referido_nombre, ' ', c.referido_apellido)) AS paciente,
                p.dpi, p.fecha_nacimiento, p.genero,
                s.nombre AS sala, s.codigo AS sala_codigo,
                c.cirujano_nombre, c.anestesista_nombre
         FROM cirugias c
         LEFT JOIN pacientes p ON c.id_paciente = p.id_paciente
         LEFT JOIN salas_quirurgicas s ON c.id_sala = s.id_sala
         WHERE c.id_cirugia = ? AND c.id_hospital = ?
    ");
    $stmt->execute([$id_cirugia, $id_hospital]);
    $cirugia = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cirugia) { header('Location: index.php'); exit; }

    // Consumos
    $stmtCons = $conn->prepare("
        SELECT cc.*, inv.nom_medicamento, inv.presentacion_med
        FROM cirugia_consumos cc
        JOIN inventario inv ON cc.id_inventario = inv.id_inventario
        WHERE cc.id_cirugia = ?
        ORDER BY cc.id DESC
    ");
    $stmtCons->execute([$id_cirugia]);
    $consumos = $stmtCons->fetchAll(PDO::FETCH_ASSOC);

    // Descuentos aplicados a esta cirugía
    $stmtDesc = $conn->prepare("
        SELECT id_descuento, concepto, monto, creado_en, cancelado, motivo_cancelacion
        FROM cirugia_descuentos
        WHERE id_cirugia = ? AND id_hospital = ?
        ORDER BY creado_en DESC
    ");
    $stmtDesc->execute([$id_cirugia, $id_hospital]);
    $descuentos = $stmtDesc->fetchAll(PDO::FETCH_ASSOC);

    // Total descuentos activos (no cancelados)
    $total_descuentos = 0.0;
    foreach ($descuentos as $d) {
        if (!$d['cancelado']) $total_descuentos += (float)$d['monto'];
    }

    // Cargos de la cuenta de cirugía
    $cargos_cirugia = [];
    $totales_cargos = [
        'Cirugía' => 0, 'Medicamento' => 0, 'Insumo' => 0,
        'Honorario' => 0, 'Procedimiento' => 0, 'Laboratorio' => 0, 'Otro' => 0
    ];
    if ($cirugia['id_encamamiento']) {
        $stmtCargos = $conn->prepare("
            SELECT ch.id_cargo, ch.tipo_cargo, ch.descripcion, ch.cantidad, ch.precio_unitario,
                   ch.precio_costo, ch.subtotal, ch.fecha_cargo, ch.cancelado, u.nombre AS registrado_nombre, u.apellido AS registrado_apellido
            FROM cargos_hospitalarios ch
            LEFT JOIN usuarios u ON ch.registrado_por = u.idUsuario
            WHERE ch.id_cirugia = ? AND ch.id_hospital = ?
            ORDER BY ch.fecha_cargo DESC, ch.id_cargo DESC
        ");
        $stmtCargos->execute([$id_cirugia, $id_hospital]);
        $cargos_cirugia = $stmtCargos->fetchAll(PDO::FETCH_ASSOC);

        foreach ($cargos_cirugia as $cg) {
            if (!$cg['cancelado']) {
                $tipo = $cg['tipo_cargo'];
                if (isset($totales_cargos[$tipo])) {
                    $totales_cargos[$tipo] += (float)$cg['subtotal'];
                } else {
                    $totales_cargos['Otro'] += (float)$cg['subtotal'];
                }
            }
        }
    }

    // Equipo
    $stmtEq = $conn->prepare("SELECT ce.*, u.nombre, u.apellido, u.especialidad FROM cirugia_equipo ce JOIN usuarios u ON ce.id_usuario = u.idUsuario WHERE ce.id_cirugia = ?");
    $stmtEq->execute([$id_cirugia]);
    $equipo = $stmtEq->fetchAll(PDO::FETCH_ASSOC);

    // Si tiene encamamiento
    $encamamiento = null;
    if ($cirugia['id_encamamiento']) {
        $stmtEnc = $conn->prepare("
            SELECT e.*, h.numero_habitacion, c.numero_cama, ch.id_cuenta
            FROM encamamientos e
            JOIN camas c ON e.id_cama = c.id_cama
            JOIN habitaciones h ON c.id_habitacion = h.id_habitacion
            LEFT JOIN cuenta_hospitalaria ch ON e.id_encamamiento = ch.id_encamamiento AND ch.id_hospital = ?
            WHERE e.id_encamamiento = ?
        ");
        $stmtEnc->execute([$id_hospital, $cirugia['id_encamamiento']]);
        $encamamiento = $stmtEnc->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log('detalle_cirugia.php: ' . $e->getMessage());
    die('Error al cargar la cirugía.');
}

$page_title = "Cirugía #" . $cirugia['numero_cirugia'];
$edad = '';
if ($cirugia['fecha_nacimiento'] && $cirugia['fecha_nacimiento'] !== '1900-01-01') {
    $edad = (new DateTime($cirugia['fecha_nacimiento']))->diff(new DateTime())->y . ' años';
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?></title>
    <meta name="csrf-token" content="<?php echo csrf_token(); ?>">
    <link rel="icon" type="image/png" href="../../assets/img/cmhs.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=optional" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
</head>
<body>
<div class="marble-effect"></div>
<div class="dashboard-container">
    <header class="dashboard-header">
        <div class="header-content">
            <div class="brand-container">
                <img src="../../assets/img/cmhs.png" alt="CMHS" class="brand-logo" width="40" height="40">
                <div>
                    <h2 class="mb-0" style="font-size: 1.25rem;"><?php echo $page_title; ?></h2>
                    <small class="text-muted"><?php echo htmlspecialchars($cirugia['paciente']); ?></small>
                </div>
            </div>
            <div class="header-controls">
                <a href="index.php" class="back-btn"><i class="bi bi-arrow-left"></i><span>Quirófano</span></a>
            </div>
        </div>
    </header>

    <main class="main-content">
        <!-- Header del paciente + acciones -->
        <div class="card shadow-sm border-0 rounded-3 mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">
                            <?php echo htmlspecialchars($cirugia['paciente']); ?>
                            <?php if ($cirugia['tipo_paciente'] === 'Referido'): ?>
                                <span class="badge bg-warning text-dark">Referido</span>
                            <?php endif; ?>
                        </h4>
                        <p class="mb-1 text-muted">
                            <?php echo $edad ? $edad . ' · ' : ''; ?>
                            <?php echo htmlspecialchars($cirugia['genero'] ?? ''); ?>
                            · DPI: <?php echo htmlspecialchars($cirugia['dpi'] ?? '—'); ?>
                        </p>
                        <p class="mb-0">
                            <span class="badge 
                                <?php echo ['Programada' => 'bg-secondary', 'En_Curso' => 'bg-danger', 'Finalizada' => 'bg-success', 'Cancelada' => 'bg-dark'][$cirugia['estado']]; ?>">
                                <?php echo $cirugia['estado']; ?>
                            </span>
                            · Sala: <?php echo htmlspecialchars($cirugia['sala'] ?? '—'); ?>
                        </p>
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-start">
                        <?php if ($cirugia['estado'] === 'Programada'): ?>
                            <button class="btn btn-danger" onclick="cambiarEstado('En_Curso')">
                                <i class="bi bi-play-fill"></i> Iniciar Cirugía
                            </button>
                            <button class="btn btn-outline-dark" onclick="cambiarEstado('Cancelada')">
                                <i class="bi bi-x-circle"></i> Cancelar
                            </button>
                        <?php elseif ($cirugia['estado'] === 'En_Curso'): ?>
                            <button class="btn btn-primary" onclick="openCargoCirugiaModal()">
                                <i class="bi bi-receipt"></i> Agregar Cargos
                            </button>
                            <button class="btn btn-info" onclick="previewAsignacion()">
                                <i class="bi bi-eye"></i> Ver Asignación
                            </button>
                            <button class="btn btn-success" onclick="finalizarCirugia()">
                                <i class="bi bi-check-circle"></i> Finalizar Cirugía
                            </button>
                        <?php endif; ?>
                        <?php if ($encamamiento): ?>
                            <a href="../hospitalization/detalle_encamamiento.php?id=<?php echo (int)$encamamiento['id_encamamiento']; ?>" class="btn btn-outline-info">
                                <i class="bi bi-hospital"></i> Ver Encamamiento
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel Vista Previa de Asignación (oculto, se muestra con el botón) -->
        <div class="card shadow-sm border-0 rounded-3 mb-3 d-none" id="preview-asignacion-card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-eye me-2"></i>Vista Previa — Asignación post-operatoria</h5>
            </div>
            <div class="card-body" id="preview-asignacion-body">
                <div class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm me-2"></div>Cargando...</div>
            </div>
        </div>

        <div class="row g-3">
            <!-- Detalles -->
            <div class="col-md-6">
                <div class="card shadow-sm border-0 rounded-3 h-100">
                    <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-clipboard-data me-2"></i>Detalles</h5></div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5">Fecha Programada:</dt><dd class="col-sm-7"><?php echo $cirugia['fecha_programada'] ? date('d/m/Y H:i', strtotime($cirugia['fecha_programada'])) : '—'; ?></dd>
                            <dt class="col-sm-5">Inicio:</dt><dd class="col-sm-7"><?php echo $cirugia['fecha_inicio'] ? date('d/m/Y H:i', strtotime($cirugia['fecha_inicio'])) : '—'; ?></dd>
                            <dt class="col-sm-5">Fin:</dt><dd class="col-sm-7"><?php echo $cirugia['fecha_fin'] ? date('d/m/Y H:i', strtotime($cirugia['fecha_fin'])) : '—'; ?></dd>
                            <dt class="col-sm-5">Cirujano:</dt><dd class="col-sm-7"><?php echo htmlspecialchars($cirugia['cirujano_nombre'] ?? '—'); ?></dd>
                            <dt class="col-sm-5">Anestesista:</dt><dd class="col-sm-7"><?php echo htmlspecialchars($cirugia['anestesista_nombre'] ?? '—'); ?></dd>
                            <dt class="col-sm-5">Cargo Total:</dt>
                            <dd class="col-sm-7 fw-bold text-primary fs-5 d-flex align-items-center gap-2">
                                <span id="cargo-total-display">Q<?php echo number_format($cirugia['cargo_total'], 2); ?></span>
                                <?php if (in_array($cirugia['estado'], ['Programada', 'En_Curso'], true)): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="editCargoTotal()" title="Editar cargo total">
                                        <i class="bi bi-pencil"></i> Editar
                                    </button>
                                <?php endif; ?>
                            </dd>
                        </dl>
                        <?php if ($cirugia['procedimiento']): ?>
                        <hr>
                        <h6 class="text-muted small text-uppercase">Procedimiento</h6>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($cirugia['procedimiento'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Equipo -->
            <div class="col-md-6">
                <div class="card shadow-sm border-0 rounded-3 h-100">
                    <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-people me-2"></i>Equipo Quirúrgico</h5></div>
                    <div class="card-body">
                        <?php if (empty($equipo)): ?>
                            <p class="text-muted text-center my-3">Sin equipo registrado</p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($equipo as $m): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <div>
                                            <strong><?php echo htmlspecialchars($m['nombre'] . ' ' . $m['apellido']); ?></strong>
                                            <small class="text-muted d-block"><?php echo htmlspecialchars($m['especialidad'] ?? ''); ?></small>
                                        </div>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($m['rol']); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Consumos -->
            <div class="col-12">
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-capsule me-2"></i>Medicamentos Consumidos</h5>
                        <?php if (in_array($cirugia['estado'], ['Programada', 'En_Curso'], true)): ?>
                            <button class="btn btn-sm btn-primary" onclick="openConsumoModal()">
                                <i class="bi bi-plus"></i> Agregar
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($consumos)): ?>
                            <div class="text-center text-muted p-4">
                                <i class="bi bi-bandaid" style="font-size: 2rem;"></i>
                                <p class="mt-2 mb-0">No se han consumido medicamentos.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="data-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Medicamento</th>
                                            <th>Presentación</th>
                                            <th class="text-end">Cantidad</th>
                                            <th class="text-end">Precio Unit.</th>
                                            <th class="text-end">Costo</th>
                                            <th class="text-end">Subtotal</th>
                                            <?php if (in_array($cirugia['estado'], ['Programada', 'En_Curso'], true)): ?>
                                                <th class="text-center">Acción</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($consumos as $c): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($c['nom_medicamento']); ?></td>
                                                <td><?php echo htmlspecialchars($c['presentacion_med'] ?? '—'); ?></td>
                                                <td class="text-end"><?php echo number_format($c['cantidad'], 2); ?></td>
                                                <td class="text-end">Q<?php echo number_format($c['precio_unitario'], 2); ?></td>
                                                <td class="text-end">Q<?php echo number_format($c['precio_costo'] ?? 0, 2); ?></td>
                                                <td class="text-end fw-bold">Q<?php echo number_format($c['subtotal'], 2); ?></td>
                                                <?php if (in_array($cirugia['estado'], ['Programada', 'En_Curso'], true)): ?>
                                                    <td class="text-center">
                                                        <button class="btn btn-sm btn-outline-danger"
                                                                onclick="eliminarConsumo(<?php echo (int)$c['id']; ?>, '<?php echo htmlspecialchars(addslashes($c['nom_medicamento'])); ?>', <?php echo (float)$c['cantidad']; ?>)"
                                                                title="Retornar al inventario de Quirófano">
                                                            <i class="bi bi-arrow-counterclockwise"></i>
                                                        </button>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-light">
                                            <td colspan="<?php echo in_array($cirugia['estado'], ['Programada', 'En_Curso'], true) ? '6' : '5'; ?>" class="text-end fw-bold">Total Consumos:</td>
                                            <td class="text-end fw-bold text-primary">Q<?php echo number_format(array_sum(array_column($consumos, 'subtotal')), 2); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Descuentos -->
            <div class="col-12">
                <div class="card shadow-sm border-0 rounded-3 border-start border-success border-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-percent me-2 text-success"></i>Descuentos Aplicados</h5>
                        <?php if (in_array($cirugia['estado'], ['Programada', 'En_Curso', 'Finalizada'], true)): ?>
                            <button class="btn btn-sm btn-success" onclick="openDescuentoModal()">
                                <i class="bi bi-plus"></i> Agregar Descuento
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($descuentos)): ?>
                            <div class="text-center text-muted p-4">
                                <i class="bi bi-percent" style="font-size: 2rem;"></i>
                                <p class="mt-2 mb-0">No se han aplicado descuentos a esta cirugía.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="data-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Concepto</th>
                                            <th class="text-end">Monto</th>
                                            <th class="text-center">Aplicado</th>
                                            <?php if (in_array($cirugia['estado'], ['Programada', 'En_Curso', 'Finalizada'], true)): ?>
                                                <th class="text-center">Acción</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($descuentos as $d): ?>
                                            <tr class="<?= $d['cancelado'] ? 'text-decoration-line-through text-muted' : '' ?>">
                                                <td><?php echo htmlspecialchars($d['concepto']); ?></td>
                                                <td class="text-end fw-bold text-success">-Q<?php echo number_format((float)$d['monto'], 2); ?></td>
                                                <td class="text-center"><small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($d['creado_en'])); ?></small></td>
                                                <?php if (in_array($cirugia['estado'], ['Programada', 'En_Curso', 'Finalizada'], true)): ?>
                                                    <td class="text-center">
                                                        <?php if (!$d['cancelado']): ?>
                                                        <button class="btn btn-sm btn-outline-danger"
                                                                onclick="eliminarDescuento(<?= (int)$d['id_descuento']; ?>, '<?= htmlspecialchars(addslashes($d['concepto'])); ?>', <?= (float)$d['monto']; ?>)"
                                                                title="Eliminar descuento">
                                                            <i class="bi bi-x-circle"></i>
                                                        </button>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Cancelado</span>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-success">
                                            <td class="fw-bold">Total Descuentos:</td>
                                            <td class="text-end fw-bold text-success">-Q<?php echo number_format($total_descuentos, 2); ?></td>
                                            <td colspan="<?= in_array($cirugia['estado'], ['Programada', 'En_Curso', 'Finalizada'], true) ? '2' : '1' ?>"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Cuenta de Cirugía: cargos -->
            <div class="col-12">
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-receipt me-2 text-primary"></i>Cuenta de Cirugía</h5>
                        <?php if (in_array($cirugia['estado'], ['Programada', 'En_Curso'], true) && $cirugia['id_encamamiento']): ?>
                            <button class="btn btn-sm btn-primary" onclick="openCargoCirugiaModal()">
                                <i class="bi bi-plus"></i> Agregar Cargos
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (!$cirugia['id_encamamiento']): ?>
                            <div class="alert alert-info border-0 mb-0">
                                <i class="bi bi-info-circle me-2"></i>
                                La cuenta de cirugía se crea automáticamente al <strong>Iniciar la cirugía</strong>.
                            </div>
                        <?php else: ?>
                            <div class="row g-2 mb-3">
                                <div class="col-md-2 col-6">
                                    <div class="border rounded p-2 text-center bg-light">
                                        <small class="text-muted d-block text-uppercase">Cirugía</small>
                                        <strong class="text-primary">Q<?= number_format($totales_cargos['Cirugía'], 2) ?></strong>
                                    </div>
                                </div>
                                <div class="col-md-2 col-6">
                                    <div class="border rounded p-2 text-center bg-light">
                                        <small class="text-muted d-block text-uppercase">Medicamentos</small>
                                        <strong class="text-info">Q<?= number_format($totales_cargos['Medicamento'], 2) ?></strong>
                                    </div>
                                </div>
                                <div class="col-md-2 col-6">
                                    <div class="border rounded p-2 text-center bg-light">
                                        <small class="text-muted d-block text-uppercase">Insumos</small>
                                        <strong class="text-secondary">Q<?= number_format($totales_cargos['Insumo'], 2) ?></strong>
                                    </div>
                                </div>
                                <div class="col-md-2 col-6">
                                    <div class="border rounded p-2 text-center bg-light">
                                        <small class="text-muted d-block text-uppercase">Honorarios</small>
                                        <strong class="text-warning">Q<?= number_format($totales_cargos['Honorario'], 2) ?></strong>
                                    </div>
                                </div>
                                <div class="col-md-2 col-6">
                                    <div class="border rounded p-2 text-center bg-light">
                                        <small class="text-muted d-block text-uppercase">Procedimientos</small>
                                        <strong>Q<?= number_format($totales_cargos['Procedimiento'], 2) ?></strong>
                                    </div>
                                </div>
                                <div class="col-md-2 col-6">
                                    <div class="border rounded p-2 text-center bg-light">
                                        <small class="text-muted d-block text-uppercase">Otros</small>
                                        <strong class="text-muted">Q<?= number_format($totales_cargos['Otro'] + $totales_cargos['Laboratorio'], 2) ?></strong>
                                    </div>
                                </div>
                            </div>

                            <?php if (empty($cargos_cirugia)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                    <p class="mt-2 mb-0">No hay cargos registrados aún.</p>
                                    <?php if (in_array($cirugia['estado'], ['Programada', 'En_Curso'], true)): ?>
                                        <small>Use el botón <strong>Agregar Cargos</strong> para empezar.</small>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="data-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Tipo</th>
                                                <th>Descripción</th>
                                                <th class="text-end">Cant.</th>
                                                <th class="text-end">Precio Unit.</th>
                                                <th class="text-end">Costo</th>
                                                <th class="text-end">Subtotal</th>
                                                <?php if (in_array($cirugia['estado'], ['Programada', 'En_Curso'], true)): ?>
                                                    <th class="text-center">Acciones</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($cargos_cirugia as $cg): ?>
                                                <tr class="<?= $cg['cancelado'] ? 'text-decoration-line-through text-muted' : '' ?>">
                                                    <td><?= date('d/m/Y H:i', strtotime($cg['fecha_cargo'])) ?></td>
                                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($cg['tipo_cargo']) ?></span></td>
                                                    <td><?= htmlspecialchars($cg['descripcion']) ?>
                                                        <?php if ($cg['cancelado']): ?>
                                                            <small class="text-danger d-block">Cancelado</small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-end"><?= number_format($cg['cantidad'], 2) ?></td>
                                                    <td class="text-end">Q<?= number_format($cg['precio_unitario'], 2) ?></td>
                                                    <td class="text-end">Q<?= number_format($cg['precio_costo'] ?? 0, 2) ?></td>
                                                    <td class="text-end fw-bold">Q<?= number_format($cg['subtotal'], 2) ?></td>
                                                    <?php if (in_array($cirugia['estado'], ['Programada', 'En_Curso'], true)): ?>
                                                        <td class="text-center">
                                                            <?php if (!$cg['cancelado']): ?>
                                                                <button class="btn btn-sm btn-outline-primary" onclick='editCargoCirugia(<?= json_encode($cg, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Editar">
                                                                    <i class="bi bi-pencil"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteCargoCirugia(<?= (int)$cg['id_cargo'] ?>)" title="Eliminar">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php
                                            $subtotal_visible = 0;
                                            foreach ($cargos_cirugia as $cg) {
                                                if (!$cg['cancelado']) $subtotal_visible += (float)$cg['subtotal'];
                                            }
                                            ?>
                                            <tr class="table-light">
                                                <td colspan="<?= in_array($cirugia['estado'], ['Programada', 'En_Curso'], true) ? '6' : '5' ?>" class="text-end fw-bold">Total Cuenta:</td>
                                                <td class="text-end fw-bold text-primary">Q<?= number_format($subtotal_visible, 2) ?></td>
                                                <?php if (in_array($cirugia['estado'], ['Programada', 'En_Curso'], true)): ?>
                                                    <td></td>
                                                <?php endif; ?>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal Agregar Descuento -->
<div class="modal fade" id="descuentoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-percent me-2"></i>Agregar Descuento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="descuentoForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="id_cirugia" value="<?= $id_cirugia ?>">
                    <div class="mb-3">
                        <label class="form-label">Concepto del Descuento *</label>
                        <input type="text" class="form-control" name="concepto" id="desc-concepto" required maxlength="255" placeholder="Ej: Descuento por convenio, promoción...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Monto del Descuento (Q) *</label>
                        <input type="number" step="0.01" min="0.01" class="form-control form-control-lg" name="monto" id="desc-monto" required>
                        <small class="text-muted">Este monto se restará del total de la cirugía al momento de aplicar cargos al paciente.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Aplicar Descuento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Agregar Medicamento -->
<div class="modal fade" id="consumoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Agregar Medicamento Usado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="consumoForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="id_cirugia" value="<?php echo $id_cirugia; ?>">
                    <div class="mb-3">
                        <label class="form-label">Buscar Medicamento *</label>
                        <input type="text" id="search-med" class="form-control" placeholder="Escriba nombre o código..." autocomplete="off">
                        <div id="med-results" class="list-group mt-1" style="max-height: 200px; overflow-y: auto;"></div>
                        <input type="hidden" name="id_inventario" id="id_inventario">
                        <div id="med-seleccionado" class="alert alert-success mt-2 d-none"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cantidad *</label>
                        <input type="number" step="0.01" min="0.01" class="form-control" name="cantidad" id="cantidad" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Precio Unitario (Q) *</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="precio_unitario" id="consumo-precio" placeholder="0.00">
                        <div class="form-text">Ingrese el precio manualmente. Si lo deja en 0, se tomará el precio del inventario de quirófano.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Costo (Q)</label>
                        <div class="form-control bg-light" id="consumo-costo">—</div>
                        <div class="form-text text-muted">Precio de compra del medicamento (referencia).</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Agregar y Descontar Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>window.ES_CREADOR = <?php echo isset($_SESSION['es_creador']) && $_SESSION['es_creador'] ? 'true' : 'false'; ?>;</script>
<script defer src="../../assets/js/security.js"></script>
<script>
const idCirugia = <?php echo $id_cirugia; ?>;
const csrf = <?php echo json_encode($csrf_token); ?>;
let consumoModal;

document.addEventListener('DOMContentLoaded', () => {
    consumoModal = new bootstrap.Modal(document.getElementById('consumoModal'));
    document.getElementById('consumoForm').addEventListener('submit', saveConsumo);

    const searchMed = document.getElementById('search-med');
    const medResults = document.getElementById('med-results');
    let timer;
    searchMed.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            const q = searchMed.value.trim();
            if (q.length < 1) { medResults.innerHTML = ''; return; }
            try {
                const res = await fetch('api/search_meds_quirofano.php?q=' + encodeURIComponent(q));
                const json = await res.json();
                if (json.success && json.data.length) {
                    medResults.innerHTML = json.data.map(m =>
                        `<a href="javascript:void(0)" class="list-group-item list-group-item-action" data-id="${m.id_inventario}" data-name="${m.nom_medicamento}" data-stock="${m.stock_quirofano}"
                            data-precio="${m.precio_quirofano || m.precio_hospital || m.precio_venta || 0}" data-costo="${m.precio_compra || 0}">
                            <strong>${m.nom_medicamento}</strong> · Stock: ${m.stock_quirofano}
                            <div class="small text-muted">Precio Q${parseFloat(m.precio_quirofano || m.precio_hospital || m.precio_venta || 0).toFixed(2)} · Costo Q${parseFloat(m.precio_compra || 0).toFixed(2)}</div>
                        </a>`
                    ).join('');
                    medResults.querySelectorAll('a').forEach(a => {
                        a.addEventListener('click', () => {
                            document.getElementById('id_inventario').value = a.dataset.id;
                            document.getElementById('cantidad').max = a.dataset.stock;
                            document.getElementById('consumo-precio').value = a.dataset.precio;
                            document.getElementById('consumo-costo').textContent = 'Q' + parseFloat(a.dataset.costo).toFixed(2);
                            const div = document.getElementById('med-seleccionado');
                            div.textContent = '✓ ' + a.dataset.name + ' (Stock disponible: ' + a.dataset.stock + ')';
                            div.classList.remove('d-none');
                            medResults.innerHTML = '';
                            searchMed.value = a.dataset.name;
                        });
                    });
                } else {
                    medResults.innerHTML = '<div class="list-group-item text-muted">Sin resultados o sin stock</div>';
                }
            } catch (e) { medResults.innerHTML = '<div class="list-group-item text-danger">Error</div>'; }
        }, 300);
    });
});

function openConsumoModal() {
    document.getElementById('consumoForm').reset();
    document.getElementById('med-results').innerHTML = '';
    document.getElementById('med-seleccionado').classList.add('d-none');
    document.getElementById('consumo-precio').value = '';
    document.getElementById('consumo-costo').textContent = '—';
    consumoModal.show();
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
}

async function saveConsumo(e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    try {
        const res = await fetch('api/add_consumo_cirugia.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            Swal.fire('Agregado', json.message, 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    } catch (e) { Swal.fire('Error', 'Fallo de red', 'error'); }
}

async function cambiarEstado(estado) {
    const txt = estado === 'En_Curso' ? 'iniciar' : 'cancelar';
    const r = await Swal.fire({
        title: '¿' + txt.charAt(0).toUpperCase() + txt.slice(1) + ' cirugía?',
        icon: 'question', showCancelButton: true,
        confirmButtonText: 'Sí, ' + txt, cancelButtonText: 'No'
    });
    if (!r.isConfirmed) return;
    const fd = new FormData();
    fd.append('id_cirugia', idCirugia);
    fd.append('estado', estado);
    fd.append('csrf_token', csrf);
    const res = await fetch('api/cambiar_estado_cirugia.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.success) {
        Swal.fire('OK', json.message, 'success').then(() => location.reload());
    } else { Swal.fire('Error', json.message, 'error'); }
}

async function editCargoTotal() {
    const currentText = document.getElementById('cargo-total-display').textContent.replace(/[^\d.]/g, '');
    const currentVal = parseFloat(currentText) || 0;
    const { value: newVal } = await Swal.fire({
        title: 'Editar Cargo Total',
        html: `<p class="small text-muted mb-2">Valor actual: <strong>Q${currentVal.toFixed(2)}</strong></p>
               <input id="swal-cargo" type="number" step="0.01" min="0" class="form-control form-control-lg" value="${currentVal.toFixed(2)}">
               <input id="swal-motivo" type="text" maxlength="255" class="form-control mt-2" placeholder="Motivo (opcional)">`,
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const v = parseFloat(document.getElementById('swal-cargo').value);
            const m = document.getElementById('swal-motivo').value.trim();
            if (isNaN(v) || v < 0) {
                Swal.showValidationMessage('Ingrese un valor válido (≥ 0)');
                return false;
            }
            return { value: v, motivo: m };
        }
    });
    if (!newVal) return;
    const fd = new FormData();
    fd.append('id_cirugia', idCirugia);
    fd.append('cargo_total', newVal.value);
    fd.append('motivo', newVal.motivo);
    fd.append('csrf_token', csrf);
    try {
        const res = await fetch('api/update_cargo_total.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            document.getElementById('cargo-total-display').textContent = 'Q' + json.new_cargo.toFixed(2);
            Swal.fire('Actualizado', json.message, 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    } catch (err) { Swal.fire('Error', 'Fallo de red', 'error'); }
}

async function cargarComboCirugia(forzar = false) {
    // DEPRECATED: ya no se usa — los cargos se agregan manualmente via openCargoCirugiaModal
    Swal.fire('Información', 'Use el botón "Agregar Cargos" para cargar medicamentos.', 'info');
}

// --- CUENTA DE CIRUGÍA: Agregar/Editar/Eliminar cargos ---
async function openCargoCirugiaModal() {
    const { value: formValues } = await Swal.fire({
        title: 'Agregar Cargos a la Cirugía',
        html: `
        <div class="text-start mb-2">
            <p class="text-muted small">Agregue uno o más cargos a la cuenta de la cirugía.</p>
        </div>
        <div class="table-responsive">
            <table class="table table-sm" id="batchCargoCirugiaTable">
                <thead>
                    <tr>
                        <th style="width: 18%">Tipo</th>
                        <th style="width: 34%">Descripción</th>
                        <th style="width: 10%">Cant.</th>
                        <th style="width: 14%">Precio</th>
                        <th style="width: 16%">Costo</th>
                        <th style="width: 8%"></th>
                    </tr>
                </thead>
                <tbody id="cargoCirugiaRows">
                    <tr>
                        <td>
                            <input list="tipos-cargo-cirugia-list" type="text" class="form-control form-control-sm cargo-tipo" name="tipo_cargo[]" required maxlength="50" placeholder="Tipo..." autocomplete="off">
                        </td>
                        <td>
                            <div class="desc-container" style="position:relative;">
                                <input type="text" class="form-control form-control-sm cargo-desc" name="descripcion[]" required placeholder="Descripción del cargo">
                                <div class="search-results-inline" style="display:none; position:absolute; z-index:1000; background:white; border:1px solid #ddd; max-height:200px; overflow-y:auto; width:100%; box-shadow:0 2px 4px rgba(0,0,0,0.1);"></div>
                                <input type="hidden" class="cargo-id-inventario" name="id_inventario[]">
                            </div>
                        </td>
                        <td><input type="number" step="0.01" class="form-control form-control-sm cargo-cantidad" name="cantidad[]" value="1" min="0.01" required></td>
                        <td><input type="number" step="0.01" class="form-control form-control-sm cargo-precio" name="precio_unitario[]" min="0" required></td>
                        <td><input type="number" step="0.01" class="form-control form-control-sm cargo-costo" name="precio_costo[]" min="0" value="0.00"></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <datalist id="tipos-cargo-cirugia-list">
            <option value="Cirugía">
            <option value="Medicamento">
            <option value="Insumo">
            <option value="Honorario">
            <option value="Procedimiento">
            <option value="Laboratorio">
            <option value="Otro">
        </datalist>
        <div class="text-start mt-2">
            <button type="button" class="btn btn-sm btn-outline-primary" id="addCargoCirugiaRowBtn">
                <i class="bi bi-plus-lg"></i> Agregar otra fila
            </button>
        </div>
        `,
        width: 900,
        showCancelButton: true,
        confirmButtonText: 'Guardar Cargos',
        cancelButtonText: 'Cancelar',
        didOpen: () => {
            setupCargoCirugiaRows();
            document.getElementById('addCargoCirugiaRowBtn').addEventListener('click', addCargoCirugiaRow);
        },
        preConfirm: () => {
            const rows = document.querySelectorAll('#cargoCirugiaRows tr');
            const cargos = [];

            rows.forEach(row => {
                const tipo = row.querySelector('[name="tipo_cargo[]"]').value.trim();
                const desc = row.querySelector('[name="descripcion[]"]').value.trim();
                const cant = parseFloat(row.querySelector('[name="cantidad[]"]').value) || 0;
                const price = parseFloat(row.querySelector('[name="precio_unitario[]"]').value) || 0;
                const costo = parseFloat(row.querySelector('[name="precio_costo[]"]').value) || 0;
                const idInv = parseInt(row.querySelector('.cargo-id-inventario').value) || 0;

                if (tipo && desc && cant > 0 && price >= 0) {
                    cargos.push({
                        id_cirugia: idCirugia,
                        tipo_cargo: tipo,
                        descripcion: desc,
                        cantidad: cant,
                        precio_unitario: price,
                        precio_costo: costo,
                        id_inventario: idInv || null
                    });
                }
            });

            if (cargos.length === 0) {
                Swal.showValidationMessage('Debe agregar al menos un cargo válido');
                return false;
            }

            const formData = new FormData();
            cargos.forEach((cargo, index) => {
                formData.append(`cargos[${index}][id_cirugia]`, cargo.id_cirugia);
                formData.append(`cargos[${index}][tipo_cargo]`, cargo.tipo_cargo);
                formData.append(`cargos[${index}][descripcion]`, cargo.descripcion);
                formData.append(`cargos[${index}][cantidad]`, cargo.cantidad);
                formData.append(`cargos[${index}][precio_unitario]`, cargo.precio_unitario);
                formData.append(`cargos[${index}][precio_costo]`, cargo.precio_costo);
                if (cargo.id_inventario) formData.append(`cargos[${index}][id_inventario]`, cargo.id_inventario);
            });
            formData.append('csrf_token', csrf);

            return fetch('api/add_cargo_cirugia.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) throw new Error(data.message);
                    return data;
                })
                .catch(error => {
                    Swal.showValidationMessage(error.message || 'Error del servidor');
                });
        }
    });

    if (formValues && formValues.success) {
        Swal.fire('¡Éxito!', formValues.message, 'success').then(() => location.reload());
    }
}

function setupCargoCirugiaRows() {
    document.querySelectorAll('#cargoCirugiaRows tr').forEach(row => setupCargoCirugiaRow(row));
}

function setupCargoCirugiaRow(row) {
    const tipoSelect = row.querySelector('.cargo-tipo');
    const descInput = row.querySelector('.cargo-desc');
    const precioInput = row.querySelector('.cargo-precio');
    const costoInput = row.querySelector('.cargo-costo');
    const cantidadInput = row.querySelector('.cargo-cantidad');
    const resultsDiv = row.querySelector('.search-results-inline');

    tipoSelect.addEventListener('change', function () {
        if (this.value === 'Medicamento' || this.value === 'Insumo') {
            descInput.placeholder = 'Buscar ' + this.value.toLowerCase() + '...';
            descInput.value = '';
            precioInput.value = '';
            if (costoInput) costoInput.value = 0;
            row.querySelector('.cargo-id-inventario').value = '';
        } else {
            descInput.placeholder = 'Descripción del cargo';
            resultsDiv.style.display = 'none';
            row.querySelector('.cargo-id-inventario').value = '';
        }
    });

    let timer;
    descInput.addEventListener('input', function () {
        clearTimeout(timer);
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

        timer = setTimeout(() => {
            fetch(`api/search_meds_quirofano.php?q=${encodeURIComponent(term)}`)
                .then(res => res.json())
                .then(data => {
                    const items = (data.success ? data.data : data) || [];
                    if (items.length === 0) {
                        resultsDiv.innerHTML = '<div class="p-2 text-muted small">No se encontraron resultados</div>';
                        resultsDiv.style.display = 'block';
                        return;
                    }
                    let html = '';
                    items.forEach(med => {
                        const precio = med.precio_quirofano || med.precio_hospital || med.precio_venta || 0;
                        html += `
                            <div class="search-result-item p-2" style="cursor:pointer; border-bottom:1px solid #eee;"
                                 data-name="${(med.nom_medicamento || '').replace(/"/g, '&quot;')}"
                                 data-precio="${parseFloat(precio).toFixed(2)}"
                                 data-costo="${med.precio_compra || 0}"
                                 data-id="${med.id_inventario}">
                                <div class="fw-bold small">${escapeHtml(med.nom_medicamento)}</div>
                                <div class="text-muted" style="font-size:0.75rem;">${escapeHtml(med.presentacion_med || '')}</div>
                                <div class="d-flex justify-content-between" style="font-size:0.75rem;">
                                    <span class="text-info">Quirófano: ${med.stock_quirofano || 0}</span>
                                    <span class="fw-bold">Q${parseFloat(precio).toFixed(2)}</span>
                                    <span class="text-muted">Costo: Q${parseFloat(med.precio_compra || 0).toFixed(2)}</span>
                                </div>
                            </div>
                        `;
                    });
                    resultsDiv.innerHTML = html;
                    resultsDiv.style.display = 'block';

                    resultsDiv.querySelectorAll('.search-result-item').forEach(item => {
                        item.addEventListener('click', function () {
                            const name = this.getAttribute('data-name');
                            const precio = this.getAttribute('data-precio');
                            const costo = this.getAttribute('data-costo');
                            const idInv = this.getAttribute('data-id');

                            descInput.value = name;
                            precioInput.value = precio;
                            if (costoInput) costoInput.value = costo || 0;
                            row.querySelector('.cargo-id-inventario').value = idInv;
                            resultsDiv.style.display = 'none';
                        });
                        item.addEventListener('mouseenter', function () { this.style.backgroundColor = '#f8f9fa'; });
                        item.addEventListener('mouseleave', function () { this.style.backgroundColor = 'white'; });
                    });
                })
                .catch(err => console.error('search error:', err));
        }, 300);
    });

    descInput.addEventListener('blur', function () {
        setTimeout(() => { resultsDiv.style.display = 'none'; }, 200);
    });
}

function addCargoCirugiaRow() {
    const tbody = document.getElementById('cargoCirugiaRows');
    const newRow = document.createElement('tr');
    newRow.innerHTML = `
        <td>
            <input list="tipos-cargo-cirugia-list" type="text" class="form-control form-control-sm cargo-tipo" name="tipo_cargo[]" required maxlength="50" placeholder="Tipo..." autocomplete="off">
        </td>
        <td>
            <div class="desc-container" style="position:relative;">
                <input type="text" class="form-control form-control-sm cargo-desc" name="descripcion[]" required placeholder="Descripción del cargo">
                <div class="search-results-inline" style="display:none; position:absolute; z-index:1000; background:white; border:1px solid #ddd; max-height:200px; overflow-y:auto; width:100%; box-shadow:0 2px 4px rgba(0,0,0,0.1);"></div>
                <input type="hidden" class="cargo-id-inventario" name="id_inventario[]">
            </div>
        </td>
        <td><input type="number" step="0.01" class="form-control form-control-sm cargo-cantidad" name="cantidad[]" value="1" min="0.01" required></td>
        <td><input type="number" step="0.01" class="form-control form-control-sm cargo-precio" name="precio_unitario[]" min="0" required></td>
        <td><input type="number" step="0.01" class="form-control form-control-sm cargo-costo" name="precio_costo[]" min="0" value="0.00"></td>
        <td>
            <button type="button" class="btn btn-link text-danger p-0 btn-remove-row">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(newRow);
    setupCargoCirugiaRow(newRow);
    newRow.querySelector('.btn-remove-row').addEventListener('click', () => newRow.remove());
}

async function editCargoCirugia(cargo) {
    const { value: formValues } = await Swal.fire({
        title: 'Editar Cargo',
        html: `
            <div class="text-start">
                <label class="form-label fw-bold">Tipo</label>
                <input id="edit-tipo" type="text" class="form-control" value="${escapeHtml(cargo.tipo_cargo)}" disabled>
                <label class="form-label fw-bold mt-2">Descripción</label>
                <input id="edit-desc" type="text" class="form-control" value="${escapeHtml(cargo.descripcion)}" maxlength="500">
                <div class="row mt-2">
                    <div class="col-6">
                        <label class="form-label fw-bold">Cantidad</label>
                        <input id="edit-cant" type="number" step="0.01" min="0.01" class="form-control" value="${cargo.cantidad}">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold">Precio Unit. (Q)</label>
                        <input id="edit-precio" type="number" step="0.01" min="0" class="form-control" value="${cargo.precio_unitario}">
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-6">
                        <label class="form-label fw-bold">Costo (Q)</label>
                        <input id="edit-costo" type="number" step="0.01" min="0" class="form-control" value="${cargo.precio_costo ?? 0}">
                    </div>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const desc = document.getElementById('edit-desc').value.trim();
            const cant = parseFloat(document.getElementById('edit-cant').value) || 0;
            const precio = parseFloat(document.getElementById('edit-precio').value) || 0;
            const costo = parseFloat(document.getElementById('edit-costo').value) || 0;
            if (!desc) { Swal.showValidationMessage('Descripción requerida'); return false; }
            if (cant <= 0) { Swal.showValidationMessage('Cantidad debe ser > 0'); return false; }
            if (precio < 0) { Swal.showValidationMessage('Precio inválido'); return false; }
            return { desc, cant, precio, costo };
        }
    });

    if (!formValues) return;
    const fd = new FormData();
    fd.append('id_cargo', cargo.id_cargo);
    fd.append('descripcion', formValues.desc);
    fd.append('cantidad', formValues.cant);
    fd.append('precio_unitario', formValues.precio);
    fd.append('precio_costo', formValues.costo);
    fd.append('csrf_token', csrf);

    try {
        const res = await fetch('api/update_cargo_cirugia.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            Swal.fire('Actualizado', json.message, 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    } catch (err) { Swal.fire('Error', 'Fallo de red', 'error'); }
}

async function deleteCargoCirugia(idCargo) {
    const r = await Swal.fire({
        title: '¿Eliminar cargo?',
        text: 'El cargo se marcará como cancelado. Si está vinculado a inventario, se devolverá el stock.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    });
    if (!r.isConfirmed) return;

    const { value: motivo } = await Swal.fire({
        title: 'Motivo de eliminación',
        input: 'text',
        inputPlaceholder: 'Opcional',
        showCancelButton: true,
        confirmButtonText: 'Eliminar',
        cancelButtonText: 'Cancelar'
    });
    if (motivo === null) return;

    const fd = new FormData();
    fd.append('id_cargo', idCargo);
    fd.append('motivo', motivo || 'Eliminado por el usuario');
    fd.append('csrf_token', csrf);

    Swal.fire({ title: 'Eliminando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    try {
        const res = await fetch('api/delete_cargo_cirugia.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            Swal.fire('Eliminado', json.message, 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    } catch (err) { Swal.fire('Error', 'Fallo de red', 'error'); }
}

// --- DESCUENTOS ---
let descuentoModal;
document.addEventListener('DOMContentLoaded', () => {
    const dm = document.getElementById('descuentoModal');
    if (dm) descuentoModal = new bootstrap.Modal(dm);
    const df = document.getElementById('descuentoForm');
    if (df) df.addEventListener('submit', saveDescuento);
});

function openDescuentoModal() {
    const f = document.getElementById('descuentoForm');
    if (f) f.reset();
    descuentoModal.show();
}

async function saveDescuento(e) {
    e.preventDefault();
    const concepto = document.getElementById('desc-concepto').value.trim();
    const monto = parseFloat(document.getElementById('desc-monto').value) || 0;
    if (!concepto) {
        Swal.fire('Error', 'Ingrese un concepto para el descuento', 'error');
        return;
    }
    if (monto <= 0) {
        Swal.fire('Error', 'El monto del descuento debe ser mayor a cero', 'error');
        return;
    }
    const fd = new FormData(e.target);
    Swal.fire({ title: 'Aplicando descuento...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    try {
        const res = await fetch('api/save_descuento_cirugia.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            descuentoModal.hide();
            Swal.fire({
                icon: 'success',
                title: '✓ Descuento aplicado',
                text: json.message,
                html: '<div class="text-start small mt-2">Se desconto Q' + parseFloat(json.monto).toFixed(2) + ' por "' + escapeHtml(json.concepto) + '".<br>Total descuentos acumulados: <strong>Q' + parseFloat(json.total_descuentos).toFixed(2) + '</strong></div>'
            }).then(() => location.reload());
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Fallo de red: ' + err.message, 'error');
    }
}

async function eliminarDescuento(idDescuento, concepto, monto) {
    const r = await Swal.fire({
        title: '¿Eliminar descuento?',
        html: '<div class="text-start"><p>Se eliminara el descuento de <strong>Q' + parseFloat(monto).toFixed(2) + '</strong> por concepto <strong>"' + escapeHtml(concepto) + '"</strong>.</p><p class="text-muted small mb-0">Esta accion no se puede deshacer.</p></div>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Si, eliminar',
        cancelButtonText: 'Cancelar'
    });
    if (!r.isConfirmed) return;
    const fd = new FormData();
    fd.append('id_descuento', idDescuento);
    fd.append('csrf_token', csrf);
    Swal.fire({ title: 'Eliminando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    try {
        const res = await fetch('api/delete_descuento_cirugia.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            Swal.fire({ icon: 'success', title: '✓ Descuento eliminado', text: json.message }).then(() => location.reload());
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Fallo de red: ' + err.message, 'error');
    }
}

async function eliminarConsumo(idConsumo, nombreMedicamento, cantidad) {
    // Modal con input de cantidad a retornar (permite retorno parcial)
    const { value: formValues } = await Swal.fire({
        title: 'Retornar al inventario',
        html: `<div class="text-start">
            <p>Medicamento: <strong>${escapeHtml(nombreMedicamento)}</strong></p>
            <p>Cantidad consumida: <strong>${cantidad} unidades</strong></p>
            <label class="form-label fw-bold mt-2">Cantidad a retornar *</label>
            <input type="number" id="retorno-cantidad" class="form-control form-control-lg text-end" min="0.01" max="${cantidad}" step="0.01" value="${cantidad}" required>
            <div class="d-flex gap-2 mt-2">
                <button type="button" id="btn-retorno-all" class="btn btn-sm btn-outline-secondary flex-fill" data-set-retorno="all">Todo (${cantidad})</button>
                <button type="button" id="btn-retorno-half" class="btn btn-sm btn-outline-secondary flex-fill" data-set-retorno="half">Mitad</button>
                <button type="button" id="btn-retorno-one" class="btn btn-sm btn-outline-secondary flex-fill" data-set-retorno="one">Solo 1</button>
            </div>
            <p class="text-muted small mb-0 mt-2"><i class="bi bi-info-circle"></i> Si retorna menos del total, el consumo se reducirá con la cantidad restante.</p>
        </div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Retornar',
        cancelButtonText: 'Cancelar',
        didOpen: () => {
            // Asignar eventos a los botones de selección rápida (cantidad total, mitad, 1)
            // El problema es que las comillas dobles en onclick="..." dentro de un template literal JS
            // cierran el onclick prematuramente. Usamos data-attributes + addEventListener aquí.
            const inputCant = document.getElementById('retorno-cantidad');
            const btnAll = document.getElementById('btn-retorno-all');
            const btnHalf = document.getElementById('btn-retorno-half');
            const btnOne = document.getElementById('btn-retorno-one');
            const maxCant = parseFloat(inputCant ? inputCant.max : 0) || 0;
            if (btnAll) btnAll.addEventListener('click', () => { if (inputCant) inputCant.value = maxCant; });
            if (btnHalf) btnHalf.addEventListener('click', () => { if (inputCant) inputCant.value = Math.max(1, Math.floor(maxCant / 2)); });
            if (btnOne) btnOne.addEventListener('click', () => { if (inputCant) inputCant.value = 1; });
        },
        preConfirm: () => {
            const inputEl = document.getElementById('retorno-cantidad');
            const v = parseFloat(inputEl ? inputEl.value : 0);
            if (!v || v <= 0) {
                Swal.showValidationMessage('Ingrese una cantidad válida');
                return false;
            }
            const cantMax = parseFloat(inputEl ? inputEl.max : 0) || 0;
            if (v > cantMax) {
                Swal.showValidationMessage('No puede retornar más de ' + cantMax);
                return false;
            }
            return { cantidad_retorno: v };
        }
    });
    if (!formValues) return;

    const fd = new FormData();
    fd.append('id_consumo', idConsumo);
    fd.append('cantidad_retorno', formValues.cantidad_retorno);
    fd.append('csrf_token', csrf);

    Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const res = await fetch('api/delete_consumo_cirugia.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            const esTotal = json.retorno_total !== false;
            const titleTxt = esTotal ? '✓ Stock retornado' : '✓ Retorno parcial';
            const extraTxt = esTotal
                ? '<br>Stock actual: <strong>' + json.stock_nuevo + '</strong>'
                : '<br>Stock actual: <strong>' + json.stock_nuevo + '</strong><br>Quedan <strong>' + json.cantidad_restante + '</strong> unidades en el consumo';
            Swal.fire({
                icon: 'success',
                title: titleTxt,
                text: json.message,
                html: '<div class="text-start small mt-2">Se retornaron <strong>' + json.cantidad + '</strong> unidades de <strong>' + escapeHtml(json.medicamento) + '</strong> al inventario de <strong>' + json.origen_label + '</strong>.' + extraTxt + '</div>'
            }).then(() => location.reload());
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Fallo de red: ' + err.message, 'error');
    }
}

async function finalizarCirugia() {
    const r = await Swal.fire({
        title: '¿Finalizar cirugía?',
        html: 'El paciente será <strong>trasladado automáticamente</strong> a una habitación disponible (excluyendo la 401) con Q600 la primera noche y luego la tarifa normal.',
        icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Finalizar y Trasladar',
        cancelButtonText: 'Cancelar'
    });
    if (!r.isConfirmed) return;
    const fd = new FormData();
    fd.append('id_cirugia', idCirugia);
    fd.append('auto_trasladar', '1');
    fd.append('csrf_token', csrf);
    const res = await fetch('api/finalizar_cirugia.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.success) {
        Swal.fire({
            icon: 'success', title: 'Cirugía finalizada',
            text: json.message,
            confirmButtonText: 'Recargar'
        }).then(() => location.reload());
    } else { Swal.fire('Error', json.message, 'error'); }
}

async function previewAsignacion() {
    const card = document.getElementById('preview-asignacion-card');
    const body = document.getElementById('preview-asignacion-body');
    card.classList.remove('d-none');
    body.innerHTML = '<div class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm me-2"></div>Buscando cama disponible...</div>';

    try {
        const res = await fetch('api/preview_asignacion.php?id_cirugia=' + idCirugia);
        const json = await res.json();

        if (!json.success) {
            body.innerHTML = `<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>${escapeHtml(json.message || 'Error')}</div>`;
            return;
        }

        if (json.ya_hospitalizado) {
            body.innerHTML = `
                <div class="alert alert-info border-0 mb-3">
                    <i class="bi bi-info-circle me-2"></i><strong>Paciente ya hospitalizado</strong>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="text-center p-3 bg-light rounded">
                            <small class="text-muted text-uppercase d-block">Habitación</small>
                            <h3 class="mb-0 text-primary">${escapeHtml(json.habitacion)}</h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center p-3 bg-light rounded">
                            <small class="text-muted text-uppercase d-block">Cama</small>
                            <h3 class="mb-0 text-primary">${escapeHtml(json.cama)}</h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center p-3 bg-light rounded">
                            <small class="text-muted text-uppercase d-block">Tarifa / noche</small>
                            <h3 class="mb-0 text-primary">Q${parseFloat(json.tarifa_por_noche).toFixed(2)}</h3>
                        </div>
                    </div>
                </div>
                <p class="text-muted small mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Los cargos de la cirugía se agregarán a la cuenta hospitalaria existente.</p>
            `;
        } else if (!json.disponible) {
            body.innerHTML = `
                <div class="alert alert-warning border-0 mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i><strong>No hay camas disponibles</strong>
                    <p class="mb-0 mt-2">${escapeHtml(json.mensaje)}</p>
                </div>
            `;
        } else {
            const c = json.seleccionada;
            const otrasCamas = json.camas.length - 1;
            body.innerHTML = `
                <div class="alert alert-success border-0 mb-3">
                    <i class="bi bi-check-circle me-2"></i><strong>Habitación sugerida para asignación:</strong>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-primary bg-opacity-10 rounded">
                            <small class="text-muted text-uppercase d-block">Habitación</small>
                            <h3 class="mb-0 text-primary">${escapeHtml(c.numero_habitacion)}</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-info bg-opacity-10 rounded">
                            <small class="text-muted text-uppercase d-block">Cama</small>
                            <h3 class="mb-0 text-info">${escapeHtml(c.numero_cama)}</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                            <small class="text-muted text-uppercase d-block">Tipo</small>
                            <h5 class="mb-0">${escapeHtml(c.tipo_habitacion || '—')}</h5>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-success bg-opacity-10 rounded">
                            <small class="text-muted text-uppercase d-block">Tarifa / noche</small>
                            <h4 class="mb-0 text-success">Q${parseFloat(c.tarifa_por_noche).toFixed(2)}</h4>
                        </div>
                    </div>
                </div>
                <div class="border rounded p-3" style="background: rgba(13,110,253,.04);">
                    <h6 class="mb-2"><i class="bi bi-cash-stack me-1"></i>Cargos post-operatorios a aplicar:</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="mb-0 small">
                                <li>Primera noche: <strong class="text-primary">Q600.00</strong> <small class="text-muted">(tarifa fija cirugía)</small></li>
                                <li>Habitación: <strong>${escapeHtml(c.numero_habitacion)} - Cama ${escapeHtml(c.numero_cama)}</strong></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="mb-0 small">
                                <li>Noches subsiguientes: <strong class="text-success">Q${parseFloat(c.tarifa_por_noche).toFixed(2)}</strong> / noche</li>
                                ${otrasCamas > 0 ? `<li class="text-muted"><i class="bi bi-info-circle me-1"></i>${otrasCamas} cama(s) alternativa(s) disponible(s)</li>` : ''}
                            </ul>
                        </div>
                    </div>
                </div>
                <p class="text-muted small mt-3 mb-0"><i class="bi bi-shield-check me-1"></i>Esta es la habitación que se asignará automáticamente al finalizar la cirugía.</p>
            `;
        }
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (err) {
        body.innerHTML = `<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>Error de red: ${escapeHtml(err.message)}</div>`;
    }
}
</script>
</body>
</html>