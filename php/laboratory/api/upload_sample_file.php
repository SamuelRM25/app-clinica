<?php
// laboratory/api/upload_sample_file.php - Handle file upload for sample reception (Multiple files supported)
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/multitenant.php';

verify_session();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$id_orden = $_POST['id_orden'] ?? null;
$notas = $_POST['notas'] ?? '';

// CSRF validation
$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

    if (!$id_orden) {
        echo json_encode(['success' => false, 'message' => 'Faltan parámetros requeridos']);
        exit;
    }

try {
    $database = new Database();
    $conn = $database->getConnection();

    $id_hospital = hospital_id();

    $stmt_hosp = $conn->prepare("SELECT id_orden FROM ordenes_laboratorio WHERE id_orden = ? AND id_hospital = ?");
    $stmt_hosp->execute([$id_orden, $id_hospital]);
    if (!$stmt_hosp->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Orden no encontrada o no pertenece a este hospital']);
        exit;
    }

    if (!isset($_FILES['archivo_muestra'])) {
        echo json_encode(['success' => false, 'message' => 'No se recibieron archivos']);
        exit;
    }

    $files = $_FILES['archivo_muestra'];
    $fileCount = is_array($files['name']) ? count($files['name']) : 1;
    $uploadedCount = 0;
    
    $conn->beginTransaction();

    for ($i = 0; $i < $fileCount; $i++) {
        $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
        
        if ($error === UPLOAD_ERR_OK) {
            $fileTmpPath = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $fileName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
            $fileType = is_array($files['type']) ? $files['type'][$i] : $files['type'];
            
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));
            $allowedfileExtensions = ['pdf', 'jpg', 'jpeg', 'png'];

            if (!in_array($fileExtension, $allowedfileExtensions)) {
                continue; // Skip invalid files
            }

            // Compression logic for images
            if (in_array($fileExtension, ['jpg', 'jpeg', 'png'])) {
                // Compress to a temporary file
                $compressedPath = $fileTmpPath . '_compressed.' . $fileExtension;
                if (compressImage($fileTmpPath, $compressedPath, 60)) {
                    $fileTmpPath = $compressedPath;
                }
            }

            $content = file_get_contents($fileTmpPath);
            $fileSize = strlen($content);

            // Insert into archivos_resultados_laboratorio with categoria 'ORDEN_FISICA'
            $stmt = $conn->prepare("
                INSERT INTO archivos_resultados_laboratorio 
                (id_orden, categoria, nombre_archivo, tipo_contenido, tamano, contenido, notas, id_hospital) 
                VALUES (?, 'ORDEN_FISICA', ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$id_orden, $fileName, $fileType, $fileSize, $content, $notas, $id_hospital]);
            
            $uploadedCount++;

            // Clean up compressed temp file if created
            if (isset($compressedPath) && file_exists($compressedPath)) {
                unlink($compressedPath);
            }
        }
    }

    if ($uploadedCount > 0) {
        // Update order status if it was Pendiente
        $stmt = $conn->prepare("
            UPDATE ordenes_laboratorio 
            SET estado = 'Muestra_Recibida', fecha_muestra_recibida = NOW()
            WHERE id_orden = ? AND estado = 'Pendiente' AND id_hospital = ?
        ");
        $stmt->execute([$id_orden, $id_hospital]);

        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => $uploadedCount . ' archivo(s) cargado(s) correctamente',
        ]);
    } else {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'No se pudo procesar ningún archivo válido']);
    }

} catch (Exception $e) {
    if (isset($conn)) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>