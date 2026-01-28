<?php
session_start();
header('Content-Type: application/json');
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $id_paciente = $_POST['id_paciente'] ?? null;
    $id_doctor = $_POST['id_doctor'] ?? null;
    $precio = $_POST['precio'] ?? 0;
    $tipo_pago = $_POST['tipo_pago'] ?? 'Efectivo';

    if (!$id_paciente || !$precio) {
        throw new Exception('Faltan datos requeridos');
    }

    $stmt = $conn->prepare("
        INSERT INTO electrocardiogramas 
        (id_paciente, id_doctor, precio, estado_pago, realizado_por) 
        VALUES (?, ?, ?, 'Pagado', ?)
    ");

    $stmt->execute([
        $id_paciente,
        $id_doctor ?: null,
        $precio,
        $_SESSION['user_id']
    ]);

    $id = $conn->lastInsertId();

    echo json_encode([
        'status' => 'success',
        'message' => 'Electrocardiograma registrado',
        'id' => $id
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
