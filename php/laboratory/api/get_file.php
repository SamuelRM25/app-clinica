<?php
// laboratory/api/get_file.php - Serve file from database
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/multitenant.php';

verify_session();

$id_hospital = hospital_id();

$id_archivo = $_GET['id'] ?? null;
$id_orden_prueba = $_GET['test_id'] ?? null;

if (!$id_archivo && !$id_orden_prueba) {
    die("ID no proporcionado");
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    if ($id_archivo) {
        $stmt = $conn->prepare("
            SELECT ao.* FROM archivos_orden ao
            JOIN orden_pruebas op ON ao.id_orden_prueba = op.id_orden_prueba
            JOIN ordenes_laboratorio ol ON op.id_orden = ol.id_orden
            WHERE ao.id_archivo = ? AND ol.id_hospital = ?
        ");
        $stmt->execute([$id_archivo, $id_hospital]);
    } else {
        // Get the latest file for this test
        $stmt = $conn->prepare("
            SELECT ao.* FROM archivos_orden ao
            JOIN orden_pruebas op ON ao.id_orden_prueba = op.id_orden_prueba
            JOIN ordenes_laboratorio ol ON op.id_orden = ol.id_orden
            WHERE ao.id_orden_prueba = ? AND ol.id_hospital = ?
            ORDER BY ao.id_archivo DESC LIMIT 1
        ");
        $stmt->execute([$id_orden_prueba, $id_hospital]);
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
    error_log('Error en laboratory/api/get_file.php: ' . $e->getMessage());
    die("Error: " . 'Error del servidor.');
}
?>