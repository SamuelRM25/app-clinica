-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: bozapvpzel9yumdeik3k-mysql.services.clever-cloud.com:3306
-- Tiempo de generación: 09-05-2025 a las 02:35:33
-- Versión del servidor: 8.0.22-13
-- Versión de PHP: 8.2.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `bozapvpzel9yumdeik3k`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `citas`
--

CREATE TABLE `citas` (
  `id_cita` int NOT NULL,
  `nombre_pac` varchar(50) NOT NULL,
  `apellido_pac` varchar(50) NOT NULL,
  `num_cita` int NOT NULL,
  `fecha_cita` date NOT NULL,
  `hora_cita` time NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `historial_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Volcado de datos para la tabla `citas`
--

INSERT INTO `citas` (`id_cita`, `paciente_cita`, `num_cita`, `fecha_cita`, `hora_cita`, `telefono`, `historial_id`) VALUES
(6, 'Samuel Ramirez', 1, '2025-05-08', '10:00:00', '49617032', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cobros`
--

CREATE TABLE `cobros` (
  `in_cobro` int NOT NULL,
  `paciente_cobro` int NOT NULL,
  `cantidad_consulta` int NOT NULL,
  `fecha_consulta` date NOT NULL
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
  `fecha_compra` date NOT NULL,
  `abono_compra` int NOT NULL,
  `total_compra` int NOT NULL,
  `tipo_pago` enum('Al Contado','Credito 30','Credito 60','') NOT NULL,
  `estado_compra` enum('Pendiente','Abonado','Completo','') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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
  `hora_proxima_cita` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Volcado de datos para la tabla `historial_clinico`
--

INSERT INTO `historial_clinico` (`id_historial`, `id_paciente`, `fecha_consulta`, `motivo_consulta`, `sintomas`, `diagnostico`, `tratamiento`, `receta_medica`, `antecedentes_personales`, `antecedentes_familiares`, `examenes_realizados`, `resultados_examenes`, `observaciones`, `proxima_cita`, `medico_responsable`, `especialidad_medico`, `hora_proxima_cita`) VALUES
(2, 17, '2025-03-21 00:00:30', 'Dolor precordial de mas o menos de 5 días de evolución', 'paciente refiere que hace mas o menos 5 días inicia con dolor precordial el cual no cede, por lo que decide tomar ibuprofeno de 600 mg el cual mejora las molestias sin embargo dolor no cede por completo por lo que decide consultar', 'Estrés Postraumático\r\nDepresión\r\nAnsiedad\r\nOsteocondritis ', 'Sertralina\r\nCarbonato de Litio\r\nclonazepam\r\nParazol B\r\nMusculare\r\nDolimprym', '', 'No refiere', 'Padre de paciente con hipertensión arterial, Diabetes mellitus 2 y dislipidemias\r\nMadre de paciente enfermedad varicela en miembros inferiores\r\nAbuelo paterno fallece por IAM', 'Ekg\r\nHematologia\r\nPerfil lipidico\r\nPruebas tiroideas\r\npruebas hepáticas\r\nGlucosa\r\nHemoglobina glicosilada', 'WBC: 7.8\r\nPlt: 284\r\nHgb: 14.5\r\nHct: 41.9\r\nTGO: 10.29\r\nTGP: 22.10\r\nDHL: 318\r\nGlucosa pre: 90\r\nGlucosa pos: 102.5\r\nColesterol total: 92\r\nTriglicéridos: 158\r\nT3 libre: 1.36\r\nT4 libre: 7.9\r\nTSH: 1.9', 'Se realizará \r\nFactor reumatoide\r\nAnticuerpos citrulinados\r\nAcido úrico', NULL, 'Dr Donaldy Samayoa', 'Medico Internista', NULL),
(10, 20, '2025-03-21 23:02:20', 'Chequeo General y resultados de laboratorio', 'Paciente refiere que hace mas o menos 1 mes iniciamos tratamiento con medicamento para diabetes, colon irritable, hoy se realiza nuevos laboratorios por lo que consulta', 'Colon Irritable\r\nDiabetes Mellitus 2\r\nNeuropatia Diabética\r\nHipertensión Arterial', ' Sil-Norboral 1000/5 cada 24 horas\r\nFood enzymes \r\nKisat\r\nOdica\r\nLotrtial\r\nParasol B', '', 'Diabetes Mellitus 2', 'No refiere', 'Hemoglobina Glicosilada\r\nHematologia\r\nGlucosa\r\nCreatinina\r\nBUN\r\nColesterol Total\r\nTriglicéridos', 'WBC: 6.83\r\nHgb: 15.6\r\nHtc: 42.1\r\nPlt: 464\r\nGlucosa Pre: 86\r\nBUN: 5\r\nCreatinina: 0.96\r\nColesterol: 172\r\nTriglicéridos: 99', '', NULL, 'Dr Donaldy Samayoa', 'Medico Internista', NULL),
(11, 21, '2025-03-25 22:56:11', 'Dolor de espalda de mas o menos 2 días de evolución', 'Paciente refiere que hace mas o menos 2 días inicia con dolor en región de espalda media el cual empeora y duele mas con ciertos movimientos al momento paciente con cefalea y nausea empezando un día antes de inicio del dolor.', 'Espasmo muscular severo\r\nMeenstruación', 'Desketobios inyectado 3 días \r\nmethocarbamol inyectado 3 días\r\nDesketobios tomado por 5 días mas \r\nmusculare tomado por 5 días mas ', '', 'No refiere', 'No refiere', 'Hematologia\r\nuroanálisis ', 'WBC: 11.6\r\nNeu%: 68.4\r\nPlt: 298\r\nHgb: 15.8\r\nHct: 47.1', 'Paciente con puño percusión positiva con dolor  en toda la región de espalda a los lados de columna, resto de examen con fc mayor a 90', NULL, 'Dr Donaldy Samayoa', 'Medico Internista', NULL),
(12, 22, '2025-03-26 00:17:55', 'Fiebre y nauseas de mas o menos 2 días de evolución', 'Madre de paciente refiere que hace mas o menos 2 días inicia con fiebre y nausea la cual no cede la llevan con facultativo quien deja amoxicilina mas acido clavulanico, sin embargo paciente no tolera antibiótico y continua con molestias por lo que deciden consultar nuevamente.', 'Neumonía ', 'Cefixima\r\nTylenol\r\nMenaxol\r\nNauxil\r\nCarboxfar compuesto\r\nEsogastric\r\nRH2', '', 'No refiere', 'Padre con asma en la niñez y alérgico a varias cosas', 'No aplica', 'No Aplica', '', NULL, 'Dr Donaldy Samayoa', 'Medico Internista', NULL),
(13, 23, '2025-03-27 23:53:28', 'Dolor en región espalda y corazón de mas o menos 6 meses de evolución', 'Paciente refiere que hace mas o menos en junio del año pasado inicia con insuficiencia respiratoria por lo que visitó a hospital RR en Xela en donde indican que pulmón tiene una mancha la cual si se deja crecer puede converttirse en cancer según informa médico en HRR, sin embargo en octubre del año pasado paciente inicia con dolor en región de espalda el cual va progresando a diferentes articulaciones del cuerpo y no cede con analgésicos.', 'Hipotiroidismo\r\nNeuropatia\r\nHipertensión Arterial\r\nEspasmo muscular de músculos de espalda', 'Odica \r\nDesketobios\r\nMusculare\r\nParasol B', '', 'Hipotiroidismo con Dx en APROFAM huehuetenango\r\nHipertensión Arterial hace 6 años con Dx en Barillas\r\nHigado Graso ', 'Mama Hipertensa', 'TAC torácica\r\nEcocardiograma', 'normales', '', NULL, 'Dr Donaldy Samayoa', 'Medico Internista', NULL),
(14, 23, '2025-03-27 23:53:30', 'Dolor en región espalda y corazón de mas o menos 6 meses de evolución', 'Paciente refiere que hace mas o menos en junio del año pasado inicia con insuficiencia respiratoria por lo que visitó a hospital RR en Xela en donde indican que pulmón tiene una mancha la cual si se deja crecer puede converttirse en cancer según informa médico en HRR, sin embargo en octubre del año pasado paciente inicia con dolor en región de espalda el cual va progresando a diferentes articulaciones del cuerpo y no cede con analgésicos.', 'Hipotiroidismo\r\nNeuropatia\r\nHipertensión Arterial\r\nEspasmo muscular de músculos de espalda', 'Odica \r\nDesketobios\r\nMusculare\r\nParasol B', '', 'Hipotiroidismo con Dx en APROFAM huehuetenango\r\nHipertensión Arterial hace 6 años con Dx en Barillas\r\nHigado Graso ', 'Mama Hipertensa', 'TAC torácica\r\nEcocardiograma', 'normales', '', NULL, 'Dr Donaldy Samayoa', 'Medico Internista', NULL),
(15, 25, '2025-03-28 17:34:45', 'Dolor en región de abdomen y al defecar de 1 mes de evolución', 'PAciente refiere que hace mas o menos 1 inicia con dolor en región de flanco y fosa iliaca derecha el cual no mejora, además refiere que a la hora de defecar paciente siente dolor el cual le deja sensación de tenesmo, paciente no mejora por lo que decide consultar', 'Hipertensión Arterial\r\nNeuropatía\r\nColon Irritable \r\nEspasmo Muscular', 'Iltuxam 20/5\r\nOdica 150\r\nKisat\r\nFood Enzymes\r\nPsyllium Hulls\r\nParasol B\r\nDesketobios inyectado\r\nDesketobios plus tomado\r\nMusculare\r\n', 'Iltuxam 20/5\r\nOdica 150\r\nKisat\r\nFood Enzymes\r\nPsyllium Hulls\r\nParasol B\r\nDesketobios inyectado\r\nDesketobios plus tomado\r\nMusculare\r\n', 'Hipotiroidismo secundario a resección completa de tiroides con Tx de Eutriox 100mcg 1 vez al día\r\n', 'Papá Colon Irritable', 'No aplica', 'No aplica', 'Dolor a la palpación en región de flanco e hipocondrio derecho, no hepatomegalia, con dolor a la palpación en espalda baja a nivel de región luumbar, Presion Arterial de 170/100', NULL, 'Dr Donaldy Samayoa', 'Medico Internista', NULL),
(16, 27, '2025-03-29 01:42:17', 'Chequeo general', 'Paciente refiere que cardiólogo cambia medicamento para presión arterial sin embargo ella refiere sentirse mareada y decaída por lo que decide consultar', 'Síndrome de Meniere\r\nHipertensión Arterial\r\nTaquicardia Sinusal\r\nEPOC\r\nPrediabetes', 'Fosfobac 3gr\r\nBetistín 24 mg\r\nBreztri\r\nIltuxam HCT\r\nColber 2.5 (bisoprolol)', '', 'Hipertensión Arterial\r\nEPOC\r\nTaquicardia Sinusal\r\n', 'Mamá Diabetes mellitus 2 e hipertensión', 'Radiografía de tórax\r\nEspirometría \r\nHematología\r\nUroanalisis', '', '', NULL, 'Dr Donaldy Samayoa', 'Medico Internista', NULL),
(18, 29, '2025-04-03 03:18:26', 'Dolor en región occipital de cabeza de mas o menos 15 días', 'Paciente refiere que hace 15 días inicia con dolor abdominal el cual se acompañaba de diarrea, el cual fue tratado como helicobacter Pylori, paciente quien indica haber tenido antecedente de fiebre tifoidea hace 9 meses, paciente queda con dolor en región occipital el cual no cede por lo que decide consultar.', 'Espasmo Muscular de región posterior de esternocleidomastoideo\r\n', 'Dorixina Relax\r\nDesketobios plus\r\n', '', 'No refiere', 'Mamá con 2 IAM\r\nInsuficiencia Cardiaca\r\nHipertensión Arterial\r\nAbuelita con IAM', 'Ninguno', 'No aplica', '', NULL, 'Dr Donaldy Samayoa', 'Medico Internista', NULL),
(19, 25, '2025-04-04 17:04:07', 'chequeo general', 'Paciente quien el día de hoy se deja cita con resultados de laboratorio.', 'HTA\'S\r\nNeuropatía\r\nColon Irritable\r\nEspasmo Muscular\r\nDM2\r\nHipotiroidismo', 'Adiamet XR 1000mg\r\nEutirox de 75mcg', '', 'Hipotiroidismo', '', 'HbA1c\r\nHematologia \r\nTiroideas\r\nQuímica sanguinea', 'HbA1c: 7\r\nGlucosa pre: 96\r\nBUN: 8\r\nCreatinina: 0.64\r\nCalcio: 8.76\r\nColesterol total: 148\r\nTriglicéridos: 109\r\nNa: 141\r\nK: 3.8\r\nWBC: 4.96\r\nHGB: 13.1\r\nHCT: 37.7\r\nPLT: 290\r\nT3: 3.2\r\nT4: 1.9\r\nTSH: 1.4', '', NULL, 'Dr Donaldy Samayoa', 'Medico Internista', NULL),
(20, 30, '2025-04-04 21:05:07', 'Disnea de mas o menos 1 año y medio', 'Paciente refiere que hace mas o menos 3 años es operada por hipertrofia de cornetes en ciudad de Quetzaltenango, sin embargo paciente refiere que síntomas empeoran y que hace 1 año y medio inicio con dificultad para respirar aun más por lo que decide consultar.', 'Rinitis alérgica\r\nAsma a descartar', 'Butosol\r\n', '', 'Rinitis alérgica ', 'Mamá con antecedente de asma, DM2.', 'No aplica', 'No aplica', 'Tórax silente', NULL, 'Dr Donaldy Samayoa', 'Medico Internista', NULL),
(21, 31, '2025-04-12 17:02:42', 'Referida por facultativo por posible daño renal y por HTA\'s', 'Paciente refiere que hace mas o menos 2 mese consulta por facultativo quien indica cursa con ITU por lo que da tratamiento sin embargo paciente consulta con ginecóloga de cabecera quien indica que cursa con hipertensión arterial, le realiza laboratorios en los cuales se encuentra creatinina elevada por lo que ginecóloga refiere para manejo especializado.', 'HTA\'s\r\nEspasmo en músculos de ambas piernas', 'Lotrial 20mg\r\nDesketobios plus\r\nDorixina relax ', '', 'No refiere', 'No refiere', 'Acido Úrico\r\nCreatinina \r\nBUN\r\nFR', 'Acido úrico 3.9\r\nCreatinina: 1.36\r\nBUN: 17.5\r\nFR: <8', 'Paciente con dolor en músculos de la espinilla, con PA: 130/90, resto de examen físico normal', NULL, 'Dr Donaldy Samayoa', 'Medico Internista', NULL),
(22, 26, '2025-04-15 23:28:48', 'Niveles de glucosa altos con Tx ya establecido', 'Paciente con antecedente de Dm2 quien inicia con tratamiento de Sitabet M 50/1000 (sitagliptina mas metformin) sin embargo paciente indica que el día viernes de la semana pasada sufre incidente estresante el cual aumenta glucosa, paciente indica que había estado tomando la glucosa en casa la cual la manejaba por debajo de 200g/dl sin embargo decide consultar con controles de laboratorios ', 'DM2\r\nHTA\'S\r\nNeuropatía diabética\r\nSíndrome de colon irritable ', 'Exforge Hct 320/10/25\r\nSitabet M 50/1000\r\nFood Enzymes\r\nPsyllium Hulls\r\nGasttroflux\r\nEsobrox\r\nOdica 75mg', '', 'DM2 con diagnóstico hace mas o menos 8 años con Tx de Sitagliptina 50 mg y Metformina 1000mg\r\nHTA\'s con Dx hace mas o menos 30 años con tx de Exforge HCT dosis altas', 'Padre de paciente falleció de derrame cerebral\r\nMadre desconoce el tipo de enfermedad solo indica haber sido de riñones', 'Glucosa Pre\r\nHemoglobina Glicosilada\r\nGlucosa Post\r\n', 'Glucosa pre: 353\r\nHemoglobina Glicosilada: 10%\r\nGlucosa post: 449', '', NULL, 'Dr Donaldy Samayoa', 'Medico Internista', NULL),
(23, 32, '2025-04-16 00:24:39', 'Referida por facultativo por hipertiroidismo y Prediabetes', 'Paciente indica que desde hace 2 años inicia con dolor en garganta a repetición el cual no mejora, además hace 1 año inicia con decaimiento, el cual se acompaña con sentimiento de tristeza y llanto sin motivo alguno por lo que consulta ', 'Hipertiroidismo a D/C\r\nSCI\r\nResistencia a la Insulina\r\nEstrés Postraumatico\r\nDepresión menor\r\n', 'Kisat\r\nFood Enzymes\r\nPsyllium Hulls\r\nGastroflux\r\nDesketobios plus\r\nPortium 20mg\r\nEmergen\r\nNervaden Plus', '', 'Colon Irritable hace mas o menos 3 años.', 'Papá colon irritable ', 'Glucosa precio y pos\r\nHbA1c\r\nT3\r\nT4\r\nTSH\r\nCortisol\r\nHFE\r\nHL', 'Glucosa precio y pos 103/107\r\nHbA1c 5.7\r\nT3 4.15\r\nT4 2.05\r\nTSH 2.46\r\nCortisol 132.3\r\nHFE 2.55\r\nHL 3.14', 'Paciente con signos vitales dentro de rangos normales a excepción de PA la cual se sospecha de Hipertensión de Bata Blanca por lo que se da buen plan educasional a paciente para toma de PA ambulatoria.', NULL, 'Dr Donaldy Samayoa', 'Medico Internista', NULL),
(24, 31, '2025-04-21 22:48:11', 'Reconsulta por resultados de laboratorio', 'Paciente quien con historia de hipertensión arterial quien al momento con tratamiento de enalapril 20mg, se realizan laboratorios para valorar riesgo de resistencia  a la insulina.', 'HTA\'s\r\nPrediabetes\r\n', 'Lotrial 20 mg\r\nMetformina 500 mg', '', '', '', 'Hematologia \r\nHbA1c\r\nUroanalisis\r\nQuimica Sanguinea', 'WBC: 4.91\r\nHGB: 14.5\r\nHCT: 41.7\r\nPLT: 324\r\nHbA1c: 6%\r\nGlucosa pre: 113\r\nCrea: 0.60\r\nCa: 8.8\r\nNa: 139\r\nK: 4\r\nTriglicéridos: 140\r\nT3: 4.1\r\nT4: 1.1\r\nTSH: ?\r\nUroanalisis: Cetonas positivas', '', NULL, 'Dr Donaldy Samayoa', 'Medico Internista', NULL),
(25, 32, '2025-04-22 01:07:13', 'Reconsulta por laboratorios y resultados de ultrasonido.', 'Paciente refiere que hoy 21 de abril se realiza laboratorios los cuales trae resultados ', 'SCI\r\nEstrés postraumático\r\nDepresión Menor\r\nBroinquitis', 'Azitromicina 500 mg\r\nTrifamox IBL duo\r\nPortium\r\nDesketobios plus', '', '', '', 'USG tiroideo\r\npruebas tiroideas\r\n', 'Nódulo solido tiroideo izquierdo TIRADS 3\r\nNódulo mixto tiroideo izquierdo TIRADS 3\r\nT3: 1.1\r\nT4: 132\r\nTSH: 2.19', '', NULL, 'Dr Donaldy Samayoa', 'Medico Internista', NULL),
(26, 33, '2025-04-22 23:07:21', 'Dolor de garganta de mas o menos 15 días de evolución.', 'Paciente refiere que hace mas o menos 20 días inicia con dolor en la boca del estomago el cual lo obliga a tomar medicamentos como sucralfato, esomeprazole, sin embargo los síntomas mejoran pero aparece dolor de garganta el cual se ha hecho constante por lo que consulta', 'Sinusitis no activa\r\nERGE\r\nAmigdalitis secundaria a a ERGE', 'Sucralgastric\r\nAci-tip\r\nGastroflux\r\nEnterogermina 1 ampolla cada día por 5 días\r\nActilosa\r\nPortium\r\nDolyprim', '', 'Sinusitis de 8 años de evolución con tratamiento natural.', 'No refiere', 'Ninguno ', 'Ninguno', 'Paciente con irritación de mucosa nasofaríngea, la cual es secundaria a ERGE.\r\nRGI aumentados.', NULL, 'Dr Donaldy Samayoa', 'Medico Internista', NULL),
(27, 34, '2025-04-24 23:44:32', 'dolor en primer artejo del pie izquierdo de mas o menos 1 semana de evolución', 'Paciente refiere que inicia con dolor en primer artejo de pie izquierdo el cual no mejora y el dolor se vuelve mas intenso por lo que decide consultar.', 'Uña encarnada\r\nInfección de tejidos blandos de primer artejo de pie izquierdo.', 'Clindamicina \r\ndesketobios plus\r\nlanzoprazol', '', 'No refiere ', 'No Refiere ', 'no Refiere ', 'No aplica', '', NULL, 'Dr Donaldy Samayoa', 'Medico Internista', NULL),
(28, 35, '2025-05-02 15:27:36', 'Fatiga de mas o menos de 3 años de evolución', 'Paciente refiere que hace mas o menos 3 años inicia con fatiga la cual va subiendo de intensidad, paciente refiere que consulta con facultativo el cual le dice que tiene problemas en una válvula del corazón y le da cardiovital, sin embargo no mejora por lo que decide consultar.', 'HTA\'s\r\n', 'Vassluten H 300/12.5', 'Vassluten H 300/12.5 1 tableta cada mañana por tiempo indefinido.', 'No refiere', 'Hermana: DM2 \r\nMadre: DM2, HTA\'s\r\nHermano: IAM', 'No aplica', 'No aplica', 'Otro cel: 58414201\r\n\r\nPaciente con PA: 160/100 FC: 78 SPO2: 96%\r\ncon soplo sistólico en foco aórtico, pulmonar, mitral y tricúspide.\r\nNo edema.', NULL, 'Dr Donaldy Samayoa', 'Medico Internista', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventario`
--

CREATE TABLE `inventario` (
  `id_inventario` int NOT NULL,
  `nom_medicamento` varchar(100) NOT NULL,
  `mol_medicamento` varchar(100) NOT NULL,
  `presentacion_med` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `casa_farmaceutica` varchar(100) NOT NULL,
  `cantidad_med` int NOT NULL,
  `fecha_adquisicion` date NOT NULL,
  `fecha_vencimiento` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `medicamentos`
--

CREATE TABLE `medicamentos` (
  `idMedicamento` int NOT NULL,
  `nomMedicamento` varchar(255) NOT NULL,
  `fechaIngreso` date NOT NULL,
  `fechaVencimiento` date NOT NULL,
  `tipoMedicamento` varchar(255) NOT NULL,
  `proMedicamento` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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
(17, 'Jennifer ', 'Castañeda  de Serrano', '1995-10-18', 'Femenino', 'Zona 1 Barillas', '57660998', 'abc@gmail.com', '2025-03-20 22:46:30'),
(20, 'Julio', 'Jiménez Miguel', '1986-07-23', 'Masculino', 'La florida, Barillas', '30124931', '', '2025-03-21 22:49:17'),
(21, 'Rashel Marla Belen ', 'Muñoz Palacios', '2010-10-12', 'Femenino', 'Zona 4, Barillas', '32513220', '', '2025-03-25 22:36:06'),
(22, 'Briana Maria', 'Sosa Noriega', '2016-04-20', 'Femenino', 'Zona 1, Barillas', '45704030', '', '2025-03-25 23:23:34'),
(23, 'Maria del Rosario', 'Tello López', '1985-06-22', 'Femenino', 'Zona 6, Barillas', '32488249', '', '2025-03-27 22:38:04'),
(24, 'Erwin Leonel ', 'Noriega Avila', '1968-07-07', 'Masculino', 'Zona 2 Barillas', '59056048', '', '2025-03-28 00:05:18'),
(25, 'Bertinda ', 'Cifuentes del Valle', '1975-10-20', 'Femenino', 'Valle 1, Ixcan ', '57238010', '', '2025-03-28 16:58:54'),
(26, 'Sonia Aracely', 'Hernandez ', '1975-10-27', 'Femenino', 'Manantial carretera', '53560095', '', '2025-03-28 23:35:19'),
(27, 'Magda Noemí', 'Del Valle Morales', '1952-04-25', 'Femenino', 'Zona 6, Barillas', '53439276', '', '2025-03-29 01:35:06'),
(29, 'Delman Javier', 'Serrano Gómez', '1995-08-16', 'Masculino', 'Zona 5, Barillas', '45970865', '', '2025-04-03 02:47:32'),
(30, 'Blainy ', 'López Recinos', '2008-04-19', 'Femenino', 'Zona 1, Barillas', '46680289', '', '2025-04-04 20:17:18'),
(31, 'Marlen Natali', 'Castillo Samayoa', '1990-03-18', 'Femenino', 'Recreo A, Barillas', '46634381', '', '2025-04-12 16:34:47'),
(32, 'Deydi Daysine ', 'Solis Samayoa', '1983-11-10', 'Femenino', 'Pueblo Viejo, Barillas', '53546767', '', '2025-04-16 00:06:58'),
(33, 'José Luis ', 'Argueta', '1981-06-13', 'Masculino', 'Pueblo Viejo, Barillas', '50508936', '', '2025-04-22 22:31:08'),
(34, 'Josselin Estrella', 'Ávila Vásquez', '1993-11-13', 'Femenino', 'Zona 1, Barillas', '57716110', '', '2025-04-24 23:41:15'),
(35, 'Olga Leticia  ', 'Herrera', '1978-09-03', 'Femenino', 'Flor Santo Domingo, frontera.', '46308025', '', '2025-05-02 14:26:40');

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
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `idUsuario` int NOT NULL,
  `usuario` varchar(255) NOT NULL,
  `password` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `apellido` varchar(255) NOT NULL,
  `especialidad` varchar(255) DEFAULT NULL,
  `clinica` varchar(255) NOT NULL,
  `telefono` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`idUsuario`, `usuario`, `password`, `nombre`, `apellido`, `especialidad`, `clinica`, `telefono`, `email`) VALUES
(1, 'DBadmin', 'admin', 'Samuel', 'Ramirez', 'Administrador', 'Clinica Prueba', '49617032', 'samuel.ramirez25prs@gmail.com'),
(2, 'interclinicapp', 'Interclinic', 'Alexandra', 'Samayoa', 'Secretaria', 'Interclinic', '35970114', 'example@gmail.com'),
(3, 'interclinicmed', 'Interclinic', 'Dr Donaldy', 'Samayoa', 'Medico Internista', 'Interclinic', '42594302', 'donaldtrump232418@gmail.com');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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
-- Indices de la tabla `medicamentos`
--
ALTER TABLE `medicamentos`
  ADD PRIMARY KEY (`idMedicamento`);

--
-- Indices de la tabla `pacientes`
--
ALTER TABLE `pacientes`
  ADD PRIMARY KEY (`id_paciente`);

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
  MODIFY `id_cita` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `cobros`
--
ALTER TABLE `cobros`
  MODIFY `in_cobro` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `compras`
--
ALTER TABLE `compras`
  MODIFY `id_compras` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  MODIFY `id_detalle` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `historial_clinico`
--
ALTER TABLE `historial_clinico`
  MODIFY `id_historial` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de la tabla `inventario`
--
ALTER TABLE `inventario`
  MODIFY `id_inventario` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT de la tabla `pacientes`
--
ALTER TABLE `pacientes`
  MODIFY `id_paciente` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT de la tabla `reportes_estadisticas`
--
ALTER TABLE `reportes_estadisticas`
  MODIFY `id_reporte` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `idUsuario` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id_venta` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

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
