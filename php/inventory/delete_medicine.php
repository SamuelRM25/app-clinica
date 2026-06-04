<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

verify_session();

// CSRF validation (accepts POST/header/GET parameter)
$csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_GET['csrf_token'] ?? '';
if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
    $_SESSION['inventory_message'] = 'Token CSRF inválido';
    $_SESSION['inventory_status'] = 'error';
    header('Location: index.php');
    exit;
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        // Fetch old data before deleting for audit trail
        $stmt_old = $conn->prepare("SELECT nom_medicamento, codigo_barras, mol_medicamento, presentacion_med, casa_farmaceutica, cantidad_med FROM inventario WHERE id_inventario = ? AND id_hospital = ?");
        $stmt_old->execute([$_GET['id'], $id_hospital]);
        $oldData = $stmt_old->fetch(PDO::FETCH_ASSOC);

        // Prepare and execute the delete statement
        $stmt = $conn->prepare("DELETE FROM inventario WHERE id_inventario = ? AND id_hospital = ?");
        $result = $stmt->execute([$_GET['id'], $id_hospital]);

        if ($result) {
            audit_log('delete', 'inventory', "Medicamento eliminado: {$oldData['nom_medicamento']} (ID: {$_GET['id']})", [
                'table_name' => 'inventario',
                'record_id' => (int)$_GET['id'],
                'old_data' => $oldData
            ]);
            $_SESSION['inventory_message'] = 'Medicamento eliminado correctamente';
            $_SESSION['inventory_status'] = 'success';
        } else {
            $_SESSION['inventory_message'] = 'Error al eliminar el medicamento';
            $_SESSION['inventory_status'] = 'error';
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        error_log("php/inventory/delete_medicine.php error: " . $e->getMessage());
        $_SESSION['inventory_status'] = 'error';
    }
} else {
    $_SESSION['inventory_message'] = 'ID de medicamento no válido';
    $_SESSION['inventory_status'] = 'error';
}

// Redirect back to inventory page
header('Location: index.php');
exit;