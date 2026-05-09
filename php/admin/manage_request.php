<?php
/**
 * manage_request.php - API para que el admin apruebe/rechace solicitudes
 */
session_start();
header('Content-Type: application/json');

// Verificar sesión de superadmin
if (!isset($_SESSION['superadmin']) || $_SESSION['superadmin'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Acceso denegado']);
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';


$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // ── Aprobar solicitud ──────────────────────────────────────────────────
    if ($action === 'approve') {
        $id = (int) $_POST['id_solicitud'];
        $nota = trim($_POST['nota'] ?? '');
        $fecha_venc = $_POST['fecha_vencimiento'] ?? null;

        // Cargar solicitud
        $s = $conn->prepare("SELECT * FROM solicitudes_modulos WHERE id_solicitud = ?");
        $s->execute([$id]);
        $sol = $s->fetch();

        if (!$sol) {
            echo json_encode(['status' => 'error', 'message' => 'Solicitud no encontrada']);
            exit;
        }

        $modulos = json_decode($sol['modulos_solicitados'], true);
        // Siempre incluir 'core'
        if (!in_array('core', $modulos))
            array_unshift($modulos, 'core');

        // Actualizar hospital
        $u = $conn->prepare("
            UPDATE hospitales
            SET modulos_activos    = ?,
                tipo_suscripcion   = ?,
                estado_suscripcion = 'Activo',
                fecha_vencimiento  = ?
            WHERE id_hospital = ?
        ");
        $u->execute([
            json_encode($modulos),
            $sol['tipo_suscripcion'],
            ($sol['tipo_suscripcion'] === 'De por vida') ? null : $fecha_venc,
            $sol['id_hospital']
        ]);

        // Marcar solicitud como aprobada
        $conn->prepare("
            UPDATE solicitudes_modulos
            SET estado = 'Aprobada', nota_admin = ?, fecha_respuesta = NOW()
            WHERE id_solicitud = ?
        ")->execute([$nota, $id]);

        echo json_encode(['status' => 'success', 'message' => 'Solicitud aprobada y módulos actualizados.']);

        // ── Rechazar solicitud ─────────────────────────────────────────────────
    } elseif ($action === 'reject') {
        $id = (int) $_POST['id_solicitud'];
        $nota = trim($_POST['nota'] ?? '');

        $conn->prepare("
            UPDATE solicitudes_modulos
            SET estado = 'Rechazada', nota_admin = ?, fecha_respuesta = NOW()
            WHERE id_solicitud = ?
        ")->execute([$nota, $id]);

        echo json_encode(['status' => 'success', 'message' => 'Solicitud rechazada.']);

        // ── Actualizar módulos directo (sin solicitud) ─────────────────────────
    } elseif ($action === 'update_modules') {
        $id_hospital = (int) $_POST['id_hospital'];
        $modulos = json_decode($_POST['modulos'] ?? '[]', true);
        $tipo = $_POST['tipo_suscripcion'] ?? 'Mensual';
        $estado = $_POST['estado'] ?? 'Activo';
        $fecha_venc = $_POST['fecha_vencimiento'] ?? null;

        if (!in_array('core', $modulos))
            array_unshift($modulos, 'core');

        $conn->prepare("
            UPDATE hospitales
            SET modulos_activos    = ?,
                tipo_suscripcion   = ?,
                estado_suscripcion = ?,
                fecha_vencimiento  = ?
            WHERE id_hospital = ?
        ")->execute([json_encode($modulos), $tipo, $estado, $fecha_venc, $id_hospital]);

        echo json_encode(['status' => 'success', 'message' => 'Hospital actualizado correctamente.']);

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Acción no reconocida']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
