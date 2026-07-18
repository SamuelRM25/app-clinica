<?php
// surgery/api/save_descuento_cirugia.php - Aplica un descuento a una cirugía
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
$concepto = substr(trim($_POST['concepto'] ?? ''), 0, 255);
$monto = (float)($_POST['monto'] ?? 0);

if ($id_cirugia <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de cirugía inválido']);
    exit;
}
if ($concepto === '') {
    echo json_encode(['success' => false, 'message' => 'El concepto del descuento es obligatorio']);
    exit;
}
if ($monto <= 0) {
    echo json_encode(['success' => false, 'message' => 'El monto del descuento debe ser mayor a cero']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Verificar que la cirugía existe y pertenece al hospital
    $stmtC = $conn->prepare("SELECT id_cirugia, estado FROM cirugias WHERE id_cirugia = ? AND id_hospital = ?");
    $stmtC->execute([$id_cirugia, $id_hospital]);
    $cirugia = $stmtC->fetch(PDO::FETCH_ASSOC);
    if (!$cirugia) {
        echo json_encode(['success' => false, 'message' => 'Cirugía no encontrada']);
        exit;
    }
    if (!in_array($cirugia['estado'], ['Programada', 'En_Curso', 'Finalizada'], true)) {
        echo json_encode(['success' => false, 'message' => 'No se pueden aplicar descuentos a una cirugía en estado ' . $cirugia['estado']]);
        exit;
    }

    $user_id = (int)$_SESSION['user_id'];

    $conn->beginTransaction();

    $stmt = $conn->prepare("
        INSERT INTO cirugia_descuentos (id_cirugia, concepto, monto, creado_por, id_hospital)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$id_cirugia, $concepto, $monto, $user_id, $id_hospital]);

    // Calcular el total acumulado de descuentos activos
    $stmtT = $conn->prepare("
        SELECT COALESCE(SUM(monto), 0) AS total
        FROM cirugia_descuentos
        WHERE id_cirugia = ? AND id_hospital = ? AND cancelado = 0
    ");
    $stmtT->execute([$id_cirugia, $id_hospital]);
    $total_descuentos = (float)$stmtT->fetchColumn();

    $conn->commit();

    audit_log('create', 'surgery', "Descuento aplicado a cirugía #$id_cirugia: $concepto - Q$monto", [
        'table_name' => 'cirugia_descuentos',
        'record_id' => (int)$conn->lastInsertId(),
        'new_data' => ['id_cirugia' => $id_cirugia, 'concepto' => $concepto, 'monto' => $monto],
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Descuento aplicado correctamente',
        'concepto' => $concepto,
        'monto' => $monto,
        'total_descuentos' => $total_descuentos,
    ]);
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    error_log('save_descuento_cirugia.php error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar el descuento',
        'debug' => ($_SESSION['tipoUsuario'] ?? '') === 'admin' ? $e->getMessage() : null,
    ]);
}