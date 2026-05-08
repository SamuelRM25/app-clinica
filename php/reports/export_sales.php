<?php
// export_sales.php - Reporte de Rentabilidad - Centro Médico Herrera Saenz
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer zona horaria
date_default_timezone_set('America/Guatemala');
verify_session();

// Solo usuarios autorizados (admin o farmacia si aplicara, aquí restringimos a admin)
if ($_SESSION['tipoUsuario'] !== 'admin') {
    die("Acceso denegado.");
}

// Obtener parámetros
$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date = $_GET['end'] ?? date('Y-m-d');
$format = $_GET['format'] ?? 'print'; // print, csv, excel

// Ajustar horas
$start_datetime = $start_date . ' 00:00:00';
$end_datetime = $end_date . ' 23:59:59';

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Consulta principal
    $stmt = $conn->prepare("
        SELECT 
            i.nom_medicamento,
            i.codigo_barras,
            SUM(dv.cantidad_vendida) as cantidad_total,
            SUM(dv.cantidad_vendida * dv.precio_unitario) as total_venta,
            SUM(dv.cantidad_vendida * COALESCE(pi.unit_cost, 0)) as total_costo
        FROM detalle_ventas dv
        JOIN ventas v ON dv.id_venta = v.id_venta
        JOIN inventario i ON dv.id_inventario = i.id_inventario
        LEFT JOIN purchase_items pi ON i.id_purchase_item = pi.id
        WHERE v.fecha_venta BETWEEN ? AND ?
        AND v.tipo_pago != 'Traslado'
        AND dv.precio_unitario > 0
        AND COALESCE(pi.unit_cost, 0) > 0
        GROUP BY i.id_inventario, i.nom_medicamento, i.codigo_barras
        ORDER BY total_venta DESC
    ");

    $stmt->execute([$start_datetime, $end_datetime]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Totales
    $total_revenue = 0;
    $total_cost = 0;
    foreach ($data as $row) {
        $total_revenue += $row['total_venta'];
        $total_cost += $row['total_costo'];
    }
    $total_profit = $total_revenue - $total_cost;
    $total_margin = $total_revenue > 0 ? ($total_profit / $total_revenue) * 100 : 0;

    // Exportación CSV
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="rentabilidad_farmacia_' . $start_date . '_al_' . $end_date . '.csv"');
        $output = fopen('php://output', 'w');

        // BOM para Excel
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Encabezados
        fputcsv($output, ['Codigo', 'Medicamento', 'Cantidad', 'Precio Venta Promedio', 'Costo Promedio', 'Total Venta', 'Total Costo', 'Ganancia', '% Margen']);

        foreach ($data as $row) {
            $ganancia = $row['total_venta'] - $row['total_costo'];
            $margen = $row['total_venta'] > 0 ? ($ganancia / $row['total_venta']) * 100 : 0;
            $p_venta = $row['cantidad_total'] > 0 ? $row['total_venta'] / $row['cantidad_total'] : 0;
            $p_costo = $row['cantidad_total'] > 0 ? $row['total_costo'] / $row['cantidad_total'] : 0;

            fputcsv($output, [
                $row['codigo_barras'],
                $row['nom_medicamento'],
                $row['cantidad_total'],
                number_format($p_venta, 2, '.', ''),
                number_format($p_costo, 2, '.', ''),
                number_format($row['total_venta'], 2, '.', ''),
                number_format($row['total_costo'], 2, '.', ''),
                number_format($ganancia, 2, '.', ''),
                number_format($margen, 2, '.', '') . '%'
            ]);
        }

        fclose($output);
        exit;
    }

    // Exportación Excel (HTML Table)
    if ($format === 'excel') {
        header("Content-Type: application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"rentabilidad_farmacia_" . $start_date . ".xls\"");
        echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';

        echo "<table border='1'>";
        echo "<tr><th colspan='9' style='background-color: #f0f0f0; font-size: 16px;'>Reporte de Rentabilidad Farmacia ($start_date al $end_date)</th></tr>";
        echo "<tr>
                <th>Código</th>
                <th>Medicamento</th>
                <th>Cantidad</th>
                <th>P. Venta Prom.</th>
                <th>Costo Prom.</th>
                <th>Total Venta</th>
                <th>Total Costo</th>
                <th>Ganancia</th>
                <th>% Margen</th>
              </tr>";

        foreach ($data as $row) {
            $ganancia = $row['total_venta'] - $row['total_costo'];
            $margen = $row['total_venta'] > 0 ? ($ganancia / $row['total_venta']) * 100 : 0;
            $p_venta = $row['cantidad_total'] > 0 ? $row['total_venta'] / $row['cantidad_total'] : 0;
            $p_costo = $row['cantidad_total'] > 0 ? $row['total_costo'] / $row['cantidad_total'] : 0;

            echo "<tr>
                <td>{$row['codigo_barras']}</td>
                <td>{$row['nom_medicamento']}</td>
                <td>{$row['cantidad_total']}</td>
                <td>Q" . number_format($p_venta, 2) . "</td>
                <td>Q" . number_format($p_costo, 2) . "</td>
                <td>Q" . number_format($row['total_venta'], 2) . "</td>
                <td>Q" . number_format($row['total_costo'], 2) . "</td>
                <td style='color: " . ($ganancia >= 0 ? 'green' : 'red') . "'>Q" . number_format($ganancia, 2) . "</td>
                <td>" . number_format($margen, 1) . "%</td>
            </tr>";
        }

        echo "<tr><th colspan='5'>TOTALES</th>";
        echo "<th>Q" . number_format($total_revenue, 2) . "</th>";
        echo "<th>Q" . number_format($total_cost, 2) . "</th>";
        echo "<th>Q" . number_format($total_profit, 2) . "</th>";
        echo "<th>" . number_format($total_margin, 1) . "%</th></tr>";
        echo "</table>";
        exit;
    }

    // Vista de Impresión (PDF)
    // Usamos HTML limpio que el navegador imprime bien
    ?>
    <!DOCTYPE html>
    <html lang="es">

    <head>
        <meta charset="UTF-8">
        <title>Reporte Rentabilidad - CMHS</title>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                font-size: 12px;
                color: #333;
            }

            .header {
                text-align: center;
                margin-bottom: 20px;
                border-bottom: 2px solid #ccc;
                padding-bottom: 10px;
            }

            h1 {
                margin: 0;
                font-size: 18px;
            }

            h2 {
                margin: 5px 0;
                font-size: 14px;
                font-weight: normal;
                color: #666;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }

            th,
            td {
                border: 1px solid #ddd;
                padding: 6px;
                text-align: left;
            }

            th {
                background-color: #f2f2f2;
                font-weight: bold;
            }

            .text-right {
                text-align: right;
            }

            .totals {
                font-weight: bold;
                background-color: #e9ecef;
            }

            .print-btn {
                padding: 10px 20px;
                background: #007bff;
                color: white;
                border: none;
                cursor: pointer;
                border-radius: 4px;
            }

            @media print {
                .no-print {
                    display: none;
                }

                body {
                    margin: 0;
                    padding: 0;
                }
            }
        </style>
    </head>

    <body>
        <div class="no-print" style="margin-bottom: 20px;">
            <button onclick="window.print()" class="print-btn">🖨️ Imprimir / Guardar como PDF</button>
            <button onclick="window.close()" class="print-btn" style="background: #6c757d;">Cerrar</button>
        </div>

        <div class="header">
            <h1>Centro Médico Herrera Saenz</h1>
            <h2>Reporte de Rentabilidad en Farmacia</h2>
            <p>Periodo:
                <?php echo date('d/m/Y', strtotime($start_date)); ?> al
                <?php echo date('d/m/Y', strtotime($end_date)); ?>
            </p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Medicamento</th>
                    <th class="text-right">Cant.</th>
                    <th class="text-right">P. Venta</th>
                    <th class="text-right">Costo</th>
                    <th class="text-right">Total Venta</th>
                    <th class="text-right">Total Costo</th>
                    <th class="text-right">Ganancia</th>
                    <th class="text-right">%</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row):
                    $ganancia = $row['total_venta'] - $row['total_costo'];
                    $margen = $row['total_venta'] > 0 ? ($ganancia / $row['total_venta']) * 100 : 0;
                    $p_venta = $row['cantidad_total'] > 0 ? $row['total_venta'] / $row['cantidad_total'] : 0;
                    $p_costo = $row['cantidad_total'] > 0 ? $row['total_costo'] / $row['cantidad_total'] : 0;
                    ?>
                    <tr>
                        <td>
                            <?php echo $row['codigo_barras']; ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($row['nom_medicamento']); ?>
                        </td>
                        <td class="text-right">
                            <?php echo $row['cantidad_total']; ?>
                        </td>
                        <td class="text-right">Q
                            <?php echo number_format($p_venta, 2); ?>
                        </td>
                        <td class="text-right">Q
                            <?php echo number_format($p_costo, 2); ?>
                        </td>
                        <td class="text-right">Q
                            <?php echo number_format($row['total_venta'], 2); ?>
                        </td>
                        <td class="text-right">Q
                            <?php echo number_format($row['total_costo'], 2); ?>
                        </td>
                        <td class="text-right">Q
                            <?php echo number_format($ganancia, 2); ?>
                        </td>
                        <td class="text-right">
                            <?php echo number_format($margen, 1); ?>%
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr class="totals">
                    <td colspan="5" class="text-right">TOTALES GENERALES</td>
                    <td class="text-right">Q
                        <?php echo number_format($total_revenue, 2); ?>
                    </td>
                    <td class="text-right">Q
                        <?php echo number_format($total_cost, 2); ?>
                    </td>
                    <td class="text-right">Q
                        <?php echo number_format($total_profit, 2); ?>
                    </td>
                    <td class="text-right">
                        <?php echo number_format($total_margin, 1); ?>%
                    </td>
                </tr>
            </tbody>
        </table>

        <div style="font-size: 10px; color: #888; text-align: center; margin-top: 30px;">
            Generado por Sistema CMHS el
            <?php echo date('d/m/Y H:i:s'); ?> por
            <?php echo $_SESSION['nombre']; ?>
        </div>

        <script>
            // Auto-print on load if desired, or just let user click button
            // window.onload = function() { window.print(); }
        </script>
    </body>

    </html>
    <?php

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}
?>