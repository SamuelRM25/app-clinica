<?php
// dispensary/export_shift_pdf.php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';



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

    $id_hospital = $_SESSION['id_hospital'] ?? 0;

    $stmt = $conn->prepare("
        SELECT v.*, u.nombre as cajero_name
        FROM ventas v
        LEFT JOIN usuarios u ON v.id_usuario = u.idUsuario
        WHERE v.fecha_venta BETWEEN ? AND ? AND v.id_hospital = ?
        ORDER BY v.fecha_venta ASC
    ");
    $stmt->execute([$start_datetime, $end_datetime, $id_hospital]);
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
    error_log('Error en dispensary/export_shift_pdf.php: ' . $e->getMessage());
    die("Error: " . 'Error del servidor.');
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Corte de Jornada - Dispensario</title>
    <link rel="icon" type="image/png" href="../../assets/img/cmhs.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        @page { size: letter; margin: 1cm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            color: #1e293b;
            padding: 0;
            margin: 0;
            background: #f1f5f9;
        }
        .container {
            max-width: 850px;
            margin: 0 auto;
            padding: 24px;
            background: #fff;
            min-height: 100vh;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
        }
        .header {
            display: flex;
            align-items: center;
            gap: 20px;
            border-bottom: 3px solid #0d6efd;
            padding-bottom: 20px;
            margin-bottom: 24px;
        }
        .header .logo {
            width: 72px;
            height: 72px;
            object-fit: contain;
            flex-shrink: 0;
        }
        .header-text h1 {
            font-size: 1.5rem;
            margin: 0 0 4px 0;
            color: #0d6efd;
            font-weight: 700;
        }
        .header-text p {
            margin: 0;
            color: #64748b;
            font-size: 0.95rem;
        }
        .meta {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #0d6efd;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 24px;
            font-size: 0.9rem;
            line-height: 1.6;
        }
        .meta strong {
            color: #475569;
            font-weight: 600;
            margin-right: 6px;
        }
        .meta .meta-value {
            color: #1e293b;
            font-weight: 500;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
            font-size: 0.9rem;
        }
        thead { background: #0d6efd; color: #fff; }
        th {
            padding: 12px 14px;
            text-align: left;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        td {
            padding: 10px 14px;
            border-bottom: 1px solid #e2e8f0;
        }
        tbody tr:nth-child(even) { background: #f8fafc; }
        tbody tr:hover { background: #e0f2fe; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .badge-method {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.78rem;
            font-weight: 600;
        }
        .badge-efectivo { background: #d1fae5; color: #065f46; }
        .badge-tarjeta { background: #dbeafe; color: #1e40af; }
        .badge-transferencia { background: #fef3c7; color: #92400e; }
        .badge-seguro { background: #ede9fe; color: #5b21b6; }
        .total-box {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: #fff;
            border-radius: 10px;
            padding: 24px;
            margin-top: 24px;
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.2);
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 0.95rem;
        }
        .total-row.grand-total {
            border-top: 2px solid rgba(255, 255, 255, 0.3);
            margin-top: 12px;
            padding-top: 14px;
            font-size: 1.5rem;
            font-weight: 700;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
            font-style: italic;
            font-size: 0.95rem;
        }
        .no-print {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-bottom: 20px;
        }
        .no-print button {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .no-print .btn-print { background: #0d6efd; color: #fff; }
        .no-print .btn-print:hover { background: #0a58ca; transform: translateY(-1px); }
        .no-print .btn-close { background: #e2e8f0; color: #475569; }
        .no-print .btn-close:hover { background: #cbd5e1; }
        .footer {
            text-align: center;
            margin-top: 40px;
            color: #94a3b8;
            font-size: 0.8rem;
            border-top: 1px solid #e2e8f0;
            padding-top: 16px;
        }
        @media print {
            body { background: #fff; }
            .container { box-shadow: none; padding: 0; max-width: 100%; }
            .no-print { display: none !important; }
            .header { border-bottom-color: #1e293b; }
            .header-text h1 { color: #1e293b; }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="no-print">
            <button class="btn-print" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir / Guardar PDF</button>
            <button class="btn-close" onclick="window.close()"><i class="bi bi-x-lg"></i> Cerrar</button>
        </div>

        <div class="header">
            <img src="../../assets/img/cmhs.png" class="logo" alt="logo" onerror="this.style.display='none'">
            <div class="header-text">
                <h1>Centro Médico Herrera Saenz</h1>
                <p>Corte de Jornada - Dispensario</p>
            </div>
        </div>

        <div class="meta">
            <div><strong>Turno:</strong> <span class="meta-value"><?php echo $shift === 'morning' ? 'Mañana (08:00 - 17:00)' : 'Noche/Madrugada'; ?></span></div>
            <div><strong>Generado por:</strong> <span class="meta-value"><?php echo htmlspecialchars($_SESSION['nombre']); ?></span></div>
            <div><strong>Desde:</strong> <span class="meta-value"><?php echo date('d/m/Y H:i', strtotime($start_datetime)); ?></span></div>
            <div><strong>Fecha de generación:</strong> <span class="meta-value"><?php echo date('d/m/Y H:i'); ?></span></div>
            <div><strong>Hasta:</strong> <span class="meta-value"><?php echo date('d/m/Y H:i', strtotime($end_datetime)); ?></span></div>
            <div><strong>Total de ventas:</strong> <span class="meta-value"><?php echo count($sales); ?></span></div>
        </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha/Hora</th>
                <th>Cliente</th>
                <th>NIT</th>
                <th>Método Pago</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($sales)): ?>
                    <tr>
                        <td colspan="6" class="empty-state">No hay ventas registradas en este turno.</td>
                    </tr>
            <?php else: ?>
                    <?php foreach ($sales as $sale):
                        $method_class = 'badge-efectivo';
                        if (stripos($sale['tipo_pago'], 'tarjeta') !== false) $method_class = 'badge-tarjeta';
                        elseif (stripos($sale['tipo_pago'], 'transfer') !== false) $method_class = 'badge-transferencia';
                        elseif (stripos($sale['tipo_pago'], 'seguro') !== false) $method_class = 'badge-seguro';
                    ?>
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
                                    <span class="badge-method <?php echo $method_class; ?>">
                                        <?php echo htmlspecialchars($sale['tipo_pago']); ?>
                                    </span>
                                </td>
                                <td class="text-right">Q
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
        <div class="total-row"><span>Seguro Médico:</span> <span>Q
                <?php echo number_format($totals['Seguro Médico'], 2); ?>
            </span></div>
        <div class="total-row grand-total"><span>TOTAL GENERAL:</span> <span>Q
                <?php echo number_format($grand_total, 2); ?>
            </span></div>
    </div>

    <div class="footer">
        Sistema de Gestión Médica - Centro Médico Herrera Saenz
    </div>
    </div>

    <script>
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 500);
        });
    </script>
</body>

</html>