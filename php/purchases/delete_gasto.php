<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

csrf_token();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

    // Ensure gastos_eliminados table exists
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

    $data = json_decode(file_get_contents('php://input'), true);

    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($csrfHeader) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfHeader)) {
        throw new Exception('Token CSRF inválido');
    }

    if (!$data || !isset($data['id'])) {
        throw new Exception('ID de gasto no proporcionado');
    }

    $motivo = trim($data['motivo'] ?? '');
    if (empty($motivo)) {
        throw new Exception('Debe proporcionar un motivo de eliminación');
    }

    $id = (int)$data['id'];

    // Fetch full gasto record
    $stmt = $conn->prepare("SELECT * FROM gastos WHERE id = ? AND id_hospital = ?");
    $stmt->execute([$id, $id_hospital]);
    $gasto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$gasto) {
        throw new Exception('Gasto no encontrado');
    }

    $conn->beginTransaction();

    try {
        // Insert into gastos_eliminados
        $stmt = $conn->prepare("
            INSERT INTO gastos_eliminados
                (id_original, descripcion, cantidad, subtotal, total, fecha, created_by, id_hospital, created_at, motivo_eliminacion, eliminado_por, fecha_eliminacion)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $gasto['id'],
            $gasto['descripcion'],
            $gasto['cantidad'],
            $gasto['subtotal'],
            $gasto['total'],
            $gasto['fecha'],
            $gasto['created_by'],
            $gasto['id_hospital'],
            $gasto['created_at'] ?? date('Y-m-d H:i:s'),
            $motivo,
            $_SESSION['user_id']
        ]);

        // Delete from gastos
        $stmt = $conn->prepare("DELETE FROM gastos WHERE id = ? AND id_hospital = ?");
        $stmt->execute([$id, $id_hospital]);

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }

    audit_log('delete', 'gastos', "Gasto #{$gasto['id']} movido a eliminados - {$gasto['descripcion']} - Q{$gasto['total']} - Motivo: {$motivo}", [
        'table_name' => 'gastos_eliminados',
        'record_id' => $id,
        'old_data' => $gasto,
        'motivo' => $motivo,
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log('Error en purchases/delete_gasto.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
