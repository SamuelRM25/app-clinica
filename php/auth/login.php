<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $conn = $database->getConnection();

    $usuario = sanitize_input($_POST['usuario']);
    $password = sanitize_input($_POST['password']);

    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE usuario = ?");
    $stmt->execute([$usuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $password === $user['password']) {
        $_SESSION['user_id'] = $user['idUsuario'];
        $_SESSION['nombre'] = $user['nombre'];
        $_SESSION['id_hospital'] = $user['id_hospital'];

        // Cargar configuración del hospital
        require_once '../../includes/multitenant.php';
        $h_config = get_hospital_config($conn, $user['id_hospital']);
        
        if ($h_config) {
            $_SESSION['hospital_nombre'] = $h_config['nombre'];
            $_SESSION['hospital_modulos'] = $h_config['modulos_activos'];
            $_SESSION['hospital_status'] = $h_config['estado_suscripcion'];
            $_SESSION['hospital_expiry'] = $h_config['fecha_vencimiento'];
        }

        $_SESSION['apellido'] = $user['apellido'];
        $_SESSION['clinica'] = $user['clinica'];
        $_SESSION['especialidad'] = $user['especialidad'];
        $_SESSION['tipoUsuario'] = $user['tipoUsuario'];
        $_SESSION['usuario'] = $user['usuario'];

        header("Location: ../dashboard/index.php");
        exit();
    } else {
        header("Location: ../../index.php?error=1");
        exit();
    }
}
?>