<?php
// surgery/index.php - Dashboard de Quirófano - Centro Médico Herrera Saenz
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

check_module_access('surgery');

$id_hospital = (int) ($_SESSION['id_hospital'] ?? 0);
verify_session();
date_default_timezone_set('America/Guatemala');

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['tipoUsuario'];
$user_name = $_SESSION['nombre'];
$user_specialty = $_SESSION['especialidad'] ?? 'Personal';

try {
    $database = new Database();
    $conn = $database->getConnection();

    // ===== KPIs =====
    $stmt_programadas = $conn->prepare("SELECT COUNT(*) as total FROM cirugias WHERE estado = 'Programada' AND id_hospital = ?");
    $stmt_programadas->execute([$id_hospital]);
    $cirugias_programadas = $stmt_programadas->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt_en_curso = $conn->prepare("SELECT COUNT(*) as total FROM cirugias WHERE estado = 'En_Curso' AND id_hospital = ?");
    $stmt_en_curso->execute([$id_hospital]);
    $cirugias_en_curso = $stmt_en_curso->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt_hoy = $conn->prepare("SELECT COUNT(*) as total FROM cirugias WHERE DATE(fecha_programada) = CURDATE() AND id_hospital = ?");
    $stmt_hoy->execute([$id_hospital]);
    $cirugias_hoy = $stmt_hoy->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt_finalizadas_hoy = $conn->prepare("SELECT COUNT(*) as total FROM cirugias WHERE DATE(fecha_fin) = CURDATE() AND estado = 'Finalizada' AND id_hospital = ?");
    $stmt_finalizadas_hoy->execute([$id_hospital]);
    $cirugias_finalizadas_hoy = $stmt_finalizadas_hoy->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt_salas_total = $conn->prepare("SELECT COUNT(*) as total FROM salas_quirurgicas WHERE id_hospital = ?");
    $stmt_salas_total->execute([$id_hospital]);
    $salas_total = $stmt_salas_total->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt_salas_disp = $conn->prepare("SELECT COUNT(*) as total FROM salas_quirurgicas WHERE estado = 'Disponible' AND id_hospital = ?");
    $stmt_salas_disp->execute([$id_hospital]);
    $salas_disponibles = $stmt_salas_disp->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt_combos = $conn->prepare("SELECT COUNT(*) as total FROM cirugia_combos WHERE estado = 'Activo' AND id_hospital = ?");
    $stmt_combos->execute([$id_hospital]);
    $combos_activos = $stmt_combos->fetch(PDO::FETCH_ASSOC)['total'];

    // ===== Lista de cirugías recientes (últimas 50) =====
    $stmt_cirugias = $conn->prepare("
        SELECT c.id_cirugia, c.numero_cirugia, c.estado, c.fecha_programada,
               c.fecha_inicio, c.fecha_fin, c.cargo_total, c.tipo_paciente,
               COALESCE(CONCAT(p.nombre, ' ', p.apellido), CONCAT(c.referido_nombre, ' ', c.referido_apellido)) AS paciente,
               s.nombre AS sala,
               cc.nombre AS combo
        FROM cirugias c
        LEFT JOIN pacientes p ON c.id_paciente = p.id_paciente
        LEFT JOIN salas_quirurgicas s ON c.id_sala = s.id_sala
        LEFT JOIN cirugia_combos cc ON c.id_combo = cc.id_combo
        WHERE c.id_hospital = ?
        ORDER BY c.fecha_programada DESC, c.id_cirugia DESC
        LIMIT 50
    ");
    $stmt_cirugias->execute([$id_hospital]);
    $cirugias = $stmt_cirugias->fetchAll(PDO::FETCH_ASSOC);

    $page_title = "Quirófano - Centro Médico Herrera Saenz";
} catch (Exception $e) {
    error_log('Error en surgery/index.php: ' . $e->getMessage());
    die("Error al cargar Quirófano.");
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="icon" type="image/png" href="../../assets/img/cmhs.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
                    <img src="../../assets/img/cmhs.png" alt="Centro Médico Herrera Saenz" class="brand-logo" width="40" height="40">
                    <div>
                        <h2 class="mb-0" style="font-size: 1.25rem;">Quirófano</h2>
                        <small class="text-muted">Gestión de cirugías y procedimientos quirúrgicos</small>
                    </div>
                </div>
                <div class="header-controls">
                    <a href="../dashboard/index.php" class="back-btn">
                        <i class="bi bi-arrow-left"></i><span>Dashboard</span>
                    </a>
                </div>
            </div>
        </header>

        <main class="main-content">
            <!-- KPIs -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary"><i class="bi bi-calendar-check"></i></div>
                        <div class="stat-info">
                            <div class="stat-label">Cirugías Hoy</div>
                            <div class="stat-value"><?php echo $cirugias_hoy; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-warning"><i class="bi bi-clock-history"></i></div>
                        <div class="stat-info">
                            <div class="stat-label">Programadas</div>
                            <div class="stat-value"><?php echo $cirugias_programadas; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-danger"><i class="bi bi-activity"></i></div>
                        <div class="stat-info">
                            <div class="stat-label">En Curso</div>
                            <div class="stat-value"><?php echo $cirugias_en_curso; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-success"><i class="bi bi-check-circle"></i></div>
                        <div class="stat-info">
                            <div class="stat-label">Finalizadas Hoy</div>
                            <div class="stat-value"><?php echo $cirugias_finalizadas_hoy; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-info"><i class="bi bi-door-open"></i></div>
                        <div class="stat-info">
                            <div class="stat-label">Salas Disponibles</div>
                            <div class="stat-value"><?php echo $salas_disponibles; ?> / <?php echo $salas_total; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-secondary"><i class="bi bi-stack"></i></div>
                        <div class="stat-info">
                            <div class="stat-label">Combos Activos</div>
                            <div class="stat-value"><?php echo $combos_activos; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Acciones rápidas -->
            <div class="d-flex gap-2 mb-4 flex-wrap">
                <a href="nueva_cirugia.php" class="action-btn primary">
                    <i class="bi bi-plus-circle"></i> Nueva Cirugía
                </a>
                <a href="combos.php" class="action-btn">
                    <i class="bi bi-stack"></i> Gestionar Combos
                </a>
                <a href="salas.php" class="action-btn">
                    <i class="bi bi-door-open"></i> Gestionar Salas
                </a>
            </div>

            <!-- Tabla de cirugías recientes -->
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Cirugías Recientes</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($cirugias)): ?>
                        <div class="empty-state p-5 text-center text-muted">
                            <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                            <p class="mt-3 mb-0">No hay cirugías registradas.</p>
                            <a href="nueva_cirugia.php" class="action-btn primary mt-3">
                                <i class="bi bi-plus-circle"></i> Registrar Primera Cirugía
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th># Cirugía</th>
                                        <th>Paciente</th>
                                        <th>Sala</th>
                                        <th>Combo</th>
                                        <th>Fecha Programada</th>
                                        <th>Estado</th>
                                        <th>Cargo</th>
                                        <th class="text-center">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cirugias as $c): ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo htmlspecialchars($c['numero_cirugia']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($c['paciente']); ?>
                                                <?php if ($c['tipo_paciente'] === 'Referido'): ?>
                                                    <span class="badge bg-warning text-dark ms-1">Referido</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($c['sala'] ?? '—'); ?></td>
                                            <td><?php echo htmlspecialchars($c['combo'] ?? '—'); ?></td>
                                            <td><?php echo $c['fecha_programada'] ? date('d/m/Y H:i', strtotime($c['fecha_programada'])) : '—'; ?></td>
                                            <td>
                                                <?php
                                                $estado_class = [
                                                    'Programada' => 'bg-secondary',
                                                    'En_Curso' => 'bg-danger',
                                                    'Finalizada' => 'bg-success',
                                                    'Cancelada' => 'bg-dark'
                                                ][$c['estado']] ?? 'bg-secondary';
                                                ?>
                                                <span class="badge <?php echo $estado_class; ?>"><?php echo $c['estado']; ?></span>
                                            </td>
                                            <td class="text-end">Q<?php echo number_format($c['cargo_total'], 2); ?></td>
                                            <td class="text-center">
                                                <a href="detalle_cirugia.php?id=<?php echo $c['id_cirugia']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>