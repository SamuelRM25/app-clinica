<?php
/**
 * API: Add charge to surgery account (cuenta hospitalaria)
 * Pattern based on hospitalization/api/add_cargo.php but for surgery
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/multitenant.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

date_default_timezone_set('America/Guatemala');

try {
    // CSRF validation
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (empty($csrfHeader) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfHeader)) {
        throw new Exception('Token CSRF inválido');
    }

    $database = new Database();
    $conn = $database->getConnection();

    // Build batch array
    $cargos_to_process = [];
    if (isset($_POST['cargos']) && is_array($_POST['cargos'])) {
        $cargos_to_process = $_POST['cargos'];
    } elseif (isset($_POST['id_cirugia'])) {
        $cargos_to_process[] = [
            'id_cirugia' => $_POST['id_cirugia'],
            'tipo_cargo' => $_POST['tipo_cargo'] ?? '',
            'descripcion' => $_POST['descripcion'] ?? '',
            'cantidad' => $_POST['cantidad'] ?? 1,
            'precio_unitario' => $_POST['precio_unitario'] ?? 0,
            'precio_costo' => $_POST['precio_costo'] ?? 0,
            'id_inventario' => $_POST['id_inventario'] ?? null,
        ];
    } else {
        throw new Exception('No se recibieron datos de cargos');
    }

    $registrado_por = $_SESSION['user_id'];
    $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);
    $fecha_cargo = date('Y-m-d H:i:s');

    $conn->beginTransaction();

    foreach ($cargos_to_process as $index => $cargo_data) {
        $id_cirugia = intval($cargo_data['id_cirugia'] ?? 0);
        if (!$id_cirugia) throw new Exception("ID de cirugía requerido en cargo #$index");

        $tipo_cargo = trim($cargo_data['tipo_cargo'] ?? '');
        if ($tipo_cargo === '' || strlen($tipo_cargo) > 50) {
            throw new Exception('Tipo de cargo inválido (máx 50 caracteres)');
        }

        $descripcion = trim($cargo_data['descripcion'] ?? '');
        if ($descripcion === '') throw new Exception("Descripción requerida en cargo #$index");

        $cantidad = floatval($cargo_data['cantidad'] ?? 1);
        $precio_unitario = floatval($cargo_data['precio_unitario'] ?? 0);
        $precio_costo = isset($cargo_data['precio_costo']) ? floatval($cargo_data['precio_costo']) : 0;

        if ($cantidad <= 0) throw new Exception("Cantidad debe ser > 0 en cargo #$index");
        if ($precio_unitario < 0) throw new Exception("Precio unitario inválido en cargo #$index");

        $id_inventario = isset($cargo_data['id_inventario']) ? intval($cargo_data['id_inventario']) : null;

        // Costo: si es 0 y está vinculado a inventario, tomarlo del inventario
        if ($id_inventario && $precio_costo <= 0) {
            $stmtInvC = $conn->prepare("
                SELECT COALESCE(NULLIF(i.precio_compra, 0), pi.unit_cost, 0) as costo
                FROM inventario i
                LEFT JOIN purchase_items pi ON i.id_purchase_item = pi.id
                WHERE i.id_inventario = ? AND i.id_hospital = ?
            ");
            $stmtInvC->execute([$id_inventario, $id_hospital]);
            $costRow = $stmtInvC->fetch(PDO::FETCH_ASSOC);
            if ($costRow) $precio_costo = (float)$costRow['costo'];
        }

        // Get id_encamamiento for this cirugía
        $stmtC = $conn->prepare("SELECT id_encamamiento, estado FROM cirugias WHERE id_cirugia = ? AND id_hospital = ?");
        $stmtC->execute([$id_cirugia, $id_hospital]);
        $cirugia = $stmtC->fetch(PDO::FETCH_ASSOC);

        if (!$cirugia) throw new Exception("Cirugía #$id_cirugia no encontrada");
        if (!in_array($cirugia['estado'], ['Programada', 'En_Curso'], true)) {
            throw new Exception("Solo se pueden agregar cargos a cirugías activas (estado actual: {$cirugia['estado']})");
        }
        if (!$cirugia['id_encamamiento']) {
            throw new Exception("La cirugía no tiene cuenta hospitalaria. Inicie la cirugía primero.");
        }

        $id_encamamiento = (int)$cirugia['id_encamamiento'];

        // Get id_cuenta
        $stmt_cuenta = $conn->prepare("SELECT id_cuenta FROM cuenta_hospitalaria WHERE id_encamamiento = ? AND id_hospital = ?");
        $stmt_cuenta->execute([$id_encamamiento, $id_hospital]);
        $cuenta = $stmt_cuenta->fetch(PDO::FETCH_ASSOC);

        if (!$cuenta) {
            // Auto-create cuenta if missing
            $stmtNewCta = $conn->prepare("INSERT INTO cuenta_hospitalaria (id_encamamiento, id_hospital) VALUES (?, ?)");
            $stmtNewCta->execute([$id_encamamiento, $id_hospital]);
            $id_cuenta = (int)$conn->lastInsertId();
        } else {
            $id_cuenta = (int)$cuenta['id_cuenta'];
        }

        $referencia_id = ($id_inventario > 0) ? $id_inventario : null;
        $referencia_tabla = ($referencia_id !== null) ? 'inventario' : null;

        // Insert cargo
        $stmt = $conn->prepare("
            INSERT INTO cargos_hospitalarios
            (id_cuenta, id_cirugia, tipo_cargo, descripcion, cantidad, precio_unitario, precio_costo,
             fecha_cargo, registrado_por, referencia_id, referencia_tabla, id_hospital)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $id_cuenta,
            $id_cirugia,
            $tipo_cargo,
            $descripcion,
            $cantidad,
            $precio_unitario,
            $precio_costo,
            $fecha_cargo,
            $registrado_por,
            $referencia_id,
            $referencia_tabla,
            $id_hospital
        ]);

        // Deduct stock if linked to inventory
        if ($id_inventario > 0) {
            $stmt_deduct = $conn->prepare("
                UPDATE inventario
                SET stock_quirofano = stock_quirofano - ?
                WHERE id_inventario = ? AND stock_quirofano >= ? AND id_hospital = ?
            ");
            $stmt_deduct->execute([$cantidad, $id_inventario, $cantidad, $id_hospital]);

            // If insufficient, log warning but allow
            if ($stmt_deduct->rowCount() === 0) {
                error_log("add_cargo_cirugia: stock insuficiente para inventario $id_inventario");
            }
        }

        // Sync cuenta subtotals
        $stmtSync = $conn->prepare("
            UPDATE cuenta_hospitalaria ch SET
                subtotal_habitacion = COALESCE((SELECT SUM(subtotal) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo = 'Habitación' AND cancelado = 0), 0),
                subtotal_medicamentos = COALESCE((SELECT SUM(subtotal) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo = 'Medicamento' AND cancelado = 0), 0),
                subtotal_procedimientos = COALESCE((SELECT SUM(subtotal) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo = 'Cirugía' AND cancelado = 0), 0) + COALESCE((SELECT SUM(subtotal) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo = 'Procedimiento' AND cancelado = 0), 0),
                subtotal_laboratorios = COALESCE((SELECT SUM(subtotal) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo = 'Laboratorio' AND cancelado = 0), 0),
                subtotal_honorarios = COALESCE((SELECT SUM(subtotal) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo = 'Honorario' AND cancelado = 0), 0),
                subtotal_otros = COALESCE((SELECT SUM(subtotal) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo NOT IN ('Habitación','Medicamento','Procedimiento','Cirugía','Laboratorio','Honorario') AND cancelado = 0), 0)
            WHERE ch.id_cuenta = ?
        ");
        $stmtSync->execute([$id_cuenta]);
    }

    $conn->commit();

    audit_log('create', 'surgery', count($cargos_to_process) . ' cargo(s) agregado(s) a cirugía', [
        'table_name' => 'cargos_hospitalarios',
        'id_cirugia' => $cargos_to_process[0]['id_cirugia'],
        'total_cargos' => count($cargos_to_process),
    ]);

    echo json_encode([
        'success' => true,
        'message' => count($cargos_to_process) . ' cargo(s) agregado(s) correctamente'
    ]);
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    error_log('add_cargo_cirugia: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}