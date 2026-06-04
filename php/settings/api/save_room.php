<?php
// settings/api/save_room.php
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['tipoUsuario'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $id_habitacion = $_POST['id_habitacion'] ?? '';
    $numero_habitacion = $_POST['numero_habitacion'] ?? '';
    $tipo_habitacion = $_POST['tipo_habitacion'] ?? 'Privada';
    $tarifa_por_noche = (float) ($_POST['tarifa_por_noche'] ?? 0);
    $estado = $_POST['estado'] ?? 'Activa';

    if (empty($numero_habitacion)) {
        echo json_encode(['success' => false, 'message' => 'El número de habitación es obligatorio']);
        exit;
    }

    $id_hospital = $_SESSION['id_hospital'] ?? 0;

    if (empty($id_habitacion)) {
        $stmt_check = $conn->prepare("SELECT id_habitacion FROM habitaciones WHERE numero_habitacion = ? AND id_hospital = ?");
        $stmt_check->execute([$numero_habitacion, $id_hospital]);
        if ($stmt_check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Ya existe una habitación con el número ' . $numero_habitacion]);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO habitaciones (numero_habitacion, tipo_habitacion, tarifa_por_noche, estado, id_hospital) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$numero_habitacion, $tipo_habitacion, $tarifa_por_noche, $estado, $id_hospital]);
        $newId = $conn->lastInsertId();

        audit_log('create', 'rooms', "Habitación creada: #$numero_habitacion ($tipo_habitacion)", [
            'table_name' => 'habitaciones',
            'record_id' => (int)$newId,
            'new_data' => [
                'numero_habitacion' => $numero_habitacion,
                'tipo_habitacion' => $tipo_habitacion,
                'tarifa_por_noche' => $tarifa_por_noche,
                'estado' => $estado
            ]
        ]);

        echo json_encode(['success' => true, 'message' => 'Habitación creada correctamente']);
    } else {
        // Fetch old data for audit
        $fetchStmt = $conn->prepare("SELECT numero_habitacion, tipo_habitacion, tarifa_por_noche, estado FROM habitaciones WHERE id_habitacion = ? AND id_hospital = ?");
        $fetchStmt->execute([$id_habitacion, $id_hospital]);
        $oldData = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("UPDATE habitaciones SET numero_habitacion = ?, tipo_habitacion = ?, tarifa_por_noche = ?, estado = ? WHERE id_habitacion = ? AND id_hospital = ?");
        $stmt->execute([$numero_habitacion, $tipo_habitacion, $tarifa_por_noche, $estado, $id_habitacion, $id_hospital]);

        audit_log('update', 'rooms', "Habitación actualizada: #$numero_habitacion (ID: $id_habitacion)", [
            'table_name' => 'habitaciones',
            'record_id' => (int)$id_habitacion,
            'old_data' => $oldData,
            'new_data' => [
                'numero_habitacion' => $numero_habitacion,
                'tipo_habitacion' => $tipo_habitacion,
                'tarifa_por_noche' => $tarifa_por_noche,
                'estado' => $estado
            ]
        ]);

        echo json_encode(['success' => true, 'message' => 'Habitación actualizada correctamente']);
    }

} catch (Exception $e) {
    error_log("save_room error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al guardar la habitación.']);
}
?>