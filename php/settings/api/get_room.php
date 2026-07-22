<?php
require_once '../../../includes/functions.php';
start_app_session();
require_once '../../../config/database.php';
require_once '../../../includes/multitenant.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['tipoUsuario'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT id_habitacion, numero_habitacion, tipo_habitacion, tarifa_por_noche, piso, estado,
               descripcion, tiene_bano, tiene_tv, tiene_aire_acondicionado, capacidad_maxima, id_hospital
        FROM habitaciones
        WHERE id_habitacion = ? AND id_hospital = ?
    ");
    $stmt->execute([$id, $id_hospital]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$room) {
        echo json_encode(['success' => false, 'message' => 'Habitación no encontrada']);
        exit;
    }

    $stmtBeds = $conn->prepare("
        SELECT id_cama, numero_cama, descripcion, estado
        FROM camas
        WHERE id_habitacion = ? AND id_hospital = ?
        ORDER BY numero_cama ASC
    ");
    $stmtBeds->execute([$id, $id_hospital]);
    $camas = $stmtBeds->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'room' => $room,
        'camas' => $camas,
    ]);
} catch (Exception $e) {
    error_log('get_room error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}