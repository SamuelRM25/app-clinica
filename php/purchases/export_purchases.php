<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar sesión y permisos (solo admin)
if (!isset($_SESSION['user_id']) || $_SESSION['tipoUsuario'] !== 'admin') {
    die("No autorizado");
}
verify_session();

// Configurar cabeceras para descarga de archivo CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=respaldo_compras_' . date('Y-m-d') . '.csv');

// Abrir salida
$output = fopen('php://output', 'w');

// Agregar BOM para que Excel reconozca caracteres especiales (UTF-8)
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Encabezados del CSV (Capa plana: Datos de Compra + Datos de Item)
fputcsv($output, array(
    'Compra ID',
    'Fecha Compra',
    'Proveedor',
    'Tipo Documento',
    'No. Documento',
    'Total Compra',
    'Saldo',
    'Item ID',
    'Producto',
    'Molecula',
    'Presentacion',
    'Casa Farmaceutica',
    'Cantidad',
    'Costo Unitario',
    'Precio Venta',
    'Subtotal Item',
    'Estado Recepcion'
));

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Query unificado para Respaldo (Headers + Items)
    $query = "SELECT 
                ph.id as compra_id, ph.purchase_date, ph.provider_name, ph.document_type, ph.document_number, 
                ph.total_amount as total_compra, (ph.total_amount - COALESCE(ph.paid_amount, 0)) as balance,
                pi.id as item_id, pi.product_name, pi.molecule, pi.presentation, pi.pharmaceutical_house, 
                pi.quantity, pi.unit_cost, pi.sale_price, pi.subtotal as subtotal_item, pi.status as item_status
              FROM purchase_headers ph
              JOIN purchase_items pi ON ph.id = pi.purchase_header_id
              ORDER BY ph.id DESC, pi.id ASC";

    $stmt = $conn->query($query);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, array(
            $row['compra_id'],
            $row['purchase_date'],
            $row['provider_name'],
            $row['document_type'],
            (string) $row['document_number'],
            $row['total_compra'],
            $row['balance'],
            $row['item_id'],
            $row['product_name'],
            $row['molecule'],
            $row['presentation'],
            $row['pharmaceutical_house'],
            $row['quantity'],
            $row['unit_cost'],
            $row['sale_price'],
            $row['subtotal_item'],
            $row['item_status']
        ));
    }
} catch (Exception $e) {
    fputcsv($output, array('Error: ' . $e->getMessage()));
}

fclose($output);
exit;
?>