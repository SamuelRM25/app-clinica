<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_compras'])) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Start transaction
        $conn->beginTransaction();

        // Calculate total from quantity and price
        $total = $_POST['cantidad_compra'] * $_POST['precio_unidad'];
        
        // Determine estado_compra based on abono
        $abono = $_POST['abono_compra'];
        if ($abono <= 0) {
            $estado = 'Pendiente';
        } elseif ($abono < $total) {
            $estado = 'Abonado';
        } else {
            $estado = 'Completo';
        }

        // Update purchase record
        $stmt = $conn->prepare("UPDATE compras SET nombre_compra = ?, presentacion_compra = ?, molecula_compra = ?, casa_compra = ?, cantidad_compra = ?, precio_unidad = ?, precio_venta = ?, fecha_compra = ?, abono_compra = ?, total_compra = ?, tipo_pago = ?, estado_compra = ? WHERE id_compras = ?");
        
        $result = $stmt->execute([
            $_POST['nombre_compra'],
            $_POST['presentacion_compra'],
            $_POST['molecula_compra'],
            $_POST['casa_compra'],
            $_POST['cantidad_compra'],
            $_POST['precio_unidad'],
            $_POST['precio_venta'],
            $_POST['fecha_compra'],
            $abono,
            $total,
            $_POST['tipo_pago'],
            $estado,
            $_POST['id_compras']
        ]);

        if ($result) {
            // Check if there's a corresponding inventory item
            $stmt_check = $conn->prepare("SELECT id_inventario FROM inventario 
                                         WHERE nom_medicamento = ? 
                                         AND mol_medicamento = ? 
                                         AND presentacion_med = ? 
                                         AND casa_farmaceutica = ? 
                                         AND fecha_adquisicion = ?");
            
            $stmt_check->execute([
                $_POST['nombre_compra'],
                $_POST['molecula_compra'],
                $_POST['presentacion_compra'],
                $_POST['casa_compra'],
                $_POST['fecha_compra']
            ]);
            
            $inventory_item = $stmt_check->fetch(PDO::FETCH_ASSOC);
            
            if ($inventory_item) {
                // Update existing inventory item
                $stmt_update = $conn->prepare("UPDATE inventario 
                                              SET cantidad_med = ? 
                                              WHERE id_inventario = ?");
                
                $stmt_update->execute([
                    $_POST['cantidad_compra'],
                    $inventory_item['id_inventario']
                ]);
            }
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['purchase_message'] = 'Compra actualizada correctamente';
            $_SESSION['purchase_status'] = 'success';
        } else {
            $conn->rollBack();
            $_SESSION['purchase_message'] = 'Error al actualizar la compra';
            $_SESSION['purchase_status'] = 'error';
        }
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollBack();
        }
        error_log($e->getMessage());
        $_SESSION['purchase_message'] = 'Error: ' . $e->getMessage();
        $_SESSION['purchase_status'] = 'error';
    }
    
    // Redirect back to purchases page
    header('Location: index.php');
    exit;
}