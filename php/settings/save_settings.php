<?php
// settings/save_settings.php - Guardar configuración del sistema
require_once '../../includes/functions.php';
start_app_session();
require_once '../../config/database.php';
require_once '../../includes/multitenant.php';



verify_session();
verify_csrf_token();

if ($_SESSION['tipoUsuario'] !== 'admin' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    $id_hospital = $_SESSION['id_hospital'];

    // Action: Save Theme
    if (isset($_POST['action']) && $_POST['action'] === 'save_theme') {
        $tema = $_POST['tema'] ?? 'classic';
        $stmt = $conn->prepare("UPDATE hospitales SET tema = ? WHERE id_hospital = ?");
        $stmt->execute([$tema, $id_hospital]);
        $_SESSION['hospital_tema'] = $tema;
        header("Location: index.php?status=success&message=Tema actualizado correctamente");
        exit;
    }

    // 1. Asegurar que la tabla existe con id_hospital
    $conn->exec("CREATE TABLE IF NOT EXISTS configuracion_sistema (
        id_config INT PRIMARY KEY AUTO_INCREMENT,
        id_hospital INT NOT NULL,
        nombre_clinica VARCHAR(200),
        direccion TEXT,
        telefono VARCHAR(50),
        email VARCHAR(100),
        logo_path VARCHAR(255),
        moneda VARCHAR(10) DEFAULT 'GTQ',
        fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE(id_hospital)
    )");

    // 2. Procesar datos del formulario
    $nombre = $_POST['nombre_clinica'] ?? '';
    $email = $_POST['email'] ?? '';
    $direccion = $_POST['direccion'] ?? '';
    $telefono = $_POST['telefono'] ?? '';

    // 3. Verificar si ya hay una configuración para ESTE hospital
    $stmt = $conn->prepare("SELECT id_config FROM configuracion_sistema WHERE id_hospital = ? LIMIT 1");
    $stmt->execute([$id_hospital]);
    $exists = $stmt->fetch();

    if ($exists) {
        $stmt = $conn->prepare("UPDATE configuracion_sistema SET nombre_clinica = ?, email = ?, direccion = ?, telefono = ? WHERE id_hospital = ?");
        $stmt->execute([$nombre, $email, $direccion, $telefono, $id_hospital]);
    } else {
        $stmt = $conn->prepare("INSERT INTO configuracion_sistema (id_hospital, nombre_clinica, email, direccion, telefono) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$id_hospital, $nombre, $email, $direccion, $telefono]);
    }

    header("Location: index.php?status=success&message=Configuración actualizada correctamente");
} catch (Exception $e) {
    error_log('Error en settings/save_settings.php: ' . $e->getMessage());
    header("Location: index.php?status=error&message=Error+del+servidor");
}
?>