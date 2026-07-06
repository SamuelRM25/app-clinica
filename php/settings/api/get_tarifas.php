<?php
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

header('Content-Type: application/json');

$debug = [
    'session_user_id' => $_SESSION['user_id'] ?? null,
    'session_id_hospital' => $_SESSION['id_hospital'] ?? null,
    'session_id' => session_id(),
    'session_keys' => array_keys($_SESSION),
    'cookie' => $_COOKIE['PHPSESSID'] ?? null,
    'request_uri' => $_SERVER['REQUEST_URI'] ?? ''
];

if (!isset($_SESSION['user_id']) || !isset($_SESSION['id_hospital'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado - session missing', 'debug' => $debug]);
    exit;
}

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);
if ($id_hospital === 0) {
    echo json_encode(['success' => false, 'message' => 'Hospital no identificado']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get all tarifas
    $stmt = $conn->prepare("
        SELECT id_tarifa, tipo_servicio, id_medico, nombre_servicio,
               precio_normal, precio_inhabil, precio_radio, region_count,
               costo_normal, costo_inhabil, costo_radio
        FROM tarifas_servicios
        WHERE id_hospital = ?
        ORDER BY tipo_servicio, id_medico, region_count, nombre_servicio
    ");
    $stmt->execute([$id_hospital]);
    $tarifas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get medicos for consulta/reconsulta dropdowns
    $stmt_medicos = $conn->prepare("
        SELECT idUsuario, nombre, apellido, especialidad
        FROM usuarios
        WHERE id_hospital = ? AND tipoUsuario = 'doc'
        ORDER BY nombre
    ");
    $stmt_medicos->execute([$id_hospital]);
    $medicos = $stmt_medicos->fetchAll(PDO::FETCH_ASSOC);

    // Build indexed result by tipo
    $result = [
        'consulta' => [],
        'reconsulta' => [],
        'electrocardiograma' => [],
        'procedimiento' => [],
        'rayos_x' => [],
        'ultrasonido' => [],
        'medicos' => $medicos
    ];

    foreach ($tarifas as $t) {
        $tipo = $t['tipo_servicio'];
        $id_tarifa = (int)$t['id_tarifa'];
        $nombre = $t['nombre_servicio'];
        $medico = $t['id_medico'] ? (int)$t['id_medico'] : null;
        $region = $t['region_count'] ? (int)$t['region_count'] : null;

        $item = [
            'id_tarifa' => $id_tarifa,
            'precio_normal' => (float)$t['precio_normal'],
            'precio_inhabil' => (float)$t['precio_inhabil'],
            'costo_normal'  => $t['costo_normal']  !== null ? (float)$t['costo_normal']  : null,
            'costo_inhabil' => $t['costo_inhabil'] !== null ? (float)$t['costo_inhabil'] : null,
        ];

        if ($tipo === 'consulta' || $tipo === 'reconsulta') {
            $item['id_medico'] = $medico;
            $result[$tipo][] = $item;
        } elseif ($tipo === 'electrocardiograma') {
            $result['electrocardiograma'] = $item;
        } elseif ($tipo === 'procedimiento') {
            $item['nombre_servicio'] = $nombre;
            $result['procedimiento'][] = $item;
        } elseif ($tipo === 'rayos_x') {
            $item['region_count'] = $region;
            $result['rayos_x'][] = $item;
        } elseif ($tipo === 'ultrasonido') {
            $item['nombre_servicio'] = $nombre;
            $item['precio_radio'] = $t['precio_radio'] ? (float)$t['precio_radio'] : 0;
            $item['costo_radio']  = $t['costo_radio']  !== null ? (float)$t['costo_radio']  : null;
            $result['ultrasonido'][] = $item;
        }
    }

    echo json_encode(['success' => true, 'tarifas' => $result]);

} catch (Exception $e) {
    error_log("Error en get_tarifas.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al cargar tarifas', 'debug' => $debug]);
}