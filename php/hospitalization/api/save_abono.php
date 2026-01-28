<?php
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

date_default_timezone_set('America/Guatemala');
verify_session();

header('Content-Type: application/json');

$id_encamamiento = $_POST['id_encamamiento'] ?? 0;
$monto = $_POST['monto'] ?? 0;
$metodo_pago = $_POST['metodo_pago'] ?? 'Efectivo';
$notas = $_POST['notas'] ?? '';
$user_id = $_SESSION['user_id'];

if (!$id_encamamiento || $monto <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Datos inválidos']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Ensure table exists
    $conn->exec("CREATE TABLE IF NOT EXISTS abonos_hospitalarios (
        id_abono INT AUTO_INCREMENT PRIMARY KEY,
        id_cuenta INT NOT NULL,
        monto DECIMAL(10,2) NOT NULL,
        fecha_abono DATETIME DEFAULT CURRENT_TIMESTAMP,
        metodo_pago VARCHAR(50) DEFAULT 'Efectivo',
        notas TEXT,
        registrado_por INT,
        FOREIGN KEY (registrado_por) REFERENCES usuarios(idUsuario)
    )");

    // Ensure columns in cuenta_hospitalaria
    try {
        $conn->exec("ALTER TABLE cuenta_hospitalaria ADD COLUMN total_pagado DECIMAL(10,2) DEFAULT 0");
    } catch (Exception $e) { /* Column might exist */
    }

    // Get cuenta ID
    $stmt = $conn->prepare("SELECT id_cuenta FROM cuenta_hospitalaria WHERE id_encamamiento = ?");
    $stmt->execute([$id_encamamiento]);
    $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cuenta)
        throw new Exception("Cuenta no encontrada");
    $id_cuenta = $cuenta['id_cuenta'];

    // Insert Abono
    $stmtIns = $conn->prepare("
        INSERT INTO abonos_hospitalarios (id_cuenta, monto, metodo_pago, notas, registrado_por, fecha_abono)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmtIns->execute([$id_cuenta, $monto, $metodo_pago, $notas, $user_id]);
    $id_abono = $conn->lastInsertId();

    // Update Totals
    $stmtUpd = $conn->prepare("
        UPDATE cuenta_hospitalaria 
        SET total_pagado = (SELECT COALESCE(SUM(monto),0) FROM abonos_hospitalarios WHERE id_cuenta = ?),
            monto_pagado = (SELECT COALESCE(SUM(monto),0) FROM abonos_hospitalarios WHERE id_cuenta = ?)
        WHERE id_cuenta = ?
    ");
    $stmtUpd->execute([$id_cuenta, $id_cuenta, $id_cuenta]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Abono registrado correctamente',
        'id_abono' => $id_abono
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
