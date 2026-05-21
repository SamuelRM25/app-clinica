<?php
// laboratory/reportes_diarios.php - Daily laboratory reports
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';



verify_session();

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get today's date
    $fecha = $_GET['fecha'] ?? date('Y-m-d');

    // Get today's statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT o.id_orden) as total_ordenes,
            COUNT(DISTINCT CASE WHEN o.estado = 'Pendiente' THEN o.id_orden END) as pendientes,
            COUNT(DISTINCT CASE WHEN o.estado = 'Muestra_Recibida' THEN o.id_orden END) as muestras_recibidas,
            COUNT(DISTINCT CASE WHEN o.estado = 'En_Proceso' THEN o.id_orden END) as en_proceso,
            COUNT(DISTINCT CASE WHEN o.estado = 'Completada' THEN o.id_orden END) as completadas,
            COUNT(DISTINCT CASE WHEN o.estado = 'Validada' THEN o.id_orden END) as validadas,
            COUNT(od.id_detalle) as total_pruebas,
            SUM(cp.price) as ingresos_estimados
        FROM ordenes_laboratorio o
        LEFT JOIN orden_detalles od ON o.id_orden = od.id_orden
        LEFT JOIN catalogo_pruebas cp ON od.id_prueba = cp.id_prueba
        WHERE DATE(o.fecha_orden) = ?
    ");
    $stmt->execute([$fecha]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get orders for the day
    $stmt = $conn->prepare("
        SELECT o.*, p.nombre, p.apellido,
               COUNT(od.id_detalle) as num_pruebas
        FROM ordenes_laboratorio o
        JOIN pacientes p ON o.id_paciente = p.id_paciente
        LEFT JOIN orden_detalles od ON o.id_orden = od.id_orden
        WHERE DATE(o.fecha_orden) = ?
        GROUP BY o.id_orden
        ORDER BY o.fecha_orden DESC
    ");
    $stmt->execute([$fecha]);
    $ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $page_title = "Reporte Diario - Laboratorio";
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>

    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <?php include '../../includes/theme_head.php'; ?>

    <style>
        @media print {
            .no-print {
                display: none !important;
            }

            body, html {
                background: white !important;
                color: black !important;
            }

            .dashboard-container {
                margin: 0 !important;
                padding: 0 !important;
                background: transparent !important;
                box-shadow: none !important;
            }

            .main-content {
                padding: 0 !important;
                margin: 0 !important;
            }

            * {
                background: transparent !important;
                color: black !important;
                box-shadow: none !important;
                text-shadow: none !important;
                backdrop-filter: none !important;
                -webkit-backdrop-filter: none !important;
            }

            .stat-card {
                border: 1px solid #ddd !important;
                background: #fff !important;
                margin-bottom: 1rem;
            }

            .data-table {
                border: 1px solid #ddd !important;
            }

            .data-table th {
                border-bottom: 2px solid #000 !important;
                color: #000 !important;
                background: #f5f5f5 !important;
            }

            .data-table td {
                border-bottom: 1px solid #ddd !important;
            }
        }
    </style>
</head>

<body>
    <div class="marble-effect no-print"></div>

    <div class="dashboard-container">
        <!-- Header Superior -->
        <header class="dashboard-header no-print">
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
                            <?php echo isset($_SESSION['nombre']) ? strtoupper(substr($_SESSION['nombre'], 0, 1)) : 'U'; ?>
                        </div>
                        <div class="header-details">
                            <span class="header-name"><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></span>
                            <span class="header-role">Laboratorio</span>
                        </div>
                    </div>

                    <!-- Back Button -->
                    <a href="index.php" class="action-btn secondary">
                        <i class="bi bi-arrow-left"></i>
                        Volver
                    </a>

                    <!-- Botón de cerrar sesión -->
                    <a href="../auth/logout.php" class="logout-btn">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Salir</span>
                    </a>
                </div>
            </div>
        </header>

        <main class="main-content">
            <div class="welcome-banner animate-in mb-4 no-print">
                <h1>Reportes Diarios</h1>
                <p>Resumen detallado de la productividad y estado de órdenes del laboratorio</p>
            </div>

            <!-- Cabecera de Reporte -->
            <section class="calendar-section animate-in mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h3 class="section-title mb-1">
                            <i class="bi bi-file-earmark-text section-title-icon"></i>
                            Reporte de Actividades
                        </h3>
                        <p class="text-muted small mb-0">Fecha seleccionada: <strong class="text-primary"><?php echo date('d/m/Y', strtotime($fecha)); ?></strong></p>
                    </div>
                    <div class="d-flex gap-2 no-print">
                        <input type="date" id="fecha" class="form-control" style="width: auto;" value="<?php echo $fecha; ?>" onchange="cambiarFecha()">
                        <button class="action-btn" onclick="window.print()">
                            <i class="bi bi-printer me-1"></i> Imprimir
                        </button>
                    </div>
                </div>
            </section>

            <!-- Estadísticas en Cuadrícula Premium -->
            <div class="stats-grid mb-4">
                <div class="stat-card animate-in delay-1">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Total Órdenes</div>
                            <div class="stat-value"><?php echo $stats['total_ordenes'] ?? 0; ?></div>
                        </div>
                        <div class="stat-icon info">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card animate-in delay-2">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Pendientes</div>
                            <div class="stat-value"><?php echo $stats['pendientes'] ?? 0; ?></div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="bi bi-clock"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card animate-in delay-3">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Muestras Recibidas</div>
                            <div class="stat-value"><?php echo $stats['muestras_recibidas'] ?? 0; ?></div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="bi bi-droplet"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card animate-in delay-4">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">En Proceso</div>
                            <div class="stat-value"><?php echo $stats['en_proceso'] ?? 0; ?></div>
                        </div>
                        <div class="stat-icon info">
                            <i class="bi bi-gear"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card animate-in delay-1">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Completadas</div>
                            <div class="stat-value"><?php echo $stats['completadas'] ?? 0; ?></div>
                        </div>
                        <div class="stat-icon success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card animate-in delay-2">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Validadas</div>
                            <div class="stat-value"><?php echo $stats['validadas'] ?? 0; ?></div>
                        </div>
                        <div class="stat-icon success">
                            <i class="bi bi-shield-check"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card animate-in delay-3">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Total Pruebas</div>
                            <div class="stat-value"><?php echo $stats['total_pruebas'] ?? 0; ?></div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="bi bi-journal-medical"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card animate-in delay-4">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Ingresos Estimados</div>
                            <div class="stat-value">Q<?php echo number_format($stats['ingresos_estimados'] ?? 0, 2); ?></div>
                        </div>
                        <div class="stat-icon success">
                            <i class="bi bi-currency-dollar"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Listado de Órdenes del Día -->
            <section class="calendar-section animate-in delay-1">
                <div class="section-header d-flex justify-content-between align-items-center mb-4">
                    <h3 class="section-title">
                        <i class="bi bi-list-ul section-title-icon"></i>
                        Órdenes Registradas del Día
                    </h3>
                </div>

                <?php if (count($ordenes) > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Orden #</th>
                                    <th>Paciente</th>
                                    <th>Hora</th>
                                    <th>Pruebas</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ordenes as $orden): ?>
                                    <tr>
                                        <td class="fw-bold text-primary">
                                            <i class="bi bi-hash"></i><?php echo htmlspecialchars($orden['numero_orden']); ?>
                                        </td>
                                        <td class="fw-semibold">
                                            <?php echo htmlspecialchars($orden['nombre'] . ' ' . $orden['apellido']); ?>
                                        </td>
                                        <td class="text-muted">
                                            <i class="bi bi-clock me-1"></i><?php echo date('H:i', strtotime($orden['fecha_orden'])); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                                <?php echo $orden['num_pruebas']; ?> Pruebas
                                            </span>
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
                                                    $estado_class = 'info';
                                                    $estado_text = 'Muestra Recibida';
                                                    break;
                                                case 'En_Proceso':
                                                    $estado_class = 'en-proceso';
                                                    $estado_text = 'En Proceso';
                                                    break;
                                                case 'Completada':
                                                    $estado_class = 'activo';
                                                    $estado_text = 'Completada';
                                                    break;
                                                case 'Validada':
                                                    $estado_class = 'activo';
                                                    $estado_text = 'Validada';
                                                    break;
                                                default:
                                                    $estado_class = 'pendiente';
                                                    $estado_text = $orden['estado'];
                                                    break;
                                            }
                                            ?>
                                            <span class="status-badge <?php echo $estado_class; ?>">
                                                <?php echo $estado_text; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox text-muted" style="font-size: 4rem; opacity: 0.4;"></i>
                        <h4 class="text-muted mt-3 fw-medium">No se encontraron órdenes</h4>
                        <p class="text-muted small">No hay transacciones ni órdenes de laboratorio para la fecha seleccionada.</p>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script>
        function cambiarFecha() {
            const fecha = document.getElementById('fecha').value;
            window.location.href = `?fecha=${fecha}`;
        }
    </script>
</body>

</html>