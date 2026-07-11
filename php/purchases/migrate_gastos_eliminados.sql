CREATE TABLE IF NOT EXISTS gastos_eliminados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_original INT NOT NULL COMMENT 'ID del gasto original en gastos',
    descripcion VARCHAR(255) NOT NULL,
    cantidad INT NOT NULL DEFAULT 1,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    fecha DATE NOT NULL,
    created_by INT NOT NULL,
    id_hospital INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    motivo_eliminacion TEXT NOT NULL,
    eliminado_por INT NOT NULL,
    fecha_eliminacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_id_hospital (id_hospital),
    INDEX idx_fecha_eliminacion (fecha_eliminacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
