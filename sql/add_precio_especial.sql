-- Add precio_especial column for "Precio Esp" mode in POS
-- This replaces the use of precio_compra (cost price) for special pricing
ALTER TABLE inventario ADD COLUMN IF NOT EXISTS precio_especial DECIMAL(10,2) DEFAULT 0.00 AFTER precio_medico;
