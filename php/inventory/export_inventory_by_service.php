<?php
ob_start();
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

header('Content-Type: text/csv; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    die("No autorizado");
}
verify_session();

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

$servicio = $_GET['servicio'] ?? 'all';

$filename = match ($servicio) {
    'farmacia' => 'inventario_farmacia_' . date('Y-m-d') . '.csv',
    'hospital' => 'inventario_hospitalizacion_' . date('Y-m-d') . '.csv',
    'quirofano' => 'inventario_quirofano_' . date('Y-m-d') . '.csv',
    default => 'inventario_por_servicio_' . date('Y-m-d') . '.csv',
};
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM

try {
    $database = new Database();
    $conn = $database->getConnection();

    $where_stock = '';
    $params = [$id_hospital];
    if ($servicio === 'farmacia') {
        $where_stock = ' AND cantidad_med > 0';
    } elseif ($servicio === 'hospital') {
        $where_stock = ' AND stock_hospital > 0';
    } elseif ($servicio === 'quirofano') {
        $where_stock = ' AND stock_quirofano > 0';
    }

    $stmt = $conn->prepare("
        SELECT
            codigo_barras,
            nom_medicamento,
            mol_medicamento,
            presentacion_med,
            casa_farmaceutica,
            cantidad_med,
            stock_hospital,
            stock_quirofano,
            precio_compra,
            precio_venta,
            precio_hospital,
            precio_medico,
            precio_especial,
            fecha_vencimiento,
            estado
        FROM inventario
        WHERE id_hospital = ? $where_stock
        ORDER BY nom_medicamento ASC
    ");
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Header del CSV
    fputcsv($output, ['REPORTE DE INVENTARIO POR SERVICIO']);
    fputcsv($output, ['Servicio:', $servicio === 'all' ? 'Todos los servicios' : ucfirst($servicio)]);
    fputcsv($output, ['Generado:', date('d/m/Y H:i')]);
    fputcsv($output, []);

    // Columnas principales
    fputcsv($output, [
        'Código de Barras',
        'Medicamento',
        'Molécula',
        'Presentación',
        'Casa Farmacéutica',
        'Stock Farmacia',
        'Stock Hospitalización',
        'Stock Quirófano',
        'Precio Compra',
        'Precio Venta',
        'Precio Hospital',
        'Precio Médico',
        'Precio Especial',
        'Vencimiento',
        'Estado'
    ]);

    foreach ($items as $r) {
        fputcsv($output, [
            (string)($r['codigo_barras'] ?? ''),
            $r['nom_medicamento'],
            $r['mol_medicamento'],
            $r['presentacion_med'],
            $r['casa_farmaceutica'],
            (int)$r['cantidad_med'],
            (int)$r['stock_hospital'],
            (int)$r['stock_quirofano'],
            number_format((float)($r['precio_compra'] ?? 0), 2),
            number_format((float)($r['precio_venta'] ?? 0), 2),
            number_format((float)($r['precio_hospital'] ?? 0), 2),
            number_format((float)($r['precio_medico'] ?? 0), 2),
            number_format((float)($r['precio_especial'] ?? 0), 2),
            $r['fecha_vencimiento'],
            $r['estado']
        ]);
    }

    // Totales al final
    fputcsv($output, []);
    $tot_farm = array_sum(array_column($items, 'cantidad_med'));
    $tot_hosp = array_sum(array_column($items, 'stock_hospital'));
    $tot_quir = array_sum(array_column($items, 'stock_quirofano'));
    fputcsv($output, ['TOTALES', '', '', '', '', $tot_farm, $tot_hosp, $tot_quir, '', '', '', '', '', '', '']);

} catch (Exception $e) {
    error_log('export_inventory_by_service.php error: ' . $e->getMessage());
    fputcsv($output, ['Error: ' . 'Error del servidor.']);
}

fclose($output);
exit;