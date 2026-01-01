<?php
/**
 * API: Add charge to hospital account
 */
session_start();
header('Content-Type: application/json');
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

date_default_timezone_set('America/Guatemala');

try {
    $required = ['id_encamamiento', 'tipo_cargo', 'descripcion', 'cantidad', 'precio_unitario'];
    foreach ($required as $field) {
        if (!isset($_POST[$field]) || $_POST[$field] === '') {
            throw new Exception("Campo requerido: $field");
        }
    }
    
    $id_encamamiento = intval($_POST['id_encamamiento']);
    $tipo_cargo = $_POST['tipo_cargo'];
    $descripcion = trim($_POST['descripcion']);
    $cantidad = floatval($_POST['cantidad']);
    $precio_unitario = floatval($_POST['precio_unitario']);
    $registrado_por = $_SESSION['user_id'];
    $fecha_cargo = date('Y-m-d H:i:s');
    
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get id_cuenta
    $stmt_cuenta = $conn->prepare("SELECT id_cuenta FROM cuenta_hospitalaria WHERE id_encamamiento = ?");
    $stmt_cuenta->execute([$id_encamamiento]);
    $cuenta = $stmt_cuenta->fetch(PDO::FETCH_ASSOC);
    
    if (!$cuenta) {
        throw new Exception("No se encontró cuenta hospitalaria");
    }
    
    $id_cuenta = $cuenta['id_cuenta'];
    
    // Insert charge
    $stmt = $conn->prepare("
        INSERT INTO cargos_hospitalarios 
        (id_cuenta, tipo_cargo, descripcion, cantidad, precio_unitario, fecha_cargo, registrado_por)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $id_cuenta,
        $tipo_cargo,
        $descripcion,
        $cantidad,
        $precio_unitario,
        $fecha_cargo,
        $registrado_por
    ]);
    
    // Trigger will automatically update subtotals
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Cargo agregado correctamente',
        'id_cargo' => $conn->lastInsertId()
    ]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
