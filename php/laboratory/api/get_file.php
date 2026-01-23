<?php
// laboratory/api/get_file.php - Serve file from database
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

verify_session();

$id_archivo = $_GET['id'] ?? null;
$id_orden_prueba = $_GET['test_id'] ?? null;

if (!$id_archivo && !$id_orden_prueba) {
    die("ID no proporcionado");
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    if ($id_archivo) {
        $stmt = $conn->prepare("SELECT * FROM archivos_orden WHERE id_archivo = ?");
        $stmt->execute([$id_archivo]);
    } else {
        // Get the latest file for this test
        $stmt = $conn->prepare("SELECT * FROM archivos_orden WHERE id_orden_prueba = ? ORDER BY id_archivo DESC LIMIT 1");
        $stmt->execute([$id_orden_prueba]);
    }

    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($file) {
        // Headers to serve the file
        header("Content-Type: " . $file['tipo_contenido']);
        header("Content-Length: " . $file['tamano']);
        header("Content-Disposition: inline; filename=\"" . $file['nombre_archivo'] . "\"");

        // Clear buffer
        if (ob_get_level())
            ob_end_clean();

        echo $file['contenido'];
    } else {
        http_response_code(404);
        die("Archivo no encontrado");
    }

} catch (Exception $e) {
    http_response_code(500);
    die("Error: " . $e->getMessage());
}
?>