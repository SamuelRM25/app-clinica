<?php
// laboratory/api/create_order.php - Process new laboratory order
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

verify_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit;
}

$id_paciente = $_POST['id_paciente'] ?? null;
$id_doctor = $_POST['id_doctor'] ?: null;
$prioridad = $_POST['prioridad'] ?? 'Normal';
$instrucciones = $_POST['instrucciones'] ?? '';
$pruebas_ids = $_POST['pruebas'] ?? [];

if (!$id_paciente || empty($pruebas_ids)) {
    die("Datos incompletos");
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    $conn->beginTransaction();
    
    // 1. Generate unique order number (e.g., LAB-20231027-001)
    $today = date('Ymd');
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM ordenes_laboratorio WHERE DATE(fecha_orden) = CURDATE()");
    $stmt->execute();
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] + 1;
    $numero_orden = "LAB-" . $today . "-" . str_pad($count, 3, '0', STR_PAD_LEFT);
    
    // 2. Create Order
    $stmt = $conn->prepare("
        INSERT INTO ordenes_laboratorio (numero_orden, id_paciente, id_doctor, prioridad, instrucciones, estado, fecha_orden)
        VALUES (?, ?, ?, ?, ?, 'Pendiente', NOW())
    ");
    $stmt->execute([$numero_orden, $id_paciente, $id_doctor, $prioridad, $instrucciones]);
    $id_orden = $conn->lastInsertId();
    
    // 3. Add Tests to Order and calculate bill
    $total_order = 0;
    $stmt_prueba = $conn->prepare("INSERT INTO orden_pruebas (id_orden, id_prueba, estado) VALUES (?, ?, 'Pendiente')");
    $stmt_price = $conn->prepare("SELECT nombre_prueba, price FROM catalogo_pruebas WHERE id_prueba = ?");
    
    $items_for_billing = [];
    
    foreach ($pruebas_ids as $id_prueba) {
        $stmt_prueba->execute([$id_orden, $id_prueba]);
        
        $stmt_price->execute([$id_prueba]);
        $test_info = $stmt_price->fetch(PDO::FETCH_ASSOC);
        $total_order += $test_info['price'];
        
        $items_for_billing[] = [
            'nombre' => $test_info['nombre_prueba'],
            'precio' => $test_info['price']
        ];
    }
    
    // 4. Billing Integration
    // Check if patient is currently hospitalized
    $stmt_hosp = $conn->prepare("SELECT id_encamamiento FROM encamamientos WHERE id_paciente = ? AND estado = 'Activo' LIMIT 1");
    $stmt_hosp->execute([$id_paciente]);
    $hosp = $stmt_hosp->fetch(PDO::FETCH_ASSOC);
    
    if ($hosp) {
        // Patient is hospitalized, add as charges to their account
        $id_encamamiento = $hosp['id_encamamiento'];
        $stmt_cargo = $conn->prepare("
            INSERT INTO cargos_encamamiento (id_encamamiento, descripcion, monto, fecha_cargo, id_categoria_cargo)
            VALUES (?, ?, ?, NOW(), (SELECT id_categoria FROM categorias_cargos_hospital WHERE nombre_categoria = 'Laboratorio' LIMIT 1))
        ");
        
        foreach ($items_for_billing as $item) {
            $stmt_cargo->execute([
                $id_encamamiento,
                "Laboratorio: " . $item['nombre'] . " (Orden #" . $numero_orden . ")",
                $item['precio']
            ]);
        }
    } else {
        // Outpatient, create a general bill entry (if your system handles it this way)
        // For now, we'll just log it in the order total
    }
    
    $conn->commit();
    
    // Redirect to index with success message
    header("Location: ../index.php?success=1&order=" . $numero_orden);
    
} catch (Exception $e) {
    if (isset($conn)) $conn->rollBack();
    die("Error: " . $e->getMessage());
}
