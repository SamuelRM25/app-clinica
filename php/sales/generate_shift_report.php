<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

date_default_timezone_set('America/Guatemala');
verify_session();

if (!isset($_GET['date'])) {
    die("Fecha no especificada.");
}

$selected_date = $_GET['date'];
$start_date = $selected_date . ' 08:00:00';
$end_date = date('Y-m-d', strtotime($selected_date . ' +1 day')) . ' 08:00:00';

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Fetch sales in range
    // Assuming 'id_usuario' exists in 'ventas'. If not, we handle it gracefully with check or just standard query.
    // Ideally we should have run the schema update.
    $query = "
        SELECT v.*, u.nombre as nombre_vendedor, u.apellido as apellido_vendedor
        FROM ventas v
        LEFT JOIN usuarios u ON v.id_usuario = u.idUsuario
        WHERE v.fecha_venta >= ? AND v.fecha_venta < ?
        ORDER BY v.fecha_venta ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$start_date, $end_date]);
    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $total_sales = 0;
    $payment_methods = [];
    $sales_by_user = [];
    
    foreach ($ventas as $venta) {
        if ($venta['estado'] !== 'Cancelado') { // Only count valid sales
            $total_sales += $venta['total'];
            
            // Payment methods
            $method = $venta['tipo_pago'];
            if (!isset($payment_methods[$method])) {
                $payment_methods[$method] = 0;
            }
            $payment_methods[$method] += $venta['total'];
            
            // Sales by user
            $user_name = ($venta['nombre_vendedor'] && $venta['apellido_vendedor']) 
                ? $venta['nombre_vendedor'] . ' ' . $venta['apellido_vendedor'] 
                : 'Desconocido / Sistema';
                
            if (!isset($sales_by_user[$user_name])) {
                $sales_by_user[$user_name] = ['count' => 0, 'total' => 0];
            }
            $sales_by_user[$user_name]['count']++;
            $sales_by_user[$user_name]['total'] += $venta['total'];
        }
    }

} catch (Exception $e) {
    die("Error al generar reporte: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Ventas por Jornada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        .report-container {
            background: white;
            max-width: 1000px;
            margin: 30px auto;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
            border-radius: 12px;
        }
        .header-section {
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            height: 100%;
        }
        .table th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            background-color: #f8f9fa;
        }
        .total-row td {
            font-weight: bold;
            font-size: 1.1rem;
            border-top: 2px solid #dee2e6;
        }
        @media print {
            body {
                background: white;
            }
            .report-container {
                box-shadow: none;
                margin: 0;
                padding: 0;
                max-width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="d-flex justify-content-end mb-3 no-print mt-4">
        <button onclick="window.print()" class="btn btn-primary rounded-pill px-4">
            <i class="bi bi-printer me-2"></i>Imprimir Reporte
        </button>
        <button onclick="window.close()" class="btn btn-light rounded-pill px-4 ms-2">
            Cerrar
        </button>
    </div>

    <div class="report-container">
        <div class="header-section text-center">
            <h2 class="fw-bold text-primary mb-2">Reporte de Ventas por Jornada</h2>
            <h5 class="text-muted mb-3"><?php echo htmlspecialchars($_SESSION['clinica'] ?? 'Clínica Médica'); ?></h5>
            <div class="d-inline-block bg-light px-4 py-2 rounded-pill border">
                <i class="bi bi-calendar-range me-2 text-primary"></i>
                <span class="fw-medium">
                    <?php echo date('d/m/Y h:i A', strtotime($start_date)); ?> 
                    <span class="mx-2 text-muted">➔</span> 
                    <?php echo date('d/m/Y h:i A', strtotime($end_date)); ?>
                </span>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="summary-card text-center border-start border-4 border-primary">
                    <p class="text-uppercase text-muted text-xs mb-1">Total Ventas</p>
                    <h3 class="fw-bold text-dark mb-0">Q<?php echo number_format($total_sales, 2); ?></h3>
                    <small class="text-success fw-medium"><?php echo count($ventas); ?> transacciones</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="summary-card border-start border-4 border-success">
                    <p class="text-uppercase text-muted text-xs mb-2">Métodos de Pago</p>
                    <ul class="list-unstyled mb-0 text-sm">
                        <?php foreach ($payment_methods as $method => $amount): ?>
                        <li class="d-flex justify-content-between mb-1">
                            <span><?php echo $method; ?>:</span>
                            <span class="fw-bold">Q<?php echo number_format($amount, 2); ?></span>
                        </li>
                        <?php endforeach; ?>
                        <?php if (empty($payment_methods)): ?>
                        <li class="text-muted fst-italic">Sin registros</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <div class="col-md-4">
                <div class="summary-card border-start border-4 border-info">
                    <p class="text-uppercase text-muted text-xs mb-2">Ventas por Usuario</p>
                    <ul class="list-unstyled mb-0 text-sm">
                        <?php foreach ($sales_by_user as $user => $data): ?>
                        <li class="d-flex justify-content-between mb-1">
                            <span class="text-truncate" style="max-width: 120px;" title="<?php echo $user; ?>"><?php echo $user; ?>:</span>
                            <span class="fw-bold">Q<?php echo number_format($data['total'], 2); ?></span>
                        </li>
                        <?php endforeach; ?>
                        <?php if (empty($sales_by_user)): ?>
                        <li class="text-muted fst-italic">Sin registros</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <h5 class="fw-bold mb-3 border-bottom pb-2">Detalle de Transacciones</h5>
        <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 50px;">#</th>
                        <th>Fecha y Hora</th>
                        <th>Cliente</th>
                        <th>Vendedor</th>
                        <th>Método Pago</th>
                        <th>Estado</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($ventas) > 0): ?>
                        <?php foreach ($ventas as $index => $venta): ?>
                        <tr>
                            <td class="text-center text-muted"><?php echo $index + 1; ?></td>
                            <td>
                                <div class="fw-medium"><?php echo date('d/m/Y', strtotime($venta['fecha_venta'])); ?></div>
                                <small class="text-muted"><?php echo date('h:i A', strtotime($venta['fecha_venta'])); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($venta['nombre_cliente']); ?></td>
                            <td>
                                <?php 
                                    echo ($venta['nombre_vendedor']) 
                                        ? htmlspecialchars($venta['nombre_vendedor'] . ' ' . substr($venta['apellido_vendedor'], 0, 1) . '.') 
                                        : '<span class="text-muted fst-italic">Sistema</span>'; 
                                ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border rounded-pill fw-normal">
                                    <?php echo htmlspecialchars($venta['tipo_pago']); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $statusClass = match($venta['estado']) {
                                    'Pagado' => 'success',
                                    'Pendiente' => 'warning',
                                    'Cancelado' => 'danger',
                                    default => 'secondary'
                                };
                                ?>
                                <span class="badge bg-<?php echo $statusClass; ?> bg-opacity-10 text-<?php echo $statusClass; ?> border border-<?php echo $statusClass; ?> rounded-pill">
                                    <?php echo htmlspecialchars($venta['estado']); ?>
                                </span>
                            </td>
                            <td class="text-end fw-bold text-dark">Q<?php echo number_format($venta['total'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="total-row table-light">
                            <td colspan="6" class="text-end">Total General:</td>
                            <td class="text-end text-primary">Q<?php echo number_format($total_sales, 2); ?></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-3 opacity-25"></i>
                                No se encontraron ventas en este período.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="row mt-5 pt-4 border-top">
            <div class="col-6 text-center">
                <div class="border-top border-dark w-75 mx-auto mb-2" style="margin-top: 50px;"></div>
                <small class="text-muted text-uppercase">Firma Cajero</small>
            </div>
            <div class="col-6 text-center">
                <div class="border-top border-dark w-75 mx-auto mb-2" style="margin-top: 50px;"></div>
                <small class="text-muted text-uppercase">Firma Administración</small>
            </div>
        </div>

        <div class="text-center mt-5">
            <small class="text-muted">Generado el <?php echo date('d/m/Y h:i A'); ?> por <?php echo htmlspecialchars($_SESSION['nombre']); ?></small>
        </div>
    </div>
</div>

</body>
</html>
