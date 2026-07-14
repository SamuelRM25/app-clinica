<?php
// surgery/api/add_consumo_cirugia.php - Agregar medicamento usado en cirugía (descuenta stock_quirofano)
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
$user_id = (int)($_SESSION['user_id'] ?? 0);
$id_cirugia = (int)($_POST['id_cirugia'] ?? 0);
$id_inventario = (int)($_POST['id_inventario'] ?? 0);
$cantidad = (float)($_POST['cantidad'] ?? 0);

if (!$id_cirugia || !$id_inventario || $cantidad <= 0) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Verificar que la cirugía existe y está en curso
    $stmtC = $conn->prepare("SELECT estado, id_paciente FROM cirugias WHERE id_cirugia = ? AND id_hospital = ?");
    $stmtC->execute([$id_cirugia, $id_hospital]);
    $cirugia = $stmtC->fetch(PDO::FETCH_ASSOC);
    if (!$cirugia) throw new Exception('Cirugía no encontrada');
    if (!in_array($cirugia['estado'], ['Programada', 'En_Curso'], true)) {
        throw new Exception('Solo se pueden agregar medicamentos a cirugías programadas o en curso');
    }

    // Verificar inventario
    $stmtI = $conn->prepare("SELECT id_inventario, nom_medicamento, stock_quirofano, precio_hospital, precio_venta FROM inventario WHERE id_inventario = ? AND id_hospital = ?");
    $stmtI->execute([$id_inventario, $id_hospital]);
    $inv = $stmtI->fetch(PDO::FETCH_ASSOC);
    if (!$inv) throw new Exception('Medicamento no encontrado');
    if ((float)$inv['stock_quirofano'] < $cantidad) {
        throw new Exception('Stock insuficiente en Quirófano. Disponible: ' . (float)$inv['stock_quirofano']);
    }

    $precio_unitario = (float)($inv['precio_hospital'] ?? $inv['precio_venta'] ?? 0);
    $subtotal = $precio_unitario * $cantidad;

    $conn->beginTransaction();

    // Descontar stock_quirofano
    $stmtD = $conn->prepare("UPDATE inventario SET stock_quirofano = stock_quirofano - ? WHERE id_inventario = ? AND stock_quirofano >= ? AND id_hospital = ?");
    $stmtD->execute([$cantidad, $id_inventario, $cantidad, $id_hospital]);
    if ($stmtD->rowCount() === 0) {
        throw new Exception('No se pudo descontar el stock');
    }

    // Insert en cirugia_consumos
    $stmtC2 = $conn->prepare("INSERT INTO cirugia_consumos (id_cirugia, id_inventario, cantidad, precio_unitario, subtotal, id_hospital) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtC2->execute([$id_cirugia, $id_inventario, $cantidad, $precio_unitario, $subtotal, $id_hospital]);

    $conn->commit();

    audit_log('create', 'surgery', "Consumo cirugía #$id_cirugia: {$inv['nom_medicamento']} x $cantidad", [
        'table_name' => 'cirugia_consumos',
        'new_data' => ['id_cirugia' => $id_cirugia, 'id_inventario' => $id_inventario, 'cantidad' => $cantidad, 'subtotal' => $subtotal]
    ]);

    echo json_encode(['success' => true, 'message' => 'Medicamento agregado y stock descontado', 'subtotal' => $subtotal]);
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    error_log('add_consumo_cirugia: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}