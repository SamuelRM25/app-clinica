<?php
// settings/save_settings.php - Guardar configuración del sistema
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

if ($_SESSION['tipoUsuario'] !== 'admin' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // 1. Asegurar que la tabla existe
    $conn->exec("CREATE TABLE IF NOT EXISTS configuracion_sistema (
        id_config INT PRIMARY KEY AUTO_INCREMENT,
        nombre_clinica VARCHAR(200),
        direccion TEXT,
        telefono VARCHAR(50),
        email VARCHAR(100),
        logo_path VARCHAR(255),
        moneda VARCHAR(10) DEFAULT 'GTQ',
        fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // 2. Procesar datos del formulario
    $nombre = $_POST['nombre'] ?? '';
    $email = $_POST['email'] ?? '';
    $direccion = $_POST['direccion'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    
    // 3. Verificar si ya hay una configuración
    $stmt = $conn->query("SELECT id_config FROM configuracion_sistema LIMIT 1");
    $exists = $stmt->fetch();
    
    if ($exists) {
        $stmt = $conn->prepare("UPDATE configuracion_sistema SET nombre_clinica = ?, email = ?, direccion = ?, telefono = ? WHERE id_config = ?");
        $stmt->execute([$nombre, $email, $direccion, $telefono, $exists['id_config']]);
    } else {
        $stmt = $conn->prepare("INSERT INTO configuracion_sistema (nombre_clinica, email, direccion, telefono) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nombre, $email, $direccion, $telefono]);
    }
    
    header("Location: index.php?status=success&message=Configuración actualizada correctamente");
} catch (Exception $e) {
    header("Location: index.php?status=error&message=" . urlencode($e->getMessage()));
}
?>
