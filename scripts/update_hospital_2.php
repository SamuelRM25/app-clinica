<?php
/**
 * Script de actualización para Hospital ID = 2
 * Centro Médico Herrera Saenz
 * 
 * Ejecutar: php scripts/update_hospital_2.php
 * O desde el navegador: http://localhost/GitHub/app-clinica/scripts/update_hospital_2.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/multitenant.php';

$id_hospital = 2;

try {
    $database = new Database();
    $conn = $database->getConnection();
    $conn->beginTransaction();

    // =====================================================
    // 1. AGREGAR NUEVOS MÉDICOS
    // =====================================================
    $medicos = [
        ['mherrera', 'Manfri', 'Herrera', 'doc', 'Medicina General'],
        ['klucas', 'Kevin', 'Lucas', 'doc', 'Medicina General'],
        ['jgutierrez', 'Jeffrey', 'Gutiérrez', 'doc', 'Medicina General'],
        ['bleon', 'Brisly', 'de Leon', 'doc', 'Medicina General'],
    ];

    $stmt = $conn->prepare("INSERT IGNORE INTO usuarios (usuario, nombre, apellido, password, tipoUsuario, especialidad, id_hospital) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($medicos as $m) {
        $password = password_hash('123456', PASSWORD_DEFAULT);
        $stmt->execute([$m[0], $m[1], $m[2], $password, $m[3], $m[4], $id_hospital]);
        echo "Médico agregado: {$m[1]} {$m[2]}" . PHP_EOL;
    }

    // =====================================================
    // 2. ELIMINAR DOCTORES
    // =====================================================
    $eliminar = [
        ['Cristian', 'Mendoza'],
        ['Angie', 'Sarmiento'],
        ['Estuardo', 'Rivas'],
        ['Libny', 'Recinos'],
        ['Osber', 'Rivas'],
        ['Yoana', 'Gomez'],
    ];

    $stmt = $conn->prepare("DELETE FROM usuarios WHERE nombre = ? AND apellido = ? AND id_hospital = ?");
    foreach ($eliminar as $d) {
        $stmt->execute([$d[0], $d[1], $id_hospital]);
        if ($stmt->rowCount() > 0) {
            echo "Doctor eliminado: {$d[0]} {$d[1]}" . PHP_EOL;
        } else {
            echo "Doctor NO encontrado: {$d[0]} {$d[1]}" . PHP_EOL;
        }
    }

    // =====================================================
    // 3. AGREGAR USUARIAS DE ENFERMERÍA
    // =====================================================
    $enfermeria = [
        ['marisol', 'Marisol', 'Enfermera'],
        ['melisa', 'Melisa', 'Enfermera'],
        ['kenia', 'Kenia', 'Enfermera'],
        ['heidy', 'Heidy', 'Enfermera'],
    ];

    $stmt = $conn->prepare("INSERT IGNORE INTO usuarios (usuario, nombre, apellido, password, tipoUsuario, especialidad, id_hospital) VALUES (?, ?, ?, ?, 'user', 'enfermeria', ?)");
    foreach ($enfermeria as $e) {
        $password = password_hash('123456', PASSWORD_DEFAULT);
        $stmt->execute([$e[0], $e[1], $e[2], $password, $id_hospital]);
        echo "Enfermera agregada: {$e[1]} {$e[2]}" . PHP_EOL;
    }

    // =====================================================
    // 4. LISTAR USUARIOS PARA IDENTIFICAR ROLES
    // =====================================================
    echo PHP_EOL . "=== Usuarios actuales del hospital $id_hospital ===" . PHP_EOL;
    $stmt = $conn->prepare("SELECT idUsuario, usuario, nombre, apellido, tipoUsuario, especialidad FROM usuarios WHERE id_hospital = ? ORDER BY tipoUsuario, nombre");
    $stmt->execute([$id_hospital]);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($usuarios as $u) {
        echo "ID:{$u['idUsuario']} | {$u['usuario']} | {$u['nombre']} {$u['apellido']} | {$u['tipoUsuario']} | {$u['especialidad']}" . PHP_EOL;
    }
    
    echo PHP_EOL . "=== INSTRUCCIONES ===" . PHP_EOL;
    echo "Para asignar rol de farmacia a un usuario, ejecuta:" . PHP_EOL;
    echo "  UPDATE usuarios SET especialidad = 'farmacia' WHERE idUsuario = X AND id_hospital = 2;" . PHP_EOL;
    echo "Para asignar rol de recepcion a un usuario, ejecuta:" . PHP_EOL;
    echo "  UPDATE usuarios SET especialidad = 'recepcion' WHERE idUsuario = X AND id_hospital = 2;" . PHP_EOL;
    echo "Para cambiar contraseñas, usa la interfaz de configuración." . PHP_EOL;

    $conn->commit();
    echo PHP_EOL . "✅ Actualización completada exitosamente." . PHP_EOL;

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "❌ Error: " . $e->getMessage() . PHP_EOL;
}
