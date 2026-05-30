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

        // Prepare and execute the delete statement
        $stmt = $conn->prepare("DELETE FROM inventario WHERE id_inventario = ? AND id_hospital = ?");
        $result = $stmt->execute([$_GET['id'], $id_hospital]);

        if ($result) {
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