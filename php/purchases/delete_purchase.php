<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        // Prepare and execute the delete statement
        $stmt = $conn->prepare("DELETE FROM compras WHERE id_compras = ?");
        $result = $stmt->execute([$_GET['id']]);

        if ($result) {
            $_SESSION['purchase_message'] = 'Compra eliminada correctamente';
            $_SESSION['purchase_status'] = 'success';
        } else {
            $_SESSION['purchase_message'] = 'Error al eliminar la compra';
            $_SESSION['purchase_status'] = 'error';
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        $_SESSION['purchase_message'] = 'Error: ' . $e->getMessage();
        $_SESSION['purchase_status'] = 'error';
    }
} else {
    $_SESSION['purchase_message'] = 'ID de compra no válido';
    $_SESSION['purchase_status'] = 'error';
}

// Redirect back to purchases page
header('Location: index.php');
exit;