<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

verify_session();

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $csrf_token = $_GET['csrf_token'] ?? '';
    if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
        $_SESSION['purchase_message'] = 'Token CSRF inválido';
        $_SESSION['purchase_status'] = 'error';
        header('Location: index.php');
        exit;
    }

    try {
        $database = new Database();
        $conn = $database->getConnection();
        $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

        $stmt = $conn->prepare("DELETE FROM compras WHERE id_compras = ? AND id_hospital = ?");
        $result = $stmt->execute([$_GET['id'], $id_hospital]);

        if ($result) {
            audit_log('delete', 'purchases', "Compra antigua eliminada ID: " . $_GET['id'], ['id_compras' => $_GET['id']], $_SESSION['user_id']);
            $_SESSION['purchase_message'] = 'Compra eliminada correctamente';
            $_SESSION['purchase_status'] = 'success';
        } else {
            $_SESSION['purchase_message'] = 'Error al eliminar la compra';
            $_SESSION['purchase_status'] = 'error';
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        error_log("php/purchases/delete_purchase.php error: " . $e->getMessage());
        $_SESSION['purchase_message'] = 'Error del servidor';
        $_SESSION['purchase_status'] = 'error';
    }
} else {
    $_SESSION['purchase_message'] = 'ID de compra no válido';
    $_SESSION['purchase_status'] = 'error';
}

header('Location: index.php');
exit;
