<?php
// surgery/api/delete_descuento_cirugia.php - Elimina un descuento de una cirugía
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);
$id_descuento = (int)($_POST['id_descuento'] ?? 0);

if ($id_descuento <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de descuento inválido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Verificar que el descuento existe y pertenece a este hospital
    $stmt = $conn->prepare("
        SELECT id_descuento, id_cirugia, concepto, monto, cancelado
        FROM cirugia_descuentos
        WHERE id_descuento = ? AND id_hospital = ?
    ");
    $stmt->execute([$id_descuento, $id_hospital]);
    $descuento = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$descuento) {
        echo json_encode(['success' => false, 'message' => 'Descuento no encontrado']);
        exit;
    }
    if ($descuento['cancelado']) {
        echo json_encode(['success' => false, 'message' => 'Este descuento ya fue eliminado']);
        exit;
    }

    $conn->beginTransaction();

    // Marcar como cancelado (soft delete) para mantener trazabilidad
    $stmtDel = $conn->prepare("
        UPDATE cirugia_descuentos
        SET cancelado = 1,
            fecha_cancelacion = NOW(),
            motivo_cancelacion = 'Eliminado por el usuario'
        WHERE id_descuento = ?
    ");
    $stmtDel->execute([$id_descuento]);

    $conn->commit();

    audit_log('delete', 'surgery', "Descuento eliminado de cirugía #{$descuento['id_cirugia']}: {$descuento['concepto']} - Q{$descuento['monto']}", [
        'table_name' => 'cirugia_descuentos',
        'record_id' => $id_descuento,
        'old_data' => $descuento,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Descuento eliminado correctamente',
    ]);
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    error_log('delete_descuento_cirugia.php error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al eliminar el descuento',
        'debug' => ($_SESSION['tipoUsuario'] ?? '') === 'admin' ? $e->getMessage() : null,
    ]);
}