<?php
// laboratory/imprimir_resultados.php - View and print validated results
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';
require_once '../../includes/module_guard.php';

verify_session();
$id_hospital = hospital_id();

$id_orden = $_GET['id'] ?? null;
if (!$id_orden) {
    die("ID de orden no proporcionado");
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // 1. Get order details with patient info
    $stmt = $conn->prepare("
        SELECT ol.*, p.nombre, p.apellido, p.genero, p.fecha_nacimiento,
               u.nombre as doctor_nombre, u.apellido as doctor_apellido
        FROM ordenes_laboratorio ol
        JOIN pacientes p ON ol.id_paciente = p.id_paciente
        LEFT JOIN usuarios u ON ol.id_doctor = u.idUsuario
        WHERE ol.id_orden = ? AND ol.estado = 'Completada' AND ol.id_hospital = ?
    ");
    $stmt->execute([$id_orden, $id_hospital]);
    $orden = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$orden) {
        die("La orden no existe o no ha sido validada.");
    }

    // 2. Get validated tests and results
    $stmt = $conn->prepare("
        SELECT op.*, cp.nombre_prueba, cp.codigo_prueba
        FROM orden_pruebas op
        JOIN catalogo_pruebas cp ON op.id_prueba = cp.id_prueba
        WHERE op.id_orden = ? AND op.estado = 'Validada'
    ");
    $stmt->execute([$id_orden]);
    $pruebas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $edad = date_diff(date_create($orden['fecha_nacimiento']), date_create('today'))->y;
    $genero = $orden['genero'];

    // 3. Obtener el archivo de resultados global de la orden
    $stmt_archivo = $conn->prepare("SELECT * FROM archivos_resultados_laboratorio WHERE id_orden = ? ORDER BY id_archivo DESC LIMIT 1");
    $stmt_archivo->execute([$id_orden]);
    $archivo_orden = $stmt_archivo->fetch(PDO::FETCH_ASSOC);
    $archivo_id = $archivo_orden['id_archivo'] ?? null;

} catch (Exception $e) {
    error_log('Error en laboratory/imprimir_resultados.php: ' . $e->getMessage());
    die("Error: " . 'Error del servidor.');
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informe de Laboratorio - <?php echo $orden['numero_orden']; ?></title>

    <!-- Google Fonts - Inter -->
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">

    <style>
        :root {
            --report-padding: 40px;
            --report-border-color: #e2e8f0;
        }

        body {
            background-color: #f1f5f9;
            padding: 2rem 0;
            color: #1e293b;
        }

        .report-page {
            background: white;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: var(--report-padding);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .report-header-premium {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid var(--color-primary);
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .hospital-brand h1 {
            color: var(--color-primary);
            font-size: 24px;
            font-weight: 800;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .hospital-brand p {
            margin: 2px 0;
            color: #64748b;
            font-size: 13px;
        }

        .report-title {
            text-align: right;
        }

        .report-title h2 {
            font-size: 20px;
            font-weight: 700;
            margin: 0;
            color: #334155;
        }

        .report-title p {
            margin: 2px 0;
            color: var(--color-primary);
            font-weight: 600;
            font-size: 14px;
        }

        .patient-data-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            border: 1px solid var(--report-border-color);
        }

        .data-item label {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            color: #94a3b8;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .data-item span {
            font-size: 14px;
            font-weight: 600;
            color: #334155;
        }

        .test-result-block {
            margin-bottom: 35px;
        }

        .test-name-header {
            background: #f1f5f9;
            padding: 10px 15px;
            border-radius: 8px;
            font-weight: 700;
            color: #1e293b;
            font-size: 15px;
            margin-bottom: 15px;
            border-left: 4px solid var(--color-primary);
            display: flex;
            justify-content: space-between;
        }

        .results-table-premium {
            width: 100%;
            border-collapse: collapse;
        }

        .results-table-premium th {
            text-align: left;
            padding: 10px 12px;
            font-size: 12px;
            color: #64748b;
            border-bottom: 1px solid var(--report-border-color);
            font-weight: 600;
        }

        .results-table-premium td {
            padding: 12px;
            font-size: 14px;
            border-bottom: 1px dotted var(--report-border-color);
        }

        .val-result {
            font-weight: 700;
        }

        .flag-H {
            color: #ef4444;
        }

        .flag-L {
            color: #3b82f6;
        }

        .signatures-area {
            margin-top: auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            padding-top: 50px;
            padding-bottom: 20px;
        }

        .sig-box {
            text-align: center;
            border-top: 1px solid #cbd5e1;
            padding-top: 10px;
            font-size: 12px;
            color: #64748b;
        }

        .report-footer {
            border-top: 1px solid var(--report-border-color);
            padding-top: 15px;
            margin-top: 20px;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .report-page {
                box-shadow: none;
                margin: 0;
                width: 100%;
                padding: 0;
            }

            .no-print {
                display: none;
            }
        }

        .floating-actions {
            position: fixed;
            bottom: 30px;
            right: 30px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 100;
        }
    </style>
</head>

<body>
    <div class="floating-actions no-print">
        <button onclick="window.print()" class="action-btn"
            style="height: 50px; width: 50px; border-radius: 50%; padding: 0;">
            <i class="bi bi-printer-fill" style="font-size: 1.2rem;"></i>
        </button>
        <button onclick="window.close()" class="action-btn secondary"
            style="height: 50px; width: 50px; border-radius: 50%; padding: 0;">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <div class="report-page">
        <header class="report-header-premium">
            <div class="hospital-brand">
                <h1>Centro Médico Herrera Saenz</h1>
                <p>Excelencia en Servicios de Salud</p>
                <p>Laboratorio Clínico Automatizado</p>
                <p><i class="bi bi-geo-alt"></i> Amatitlán, Guatemala | <i class="bi bi-telephone"></i> 6633-XXXX</p>
            </div>
            <div class="report-title">
                <img src="../../assets/img/cmhs.png" alt="logo" style="height: 60px; margin-bottom: 10px;" width="60"
                    height="60">
                <h2>INFORME DE RESULTADOS</h2>
                <p>Orden #<?php echo $orden['numero_orden']; ?></p>
            </div>
        </header>

        <div class="patient-data-grid">
            <div class="data-item">
                <label>Paciente</label>
                <span><?php echo htmlspecialchars($orden['nombre'] . ' ' . $orden['apellido']); ?></span>
            </div>
            <div class="data-item">
                <label>Edad / Género</label>
                <span><?php echo $edad; ?> años / <?php echo $genero; ?></span>
            </div>
            <div class="data-item">
                <label>Fecha de Emisión</label>
                <span><?php echo date('d/m/Y H:i'); ?></span>
            </div>
            <div class="data-item">
                <label>ID Paciente</label>
                <span><?php echo $orden['id_paciente']; ?></span>
            </div>
            <div class="data-item">
                <label>Médico Solicitante</label>
                <span>Dr.
                    <?php echo htmlspecialchars($orden['doctor_nombre'] . ' ' . $orden['doctor_apellido']); ?></span>
            </div>
            <div class="data-item">
                <label>Fecha de Toma</label>
                <span><?php echo date('d/m/Y', strtotime($orden['fecha_orden'])); ?></span>
            </div>
        </div>

        <?php foreach ($pruebas as $prueba): ?>
                <div class="test-result-block">
                    <div class="test-name-header">
                        <span><?php echo htmlspecialchars($prueba['nombre_prueba']); ?></span>
                        <span
                            style="font-size: 11px; opacity: 0.7;"><?php echo htmlspecialchars($prueba['codigo_prueba']); ?></span>
                    </div>

                    <table class="results-table-premium">
                        <thead>
                            <tr>
                                <th width="40%">PARÁMETRO</th>
                                <th width="20%">RESULTADO</th>
                                <th width="15%">UNIDADES</th>
                                <th width="25%">VALORES DE REFERENCIA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt_res = $conn->prepare("
                                SELECT rl.*, pp.nombre_parametro, pp.unidad_medida, 
                                       pp.valor_ref_hombre_min, pp.valor_ref_hombre_max,
                                       pp.valor_ref_mujer_min, pp.valor_ref_mujer_max,
                                       pp.valor_ref_pediatrico_min, pp.valor_ref_pediatrico_max
                                FROM resultados_laboratorio rl
                                JOIN parametros_pruebas pp ON rl.id_parametro = pp.id_parametro
                                WHERE rl.id_orden_prueba = ?
                                ORDER BY pp.orden_visualizacion
                            ");
                            $stmt_res->execute([$prueba['id_orden_prueba']]);
                            $resultados = $stmt_res->fetchAll(PDO::FETCH_ASSOC);

                            foreach ($resultados as $res):
                                $min = 0;
                                $max = 0;
                                if ($edad <= 12) {
                                    $min = $res['valor_ref_pediatrico_min'];
                                    $max = $res['valor_ref_pediatrico_max'];
                                } elseif ($genero === 'Masculino') {
                                    $min = $res['valor_ref_hombre_min'];
                                    $max = $res['valor_ref_hombre_max'];
                                } else {
                                    $min = $res['valor_ref_mujer_min'];
                                    $max = $res['valor_ref_mujer_max'];
                                }
                                $ref_text = ($min !== null && $max !== null) ? "$min - $max" : "N/A";
                                $flag_class = '';
                                if ($res['fuera_rango'] === 'Alto')
                                    $flag_class = 'flag-H';
                                elseif ($res['fuera_rango'] === 'Bajo')
                                    $flag_class = 'flag-L';
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($res['nombre_parametro']); ?></td>
                                        <td>
                                            <span class="val-result <?php echo $flag_class; ?>">
                                                <?php echo htmlspecialchars($res['valor_resultado']); ?>
                                            </span>
                                            <?php if ($res['fuera_rango'] !== 'Normal'): ?>
                                                    <small class="<?php echo $flag_class; ?>" style="margin-left: 4px;">
                                                        (<?php echo substr($res['fuera_rango'], 0, 1); ?>)
                                                    </small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($res['unidad_medida']); ?></td>
                                        <td><span style="font-size: 12px; color: #64748b;"><?php echo $ref_text; ?></span></td>
                                    </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
        <?php endforeach; ?>

        <?php if ($archivo_orden && $archivo_id): ?>
                <div class="no-print" style="margin-top: 20px; padding: 15px; background: #f1f5f9; border-radius: 8px;">
                    <p style="margin: 0 0 10px 0; font-size: 13px; font-weight: 600;">
                        <i class="bi bi-paperclip"></i> Archivo adjunto (PDF)
                    </p>
                    <a href="api/ver_archivo.php?id=<?php echo $archivo_id; ?>" download="<?php echo htmlspecialchars($orden['numero_orden']); ?>.pdf" class="action-btn" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none;">
                        <i class="bi bi-download"></i> Descargar PDF
                    </a>
                </div>
        <?php endif; ?>

        <div class="signatures-area">
            <div class="sig-box">
                <div style="height: 60px;"></div>
                Firma y Sello Laboratorista
            </div>
            <div class="sig-box">
                <div style="height: 60px;"></div>
                Sello de Validación
            </div>
        </div>

        <footer class="report-footer">
            <p>La interpretación de estos resultados debe ser realizada exclusivamente por un médico colegiado activo.
            </p>
            <p><strong>Centro Médico Herrera Saenz</strong> - Tecnología al servicio de su salud.</p>
        </footer>
    </div>
</body>

</html>