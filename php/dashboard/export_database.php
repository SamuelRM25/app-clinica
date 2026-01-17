<?php
// Configuración de la base de datos
require_once '../../config/database.php';

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

    foreach ($tables as $table) {
        $stmt = $conn->query("SHOW CREATE TABLE $table");
        $create = $stmt->fetch(PDO::FETCH_ASSOC);
        $output .= "\n-- --------------------------------------------------------\n";
        $output .= "-- Estructura de tabla `$table`\n";
        $output .= "-- --------------------------------------------------------\n";
        $output .= $create['Create Table'] . ";\n\n";

        $stmt = $conn->query("SELECT * FROM $table");
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
    die("Error al exportar: " . $e->getMessage());
}
?>