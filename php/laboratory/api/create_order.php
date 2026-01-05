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
$prioridad_raw = $_POST['prioridad'] ?? 'Normal';
$indicaciones = $_POST['instrucciones'] ?? '';
$observaciones = $_POST['observaciones'] ?? '';
$pruebas_ids = $_POST['pruebas'] ?? [];

// Mapear prioridades al enum de la BD
$priority_map = [
    'Normal' => 'Rutina',
    'Urgente' => 'Urgente',
    'Emergencia' => 'STAT'
];
$prioridad = $priority_map[$prioridad_raw] ?? 'Rutina';

if (!$id_paciente || empty($pruebas_ids)) {
    die("Datos incompletos");
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    $conn->beginTransaction();
    
    // 1. Generate unique order number
    $today = date('Ymd');
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM ordenes_laboratorio WHERE DATE(fecha_orden) = CURDATE()");
    $stmt->execute();
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] + 1;
    $numero_orden = "LAB-" . $today . "-" . str_pad($count, 3, '0', STR_PAD_LEFT);
    
    // 2. Check if patient is hospitalized
    $stmt_hosp = $conn->prepare("SELECT id_encamamiento FROM encamamientos WHERE id_paciente = ? AND estado = 'Activo' LIMIT 1");
    $stmt_hosp->execute([$id_paciente]);
    $hosp = $stmt_hosp->fetch(PDO::FETCH_ASSOC);
    $id_encamamiento = $hosp ? $hosp['id_encamamiento'] : null;

    // 3. Create Order
    $stmt = $conn->prepare("
        INSERT INTO ordenes_laboratorio (
            numero_orden, id_paciente, id_doctor, id_encamamiento, 
            prioridad, indicaciones_especiales, observaciones, 
            estado, fecha_orden
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pendiente', NOW())
    ");
    $stmt->execute([
        $numero_orden, 
        $id_paciente, 
        $id_doctor, 
        $id_encamamiento, 
        $prioridad, 
        $indicaciones, 
        $observaciones
    ]);
    $id_orden = $conn->lastInsertId();
    
    // 4. Add Tests to Order and calculate bill
    $total_order = 0;
    $stmt_prueba = $conn->prepare("INSERT INTO orden_pruebas (id_orden, id_prueba, estado) VALUES (?, ?, 'Pendiente')");
    $stmt_price = $conn->prepare("SELECT nombre_prueba, precio FROM catalogo_pruebas WHERE id_prueba = ?");
    
    $items_for_billing = [];
    
    foreach ($pruebas_ids as $id_prueba) {
        $stmt_prueba->execute([$id_orden, $id_prueba]);
        
        $stmt_price->execute([$id_prueba]);
        $test_info = $stmt_price->fetch(PDO::FETCH_ASSOC);
        if ($test_info) {
            $total_order += $test_info['precio'];
            
            $items_for_billing[] = [
                'nombre' => $test_info['nombre_prueba'],
                'precio' => $test_info['precio']
            ];
        }
    }
    
    // 5. Billing Integration (if hospitalized)
    if ($id_encamamiento) {
        $stmt_cargo = $conn->prepare("
            INSERT INTO cargos_hospitalarios (id_cuenta, tipo_cargo, descripcion, precio_unitario, fecha_cargo, registrado_por)
            VALUES (
                (SELECT id_cuenta FROM cuenta_hospitalaria WHERE id_encamamiento = ? AND estado_pago = 'Pendiente' LIMIT 1),
                'Laboratorio', ?, ?, NOW(), ?
            )
        ");
        
        $user_id = $_SESSION['user_id'] ?? 1;
        
        foreach ($items_for_billing as $item) {
            $stmt_cargo->execute([
                $id_encamamiento,
                "Laboratorio: " . $item['nombre'] . " (Orden #" . $numero_orden . ")",
                $item['precio'],
                $user_id
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
