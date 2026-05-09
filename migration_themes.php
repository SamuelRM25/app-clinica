<?php
/**
 * migration_themes.php
 * Agrega la columna 'tema' a la tabla 'hospitales' y la tabla 'configuracion_sistema'
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/hospital.php';

$database = new Database();
$conn = $database->getConnection();

echo "<h1>Migración de Temas y Configuración</h1>";
echo "<pre>";

try {
    // 1. Agregar columna 'tema' a hospitales
    $stmt = $conn->query("SHOW COLUMNS FROM `hospitales` LIKE 'tema'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE `hospitales` ADD COLUMN `tema` VARCHAR(50) DEFAULT 'classic' AFTER `modulos_activos` ");
        echo "✅ Columna 'tema' agregada a tabla 'hospitales'.\n";
    } else {
        echo "ℹ️  La columna 'tema' ya existe.\n";
    }

    // 2. Asegurar que existe la tabla configuracion_sistema
    // (A veces el sistema la usa para datos globales de la clínica)
    $conn->exec("CREATE TABLE IF NOT EXISTS `configuracion_sistema` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `id_hospital` INT NOT NULL,
        `nombre_clinica` VARCHAR(255),
        `direccion` TEXT,
        `telefono` VARCHAR(50),
        `email` VARCHAR(255),
        `logo_path` VARCHAR(255),
        `tema_activo` VARCHAR(50) DEFAULT 'classic',
        UNIQUE(`id_hospital`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "✅ Tabla 'configuracion_sistema' verificada.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\nMigración finalizada.";
echo "</pre>";
?>
