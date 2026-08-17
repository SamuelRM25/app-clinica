<?php
// surgery/api/cambiar_estado_cirugia.php - Iniciar o cancelar cirugía + crear cuenta al iniciar
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
$nuevo_estado = $_POST['estado'] ?? '';

if (!$id_cirugia || !in_array($nuevo_estado, ['En_Curso', 'Cancelada'], true)) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmtC = $conn->prepare("SELECT estado, id_sala, id_paciente, id_encamamiento, cargo_total, numero_cirugia, procedimiento FROM cirugias WHERE id_cirugia = ? AND id_hospital = ?");
    $stmtC->execute([$id_cirugia, $id_hospital]);
    $cirugia = $stmtC->fetch(PDO::FETCH_ASSOC);
    if (!$cirugia) throw new Exception('Cirugía no encontrada');

    $conn->beginTransaction();

    if ($nuevo_estado === 'En_Curso') {
        if ($cirugia['estado'] !== 'Programada') throw new Exception('Solo cirugías programadas pueden iniciarse');

        // 1. Actualizar estado
        $stmt = $conn->prepare("UPDATE cirugias SET estado = 'En_Curso', fecha_inicio = NOW() WHERE id_cirugia = ? AND id_hospital = ?");
        $stmt->execute([$id_cirugia, $id_hospital]);

        // 2. Crear encamamiento virtual (sin cama asignada todavía)
        //    Esto permite que la cuenta hospitalaria exista desde el inicio
        $id_encamamiento_creado = null;
        if (!$cirugia['id_encamamiento']) {
            $stmtEnc = $conn->prepare("
                INSERT INTO encamamientos
                (id_paciente, id_cama, id_doctor, fecha_ingreso, fecha_alta,
                 motivo_ingreso, diagnostico_ingreso, tipo_ingreso, notas_ingreso,
                 estado, created_by, id_hospital)
                VALUES (?, NULL, ?, NOW(), NULL,
                        ?, ?, 'Programado', ?, 'Activo', ?, ?)
            ");
            $motivo = 'Cirugía #' . $cirugia['numero_cirugia'];
            $diagnostico = $cirugia['procedimiento'] ?: 'Procedimiento quirúrgico';
            $notas = 'Encamamiento virtual creado al iniciar cirugía (sin cama asignada)';
            $stmtEnc->execute([
                $cirugia['id_paciente'],
                $user_id,
                $motivo,
                $diagnostico,
                $notas,
                $user_id,
                $id_hospital
            ]);
            $id_encamamiento_creado = (int)$conn->lastInsertId();

            // 3. Crear cuenta hospitalaria
            $stmtCta = $conn->prepare("INSERT INTO cuenta_hospitalaria (id_encamamiento, id_hospital) VALUES (?, ?)");
            $stmtCta->execute([$id_encamamiento_creado, $id_hospital]);

            // 4. Asociar encamamiento a la cirugía
            $stmtUpd = $conn->prepare("UPDATE cirugias SET id_encamamiento = ? WHERE id_cirugia = ? AND id_hospital = ?");
            $stmtUpd->execute([$id_encamamiento_creado, $id_cirugia, $id_hospital]);

            // 5. Si hay cargo_total, crear cargo tipo='Cirugía'
            if ((float)$cirugia['cargo_total'] > 0) {
                $stmtCtaQ = $conn->prepare("SELECT id_cuenta FROM cuenta_hospitalaria WHERE id_encamamiento = ? AND id_hospital = ?");
                $stmtCtaQ->execute([$id_encamamiento_creado, $id_hospital]);
                $cuenta = $stmtCtaQ->fetch(PDO::FETCH_ASSOC);
                if ($cuenta) {
                    $id_cuenta = (int)$cuenta['id_cuenta'];
                    $stmtCargo = $conn->prepare("
                        INSERT INTO cargos_hospitalarios
                        (id_cuenta, id_cirugia, tipo_cargo, descripcion, cantidad, precio_unitario,
                         fecha_cargo, registrado_por, id_hospital)
                        VALUES (?, ?, 'Cirugía', ?, 1, ?, NOW(), ?, ?)
                    ");
                    $desc = "Cirugía #{$cirugia['numero_cirugia']} (cargo inicial)";
                    $stmtCargo->execute([
                        $id_cuenta,
                        $id_cirugia,
                        $desc,
                        (float)$cirugia['cargo_total'],
                        $user_id,
                        $id_hospital
                    ]);
                }
            }
        }
    } elseif ($nuevo_estado === 'Cancelada') {
        if (!in_array($cirugia['estado'], ['Programada', 'En_Curso'], true)) throw new Exception('Estado no cancelable');
        $stmt = $conn->prepare("UPDATE cirugias SET estado = 'Cancelada', fecha_fin = NOW() WHERE id_cirugia = ? AND id_hospital = ?");
        $stmt->execute([$id_cirugia, $id_hospital]);

        // Liberar sala
        $stmtSala = $conn->prepare("UPDATE salas_quirurgicas SET estado = 'Disponible' WHERE id_sala = ? AND id_hospital = ?");
        $stmtSala->execute([$cirugia['id_sala'], $id_hospital]);
    }

    $conn->commit();

    audit_log('update', 'surgery', "Cirugía #$id_cirugia → $nuevo_estado" . (isset($id_encamamiento_creado) ? " (cuenta creada)" : ''), [
        'table_name' => 'cirugias', 'record_id' => $id_cirugia,
        'new_data' => ['estado' => $nuevo_estado, 'id_encamamiento' => $id_encamamiento_creado ?? null]
    ]);

    echo json_encode(['success' => true, 'message' => "Cirugía marcada como $nuevo_estado"]);
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}