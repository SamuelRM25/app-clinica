<?php
// settings/api/save_room.php
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

    $id_habitacion = $_POST['id_habitacion'] ?? '';
    $numero_habitacion = substr(trim($_POST['numero_habitacion'] ?? ''), 0, 10);
    $tipo_habitacion = $_POST['tipo_habitacion'] ?? 'Individual';
    $tarifa_por_noche = (float) ($_POST['tarifa_por_noche'] ?? 0);
    $estado = $_POST['estado'] ?? 'Disponible';
    $piso = substr(trim($_POST['piso'] ?? ''), 0, 50);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $capacidad_maxima = max(1, (int)($_POST['capacidad_maxima'] ?? 1));
    $tiene_bano = isset($_POST['tiene_bano']) && $_POST['tiene_bano'] === '1' ? 1 : 0;
    $tiene_tv = isset($_POST['tiene_tv']) && $_POST['tiene_tv'] === '1' ? 1 : 0;
    $tiene_aire_acondicionado = isset($_POST['tiene_aire_acondicionado']) && $_POST['tiene_aire_acondicionado'] === '1' ? 1 : 0;

    if (empty($numero_habitacion)) {
        echo json_encode(['success' => false, 'message' => 'El número de habitación es obligatorio']);
        exit;
    }

    $id_hospital = $_SESSION['id_hospital'] ?? 0;

    $camas_json = $_POST['camas_json'] ?? '[]';
    $camas = json_decode($camas_json, true);
    if (!is_array($camas)) $camas = [];

    $conn->beginTransaction();

    if (empty($id_habitacion)) {
        $stmt_check = $conn->prepare("SELECT id_habitacion FROM habitaciones WHERE numero_habitacion = ? AND id_hospital = ?");
        $stmt_check->execute([$numero_habitacion, $id_hospital]);
        if ($stmt_check->fetch()) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Ya existe una habitación con el número ' . $numero_habitacion]);
            exit;
        }

        $stmt = $conn->prepare("
            INSERT INTO habitaciones (numero_habitacion, tipo_habitacion, tarifa_por_noche, piso, estado, descripcion, tiene_bano, tiene_tv, tiene_aire_acondicionado, capacidad_maxima, id_hospital)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $numero_habitacion, $tipo_habitacion, $tarifa_por_noche, $piso ?: null, $estado,
            $descripcion ?: null, $tiene_bano, $tiene_tv, $tiene_aire_acondicionado, $capacidad_maxima, $id_hospital
        ]);
        $newId = (int)$conn->lastInsertId();

        $stmtBed = $conn->prepare("INSERT INTO camas (id_habitacion, numero_cama, descripcion, estado, id_hospital) VALUES (?, ?, ?, ?, ?)");
        $bedsInserted = 0;
        foreach ($camas as $bed) {
            $num = substr(trim((string)($bed['numero_cama'] ?? '')), 0, 5);
            if ($num === '') continue;
            $desc = trim((string)($bed['descripcion'] ?? ''));
            $bedEstado = $bed['estado'] ?? 'Disponible';
            if (!in_array($bedEstado, ['Disponible', 'Ocupada', 'Mantenimiento', 'Reservada'], true)) {
                $bedEstado = 'Disponible';
            }
            $stmtBed->execute([$newId, $num, $desc !== '' ? $desc : null, $bedEstado, $id_hospital]);
            $bedsInserted++;
        }

        $conn->commit();

        audit_log('create', 'rooms', "Habitación creada: #$numero_habitacion ($tipo_habitacion) con $bedsInserted cama(s)", [
            'table_name' => 'habitaciones',
            'record_id' => $newId,
            'new_data' => [
                'numero_habitacion' => $numero_habitacion,
                'tipo_habitacion' => $tipo_habitacion,
                'tarifa_por_noche' => $tarifa_por_noche,
                'piso' => $piso ?: null,
                'estado' => $estado,
                'descripcion' => $descripcion ?: null,
                'capacidad_maxima' => $capacidad_maxima,
                'tiene_bano' => $tiene_bano,
                'tiene_tv' => $tiene_tv,
                'tiene_aire_acondicionado' => $tiene_aire_acondicionado,
                'beds_count' => $bedsInserted,
            ]
        ]);

        echo json_encode([
            'success' => true,
            'message' => "Habitación creada con $bedsInserted cama(s)",
            'id_habitacion' => $newId,
        ]);
    } else {
        $id_habitacion_int = (int)$id_habitacion;

        $fetchStmt = $conn->prepare("SELECT numero_habitacion, tipo_habitacion, tarifa_por_noche, piso, estado, descripcion, tiene_bano, tiene_tv, tiene_aire_acondicionado, capacidad_maxima FROM habitaciones WHERE id_habitacion = ? AND id_hospital = ?");
        $fetchStmt->execute([$id_habitacion_int, $id_hospital]);
        $oldData = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("
            UPDATE habitaciones
            SET numero_habitacion = ?, tipo_habitacion = ?, tarifa_por_noche = ?, piso = ?, estado = ?,
                descripcion = ?, tiene_bano = ?, tiene_tv = ?, tiene_aire_acondicionado = ?, capacidad_maxima = ?
            WHERE id_habitacion = ? AND id_hospital = ?
        ");
        $stmt->execute([
            $numero_habitacion, $tipo_habitacion, $tarifa_por_noche, $piso ?: null, $estado,
            $descripcion ?: null, $tiene_bano, $tiene_tv, $tiene_aire_acondicionado, $capacidad_maxima,
            $id_habitacion_int, $id_hospital
        ]);

        $existingStmt = $conn->prepare("SELECT id_cama, numero_cama, descripcion, estado FROM camas WHERE id_habitacion = ? AND id_hospital = ?");
        $existingStmt->execute([$id_habitacion_int, $id_hospital]);
        $existing = $existingStmt->fetchAll(PDO::FETCH_ASSOC);

        $existingById = [];
        foreach ($existing as $e) $existingById[(int)$e['id_cama']] = $e;

        $incomingIds = [];
        foreach ($camas as $bed) {
            $idCama = isset($bed['id_cama']) && $bed['id_cama'] !== null ? (int)$bed['id_cama'] : null;
            if ($idCama !== null) $incomingIds[] = $idCama;
        }

        $stmtDeleteBed = $conn->prepare("DELETE FROM camas WHERE id_cama = ? AND id_habitacion = ? AND id_hospital = ?");
        foreach ($existingById as $eid => $ebed) {
            if (!in_array($eid, $incomingIds, true)) {
                $stmtDeleteBed->execute([$eid, $id_habitacion_int, $id_hospital]);
            }
        }

        $stmtUpdateBed = $conn->prepare("UPDATE camas SET numero_cama = ?, descripcion = ?, estado = ? WHERE id_cama = ? AND id_habitacion = ? AND id_hospital = ?");
        $stmtInsertBed = $conn->prepare("INSERT INTO camas (id_habitacion, numero_cama, descripcion, estado, id_hospital) VALUES (?, ?, ?, ?, ?)");

        $bedsUpdated = 0;
        $bedsInserted = 0;
        foreach ($camas as $bed) {
            $num = substr(trim((string)($bed['numero_cama'] ?? '')), 0, 5);
            if ($num === '') continue;
            $desc = trim((string)($bed['descripcion'] ?? ''));
            $bedEstado = $bed['estado'] ?? 'Disponible';
            if (!in_array($bedEstado, ['Disponible', 'Ocupada', 'Mantenimiento', 'Reservada'], true)) {
                $bedEstado = 'Disponible';
            }
            $idCama = isset($bed['id_cama']) && $bed['id_cama'] !== null ? (int)$bed['id_cama'] : null;
            if ($idCama !== null && isset($existingById[$idCama])) {
                $stmtUpdateBed->execute([$num, $desc !== '' ? $desc : null, $bedEstado, $idCama, $id_habitacion_int, $id_hospital]);
                $bedsUpdated++;
            } else {
                $stmtInsertBed->execute([$id_habitacion_int, $num, $desc !== '' ? $desc : null, $bedEstado, $id_hospital]);
                $bedsInserted++;
            }
        }

        $conn->commit();

        audit_log('update', 'rooms', "Habitación actualizada: #$numero_habitacion (ID: $id_habitacion_int) — camas: +$bedsInserted nuevas, ~$bedsUpdated actualizadas", [
            'table_name' => 'habitaciones',
            'record_id' => $id_habitacion_int,
            'old_data' => $oldData,
            'new_data' => [
                'numero_habitacion' => $numero_habitacion,
                'tipo_habitacion' => $tipo_habitacion,
                'tarifa_por_noche' => $tarifa_por_noche,
                'piso' => $piso ?: null,
                'estado' => $estado,
                'descripcion' => $descripcion ?: null,
                'capacidad_maxima' => $capacidad_maxima,
                'tiene_bano' => $tiene_bano,
                'tiene_tv' => $tiene_tv,
                'tiene_aire_acondicionado' => $tiene_aire_acondicionado,
                'beds_added' => $bedsInserted,
                'beds_updated' => $bedsUpdated,
            ]
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Habitación actualizada correctamente',
        ]);
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("save_room error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar la habitación.',
        'debug'   => ($_SESSION['tipoUsuario'] ?? '') === 'admin' ? $e->getMessage() : null,
    ]);
}