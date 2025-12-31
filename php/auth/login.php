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
    
    if ($user && $password === $user['password']) { // In production, use password_verify()
        $_SESSION['user_id'] = $user['idUsuario'];
        $_SESSION['nombre'] = $user['nombre'];
        $_SESSION['apellido'] = $user['apellido'];
        $_SESSION['clinica'] = $user['clinica'];
        $_SESSION['especialidad'] = $user['especialidad'];
        $_SESSION['tipoUsuario'] = $user['tipoUsuario'];
        
        header("Location: ../dashboard/index.php");
        exit();
    } else {
        header("Location: ../../index.php?error=1");
        exit();
    }
}
?>