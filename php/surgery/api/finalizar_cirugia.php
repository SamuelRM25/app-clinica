<?php
// surgery/api/finalizar_cirugia.php - Finalizar cirugía + auto-traslado a encamamiento
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
$auto_trasladar = ($_POST['auto_trasladar'] ?? '1') === '1';

if (!$id_cirugia) {
    echo json_encode(['success' => false, 'message' => 'ID de cirugía inválido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Verificar cirugía
    $stmtC = $conn->prepare("SELECT * FROM cirugias WHERE id_cirugia = ? AND id_hospital = ?");
    $stmtC->execute([$id_cirugia, $id_hospital]);
    $cirugia = $stmtC->fetch(PDO::FETCH_ASSOC);
    if (!$cirugia) throw new Exception('Cirugía no encontrada');
    if ($cirugia['estado'] !== 'En_Curso') throw new Exception('La cirugía debe estar En_Curso para finalizar. Estado actual: ' . $cirugia['estado']);

    $conn->beginTransaction();

    // 1. Finalizar cirugía
    $stmtUpd = $conn->prepare("UPDATE cirugias SET estado = 'Finalizada', fecha_fin = NOW() WHERE id_cirugia = ? AND id_hospital = ?");
    $stmtUpd->execute([$id_cirugia, $id_hospital]);

    // 2. Liberar sala
    $stmtSala = $conn->prepare("UPDATE salas_quirurgicas SET estado = 'Disponible' WHERE id_sala = ? AND id_hospital = ?");
    $stmtSala->execute([$cirugia['id_sala'], $id_hospital]);

    $id_encamamiento_creado = null;
    $cargo_aplicado = false;
    $id_encamamiento_existente = null;

    // 3. Si ya existe id_encamamiento (creado al iniciar cirugía), usarlo
    if ($cirugia['id_encamamiento']) {
        $id_encamamiento_existente = (int)$cirugia['id_encamamiento'];
    }

    // 4. Auto-trasladar a cama física (si auto_trasladar)
    if ($auto_trasladar && !$id_encamamiento_existente) {
        // Buscar cama disponible EXCLUYENDO habitación 401
        $stmtCama = $conn->prepare("
            SELECT c.id_cama, c.id_habitacion, h.numero_habitacion, h.tarifa_por_noche
            FROM camas c
            INNER JOIN habitaciones h ON c.id_habitacion = h.id_habitacion
            WHERE c.estado = 'Disponible'
              AND h.estado != 'Mantenimiento'
              AND h.numero_habitacion != '401'
              AND c.id_hospital = ? AND h.id_hospital = ?
            ORDER BY h.piso, h.numero_habitacion, c.numero_cama
            LIMIT 1
        ");
        $stmtCama->execute([$id_hospital, $id_hospital]);
        $cama = $stmtCama->fetch(PDO::FETCH_ASSOC);

        if ($cama) {
            $fecha_ingreso = date('Y-m-d H:i:s');
            $motivo_ingreso = 'Post-operatorio de cirugía #' . $cirugia['numero_cirugia'];
            $diagnostico = $cirugia['procedimiento'] ?: 'Procedimiento quirúrgico';

            // Verificar que el paciente no esté ya hospitalizado
            $stmtCheck = $conn->prepare("SELECT id_encamamiento FROM encamamientos WHERE id_paciente = ? AND estado = 'Activo' AND id_hospital = ?");
            $stmtCheck->execute([$cirugia['id_paciente'], $id_hospital]);
            if ($stmtCheck->fetch()) {
                $id_encamamiento_existente = $stmtCheck->fetchColumn();
            } else {
                $stmtIngreso = $conn->prepare("
                    INSERT INTO encamamientos
                    (id_paciente, id_cama, id_doctor, fecha_ingreso, fecha_alta,
                     motivo_ingreso, diagnostico_ingreso, tipo_ingreso, notas_ingreso,
                     estado, created_by, id_hospital)
                    VALUES (?, ?, ?, ?, NULL, ?, ?, 'Programado', ?, 'Activo', ?, ?)
                ");
                $notas = 'Auto-trasladado desde cirugía #' . $cirugia['numero_cirugia'];
                $stmtIngreso->execute([
                    $cirugia['id_paciente'],
                    $cama['id_cama'],
                    $user_id,
                    $fecha_ingreso,
                    $motivo_ingreso,
                    $diagnostico,
                    $notas,
                    $user_id,
                    $id_hospital
                ]);
                $id_encamamiento_creado = (int)$conn->lastInsertId();

                // Marcar cama como ocupada
                $stmtUpdCama = $conn->prepare("UPDATE camas SET estado = 'Ocupada' WHERE id_cama = ? AND id_hospital = ?");
                $stmtUpdCama->execute([$cama['id_cama'], $id_hospital]);

                // Crear cuenta hospitalaria
                $stmtCta = $conn->prepare("INSERT INTO cuenta_hospitalaria (id_encamamiento, id_hospital) VALUES (?, ?)");
                $stmtCta->execute([$id_encamamiento_creado, $id_hospital]);
                $id_encamamiento_existente = $id_encamamiento_creado;
            }
        }
    }

    // 5. Si existe encamamiento (viejo o nuevo), asociar a la cirugía y aplicar cargos
    if ($id_encamamiento_existente) {
        // Asociar encamamiento a la cirugía si no estaba
        $stmtUpdCir = $conn->prepare("UPDATE cirugias SET id_encamamiento = ? WHERE id_cirugia = ? AND id_hospital = ?");
        $stmtUpdCir->execute([$id_encamamiento_existente, $id_cirugia, $id_hospital]);

        // Obtener id_cuenta
        $stmtCta2 = $conn->prepare("SELECT id_cuenta FROM cuenta_hospitalaria WHERE id_encamamiento = ? AND id_hospital = ?");
        $stmtCta2->execute([$id_encamamiento_existente, $id_hospital]);
        $cuenta = $stmtCta2->fetch(PDO::FETCH_ASSOC);

        if ($cuenta) {
            $id_cuenta = (int)$cuenta['id_cuenta'];

            // Solo agregar Habitación Q600 si NO existe ya una (caso de auto_trasladar sin cama previa)
            if ($auto_trasladar && $id_encamamiento_creado) {
                $stmtCheckHab = $conn->prepare("SELECT COUNT(*) FROM cargos_hospitalarios WHERE id_cuenta = ? AND tipo_cargo = 'Habitación' AND descripcion LIKE '%Post-operatorio Cirugía%'");
                $stmtCheckHab->execute([$id_cuenta]);
                if ((int)$stmtCheckHab->fetchColumn() === 0) {
                    $stmtCargoInicial = $conn->prepare("
                        INSERT INTO cargos_hospitalarios
                        (id_cuenta, id_cirugia, tipo_cargo, descripcion, cantidad, precio_unitario,
                         fecha_cargo, fecha_aplicacion, registrado_por, id_hospital)
                        VALUES (?, ?, 'Habitación', ?, 1, 600.00, NOW(), CURDATE(), ?, ?)
                    ");
                    $descInicial = "Habitación {$cama['numero_habitacion']} - Post-operatorio Cirugía #{$cirugia['numero_cirugia']} (Q600 tarifa cirugía)";
                    $stmtCargoInicial->execute([$id_cuenta, $id_cirugia, $descInicial, $user_id, $id_hospital]);
                }
            }

            // Cargos de descuentos aplicados (se restan del total)
            $stmtDescuentos = $conn->prepare("SELECT id_descuento, concepto, monto FROM cirugia_descuentos WHERE id_cirugia = ? AND id_hospital = ? AND cancelado = 0 ORDER BY creado_en ASC");
            $stmtDescuentos->execute([$id_cirugia, $id_hospital]);
            $descuentosCirugia = $stmtDescuentos->fetchAll(PDO::FETCH_ASSOC);
            $stmtCargoDesc = $conn->prepare("
                INSERT INTO cargos_hospitalarios
                (id_cuenta, id_cirugia, tipo_cargo, descripcion, cantidad, precio_unitario,
                 fecha_cargo, registrado_por, id_hospital)
                VALUES (?, ?, 'Descuento', ?, 1, ?, NOW(), ?, ?)
            ");
            foreach ($descuentosCirugia as $d) {
                $stmtCargoDesc->execute([
                    $id_cuenta,
                    $id_cirugia,
                    "Descuento: {$d['concepto']} (Cirugía #{$cirugia['numero_cirugia']})",
                    (float)$d['monto'],
                    $user_id,
                    $id_hospital
                ]);
            }

            // Sync cuenta hospitalaria subtotales
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

            $cargo_aplicado = true;
        }
    }

    $conn->commit();

    audit_log('update', 'surgery', "Cirugía finalizada #{$cirugia['numero_cirugia']}" . ($cargo_aplicado ? ' (auto-trasladado)' : ''), [
        'table_name' => 'cirugias',
        'record_id' => $id_cirugia,
        'new_data' => ['estado' => 'Finalizada', 'id_encamamiento' => $id_encamamiento_creado]
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Cirugía finalizada' . ($cargo_aplicado ? ' y paciente trasladado a encamamiento' : ''),
        'id_encamamiento' => $id_encamamiento_creado,
    ]);
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    error_log('finalizar_cirugia: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}