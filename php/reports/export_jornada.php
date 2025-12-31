<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

date_default_timezone_set('America/Guatemala');
verify_session();

// Only admin can generate this report
$rol = $_SESSION['tipoUsuario'] ?? $_SESSION['rol'] ?? '';
if ($rol !== 'admin') {
    die("Acceso denegado.");
}

$date = $_GET['date'] ?? date('Y-m-d');
$format = $_GET['format'] ?? 'html'; // html, csv, excel, word

$start_time = $date . ' 08:00:00';
$end_time = date('Y-m-d', strtotime($date . ' +1 day')) . ' 07:59:59';

try {
    $database = new Database();
    $conn = $database->getConnection();

    // 1. Total Patients seen
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT historial_id) FROM citas WHERE fecha_cita BETWEEN ? AND ?");
    $stmt->execute([$start_time, $end_time]);
    $total_patients = $stmt->fetchColumn() ?: 0;

    // 2. Minor Procedures
    $stmt = $conn->prepare("SELECT SUM(cobro) FROM procedimientos_menores WHERE fecha_procedimiento BETWEEN ? AND ?");
    $stmt->execute([$start_time, $end_time]);
    $total_procedures = $stmt->fetchColumn() ?: 0;

    // 3. Exams
    $stmt = $conn->prepare("SELECT SUM(cobro) FROM examenes_realizados WHERE fecha_examen BETWEEN ? AND ?");
    $stmt->execute([$start_time, $end_time]);
    $total_exams = $stmt->fetchColumn() ?: 0;

    // 4. Purchases (Medications)
    $stmt = $conn->prepare("SELECT SUM(total_amount) FROM purchase_headers WHERE purchase_date BETWEEN ? AND ?");
    $stmt->execute([$date, date('Y-m-d', strtotime($date . ' +1 day'))]);
    $total_purchases = $stmt->fetchColumn() ?: 0;

    // 5. Sales (Medications)
    $stmt = $conn->prepare("SELECT SUM(total) FROM ventas WHERE fecha_venta BETWEEN ? AND ?");
    $stmt->execute([$start_time, $end_time]);
    $total_sales = $stmt->fetchColumn() ?: 0;

    // 6. Billings (Cobros)
    // fecha_consulta is DATE type, so we query by the specific date instead of time range
    $stmt = $conn->prepare("SELECT SUM(cantidad_consulta) FROM cobros WHERE fecha_consulta = ?");
    $stmt->execute([$date]);
    $total_billings = $stmt->fetchColumn() ?: 0;

    $total_revenue = $total_sales + $total_procedures + $total_exams + $total_billings;

    // Prepare WhatsApp Message
    $wa_text = "*REPORTE DE JORNADA*\n";
    $wa_text .= "*Fecha:* " . date('d/m/Y', strtotime($date)) . "\n";
    $wa_text .= "--------------------------\n";
    $wa_text .= "*Pacientes:* " . $total_patients . "\n";
    $wa_text .= "*Ventas Meds:* Q" . number_format($total_sales, 2) . "\n";
    $wa_text .= "*Cobros Inf:* Q" . number_format($total_billings, 2) . "\n";
    $wa_text .= "*Proc. Menores:* Q" . number_format($total_procedures, 2) . "\n";
    $wa_text .= "*Exámenes:* Q" . number_format($total_exams, 2) . "\n";
    $wa_text .= "--------------------------\n";
    $wa_text .= "*TOTAL INGRESOS:* Q" . number_format($total_revenue, 2) . "\n";
    $wa_text .= "*TOTAL COMPRAS:* Q" . number_format($total_purchases, 2) . "\n";
    $wa_url = "https://wa.me/50239029076?text=" . urlencode($wa_text);

    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="reporte_jornada_' . $date . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Concepto', 'Monto / Cantidad']);
        fputcsv($output, ['Fecha', $date]);
        fputcsv($output, ['Pacientes Atendidos', $total_patients]);
        fputcsv($output, ['Ventas Medicamentos', number_format($total_sales, 2)]);
        fputcsv($output, ['Cobros Realizados', number_format($total_billings, 2)]);
        fputcsv($output, ['Procedimientos Menores', number_format($total_procedures, 2)]);
        fputcsv($output, ['Exámenes Médicos', number_format($total_exams, 2)]);
        fputcsv($output, ['Total Compras', number_format($total_purchases, 2)]);
        fputcsv($output, ['Total Ingresos', number_format($total_revenue, 2)]);
        fclose($output);
        exit;
    }

    if ($format === 'excel' || $format === 'word') {
        $ext = ($format === 'excel' ? ".xls" : ".doc");
        header("Content-Type: application/vnd.ms-" . ($format === 'excel' ? "excel" : "word"));
        header("Content-Disposition: attachment; filename=\"reporte_jornada_$date$ext\"");
        echo "
        <table border='1'>
            <tr><th colspan='2'><h1>Reporte de Jornada</h1></th></tr>
            <tr><td><b>Fecha:</b></td><td>$date</td></tr>
            <tr><td><b>Pacientes Atendidos:</b></td><td>$total_patients</td></tr>
            <tr><td><b>Ventas Medicamentos:</b></td><td>Q".number_format($total_sales, 2)."</td></tr>
            <tr><td><b>Cobros Realizados:</b></td><td>Q".number_format($total_billings, 2)."</td></tr>
            <tr><td><b>Procedimientos Menores:</b></td><td>Q".number_format($total_procedures, 2)."</td></tr>
            <tr><td><b>Exámenes Médicos:</b></td><td>Q".number_format($total_exams, 2)."</td></tr>
            <tr><td><b>Total Ingresos:</b></td><td><b>Q".number_format($total_revenue, 2)."</b></td></tr>
            <tr><td><b>Total Compras:</b></td><td>Q".number_format($total_purchases, 2)."</td></tr>
            <tr><td><b>Desempeño Neto:</b></td><td><b>Q".number_format($total_revenue - $total_purchases, 2)."</b></td></tr>
        </table>";
        exit;
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Jornada - <?php echo $date; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7f6; font-family: 'Segoe UI', sans-serif; }
        .report-paper { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); max-width: 800px; margin: 40px auto; }
        .stat-line { border-bottom: 1px solid #eee; padding: 12px 0; display: flex; justify-content: space-between; align-items: center; }
        .stat-label { color: #666; font-weight: 500; }
        .stat-value { font-weight: 700; color: #333; }
        @media print { .no-print { display: none !important; } .report-paper { box-shadow: none; margin: 0 auto; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="report-paper">
            <div class="d-flex justify-content-between align-items-Start mb-4">
                <div>
                    <h2 class="fw-bold text-primary mb-1">Reporte Diario de Jornada</h2>
                    <p class="text-muted">Período: <?php echo date('d/m/Y 08:00 AM', strtotime($start_time)); ?> - <?php echo date('d/m/Y 08:00 AM', strtotime($end_time)); ?></p>
                </div>
                <div class="no-print d-flex gap-2">
                    <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="bi bi-printer"></i> Imprimir</button>
                    <a href="<?php echo $wa_url; ?>" target="_blank" class="btn btn-success btn-sm">WhatsApp</a>
                </div>
            </div>

            <div class="mt-4">
                <div class="stat-line">
                    <span class="stat-label">Total Pacientes Atendidos</span>
                    <span class="stat-value"><?php echo $total_patients; ?></span>
                </div>
                <div class="stat-line">
                    <span class="stat-label">Ventas de Medicamentos</span>
                    <span class="stat-value text-primary">Q<?php echo number_format($total_sales, 2); ?></span>
                </div>
                <div class="stat-line">
                    <span class="stat-label">Cobros Realizados</span>
                    <span class="stat-value text-primary">Q<?php echo number_format($total_billings, 2); ?></span>
                </div>
                <div class="stat-line">
                    <span class="stat-label">Procedimientos Menores</span>
                    <span class="stat-value text-primary">Q<?php echo number_format($total_procedures, 2); ?></span>
                </div>
                <div class="stat-line">
                    <span class="stat-label">Exámenes Médicos</span>
                    <span class="stat-value text-primary">Q<?php echo number_format($total_exams, 2); ?></span>
                </div>
                <div class="stat-line bg-light p-3 rounded-3 mt-3">
                    <span class="stat-label text-dark fw-bold">TOTAL INGRESOS BRUTOS</span>
                    <span class="stat-value text-primary fs-4">Q<?php echo number_format($total_revenue, 2); ?></span>
                </div>
                
                <div class="stat-line mt-4">
                    <span class="stat-label">Total Compras (Egresos)</span>
                    <span class="stat-value text-danger">Q<?php echo number_format($total_purchases, 2); ?></span>
                </div>

                <div class="stat-line bg-dark p-3 rounded-3 mt-3">
                    <span class="stat-label text-white fw-bold">DESEMPEÑO NETO</span>
                    <span class="stat-value text-white fs-4">Q<?php echo number_format($total_revenue - $total_purchases, 2); ?></span>
                </div>
            </div>

            <div class="row mt-5 pt-5 text-center">
                <div class="col-6">
                    <div class="border-top pt-2 mx-auto" style="width: 150px;">Firma Administrador</div>
                </div>
                <div class="col-6">
                    <div class="border-top pt-2 mx-auto" style="width: 150px;">Firma Responsable</div>
                </div>
            </div>
            
            <div class="text-center mt-5 text-muted small">
                Generado automáticamente por CM Siloé Management System - <?php echo date('d/m/Y H:i'); ?>
            </div>
        </div>
    </div>
</body>
</html>
