-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 15-01-2026 a las 20:11:01
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

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `AgregarDatosSimulados` ()   BEGIN
    DECLARE fecha_actual DATE DEFAULT '2025-01-01';
    DECLARE id_jornada_new INT;
    DECLARE id_trans_new INT;
    DECLARE v_contador INT;
    DECLARE v_ventas_dia INT;
    DECLARE v_items_venta INT;
    DECLARE v_monto_total_venta DECIMAL(10,2);
    
    -- El bucle recorrerá todo el año 2025 agregando datos adicionales
    WHILE fecha_actual <= '2025-12-31' DO
        
        -- 1. Crear una nueva Jornada para el día (Usuario 1 como ejemplo)
        INSERT INTO control_jornadas (id_usuario_apertura_fk, fecha_apertura, monto_apertura, estado_jornada, fecha_cierre) 
        VALUES (1, CONCAT(fecha_actual, ' 08:00:00'), 40.00, 'Cerrada', CONCAT(fecha_actual, ' 21:00:00'));
        
        SET id_jornada_new = LAST_INSERT_ID();
        
        -- 2. Generar entre 3 y 7 ventas nuevas por cada día
        SET v_ventas_dia = FLOOR(3 + (RAND() * 5));
        SET v_contador = 0;
        
        WHILE v_contador < v_ventas_dia DO
            -- Insertar Cabecera de Transacción
            INSERT INTO transacciones (id_usuario_cajero_fk, id_jornada_fk, fecha_transaccion, es_egreso, monto_total) 
            VALUES (1, id_jornada_new, CONCAT(fecha_actual, ' ', LPAD(FLOOR(9 + (RAND() * 10)), 2, '0'), ':', LPAD(FLOOR(RAND() * 60), 2, '0'), ':00'), 0, 0);
            
            SET id_trans_new = LAST_INSERT_ID();
            
            -- 3. Agregar productos aleatorios al detalle (cantidad 1 o 2)
            -- Se usa el campo 'precio' de la tabla inventario
            INSERT INTO detalle_transaccion (id_transaccion_fk, id_producto_fk, cantidad, precio_venta)
            SELECT id_trans_new, id_producto, FLOOR(1 + (RAND() * 2)), precio 
            FROM inventario 
            ORDER BY RAND() 
            LIMIT 2;
            
            -- Calcular y actualizar el monto total de esta nueva transacción
            SELECT SUM(cantidad * precio_venta) INTO v_monto_total_venta 
            FROM detalle_transaccion 
            WHERE id_transaccion_fk = id_trans_new;
            
            UPDATE transacciones SET monto_total = v_monto_total_venta WHERE id_registro = id_trans_new;
            
            -- 4. Registrar el Pago
            INSERT INTO detalle_pago (id_transaccion_fk, id_metodo_fk, monto_pago)
            VALUES (id_trans_new, (SELECT id_metodo FROM metodos_pago LIMIT 1), v_monto_total_venta);
            
            SET v_contador = v_contador + 1;
        END WHILE;

        SET fecha_actual = DATE_ADD(fecha_actual, INTERVAL 1 DAY);
    END WHILE;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GenerarDatos2025` ()   BEGIN
    DECLARE fecha_actual DATE DEFAULT '2025-01-01';
    DECLARE id_jornada_new INT;
    DECLARE id_trans_new INT;
    DECLARE v_contador INT;
    DECLARE v_ventas_dia INT;
    DECLARE v_items_venta INT;
    DECLARE v_prod_id INT;
    DECLARE v_precio DECIMAL(10,2);
    DECLARE v_monto_total_venta DECIMAL(10,2);
    
    -- Limpiar datos previos del 2025 para evitar duplicados si lo corres de nuevo
    DELETE FROM detalle_pago WHERE id_transaccion_fk IN (SELECT id_registro FROM transacciones WHERE YEAR(fecha_transaccion) = 2025);
    DELETE FROM detalle_transaccion WHERE id_transaccion_fk IN (SELECT id_registro FROM transacciones WHERE YEAR(fecha_transaccion) = 2025);
    DELETE FROM transacciones WHERE YEAR(fecha_transaccion) = 2025;
    DELETE FROM control_jornadas WHERE YEAR(fecha_apertura) = 2025;

    WHILE fecha_actual <= '2025-12-31' DO
        -- 1. Crear Jornada (ID Usuario 1)
        INSERT INTO control_jornadas (id_usuario_apertura_fk, fecha_apertura, monto_apertura, estado_jornada, fecha_cierre) 
        VALUES (1, CONCAT(fecha_actual, ' 08:00:00'), 50.00, 'Cerrada', CONCAT(fecha_actual, ' 20:00:00'));
        
        SET id_jornada_new = LAST_INSERT_ID();
        
        -- 2. Decidir cuántas ventas tendrá el día (entre 5 y 15)
        SET v_ventas_dia = FLOOR(5 + (RAND() * 11));
        SET v_contador = 0;
        
        WHILE v_contador < v_ventas_dia DO
            -- Insertar Cabecera de Transacción
            INSERT INTO transacciones (id_usuario_cajero_fk, id_jornada_fk, fecha_transaccion, es_egreso, monto_total) 
            VALUES (1, id_jornada_new, CONCAT(fecha_actual, ' ', LPAD(FLOOR(9 + (RAND() * 10)), 2, '0'), ':', LPAD(FLOOR(RAND() * 60), 2, '0'), ':00'), 0, 0);
            
            SET id_trans_new = LAST_INSERT_ID();
            SET v_monto_total_venta = 0;
            
            -- 3. Agregar 1 o 2 productos al detalle
            SET v_items_venta = FLOOR(1 + (RAND() * 2));
            
            -- Seleccionamos un producto al azar de tu tabla inventario
            -- Nota: Se asume que existen productos. Ajustamos el precio según tu columna 'precio'
            INSERT INTO detalle_transaccion (id_transaccion_fk, id_producto_fk, cantidad, precio_venta)
            SELECT id_trans_new, id_producto, 1, precio 
            FROM inventario 
            ORDER BY RAND() 
            LIMIT v_items_venta;
            
            -- Calcular el monto total de la venta basado en lo que acabamos de insertar
            SELECT SUM(cantidad * precio_venta) INTO v_monto_total_venta 
            FROM detalle_transaccion 
            WHERE id_transaccion_fk = id_trans_new;
            
            -- Actualizar la cabecera con el total real
            UPDATE transacciones SET monto_total = v_monto_total_venta WHERE id_registro = id_trans_new;
            
            -- 4. Registrar el Pago (usando el primer método de pago disponible)
            INSERT INTO detalle_pago (id_transaccion_fk, id_metodo_fk, monto_pago)
            VALUES (id_trans_new, (SELECT id_metodo FROM metodos_pago LIMIT 1), v_monto_total_venta);
            
            SET v_contador = v_contador + 1;
        END WHILE;

        SET fecha_actual = DATE_ADD(fecha_actual, INTERVAL 1 DAY);
    END WHILE;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GenerarDatosVentas2025` ()   BEGIN
    DECLARE fecha_iteracion DATE DEFAULT '2025-01-01';
    DECLARE id_jornada_temp INT;
    DECLARE id_trans_temp INT;
    DECLARE contador_ventas INT;
    DECLARE ventas_por_dia INT;
    DECLARE id_usuario_simulado INT DEFAULT 1; -- Asumiendo que el ID 1 es el admin/cajero
    DECLARE id_metodo_simulado INT DEFAULT 1;  -- Asumiendo que el ID 1 es Efectivo

    -- Bucle principal: Recorre cada día del año 2025
    WHILE fecha_iteracion <= '2025-12-31' DO
        
        -- PASO 1: Simular 'form_jornada.php' (Apertura de Jornada)
        -- Insertamos la jornada con el estado 'Cerrada' para que sea un registro histórico
        INSERT INTO control_jornadas (id_usuario_apertura_fk, fecha_apertura, monto_apertura, estado_jornada) 
        VALUES (id_usuario_simulado, CONCAT(fecha_iteracion, ' 08:00:00'), 50.00, 'Cerrada');
        
        SET id_jornada_temp = LAST_INSERT_ID();

        -- PASO 2: Determinar cuántas ventas tendrá este día (entre 3 y 8 ventas)
        SET ventas_por_dia = FLOOR(3 + (RAND() * 6));
        SET contador_ventas = 0;

        WHILE contador_ventas < ventas_por_dia DO
            
            -- PASO 3: Simular 'form_transacciones.php' (Cabecera de la venta)
            -- es_egreso = 0 porque es una venta/ingreso
            INSERT INTO transacciones (id_usuario_cajero_fk, id_jornada_fk, fecha_transaccion, es_egreso) 
            VALUES (id_usuario_simulado, id_jornada_temp, CONCAT(fecha_iteracion, ' ', LPAD(FLOOR(9 + (RAND() * 10)), 2, '0'), ':', LPAD(FLOOR(RAND() * 60), 2, '0'), ':00'), 0);
            
            SET id_trans_temp = LAST_INSERT_ID();

            -- PASO 4: Simular el Detalle de Pago
            -- Generamos un monto aleatorio para la venta (ej. entre $10 y $100)
            INSERT INTO detalle_pago (id_transaccion_fk, id_metodo_pago_fk, monto_pagado, referencia) 
            VALUES (id_trans_temp, id_metodo_simulado, ROUND(10 + (RAND() * 90), 2), 'SIM-2025');

            SET contador_ventas = contador_ventas + 1;
        END WHILE;

        -- Avanzar al siguiente día
        SET fecha_iteracion = DATE_ADD(fecha_iteracion, INTERVAL 1 DAY);
    END WHILE;

END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GenerarSistemaReal2025` ()   BEGIN
    DECLARE fecha_iter DATE DEFAULT '2025-01-01';
    DECLARE id_jornada_temp, id_trans_temp, id_prod_temp, v_usuario INT DEFAULT 1;
    DECLARE v_ventas_dia, v_items_venta, i, j INT;
    DECLARE v_precio_prod, v_total_venta DECIMAL(10,2);
    DECLARE v_referencia VARCHAR(50);
    DECLARE v_metodo_pago INT;

    -- Limpieza para evitar duplicados
    DELETE FROM detalle_pago WHERE id_transaccion_fk IN (SELECT id_registro FROM transacciones WHERE YEAR(fecha_transaccion) = 2025);
    DELETE FROM detalle_egresos WHERE id_transaccion_fk IN (SELECT id_registro FROM transacciones WHERE YEAR(fecha_transaccion) = 2025);
    DELETE FROM transacciones WHERE YEAR(fecha_transaccion) = 2025;
    DELETE FROM control_jornadas WHERE YEAR(fecha_apertura) = 2025;

    WHILE fecha_iter <= '2025-12-31' DO
        -- 1. CREAR JORNADA
        INSERT INTO control_jornadas (id_usuario_apertura_fk, fecha_apertura, monto_apertura, estado_jornada) 
        VALUES (v_usuario, CONCAT(fecha_iter, ' 08:00:00'), ROUND(30 + RAND() * 20, 2), 'Cerrada');
        SET id_jornada_temp = LAST_INSERT_ID();

        -- Determinar ventas (de 4 a 10 por día)
        SET v_ventas_dia = FLOOR(4 + RAND() * 7);
        SET i = 0;

        WHILE i < v_ventas_dia DO
            -- 2. CREAR TRANSACCIÓN (Venta)
            INSERT INTO transacciones (id_usuario_cajero_fk, id_jornada_fk, fecha_transaccion, es_egreso) 
            VALUES (v_usuario, id_jornada_temp, CONCAT(fecha_iter, ' ', MAKETIME(9 + (i % 10), FLOOR(RAND()*60), 0)), 0);
            SET id_trans_temp = LAST_INSERT_ID();

            -- 3. AGREGAR PRODUCTOS ALEATORIOS (Detalle Egreso/Venta)
            SET v_items_venta = FLOOR(1 + RAND() * 3); -- De 1 a 3 productos por venta
            SET v_total_venta = 0;
            SET j = 0;

            WHILE j < v_items_venta DO
                -- Seleccionamos un producto al azar del inventario que tenga stock
                SELECT id_producto, precio_venta INTO id_prod_temp, v_precio_prod 
                FROM inventario ORDER BY RAND() LIMIT 1;

                INSERT INTO detalle_egresos (id_transaccion_fk, id_producto_fk, cantidad, precio_unitario)
                VALUES (id_trans_temp, id_prod_temp, 1, v_precio_prod);
                
                SET v_total_venta = v_total_venta + v_precio_prod;
                SET j = j + 1;
            END WHILE;

            -- 4. CREAR PAGO ALEATORIO
            -- Variedad en métodos de pago (asumiendo IDs 1 al 3: Efectivo, Transferencia, etc.)
            SET v_metodo_pago = FLOOR(1 + RAND() * 3);
            -- Referencia aleatoria (Combinación de letras y números)
            SET v_referencia = CONCAT('REF-', UPPER(SUBSTRING(MD5(RAND()),1,8)));

            INSERT INTO detalle_pago (id_transaccion_fk, id_metodo_pago_fk, monto_pagado, referencia) 
            VALUES (id_trans_temp, v_metodo_pago, v_total_venta, v_referencia);

            SET i = i + 1;
        END WHILE;

        SET fecha_iter = DATE_ADD(fecha_iter, INTERVAL 1 DAY);
    END WHILE;
END$$

DELIMITER ;

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
(2, '2025-12-11', 101, NULL, 1500.00, 1475.00, NULL, -25.00, 'X', NULL),
(5, '2026-01-10', 1, NULL, 0.00, 0.00, NULL, 0.00, 'X', 'sin'),
(10, '2026-01-11', 1, NULL, 0.00, 0.00, NULL, 0.00, 'Z', 'sin novedad'),
(12, '2026-01-12', 1, NULL, 0.00, 0.00, NULL, 0.00, 'Z', 'sin novedad');

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
(9, '2026-01-04 18:19:23', 1, NULL, 0.00, 0.00, 0.00, 0.00, 'Cerrada', NULL, '2026-01-04 22:19:23', NULL, '2026-01-04 18:41:17', NULL, NULL),
(10, '2026-01-04 19:26:34', 1, NULL, 0.00, 0.00, 0.00, 0.00, 'Cerrada', NULL, '2026-01-04 23:26:34', NULL, '2026-01-04 22:02:36', NULL, NULL),
(14, '2025-01-01 08:00:00', 1, NULL, 35.67, 0.00, 0.00, 0.00, 'Cerrada', NULL, '2026-01-05 02:08:10', NULL, NULL, NULL, NULL),
(15, '2026-01-05 13:22:37', 1, NULL, 15.00, 0.00, 0.00, 0.00, 'Cerrada', NULL, '2026-01-05 17:22:37', NULL, '2026-01-05 21:42:04', NULL, NULL),
(16, '2026-01-08 14:51:35', 1, NULL, 0.00, 0.00, 0.00, 0.00, 'Cerrada', NULL, '2026-01-08 18:51:35', NULL, '2026-01-10 11:32:48', NULL, NULL),
(17, '2026-01-10 12:02:05', 1, NULL, 0.00, 0.00, 0.00, 0.00, 'Cerrada', NULL, '2026-01-10 16:02:05', NULL, '2026-01-10 12:40:52', NULL, NULL),
(18, '2026-01-10 12:47:25', 1, NULL, 0.00, 0.00, 0.00, 0.00, 'Cerrada', NULL, '2026-01-10 16:47:25', NULL, '2026-01-10 12:58:20', NULL, NULL),
(19, '2026-01-10 12:59:44', 1, NULL, 0.00, 0.00, 0.00, 0.00, 'Cerrada', NULL, '2026-01-10 16:59:44', NULL, '2026-01-11 23:09:07', NULL, NULL),
(20, '2026-01-11 23:10:03', 1, NULL, 0.00, 0.00, 0.00, 0.00, 'Cerrada', NULL, '2026-01-12 03:10:03', NULL, '2026-01-11 23:36:13', NULL, NULL),
(21, '2026-01-11 23:36:31', 1, NULL, 0.00, 0.00, 0.00, 0.00, 'Cerrada', NULL, '2026-01-12 03:36:31', NULL, '2026-01-12 00:05:28', NULL, NULL),
(22, '2026-01-12 00:07:22', 1, NULL, 0.00, 0.00, 0.00, 0.00, 'Cerrada', NULL, '2026-01-12 04:07:22', NULL, '2026-01-12 00:21:29', NULL, NULL),
(23, '2026-01-12 00:40:50', 1, NULL, 0.00, 0.00, 0.00, 0.00, 'Cerrada', NULL, '2026-01-12 04:40:50', NULL, '2026-01-12 01:27:10', NULL, NULL),
(24, '2026-01-12 10:46:22', 1, NULL, 0.00, 0.00, 0.00, 0.00, 'Abierta', NULL, '2026-01-12 14:46:22', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_egresos`
--

CREATE TABLE `detalle_egresos` (
  `id_detalle` int(11) NOT NULL,
  `id_transaccion_fk` bigint(20) DEFAULT NULL,
  `id_producto_fk` int(11) DEFAULT NULL,
  `id_tipo_movimiento_fk` int(11) DEFAULT NULL,
  `cantidad` int(11) NOT NULL,
  `usuario_autorizador` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `detalle_egresos`
--

INSERT INTO `detalle_egresos` (`id_detalle`, `id_transaccion_fk`, `id_producto_fk`, `id_tipo_movimiento_fk`, `cantidad`, `usuario_autorizador`) VALUES
(2, 5060, 2, 3, 1, 6),
(3, 5061, 2, 3, 1, 6),
(4, 5062, 2, 3, 1, 1),
(5, 5063, 3, 3, 3, 1),
(6, 5063, 2, 3, 1, 1),
(7, 5063, 8, 3, 2, 1),
(8, 5064, 2, 3, 1, 1),
(9, 5065, 2, 3, 1, 1),
(10, 5066, 2, 3, 1, 1),
(11, 5067, 2, 3, 1, 1),
(12, 5069, 2, 3, 1, 1),
(13, 5071, 2, 3, 1, 1),
(14, 5075, 2, 3, 1, 1),
(15, 5075, 3, 3, 1, 1),
(16, 5076, 2, 2, 1, 1),
(17, 5077, 3, 0, 1, 1),
(18, 5078, 3, 1, 1, 1),
(19, 5080, 3, 3, 1, 1),
(20, 5082, 2, 3, 1, 1),
(21, 5086, 2, 3, 1, 1),
(22, 5087, 2, 3, 1, 1),
(23, 5090, 2, 2, 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_pago`
--

CREATE TABLE `detalle_pago` (
  `id_pago` int(11) NOT NULL,
  `id_transaccion_fk` bigint(20) NOT NULL,
  `id_metodo_fk` int(11) NOT NULL,
  `monto_pago` decimal(10,2) NOT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `conciliado_banco` tinyint(1) NOT NULL DEFAULT 0,
  `referencia_banco` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `detalle_pago`
--

INSERT INTO `detalle_pago` (`id_pago`, `id_transaccion_fk`, `id_metodo_fk`, `monto_pago`, `referencia`, `conciliado_banco`, `referencia_banco`) VALUES
(77, 5040, 2, 2.50, NULL, 0, ''),
(78, 5041, 2, 2.50, NULL, 0, ''),
(79, 5042, 3, 2.50, NULL, 0, '2325'),
(80, 5043, 4, 2.50, NULL, 0, '145687'),
(81, 5044, 1, 2.50, NULL, 0, ''),
(82, 5045, 1, 2.50, NULL, 1, ''),
(83, 5046, 2, 2.50, NULL, 1, ''),
(84, 5047, 4, 2.50, NULL, 0, '23423'),
(85, 5048, 1, 2.50, NULL, 0, ''),
(86, 5049, 2, 2.50, NULL, 0, ''),
(87, 5050, 4, 2.50, NULL, 0, '32423'),
(88, 5051, 4, 2.50, NULL, 0, '4532'),
(89, 5056, 1, 2.50, NULL, 0, ''),
(90, 5057, 2, 2.50, NULL, 1, ''),
(91, 5058, 4, 2.50, NULL, 1, '154568'),
(92, 5059, 2, 2.50, NULL, 1, ''),
(93, 5068, 4, 7.50, NULL, 1, '234232'),
(94, 5070, 2, 2.50, NULL, 1, ''),
(95, 5072, 2, 2.50, NULL, 1, ''),
(97, 5074, 2, 2.50, NULL, 1, ''),
(98, 5079, 2, 12.00, NULL, 1, ''),
(99, 5081, 2, 3.00, NULL, 1, ''),
(100, 5081, 2, 3.88, NULL, 1, ''),
(101, 5083, 2, 3.25, NULL, 0, ''),
(102, 5084, 2, 3.25, NULL, 0, ''),
(103, 5085, 2, 3.25, NULL, 0, ''),
(104, 5088, 2, 3.25, NULL, 0, ''),
(105, 5089, 2, 1.95, NULL, 0, ''),
(106, 5091, 2, 3.25, NULL, 0, '');

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
  `usuario_autorizador` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `detalle_transaccion`
--

INSERT INTO `detalle_transaccion` (`id_detalle`, `id_transaccion_fk`, `id_producto_fk`, `cantidad`, `precio_venta`, `es_egreso_especial`, `motivo`, `usuario_autorizador`) VALUES
(55, 5040, 2, 1, 2.50, 0, NULL, NULL),
(56, 5041, 2, 1, 2.50, 0, NULL, NULL),
(57, 5042, 2, 1, 2.50, 0, NULL, NULL),
(58, 5043, 2, 1, 2.50, 0, NULL, NULL),
(59, 5044, 2, 1, 2.50, 0, NULL, NULL),
(60, 5045, 2, 1, 2.50, 0, NULL, NULL),
(61, 5046, 2, 1, 2.50, 0, NULL, NULL),
(62, 5047, 2, 1, 2.50, 0, NULL, NULL),
(63, 5048, 2, 1, 2.50, 0, NULL, NULL),
(64, 5049, 2, 1, 2.50, 0, NULL, NULL),
(65, 5050, 2, 1, 2.50, 0, NULL, NULL),
(66, 5051, 2, 1, 2.50, 0, NULL, NULL),
(67, 5056, 2, 1, 2.50, 0, NULL, NULL),
(68, 5057, 2, 1, 2.50, 0, NULL, NULL),
(69, 5058, 2, 1, 2.50, 0, NULL, NULL),
(70, 5059, 2, 1, 2.50, 0, NULL, 6),
(71, 5068, 2, 3, 2.50, 0, NULL, NULL),
(72, 5070, 2, 1, 2.50, 0, NULL, NULL),
(73, 5074, 2, 1, 2.50, 0, NULL, 1),
(74, 5079, 8, 1, 12.00, 0, NULL, 1),
(75, 5081, 1, 1, 1.88, 0, NULL, 1),
(76, 5081, 3, 1, 5.00, 0, NULL, 1),
(77, 5083, 2, 1, 3.25, 0, NULL, 1),
(78, 5084, 2, 1, 3.25, 0, NULL, 1),
(79, 5085, 2, 1, 3.25, 0, NULL, 1),
(80, 5088, 2, 1, 3.25, 0, NULL, 1),
(81, 5089, 1, 1, 1.95, 0, NULL, 1),
(82, 5091, 2, 1, 3.25, 0, NULL, 5);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `inventario`
--

INSERT INTO `inventario` (`id_producto`, `nombre_producto`, `stock_actual`, `costo_unitario`, `ultima_venta_fecha`) VALUES
(1, 'Café Americano', 3, 1.50, '2025-12-14'),
(2, 'Bebida Energética X', 56, 2.50, '2025-10-01'),
(3, 'Pan de Jamón', 33, 4.00, '2025-12-13'),
(4, 'Cachito', 8, 30.00, '2025-12-15'),
(6, 'Empanadas', 1, 1.50, '2025-12-21'),
(8, 'arepa', 9, 12.00, '2025-12-22'),
(9, 'Gatorade', 0, 70.00, '2026-01-06');

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
(3, 'Transferencia', 0),
(4, 'Pago Movil Banco Exterior', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `parametros_negocio`
--

CREATE TABLE `parametros_negocio` (
  `id_parametro` int(11) NOT NULL,
  `umbral_tolerancia_efectivo` decimal(10,2) DEFAULT 5.00,
  `umbral_conciliacion_bancaria` decimal(10,2) DEFAULT 2.00,
  `efectivo_requiere_conteo_inicial` tinyint(1) DEFAULT 1,
  `margen_ganancia_estandar` decimal(5,2) DEFAULT 30.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `parametros_negocio`
--

INSERT INTO `parametros_negocio` (`id_parametro`, `umbral_tolerancia_efectivo`, `umbral_conciliacion_bancaria`, `efectivo_requiere_conteo_inicial`, `margen_ganancia_estandar`) VALUES
(1, 0.00, 2.00, 0, 30.00);

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
(2, 'Gerente', 'Auditoría y conciliación financiera (A.2).'),
(3, 'Supervisor', 'Autoriza cierres (A.1) y egresos.'),
(4, 'Cajero', 'Registro de transacciones diarias (D1).');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipo_movimiento`
--

CREATE TABLE `tipo_movimiento` (
  `id_tipo_movimiento` int(11) NOT NULL,
  `nombre_movimiento` varchar(100) NOT NULL,
  `tipo_flujo` enum('Entrada','Salida') NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `tipo_movimiento`
--

INSERT INTO `tipo_movimiento` (`id_tipo_movimiento`, `nombre_movimiento`, `tipo_flujo`, `activo`) VALUES
(0, 'Venta Normal', 'Salida', 0),
(1, 'Producto dañado', 'Salida', 1),
(2, 'Producto vencido', 'Salida', 1),
(3, 'Cortesia', 'Salida', 1),
(4, 'Compra/Entrada de Stock', 'Entrada', 0);

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
  `motivo_egreso` mediumtext DEFAULT NULL,
  `referencia_banco` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `transacciones`
--

INSERT INTO `transacciones` (`id_registro`, `id_usuario_cajero_fk`, `id_jornada_fk`, `fecha_transaccion`, `turno`, `tipo_cobro`, `monto_total`, `es_egreso`, `motivo_egreso`, `referencia_banco`) VALUES
(5040, 1, 8, '2026-01-04 17:46:41', NULL, '', NULL, 0, NULL, NULL),
(5041, 1, 8, '2026-01-04 17:56:25', NULL, '', NULL, 0, NULL, NULL),
(5042, 1, 8, '2026-01-04 17:56:55', NULL, '', NULL, 0, NULL, NULL),
(5043, 1, 8, '2026-01-04 17:58:35', NULL, '', NULL, 0, NULL, NULL),
(5044, 1, 8, '2026-01-04 18:08:18', NULL, '', NULL, 0, NULL, NULL),
(5045, 1, 9, '2026-01-04 18:40:27', NULL, '', NULL, 0, NULL, NULL),
(5046, 1, 9, '2026-01-04 18:40:50', NULL, '', NULL, 0, NULL, NULL),
(5047, 1, 10, '2026-01-04 19:27:14', NULL, '', NULL, 0, NULL, NULL),
(5048, 1, 10, '2026-01-04 19:27:23', NULL, '', NULL, 0, NULL, NULL),
(5049, 1, 10, '2026-01-04 20:14:10', NULL, '', NULL, 0, NULL, NULL),
(5050, 1, 10, '2026-01-04 20:18:14', NULL, '', NULL, 0, NULL, NULL),
(5051, 1, 10, '2026-01-04 20:18:50', NULL, '', NULL, 0, NULL, NULL),
(5055, 1, 14, '2025-01-01 09:52:00', NULL, '', NULL, 0, NULL, NULL),
(5056, 1, 15, '2026-01-05 13:23:13', NULL, '', NULL, 0, NULL, NULL),
(5057, 1, 15, '2026-01-05 13:23:41', NULL, '', NULL, 0, NULL, NULL),
(5058, 1, 15, '2026-01-05 13:23:58', NULL, '', NULL, 0, NULL, NULL),
(5059, 6, 15, '2026-01-05 15:18:11', NULL, '', NULL, 0, NULL, NULL),
(5060, 6, 15, '2026-01-05 15:24:33', NULL, '', NULL, 1, NULL, NULL),
(5061, 6, 15, '2026-01-05 15:24:57', NULL, '', NULL, 1, NULL, NULL),
(5062, 1, 15, '2026-01-05 15:42:12', NULL, '', NULL, 1, NULL, NULL),
(5063, 1, 15, '2026-01-05 15:50:03', NULL, '', NULL, 1, NULL, NULL),
(5064, 1, 15, '2026-01-05 16:08:38', NULL, '', NULL, 1, 'Cortesia', NULL),
(5065, 1, 15, '2026-01-05 16:09:50', NULL, '', NULL, 1, 'Egreso por Vencimiento', NULL),
(5066, 1, 15, '2026-01-05 16:30:43', NULL, '', NULL, 1, NULL, NULL),
(5067, 1, 15, '2026-01-05 16:40:02', NULL, '', NULL, 1, 'Obsequio 6', NULL),
(5068, 1, 15, '2026-01-05 16:46:48', NULL, '', NULL, 0, NULL, NULL),
(5069, 1, 15, '2026-01-05 17:34:52', NULL, '', NULL, 1, 'Un regalo 6', NULL),
(5070, 1, 15, '2026-01-05 17:42:53', NULL, '', NULL, 0, NULL, NULL),
(5071, 1, 15, '2026-01-05 17:44:45', NULL, '', NULL, 1, 'un séptimo regalo', NULL),
(5072, 1, 15, '2026-01-05 17:54:22', NULL, '', NULL, 0, NULL, NULL),
(5074, 1, 15, '2026-01-05 18:06:04', NULL, '', NULL, 0, NULL, NULL),
(5075, 1, 15, '2026-01-05 18:08:02', NULL, '', NULL, 1, 'Un octavo regalo', NULL),
(5076, 1, 15, '2026-01-05 20:10:29', NULL, '', NULL, 1, 'Bebida vencida', NULL),
(5077, 1, 15, '2026-01-05 20:16:14', NULL, '', NULL, 1, 'Se cayo al suelo', NULL),
(5078, 1, 15, '2026-01-05 20:19:09', NULL, '', NULL, 1, 'Se cayo en un tobo', NULL),
(5079, 1, 16, '2026-01-08 14:57:04', NULL, '', NULL, 0, NULL, NULL),
(5080, 1, 16, '2026-01-08 14:58:12', NULL, '', NULL, 1, 'Regalo a proveedores', NULL),
(5081, 1, 16, '2026-01-08 18:09:52', NULL, '', 6.88, 0, NULL, NULL),
(5082, 1, 16, '2026-01-08 23:16:15', NULL, '', NULL, 1, 'Un regalo a proveedor 1', NULL),
(5083, 1, 17, '2026-01-10 12:02:43', NULL, '', 3.25, 0, NULL, NULL),
(5084, 1, 18, '2026-01-10 12:56:30', NULL, '', 3.25, 0, NULL, NULL),
(5085, 1, 21, '2026-01-11 23:47:55', NULL, '', 3.25, 0, NULL, NULL),
(5086, 1, 22, '2026-01-12 00:18:28', NULL, '', NULL, 1, 'regalo', NULL),
(5087, 1, 23, '2026-01-12 00:41:04', NULL, '', NULL, 1, 'regalo', NULL),
(5088, 1, 23, '2026-01-12 01:26:56', NULL, '', 3.25, 0, NULL, NULL),
(5089, 1, 24, '2026-01-12 20:34:56', NULL, '', 1.95, 0, NULL, NULL),
(5090, 1, 24, '2026-01-12 20:44:25', NULL, '', NULL, 1, 'regalo', NULL),
(5091, 5, 24, '2026-01-12 20:48:46', NULL, '', 3.25, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `user_full_name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `id_rol_fk` int(11) NOT NULL,
  `estado` varchar(20) DEFAULT 'Activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `user_full_name`, `username`, `password_hash`, `id_rol_fk`, `estado`, `fecha_creacion`) VALUES
(1, 'Administrador', 'admin', '$2y$10$C5t/lxegrR8dXlMOs9uyG.HxaXXyT5VIPYG2UvaATm0HvtEI5zwsC', 1, 'Activo', '2026-01-04 23:09:20'),
(2, 'Cajero 1', 'cajero1', '$2y$10$h164O2aWY/ryAh5ZiECw.uhIiDiIbrAKj5AWf3EmD.myETAb2Re5q', 4, 'Activo', '2026-01-04 23:09:20'),
(3, 'Cajero 2', 'cajero2', '$2y$10$D5oWYIQAoc75zpDQDfbDZ.WmEopPiL9QETXzMwXad1w/VPc89DeSe', 4, 'Activo', '2026-01-04 23:10:12'),
(4, 'cajero 3', 'cajero3', '$2y$10$qjRneWrsQdOqDRQpGm9tIeZii9kxmGOUcJUHK8s9MsralJzfb6prq', 4, 'Activo', '2026-01-04 23:10:26'),
(5, 'Carlos Herrera', 'cherrera', '$2y$10$PbKHtINFlwVMVN3uWvNtB.rnsviOgaTqfbbBiqFvLb1VGLNFgmp3W', 1, 'Activo', '2026-01-04 23:12:26'),
(6, 'Luis Lopez', 'llopez', '$2y$10$Yo.C0cBihoXkgtFhw6CTD.SukUFfzv8ij/pQpTI7/WdfriPdjHSg.', 3, 'Activo', '2026-01-04 23:24:08'),
(7, 'Gerente', 'gerente1', '$2y$10$RP7LlxhQf62sSuqS.VaeJOFeIEt/gxQl./kUEN.GwK/tvAiOSp2rK', 2, 'Activo', '2026-01-13 02:34:08');

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
  ADD KEY `id_producto_fk` (`id_producto_fk`),
  ADD KEY `fk_detalle_tipo_movimiento` (`id_tipo_movimiento_fk`),
  ADD KEY `fk_detalle_egresos_usuario` (`usuario_autorizador`);

--
-- Indices de la tabla `detalle_pago`
--
ALTER TABLE `detalle_pago`
  ADD PRIMARY KEY (`id_pago`),
  ADD KEY `id_transaccion_fk` (`id_transaccion_fk`),
  ADD KEY `id_metodo_fk` (`id_metodo_fk`);

--
-- Indices de la tabla `detalle_transaccion`
--
ALTER TABLE `detalle_transaccion`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_transaccion_fk` (`id_transaccion_fk`),
  ADD KEY `id_producto_fk` (`id_producto_fk`),
  ADD KEY `fk_detalle_transaccion_usuario` (`usuario_autorizador`);

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
  ADD PRIMARY KEY (`id_registro`),
  ADD KEY `fk_transacciones_cajero` (`id_usuario_cajero_fk`);

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
  MODIFY `id_cierre` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

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
  MODIFY `id_jornada` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT de la tabla `detalle_egresos`
--
ALTER TABLE `detalle_egresos`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de la tabla `detalle_pago`
--
ALTER TABLE `detalle_pago`
  MODIFY `id_pago` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=107;

--
-- AUTO_INCREMENT de la tabla `detalle_transaccion`
--
ALTER TABLE `detalle_transaccion`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT de la tabla `inventario`
--
ALTER TABLE `inventario`
  MODIFY `id_producto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `metodos_pago`
--
ALTER TABLE `metodos_pago`
  MODIFY `id_metodo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `parametros_negocio`
--
ALTER TABLE `parametros_negocio`
  MODIFY `id_parametro` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `roles_y_privilegios`
--
ALTER TABLE `roles_y_privilegios`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `transacciones`
--
ALTER TABLE `transacciones`
  MODIFY `id_registro` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5092;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `detalle_egresos`
--
ALTER TABLE `detalle_egresos`
  ADD CONSTRAINT `detalle_egresos_ibfk_1` FOREIGN KEY (`id_transaccion_fk`) REFERENCES `transacciones` (`id_registro`),
  ADD CONSTRAINT `detalle_egresos_ibfk_2` FOREIGN KEY (`id_producto_fk`) REFERENCES `inventario` (`id_producto`),
  ADD CONSTRAINT `fk_detalle_egresos_usuario` FOREIGN KEY (`usuario_autorizador`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_detalle_tipo_movimiento` FOREIGN KEY (`id_tipo_movimiento_fk`) REFERENCES `tipo_movimiento` (`id_tipo_movimiento`);

--
-- Filtros para la tabla `detalle_pago`
--
ALTER TABLE `detalle_pago`
  ADD CONSTRAINT `detalle_pago_ibfk_1` FOREIGN KEY (`id_transaccion_fk`) REFERENCES `transacciones` (`id_registro`) ON DELETE CASCADE,
  ADD CONSTRAINT `detalle_pago_ibfk_2` FOREIGN KEY (`id_metodo_fk`) REFERENCES `metodos_pago` (`id_metodo`);

--
-- Filtros para la tabla `detalle_transaccion`
--
ALTER TABLE `detalle_transaccion`
  ADD CONSTRAINT `fk_detalle_transaccion_usuario` FOREIGN KEY (`usuario_autorizador`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `transacciones`
--
ALTER TABLE `transacciones`
  ADD CONSTRAINT `fk_transacciones_cajero` FOREIGN KEY (`id_usuario_cajero_fk`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
