<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

verify_session();

header('Content-Type: application/json');

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        $stmt = $conn->prepare("
            SELECT i.*, pi.unit_cost as precio_compra_original
            FROM inventario i
            LEFT JOIN purchase_items pi ON i.id_purchase_item = pi.id
            WHERE i.id_inventario = ? AND i.id_hospital = ?
        ");
        $stmt->execute([$_GET['id'], $id_hospital]);
        $medicine = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($medicine) {
            // Format date for HTML date input
            $medicine['fecha_adquisicion'] = date('Y-m-d', strtotime($medicine['fecha_adquisicion']));
            $medicine['fecha_vencimiento'] = date('Y-m-d', strtotime($medicine['fecha_vencimiento']));

            // Use purchase price if not set in inventario
            if (!isset($medicine['precio_compra']) || $medicine['precio_compra'] == 0) {
                $medicine['precio_compra'] = $medicine['precio_compra_original'] ?? 0;
            }

            echo json_encode($medicine);
        } else {
            echo json_encode(['error' => 'Medicamento no encontrado']);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        error_log('Error en php/inventory/get_medicine.php: ' . $e->getMessage());
        echo json_encode(['error' => 'Error del servidor.']);
    }
} else {
    echo json_encode(['error' => 'ID no válido']);
}