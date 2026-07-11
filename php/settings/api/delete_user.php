<?php
// settings/api/delete_user.php
// Soft delete / reactivate: toggle the `activo` flag instead of DELETE FROM.
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['tipoUsuario'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

$idUsuario = $_POST['id'] ?? $_GET['id'] ?? null;
if (!$idUsuario) {
    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
    exit;
}

// CSRF validation: accepts header (from fetch monkey-patch) or GET parameter
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_GET['csrf_token'] ?? '';
if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

$idUsuario = (int)$idUsuario;

// No permitir desactivarse a sí mismo
if ($idUsuario == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'No puede desactivar su propio usuario']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'toggle';
// action: 'toggle' (default) | 'deactivate' | 'reactivate'

try {
    $database = new Database();
    $conn = $database->getConnection();

    $id_hospital = $_SESSION['id_hospital'] ?? 0;

    // Fetch old data for audit
    $fetchStmt = $conn->prepare("SELECT usuario, nombre, apellido, tipoUsuario, especialidad, telefono, email, activo FROM usuarios WHERE idUsuario = ? AND id_hospital = ?");
    $fetchStmt->execute([$idUsuario, $id_hospital]);
    $user = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        exit;
    }

    // Determine target state
    if ($action === 'reactivate') {
        $new_state = 1;
    } elseif ($action === 'deactivate') {
        $new_state = 0;
    } else {
        $new_state = (int)($user['activo'] ?? 1) === 1 ? 0 : 1;
    }

    $stmt = $conn->prepare("UPDATE usuarios SET activo = ? WHERE idUsuario = ? AND id_hospital = ?");
    $stmt->execute([$new_state, $idUsuario, $id_hospital]);

    $verb = $new_state === 1 ? 'reactivado' : 'desactivado';
    audit_log($verb === 'reactivado' ? 'reactivate' : 'deactivate', 'users', "Usuario $verb: {$user['usuario']} (ID: $idUsuario)", [
        'table_name' => 'usuarios',
        'record_id' => $idUsuario,
        'old_data' => ['activo' => (int)($user['activo'] ?? 1)],
        'new_data' => ['activo' => $new_state],
    ]);

    echo json_encode([
        'success' => true,
        'message' => "Usuario $verb correctamente",
        'activo' => $new_state,
    ]);

} catch (Exception $e) {
    error_log("delete_user error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al cambiar el estado del usuario.',
        'debug'   => ($_SESSION['tipoUsuario'] ?? '') === 'admin' ? $e->getMessage() : null,
    ]);
}