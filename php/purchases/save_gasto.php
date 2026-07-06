<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

csrf_token();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

    $data = json_decode(file_get_contents('php://input'), true);

    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($csrfHeader) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfHeader)) {
        throw new Exception('Token CSRF inválido');
    }

    if (!$data || empty($data['descripcion']) || !isset($data['cantidad']) || !isset($data['subtotal'])) {
        throw new Exception('Datos incompletos (descripcion, cantidad, subtotal requeridos)');
    }

    $descripcion = trim($data['descripcion']);
    $cantidad    = (int)($data['cantidad'] ?? 1);
    $subtotal    = (float)($data['subtotal'] ?? 0);
    $total       = (float)($data['total'] ?? ($cantidad * $subtotal));
    $fecha       = $data['fecha'] ?? date('Y-m-d');

    if ($cantidad < 1) $cantidad = 1;
    if ($subtotal < 0) $subtotal = 0;
    if ($total < 0) $total = $cantidad * $subtotal;

    $stmt = $conn->prepare("INSERT INTO gastos (descripcion, cantidad, subtotal, total, fecha, created_by, id_hospital) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $descripcion,
        $cantidad,
        $subtotal,
        $total,
        $fecha,
        $_SESSION['user_id'],
        $id_hospital
    ]);
    $gastoId = $conn->lastInsertId();

    audit_log('create', 'gastos', "Gasto #$gastoId - {$descripcion} - Q{$total}", [
        'table_name' => 'gastos',
        'record_id' => (int)$gastoId,
        'new_data' => [
            'descripcion' => $descripcion,
            'cantidad' => $cantidad,
            'subtotal' => $subtotal,
            'total' => $total,
            'fecha' => $fecha,
        ]
    ]);

    echo json_encode(['success' => true, 'id' => $gastoId]);

} catch (Exception $e) {
    error_log('Error en purchases/save_gasto.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
