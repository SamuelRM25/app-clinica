<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

header('Content-Type: application/json');

// Manejar reporte por jornada
if (isset($_GET['shift_report']) && $_GET['shift_report'] == 1 && isset($_GET['shift_date'])) {
    generate_shift_report($_GET['shift_date']);
    exit;
}

// Manejar solicitud de detalles de venta individual
if (isset($_GET['id'])) {
    $id_venta = intval($_GET['id']);
    
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Obtener datos de la venta
        $stmt = $conn->prepare("
            SELECT v.id_venta, v.fecha_venta, v.nombre_cliente, v.tipo_pago, v.total, v.estado,
                   u.nombre as nombre_vendedor, u.apellido as apellido_vendedor
            FROM ventas v
            LEFT JOIN usuarios u ON v.id_usuario = u.idUsuario
            WHERE v.id_venta = ?
        ");
        $stmt->execute([$id_venta]);
        $venta = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($venta) {
            // Obtener items de la venta
            $stmt = $conn->prepare("
                SELECT dv.*, i.nom_medicamento, i.presentacion_med
                FROM detalle_ventas dv
                JOIN inventario i ON dv.id_inventario = i.id_inventario
                WHERE dv.id_venta = ?
            ");
            $stmt->execute([$id_venta]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'status' => 'success',
                'venta' => $venta,
                'items' => $items
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Venta no encontrada']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

function generate_shift_report($shift_date) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Calcular fechas de la jornada (8am a 8am siguiente)
        $start_date = date('Y-m-d 08:00:00', strtotime($shift_date));
        $end_date = date('Y-m-d 08:00:00', strtotime($shift_date . ' +1 day'));
        
        // Obtener todas las ventas de la jornada
        $stmt = $conn->prepare("
            SELECT v.id_venta, v.fecha_venta, v.nombre_cliente, v.tipo_pago, v.total, v.estado,
                   u.nombre as nombre_vendedor, u.apellido as apellido_vendedor
            FROM ventas v
            LEFT JOIN usuarios u ON v.id_usuario = u.idUsuario
            WHERE v.fecha_venta >= ? AND v.fecha_venta < ?
            ORDER BY v.fecha_venta
        ");
        $stmt->execute([$start_date, $end_date]);
        $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener detalles de cada venta
        foreach ($ventas as &$venta) {
            $stmt = $conn->prepare("
                SELECT dv.*, i.nom_medicamento, i.mol_medicamento, i.presentacion_med, i.casa_farmaceutica
                FROM detalle_ventas dv
                JOIN inventario i ON dv.id_inventario = i.id_inventario
                WHERE dv.id_venta = ?
            ");
            $stmt->execute([$venta['id_venta']]);
            $venta['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($venta); // Romper la referencia
        
        // Calcular total de la jornada
        $total_jornada = array_sum(array_column($ventas, 'total'));
        
        // Generar reporte HTML
        generate_html_report($ventas, $start_date, $end_date, $total_jornada);
        
    } catch (Exception $e) {
        die("Error generando reporte: " . $e->getMessage());
    }
}

function generate_html_report($ventas, $start_date, $end_date, $total_jornada) {
    // Configurar cabeceras para mostrar como HTML
    header('Content-Type: text/html; charset=utf-8');
    
    // Obtener la ruta base
    $base_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF']));
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Reporte de Jornada</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
        <style>
            @media print {
                .no-print {
                    display: none;
                }
                body {
                    padding: 20px;
                }
            }
            .header-report {
                border-bottom: 2px solid #333;
                padding-bottom: 15px;
                margin-bottom: 20px;
            }
            .report-title {
                font-size: 1.8rem;
                font-weight: bold;
            }
            .shift-info {
                background-color: #f8f9fa;
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 20px;
            }
            .total-jornada {
                font-size: 1.4rem;
                font-weight: bold;
                background-color: #e9ecef;
                padding: 10px;
                border-radius: 5px;
            }
            .sale-details {
                margin-top: 30px;
            }
            .sale-header {
                background-color: #0d6efd;
                color: white;
                padding: 8px 15px;
                border-radius: 5px;
                margin-bottom: 10px;
            }
            .sale-items {
                margin-left: 30px;
                margin-bottom: 20px;
            }
            .sale-item {
                border-bottom: 1px solid #dee2e6;
                padding: 5px 0;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header-report text-center">
                <div class="report-title">Reporte de Jornada</div>
                <div class="text-muted">Clínica Médica</div>
            </div>
            
            <div class="shift-info">
                <div><strong>Jornada:</strong> <?= date('d/m/Y H:i', strtotime($start_date)) ?> - <?= date('d/m/Y H:i', strtotime($end_date)) ?></div>
                <div><strong>Total de Ventas:</strong> <?= count($ventas) ?></div>
            </div>
            
            <div class="total-jornada text-end">
                Total Jornada: Q<?= number_format($total_jornada, 2) ?>
            </div>
            
            <?php if (count($ventas) > 0): ?>
                <?php foreach ($ventas as $venta): ?>
                    <div class="sale-details">
                        <div class="sale-header d-flex justify-content-between">
                            <div>
                                <strong>Venta #<?= $venta['id_venta'] ?></strong> | 
                                <?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?>
                            </div>
                            <div>Q<?= number_format($venta['total'], 2) ?></div>
                        </div>
                        
                        <div class="mb-2">
                            <strong>Cliente:</strong> <?= htmlspecialchars($venta['nombre_cliente']) ?> | 
                            <strong>Vendedor:</strong> <?= htmlspecialchars(($venta['nombre_vendedor'] ?? '') . ' ' . ($venta['apellido_vendedor'] ?? '')) ?> |
                            <strong>Pago:</strong> <?= htmlspecialchars($venta['tipo_pago']) ?> | 
                            <strong>Estado:</strong> <span class="badge bg-<?= 
                                ($venta['estado'] == 'Pagado') ? 'success' : 
                                (($venta['estado'] == 'Pendiente') ? 'warning' : 'danger') ?>">
                                <?= htmlspecialchars($venta['estado']) ?>
                            </span>
                        </div>
                        
                        <div class="sale-items">
                            <div class="fw-bold mb-2">Productos:</div>
                            <?php foreach ($venta['items'] as $item): ?>
                                <div class="sale-item">
                                    <div><?= htmlspecialchars($item['nom_medicamento']) ?> (<?= htmlspecialchars($item['presentacion_med']) ?>)</div>
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <?= $item['cantidad_vendida'] ?> x Q<?= number_format($item['precio_unitario'], 2) ?>
                                        </div>
                                        <div>
                                            <strong>Q<?= number_format($item['cantidad_vendida'] * $item['precio_unitario'], 2) ?></strong>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-warning text-center">
                    No se encontraron ventas en esta jornada
                </div>
            <?php endif; ?>
            
            <div class="mt-4 no-print">
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Imprimir Reporte
                </button>
                <button class="btn btn-secondary" onclick="window.close()">
                    <i class="bi bi-x-circle me-1"></i> Cerrar
                </button>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>
