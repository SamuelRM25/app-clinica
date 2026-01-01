<?php
// Iniciar buffer de salida para evitar que warnings/notices rompan el JSON
ob_start();

session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Limpiar cualquier output previo (espacios, warnings)
ob_clean();
header('Content-Type: application/json');

try {
    // Verificar sesión manualmente para manejar el error en JSON
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Sesión no válida o expirada');
    }

    if (!isset($_GET['date'])) {
        throw new Exception('Fecha no proporcionada');
    }

    $database = new Database();
    $conn = $database->getConnection();

    $date = $_GET['date'];
    
    // Definir jornada: 8:00 AM del día seleccionado hasta 8:00 AM del día siguiente
    // Jornada 1: 08:00 AM a 05:00 PM (17:00)
    // Jornada 2: 05:00 PM (17:00) a 08:00 AM del día siguiente
    $start_datetime = $date . ' 08:00:00';
    $end_datetime = $date . ' 17:00:00';

    // Verificar si la columna usuario existe o usar un valor por defecto si falla
    $sql = "SELECT 
                e.nombre_paciente, 
                e.tipo_examen, 
                e.cobro, 
                e.fecha_examen,
                e.usuario 
            FROM examenes_realizados e
            WHERE e.fecha_examen >= :start_dt AND e.fecha_examen < :end_dt
            ORDER BY e.fecha_examen ASC";

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
            'generated_by' => $_SESSION['nombre'] ?? 'Usuario',
            'generated_at' => date('d/m/Y H:i:s', strtotime('-6 hours')),
            'jornada_start' => date('d/m/Y 08:00 A', strtotime($start_datetime)),
            'jornada_end' => date('d/m/Y 05:00 P', strtotime($end_datetime))
        ]
    ]);

} catch (Exception $e) {
    // Asegurar que solo devolvemos JSON válido incluso en error
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
// Finalizar buffer y enviar
ob_end_flush();
?>
