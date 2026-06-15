<?php
// billing/edit_cobro_time.php
// Permite a un admin editar la hora/fecha de cualquier cobro (7 fuentes).
// Solo accesible para usuarios con tipoUsuario === 'admin' y que hayan
// validado el AUTH_CODE de .env (gateado en frontend; defensa en profundidad aquí).
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

header('Content-Type: application/json');

verify_session();

// Defensa en profundidad: solo admin puede editar horas
$user_type = $_SESSION['tipoUsuario'] ?? '';
if ($user_type !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Permiso denegado. Solo administradores pueden editar horas.']);
    exit;
}

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$fuente = $data['fuente'] ?? '';
$id = isset($data['id']) ? (int)$data['id'] : 0;
$fecha_consulta = $data['fecha_consulta'] ?? '';
$csrf_token = $data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

if ($id <= 0 || empty($fuente) || empty($fecha_consulta)) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

// Validar formato de fecha: aceptar Y-m-d H:i:s o Y-m-d\TH:i (datetime-local)
$fecha_normalized = null;
$formats_to_try = ['Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i', DateTime::ATOM];
foreach ($formats_to_try as $fmt) {
    $dt = DateTime::createFromFormat($fmt, $fecha_consulta);
    if ($dt && $dt->format('Y') > 2000) {
        $fecha_normalized = $dt->format('Y-m-d H:i:s');
        break;
    }
}
// Fallback: try strtotime
if (!$fecha_normalized) {
    $ts = strtotime($fecha_consulta);
    if ($ts !== false) {
        $fecha_normalized = date('Y-m-d H:i:s', $ts);
    }
}

if (!$fecha_normalized) {
    echo json_encode(['success' => false, 'message' => 'Formato de fecha inválido. Use YYYY-MM-DD HH:MM:SS']);
    exit;
}

// Mapeo fuente -> (tabla, columna_id, columna_fecha)
// 4 tablas son TIMESTAMP (UTC), 3 son DATETIME (sin conversión)
$source_map = [
    'cobro' => [
        'table' => 'cobros',
        'id_col' => 'in_cobro',
        'date_col' => 'fecha_consulta',
        'is_timestamp' => false,
    ],
    'venta' => [
        'table' => 'ventas',
        'id_col' => 'id_venta',
        'date_col' => 'fecha_venta',
        'is_timestamp' => false,
    ],
    'examen' => [
        'table' => 'examenes_realizados',
        'id_col' => 'id_examen_realizado',
        'date_col' => 'fecha_examen',
        'is_timestamp' => true,
    ],
    'procedimiento' => [
        'table' => 'procedimientos_menores',
        'id_col' => 'id_procedimiento',
        'date_col' => 'fecha_procedimiento',
        'is_timestamp' => true,
    ],
    'ultrasonido' => [
        'table' => 'ultrasonidos',
        'id_col' => 'id_ultrasonido',
        'date_col' => 'fecha_ultrasonido',
        'is_timestamp' => true,
    ],
    'rayos_x' => [
        'table' => 'rayos_x',
        'id_col' => 'id_rayos_x',
        'date_col' => 'fecha_estudio',
        'is_timestamp' => true,
    ],
    'electro' => [
        'table' => 'electrocardiogramas',
        'id_col' => 'id_electro',
        'date_col' => 'fecha_realizado',
        'is_timestamp' => false,
    ],
];

if (!isset($source_map[$fuente])) {
    echo json_encode(['success' => false, 'message' => 'Fuente inválida']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $info = $source_map[$fuente];
    $table = $info['table'];
    $id_col = $info['id_col'];
    $date_col = $info['date_col'];

    // Para tablas TIMESTAMP: forzar TZ Guatemala antes de UPDATE para que
    // el string que el usuario tipeó se interprete en hora local y no se
    // haga conversión a UTC implícita.
    if ($info['is_timestamp']) {
        $conn->exec("SET time_zone = '-06:00'");
    }

    $conn->beginTransaction();

    // Obtener el valor actual antes del UPDATE (para audit_log old_data)
    $stmt_old = $conn->prepare("SELECT `$date_col` AS old_date FROM `$table` WHERE `$id_col` = ? AND id_hospital = ?");
    $stmt_old->execute([$id, $id_hospital]);
    $old_row = $stmt_old->fetch(PDO::FETCH_ASSOC);

    if (!$old_row) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Registro no encontrado']);
        exit;
    }

    $old_date = $old_row['old_date'];

    $stmt = $conn->prepare("UPDATE `$table` SET `$date_col` = ? WHERE `$id_col` = ? AND id_hospital = ?");
    $stmt->execute([$fecha_normalized, $id, $id_hospital]);

    if ($stmt->rowCount() === 0) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'No se pudo actualizar el registro']);
        exit;
    }

    $conn->commit();

    audit_log('update', 'billing', "Hora de cobro editada - Fuente: $fuente, ID: $id", [
        'table_name' => $table,
        'record_id' => $id,
        'old_data' => [$date_col => $old_date],
        'new_data' => [$date_col => $fecha_normalized],
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Hora actualizada correctamente',
        'new_date' => $fecha_normalized,
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error en edit_cobro_time.php: " . $e->getMessage());
    $msg = $e->getMessage();
    if (strlen($msg) < 200 && !str_contains($msg, 'SQLSTATE')) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $msg]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar la hora']);
    }
}
