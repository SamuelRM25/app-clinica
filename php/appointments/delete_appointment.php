<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';



verify_session();

// Get the JSON data from the request
$data = json_decode(file_get_contents('php://input'), true);

header('Content-Type: application/json');

if (isset($data['id'])) {
    try {
        // CSRF validation
        $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
        if (empty($csrfHeader) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfHeader)) {
            throw new Exception('Token CSRF inválido');
        }

        $database = new Database();
        if (!($conn = $database->getConnection())) {
            throw new Exception('Failed to establish database connection');
        }

        // Prepare and execute the delete statement
        $id_hospital = $_SESSION['id_hospital'] ?? 0;
        $stmt = $conn->prepare("DELETE FROM citas WHERE id_cita = ? AND id_hospital = ?");
        $result = $stmt->execute([$data['id'], $id_hospital]);

        if ($result) {
            echo json_encode(['status' => 'success', 'message' => 'Cita eliminada correctamente']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar la cita']);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        error_log('Error en appointments/delete_appointment.php: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Error al eliminar la cita: ' . 'Error del servidor.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'ID de cita no proporcionado']);
}