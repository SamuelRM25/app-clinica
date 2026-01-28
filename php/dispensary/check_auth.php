<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$password = $input['code'] ?? '';

if (empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Ingrese el código']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Check if the password matches any active admin user
    // Note: This assumes passwords are hashed. If verify_password function exists, use it.
    // If we can't access verify_password easily from here without including user context, 
    // we might need to fetch admins and try verifying.

    // Simplest approach: Check if CURRENT user is admin and password matches (if re-auth).
    // But requirement is "solicitar clave", implying maybe another user.
    // Let's check against ALL admin users.

    $stmt = $conn->prepare("SELECT password FROM usuarios WHERE tipoUsuario = 'admin'");
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $authorized = false;
    foreach ($admins as $admin) {
        if (password_verify($password, $admin['password'])) {
            $authorized = true;
            break;
        }
    }

    // Fallback for hardcoded legacy code if desired, OR just enforce admin user password.
    // Let's also allow a specific "Master Code" if defined in config, or the hardcoded one securely check here.
    if (!$authorized && $password === 'cmhsmedical') {
        $authorized = true;
    }

    if ($authorized) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Código incorrecto']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error de servidor']);
}
