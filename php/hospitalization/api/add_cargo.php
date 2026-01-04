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
    $database = new Database();
    $conn = $database->getConnection();
    
    // Check if we are receiving an array of charges or a single one
    $cargos_to_process = [];
    
    if (isset($_POST['cargos']) && is_array($_POST['cargos'])) {
        $cargos_to_process = $_POST['cargos'];
    } elseif (isset($_POST['id_encamamiento'])) {
        // Single charge (classic way)
        $cargos_to_process[] = [
            'id_encamamiento' => $_POST['id_encamamiento'],
            'tipo_cargo' => $_POST['tipo_cargo'],
            'descripcion' => $_POST['descripcion'],
            'cantidad' => $_POST['cantidad'],
            'precio_unitario' => $_POST['precio_unitario']
        ];
    } else {
        throw new Exception("No se recibieron datos de cargos");
    }
    
    $registrado_por = $_SESSION['user_id'];
    $fecha_cargo = date('Y-m-d H:i:s');
    
    $conn->beginTransaction();
    
    foreach ($cargos_to_process as $index => $cargo_data) {
        $id_encamamiento = intval($cargo_data['id_encamamiento']);
        $tipo_cargo = $cargo_data['tipo_cargo'];
        $descripcion = trim($cargo_data['descripcion']);
        $cantidad = floatval($cargo_data['cantidad']);
        $precio_unitario = floatval($cargo_data['precio_unitario']);
        
        // Get id_cuenta for this encamamiento
        $stmt_cuenta = $conn->prepare("SELECT id_cuenta FROM cuenta_hospitalaria WHERE id_encamamiento = ?");
        $stmt_cuenta->execute([$id_encamamiento]);
        $cuenta = $stmt_cuenta->fetch(PDO::FETCH_ASSOC);
        
        if (!$cuenta) {
            throw new Exception("No se encontró cuenta hospitalaria para el cargo #$index");
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
    }
    
    $conn->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => count($cargos_to_process) . ' cargo(s) agregado(s) correctamente'
    ]);
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
