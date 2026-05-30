<?php
// report_insumos.php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

$id_hospital = (int) ($_SESSION['id_hospital'] ?? 0);

if (!isset($_SESSION['user_id'])) {
    die("Acceso no autorizado");
}

$date = $_GET['date'] ?? date('Y-m-d');
$shift = $_GET['shift'] ?? 'morning';

// Define time ranges
if ($shift === 'morning') {
    $start = $date . ' 08:00:00';
    $end = $date . ' 17:00:00';
    $shift_name = 'Matutina (08:00 AM - 05:00 PM)';
} else {
    $start = $date . ' 17:00:00';
    $end = date('Y-m-d', strtotime($date . ' +1 day')) . ' 07:59:59';
    $shift_name = 'Nocturna (05:00 PM - 08:00 AM)';
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Fetch Insumos data
    $sql = "SELECT i.fecha, CONCAT(inv.nom_medicamento, ' (', inv.presentacion_med, ')') as nombre, i.cantidad, i.precio_venta, (i.cantidad * i.precio_venta) as subtotal, 
                   CONCAT(u.nombre, ' ', u.apellido) as usuario
            FROM insumos i
            JOIN inventario inv ON i.id_inventario = inv.id_inventario
            JOIN usuarios u ON i.id_usuario = u.idUsuario
            WHERE i.fecha BETWEEN ? AND ? AND inv.id_hospital = ?
            ORDER BY i.fecha ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$start, $end, $id_hospital]);
    $insumos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = 0;
    foreach ($insumos as $insumo) {
        $total += $insumo['subtotal'];
    }

} catch (Exception $e) {
    error_log("php/inventory/report_insumos.php error: " . $e->getMessage());
    die("Error: " . 'Error del servidor.');
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Insumos - <?php echo $date; ?></title>

    <link rel="icon" type="image/png" href="../../assets/img/cmhs.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <?php include '../../includes/theme_head.php'; ?>

    <style>
        .signature-section {
            margin-top: 5rem;
        }

        .signature-line {
            border-top: 1.5px solid var(--color-text-secondary);
            width: 70%;
            margin: 0 auto;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body,
            html {
                background: white !important;
                color: black !important;
                padding: 0 !important;
                margin: 0 !important;
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
            }

            .data-table {
                border: 1px solid #ddd !important;
                border-spacing: 0 !important;
                width: 100% !important;
            }

            .data-table th {
                border-bottom: 2px solid #000 !important;
                color: #000 !important;
                background: #f5f5f5 !important;
                padding: 0.5rem !important;
            }

            .data-table td {
                border-bottom: 1px solid #ddd !important;
                padding: 0.5rem !important;
            }

            .signature-line {
                border-top: 1.5px solid #000 !important;
            }
        }
    </style>
</head>

<body class="p-4">
    <div class="marble-effect no-print"></div>

    <div class="dashboard-container">
        <!-- Header Superior no-print -->
        <header class="dashboard-header no-print">
            <div class="header-content">
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
                            <?php echo isset($_SESSION['nombre']) ? strtoupper(substr($_SESSION['nombre'], 0, 1)) : 'U'; ?>
                        </div>
                        <div class="header-details">
                            <span
                                class="header-name"><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></span>
                            <span class="header-role">Inventario</span>
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
            <!-- Sección Cabecera -->
            <section class="calendar-section animate-in mb-4 no-print">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h3 class="section-title mb-1">
                            <i class="bi bi-receipt section-title-icon"></i>
                            Reporte de Insumos Descargados
                        </h3>
                        <p class="text-muted small mb-0">Jornada: <strong
                                class="text-primary"><?php echo $shift_name; ?></strong></p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="action-btn" onclick="window.print()">
                            <i class="bi bi-printer me-1"></i> Imprimir Reporte
                        </button>
                    </div>
                </div>
            </section>

            <!-- Datos de Reporte en Card -->
            <div class="card animate-in delay-1 p-4 mb-4">
                <div class="header text-center mb-4">
                    <h4 class="fw-bold">Centro Médico</h4>
                    <h5 class="text-muted">Reporte de Insumos Descargados</h5>
                    <p class="mb-0"><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($date)); ?></p>
                    <p class="mb-0"><strong>Jornada:</strong> <?php echo $shift_name; ?></p>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Hora</th>
                                <th>Producto</th>
                                <th>Usuario</th>
                                <th class="text-center">Cant.</th>
                                <th class="text-end">Precio U.</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($insumos) > 0): ?>
                                    <?php foreach ($insumos as $row): ?>
                                            <tr>
                                                <td>
                                                    <i class="bi bi-clock me-1 text-muted"></i>
                                                    <?php echo date('H:i', strtotime($row['fecha'])); ?>
                                                </td>
                                                <td class="fw-semibold">
                                                    <?php echo htmlspecialchars($row['nombre']); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($row['usuario']); ?>
                                                </td>
                                                <td class="text-center fw-bold text-muted">
                                                    <?php echo $row['cantidad']; ?>
                                                </td>
                                                <td class="text-end">
                                                    Q<?php echo number_format($row['precio_venta'], 2); ?>
                                                </td>
                                                <td class="text-end fw-bold text-primary">
                                                    Q<?php echo number_format($row['subtotal'], 2); ?>
                                                </td>
                                            </tr>
                                    <?php endforeach; ?>
                            <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">No hay insumos registrados en este
                                            turno.</td>
                                    </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <td colspan="5" class="text-end fw-bold">TOTAL GENERAL</td>
                                <td class="text-end fw-bold text-success fs-5">
                                    Q<?php echo number_format($total, 2); ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Firmas -->
                <div class="signature-section mt-5 pt-4 text-center">
                    <div class="row">
                        <div class="col-6">
                            <div class="signature-line"></div>
                            <p class="mt-2 text-muted fw-semibold small">Firma Responsable</p>
                        </div>
                        <div class="col-6">
                            <div class="signature-line"></div>
                            <p class="mt-2 text-muted fw-semibold small">Firma Recibido</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>

</html>