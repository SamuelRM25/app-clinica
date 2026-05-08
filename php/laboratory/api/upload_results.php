<?php
// laboratory/api/upload_results.php - Handle file upload for lab results (With compression and larger file support)
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

verify_session();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$id_orden = $_POST['id_orden'] ?? null;
$notas = $_POST['notas'] ?? '';

if (!$id_orden) {
    echo json_encode(['success' => false, 'message' => 'Faltan parámetros requeridos']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    if (isset($_FILES['archivo_resultado'])) {
        $files = $_FILES['archivo_resultado'];
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
                    continue; // Skip invalid files instead of throwing error
                }

                // Compression logic for images
                $usedPath = $fileTmpPath;
                $compressedPath = null;
                if (in_array($fileExtension, ['jpg', 'jpeg', 'png'])) {
                    $compressedPath = $fileTmpPath . '_compressed.' . $fileExtension;
                    if (compressImage($fileTmpPath, $compressedPath, 70)) {
                        $usedPath = $compressedPath;
                    }
                }

                $content = file_get_contents($usedPath);
                $fileSize = strlen($content);

                // Insert with explicit categoria 'RESULTADO'
                $stmt = $conn->prepare("
                    INSERT INTO archivos_resultados_laboratorio 
                    (id_orden, categoria, nombre_archivo, tipo_contenido, tamano, contenido, notas) 
                    VALUES (?, 'RESULTADO', ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$id_orden, $fileName, $fileType, $fileSize, $content, $notas]);
                $uploadedCount++;

                // Clean up
                if ($compressedPath && file_exists($compressedPath)) {
                    unlink($compressedPath);
                }
            }
        }

        if ($uploadedCount > 0) {
            $conn->commit();
            echo json_encode([
                'success' => true,
                'message' => $uploadedCount . ' archivo(s) cargado(s) correctamente',
            ]);
        } else {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'No se pudo procesar ningún archivo válido']);
        }

    } else {
        echo json_encode(['success' => false, 'message' => 'Archivo no recibido']);
    }

} catch (Exception $e) {
    if (isset($conn)) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>