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

try {
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (empty($csrfHeader) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfHeader)) {
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
        exit;
    }
    $database = new Database();
    $conn = $database->getConnection();

    $id_paciente = $_POST['patient_id'] ?? null;
    $tipo_ultrasonido = $_POST['ultrasound_type'] ?? '';
    $cobro = $_POST['amount'] ?? 0;
    $tipo_pago = $_POST['tipo_pago'] ?? 'Efectivo';
    $usuario = $_SESSION['nombre'];
    $id_hospital = $_SESSION['id_hospital'] ?? 0;

    if (!$id_paciente || !$cobro) {
        throw new Exception('Datos incompletos');
    }

    $stmtP = $conn->prepare("SELECT CONCAT(nombre, ' ', apellido) as nombre FROM pacientes WHERE id_paciente = ? AND id_hospital = ?");
    $stmtP->execute([$id_paciente, $id_hospital]);
    $pat = $stmtP->fetch(PDO::FETCH_ASSOC);
    $nombre_paciente = $pat['nombre'] ?? '';

    $stmt = $conn->prepare("
        INSERT INTO ultrasonidos 
        (id_paciente, nombre_paciente, tipo_ultrasonido, cobro, usuario, tipo_pago, id_hospital) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([$id_paciente, $nombre_paciente, $tipo_ultrasonido, $cobro, $usuario, $tipo_pago, $id_hospital]);
    $id = $conn->lastInsertId();

    echo json_encode([
        'status' => 'success',
        'message' => 'Ultrasonido registrado',
        'id' => $id
    ]);

} catch (Exception $e) {
    error_log('Error en php/dashboard/api/save_us_charge.php: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error del servidor.']);
}
