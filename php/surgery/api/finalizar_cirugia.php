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

    // 3. Auto-trasladar a encamamiento
    if ($auto_trasladar) {
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
            $fecha_alta = null;
            $motivo_ingreso = 'Post-operatorio de cirugía #' . $cirugia['numero_cirugia'];
            $diagnostico = $cirugia['procedimiento'] ?: 'Procedimiento quirúrgico';

            // Verificar que el paciente no esté ya hospitalizado
            $stmtCheck = $conn->prepare("SELECT id_encamamiento FROM encamamientos WHERE id_paciente = ? AND estado = 'Activo' AND id_hospital = ?");
            $stmtCheck->execute([$cirugia['id_paciente'], $id_hospital]);
            if ($stmtCheck->fetch()) {
                // Ya está hospitalizado: no crear nuevo encamamiento
                $id_encamamiento_existente = $stmtCheck->fetchColumn();
            } else {
                $stmtIngreso = $conn->prepare("
                    INSERT INTO encamamientos
                    (id_paciente, id_cama, id_doctor, fecha_ingreso, fecha_alta,
                     motivo_ingreso, diagnostico_ingreso, tipo_ingreso, notas_ingreso,
                     estado, created_by, id_hospital)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Programado', ?, 'Activo', ?, ?)
                ");
                $notas = 'Auto-trasladado desde cirugía #' . $cirugia['numero_cirugia'];
                $stmtIngreso->execute([
                    $cirugia['id_paciente'],
                    $cama['id_cama'],
                    $user_id,
                    $fecha_ingreso,
                    $fecha_alta,
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

                // Crear cuenta hospitalaria (si no existe trigger)
                $stmtVerifCta = $conn->prepare("SELECT id_cuenta FROM cuenta_hospitalaria WHERE id_encamamiento = ? AND id_hospital = ?");
                $stmtVerifCta->execute([$id_encamamiento_creado, $id_hospital]);
                if (!$stmtVerifCta->fetch()) {
                    $stmtCta = $conn->prepare("INSERT INTO cuenta_hospitalaria (id_encamamiento, id_hospital) VALUES (?, ?)");
                    $stmtCta->execute([$id_encamamiento_creado, $id_hospital]);
                }
            }

            // Obtener id_cuenta
            $id_encamamiento_target = $id_encamamiento_creado ?: $id_encamamiento_existente;
            $stmtCta2 = $conn->prepare("SELECT id_cuenta FROM cuenta_hospitalaria WHERE id_encamamiento = ? AND id_hospital = ?");
            $stmtCta2->execute([$id_encamamiento_target, $id_hospital]);
            $cuenta = $stmtCta2->fetch(PDO::FETCH_ASSOC);

            if ($cuenta) {
                $id_cuenta = (int)$cuenta['id_cuenta'];

                // Cargo Q600 primera noche (tarifa cirugía)
                $stmtCargoInicial = $conn->prepare("
                    INSERT INTO cargos_hospitalarios
                    (id_cuenta, tipo_cargo, descripcion, cantidad, precio_unitario,
                     fecha_cargo, fecha_aplicacion, registrado_por, id_hospital)
                    VALUES (?, 'Habitación', ?, 1, 600.00, NOW(), CURDATE(), ?, ?)
                ");
                $descInicial = "Habitación {$cama['numero_habitacion']} - Post-operatorio Cirugía #{$cirugia['numero_cirugia']} (Q600 tarifa cirugía)";
                $stmtCargoInicial->execute([$id_cuenta, $descInicial, $user_id, $id_hospital]);

                // Cargo del combo (si hay)
                if ((float)$cirugia['cargo_total'] > 0) {
                    $stmtNombreCombo = $conn->prepare("SELECT nombre FROM cirugia_combos WHERE id_combo = ?");
                    $stmtNombreCombo->execute([$cirugia['id_combo']]);
                    $combo = $stmtNombreCombo->fetch(PDO::FETCH_ASSOC);
                    $comboNombre = $combo['nombre'] ?? 'Combo';

                    $stmtCargoCombo = $conn->prepare("
                        INSERT INTO cargos_hospitalarios
                        (id_cuenta, tipo_cargo, descripcion, cantidad, precio_unitario,
                         fecha_cargo, registrado_por, id_hospital)
                        VALUES (?, 'Cirugía', ?, 1, ?, NOW(), ?, ?)
                    ");
                    $stmtCargoCombo->execute([$id_cuenta, "Cirugía: {$comboNombre} (#{$cirugia['numero_cirugia']})", (float)$cirugia['cargo_total'], $user_id, $id_hospital]);
                }

                // Cargos de medicamentos consumidos
                $stmtConsumos = $conn->prepare("SELECT cc.*, inv.nom_medicamento FROM cirugia_consumos cc JOIN inventario inv ON cc.id_inventario = inv.id_inventario WHERE cc.id_cirugia = ?");
                $stmtConsumos->execute([$id_cirugia]);
                $consumos = $stmtConsumos->fetchAll(PDO::FETCH_ASSOC);
                $stmtCargoConsumo = $conn->prepare("
                    INSERT INTO cargos_hospitalarios
                    (id_cuenta, tipo_cargo, descripcion, cantidad, precio_unitario,
                     subtotal, fecha_cargo, registrado_por, referencia_id, referencia_tabla, id_hospital)
                    VALUES (?, 'Medicamento', ?, ?, ?, ?, NOW(), ?, ?, 'inventario', ?)
                ");
                foreach ($consumos as $co) {
                    $stmtCargoConsumo->execute([
                        $id_cuenta,
                        "{$co['nom_medicamento']} (Cirugía #{$cirugia['numero_cirugia']})",
                        (float)$co['cantidad'],
                        (float)$co['precio_unitario'],
                        (float)$co['subtotal'],
                        $user_id,
                        (int)$co['id_inventario'],
                        $id_hospital
                    ]);
                }

                // Sync cuenta hospitalaria subtotales (total_general es GENERATED, no se actualiza manualmente)
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

            // Actualizar cirugia con id_encamamiento
            if ($id_encamamiento_target) {
                $stmtUpdCir = $conn->prepare("UPDATE cirugias SET id_encamamiento = ? WHERE id_cirugia = ?");
                $stmtUpdCir->execute([$id_encamamiento_target, $id_cirugia]);
            }
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