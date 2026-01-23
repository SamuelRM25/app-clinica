<?php
// api/save_procedure_charge.php
session_start();
header('Content-Type: application/json');

require_once '../../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $patient_id = $_POST['patient_id'] ?? null;
    $procedure = $_POST['procedure'] ?? null;
    $amount = $_POST['amount'] ?? 0;

    // Validar datos
    if (!$patient_id || !$procedure || !$amount) {
        throw new Exception('Faltan datos requeridos');
    }

    // Obtener nombre del paciente
    $stmt = $conn->prepare("SELECT CONCAT(nombre, ' ', apellido) as nombre_completo FROM pacientes WHERE id_paciente = ?");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        throw new Exception('Paciente no encontrado');
    }

    // Insertar en procedimientos_menores
    $tipo_pago = $_POST['tipo_pago'] ?? 'Efectivo';
    $stmt = $conn->prepare("
        INSERT INTO procedimientos_menores 
        (id_paciente, nombre_paciente, procedimiento, cobro, tipo_pago, usuario) 
        VALUES (:id_paciente, :nombre_paciente, :procedimiento, :cobro, :tipo_pago, :usuario)
    ");

    $result = $stmt->execute([
        ':id_paciente' => $patient_id,
        ':nombre_paciente' => $patient['nombre_completo'],
        ':procedimiento' => $procedure,
        ':cobro' => $amount,
        ':tipo_pago' => $tipo_pago,
        ':usuario' => $_SESSION['usuario'] ?? 'system'
    ]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Cobro registrado correctamente']);
    } else {
        throw new Exception('Error al guardar en la base de datos');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
