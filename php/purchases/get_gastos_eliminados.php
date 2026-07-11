<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/multitenant.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

    $conn->exec("CREATE TABLE IF NOT EXISTS gastos_eliminados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_original INT NOT NULL,
        descripcion VARCHAR(255) NOT NULL,
        cantidad INT NOT NULL DEFAULT 1,
        subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        fecha DATE NOT NULL,
        created_by INT NOT NULL,
        id_hospital INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        motivo_eliminacion TEXT NOT NULL,
        eliminado_por INT NOT NULL,
        fecha_eliminacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_id_hospital (id_hospital),
        INDEX idx_fecha_eliminacion (fecha_eliminacion)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
    $fecha_fin    = $_GET['fecha_fin'] ?? date('Y-m-d');

    $stmt = $conn->prepare("
        SELECT
            e.*,
            CONCAT(u.nombre, ' ', u.apellido) AS registrado_por,
            CONCAT(ue.nombre, ' ', ue.apellido) AS eliminado_por_nombre
        FROM gastos_eliminados e
        LEFT JOIN usuarios u ON e.created_by = u.idUsuario
        LEFT JOIN usuarios ue ON e.eliminado_por = ue.idUsuario
        WHERE e.fecha_eliminacion BETWEEN ? AND ?
          AND e.id_hospital = ?
        ORDER BY e.fecha_eliminacion DESC
    ");
    $stmt->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59', $id_hospital]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = 0;
    foreach ($rows as &$row) {
        $row['total'] = (float)$row['total'];
        $row['subtotal'] = (float)$row['subtotal'];
        $row['cantidad'] = (int)$row['cantidad'];
        $total += $row['total'];
    }
    unset($row);

    echo json_encode([
        'success' => true,
        'rows' => $rows,
        'total_eliminados' => $total,
        'count' => count($rows),
    ]);

} catch (Exception $e) {
    error_log('Error en purchases/get_gastos_eliminados.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
