<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';



verify_session();

header('Content-Type: application/json');

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

        $stmt = $conn->prepare("SELECT * FROM compras WHERE id_compras = ? AND id_hospital = ?");
        $stmt->execute([$_GET['id'], $id_hospital]);
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
        error_log('Error en purchases/get_purchase.php: ' . $e->getMessage());
        echo json_encode(['error' => 'Error del servidor.']);
    }
} else {
    echo json_encode(['error' => 'ID no válido']);
}