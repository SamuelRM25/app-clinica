<?php
// surgery/api/delete_combo.php
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['tipoUsuario'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);
    $id_combo = (int)($_POST['id_combo'] ?? 0);

    if (!$id_combo) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    $conn->beginTransaction();

    // Si hay cirugías usando este combo, desvincular (id_combo → NULL) mediante UPDATE
    // No hay FK constraint en cirugias.id_combo, así que es seguro desvincular o eliminar directamente
    $stmt_unlink = $conn->prepare("UPDATE cirugias SET id_combo = NULL WHERE id_combo = ? AND id_hospital = ?");
    $stmt_unlink->execute([$id_combo, $id_hospital]);
    $cirugias_desvinculadas = $stmt_unlink->rowCount();

    // Eliminar los items del combo (FK constraint los borra automáticamente por CASCADE, pero explícito es más claro)
    $stmt_items = $conn->prepare("DELETE FROM cirugia_combo_items WHERE id_combo = ? AND id_hospital = ?");
    $stmt_items->execute([$id_combo, $id_hospital]);

    // Eliminar el combo
    $stmt = $conn->prepare("DELETE FROM cirugia_combos WHERE id_combo = ? AND id_hospital = ?");
    $stmt->execute([$id_combo, $id_hospital]);

    if ($stmt->rowCount() === 0) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Combo no encontrado.']);
        exit;
    }

    $conn->commit();

    audit_log('delete', 'surgery', "Combo eliminado ID: $id_combo" . ($cirugias_desvinculadas > 0 ? " (desvinculado de $cirugias_desvinculadas cirugías)" : ''), [
        'table_name' => 'cirugia_combos',
        'record_id' => $id_combo,
        'cirugias_desvinculadas' => $cirugias_desvinculadas
    ]);

    $msg = 'Combo eliminado correctamente.';
    if ($cirugias_desvinculadas > 0) {
        $msg .= " Se desvincularon $cirugias_desvinculadas cirugía(s) que usaban este combo.";
    }
    echo json_encode(['success' => true, 'message' => $msg, 'cirugias_desvinculadas' => $cirugias_desvinculadas]);
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
}