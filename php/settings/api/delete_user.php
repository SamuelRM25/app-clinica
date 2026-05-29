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

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
    exit;
}

// CSRF validation via X-CSRF-Token header
$csrf_header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($csrf_header) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_header)) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

$idUsuario = $_GET['id'];

// No permitir borrarse a sí mismo
if ($idUsuario == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'No puede eliminar su propio usuario']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $id_hospital = $_SESSION['id_hospital'] ?? 0;

    $stmt = $conn->prepare("DELETE FROM usuarios WHERE idUsuario = ? AND id_hospital = ?");
    $stmt->execute([$idUsuario, $id_hospital]);

    echo json_encode(['success' => true, 'message' => 'Usuario eliminado correctamente']);

} catch (Exception $e) {
    error_log("Error delete_user: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al eliminar el usuario.']);
}
?>
