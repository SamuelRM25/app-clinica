<?php
// api/add_doctors_migration.php
require_once '../../config/database.php';

header('Content-Type: text/plain');

try {
    $database = new Database();
    $conn = $database->getConnection();

    $doctors_to_add = [
        [
            'usuario' => 'dra.belen',
            'password' => '12345', // Default password, should be changed
            'nombre' => 'Belén',
            'apellido' => 'López',
            'tipoUsuario' => 'doc',
            'especialidad' => 'Pediatra'
        ],
        [
            'usuario' => 'dra.yoana',
            'password' => '12345', // Default password
            'nombre' => 'Yoana Mabel',
            'apellido' => 'Gómez López',
            'tipoUsuario' => 'doc',
            'especialidad' => 'Médico General'
        ]
    ];

    foreach ($doctors_to_add as $doc) {
        // Check if exists
        $stmt = $conn->prepare("SELECT COUNT(*) FROM usuarios WHERE usuario = ?");
        $stmt->execute([$doc['usuario']]);
        if ($stmt->fetchColumn() == 0) {
            $sql = "INSERT INTO usuarios (usuario, password, nombre, apellido, tipoUsuario, especialidad) VALUES (?, ?, ?, ?, ?, ?)";
            $stmtInsert = $conn->prepare($sql);
            $stmtInsert->execute([
                $doc['usuario'],
                $doc['password'],
                $doc['nombre'],
                $doc['apellido'],
                $doc['tipoUsuario'],
                $doc['especialidad']
            ]);
            echo "Doctor agregado: " . $doc['nombre'] . " " . $doc['apellido'] . "\n";
        } else {
            echo "Doctor ya existe: " . $doc['nombre'] . " " . $doc['apellido'] . "\n";
        }
    }
    echo "Proceso finalizado.";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>