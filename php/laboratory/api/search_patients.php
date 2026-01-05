<?php
// laboratory/api/search_patients.php - Search for patients by name, apellido or DPI
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

verify_session();

header('Content-Type: application/json');

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get search term from request
    $search = $_GET['q'] ?? '';
    
    if (strlen($search) < 2) {
        echo json_encode([
            'success' => false,
            'message' => 'El término de búsqueda debe tener al menos 2 caracteres'
        ]);
        exit;
    }
    
    // Search patients by name or apellido
    $stmt = $conn->prepare("
        SELECT 
            id_paciente,
            nombre,
            apellido,
            fecha_nacimiento,
            genero,
            direccion,
            telefono
        FROM pacientes 
        WHERE 
            nombre LIKE ? OR 
            apellido LIKE ?
        ORDER BY apellido, nombre
        LIMIT 20
    ");
    
    $searchTerm = "%{$search}%";
    $stmt->execute([$searchTerm, $searchTerm]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format results for display
    $formatted_results = array_map(function($patient) {
        return [
            'id' => $patient['id_paciente'],
            'nombre_completo' => trim($patient['nombre'] . ' ' . $patient['apellido']),
            'nombre' => $patient['nombre'],
            'apellido' => $patient['apellido'],
            'fecha_nac' => $patient['fecha_nacimiento'],
            'genero' => $patient['genero'],
            'telefono' => $patient['telefono'],
            'direccion' => $patient['direccion'],
            'label' => trim($patient['nombre'] . ' ' . $patient['apellido'])
        ];
    }, $results);
    
    echo json_encode([
        'success' => true,
        'results' => $formatted_results,
        'count' => count($formatted_results)
    ]);
    
} catch (Exception $e) {
    error_log("Error en search_patients.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al buscar pacientes'
    ]);
}
