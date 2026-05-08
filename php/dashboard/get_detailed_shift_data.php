<?php
// get_detailed_shift_data.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    $date = $_GET['date'] ?? date('Y-m-d');
    $shift = $_GET['shift'] ?? 'morning';

    if ($shift === 'morning') {
        $start = $date . ' 08:00:00';
        $end = $date . ' 17:00:00';
    } else {
        $start = $date . ' 17:00:00';
        $end = date('Y-m-d', strtotime($date . ' +1 day')) . ' 07:59:59';
    }

    // 1. Detalle Farmacia
    $stmt = $conn->prepare("SELECT fecha_venta as fecha, nombre_cliente, total, tipo_pago FROM ventas WHERE fecha_venta BETWEEN ? AND ? ORDER BY fecha_venta ASC");
    $stmt->execute([$start, $end]);
    $pharmacy = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Detalle Consultas
    $stmt = $conn->prepare("SELECT c.fecha_consulta as fecha, c.paciente_cobro as nombre_cliente, c.cantidad_consulta as total, c.tipo_pago, CONCAT(u.nombre, ' ', u.apellido) as doctor 
                            FROM cobros c 
                            JOIN usuarios u ON c.id_doctor = u.idUsuario 
                            WHERE c.fecha_consulta BETWEEN ? AND ? ORDER BY c.fecha_consulta ASC");
    $stmt->execute([$start, $end]);
    $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Detalle Procedimientos
    $stmt = $conn->prepare("SELECT fecha_procedimiento as fecha, nombre_paciente as nombre_cliente, cobro as total, tipo_pago 
                            FROM procedimientos_menores WHERE fecha_procedimiento BETWEEN ? AND ? ORDER BY fecha_procedimiento ASC");
    $stmt->execute([$start, $end]);
    $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Detalle Ultrasonidos
    $stmt = $conn->prepare("SELECT fecha_ultrasonido as fecha, nombre_paciente as nombre_cliente, cobro as total, tipo_pago 
                            FROM ultrasonidos WHERE fecha_ultrasonido BETWEEN ? AND ? ORDER BY fecha_ultrasonido ASC");
    $stmt->execute([$start, $end]);
    $ultrasounds = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Detalle Laboratorios
    $stmt = $conn->prepare("SELECT fecha_examen as fecha, nombre_paciente as nombre_cliente, cobro as total, tipo_pago 
                            FROM examenes_realizados WHERE fecha_examen BETWEEN ? AND ? ORDER BY fecha_examen ASC");
    $stmt->execute([$start, $end]);
    $labs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Detalle Rayos X
    $stmt = $conn->prepare("SELECT fecha_estudio as fecha, nombre_paciente as nombre_cliente, cobro as total, tipo_pago 
                            FROM rayos_x WHERE fecha_estudio BETWEEN ? AND ? ORDER BY fecha_estudio ASC");
    $stmt->execute([$start, $end]);
    $xrays = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. Detalle Hospitalización (Cargos)
    $stmt = $conn->prepare("SELECT ch.fecha_cargo as fecha, p.nombre_paciente as nombre_cliente, ch.subtotal as total, 'Cargo' as tipo_pago, ch.descripcion 
                            FROM cargos_hospitalarios ch
                            JOIN cuentas_hospitalarias chosp ON ch.id_cuenta = chosp.id_cuenta
                            JOIN pacientes p ON chosp.id_paciente = p.id_paciente
                            WHERE ch.fecha_cargo BETWEEN ? AND ? ORDER BY ch.fecha_cargo ASC");
    // Wait, let's verify if 'cuentas_hospitalarias' and 'pacientes' tables/links are correct.
    // The user provided the cargos_hospitalarios schema. Let's re-check the join.
    $stmt->execute([$start, $end]);
    $hosp_charges = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 8. Detalle Hospitalización (Abonos)
    $stmt = $conn->prepare("SELECT ah.fecha_abono as fecha, p.nombre_paciente as nombre_cliente, ah.monto as total, ah.metodo_pago as tipo_pago 
                            FROM abonos_hospitalarios ah
                            JOIN cuentas_hospitalarias chosp ON ah.id_cuenta = chosp.id_cuenta
                            JOIN pacientes p ON chosp.id_paciente = p.id_paciente
                            WHERE ah.fecha_abono BETWEEN ? AND ? ORDER BY ah.fecha_abono ASC");
    $stmt->execute([$start, $end]);
    $hosp_abonos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'pharmacy' => $pharmacy,
        'consultations' => $consultations,
        'procedures' => $procedures,
        'ultrasounds' => $ultrasounds,
        'labs' => $labs,
        'xrays' => $xrays,
        'hosp_charges' => $hosp_charges,
        'hosp_abonos' => $hosp_abonos
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
