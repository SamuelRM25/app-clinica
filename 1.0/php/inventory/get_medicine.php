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

        $stmt = $conn->prepare("SELECT * FROM inventario WHERE id_inventario = ?");
        $stmt->execute([$_GET['id']]);
        $medicine = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($medicine) {
            // Format date for HTML date input
            $medicine['fecha_adquisicion'] = date('Y-m-d', strtotime($medicine['fecha_adquisicion']));
            $medicine['fecha_vencimiento'] = date('Y-m-d', strtotime($medicine['fecha_vencimiento']));
            echo json_encode($medicine);
        } else {
            echo json_encode(['error' => 'Medicamento no encontrado']);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'ID no válido']);
}