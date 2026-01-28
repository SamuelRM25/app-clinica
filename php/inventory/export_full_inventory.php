<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar sesión y permisos
if (!isset($_SESSION['user_id'])) {
    die("No autorizado");
}
verify_session();

// Configurar cabeceras para descarga de archivo CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=inventario_completo_' . date('Y-m-d') . '.csv');

// Abrir salida
$output = fopen('php://output', 'w');

// Agregar BOM para que Excel reconozca caracteres especiales (UTF-8)
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Encabezados del CSV (Todas las columnas importantes)
fputcsv($output, array(
    'ID',
    'Codigo de Barras',
    'Nombre del Medicamento',
    'Molecula',
    'Presentacion',
    'Casa Farmaceutica',
    'Cantidad',
    'Fecha Adquisicion',
    'Fecha Vencimiento',
    'Estado',
    'P. Compra (Item)',
    'Factura Compra',
    'Precio Venta',
    'Precio Hospital',
    'Precio Medico',
    'Stock Hospital'
));

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener todo el inventario con datos de compra si existen
    $query = "
        SELECT i.*, ph.document_number, pi.unit_cost 
        FROM inventario i
        LEFT JOIN purchase_items pi ON i.id_purchase_item = pi.id
        LEFT JOIN purchase_headers ph ON pi.purchase_header_id = ph.id
        ORDER BY i.nom_medicamento ASC
    ";
    $stmt = $conn->query($query);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, array(
            $row['id_inventario'],
            (string) $row['codigo_barras'], // Asegurar que el código de barras sea string
            $row['nom_medicamento'],
            $row['mol_medicamento'],
            $row['presentacion_med'],
            $row['casa_farmaceutica'],
            $row['cantidad_med'],
            $row['fecha_adquisicion'],
            $row['fecha_vencimiento'],
            $row['estado'],
            $row['unit_cost'] ?? $row['precio_compra'],
            $row['document_number'] ?? 'N/A',
            $row['precio_venta'],
            $row['precio_hospital'],
            $row['precio_medico'],
            $row['stock_hospital']
        ));
    }
} catch (Exception $e) {
    fputcsv($output, array('Error: ' . $e->getMessage()));
}

fclose($output);
exit;
?>