<?php
session_start();
header('Content-Type: application/json');
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/multitenant.php';

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
    $id_hospital = $_SESSION['id_hospital'] ?? 0;

    if (!$id_paciente || !$precio) {
        throw new Exception('Faltan datos requeridos');
    }

    $stmt = $conn->prepare("
        INSERT INTO electrocardiogramas 
        (id_paciente, id_doctor, precio, estado_pago, tipo_pago, realizado_por, fecha_realizado, id_hospital) 
        VALUES (?, ?, ?, 'Pagado', ?, ?, NOW(), ?)
    ");

    $stmt->execute([
        $id_paciente,
        $id_doctor ?: null,
        $precio,
        $tipo_pago,
        $_SESSION['user_id'],
        $id_hospital
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
