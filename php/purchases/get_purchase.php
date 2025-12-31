<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

header('Content-Type: application/json');

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        $stmt = $conn->prepare("SELECT * FROM compras WHERE id_compras = ?");
        $stmt->execute([$_GET['id']]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($purchase) {
            // Format date for HTML date input
            $purchase['fecha_compra'] = date('Y-m-d', strtotime($purchase['fecha_compra']));
            echo json_encode($purchase);
        } else {
            echo json_encode(['error' => 'Compra no encontrada']);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'ID no válido']);
}