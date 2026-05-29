<?php
// api/save_physical_count.php - Guardar conteo físico de inventario
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id_inventario'])) {
    echo json_encode(['success' => false, 'message' => 'Petición inválida']);
    exit;
}

// CSRF validation
verify_csrf_token();

$id_inventario = intval($_POST['id_inventario']);
$cantidad_fisica = $_POST['cantidad_fisica'] !== '' ? intval($_POST['cantidad_fisica']) : null;
$diferencia = intval($_POST['diferencia'] ?? 0);
$estado = $_POST['estado'] ?? 'Pendiente';
$id_hospital = hospital_id();
$id_usuario = $_SESSION['user_id'];

try {
    $database = new Database();
    $conn = $database->getConnection();

    if ($cantidad_fisica === null) {
        // Remove count (clear the physical count)
        $stmt = $conn->prepare("DELETE FROM conteo_fisico WHERE id_inventario = ? AND id_hospital = ? AND DATE(fecha_conteo) = CURDATE()");
        $stmt->execute([$id_inventario, $id_hospital]);
    } else {
        // Check if today's count exists
        $stmt = $conn->prepare("SELECT id_conteo FROM conteo_fisico WHERE id_inventario = ? AND id_hospital = ? AND DATE(fecha_conteo) = CURDATE()");
        $stmt->execute([$id_inventario, $id_hospital]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update existing
            $stmt = $conn->prepare("
                UPDATE conteo_fisico 
                SET cantidad_fisica = ?, diferencia = ?, estado = ?, fecha_conteo = CURRENT_TIMESTAMP
                WHERE id_conteo = ?
            ");
            $stmt->execute([$cantidad_fisica, $diferencia, $estado, $existing['id_conteo']]);
        } else {
            // Insert new
            $stmt = $conn->prepare("
                INSERT INTO conteo_fisico (id_inventario, id_hospital, id_usuario, cantidad_sistema, cantidad_fisica, diferencia, estado)
                VALUES (?, ?, ?, (SELECT cantidad_med FROM inventario WHERE id_inventario = ?), ?, ?, ?)
            ");
            $stmt->execute([
                $id_inventario,
                $id_hospital,
                $id_usuario,
                $id_inventario,
                $cantidad_fisica,
                $diferencia,
                $estado
            ]);
        }
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Error saving physical count: " . $e->getMessage());
        error_log("inventory/api/save_physical_count.php error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error del servidor.']);
}
