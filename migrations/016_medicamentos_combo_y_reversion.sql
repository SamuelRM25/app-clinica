-- Migration 016: Sistema de medicamentos en combos + reversión de stock
-- 1. Vincular medicamentos a items de combo
ALTER TABLE cirugia_combo_items
  ADD COLUMN id_inventario INT NULL AFTER id_combo,
  ADD COLUMN cantidad DECIMAL(10,2) DEFAULT 1 AFTER id_inventario,
  ADD CONSTRAINT fk_combo_item_inv FOREIGN KEY (id_inventario) REFERENCES inventario(id_inventario) ON DELETE SET NULL;

-- 2. Tabla para registrar todos los movimientos de stock (descarga y reversión)
CREATE TABLE IF NOT EXISTS movimientos_stock_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_inventario INT NOT NULL,
    id_hospital INT NOT NULL,
    id_referencia INT NULL,
    tabla_origen VARCHAR(50) NOT NULL,
    tipo_movimiento ENUM('descarga','reversion') NOT NULL,
    stock_column VARCHAR(50) NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL,
    usuario_id INT,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    notas TEXT,
    KEY idx_referencia (tabla_origen, id_referencia),
    KEY idx_inventario (id_inventario, id_hospital)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;