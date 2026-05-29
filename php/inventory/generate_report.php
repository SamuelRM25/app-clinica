<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

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
    $stmt = $conn->prepare("SELECT * FROM inventario WHERE cantidad_med > 0 AND id_hospital = ? ORDER BY nom_medicamento");
    $stmt->execute([$id_hospital]);
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
error_log('Error en inventory/generate_report.php: ' . $e->getMessage());
        fputcsv($output, array('Error: Error del servidor.'));
}
fclose($output);
exit;