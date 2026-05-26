<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

require_once '../../config/hospital.php'; // Identidad de la carpeta


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $conn = $database->getConnection();

    $usuario = sanitize_input($_POST['usuario']);
    $password = sanitize_input($_POST['password']);

    error_log("LOGIN DEBUG: Attempting login for user: '" . $usuario . "' with password: '" . $password . "'. Hospital code: '" . CURRENT_HOSPITAL_CODE . "'");

    // Buscar el usuario uniendo con la tabla hospitales para validar el CÓDIGO
    $stmt = $conn->prepare("
        SELECT u.*, h.id_hospital as hospital_real_id
        FROM usuarios u
        JOIN hospitales h ON u.id_hospital = h.id_hospital
        WHERE u.usuario = ? AND h.codigo_hospital = ?
    ");
    $stmt->execute([$usuario, CURRENT_HOSPITAL_CODE]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        error_log("LOGIN DEBUG: User found in DB. DB password: '" . $user['password'] . "'");
    } else {
        error_log("LOGIN DEBUG: User NOT found in DB with this hospital code.");
    }

    if ($user && $password === $user['password']) {
        error_log("LOGIN DEBUG: Password match. Setting session variables.");
        $_SESSION['user_id'] = $user['idUsuario'];
        $_SESSION['nombre'] = $user['nombre'];
        $_SESSION['id_hospital'] = $user['hospital_real_id']; // Guardamos el ID numérico real

        // Cargar configuración del hospital
        require_once '../../includes/multitenant.php';
        $h_config = get_hospital_config($conn, $user['id_hospital']);

        if ($h_config) {
            error_log("LOGIN DEBUG: Hospital config loaded successfully.");
            $_SESSION['hospital_nombre'] = $h_config['nombre'];
            $_SESSION['hospital_modulos'] = $h_config['modulos_activos'];
            $_SESSION['hospital_status'] = $h_config['estado_suscripcion'];
            $_SESSION['hospital_type'] = $h_config['tipo_suscripcion'];
            $_SESSION['hospital_expiry'] = $h_config['fecha_vencimiento'];
        } else {
            error_log("LOGIN DEBUG: Hospital config NOT found.");
        }

        $_SESSION['apellido'] = $user['apellido'];
        $_SESSION['clinica'] = $user['clinica'];
        $_SESSION['especialidad'] = $user['especialidad'];
        $_SESSION['tipoUsuario'] = $user['tipoUsuario'];
        $_SESSION['usuario'] = $user['usuario'];

        error_log("LOGIN DEBUG: Redirecting to dashboard/index.php");
        header("Location: ../dashboard/index.php");
        exit();
    } else {
        error_log("LOGIN DEBUG: Login failed. Redirecting back to index.php?error=1");
        header("Location: ../../index.php?error=1");
        exit();
    }
}
?>