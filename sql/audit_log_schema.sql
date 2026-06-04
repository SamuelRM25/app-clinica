-- ===============================================
-- Tabla de Auditoría para App Clínica
-- ===============================================
-- Esta tabla registra TODOS los movimientos del sistema
-- incluyendo login, logout, CRUD de pacientes, usuarios,
-- inventario, ventas, compras, etc.

CREATE TABLE IF NOT EXISTS audit_log (
    id_audit BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Multitenancy - Hospital al que pertenece el registro
    id_hospital INT UNSIGNED NOT NULL,

    -- Timestamp del evento
    fecha_audit DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Información del usuario que realizó la acción
    user_id INT UNSIGNED NULL,
    user_nombre VARCHAR(255) NULL,
    user_tipo VARCHAR(50) NULL,

    -- Información de conexión
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(512) NULL,
    session_id VARCHAR(128) NULL,

    -- Identificación de la acción
    accion VARCHAR(100) NOT NULL,
    modulo VARCHAR(100) NOT NULL,
    descripcion TEXT NULL,

    -- Identificación del registro afectado
    tabla_afectada VARCHAR(100) NULL,
    id_registro INT UNSIGNED NULL,

    -- Tracking de cambios de datos
    datos_anteriores JSON NULL,
    datos_nuevos JSON NULL,

    -- Resultado de la operación
    resultado VARCHAR(20) DEFAULT 'exito',
    mensaje_error TEXT NULL,

    -- Índices para mejor rendimiento
    INDEX idx_audit_hospital (id_hospital),
    INDEX idx_audit_fecha (fecha_audit),
    INDEX idx_audit_usuario (user_id),
    INDEX idx_audit_accion (accion),
    INDEX idx_audit_modulo (modulo),
    INDEX idx_audit_tabla_registro (tabla_afectada, id_registro),
    INDEX idx_audit_resultado (resultado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;