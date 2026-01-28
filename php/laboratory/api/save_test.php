<?php
// laboratory/api/save_test.php - Save or update a clinical test
header('Content-Type: application/json');
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

if ($_SESSION['tipoUsuario'] !== 'admin' && $_SESSION['user_id'] != 7) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$id_prueba = (!empty($_POST['id_prueba'])) ? $_POST['id_prueba'] : null;
$nombre = $_POST['nombre'] ?? '';
$codigo = $_POST['codigo'] ?? '';
$categoria = $_POST['categoria'] ?? '';
$precio = (float) ($_POST['precio'] ?? 0);
$muestra = $_POST['muestra_requerida'] ?? ''; // Fixed mapping
$tiempo = (int) ($_POST['tiempo_procesamiento_horas'] ?? 0); // Fixed mapping
$notas = $_POST['descripcion'] ?? ''; // Map description to notas

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
            SET nombre_prueba = ?, codigo_prueba = ?, categoria = ?, precio = ?, muestra_requerida = ?, tiempo_procesamiento_horas = ?, notas = ?
            WHERE id_prueba = ?
        ");
        $stmt->execute([$nombre, $codigo, $categoria, $precio, $muestra, $tiempo, $notas, $id_prueba]);
        $message = 'Prueba actualizada correctamente';
    } else {
        // Create - Check for duplicate code first
        $checkStmt = $conn->prepare("SELECT id_prueba FROM catalogo_pruebas WHERE codigo_prueba = ?");
        $checkStmt->execute([$codigo]);
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Ya existe una prueba con el código ' . $codigo]);
            exit;
        }

        $stmt = $conn->prepare("
            INSERT INTO catalogo_pruebas (nombre_prueba, codigo_prueba, categoria, precio, muestra_requerida, tiempo_procesamiento_horas, notas)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nombre, $codigo, $categoria, $precio, $muestra, $tiempo, $notas]);
        $message = 'Prueba creada correctamente';
    }

    echo json_encode(['success' => true, 'message' => $message]);

} catch (PDOException $e) {
    // Handle database-specific errors
    if ($e->getCode() == 23000) { // Integrity constraint violation
        echo json_encode(['success' => false, 'message' => 'El código de prueba ya existe. Por favor use un código diferente.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
