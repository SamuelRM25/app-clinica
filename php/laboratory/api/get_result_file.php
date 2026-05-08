<?php
// laboratory/api/get_result_file.php - Serve result file from database (archivos_resultados_laboratorio)
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

verify_session();

$id_orden = $_GET['id_orden'] ?? null;
$id_archivo = $_GET['id'] ?? null;

if (!$id_orden && !$id_archivo) {
    die("ID no proporcionado");
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    if ($id_archivo) {
        $stmt = $conn->prepare("SELECT * FROM archivos_resultados_laboratorio WHERE id_archivo = ?");
        $stmt->execute([$id_archivo]);
    } else {
        // Get the latest file for this order
        $stmt = $conn->prepare("SELECT * FROM archivos_resultados_laboratorio WHERE id_orden = ? ORDER BY id_archivo DESC LIMIT 1");
        $stmt->execute([$id_orden]);
    }

    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($file) {
        // Headers to serve the file
        // Detect MIME type if not stored cleanly or fallback
        $mime_type = $file['tipo_contenido'];
        // If tipo_contenido is empty or generic, we could try to detect from extension, but it should be saved correctly.

        header("Content-Type: " . $mime_type);
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