-- =====================================================
-- Script de actualización para Hospital ID = 2
-- Centro Médico Herrera Saenz
-- =====================================================

-- =====================================================
-- 1. AGREGAR NUEVOS MÉDICOS (id_hospital=2)
-- =====================================================
INSERT INTO usuarios (usuario, nombre, apellido, password, tipoUsuario, especialidad, id_hospital)
VALUES 
('mherrera', 'Manfri', 'Herrera', '$2y$10$' || SUBSTRING(MD5(RAND()) FROM 1 FOR 30) || '.', 'doc', 'Medicina General', 2),
('klucas', 'Kevin', 'Lucas', '$2y$10$' || SUBSTRING(MD5(RAND()) FROM 1 FOR 30) || '.', 'doc', 'Medicina General', 2),
('jgutierrez', 'Jeffrey', 'Gutiérrez', '$2y$10$' || SUBSTRING(MD5(RAND()) FROM 1 FOR 30) || '.', 'doc', 'Medicina General', 2),
('bleon', 'Brisly', 'de Leon', '$2y$10$' || SUBSTRING(MD5(RAND()) FROM 1 FOR 30) || '.', 'doc', 'Medicina General', 2);

-- =====================================================
-- 2. ELIMINAR DOCTORES (id_hospital=2)
-- =====================================================
DELETE FROM usuarios WHERE apellido IN ('Mendoza', 'Sarmiento', 'Rivas', 'Recinos', 'Gomez')
AND (nombre IN ('Cristian', 'Angie', 'Estuardo', 'Libny', 'Osber', 'Yoana'))
AND id_hospital = 2;

-- =====================================================
-- 3. AGREGAR USUARIAS DE ENFERMERÍA (id_hospital=2)
-- =====================================================
INSERT INTO usuarios (usuario, nombre, apellido, password, tipoUsuario, especialidad, id_hospital)
VALUES
('marisol', 'Marisol', 'Enfermera', '$2y$10$' || SUBSTRING(MD5(RAND()) FROM 1 FOR 30) || '.', 'user', 'enfermeria', 2),
('melisa', 'Melisa', 'Enfermera', '$2y$10$' || SUBSTRING(MD5(RAND()) FROM 1 FOR 30) || '.', 'user', 'enfermeria', 2),
('kenia', 'Kenia', 'Enfermera', '$2y$10$' || SUBSTRING(MD5(RAND()) FROM 1 FOR 30) || '.', 'user', 'enfermeria', 2),
('heidy', 'Heidy', 'Enfermera', '$2y$10$' || SUBSTRING(MD5(RAND()) FROM 1 FOR 30) || '.', 'user', 'enfermeria', 2);

-- =====================================================
-- 4. ACTUALIZAR ESPECIALIDAD PARA ROLES ESPECÍFICOS
-- =====================================================
-- Asignar 'farmacia' a usuarios de farmacia existentes
-- (ajusta nombres según los usuarios reales)
-- UPDATE usuarios SET especialidad = 'farmacia' WHERE usuario IN ('jrivas', 'farmacia_user') AND id_hospital = 2;

-- Asignar 'recepcion' a usuarios de recepción existentes
-- UPDATE usuarios SET especialidad = 'recepcion' WHERE usuario IN ('recepcion_user') AND id_hospital = 2;

-- NOTA: Los usuarios existentes necesitan que su especialidad se actualice manualmente
-- según los nombres de usuario reales en el sistema.
-- Ejecutar después de identificar los usuarios:
-- UPDATE usuarios SET especialidad = 'farmacia' WHERE usuario = 'NOMBRE_USUARIO_FARMACIA' AND id_hospital = 2;
-- UPDATE usuarios SET especialidad = 'recepcion' WHERE usuario = 'NOMBRE_USUARIO_RECEPCION' AND id_hospital = 2;
