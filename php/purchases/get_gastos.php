<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/multitenant.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

    $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
    $fecha_fin    = $_GET['fecha_fin'] ?? date('Y-m-d');

    $stmt = $conn->prepare("
        SELECT g.*, CONCAT(u.nombre, ' ', u.apellido) AS registrado_por
        FROM gastos g
        LEFT JOIN usuarios u ON g.created_by = u.idUsuario
        WHERE g.fecha BETWEEN ? AND ?
          AND g.id_hospital = ?
        ORDER BY g.fecha DESC, g.created_at DESC
    ");
    $stmt->execute([$fecha_inicio, $fecha_fin, $id_hospital]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_gastos = 0;
    foreach ($rows as &$row) {
        $row['total'] = (float)$row['total'];
        $row['subtotal'] = (float)$row['subtotal'];
        $row['cantidad'] = (int)$row['cantidad'];
        $total_gastos += $row['total'];
    }
    unset($row);

    echo json_encode([
        'success' => true,
        'rows' => $rows,
        'total_gastos' => $total_gastos,
        'count' => count($rows),
    ]);

} catch (Exception $e) {
    error_log('Error en purchases/get_gastos.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
