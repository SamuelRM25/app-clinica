<?php
// api/check_consultation_history.php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';


if (!isset($_GET['id_paciente'])) {
    echo json_encode(['status' => 'error', 'message' => 'Falta id_paciente']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $id_paciente = $_GET['id_paciente'];
    $id_hospital = $_SESSION['id_hospital'] ?? 0;

    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM citas WHERE id_paciente = ? AND id_hospital = ?");
    $stmt->execute([$id_paciente, $id_hospital]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    echo json_encode([
        'status' => 'success',
        'count' => (int) $count,
        'has_prior' => $count > 0
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>