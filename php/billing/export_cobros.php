<?php
// billing/export_cobros.php - Exportar cobros a CSV
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

if ($_SESSION['tipoUsuario'] !== 'admin') {
    die("Acceso denegado");
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
    $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
    
    $stmt = $conn->prepare("
        SELECT c.*, p.nombre, p.apellido 
        FROM cobros c
        JOIN pacientes p ON c.id_paciente = p.id_paciente
        WHERE c.fecha_consulta BETWEEN ? AND ?
        ORDER BY c.fecha_consulta DESC
    ");
    $stmt->execute([$fecha_inicio, $fecha_fin]);
    $cobros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=cobros_' . $fecha_inicio . '_a_' . $fecha_fin . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Paciente', 'Fecha', 'Cantidad', 'Método Pago', 'Estado', 'Notas']);
    
    foreach ($cobros as $row) {
        fputcsv($output, [
            $row['id_cobro'],
            $row['nombre'] . ' ' . $row['apellido'],
            $row['fecha_consulta'],
            $row['cantidad_consulta'],
            $row['metodo_pago'] ?? 'N/A',
            $row['estado'] ?? 'Pagado',
            $row['comentarios'] ?? ''
        ]);
    }
    fclose($output);
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
