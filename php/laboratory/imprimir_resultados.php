<?php
// laboratory/imprimir_resultados.php - View and print validated results
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

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
        WHERE ol.id_orden = ? AND ol.estado = 'Completada'
    ");
    $stmt->execute([$id_orden]);
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

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Informe de Laboratorio - <?php echo $orden['numero_orden']; ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 40px;
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #7c90db;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .hospital-info h1 {
            margin: 0;
            color: #7c90db;
            font-size: 24px;
        }

        .hospital-info p {
            margin: 2px 0;
            font-size: 12px;
            color: #666;
        }

        .logo {
            height: 80px;
        }

        .patient-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .info-item {
            font-size: 13px;
        }

        .info-label {
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            font-size: 11px;
            display: block;
        }

        .test-section {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 30px;
            overflow: hidden;
        }

        .test-header {
            background: #7c90db;
            color: white;
            padding: 10px 15px;
            font-weight: 700;
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
        }

        .results-table th {
            background: #f1f5f9;
            text-align: left;
            padding: 10px 15px;
            font-size: 11px;
            text-transform: uppercase;
            color: #64748b;
        }

        .results-table td {
            padding: 10px 15px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
        }

        .flag-H {
            color: #ef4444;
            font-weight: 700;
        }

        .flag-L {
            color: #3b82f6;
            font-weight: 700;
        }

        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
        }

        .signatures {
            display: flex;
            justify-content: space-around;
            margin-top: 60px;
        }

        .signature-box {
            border-top: 1px solid #333;
            width: 220px;
            text-align: center;
            padding-top: 10px;
            font-size: 12px;
        }

        @media print {
            body {
                padding: 0;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.print()"
            style="background: #7c90db; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
            Imprimir Informe
        </button>
    </div>

    <header class="report-header">
        <div class="hospital-info">
            <h1>CENTRO MÉDICO HERRERA SAENZ</h1>
            <p>Laboratorio Clínico Especializado</p>
            <p>Amatitlán, Guatemala | Tel: 6633-XXXX</p>
        </div>
        <img src="../../assets/img/herrerasaenz.png" alt="Logo" class="logo">
    </header>

    <div class="patient-info">
        <div class="info-item">
            <span class="info-label">Paciente</span>
            <strong><?php echo htmlspecialchars($orden['nombre'] . ' ' . $orden['apellido']); ?></strong>
        </div>
        <div class="info-item">
            <span class="info-label">ID Paciente</span>
            <?php echo $orden['id_paciente']; ?>
        </div>
        <div class="info-item">
            <span class="info-label">Edad / Género</span>
            <?php echo $edad; ?> años / <?php echo $genero; ?>
        </div>
        <div class="info-item">
            <span class="info-label">Número de Orden</span>
            #<?php echo $orden['numero_orden']; ?>
        </div>
        <div class="info-item">
            <span class="info-label">Fecha de Orden</span>
            <?php echo date('d/m/Y H:i', strtotime($orden['fecha_orden'])); ?>
        </div>
        <div class="info-item">
            <span class="info-label">Médico Solicitante</span>
            Dr. <?php echo htmlspecialchars($orden['doctor_nombre'] . ' ' . $orden['doctor_apellido']); ?>
        </div>
    </div>

    <?php foreach ($pruebas as $prueba): ?>
        <section class="test-section">
            <div class="test-header"><?php echo htmlspecialchars($prueba['nombre_prueba']); ?></div>
            <table class="results-table">
                <thead>
                    <tr>
                        <th width="40%">Parámetro</th>
                        <th width="20%">Resultado</th>
                        <th width="15%">Unidades</th>
                        <th width="25%">Valores de Referencia</th>
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
                        // Range logic for display
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
                                <strong class="<?php echo $flag_class; ?>">
                                    <?php echo htmlspecialchars($res['valor_resultado']); ?>
                                </strong>
                                <?php if ($res['fuera_rango'] !== 'Normal'): ?>
                                    <span
                                        class="<?php echo $flag_class; ?>">(<?php echo substr($res['fuera_rango'], 0, 1); ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($res['unidad_medida']); ?></td>
                            <td><small><?php echo $ref_text; ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endforeach; ?>

    <div class="signatures">
        <div class="signature-box">Laboratorista Responsable</div>
        <div class="signature-box">Sello de Laboratorio</div>
    </div>

    <footer class="footer">
        Este documento es un informe de resultados de laboratorio clínico. <br>
        La interpretación de estos resultados debe ser realizada por un médico profesional. <br>
        Generado el <?php echo date('d/m/Y H:i:s'); ?>
    </footer>
</body>

</html>