<?php
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/multitenant.php';

verify_session();

$id_hospital = hospital_id();

$id_archivo = $_GET['id'] ?? null;

if (!$id_archivo) {
    http_response_code(404);
    die("ID no proporcionado");
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("
        SELECT ar.* FROM archivos_resultados_laboratorio ar
        WHERE ar.id_archivo = ? AND ar.id_hospital = ?
    ");
    $stmt->execute([$id_archivo, $id_hospital]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($file) {
        header("Content-Type: " . ($file['tipo_contenido'] ?? 'application/octet-stream'));
        header("Content-Disposition: inline; filename=\"" . ($file['nombre_archivo'] ?? 'archivo') . "\"");

        if (ob_get_level())
            ob_end_clean();

        if (!empty($file['contenido'])) {
            echo $file['contenido'];
        } else {
            http_response_code(404);
            die("Archivo sin contenido");
        }
    } else {
        http_response_code(404);
        die("Archivo no encontrado");
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log('Error en laboratory/api/get_file.php: ' . $e->getMessage());
    die("Error del servidor.");
}
