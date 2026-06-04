<?php
// api/ver_archivo.php - Sirve archivos PDF almacenados en la DB
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/multitenant.php';

header('X-Frame-Options: SAMEORIGIN');
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
header('Access-Control-Allow-Credentials: true');

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);
$id_archivo = (int)($_GET['id'] ?? 0);

if (!$id_archivo) {
    http_response_code(400);
    exit('ID de archivo requerido');
}

if (!isset($_SESSION['user_id']) || empty($id_hospital)) {
    http_response_code(401);
    exit('Sesión inválida');
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("SELECT * FROM archivos_resultados_laboratorio WHERE id_archivo = ? AND id_hospital = ?");
    $stmt->execute([$id_archivo, $id_hospital]);
    $archivo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$archivo) {
        http_response_code(404);
        exit('Archivo no encontrado');
    }

    $contenido = $archivo['contenido'] ?? null;
    $tipo_mime = $archivo['tipo_mime'] ?? 'application/pdf';
    $nombre = $archivo['nombre_archivo'] ?? 'resultado.pdf';

    if (!$contenido) {
        http_response_code(404);
        exit('Contenido del archivo no disponible');
    }

    header('Content-Type: ' . $tipo_mime);
    header('Content-Length: ' . strlen($contenido));
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: private');

    echo $contenido;

} catch (Exception $e) {
    error_log('Error en api/ver_archivo.php: ' . $e->getMessage());
    http_response_code(500);
    exit('Error del servidor');
}