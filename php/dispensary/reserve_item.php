<?php
session_start();
require_once '../../config/database.php';

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
    $stmt = $conn->prepare("
        SELECT cantidad_med - COALESCE(
            (SELECT SUM(cantidad) FROM reservas_inventario WHERE id_inventario = ? AND session_id != ?), 0
        ) as disponible
        FROM inventario WHERE id_inventario = ?
    ");
    $stmt->execute([$id_inventario, $session_id, $id_inventario]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$res || $res['disponible'] < $cantidad) {
        echo json_encode(['status' => 'error', 'message' => 'Stock insuficiente (reservado por otros)']);
        exit;
    }

    // Upsert reservation for this session
    $stmt = $conn->prepare("SELECT id_reserva FROM reservas_inventario WHERE id_inventario = ? AND session_id = ?");
    $stmt->execute([$id_inventario, $session_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $conn->prepare("UPDATE reservas_inventario SET cantidad = ?, fecha_reserva = NOW() WHERE id_inventario = ? AND session_id = ?");
        $stmt->execute([$cantidad, $id_inventario, $session_id]);
    } else {
        $stmt = $conn->prepare("INSERT INTO reservas_inventario (id_inventario, cantidad, session_id) VALUES (?, ?, ?)");
        $stmt->execute([$id_inventario, $cantidad, $session_id]);
    }

    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
