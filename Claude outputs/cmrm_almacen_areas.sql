-- ============================================================================
-- CMRM · Almacén de Laboratorio y Quirófano
-- Crea las tablas del almacén por área, plantillas de quirófano e insumos por prueba.
-- Agrega purchase_headers.area (default farmacia) y cirugias.id_plantilla.
-- Seguro de correr más de una vez. No modifica datos existentes.
-- (También se puede instalar desde la app: Almacén > Instalar almacén, como admin.)
-- ============================================================================

CREATE TABLE IF NOT EXISTS area_items (
    id_item INT AUTO_INCREMENT PRIMARY KEY,
    id_hospital INT NOT NULL,
    area VARCHAR(20) NOT NULL,
    codigo VARCHAR(50) NULL,
    nombre VARCHAR(150) NOT NULL,
    categoria VARCHAR(80) NULL,
    unidad VARCHAR(30) NOT NULL DEFAULT 'unidad',
    presentacion VARCHAR(120) NULL,
    stock DECIMAL(12,3) NOT NULL DEFAULT 0,
    stock_minimo DECIMAL(12,3) NOT NULL DEFAULT 0,
    costo_unitario DECIMAL(12,4) NOT NULL DEFAULT 0,
    fecha_vencimiento DATE NULL,
    lote VARCHAR(60) NULL,
    ubicacion VARCHAR(80) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_area (id_hospital, area, activo),
    KEY idx_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS area_movimientos (
    id_mov INT AUTO_INCREMENT PRIMARY KEY,
    id_hospital INT NOT NULL,
    area VARCHAR(20) NOT NULL,
    id_item INT NOT NULL,
    tipo VARCHAR(20) NOT NULL,
    cantidad DECIMAL(12,3) NOT NULL,
    stock_antes DECIMAL(12,3) NOT NULL DEFAULT 0,
    stock_despues DECIMAL(12,3) NOT NULL DEFAULT 0,
    costo_unitario DECIMAL(12,4) NOT NULL DEFAULT 0,
    ref_tipo VARCHAR(30) NULL,
    ref_id INT NULL,
    documento VARCHAR(80) NULL,
    nota VARCHAR(255) NULL,
    id_usuario INT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_area_fecha (id_hospital, area, fecha),
    KEY idx_item (id_item),
    KEY idx_ref (ref_tipo, ref_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS area_compra_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_hospital INT NOT NULL,
    purchase_header_id INT NOT NULL,
    id_item INT NOT NULL,
    cantidad DECIMAL(12,3) NOT NULL,
    costo_unitario DECIMAL(12,4) NOT NULL DEFAULT 0,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    fecha_vencimiento DATE NULL,
    lote VARCHAR(60) NULL,
    KEY idx_header (purchase_header_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS area_cortes (
    id_corte INT AUTO_INCREMENT PRIMARY KEY,
    id_hospital INT NOT NULL,
    area VARCHAR(20) NOT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    id_usuario INT NULL,
    items_contados INT NOT NULL DEFAULT 0,
    items_diferencia INT NOT NULL DEFAULT 0,
    valor_sistema DECIMAL(14,2) NOT NULL DEFAULT 0,
    valor_fisico DECIMAL(14,2) NOT NULL DEFAULT 0,
    valor_diferencia DECIMAL(14,2) NOT NULL DEFAULT 0,
    notas VARCHAR(500) NULL,
    KEY idx_area (id_hospital, area, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS area_corte_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_corte INT NOT NULL,
    id_item INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    unidad VARCHAR(30) NULL,
    stock_sistema DECIMAL(12,3) NOT NULL,
    stock_fisico DECIMAL(12,3) NOT NULL,
    diferencia DECIMAL(12,3) NOT NULL,
    costo_unitario DECIMAL(12,4) NOT NULL DEFAULT 0,
    KEY idx_corte (id_corte)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS area_recetas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_hospital INT NOT NULL,
    ref_tipo VARCHAR(20) NOT NULL,
    ref_id INT NOT NULL,
    fuente VARCHAR(10) NOT NULL DEFAULT 'area',
    id_item INT NULL,
    id_inventario INT NULL,
    cantidad DECIMAL(12,3) NOT NULL,
    KEY idx_ref (id_hospital, ref_tipo, ref_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS qx_plantillas (
    id_plantilla INT AUTO_INCREMENT PRIMARY KEY,
    id_hospital INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    tipo VARCHAR(20) NOT NULL DEFAULT 'Cirugía',
    descripcion VARCHAR(500) NULL,
    precio DECIMAL(12,2) NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_h (id_hospital, activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_headers' AND COLUMN_NAME = 'area');

SET @s := IF(@c = 0, 'ALTER TABLE purchase_headers ADD COLUMN area VARCHAR(20) NOT NULL DEFAULT ''farmacia''', 'SELECT 1');

PREPARE st FROM @s;

EXECUTE st;

DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cirugias' AND COLUMN_NAME = 'id_plantilla');

SET @s := IF(@c = 0, 'ALTER TABLE cirugias ADD COLUMN id_plantilla INT NULL', 'SELECT 1');

PREPARE st FROM @s;

EXECUTE st;

DEALLOCATE PREPARE st;

-- Verificación
SELECT COUNT(*) AS columna_area FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_headers' AND COLUMN_NAME = 'area';
SHOW TABLES LIKE 'area_%';
