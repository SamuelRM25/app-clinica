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
        $stmt = $conn->prepare("DELETE FROM inventario WHERE id_inventario = ?");
        $result = $stmt->execute([$_GET['id']]);

        if ($result) {
            $_SESSION['inventory_message'] = 'Medicamento eliminado correctamente';
            $_SESSION['inventory_status'] = 'success';
        } else {
            $_SESSION['inventory_message'] = 'Error al eliminar el medicamento';
            $_SESSION['inventory_status'] = 'error';
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        $_SESSION['inventory_message'] = 'Error: ' . $e->getMessage();
        $_SESSION['inventory_status'] = 'error';
    }
} else {
    $_SESSION['inventory_message'] = 'ID de medicamento no válido';
    $_SESSION['inventory_status'] = 'error';
}

// Redirect back to inventory page
header('Location: index.php');
exit;