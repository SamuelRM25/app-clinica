<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=reporte_inventario.csv');

$output = fopen('php://output', 'w');

// Encabezados del CSV
fputcsv($output, array(
    'Nombre del Medicamento',
    'Molécula',
    'Presentación',
    'Casa Farmacéutica',
    'Cantidad',
    'Fecha Adquisición',
    'Fecha Vencimiento'
));

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Solo medicamentos con stock
    $stmt = $conn->query("SELECT * FROM inventario WHERE cantidad_med > 0 ORDER BY nom_medicamento");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, array(
            $row['nom_medicamento'],
            $row['mol_medicamento'],
            $row['presentacion_med'],
            $row['casa_farmaceutica'],
            $row['cantidad_med'],
            date('d/m/Y', strtotime($row['fecha_adquisicion'])),
            date('d/m/Y', strtotime($row['fecha_vencimiento']))
        ));
    }
} catch (Exception $e) {
    // Si hay error, lo mostramos en el CSV
    fputcsv($output, array('Error: ' . $e->getMessage()));
}
fclose($output);
exit;