<?php
// api/save_procedure_charge.php
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
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (empty($csrfHeader) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfHeader)) {
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
        exit;
    }
    $database = new Database();
    $conn = $database->getConnection();

    $patient_id = $_POST['patient_id'] ?? null;
    $procedure = $_POST['procedure'] ?? null;
    $amount = $_POST['amount'] ?? 0;
    $id_hospital = $_SESSION['id_hospital'] ?? 0;

    if (!$patient_id || !$procedure || !$amount) {
        throw new Exception('Faltan datos requeridos');
    }

    $stmt = $conn->prepare("SELECT CONCAT(nombre, ' ', apellido) as nombre_completo FROM pacientes WHERE id_paciente = ? AND id_hospital = ?");
    $stmt->execute([$patient_id, $id_hospital]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        throw new Exception('Paciente no encontrado');
    }

    $tipo_pago = $_POST['tipo_pago'] ?? 'Efectivo';
    $stmt = $conn->prepare("
        INSERT INTO procedimientos_menores 
        (id_paciente, nombre_paciente, procedimiento, cobro, tipo_pago, usuario, fecha_procedimiento, id_hospital) 
        VALUES (:id_paciente, :nombre_paciente, :procedimiento, :cobro, :tipo_pago, :usuario, NOW(), :id_hospital)
    ");

    $result = $stmt->execute([
        ':id_paciente' => $patient_id,
        ':nombre_paciente' => $patient['nombre_completo'],
        ':procedimiento' => $procedure,
        ':cobro' => $amount,
        ':tipo_pago' => $tipo_pago,
        ':usuario' => $_SESSION['usuario'] ?? 'system',
        ':id_hospital' => $id_hospital
    ]);

    if ($result) {
        $id_procedimiento = $conn->lastInsertId();
        echo json_encode([
            'status' => 'success',
            'message' => 'Cobro registrado correctamente',
            'id' => $id_procedimiento
        ]);
    } else {
        throw new Exception('Error al guardar en la base de datos');
    }

} catch (Exception $e) {
    error_log('Error en php/dashboard/api/save_procedure_charge.php: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error del servidor.']);
}
