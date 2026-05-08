<?php
// inventory/api/link_hospital_medication.php
// API para vincular un cargo hospitalario a un item del inventario y descargarlo

session_start();
header('Content-Type: application/json');

// Verificar sesión activa
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

require_once '../../../config/database.php';

// Verificar permisos de gestión (admin o usuarios específicos 1, 6)
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['tipoUsuario'] ?? '';
if ($user_type !== 'admin' && !in_array($user_id, [1, 6])) {
    echo json_encode(['success' => false, 'message' => 'No tiene permisos para realizar esta acción.']);
    exit;
}

$id_cargo = isset($_POST['id_cargo']) ? intval($_POST['id_cargo']) : 0;
$id_inventario = isset($_POST['id_inventario']) ? intval($_POST['id_inventario']) : 0;
$cantidad = isset($_POST['cantidad']) ? floatval($_POST['cantidad']) : 0;

if ($id_cargo <= 0 || $id_inventario <= 0 || $cantidad <= 0) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos.']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Iniciar transacción
    $conn->beginTransaction();
    
    // 1. Verificar el cargo hospitalario
    $stmt = $conn->prepare("SELECT id_cargo, referencia_id FROM cargos_hospitalarios WHERE id_cargo = ? FOR UPDATE");
    $stmt->execute([$id_cargo]);
    $cargo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cargo) {
        throw new Exception('El cargo hospitalario no existe.');
    }
    
    if ($cargo['referencia_id'] != null) {
        throw new Exception('Este medicamento ya ha sido descargado o vinculado al inventario previamente.');
    }
    
    // 2. Verificar inventario y stock
    $stmt = $conn->prepare("SELECT nom_medicamento, stock_hospital FROM inventario WHERE id_inventario = ? FOR UPDATE");
    $stmt->execute([$id_inventario]);
    $inv = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$inv) {
        throw new Exception('El medicamento seleccionado en el inventario no existe.');
    }
    
    if ($inv['stock_hospital'] < $cantidad) {
        throw new Exception('Stock insuficiente en farmacia hospitalaria. Disponible: ' . $inv['stock_hospital']);
    }
    
    // 3. Descontar del inventario (stock_hospital)
    $stmt = $conn->prepare("UPDATE inventario SET stock_hospital = stock_hospital - ? WHERE id_inventario = ?");
    $stmt->execute([$cantidad, $id_inventario]);
    
    // 4. Actualizar el cargo hospitalario
    $stmt = $conn->prepare("UPDATE cargos_hospitalarios SET referencia_id = ?, referencia_tabla = 'inventario' WHERE id_cargo = ?");
    $stmt->execute([$id_inventario, $id_cargo]);
    
    // Confirmar transacción
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Medicamento descargado exitosamente. Se descontaron ' . $cantidad . ' de ' . $inv['nom_medicamento']
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error linking hospital medication: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
