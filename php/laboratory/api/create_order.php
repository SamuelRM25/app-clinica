<?php
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/multitenant.php';

header('Content-Type: application/json');

verify_session();

$id_hospital = hospital_id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$id_paciente = $_POST['id_paciente'] ?? null;
$id_doctor = $_POST['id_doctor'] ?: null;
$prioridad_raw = $_POST['prioridad'] ?? 'Normal';
$indicaciones = $_POST['instrucciones'] ?? '';
$observaciones = $_POST['observaciones'] ?? '';
$pruebas_ids = $_POST['pruebas'] ?? [];
$is_embedded = isset($_POST['is_embedded']) && $_POST['is_embedded'] == '1';

$priority_map = [
    'Normal' => 'Rutina',
    'Urgente' => 'Urgente',
    'Emergencia' => 'STAT'
];
$prioridad = $priority_map[$prioridad_raw] ?? 'Rutina';

if (!$id_paciente || empty($pruebas_ids) || !is_array($pruebas_ids)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (empty($csrfHeader) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfHeader)) {
        throw new Exception('Token CSRF inválido');
    }

    $database = new Database();
    $conn = $database->getConnection();
    $conn->beginTransaction();

    $today = date('Ymd');
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM ordenes_laboratorio WHERE DATE(fecha_orden) = CURDATE() AND id_hospital = ?");
    $stmt->execute([$id_hospital]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] + 1;
    $numero_orden = "LAB-" . $today . "-" . str_pad($count, 3, '0', STR_PAD_LEFT);

    $stmt_hosp = $conn->prepare("SELECT id_encamamiento FROM encamamientos WHERE id_paciente = ? AND estado = 'Activo' AND id_hospital = ? LIMIT 1");
    $stmt_hosp->execute([$id_paciente, $id_hospital]);
    $hosp = $stmt_hosp->fetch(PDO::FETCH_ASSOC);
    $id_encamamiento = $hosp ? $hosp['id_encamamiento'] : null;

    $stmt = $conn->prepare("
        INSERT INTO ordenes_laboratorio (
            numero_orden, id_paciente, id_doctor, id_encamamiento, 
            prioridad, indicaciones_especiales, observaciones, 
            estado, fecha_orden, id_hospital
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pendiente', NOW(), ?)
    ");
    $stmt->execute([
        $numero_orden,
        $id_paciente,
        $id_doctor,
        $id_encamamiento,
        $prioridad,
        $indicaciones,
        $observaciones,
        $id_hospital
    ]);
    $id_orden = $conn->lastInsertId();

    $total_order = 0;
    $stmt_prueba = $conn->prepare("INSERT INTO orden_pruebas (id_orden, id_prueba, estado) VALUES (?, ?, 'Pendiente')");
    $stmt_price = $conn->prepare("SELECT nombre_prueba, precio FROM catalogo_pruebas WHERE id_prueba = ? AND id_hospital = ?");

    $items_for_billing = [];

    foreach ($pruebas_ids as $id_prueba) {
        $id_prueba = intval($id_prueba);
        if ($id_prueba <= 0) continue;

        $stmt_prueba->execute([$id_orden, $id_prueba]);

        $stmt_price->execute([$id_prueba, $id_hospital]);
        $test_info = $stmt_price->fetch(PDO::FETCH_ASSOC);
        if ($test_info) {
            $total_order += $test_info['precio'];
            $items_for_billing[] = [
                'nombre' => $test_info['nombre_prueba'],
                'precio' => $test_info['precio']
            ];
        }
    }

    if ($id_encamamiento) {
        $stmt_cargo = $conn->prepare("
            INSERT INTO cargos_hospitalarios (id_cuenta, tipo_cargo, descripcion, precio_unitario, fecha_cargo, registrado_por, id_hospital)
            VALUES (
                (SELECT id_cuenta FROM cuenta_hospitalaria WHERE id_encamamiento = ? AND estado_pago = 'Pendiente' LIMIT 1),
                'Laboratorio', ?, ?, NOW(), ?, ?
            )
        ");

        $user_id = $_SESSION['user_id'] ?? 1;

        foreach ($items_for_billing as $item) {
            $stmt_cargo->execute([
                $id_encamamiento,
                "Laboratorio: " . $item['nombre'] . " (Orden #" . $numero_orden . ")",
                $item['precio'],
                $user_id,
                $id_hospital
            ]);
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Orden generada correctamente',
        'order_number' => $numero_orden,
        'redirect' => !$is_embedded ? '../index.php?success=1&order=' . $numero_orden : null
    ]);
    exit;

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Error en laboratory/api/create_order.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    exit;
}
