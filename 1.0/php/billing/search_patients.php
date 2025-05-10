<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

header('Content-Type: application/json');

if (!isset($_GET['term']) || empty($_GET['term'])) {
    echo json_encode([]);
    exit;
}

$term = $_GET['term'];

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Search for patients by name or last name
    $stmt = $conn->prepare("
        SELECT id_paciente, CONCAT(nombre, ' ', apellido) as nombre_completo 
        FROM pacientes 
        WHERE nombre LIKE ? OR apellido LIKE ? 
        ORDER BY nombre, apellido 
        LIMIT 10
    ");
    
    $searchTerm = "%{$term}%";
    $stmt->execute([$searchTerm, $searchTerm]);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($patients);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}