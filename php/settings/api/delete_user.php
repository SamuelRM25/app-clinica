<?php
// settings/api/delete_user.php
session_start();
require_once '../../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['tipoUsuario'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
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

    $stmt = $conn->prepare("DELETE FROM usuarios WHERE idUsuario = ?");
    $stmt->execute([$idUsuario]);

    echo json_encode(['success' => true, 'message' => 'Usuario eliminado correctamente']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
