<?php
// laboratory/api/upload_sample_file.php - Handle file upload for sample reception
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

verify_session();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$id_orden_prueba = $_POST['id_orden_prueba'] ?? null;
$id_orden = $_POST['id_orden'] ?? null;
$notas = $_POST['notas'] ?? '';

if (!$id_orden_prueba || !$id_orden) {
    echo json_encode(['success' => false, 'message' => 'Faltan parámetros requeridos']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Handle file upload
    if (isset($_FILES['archivo_muestra']) && $_FILES['archivo_muestra']['error'] === UPLOAD_ERR_OK) {

        $fileTmpPath = $_FILES['archivo_muestra']['tmp_name'];
        $fileName = $_FILES['archivo_muestra']['name'];
        $fileSize = $_FILES['archivo_muestra']['size'];
        $fileType = $_FILES['archivo_muestra']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        $allowedfileExtensions = ['pdf', 'jpg', 'jpeg', 'png'];

        if (!in_array($fileExtension, $allowedfileExtensions)) {
            echo json_encode(['success' => false, 'message' => 'Tipo de archivo no permitido. Solo PDF, JPG, PNG.']);
            exit;
        }

        // Read file content
        $content = file_get_contents($fileTmpPath);

        $conn->beginTransaction();

        // 1. Insert file into archivos_orden
        $stmt = $conn->prepare("INSERT INTO archivos_orden (id_orden_prueba, nombre_archivo, tipo_contenido, tamano, contenido) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$id_orden_prueba, $fileName, $fileType, $fileSize, $content]);

        // 2. Update orden_pruebas status
        $stmt = $conn->prepare("
            UPDATE orden_pruebas 
            SET estado = 'Muestra_Recibida', 
                fecha_muestra_recibida = NOW(),
                notas_tecnico = ?
            WHERE id_orden_prueba = ?
        ");
        $stmt->execute([$notas, $id_orden_prueba]);

        // 3. Update order status if it was Pendiente
        $stmt = $conn->prepare("
            UPDATE ordenes_laboratorio 
            SET estado = 'Muestra_Recibida', fecha_muestra_recibida = NOW()
            WHERE id_orden = ? AND estado = 'Pendiente'
        ");
        $stmt->execute([$id_orden]);

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Archivo cargado y guardado en base de datos correctamente',
        ]);

    } else {
        $error = $_FILES['archivo_muestra']['error'] ?? 'Archivo no recibido';
        echo json_encode(['success' => false, 'message' => 'Error en la carga: ' . $error]);
    }

} catch (Exception $e) {
    if (isset($conn))
        $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>