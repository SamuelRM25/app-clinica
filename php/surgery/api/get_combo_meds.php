<?php
// surgery/api/get_combo_meds.php
// Devuelve los medicamentos asociados a un combo (para preview antes de iniciar cirugía)
session_start();
require_once '../../../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);
    $id_combo = (int)($_GET['id_combo'] ?? 0);

    if (!$id_combo) {
        echo json_encode(['success' => false, 'message' => 'ID de combo requerido']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id_combo, codigo, nombre, precio_total FROM cirugia_combos WHERE id_combo = ? AND id_hospital = ?");
    $stmt->execute([$id_combo, $id_hospital]);
    $combo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$combo) {
        echo json_encode(['success' => false, 'message' => 'Combo no encontrado']);
        exit;
    }

    $stmtMeds = $conn->prepare("
        SELECT cci.id_inventario, cci.cantidad, cci.categoria, cci.descripcion, cci.monto,
               inv.nom_medicamento, inv.presentacion_med, inv.stock_quirofano, inv.precio_venta, inv.precio_hospital
        FROM cirugia_combo_items cci
        LEFT JOIN inventario inv ON cci.id_inventario = inv.id_inventario
        WHERE cci.id_combo = ? AND cci.id_hospital = ? AND cci.id_inventario IS NOT NULL
        ORDER BY cci.tipo, cci.categoria
    ");
    $stmtMeds->execute([$id_combo, $id_hospital]);
    $medicamentos = $stmtMeds->fetchAll(PDO::FETCH_ASSOC);

    // Calcular alertas de stock
    foreach ($medicamentos as &$med) {
        $stock = (float)($med['stock_quirofano'] ?? 0);
        $req = (float)($med['cantidad'] ?? 1);
        $med['stock_suficiente'] = $stock >= $req;
        $med['stock_faltante'] = max(0, $req - $stock);
    }

    echo json_encode([
        'success' => true,
        'combo' => $combo,
        'medicamentos' => $medicamentos
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}