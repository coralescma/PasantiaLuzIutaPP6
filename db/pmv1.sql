-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 16-12-2025 a las 14:12:21
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
-- Base de datos: `pmv1`
--

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
-- Estructura de tabla para la tabla `control`
--

CREATE TABLE `control` (
  `id_control` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `id_cajero_fk` int(11) NOT NULL,
  `monto_registrado_efectivo` decimal(10,2) NOT NULL,
  `monto_contado_fisico` decimal(10,2) NOT NULL,
  `diferencia` decimal(10,2) NOT NULL,
  `codigo_validacion` enum('X','Z') NOT NULL,
  `observacion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `control`
--

INSERT INTO `control` (`id_control`, `fecha`, `id_cajero_fk`, `monto_registrado_efectivo`, `monto_contado_fisico`, `diferencia`, `codigo_validacion`, `observacion`) VALUES
(1, '2025-12-10', 101, 1250.00, 1248.00, -2.00, 'Z', NULL),
(2, '2025-12-11', 101, 1500.00, 1475.00, -25.00, 'X', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `control_jornadas`
--

CREATE TABLE `control_jornadas` (
  `id_jornada` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `id_usuario_fk` int(11) NOT NULL,
  `id_usuario_cierre_fk` int(11) DEFAULT NULL,
  `monto_apertura` decimal(10,2) NOT NULL,
  `monto_ventas_sistema` decimal(10,2) DEFAULT 0.00,
  `monto_conteo_fisico` decimal(10,2) DEFAULT 0.00,
  `diferencia` decimal(10,2) DEFAULT 0.00,
  `estado_jornada` int(11) DEFAULT 1,
  `notas` text DEFAULT NULL,
  `apertura_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `cierre_timestamp` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `control_jornadas`
--

INSERT INTO `control_jornadas` (`id_jornada`, `fecha`, `id_usuario_fk`, `id_usuario_cierre_fk`, `monto_apertura`, `monto_ventas_sistema`, `monto_conteo_fisico`, `diferencia`, `estado_jornada`, `notas`, `apertura_timestamp`, `cierre_timestamp`) VALUES
(1, '2025-12-14', 1, NULL, 200.00, 0.00, 0.00, 0.00, 1, 'Jornada de regularización de ventas pasadas', '2025-12-16 08:07:13', NULL);

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
(1, 1, 1, 1, 8.50, 0, NULL, NULL),
(2, 2, 3, 1, 5.00, 0, NULL, NULL),
(3, 2, 2, 1, 1.25, 0, NULL, NULL),
(4, 2, 1, 1, 8.50, 0, NULL, NULL),
(5, 3, 1, 1, 8.50, 0, NULL, NULL);

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
  `nombre_producto` varchar(255) NOT NULL,
  `stock_actual` int(11) NOT NULL DEFAULT 0,
  `costo_unitario` decimal(10,2) NOT NULL,
  `ultima_venta_fecha` date DEFAULT NULL,
  `proveedor` varchar(255) DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `inventario`
--

INSERT INTO `inventario` (`id_producto`, `nombre_producto`, `stock_actual`, `costo_unitario`, `ultima_venta_fecha`, `proveedor`, `fecha_registro`) VALUES
(1, 'Café Molido Gourmet 250g', 147, 8.50, '2025-12-16', NULL, '2025-12-16 01:21:17'),
(2, 'Tostada Integral', 299, 1.25, '2025-12-16', NULL, '2025-12-16 01:21:17'),
(3, 'Jugo Natural de Naranja (Litro)', 49, 5.00, '2025-12-16', NULL, '2025-12-16 01:21:17');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `parametros_negocio`
--

CREATE TABLE `parametros_negocio` (
  `id_parametro` int(11) NOT NULL,
  `umbral_tolerancia_efectivo` decimal(5,2) NOT NULL DEFAULT 5.00,
  `umbral_conciliacion_bancaria` decimal(5,2) NOT NULL DEFAULT 2.00,
  `efectivo_requiere_conteo_inicial` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `parametros_negocio`
--

INSERT INTO `parametros_negocio` (`id_parametro`, `umbral_tolerancia_efectivo`, `umbral_conciliacion_bancaria`, `efectivo_requiere_conteo_inicial`) VALUES
(1, 5.00, 2.00, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles_y_privilegios`
--

CREATE TABLE `roles_y_privilegios` (
  `id_rol` int(11) NOT NULL,
  `nombre_rol` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(3, 'Egreso por Mermas', 'Salida'),
(4, 'Compra/Entrada de Stock', 'Entrada');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `transacciones`
--

CREATE TABLE `transacciones` (
  `id_transaccion` int(11) NOT NULL,
  `fecha_venta` date NOT NULL,
  `monto_venta` decimal(10,2) NOT NULL,
  `tipo_cobro` enum('Efectivo','TPV','Pago_Movil','N/A') NOT NULL,
  `es_egreso` tinyint(1) NOT NULL DEFAULT 0,
  `usuario_registro` varchar(100) NOT NULL,
  `referencia_banco` varchar(255) DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `transacciones`
--

INSERT INTO `transacciones` (`id_transaccion`, `fecha_venta`, `monto_venta`, `tipo_cobro`, `es_egreso`, `usuario_registro`, `referencia_banco`, `fecha_registro`) VALUES
(1, '2025-12-16', 8.50, 'TPV', 0, 'admin', '131', '2025-12-16 02:18:46'),
(2, '2025-12-16', 14.75, 'Pago_Movil', 0, 'admin', '32453', '2025-12-16 02:19:17'),
(3, '2025-12-16', 8.50, 'Pago_Movil', 0, 'admin', '5232', '2025-12-16 08:33:45');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL,
  `id_rol_fk` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `contrasena_hash` varchar(255) NOT NULL,
  `estado` enum('Activo','Inactivo') DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`id_usuario`, `id_rol_fk`, `nombre`, `usuario`, `contrasena_hash`, `estado`) VALUES
(101, 2, 'Ana López', 'ana.lopez', 'hash123', 'Activo'),
(102, 3, 'Gerente de Turno', 'gerente', 'hash123', 'Activo'),
(103, 4, 'Maria Castillo', 'm.castillo', 'hash123', 'Activo'),
(104, 1, 'Admin Master', 'admin', 'hash123', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `user_full_name` varchar(100) NOT NULL,
  `rol` enum('Administrador','Supervisor','Cajero') NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `username`, `password_hash`, `user_full_name`, `rol`, `estado`) VALUES
(1, 'admin', '$2y$10$fQ7xH4c7tN2/qQ8nS/2H0.w0f0jR7g0pQ.xGjTf2.hD6E.V1E.V5', 'Administrador Principal', 'Administrador', 'Activo');

--
-- Índices para tablas volcadas
--

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
-- Indices de la tabla `control`
--
ALTER TABLE `control`
  ADD PRIMARY KEY (`id_control`),
  ADD UNIQUE KEY `fecha` (`fecha`),
  ADD KEY `id_cajero_fk` (`id_cajero_fk`);

--
-- Indices de la tabla `control_jornadas`
--
ALTER TABLE `control_jornadas`
  ADD PRIMARY KEY (`id_jornada`),
  ADD KEY `id_usuario_fk` (`id_usuario_fk`),
  ADD KEY `fk_usuario_cierre` (`id_usuario_cierre_fk`);

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
-- Indices de la tabla `parametros_negocio`
--
ALTER TABLE `parametros_negocio`
  ADD PRIMARY KEY (`id_parametro`);

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
  ADD PRIMARY KEY (`id_transaccion`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `usuario` (`usuario`),
  ADD KEY `id_rol_fk` (`id_rol_fk`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

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
-- AUTO_INCREMENT de la tabla `control`
--
ALTER TABLE `control`
  MODIFY `id_control` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `control_jornadas`
--
ALTER TABLE `control_jornadas`
  MODIFY `id_jornada` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `detalle_transaccion`
--
ALTER TABLE `detalle_transaccion`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `historial_tasa_de_cambio`
--
ALTER TABLE `historial_tasa_de_cambio`
  MODIFY `id_tasa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `inventario`
--
ALTER TABLE `inventario`
  MODIFY `id_producto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `roles_y_privilegios`
--
ALTER TABLE `roles_y_privilegios`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `tipo_movimiento`
--
ALTER TABLE `tipo_movimiento`
  MODIFY `id_tipo_movimiento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `transacciones`
--
ALTER TABLE `transacciones`
  MODIFY `id_transaccion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `conciliacion`
--
ALTER TABLE `conciliacion`
  ADD CONSTRAINT `conciliacion_ibfk_1` FOREIGN KEY (`id_conciliacion_final_fk`) REFERENCES `conciliacion_final` (`id_conciliacion_final`);

--
-- Filtros para la tabla `conciliacion_final`
--
ALTER TABLE `conciliacion_final`
  ADD CONSTRAINT `conciliacion_final_ibfk_1` FOREIGN KEY (`id_auditor_fk`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `control`
--
ALTER TABLE `control`
  ADD CONSTRAINT `control_ibfk_1` FOREIGN KEY (`id_cajero_fk`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `control_jornadas`
--
ALTER TABLE `control_jornadas`
  ADD CONSTRAINT `control_jornadas_ibfk_1` FOREIGN KEY (`id_usuario_fk`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `fk_usuario_cierre` FOREIGN KEY (`id_usuario_cierre_fk`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `detalle_transaccion`
--
ALTER TABLE `detalle_transaccion`
  ADD CONSTRAINT `detalle_transaccion_ibfk_1` FOREIGN KEY (`id_transaccion_fk`) REFERENCES `transacciones` (`id_transaccion`),
  ADD CONSTRAINT `detalle_transaccion_ibfk_2` FOREIGN KEY (`id_producto_fk`) REFERENCES `inventario` (`id_producto`);

--
-- Filtros para la tabla `historial_tasa_de_cambio`
--
ALTER TABLE `historial_tasa_de_cambio`
  ADD CONSTRAINT `historial_tasa_de_cambio_ibfk_1` FOREIGN KEY (`usuario_modifico`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD CONSTRAINT `usuario_ibfk_1` FOREIGN KEY (`id_rol_fk`) REFERENCES `roles_y_privilegios` (`id_rol`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
