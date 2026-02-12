<?php
// api/check_consultation_history.php
header('Content-Type: application/json');
require_once '../../config/database.php';

if (!isset($_GET['id_paciente'])) {
    echo json_encode(['status' => 'error', 'message' => 'Falta id_paciente']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $id_paciente = $_GET['id_paciente'];

    // Contar citas pasadas que ya ocurrieron (fecha < hoy o (fecha = hoy y hora < ahora) o simplemente todas las registradas)
    // Asumiremos que cualquier registro en 'citas' cuenta, o quizás solo las que tienen diagnóstico o están marcadas como completadas.
    // Dado el esquema simple, contaremos todas las citas anteriores.

    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM citas WHERE id_paciente = ?");
    $stmt->execute([$id_paciente]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    echo json_encode([
        'status' => 'success',
        'count' => (int) $count,
        'has_prior' => $count > 0
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>