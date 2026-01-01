
<?php
/**
 * LIMS Data Import Script
 * Import laboratory test catalog from base_datos_lims.csv
 * 
 * This script imports 76 laboratory tests with their parameters and reference values
 * Run this AFTER running the migration SQL
 */

session_start();
// Incluir configuraciones y funciones
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Set timezone
date_default_timezone_set('America/Guatemala');

// Only allow admin access
if (!isset($_SESSION['tipoUsuario']) || $_SESSION['tipoUsuario'] !== 'admin') {
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized access']));
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Path to CSV file
    $csv_file = '../../base_datos_lims.csv';
    
    if (!file_exists($csv_file)) {
        throw new Exception("CSV file not found: $csv_file");
    }
    
    // Open CSV file
    if (($handle = fopen($csv_file, 'r')) === FALSE) {
        throw new Exception("Cannot open CSV file");
    }
    
    // Skip header row
    $header = fgetcsv($handle);
    
    $imported_tests = 0;
    $imported_params = 0;
    $errors = [];
    
    // Start transaction
    $conn->beginTransaction();
    
    while (($row = fgetcsv($handle)) !== FALSE) {
        // Skip empty rows
        if (empty($row[0])) continue;
        
        try {
            // Parse CSV columns
            $nombre_prueba = trim($row[0]);
            $forma_resultado = trim($row[1]); // This is the parameter name
            $codigo = trim($row[2]);
            $muestra = trim($row[3]);
            $metodo_toma = trim($row[4]);
            $unidad = trim($row[5]);
            $ref_hombre = trim($row[6]);
            $ref_mujer = trim($row[7]);
            $ref_pediatrico = trim($row[8]);
            
            // Categorize tests
            $categoria = categorize_test($nombre_prueba);
            
            // Check if test already exists
            $stmt_check = $conn->prepare("SELECT id_prueba FROM catalogo_pruebas WHERE codigo_prueba = ?");
            $stmt_check->execute([$codigo]);
            $existing_test = $stmt_check->fetch(PDO::FETCH_ASSOC);
            
            $id_prueba = null;
            
            if ($existing_test) {
                $id_prueba = $existing_test['id_prueba'];
            } else {
                // Determine price based on category
                $precio = determine_price($categoria);
                
                // Determine if requires fasting
                $requiere_ayuno = (stripos($metodo_toma, 'ayuno') !== false);
                $horas_ayuno = null;
                if ($requiere_ayuno) {
                    if (preg_match('/(\d+)\s*h/', $metodo_toma, $matches)) {
                        $horas_ayuno = intval($matches[1]);
                    }
                }
                
                // Insert test
                $stmt_test = $conn->prepare("
                    INSERT INTO catalogo_pruebas 
                    (codigo_prueba, nombre_prueba, abreviatura, muestra_requerida, 
                     metodo_toma, precio, requiere_ayuno, horas_ayuno, categoria, estado)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Activo')
                ");
                
                $stmt_test->execute([
                    $codigo,
                    $nombre_prueba,
                    $codigo,
                    $muestra,
                    $metodo_toma,
                    $precio,
                    $requiere_ayuno,
                    $horas_ayuno,
                    $categoria
                ]);
                
                $id_prueba = $conn->lastInsertId();
                $imported_tests++;
            }
            
            // Parse reference values
            $ref_values = parse_reference_values($ref_hombre, $ref_mujer, $ref_pediatrico, $unidad);
            
            // Determine data type
            $tipo_dato = determine_data_type($ref_values);
            
            // Insert parameter
            $stmt_param = $conn->prepare("
                INSERT INTO parametros_pruebas 
                (id_prueba, nombre_parametro, unidad_medida, 
                 valor_ref_hombre_min, valor_ref_hombre_max,
                 valor_ref_mujer_min, valor_ref_mujer_max,
                 valor_ref_pediatrico_min, valor_ref_pediatrico_max,
                 tipo_dato, valores_normales, orden_visualizacion)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt_param->execute([
                $id_prueba,
                $forma_resultado,
                $unidad,
                $ref_values['hombre_min'],
                $ref_values['hombre_max'],
                $ref_values['mujer_min'],
                $ref_values['mujer_max'],
                $ref_values['pediatrico_min'],
                $ref_values['pediatrico_max'],
                $tipo_dato,
                $ref_values['texto_completo'],
                1
            ]);
            
            $imported_params++;
            
        } catch (Exception $e) {
            $errors[] = "Row error: " . $e->getMessage() . " | Data: " . implode(', ', $row);
        }
    }
    
    fclose($handle);
    
    // Commit transaction
    $conn->commit();
    
    // Return success
    echo json_encode([
        'status' => 'success',
        'message' => 'LIMS data imported successfully',
        'data' => [
            'tests_imported' => $imported_tests,
            'parameters_imported' => $imported_params,
            'errors' => $errors,
            'errors_count' => count($errors)
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'errors' => isset($errors) ? $errors : []
    ]);
}

// =====================================================
// HELPER FUNCTIONS
// =====================================================

function categorize_test($nombre) {
    $nombre_lower = strtolower($nombre);
    
    if (stripos($nombre_lower, 'hemograma') !== false || 
        stripos($nombre_lower, 'eritrocito') !== false ||
        stripos($nombre_lower, 'leucocito') !== false ||
        stripos($nombre_lower, 'plaqueta') !== false ||
        stripos($nombre_lower, 'hemoglobina') !== false ||
        stripos($nombre_lower, 'hematocrito') !== false) {
        return 'Hematología';
    }
    
    if (stripos($nombre_lower, 'glucosa') !== false ||
        stripos($nombre_lower, 'colesterol') !== false ||
        stripos($nombre_lower, 'triglicérido') !== false ||
        stripos($nombre_lower, 'urea') !== false ||
        stripos($nombre_lower, 'creatinina') !== false ||
        stripos($nombre_lower, 'ácido úrico') !== false ||
        stripos($nombre_lower, 'bilirrubina') !== false ||
        stripos($nombre_lower, 'proteína') !== false ||
        stripos($nombre_lower, 'albúmina') !== false) {
        return 'Química Clínica';
    }
    
    if (stripos($nombre_lower, 'tsh') !== false ||
        stripos($nombre_lower, 't3') !== false ||
        stripos($nombre_lower, 't4') !== false ||
        stripos($nombre_lower, 'cortisol') !== false ||
        stripos($nombre_lower, 'testosterona') !== false ||
        stripos($nombre_lower, 'estradiol') !== false ||
        stripos($nombre_lower, 'progesterona') !== false ||
        stripos($nombre_lower, 'prolactina') !== false ||
        stripos($nombre_lower, 'hormona') !== false) {
        return 'Endocrinología';
    }
    
    if (stripos($nombre_lower, 'alt') !== false ||
        stripos($nombre_lower, 'ast') !== false ||
        stripos($nombre_lower, 'alp') !== false ||
        stripos($nombre_lower, 'ggt') !== false ||
        stripos($nombre_lower, 'aminotransferasa') !== false ||
        stripos($nombre_lower, 'fosfatasa') !== false) {
        return 'Función Hepática';
    }
    
    if (stripos($nombre_lower, 'sodio') !== false ||
        stripos($nombre_lower, 'potasio') !== false ||
        stripos($nombre_lower, 'cloro') !== false ||
        stripos($nombre_lower, 'calcio') !== false ||
        stripos($nombre_lower, 'fósforo') !== false ||
        stripos($nombre_lower, 'magnesio') !== false) {
        return 'Electrolitos';
    }
    
    if (stripos($nombre_lower, 'protrombina') !== false ||
        stripos($nombre_lower, 'inr') !== false ||
        stripos($nombre_lower, 'tromboplastina') !== false ||
        stripos($nombre_lower, 'fibrinógeno') !== false ||
        stripos($nombre_lower, 'dímero') !== false) {
        return 'Coagulación';
    }
    
    if (stripos($nombre_lower, 'urian') !== false ||
        stripos($nombre_lower, 'orina') !== false) {
        return 'Urianálisis';
    }
    
    if (stripos($nombre_lower, 'hierro') !== false ||
        stripos($nombre_lower, 'ferritina') !== false ||
        stripos($nombre_lower, 'transferrina') !== false ||
        stripos($nombre_lower, 'tibc') !== false) {
        return 'Metabolismo del Hierro';
    }
    
    if (stripos($nombre_lower, 'vitamina') !== false) {
        return 'Vitaminas';
    }
    
    if (stripos($nombre_lower, 'cpk') !== false ||
        stripos($nombre_lower, 'troponina') !== false ||
        stripos($nombre_lower, 'ldh') !== false ||
        stripos($nombre_lower, 'amilasa') !== false) {
        return 'Marcadores Cardíacos/Enzimas';
    }
    
    if (stripos($nombre_lower, 'pcr') !== false ||
        stripos($nombre_lower, 'factor reumatoide') !== false ||
        stripos($nombre_lower, 'antiestreptolisina') !== false) {
        return 'Inmunología';
    }
    
    return 'Química General';
}

function determine_price($categoria) {
    $prices = [
        'Hematología' => 50.00,
        'Química Clínica' => 30.00,
        'Endocrinología' => 150.00,
        'Función Hepática' => 40.00,
        'Electrolitos' => 35.00,
        'Coagulación' => 80.00,
        'Urianálisis' => 25.00,
        'Metabolismo del Hierro' => 60.00,
        'Vitaminas' => 120.00,
        'Marcadores Cardíacos/Enzimas' => 100.00,
        'Inmunología' => 70.00,
        'Química General' => 40.00
    ];
    
    return $prices[$categoria] ?? 50.00;
}

function parse_reference_values($ref_hombre, $ref_mujer, $ref_pediatrico, $unidad) {
    $result = [
        'hombre_min' => null,
        'hombre_max' => null,
        'mujer_min' => null,
        'mujer_max' => null,
        'pediatrico_min' => null,
        'pediatrico_max' => null,
        'texto_completo' => null
    ];
    
    // Helper to extract min/max from reference string
    $extract_range = function($ref_string) {
        $ref_string = trim($ref_string);
        
        // Handle "< X" format
        if (preg_match('/^[<≤]\s*([\d.]+)/', $ref_string, $matches)) {
            return [null, (float)$matches[1]];
        }
        
        // Handle "> X" format
        if (preg_match('/^[>≥]\s*([\d.]+)/', $ref_string, $matches)) {
            return [(float)$matches[1], null];
        }
        
        // Handle "X - Y" format
        if (preg_match('/([\d.]+)\s*[-–]\s*([\d.]+)/', $ref_string, $matches)) {
            return [(float)$matches[1], (float)$matches[2]];
        }
        
        // Handle single number
        if (is_numeric($ref_string)) {
            return [(float)$ref_string, (float)$ref_string];
        }
        
        // If contains text, it's qualitative
        return [null, null];
    };
    
    // Parse each reference value
    list($result['hombre_min'], $result['hombre_max']) = $extract_range($ref_hombre);
    list($result['mujer_min'], $result['mujer_max']) = $extract_range($ref_mujer);
    list($result['pediatrico_min'], $result['pediatrico_max']) = $extract_range($ref_pediatrico);
    
    // Store complete text for qualitative results
    if ($result['hombre_min'] === null && $result['hombre_max'] === null) {
        $result['texto_completo'] = "Hombres: $ref_hombre, Mujeres: $ref_mujer, Pediátrico: $ref_pediatrico";
    }
    
    return $result;
}

function determine_data_type($ref_values) {
    // If we have numeric ranges, it's numeric
    if ($ref_values['hombre_min'] !== null || $ref_values['hombre_max'] !== null ||
        $ref_values['mujer_min'] !== null || $ref_values['mujer_max'] !== null) {
        return 'Numérico';
    }
    
    // If we have descriptive text, check if it's a selection or free text
    if ($ref_values['texto_completo'] !== null) {
        if (stripos($ref_values['texto_completo'], 'Negativo') !== false ||
            stripos($ref_values['texto_completo'], 'Positivo') !== false) {
            return 'Cualitativo';
        }
        return 'Texto';
    }
    
    return 'Numérico';
}
