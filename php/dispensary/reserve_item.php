<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';


header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id_inventario = $input['id_inventario'] ?? null;
$cantidad = $input['cantidad'] ?? 0;
$session_id = session_id();

if (!$id_inventario || $cantidad <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Datos insuficientes']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Check availability (stock - other reservations)
    $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

    $stmt = $conn->prepare("
        SELECT cantidad_med - COALESCE(
            (SELECT SUM(cantidad) FROM reservas_inventario WHERE id_inventario = ? AND session_id != ? AND id_hospital = ?), 0
        ) as disponible
        FROM inventario WHERE id_inventario = ? AND id_hospital = ?
    ");
    $stmt->execute([$id_inventario, $session_id, $id_hospital, $id_inventario, $id_hospital]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$res || $res['disponible'] < $cantidad) {
        echo json_encode(['status' => 'error', 'message' => 'Stock insuficiente (reservado por otros)']);
        exit;
    }

    // Upsert reservation for this session
    $stmt = $conn->prepare("SELECT id_reserva FROM reservas_inventario WHERE id_inventario = ? AND session_id = ? AND id_hospital = ?");
    $stmt->execute([$id_inventario, $session_id, $id_hospital]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $conn->prepare("UPDATE reservas_inventario SET cantidad = ?, fecha_reserva = NOW() WHERE id_inventario = ? AND session_id = ? AND id_hospital = ?");
        $stmt->execute([$cantidad, $id_inventario, $session_id, $id_hospital]);
    } else {
        $stmt = $conn->prepare("INSERT INTO reservas_inventario (id_inventario, cantidad, session_id, id_hospital) VALUES (?, ?, ?, ?)");
        $stmt->execute([$id_inventario, $cantidad, $session_id, $id_hospital]);
    }

    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
