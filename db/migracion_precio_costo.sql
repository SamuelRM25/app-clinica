-- Migración: Agregar columna de precio costo
-- Ejecutar en phpMyAdmin / MySQL (una vez por base de datos del hospital)

-- 1. cargos hospitalarios (encamamiento + cirugía)
ALTER TABLE cargos_hospitalarios
    ADD COLUMN precio_costo DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER precio_unitario;

-- 2. consumos de quirófano
ALTER TABLE cirugia_consumos
    ADD COLUMN precio_costo DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER precio_unitario;
