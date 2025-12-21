-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 21-12-2025 a las 18:18:18
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `pmv`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cierres_caja`
--

CREATE TABLE `cierres_caja` (
  `id_cierre` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `id_cajero_fk` int(11) NOT NULL,
  `id_supervisor_fk` int(11) DEFAULT NULL,
  `monto_registrado_efectivo` decimal(10,2) NOT NULL,
  `monto_contado_fisico` decimal(10,2) NOT NULL,
  `monto_esperado` decimal(10,2) DEFAULT NULL,
  `diferencia` decimal(10,2) NOT NULL,
  `codigo_validacion` enum('X','Z') NOT NULL,
  `observacion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cierres_caja`
--

INSERT INTO `cierres_caja` (`id_cierre`, `fecha`, `id_cajero_fk`, `id_supervisor_fk`, `monto_registrado_efectivo`, `monto_contado_fisico`, `monto_esperado`, `diferencia`, `codigo_validacion`, `observacion`) VALUES
(1, '2025-12-10', 101, NULL, 1250.00, 1248.00, NULL, -2.00, 'Z', NULL),
(2, '2025-12-11', 101, NULL, 1500.00, 1475.00, NULL, -25.00, 'X', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `conciliacion`
--

CREATE TABLE `conciliacion` (
  `id_conciliacion` int(11) NOT NULL,
  `id_conciliacion_final_fk` int(11) NOT NULL,
  `tipo_cobro` enum('TPV','Pago_Movil') NOT NULL,
  `registrado_d1` decimal(10,2) NOT NULL,
  `liquidado_d2` decimal(10,2) NOT NULL,
  `desviacion` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `conciliacion`
--

INSERT INTO `conciliacion` (`id_conciliacion`, `id_conciliacion_final_fk`, `tipo_cobro`, `registrado_d1`, `liquidado_d2`, `desviacion`) VALUES
(1, 1, 'TPV', 4000.00, 3950.00, -50.00),
(2, 1, 'Pago_Movil', 0.00, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `conciliacion_final`
--

CREATE TABLE `conciliacion_final` (
  `id_conciliacion_final` int(11) NOT NULL,
  `fecha_venta` date NOT NULL,
  `id_auditor_fk` int(11) NOT NULL,
  `desviacion_total` decimal(10,2) NOT NULL,
  `codigo_validacion` enum('X','Z') NOT NULL,
  `observacion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `conciliacion_final`
--

INSERT INTO `conciliacion_final` (`id_conciliacion_final`, `fecha_venta`, `id_auditor_fk`, `desviacion_total`, `codigo_validacion`, `observacion`) VALUES
(1, '2025-12-13', 103, -50.00, 'X', 'Desviación en TPV. Lote bancario no coincidió con el registro D1.');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `control_jornadas`
--

CREATE TABLE `control_jornadas` (
  `id_jornada` int(11) NOT NULL,
  `fecha_apertura` datetime DEFAULT NULL,
  `id_usuario_apertura_fk` int(11) DEFAULT NULL,
  `id_usuario_cierre_fk` int(11) DEFAULT NULL,
  `monto_apertura` decimal(10,2) NOT NULL,
  `monto_ventas_sistema` decimal(10,2) DEFAULT 0.00,
  `monto_conteo_fisico` decimal(10,2) DEFAULT 0.00,
  `diferencia` decimal(10,2) DEFAULT 0.00,
  `estado_jornada` varchar(20) DEFAULT 'Abierta',
  `notas` text DEFAULT NULL,
  `apertura_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `cierre_timestamp` datetime DEFAULT NULL,
  `fecha_cierre` datetime DEFAULT NULL,
  `monto_cierre_real` decimal(10,2) DEFAULT NULL,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `control_jornadas`
--

INSERT INTO `control_jornadas` (`id_jornada`, `fecha_apertura`, `id_usuario_apertura_fk`, `id_usuario_cierre_fk`, `monto_apertura`, `monto_ventas_sistema`, `monto_conteo_fisico`, `diferencia`, `estado_jornada`, `notas`, `apertura_timestamp`, `cierre_timestamp`, `fecha_cierre`, `monto_cierre_real`, `observaciones`) VALUES
(1, '2025-12-14 00:00:00', 1, 1, 200.00, 0.00, 0.00, 0.00, 'Cerrada', 'Jornada de regularización de ventas pasadas', '2025-12-16 08:07:13', NULL, '2025-12-20 23:06:23', 0.00, 'primer cierre'),
(2, '2025-12-20 23:23:16', 1, NULL, 230.00, 0.00, 0.00, 0.00, 'Cerrada', NULL, '2025-12-21 03:23:16', NULL, '2025-12-21 01:07:48', 0.00, NULL),
(3, '2025-12-21 01:08:49', 1, 1, 0.00, 0.00, 0.00, 0.00, 'Cerrada', NULL, '2025-12-21 05:08:49', NULL, '2025-12-21 11:11:29', 0.00, 'tercera prueba'),
(4, '2025-12-21 11:15:29', 1, 1, 10.00, 0.00, 0.00, 0.00, 'Cerrada', NULL, '2025-12-21 15:15:29', NULL, '2025-12-21 11:16:42', 10.00, 'cuarto cerrado'),
(5, '2025-12-21 11:17:07', 1, NULL, 10.00, 0.00, 0.00, 0.00, 'Cerrada', NULL, '2025-12-21 15:17:07', NULL, '2025-12-21 12:36:33', NULL, NULL),
(6, '2025-12-21 12:37:33', 1, NULL, 0.00, 0.00, 0.00, 0.00, 'Abierta', NULL, '2025-12-21 16:37:33', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_egresos`
--

CREATE TABLE `detalle_egresos` (
  `id_detalle` int(11) NOT NULL,
  `id_transaccion_fk` bigint(20) DEFAULT NULL,
  `id_producto_fk` int(11) DEFAULT NULL,
  `cantidad` int(11) NOT NULL,
  `motivo` varchar(100) NOT NULL,
  `usuario_autorizador` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `detalle_egresos`
--

INSERT INTO `detalle_egresos` (`id_detalle`, `id_transaccion_fk`, `id_producto_fk`, `cantidad`, `motivo`, `usuario_autorizador`) VALUES
(1, 3003, 3, 3, 'Vencimiento', 'Supervisor');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_pago`
--

CREATE TABLE `detalle_pago` (
  `id_detalle` int(11) NOT NULL,
  `id_transaccion_fk` int(11) NOT NULL,
  `id_metodo_fk` int(11) NOT NULL,
  `monto_pago` decimal(10,2) NOT NULL,
  `conciliado_banco` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `detalle_pago`
--

INSERT INTO `detalle_pago` (`id_detalle`, `id_transaccion_fk`, `id_metodo_fk`, `monto_pago`, `conciliado_banco`) VALUES
(1, 5003, 1, 1.50, 0),
(2, 5004, 1, 4.00, 0),
(3, 5005, 1, 30.00, 0),
(4, 5006, 1, 35.50, 0),
(5, 5007, 1, 2.50, 0),
(6, 5008, 1, 25.00, 0),
(7, 5009, 1, 15.00, 0),
(8, 5011, 1, 2.50, 0),
(9, 5012, 2, 6.50, 0),
(10, 5013, 2, 6.50, 0),
(11, 5014, 2, 6.50, 0),
(12, 5015, 2, 7.00, 0),
(13, 5016, 2, 2.50, 0),
(14, 5017, 2, 2.50, 0),
(15, 5018, 2, 2.50, 0),
(16, 5019, 2, 1.50, 0),
(17, 5020, 3, 32.50, 0),
(18, 5021, 2, 2.50, 0),
(19, 5022, 2, 2.50, 0),
(20, 5023, 2, 2.50, 0),
(21, 5024, 3, 4.00, 0),
(22, 5025, 2, 2.50, 0),
(23, 5026, 2, 5.00, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_transaccion`
--

CREATE TABLE `detalle_transaccion` (
  `id_detalle` int(11) NOT NULL,
  `id_transaccion_fk` int(11) NOT NULL,
  `id_producto_fk` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_venta` decimal(10,2) NOT NULL,
  `es_egreso_especial` tinyint(1) DEFAULT 0,
  `motivo` varchar(255) DEFAULT NULL,
  `usuario_autorizador` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `detalle_transaccion`
--

INSERT INTO `detalle_transaccion` (`id_detalle`, `id_transaccion_fk`, `id_producto_fk`, `cantidad`, `precio_venta`, `es_egreso_especial`, `motivo`, `usuario_autorizador`) VALUES
(10, 5003, 1, 1, 1.50, 0, NULL, NULL),
(11, 5004, 3, 1, 4.00, 0, NULL, NULL),
(12, 5005, 4, 1, 30.00, 0, NULL, NULL),
(13, 5006, 1, 1, 1.50, 0, NULL, NULL),
(14, 5006, 3, 1, 4.00, 0, NULL, NULL),
(15, 5006, 4, 1, 30.00, 0, NULL, NULL),
(16, 5007, 2, 1, 2.50, 0, NULL, NULL),
(17, 5008, 2, 10, 2.50, 0, NULL, NULL),
(18, 5009, 1, 10, 1.50, 0, NULL, NULL),
(19, 5010, 2, 1, 2.50, 0, NULL, NULL),
(20, 5011, 2, 1, 2.50, 0, NULL, NULL),
(21, 5012, 2, 1, 2.50, 0, NULL, NULL),
(22, 5012, 3, 1, 4.00, 0, NULL, NULL),
(23, 5013, 2, 1, 2.50, 0, NULL, NULL),
(24, 5013, 3, 1, 4.00, 0, NULL, NULL),
(25, 5014, 2, 1, 2.50, 0, NULL, NULL),
(26, 5014, 3, 1, 4.00, 0, NULL, NULL),
(27, 5015, 1, 1, 1.50, 0, NULL, NULL),
(28, 5015, 3, 1, 4.00, 0, NULL, NULL),
(29, 5015, 6, 1, 1.50, 0, NULL, NULL),
(30, 5016, 2, 1, 2.50, 0, NULL, NULL),
(31, 5017, 2, 1, 2.50, 0, NULL, NULL),
(32, 5018, 2, 1, 2.50, 0, NULL, NULL),
(33, 5019, 1, 1, 1.50, 0, NULL, NULL),
(34, 5020, 2, 13, 2.50, 0, NULL, NULL),
(35, 5021, 2, 1, 2.50, 0, NULL, NULL),
(36, 5022, 2, 1, 2.50, 0, NULL, NULL),
(37, 5023, 2, 1, 2.50, 0, NULL, NULL),
(38, 5024, 3, 1, 4.00, 0, NULL, NULL),
(39, 5025, 2, 1, 2.50, 0, NULL, NULL),
(40, 5026, 2, 2, 2.50, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_tasa_de_cambio`
--

CREATE TABLE `historial_tasa_de_cambio` (
  `id_tasa` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `tasa_usd_ves` decimal(10,4) NOT NULL,
  `usuario_modifico` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `historial_tasa_de_cambio`
--

INSERT INTO `historial_tasa_de_cambio` (`id_tasa`, `fecha`, `tasa_usd_ves`, `usuario_modifico`) VALUES
(1, '2025-12-09', 36.0000, 102),
(2, '2025-12-12', 36.5000, 103),
(3, '2025-12-15', 36.8000, 103);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventario`
--

CREATE TABLE `inventario` (
  `id_producto` int(11) NOT NULL,
  `nombre_producto` varchar(100) NOT NULL,
  `stock_actual` int(11) NOT NULL,
  `costo_unitario` decimal(10,2) NOT NULL,
  `ultima_venta_fecha` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `inventario`
--

INSERT INTO `inventario` (`id_producto`, `nombre_producto`, `stock_actual`, `costo_unitario`, `ultima_venta_fecha`) VALUES
(1, 'Café Americano', 5, 1.50, '2025-12-14'),
(2, 'Bebida Energética X', 110, 2.50, '2025-10-01'),
(3, 'Pan de Jamón', 43, 4.00, '2025-12-13'),
(4, 'Cachito', 8, 30.00, '2025-12-15'),
(6, 'Empanadas', 1, 1.50, '2025-12-21');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `metodos_pago`
--

CREATE TABLE `metodos_pago` (
  `id_metodo` int(11) NOT NULL,
  `nombre_metodo` varchar(50) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `metodos_pago`
--

INSERT INTO `metodos_pago` (`id_metodo`, `nombre_metodo`, `activo`) VALUES
(1, 'Efectivo', 0),
(2, 'Tarjeta', 1),
(3, 'Transferencia', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `parametros_negocio`
--

CREATE TABLE `parametros_negocio` (
  `id_parametro` int(11) NOT NULL,
  `nombre_parametro` varchar(50) NOT NULL,
  `valor_numerico` decimal(10,2) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `parametros_negocio`
--

INSERT INTO `parametros_negocio` (`id_parametro`, `nombre_parametro`, `valor_numerico`, `descripcion`) VALUES
(13, 'Umbral_Tolerancia_Efectivo', 5.00, 'Diferencia máxima aceptada en el cuadre de caja (USD).'),
(14, 'Tasa_Riesgo_TDC', 0.02, 'Umbral para la Alerta Roja de Tasa de Descuadre Crítico (2.0%).'),
(15, 'Dias_Stock_Seguridad', 5.00, 'Días mínimos de inventario para el KPI 7 (DRI).'),
(16, 'Dias_Obsolescencia', 60.00, 'Días sin venta para clasificar un producto como Inventario Muerto (KPI 8).');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles_y_privilegios`
--

CREATE TABLE `roles_y_privilegios` (
  `id_rol` int(11) NOT NULL,
  `nombre_rol` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `roles_y_privilegios`
--

INSERT INTO `roles_y_privilegios` (`id_rol`, `nombre_rol`, `descripcion`) VALUES
(1, 'Administrador', 'Acceso total a configuración y data maestra.'),
(2, 'Cajero', 'Registro de transacciones diarias (D1).'),
(3, 'Supervisor', 'Autoriza cierres (A.1) y egresos.'),
(4, 'Contador', 'Auditoría y conciliación financiera (A.2).');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipo_movimiento`
--

CREATE TABLE `tipo_movimiento` (
  `id_tipo_movimiento` int(11) NOT NULL,
  `nombre_movimiento` varchar(100) NOT NULL,
  `tipo_flujo` enum('Entrada','Salida') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tipo_movimiento`
--

INSERT INTO `tipo_movimiento` (`id_tipo_movimiento`, `nombre_movimiento`, `tipo_flujo`) VALUES
(1, 'Venta Normal', 'Salida'),
(2, 'Egreso por Vencimiento', 'Salida'),
(3, 'Cortesia', 'Salida'),
(4, 'Compra/Entrada de Stock', 'Entrada');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `transacciones`
--

CREATE TABLE `transacciones` (
  `id_registro` bigint(20) NOT NULL,
  `id_usuario_cajero_fk` int(11) DEFAULT NULL,
  `id_jornada_fk` int(11) DEFAULT NULL,
  `fecha_transaccion` datetime DEFAULT NULL,
  `turno` varchar(10) DEFAULT NULL,
  `tipo_cobro` varchar(20) NOT NULL,
  `monto_total` decimal(10,2) DEFAULT NULL,
  `es_egreso` tinyint(1) NOT NULL,
  `referencia_banco` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Volcado de datos para la tabla `transacciones`
--

INSERT INTO `transacciones` (`id_registro`, `id_usuario_cajero_fk`, `id_jornada_fk`, `fecha_transaccion`, `turno`, `tipo_cobro`, `monto_total`, `es_egreso`, `referencia_banco`) VALUES
(1001, NULL, NULL, '2025-12-10 00:00:00', 'Tarde', 'Efectivo', 1250.00, 0, NULL),
(1002, NULL, NULL, '2025-12-10 00:00:00', 'Tarde', 'TPV', 3750.00, 0, NULL),
(2001, NULL, NULL, '2025-12-11 00:00:00', 'Tarde', 'Efectivo', 1500.00, 0, NULL),
(2002, NULL, NULL, '2025-12-11 00:00:00', 'Tarde', 'Pago_Movil', 3600.00, 0, NULL),
(3001, NULL, NULL, '2025-12-12 00:00:00', 'Mañana', 'Efectivo', 1100.00, 0, NULL),
(3002, NULL, NULL, '2025-12-12 00:00:00', 'Mañana', 'TPV', 3700.00, 0, NULL),
(3003, NULL, NULL, '2025-12-12 00:00:00', 'Mañana', 'N/A', 12.00, 1, NULL),
(4001, NULL, NULL, '2025-12-13 00:00:00', 'Tarde', 'Efectivo', 1600.00, 0, NULL),
(4002, NULL, NULL, '2025-12-13 00:00:00', 'Tarde', 'TPV', 4000.00, 0, NULL),
(5001, NULL, NULL, '2025-12-14 00:00:00', 'Mañana', 'Efectivo', 1200.00, 0, NULL),
(5002, NULL, NULL, '2025-12-14 00:00:00', 'Mañana', 'Pago_Movil', 3700.00, 0, NULL),
(5003, 1, 2, '2025-12-21 00:16:15', NULL, '', NULL, 0, NULL),
(5004, 1, 2, '2025-12-21 00:16:23', NULL, '', NULL, 0, NULL),
(5005, 1, 2, '2025-12-21 00:16:27', NULL, '', NULL, 0, NULL),
(5006, 1, 2, '2025-12-21 00:16:37', NULL, '', NULL, 0, NULL),
(5007, 1, 2, '2025-12-21 00:16:43', NULL, '', NULL, 0, NULL),
(5008, 1, 2, '2025-12-21 00:21:10', NULL, '', NULL, 0, NULL),
(5009, 1, 2, '2025-12-21 00:23:52', NULL, '', NULL, 0, NULL),
(5010, 1, 2, '2025-12-21 00:40:17', NULL, '', NULL, 0, NULL),
(5011, 1, 2, '2025-12-21 00:44:09', NULL, '', NULL, 0, NULL),
(5012, 1, 3, '2025-12-21 01:10:07', NULL, '', NULL, 0, NULL),
(5013, 1, 3, '2025-12-21 01:12:31', NULL, '', NULL, 0, NULL),
(5014, 1, 3, '2025-12-21 01:12:37', NULL, '', NULL, 0, NULL),
(5015, 1, 3, '2025-12-21 01:12:59', NULL, '', NULL, 0, NULL),
(5016, 1, 3, '2025-12-21 01:15:38', NULL, '', NULL, 0, NULL),
(5017, 1, 3, '2025-12-21 01:15:46', NULL, '', NULL, 0, NULL),
(5018, 1, 3, '2025-12-21 01:17:51', NULL, '', NULL, 0, NULL),
(5019, 1, 4, '2025-12-21 11:15:49', NULL, '', NULL, 0, NULL),
(5020, 1, 4, '2025-12-21 11:16:14', NULL, '', NULL, 0, NULL),
(5021, 1, 5, '2025-12-21 11:24:12', NULL, '', NULL, 0, NULL),
(5022, 1, 5, '2025-12-21 11:44:10', NULL, '', NULL, 0, NULL),
(5023, 1, 6, '2025-12-21 12:38:02', NULL, '', 2.50, 0, NULL),
(5024, 1, 6, '2025-12-21 12:38:10', NULL, '', 4.00, 0, NULL),
(5025, 1, 6, '2025-12-21 12:52:28', NULL, '', 2.50, 0, NULL),
(5026, 1, 6, '2025-12-21 12:52:50', NULL, '', 5.00, 0, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `user_full_name` varchar(100) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `id_rol_fk` int(11) DEFAULT 2,
  `role` varchar(20) DEFAULT 'Cajero'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `username`, `password_hash`, `user_full_name`, `estado`, `id_rol_fk`, `role`) VALUES
(1, 'admin', '$2y$10$9xHeMTr1a3zpluHXmLhsZOik7rkgI3h35.M88siJb60fN0t1B.rau', 'Administrador Principal', 'Activo', 1, 'Admin'),
(2, 'mcorales', '$2y$10$5F8B1sVi2NKY6JePFHzAhO19/rLUmhq693BdtF4vv8ZxG8AroHsSC', 'Marcos Corales', 'Activo', 3, 'Supervisor');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `cierres_caja`
--
ALTER TABLE `cierres_caja`
  ADD PRIMARY KEY (`id_cierre`),
  ADD UNIQUE KEY `fecha` (`fecha`),
  ADD KEY `id_cajero_fk` (`id_cajero_fk`);

--
-- Indices de la tabla `conciliacion`
--
ALTER TABLE `conciliacion`
  ADD PRIMARY KEY (`id_conciliacion`),
  ADD KEY `id_conciliacion_final_fk` (`id_conciliacion_final_fk`);

--
-- Indices de la tabla `conciliacion_final`
--
ALTER TABLE `conciliacion_final`
  ADD PRIMARY KEY (`id_conciliacion_final`),
  ADD UNIQUE KEY `fecha_venta` (`fecha_venta`),
  ADD KEY `id_auditor_fk` (`id_auditor_fk`);

--
-- Indices de la tabla `control_jornadas`
--
ALTER TABLE `control_jornadas`
  ADD PRIMARY KEY (`id_jornada`),
  ADD KEY `fk_jornada_apertura` (`id_usuario_apertura_fk`),
  ADD KEY `fk_jornada_cierre` (`id_usuario_cierre_fk`);

--
-- Indices de la tabla `detalle_egresos`
--
ALTER TABLE `detalle_egresos`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_transaccion_fk` (`id_transaccion_fk`),
  ADD KEY `id_producto_fk` (`id_producto_fk`);

--
-- Indices de la tabla `detalle_pago`
--
ALTER TABLE `detalle_pago`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_transaccion_fk` (`id_transaccion_fk`);

--
-- Indices de la tabla `detalle_transaccion`
--
ALTER TABLE `detalle_transaccion`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_transaccion_fk` (`id_transaccion_fk`),
  ADD KEY `id_producto_fk` (`id_producto_fk`);

--
-- Indices de la tabla `historial_tasa_de_cambio`
--
ALTER TABLE `historial_tasa_de_cambio`
  ADD PRIMARY KEY (`id_tasa`),
  ADD KEY `usuario_modifico` (`usuario_modifico`);

--
-- Indices de la tabla `inventario`
--
ALTER TABLE `inventario`
  ADD PRIMARY KEY (`id_producto`);

--
-- Indices de la tabla `metodos_pago`
--
ALTER TABLE `metodos_pago`
  ADD PRIMARY KEY (`id_metodo`);

--
-- Indices de la tabla `parametros_negocio`
--
ALTER TABLE `parametros_negocio`
  ADD PRIMARY KEY (`id_parametro`),
  ADD UNIQUE KEY `nombre_parametro` (`nombre_parametro`);

--
-- Indices de la tabla `roles_y_privilegios`
--
ALTER TABLE `roles_y_privilegios`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `nombre_rol` (`nombre_rol`);

--
-- Indices de la tabla `tipo_movimiento`
--
ALTER TABLE `tipo_movimiento`
  ADD PRIMARY KEY (`id_tipo_movimiento`);

--
-- Indices de la tabla `transacciones`
--
ALTER TABLE `transacciones`
  ADD PRIMARY KEY (`id_registro`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_usuario_rol` (`id_rol_fk`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `cierres_caja`
--
ALTER TABLE `cierres_caja`
  MODIFY `id_cierre` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `conciliacion`
--
ALTER TABLE `conciliacion`
  MODIFY `id_conciliacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `conciliacion_final`
--
ALTER TABLE `conciliacion_final`
  MODIFY `id_conciliacion_final` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `control_jornadas`
--
ALTER TABLE `control_jornadas`
  MODIFY `id_jornada` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `detalle_egresos`
--
ALTER TABLE `detalle_egresos`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `detalle_pago`
--
ALTER TABLE `detalle_pago`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de la tabla `detalle_transaccion`
--
ALTER TABLE `detalle_transaccion`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT de la tabla `inventario`
--
ALTER TABLE `inventario`
  MODIFY `id_producto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `metodos_pago`
--
ALTER TABLE `metodos_pago`
  MODIFY `id_metodo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `parametros_negocio`
--
ALTER TABLE `parametros_negocio`
  MODIFY `id_parametro` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de la tabla `roles_y_privilegios`
--
ALTER TABLE `roles_y_privilegios`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `transacciones`
--
ALTER TABLE `transacciones`
  MODIFY `id_registro` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5027;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `detalle_egresos`
--
ALTER TABLE `detalle_egresos`
  ADD CONSTRAINT `detalle_egresos_ibfk_1` FOREIGN KEY (`id_transaccion_fk`) REFERENCES `transacciones` (`id_registro`),
  ADD CONSTRAINT `detalle_egresos_ibfk_2` FOREIGN KEY (`id_producto_fk`) REFERENCES `inventario` (`id_producto`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
