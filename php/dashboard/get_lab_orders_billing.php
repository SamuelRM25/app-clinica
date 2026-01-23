<?php
// get_lab_orders_billing.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once '../../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Fetch pending orders (or orders that haven't been fully billed - assuming 'Pendiente' or 'Muestra_Recibida' are billable states)
    // Adjusting query to get relevant details
    $query = "
        SELECT 
            ol.id_orden, 
            ol.numero_orden, 
            ol.fecha_orden,
            ol.id_paciente,
            CONCAT(p.nombre, ' ', p.apellido) AS nombre_paciente,
            GROUP_CONCAT(cp.nombre_prueba SEPARATOR ', ') AS lista_pruebas,
            SUM(cp.precio) AS total_estimado
        FROM ordenes_laboratorio ol
        JOIN pacientes p ON ol.id_paciente = p.id_paciente
        JOIN orden_pruebas op ON ol.id_orden = op.id_orden
        JOIN catalogo_pruebas cp ON op.id_prueba = cp.id_prueba
        WHERE ol.estado NOT IN ('Cancelada') 
        -- Optional: Add check if already billed if tracking exists, for now assuming we list active orders
        GROUP BY ol.id_orden
        ORDER BY ol.fecha_orden DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'orders' => $orders]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>