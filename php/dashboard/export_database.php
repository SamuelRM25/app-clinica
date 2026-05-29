<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['tipoUsuario'] !== 'admin') {
    die("Acceso no autorizado.");
}

require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

try {
    $database = new Database();
    $conn = $database->getConnection();

    $tables = [];
    $stmt = $conn->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    $output = "-- Exportación de base de datos\n";
    $output .= "-- Fecha: " . date('Y-m-d H:i:s') . "\n\n";

    $hospital_tables = ['pacientes', 'citas', 'inventario', 'encamamientos', 'camas', 'cobros', 'procedimientos_menores', 'examenes_realizados', 'cuenta_hospitalaria', 'ordenes_laboratorio', 'ventas', 'catalogo_pruebas', 'widget_settings'];

    foreach ($tables as $table) {
        $stmt = $conn->query("SHOW CREATE TABLE $table");
        $create = $stmt->fetch(PDO::FETCH_ASSOC);
        $output .= "\n-- --------------------------------------------------------\n";
        $output .= "-- Estructura de tabla `$table`\n";
        $output .= "-- --------------------------------------------------------\n";
        $output .= $create['Create Table'] . ";\n\n";

        if (in_array($table, $hospital_tables)) {
            $stmt = $conn->prepare("SELECT * FROM $table WHERE id_hospital = ?");
            $stmt->execute([$id_hospital]);
        } else {
            $stmt = $conn->query("SELECT * FROM $table");
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            $output .= "-- Volcado de datos para la tabla `$table`\n";
            foreach ($rows as $row) {
                $values = array_map(function ($v) use ($conn) {
                    return $conn->quote($v);
                }, array_values($row));
                $output .= "INSERT INTO `$table` VALUES (" . implode(',', $values) . ");\n";
            }
            $output .= "\n";
        }
    }

    // Descargar archivo
    $filename = "BD/Pruebas_" . date('Y-m-d_H-i-s') . ".sql";
    header("Content-Type: application/octet-stream");
    header("Content-Transfer-Encoding: Binary");
    header("Content-disposition: attachment; filename=\"$filename\"");
    echo $output;
    exit;

} catch (Exception $e) {
    error_log("export_database error: " . $e->getMessage());
    die("Error al exportar la base de datos.");
}
?>