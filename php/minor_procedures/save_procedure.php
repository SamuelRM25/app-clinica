<?php
// Iniciar buffer de salida para evitar salida accidental
ob_start();

session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';



// Establecer la zona horaria correcta
date_default_timezone_set('America/Guatemala');

// Establecer header JSON - REMOVED for redirection handling
// header('Content-Type: application/json');

try {
    // CSRF validation
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (empty($csrfHeader) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfHeader)) {
        throw new Exception('Token CSRF inválido');
    }

    // Verificar sesión
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Sesión no válida o expirada');
    }

    // Verificar que sea POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // Obtener y validar datos
    $id_paciente = $_POST['id_paciente'] ?? null;
    $nombre_paciente = $_POST['nombre_paciente'] ?? null;
    $procedimientos_array = $_POST['procedimientos'] ?? [];
    $cobro = $_POST['cobro'] ?? null;
    $fecha_procedimiento = $_POST['fecha_procedimiento'] ?? null;

    // Filtrar procedimientos vacíos
    $procedimientos_filtrados = array_filter($procedimientos_array, function ($value) {
        return !empty(trim($value));
    });

    // Validar campos requeridos
    if (empty($id_paciente)) {
        throw new Exception('Debe seleccionar un paciente');
    }

    if (empty($procedimientos_filtrados)) {
        throw new Exception('Debe seleccionar o agregar al menos un procedimiento');
    }

    if (empty($cobro) || !is_numeric($cobro)) {
        throw new Exception('Debe ingresar un costo válido');
    }

    if (empty($fecha_procedimiento)) {
        throw new Exception('Debe seleccionar una fecha y hora');
    }

    // Combinar procedimientos en un solo texto
    $procedimiento_final = implode(', ', $procedimientos_filtrados);

    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();

    $tipo_pago = $_POST['tipo_pago'] ?? 'Efectivo';

    $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

    // Preparar la consulta para insertar
    $stmt = $conn->prepare(
        "INSERT INTO procedimientos_menores 
        (id_paciente, nombre_paciente, procedimiento, cobro, fecha_procedimiento, tipo_pago, usuario, id_hospital) 
        VALUES 
        (:id_paciente, :nombre_paciente, :procedimiento, :cobro, :fecha_procedimiento, :tipo_pago, :usuario, :id_hospital)"
    );

    $stmt->bindParam(':id_paciente', $id_paciente);
    $stmt->bindParam(':nombre_paciente', $nombre_paciente);
    $stmt->bindParam(':procedimiento', $procedimiento_final);
    $stmt->bindParam(':cobro', $cobro);
    $stmt->bindParam(':fecha_procedimiento', $fecha_procedimiento);
    $stmt->bindParam(':tipo_pago', $tipo_pago);
    $stmt->bindParam(':usuario', $_SESSION['nombre']);
    $stmt->bindParam(':id_hospital', $id_hospital, PDO::PARAM_INT);

    $stmt->execute();

    // Limpiar buffer y enviar respuesta exitosa
    ob_clean();
    header("Location: index.php?status=success&message=" . urlencode("Procedimiento registrado exitosamente"));
    exit;

} catch (PDOException $e) {
    // Error de base de datos
    ob_clean();
error_log('Error en save_procedure.php DB: ' . $e->getMessage());
    header("Location: index.php?status=error&message=" . urlencode("Error al guardar en la base de datos"));
    exit;
} catch (Exception $e) {
    // Otros errores
    ob_clean();
error_log('Error en save_procedure.php: ' . $e->getMessage());
    header("Location: index.php?status=error&message=" . urlencode("Error del servidor"));
    exit;
}
?>