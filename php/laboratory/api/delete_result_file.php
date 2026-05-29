<?php
// laboratory/api/delete_result_file.php
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

header('Content-Type: application/json');

verify_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id_archivo = $input['id_archivo'] ?? null;

// CSRF validation from header
$csrf_header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($csrf_header) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_header)) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

if (!$id_archivo) {
    echo json_encode(['success' => false, 'message' => 'ID de archivo no proporcionado']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Verify if file exists
    $id_hospital = $_SESSION['id_hospital'] ?? 0;

    $stmt = $conn->prepare("SELECT id_archivo FROM archivos_resultados_laboratorio WHERE id_archivo = ? AND id_hospital = ?");
    $stmt->execute([$id_archivo, $id_hospital]);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Archivo no encontrado']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM archivos_resultados_laboratorio WHERE id_archivo = ? AND id_hospital = ?");
    $stmt->execute([$id_archivo, $id_hospital]);

    echo json_encode(['success' => true, 'message' => 'Archivo eliminado correctamente']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
