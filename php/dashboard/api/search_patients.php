<?php
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/multitenant.php';

csrf_token();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);
$query = trim($_GET['q'] ?? '');
$limit = min((int)($_GET['limit'] ?? 10), 20);

if (strlen($query) < 1) {
    echo json_encode(['status' => 'success', 'patients' => []]);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $searchTerm = '%' . $query . '%';
    $limitInt = (int)$limit;
    $stmt = $conn->prepare("
        SELECT id_paciente,
               CONCAT(nombre, ' ', apellido) as nombre_completo,
               dpi,
               fecha_nacimiento,
               TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) as edad
        FROM pacientes
        WHERE id_hospital = ?
          AND (nombre LIKE ? OR apellido LIKE ? OR CONCAT(nombre, ' ', apellido) LIKE ? OR dpi LIKE ?)
        ORDER BY nombre ASC
        LIMIT $limitInt
    ");
    $stmt->execute([$id_hospital, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'patients' => $patients]);

} catch (Exception $e) {
    error_log('Error en api/search_patients.php: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error del servidor']);
}