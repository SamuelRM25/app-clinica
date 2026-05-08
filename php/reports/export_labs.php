<?php
// export_labs.php - Reporte de Laboratorios - Centro Médico Herrera Saenz
// Versión 1.0 - Integrado al Diseño del Dashboard Principal
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer zona horaria
date_default_timezone_set('America/Guatemala');
verify_session();

// Obtener información del usuario
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['tipoUsuario'];
$user_name = $_SESSION['nombre'];
$user_specialty = $_SESSION['especialidad'] ?? 'Profesional Médico';

// Solo administradores pueden generar este reporte
if ($user_type !== 'admin') {
    die("Acceso denegado.");
}

// Obtener parámetros de fecha y formato
$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date = $_GET['end'] ?? date('Y-m-d');
$format = $_GET['format'] ?? 'excel'; // html, csv, excel, word

// Ajustar para final del día
$start_datetime = $start_date . ' 00:00:00';
$end_datetime = $end_date . ' 23:59:59';

try {
    $database = new Database();
    $conn = $database->getConnection();

    // ============ CÁLCULO DE MÉTRICAS ============

    $stmt_labs_detail = $conn->prepare("
        SELECT 
            p.nombre as paciente_nombre,
            p.apellido as paciente_apellido,
            cp.nombre_prueba,
            DATE(ol.fecha_orden) as fecha,
            TIME(ol.fecha_orden) as hora,
            cp.precio
        FROM ordenes_laboratorio ol
        JOIN orden_pruebas op ON ol.id_orden = op.id_orden
        JOIN catalogo_pruebas cp ON op.id_prueba = cp.id_prueba
        JOIN pacientes p ON ol.id_paciente = p.id_paciente
        WHERE ol.fecha_orden BETWEEN ? AND ?
        AND op.estado != 'Devuelto'
        ORDER BY ol.fecha_orden DESC
    ");
    $stmt_labs_detail->execute([$start_datetime, $end_datetime]);
    $labs_detail_data = $stmt_labs_detail->fetchAll(PDO::FETCH_ASSOC);

    $total_labs_report = 0;
    foreach ($labs_detail_data as $lab) {
        $total_labs_report += $lab['precio'];
    }

    // ============ PREPARAR DATOS PARA EXPORTACIÓN ============

    // Exportación CSV
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="reporte_laboratorios_' . $start_date . '_al_' . $end_date . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Reporte de Laboratorios Detallado']);
        fputcsv($output, ['Periodo:', $start_date . ' al ' . $end_date]);
        fputcsv($output, []);
        fputcsv($output, ['Paciente', 'Examen (Prueba)', 'Fecha', 'Hora', 'Precio (Q)']);

        foreach ($labs_detail_data as $lab) {
            fputcsv($output, [
                $lab['paciente_nombre'] . ' ' . $lab['paciente_apellido'],
                $lab['nombre_prueba'],
                date('d/m/Y', strtotime($lab['fecha'])),
                date('h:i A', strtotime($lab['hora'])),
                number_format($lab['precio'], 2)
            ]);
        }

        fputcsv($output, []);
        fputcsv($output, ['Total General', '', '', '', number_format($total_labs_report, 2)]);

        fclose($output);
        exit;
    }

    // Exportación Excel o Word
    if ($format === 'excel' || $format === 'word') {
        $ext = ($format === 'excel' ? ".xls" : ".doc");
        header("Content-Type: application/vnd.ms-" . ($format === 'excel' ? "excel" : "word"));
        header("Content-Disposition: attachment; filename=\"reporte_laboratorios_" . $start_date . "_al_" . $end_date . $ext . "\"");

        echo "<style>th { background-color: #f2f2f2; text-align: left; } .text-right { text-align: right; }</style>";
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th colspan='5'><h1 style='margin:0;'>Reporte Detallado de Laboratorios</h1></th></tr>";
        echo "<tr><td colspan='5'><b>Período:</b> " . date('d/m/Y', strtotime($start_date)) . " al " . date('d/m/Y', strtotime($end_date)) . "</td></tr>";
        echo "<tr></tr>";

        echo "<tr>
                <th>Paciente</th>
                <th>Examen (Prueba)</th>
                <th>Fecha</th>
                <th>Hora</th>
                <th class='text-right'>Precio (Q)</th>
              </tr>";

        if (empty($labs_detail_data)) {
            echo "<tr><td colspan='5' align='center'>No se encontraron registros en este período.</td></tr>";
        } else {
            foreach ($labs_detail_data as $lab) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($lab['paciente_nombre'] . ' ' . $lab['paciente_apellido']) . "</td>";
                echo "<td>" . htmlspecialchars($lab['nombre_prueba']) . "</td>";
                echo "<td>" . date('d/m/Y', strtotime($lab['fecha'])) . "</td>";
                echo "<td>" . date('h:i A', strtotime($lab['hora'])) . "</td>";
                echo "<td class='text-right'>Q" . number_format($lab['precio'], 2) . "</td>";
                echo "</tr>";
            }
        }

        echo "<tr><td colspan='4' align='right'><b>TOTAL GENERADO:</b></td><td class='text-right'><b>Q" . number_format($total_labs_report, 2) . "</b></td></tr>";
        echo "</table>";
        exit;
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>