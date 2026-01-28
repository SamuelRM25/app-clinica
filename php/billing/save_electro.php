<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

date_default_timezone_set('America/Guatemala');
verify_session();

header('Content-Type: application/json');

$data = [];
if (!empty($_POST)) {
    $data = $_POST;
} else {
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
}

$paciente_id = !empty($data['paciente']) ? $data['paciente'] : null;
$paciente_nombre = !empty($data['paciente_nombre']) ? $data['paciente_nombre'] : '';
$cantidad = !empty($data['cantidad']) ? (float) $data['cantidad'] : 0;
$fecha = !empty($data['fecha']) ? $data['fecha'] : date('Y-m-d');
$id_doctor = !empty($data['id_doctor']) ? $data['id_doctor'] : null;
$tipo_pago = !empty($data['tipo_pago']) ? $data['tipo_pago'] : 'Efectivo';

if (empty($cantidad)) {
    echo json_encode(['status' => 'error', 'message' => 'Monto requerido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Ensure table exists
    $conn->exec("CREATE TABLE IF NOT EXISTS electrocardiogramas (
        id_electro INT AUTO_INCREMENT PRIMARY KEY,
        id_paciente INT NULL,
        id_doctor INT NULL,
        precio DECIMAL(10,2) NOT NULL,
        fecha_estudio DATETIME DEFAULT CURRENT_TIMESTAMP,
        estado_pago VARCHAR(50) DEFAULT 'Pagado',
        tipo_pago VARCHAR(50) DEFAULT 'Efectivo',
        observaciones TEXT NULL,
        FOREIGN KEY (id_paciente) REFERENCES pacientes(id_paciente) ON DELETE SET NULL
    )");

    // Create patient if needed
    if (empty($paciente_id) && !empty($paciente_nombre)) {
        $parts = explode(' ', $paciente_nombre, 2);
        $nombre = $parts[0];
        $apellido = isset($parts[1]) ? $parts[1] : '';
        $stmtP = $conn->prepare("INSERT INTO pacientes (nombre, apellido, fecha_registro) VALUES (?, ?, NOW())");
        $stmtP->execute([$nombre, $apellido]);
        $paciente_id = $conn->lastInsertId();
    }

    $stmt = $conn->prepare("
        INSERT INTO electrocardiogramas (id_paciente, id_doctor, precio, fecha_estudio, estado_pago, tipo_pago) 
        VALUES (?, ?, ?, ?, 'Pagado', ?)
    ");

    $stmt->execute([
        $paciente_id,
        $id_doctor,
        $cantidad,
        $fecha . ' ' . date('H:i:s'),
        $tipo_pago
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Electrocardiograma registrado',
        'id_electro' => $conn->lastInsertId()
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
