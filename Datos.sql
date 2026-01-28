-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: bzlwnzdfwf8n1tct7ebf-mysql.services.clever-cloud.com:3306
-- Tiempo de generación: 24-01-2026 a las 18:25:36
-- Versión del servidor: 8.0.22-13
-- Versión de PHP: 8.2.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `bzlwnzdfwf8n1tct7ebf`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `abonos_hospitalarios`
--

CREATE TABLE `abonos_hospitalarios` (
  `id_abono` int NOT NULL,
  `id_cuenta` int NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `metodo_pago` enum('Efectivo','Tarjeta','Transferencia','Seguro') NOT NULL DEFAULT 'Efectivo',
  `fecha_abono` datetime DEFAULT CURRENT_TIMESTAMP,
  `saldo_pendiente` decimal(10,2) NOT NULL DEFAULT '0.00',
  `notas` text,
  `registrado_por` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `administracion_medicamentos`
--

CREATE TABLE `administracion_medicamentos` (
  `id_administracion` int NOT NULL,
  `id_encamamiento` int NOT NULL,
  `id_medicamento` int DEFAULT NULL COMMENT 'Referencia a inventario',
  `nombre_medicamento` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dosis` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `via_administracion` enum('Oral','Intravenosa','Intramuscular','Subcutánea','Tópica','Rectal','Otra') COLLATE utf8mb4_unicode_ci NOT NULL,
  `frecuencia` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ej: Cada 8 horas, 3 veces al día',
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `indicado_por` int DEFAULT NULL,
  `administrado_por` int DEFAULT NULL,
  `fecha_administracion` datetime DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci,
  `estado` enum('Programado','Administrado','Omitido','Suspendido') COLLATE utf8mb4_unicode_ci DEFAULT 'Programado',
  `motivo_omision` text COLLATE utf8mb4_unicode_ci,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `archivos_orden`
--

CREATE TABLE `archivos_orden` (
  `id_archivo` int NOT NULL,
  `id_orden_prueba` int NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `tipo_contenido` varchar(100) NOT NULL,
  `tamano` int NOT NULL,
  `contenido` longblob NOT NULL,
  `fecha_carga` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `camas`
--

CREATE TABLE `camas` (
  `id_cama` int NOT NULL,
  `id_habitacion` int NOT NULL,
  `numero_cama` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` enum('Disponible','Ocupada','Mantenimiento','Reservada') COLLATE utf8mb4_unicode_ci DEFAULT 'Disponible',
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `camas`
--

INSERT INTO `camas` (`id_cama`, `id_habitacion`, `numero_cama`, `estado`, `descripcion`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(2, 2, '1', 'Disponible', NULL, '2026-01-18 17:10:00', '2026-01-18 17:10:00'),
(3, 3, '1', 'Disponible', NULL, '2026-01-18 17:10:00', '2026-01-18 17:10:00'),
(4, 4, '1', 'Disponible', NULL, '2026-01-18 17:10:00', '2026-01-21 20:13:11'),
(5, 5, '1', 'Disponible', NULL, '2026-01-18 17:10:00', '2026-01-18 17:10:00'),
(6, 6, '1', 'Disponible', NULL, '2026-01-18 17:10:00', '2026-01-18 17:10:00'),
(7, 7, '1', 'Disponible', NULL, '2026-01-18 17:10:00', '2026-01-18 17:10:00'),
(8, 8, '1', 'Disponible', NULL, '2026-01-18 17:10:00', '2026-01-18 17:10:00'),
(9, 9, '1', 'Ocupada', NULL, '2026-01-18 17:10:00', '2026-01-24 07:57:14');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cargos_hospitalarios`
--

CREATE TABLE `cargos_hospitalarios` (
  `id_cargo` int NOT NULL,
  `id_cuenta` int NOT NULL,
  `tipo_cargo` enum('Habitación','Medicamento','Procedimiento','Laboratorio','Honorario','Insumo','Otro') COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cantidad` decimal(10,3) DEFAULT '1.000',
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) GENERATED ALWAYS AS ((`cantidad` * `precio_unitario`)) STORED,
  `fecha_cargo` datetime NOT NULL,
  `fecha_aplicacion` date DEFAULT NULL COMMENT 'Para cargos de habitación por noche',
  `registrado_por` int DEFAULT NULL,
  `referencia_id` int DEFAULT NULL COMMENT 'ID del item original (id_medicamento, id_procedimiento, etc)',
  `referencia_tabla` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nombre de la tabla de referencia',
  `notas` text COLLATE utf8mb4_unicode_ci,
  `cancelado` tinyint(1) DEFAULT '0',
  `fecha_cancelacion` datetime DEFAULT NULL,
  `motivo_cancelacion` text COLLATE utf8mb4_unicode_ci,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `cargos_hospitalarios`
--

INSERT INTO `cargos_hospitalarios` (`id_cargo`, `id_cuenta`, `tipo_cargo`, `descripcion`, `cantidad`, `precio_unitario`, `fecha_cargo`, `fecha_aplicacion`, `registrado_por`, `referencia_id`, `referencia_tabla`, `notas`, `cancelado`, `fecha_cancelacion`, `motivo_cancelacion`, `fecha_creacion`) VALUES
(4, 2, 'Habitación', 'Habitación 201 - Cama 1 (Día de ingreso)', 1.000, 600.00, '2026-01-21 14:10:00', '2026-01-21', 1, NULL, NULL, NULL, 0, NULL, NULL, '2026-01-21 20:10:44'),
(5, 2, 'Medicamento', 'Paracetamol 500mg', 1.000, 100.00, '2026-01-21 14:12:14', NULL, 1, NULL, NULL, NULL, 0, NULL, NULL, '2026-01-21 20:12:15'),
(6, 2, 'Laboratorio', 'Hematología', 1.000, 50.00, '2026-01-21 14:12:14', NULL, 1, NULL, NULL, NULL, 0, NULL, NULL, '2026-01-21 20:12:15'),
(7, 3, 'Habitación', 'Habitación 403 - Cama 1 (Día de ingreso)', 1.000, 1100.00, '2026-01-24 01:56:00', '2026-01-24', 1, NULL, NULL, NULL, 0, NULL, NULL, '2026-01-24 07:57:15');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `catalogo_pruebas`
--

CREATE TABLE `catalogo_pruebas` (
  `id_prueba` int NOT NULL,
  `codigo_prueba` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_prueba` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abreviatura` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `muestra_requerida` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ej: Sangre Total (EDTA)',
  `metodo_toma` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Instrucciones de toma de muestra',
  `precio` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tiempo_procesamiento_horas` int DEFAULT '24',
  `requiere_ayuno` tinyint(1) DEFAULT '0',
  `horas_ayuno` int DEFAULT NULL,
  `estado` enum('Activo','Inactivo','Descontinuado') COLLATE utf8mb4_unicode_ci DEFAULT 'Activo',
  `categoria` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ej: Hematología, Química, Hormonas',
  `notas` text COLLATE utf8mb4_unicode_ci,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `catalogo_pruebas`
--

INSERT INTO `catalogo_pruebas` (`id_prueba`, `codigo_prueba`, `nombre_prueba`, `abreviatura`, `muestra_requerida`, `metodo_toma`, `precio`, `tiempo_procesamiento_horas`, `requiere_ayuno`, `horas_ayuno`, `estado`, `categoria`, `notas`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'LAB-01', 'Hematología Completa', 'HC', 'Sangre', NULL, 150.00, 3, 1, 8, 'Activo', 'Hematología', NULL, '2026-01-23 04:27:34', '2026-01-23 04:27:34'),
(2, 'CTS', 'Cortisol', NULL, 'Sangre', NULL, 200.00, 2, 0, NULL, 'Activo', 'Cortisol', 'Cortisol', '2026-01-23 05:03:56', '2026-01-23 05:03:56'),
(3, 'HEM-001', 'Hemograma completo', 'HC', 'Sangre EDTA', NULL, 150.00, 2, 0, NULL, 'Activo', 'Hematología', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(4, 'HEM-002', 'Recuento plaquetario', 'PLT', 'Sangre EDTA', NULL, 80.00, 2, 0, NULL, 'Activo', 'Hematología', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(5, 'HEM-003', 'Velocidad sedimentación', 'VSG', 'Sangre citrato', NULL, 70.00, 1, 0, NULL, 'Activo', 'Hematología', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(6, 'QUI-001', 'Glucosa en ayunas', 'GLU', 'Sangre fluo', NULL, 50.00, 1, 0, NULL, 'Activo', 'Química', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(7, 'QUI-002', 'Urea', 'URE', 'Sangre', NULL, 45.00, 1, 0, NULL, 'Activo', 'Química', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(8, 'QUI-003', 'Creatinina', 'CRE', 'Sangre', NULL, 45.00, 1, 0, NULL, 'Activo', 'Química', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(9, 'QUI-004', 'Ácido úrico', 'AU', 'Sangre', NULL, 50.00, 1, 0, NULL, 'Activo', 'Química', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(10, 'QUI-005', 'Colesterol total', 'COL', 'Sangre', NULL, 60.00, 1, 0, NULL, 'Activo', 'Química', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(11, 'QUI-006', 'Triglicéridos', 'TRI', 'Sangre', NULL, 60.00, 1, 0, NULL, 'Activo', 'Química', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(12, 'QUI-007', 'Transaminasa GOT', 'GOT', 'Sangre', NULL, 55.00, 1, 0, NULL, 'Activo', 'Química', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(13, 'QUI-008', 'Transaminasa GPT', 'GPT', 'Sangre', NULL, 55.00, 1, 0, NULL, 'Activo', 'Química', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(14, 'QUI-009', 'Fosfatasa alcalina', 'FA', 'Sangre', NULL, 65.00, 1, 0, NULL, 'Activo', 'Química', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(15, 'QUI-010', 'Bilirrubina total', 'BT', 'Sangre', NULL, 50.00, 1, 0, NULL, 'Activo', 'Química', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(16, 'HOR-001', 'TSH', 'TSH', 'Sangre', NULL, 120.00, 24, 0, NULL, 'Activo', 'Hormonas', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(17, 'HOR-002', 'T4 libre', 'T4L', 'Sangre', NULL, 110.00, 24, 0, NULL, 'Activo', 'Hormonas', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(18, 'HOR-003', 'Cortisol', 'CORT', 'Sangre', NULL, 130.00, 24, 0, NULL, 'Activo', 'Hormonas', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(19, 'HOR-004', 'Prolactina', 'PRL', 'Sangre', NULL, 140.00, 24, 0, NULL, 'Activo', 'Hormonas', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(20, 'HOR-005', 'Estradiol', 'E2', 'Sangre', NULL, 150.00, 24, 0, NULL, 'Activo', 'Hormonas', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(21, 'HOR-006', 'Testosterona', 'TEST', 'Sangre', NULL, 145.00, 24, 0, NULL, 'Activo', 'Hormonas', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(22, 'HOR-007', 'Progesterona', 'PROG', 'Sangre', NULL, 135.00, 24, 0, NULL, 'Activo', 'Hormonas', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(23, 'HOR-008', 'FSH', 'FSH', 'Sangre', NULL, 125.00, 24, 0, NULL, 'Activo', 'Hormonas', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(24, 'HOR-009', 'LH', 'LH', 'Sangre', NULL, 125.00, 24, 0, NULL, 'Activo', 'Hormonas', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(25, 'HOR-010', 'HCG cuantitativo', 'HCG', 'Sangre', NULL, 100.00, 3, 0, NULL, 'Activo', 'Hormonas', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(26, 'MIC-001', 'Urocultivo', 'UC', 'Orina', NULL, 180.00, 48, 0, NULL, 'Activo', 'Microbiología', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(27, 'MIC-002', 'Coprocultivo', 'CC', 'Heces', NULL, 200.00, 48, 0, NULL, 'Activo', 'Microbiología', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(28, 'MIC-003', 'Examen directo', 'ED', 'Secreción', NULL, 90.00, 24, 0, NULL, 'Activo', 'Microbiología', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(29, 'MIC-004', 'Prueba de sensibilidad', 'ATB', 'Aislamiento', NULL, 120.00, 48, 0, NULL, 'Activo', 'Microbiología', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(30, 'INA-001', 'VIH', 'VIH', 'Sangre', NULL, 250.00, 72, 0, NULL, 'Activo', 'Inmunología', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(31, 'INA-002', 'VDRL', 'VDRL', 'Sangre', NULL, 100.00, 24, 0, NULL, 'Activo', 'Inmunología', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56'),
(32, 'INA-003', 'Factor reumatoide', 'FR', 'Sangre', NULL, 150.00, 24, 0, NULL, 'Activo', 'Inmunología', NULL, '2026-01-23 15:34:56', '2026-01-23 15:34:56');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `citas`
--

CREATE TABLE `citas` (
  `id_cita` int NOT NULL,
  `nombre_pac` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `apellido_pac` varchar(50) NOT NULL,
  `num_cita` int NOT NULL,
  `fecha_cita` date NOT NULL,
  `hora_cita` time NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `historial_id` int DEFAULT NULL,
  `id_doctor` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Volcado de datos para la tabla `citas`
--

INSERT INTO `citas` (`id_cita`, `nombre_pac`, `apellido_pac`, `num_cita`, `fecha_cita`, `hora_cita`, `telefono`, `historial_id`, `id_doctor`) VALUES
(4, 'Oscar Samuel', 'Ramírez Martínez', 1, '2026-02-24', '10:00:00', '39029076', NULL, 16),
(5, 'Samuel', 'Ramirez', 2, '2026-01-25', '12:00:00', '49617032', NULL, 13);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cobros`
--

CREATE TABLE `cobros` (
  `in_cobro` int NOT NULL,
  `paciente_cobro` int NOT NULL,
  `id_doctor` int DEFAULT NULL,
  `cantidad_consulta` int NOT NULL,
  `fecha_consulta` datetime NOT NULL,
  `tipo_pago` enum('Efectivo','Tarjeta','Transferencia') DEFAULT 'Efectivo',
  `tipo_consulta` enum('Consulta','Reconsulta') DEFAULT 'Consulta'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Volcado de datos para la tabla `cobros`
--

INSERT INTO `cobros` (`in_cobro`, `paciente_cobro`, `id_doctor`, `cantidad_consulta`, `fecha_consulta`, `tipo_pago`, `tipo_consulta`) VALUES
(1, 5, 17, 150, '2026-01-23 00:00:00', 'Transferencia', 'Reconsulta'),
(2, 27, 18, 200, '2026-01-23 00:00:00', 'Efectivo', 'Consulta'),
(3, 26, 15, 100, '2026-01-23 00:00:00', 'Transferencia', 'Consulta'),
(4, 9, 14, 500, '2026-01-23 00:00:00', 'Tarjeta', 'Reconsulta'),
(5, 19, 18, 200, '2026-01-24 00:00:00', 'Efectivo', 'Consulta'),
(6, 3, 17, 150, '2026-01-24 00:00:00', 'Tarjeta', 'Reconsulta'),
(7, 15, 16, 250, '2026-01-24 00:00:00', 'Efectivo', 'Consulta');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `compras`
--

CREATE TABLE `compras` (
  `id_compras` int NOT NULL,
  `nombre_compra` varchar(100) NOT NULL,
  `presentacion_compra` varchar(100) NOT NULL,
  `molecula_compra` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `casa_compra` varchar(100) NOT NULL,
  `cantidad_compra` int NOT NULL,
  `precio_unidad` int NOT NULL,
  `precio_venta` int NOT NULL,
  `fecha_compra` date NOT NULL,
  `abono_compra` int NOT NULL,
  `total_compra` int NOT NULL,
  `tipo_pago` enum('Al Contado','Credito 30','Credito 60','') NOT NULL,
  `estado_compra` enum('Pendiente','Abonado','Completo','') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `control_calidad_lab`
--

CREATE TABLE `control_calidad_lab` (
  `id_control` int NOT NULL,
  `id_prueba` int NOT NULL,
  `fecha_control` date NOT NULL,
  `lote_control` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor_esperado` decimal(12,4) DEFAULT NULL,
  `valor_obtenido` decimal(12,4) DEFAULT NULL,
  `diferencia` decimal(12,4) GENERATED ALWAYS AS (abs((`valor_obtenido` - `valor_esperado`))) STORED,
  `dentro_rango` tinyint(1) DEFAULT NULL,
  `desviacion_estandar` decimal(12,4) DEFAULT NULL,
  `coeficiente_variacion` decimal(12,4) DEFAULT NULL,
  `accion_correctiva` text COLLATE utf8mb4_unicode_ci,
  `realizado_por` int DEFAULT NULL,
  `aprobado_por` int DEFAULT NULL,
  `estado` enum('Aprobado','Rechazado','Requiere_Acción') COLLATE utf8mb4_unicode_ci DEFAULT 'Aprobado',
  `notas` text COLLATE utf8mb4_unicode_ci,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cuenta_hospitalaria`
--

CREATE TABLE `cuenta_hospitalaria` (
  `id_cuenta` int NOT NULL,
  `id_encamamiento` int NOT NULL,
  `subtotal_habitacion` decimal(10,2) DEFAULT '0.00',
  `subtotal_medicamentos` decimal(10,2) DEFAULT '0.00',
  `subtotal_procedimientos` decimal(10,2) DEFAULT '0.00',
  `subtotal_laboratorios` decimal(10,2) DEFAULT '0.00',
  `subtotal_honorarios` decimal(10,2) DEFAULT '0.00',
  `subtotal_otros` decimal(10,2) DEFAULT '0.00',
  `descuento` decimal(10,2) DEFAULT '0.00',
  `total_general` decimal(10,2) GENERATED ALWAYS AS (((((((`subtotal_habitacion` + `subtotal_medicamentos`) + `subtotal_procedimientos`) + `subtotal_laboratorios`) + `subtotal_honorarios`) + `subtotal_otros`) - `descuento`)) STORED,
  `estado_pago` enum('Pendiente','Parcialmente_Pagado','Pagado','Condonado') COLLATE utf8mb4_unicode_ci DEFAULT 'Pendiente',
  `monto_pagado` decimal(10,2) DEFAULT '0.00',
  `saldo_pendiente` decimal(10,2) GENERATED ALWAYS AS ((((((((`subtotal_habitacion` + `subtotal_medicamentos`) + `subtotal_procedimientos`) + `subtotal_laboratorios`) + `subtotal_honorarios`) + `subtotal_otros`) - `descuento`) - `monto_pagado`)) STORED,
  `metodo_pago` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Efectivo, Tarjeta, Transferencia, Mixto',
  `notas_pago` text COLLATE utf8mb4_unicode_ci,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `total_pagado` decimal(10,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `cuenta_hospitalaria`
--

INSERT INTO `cuenta_hospitalaria` (`id_cuenta`, `id_encamamiento`, `subtotal_habitacion`, `subtotal_medicamentos`, `subtotal_procedimientos`, `subtotal_laboratorios`, `subtotal_honorarios`, `subtotal_otros`, `descuento`, `estado_pago`, `monto_pagado`, `metodo_pago`, `notas_pago`, `fecha_creacion`, `fecha_actualizacion`, `total_pagado`) VALUES
(2, 2, 600.00, 100.00, 0.00, 50.00, 0.00, 0.00, 0.00, 'Pendiente', 0.00, NULL, NULL, '2026-01-21 20:10:44', '2026-01-21 20:12:18', 0.00),
(3, 3, 1100.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Pendiente', 0.00, NULL, NULL, '2026-01-24 07:57:14', '2026-01-24 07:57:18', 0.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_ventas`
--

CREATE TABLE `detalle_ventas` (
  `id_detalle` int NOT NULL,
  `id_venta` int DEFAULT NULL,
  `id_inventario` int DEFAULT NULL,
  `cantidad_vendida` int NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) GENERATED ALWAYS AS ((`cantidad_vendida` * `precio_unitario`)) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Volcado de datos para la tabla `detalle_ventas`
--

INSERT INTO `detalle_ventas` (`id_detalle`, `id_venta`, `id_inventario`, `cantidad_vendida`, `precio_unitario`) VALUES
(1, 1, 102, 1, 90.00),
(2, 2, 102, 1, 90.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `electrocardiogramas`
--

CREATE TABLE `electrocardiogramas` (
  `id_electro` int NOT NULL,
  `id_paciente` int NOT NULL,
  `id_doctor` int DEFAULT NULL,
  `fecha_realizado` datetime DEFAULT CURRENT_TIMESTAMP,
  `observaciones` text,
  `precio` decimal(10,2) NOT NULL DEFAULT '0.00',
  `estado_pago` enum('Pendiente','Pagado') DEFAULT 'Pendiente',
  `realizado_por` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `encamamientos`
--

CREATE TABLE `encamamientos` (
  `id_encamamiento` int NOT NULL,
  `id_paciente` int NOT NULL,
  `id_cama` int NOT NULL,
  `id_doctor` int DEFAULT NULL,
  `fecha_ingreso` datetime NOT NULL,
  `fecha_alta` datetime DEFAULT NULL,
  `motivo_ingreso` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `diagnostico_ingreso` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `diagnostico_egreso` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('Activo','Alta_Medica','Alta_Administrativa','Transferido','Fallecido') COLLATE utf8mb4_unicode_ci DEFAULT 'Activo',
  `tipo_ingreso` enum('Programado','Emergencia','Referido') COLLATE utf8mb4_unicode_ci DEFAULT 'Programado',
  `notas_ingreso` text COLLATE utf8mb4_unicode_ci,
  `notas_alta` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `encamamientos`
--

INSERT INTO `encamamientos` (`id_encamamiento`, `id_paciente`, `id_cama`, `id_doctor`, `fecha_ingreso`, `fecha_alta`, `motivo_ingreso`, `diagnostico_ingreso`, `diagnostico_egreso`, `estado`, `tipo_ingreso`, `notas_ingreso`, `notas_alta`, `created_by`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(2, 3, 4, 15, '2026-01-21 14:10:00', '2026-01-21 20:13:11', 'Prueba', 'Prueba', 'Prueba', 'Alta_Administrativa', 'Emergencia', 'Prueba', 'Prueba', 1, '2026-01-21 20:10:43', '2026-01-21 20:13:11'),
(3, 12, 9, 11, '2026-01-24 01:56:00', NULL, 'Prueba', 'Prueba', NULL, 'Activo', 'Emergencia', 'Prueba', NULL, 1, '2026-01-24 07:57:14', '2026-01-24 07:57:14');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `encamamientos_con_dias`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `encamamientos_con_dias` (
`created_by` int
,`diagnostico_egreso` varchar(500)
,`diagnostico_ingreso` varchar(500)
,`dias_hospitalizacion` int
,`estado` enum('Activo','Alta_Medica','Alta_Administrativa','Transferido','Fallecido')
,`fecha_actualizacion` timestamp
,`fecha_alta` datetime
,`fecha_creacion` timestamp
,`fecha_ingreso` datetime
,`id_cama` int
,`id_doctor` int
,`id_encamamiento` int
,`id_paciente` int
,`motivo_ingreso` text
,`notas_alta` text
,`notas_ingreso` text
,`tipo_ingreso` enum('Programado','Emergencia','Referido')
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `evoluciones_medicas`
--

CREATE TABLE `evoluciones_medicas` (
  `id_evolucion` int NOT NULL,
  `id_encamamiento` int NOT NULL,
  `fecha_evolucion` datetime NOT NULL,
  `id_doctor` int NOT NULL,
  `subjetivo` text COLLATE utf8mb4_unicode_ci COMMENT 'SOAP: Subjetivo',
  `objetivo` text COLLATE utf8mb4_unicode_ci COMMENT 'SOAP: Objetivo',
  `evaluacion` text COLLATE utf8mb4_unicode_ci COMMENT 'SOAP: Evaluación/Assessment',
  `plan_tratamiento` text COLLATE utf8mb4_unicode_ci COMMENT 'SOAP: Plan',
  `notas_adicionales` text COLLATE utf8mb4_unicode_ci,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `examenes_realizados`
--

CREATE TABLE `examenes_realizados` (
  `id_examen_realizado` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `id_paciente` int NOT NULL,
  `nombre_paciente` varchar(255) NOT NULL,
  `tipo_examen` varchar(255) NOT NULL COMMENT 'Nombre del examen (ej. Electrocardiograma, Ultrasonido)',
  `cobro` decimal(10,2) NOT NULL,
  `fecha_examen` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario` varchar(255) DEFAULT NULL,
  `tipo_pago` enum('Efectivo','Tarjeta','Transferencia') DEFAULT 'Efectivo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Volcado de datos para la tabla `examenes_realizados`
--

INSERT INTO `examenes_realizados` (`id_examen_realizado`, `id_paciente`, `nombre_paciente`, `tipo_examen`, `cobro`, `fecha_examen`, `usuario`, `tipo_pago`) VALUES
(NULL, 3, 'Emilia Alejndra Pérez Castillo', 'Orden #3: Cortisol', 200.00, '2026-01-23 06:57:27', 'Anye', 'Transferencia'),
(NULL, 4, 'Samuel Ramirez', 'Ultrasonido: HOMBRO', 500.00, '2026-01-23 06:57:52', 'Anye', 'Efectivo'),
(NULL, 3, 'Emilia Alejndra Pérez Castillo', 'Ultrasonido: OBSTETRICO', 300.00, '2026-01-23 07:02:46', 'Anye', 'Tarjeta'),
(NULL, 3, 'Emilia Alejndra Pérez Castillo', 'Orden #2: Cortisol', 200.00, '2026-01-23 07:03:29', 'Anye', 'Transferencia'),
(NULL, 4, 'Samuel Ramirez', 'Orden #13: Hematología Completa', 150.00, '2026-01-23 17:02:53', 'Anye', 'Efectivo'),
(NULL, 5, 'Oscar Martinez', 'Orden #12: Cortisol', 200.00, '2026-01-23 17:03:09', 'Anye', 'Transferencia'),
(NULL, 5, 'Oscar Martinez', 'Orden #11: Cortisol', 200.00, '2026-01-23 17:03:25', 'Anye', 'Tarjeta'),
(NULL, 5, 'Oscar Martinez', 'Orden #12: Cortisol', 200.00, '2026-01-24 07:55:34', 'Anye', 'Tarjeta'),
(NULL, 5, 'Oscar Martinez', 'Orden #12: Cortisol', 200.00, '2026-01-24 17:43:45', 'Anye', 'Transferencia'),
(NULL, 5, 'Oscar Martinez', 'Orden #12: Cortisol', 200.00, '2026-01-24 17:55:46', 'Anye', 'Transferencia');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `habitaciones`
--

CREATE TABLE `habitaciones` (
  `id_habitacion` int NOT NULL,
  `numero_habitacion` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_habitacion` enum('Individual','Compartida','UCI','Pediatría','Observación') COLLATE utf8mb4_unicode_ci NOT NULL,
  `tarifa_por_noche` decimal(10,2) NOT NULL,
  `piso` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('Disponible','Ocupada','Mantenimiento','Reservada') COLLATE utf8mb4_unicode_ci DEFAULT 'Disponible',
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `tiene_bano` tinyint(1) DEFAULT '1',
  `tiene_tv` tinyint(1) DEFAULT '0',
  `tiene_aire_acondicionado` tinyint(1) DEFAULT '0',
  `capacidad_maxima` int DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `habitaciones`
--

INSERT INTO `habitaciones` (`id_habitacion`, `numero_habitacion`, `tipo_habitacion`, `tarifa_por_noche`, `piso`, `estado`, `descripcion`, `tiene_bano`, `tiene_tv`, `tiene_aire_acondicionado`, `capacidad_maxima`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(2, 'Emerg 1', 'Observación', 0.00, '1', 'Disponible', NULL, 1, 0, 0, 1, '2026-01-18 17:09:59', '2026-01-18 17:09:59'),
(3, 'Emerg 2', 'Observación', 0.00, '1', 'Disponible', NULL, 1, 0, 0, 1, '2026-01-18 17:10:00', '2026-01-18 17:10:00'),
(4, '201', 'Individual', 600.00, '2', 'Disponible', NULL, 1, 0, 0, 1, '2026-01-18 17:10:00', '2026-01-18 17:10:00'),
(5, '202', 'Individual', 600.00, '2', 'Disponible', NULL, 1, 0, 0, 1, '2026-01-18 17:10:00', '2026-01-18 17:10:00'),
(6, '301', 'Individual', 950.00, '3', 'Disponible', NULL, 1, 0, 0, 1, '2026-01-18 17:10:00', '2026-01-18 17:10:00'),
(7, '401', 'Individual', 950.00, '4', 'Disponible', NULL, 1, 0, 0, 1, '2026-01-18 17:10:00', '2026-01-18 17:10:00'),
(8, '402', 'Individual', 950.00, '4', 'Disponible', NULL, 1, 0, 0, 1, '2026-01-18 17:10:00', '2026-01-18 17:10:00'),
(9, '403', 'Individual', 1100.00, '4', 'Disponible', NULL, 1, 0, 0, 1, '2026-01-18 17:10:00', '2026-01-18 17:10:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_clinico`
--

CREATE TABLE `historial_clinico` (
  `id_historial` int NOT NULL,
  `id_paciente` int NOT NULL,
  `fecha_consulta` datetime DEFAULT CURRENT_TIMESTAMP,
  `motivo_consulta` text NOT NULL,
  `sintomas` text NOT NULL,
  `diagnostico` text NOT NULL,
  `tratamiento` text NOT NULL,
  `receta_medica` text,
  `antecedentes_personales` text,
  `antecedentes_familiares` text,
  `examenes_realizados` text,
  `resultados_examenes` text,
  `observaciones` text,
  `proxima_cita` date DEFAULT NULL,
  `medico_responsable` varchar(100) NOT NULL,
  `especialidad_medico` varchar(100) DEFAULT NULL,
  `hora_proxima_cita` time DEFAULT NULL,
  `examen_fisico` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Volcado de datos para la tabla `historial_clinico`
--

INSERT INTO `historial_clinico` (`id_historial`, `id_paciente`, `fecha_consulta`, `motivo_consulta`, `sintomas`, `diagnostico`, `tratamiento`, `receta_medica`, `antecedentes_personales`, `antecedentes_familiares`, `examenes_realizados`, `resultados_examenes`, `observaciones`, `proxima_cita`, `medico_responsable`, `especialidad_medico`, `hora_proxima_cita`, `examen_fisico`) VALUES
(3, 3, '2026-01-21 20:10:43', 'Prueba', '', 'Prueba', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sistema', NULL, NULL, NULL),
(4, 6, '2026-01-20 09:00:00', 'Dolor de cabeza', 'Cefalea frontal, náuseas', 'Migraña', 'Analgésicos, reposo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dr. Carlos López', NULL, NULL, NULL),
(5, 7, '2026-01-20 10:30:00', 'Fiebre y tos', 'Fiebre 38.5°C, tos seca', 'Infección respiratoria', 'Antibióticos, antipiréticos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dra. María Rodríguez', NULL, NULL, NULL),
(6, 8, '2026-01-20 11:15:00', 'Dolor abdominal', 'Dolor en hipocondrio derecho', 'Colecistitis', 'Dieta baja en grasas, medicamentos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dr. Juan Martínez', NULL, NULL, NULL),
(7, 9, '2026-01-20 12:00:00', 'Control rutinario', 'Sin síntomas', 'Paciente sano', 'Controles anuales', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dra. Laura Hernández', NULL, NULL, NULL),
(8, 10, '2026-01-20 13:45:00', 'Lesión deportiva', 'Dolor en rodilla derecha', 'Esguince grado I', 'Reposo, hielo, elevación', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dr. Pedro Gómez', NULL, NULL, NULL),
(9, 11, '2026-01-20 14:20:00', 'Hipertensión', 'Cefalea, mareos', 'Hipertensión arterial', 'Antihipertensivos, dieta baja en sal', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dra. Sofía Pérez', NULL, NULL, NULL),
(10, 12, '2026-01-20 15:10:00', 'Diabetes', 'Poliuria, polidipsia', 'Diabetes tipo 2', 'Metformina, dieta, ejercicio', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dr. Miguel Sánchez', NULL, NULL, NULL),
(11, 13, '2026-01-20 16:00:00', 'Alergia', 'Estornudos, congestión nasal', 'Rinitis alérgica', 'Antihistamínicos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dra. Isabel Ramírez', NULL, NULL, NULL),
(12, 14, '2026-01-20 17:30:00', 'Dolor lumbar', 'Dolor en zona lumbar', 'Lumbalgia mecánica', 'Analgésicos, fisioterapia', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dr. Francisco Torres', NULL, NULL, NULL),
(13, 15, '2026-01-20 18:15:00', 'Ansiedad', 'Insomnio, nerviosismo', 'Trastorno de ansiedad', 'Terapia, medicamentos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dra. Carmen Flores', NULL, NULL, NULL),
(14, 16, '2026-01-21 09:30:00', 'Gripe', 'Fiebre, dolor muscular', 'Influenza', 'Reposo, hidratación, antivirales', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dr. Ricardo Vázquez', NULL, NULL, NULL),
(15, 17, '2026-01-21 10:45:00', 'Control embarazo', 'Primer trimestre', 'Embarazo normal', 'Ácido fólico, controles', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dra. Patricia Díaz', NULL, NULL, NULL),
(16, 18, '2026-01-21 11:20:00', 'Artritis', 'Dolor articular', 'Artritis reumatoide', 'Antiinflamatorios', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dr. Jorge Morales', NULL, NULL, NULL),
(17, 19, '2026-01-21 12:30:00', 'Asma', 'Disnea, sibilancias', 'Crisis asmática', 'Broncodilatadores', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dra. Adriana Ortiz', NULL, NULL, NULL),
(18, 20, '2026-01-21 13:45:00', 'Gastritis', 'Dolor epigástrico', 'Gastritis aguda', 'Protectores gástricos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dr. Fernando Castro', NULL, NULL, NULL),
(19, 21, '2026-01-21 14:50:00', 'Depresión', 'Tristeza, falta de energía', 'Depresión moderada', 'Antidepresivos, terapia', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dra. Gabriela Romero', NULL, NULL, NULL),
(20, 22, '2026-01-21 15:35:00', 'Infección urinaria', 'Disuria, polaquiuria', 'Cistitis', 'Antibióticos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dr. Raúl Álvarez', NULL, NULL, NULL),
(21, 23, '2026-01-21 16:40:00', 'Dermatitis', 'Erupción cutánea', 'Dermatitis atópica', 'Corticoides tópicos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dra. Verónica Ruiz', NULL, NULL, NULL),
(22, 24, '2026-01-21 17:25:00', 'Obesidad', 'Sobrepeso', 'Obesidad grado I', 'Dieta, ejercicio', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dr. Oscar Jiménez', NULL, NULL, NULL),
(23, 25, '2026-01-22 09:15:00', 'Anemia', 'Fatiga, palidez', 'Anemia ferropénica', 'Suplementos de hierro', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dra. Diana Mendoza', NULL, NULL, NULL),
(24, 26, '2026-01-22 10:30:00', 'Insomnio', 'Dificultad para dormir', 'Trastorno del sueño', 'Higiene del sueño', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dr. Eduardo Guerrero', NULL, NULL, NULL),
(25, 27, '2026-01-22 11:45:00', 'Varices', 'Dolor en piernas', 'Insuficiencia venosa', 'Medias compresivas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dra. Claudia Rojas', NULL, NULL, NULL),
(26, 28, '2026-01-22 13:00:00', 'Catarata', 'Visión borrosa', 'Catarata senil', 'Cirugía programada', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dr. Antonio Salazar', NULL, NULL, NULL),
(27, 29, '2026-01-22 14:15:00', 'Osteoporosis', 'Dolor óseo', 'Osteoporosis posmenopáusica', 'Bifosfonatos, calcio', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dra. Teresa Molina', NULL, NULL, NULL),
(28, 30, '2026-01-23 09:30:00', 'Control pediátrico', 'Niño sano', 'Desarrollo normal', 'Vacunas al día', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dra. Isabel Ramírez', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventario`
--

CREATE TABLE `inventario` (
  `id_inventario` int NOT NULL,
  `codigo_barras` varchar(100) DEFAULT NULL,
  `nom_medicamento` varchar(100) NOT NULL,
  `mol_medicamento` varchar(100) NOT NULL,
  `presentacion_med` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `casa_farmaceutica` varchar(100) NOT NULL,
  `cantidad_med` int NOT NULL,
  `fecha_adquisicion` date NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `estado` enum('Disponible','Pendiente') DEFAULT 'Disponible',
  `id_purchase_item` int DEFAULT NULL,
  `precio_venta` decimal(10,2) DEFAULT '0.00',
  `precio_compra` decimal(10,2) DEFAULT '0.00',
  `precio_hospital` decimal(10,2) DEFAULT '0.00',
  `precio_medico` decimal(10,2) DEFAULT '0.00',
  `stock_hospital` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Volcado de datos para la tabla `inventario`
--

INSERT INTO `inventario` (`id_inventario`, `codigo_barras`, `nom_medicamento`, `mol_medicamento`, `presentacion_med`, `casa_farmaceutica`, `cantidad_med`, `fecha_adquisicion`, `fecha_vencimiento`, `estado`, `id_purchase_item`, `precio_venta`, `precio_compra`, `precio_hospital`, `precio_medico`, `stock_hospital`) VALUES
(4, 'prueba', 'Antigrip', 'Eucolapto-Guayacol', 'Ampolla', 'Servimedic', 5, '2026-01-16', '2028-05-01', 'Disponible', 4, 35.00, 0.00, 0.00, 0.00, 0),
(5, '7401094605493', 'Ibuvanz', 'Ibuprofeno100mg/5ml', 'Suspension', 'Servimedic', 5, '2026-01-16', '2029-08-01', 'Disponible', 5, 62.00, 0.00, 0.00, 0.00, 0),
(6, 'prueba2', 'Fungiter', 'Terbinafina 1g', 'Crema topica', 'Servimedic', 5, '2026-01-16', '2027-01-24', 'Disponible', 6, 140.00, 0.00, 0.00, 0.00, 0),
(7, NULL, 'D3-fENDER', 'Vitamina D3100,000UI', 'Capsula', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 7, 140.00, 0.00, 0.00, 0.00, 0),
(8, NULL, 'Bisocard 5mg', 'Bisoprolol famarato 5mg', 'Tableta', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 8, 270.00, 0.00, 0.00, 0.00, 0),
(9, NULL, 'Olmepress HCT 40/12.5mg', 'Olmesartan Medoxomil40mg+Hidroclorotiazida 12.5mg', 'Tableta', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 9, 350.00, 0.00, 0.00, 0.00, 0),
(10, NULL, 'Gacimex', 'Magaldrato 800mg/Simeticona 60mg/10ml', 'suspension', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 10, 155.00, 0.00, 0.00, 0.00, 0),
(11, NULL, 'Ultram D', 'Dutasterida 0.5+Tamsulona clorhidrato 0.4mg', 'Capsula', 'Servimedic', 4, '2026-01-16', '2026-01-16', 'Pendiente', 11, 600.00, 0.00, 0.00, 0.00, 0),
(12, NULL, 'Triacid', 'Pinaverium 100mg+Simethicone 300mg', 'Tableta', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 12, 230.00, 0.00, 0.00, 0.00, 0),
(13, NULL, 'Tónico de alfalfa R95', 'tónico de alfalfa', 'Suspensión', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 13, 210.00, 0.00, 0.00, 0.00, 0),
(14, NULL, 'Metiom H. pylori', 'esomeprazol-levofloxamina-amoxicilina', 'Cápsula', 'Servimedic', 4, '2026-01-16', '2026-01-16', 'Pendiente', 14, 630.00, 0.00, 0.00, 0.00, 0),
(15, NULL, 'Vertiless', 'Betahistina- diclorhidrato 16mg', 'Tableta', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 15, 180.00, 0.00, 0.00, 0.00, 0),
(16, NULL, 'Lyverium 1mg', 'Alprazolam 1mg', 'Tableta', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 16, 255.00, 0.00, 0.00, 0.00, 0),
(17, NULL, 'Lyverium 0.5mg', 'Alprazolam 0.5mg', 'Tableta', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 17, 150.00, 0.00, 0.00, 0.00, 0),
(18, NULL, 'Equiliv', 'clonazepam 2.5/ml', 'Gotero', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 18, 115.00, 0.00, 0.00, 0.00, 0),
(19, NULL, 'Atenua', 'dexketoprofeno 25mg', 'Comprimidos', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 19, 140.00, 0.00, 0.00, 0.00, 0),
(20, NULL, 'Sitalev Met', 'sitaglipina 50mg +metformina 500mg', 'Tabletas', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 20, 220.00, 0.00, 0.00, 0.00, 0),
(21, NULL, 'Inuric-G', 'Febuxostat 80mg', 'Tableta', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 21, 320.00, 0.00, 0.00, 0.00, 0),
(22, NULL, 'Gabin', 'Gabapentina 400mg', 'Tableta', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 22, 250.00, 0.00, 0.00, 0.00, 0),
(23, NULL, 'Atrolip Plus', 'atorvastatina 10mg + ezetimibe 10 mg', 'Comprimidos', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 23, 380.00, 0.00, 0.00, 0.00, 0),
(24, NULL, 'Glutamax C', 'Glutathione + vit C', 'Viales', 'Servimedic', 3, '2026-01-16', '2026-01-16', 'Pendiente', 24, 200.00, 0.00, 0.00, 0.00, 0),
(25, NULL, 'Rupagán', 'Rupatadina 1mg/ml.', 'Suspensión', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 25, 145.00, 0.00, 0.00, 0.00, 0),
(26, NULL, 'Biotos Inmune', 'Hedera helix & Pelargonium sidoides', 'Suspensión', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 26, 185.00, 0.00, 0.00, 0.00, 0),
(27, NULL, 'Biotos Inmune Pediátrico', 'Hedera Helix & Pelargonium sidoides', 'Suspensión', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 27, 135.00, 0.00, 0.00, 0.00, 0),
(28, NULL, 'Omega 1000', 'Omega 3', 'Cápsulas', 'Servimedic', 2, '2026-01-16', '2026-01-16', 'Pendiente', 28, 425.00, 0.00, 0.00, 0.00, 0),
(29, NULL, 'Aci-tip', 'Magaldrato 800mg - simeticona 40mg', 'Comprimidos', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 29, 120.00, 0.00, 0.00, 0.00, 0),
(30, '', 'Neuralplus', 'Tiamina, piridoxina, cianocobalamina, diclofenaco', '10 Tabletas', 'Servimedic', 4, '2026-01-16', '2027-11-01', 'Disponible', 30, 115.00, 0.00, 0.00, 0.00, 0),
(31, NULL, 'Kardiopil HCT', 'Irbesartán 300mg + hidroclorotiazida 12.5 mg', 'Tableta', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 31, 250.00, 0.00, 0.00, 0.00, 0),
(32, NULL, 'Milenium', 'esomeprazol 40mg', 'Cápsula', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 32, 250.00, 0.00, 0.00, 0.00, 0),
(33, NULL, 'Denk man active', 'extraxto de ginkgo, arginina', 'Cápsula', 'Servimedic', 3, '2026-01-16', '2026-01-16', 'Pendiente', 33, 220.00, 0.00, 0.00, 0.00, 0),
(34, NULL, 'Inmuno biter', 'extracto glicerinado de jara+tomillo', 'Ampolla bebible', 'Servimedic', 3, '2026-01-16', '2026-01-16', 'Pendiente', 34, 390.00, 0.00, 0.00, 0.00, 0),
(35, NULL, 'Spacek', 'Bromuro de otilonio 40mg', 'Tabletas', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 35, 170.00, 0.00, 0.00, 0.00, 0),
(36, NULL, 'Spirocard', 'spironolactone 100mg', 'Tableta', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 36, 260.00, 0.00, 0.00, 0.00, 0),
(37, NULL, 'Kardiopil Amlo', 'Irbesartan 300mg + Amlodipine 5mg', 'Tableta', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 37, 410.00, 0.00, 0.00, 0.00, 0),
(38, NULL, 'Gabex', 'Gabapentin 300mg', 'Cápsula', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 38, 200.00, 0.00, 0.00, 0.00, 0),
(39, NULL, 'biobronq', 'Hedera Helix 35mg/5ml', 'Suspensión', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 39, 80.00, 0.00, 0.00, 0.00, 0),
(40, NULL, 'Disolflem', 'Acetilcisteína', 'sticks granulado', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 40, 105.00, 0.00, 0.00, 0.00, 0),
(41, NULL, 'Uroprin', 'Fosfomicina 3g', 'Sticks granulado', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 41, 165.00, 0.00, 0.00, 0.00, 0),
(42, '7401094610121', 'Clevium 25mg/10ml', 'Desketoprofen (Trometamol) 25mg/10ml', '10 Sobres Bebible', 'Servimedic', 2, '2026-01-16', '2028-01-01', 'Disponible', 42, 140.00, 0.00, 0.00, 0.00, 0),
(43, '7401094604649', 'Clevium 30g', 'Dexketoprofeno 1.25%', 'Gel tópico', 'Servimedic', 1, '2026-01-16', '2029-08-01', 'Disponible', 43, 80.00, 0.00, 0.00, 0.00, 0),
(44, NULL, 'Flavia', 'Melatonina, calcio', 'Tabletas', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 44, 250.00, 0.00, 0.00, 0.00, 0),
(45, NULL, 'Demilos', 'carbonato de calcio colecalciferol, vitamina d3', 'Comprimidos', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 45, 215.00, 0.00, 0.00, 0.00, 0),
(46, NULL, 'Zefalox', 'cefixime 400mg', '20 Cápsulas', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 46, 650.00, 0.00, 0.00, 0.00, 0),
(47, NULL, 'Zefalox', 'Cefixima 100mg/5ml', 'Suspensión 50ml', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 47, 205.00, 0.00, 0.00, 0.00, 0),
(48, NULL, 'Zefalox', 'Cefixima', 'Suspesión 100ml', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 48, 300.00, 0.00, 0.00, 0.00, 0),
(49, NULL, 'Conflexil Plus Shot', 'tiocolchicosido 4mg-diclofenaco 50mh', 'Sticks bebible', 'Servimedic', 100, '2026-01-16', '2026-01-16', 'Pendiente', 49, 22.00, 0.00, 0.00, 0.00, 0),
(50, NULL, 'Rofemed', 'ceftriaxona 1g', 'Vial', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 50, 120.00, 0.00, 0.00, 0.00, 0),
(51, NULL, 'Milenium', 'esomeprazol 20ml', '30 Cápsulas', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 51, 200.00, 0.00, 0.00, 0.00, 0),
(52, NULL, 'Gadavyt fibra liquida', 'Fibra dietética jugo natural de ciruela', 'Suspensión', 'Servimedic', 2, '2026-01-16', '2026-01-16', 'Pendiente', 52, 345.00, 0.00, 0.00, 0.00, 0),
(53, NULL, 'Fungiter', 'Terbinafine HCI 1%', 'Spray', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 53, 100.00, 0.00, 0.00, 0.00, 0),
(54, NULL, 'Fungiter', 'Terbinafine 250 mg', '28 Tabletas', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 54, 545.00, 0.00, 0.00, 0.00, 0),
(55, NULL, 'Septidex', 'Polimixina. neomicina 40g', 'Spray', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 55, 105.00, 0.00, 0.00, 0.00, 0),
(56, NULL, 'Dinivanz', 'Salbutamol, salino solucion', 'Solución p/ nebulizar', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 56, 130.00, 0.00, 0.00, 0.00, 0),
(57, NULL, 'Hicet', 'Cetirizina diclorhidrato 10mg/ml', 'Gotas pediátricas', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 57, 105.00, 0.00, 0.00, 0.00, 0),
(58, NULL, 'Hicet', 'Cetirizina diclorhidrato 5mg/ml', 'Jarabe 120ml', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 58, 140.00, 0.00, 0.00, 0.00, 0),
(59, NULL, 'Hicet', 'Cetirizina diclorhidrato 5mg/5ml', 'Jarabe 60ml', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 59, 90.00, 0.00, 0.00, 0.00, 0),
(60, NULL, 'Hicet', 'Cetirizina diclorhidrato 10mg', '10 Cápsulas', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 60, 90.00, 0.00, 0.00, 0.00, 0),
(61, NULL, 'Gabex Plus', 'Gabapentina + vitamina B1 y B12', '30 Tabletas recubiertas', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 61, 350.00, 0.00, 0.00, 0.00, 0),
(62, NULL, 'Levent-Vit-E', 'vitamina E', '30 Cápsulas', 'Servimedic', 3, '2026-01-16', '2026-01-16', 'Pendiente', 62, 280.00, 0.00, 0.00, 0.00, 0),
(63, NULL, 'Rosecol', 'Rosuvastatina 20mg', '30 Tabletas recubiertas', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 63, 235.00, 0.00, 0.00, 0.00, 0),
(64, NULL, 'Prednicet', 'Prednisolona 5mg', '20 Tabletas', 'Servimedic', 5, '2026-01-16', '2027-07-01', 'Disponible', 64, 85.00, 0.00, 0.00, 0.00, 0),
(65, NULL, 'Conflexil', 'Tiocolchicósido', 'Ampollas 4mg/2ml', 'Servimedic', 25, '2026-01-16', '2026-01-16', 'Pendiente', 65, 35.00, 0.00, 0.00, 0.00, 0),
(66, NULL, 'Viater Forte', 'ginseng, vitamina E, zinc', 'Viales bebibles', 'Servimedic', 1, '2026-01-16', '2026-01-16', 'Pendiente', 66, 300.00, 0.00, 0.00, 0.00, 0),
(67, NULL, 'Acla-med bid', 'amoxicilina 875mg, acido clavulanico 125mg', '14 tabletas recubiertas', 'Servimedic', 1, '2026-01-16', '2026-01-16', 'Pendiente', 67, 215.00, 0.00, 0.00, 0.00, 0),
(68, NULL, 'Symbio flor 1', 'enterococcusfaecalis', 'Suspension oral', 'Servimedic', 1, '2026-01-16', '2026-01-16', 'Pendiente', 68, 255.00, 0.00, 0.00, 0.00, 0),
(69, '7401095800965', 'Klevraxr', 'levetiracetam 500mg', '30 tabletas', 'Servimedic', 3, '2026-01-16', '2027-03-01', 'Disponible', 69, 170.00, 0.00, 0.00, 0.00, 0),
(70, NULL, 'Suganon', 'Evogliptina 5mg', '30 Comprimidos', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 70, 505.00, 0.00, 0.00, 0.00, 0),
(71, NULL, 'Zukermen Met', 'vildagliptina 50ml+metformina 1000mg', '30 Tabletas', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 71, 300.00, 0.00, 0.00, 0.00, 0),
(72, NULL, 'Tusivanz', 'dextromethorphan+carboxymethylcysteine', 'gotas pediatricas', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 72, 105.00, 0.00, 0.00, 0.00, 0),
(73, NULL, 'Budoxigen', 'Budesonida 50mcg/100mcl', 'spray 200 aplicaciones', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 73, 190.00, 0.00, 0.00, 0.00, 0),
(74, NULL, 'Total Magnesiano', 'cloruro de magnesio 4.5H2O 1.5g + fluoruro de magnesio 0.0015g', 'Sobres efervecentes', 'Servimedic', 2, '2026-01-16', '2026-01-16', 'Pendiente', 74, 250.00, 0.00, 0.00, 0.00, 0),
(75, NULL, 'Acla-med', 'Amoxicilina 600mg+Acido clavulanico 42.9mg', 'Suspension', 'Servimedic', 3, '2026-01-16', '2026-01-16', 'Pendiente', 75, 175.00, 0.00, 0.00, 0.00, 0),
(76, NULL, 'Avsar Plus', 'valsartan 320mg+amlodipina 10mg+hidroclorotiazida 25mg', '28 Tabletas', 'Servimedic', 3, '2026-01-16', '2026-01-16', 'Pendiente', 76, 520.00, 0.00, 0.00, 0.00, 0),
(77, NULL, 'Deflarin', 'desflazacort 30mg', '10 comprimidos', 'Servimedic', 3, '2026-01-16', '2026-01-16', 'Pendiente', 77, 325.00, 0.00, 0.00, 0.00, 0),
(78, NULL, 'Disoflem', 'Acetilcisteina 200mg', 'Sobres Granulados', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 78, 105.00, 0.00, 0.00, 0.00, 0),
(79, NULL, 'Megamol', 'vitamina D3', '100 capsulas', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 79, 250.00, 0.00, 0.00, 0.00, 0),
(80, NULL, 'Diabilev', 'Metformina HCI 500mg', '30 Tabletas', 'Servimedic', 2, '2026-01-16', '2026-01-16', 'Pendiente', 80, 90.00, 0.00, 0.00, 0.00, 0),
(81, NULL, 'Denk immun active', 'Zinc, selenio', 'Sobres', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 81, 195.00, 0.00, 0.00, 0.00, 0),
(82, NULL, 'Melatina', 'Melatonina 10.53mg', 'Gotero', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 82, 160.00, 0.00, 0.00, 0.00, 0),
(83, NULL, 'Bru-sone', 'betametasona dipropionato 5mg+fosfato sodico 2mg', 'Ampolla', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 83, 190.00, 0.00, 0.00, 0.00, 0),
(84, NULL, 'Gastrexx plus', 'amoxicilina 1g+ levofloxacina 500mg', '28 capsulas', 'Servimedic', 3, '2026-01-16', '2026-01-16', 'Pendiente', 84, 480.00, 0.00, 0.00, 0.00, 0),
(85, NULL, 'Modepar', 'Nicotinamida 17.5mg, Acido Ascorbico 50mg', '60 Tabletas', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 85, 550.00, 0.00, 0.00, 0.00, 0),
(86, NULL, 'Adiaplex', 'Dapagliflozina 10mg', '30 Tabletas', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 86, 410.00, 0.00, 0.00, 0.00, 0),
(87, NULL, 'Glidap Max', 'Dapagliflozina 5mg+metformina HCI lp 1000mg', '30 tabletas', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 87, 300.00, 0.00, 0.00, 0.00, 0),
(88, NULL, 'Gesimax', 'Naproxeno sodico 550mg', '10 tabletas', 'Servimedic', 20, '2026-01-16', '2026-01-16', 'Pendiente', 88, 60.00, 0.00, 0.00, 0.00, 0),
(89, NULL, 'Lisinox', 'Propinoxato HCL 10mg+clonixinato de lisina 125mg', '10 Tabletas', 'Servimedic', 10, '2026-01-16', '2026-01-16', 'Pendiente', 89, 45.00, 0.00, 0.00, 0.00, 0),
(90, '7401130000534', 'Solocin Plus', 'pancreatina 400mg+simeticona 60mg+cinitaprina 1mg', '20 comprimidos', 'Servimedic', 5, '2026-01-16', '2027-03-01', 'Disponible', 90, 220.00, 0.00, 0.00, 0.00, 0),
(91, NULL, 'Ferrum 16', 'hierro, vitaminas y minerales', 'Jarabe 240ml', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 91, 120.00, 0.00, 0.00, 0.00, 0),
(92, NULL, 'Gadysen', 'Duloxetina 60mg', '30 capsulas', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 92, 560.00, 0.00, 0.00, 0.00, 0),
(93, NULL, 'Gadysen', 'Duloxetina 30mg', '30 capsulas', 'Servimedic', 3, '2026-01-16', '2026-01-16', 'Pendiente', 93, 510.00, 0.00, 0.00, 0.00, 0),
(94, NULL, 'Multiflora Adance', 'probiotico', '30 capsulas', 'Servimedic', 3, '2026-01-16', '2026-01-16', 'Pendiente', 94, 420.00, 0.00, 0.00, 0.00, 0),
(95, NULL, 'Estoma dol', 'trisilicato de magnesio, carbon vegetal', '30 capsulas', 'Servimedic', 2, '2026-01-16', '2026-01-16', 'Pendiente', 95, 140.00, 0.00, 0.00, 0.00, 0),
(96, NULL, 'Exlant', 'dexlansoprazol 30mg', '30 capsulas', 'Servimedic', 4, '2026-01-16', '2026-01-16', 'Pendiente', 96, 365.00, 0.00, 0.00, 0.00, 0),
(97, NULL, 'Ki-Cab', 'tegoprazan 50mg', '50 tabletas', 'Servimedic', 1, '2026-01-16', '2026-01-16', 'Pendiente', 97, 830.00, 0.00, 0.00, 0.00, 0),
(98, NULL, 'Lisinox', 'Propinoxato clorhidrato 5mg/ml', 'Gotas 20ml', 'Servimedic', 3, '2026-01-16', '2026-01-16', 'Pendiente', 98, 80.00, 0.00, 0.00, 0.00, 0),
(99, NULL, 'Probiocyan', 'lactobacillus plantarum, zinc 5mg', '30 capsulas', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 99, 230.00, 0.00, 0.00, 0.00, 0),
(100, NULL, 'Colitran', 'clordiazepoxido HCI/ Bromuro de clidinio', '10 grageas', 'Servimedic', 10, '2026-01-16', '2026-01-16', 'Pendiente', 100, 40.00, 0.00, 0.00, 0.00, 0),
(101, NULL, 'Sucralfato', 'sucralfato 1g', '40 Tabletas', 'Servimedic', 1, '2026-01-16', '2026-01-16', 'Pendiente', 101, 105.00, 0.00, 0.00, 0.00, 0),
(102, '7401018110621', 'Cetamin CC', 'Acetaminofen 325mg+codeina 15mg', '10 Tabletas', 'Servimedic', 5, '2026-01-16', '2029-09-01', 'Disponible', 102, 90.00, 0.00, 0.00, 0.00, 0),
(103, NULL, 'Tensinor Plus', 'Valsartan 160mg/hidroclorotiazida 12.5mg/amlodipino 5mg', '30 Tabletas', 'Servimedic', 2, '2026-01-16', '2026-01-16', 'Pendiente', 103, 480.00, 0.00, 0.00, 0.00, 0),
(104, NULL, 'Tensinor Plus', 'Valsartan 320mg/hidroclorotiazida 25mg/amlodipino 10mg', '30 Tabletas', 'Servimedic', 2, '2026-01-16', '2026-01-16', 'Pendiente', 104, 480.00, 0.00, 0.00, 0.00, 0),
(105, NULL, 'Metavan', 'metformina HCI 1000mg', '30 Tabletas', 'Servimedic', 1, '2026-01-16', '2026-01-16', 'Pendiente', 105, 245.00, 0.00, 0.00, 0.00, 0),
(106, NULL, 'FILINAR g', 'acebrifilina 5mg/ml', 'Suspension', 'Servimedic', 1, '2026-01-16', '2026-01-16', 'Pendiente', 106, 160.00, 0.00, 0.00, 0.00, 0),
(107, NULL, 'Myo & D-Chiro Inositol', 'inositol chiro', '90 capsulas', 'Servimedic', 2, '2026-01-16', '2026-01-16', 'Pendiente', 107, 470.00, 0.00, 0.00, 0.00, 0),
(108, NULL, 'Gastroflux', 'domperidona 1mg/ml', 'suspension', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 108, 235.00, 0.00, 0.00, 0.00, 0),
(109, NULL, 'Careject', 'aceite de soja, glicerol', 'Spray nasal', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 109, 150.00, 0.00, 0.00, 0.00, 0),
(110, '2350735122123', 'Aidex 25mg/10ml', 'dexketoprofeno 25mg/10ml', '10 Sobres bebibles 10ml', 'Servimedic', 5, '2026-01-16', '2027-07-01', 'Disponible', 110, 110.00, 0.00, 0.00, 0.00, 0),
(111, NULL, 'Rusitan', 'Rupatadina fumarato 1mg/ml', 'Suspension', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 111, 175.00, 0.00, 0.00, 0.00, 0),
(112, NULL, 'Acetaminofen lancasco', 'acetaminofen 120/5ml', 'Suspension', 'Servimedic', 3, '2026-01-16', '2026-01-16', 'Pendiente', 112, 30.00, 0.00, 0.00, 0.00, 0),
(113, NULL, 'Bucaglu', 'ruibarbo y acido salicilico', 'Tintura Oral', 'Servimedic', 3, '2026-01-16', '2026-01-16', 'Pendiente', 113, 130.00, 0.00, 0.00, 0.00, 0),
(114, NULL, 'Contractil', 'tiocolchicosido 4mg', '10 Tabletas', 'Servimedic', 3, '2026-01-16', '2026-01-16', 'Pendiente', 114, 130.00, 0.00, 0.00, 0.00, 0),
(115, NULL, 'Etoricox', 'Etoricoxib 120mg', '14 Tabletas', 'Servimedic', 1, '2026-01-16', '2026-01-16', 'Pendiente', 115, 400.00, 0.00, 0.00, 0.00, 0),
(116, NULL, 'Isocraneol', 'Citicolina 500mg', '30 Comprimidos', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 116, 500.00, 0.00, 0.00, 0.00, 0),
(117, NULL, 'Rodiflux', 'Dextrometorfan, carboximetilcisteina, clorfeniramina', 'Gotero', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 117, 110.00, 0.00, 0.00, 0.00, 0),
(118, NULL, 'Gebrix-G 240ml', 'Jengibre, Equinacea, vitamina C', 'Suspension', 'Servimedic', 3, '2026-01-16', '2026-01-16', 'Pendiente', 118, 200.00, 0.00, 0.00, 0.00, 0),
(119, NULL, 'Zirtraler-D 60ml', 'Cetirizina HCI, Fenilefrina HCI', 'Suspension', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 119, 125.00, 0.00, 0.00, 0.00, 0),
(120, NULL, 'Neo-melubrina', 'Metamizol sodico 250mg/5ml', 'Jarabe 100ml', 'Servimedic', 2, '2026-01-16', '2026-01-16', 'Pendiente', 120, 75.00, 0.00, 0.00, 0.00, 0),
(121, NULL, 'Neobol', 'neomicina- clostebol', 'Spray 30g', 'Servimedic', 2, '2026-01-16', '2026-01-16', 'Pendiente', 121, 135.00, 0.00, 0.00, 0.00, 0),
(122, NULL, 'Mero Clav', 'cefuroxima+ acido clavulanico', 'suspension 70ml', 'Servimedic', 2, '2026-01-16', '2026-01-16', 'Pendiente', 122, 250.00, 0.00, 0.00, 0.00, 0),
(123, NULL, 'Dexamicina', 'Dexametazona/neomicina', 'Gotero Oftalmico 5ml', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 123, 55.00, 0.00, 0.00, 0.00, 0),
(124, NULL, 'Aciclovirax', 'Aciclovir pediatrico', 'Suspension 120ml', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 124, 200.00, 0.00, 0.00, 0.00, 0),
(125, NULL, 'Bencidamin', 'Bencidamina', 'Spray bucal', 'Servimedic', 2, '2026-01-16', '2026-01-16', 'Pendiente', 125, 90.00, 0.00, 0.00, 0.00, 0),
(126, NULL, 'Metronis', 'Nitazoxanida 100mg/5ml', 'suspension', 'Servimedic', 2, '2026-01-16', '2026-01-16', 'Pendiente', 126, 80.00, 0.00, 0.00, 0.00, 0),
(127, NULL, 'Sinedol Forte', 'Acetaminofen 750mg', '10 Tabletas', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 127, 45.00, 0.00, 0.00, 0.00, 0),
(128, NULL, 'Mucarbol Pediatrico', 'Carbocisteina 100mg/5ml', 'Jarabe', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 128, 65.00, 0.00, 0.00, 0.00, 0),
(129, NULL, 'Mucarbol Adulto', 'Carbocisteina 750mg/15ml', 'Jarabe', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 129, 70.00, 0.00, 0.00, 0.00, 0),
(130, NULL, 'Neo-Melubrina', 'Metamizol 500mg', '4 Tabletas', 'Servimedic', 25, '2026-01-16', '2026-01-16', 'Pendiente', 130, 15.00, 0.00, 0.00, 0.00, 0),
(131, NULL, 'AGE III', 'cucurbita pepo. africanum', '30 Capsulas', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 131, 200.00, 0.00, 0.00, 0.00, 0),
(132, NULL, 'Sertal Forte Perlas', 'Propinox Clorhidrato 20mf', '10 capsulas', 'Servimedic', 6, '2026-01-16', '2026-01-16', 'Pendiente', 132, 90.00, 0.00, 0.00, 0.00, 0),
(133, '', 'Ardix 25mg', 'dexketoprofeno 25mg', '10 Tabletas', 'Servimedic', 5, '2026-01-16', '2027-02-01', 'Disponible', 133, 95.00, 0.00, 0.00, 0.00, 0),
(134, NULL, 'Wen vision', 'Dexametasona, neomicina', 'Gotero Oftalmico 5ml', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 134, 55.00, 0.00, 0.00, 0.00, 0),
(135, NULL, 'Selenio+Vit E', 'Vitamina E 1000UI+ Selenio 200', '60 Capsulas', 'Servimedic', 2, '2026-01-16', '2026-01-16', 'Pendiente', 135, 175.00, 0.00, 0.00, 0.00, 0),
(136, NULL, 'Brucort-A', 'Triamcinolona acetonido 0.1%', 'Crema Topica', 'Servimedic', 4, '2026-01-16', '2026-01-16', 'Pendiente', 136, 110.00, 0.00, 0.00, 0.00, 0),
(137, NULL, 'Uxbi', 'Acido ursodesoxicolico 250mg', '30 capsulas', 'Servimedic', 2, '2026-01-16', '2026-01-16', 'Pendiente', 137, 375.00, 0.00, 0.00, 0.00, 0),
(138, NULL, 'Allopurikem', 'alopurinol 300mg', '10 Tabletas', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 138, 75.00, 0.00, 0.00, 0.00, 0),
(139, NULL, 'Deka-C Adultos', 'vitaminas A, D, E y C', 'Ampollas bebibles 5ml', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 139, 75.00, 0.00, 0.00, 0.00, 0),
(140, NULL, 'Rexacort', 'mometasona furoato 50pg', 'Spray nasal 18g', 'Servimedic', 3, '2026-01-16', '2026-01-16', 'Pendiente', 140, 130.00, 0.00, 0.00, 0.00, 0),
(141, NULL, 'Histakem Block', 'Cloruro de cetilpiridinio 0.05g+benzocaina 1.0g', 'Spray bucal 30ml', 'Servimedic', 2, '2026-01-16', '2026-01-16', 'Pendiente', 141, 125.00, 0.00, 0.00, 0.00, 0),
(142, NULL, 'Colchinet', 'Colchicina 0.5 mg', '20 Tabletas', 'Servimedic', 15, '2026-01-16', '2026-01-16', 'Pendiente', 142, 65.00, 0.00, 0.00, 0.00, 0),
(143, NULL, 'Triglix', 'Fenofibrato 160mg', '40 capsulas', 'Servimedic', 4, '2026-01-16', '2026-01-16', 'Pendiente', 143, 390.00, 0.00, 0.00, 0.00, 0),
(144, NULL, 'Equiliv', 'Clonazepan 2mg', '30 Tabletas', 'Servimedic', 5, '2026-01-16', '2026-01-16', 'Pendiente', 144, 135.00, 0.00, 0.00, 0.00, 0),
(145, NULL, 'ESOGASTRIC 10MG', 'ESOMEPRAZOL', '15 SOBRES', 'Servimedic', 2, '2026-01-24', '2027-01-24', 'Pendiente', 145, 165.00, 98.12, 98.12, 98.12, 0),
(146, NULL, 'SPASMO-UROLONG', 'NITROFURANTOINA 75MG', '10 COMPRIMIDOS', 'Servimedic', 2, '2026-01-24', '2027-01-24', 'Pendiente', 146, 80.00, 43.00, 43.00, 43.00, 0),
(147, NULL, 'Burts bees baby', 'esencia coco', 'rolon', 'Servimedic', 3, '2026-01-24', '2027-01-24', 'Pendiente', 147, 105.00, 30.00, 30.00, 30.00, 0),
(148, NULL, 'propix-duo', 'propinoxato15mg/clonixinato de lisina 100mg', 'ampolla', 'Servimedic', 6, '2026-01-24', '2027-01-24', 'Pendiente', 148, 50.00, 26.10, 26.10, 26.10, 0),
(149, NULL, 'ovumix', 'metronidazol, sulfato neomicina, centella asiatica', 'ovulos vaginales', 'Servimedic', 1, '2026-01-24', '2027-01-24', 'Pendiente', 149, 255.00, 172.26, 172.26, 172.26, 0),
(150, NULL, 'Gesimax 150mg/5ml', 'naproxeno', 'suspension 60ml', 'Servimedic', 2, '2026-01-24', '2027-01-24', 'Pendiente', 150, 65.00, 40.00, 40.00, 40.00, 0),
(151, NULL, 'Paracetamol Denk 500mg', 'Paracetamol', '20 comprimidos', 'Servimedic', 2, '2026-01-24', '2027-01-24', 'Pendiente', 151, 50.00, 29.50, 29.50, 29.50, 0),
(152, NULL, 'Dolvi plex', 'Metamizol 500mg', '10 tabletas', 'Servimedic', 1, '2026-01-24', '2027-01-24', 'Pendiente', 152, 20.00, 9.00, 9.00, 9.00, 0),
(153, NULL, 'Melanoblock', 'aqua cetearyl alcohol', 'Crema Facial', 'Servimedic', 5, '2026-01-24', '2027-01-24', 'Pendiente', 153, 375.00, 162.00, 162.00, 162.00, 0),
(154, NULL, 'regenhial crema', 'Acido hialuronico 1%', 'Crema Facial', 'Servimedic', 4, '2026-01-24', '2027-01-24', 'Pendiente', 154, 450.00, 282.85, 282.85, 282.85, 0),
(155, NULL, 'Regenhial Gel', 'Acido hialuronico 1%', 'Crema Facial', 'Servimedic', 3, '2026-01-24', '2027-01-24', 'Pendiente', 155, 275.00, 194.00, 194.00, 194.00, 0),
(156, NULL, 'Hidribet 10%', 'Glicerin, sorbitan', 'Locion topica', 'Servimedic', 1, '2026-01-24', '2027-01-24', 'Pendiente', 156, 125.00, 74.15, 74.15, 74.15, 0),
(157, NULL, 'Umbrella', 'aqua,penylene glycol', 'Protector solar facial', 'Servimedic', 2, '2026-01-24', '2027-01-24', 'Pendiente', 157, 225.00, 165.64, 165.64, 165.64, 0),
(158, NULL, 'Figure active', 'carnitina,triptofano,buchu', '14 sobres', 'Servimedic', 3, '2026-01-24', '2027-01-24', 'Pendiente', 158, 300.00, 217.90, 217.90, 217.90, 0),
(159, NULL, 'Ureactiv 10%', 'carbamida -urea', 'Crema humectante', 'Servimedic', 1, '2026-01-24', '2027-01-24', 'Pendiente', 159, 155.00, 95.42, 95.42, 95.42, 0),
(160, NULL, 'Regenhial Gel Oral', 'Acido hialuronico 250mg', 'Enjuague bucal', 'Servimedic', 4, '2026-01-24', '2027-01-24', 'Pendiente', 160, 200.00, 110.00, 110.00, 110.00, 0),
(161, NULL, 'Claribac 500mg', 'Claritromicina', '10 tabletas', 'Servimedic', 2, '2026-01-24', '2027-01-24', 'Pendiente', 161, 325.00, 151.46, 151.46, 151.46, 0),
(162, NULL, 'Unocef 400mg', 'Cefixima', '8 Comprimidos', 'Servimedic', 5, '2026-01-24', '2027-01-24', 'Pendiente', 162, 300.00, 201.35, 201.35, 201.35, 0),
(163, NULL, 'Quinolide 500mg', 'Ciprofloxacina', '10 tabletas', 'Servimedic', 14, '2026-01-24', '2027-01-24', 'Pendiente', 163, 100.00, 27.50, 27.50, 27.50, 0),
(164, NULL, 'Supraxil 1g', 'Ceftriaxona', 'Vial', 'Servimedic', 2, '2026-01-24', '2027-01-24', 'Pendiente', 164, 130.00, 45.00, 45.00, 45.00, 0),
(165, NULL, 'Tiamina 100mg', 'Tiamina 10ml', 'Vial', 'Servimedic', 3, '2026-01-24', '2027-01-24', 'Pendiente', 165, 25.00, 9.00, 9.00, 9.00, 0),
(166, NULL, 'Complejo B', 'Complejo B 10ML', 'Vial', 'Servimedic', 3, '2026-01-24', '2027-01-24', 'Pendiente', 166, 25.00, 12.00, 12.00, 12.00, 0),
(167, NULL, 'Celedexa', 'Betametasona dexclorfeniramina', 'Jarabe 120ml', 'Servimedic', 5, '2026-01-24', '2027-01-24', 'Pendiente', 167, 140.00, 72.80, 72.80, 72.80, 0),
(168, NULL, 'Indugastric 120ml', 'regaliz,resina,', 'Jarabe', 'Servimedic', 1, '2026-01-24', '2027-01-24', 'Pendiente', 168, 210.00, 118.14, 118.14, 118.14, 0),
(169, NULL, 'Ambiare', 'Dexclorfeniramina,betametasona', '10 Tabletas', 'Servimedic', 2, '2026-01-24', '2027-01-24', 'Pendiente', 169, 55.00, 35.00, 35.00, 35.00, 0),
(170, NULL, 'Fenobrox', 'Cloperastina', 'suspension', 'Servimedic', 4, '2026-01-24', '2027-01-24', 'Pendiente', 170, 110.00, 36.00, 36.00, 36.00, 0),
(171, NULL, 'Acla-Med Bid 400mg', 'Amoxicilina+acido clavulanico', 'Suspension', 'Servimedic', 4, '2026-01-24', '2027-01-24', 'Pendiente', 171, 125.00, 51.20, 51.20, 51.20, 0),
(172, NULL, 'Vaginsol F', 'Clindamicina100mg+clotrimazol 200mg', '7 ovulos vaginales', 'Servimedic', 2, '2026-01-24', '2027-01-24', 'Pendiente', 172, 360.00, 244.00, 244.00, 244.00, 0),
(173, NULL, 'Ferra Q', 'Acido folico1000mcg+hierro aminoquelado 30mg', '30 Capsulas', 'Servimedic', 1, '2026-01-24', '2027-01-24', 'Pendiente', 173, 115.00, 55.20, 55.20, 55.20, 0),
(174, NULL, 'Hepamob', 'Cilimarina+complejo b', '30 Comprimidos', 'Servimedic', 2, '2026-01-24', '2027-01-24', 'Pendiente', 174, 150.00, 90.00, 90.00, 90.00, 0),
(175, NULL, 'Prednitab 50mg', 'Prednisona', '20 Tabletas', 'Servimedic', 4, '2026-01-24', '2027-01-24', 'Pendiente', 175, 385.00, 265.30, 265.30, 265.30, 0),
(176, NULL, 'Lansogastric 15Mg', 'Lansoprazol', '15 Sobres', 'Servimedic', 3, '2026-01-24', '2027-01-24', 'Pendiente', 176, 90.00, 34.00, 34.00, 34.00, 0),
(177, NULL, 'Sargikem', 'Aspartato de L arginina', '30 Capsulas', 'Servimedic', 1, '2026-01-24', '2027-01-24', 'Pendiente', 177, 165.00, 83.60, 83.60, 83.60, 0),
(178, NULL, 'Lergiless', 'loratadina 5mg/betametasona 0.25mg', 'Jarabe 60ml', 'Servimedic', 2, '2026-01-24', '2027-01-24', 'Pendiente', 178, 110.00, 64.00, 64.00, 64.00, 0),
(179, NULL, 'Oriprox-M', 'Moxifloxacino 400mg', '10 Tabletas', 'Servimedic', 5, '2026-01-24', '2027-01-24', 'Pendiente', 179, 400.00, 225.00, 225.00, 225.00, 0),
(180, NULL, 'Tibonella', 'Tibolona 2.5mg', '28 Tabletas', 'Servimedic', 4, '2026-01-24', '2027-01-24', 'Pendiente', 180, 290.00, 170.00, 170.00, 170.00, 0),
(181, NULL, 'Metocarban AC', 'Metocarbamol400mg/acetaminofen 250mg', '30 Tabletas', 'Servimedic', 3, '2026-01-24', '2027-01-24', 'Pendiente', 181, 110.00, 60.20, 60.20, 60.20, 0),
(182, NULL, 'Dyflam', 'Diclofenaco resinato', 'Gotas 15ml', 'Servimedic', 5, '2026-01-24', '2027-01-24', 'Pendiente', 182, 50.00, 21.40, 21.40, 21.40, 0),
(183, NULL, 'Cefina 100mg/5ml', 'Cefixima', 'Suspension 100ml', 'Servimedic', 1, '2026-01-24', '2027-01-24', 'Pendiente', 183, 220.00, 90.00, 90.00, 90.00, 0),
(184, NULL, 'Floxa-Pack 10 Dias', 'Lansoprazol 30mg/levofloxacina 500mg/amoxicilina 500mg', '10 Comprimidos', 'Servimedic', 2, '2026-01-24', '2027-01-24', 'Pendiente', 184, 450.00, 190.00, 190.00, 190.00, 0),
(185, NULL, 'Floxa- Pack ES 10 Dias', 'Esomeprazol 40mg/levofloxacina 500mg/amoxicilina 500mg', '10 Comprimidos', 'Servimedic', 1, '2026-01-24', '2027-01-24', 'Pendiente', 185, 515.00, 213.00, 213.00, 213.00, 0),
(186, NULL, 'Arginina Junior', 'aspartato de arginina 1g/5ml', '10 ampollas bebibles', 'Servimedic', 2, '2026-01-24', '2027-01-24', 'Pendiente', 186, 95.00, 70.00, 70.00, 70.00, 0),
(187, NULL, 'Arginina Forte', 'Aspartato de arginina 5g/10ml', '10 ampollas bebibles', 'Servimedic', 2, '2026-01-24', '2027-01-24', 'Pendiente', 187, 135.00, 98.00, 98.00, 98.00, 0),
(188, NULL, 'Redical', 'Esomeprazol 10mg', '28 Sobres', 'Servimedic', 1, '2026-01-24', '2027-01-24', 'Pendiente', 188, 420.00, 214.60, 214.60, 214.60, 0),
(189, NULL, 'Gripcol D', 'Fenilefrina,dextrometorfano,acetaminofen', 'Susspencion 120ml', 'Servimedic', 1, '2026-01-24', '2027-01-24', 'Pendiente', 189, 55.00, 28.00, 28.00, 28.00, 0),
(190, NULL, 'Deflarin 6mg', 'Deflazacort', '10 Comprimidos', 'Servimedic', 5, '2026-01-24', '2027-01-24', 'Pendiente', 190, 135.00, 77.00, 77.00, 77.00, 0),
(191, NULL, 'Totalvit ZINC', 'Sulfatode zinc 20mg', 'Jarabe 120ml', 'Servimedic', 2, '2026-01-24', '2027-01-24', 'Pendiente', 191, 110.00, 40.00, 40.00, 40.00, 0),
(192, NULL, 'Musculare 10mg', 'Clorhidrato de ciclobenzaprina', '15 Tabletas', 'Servimedic', 5, '2026-01-24', '2027-01-24', 'Pendiente', 192, 145.00, 102.28, 102.28, 102.28, 0),
(193, NULL, 'Musculare 5mg', 'Clorhidrato de ciclobenzaprina', '15 Tabletas', 'Servimedic', 5, '2026-01-24', '2027-01-24', 'Pendiente', 193, 125.00, 91.58, 91.58, 91.58, 0),
(194, NULL, 'Dyflam 120ml', 'Diclofenaco 9mg/5ml', 'Suspension', 'Servimedic', 5, '2026-01-24', '2027-01-24', 'Pendiente', 194, 65.00, 34.00, 34.00, 34.00, 0),
(195, NULL, 'Broncodil 120ml', 'Carboximetilcisteina', 'Suapension', 'Servimedic', 5, '2026-01-24', '2027-01-24', 'Pendiente', 195, 110.00, 40.00, 40.00, 40.00, 0),
(196, NULL, 'Gastrexx 40mg', 'Esomeprazol', '15 Capsulas', 'Servimedic', 5, '2026-01-24', '2027-01-24', 'Pendiente', 196, 600.00, 220.26, 220.26, 220.26, 0),
(197, NULL, 'Levamisol 12.5mg/5ml', 'Diclofenaco 50mg+tiocolchicosico', 'Sobres bebibles', 'Servimedic', 50, '2026-01-24', '2027-01-24', 'Pendiente', 197, 22.00, 12.38, 12.38, 12.38, 0),
(198, NULL, 'Nocicep 10mg', 'Rupatadina', '10 Tabletas', 'Servimedic', 4, '2026-01-24', '2027-01-24', 'Pendiente', 198, 130.00, 56.40, 56.40, 56.40, 0),
(199, NULL, 'Levax', 'Levamisol 12.5mg/5ml', 'Suspension 120ml', 'Servimedic', 2, '2026-01-24', '2027-01-24', 'Pendiente', 199, 100.00, 61.60, 61.60, 61.60, 0),
(200, NULL, 'Levax', 'Levamisol 75mg', '10 tabletas', 'Servimedic', 2, '2026-01-24', '2027-01-24', 'Pendiente', 200, 165.00, 107.10, 107.10, 107.10, 0),
(201, NULL, 'Sinervit', 'Tiamina,piridoxina,cianocobalamina', '30 Capsulas', 'Servimedic', 1, '2026-01-24', '2027-01-24', 'Pendiente', 201, 190.00, 90.00, 90.00, 90.00, 0),
(202, NULL, 'Dinivanz Compuesto', 'Bromuro de ipatropium/salino/salbutamol', 'kit para nebulizar', 'Servimedic', 5, '2026-01-24', '2027-01-24', 'Pendiente', 202, 240.00, 103.44, 103.44, 103.44, 0),
(203, NULL, 'Betasporina', 'Ceftriaxona 1g', 'Vial', 'Servimedic', 10, '2026-01-24', '2027-01-24', 'Pendiente', 203, 140.00, 55.00, 55.00, 55.00, 0),
(204, NULL, 'Ceftrian', 'Ceftriaxona 1g', 'Vial', 'Servimedic', 3, '2026-01-24', '2027-01-24', 'Pendiente', 204, 110.00, 35.00, 35.00, 35.00, 0),
(205, NULL, 'Dipronova', 'Betamethasone dipropionate', 'Vial', 'Servimedic', 1, '2026-01-24', '2027-01-24', 'Pendiente', 205, 180.00, 60.00, 60.00, 60.00, 0),
(206, NULL, 'Esomeprakem', 'Esomeprazol 40mg/levofloxacina 500mg/amoxicilina 500mg', '10 Capsulas', 'Servimedic', 3, '2026-01-24', '2027-01-24', 'Pendiente', 206, 70.00, 36.00, 36.00, 36.00, 0),
(207, NULL, 'Nocpidem', 'Zolpidem 10mg', '30 Comprimidos', 'Servimedic', 3, '2026-01-24', '2027-01-24', 'Pendiente', 207, 350.00, 225.60, 225.60, 225.60, 0),
(208, NULL, 'Triviplex 25000', 'Vitaminas B12,B2,B12', 'Ampolla 2ml', 'Servimedic', 5, '2026-01-24', '2027-01-24', 'Pendiente', 208, 45.00, 19.00, 19.00, 19.00, 0),
(209, NULL, 'Dexa-triviplex', 'Vitaminas neurotropas+dexa', '2 ampollas', 'Servimedic', 5, '2026-01-24', '2027-01-24', 'Pendiente', 209, 55.00, 29.00, 29.00, 29.00, 0),
(210, NULL, 'Dolo Triviplex', 'Diclofenaco+vitaminas', '2 ampollas', 'Servimedic', 10, '2026-01-24', '2027-01-24', 'Pendiente', 210, 50.00, 23.00, 23.00, 23.00, 0),
(211, NULL, 'Suero Hidravida', 'sabor coco', 'suero oral', 'Servimedic', 12, '2026-01-24', '2027-01-24', 'Pendiente', 211, 18.00, 14.30, 14.30, 14.30, 0),
(212, NULL, 'Ledestil', 'carbohidratos,lipidos totales', 'ampollas', 'Servimedic', 24, '2026-01-24', '2027-01-24', 'Pendiente', 212, 100.00, 52.33, 52.33, 52.33, 0),
(213, NULL, 'Agujas Hipodermicas', '31GX3/16', '100 Agujas', 'Servimedic', 5, '2026-01-24', '2027-01-24', 'Pendiente', 213, 140.00, 90.00, 90.00, 90.00, 0),
(214, NULL, 'Enna', '', 'Esfera', 'Servimedic', 1, '2026-01-24', '2027-01-24', 'Pendiente', 214, 450.00, 0.00, 0.00, 0.00, 0),
(215, NULL, 'Nircip', 'Ciprofloxacina 200mg/100m', 'Frasco Inyectable', 'Servimedic', 6, '2026-01-24', '2027-01-24', 'Pendiente', 215, 80.00, 23.00, 23.00, 23.00, 0),
(216, NULL, 'Ampidelt', 'Ampi+sulbactam', 'Vial', 'Servimedic', 30, '2026-01-24', '2027-01-24', 'Pendiente', 216, 80.00, 15.75, 15.75, 15.75, 0),
(217, NULL, 'Tiamina bonin', 'Tiamina', 'Vial', 'Servimedic', 10, '2026-01-24', '2027-01-24', 'Pendiente', 217, 25.00, 9.10, 9.10, 9.10, 0),
(218, NULL, 'Fluconazol 100ml', 'Fluconazol 200mg/100ml', 'Frasco Inyectable', 'Servimedic', 2, '2026-01-24', '2027-01-24', 'Pendiente', 218, 0.00, 32.70, 32.70, 32.70, 0),
(219, NULL, 'Bactemicina 600mg/4ml', 'Clindamicina', 'Ampolla', 'Servimedic', 5, '2026-01-24', '2027-01-24', 'Pendiente', 219, 0.00, 30.50, 30.50, 30.50, 0),
(220, NULL, 'Jeringas de 20ml', 'Insumo', 'Insumo', 'Servimedic', 195, '2026-01-24', '2027-01-24', 'Pendiente', 220, 0.00, 1.70, 1.70, 1.70, 0),
(221, NULL, 'Jeringas de 3ml', 'Insumo', 'Insumo', 'Servimedic', 290, '2026-01-24', '2027-01-24', 'Pendiente', 221, 0.00, 0.73, 0.73, 0.73, 0),
(222, NULL, 'Jeringa de 1ml', 'Insumo', 'Insumo', 'Servimedic', 500, '2026-01-24', '2027-01-24', 'Pendiente', 222, 0.00, 1.45, 1.45, 1.45, 0),
(223, NULL, 'Baja Lenguas', 'Insumo', 'Insumo', 'Servimedic', 12, '2026-01-24', '2027-01-24', 'Pendiente', 223, 0.00, 0.00, 0.00, 0.00, 0),
(224, NULL, 'Angiocath #22', 'Insumo', 'Insumo', 'Servimedic', 150, '2026-01-24', '2027-01-24', 'Pendiente', 224, 0.00, 4.10, 4.10, 4.10, 0),
(225, NULL, 'Angiocath #18', 'Insumo', 'Insumo', 'Servimedic', 50, '2026-01-24', '2027-01-24', 'Pendiente', 225, 0.00, 4.10, 4.10, 4.10, 0),
(226, NULL, 'Angiocath #20', 'Insumo', 'Insumo', 'Servimedic', 50, '2026-01-24', '2027-01-24', 'Pendiente', 226, 0.00, 4.10, 4.10, 4.10, 0),
(227, NULL, 'Angiocath #24', 'Insumo', 'Insumo', 'Servimedic', 96, '2026-01-24', '2027-01-24', 'Pendiente', 227, 0.00, 4.10, 4.10, 4.10, 0),
(228, NULL, 'Lidocaina c/ Epinefrina', 'Insumo', 'Insumo', 'Servimedic', 3, '2026-01-24', '2027-01-24', 'Pendiente', 228, 0.00, 36.00, 36.00, 36.00, 0),
(229, NULL, 'LIdocaina SIN/ Epinefrina', 'Insumo', 'Insumo', 'Servimedic', 3, '2026-01-24', '2027-01-24', 'Pendiente', 229, 0.00, 32.00, 32.00, 32.00, 0),
(230, NULL, 'Metoclopramida', 'Metoclopramida 10mg', 'Ampolla 2ml', 'Servimedic', 110, '2026-01-24', '2027-01-24', 'Pendiente', 230, 50.00, 2.00, 2.00, 2.00, 0),
(231, NULL, 'Ranitidina', 'Ranitidina 50mg', 'Ampolla 2ml', 'Servimedic', 200, '2026-01-24', '2027-01-24', 'Pendiente', 231, 50.00, 2.00, 2.00, 2.00, 0),
(232, NULL, 'Tramadol', 'Tramadol 100mg', 'Ampolla 2ml', 'Servimedic', 100, '2026-01-24', '2027-01-24', 'Pendiente', 232, 50.00, 2.40, 2.40, 2.40, 0),
(233, NULL, 'Dexametasona', 'Dexametasona 4mg', 'Ampolla 1ml', 'Servimedic', 109, '2026-01-24', '2027-01-24', 'Pendiente', 233, 50.00, 2.50, 2.50, 2.50, 0),
(234, NULL, 'Dipirona', 'Dipirona 1g', 'Ampolla 2ml', 'Servimedic', 204, '2026-01-24', '2027-01-24', 'Pendiente', 234, 50.00, 3.00, 3.00, 3.00, 0),
(235, NULL, 'Selestina', 'Dexa 8mg', 'Ampolla 2ml', 'Servimedic', 8, '2026-01-24', '2027-01-24', 'Pendiente', 235, 50.00, 2.50, 2.50, 2.50, 0),
(236, NULL, 'Parenten', 'Diazepoam 10mg', 'Ampolla 2ml', 'Servimedic', 3, '2026-01-24', '2027-01-24', 'Pendiente', 236, 75.00, 10.00, 10.00, 10.00, 0),
(237, NULL, 'Jeringas de 5ml', 'Insumo', 'Insumo', 'Servimedic', 200, '2026-01-24', '2027-01-24', 'Pendiente', 237, 0.00, 0.37, 0.37, 0.37, 0),
(238, NULL, 'Jeringas de 10ml', 'Insumo', 'Insumo', 'Servimedic', 95, '2026-01-24', '2027-01-24', 'Pendiente', 238, 0.00, 0.58, 0.58, 0.58, 0),
(239, NULL, 'Clorfeniramida', 'Clorfeniramida 10mg', 'Ampolla 2ml', 'Servimedic', 25, '2026-01-24', '2027-01-24', 'Pendiente', 239, 50.00, 2.10, 2.10, 2.10, 0),
(240, NULL, 'Neo-Melumbrina', 'Metamizol 500mg', 'Ampolla 2ml', 'Servimedic', 60, '2026-01-24', '2027-01-24', 'Pendiente', 240, 50.00, 6.75, 6.75, 6.75, 0),
(241, NULL, 'Ceftriaxona', 'Ceftriaxona 1g', 'Vial Polvo', 'Servimedic', 56, '2026-01-24', '2027-01-24', 'Pendiente', 241, 0.00, 7.70, 7.70, 7.70, 0),
(242, NULL, 'Meropenem', 'Meropenem 500mg', 'Vial Polvo', 'Servimedic', 10, '2026-01-24', '2027-01-24', 'Pendiente', 242, 0.00, 32.00, 32.00, 32.00, 0),
(243, NULL, 'Esomeprazol', 'Esomeprazol 40mg', 'Vial Polvo', 'Servimedic', 2, '2026-01-24', '2027-01-24', 'Pendiente', 243, 80.00, 27.00, 27.00, 27.00, 0),
(244, NULL, 'Bonadiona', 'Vitamian K 10MG', 'Ampolla 1ml', 'Servimedic', 3, '2026-01-24', '2027-01-24', 'Pendiente', 244, 25.00, 9.00, 9.00, 9.00, 0),
(245, NULL, 'Omeprazol', 'Omeprazol 40mg', 'Vial Polvo', 'Servimedic', 62, '2026-01-24', '2027-01-24', 'Pendiente', 245, 80.00, 9.80, 9.80, 9.80, 0),
(246, NULL, 'Diclofenaco', 'Diclofenaco 75mg', 'Ampolla 3ml', 'Servimedic', 100, '2026-01-24', '2027-01-24', 'Pendiente', 246, 50.00, 1.80, 1.80, 1.80, 0),
(247, NULL, 'Nauseol', 'Dimehidrato 50mg', 'Ampolla 1ml', 'Servimedic', 50, '2026-01-24', '2027-01-24', 'Pendiente', 247, 50.00, 6.91, 6.91, 6.91, 0),
(248, NULL, 'Furosemida', 'Furosemida 20mg', 'Ampolla 2ml', 'Servimedic', 200, '2026-01-24', '2027-01-24', 'Pendiente', 248, 50.00, 1.50, 1.50, 1.50, 0),
(249, NULL, 'Amikacina', 'Amikacina 500mg', 'Ampolla 2ml', 'Servimedic', 40, '2026-01-24', '2027-01-24', 'Pendiente', 249, 80.00, 5.40, 5.40, 5.40, 0),
(250, NULL, 'Sello Heparina', 'Insumo', 'Insumo', 'Servimedic', 216, '2026-01-24', '2027-01-24', 'Pendiente', 250, 0.00, 1.35, 1.35, 1.35, 0),
(251, NULL, 'Guates descartables', 'Talla M', 'Magica', 'Servimedic', 5, '2026-01-24', '2027-01-24', 'Pendiente', 251, 0.00, 0.00, 0.00, 0.00, 0),
(252, NULL, 'Agujas hipodermicas', 'aguja 24GX1', 'Steril', 'Servimedic', 2, '2026-01-24', '2027-01-24', 'Pendiente', 252, 0.00, 0.00, 0.00, 0.00, 0),
(253, NULL, 'Nylon #3-0', '3-0', 'Atramat', 'Servimedic', 50, '2026-01-24', '2027-01-24', 'Pendiente', 253, 0.00, 0.00, 0.00, 0.00, 0),
(254, NULL, 'Micropore 1/2', 'color blanco', 'Nexcare', 'Servimedic', 11, '2026-01-24', '2027-01-24', 'Pendiente', 254, 0.00, 0.00, 0.00, 0.00, 0),
(255, NULL, 'Bisturi #15', 'Insumo', 'Sterile', 'Servimedic', 57, '2026-01-24', '2027-01-24', 'Pendiente', 255, 0.00, 0.00, 0.00, 0.00, 0),
(256, NULL, 'Blood Lancets', 'Lancetas via med', '100 piezas', 'Servimedic', 6, '2026-01-24', '2027-01-24', 'Pendiente', 256, 0.00, 0.00, 0.00, 0.00, 0),
(257, NULL, 'Accu-chek', 'tiras para glucometro', '50 piexas', 'Servimedic', 4, '2026-01-24', '2027-01-24', 'Pendiente', 257, 0.00, 0.00, 0.00, 0.00, 0),
(258, NULL, 'Sonda Alimentacion #12', 'sondas', '#12', 'Servimedic', 9, '2026-01-24', '2027-01-24', 'Pendiente', 258, 0.00, 0.00, 0.00, 0.00, 0),
(259, NULL, 'Bolsa recolectora orina', 'Adulto', 'de cama', 'Servimedic', 10, '2026-01-24', '2027-01-24', 'Pendiente', 259, 0.00, 0.00, 0.00, 0.00, 0),
(260, NULL, 'Micropore 1p', 'Insumo', 'color blanco', 'Servimedic', 24, '2026-01-24', '2027-01-24', 'Pendiente', 260, 0.00, 0.00, 0.00, 0.00, 0),
(261, NULL, 'Micropore 2p', 'Insumo', 'color blanco', 'Servimedic', 12, '2026-01-24', '2027-01-24', 'Pendiente', 261, 0.00, 0.00, 0.00, 0.00, 0),
(262, NULL, 'Mascarillas para nebulizar', 'Insumo', 'neonatal', 'Servimedic', 2, '2026-01-24', '2027-01-24', 'Pendiente', 262, 0.00, 0.00, 0.00, 0.00, 0),
(263, NULL, 'Mascarillas para nebulizar', 'Insumo', 'Pediatrico', 'Servimedic', 4, '2026-01-24', '2027-01-24', 'Pendiente', 263, 0.00, 0.00, 0.00, 0.00, 0),
(264, NULL, 'Mascarillas para nebulizar', 'Insumo', 'Adulto', 'Servimedic', 4, '2026-01-24', '2027-01-24', 'Pendiente', 264, 0.00, 0.00, 0.00, 0.00, 0),
(265, NULL, 'Sonda alimentacion #5', 'Insumo', 'Operson', 'Servimedic', 5, '2026-01-24', '2027-01-24', 'Pendiente', 265, 0.00, 0.00, 0.00, 0.00, 0),
(266, NULL, 'Sonda alimentacion #8', 'Insumo', 'Operson', 'Servimedic', 4, '2026-01-24', '2027-01-24', 'Pendiente', 266, 0.00, 0.00, 0.00, 0.00, 0),
(267, NULL, 'Bolsa recolectora orina', 'Sterile', 'Pediatrico', 'Servimedic', 31, '2026-01-24', '2027-01-24', 'Pendiente', 267, 0.00, 0.00, 0.00, 0.00, 0),
(268, NULL, 'Canula Binasal', 'Insumo', 'Adulto', 'Servimedic', 5, '2026-01-24', '2027-01-24', 'Pendiente', 268, 0.00, 0.00, 0.00, 0.00, 0),
(269, NULL, 'Venoset', 'Insumo', 'Greetmed', 'Servimedic', 88, '2026-01-24', '2027-01-24', 'Pendiente', 269, 0.00, 0.00, 0.00, 0.00, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ordenes_laboratorio`
--

CREATE TABLE `ordenes_laboratorio` (
  `id_orden` int NOT NULL,
  `numero_orden` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_paciente` int NOT NULL,
  `id_doctor` int DEFAULT NULL,
  `id_encamamiento` int DEFAULT NULL COMMENT 'NULL si es paciente ambulatorio',
  `fecha_orden` datetime NOT NULL,
  `prioridad` enum('Rutina','Urgente','STAT') COLLATE utf8mb4_unicode_ci DEFAULT 'Rutina',
  `estado` enum('Pendiente','Muestra_Recibida','En_Proceso','Completada','Cancelada','Entregada') COLLATE utf8mb4_unicode_ci DEFAULT 'Pendiente',
  `diagnostico_clinico` text COLLATE utf8mb4_unicode_ci,
  `indicaciones_especiales` text COLLATE utf8mb4_unicode_ci,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `creado_por` int DEFAULT NULL,
  `fecha_muestra_recibida` datetime DEFAULT NULL,
  `fecha_completada` datetime DEFAULT NULL,
  `fecha_entregada` datetime DEFAULT NULL,
  `entregado_a` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metodo_entrega` enum('En_Persona','Correo','WhatsApp','Sistema') COLLATE utf8mb4_unicode_ci DEFAULT 'En_Persona',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `archivo_resultados` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `ordenes_laboratorio`
--

INSERT INTO `ordenes_laboratorio` (`id_orden`, `numero_orden`, `id_paciente`, `id_doctor`, `id_encamamiento`, `fecha_orden`, `prioridad`, `estado`, `diagnostico_clinico`, `indicaciones_especiales`, `observaciones`, `creado_por`, `fecha_muestra_recibida`, `fecha_completada`, `fecha_entregada`, `entregado_a`, `metodo_entrega`, `fecha_creacion`, `fecha_actualizacion`, `archivo_resultados`) VALUES
(1, 'LAB-20260122-001', 3, 13, NULL, '2026-01-23 04:35:23', 'STAT', 'Completada', NULL, 'Dormir', '', NULL, NULL, NULL, NULL, NULL, 'En_Persona', '2026-01-23 04:35:23', '2026-01-23 05:16:00', NULL),
(2, 'LAB-20260122-002', 3, 14, NULL, '2026-01-23 05:41:59', 'Rutina', 'Completada', NULL, NULL, '', NULL, '2026-01-23 05:42:37', NULL, NULL, NULL, 'En_Persona', '2026-01-23 05:41:59', '2026-01-23 05:43:30', NULL),
(3, 'LAB-20260123-003', 3, 18, NULL, '2026-01-23 06:22:01', 'Rutina', 'Completada', NULL, '', '', NULL, '2026-01-23 06:53:35', NULL, NULL, NULL, 'En_Persona', '2026-01-23 06:22:01', '2026-01-23 06:59:56', NULL),
(7, 'LAB-20260123-004', 4, 13, NULL, '2026-01-23 07:24:17', 'Rutina', 'Pendiente', NULL, '', '', NULL, NULL, NULL, NULL, NULL, 'En_Persona', '2026-01-23 07:24:17', '2026-01-23 07:24:17', NULL),
(8, 'LAB-20260123-005', 5, 17, NULL, '2026-01-23 07:25:01', 'Rutina', 'Pendiente', NULL, NULL, 'Muestras en recepción', NULL, NULL, NULL, NULL, NULL, 'En_Persona', '2026-01-23 07:25:01', '2026-01-23 07:25:01', NULL),
(9, 'LAB-20260123-006', 4, 16, NULL, '2026-01-23 07:26:58', 'Rutina', 'Pendiente', NULL, NULL, '', NULL, NULL, NULL, NULL, NULL, 'En_Persona', '2026-01-23 07:26:58', '2026-01-23 07:26:58', NULL),
(10, 'LAB-20260123-007', 3, 18, NULL, '2026-01-23 07:27:34', 'Rutina', 'Pendiente', NULL, NULL, '', NULL, NULL, NULL, NULL, NULL, 'En_Persona', '2026-01-23 07:27:34', '2026-01-23 07:27:34', NULL),
(11, 'LAB-20260123-008', 5, 17, NULL, '2026-01-23 07:28:20', 'Rutina', 'Pendiente', NULL, NULL, '', NULL, NULL, NULL, NULL, NULL, 'En_Persona', '2026-01-23 07:28:20', '2026-01-23 07:28:20', NULL),
(12, 'LAB-20260123-009', 5, 14, NULL, '2026-01-23 07:28:46', 'Rutina', 'Pendiente', NULL, NULL, '', NULL, NULL, NULL, NULL, NULL, 'En_Persona', '2026-01-23 07:28:46', '2026-01-23 07:28:46', NULL),
(13, 'LAB-20260123-010', 4, 13, NULL, '2026-01-23 07:30:11', 'Rutina', 'Pendiente', NULL, NULL, '', NULL, NULL, NULL, NULL, NULL, 'En_Persona', '2026-01-23 07:30:11', '2026-01-23 07:30:11', NULL),
(35, 'LAB-20260124-001', 16, 14, NULL, '2026-01-24 12:14:38', 'Rutina', 'Pendiente', NULL, NULL, '', NULL, NULL, NULL, NULL, NULL, 'En_Persona', '2026-01-24 18:14:38', '2026-01-24 18:14:38', NULL),
(36, 'LAB-20260124-002', 21, 18, NULL, '2026-01-24 12:15:41', 'Rutina', 'Pendiente', NULL, NULL, '', NULL, NULL, NULL, NULL, NULL, 'En_Persona', '2026-01-24 18:15:41', '2026-01-24 18:15:41', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `orden_pruebas`
--

CREATE TABLE `orden_pruebas` (
  `id_orden_prueba` int NOT NULL,
  `id_orden` int NOT NULL,
  `id_prueba` int NOT NULL,
  `estado` enum('Pendiente','Muestra_Recibida','En_Proceso','Resultados_Parciales','Completada','Validada','Cancelada') COLLATE utf8mb4_unicode_ci DEFAULT 'Pendiente',
  `fecha_muestra_recibida` datetime DEFAULT NULL,
  `fecha_inicio_proceso` datetime DEFAULT NULL,
  `fecha_completada` datetime DEFAULT NULL,
  `fecha_validada` datetime DEFAULT NULL,
  `notas_tecnico` text COLLATE utf8mb4_unicode_ci,
  `archivo_resultados` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `procesado_por` int DEFAULT NULL,
  `validado_por` int DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `orden_pruebas`
--

INSERT INTO `orden_pruebas` (`id_orden_prueba`, `id_orden`, `id_prueba`, `estado`, `fecha_muestra_recibida`, `fecha_inicio_proceso`, `fecha_completada`, `fecha_validada`, `notas_tecnico`, `archivo_resultados`, `procesado_por`, `validado_por`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 1, 1, 'Validada', '2026-01-23 05:00:53', NULL, NULL, '2026-01-23 05:15:59', NULL, NULL, NULL, 1, '2026-01-23 04:35:24', '2026-01-23 05:15:59'),
(2, 2, 2, 'Validada', '2026-01-23 05:42:37', NULL, NULL, '2026-01-23 05:43:29', '', NULL, NULL, 7, '2026-01-23 05:41:59', '2026-01-23 05:43:29'),
(3, 3, 2, 'Validada', '2026-01-23 06:58:38', NULL, NULL, '2026-01-23 06:59:56', '', '../../uploads/samples/sample_3_69731c1de00d6.pdf', NULL, 7, '2026-01-23 06:22:01', '2026-01-23 06:59:56'),
(4, 7, 2, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 07:24:17', '2026-01-23 07:24:17'),
(5, 8, 1, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 07:25:01', '2026-01-23 07:25:01'),
(6, 9, 2, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 07:26:58', '2026-01-23 07:26:58'),
(7, 10, 2, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 07:27:34', '2026-01-23 07:27:34'),
(8, 11, 2, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 07:28:20', '2026-01-23 07:28:20'),
(9, 12, 2, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 07:28:46', '2026-01-23 07:28:46'),
(10, 13, 1, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-23 07:30:11', '2026-01-23 07:30:11'),
(11, 35, 2, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-24 18:14:38', '2026-01-24 18:14:38'),
(12, 35, 1, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-24 18:14:39', '2026-01-24 18:14:39'),
(13, 36, 15, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-24 18:15:41', '2026-01-24 18:15:41');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pacientes`
--

CREATE TABLE `pacientes` (
  `id_paciente` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `fecha_nacimiento` date NOT NULL,
  `genero` enum('Masculino','Femenino') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(15) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Volcado de datos para la tabla `pacientes`
--

INSERT INTO `pacientes` (`id_paciente`, `nombre`, `apellido`, `fecha_nacimiento`, `genero`, `direccion`, `telefono`, `correo`, `fecha_registro`) VALUES
(3, 'Emilia Alejndra', 'Pérez Castillo', '2018-03-21', 'Femenino', 'Chiantla, zona 1, Huehuetenango', '', '', '2026-01-17 17:38:39'),
(4, 'Samuel', 'Ramirez', '2000-08-25', 'Masculino', 'Huehuetenango, Zona 12', '39029076', 'ejemplo@gmail.com', '2026-01-23 06:25:01'),
(5, 'Oscar', 'Martinez', '2004-04-12', 'Masculino', 'Prueba', '12345678', '', '2026-01-23 06:37:03'),
(6, 'Ana', 'García', '1985-03-15', 'Femenino', 'Calle Primavera 123', '555-0101', 'ana.garcia@email.com', '2026-01-20 09:00:00'),
(7, 'Carlos', 'López', '1990-07-22', 'Masculino', 'Av Libertad 456', '555-0102', 'carlos.lopez@email.com', '2026-01-20 10:30:00'),
(8, 'María', 'Rodríguez', '1978-11-30', 'Femenino', 'Calle Luna 789', '555-0103', 'maria.rodriguez@email.com', '2026-01-20 11:15:00'),
(9, 'Juan', 'Martínez', '1982-05-10', 'Masculino', 'Av Sol 101', '555-0104', 'juan.martinez@email.com', '2026-01-20 12:00:00'),
(10, 'Laura', 'Hernández', '1995-02-14', 'Femenino', 'Calle Estrella 202', '555-0105', 'laura.hernandez@email.com', '2026-01-20 13:45:00'),
(11, 'Pedro', 'Gómez', '1988-09-05', 'Masculino', 'Av Norte 303', '555-0106', 'pedro.gomez@email.com', '2026-01-20 14:20:00'),
(12, 'Sofía', 'Pérez', '1975-12-18', 'Femenino', 'Calle Sur 404', '555-0107', 'sofia.perez@email.com', '2026-01-20 15:10:00'),
(13, 'Miguel', 'Sánchez', '1992-04-25', 'Masculino', 'Av Este 505', '555-0108', 'miguel.sanchez@email.com', '2026-01-20 16:00:00'),
(14, 'Isabel', 'Ramírez', '1980-08-12', 'Femenino', 'Calle Oeste 606', '555-0109', 'isabel.ramirez@email.com', '2026-01-20 17:30:00'),
(15, 'Francisco', 'Torres', '1987-01-30', 'Masculino', 'Av Central 707', '555-0110', 'francisco.torres@email.com', '2026-01-20 18:15:00'),
(16, 'Carmen', 'Flores', '1993-06-08', 'Femenino', 'Calle Jardín 808', '555-0111', 'carmen.flores@email.com', '2026-01-21 09:30:00'),
(17, 'Ricardo', 'Vázquez', '1979-10-17', 'Masculino', 'Av Parque 909', '555-0112', 'ricardo.vazquez@email.com', '2026-01-21 10:45:00'),
(18, 'Patricia', 'Díaz', '1984-03-22', 'Femenino', 'Calle Río 1010', '555-0113', 'patricia.diaz@email.com', '2026-01-21 11:20:00'),
(19, 'Jorge', 'Morales', '1991-07-14', 'Masculino', 'Av Montaña 1111', '555-0114', 'jorge.morales@email.com', '2026-01-21 12:30:00'),
(20, 'Adriana', 'Ortiz', '1986-12-05', 'Femenino', 'Calle Valle 1212', '555-0115', 'adriana.ortiz@email.com', '2026-01-21 13:45:00'),
(21, 'Fernando', 'Castro', '1977-04-18', 'Masculino', 'Av Bosque 1313', '555-0116', 'fernando.castro@email.com', '2026-01-21 14:50:00'),
(22, 'Gabriela', 'Romero', '1994-09-27', 'Femenino', 'Calle Lago 1414', '555-0117', 'gabriela.romero@email.com', '2026-01-21 15:35:00'),
(23, 'Raúl', 'Álvarez', '1983-02-11', 'Masculino', 'Av Mar 1515', '555-0118', 'raul.alvarez@email.com', '2026-01-21 16:40:00'),
(24, 'Verónica', 'Ruiz', '1989-05-24', 'Femenino', 'Calle Playa 1616', '555-0119', 'veronica.ruiz@email.com', '2026-01-21 17:25:00'),
(25, 'Oscar', 'Jiménez', '1976-08-09', 'Masculino', 'Av Campo 1717', '555-0120', 'oscar.jimenez@email.com', '2026-01-22 09:15:00'),
(26, 'Diana', 'Mendoza', '1996-01-19', 'Femenino', 'Calle Granja 1818', '555-0121', 'diana.mendoza@email.com', '2026-01-22 10:30:00'),
(27, 'Eduardo', 'Guerrero', '1981-11-03', 'Masculino', 'Av Ciudad 1919', '555-0122', 'eduardo.guerrero@email.com', '2026-01-22 11:45:00'),
(28, 'Claudia', 'Rojas', '1990-03-28', 'Femenino', 'Calle Pueblo 2020', '555-0123', 'claudia.rojas@email.com', '2026-01-22 13:00:00'),
(29, 'Antonio', 'Salazar', '1985-07-16', 'Masculino', 'Av Metrópolis 2121', '555-0124', 'antonio.salazar@email.com', '2026-01-22 14:15:00'),
(30, 'Teresa', 'Molina', '1978-12-23', 'Femenino', 'Calle Capital 2222', '555-0125', 'teresa.molina@email.com', '2026-01-23 09:30:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `parametros_pruebas`
--

CREATE TABLE `parametros_pruebas` (
  `id_parametro` int NOT NULL,
  `id_prueba` int NOT NULL,
  `nombre_parametro` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unidad_medida` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor_ref_hombre_min` decimal(12,4) DEFAULT NULL,
  `valor_ref_hombre_max` decimal(12,4) DEFAULT NULL,
  `valor_ref_mujer_min` decimal(12,4) DEFAULT NULL,
  `valor_ref_mujer_max` decimal(12,4) DEFAULT NULL,
  `valor_ref_pediatrico_min` decimal(12,4) DEFAULT NULL,
  `valor_ref_pediatrico_max` decimal(12,4) DEFAULT NULL,
  `tipo_dato` enum('Numérico','Texto','Selección','Cualitativo') COLLATE utf8mb4_unicode_ci DEFAULT 'Numérico',
  `opciones_seleccion` text COLLATE utf8mb4_unicode_ci COMMENT 'JSON con opciones si es tipo Selección',
  `valores_normales` text COLLATE utf8mb4_unicode_ci COMMENT 'Para resultados cualitativos',
  `orden_visualizacion` int DEFAULT '0',
  `critico_bajo` decimal(12,4) DEFAULT NULL COMMENT 'Valor crítico bajo',
  `critico_alto` decimal(12,4) DEFAULT NULL COMMENT 'Valor crítico alto',
  `formula_calculo` text COLLATE utf8mb4_unicode_ci COMMENT 'Si se calcula a partir de otros parámetros',
  `notas` text COLLATE utf8mb4_unicode_ci,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `parametros_pruebas`
--

INSERT INTO `parametros_pruebas` (`id_parametro`, `id_prueba`, `nombre_parametro`, `unidad_medida`, `valor_ref_hombre_min`, `valor_ref_hombre_max`, `valor_ref_mujer_min`, `valor_ref_mujer_max`, `valor_ref_pediatrico_min`, `valor_ref_pediatrico_max`, `tipo_dato`, `opciones_seleccion`, `valores_normales`, `orden_visualizacion`, `critico_bajo`, `critico_alto`, `formula_calculo`, `notas`, `fecha_creacion`) VALUES
(2, 1, 'Prueba', 'mg', 4.0000, 10.0000, 2.0000, 7.0000, 4.0000, 8.0000, 'Texto', NULL, NULL, 0, NULL, NULL, NULL, NULL, '2026-01-23 04:29:15');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `procedimientos_menores`
--

CREATE TABLE `procedimientos_menores` (
  `id_procedimiento` int NOT NULL,
  `id_paciente` int NOT NULL,
  `nombre_paciente` varchar(255) NOT NULL,
  `procedimiento` varchar(255) NOT NULL COMMENT 'Nombre del procedimiento (ej. Sutura, Curación)',
  `cobro` decimal(10,2) NOT NULL,
  `fecha_procedimiento` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario` varchar(255) DEFAULT NULL,
  `tipo_pago` enum('Efectivo','Tarjeta','Transferencia') DEFAULT 'Efectivo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Volcado de datos para la tabla `procedimientos_menores`
--

INSERT INTO `procedimientos_menores` (`id_procedimiento`, `id_paciente`, `nombre_paciente`, `procedimiento`, `cobro`, `fecha_procedimiento`, `usuario`, `tipo_pago`) VALUES
(1, 3, 'Emilia Alejndra Pérez Castillo', 'Glucometria', 30.00, '2026-01-23 04:18:16', 'system', 'Efectivo'),
(2, 4, 'Samuel Ramirez', 'Glucometria', 30.00, '2026-01-23 06:27:57', 'system', 'Efectivo'),
(3, 4, 'Samuel Ramirez', 'Glucometria', 30.00, '2026-01-23 06:57:39', 'system', 'Efectivo'),
(4, 16, 'Carmen Flores', 'Inyeccion', 5.00, '2026-01-23 17:03:48', 'system', 'Efectivo'),
(5, 14, 'Isabel Ramírez', 'Colacacion de Sonda Foley', 250.00, '2026-01-23 17:04:08', 'system', 'Efectivo'),
(6, 25, 'Oscar Jiménez', 'Sutura 6-10 pts', 500.00, '2026-01-23 17:04:31', 'system', 'Efectivo'),
(7, 27, 'Eduardo Guerrero', 'Unicotomia', 150.00, '2026-01-23 17:15:14', 'system', 'Efectivo'),
(8, 3, 'Emilia Alejndra Pérez Castillo', 'Lavado de Oido', 100.00, '2026-01-24 17:55:23', 'system', 'Tarjeta'),
(9, 25, 'Oscar Jiménez', 'Curacion de herida', 150.00, '2026-01-24 17:56:18', 'system', 'Tarjeta'),
(10, 5, 'Oscar Martinez', 'Canalizacion con Stopper', 125.00, '2026-01-24 18:22:16', 'system', 'Tarjeta');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `purchase_headers`
--

CREATE TABLE `purchase_headers` (
  `id` int NOT NULL,
  `document_type` enum('Factura','Nota de Envío','Consumidor Final') NOT NULL,
  `document_number` varchar(50) DEFAULT NULL,
  `provider_name` varchar(100) DEFAULT NULL,
  `purchase_date` date NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('Pendiente','Completado') DEFAULT 'Pendiente',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_amount` decimal(10,2) DEFAULT '0.00',
  `payment_status` enum('Pendiente','Parcial','Pagado') DEFAULT 'Pendiente',
  `created_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `purchase_headers`
--

INSERT INTO `purchase_headers` (`id`, `document_type`, `document_number`, `provider_name`, `purchase_date`, `total_amount`, `status`, `created_at`, `paid_amount`, `payment_status`, `created_by`) VALUES
(4, 'Nota de Envío', 'A-0001s', 'Servimedic', '2026-01-16', 31280.84, 'Pendiente', '2026-01-16 19:58:11', 0.00, 'Pendiente', NULL),
(5, 'Nota de Envío', 'A-0001', 'Servimedic', '2026-01-16', 31162.24, 'Pendiente', '2026-01-17 04:51:31', 0.00, 'Pendiente', NULL),
(6, 'Nota de Envío', 'A-0003', 'Servimedic', '2026-01-24', 30674.18, 'Pendiente', '2026-01-24 07:31:35', 0.00, 'Pendiente', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `purchase_items`
--

CREATE TABLE `purchase_items` (
  `id` int NOT NULL,
  `purchase_header_id` int NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `presentation` varchar(100) DEFAULT NULL,
  `molecule` varchar(100) DEFAULT NULL,
  `pharmaceutical_house` varchar(100) DEFAULT NULL,
  `quantity` int NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `sale_price` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `status` enum('Pendiente','Recibido') DEFAULT 'Pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `purchase_items`
--

INSERT INTO `purchase_items` (`id`, `purchase_header_id`, `product_name`, `presentation`, `molecule`, `pharmaceutical_house`, `quantity`, `unit_cost`, `sale_price`, `subtotal`, `status`) VALUES
(4, 4, 'Antigrip', 'Ampolla', 'Eucolapto-Guayacol', 'Servimedic', 5, 21.69, 35.00, 108.45, 'Recibido'),
(5, 4, 'Ibuvanz', 'Suspension', 'Ibuprofeno100mg/5ml', 'Servimedic', 5, 21.99, 62.00, 109.95, 'Recibido'),
(6, 4, 'Fungiter', 'Crema topica', 'Terbinafina 1g', 'Servimedic', 5, 27.33, 140.00, 136.65, 'Recibido'),
(7, 4, 'D3-fENDER', 'Capsula', 'Vitamina D3100,000UI', 'Servimedic', 5, 95.17, 140.00, 475.85, 'Pendiente'),
(8, 4, 'Bisocard 5mg', 'Tableta', 'Bisoprolol famarato 5mg', 'Servimedic', 5, 116.32, 270.00, 581.60, 'Pendiente'),
(9, 4, 'Olmepress HCT 40/12.5mg', 'Tableta', 'Olmesartan Medoxomil40mg+Hidroclorotiazida 12.5mg', 'Servimedic', 5, 170.38, 350.00, 851.90, 'Pendiente'),
(10, 4, 'Gacimex', 'suspension', 'Magaldrato 800mg/Simeticona 60mg/10ml', 'Servimedic', 5, 92.18, 155.00, 460.90, 'Pendiente'),
(11, 4, 'Ultram D', 'Capsula', 'Dutasterida 0.5+Tamsulona clorhidrato 0.4mg', 'Servimedic', 4, 296.24, 600.00, 1184.96, 'Pendiente'),
(12, 4, 'Triacid', 'Tableta', 'Pinaverium 100mg+Simethicone 300mg', 'Servimedic', 5, 142.48, 230.00, 712.40, 'Pendiente'),
(13, 4, 'Tónico de alfalfa R95', 'Suspensión', 'tónico de alfalfa', 'Servimedic', 5, 104.90, 210.00, 524.50, 'Pendiente'),
(14, 4, 'Metiom H. pylori', 'Cápsula', 'esomeprazol-levofloxamina-amoxicilina', 'Servimedic', 4, 445.47, 630.00, 1781.88, 'Pendiente'),
(15, 4, 'Vertiless', 'Tableta', 'Betahistina- diclorhidrato 16mg', 'Servimedic', 5, 86.40, 180.00, 432.00, 'Pendiente'),
(16, 4, 'Lyverium 1mg', 'Tableta', 'Alprazolam 1mg', 'Servimedic', 5, 112.02, 255.00, 560.10, 'Pendiente'),
(17, 4, 'Lyverium 0.5mg', 'Tableta', 'Alprazolam 0.5mg', 'Servimedic', 5, 72.67, 150.00, 363.35, 'Pendiente'),
(18, 4, 'Equiliv', 'Gotero', 'clonazepam 2.5/ml', 'Servimedic', 5, 78.40, 115.00, 392.00, 'Pendiente'),
(19, 4, 'Atenua', 'Comprimidos', 'dexketoprofeno 25mg', 'Servimedic', 5, 48.98, 140.00, 244.90, 'Pendiente'),
(20, 4, 'Sitalev Met', 'Tabletas', 'sitaglipina 50mg +metformina 500mg', 'Servimedic', 5, 123.62, 220.00, 618.10, 'Pendiente'),
(21, 4, 'Inuric-G', 'Tableta', 'Febuxostat 80mg', 'Servimedic', 5, 148.50, 320.00, 742.50, 'Pendiente'),
(22, 4, 'Gabin', 'Tableta', 'Gabapentina 400mg', 'Servimedic', 5, 97.50, 250.00, 487.50, 'Pendiente'),
(23, 4, 'Atrolip Plus', 'Comprimidos', 'atorvastatina 10mg + ezetimibe 10 mg', 'Servimedic', 5, 203.56, 380.00, 1017.80, 'Pendiente'),
(24, 4, 'Glutamax C', 'Viales', 'Glutathione + vit C', 'Servimedic', 3, 89.20, 200.00, 267.60, 'Pendiente'),
(25, 4, 'Rupagán', 'Suspensión', 'Rupatadina 1mg/ml.', 'Servimedic', 5, 80.13, 145.00, 400.65, 'Pendiente'),
(26, 4, 'Biotos Inmune', 'Suspensión', 'Hedera helix & Pelargonium sidoides', 'Servimedic', 5, 75.16, 185.00, 375.80, 'Pendiente'),
(27, 4, 'Biotos Inmune Pediátrico', 'Suspensión', 'Hedera Helix & Pelargonium sidoides', 'Servimedic', 5, 54.91, 135.00, 274.55, 'Pendiente'),
(28, 4, 'Omega 1000', 'Cápsulas', 'Omega 3', 'Servimedic', 2, 227.90, 425.00, 455.80, 'Pendiente'),
(29, 4, 'Aci-tip', 'Comprimidos', 'Magaldrato 800mg - simeticona 40mg', 'Servimedic', 5, 55.56, 120.00, 277.80, 'Pendiente'),
(30, 4, 'Neuralplus', 'Tableta', 'Tiamina, piridoxina, cianocobalamina, diclofenaco', 'Servimedic', 4, 38.48, 115.00, 153.92, 'Recibido'),
(31, 4, 'Kardiopil HCT', 'Tableta', 'Irbesartán 300mg + hidroclorotiazida 12.5 mg', 'Servimedic', 5, 144.54, 250.00, 722.70, 'Pendiente'),
(32, 4, 'Milenium', 'Cápsula', 'esomeprazol 40mg', 'Servimedic', 5, 88.00, 250.00, 440.00, 'Pendiente'),
(33, 4, 'Denk man active', 'Cápsula', 'extraxto de ginkgo, arginina', 'Servimedic', 3, 114.00, 220.00, 342.00, 'Pendiente'),
(34, 4, 'Inmuno biter', 'Ampolla bebible', 'extracto glicerinado de jara+tomillo', 'Servimedic', 3, 289.90, 390.00, 869.70, 'Pendiente'),
(35, 4, 'Spacek', 'Tabletas', 'Bromuro de otilonio 40mg', 'Servimedic', 5, 71.00, 170.00, 355.00, 'Pendiente'),
(36, 4, 'Spirocard', 'Tableta', 'spironolactone 100mg', 'Servimedic', 5, 144.90, 260.00, 724.50, 'Pendiente'),
(37, 4, 'Kardiopil Amlo', 'Tableta', 'Irbesartan 300mg + Amlodipine 5mg', 'Servimedic', 5, 237.72, 410.00, 1188.60, 'Pendiente'),
(38, 4, 'Gabex', 'Cápsula', 'Gabapentin 300mg', 'Servimedic', 5, 90.93, 200.00, 454.65, 'Pendiente'),
(39, 4, 'biobronq', 'Suspensión', 'Hedera Helix 35mg/5ml', 'Servimedic', 5, 50.61, 80.00, 253.05, 'Pendiente'),
(40, 4, 'Disolflem', 'sticks granulado', 'Acetilcisteína', 'Servimedic', 5, 42.73, 105.00, 213.65, 'Pendiente'),
(41, 4, 'Uroprin', 'Sticks granulado', 'Fosfomicina 3g', 'Servimedic', 5, 126.40, 165.00, 632.00, 'Pendiente'),
(42, 4, 'Clevium', 'Sobres Bebible', 'Desketoprofen (Trometamol) 25mg/10ml', 'Servimedic', 5, 84.63, 140.00, 423.15, 'Recibido'),
(43, 4, 'Clevium', 'Gel', 'Dexketoprofeno 1.25%', 'Servimedic', 5, 37.00, 80.00, 185.00, 'Recibido'),
(44, 4, 'Flavia', 'Tabletas', 'Melatonina, calcio', 'Servimedic', 5, 153.84, 250.00, 769.20, 'Pendiente'),
(45, 4, 'Demilos', 'Comprimidos', 'carbonato de calcio colecalciferol, vitamina d3', 'Servimedic', 5, 134.22, 215.00, 671.10, 'Pendiente'),
(46, 4, 'Zefalox', '20 Cápsulas', 'cefixime 400mg', 'Servimedic', 5, 287.38, 650.00, 1436.90, 'Pendiente'),
(47, 4, 'Zefalox', 'Suspensión 50ml', 'Cefixima 100mg/5ml', 'Servimedic', 5, 66.77, 205.00, 333.85, 'Pendiente'),
(48, 4, 'Zefalox', 'Suspesión 100ml', 'Cefixima', 'Servimedic', 5, 101.43, 300.00, 507.15, 'Pendiente'),
(49, 4, 'Conflexil Plus Shot', 'Sticks bebible', 'tiocolchicosido 4mg-diclofenaco 50mh', 'Servimedic', 100, 12.06, 22.00, 1206.00, 'Pendiente'),
(50, 4, 'Rofemed', 'Vial', 'ceftriaxona 1g', 'Servimedic', 5, 21.36, 120.00, 106.80, 'Pendiente'),
(51, 4, 'Milenium', '30 Cápsulas', 'esomeprazol 20ml', 'Servimedic', 5, 53.50, 200.00, 267.50, 'Pendiente'),
(52, 4, 'Gadavyt fibra liquida', 'Suspensión', 'Fibra dietética jugo natural de ciruela', 'Servimedic', 2, 215.46, 345.00, 430.92, 'Pendiente'),
(53, 4, 'Fungiter', 'Spray', 'Terbinafine HCI 1%', 'Servimedic', 5, 45.71, 100.00, 228.55, 'Pendiente'),
(54, 4, 'Fungiter', '28 Tabletas', 'Terbinafine 250 mg', 'Servimedic', 5, 219.76, 545.00, 1098.80, 'Pendiente'),
(55, 4, 'Septidex', 'Spray', 'Polimixina. neomicina 40g', 'Servimedic', 5, 52.45, 105.00, 262.25, 'Pendiente'),
(56, 4, 'Dinivanz', 'Solución p/ nebulizar', 'Salbutamol, salino solucion', 'Servimedic', 5, 42.95, 130.00, 214.75, 'Pendiente'),
(57, 4, 'Hicet', 'Gotas pediátricas', 'Cetirizina diclorhidrato 10mg/ml', 'Servimedic', 5, 42.26, 105.00, 211.30, 'Pendiente'),
(58, 4, 'Hicet', 'Jarabe 120ml', 'Cetirizina diclorhidrato 5mg/ml', 'Servimedic', 5, 58.76, 140.00, 293.80, 'Pendiente'),
(59, 4, 'Hicet', 'Jarabe 60ml', 'Cetirizina diclorhidrato 5mg/5ml', 'Servimedic', 5, 35.96, 90.00, 179.80, 'Pendiente'),
(60, 4, 'Hicet', '10 Cápsulas', 'Cetirizina diclorhidrato 10mg', 'Servimedic', 5, 38.03, 90.00, 190.15, 'Pendiente'),
(61, 4, 'Gabex Plus', '30 Tabletas recubiertas', 'Gabapentina + vitamina B1 y B12', 'Servimedic', 5, 156.20, 350.00, 781.00, 'Pendiente'),
(62, 4, 'Levent-Vit-E', '30 Cápsulas', 'vitamina E', 'Servimedic', 3, 180.67, 280.00, 542.01, 'Pendiente'),
(63, 4, 'Rosecol', '30 Tabletas recubiertas', 'Rosuvastatina 20mg', 'Servimedic', 5, 109.45, 235.00, 547.25, 'Pendiente'),
(64, 4, 'Prednicet', '20 Tabletas', 'Prednisolona 5mg', 'Servimedic', 5, 40.87, 85.00, 204.35, 'Recibido'),
(65, 5, 'Conflexil', 'Ampollas 4mg/2ml', 'Tiocolchicósido', 'Servimedic', 25, 13.50, 35.00, 337.50, 'Pendiente'),
(66, 5, 'Viater Forte', 'Viales bebibles', 'ginseng, vitamina E, zinc', 'Servimedic', 1, 206.90, 300.00, 206.90, 'Pendiente'),
(67, 5, 'Acla-med bid', '14 tabletas recubiertas', 'amoxicilina 875mg, acido clavulanico 125mg', 'Servimedic', 1, 86.03, 215.00, 86.03, 'Pendiente'),
(68, 5, 'Symbio flor 1', 'Suspension oral', 'enterococcusfaecalis', 'Servimedic', 1, 178.00, 255.00, 178.00, 'Pendiente'),
(69, 5, 'Klevraxr', '30 tabletas', 'levetiracetam 500mg', 'Servimedic', 3, 105.00, 170.00, 315.00, 'Recibido'),
(70, 5, 'Suganon', '30 Comprimidos', 'Evogliptina 5mg', 'Servimedic', 5, 359.00, 505.00, 1795.00, 'Pendiente'),
(71, 5, 'Zukermen Met', '30 Tabletas', 'vildagliptina 50ml+metformina 1000mg', 'Servimedic', 5, 126.78, 300.00, 633.90, 'Pendiente'),
(72, 5, 'Tusivanz', 'gotas pediatricas', 'dextromethorphan+carboxymethylcysteine', 'Servimedic', 5, 46.17, 105.00, 230.85, 'Pendiente'),
(73, 5, 'Budoxigen', 'spray 200 aplicaciones', 'Budesonida 50mcg/100mcl', 'Servimedic', 5, 91.34, 190.00, 456.70, 'Pendiente'),
(74, 5, 'Total Magnesiano', 'Sobres efervecentes', 'cloruro de magnesio 4.5H2O 1.5g + fluoruro de magnesio 0.0015g', 'Servimedic', 2, 152.00, 250.00, 304.00, 'Pendiente'),
(75, 5, 'Acla-med', 'Suspension', 'Amoxicilina 600mg+Acido clavulanico 42.9mg', 'Servimedic', 3, 64.43, 175.00, 193.29, 'Pendiente'),
(76, 5, 'Avsar Plus', '28 Tabletas', 'valsartan 320mg+amlodipina 10mg+hidroclorotiazida 25mg', 'Servimedic', 3, 166.70, 520.00, 500.10, 'Pendiente'),
(77, 5, 'Deflarin', '10 comprimidos', 'desflazacort 30mg', 'Servimedic', 3, 210.00, 325.00, 630.00, 'Pendiente'),
(78, 5, 'Disoflem', 'Sobres Granulados', 'Acetilcisteina 200mg', 'Servimedic', 5, 42.73, 105.00, 213.65, 'Pendiente'),
(79, 5, 'Megamol', '100 capsulas', 'vitamina D3', 'Servimedic', 5, 102.90, 250.00, 514.50, 'Pendiente'),
(80, 5, 'Diabilev', '30 Tabletas', 'Metformina HCI 500mg', 'Servimedic', 2, 54.70, 90.00, 109.40, 'Pendiente'),
(81, 5, 'Denk immun active', 'Sobres', 'Zinc, selenio', 'Servimedic', 5, 118.63, 195.00, 593.15, 'Pendiente'),
(82, 5, 'Melatina', 'Gotero', 'Melatonina 10.53mg', 'Servimedic', 5, 82.90, 160.00, 414.50, 'Pendiente'),
(83, 5, 'Bru-sone', 'Ampolla', 'betametasona dipropionato 5mg+fosfato sodico 2mg', 'Servimedic', 5, 97.34, 190.00, 486.70, 'Pendiente'),
(84, 5, 'Gastrexx plus', '28 capsulas', 'amoxicilina 1g+ levofloxacina 500mg', 'Servimedic', 3, 200.55, 480.00, 601.65, 'Pendiente'),
(85, 5, 'Modepar', '60 Tabletas', 'Nicotinamida 17.5mg, Acido Ascorbico 50mg', 'Servimedic', 5, 375.50, 550.00, 1877.50, 'Pendiente'),
(86, 5, 'Adiaplex', '30 Tabletas', 'Dapagliflozina 10mg', 'Servimedic', 5, 273.93, 410.00, 1369.65, 'Pendiente'),
(87, 5, 'Glidap Max', '30 tabletas', 'Dapagliflozina 5mg+metformina HCI lp 1000mg', 'Servimedic', 5, 130.00, 300.00, 650.00, 'Pendiente'),
(88, 5, 'Gesimax', '10 tabletas', 'Naproxeno sodico 550mg', 'Servimedic', 20, 48.40, 60.00, 968.00, 'Pendiente'),
(89, 5, 'Lisinox', '10 Tabletas', 'Propinoxato HCL 10mg+clonixinato de lisina 125mg', 'Servimedic', 10, 18.31, 45.00, 183.10, 'Pendiente'),
(90, 5, 'Solocin Plus', '20 comprimidos', 'pancreatina 400mg+simeticona 60mg+cinitaprina 1mg', 'Servimedic', 5, 109.34, 220.00, 546.70, 'Recibido'),
(91, 5, 'Ferrum 16', 'Jarabe 240ml', 'hierro, vitaminas y minerales', 'Servimedic', 5, 68.00, 120.00, 340.00, 'Pendiente'),
(92, 5, 'Gadysen', '30 capsulas', 'Duloxetina 60mg', 'Servimedic', 5, 257.76, 560.00, 1288.80, 'Pendiente'),
(93, 5, 'Gadysen', '30 capsulas', 'Duloxetina 30mg', 'Servimedic', 3, 226.00, 510.00, 678.00, 'Pendiente'),
(94, 5, 'Multiflora Adance', '30 capsulas', 'probiotico', 'Servimedic', 3, 271.60, 420.00, 814.80, 'Pendiente'),
(95, 5, 'Estoma dol', '30 capsulas', 'trisilicato de magnesio, carbon vegetal', 'Servimedic', 2, 82.90, 140.00, 165.80, 'Pendiente'),
(96, 5, 'Exlant', '30 capsulas', 'dexlansoprazol 30mg', 'Servimedic', 4, 149.50, 365.00, 598.00, 'Pendiente'),
(97, 5, 'Ki-Cab', '50 tabletas', 'tegoprazan 50mg', 'Servimedic', 1, 593.41, 830.00, 593.41, 'Pendiente'),
(98, 5, 'Lisinox', 'Gotas 20ml', 'Propinoxato clorhidrato 5mg/ml', 'Servimedic', 3, 42.46, 80.00, 127.38, 'Pendiente'),
(99, 5, 'Probiocyan', '30 capsulas', 'lactobacillus plantarum, zinc 5mg', 'Servimedic', 5, 138.34, 230.00, 691.70, 'Pendiente'),
(100, 5, 'Colitran', '10 grageas', 'clordiazepoxido HCI/ Bromuro de clidinio', 'Servimedic', 10, 23.00, 40.00, 230.00, 'Pendiente'),
(101, 5, 'Sucralfato', '40 Tabletas', 'sucralfato 1g', 'Servimedic', 1, 59.98, 105.00, 59.98, 'Pendiente'),
(102, 5, 'Cetamin CC', '10 Tabletas', 'Acetaminofen 325mg+codeina 15mg', 'Servimedic', 5, 43.60, 90.00, 218.00, 'Recibido'),
(103, 5, 'Tensinor Plus', '30 Tabletas', 'Valsartan 160mg/hidroclorotiazida 12.5mg/amlodipino 5mg', 'Servimedic', 2, 270.00, 480.00, 540.00, 'Pendiente'),
(104, 5, 'Tensinor Plus', '30 Tabletas', 'Valsartan 320mg/hidroclorotiazida 25mg/amlodipino 10mg', 'Servimedic', 2, 270.00, 480.00, 540.00, 'Pendiente'),
(105, 5, 'Metavan', '30 Tabletas', 'metformina HCI 1000mg', 'Servimedic', 1, 210.14, 245.00, 210.14, 'Pendiente'),
(106, 5, 'FILINAR g', 'Suspension', 'acebrifilina 5mg/ml', 'Servimedic', 1, 102.74, 160.00, 102.74, 'Pendiente'),
(107, 5, 'Myo & D-Chiro Inositol', '90 capsulas', 'inositol chiro', 'Servimedic', 2, 350.00, 470.00, 700.00, 'Pendiente'),
(108, 5, 'Gastroflux', 'suspension', 'domperidona 1mg/ml', 'Servimedic', 5, 170.63, 235.00, 853.15, 'Pendiente'),
(109, 5, 'Careject', 'Spray nasal', 'aceite de soja, glicerol', 'Servimedic', 5, 74.48, 150.00, 372.40, 'Pendiente'),
(110, 5, 'Aidex', 'Sobres bebibles', 'dexketoprofeno 25mg/10ml', 'Servimedic', 5, 85.00, 110.00, 425.00, 'Recibido'),
(111, 5, 'Rusitan', 'Suspension', 'Rupatadina fumarato 1mg/ml', 'Servimedic', 5, 107.70, 175.00, 538.50, 'Pendiente'),
(112, 5, 'Acetaminofen lancasco', 'Suspension', 'acetaminofen 120/5ml', 'Servimedic', 3, 15.00, 30.00, 45.00, 'Pendiente'),
(113, 5, 'Bucaglu', 'Tintura Oral', 'ruibarbo y acido salicilico', 'Servimedic', 3, 55.00, 130.00, 165.00, 'Pendiente'),
(114, 5, 'Contractil', '10 Tabletas', 'tiocolchicosido 4mg', 'Servimedic', 3, 68.73, 130.00, 206.19, 'Pendiente'),
(115, 5, 'Etoricox', '14 Tabletas', 'Etoricoxib 120mg', 'Servimedic', 1, 275.00, 400.00, 275.00, 'Pendiente'),
(116, 5, 'Isocraneol', '30 Comprimidos', 'Citicolina 500mg', 'Servimedic', 5, 321.53, 500.00, 1607.65, 'Pendiente'),
(117, 5, 'Rodiflux', 'Gotero', 'Dextrometorfan, carboximetilcisteina, clorfeniramina', 'Servimedic', 5, 52.80, 110.00, 264.00, 'Pendiente'),
(118, 5, 'Gebrix-G 240ml', 'Suspension', 'Jengibre, Equinacea, vitamina C', 'Servimedic', 3, 100.00, 200.00, 300.00, 'Pendiente'),
(119, 5, 'Zirtraler-D 60ml', 'Suspension', 'Cetirizina HCI, Fenilefrina HCI', 'Servimedic', 5, 65.69, 125.00, 328.45, 'Pendiente'),
(120, 5, 'Neo-melubrina', 'Jarabe 100ml', 'Metamizol sodico 250mg/5ml', 'Servimedic', 2, 35.00, 75.00, 70.00, 'Pendiente'),
(121, 5, 'Neobol', 'Spray 30g', 'neomicina- clostebol', 'Servimedic', 2, 60.00, 135.00, 120.00, 'Pendiente'),
(122, 5, 'Mero Clav', 'suspension 70ml', 'cefuroxima+ acido clavulanico', 'Servimedic', 2, 145.00, 250.00, 290.00, 'Pendiente'),
(123, 5, 'Dexamicina', 'Gotero Oftalmico 5ml', 'Dexametazona/neomicina', 'Servimedic', 5, 25.00, 55.00, 125.00, 'Pendiente'),
(124, 5, 'Aciclovirax', 'Suspension 120ml', 'Aciclovir pediatrico', 'Servimedic', 5, 112.13, 200.00, 560.65, 'Pendiente'),
(125, 5, 'Bencidamin', 'Spray bucal', 'Bencidamina', 'Servimedic', 2, 32.00, 90.00, 64.00, 'Pendiente'),
(126, 5, 'Metronis', 'suspension', 'Nitazoxanida 100mg/5ml', 'Servimedic', 2, 34.33, 80.00, 68.66, 'Pendiente'),
(127, 5, 'Sinedol Forte', '10 Tabletas', 'Acetaminofen 750mg', 'Servimedic', 5, 29.84, 45.00, 149.20, 'Pendiente'),
(128, 5, 'Mucarbol Pediatrico', 'Jarabe', 'Carbocisteina 100mg/5ml', 'Servimedic', 5, 39.23, 65.00, 196.15, 'Pendiente'),
(129, 5, 'Mucarbol Adulto', 'Jarabe', 'Carbocisteina 750mg/15ml', 'Servimedic', 5, 42.37, 70.00, 211.85, 'Pendiente'),
(130, 5, 'Neo-Melubrina', '4 Tabletas', 'Metamizol 500mg', 'Servimedic', 25, 2.80, 15.00, 70.00, 'Pendiente'),
(131, 5, 'AGE III', '30 Capsulas', 'cucurbita pepo. africanum', 'Servimedic', 5, 132.85, 200.00, 664.25, 'Pendiente'),
(132, 5, 'Sertal Forte Perlas', '10 capsulas', 'Propinox Clorhidrato 20mf', 'Servimedic', 6, 49.87, 90.00, 299.22, 'Pendiente'),
(133, 5, 'Ardix', '10 Tabletas', 'dexketoprofeno 25mg', 'Servimedic', 1, 50.00, 95.00, 50.00, 'Recibido'),
(134, 5, 'Wen vision', 'Gotero Oftalmico 5ml', 'Dexametasona, neomicina', 'Servimedic', 5, 25.00, 55.00, 125.00, 'Pendiente'),
(135, 5, 'Selenio+Vit E', '60 Capsulas', 'Vitamina E 1000UI+ Selenio 200', 'Servimedic', 2, 56.33, 175.00, 112.66, 'Pendiente'),
(136, 5, 'Brucort-A', 'Crema Topica', 'Triamcinolona acetonido 0.1%', 'Servimedic', 4, 50.00, 110.00, 200.00, 'Pendiente'),
(137, 5, 'Uxbi', '30 capsulas', 'Acido ursodesoxicolico 250mg', 'Servimedic', 2, 200.00, 375.00, 400.00, 'Pendiente'),
(138, 5, 'Allopurikem', '10 Tabletas', 'alopurinol 300mg', 'Servimedic', 5, 29.40, 75.00, 147.00, 'Pendiente'),
(139, 5, 'Deka-C Adultos', 'Ampollas bebibles 5ml', 'vitaminas A, D, E y C', 'Servimedic', 5, 25.75, 75.00, 128.75, 'Pendiente'),
(140, 5, 'Rexacort', 'Spray nasal 18g', 'mometasona furoato 50pg', 'Servimedic', 3, 55.38, 130.00, 166.14, 'Pendiente'),
(141, 5, 'Histakem Block', 'Spray bucal 30ml', 'Cloruro de cetilpiridinio 0.05g+benzocaina 1.0g', 'Servimedic', 2, 80.00, 125.00, 160.00, 'Pendiente'),
(142, 5, 'Colchinet', '20 Tabletas', 'Colchicina 0.5 mg', 'Servimedic', 15, 36.00, 65.00, 540.00, 'Pendiente'),
(143, 5, 'Triglix', '40 capsulas', 'Fenofibrato 160mg', 'Servimedic', 4, 219.00, 390.00, 876.00, 'Pendiente'),
(144, 5, 'Equiliv', '30 Tabletas', 'Clonazepan 2mg', 'Servimedic', 5, 77.85, 135.00, 389.25, 'Pendiente'),
(145, 6, 'ESOGASTRIC 10MG', '15 SOBRES', 'ESOMEPRAZOL', 'Servimedic', 2, 98.12, 165.00, 196.24, 'Pendiente'),
(146, 6, 'SPASMO-UROLONG', '10 COMPRIMIDOS', 'NITROFURANTOINA 75MG', 'Servimedic', 2, 43.00, 80.00, 86.00, 'Pendiente'),
(147, 6, 'Burts bees baby', 'rolon', 'esencia coco', 'Servimedic', 3, 30.00, 105.00, 90.00, 'Pendiente'),
(148, 6, 'propix-duo', 'ampolla', 'propinoxato15mg/clonixinato de lisina 100mg', 'Servimedic', 6, 26.10, 50.00, 156.60, 'Pendiente'),
(149, 6, 'ovumix', 'ovulos vaginales', 'metronidazol, sulfato neomicina, centella asiatica', 'Servimedic', 1, 172.26, 255.00, 172.26, 'Pendiente'),
(150, 6, 'Gesimax 150mg/5ml', 'suspension 60ml', 'naproxeno', 'Servimedic', 2, 40.00, 65.00, 80.00, 'Pendiente'),
(151, 6, 'Paracetamol Denk 500mg', '20 comprimidos', 'Paracetamol', 'Servimedic', 2, 29.50, 50.00, 59.00, 'Pendiente'),
(152, 6, 'Dolvi plex', '10 tabletas', 'Metamizol 500mg', 'Servimedic', 1, 9.00, 20.00, 9.00, 'Pendiente'),
(153, 6, 'Melanoblock', 'Crema Facial', 'aqua cetearyl alcohol', 'Servimedic', 5, 162.00, 375.00, 810.00, 'Pendiente'),
(154, 6, 'regenhial crema', 'Crema Facial', 'Acido hialuronico 1%', 'Servimedic', 4, 282.85, 450.00, 1131.40, 'Pendiente'),
(155, 6, 'Regenhial Gel', 'Crema Facial', 'Acido hialuronico 1%', 'Servimedic', 3, 194.00, 275.00, 582.00, 'Pendiente'),
(156, 6, 'Hidribet 10%', 'Locion topica', 'Glicerin, sorbitan', 'Servimedic', 1, 74.15, 125.00, 74.15, 'Pendiente'),
(157, 6, 'Umbrella', 'Protector solar facial', 'aqua,penylene glycol', 'Servimedic', 2, 165.64, 225.00, 331.28, 'Pendiente'),
(158, 6, 'Figure active', '14 sobres', 'carnitina,triptofano,buchu', 'Servimedic', 3, 217.90, 300.00, 653.70, 'Pendiente'),
(159, 6, 'Ureactiv 10%', 'Crema humectante', 'carbamida -urea', 'Servimedic', 1, 95.42, 155.00, 95.42, 'Pendiente'),
(160, 6, 'Regenhial Gel Oral', 'Enjuague bucal', 'Acido hialuronico 250mg', 'Servimedic', 4, 110.00, 200.00, 440.00, 'Pendiente'),
(161, 6, 'Claribac 500mg', '10 tabletas', 'Claritromicina', 'Servimedic', 2, 151.46, 325.00, 302.92, 'Pendiente'),
(162, 6, 'Unocef 400mg', '8 Comprimidos', 'Cefixima', 'Servimedic', 5, 201.35, 300.00, 1006.75, 'Pendiente'),
(163, 6, 'Quinolide 500mg', '10 tabletas', 'Ciprofloxacina', 'Servimedic', 14, 27.50, 100.00, 385.00, 'Pendiente'),
(164, 6, 'Supraxil 1g', 'Vial', 'Ceftriaxona', 'Servimedic', 2, 45.00, 130.00, 90.00, 'Pendiente'),
(165, 6, 'Tiamina 100mg', 'Vial', 'Tiamina 10ml', 'Servimedic', 3, 9.00, 25.00, 27.00, 'Pendiente'),
(166, 6, 'Complejo B', 'Vial', 'Complejo B 10ML', 'Servimedic', 3, 12.00, 25.00, 36.00, 'Pendiente'),
(167, 6, 'Celedexa', 'Jarabe 120ml', 'Betametasona dexclorfeniramina', 'Servimedic', 5, 72.80, 140.00, 364.00, 'Pendiente'),
(168, 6, 'Indugastric 120ml', 'Jarabe', 'regaliz,resina,', 'Servimedic', 1, 118.14, 210.00, 118.14, 'Pendiente'),
(169, 6, 'Ambiare', '10 Tabletas', 'Dexclorfeniramina,betametasona', 'Servimedic', 2, 35.00, 55.00, 70.00, 'Pendiente'),
(170, 6, 'Fenobrox', 'suspension', 'Cloperastina', 'Servimedic', 4, 36.00, 110.00, 144.00, 'Pendiente'),
(171, 6, 'Acla-Med Bid 400mg', 'Suspension', 'Amoxicilina+acido clavulanico', 'Servimedic', 4, 51.20, 125.00, 204.80, 'Pendiente'),
(172, 6, 'Vaginsol F', '7 ovulos vaginales', 'Clindamicina100mg+clotrimazol 200mg', 'Servimedic', 2, 244.00, 360.00, 488.00, 'Pendiente'),
(173, 6, 'Ferra Q', '30 Capsulas', 'Acido folico1000mcg+hierro aminoquelado 30mg', 'Servimedic', 1, 55.20, 115.00, 55.20, 'Pendiente'),
(174, 6, 'Hepamob', '30 Comprimidos', 'Cilimarina+complejo b', 'Servimedic', 2, 90.00, 150.00, 180.00, 'Pendiente'),
(175, 6, 'Prednitab 50mg', '20 Tabletas', 'Prednisona', 'Servimedic', 4, 265.30, 385.00, 1061.20, 'Pendiente'),
(176, 6, 'Lansogastric 15Mg', '15 Sobres', 'Lansoprazol', 'Servimedic', 3, 34.00, 90.00, 102.00, 'Pendiente'),
(177, 6, 'Sargikem', '30 Capsulas', 'Aspartato de L arginina', 'Servimedic', 1, 83.60, 165.00, 83.60, 'Pendiente'),
(178, 6, 'Lergiless', 'Jarabe 60ml', 'loratadina 5mg/betametasona 0.25mg', 'Servimedic', 2, 64.00, 110.00, 128.00, 'Pendiente'),
(179, 6, 'Oriprox-M', '10 Tabletas', 'Moxifloxacino 400mg', 'Servimedic', 5, 225.00, 400.00, 1125.00, 'Pendiente'),
(180, 6, 'Tibonella', '28 Tabletas', 'Tibolona 2.5mg', 'Servimedic', 4, 170.00, 290.00, 680.00, 'Pendiente'),
(181, 6, 'Metocarban AC', '30 Tabletas', 'Metocarbamol400mg/acetaminofen 250mg', 'Servimedic', 3, 60.20, 110.00, 180.60, 'Pendiente'),
(182, 6, 'Dyflam', 'Gotas 15ml', 'Diclofenaco resinato', 'Servimedic', 5, 21.40, 50.00, 107.00, 'Pendiente'),
(183, 6, 'Cefina 100mg/5ml', 'Suspension 100ml', 'Cefixima', 'Servimedic', 1, 90.00, 220.00, 90.00, 'Pendiente'),
(184, 6, 'Floxa-Pack 10 Dias', '10 Comprimidos', 'Lansoprazol 30mg/levofloxacina 500mg/amoxicilina 500mg', 'Servimedic', 2, 190.00, 450.00, 380.00, 'Pendiente'),
(185, 6, 'Floxa- Pack ES 10 Dias', '10 Comprimidos', 'Esomeprazol 40mg/levofloxacina 500mg/amoxicilina 500mg', 'Servimedic', 1, 213.00, 515.00, 213.00, 'Pendiente'),
(186, 6, 'Arginina Junior', '10 ampollas bebibles', 'aspartato de arginina 1g/5ml', 'Servimedic', 2, 70.00, 95.00, 140.00, 'Pendiente'),
(187, 6, 'Arginina Forte', '10 ampollas bebibles', 'Aspartato de arginina 5g/10ml', 'Servimedic', 2, 98.00, 135.00, 196.00, 'Pendiente'),
(188, 6, 'Redical', '28 Sobres', 'Esomeprazol 10mg', 'Servimedic', 1, 214.60, 420.00, 214.60, 'Pendiente'),
(189, 6, 'Gripcol D', 'Susspencion 120ml', 'Fenilefrina,dextrometorfano,acetaminofen', 'Servimedic', 1, 28.00, 55.00, 28.00, 'Pendiente'),
(190, 6, 'Deflarin 6mg', '10 Comprimidos', 'Deflazacort', 'Servimedic', 5, 77.00, 135.00, 385.00, 'Pendiente'),
(191, 6, 'Totalvit ZINC', 'Jarabe 120ml', 'Sulfatode zinc 20mg', 'Servimedic', 2, 40.00, 110.00, 80.00, 'Pendiente'),
(192, 6, 'Musculare 10mg', '15 Tabletas', 'Clorhidrato de ciclobenzaprina', 'Servimedic', 5, 102.28, 145.00, 511.40, 'Pendiente'),
(193, 6, 'Musculare 5mg', '15 Tabletas', 'Clorhidrato de ciclobenzaprina', 'Servimedic', 5, 91.58, 125.00, 457.90, 'Pendiente'),
(194, 6, 'Dyflam 120ml', 'Suspension', 'Diclofenaco 9mg/5ml', 'Servimedic', 5, 34.00, 65.00, 170.00, 'Pendiente'),
(195, 6, 'Broncodil 120ml', 'Suapension', 'Carboximetilcisteina', 'Servimedic', 5, 40.00, 110.00, 200.00, 'Pendiente'),
(196, 6, 'Gastrexx 40mg', '15 Capsulas', 'Esomeprazol', 'Servimedic', 5, 220.26, 600.00, 1101.30, 'Pendiente'),
(197, 6, 'Levamisol 12.5mg/5ml', 'Sobres bebibles', 'Diclofenaco 50mg+tiocolchicosico', 'Servimedic', 50, 12.38, 22.00, 619.00, 'Pendiente'),
(198, 6, 'Nocicep 10mg', '10 Tabletas', 'Rupatadina', 'Servimedic', 4, 56.40, 130.00, 225.60, 'Pendiente'),
(199, 6, 'Levax', 'Suspension 120ml', 'Levamisol 12.5mg/5ml', 'Servimedic', 2, 61.60, 100.00, 123.20, 'Pendiente'),
(200, 6, 'Levax', '10 tabletas', 'Levamisol 75mg', 'Servimedic', 2, 107.10, 165.00, 214.20, 'Pendiente'),
(201, 6, 'Sinervit', '30 Capsulas', 'Tiamina,piridoxina,cianocobalamina', 'Servimedic', 1, 90.00, 190.00, 90.00, 'Pendiente'),
(202, 6, 'Dinivanz Compuesto', 'kit para nebulizar', 'Bromuro de ipatropium/salino/salbutamol', 'Servimedic', 5, 103.44, 240.00, 517.20, 'Pendiente'),
(203, 6, 'Betasporina', 'Vial', 'Ceftriaxona 1g', 'Servimedic', 10, 55.00, 140.00, 550.00, 'Pendiente'),
(204, 6, 'Ceftrian', 'Vial', 'Ceftriaxona 1g', 'Servimedic', 3, 35.00, 110.00, 105.00, 'Pendiente'),
(205, 6, 'Dipronova', 'Vial', 'Betamethasone dipropionate', 'Servimedic', 1, 60.00, 180.00, 60.00, 'Pendiente'),
(206, 6, 'Esomeprakem', '10 Capsulas', 'Esomeprazol 40mg/levofloxacina 500mg/amoxicilina 500mg', 'Servimedic', 3, 36.00, 70.00, 108.00, 'Pendiente'),
(207, 6, 'Nocpidem', '30 Comprimidos', 'Zolpidem 10mg', 'Servimedic', 3, 225.60, 350.00, 676.80, 'Pendiente'),
(208, 6, 'Triviplex 25000', 'Ampolla 2ml', 'Vitaminas B12,B2,B12', 'Servimedic', 5, 19.00, 45.00, 95.00, 'Pendiente'),
(209, 6, 'Dexa-triviplex', '2 ampollas', 'Vitaminas neurotropas+dexa', 'Servimedic', 5, 29.00, 55.00, 145.00, 'Pendiente'),
(210, 6, 'Dolo Triviplex', '2 ampollas', 'Diclofenaco+vitaminas', 'Servimedic', 10, 23.00, 50.00, 230.00, 'Pendiente'),
(211, 6, 'Suero Hidravida', 'suero oral', 'sabor coco', 'Servimedic', 12, 14.30, 18.00, 171.60, 'Pendiente'),
(212, 6, 'Ledestil', 'ampollas', 'carbohidratos,lipidos totales', 'Servimedic', 24, 52.33, 100.00, 1255.92, 'Pendiente'),
(213, 6, 'Agujas Hipodermicas', '100 Agujas', '31GX3/16', 'Servimedic', 5, 90.00, 140.00, 450.00, 'Pendiente'),
(214, 6, 'Enna', 'Esfera', '', 'Servimedic', 1, 0.00, 450.00, 0.00, 'Pendiente'),
(215, 6, 'Nircip', 'Frasco Inyectable', 'Ciprofloxacina 200mg/100m', 'Servimedic', 6, 23.00, 80.00, 138.00, 'Pendiente'),
(216, 6, 'Ampidelt', 'Vial', 'Ampi+sulbactam', 'Servimedic', 30, 15.75, 80.00, 472.50, 'Pendiente'),
(217, 6, 'Tiamina bonin', 'Vial', 'Tiamina', 'Servimedic', 10, 9.10, 25.00, 91.00, 'Pendiente'),
(218, 6, 'Fluconazol 100ml', 'Frasco Inyectable', 'Fluconazol 200mg/100ml', 'Servimedic', 2, 32.70, 0.00, 65.40, 'Pendiente'),
(219, 6, 'Bactemicina 600mg/4ml', 'Ampolla', 'Clindamicina', 'Servimedic', 5, 30.50, 0.00, 152.50, 'Pendiente'),
(220, 6, 'Jeringas de 20ml', 'Insumo', 'Insumo', 'Servimedic', 195, 1.70, 0.00, 331.50, 'Pendiente'),
(221, 6, 'Jeringas de 3ml', 'Insumo', 'Insumo', 'Servimedic', 290, 0.73, 0.00, 211.70, 'Pendiente'),
(222, 6, 'Jeringa de 1ml', 'Insumo', 'Insumo', 'Servimedic', 500, 1.45, 0.00, 725.00, 'Pendiente'),
(223, 6, 'Baja Lenguas', 'Insumo', 'Insumo', 'Servimedic', 12, 0.00, 0.00, 0.00, 'Pendiente'),
(224, 6, 'Angiocath #22', 'Insumo', 'Insumo', 'Servimedic', 150, 4.10, 0.00, 615.00, 'Pendiente'),
(225, 6, 'Angiocath #18', 'Insumo', 'Insumo', 'Servimedic', 50, 4.10, 0.00, 205.00, 'Pendiente'),
(226, 6, 'Angiocath #20', 'Insumo', 'Insumo', 'Servimedic', 50, 4.10, 0.00, 205.00, 'Pendiente'),
(227, 6, 'Angiocath #24', 'Insumo', 'Insumo', 'Servimedic', 96, 4.10, 0.00, 393.60, 'Pendiente'),
(228, 6, 'Lidocaina c/ Epinefrina', 'Insumo', 'Insumo', 'Servimedic', 3, 36.00, 0.00, 108.00, 'Pendiente'),
(229, 6, 'LIdocaina SIN/ Epinefrina', 'Insumo', 'Insumo', 'Servimedic', 3, 32.00, 0.00, 96.00, 'Pendiente'),
(230, 6, 'Metoclopramida', 'Ampolla 2ml', 'Metoclopramida 10mg', 'Servimedic', 110, 2.00, 50.00, 220.00, 'Pendiente'),
(231, 6, 'Ranitidina', 'Ampolla 2ml', 'Ranitidina 50mg', 'Servimedic', 200, 2.00, 50.00, 400.00, 'Pendiente'),
(232, 6, 'Tramadol', 'Ampolla 2ml', 'Tramadol 100mg', 'Servimedic', 100, 2.40, 50.00, 240.00, 'Pendiente'),
(233, 6, 'Dexametasona', 'Ampolla 1ml', 'Dexametasona 4mg', 'Servimedic', 109, 2.50, 50.00, 272.50, 'Pendiente'),
(234, 6, 'Dipirona', 'Ampolla 2ml', 'Dipirona 1g', 'Servimedic', 204, 3.00, 50.00, 612.00, 'Pendiente'),
(235, 6, 'Selestina', 'Ampolla 2ml', 'Dexa 8mg', 'Servimedic', 8, 2.50, 50.00, 20.00, 'Pendiente'),
(236, 6, 'Parenten', 'Ampolla 2ml', 'Diazepoam 10mg', 'Servimedic', 3, 10.00, 75.00, 30.00, 'Pendiente'),
(237, 6, 'Jeringas de 5ml', 'Insumo', 'Insumo', 'Servimedic', 200, 0.37, 0.00, 74.00, 'Pendiente'),
(238, 6, 'Jeringas de 10ml', 'Insumo', 'Insumo', 'Servimedic', 95, 0.58, 0.00, 55.10, 'Pendiente'),
(239, 6, 'Clorfeniramida', 'Ampolla 2ml', 'Clorfeniramida 10mg', 'Servimedic', 25, 2.10, 50.00, 52.50, 'Pendiente'),
(240, 6, 'Neo-Melumbrina', 'Ampolla 2ml', 'Metamizol 500mg', 'Servimedic', 60, 6.75, 50.00, 405.00, 'Pendiente'),
(241, 6, 'Ceftriaxona', 'Vial Polvo', 'Ceftriaxona 1g', 'Servimedic', 56, 7.70, 0.00, 431.20, 'Pendiente'),
(242, 6, 'Meropenem', 'Vial Polvo', 'Meropenem 500mg', 'Servimedic', 10, 32.00, 0.00, 320.00, 'Pendiente'),
(243, 6, 'Esomeprazol', 'Vial Polvo', 'Esomeprazol 40mg', 'Servimedic', 2, 27.00, 80.00, 54.00, 'Pendiente'),
(244, 6, 'Bonadiona', 'Ampolla 1ml', 'Vitamian K 10MG', 'Servimedic', 3, 9.00, 25.00, 27.00, 'Pendiente'),
(245, 6, 'Omeprazol', 'Vial Polvo', 'Omeprazol 40mg', 'Servimedic', 62, 9.80, 80.00, 607.60, 'Pendiente'),
(246, 6, 'Diclofenaco', 'Ampolla 3ml', 'Diclofenaco 75mg', 'Servimedic', 100, 1.80, 50.00, 180.00, 'Pendiente'),
(247, 6, 'Nauseol', 'Ampolla 1ml', 'Dimehidrato 50mg', 'Servimedic', 50, 6.91, 50.00, 345.50, 'Pendiente'),
(248, 6, 'Furosemida', 'Ampolla 2ml', 'Furosemida 20mg', 'Servimedic', 200, 1.50, 50.00, 300.00, 'Pendiente'),
(249, 6, 'Amikacina', 'Ampolla 2ml', 'Amikacina 500mg', 'Servimedic', 40, 5.40, 80.00, 216.00, 'Pendiente'),
(250, 6, 'Sello Heparina', 'Insumo', 'Insumo', 'Servimedic', 216, 1.35, 0.00, 291.60, 'Pendiente'),
(251, 6, 'Guantes descartables', 'Magica', 'Talla M', 'Servimedic', 5, 0.00, 0.00, 0.00, 'Pendiente'),
(252, 6, 'Agujas hipodermicas', 'Steril', 'aguja 24GX1', 'Servimedic', 2, 0.00, 0.00, 0.00, 'Pendiente'),
(253, 6, 'Nylon #3-0', 'Atramat', '3-0', 'Servimedic', 50, 0.00, 0.00, 0.00, 'Pendiente'),
(254, 6, 'Micropore 1/2', 'Nexcare', 'color blanco', 'Servimedic', 11, 0.00, 0.00, 0.00, 'Pendiente'),
(255, 6, 'Bisturi #15', 'Sterile', 'Insumo', 'Servimedic', 57, 0.00, 0.00, 0.00, 'Pendiente'),
(256, 6, 'Blood Lancets', '100 piezas', 'Lancetas via med', 'Servimedic', 6, 0.00, 0.00, 0.00, 'Pendiente'),
(257, 6, 'Accu-chek', '50 piexas', 'tiras para glucometro', 'Servimedic', 4, 0.00, 0.00, 0.00, 'Pendiente'),
(258, 6, 'Sonda Alimentacion #12', '#12', 'sondas', 'Servimedic', 9, 0.00, 0.00, 0.00, 'Pendiente'),
(259, 6, 'Bolsa recolectora orina', 'de cama', 'Adulto', 'Servimedic', 10, 0.00, 0.00, 0.00, 'Pendiente'),
(260, 6, 'Micropore 1p', 'color blanco', 'Insumo', 'Servimedic', 24, 0.00, 0.00, 0.00, 'Pendiente'),
(261, 6, 'Micropore 2p', 'color blanco', 'Insumo', 'Servimedic', 12, 0.00, 0.00, 0.00, 'Pendiente'),
(262, 6, 'Mascarillas para nebulizar', 'neonatal', 'Insumo', 'Servimedic', 2, 0.00, 0.00, 0.00, 'Pendiente'),
(263, 6, 'Mascarillas para nebulizar', 'Pediatrico', 'Insumo', 'Servimedic', 4, 0.00, 0.00, 0.00, 'Pendiente'),
(264, 6, 'Mascarillas para nebulizar', 'Adulto', 'Insumo', 'Servimedic', 4, 0.00, 0.00, 0.00, 'Pendiente'),
(265, 6, 'Sonda alimentacion #5', 'Operson', 'Insumo', 'Servimedic', 5, 0.00, 0.00, 0.00, 'Pendiente'),
(266, 6, 'Sonda alimentacion #8', 'Operson', 'Insumo', 'Servimedic', 4, 0.00, 0.00, 0.00, 'Pendiente'),
(267, 6, 'Bolsa recolectora orina', 'Pediatrico', 'Sterile', 'Servimedic', 31, 0.00, 0.00, 0.00, 'Pendiente'),
(268, 6, 'Canula Binasal', 'Adulto', 'Insumo', 'Servimedic', 5, 0.00, 0.00, 0.00, 'Pendiente'),
(269, 6, 'Venoset', 'Greetmed', 'Insumo', 'Servimedic', 88, 0.00, 0.00, 0.00, 'Pendiente');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `purchase_payments`
--

CREATE TABLE `purchase_payments` (
  `id` int NOT NULL,
  `purchase_header_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) DEFAULT 'Efectivo',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rayos_x`
--

CREATE TABLE `rayos_x` (
  `id_rayos_x` int NOT NULL,
  `id_paciente` int NOT NULL,
  `nombre_paciente` varchar(255) NOT NULL,
  `tipo_estudio` varchar(255) NOT NULL,
  `cobro` decimal(10,2) NOT NULL,
  `fecha_estudio` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario` varchar(255) DEFAULT NULL,
  `tipo_pago` enum('Efectivo','Tarjeta','Transferencia') DEFAULT 'Efectivo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Volcado de datos para la tabla `rayos_x`
--

INSERT INTO `rayos_x` (`id_rayos_x`, `id_paciente`, `nombre_paciente`, `tipo_estudio`, `cobro`, `fecha_estudio`, `usuario`, `tipo_pago`) VALUES
(1, 28, 'Claudia Rojas', 'Mano', 150.00, '2026-01-24 08:07:17', 'Anye', 'Transferencia'),
(2, 28, 'Claudia Rojas', 'Torax', 150.00, '2026-01-24 17:45:40', 'Anye', 'Efectivo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reactivos_laboratorio`
--

CREATE TABLE `reactivos_laboratorio` (
  `id_reactivo` int NOT NULL,
  `codigo_reactivo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_reactivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fabricante` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proveedor` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero_lote` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero_serie` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_fabricacion` date DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `cantidad_disponible` decimal(10,3) NOT NULL DEFAULT '0.000',
  `unidad_medida` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ml, piezas, tests, etc',
  `cantidad_minima` decimal(10,3) DEFAULT '10.000',
  `costo_unitario` decimal(10,2) DEFAULT NULL,
  `ubicacion` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Refrigeradora A, Estante 3, etc',
  `condiciones_almacenamiento` text COLLATE utf8mb4_unicode_ci COMMENT 'Temperatura, luz, humedad',
  `estado` enum('Disponible','Por_Vencer','Vencido','Agotado','En_Cuarentena') COLLATE utf8mb4_unicode_ci DEFAULT 'Disponible',
  `notas` text COLLATE utf8mb4_unicode_ci,
  `fecha_ingreso` date DEFAULT NULL,
  `ingresado_por` int DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reportes_estadisticas`
--

CREATE TABLE `reportes_estadisticas` (
  `id_reporte` int NOT NULL,
  `tipo_reporte` varchar(50) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `datos` json NOT NULL,
  `fecha_generacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_generacion` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reservas_inventario`
--

CREATE TABLE `reservas_inventario` (
  `id_reserva` int NOT NULL,
  `id_inventario` int NOT NULL,
  `cantidad` int NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `fecha_reserva` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `resultados_laboratorio`
--

CREATE TABLE `resultados_laboratorio` (
  `id_resultado` int NOT NULL,
  `id_orden_prueba` int NOT NULL,
  `id_parametro` int NOT NULL,
  `valor_resultado` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Valor como texto',
  `valor_numerico` decimal(12,4) DEFAULT NULL COMMENT 'Para facilitar queries y análisis',
  `fuera_rango` enum('Normal','Alto','Bajo','Crítico_Alto','Crítico_Bajo') COLLATE utf8mb4_unicode_ci DEFAULT 'Normal',
  `valor_referencia` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Rango aplicable según paciente',
  `unidad_medida` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metodo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Método de análisis utilizado',
  `validado` tinyint(1) DEFAULT '0',
  `fecha_resultado` datetime DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `procesado_por` int DEFAULT NULL,
  `validado_por` int DEFAULT NULL,
  `fecha_validacion` datetime DEFAULT NULL,
  `firma_digital` text COLLATE utf8mb4_unicode_ci COMMENT 'Hash o firma del validador',
  `enviado_medico` tinyint(1) DEFAULT '0',
  `fecha_envio_medico` datetime DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `resultados_laboratorio`
--

INSERT INTO `resultados_laboratorio` (`id_resultado`, `id_orden_prueba`, `id_parametro`, `valor_resultado`, `valor_numerico`, `fuera_rango`, `valor_referencia`, `unidad_medida`, `metodo`, `validado`, `fecha_resultado`, `observaciones`, `procesado_por`, `validado_por`, `fecha_validacion`, `firma_digital`, `enviado_medico`, `fecha_envio_medico`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 1, 2, '5', 5.0000, 'Normal', NULL, NULL, NULL, 1, '2026-01-23 05:01:51', NULL, 1, 1, '2026-01-23 05:15:59', NULL, 0, NULL, '2026-01-23 05:01:51', '2026-01-23 05:15:59');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `signos_vitales`
--

CREATE TABLE `signos_vitales` (
  `id_signo` int NOT NULL,
  `id_encamamiento` int NOT NULL,
  `fecha_registro` datetime NOT NULL,
  `temperatura` decimal(4,2) DEFAULT NULL COMMENT 'Celsius',
  `presion_sistolica` int DEFAULT NULL COMMENT 'mmHg',
  `presion_diastolica` int DEFAULT NULL COMMENT 'mmHg',
  `pulso` int DEFAULT NULL COMMENT 'latidos por minuto',
  `frecuencia_respiratoria` int DEFAULT NULL COMMENT 'respiraciones por minuto',
  `saturacion_oxigeno` decimal(5,2) DEFAULT NULL COMMENT 'Porcentaje',
  `peso_kg` decimal(6,2) DEFAULT NULL,
  `talla_cm` decimal(5,2) DEFAULT NULL,
  `imc` decimal(5,2) GENERATED ALWAYS AS ((case when (`talla_cm` > 0) then (`peso_kg` / ((`talla_cm` / 100) * (`talla_cm` / 100))) else NULL end)) STORED,
  `glucometria` decimal(5,2) DEFAULT NULL COMMENT 'mg/dL',
  `dolor_escala` int DEFAULT NULL COMMENT 'Escala 0-10',
  `estado_conciencia` enum('Alerta','Somnoliento','Estuporoso','Comatoso') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci,
  `registrado_por` int DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ultrasonidos`
--

CREATE TABLE `ultrasonidos` (
  `id_ultrasonido` int NOT NULL,
  `id_paciente` int NOT NULL,
  `nombre_paciente` varchar(255) NOT NULL,
  `tipo_ultrasonido` varchar(255) NOT NULL,
  `cobro` decimal(10,2) NOT NULL,
  `fecha_ultrasonido` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario` varchar(255) DEFAULT NULL,
  `tipo_pago` enum('Efectivo','Tarjeta','Transferencia') DEFAULT 'Efectivo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Volcado de datos para la tabla `ultrasonidos`
--

INSERT INTO `ultrasonidos` (`id_ultrasonido`, `id_paciente`, `nombre_paciente`, `tipo_ultrasonido`, `cobro`, `fecha_ultrasonido`, `usuario`, `tipo_pago`) VALUES
(1, 21, 'Fernando Castro', 'HOMBRO', 500.00, '2026-01-24 08:07:31', 'Anye', 'Transferencia'),
(2, 16, 'Carmen Flores', 'HOMBRO', 500.00, '2026-01-24 17:45:56', 'Anye', 'Transferencia');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `idUsuario` int NOT NULL,
  `usuario` varchar(255) NOT NULL,
  `password` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `apellido` varchar(255) NOT NULL,
  `especialidad` varchar(255) DEFAULT NULL,
  `tipoUsuario` enum('admin','doc','user','') NOT NULL,
  `clinica` varchar(255) NOT NULL,
  `telefono` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `permisos_modulos` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`idUsuario`, `usuario`, `password`, `nombre`, `apellido`, `especialidad`, `tipoUsuario`, `clinica`, `telefono`, `email`, `permisos_modulos`) VALUES
(1, 'admin', 'admin', 'Samuel', 'Ramirez', 'Ingeniero en Sistemas', 'admin', 'Centro Médico Herrera Saenz', '49617032', 'samuel.ramirez25prs@gmail.com', '{\"hospitalization\": true, \"laboratory\": true, \"hospitalization_admin\": true, \"laboratory_admin\": true, \"view_all_patients\": true, \"inventory\": true, \"billing\": true, \"reports\": true, \"appointments\": true, \"patients\": true, \"medications\": true, \"settings\": true}'),
(6, 'jrivas_farmacia', 'cmhs', 'Jeimi', 'Rivas', 'Farmacia', 'user', 'Centro Médico Herrera Saenz', '0000', 'jeimi@example.com', '{\"hospitalization\": true, \"laboratory\": true, \"hospitalization_admin\": false, \"laboratory_admin\": false, \"view_all_patients\": true, \"inventory\": false, \"billing\": true, \"reports\": false, \"appointments\": true, \"patients\": true, \"medications\": true, \"settings\": false}'),
(7, 'atello', 'cmhs', 'Anye', 'Tello', 'Recepción y Cobros', 'user', 'Centro Médico Herrera Saenz', '0000', 'anye@example.com', '{\"hospitalization\": true, \"laboratory\": true, \"hospitalization_admin\": false, \"laboratory_admin\": false, \"view_all_patients\": true, \"inventory\": false, \"billing\": true, \"reports\": false, \"appointments\": true, \"patients\": true, \"medications\": true, \"settings\": false}'),
(8, 'fherrera', 'cmhs', 'Francisco', 'Herrera', 'Administrador General', 'admin', 'Centro Médico Herrera Saenz', '0000', 'francisco@example.com', '{\"hospitalization\": true, \"laboratory\": true, \"hospitalization_admin\": true, \"laboratory_admin\": true, \"view_all_patients\": true, \"inventory\": true, \"billing\": true, \"reports\": true, \"appointments\": true, \"patients\": true, \"medications\": true, \"settings\": true}'),
(9, 'jannyar', 'cmhs', 'Jannya', 'Rivas', 'Administrador General', 'admin', 'Centro Médico Herrera Saenz', '0000', 'jannya@example.com', '{\"hospitalization\": true, \"laboratory\": true, \"hospitalization_admin\": true, \"laboratory_admin\": true, \"view_all_patients\": true, \"inventory\": true, \"billing\": true, \"reports\": true, \"appointments\": true, \"patients\": true, \"medications\": true, \"settings\": true}'),
(10, 'epineda', 'cmhs', 'Enrique', 'Pineda', 'Administrador General', 'admin', 'Centro Médico Herrera Saenz', '0000', 'enrique@example.com', '{\"hospitalization\": true, \"laboratory\": true, \"hospitalization_admin\": true, \"laboratory_admin\": true, \"view_all_patients\": true, \"inventory\": true, \"billing\": true, \"reports\": true, \"appointments\": true, \"patients\": true, \"medications\": true, \"settings\": true}'),
(11, 'iherrera', 'cmhs', 'Isabel', 'Herrera', 'Administrador General', 'admin', 'Centro Médico Herrera Saenz', '0000', 'isabel@example.com', '{\"hospitalization\": true, \"laboratory\": true, \"hospitalization_admin\": true, \"laboratory_admin\": true, \"view_all_patients\": true, \"inventory\": true, \"billing\": true, \"reports\": true, \"appointments\": true, \"patients\": true, \"medications\": true, \"settings\": true}'),
(12, 'ysantos', 'cmhs', 'Yenifer', 'Santos', 'Farmacia Interna y Controles', 'admin', 'Centro Médico Herrera Saenz', '0000', 'yenifer@example.com', '{\"hospitalization\": true, \"laboratory\": true, \"hospitalization_admin\": true, \"laboratory_admin\": true, \"view_all_patients\": true, \"inventory\": true, \"billing\": true, \"reports\": true, \"appointments\": true, \"patients\": true, \"medications\": true, \"settings\": true}'),
(13, 'lvalle', 'cmhs', 'Luis Carlos', 'del Valle', 'Medicina Interna', 'doc', 'Centro Médico Herrera Saenz', '0000', 'luis@example.com', '{\"hospitalization\": true, \"laboratory\": true, \"hospitalization_admin\": false, \"laboratory_admin\": false, \"view_all_patients\": true, \"inventory\": false, \"billing\": true, \"reports\": false, \"appointments\": true, \"patients\": true, \"medications\": true, \"settings\": false}'),
(14, 'jrivas_saenz', 'cmhs', 'Jannya', 'Rivas Sáenz', 'Medico y cirujano', 'doc', 'Centro Médico Herrera Saenz', '0000', 'jannyas@example.com', '{\"hospitalization\": true, \"laboratory\": true, \"hospitalization_admin\": false, \"laboratory_admin\": false, \"view_all_patients\": true, \"inventory\": false, \"billing\": true, \"reports\": false, \"appointments\": true, \"patients\": true, \"medications\": true, \"settings\": false}'),
(15, 'erivas', 'cmhs', 'Estuardo', 'Rivas', 'Ginecólogo y Obstetra', 'doc', 'Centro Médico Herrera Saenz', '0000', 'estuardo@example.com', '{\"hospitalization\": true, \"laboratory\": true, \"hospitalization_admin\": false, \"laboratory_admin\": false, \"view_all_patients\": true, \"inventory\": false, \"billing\": true, \"reports\": false, \"appointments\": true, \"patients\": true, \"medications\": true, \"settings\": false}'),
(16, 'mmutas', 'cmhs', 'Mayeli', 'Mutás Ochoa', 'Medicina Interna', 'doc', 'Centro Médico Herrera Saenz', '0000', 'mayeli@example.com', '{\"hospitalization\": true, \"laboratory\": true, \"hospitalization_admin\": false, \"laboratory_admin\": false, \"view_all_patients\": true, \"inventory\": false, \"billing\": true, \"reports\": false, \"appointments\": true, \"patients\": true, \"medications\": true, \"settings\": false}'),
(17, 'lrecinos', 'cmhs', 'Libny', 'Recinos', 'Pediatra', 'doc', 'Centro Médico Herrera Saenz', '0000', 'libny@example.com', '{\"hospitalization\": true, \"laboratory\": true, \"hospitalization_admin\": false, \"laboratory_admin\": false, \"view_all_patients\": true, \"inventory\": false, \"billing\": true, \"reports\": false, \"appointments\": true, \"patients\": true, \"medications\": true, \"settings\": false}'),
(18, 'iherrera_nutri', 'cmhs', 'Isabel', 'Herrera', 'Nutricionista', 'doc', 'Centro Médico Herrera Saenz', '0000', 'isabel_n@example.com', '{\"hospitalization\": true, \"laboratory\": true, \"hospitalization_admin\": false, \"laboratory_admin\": false, \"view_all_patients\": true, \"inventory\": false, \"billing\": true, \"reports\": false, \"appointments\": true, \"patients\": true, \"medications\": true, \"settings\": false}');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas`
--

CREATE TABLE `ventas` (
  `id_venta` int NOT NULL,
  `id_usuario` int DEFAULT NULL,
  `fecha_venta` datetime DEFAULT CURRENT_TIMESTAMP,
  `nombre_cliente` varchar(100) DEFAULT NULL,
  `nit_cliente` varchar(50) DEFAULT 'C/F',
  `tipo_pago` enum('Efectivo','Tarjeta','Seguro Médico','Transferencia') DEFAULT NULL,
  `total` decimal(10,2) DEFAULT '0.00',
  `estado` enum('Pendiente','Pagado','Cancelado') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Volcado de datos para la tabla `ventas`
--

INSERT INTO `ventas` (`id_venta`, `id_usuario`, `fecha_venta`, `nombre_cliente`, `tipo_pago`, `total`, `estado`) VALUES
(1, 1, '2026-01-24 00:40:32', 'Samuel Ramírez', 'Tarjeta', 90.00, 'Pagado'),
(2, 7, '2026-01-24 11:47:03', 'Samuel', 'Efectivo', 90.00, 'Pagado');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `abonos_hospitalarios`
--
ALTER TABLE `abonos_hospitalarios`
  ADD PRIMARY KEY (`id_abono`),
  ADD KEY `id_cuenta` (`id_cuenta`),
  ADD KEY `registrado_por` (`registrado_por`);

--
-- Indices de la tabla `administracion_medicamentos`
--
ALTER TABLE `administracion_medicamentos`
  ADD PRIMARY KEY (`id_administracion`),
  ADD KEY `id_medicamento` (`id_medicamento`),
  ADD KEY `indicado_por` (`indicado_por`),
  ADD KEY `administrado_por` (`administrado_por`),
  ADD KEY `idx_encamamiento` (`id_encamamiento`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_fecha_admin` (`fecha_administracion`);

--
-- Indices de la tabla `archivos_orden`
--
ALTER TABLE `archivos_orden`
  ADD PRIMARY KEY (`id_archivo`),
  ADD KEY `id_orden_prueba` (`id_orden_prueba`);

--
-- Indices de la tabla `camas`
--
ALTER TABLE `camas`
  ADD PRIMARY KEY (`id_cama`),
  ADD UNIQUE KEY `unique_cama` (`id_habitacion`,`numero_cama`),
  ADD KEY `idx_estado` (`estado`);

--
-- Indices de la tabla `cargos_hospitalarios`
--
ALTER TABLE `cargos_hospitalarios`
  ADD PRIMARY KEY (`id_cargo`),
  ADD KEY `registrado_por` (`registrado_por`),
  ADD KEY `idx_cuenta` (`id_cuenta`),
  ADD KEY `idx_tipo_cargo` (`tipo_cargo`),
  ADD KEY `idx_fecha_cargo` (`fecha_cargo`),
  ADD KEY `idx_cancelado` (`cancelado`);

--
-- Indices de la tabla `catalogo_pruebas`
--
ALTER TABLE `catalogo_pruebas`
  ADD PRIMARY KEY (`id_prueba`),
  ADD UNIQUE KEY `codigo_prueba` (`codigo_prueba`),
  ADD KEY `idx_codigo` (`codigo_prueba`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_categoria` (`categoria`);

--
-- Indices de la tabla `citas`
--
ALTER TABLE `citas`
  ADD PRIMARY KEY (`id_cita`),
  ADD KEY `fk_doctor_cita` (`id_doctor`);

--
-- Indices de la tabla `cobros`
--
ALTER TABLE `cobros`
  ADD PRIMARY KEY (`in_cobro`),
  ADD KEY `paciente_cobro` (`paciente_cobro`);

--
-- Indices de la tabla `compras`
--
ALTER TABLE `compras`
  ADD PRIMARY KEY (`id_compras`);

--
-- Indices de la tabla `control_calidad_lab`
--
ALTER TABLE `control_calidad_lab`
  ADD PRIMARY KEY (`id_control`),
  ADD KEY `realizado_por` (`realizado_por`),
  ADD KEY `aprobado_por` (`aprobado_por`),
  ADD KEY `idx_prueba` (`id_prueba`),
  ADD KEY `idx_fecha` (`fecha_control`),
  ADD KEY `idx_estado` (`estado`);

--
-- Indices de la tabla `cuenta_hospitalaria`
--
ALTER TABLE `cuenta_hospitalaria`
  ADD PRIMARY KEY (`id_cuenta`),
  ADD UNIQUE KEY `id_encamamiento` (`id_encamamiento`),
  ADD KEY `idx_estado_pago` (`estado_pago`);

--
-- Indices de la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_venta` (`id_venta`),
  ADD KEY `id_inventario` (`id_inventario`);

--
-- Indices de la tabla `electrocardiogramas`
--
ALTER TABLE `electrocardiogramas`
  ADD PRIMARY KEY (`id_electro`),
  ADD KEY `electrocardiogramas_ibfk_1` (`id_paciente`),
  ADD KEY `electrocardiogramas_ibfk_2` (`id_doctor`),
  ADD KEY `electrocardiogramas_ibfk_3` (`realizado_por`);

--
-- Indices de la tabla `encamamientos`
--
ALTER TABLE `encamamientos`
  ADD PRIMARY KEY (`id_encamamiento`),
  ADD KEY `id_cama` (`id_cama`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_paciente` (`id_paciente`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_fecha_ingreso` (`fecha_ingreso`),
  ADD KEY `idx_doctor` (`id_doctor`);

--
-- Indices de la tabla `evoluciones_medicas`
--
ALTER TABLE `evoluciones_medicas`
  ADD PRIMARY KEY (`id_evolucion`),
  ADD KEY `idx_encamamiento` (`id_encamamiento`),
  ADD KEY `idx_fecha` (`fecha_evolucion`),
  ADD KEY `idx_doctor` (`id_doctor`);

--
-- Indices de la tabla `examenes_realizados`
--
ALTER TABLE `examenes_realizados`
  ADD UNIQUE KEY `id_examen_realizado` (`id_examen_realizado`);

--
-- Indices de la tabla `habitaciones`
--
ALTER TABLE `habitaciones`
  ADD PRIMARY KEY (`id_habitacion`),
  ADD UNIQUE KEY `numero_habitacion` (`numero_habitacion`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_tipo` (`tipo_habitacion`);

--
-- Indices de la tabla `historial_clinico`
--
ALTER TABLE `historial_clinico`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `id_paciente` (`id_paciente`);

--
-- Indices de la tabla `inventario`
--
ALTER TABLE `inventario`
  ADD PRIMARY KEY (`id_inventario`),
  ADD KEY `idx_codigo_barras` (`codigo_barras`);

--
-- Indices de la tabla `ordenes_laboratorio`
--
ALTER TABLE `ordenes_laboratorio`
  ADD PRIMARY KEY (`id_orden`),
  ADD UNIQUE KEY `numero_orden` (`numero_orden`),
  ADD KEY `id_doctor` (`id_doctor`),
  ADD KEY `id_encamamiento` (`id_encamamiento`),
  ADD KEY `creado_por` (`creado_por`),
  ADD KEY `idx_numero_orden` (`numero_orden`),
  ADD KEY `idx_paciente` (`id_paciente`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_fecha_orden` (`fecha_orden`),
  ADD KEY `idx_prioridad` (`prioridad`);

--
-- Indices de la tabla `orden_pruebas`
--
ALTER TABLE `orden_pruebas`
  ADD PRIMARY KEY (`id_orden_prueba`),
  ADD KEY `procesado_por` (`procesado_por`),
  ADD KEY `validado_por` (`validado_por`),
  ADD KEY `idx_orden` (`id_orden`),
  ADD KEY `idx_prueba` (`id_prueba`),
  ADD KEY `idx_estado` (`estado`);

--
-- Indices de la tabla `pacientes`
--
ALTER TABLE `pacientes`
  ADD PRIMARY KEY (`id_paciente`);

--
-- Indices de la tabla `parametros_pruebas`
--
ALTER TABLE `parametros_pruebas`
  ADD PRIMARY KEY (`id_parametro`),
  ADD KEY `idx_prueba` (`id_prueba`),
  ADD KEY `idx_orden` (`orden_visualizacion`);

--
-- Indices de la tabla `procedimientos_menores`
--
ALTER TABLE `procedimientos_menores`
  ADD PRIMARY KEY (`id_procedimiento`),
  ADD UNIQUE KEY `id_procedimiento` (`id_procedimiento`);

--
-- Indices de la tabla `purchase_headers`
--
ALTER TABLE `purchase_headers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indices de la tabla `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_header_id` (`purchase_header_id`);

--
-- Indices de la tabla `purchase_payments`
--
ALTER TABLE `purchase_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_header_id` (`purchase_header_id`);

--
-- Indices de la tabla `rayos_x`
--
ALTER TABLE `rayos_x`
  ADD PRIMARY KEY (`id_rayos_x`);

--
-- Indices de la tabla `reactivos_laboratorio`
--
ALTER TABLE `reactivos_laboratorio`
  ADD PRIMARY KEY (`id_reactivo`),
  ADD UNIQUE KEY `codigo_reactivo` (`codigo_reactivo`),
  ADD KEY `ingresado_por` (`ingresado_por`),
  ADD KEY `idx_codigo` (`codigo_reactivo`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_vencimiento` (`fecha_vencimiento`);

--
-- Indices de la tabla `reportes_estadisticas`
--
ALTER TABLE `reportes_estadisticas`
  ADD PRIMARY KEY (`id_reporte`);

--
-- Indices de la tabla `reservas_inventario`
--
ALTER TABLE `reservas_inventario`
  ADD PRIMARY KEY (`id_reserva`),
  ADD KEY `id_inventario` (`id_inventario`),
  ADD KEY `session_id` (`session_id`);

--
-- Indices de la tabla `resultados_laboratorio`
--
ALTER TABLE `resultados_laboratorio`
  ADD PRIMARY KEY (`id_resultado`),
  ADD KEY `procesado_por` (`procesado_por`),
  ADD KEY `validado_por` (`validado_por`),
  ADD KEY `idx_orden_prueba` (`id_orden_prueba`),
  ADD KEY `idx_parametro` (`id_parametro`),
  ADD KEY `idx_validado` (`validado`),
  ADD KEY `idx_fuera_rango` (`fuera_rango`);

--
-- Indices de la tabla `signos_vitales`
--
ALTER TABLE `signos_vitales`
  ADD PRIMARY KEY (`id_signo`),
  ADD KEY `registrado_por` (`registrado_por`),
  ADD KEY `idx_encamamiento` (`id_encamamiento`),
  ADD KEY `idx_fecha` (`fecha_registro`);

--
-- Indices de la tabla `ultrasonidos`
--
ALTER TABLE `ultrasonidos`
  ADD PRIMARY KEY (`id_ultrasonido`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`idUsuario`),
  ADD UNIQUE KEY `usuario` (`usuario`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD PRIMARY KEY (`id_venta`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `abonos_hospitalarios`
--
ALTER TABLE `abonos_hospitalarios`
  MODIFY `id_abono` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `administracion_medicamentos`
--
ALTER TABLE `administracion_medicamentos`
  MODIFY `id_administracion` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `archivos_orden`
--
ALTER TABLE `archivos_orden`
  MODIFY `id_archivo` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `camas`
--
ALTER TABLE `camas`
  MODIFY `id_cama` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `cargos_hospitalarios`
--
ALTER TABLE `cargos_hospitalarios`
  MODIFY `id_cargo` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `catalogo_pruebas`
--
ALTER TABLE `catalogo_pruebas`
  MODIFY `id_prueba` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT de la tabla `citas`
--
ALTER TABLE `citas`
  MODIFY `id_cita` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `cobros`
--
ALTER TABLE `cobros`
  MODIFY `in_cobro` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `compras`
--
ALTER TABLE `compras`
  MODIFY `id_compras` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `control_calidad_lab`
--
ALTER TABLE `control_calidad_lab`
  MODIFY `id_control` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cuenta_hospitalaria`
--
ALTER TABLE `cuenta_hospitalaria`
  MODIFY `id_cuenta` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  MODIFY `id_detalle` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `electrocardiogramas`
--
ALTER TABLE `electrocardiogramas`
  MODIFY `id_electro` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `encamamientos`
--
ALTER TABLE `encamamientos`
  MODIFY `id_encamamiento` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `evoluciones_medicas`
--
ALTER TABLE `evoluciones_medicas`
  MODIFY `id_evolucion` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `habitaciones`
--
ALTER TABLE `habitaciones`
  MODIFY `id_habitacion` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `historial_clinico`
--
ALTER TABLE `historial_clinico`
  MODIFY `id_historial` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de la tabla `inventario`
--
ALTER TABLE `inventario`
  MODIFY `id_inventario` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=270;

--
-- AUTO_INCREMENT de la tabla `ordenes_laboratorio`
--
ALTER TABLE `ordenes_laboratorio`
  MODIFY `id_orden` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT de la tabla `orden_pruebas`
--
ALTER TABLE `orden_pruebas`
  MODIFY `id_orden_prueba` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `pacientes`
--
ALTER TABLE `pacientes`
  MODIFY `id_paciente` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT de la tabla `parametros_pruebas`
--
ALTER TABLE `parametros_pruebas`
  MODIFY `id_parametro` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `procedimientos_menores`
--
ALTER TABLE `procedimientos_menores`
  MODIFY `id_procedimiento` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `purchase_headers`
--
ALTER TABLE `purchase_headers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `purchase_items`
--
ALTER TABLE `purchase_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=270;

--
-- AUTO_INCREMENT de la tabla `purchase_payments`
--
ALTER TABLE `purchase_payments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rayos_x`
--
ALTER TABLE `rayos_x`
  MODIFY `id_rayos_x` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `reactivos_laboratorio`
--
ALTER TABLE `reactivos_laboratorio`
  MODIFY `id_reactivo` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reportes_estadisticas`
--
ALTER TABLE `reportes_estadisticas`
  MODIFY `id_reporte` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reservas_inventario`
--
ALTER TABLE `reservas_inventario`
  MODIFY `id_reserva` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `resultados_laboratorio`
--
ALTER TABLE `resultados_laboratorio`
  MODIFY `id_resultado` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `signos_vitales`
--
ALTER TABLE `signos_vitales`
  MODIFY `id_signo` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ultrasonidos`
--
ALTER TABLE `ultrasonidos`
  MODIFY `id_ultrasonido` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `idUsuario` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id_venta` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

-- --------------------------------------------------------

--
-- Estructura para la vista `encamamientos_con_dias`
--
DROP TABLE IF EXISTS `encamamientos_con_dias`;

CREATE ALGORITHM=UNDEFINED DEFINER=`uiewshfkax9viaaw`@`%` SQL SECURITY DEFINER VIEW `encamamientos_con_dias`  AS SELECT `e`.`id_encamamiento` AS `id_encamamiento`, `e`.`id_paciente` AS `id_paciente`, `e`.`id_cama` AS `id_cama`, `e`.`id_doctor` AS `id_doctor`, `e`.`fecha_ingreso` AS `fecha_ingreso`, `e`.`fecha_alta` AS `fecha_alta`, `e`.`motivo_ingreso` AS `motivo_ingreso`, `e`.`diagnostico_ingreso` AS `diagnostico_ingreso`, `e`.`diagnostico_egreso` AS `diagnostico_egreso`, `e`.`estado` AS `estado`, `e`.`tipo_ingreso` AS `tipo_ingreso`, `e`.`notas_ingreso` AS `notas_ingreso`, `e`.`notas_alta` AS `notas_alta`, `e`.`created_by` AS `created_by`, `e`.`fecha_creacion` AS `fecha_creacion`, `e`.`fecha_actualizacion` AS `fecha_actualizacion`, (case when (`e`.`fecha_alta` is null) then (to_days(curdate()) - to_days(cast(`e`.`fecha_ingreso` as date))) else (to_days(`e`.`fecha_alta`) - to_days(`e`.`fecha_ingreso`)) end) AS `dias_hospitalizacion` FROM `encamamientos` AS `e` ;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `abonos_hospitalarios`
--
ALTER TABLE `abonos_hospitalarios`
  ADD CONSTRAINT `abonos_hospitalarios_ibfk_1` FOREIGN KEY (`id_cuenta`) REFERENCES `cuenta_hospitalaria` (`id_cuenta`) ON DELETE CASCADE,
  ADD CONSTRAINT `abonos_hospitalarios_ibfk_2` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`idUsuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `administracion_medicamentos`
--
ALTER TABLE `administracion_medicamentos`
  ADD CONSTRAINT `administracion_medicamentos_ibfk_1` FOREIGN KEY (`id_encamamiento`) REFERENCES `encamamientos` (`id_encamamiento`) ON DELETE CASCADE,
  ADD CONSTRAINT `administracion_medicamentos_ibfk_2` FOREIGN KEY (`id_medicamento`) REFERENCES `inventario` (`id_inventario`) ON DELETE SET NULL,
  ADD CONSTRAINT `administracion_medicamentos_ibfk_3` FOREIGN KEY (`indicado_por`) REFERENCES `usuarios` (`idUsuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `administracion_medicamentos_ibfk_4` FOREIGN KEY (`administrado_por`) REFERENCES `usuarios` (`idUsuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `archivos_orden`
--
ALTER TABLE `archivos_orden`
  ADD CONSTRAINT `fk_archivos_orden_prueba` FOREIGN KEY (`id_orden_prueba`) REFERENCES `orden_pruebas` (`id_orden_prueba`) ON DELETE CASCADE;

--
-- Filtros para la tabla `camas`
--
ALTER TABLE `camas`
  ADD CONSTRAINT `camas_ibfk_1` FOREIGN KEY (`id_habitacion`) REFERENCES `habitaciones` (`id_habitacion`) ON DELETE CASCADE;

--
-- Filtros para la tabla `cargos_hospitalarios`
--
ALTER TABLE `cargos_hospitalarios`
  ADD CONSTRAINT `cargos_hospitalarios_ibfk_1` FOREIGN KEY (`id_cuenta`) REFERENCES `cuenta_hospitalaria` (`id_cuenta`) ON DELETE CASCADE,
  ADD CONSTRAINT `cargos_hospitalarios_ibfk_2` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`idUsuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `citas`
--
ALTER TABLE `citas`
  ADD CONSTRAINT `fk_doctor_cita` FOREIGN KEY (`id_doctor`) REFERENCES `usuarios` (`idUsuario`);

--
-- Filtros para la tabla `cobros`
--
ALTER TABLE `cobros`
  ADD CONSTRAINT `paciente_cobro` FOREIGN KEY (`paciente_cobro`) REFERENCES `pacientes` (`id_paciente`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Filtros para la tabla `control_calidad_lab`
--
ALTER TABLE `control_calidad_lab`
  ADD CONSTRAINT `control_calidad_lab_ibfk_1` FOREIGN KEY (`id_prueba`) REFERENCES `catalogo_pruebas` (`id_prueba`) ON DELETE CASCADE,
  ADD CONSTRAINT `control_calidad_lab_ibfk_2` FOREIGN KEY (`realizado_por`) REFERENCES `usuarios` (`idUsuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `control_calidad_lab_ibfk_3` FOREIGN KEY (`aprobado_por`) REFERENCES `usuarios` (`idUsuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `cuenta_hospitalaria`
--
ALTER TABLE `cuenta_hospitalaria`
  ADD CONSTRAINT `cuenta_hospitalaria_ibfk_1` FOREIGN KEY (`id_encamamiento`) REFERENCES `encamamientos` (`id_encamamiento`) ON DELETE CASCADE;

--
-- Filtros para la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  ADD CONSTRAINT `detalle_ventas_ibfk_1` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`) ON DELETE CASCADE,
  ADD CONSTRAINT `detalle_ventas_ibfk_2` FOREIGN KEY (`id_inventario`) REFERENCES `inventario` (`id_inventario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `electrocardiogramas`
--
ALTER TABLE `electrocardiogramas`
  ADD CONSTRAINT `electrocardiogramas_ibfk_1` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`) ON DELETE RESTRICT,
  ADD CONSTRAINT `electrocardiogramas_ibfk_2` FOREIGN KEY (`id_doctor`) REFERENCES `usuarios` (`idUsuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `electrocardiogramas_ibfk_3` FOREIGN KEY (`realizado_por`) REFERENCES `usuarios` (`idUsuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `encamamientos`
--
ALTER TABLE `encamamientos`
  ADD CONSTRAINT `encamamientos_ibfk_1` FOREIGN KEY (`id_paciente`) REFERENCES `historial_clinico` (`id_paciente`) ON DELETE RESTRICT,
  ADD CONSTRAINT `encamamientos_ibfk_2` FOREIGN KEY (`id_cama`) REFERENCES `camas` (`id_cama`) ON DELETE RESTRICT,
  ADD CONSTRAINT `encamamientos_ibfk_3` FOREIGN KEY (`id_doctor`) REFERENCES `usuarios` (`idUsuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `encamamientos_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`idUsuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `evoluciones_medicas`
--
ALTER TABLE `evoluciones_medicas`
  ADD CONSTRAINT `evoluciones_medicas_ibfk_1` FOREIGN KEY (`id_encamamiento`) REFERENCES `encamamientos` (`id_encamamiento`) ON DELETE CASCADE,
  ADD CONSTRAINT `evoluciones_medicas_ibfk_2` FOREIGN KEY (`id_doctor`) REFERENCES `usuarios` (`idUsuario`) ON DELETE RESTRICT;

--
-- Filtros para la tabla `historial_clinico`
--
ALTER TABLE `historial_clinico`
  ADD CONSTRAINT `historial_clinico_ibfk_1` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`) ON DELETE CASCADE;

--
-- Filtros para la tabla `ordenes_laboratorio`
--
ALTER TABLE `ordenes_laboratorio`
  ADD CONSTRAINT `ordenes_laboratorio_ibfk_1` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`) ON DELETE RESTRICT,
  ADD CONSTRAINT `ordenes_laboratorio_ibfk_2` FOREIGN KEY (`id_doctor`) REFERENCES `usuarios` (`idUsuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `ordenes_laboratorio_ibfk_3` FOREIGN KEY (`id_encamamiento`) REFERENCES `encamamientos` (`id_encamamiento`) ON DELETE SET NULL,
  ADD CONSTRAINT `ordenes_laboratorio_ibfk_4` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`idUsuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `orden_pruebas`
--
ALTER TABLE `orden_pruebas`
  ADD CONSTRAINT `orden_pruebas_ibfk_1` FOREIGN KEY (`id_orden`) REFERENCES `ordenes_laboratorio` (`id_orden`) ON DELETE CASCADE,
  ADD CONSTRAINT `orden_pruebas_ibfk_2` FOREIGN KEY (`id_prueba`) REFERENCES `catalogo_pruebas` (`id_prueba`) ON DELETE RESTRICT,
  ADD CONSTRAINT `orden_pruebas_ibfk_3` FOREIGN KEY (`procesado_por`) REFERENCES `usuarios` (`idUsuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `orden_pruebas_ibfk_4` FOREIGN KEY (`validado_por`) REFERENCES `usuarios` (`idUsuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `parametros_pruebas`
--
ALTER TABLE `parametros_pruebas`
  ADD CONSTRAINT `parametros_pruebas_ibfk_1` FOREIGN KEY (`id_prueba`) REFERENCES `catalogo_pruebas` (`id_prueba`) ON DELETE CASCADE;

--
-- Filtros para la tabla `purchase_headers`
--
ALTER TABLE `purchase_headers`
  ADD CONSTRAINT `purchase_headers_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`idUsuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD CONSTRAINT `purchase_items_ibfk_1` FOREIGN KEY (`purchase_header_id`) REFERENCES `purchase_headers` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `purchase_payments`
--
ALTER TABLE `purchase_payments`
  ADD CONSTRAINT `purchase_payments_ibfk_1` FOREIGN KEY (`purchase_header_id`) REFERENCES `purchase_headers` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `reactivos_laboratorio`
--
ALTER TABLE `reactivos_laboratorio`
  ADD CONSTRAINT `reactivos_laboratorio_ibfk_1` FOREIGN KEY (`ingresado_por`) REFERENCES `usuarios` (`idUsuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `resultados_laboratorio`
--
ALTER TABLE `resultados_laboratorio`
  ADD CONSTRAINT `resultados_laboratorio_ibfk_1` FOREIGN KEY (`id_orden_prueba`) REFERENCES `orden_pruebas` (`id_orden_prueba`) ON DELETE CASCADE,
  ADD CONSTRAINT `resultados_laboratorio_ibfk_2` FOREIGN KEY (`id_parametro`) REFERENCES `parametros_pruebas` (`id_parametro`) ON DELETE RESTRICT,
  ADD CONSTRAINT `resultados_laboratorio_ibfk_3` FOREIGN KEY (`procesado_por`) REFERENCES `usuarios` (`idUsuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `resultados_laboratorio_ibfk_4` FOREIGN KEY (`validado_por`) REFERENCES `usuarios` (`idUsuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `signos_vitales`
--
ALTER TABLE `signos_vitales`
  ADD CONSTRAINT `signos_vitales_ibfk_1` FOREIGN KEY (`id_encamamiento`) REFERENCES `encamamientos` (`id_encamamiento`) ON DELETE CASCADE,
  ADD CONSTRAINT `signos_vitales_ibfk_2` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`idUsuario`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
