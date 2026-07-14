-- Migration 015: Módulo de Quirófano
-- Crea tablas para salas quirúrgicas, cirugías, combos, consumos y equipo

-- 1. Salas quirúrgicas
CREATE TABLE IF NOT EXISTS salas_quirurgicas (
    id_sala INT PRIMARY KEY AUTO_INCREMENT,
    codigo VARCHAR(20) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    tipo VARCHAR(50),
    tarifa_base DECIMAL(10,2) DEFAULT 0,
    estado ENUM('Disponible','Ocupada','Mantenimiento') DEFAULT 'Disponible',
    id_hospital INT NOT NULL,
    fecha_creacion TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_sala_codigo (codigo, id_hospital)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Combos de operación (CRUD)
CREATE TABLE IF NOT EXISTS cirugia_combos (
    id_combo INT PRIMARY KEY AUTO_INCREMENT,
    codigo VARCHAR(30) NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    precio_total DECIMAL(10,2) DEFAULT 0,
    estado ENUM('Activo','Inactivo') DEFAULT 'Activo',
    id_hospital INT NOT NULL,
    fecha_creacion TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_combo_codigo (codigo, id_hospital)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Items del combo (Ganancias/Gastos)
CREATE TABLE IF NOT EXISTS cirugia_combo_items (
    id_item INT PRIMARY KEY AUTO_INCREMENT,
    id_combo INT NOT NULL,
    tipo ENUM('Ganancia','Gasto') NOT NULL,
    categoria VARCHAR(50) NOT NULL,
    descripcion VARCHAR(150),
    monto DECIMAL(10,2) DEFAULT 0,
    id_hospital INT NOT NULL,
    FOREIGN KEY (id_combo) REFERENCES cirugia_combos(id_combo) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Cirugía (admisión quirúrgica)
CREATE TABLE IF NOT EXISTS cirugias (
    id_cirugia INT PRIMARY KEY AUTO_INCREMENT,
    numero_cirugia VARCHAR(30) NOT NULL,
    id_paciente INT NOT NULL,
    id_sala INT NOT NULL,
    id_cirujano INT,
    id_anestesista INT,
    id_combo INT NULL,
    tipo_paciente ENUM('Interno','Referido') DEFAULT 'Interno',
    referido_nombre VARCHAR(255),
    referido_apellido VARCHAR(255),
    procedimiento TEXT,
    fecha_programada DATETIME,
    fecha_inicio DATETIME NULL,
    fecha_fin DATETIME NULL,
    cargo_total DECIMAL(10,2) DEFAULT 0,
    estado ENUM('Programada','En_Curso','Finalizada','Cancelada') DEFAULT 'Programada',
    id_encamamiento INT NULL,
    created_by INT,
    id_hospital INT NOT NULL,
    fecha_creacion TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_cirugia_numero (numero_cirugia, id_hospital),
    KEY idx_cirugia_estado (estado, id_hospital),
    KEY idx_cirugia_fecha (fecha_programada, id_hospital)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Medicamentos consumidos durante la cirugía
CREATE TABLE IF NOT EXISTS cirugia_consumos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_cirugia INT NOT NULL,
    id_inventario INT NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL,
    precio_unitario DECIMAL(10,2),
    subtotal DECIMAL(10,2),
    id_hospital INT NOT NULL,
    FOREIGN KEY (id_cirugia) REFERENCES cirugias(id_cirugia) ON DELETE CASCADE,
    KEY idx_consumo_cirugia (id_cirugia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Equipo quirúrgico
CREATE TABLE IF NOT EXISTS cirugia_equipo (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_cirugia INT NOT NULL,
    id_usuario INT NOT NULL,
    rol VARCHAR(50),
    id_hospital INT NOT NULL,
    FOREIGN KEY (id_cirugia) REFERENCES cirugias(id_cirugia) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Extender cargos_hospitalarios.tipo_cargo para aceptar 'Cirugía'
ALTER TABLE cargos_hospitalarios 
    MODIFY tipo_cargo ENUM('Habitación','Medicamento','Procedimiento','Laboratorio',
                           'Honorario','Insumo','Cirugía','Otro') NOT NULL;