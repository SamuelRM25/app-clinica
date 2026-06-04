<?php
// settings/api/save_user.php
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['tipoUsuario'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

// Validate CSRF token safely
$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $idUsuario = $_POST['idUsuario'] ?? '';
    $usuario = $_POST['usuario'] ?? '';
    $nombre = $_POST['nombre'] ?? '';
    $apellido = $_POST['apellido'] ?? '';
    $tipoUsuario = $_POST['tipoUsuario'] ?? 'user';
    $especialidad = $_POST['especialidad'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($usuario) || empty($nombre) || empty($apellido)) {
        echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios']);
        exit;
    }

    $id_hospital = $_SESSION['id_hospital'] ?? 0;

    if (!empty($password)) {
        $passErrors = validate_password_strength($password);
        if (!empty($passErrors)) {
            echo json_encode(['success' => false, 'message' => 'La contraseña no cumple los requisitos: ' . implode(', ', $passErrors)]);
            exit;
        }
    }

    if (empty($idUsuario)) {
        if (empty($password)) {
            echo json_encode(['success' => false, 'message' => 'La contraseña es obligatoria para nuevos usuarios']);
            exit;
        }

        $stmt_check = $conn->prepare("SELECT idUsuario FROM usuarios WHERE usuario = ? AND id_hospital = ?");
        $stmt_check->execute([$usuario, $id_hospital]);
        if ($stmt_check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'El nombre de usuario ya existe']);
            exit;
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO usuarios (usuario, password, nombre, apellido, tipoUsuario, especialidad, email, clinica, id_hospital) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$usuario, $hashed_password, $nombre, $apellido, $tipoUsuario, $especialidad, $email, 'Centro Médico Herrera Saenz', $id_hospital]);
        $newId = $conn->lastInsertId();

        audit_log('create', 'users', "Usuario creado: $usuario ($nombre $apellido)", [
            'table_name' => 'usuarios',
            'record_id' => (int)$newId,
            'new_data' => [
                'usuario' => $usuario,
                'nombre' => $nombre,
                'apellido' => $apellido,
                'tipoUsuario' => $tipoUsuario,
                'especialidad' => $especialidad,
                'email' => $email
            ]
        ]);

        echo json_encode(['success' => true, 'message' => 'Usuario creado correctamente']);
    } else {
        // Fetch old data for audit
        $fetchStmt = $conn->prepare("SELECT usuario, nombre, apellido, tipoUsuario, especialidad, email FROM usuarios WHERE idUsuario = ? AND id_hospital = ?");
        $fetchStmt->execute([$idUsuario, $id_hospital]);
        $oldData = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE usuarios SET usuario = ?, password = ?, nombre = ?, apellido = ?, tipoUsuario = ?, especialidad = ?, email = ? WHERE idUsuario = ? AND id_hospital = ?");
            $stmt->execute([$usuario, $hashed_password, $nombre, $apellido, $tipoUsuario, $especialidad, $email, $idUsuario, $id_hospital]);
        } else {
            $stmt = $conn->prepare("UPDATE usuarios SET usuario = ?, nombre = ?, apellido = ?, tipoUsuario = ?, especialidad = ?, email = ? WHERE idUsuario = ? AND id_hospital = ?");
            $stmt->execute([$usuario, $nombre, $apellido, $tipoUsuario, $especialidad, $email, $idUsuario, $id_hospital]);
        }

        audit_log('update', 'users', "Usuario actualizado: $usuario (ID: $idUsuario)", [
            'table_name' => 'usuarios',
            'record_id' => (int)$idUsuario,
            'old_data' => $oldData,
            'new_data' => [
                'usuario' => $usuario,
                'nombre' => $nombre,
                'apellido' => $apellido,
                'tipoUsuario' => $tipoUsuario,
                'especialidad' => $especialidad,
                'email' => $email
            ]
        ]);

        echo json_encode(['success' => true, 'message' => 'Usuario actualizado correctamente']);
    }

} catch (Exception $e) {
    error_log("save_user error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al guardar el usuario.']);
}
?>