<?php
/**
 * request_modules.php - API para que hospitales envíen solicitudes de módulos
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// CSRF validation
$csrf_header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
if (empty($csrf_header) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_header)) {
    echo json_encode(['status' => 'error', 'message' => 'Token CSRF inválido']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $id_hospital     = (int)($_SESSION['id_hospital'] ?? 1);
    $modulos         = json_decode($_POST['modulos'] ?? '[]', true);
    $tipo_suscripcion = $_POST['tipo_suscripcion'] ?? 'Mensual';
    $mensaje         = trim($_POST['mensaje'] ?? '');

    if (empty($modulos) || !is_array($modulos)) {
        echo json_encode(['status' => 'error', 'message' => 'Selecciona al menos un módulo']);
        exit;
    }

    $validos = ['core','pharmacy','hospitalization','laboratory','inventory','imaging','purchases','sales','finances','reports','surgery'];
    foreach ($modulos as $m) {
        if (!in_array($m, $validos)) {
            echo json_encode(['status' => 'error', 'message' => 'Módulo inválido: ' . $m]);
            exit;
        }
    }

    $stmt = $conn->prepare("
        INSERT INTO solicitudes_modulos (id_hospital, modulos_solicitados, tipo_suscripcion, mensaje)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $id_hospital,
        json_encode($modulos),
        $tipo_suscripcion,
        $mensaje
    ]);

    echo json_encode(['status' => 'success', 'message' => 'Solicitud enviada correctamente. El administrador la revisará pronto.']);

} catch (Exception $e) {
    error_log('Error en settings/api/request_modules.php: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error interno: ' . 'Error del servidor.']);
}
