<?php
// dispensary/export_shift_pdf.php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    die("No autorizado");
}
verify_session();

// Establecer la zona horaria correcta
date_default_timezone_set('America/Guatemala');

try {
    $database = new Database();
    $conn = $database->getConnection();

    $date = date('Y-m-d');
    $now = date('H:i:s');

    // Define shift ranges (copied from dashboard logic)
    if ($now >= '08:00:00' && $now < '17:00:00') {
        $shift = 'morning';
        $start_datetime = $date . ' 08:00:00';
        $end_datetime = $date . ' 17:00:00';
    } else {
        $shift = 'night';
        if ($now < '08:00:00') {
            $start_datetime = date('Y-m-d', strtotime('-1 day')) . ' 17:00:00';
            $end_datetime = $date . ' 07:59:59';
        } else {
            $start_datetime = $date . ' 17:00:00';
            $end_datetime = date('Y-m-d', strtotime('+1 day')) . ' 07:59:59';
        }
    }

    $stmt = $conn->prepare("
        SELECT v.*, u.nombre as cajero_name
        FROM ventas v
        LEFT JOIN usuarios u ON v.id_usuario = u.idUsuario
        WHERE v.fecha_venta BETWEEN ? AND ?
        ORDER BY v.fecha_venta ASC
    ");
    $stmt->execute([$start_datetime, $end_datetime]);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals by payment method
    $totals = [
        'Efectivo' => 0,
        'Tarjeta' => 0,
        'Transferencia' => 0,
        'Seguro Médico' => 0
    ];
    $grand_total = 0;
    foreach ($sales as $sale) {
        $grand_total += $sale['total'];
        if (isset($totals[$sale['tipo_pago']])) {
            $totals[$sale['tipo_pago']] += $sale['total'];
        }
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Corte de Jornada - Dispensario</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            margin: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }

        .logo {
            height: 60px;
            margin-bottom: 10px;
        }

        h1 {
            margin: 0;
            font-size: 16pt;
            color: #198754;
        }

        .meta {
            font-size: 9pt;
            color: #666;
            margin-top: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th {
            background-color: #f2f2f2;
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 9pt;
        }

        td {
            border: 1px solid #ddd;
            padding: 6px;
            font-size: 8pt;
        }

        .total-box {
            margin-top: 20px;
            float: right;
            width: 250px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }

        .grand-total {
            font-weight: bold;
            font-size: 11pt;
            border-bottom: 2px solid #333;
        }

        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 8pt;
            color: #999;
            padding: 10px 0;
        }

        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="no-print"
        style="background: #e9ecef; padding: 10px; margin-bottom: 20px; border-radius: 5px; text-align: right;">
        <button onclick="window.print()"
            style="background: #198754; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold;">
            🖨️ Imprimir / Guardar PDF
        </button>
        <button onclick="window.close()"
            style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-left: 10px;">
            Cerrar
        </button>
    </div>

    <div class="header">
        <img src="../../assets/img/Logo.png" class="logo" alt="Logo" onerror="this.style.display='none'">
        <h1>Centro Médico Herrera Saenz</h1>
        <p>Corte de Jornada - Dispensario</p>
        <div class="meta">
            Turno:
            <?php echo $shift === 'morning' ? 'Mañana (08:00 - 17:00)' : 'Noche/Madrugada'; ?><br>
            Desde:
            <?php echo date('d/m/Y H:i', strtotime($start_datetime)); ?> |
            Hasta:
            <?php echo date('d/m/Y H:i', strtotime($end_datetime)); ?><br>
            Generado por:
            <?php echo $_SESSION['nombre']; ?> el
            <?php echo date('d/m/Y H:i'); ?>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha/Hora</th>
                <th>Cliente</th>
                <th>NIT</th>
                <th>Método Pago</th>
                <th style="text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($sales)): ?>
                <tr>
                    <td colspan="6" style="text-align: center;">No hay ventas registradas en este turno.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($sales as $sale): ?>
                    <tr>
                        <td>#
                            <?php echo str_pad($sale['id_venta'], 5, '0', STR_PAD_LEFT); ?>
                        </td>
                        <td>
                            <?php echo date('d/m/y H:i', strtotime($sale['fecha_venta'])); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($sale['nombre_cliente']); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($sale['nit_cliente'] ?? 'C/F'); ?>
                        </td>
                        <td>
                            <?php echo $sale['tipo_pago']; ?>
                        </td>
                        <td style="text-align: right;">Q
                            <?php echo number_format($sale['total'], 2); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="total-box">
        <div class="total-row"><span>Efectivo:</span> <span>Q
                <?php echo number_format($totals['Efectivo'], 2); ?>
            </span></div>
        <div class="total-row"><span>Tarjeta:</span> <span>Q
                <?php echo number_format($totals['Tarjeta'], 2); ?>
            </span></div>
        <div class="total-row"><span>Transferencia:</span> <span>Q
                <?php echo number_format($totals['Transferencia'], 2); ?>
            </span></div>
        <div class="total-row grand-total"><span>TOTAL GENERAL:</span> <span>Q
                <?php echo number_format($grand_total, 2); ?>
            </span></div>
    </div>

    <div class="footer">
        Sistema de Gestión Médica - Centro Médico Herrera Saenz
    </div>

    <script>
        // window.onload = function() { window.print(); };
    </script>
</body>

</html>