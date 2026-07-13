<?php
ob_start();
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Sesión no iniciada']);
    exit;
}

require_once '../../../config/database.php';

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);
if ($id_hospital === 0) {
    ob_clean();
    echo json_encode(['error' => 'Hospital no identificado']);
    exit;
}

$periodo = $_GET['periodo'] ?? 'current_month';
$mes = $_GET['mes'] ?? date('Y-m');

ob_clean(); // descartar cualquier warning previo para no romper el JSON
try {
    $database = new Database();
    $conn = $database->getConnection();

    // Determinar rango de fechas según período
    if ($periodo === 'current_month') {
        $start = date('Y-m-01');
        $end = date('Y-m-t');
        $label = date('Y-m');
    } elseif ($periodo === 'previous_month') {
        $prev = date('Y-m-01', strtotime('first day of last month'));
        $start = $prev;
        $end = date('Y-m-t', strtotime('last day of last month'));
        $label = $prev;
    } elseif ($periodo === 'specific_month' && preg_match('/^\d{4}-\d{2}$/', $mes)) {
        $start = $mes . '-01';
        $end = date('Y-m-t', strtotime($mes . '-01'));
        $label = $mes;
    } else { // 'all'
        $start = '2000-01-01';
        $end = date('Y-m-d');
        $label = 'General';
    }

    $params = [$start, $end, $id_hospital];

    // 1. Total comprado
    $stmt = $conn->prepare("SELECT COUNT(*) as num, COALESCE(SUM(total_amount), 0) as total FROM purchase_headers WHERE purchase_date BETWEEN ? AND ? AND id_hospital = ?");
    $stmt->execute($params);
    $comprado = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Total pagado (en el rango)
    $stmt = $conn->prepare("SELECT COUNT(*) as num, COALESCE(SUM(amount), 0) as total FROM purchase_payments WHERE payment_date BETWEEN ? AND ? AND id_hospital = ?");
    $stmt->execute($params);
    $pagado = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3. Saldo pendiente (compras dentro del rango con saldo)
    $stmt = $conn->prepare("SELECT COUNT(*) as num, COALESCE(SUM(total_amount - COALESCE(paid_amount, 0)), 0) as saldo FROM purchase_headers WHERE purchase_date BETWEEN ? AND ? AND id_hospital = ? AND (total_amount - COALESCE(paid_amount, 0)) > 0");
    $stmt->execute($params);
    $pendiente = $stmt->fetch(PDO::FETCH_ASSOC);

    // 4. # Proveedores únicos en el rango
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT provider_name) as num FROM purchase_headers WHERE purchase_date BETWEEN ? AND ? AND id_hospital = ? AND provider_name IS NOT NULL AND provider_name != ''");
    $stmt->execute($params);
    $proveedores = $stmt->fetch(PDO::FETCH_ASSOC);

    // 5. Por proveedor
    $stmt = $conn->prepare("SELECT provider_name,
               COUNT(*) as num_compras,
               COALESCE(SUM(total_amount), 0) as total_comprado,
               COALESCE(SUM(paid_amount), 0) as total_pagado
            FROM purchase_headers
            WHERE purchase_date BETWEEN ? AND ? AND id_hospital = ?
              AND provider_name IS NOT NULL AND provider_name != ''
            GROUP BY provider_name
            ORDER BY total_comprado DESC");
    $stmt->execute($params);
    $por_proveedor = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // 6. Resumen por mes (últimos 12 meses) - Compras
    $stmt = $conn->prepare("SELECT DATE_FORMAT(purchase_date, '%Y-%m') as mes,
               COUNT(*) as num_compras,
               COALESCE(SUM(total_amount), 0) as total_comprado
            FROM purchase_headers
            WHERE purchase_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
              AND id_hospital = ?
            GROUP BY DATE_FORMAT(purchase_date, '%Y-%m')
            ORDER BY mes DESC");
    $stmt->execute([$id_hospital]);
    $compras_por_mes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. Pagos por mes
    $stmt = $conn->prepare("SELECT DATE_FORMAT(payment_date, '%Y-%m') as mes,
               COUNT(*) as num_pagos,
               COALESCE(SUM(amount), 0) as total_pagado
            FROM purchase_payments
            WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
              AND id_hospital = ?
            GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
            ORDER BY mes DESC");
    $stmt->execute([$id_hospital]);
    $pagos_por_mes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Combinar compras y pagos por mes
    $meses_map = [];
    foreach ($compras_por_mes as $c) {
        $meses_map[$c['mes']] = [
            'mes' => $c['mes'],
            'num_compras' => (int)$c['num_compras'],
            'total_comprado' => (float)$c['total_comprado'],
            'num_pagos' => 0,
            'total_pagado' => 0.0
        ];
    }
    foreach ($pagos_por_mes as $p) {
        if (!isset($meses_map[$p['mes']])) {
            $meses_map[$p['mes']] = [
                'mes' => $p['mes'],
                'num_compras' => 0,
                'total_comprado' => 0.0,
                'num_pagos' => 0,
                'total_pagado' => 0.0
            ];
        }
        $meses_map[$p['mes']]['num_pagos'] = (int)$p['num_pagos'];
        $meses_map[$p['mes']]['total_pagado'] = (float)$p['total_pagado'];
    }
    krsort($meses_map);
    $resumen_mensual = array_values($meses_map);

    echo json_encode([
        'success' => true,
        'periodo' => $periodo,
        'label' => $label,
        'start' => $start,
        'end' => $end,
        'kpis' => [
            'comprado' => [
                'num' => (int)$comprado['num'],
                'total' => (float)$comprado['total']
            ],
            'pagado' => [
                'num' => (int)$pagado['num'],
                'total' => (float)$pagado['total']
            ],
            'pendiente' => [
                'num' => (int)$pendiente['num'],
                'saldo' => (float)$pendiente['saldo']
            ],
            'proveedores' => (int)$proveedores['num']
        ],
        'por_proveedor' => array_map(function($p) {
            $pct = $p['total_comprado'] > 0 ? round(($p['total_pagado'] / $p['total_comprado']) * 100, 1) : 0;
            return [
                'proveedor' => $p['provider_name'],
                'num_compras' => (int)$p['num_compras'],
                'total_comprado' => (float)$p['total_comprado'],
                'total_pagado' => (float)$p['total_pagado'],
                'saldo' => (float)($p['total_comprado'] - $p['total_pagado']),
                'pct_pagado' => $pct
            ];
        }, $por_proveedor),
        'resumen_mensual' => $resumen_mensual
    ]);

} catch (Exception $e) {
    error_log('api_get_accounting.php error: ' . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Error al cargar contabilidad',
        'debug' => ($_SESSION['tipoUsuario'] ?? '') === 'admin' ? $e->getMessage() : null
    ]);
}
