<?php
// get_auditoria.php - Endpoint para consultar movimientos financieros del audit_log
// Solo accesible para admin
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if (($_SESSION['tipoUsuario'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Solo administradores.']);
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

    $start = $_GET['start'] ?? date('Y-m-01');
    $end = $_GET['end'] ?? date('Y-m-d');
    $modulo = $_GET['modulo'] ?? '';
    $accion = $_GET['accion'] ?? '';
    $usuario = $_GET['usuario'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per_page = 50;
    $offset = ($page - 1) * $per_page;

    $start_datetime = $start . ' 00:00:00';
    $end_datetime = $end . ' 23:59:59';

    // Construir WHERE dinámico
    $where = "id_hospital = ? AND fecha_audit BETWEEN ? AND ?
              AND modulo IN ('billing','dispensary','purchases','gastos','hospitalization','tarifas','surgery','inventory','reports')";
    $params = [$id_hospital, $start_datetime, $end_datetime];

    if ($modulo !== '') {
        $where .= " AND modulo = ?";
        $params[] = $modulo;
    }
    if ($accion !== '') {
        $where .= " AND accion = ?";
        $params[] = $accion;
    }
    if ($usuario !== '') {
        $where .= " AND user_nombre LIKE ?";
        $params[] = '%' . $usuario . '%';
    }

    // Conteo total
    $stmt_count = $conn->prepare("SELECT COUNT(*) FROM audit_log WHERE $where");
    $stmt_count->execute($params);
    $total = (int)$stmt_count->fetchColumn();

    // Consulta paginada
    $stmt = $conn->prepare("
        SELECT
            id_audit, fecha_audit, user_id, user_nombre, user_tipo,
            accion, modulo, tabla_afectada, id_registro,
            CAST(JSON_UNQUOTE(JSON_EXTRACT(datos_nuevos, '\$.monto')) AS DECIMAL(12,2)) AS monto,
            CAST(JSON_UNQUOTE(JSON_EXTRACT(datos_nuevos, '\$.total')) AS DECIMAL(12,2)) AS total,
            JSON_UNQUOTE(JSON_EXTRACT(datos_nuevos, '\$.descripcion')) AS descripcion,
            resultado
        FROM audit_log
        WHERE $where
        ORDER BY fecha_audit DESC
        LIMIT $per_page OFFSET $offset
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'pages' => ceil($total / $per_page),
        ],
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al consultar auditoría',
        'debug' => ($_SESSION['tipoUsuario'] ?? '') === 'admin' ? $e->getMessage() : null,
    ]);
}
