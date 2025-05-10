<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer la zona horaria correcta
date_default_timezone_set('America/Mexico_City'); // Ajusta esto a tu zona horaria local

verify_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Start transaction to ensure both operations succeed or fail together
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

        // Insert into compras table
        $stmt = $conn->prepare("INSERT INTO compras (nombre_compra, presentacion_compra, molecula_compra, casa_compra, cantidad_compra, precio_unidad, fecha_compra, abono_compra, total_compra, tipo_pago, estado_compra) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $result = $stmt->execute([
            $_POST['nombre_compra'],
            $_POST['presentacion_compra'],
            $_POST['molecula_compra'],
            $_POST['casa_compra'],
            $_POST['cantidad_compra'],
            $_POST['precio_unidad'],
            $_POST['fecha_compra'],
            $abono,
            $total,
            $_POST['tipo_pago'],
            $estado
        ]);
        
        if ($result) {
            // Also add to inventory with a placeholder expiration date
            // The expiration date will need to be updated later
            $stmt_inventory = $conn->prepare("INSERT INTO inventario (nom_medicamento, mol_medicamento, presentacion_med, casa_farmaceutica, cantidad_med, fecha_adquisicion, fecha_vencimiento) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            // Use a placeholder date for expiration (1 year from purchase date)
            $fecha_adquisicion = $_POST['fecha_compra'];
            $fecha_vencimiento_placeholder = date('Y-m-d', strtotime($fecha_adquisicion . ' + 1 year'));
            
            $result_inventory = $stmt_inventory->execute([
                $_POST['nombre_compra'],
                $_POST['molecula_compra'],
                $_POST['presentacion_compra'],
                $_POST['casa_compra'],
                $_POST['cantidad_compra'],
                $fecha_adquisicion,
                $fecha_vencimiento_placeholder
            ]);
            
            if ($result_inventory) {
                // Get the ID of the newly inserted inventory item
                $inventory_id = $conn->lastInsertId();
                
                // Commit the transaction
                $conn->commit();
                
                $_SESSION['purchase_message'] = 'Compra registrada correctamente y agregada al inventario (ID: ' . $inventory_id . '). Por favor, actualice la fecha de vencimiento.';
                $_SESSION['purchase_status'] = 'success';
                
                // Set a session variable to indicate that the inventory needs updating
                $_SESSION['inventory_needs_update'] = $inventory_id;
            } else {
                // Rollback if inventory insert fails
                $conn->rollBack();
                $_SESSION['purchase_message'] = 'Error al agregar al inventario';
                $_SESSION['purchase_status'] = 'error';
            }
        } else {
            $_SESSION['purchase_message'] = 'Error al registrar la compra';
            $_SESSION['purchase_status'] = 'error';
        }
    } catch (Exception $e) {
        // Rollback on any exception
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