<?php
// inventory/save_insumos.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
    exit;
}

require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $id_inventario = $_POST['id_inventario'] ?? null;
    $cantidad = $_POST['cantidad'] ?? null;
    $precio_venta = $_POST['precio_venta'] ?? null;

    if ($id_inventario === null || $cantidad === null || $precio_venta === null || $id_inventario === '' || $cantidad === '' || $precio_venta === '') {
        echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
        exit;
    }

    try {
        $database = new Database();
        $conn = $database->getConnection();
        $conn->beginTransaction();

        // 1. Verificar stock actual
        $stmt = $conn->prepare("SELECT cantidad_med FROM inventario WHERE id_inventario = ? FOR UPDATE");
        $stmt->execute([$id_inventario]);
        $current_stock = $stmt->fetchColumn();

        if ($current_stock === false) {
            throw new Exception("Producto no encontrado");
        }

        if ($current_stock < $cantidad) {
            throw new Exception("Stock insuficiente (Disponible: $current_stock)");
        }

        // 2. Rebajar stock
        $new_stock = $current_stock - $cantidad;
        $stmt = $conn->prepare("UPDATE inventario SET cantidad_med = ? WHERE id_inventario = ?");
        $stmt->execute([$new_stock, $id_inventario]);

        // 3. Registrar en tabla insumos
        $stmt = $conn->prepare("INSERT INTO insumos (id_inventario, cantidad, precio_venta, id_usuario) VALUES (?, ?, ?, ?)");
        $stmt->execute([$id_inventario, $cantidad, $precio_venta, $user_id]);

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Insumo registrado correctamente']);

    } catch (Exception $e) {
        if (isset($conn))
            $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
}
