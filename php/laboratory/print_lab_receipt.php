<?php
// laboratory/print_lab_receipt.php
// Receipt for a single lab exam cobro (row from examenes_realizados).
// Used by the dashboard Historial and Corte de Turno "Reimprimir" actions.
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID inválido");
}
$id = (int) $_GET['id'];
$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("
        SELECT e.id_examen_realizado, e.cobro, e.tipo_pago, e.tipo_examen,
               e.nombre_paciente, e.fecha_examen, e.id_paciente, e.usuario,
               COALESCE(NULLIF(CONCAT(u.nombre, ' ', u.apellido), ''), e.usuario) AS doctor_nombre
        FROM examenes_realizados e
        LEFT JOIN usuarios u ON (
            LOWER(CONCAT(u.nombre, ' ', u.apellido)) = LOWER(e.usuario)
            OR LOWER(u.nombre) = LOWER(SUBSTRING_INDEX(e.usuario, ' ', 1))
        )
        WHERE e.id_examen_realizado = ? AND e.id_hospital = ?
    ");
    $stmt->execute([$id, $id_hospital]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        die("Registro no encontrado");
    }

    // Try to get the patient's real name from pacientes if we have an id_paciente
    $paciente_nombre = $row['nombre_paciente'];
    if (!empty($row['id_paciente'])) {
        $stmtP = $conn->prepare("SELECT CONCAT(nombre, ' ', apellido) AS nombre FROM pacientes WHERE id_paciente = ?");
        $stmtP->execute([$row['id_paciente']]);
        $p = $stmtP->fetch(PDO::FETCH_ASSOC);
        if ($p && !empty($p['nombre'])) {
            $paciente_nombre = $p['nombre'];
        }
    }

    $fecha_full = $row['fecha_examen'];
    $fecha = new DateTime($fecha_full);
    $fecha_formateada = $fecha->format('d/m/Y');
    $hora_formateada = $fecha->format('H:i');
    $monto = (float) $row['cobro'];
    $tipo_pago = $row['tipo_pago'] ?: 'Efectivo';
    $tipo_examen = $row['tipo_examen'] ?: 'Examen de Laboratorio';
    $doctor = $row['doctor_nombre'] ? 'Dr(a). ' . $row['doctor_nombre'] : 'N/A';
    $user_name = $_SESSION['nombre'] ?? 'Sistema';

} catch (Exception $e) {
    error_log('Error en print_lab_receipt.php: ' . $e->getMessage());
    die('Error al generar el comprobante');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Laboratorio #<?php echo $id; ?></title>
    <link rel="icon" type="image/png" href="../../assets/img/cmhs.png">
    <link rel="stylesheet" href="../../assets/css/print_thermal.css">
    <style>
        @page { size: 80mm auto; margin: 0; }
        body { font-family: 'Courier New', monospace; max-width: 80mm; margin: 0 auto; padding: 8px; }
        .receipt-container { padding: 4px; }
        .receipt-header { text-align: center; border-bottom: 1px dashed #000; padding-bottom: 6px; margin-bottom: 8px; }
        .receipt-header h2 { font-size: 14px; margin: 0; }
        .receipt-header .subtitle { font-size: 11px; color: #555; }
        .receipt-section { margin: 6px 0; font-size: 12px; }
        .receipt-section .row { display: flex; justify-content: space-between; }
        .receipt-section .label { color: #555; }
        .receipt-section .value { font-weight: 700; text-align: right; }
        .divider { border-top: 1px dashed #000; margin: 8px 0; }
        .total-row { display: flex; justify-content: space-between; font-size: 14px; font-weight: 700; padding: 4px 0; }
        .payment-badge { display: inline-block; padding: 2px 6px; border: 1px solid #000; border-radius: 3px; font-size: 11px; }
        .footer-note { text-align: center; font-size: 10px; color: #666; margin-top: 8px; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="receipt-header">
            <h2>Centro Médico Herrera Saenz</h2>
            <div class="subtitle">Comprobante de Laboratorio</div>
        </div>

        <div class="receipt-section">
            <div class="row"><span class="label">Recibo #:</span><span class="value"><?php echo str_pad($id, 6, '0', STR_PAD_LEFT); ?></span></div>
            <div class="row"><span class="label">Fecha:</span><span class="value"><?php echo $fecha_formateada; ?></span></div>
            <div class="row"><span class="label">Hora:</span><span class="value"><?php echo $hora_formateada; ?></span></div>
        </div>

        <div class="divider"></div>

        <div class="receipt-section">
            <div class="row"><span class="label">Paciente:</span></div>
            <div style="font-size: 13px; font-weight: 700; margin-top: 2px;"><?php echo htmlspecialchars($paciente_nombre); ?></div>
        </div>

        <div class="receipt-section">
            <div class="row"><span class="label">Médico:</span><span class="value"><?php echo htmlspecialchars($doctor); ?></span></div>
            <div class="row"><span class="label">Examen:</span><span class="value"><?php echo htmlspecialchars($tipo_examen); ?></span></div>
        </div>

        <div class="divider"></div>

        <div class="receipt-section">
            <div class="row"><span class="label">Método de pago:</span><span class="value"><span class="payment-badge"><?php echo htmlspecialchars($tipo_pago); ?></span></span></div>
        </div>

        <div class="total-row">
            <span>TOTAL:</span>
            <span>Q<?php echo number_format($monto, 2); ?></span>
        </div>

        <div class="divider"></div>

        <div class="footer-note">
            Comprobante generado el <?php echo date('d/m/Y H:i'); ?><br>
            Atendido por: <?php echo htmlspecialchars($user_name); ?>
        </div>

        <div class="no-print" style="text-align: center; margin-top: 12px;">
            <button onclick="window.print()" style="padding: 8px 16px; background: #0d6efd; color: #fff; border: none; border-radius: 4px; cursor: pointer;">Imprimir</button>
            <button onclick="window.close()" style="padding: 8px 16px; background: #6c757d; color: #fff; border: none; border-radius: 4px; cursor: pointer; margin-left: 8px;">Cerrar</button>
        </div>
    </div>

    <script>
        // Auto-trigger print on load (gives the user a chance to cancel if needed)
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
                setTimeout(function() { window.close(); }, 500);
            }, 300);
        });
    </script>
</body>
</html>
