<?php
// patients/search_patients.php - Ajax search for patients
header('Content-Type: application/json');
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$q = $_GET['q'] ?? '';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $search = "%$q%";
    $stmt = $conn->prepare("
        SELECT id_paciente, nombre, apellido, dpi 
        FROM pacientes 
        WHERE nombre LIKE ? OR apellido LIKE ? OR dpi LIKE ?
        LIMIT 20
    ");
    $stmt->execute([$search, $search, $search]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($results);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
