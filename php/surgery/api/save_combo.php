<?php
// surgery/api/save_combo.php - CRUD de combos de operación (parent + children)
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['tipoUsuario'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $id_combo = $_POST['id_combo'] ?? '';
    $codigo = substr(trim($_POST['codigo'] ?? ''), 0, 30);
    $nombre = substr(trim($_POST['nombre'] ?? ''), 0, 150);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio_total = (float) ($_POST['precio_total'] ?? 0);
    $estado = $_POST['estado'] ?? 'Activo';

    if (empty($codigo) || empty($nombre)) {
        echo json_encode(['success' => false, 'message' => 'Código y nombre son obligatorios']);
        exit;
    }
    if (!in_array($estado, ['Activo', 'Inactivo'], true)) $estado = 'Activo';

    $items_json = $_POST['items_json'] ?? '[]';
    $items = json_decode($items_json, true);
    if (!is_array($items)) $items = [];

    $id_hospital = $_SESSION['id_hospital'] ?? 0;

    $conn->beginTransaction();

    if (empty($id_combo)) {
        $stmt_check = $conn->prepare("SELECT id_combo FROM cirugia_combos WHERE codigo = ? AND id_hospital = ?");
        $stmt_check->execute([$codigo, $id_hospital]);
        if ($stmt_check->fetch()) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Ya existe un combo con el código ' . $codigo]);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO cirugia_combos (codigo, nombre, descripcion, precio_total, estado, id_hospital) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$codigo, $nombre, $descripcion ?: null, $precio_total, $estado, $id_hospital]);
        $newId = (int)$conn->lastInsertId();

        $stmtItem = $conn->prepare("INSERT INTO cirugia_combo_items (id_combo, id_inventario, cantidad, tipo, categoria, descripcion, monto, id_hospital) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $count = 0;
        $med_count = 0;
        foreach ($items as $it) {
            $tipo = $it['tipo'] ?? '';
            $categoria = substr(trim((string)($it['categoria'] ?? '')), 0, 50);
            $desc = substr(trim((string)($it['descripcion'] ?? '')), 0, 150);
            $monto = (float)($it['monto'] ?? 0);
            $id_inventario = isset($it['id_inventario']) && $it['id_inventario'] ? (int)$it['id_inventario'] : null;
            $cantidad = isset($it['cantidad']) && $it['cantidad'] > 0 ? (float)$it['cantidad'] : 1;
            if (!in_array($tipo, ['Ganancia', 'Gasto'], true) || $categoria === '') continue;
            $stmtItem->execute([$newId, $id_inventario, $cantidad, $tipo, $categoria, $desc ?: null, $monto, $id_hospital]);
            $count++;
            if ($id_inventario) $med_count++;
        }

        $conn->commit();

        audit_log('create', 'surgery', "Combo creado: $nombre ($codigo) con $count items ($med_count medicamentos)", [
            'table_name' => 'cirugia_combos',
            'record_id' => $newId,
            'new_data' => ['codigo' => $codigo, 'nombre' => $nombre, 'precio_total' => $precio_total, 'items' => $count, 'medicamentos' => $med_count],
        ]);

        echo json_encode(['success' => true, 'message' => "Combo creado con $count items" . ($med_count > 0 ? " ($med_count medicamentos)" : ''), 'id_combo' => $newId]);
    } else {
        $id_combo_int = (int)$id_combo;

        $fetchStmt = $conn->prepare("SELECT codigo, nombre, descripcion, precio_total, estado FROM cirugia_combos WHERE id_combo = ? AND id_hospital = ?");
        $fetchStmt->execute([$id_combo_int, $id_hospital]);
        $oldData = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("UPDATE cirugia_combos SET codigo = ?, nombre = ?, descripcion = ?, precio_total = ?, estado = ? WHERE id_combo = ? AND id_hospital = ?");
        $stmt->execute([$codigo, $nombre, $descripcion ?: null, $precio_total, $estado, $id_combo_int, $id_hospital]);

        // Wipe and reinsert items (simpler than diff; items are usually small)
        $delStmt = $conn->prepare("DELETE FROM cirugia_combo_items WHERE id_combo = ?");
        $delStmt->execute([$id_combo_int]);

        $stmtItem = $conn->prepare("INSERT INTO cirugia_combo_items (id_combo, id_inventario, cantidad, tipo, categoria, descripcion, monto, id_hospital) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $count = 0;
        $med_count = 0;
        foreach ($items as $it) {
            $tipo = $it['tipo'] ?? '';
            $categoria = substr(trim((string)($it['categoria'] ?? '')), 0, 50);
            $desc = substr(trim((string)($it['descripcion'] ?? '')), 0, 150);
            $monto = (float)($it['monto'] ?? 0);
            $id_inventario = isset($it['id_inventario']) && $it['id_inventario'] ? (int)$it['id_inventario'] : null;
            $cantidad = isset($it['cantidad']) && $it['cantidad'] > 0 ? (float)$it['cantidad'] : 1;
            if (!in_array($tipo, ['Ganancia', 'Gasto'], true) || $categoria === '') continue;
            $stmtItem->execute([$id_combo_int, $id_inventario, $cantidad, $tipo, $categoria, $desc ?: null, $monto, $id_hospital]);
            $count++;
            if ($id_inventario) $med_count++;
        }

        $conn->commit();

        audit_log('update', 'surgery', "Combo actualizado: $nombre", [
            'table_name' => 'cirugia_combos',
            'record_id' => $id_combo_int,
            'old_data' => $oldData,
            'new_data' => ['codigo' => $codigo, 'nombre' => $nombre, 'precio_total' => $precio_total, 'items' => $count, 'medicamentos' => $med_count],
        ]);

        echo json_encode(['success' => true, 'message' => "Combo actualizado: $count items" . ($med_count > 0 ? " ($med_count medicamentos)" : '')]);
    }
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    error_log('save_combo error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar el combo.',
        'debug' => ($_SESSION['tipoUsuario'] ?? '') === 'admin' ? $e->getMessage() : null,
    ]);
}