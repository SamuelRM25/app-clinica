<?php
// dispensary/get_transfer_details.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
    exit;
}

require_once '../../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    $start_date = $_GET['start'] ?? date('Y-m-d');
    $end_date = $_GET['end'] ?? date('Y-m-d');
    $search = isset($_GET['q']) ? trim($_GET['q']) : '';

    // Append time to ensure we get exactly midnight to almost midnight
    $start_datetime = $start_date . ' 00:00:00';
    $end_datetime = $end_date . ' 23:59:59';

    $sql = "
        SELECT 
            i.id_inventario,
            i.nom_medicamento, 
            i.mol_medicamento, 
            dv.cantidad_vendida,
            v.fecha_venta,
            v.nombre_cliente,
            CONCAT(u.nombre, ' ', u.apellido) as realizado_por
        FROM ventas v
        JOIN detalle_ventas dv ON v.id_venta = dv.id_venta
        JOIN inventario i ON dv.id_inventario = i.id_inventario
        LEFT JOIN usuarios u ON v.id_usuario = u.idUsuario
        WHERE v.tipo_pago = 'Traslado'
        AND v.fecha_venta BETWEEN ? AND ?
    ";
    
    $params = [$start_datetime, $end_datetime];

    if ($search !== '') {
        $sql .= " AND (i.nom_medicamento LIKE ? OR i.mol_medicamento LIKE ? OR i.codigo_barras LIKE ?)";
        $term = "%$search%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $sql .= " ORDER BY v.fecha_venta DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'details' => $details,
        'period' => [
            'start' => $start_datetime,
            'end' => $end_datetime
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
