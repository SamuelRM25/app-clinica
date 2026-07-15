<?php
// surgery/api/create_cirugia.php
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit;
}

$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

$data = $_POST;
$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);
$user_id = (int)($_SESSION['user_id'] ?? 0);

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Validate
    $tipo_paciente = $data['tipo_paciente'] ?? 'Interno';
    if (!in_array($tipo_paciente, ['Interno', 'Referido'], true)) {
        throw new Exception('Tipo de paciente inválido');
    }

    $id_sala = (int)($data['id_sala'] ?? 0);
    if (!$id_sala) throw new Exception('Debe seleccionar una sala quirúrgica');

    $id_combo = !empty($data['id_combo']) ? (int)$data['id_combo'] : null;

    // Generar número de cirugía
    $today = date('Ymd');
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM cirugias WHERE DATE(fecha_creacion) = CURDATE() AND id_hospital = ?");
    $stmt->execute([$id_hospital]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] + 1;
    $numero_cirugia = "CIR-" . $today . "-" . str_pad($count, 3, '0', STR_PAD_LEFT);

    $conn->beginTransaction();

    // Si es referido, crear paciente temporal
    if ($tipo_paciente === 'Referido') {
        $nombre = trim($data['referido_nombre'] ?? '');
        $apellido = trim($data['referido_apellido'] ?? '');
        if (empty($nombre)) throw new Exception('Nombre del paciente referido es obligatorio');

        $stmtP = $conn->prepare("INSERT INTO pacientes (nombre, apellido, fecha_nacimiento, genero, direccion, id_hospital) VALUES (?, ?, '1900-01-01', 'Masculino', 'Referente Externo', ?)");
        $stmtP->execute([$nombre, $apellido, $id_hospital]);
        $id_paciente = (int)$conn->lastInsertId();

        $stmtH = $conn->prepare("INSERT INTO historial_clinico (id_paciente, fecha_consulta, motivo_consulta, sintomas, diagnostico, tratamiento, medico_responsable, id_hospital) VALUES (?, NOW(), ?, '', 'Paciente referido de cirugía', '', ?, ?)");
        $stmtH->execute([$id_paciente, 'Cirugía - ' . ($nombre . ' ' . $apellido), $_SESSION['nombre'] ?? 'Sistema', $id_hospital]);
    } else {
        $id_paciente = (int)($data['id_paciente'] ?? 0);
        if (!$id_paciente) throw new Exception('Debe seleccionar un paciente');

        $stmtV = $conn->prepare("SELECT id_paciente FROM pacientes WHERE id_paciente = ? AND id_hospital = ?");
        $stmtV->execute([$id_paciente, $id_hospital]);
        if (!$stmtV->fetch()) throw new Exception('Paciente no encontrado');

        $stmtH = $conn->prepare("SELECT id_paciente FROM historial_clinico WHERE id_paciente = ? AND id_hospital = ? LIMIT 1");
        $stmtH->execute([$id_paciente, $id_hospital]);
        if (!$stmtH->fetch()) {
            $stmtHC = $conn->prepare("INSERT INTO historial_clinico (id_paciente, fecha_consulta, motivo_consulta, sintomas, diagnostico, tratamiento, medico_responsable, id_hospital) VALUES (?, NOW(), '', '', '', '', ?, ?)");
            $stmtHC->execute([$id_paciente, $_SESSION['nombre'] ?? 'Sistema', $id_hospital]);
        }
    }

    // Obtener precio del combo
    $cargo_total = 0;
    if ($id_combo) {
        $stmtP = $conn->prepare("SELECT precio_total FROM cirugia_combos WHERE id_combo = ? AND id_hospital = ?");
        $stmtP->execute([$id_combo, $id_hospital]);
        $cargo_total = (float)($stmtP->fetch(PDO::FETCH_ASSOC)['precio_total'] ?? 0);
    }

    // Verificar sala disponible
    $stmtSala = $conn->prepare("SELECT estado FROM salas_quirurgicas WHERE id_sala = ? AND id_hospital = ?");
    $stmtSala->execute([$id_sala, $id_hospital]);
    $sala = $stmtSala->fetch(PDO::FETCH_ASSOC);
    if (!$sala) throw new Exception('Sala no encontrada');
    if ($sala['estado'] === 'Mantenimiento') throw new Exception('Sala en mantenimiento');
    if ($sala['estado'] === 'Ocupada') throw new Exception('Sala actualmente ocupada');

    // INSERT cirugía
    $cirujano_nombre = trim($data['cirujano_nombre'] ?? '');
    $anestesista_nombre = trim($data['anestesista_nombre'] ?? '');
    $stmt = $conn->prepare("
        INSERT INTO cirugias (
            numero_cirugia, id_paciente, id_sala, id_cirujano, cirujano_nombre, id_anestesista, anestesista_nombre, id_combo,
            tipo_paciente, referido_nombre, referido_apellido, procedimiento,
            fecha_programada, cargo_total, estado, created_by, id_hospital
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Programada', ?, ?)
    ");
    $stmt->execute([
        $numero_cirugia,
        $id_paciente,
        $id_sala,
        null, // id_cirujano ya no se usa
        $cirujano_nombre ?: null,
        null, // id_anestesista ya no se usa
        $anestesista_nombre ?: null,
        $id_combo,
        $tipo_paciente,
        $tipo_paciente === 'Referido' ? trim($data['referido_nombre'] ?? '') : null,
        $tipo_paciente === 'Referido' ? trim($data['referido_apellido'] ?? '') : null,
        trim($data['procedimiento'] ?? ''),
        !empty($data['fecha_programada']) ? date('Y-m-d H:i:s', strtotime($data['fecha_programada'])) : null,
        $cargo_total,
        $user_id,
        $id_hospital
    ]);
    $id_cirugia = (int)$conn->lastInsertId();

    // Update sala → Ocupada
    $stmtUpd = $conn->prepare("UPDATE salas_quirurgicas SET estado = 'Ocupada' WHERE id_sala = ? AND id_hospital = ?");
    $stmtUpd->execute([$id_sala, $id_hospital]);

    // Equipo quirúrgico (solo texto libre)
    if ($cirujano_nombre) {
        // Para mantener compatibilidad, insertamos 0 en id_usuario si es texto libre
        // Mejor lo guardamos sólo como texto y registramos un marker
        $stmtEq = $conn->prepare("INSERT INTO cirugia_equipo (id_cirugia, id_usuario, rol, id_hospital) VALUES (?, 0, 'Cirujano', ?)");
        // En realidad id_usuario FK en cirugia_equipo puede no permitir 0
        // Mejor NO insertar si id_usuario=0; sólo guardamos en cirugias.cirujano_nombre
    }
    if ($anestesista_nombre) {
        // Mismo caso
    }

    $conn->commit();

    audit_log('create', 'surgery', "Cirugía creada: $numero_cirugia", [
        'table_name' => 'cirugias', 'record_id' => $id_cirugia,
        'new_data' => ['numero' => $numero_cirugia, 'id_paciente' => $id_paciente, 'id_combo' => $id_combo, 'cargo' => $cargo_total]
    ]);

    echo json_encode([
        'success' => true,
        'message' => "Cirugía $numero_cirugia registrada",
        'id_cirugia' => $id_cirugia,
        'numero_cirugia' => $numero_cirugia,
    ]);
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    error_log('create_cirugia error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}