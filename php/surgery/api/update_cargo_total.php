<?php
// surgery/api/update_cargo_total.php
// Editar el cargo_total de una cirugía activa (Programada o En_Curso)
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);
$id_cirugia = (int)($_POST['id_cirugia'] ?? 0);
$new_cargo = (float)($_POST['cargo_total'] ?? 0);

if (!$id_cirugia || $new_cargo < 0) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("SELECT estado, cargo_total, numero_cirugia FROM cirugias WHERE id_cirugia = ? AND id_hospital = ?");
    $stmt->execute([$id_cirugia, $id_hospital]);
    $cirugia = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cirugia) throw new Exception('Cirugía no encontrada');
    if (!in_array($cirugia['estado'], ['Programada', 'En_Curso'], true)) {
        throw new Exception('Solo se puede editar el cargo total de cirugías activas (Programada o En_Curso)');
    }

    $old_cargo = (float)$cirugia['cargo_total'];

    $update = $conn->prepare("UPDATE cirugias SET cargo_total = ? WHERE id_cirugia = ? AND id_hospital = ?");
    $update->execute([$new_cargo, $id_cirugia, $id_hospital]);

    audit_log('update', 'surgery', "Cargo total actualizado: Q{$old_cargo} → Q{$new_cargo} (Cirugía #{$cirugia['numero_cirugia']})", [
        'id_cirugia' => $id_cirugia,
        'old_cargo' => $old_cargo,
        'new_cargo' => $new_cargo,
        'numero_cirugia' => $cirugia['numero_cirugia'],
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Cargo total actualizado correctamente',
        'old_cargo' => $old_cargo,
        'new_cargo' => $new_cargo,
    ]);
} catch (Exception $e) {
    error_log('update_cargo_total: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}