<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$id_inventario = $input['id_inventario'] ?? null;
$session_id = session_id();

try {
    $database = new Database();
    $conn = $database->getConnection();

    if ($id_inventario) {
        $stmt = $conn->prepare("DELETE FROM reservas_inventario WHERE id_inventario = ? AND session_id = ?");
        $stmt->execute([$id_inventario, $session_id]);
    } else {
        // Clear all for this session
        $stmt = $conn->prepare("DELETE FROM reservas_inventario WHERE session_id = ?");
        $stmt->execute([$session_id]);
    }

    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
