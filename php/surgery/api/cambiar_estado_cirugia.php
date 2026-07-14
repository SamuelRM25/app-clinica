<?php
// surgery/api/cambiar_estado_cirugia.php - Iniciar o cancelar cirugía
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
$id_cirugia = (int)($_POST['id_cirugia'] ?? 0);
$nuevo_estado = $_POST['estado'] ?? '';

if (!$id_cirugia || !in_array($nuevo_estado, ['En_Curso', 'Cancelada'], true)) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmtC = $conn->prepare("SELECT estado, id_sala FROM cirugias WHERE id_cirugia = ? AND id_hospital = ?");
    $stmtC->execute([$id_cirugia, $id_hospital]);
    $cirugia = $stmtC->fetch(PDO::FETCH_ASSOC);
    if (!$cirugia) throw new Exception('Cirugía no encontrada');

    $conn->beginTransaction();

    if ($nuevo_estado === 'En_Curso') {
        if ($cirugia['estado'] !== 'Programada') throw new Exception('Solo cirugías programadas pueden iniciarse');
        $stmt = $conn->prepare("UPDATE cirugias SET estado = 'En_Curso', fecha_inicio = NOW() WHERE id_cirugia = ? AND id_hospital = ?");
        $stmt->execute([$id_cirugia, $id_hospital]);
    } elseif ($nuevo_estado === 'Cancelada') {
        if (!in_array($cirugia['estado'], ['Programada', 'En_Curso'], true)) throw new Exception('Estado no cancelable');
        $stmt = $conn->prepare("UPDATE cirugias SET estado = 'Cancelada', fecha_fin = NOW() WHERE id_cirugia = ? AND id_hospital = ?");
        $stmt->execute([$id_cirugia, $id_hospital]);

        // Liberar sala
        $stmtSala = $conn->prepare("UPDATE salas_quirurgicas SET estado = 'Disponible' WHERE id_sala = ? AND id_hospital = ?");
        $stmtSala->execute([$cirugia['id_sala'], $id_hospital]);
    }

    $conn->commit();

    audit_log('update', 'surgery', "Cirugía #$id_cirugia → $nuevo_estado", [
        'table_name' => 'cirugias', 'record_id' => $id_cirugia,
        'new_data' => ['estado' => $nuevo_estado]
    ]);

    echo json_encode(['success' => true, 'message' => "Cirugía marcada como $nuevo_estado"]);
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}