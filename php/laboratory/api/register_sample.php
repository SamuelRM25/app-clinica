<?php
// laboratory/api/register_sample.php - API to register sample reception
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/multitenant.php';

header('Content-Type: application/json');

verify_session();

$id_hospital = hospital_id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    // CSRF validation
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (empty($csrfHeader) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfHeader)) {
        throw new Exception('Token CSRF inválido');
    }

    $database = new Database();
    $conn = $database->getConnection();

    $id_orden = $_POST['id_orden'] ?? null;
    $fecha_recepcion = $_POST['fecha_recepcion'] ?? date('Y-m-d H:i:s');
    $observaciones = $_POST['observaciones'] ?? '';

    if (!$id_orden) {
        throw new Exception('ID de orden no proporcionado');
    }

    $stmt_verify = $conn->prepare("SELECT id_orden FROM ordenes_laboratorio WHERE id_orden = ? AND id_hospital = ?");
    $stmt_verify->execute([$id_orden, $id_hospital]);
    if (!$stmt_verify->fetch()) {
        throw new Exception('Orden no encontrada o no pertenece a este hospital');
    }

    $stmt = $conn->prepare("
        UPDATE ordenes_laboratorio 
        SET estado = 'Muestra_Recibida',
            fecha_muestra_recibida = ?
        WHERE id_orden = ? AND id_hospital = ?
    ");
    $stmt->execute([$fecha_recepcion, $id_orden, $id_hospital]);

    // Handle file upload if present
    if (isset($_FILES['archivo_orden']) && $_FILES['archivo_orden']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../../uploads/results/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileInfo = pathinfo($_FILES['archivo_orden']['name']);
        $extension = strtolower($fileInfo['extension']);
        $newFileName = 'orden_' . $id_orden . '_' . uniqid() . '.' . $extension;
        $targetPath = $uploadDir . $newFileName;

        $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];

        if (in_array($extension, $allowedExts)) {
            if (move_uploaded_file($_FILES['archivo_orden']['tmp_name'], $targetPath)) {
                $dbPath = '../../uploads/results/' . $newFileName;
                $stmt_file = $conn->prepare("UPDATE ordenes_laboratorio SET archivo_resultados = ? WHERE id_orden = ? AND id_hospital = ?");
                $stmt_file->execute([$dbPath, $id_orden, $id_hospital]);
            }
        }
    }

    // Log the action if observations were provided
    if ($observaciones) {
        $stmt = $conn->prepare("
            INSERT INTO orden_logs (id_orden, accion, observaciones, id_usuario, fecha, id_hospital)
            VALUES (?, 'Muestra Recibida', ?, ?, NOW(), ?)
        ");
        $userId = $_SESSION['user_id'] ?? null;
        $stmt->execute([$id_orden, $observaciones, $userId, $id_hospital]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Muestra registrada exitosamente'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
