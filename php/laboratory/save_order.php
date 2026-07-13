<?php
// laboratory/save_order.php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

$id_hospital = (int) ($_SESSION['id_hospital'] ?? 0);

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

// Validate laboratorio_externo (obligatorio)
$lab_externo = $data['laboratorio_externo'] ?? '';
$lab_externo = in_array($lab_externo, ['Medialab', 'La Esperanza'], true) ? $lab_externo : null;
if ($lab_externo === null) {
    echo json_encode(['status' => 'error', 'message' => 'Debe seleccionar el laboratorio (Medialab o La Esperanza)']);
    exit;
}

try {
    // CSRF validation
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (empty($csrfHeader) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfHeader)) {
        throw new Exception('Token CSRF inválido');
    }

    $database = new Database();
    $conn = $database->getConnection();
    $conn->beginTransaction();

    // 1. Generate unique order number (race-condition safe: retry on duplicate key)
    $today = date('Ymd');
    $maxAttempts = 10;
    $id_orden = null;
    $numero_orden = null;
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM ordenes_laboratorio WHERE DATE(fecha_orden) = CURDATE() AND id_hospital = ?");
        $stmt->execute([$id_hospital]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] + 1;
        $numero_orden = "LAB-" . $today . "-" . str_pad($count, 3, '0', STR_PAD_LEFT);

        // 2. Check if patient is hospitalized
        $stmt_hosp = $conn->prepare("SELECT id_encamamiento FROM encamamientos WHERE id_paciente = ? AND estado = 'Activo' AND id_hospital = ? LIMIT 1");
        $stmt_hosp->execute([$data['id_paciente'], $id_hospital]);
        $hosp = $stmt_hosp->fetch(PDO::FETCH_ASSOC);
        $id_encamamiento = $hosp ? $hosp['id_encamamiento'] : null;

        // 3. Attempt insert; if duplicate key on numero_orden, retry with next number
        $stmt = $conn->prepare("
            INSERT INTO ordenes_laboratorio (
                numero_orden, id_paciente, id_doctor, laboratorio_externo, id_encamamiento,
                prioridad, observaciones,
                estado, fecha_orden, id_hospital
            ) VALUES (?, ?, ?, ?, ?, 'Rutina', ?, 'Pendiente', NOW(), ?)
        ");

        try {
            $stmt->execute([
                $numero_orden,
                $data['id_paciente'],
                $data['id_doctor'],
                $lab_externo,
                $id_encamamiento,
                $data['observaciones'] ?? '',
                $id_hospital
            ]);
            $id_orden = $conn->lastInsertId();
            break; // success
        } catch (PDOException $e) {
            // 23000 = integrity constraint violation (duplicate key)
            if ($e->getCode() === '23000' && $attempt < $maxAttempts) {
                // Re-roll the transaction fragment and retry
                $conn->rollBack();
                $conn->beginTransaction();
                continue;
            }
            throw $e;
        }
    }

    if ($id_orden === null) {
        throw new Exception('No se pudo generar un número de orden único después de ' . $maxAttempts . ' intentos');
    }

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
            $final_price = $test_info['precio'];

            // EPS Logic: Use custom price if available and is_eps is true
            if (!empty($data['is_eps']) && isset($data['custom_prices'][$id_prueba])) {
                $final_price = floatval($data['custom_prices'][$id_prueba]);
            }

            $items_for_billing[] = [
                'nombre' => $test_info['nombre_prueba'],
                'precio' => $final_price
            ];
        }
    }

    // 5. Billing Integration (if hospitalized)
    if ($id_encamamiento) {
        $stmt_cargo = $conn->prepare("
            INSERT INTO cargos_hospitalarios (id_cuenta, tipo_cargo, descripcion, precio_unitario, fecha_cargo, registrado_por, id_hospital)
            VALUES (
                (SELECT id_cuenta FROM cuenta_hospitalaria WHERE id_encamamiento = ? AND estado_pago = 'Pendiente' LIMIT 1),
                'Laboratorio', ?, ?, NOW(), ?, ?
            )
        ");

        $user_id = $_SESSION['user_id'];

        foreach ($items_for_billing as $item) {
            $stmt_cargo->execute([
                $id_encamamiento,
                "Laboratorio: " . $item['nombre'] . " (Orden #" . $numero_orden . ")",
                $item['precio'],
                $user_id,
                $id_hospital
            ]);
        }

        // Also insert a record in examenes_realizados so the receipt can be printed
        $total_hosp = 0;
        $pruebas_nombres_hosp = [];
        foreach ($items_for_billing as $item) {
            $total_hosp += $item['precio'];
            $pruebas_nombres_hosp[] = $item['nombre'];
        }

        $stmt_p_hosp = $conn->prepare("SELECT CONCAT(nombre, ' ', apellido) as nombre FROM pacientes WHERE id_paciente = ? AND id_hospital = ?");
        $stmt_p_hosp->execute([$data['id_paciente'], $id_hospital]);
        $paciente_data_hosp = $stmt_p_hosp->fetch(PDO::FETCH_ASSOC);
        $nombre_paciente_hosp = $paciente_data_hosp['nombre'] ?? 'Paciente Desconocido';

        $descripcion_hosp = "Servicios Laboratorio Order #" . $numero_orden . ": " . implode(", ", $pruebas_nombres_hosp);
        $stmt_bill_hosp = $conn->prepare("
            INSERT INTO examenes_realizados (id_paciente, id_orden, nombre_paciente, tipo_examen, cobro, tipo_pago, fecha_examen, id_hospital)
            VALUES (?, ?, ?, ?, ?, 'Hospitalización', NOW(), ?)
        ");
        $stmt_bill_hosp->execute([
            $data['id_paciente'],
            $id_orden,
            $nombre_paciente_hosp,
            $descripcion_hosp,
            $total_hosp,
            $id_hospital
        ]);
        $id_pago = $conn->lastInsertId();
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

        // Cargo por Hora Inhábil (no hospitalizados, después de las 18:00)
        $cargo_inhabil = 0.0;
        $es_horario_inhabil = ((int) date('H') >= 18);
        if ($es_horario_inhabil) {
            $cargo_inhabil = 35.00; // Q35.00 fijo
        }
        $total_con_inhabil = $total_order + $cargo_inhabil;

        // Get patient name
        $stmt_p = $conn->prepare("SELECT CONCAT(nombre, ' ', apellido) as nombre FROM pacientes WHERE id_paciente = ? AND id_hospital = ?");
        $stmt_p->execute([$data['id_paciente'], $id_hospital]);
        $paciente_data = $stmt_p->fetch(PDO::FETCH_ASSOC);
        $nombre_paciente_full = $paciente_data['nombre'] ?? 'Paciente Desconocido';

        $stmt_bill = $conn->prepare("
            INSERT INTO examenes_realizados (id_paciente, id_orden, nombre_paciente, tipo_examen, cobro, tipo_pago, fecha_examen, id_hospital)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
        ");

        // Fila 1: cobro de las pruebas
        $descripcion_bill = "Servicios Laboratorio Order #" . $numero_orden . ": " . implode(", ", $pruebas_nombres);
        $stmt_bill->execute([
            $data['id_paciente'],
            $id_orden,
            $nombre_paciente_full,
            $descripcion_bill,
            $total_order,
            $tipo_pago,
            $id_hospital
        ]);
        $id_pago = $conn->lastInsertId();

        // Fila 2: cargo Hora Inhábil (si aplica) — como row separado para que aparezca como línea en el ticket
        if ($cargo_inhabil > 0) {
            $stmt_bill_inh = $conn->prepare("
                INSERT INTO examenes_realizados (id_paciente, id_orden, nombre_paciente, tipo_examen, cobro, tipo_pago, fecha_examen, id_hospital)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $stmt_bill_inh->execute([
                $data['id_paciente'],
                $id_orden,
                $nombre_paciente_full,
                'Horario Inhabil',
                $cargo_inhabil,
                $tipo_pago,
                $id_hospital
            ]);
        }
    }

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Orden y cobro generados',
        'id_orden' => $id_orden,
        'numero_orden' => $numero_orden,
        'id_pago' => $id_pago ?? null,
        'cargo_inhabil' => $cargo_inhabil ?? 0.0,
        'horario_inhabil' => $es_horario_inhabil ?? false
    ]);

} catch (Exception $e) {
    if (isset($conn))
        $conn->rollBack();
    error_log('Error en laboratory/save_order.php: ' . $e->getMessage());

    // Return specific messages for common errors, generic for unknown
    $message = $e->getMessage();
    if (strpos($message, 'Duplicate entry') !== false && strpos($message, 'numero_orden') !== false) {
        $response = ['status' => 'error', 'message' => 'Conflicto de número de orden. Reintente.'];
    } elseif (strlen($message) < 200 && !str_contains($message, 'SQLSTATE')) {
        // Short, safe-to-display messages (e.g., our own thrown exceptions)
        $response = ['status' => 'error', 'message' => $message];
    } else {
        // Raw DB errors: don't leak schema details
        $response = ['status' => 'error', 'message' => 'Error del servidor. Por favor reintente.'];
    }
    echo json_encode($response);
}
