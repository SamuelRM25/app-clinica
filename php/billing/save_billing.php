<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';



// Establecer la zona horaria correcta
date_default_timezone_set('America/Guatemala');


verify_session();

// Set content type to JSON
header('Content-Type: application/json');

// Get JSON data
// Validate data logic adjusted for both JSON and POST
$data = [];
if (!empty($_POST)) {
    $data = $_POST;
} else {
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
}

// Validate required fields
$paciente_id = !empty($data['paciente']) ? $data['paciente'] : null;
$paciente_nombre = !empty($data['paciente_nombre']) ? $data['paciente_nombre'] : '';
$cantidad = !empty($data['cantidad']) ? (float) $data['cantidad'] : 0;
$fecha = !empty($data['fecha_consulta']) ? $data['fecha_consulta'] : date('Y-m-d H:i:s');
$id_doctor = !empty($data['id_doctor']) ? $data['id_doctor'] : null;
$tipo_consulta = !empty($data['tipo_consulta']) ? $data['tipo_consulta'] : 'Consulta';

if ((empty($paciente_id) && empty($paciente_nombre)) || empty($cantidad)) {
    echo json_encode(['status' => 'error', 'message' => 'Faltan datos requeridos (Paciente o Monto)']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $id_hospital = $_SESSION['id_hospital'] ?? 0;

    if (empty($paciente_id)) {
        $parts = explode(' ', $paciente_nombre, 2);
        $nombre = $parts[0];
        $apellido = isset($parts[1]) ? $parts[1] : '';

        $stmtP = $conn->prepare("INSERT INTO pacientes (nombre, apellido, fecha_registro, id_hospital) VALUES (?, ?, NOW(), ?)");
        $stmtP->execute([$nombre, $apellido, $id_hospital]);
        $paciente_id = $conn->lastInsertId();
    }

    $tipo_pago = !empty($data['tipo_pago']) ? $data['tipo_pago'] : 'Efectivo';

    $stmt = $conn->prepare("
        INSERT INTO cobros (paciente_cobro, cantidad_consulta, fecha_consulta, id_doctor, tipo_consulta, tipo_pago, id_hospital) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $paciente_id,
        $cantidad,
        $fecha,
        $id_doctor,
        $tipo_consulta,
        $tipo_pago,
        $id_hospital
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Cobro guardado correctamente',
        'id_cobro' => $conn->lastInsertId()
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}