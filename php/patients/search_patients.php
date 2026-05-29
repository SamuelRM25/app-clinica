<?php
// patients/search_patients.php - Ajax search for patients
header('Content-Type: application/json');
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$q = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $hid    = hospital_id();
    $search = "%" . $q . "%";

    $stmt = $conn->prepare("
        SELECT id_paciente, nombre, apellido, dpi, telefono 
        FROM pacientes 
        WHERE id_hospital = ?
          AND (nombre LIKE ? OR apellido LIKE ? OR dpi LIKE ? 
               OR CONCAT(nombre, ' ', apellido) LIKE ?)
        ORDER BY nombre, apellido
        LIMIT 25
    ");
    $stmt->execute([$hid, $search, $search, $search, $search]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($results);

} catch (Exception $e) {
    error_log("search_patients error: " . $e->getMessage());
    echo json_encode([]);   // Always return array, never object
}
