-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: bzlwnzdfwf8n1tct7ebf-mysql.services.clever-cloud.com:3306
-- Tiempo de generación: 08-05-2026 a las 15:49:25
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
-- Estructura de tabla para la tabla `archivos_resultados_laboratorio`
--

CREATE TABLE `archivos_resultados_laboratorio` (
  `id_archivo` int NOT NULL,
  `id_orden` int NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `tipo_contenido` varchar(100) NOT NULL,
  `tamano` int NOT NULL,
  `contenido` longblob NOT NULL,
  `notas` text,
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
  `tipo_pago` varchar(50) DEFAULT 'Efectivo',
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

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `encamamientos_con_dias`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `encamamientos_con_dias` (
`id_encamamiento` int
,`id_paciente` int
,`id_cama` int
,`id_doctor` int
,`fecha_ingreso` datetime
,`fecha_alta` datetime
,`motivo_ingreso` text
,`diagnostico_ingreso` varchar(500)
,`diagnostico_egreso` varchar(500)
,`estado` enum('Activo','Alta_Medica','Alta_Administrativa','Transferido','Fallecido')
,`tipo_ingreso` enum('Programado','Emergencia','Referido')
,`notas_ingreso` text
,`notas_alta` text
,`created_by` int
,`fecha_creacion` timestamp
,`fecha_actualizacion` timestamp
,`dias_hospitalizacion` int
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
  `id_examen_realizado` int NOT NULL,
  `id_paciente` int NOT NULL,
  `nombre_paciente` varchar(255) NOT NULL,
  `tipo_examen` varchar(255) NOT NULL COMMENT 'Nombre del examen (ej. Electrocardiograma, Ultrasonido)',
  `cobro` decimal(10,2) NOT NULL,
  `fecha_examen` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario` varchar(255) DEFAULT NULL,
  `tipo_pago` enum('Efectivo','Tarjeta','Transferencia') DEFAULT 'Efectivo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `insumos`
--

CREATE TABLE `insumos` (
  `id_insumo` int NOT NULL,
  `id_inventario` int NOT NULL,
  `cantidad` int NOT NULL,
  `precio_venta` decimal(10,2) NOT NULL,
  `id_usuario` int NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `stock_hospital` int NOT NULL DEFAULT '0',
  `precio_noche` decimal(10,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `notas` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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
  `email` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `permisos_modulos` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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
  `tipo_pago` enum('Efectivo','Tarjeta','Transferencia') CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `total` decimal(10,2) DEFAULT '0.00',
  `estado` enum('Pendiente','Pagado','Cancelado') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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
-- Indices de la tabla `archivos_resultados_laboratorio`
--
ALTER TABLE `archivos_resultados_laboratorio`
  ADD PRIMARY KEY (`id_archivo`);

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
  ADD PRIMARY KEY (`id_examen_realizado`),
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
-- Indices de la tabla `insumos`
--
ALTER TABLE `insumos`
  ADD PRIMARY KEY (`id_insumo`);

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
-- AUTO_INCREMENT de la tabla `archivos_resultados_laboratorio`
--
ALTER TABLE `archivos_resultados_laboratorio`
  MODIFY `id_archivo` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `camas`
--
ALTER TABLE `camas`
  MODIFY `id_cama` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cargos_hospitalarios`
--
ALTER TABLE `cargos_hospitalarios`
  MODIFY `id_cargo` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `catalogo_pruebas`
--
ALTER TABLE `catalogo_pruebas`
  MODIFY `id_prueba` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `citas`
--
ALTER TABLE `citas`
  MODIFY `id_cita` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cobros`
--
ALTER TABLE `cobros`
  MODIFY `in_cobro` int NOT NULL AUTO_INCREMENT;

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
  MODIFY `id_cuenta` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  MODIFY `id_detalle` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `electrocardiogramas`
--
ALTER TABLE `electrocardiogramas`
  MODIFY `id_electro` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `encamamientos`
--
ALTER TABLE `encamamientos`
  MODIFY `id_encamamiento` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `evoluciones_medicas`
--
ALTER TABLE `evoluciones_medicas`
  MODIFY `id_evolucion` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `examenes_realizados`
--
ALTER TABLE `examenes_realizados`
  MODIFY `id_examen_realizado` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `habitaciones`
--
ALTER TABLE `habitaciones`
  MODIFY `id_habitacion` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historial_clinico`
--
ALTER TABLE `historial_clinico`
  MODIFY `id_historial` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `insumos`
--
ALTER TABLE `insumos`
  MODIFY `id_insumo` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `inventario`
--
ALTER TABLE `inventario`
  MODIFY `id_inventario` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ordenes_laboratorio`
--
ALTER TABLE `ordenes_laboratorio`
  MODIFY `id_orden` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `orden_pruebas`
--
ALTER TABLE `orden_pruebas`
  MODIFY `id_orden_prueba` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pacientes`
--
ALTER TABLE `pacientes`
  MODIFY `id_paciente` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `parametros_pruebas`
--
ALTER TABLE `parametros_pruebas`
  MODIFY `id_parametro` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `procedimientos_menores`
--
ALTER TABLE `procedimientos_menores`
  MODIFY `id_procedimiento` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `purchase_headers`
--
ALTER TABLE `purchase_headers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `purchase_items`
--
ALTER TABLE `purchase_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `purchase_payments`
--
ALTER TABLE `purchase_payments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rayos_x`
--
ALTER TABLE `rayos_x`
  MODIFY `id_rayos_x` int NOT NULL AUTO_INCREMENT;

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
  MODIFY `id_reserva` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `resultados_laboratorio`
--
ALTER TABLE `resultados_laboratorio`
  MODIFY `id_resultado` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `signos_vitales`
--
ALTER TABLE `signos_vitales`
  MODIFY `id_signo` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ultrasonidos`
--
ALTER TABLE `ultrasonidos`
  MODIFY `id_ultrasonido` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `idUsuario` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id_venta` int NOT NULL AUTO_INCREMENT;

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
