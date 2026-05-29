<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

// Establecer la zona horaria correcta
date_default_timezone_set('America/Guatemala');


verify_session();

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

    // CSRF validation for JSON requests via X-CSRF-Token header
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($csrfHeader) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfHeader)) {
        throw new Exception('Token CSRF inválido');
    }

    if (!isset($data['nombre_cliente']) || !isset($data['tipo_pago']) || !isset($data['items']) || empty($data['items'])) {
        throw new Exception('Datos incompletos');
    }

    // Validar tipo de pago contra lista blanca
    if (!validar_tipo_pago($data['tipo_pago'])) {
        throw new Exception('Tipo de pago inválido: ' . $data['tipo_pago']);
    }

    $database = new Database();
    $conn = $database->getConnection();

    // Start transaction
    $conn->beginTransaction();

    // Insert sale record
    $stmt = $conn->prepare("INSERT INTO ventas (id_usuario, nombre_cliente, nit_cliente, document_identifier, tipo_pago, total, estado, fecha_venta, id_hospital) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    // Obtener la fecha y hora actual en la zona horaria de Guatemala
    $fecha_actual = date('Y-m-d H:i:s');

    // Preparar identificador de documento
    $doc_type = $data['document_type'] ?? '';
    $doc_num = $data['document_number'] ?? '';
    $doc_identifier = trim($doc_type . ' ' . $doc_num);

    $stmt->execute([
        $_SESSION['user_id'] ?? null,
        $data['nombre_cliente'],
        $data['nit_cliente'] ?? 'C/F',
        $doc_identifier ?: null,
        $data['tipo_pago'],
        $data['total'],
        $data['estado'],
        $fecha_actual,
        $id_hospital
    ]);

    $id_venta = $conn->lastInsertId();

    // Insert sale details and update inventory
    $stmt = $conn->prepare("INSERT INTO detalle_ventas (id_venta, id_inventario, cantidad_vendida, precio_unitario) VALUES (?, ?, ?, ?)");
    $stmt_inv = $conn->prepare("UPDATE inventario SET cantidad_med = cantidad_med - ? WHERE id_inventario = ?");

    // Prepare stock check statement
    $stmt_check = $conn->prepare("SELECT cantidad_med FROM inventario WHERE id_inventario = ? AND id_hospital = ?");

    foreach ($data['items'] as $item) {
        // Validate item data
        if (!isset($item['id_inventario']) || !isset($item['cantidad']) || !isset($item['precio_unitario'])) {
            throw new Exception('Datos de item incompletos');
        }

        // Check stock availability before decrementing
        $stmt_check->execute([$item['id_inventario'], $id_hospital]);
        $current_stock = $stmt_check->fetchColumn();
        if ($current_stock === false) {
            throw new Exception('Producto no encontrado en inventario');
        }
        if ((int)$current_stock < (int)$item['cantidad']) {
            throw new Exception('Stock insuficiente. Disponible: ' . (int)$current_stock . ', solicitado: ' . (int)$item['cantidad']);
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
    $stmt_res = $conn->prepare("DELETE FROM reservas_inventario WHERE session_id = ? AND id_hospital = ?");
    $stmt_res->execute([session_id(), $id_hospital]);

    // Commit transaction
    $conn->commit();

    audit_log('venta_creada', 'Venta #' . $id_venta . ' - Total: Q' . $data['total'] . ' - Cliente: ' . $data['nombre_cliente'], $_SESSION['user_id'] ?? null);

    echo json_encode(['status' => 'success', 'message' => 'Venta registrada correctamente', 'id_venta' => $id_venta]);

} catch (Exception $e) {
    // Rollback transaction on error if connection exists
    if (isset($conn) && $conn instanceof PDO) {
        $conn->rollBack();
    }

    // Log the error
    error_log('Error in save_venta.php: ' . $e->getMessage());

    // Return error response
    error_log('Error en dispensary/save_venta.php: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error del servidor.']);
}