<?php
// api/balance_inventory.php - Equilibrar inventario (igualar stock sistema con físico)
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/multitenant.php';
require_once '../../../includes/module_guard.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id_inventario']) || !isset($_POST['nueva_cantidad'])) {
    echo json_encode(['success' => false, 'message' => 'Petición inválida']);
    exit;
}

// CSRF validation
verify_csrf_token();

$id_inventario = intval($_POST['id_inventario']);
$nueva_cantidad = intval($_POST['nueva_cantidad']);
$id_hospital = hospital_id();
$id_usuario = $_SESSION['user_id'];

if ($nueva_cantidad < 0) {
    echo json_encode(['success' => false, 'message' => 'La cantidad no puede ser negativa']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Create audit log table if not exists
    $conn->exec("CREATE TABLE IF NOT EXISTS ajustes_inventario (
        id_ajuste INT AUTO_INCREMENT PRIMARY KEY,
        id_inventario INT NOT NULL,
        id_hospital INT NOT NULL,
        id_usuario INT NOT NULL,
        cantidad_anterior INT NOT NULL,
        cantidad_nueva INT NOT NULL,
        motivo VARCHAR(255),
        fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (id_inventario),
        INDEX (id_hospital),
        INDEX (fecha)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->beginTransaction();

    // Verify item belongs to this hospital
    $stmt = $conn->prepare("SELECT id_inventario, nom_medicamento, cantidad_med FROM inventario WHERE id_inventario = ? AND id_hospital = ?");
    $stmt->execute([$id_inventario, $id_hospital]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception("Medicamento no encontrado o no pertenece a este hospital");
    }

    $cantidad_anterior = $item['cantidad_med'];
    $nombre = $item['nom_medicamento'];

    // Update inventory quantity
    $stmt = $conn->prepare("UPDATE inventario SET cantidad_med = ? WHERE id_inventario = ? AND id_hospital = ?");
    $stmt->execute([$nueva_cantidad, $id_inventario, $id_hospital]);

    // Update conteo_fisico record to mark as balanced
    $stmt = $conn->prepare("
        UPDATE conteo_fisico 
        SET estado = 'Listo', 
            fecha_equilibrado = CURRENT_TIMESTAMP,
            diferencia = 0,
            cantidad_fisica = ?
        WHERE id_inventario = ? AND id_hospital = ? AND DATE(fecha_conteo) = CURDATE()
    ");
    $stmt->execute([$nueva_cantidad, $id_inventario, $id_hospital]);

    // Log the adjustment
    $stmt = $conn->prepare("
        INSERT INTO ajustes_inventario (id_inventario, id_hospital, id_usuario, cantidad_anterior, cantidad_nueva, motivo, fecha)
        VALUES (?, ?, ?, ?, ?, 'Equilibrio de conteo físico', NOW())
    ");
    $stmt->execute([$id_inventario, $id_hospital, $id_usuario, $cantidad_anterior, $nueva_cantidad]);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => "Inventario equilibrado: {$nombre} de {$cantidad_anterior} a {$nueva_cantidad}",
        'anterior' => $cantidad_anterior,
        'nuevo' => $nueva_cantidad
    ]);
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error balancing inventory: " . $e->getMessage());
        error_log("inventory/api/balance_inventory.php error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error del servidor.']);
}
