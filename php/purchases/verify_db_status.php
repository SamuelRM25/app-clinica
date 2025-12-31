<?php
require_once '../../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $results = [];
    
    // Check purchase_headers
    try {
        $conn->query("SELECT 1 FROM purchase_headers LIMIT 1");
        $results['purchase_headers'] = "OK";
    } catch (Exception $e) {
        $results['purchase_headers'] = "Missing or Error: " . $e->getMessage();
    }
    
    // Check purchase_items
    try {
        $conn->query("SELECT 1 FROM purchase_items LIMIT 1");
        $results['purchase_items'] = "OK";
    } catch (Exception $e) {
        $results['purchase_items'] = "Missing or Error: " . $e->getMessage();
    }
    
    // Check inventario columns
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM inventario LIKE 'estado'");
        $results['inventario_estado'] = $stmt->fetch() ? "OK" : "Missing";
        
        $stmt = $conn->query("SHOW COLUMNS FROM inventario LIKE 'id_purchase_item'");
        $results['inventario_fk'] = $stmt->fetch() ? "OK" : "Missing";
    } catch (Exception $e) {
        $results['inventario_cols'] = "Error: " . $e->getMessage();
    }
    
    echo json_encode($results, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
