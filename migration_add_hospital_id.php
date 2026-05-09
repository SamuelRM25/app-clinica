<?php
/**
 * migration_add_hospital_id.php
 * 
 * Ejecuta este script UNA SOLA VEZ en cada instancia del servidor.
 * Agrega la columna id_hospital a todas las tablas de datos clínicos
 * para activar el aislamiento multi-tenant completo.
 * 
 * Acceder a: https://tu-dominio.com/base/migration_add_hospital_id.php
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/hospital.php';

$database = new Database();
$conn = $database->getConnection();

// Obtener el id_hospital de esta instalación
$stmt = $conn->prepare("SELECT id_hospital FROM hospitales WHERE codigo_hospital = ?");
$stmt->execute([CURRENT_HOSPITAL_CODE]);
$hospital = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$hospital) {
    die("<h2 style='color:red'>❌ Error: No se encontró el hospital con código '" . CURRENT_HOSPITAL_CODE . "' en la base de datos.</h2>");
}

$hospital_id = $hospital['id_hospital'];

echo "<h1>Migración Multi-Tenant - Hospital ID: $hospital_id (" . CURRENT_HOSPITAL_CODE . ")</h1>";
echo "<pre style='background:#f0f0f0; padding:20px; font-family:monospace'>";

// Tablas que necesitan id_hospital con sus claves primarias
$tables = [
    'pacientes'              => 'id_paciente',
    'citas'                  => 'id_cita',
    'cobros'                 => 'in_cobro',
    'ordenes_laboratorio'    => 'id_orden',
    'inventario'             => 'id_inventario',
    'ventas'                 => 'id_venta',
    'hospitalizaciones'      => 'id_hospitalizacion',
    'compras'                => 'id_compra',
    'purchase_headers'       => 'id',
    'historial_clinico'      => 'id_historial',
    'electros'               => 'id_electro',
    'procedimientos'         => 'id_procedimiento',
    'rayos_x'                => 'id_rx',
    'ultrasonidos'           => 'id_us',
    'reportes_corte'         => 'id_corte',
];

$migrated = [];
$skipped  = [];
$errors   = [];

foreach ($tables as $table => $pk) {
    try {
        // Verificar si la tabla existe
        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() === 0) {
            $skipped[] = "$table (tabla no existe)";
            continue;
        }

        // Verificar si la columna ya existe
        $stmt = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'id_hospital'");
        if ($stmt->rowCount() > 0) {
            // Actualizar filas sin asignar
            $conn->exec("UPDATE `$table` SET id_hospital = $hospital_id WHERE id_hospital IS NULL OR id_hospital = 0");
            $skipped[] = "$table (columna ya existía, datos actualizados)";
            continue;
        }

        // Agregar la columna
        $conn->exec("ALTER TABLE `$table` ADD COLUMN id_hospital INT NOT NULL DEFAULT $hospital_id AFTER `$pk`");
        
        // Asignar este hospital a todos los registros existentes (son del hospital actual)
        $conn->exec("UPDATE `$table` SET id_hospital = $hospital_id WHERE id_hospital = 0 OR id_hospital IS NULL");
        
        $migrated[] = $table;
        echo "✅ $table → columna agregada y datos asignados a hospital $hospital_id\n";

    } catch (Exception $e) {
        $errors[] = "$table: " . $e->getMessage();
        echo "❌ Error en $table: " . $e->getMessage() . "\n";
    }
}

echo "\n--- RESUMEN ---\n";
echo "✅ Migradas: " . count($migrated) . " tablas\n";
echo "⏭  Omitidas: " . count($skipped) . " tablas\n";
if (!empty($skipped)) {
    foreach ($skipped as $s) echo "   - $s\n";
}
echo "❌ Errores:  " . count($errors) . " tablas\n";
if (!empty($errors)) {
    foreach ($errors as $e) echo "   - $e\n";
}

echo "\n🎉 Migración completada. Puedes eliminar este archivo del servidor.\n";
echo "</pre>";
?>
