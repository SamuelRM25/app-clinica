<?php
// export_transfers.php - Reporte de Traslados (Restringido) - Centro Médico Herrera Saenz
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer zona horaria
date_default_timezone_set('America/Guatemala');
verify_session();

// Obtener información del usuario
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['tipoUsuario'];

// Solo usuarios específicos pueden generar este reporte
if (!in_array($user_id, [1, 9, 10])) {
    die("Acceso denegado.");
}

// Obtener parámetros de fecha
$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date = $_GET['end'] ?? date('Y-m-d');

// Ajustar para final del día
$start_datetime = $start_date . ' 00:00:00';
$end_datetime = $end_date . ' 23:59:59';

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt_transfers = $conn->prepare("
        SELECT 
            i.nom_medicamento,
            dv.cantidad_vendida,
            v.fecha_venta,
            v.nombre_cliente as destino,
            v.total as valor_traslado,
            CONCAT(u.nombre, ' ', u.apellido) as realizado_por
        FROM ventas v
        JOIN detalle_ventas dv ON v.id_venta = dv.id_venta
        JOIN inventario i ON dv.id_inventario = i.id_inventario
        LEFT JOIN usuarios u ON v.id_usuario = u.idUsuario
        WHERE v.tipo_pago = 'Traslado'
        AND v.fecha_venta BETWEEN ? AND ?
        ORDER BY v.fecha_venta DESC
    ");
    $stmt_transfers->execute([$start_datetime, $end_datetime]);
    $transfers_data = $stmt_transfers->fetchAll(PDO::FETCH_ASSOC);

    $total_transfers_amount = 0;
    foreach ($transfers_data as $transfer) {
        $total_transfers_amount += $transfer['valor_traslado'];
    }

    // Exportación Excel
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"reporte_traslados_" . $start_date . "_al_" . $end_date . ".xls\"");

    echo "<style>th { background-color: #f2f2f2; text-align: left; } .text-right { text-align: right; } .text-center { text-align: center; }</style>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th colspan='6'><h1 style='margin:0;'>Reporte de Traslados (Dispensario)</h1></th></tr>";
    echo "<tr><td colspan='6'><b>Período:</b> " . date('d/m/Y', strtotime($start_date)) . " al " . date('d/m/Y', strtotime($end_date)) . "</td></tr>";
    echo "<tr></tr>";

    echo "<tr>
            <th>Medicamento</th>
            <th class='text-center'>Cant.</th>
            <th>Destino / Paciente</th>
            <th>Realizado por</th>
            <th>Fecha y Hora</th>
            <th class='text-right'>Valor (Q)</th>
          </tr>";

    if (empty($transfers_data)) {
        echo "<tr><td colspan='6' align='center'>No se encontraron registros en este período.</td></tr>";
    } else {
        foreach ($transfers_data as $transfer) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($transfer['nom_medicamento']) . "</td>";
            echo "<td align='center'>" . $transfer['cantidad_vendida'] . "</td>";
            echo "<td>" . htmlspecialchars($transfer['destino']) . "</td>";
            echo "<td>" . htmlspecialchars($transfer['realizado_por']) . "</td>";
            echo "<td>" . date('d/m/Y h:i A', strtotime($transfer['fecha_venta'])) . "</td>";
            echo "<td class='text-right'>Q" . number_format($transfer['valor_traslado'], 2) . "</td>";
            echo "</tr>";
        }
    }

    echo "<tr><td colspan='5' align='right'><b>VALOR TOTAL DE TRASLADOS:</b></td><td class='text-right'><b>Q" . number_format($total_transfers_amount, 2) . "</b></td></tr>";
    echo "</table>";
    exit;

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
