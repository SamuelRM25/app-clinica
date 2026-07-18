<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

csrf_token();
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

    // Determinar qué columna de stock usar según el modo de venta
    // tipo_almacen: 'hospital' usa stock_hospital, otros usan cantidad_med
    $tipo_almacen = $data['tipo_almacen'] ?? '';
    $stock_column = ($tipo_almacen === 'hospital') ? 'stock_hospital' : 'cantidad_med';

    // Insert sale details and update inventory
    $stmt = $conn->prepare("INSERT INTO detalle_ventas (id_venta, id_inventario, cantidad_vendida, precio_unitario, id_hospital) VALUES (?, ?, ?, ?, ?)");
    $stmt_inv = $conn->prepare("UPDATE inventario SET {$stock_column} = {$stock_column} - ? WHERE id_inventario = ? AND id_hospital = ?");

    // Prepare stock check statement
    $stmt_check = $conn->prepare("SELECT {$stock_column} FROM inventario WHERE id_inventario = ? AND id_hospital = ?");

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
            $item['precio_unitario'],
            $id_hospital
        ]);

        // Update inventory (reduce quantity) - includes id_hospital filter and rowCount check
        $stmt_inv->execute([$item['cantidad'], $item['id_inventario'], $id_hospital]);
        if ($stmt_inv->rowCount() !== 1) {
            throw new Exception('No se pudo actualizar el stock del producto ID: ' . $item['id_inventario']);
        }
    }

    // ========================================================================
    // Si es Traslado: ajustar purchase_items y purchase_headers
    // ========================================================================
    if ($data['tipo_pago'] === 'Traslado') {
        $stmt_get_pi = $conn->prepare("
            SELECT i.id_purchase_item, pi.id AS pi_id, pi.purchase_header_id,
                   pi.unit_cost, pi.quantity AS pi_quantity,
                   i.nom_medicamento
            FROM inventario i
            JOIN purchase_items pi ON i.id_purchase_item = pi.id
            WHERE i.id_inventario = ? AND i.id_hospital = ?
        ");
        $stmt_upd_pi = $conn->prepare("
            UPDATE purchase_items
            SET quantity = quantity - ?,
                subtotal = subtotal - ROUND(? * unit_cost, 2)
            WHERE id = ? AND quantity >= ?
        ");
        $stmt_upd_ph = $conn->prepare("
            UPDATE purchase_headers
            SET total_amount = total_amount - ?
            WHERE id = ? AND total_amount >= ?
        ");
        $stmt_ins_pay = $conn->prepare("
            INSERT INTO purchase_payments
                (purchase_header_id, amount, payment_date, payment_method, notes, id_hospital)
            VALUES (?, ?, NOW(), 'Traslado', ?, ?)
        ");

        foreach ($data['items'] as $item) {
            $stmt_get_pi->execute([$item['id_inventario'], $id_hospital]);
            $pi_row = $stmt_get_pi->fetch(PDO::FETCH_ASSOC);

            if (!$pi_row) {
                throw new Exception(
                    "Error al ajustar compra por traslado: El medicamento '" .
                    ($item['nombre'] ?? 'desconocido') . "' no tiene una compra asociada. " .
                    "No se puede realizar el traslado sin un vínculo de compra."
                );
            }

            $reduction = round($item['cantidad'] * $pi_row['unit_cost'], 2);
            $product_name = $pi_row['nom_medicamento'];
            $destination = $data['nombre_cliente'];

            // Reducir cantidad y subtotal del purchase_item
            $stmt_upd_pi->execute([
                $item['cantidad'],
                $item['cantidad'],
                $pi_row['pi_id'],
                $item['cantidad']
            ]);
            if ($stmt_upd_pi->rowCount() === 0) {
                throw new Exception(
                    "Error al ajustar compra por traslado: Stock insuficiente en el ítem de compra " .
                    "para '{$product_name}'. Disponible en compra: {$pi_row['pi_quantity']}, " .
                    "solicitado: {$item['cantidad']}."
                );
            }

            // Reducir total de la factura de compra
            $stmt_upd_ph->execute([
                $reduction,
                $pi_row['purchase_header_id'],
                $reduction
            ]);

            // Registrar abono documentando el traslado
            $notes = "Traslado: {$item['cantidad']} unid de {$product_name} a {$destination}. Valor: Q{$reduction}";
            $stmt_ins_pay->execute([
                $pi_row['purchase_header_id'],
                $reduction,
                $notes,
                $id_hospital
            ]);
        }
    }

    // Clear reservations for this session (since cart is now processed)
    $stmt_res = $conn->prepare("DELETE FROM reservas_inventario WHERE session_id = ? AND id_hospital = ?");
    $stmt_res->execute([session_id(), $id_hospital]);

    // Commit transaction
    $conn->commit();

    audit_log('create', 'dispensary', "Venta #$id_venta - Total: Q{$data['total']} - Cliente: {$data['nombre_cliente']}", [
        'table_name' => 'ventas',
        'record_id' => (int)$id_venta,
        'new_data' => [
            'nombre_cliente' => $data['nombre_cliente'],
            'nit_cliente' => $data['nit_cliente'] ?? 'C/F',
            'tipo_pago' => $data['tipo_pago'],
            'total' => $data['total'],
            'estado' => $data['estado'],
            'items_count' => count($data['items'])
        ]
    ]);

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
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}