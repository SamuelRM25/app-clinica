<?php
ob_start();
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['error' => 'Sesión no iniciada']);
    exit;
}

require_once '../../../config/database.php';

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);
if ($id_hospital === 0) {
    ob_clean();
    echo json_encode(['error' => 'Hospital no identificado']);
    exit;
}

$servicio = $_GET['servicio'] ?? 'all';

ob_clean();

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Total general (para KPIs)
    $stmt = $conn->prepare("
        SELECT
            SUM(cantidad_med) AS total_farmacia,
            SUM(stock_hospital) AS total_hospital,
            SUM(stock_quirofano) AS total_quirofano,
            COUNT(*) AS total_items,
            SUM(CASE WHEN cantidad_med > 0 THEN 1 ELSE 0 END) AS items_farmacia,
            SUM(CASE WHEN stock_hospital > 0 THEN 1 ELSE 0 END) AS items_hospital,
            SUM(CASE WHEN stock_quirofano > 0 THEN 1 ELSE 0 END) AS items_quirofano
        FROM inventario
        WHERE id_hospital = ?
    ");
    $stmt->execute([$id_hospital]);
    $kpis = $stmt->fetch(PDO::FETCH_ASSOC);

    // Items por servicio
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
            id_inventario,
            codigo_barras,
            nom_medicamento,
            mol_medicamento,
            presentacion_med,
            casa_farmaceutica,
            cantidad_med,
            stock_hospital,
            stock_quirofano,
            precio_venta,
            precio_hospital,
            precio_medico,
            precio_especial,
            precio_compra,
            fecha_vencimiento,
            estado
        FROM inventario
        WHERE id_hospital = ? $where_stock
        ORDER BY nom_medicamento ASC
    ");
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'servicio' => $servicio,
        'kpis' => [
            'total_items' => (int)($kpis['total_items'] ?? 0),
            'total_farmacia' => (int)($kpis['total_farmacia'] ?? 0),
            'total_hospital' => (int)($kpis['total_hospital'] ?? 0),
            'total_quirofano' => (int)($kpis['total_quirofano'] ?? 0),
            'items_farmacia' => (int)($kpis['items_farmacia'] ?? 0),
            'items_hospital' => (int)($kpis['items_hospital'] ?? 0),
            'items_quirofano' => (int)($kpis['items_quirofano'] ?? 0),
        ],
        'items' => $items
    ]);

} catch (Exception $e) {
    error_log('get_inventory_by_service.php error: ' . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Error al cargar inventario por servicio',
        'debug' => ($_SESSION['tipoUsuario'] ?? '') === 'admin' ? $e->getMessage() : null
    ]);
}