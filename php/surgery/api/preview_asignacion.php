<?php
// surgery/api/preview_asignacion.php - Vista previa de habitación a asignar tras finalizar cirugía
session_start();
require_once '../../../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);
$id_cirugia = (int)($_GET['id_cirugia'] ?? 0);

if (!$id_cirugia) {
    echo json_encode(['success' => false, 'message' => 'ID de cirugía requerido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener info de la cirugía
    $stmtC = $conn->prepare("SELECT id_paciente, tipo_paciente FROM cirugias WHERE id_cirugia = ? AND id_hospital = ?");
    $stmtC->execute([$id_cirugia, $id_hospital]);
    $cirugia = $stmtC->fetch(PDO::FETCH_ASSOC);
    if (!$cirugia) {
        echo json_encode(['success' => false, 'message' => 'Cirugía no encontrada']);
        exit;
    }

    // Verificar si el paciente ya tiene encamamiento activo
    $stmtCheck = $conn->prepare("SELECT e.id_encamamiento, h.numero_habitacion, c.numero_cama, h.tarifa_por_noche FROM encamamientos e JOIN camas c ON e.id_cama = c.id_cama JOIN habitaciones h ON c.id_habitacion = h.id_habitacion WHERE e.id_paciente = ? AND e.estado = 'Activo' AND e.id_hospital = ?");
    $stmtCheck->execute([$cirugia['id_paciente'], $id_hospital]);
    $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo json_encode([
            'success' => true,
            'ya_hospitalizado' => true,
            'habitacion' => $existing['numero_habitacion'],
            'cama' => $existing['numero_cama'],
            'tarifa_por_noche' => (float)$existing['tarifa_por_noche'],
            'mensaje' => 'El paciente ya tiene encamamiento activo. Se le agregarán los cargos de cirugía a esa cuenta.'
        ]);
        exit;
    }

    // Buscar la mejor cama disponible (excluyendo la 401)
    $stmtCamas = $conn->prepare("
        SELECT
            c.id_cama,
            h.id_habitacion,
            h.numero_habitacion,
            h.tipo_habitacion,
            h.piso,
            h.tarifa_por_noche,
            c.numero_cama
        FROM camas c
        INNER JOIN habitaciones h ON c.id_habitacion = h.id_habitacion
        WHERE c.estado = 'Disponible'
          AND h.estado != 'Mantenimiento'
          AND h.numero_habitacion != '401'
          AND c.id_hospital = ? AND h.id_hospital = ?
        ORDER BY h.piso, h.numero_habitacion, c.numero_cama
        LIMIT 5
    ");
    $stmtCamas->execute([$id_hospital, $id_hospital]);
    $camas = $stmtCamas->fetchAll(PDO::FETCH_ASSOC);

    if (empty($camas)) {
        echo json_encode([
            'success' => true,
            'disponible' => false,
            'mensaje' => 'No hay camas disponibles (excluyendo la 401). La cirugía se finalizará sin auto-trasladar al paciente.'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'disponible' => true,
        'ya_hospitalizado' => false,
        'camas' => $camas,
        'seleccionada' => $camas[0],
        'primera_noche_cirugia' => 600.00,  // Tarifa fija Q600
        'mensaje' => 'Se asignará la siguiente cama disponible. Q600 la primera noche (tarifa cirugía), luego la tarifa del cuarto.'
    ]);
} catch (Exception $e) {
    error_log('preview_asignacion: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}