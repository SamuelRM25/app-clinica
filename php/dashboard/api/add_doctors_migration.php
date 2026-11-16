<?php
// api/add_doctors_migration.php
// SECURITY: This script requires an active admin session
session_start();
require_once '../../../includes/functions.php';
require_once '../../../config/database.php';
require_once '../../../config/hospital.php';

header('Content-Type: text/plain');

verify_session();

// Only admins can run this migration
if ($_SESSION['tipoUsuario'] !== 'admin') {
    die("Acceso denegado.");
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("SELECT id_hospital FROM hospitales WHERE codigo_hospital = ?");
    $stmt->execute([CURRENT_HOSPITAL_CODE]);
    $hospital = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$hospital) {
        die("Hospital no encontrado para codigo: " . CURRENT_HOSPITAL_CODE);
    }
    $id_hospital = $hospital['id_hospital'];

    $doctors_to_add = [
        [
            'usuario' => 'dra.belen',
            'password' => password_hash('12345', PASSWORD_DEFAULT),
            'nombre' => 'Belén',
            'apellido' => 'López',
            'tipoUsuario' => 'doc',
            'especialidad' => 'Pediatra'
        ],
        [
            'usuario' => 'dra.yoana',
            'password' => password_hash('12345', PASSWORD_DEFAULT),
            'nombre' => 'Yoana Mabel',
            'apellido' => 'Gómez López',
            'tipoUsuario' => 'doc',
            'especialidad' => 'Médico General'
        ]
    ];

    foreach ($doctors_to_add as $doc) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM usuarios WHERE usuario = ? AND id_hospital = ?");
        $stmt->execute([$doc['usuario'], $id_hospital]);
        if ($stmt->fetchColumn() == 0) {
            $sql = "INSERT INTO usuarios (usuario, password, nombre, apellido, tipoUsuario, especialidad, clinica, telefono, email, id_hospital) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmtInsert = $conn->prepare($sql);
            $stmtInsert->execute([
                $doc['usuario'],
                $doc['password'],
                $doc['nombre'],
                $doc['apellido'],
                $doc['tipoUsuario'],
                $doc['especialidad'],
                'Centro Médico Herrera Saenz',
                '0000',
                $doc['usuario'] . '@logo.com',
                $id_hospital
            ]);
            echo "Doctor agregado: " . $doc['nombre'] . " " . $doc['apellido'] . "\n";
        } else {
            echo "Doctor ya existe: " . $doc['nombre'] . " " . $doc['apellido'] . "\n";
        }
    }
    echo "Proceso finalizado.";

} catch (Exception $e) {
    error_log("Migration error: " . $e->getMessage());
    echo "Error al ejecutar la migración.";
}
?>