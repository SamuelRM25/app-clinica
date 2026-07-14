<?php
// surgery/api/save_sala.php - CRUD de salas quirúrgicas
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['tipoUsuario'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $id_sala = $_POST['id_sala'] ?? '';
    $codigo = substr(trim($_POST['codigo'] ?? ''), 0, 20);
    $nombre = substr(trim($_POST['nombre'] ?? ''), 0, 100);
    $tipo = substr(trim($_POST['tipo'] ?? ''), 0, 50);
    $tarifa_base = (float) ($_POST['tarifa_base'] ?? 0);
    $estado = $_POST['estado'] ?? 'Disponible';

    if (empty($codigo) || empty($nombre)) {
        echo json_encode(['success' => false, 'message' => 'Código y nombre son obligatorios']);
        exit;
    }

    if (!in_array($estado, ['Disponible', 'Ocupada', 'Mantenimiento'], true)) {
        $estado = 'Disponible';
    }

    $id_hospital = $_SESSION['id_hospital'] ?? 0;

    $conn->beginTransaction();

    if (empty($id_sala)) {
        $stmt_check = $conn->prepare("SELECT id_sala FROM salas_quirurgicas WHERE codigo = ? AND id_hospital = ?");
        $stmt_check->execute([$codigo, $id_hospital]);
        if ($stmt_check->fetch()) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Ya existe una sala con el código ' . $codigo]);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO salas_quirurgicas (codigo, nombre, tipo, tarifa_base, estado, id_hospital) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$codigo, $nombre, $tipo ?: null, $tarifa_base, $estado, $id_hospital]);
        $newId = (int)$conn->lastInsertId();
        $conn->commit();

        audit_log('create', 'surgery', "Sala quirúrgica creada: $nombre ($codigo)", [
            'table_name' => 'salas_quirurgicas',
            'record_id' => $newId,
            'new_data' => [
                'codigo' => $codigo, 'nombre' => $nombre, 'tipo' => $tipo,
                'tarifa_base' => $tarifa_base, 'estado' => $estado,
            ]
        ]);

        echo json_encode(['success' => true, 'message' => 'Sala creada correctamente', 'id_sala' => $newId]);
    } else {
        $id_sala_int = (int)$id_sala;

        $fetchStmt = $conn->prepare("SELECT codigo, nombre, tipo, tarifa_base, estado FROM salas_quirurgicas WHERE id_sala = ? AND id_hospital = ?");
        $fetchStmt->execute([$id_sala_int, $id_hospital]);
        $oldData = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("UPDATE salas_quirurgicas SET codigo = ?, nombre = ?, tipo = ?, tarifa_base = ?, estado = ? WHERE id_sala = ? AND id_hospital = ?");
        $stmt->execute([$codigo, $nombre, $tipo ?: null, $tarifa_base, $estado, $id_sala_int, $id_hospital]);
        $conn->commit();

        audit_log('update', 'surgery', "Sala quirúrgica actualizada: $nombre", [
            'table_name' => 'salas_quirurgicas',
            'record_id' => $id_sala_int,
            'old_data' => $oldData,
            'new_data' => [
                'codigo' => $codigo, 'nombre' => $nombre, 'tipo' => $tipo,
                'tarifa_base' => $tarifa_base, 'estado' => $estado,
            ]
        ]);

        echo json_encode(['success' => true, 'message' => 'Sala actualizada correctamente']);
    }
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    error_log('save_sala error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar la sala.',
        'debug' => ($_SESSION['tipoUsuario'] ?? '') === 'admin' ? $e->getMessage() : null,
    ]);
}