-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: bxalmyxvqzxusgp20tip-mysql.services.clever-cloud.com:21400
-- Tiempo de generación: 06-08-2025 a las 19:14:33
-- Versión del servidor: 8.4.5-5
-- Versión de PHP: 8.2.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `bxalmyxvqzxusgp20tip`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `citas`
--

CREATE TABLE `citas` (
  `id_cita` int NOT NULL,
  `nombre_pac` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `apellido_pac` varchar(50) NOT NULL,
  `num_cita` int NOT NULL,
  `fecha_cita` date NOT NULL,
  `hora_cita` time NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `historial_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cobros`
--

CREATE TABLE `cobros` (
  `in_cobro` int NOT NULL,
  `paciente_cobro` int NOT NULL,
  `cantidad_consulta` int NOT NULL,
  `fecha_consulta` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `compras`
--

CREATE TABLE `compras` (
  `id_compras` int NOT NULL,
  `nombre_compra` varchar(100) NOT NULL,
  `presentacion_compra` varchar(100) NOT NULL,
  `molecula_compra` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `casa_compra` varchar(100) NOT NULL,
  `cantidad_compra` int NOT NULL,
  `precio_unidad` int NOT NULL,
  `precio_venta` int NOT NULL,
  `fecha_compra` date NOT NULL,
  `abono_compra` int NOT NULL,
  `total_compra` int NOT NULL,
  `tipo_pago` enum('Al Contado','Credito 30','Credito 60','') NOT NULL,
  `estado_compra` enum('Pendiente','Abonado','Completo','') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `examenes_realizados`
--

CREATE TABLE `examenes_realizados` (
  `id_examen_realizado` int DEFAULT NULL,
  `id_paciente` int NOT NULL,
  `nombre_paciente` varchar(255) NOT NULL,
  `tipo_examen` varchar(255) NOT NULL COMMENT 'Nombre del examen (ej. Electrocardiograma, Ultrasonido)',
  `cobro` decimal(10,2) NOT NULL,
  `fecha_examen` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventario`
--

CREATE TABLE `inventario` (
  `id_inventario` int NOT NULL,
  `nom_medicamento` varchar(100) NOT NULL,
  `mol_medicamento` varchar(100) NOT NULL,
  `presentacion_med` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `casa_farmaceutica` varchar(100) NOT NULL,
  `cantidad_med` int NOT NULL,
  `fecha_adquisicion` date NOT NULL,
  `fecha_vencimiento` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pacientes`
--

CREATE TABLE `pacientes` (
  `id_paciente` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `fecha_nacimiento` date NOT NULL,
  `genero` enum('Masculino','Femenino') CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(15) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `procedimientos_menores`
--

CREATE TABLE `procedimientos_menores` (
  `id_procedimiento` int DEFAULT NULL,
  `id_paciente` int NOT NULL,
  `nombre_paciente` varchar(255) NOT NULL,
  `procedimiento` varchar(255) NOT NULL COMMENT 'Nombre del procedimiento (ej. Sutura, Curación)',
  `cobro` decimal(10,2) NOT NULL,
  `fecha_procedimiento` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `idUsuario` int NOT NULL,
  `usuario` varchar(255) NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `apellido` varchar(255) NOT NULL,
  `especialidad` varchar(255) DEFAULT NULL,
  `tipoUsuario` enum('admin','doc','user','') NOT NULL,
  `clinica` varchar(255) NOT NULL,
  `telefono` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas`
--

CREATE TABLE `ventas` (
  `id_venta` int NOT NULL,
  `fecha_venta` datetime DEFAULT CURRENT_TIMESTAMP,
  `nombre_cliente` varchar(100) DEFAULT NULL,
  `tipo_pago` enum('Efectivo','Tarjeta','Seguro Médico') DEFAULT NULL,
  `total` decimal(10,2) DEFAULT '0.00',
  `estado` enum('Pendiente','Pagado','Cancelado') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `citas`
--
ALTER TABLE `citas`
  ADD PRIMARY KEY (`id_cita`);

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
-- Indices de la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_venta` (`id_venta`),
  ADD KEY `id_inventario` (`id_inventario`);

--
-- Indices de la tabla `examenes_realizados`
--
ALTER TABLE `examenes_realizados`
  ADD UNIQUE KEY `id_examen_realizado` (`id_examen_realizado`);

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
  ADD PRIMARY KEY (`id_inventario`);

--
-- Indices de la tabla `pacientes`
--
ALTER TABLE `pacientes`
  ADD PRIMARY KEY (`id_paciente`);

--
-- Indices de la tabla `procedimientos_menores`
--
ALTER TABLE `procedimientos_menores`
  ADD UNIQUE KEY `id_procedimiento` (`id_procedimiento`);

--
-- Indices de la tabla `reportes_estadisticas`
--
ALTER TABLE `reportes_estadisticas`
  ADD PRIMARY KEY (`id_reporte`);

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
-- AUTO_INCREMENT de la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  MODIFY `id_detalle` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historial_clinico`
--
ALTER TABLE `historial_clinico`
  MODIFY `id_historial` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `inventario`
--
ALTER TABLE `inventario`
  MODIFY `id_inventario` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pacientes`
--
ALTER TABLE `pacientes`
  MODIFY `id_paciente` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reportes_estadisticas`
--
ALTER TABLE `reportes_estadisticas`
  MODIFY `id_reporte` int NOT NULL AUTO_INCREMENT;

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

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `cobros`
--
ALTER TABLE `cobros`
  ADD CONSTRAINT `paciente_cobro` FOREIGN KEY (`paciente_cobro`) REFERENCES `pacientes` (`id_paciente`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Filtros para la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  ADD CONSTRAINT `detalle_ventas_ibfk_1` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`) ON DELETE CASCADE,
  ADD CONSTRAINT `detalle_ventas_ibfk_2` FOREIGN KEY (`id_inventario`) REFERENCES `inventario` (`id_inventario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `historial_clinico`
--
ALTER TABLE `historial_clinico`
  ADD CONSTRAINT `historial_clinico_ibfk_1` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
