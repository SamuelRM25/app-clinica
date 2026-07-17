<?php
// surgery/api/cargar_combo_cirugia.php
// Al iniciar una cirugía (Programada -> En_Curso), carga los medicamentos del combo
// descontando de stock_quirofano y creando cirugia_consumos por cada uno.
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
$user_id = (int)$_SESSION['user_id'];
$id_cirugia = (int)($_POST['id_cirugia'] ?? 0);
$forzar = ($_POST['forzar'] ?? '0') === '1';

if (!$id_cirugia) {
    echo json_encode(['success' => false, 'message' => 'ID de cirugía requerido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // 1. Obtener la cirugía y su combo
    $stmtC = $conn->prepare("SELECT id_cirugia, id_combo, estado, numero_cirugia FROM cirugias WHERE id_cirugia = ? AND id_hospital = ?");
    $stmtC->execute([$id_cirugia, $id_hospital]);
    $cirugia = $stmtC->fetch(PDO::FETCH_ASSOC);
    if (!$cirugia) throw new Exception('Cirugía no encontrada');
    if (!$cirugia['id_combo']) throw new Exception('Esta cirugía no tiene un combo asignado');
    if ($cirugia['estado'] !== 'En_Curso') throw new Exception('La cirugía debe estar En_Curso para cargar el combo');

    // 2. Obtener los medicamentos del combo
    $stmtMed = $conn->prepare("SELECT id_inventario, cantidad, categoria, descripcion, monto
                                FROM cirugia_combo_items
                                WHERE id_combo = ? AND id_inventario IS NOT NULL AND id_hospital = ?");
    $stmtMed->execute([$cirugia['id_combo'], $id_hospital]);
    $medicamentos = $stmtMed->fetchAll(PDO::FETCH_ASSOC);

    if (empty($medicamentos)) {
        echo json_encode([
            'success' => true,
            'message' => 'El combo no incluye medicamentos para descontar.',
            'descargados' => 0,
            'total_medicamentos' => 0
        ]);
        exit;
    }

    // 3. Verificar si ya hay consumos cargados para esta cirugía
    $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM cirugia_consumos WHERE id_cirugia = ? AND id_hospital = ?");
    $stmtCheck->execute([$id_cirugia, $id_hospital]);
    $ya_existen = (int)$stmtCheck->fetchColumn() > 0;

    if ($ya_existen && !$forzar) {
        echo json_encode([
            'success' => false,
            'ya_cargado' => true,
            'message' => 'Esta cirugía ya tiene medicamentos cargados. Use la opción "Recargar" para forzar (duplicará consumos).'
        ]);
        exit;
    }

    // 4. Validar stock disponible antes de procesar
    $alertas = [];
    $stmtPrecio = $conn->prepare("SELECT precio_venta, precio_hospital, stock_quirofano, nom_medicamento
                                  FROM inventario WHERE id_inventario = ? AND id_hospital = ?");
    $stmtInsertConsumo = $conn->prepare("INSERT INTO cirugia_consumos
                                          (id_cirugia, id_inventario, cantidad, precio_unitario, subtotal, id_hospital)
                                          VALUES (?, ?, ?, ?, ?, ?)");
    $stmtDeduct = $conn->prepare("UPDATE inventario SET stock_quirofano = stock_quirofano - ?
                                    WHERE id_inventario = ? AND stock_quirofano >= ? AND id_hospital = ?");
    $stmtLog = $conn->prepare("INSERT INTO movimientos_stock_log
                                (id_inventario, id_hospital, id_referencia, tabla_origen, tipo_movimiento, stock_column, cantidad, usuario_id, notas)
                                VALUES (?, ?, ?, 'cirugia_consumos', 'descarga', 'stock_quirofano', ?, ?, ?)");

    $conn->beginTransaction();

    $cargados = 0;
    $errores_stock = [];

    foreach ($medicamentos as $med) {
        $id_inv = (int)$med['id_inventario'];
        $cantidad = (float)($med['cantidad'] ?? 1);

        $stmtPrecio->execute([$id_inv, $id_hospital]);
        $inv = $stmtPrecio->fetch(PDO::FETCH_ASSOC);

        if (!$inv) {
            $errores_stock[] = "Medicamento ID $id_inv no encontrado";
            continue;
        }

        $stock_actual = (float)$inv['stock_quirofano'];
        if ($stock_actual < $cantidad) {
            $errores_stock[] = "{$inv['nom_medicamento']}: stock disponible {$stock_actual}, solicitado {$cantidad}";
            continue;
        }

        // Descontar stock
        $stmtDeduct->execute([$cantidad, $id_inv, $cantidad, $id_hospital]);
        if ($stmtDeduct->rowCount() === 0) {
            $errores_stock[] = "{$inv['nom_medicamento']}: no se pudo descontar (concurrencia)";
            continue;
        }

        // Precio unitario (precio_hospital si existe, si no precio_venta)
        $precio_unitario = (float)($inv['precio_hospital'] ?? $inv['precio_venta'] ?? 0);
        $subtotal = $precio_unitario * $cantidad;

        // Insert en cirugia_consumos
        $stmtInsertConsumo->execute([$id_cirugia, $id_inv, $cantidad, $precio_unitario, $subtotal, $id_hospital]);

        // Log de descarga
        $notas = "Carga automática de combo en cirugía #{$cirugia['numero_cirugia']}";
        $stmtLog->execute([$id_inv, $id_hospital, $id_cirugia, $cantidad, $user_id, $notas]);

        $cargados++;
    }

    if ($errores_stock && count($errores_stock) === count($medicamentos)) {
        // Todos los medicamentos sin stock: revertir y avisar
        $conn->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Ningún medicamento del combo tiene stock suficiente en Quirófano.',
            'detalles' => $errores_stock
        ]);
        exit;
    }

    $conn->commit();

    audit_log('create', 'surgery', "Carga de combo en cirugía #{$cirugia['numero_cirugia']}: $cargados medicamentos descontados", [
        'id_cirugia' => $id_cirugia,
        'id_combo' => $cirugia['id_combo'],
        'descargados' => $cargados,
        'errores' => count($errores_stock)
    ]);

    $mensaje = "✓ {$cargados} medicamento(s) del combo descontado(s) del stock de Quirófano";
    if (!empty($errores_stock)) {
        $mensaje .= ". Advertencias: " . implode('; ', $errores_stock);
    }

    echo json_encode([
        'success' => true,
        'message' => $mensaje,
        'descargados' => $cargados,
        'total_medicamentos' => count($medicamentos),
        'errores_stock' => $errores_stock
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    error_log('cargar_combo_cirugia: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}