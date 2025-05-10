<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

// Get the JSON data from the request
$data = json_decode(file_get_contents('php://input'), true);

header('Content-Type: application/json');

if (isset($data['id'])) {
    try {
        $database = new Database();
if (!($conn = $database->getConnection())) {
    throw new Exception('Failed to establish database connection');
}

        // Prepare and execute the delete statement
        $stmt = $conn->prepare("DELETE FROM citas WHERE id_cita = ?");
        $result = $stmt->execute([$data['id']]);

        if ($result) {
            echo json_encode(['status' => 'success', 'message' => 'Cita eliminada correctamente']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar la cita']);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Error al eliminar la cita: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'ID de cita no proporcionado']);
}