-- Add precio_especial column for "Precio Esp" mode in POS
-- This replaces the use of precio_compra (cost price) for special pricing
-- Compatible with MySQL 5.7+ (no IF NOT EXISTS for columns)

SET @dbname = (SELECT DATABASE());
SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'inventario' AND COLUMN_NAME = 'precio_especial');
SET @sql = IF(@exists = 0,
    'ALTER TABLE inventario ADD COLUMN precio_especial DECIMAL(10,2) DEFAULT 0.00 AFTER precio_medico',
    'SELECT 1 AS column_already_exists');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
