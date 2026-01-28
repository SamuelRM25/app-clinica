<?php
// laboratory/save_order.php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Set timezone
date_default_timezone_set('America/Guatemala');

// Verify session
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
    exit;
}

// Set content type to JSON
header('Content-Type: application/json');

// Get POST data or JSON data
$data = [];
if (!empty($_POST)) {
    $data = $_POST;
} else {
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
}

// Validate required fields
if (empty($data['id_paciente']) || empty($data['id_doctor']) || empty($data['pruebas'])) {
    echo json_encode(['status' => 'error', 'message' => 'Faltan datos requeridos (paciente, doctor o pruebas)']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    $conn->beginTransaction();

    // 1. Generate unique order number
    $today = date('Ymd');
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM ordenes_laboratorio WHERE DATE(fecha_orden) = CURDATE()");
    $stmt->execute();
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] + 1;
    $numero_orden = "LAB-" . $today . "-" . str_pad($count, 3, '0', STR_PAD_LEFT);

    // 2. Check if patient is hospitalized
    $stmt_hosp = $conn->prepare("SELECT id_encamamiento FROM encamamientos WHERE id_paciente = ? AND estado = 'Activo' LIMIT 1");
    $stmt_hosp->execute([$data['id_paciente']]);
    $hosp = $stmt_hosp->fetch(PDO::FETCH_ASSOC);
    $id_encamamiento = $hosp ? $hosp['id_encamamiento'] : null;

    // 3. Create Order
    $stmt = $conn->prepare("
        INSERT INTO ordenes_laboratorio (
            numero_orden, id_paciente, id_doctor, id_encamamiento, 
            prioridad, observaciones, 
            estado, fecha_orden
        ) VALUES (?, ?, ?, ?, 'Rutina', ?, 'Pendiente', NOW())
    ");

    $stmt->execute([
        $numero_orden,
        $data['id_paciente'],
        $data['id_doctor'],
        $id_encamamiento,
        $data['observaciones'] ?? ''
    ]);

    $id_orden = $conn->lastInsertId();

    // 4. Insert Order Details (Pruebas)
    $stmtDetail = $conn->prepare("INSERT INTO orden_pruebas (id_orden, id_prueba, estado) VALUES (?, ?, 'Pendiente')");
    $stmt_price = $conn->prepare("SELECT nombre_prueba, precio FROM catalogo_pruebas WHERE id_prueba = ?");

    $items_for_billing = [];

    foreach ($data['pruebas'] as $id_prueba) {
        $stmtDetail->execute([$id_orden, $id_prueba]);

        // Fetch price for billing logic
        $stmt_price->execute([$id_prueba]);
        $test_info = $stmt_price->fetch(PDO::FETCH_ASSOC);
        if ($test_info) {
            $items_for_billing[] = [
                'nombre' => $test_info['nombre_prueba'],
                'precio' => $test_info['precio']
            ];
        }
    }

    // 5. Billing Integration (if hospitalized)
    if ($id_encamamiento) {
        $stmt_cargo = $conn->prepare("
            INSERT INTO cargos_hospitalarios (id_cuenta, tipo_cargo, descripcion, precio_unitario, fecha_cargo, registrado_por)
            VALUES (
                (SELECT id_cuenta FROM cuenta_hospitalaria WHERE id_encamamiento = ? AND estado_pago = 'Pendiente' LIMIT 1),
                'Laboratorio', ?, ?, NOW(), ?
            )
        ");

        $user_id = $_SESSION['user_id'];

        foreach ($items_for_billing as $item) {
            $stmt_cargo->execute([
                $id_encamamiento,
                "Laboratorio: " . $item['nombre'] . " (Orden #" . $numero_orden . ")",
                $item['precio'],
                $user_id
            ]);
        }
    }

    // 6. Integration with Payments (if NOT hospitalized)
    if (!$id_encamamiento) {
        $tipo_pago = $data['tipo_pago'] ?? 'Efectivo';
        $total_order = 0;
        $pruebas_nombres = [];
        foreach ($items_for_billing as $item) {
            $total_order += $item['precio'];
            $pruebas_nombres[] = $item['nombre'];
        }

        // Get patient name
        $stmt_p = $conn->prepare("SELECT CONCAT(nombre, ' ', apellido) as nombre FROM pacientes WHERE id_paciente = ?");
        $stmt_p->execute([$data['id_paciente']]);
        $paciente_data = $stmt_p->fetch(PDO::FETCH_ASSOC);
        $nombre_paciente_full = $paciente_data['nombre'] ?? 'Paciente Desconocido';

        $stmt_bill = $conn->prepare("
            INSERT INTO examenes_realizados (id_paciente, nombre_paciente, tipo_examen, cobro, tipo_pago, fecha_examen)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $descripcion_bill = "Servicios Laboratorio Order #" . $numero_orden . ": " . implode(", ", $pruebas_nombres);
        $stmt_bill->execute([
            $data['id_paciente'],
            $nombre_paciente_full,
            $descripcion_bill,
            $total_order,
            $tipo_pago
        ]);
        $id_pago = $conn->lastInsertId();
    }

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Orden y cobro generados',
        'id_orden' => $id_orden,
        'numero_orden' => $numero_orden,
        'id_pago' => $id_pago ?? null
    ]);

} catch (Exception $e) {
    if (isset($conn))
        $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
