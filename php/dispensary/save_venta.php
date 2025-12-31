<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer la zona horaria correcta
date_default_timezone_set('America/Guatemala');


verify_session();

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    // Get JSON data
    $json_data = file_get_contents('php://input');
    if (!$json_data) {
        throw new Exception('No data received');
    }
    
    $data = json_decode($json_data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }
    
    if (!isset($data['nombre_cliente']) || !isset($data['tipo_pago']) || !isset($data['items']) || empty($data['items'])) {
        throw new Exception('Datos incompletos');
    }
    
    $database = new Database();
    $conn = $database->getConnection();
    
    // Start transaction
    $conn->beginTransaction();
    
    // Insert sale record
    $stmt = $conn->prepare("INSERT INTO ventas (id_usuario, nombre_cliente, tipo_pago, total, estado, fecha_venta) VALUES (?, ?, ?, ?, ?, ?)");
    
    // Obtener la fecha y hora actual en la zona horaria de Guatemala
    $fecha_actual = date('Y-m-d H:i:s');
    
    $stmt->execute([
        $_SESSION['user_id'] ?? null,
        $data['nombre_cliente'],
        $data['tipo_pago'],
        $data['total'],
        $data['estado'],
        $fecha_actual
    ]);
    
    $id_venta = $conn->lastInsertId();
    
    // Insert sale details and update inventory
    $stmt = $conn->prepare("INSERT INTO detalle_ventas (id_venta, id_inventario, cantidad_vendida, precio_unitario) VALUES (?, ?, ?, ?)");
    $stmt_inv = $conn->prepare("UPDATE inventario SET cantidad_med = cantidad_med - ? WHERE id_inventario = ?");
    
    foreach ($data['items'] as $item) {
        // Validate item data
        if (!isset($item['id_inventario']) || !isset($item['cantidad']) || !isset($item['precio_unitario'])) {
            throw new Exception('Datos de item incompletos');
        }
        
        $stmt->execute([
            $id_venta,
            $item['id_inventario'],
            $item['cantidad'],
            $item['precio_unitario']
        ]);
        
        // Update inventory (reduce quantity)
        $stmt_inv->execute([$item['cantidad'], $item['id_inventario']]);
    }
    
    // Clear reservations for this session (since cart is now processed)
    $stmt_res = $conn->prepare("DELETE FROM reservas_inventario WHERE session_id = ?");
    $stmt_res->execute([session_id()]);

    // Commit transaction
    $conn->commit();
    
    echo json_encode(['status' => 'success', 'message' => 'Venta registrada correctamente', 'id_venta' => $id_venta]);
    
} catch (Exception $e) {
    // Rollback transaction on error if connection exists
    if (isset($conn) && $conn instanceof PDO) {
        $conn->rollBack();
    }
    
    // Log the error
    error_log('Error in save_venta.php: ' . $e->getMessage());
    
    // Return error response
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}