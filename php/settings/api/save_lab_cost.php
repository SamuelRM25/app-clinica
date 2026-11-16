<?php
/**
 * settings/api/save_lab_cost.php — Guarda precio de venta y costos externos (Medialab / La Esperanza)
 * de las pruebas del catálogo de laboratorio.
 * Acepta { csrf_token, items: [{ id_prueba, precio?, precio_medilab?, precio_la_esperanza? }, ...] }
 * o un solo item (formato anterior) en el cuerpo.
 */
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
start_app_session();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}
if (($_SESSION['tipoUsuario'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Solo el administrador puede cambiar tarifas']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];

if (!hash_equals($_SESSION['csrf_token'] ?? '', $data['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Token CSRF inválido. Recargue la página.']);
    exit;
}

$items = isset($data['items']) && is_array($data['items']) ? $data['items'] : (empty($data['id_prueba']) ? [] : [$data]);
if (!$items) {
    echo json_encode(['success' => false, 'error' => 'No hay pruebas para guardar']);
    exit;
}

// Lee un monto: null si no viene; error si es negativo o no numérico
function monto_lab($item, string $campo) {
    if (!array_key_exists($campo, $item) || $item[$campo] === '' || $item[$campo] === null) return null;
    if (!is_numeric($item[$campo]) || (float) $item[$campo] < 0) {
        throw new InvalidArgumentException('Monto inválido en ' . $campo);
    }
    return round((float) $item[$campo], 2);
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    $id_hospital = (int) ($_SESSION['id_hospital'] ?? 0);

    $conn->beginTransaction();
    $updated = 0;
    foreach ($items as $item) {
        $id = (int) ($item['id_prueba'] ?? 0);
        if ($id <= 0) continue;
        $sets = [];
        $vals = [];
        foreach (['precio', 'precio_medilab', 'precio_la_esperanza'] as $campo) {
            $v = monto_lab($item, $campo);
            if ($v !== null) { $sets[] = "$campo = ?"; $vals[] = $v; }
        }
        if (!$sets) continue;
        $vals[] = $id;
        $vals[] = $id_hospital;
        $st = $conn->prepare('UPDATE catalogo_pruebas SET ' . implode(', ', $sets) . ' WHERE id_prueba = ? AND id_hospital = ?');
        $st->execute($vals);
        $updated++;
    }
    $conn->commit();

    if (function_exists('audit_log')) {
        audit_log('update', 'tarifas', "Precios/costos de laboratorio actualizados: $updated prueba(s)", [
            'table_name' => 'catalogo_pruebas',
            'new_data' => $items,
        ]);
    }

    echo json_encode(['success' => true, 'updated' => $updated, 'message' => 'Precios y costos actualizados correctamente']);
} catch (InvalidArgumentException $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    error_log('save_lab_cost: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'No se pudo guardar. Intente de nuevo.']);
}
