<?php
// settings/api/delete_user.php
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

// No permitir borrarse a sí mismo
if ($idUsuario == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'No puede eliminar su propio usuario']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $id_hospital = $_SESSION['id_hospital'] ?? 0;

    // Fetch old data for audit
    $fetchStmt = $conn->prepare("SELECT usuario, nombre, apellido, tipoUsuario, especialidad, email FROM usuarios WHERE idUsuario = ? AND id_hospital = ?");
    $fetchStmt->execute([$idUsuario, $id_hospital]);
    $oldData = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("DELETE FROM usuarios WHERE idUsuario = ? AND id_hospital = ?");
    $stmt->execute([$idUsuario, $id_hospital]);

    audit_log('delete', 'users', "Usuario eliminado: {$oldData['usuario']} (ID: $idUsuario)", [
        'table_name' => 'usuarios',
        'record_id' => $idUsuario,
        'old_data' => $oldData
    ]);

    echo json_encode(['success' => true, 'message' => 'Usuario eliminado correctamente']);

} catch (Exception $e) {
    error_log("Error delete_user: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al eliminar el usuario.']);
}
?>
