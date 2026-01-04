<?php
// laboratory/api/save_test.php - Save or update a clinical test
header('Content-Type: application/json');
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

if ($_SESSION['tipoUsuario'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$id_prueba = $_POST['id_prueba'] ?? null;
$nombre = $_POST['nombre'] ?? '';
$codigo = $_POST['codigo'] ?? '';
$categoria = $_POST['categoria'] ?? '';
$price = $_POST['price'] ?? 0;
$muestra = $_POST['muestra'] ?? '';
$tiempo = $_POST['tiempo'] ?? 0;

if (empty($nombre) || empty($codigo)) {
    echo json_encode(['success' => false, 'message' => 'El nombre y el código son obligatorios']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($id_prueba) {
        // Update
        $stmt = $conn->prepare("
            UPDATE catalogo_pruebas 
            SET nombre_prueba = ?, codigo_prueba = ?, categoria = ?, price = ?, muestra_requerida = ?, tiempo_procesamiento_horas = ?
            WHERE id_prueba = ?
        ");
        $stmt->execute([$nombre, $codigo, $categoria, $price, $muestra, $tiempo, $id_prueba]);
        $message = 'Prueba actualizada correctamente';
    } else {
        // Create
        $stmt = $conn->prepare("
            INSERT INTO catalogo_pruebas (nombre_prueba, codigo_prueba, categoria, price, muestra_requerida, tiempo_procesamiento_horas)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nombre, $codigo, $categoria, $price, $muestra, $tiempo]);
        $message = 'Prueba creada correctamente';
    }
    
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
