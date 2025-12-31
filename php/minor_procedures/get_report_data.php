<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();
header('Content-Type: application/json');

if (!isset($_GET['date'])) {
    echo json_encode(['status' => 'error', 'message' => 'Fecha no proporcionada']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $date = $_GET['date'];
    
    // Definir jornada: 8:00 AM del día seleccionado hasta 8:00 AM del día siguiente
    $start_datetime = $date . ' 08:00:00';
    $end_datetime = date('Y-m-d', strtotime($date . ' +1 day')) . ' 08:00:00';

    $sql = "SELECT 
                p.nombre_paciente, 
                p.procedimiento, 
                p.cobro, 
                p.fecha_procedimiento,
                p.usuario 
            FROM procedimientos_menores p
            WHERE p.fecha_procedimiento >= :start_dt AND p.fecha_procedimiento < :end_dt
            ORDER BY p.fecha_procedimiento ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':start_dt', $start_datetime);
    $stmt->bindParam(':end_dt', $end_datetime);
    $stmt->execute();
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular total
    $total = 0;
    foreach ($data as $row) {
        $total += $row['cobro'];
    }

    echo json_encode([
        'status' => 'success',
        'data' => $data,
        'total' => $total,
        'metadata' => [
            'generated_by' => $_SESSION['nombre'],
            'generated_at' => date('d/m/Y H:i:s'),
            'jornada_start' => date('d/m/Y 08:00 A', strtotime($start_datetime)),
            'jornada_end' => date('d/m/Y 08:00 A', strtotime($end_datetime))
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
