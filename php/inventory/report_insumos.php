<?php
// report_insumos.php
session_start();
require_once '../../config/database.php';

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
            WHERE i.fecha BETWEEN ? AND ?
            ORDER BY i.fecha ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$start, $end]);
    $insumos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = 0;
    foreach ($insumos as $insumo) {
        $total += $insumo['subtotal'];
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Reporte de Insumos -
        <?php echo $date; ?>
    </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 14px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .table thead th {
            background-color: #f8f9fa;
        }

        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body class="p-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <h3>Reporte de Insumos</h3>
            <div>
                <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer"></i> Imprimir</button>
                <button onclick="window.close()" class="btn btn-secondary">Cerrar</button>
            </div>
        </div>

        <div class="header">
            <h4>Centro Médico</h4>
            <h5>Reporte de Insumos Descargados</h5>
            <p class="mb-0"><strong>Fecha:</strong>
                <?php echo date('d/m/Y', strtotime($date)); ?>
            </p>
            <p><strong>Jornada:</strong>
                <?php echo $shift_name; ?>
            </p>
        </div>

        <table class="table table-bordered">
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
                                <?php echo date('H:i', strtotime($row['fecha'])); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($row['nombre']); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($row['usuario']); ?>
                            </td>
                            <td class="text-center">
                                <?php echo $row['cantidad']; ?>
                            </td>
                            <td class="text-end">Q
                                <?php echo number_format($row['precio_venta'], 2); ?>
                            </td>
                            <td class="text-end fw-bold">Q
                                <?php echo number_format($row['subtotal'], 2); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-3">No hay insumos registrados en este turno.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-end fw-bold">TOTAL GENERAL</td>
                    <td class="text-end fw-bold bg-light">Q
                        <?php echo number_format($total, 2); ?>
                    </td>
                </tr>
            </tfoot>
        </table>

        <div class="mt-5 pt-5 text-center">
            <div class="row">
                <div class="col-6">
                    <div class="border-top border-dark w-75 mx-auto"></div>
                    <p class="mt-1">Firma Responsable</p>
                </div>
                <div class="col-6">
                    <div class="border-top border-dark w-75 mx-auto"></div>
                    <p class="mt-1">Firma Recibido</p>
                </div>
            </div>
        </div>
    </div>
</body>

</html>