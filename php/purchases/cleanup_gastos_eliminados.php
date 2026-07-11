<?php
// cleanup_gastos_eliminados.php
// Elimina definitivamente los gastos eliminados del mes anterior o anterior.
// Ejecutar como cron job al inicio de cada mes:
//   0 0 1 * * /usr/bin/php /ruta/a/cleanup_gastos_eliminados.php

session_start();
require_once '../../config/database.php';

$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    // Web access — require auth
    require_once '../../includes/functions.php';
    require_once '../../includes/multitenant.php';

    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Delete records from previous months (before the 1st of current month)
    $corte = date('Y-m-01 00:00:00');

    $stmt = $conn->prepare("DELETE FROM gastos_eliminados WHERE fecha_eliminacion < ?");
    $stmt->execute([$corte]);
    $count = $stmt->rowCount();

    if ($is_cli) {
        echo "[" . date('Y-m-d H:i:s') . "] Cleanup: $count registros eliminados definitivamente de gastos_eliminados.\n";
    } else {
        echo json_encode([
            'success' => true,
            'message' => "Se eliminaron $count registros anteriores a $corte",
            'eliminados' => $count,
            'corte' => $corte,
        ]);
    }

} catch (Exception $e) {
    $msg = 'Error en cleanup_gastos_eliminados: ' . $e->getMessage();
    error_log($msg);

    if ($is_cli) {
        echo "[" . date('Y-m-d H:i:s') . "] ERROR: $msg\n";
        exit(1);
    } else {
        echo json_encode(['success' => false, 'message' => $msg]);
    }
}
