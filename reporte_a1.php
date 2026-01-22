<?php
// reporte_a1.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'includes/auth.php'; 
require_login(['Administrador', 'Gerente', 'Supervisor', 'Contador']); 
include 'includes/db_connect.php'; 

$pagina_activa = 'reporte_a1'; 

// --- 1. Obtener lista de jornadas para el selector ---
$sql_jornadas = "SELECT id_jornada, fecha_apertura, estado_jornada FROM control_jornadas ORDER BY id_jornada DESC";
$lista_jornadas = $conn->query($sql_jornadas);

// --- 2. Determinar jornada a consultar ---
$id_jornada_consulta = isset($_GET['id_jornada']) ? intval($_GET['id_jornada']) : 0;

if ($id_jornada_consulta === 0) {
    // Función asumida en tu sistema para obtener la jornada actual
    if (function_exists('obtenerJornadaActiva')) {
        $id_jornada_consulta = obtenerJornadaActiva($conn);
    }
}

$datos_jornada = null;
$total_registrado_efectivo = 0;
$total_otros_metodos = 0;
$total_costo_egresos = 0;

if ($id_jornada_consulta) {
    // Obtener datos maestros de la jornada
    $sql_master = "SELECT * FROM control_jornadas WHERE id_jornada = $id_jornada_consulta";
    $res_master = $conn->query($sql_master);
    $datos_jornada = ($res_master) ? $res_master->fetch_assoc() : null;

    if ($datos_jornada) {
        // --- 3. CONCILIACIÓN DE VENTAS (Suma desde detalle_pago) ---
        $sql_ventas = "SELECT mp.nombre_metodo, SUM(dp.monto_pago) AS total_por_metodo
                       FROM detalle_pago dp
                       JOIN transacciones t ON dp.id_transaccion_fk = t.id_registro
                       JOIN metodos_pago mp ON dp.id_metodo_fk = mp.id_metodo
                       WHERE t.id_jornada_fk = $id_jornada_consulta 
                       AND t.es_egreso = 0
                       GROUP BY mp.nombre_metodo";
        
        $res_ventas = $conn->query($sql_ventas);
        $desglose_pagos = [];
        
        while ($row = $res_ventas->fetch_assoc()) {
            $desglose_pagos[] = $row;
            if ($row['nombre_metodo'] == 'Efectivo') {
                $total_registrado_efectivo = $row['total_por_metodo'];
            } else {
                $total_otros_metodos += $row['total_por_metodo'];
            }
        }

        // --- 4 y 5. LÓGICA DE EGRESOS DE INVENTARIO (Salidas Especiales) ---
        $sql_egresos = "SELECT 
            t.id_registro AS id_trans,
            u.user_full_name AS cajero,
            i.nombre_producto, 
            de.cantidad, 
            tm.nombre_movimiento AS motivo, 
            t.motivo_egreso AS comentario,
            (de.cantidad * i.costo_unitario) AS costo_asociado 
        FROM detalle_egresos de
        JOIN transacciones t ON de.id_transaccion_fk = t.id_registro
        JOIN inventario i ON de.id_producto_fk = i.id_producto
        JOIN usuarios u ON t.id_usuario_cajero_fk = u.id_usuario
        LEFT JOIN tipo_movimiento tm ON de.id_tipo_movimiento_fk = tm.id_tipo_movimiento
        WHERE t.id_jornada_fk = $id_jornada_consulta 
        AND t.es_egreso = 1
        ORDER BY t.id_registro DESC";

        $res_egresos = $conn->query($sql_egresos);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte A.1 - Cierre Preliminar</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .selector-jornada { background: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e2e8f0; }
        .grid-resumen { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .card { background: white; padding: 15px; border-radius: 8px; border: 1px solid #eee; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .alerta-roja { color: #dc2626; background: #fee2e2; padding: 5px; border-radius: 4px; }
        .alerta-verde { color: #16a34a; }
        .metodo-item { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px dashed #ccc; }
    </style>
</head>
<body>
    <?php include 'includes/menu.php'; ?>
    
    <div class="report-container">
        <header class="report-header">
            <h1>REPORTE A.1: CIERRE POR JORNADA</h1>
            <div class="selector-jornada">
                <form method="GET" action="reporte_a1.php">
                    <label><strong>Seleccionar Jornada:</strong></label>
                    <select name="id_jornada" onchange="this.form.submit()">
                        <option value="">-- Seleccione --</option>
                        <?php while($j = $lista_jornadas->fetch_assoc()): ?>
                            <option value="<?php echo $j['id_jornada']; ?>" <?php echo ($id_jornada_consulta == $j['id_jornada']) ? 'selected' : ''; ?>>
                                Jornada #<?php echo $j['id_jornada']; ?> (<?php echo $j['fecha_apertura']; ?>) - <?php echo $j['estado_jornada']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </form>
            </div>
        </header>

        <?php if ($datos_jornada): 
            $total_esperado_caja = $datos_jornada['monto_apertura'] + $total_registrado_efectivo;
            $diferencia = ($datos_jornada['monto_cierre_real'] ?? 0) - $total_esperado_caja;
            $clase_alerta = (abs($diferencia) > 5) ? 'alerta-roja' : 'alerta-verde';
        ?>
            
            <div class="grid-resumen">
                <div class="card">
                    <h3>💰 Cuadre de Caja (Efectivo)</h3>
                    <table style="width: 100%;">
                        <tr><td>Monto Apertura:</td><td align="right">Bs <?php echo number_format($datos_jornada['monto_apertura'], 2); ?></td></tr>
                        <tr><td>Ventas Efectivo:</td><td align="right">Bs <?php echo number_format($total_registrado_efectivo, 2); ?></td></tr>
                        <tr style="border-top: 2px solid #000;">
                            <td><strong>Total Esperado:</strong></td>
                            <td align="right"><strong>Bs <?php echo number_format($total_esperado_caja, 2); ?></strong></td>
                        </tr>
                        <tr><td>Cierre Real (Físico):</td><td align="right">Bs <?php echo number_format($datos_jornada['monto_cierre_real'] ?? 0, 2); ?></td></tr>
                        <tr>
                            <td><strong>Diferencia:</strong></td>
                            <td align="right" class="<?php echo $clase_alerta; ?>"><strong>Bs <?php echo number_format($diferencia, 2); ?></strong></td>
                        </tr>
                    </table>
                </div>

                <div class="card">
                    <h3>💳 Otros Métodos de Pago</h3>
                    <?php if (!empty($desglose_pagos)): ?>
                        <?php foreach($desglose_pagos as $pago): 
                            if($pago['nombre_metodo'] == 'Efectivo') continue; ?>
                            <div class="metodo-item">
                                <span><?php echo $pago['nombre_metodo']; ?>:</span>
                                <strong>Bs <?php echo number_format($pago['total_por_metodo'], 2); ?></strong>
                            </div>
                        <?php endforeach; ?>
                        <div class="metodo-item" style="margin-top:10px; border-top: 1px solid #000;">
                            <span><strong>TOTAL BANCOS:</strong></span>
                            <strong>Bs <?php echo number_format($total_otros_metodos, 2); ?></strong>
                        </div>
                    <?php else: ?>
                        <p>No hay registros de otros métodos.</p>
                    <?php endif; ?>
                </div>
            </div>

            <section class="detalle-egresos">
                <?php if ($res_egresos && $res_egresos->num_rows > 0): 
                    $total_costo_egresos = 0;
                    $egresos_agrupados = [];

                    while($fila = $res_egresos->fetch_assoc()) {
                        $egresos_agrupados[$fila['id_trans']]['info'] = [
                            'cajero' => $fila['cajero'],
                            'motivo' => $fila['motivo'],
                            'comentario' => $fila['comentario']
                        ];
                        $egresos_agrupados[$fila['id_trans']]['items'][] = [
                            'producto' => $fila['nombre_producto'],
                            'cantidad' => $fila['cantidad'],
                            'costo' => $fila['costo_asociado']
                        ];
                        $total_costo_egresos += $fila['costo_asociado'];
                    }
                ?>

                <h3>📉 Egresos de Inventario (Salidas Especiales)</h3>

                <div class="contenedor-tarjetas" style="display: flex; flex-direction: column; gap: 15px;">
                    <?php foreach ($egresos_agrupados as $id_trans => $datos): ?>
                        <div class="card-egreso" style="border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                            <div class="card-header" style="background: #f8fafc; padding: 10px 15px; border-bottom: 1px solid #cbd5e1; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <span style="background: #334155; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.8em; margin-right: 10px;">#<?php echo $id_trans; ?></span>
                                    <strong>Cajero:</strong> <?php echo htmlspecialchars($datos['info']['cajero']); ?>
                                </div>
                                <span class="badge-info" style="background: #dbeafe; color: #1e40af; padding: 4px 10px; border-radius: 12px; font-size: 0.85em; font-weight: bold;">
                                    <?php echo htmlspecialchars($datos['info']['motivo'] ?? 'Salida'); ?>
                                </span>
                            </div>

                            <div class="card-body" style="padding: 10px 15px;">
                                <table style="width: 100%; border-collapse: collapse; font-size: 0.95em;">
                                    <thead>
                                        <tr style="color: #64748b; border-bottom: 1px solid #f1f5f9; text-align: left;">
                                            <th style="padding: 5px 0;">Producto</th>
                                            <th style="padding: 5px 0; text-align: center;">Cant.</th>
                                            <th style="padding: 5px 0; text-align: right;">Costo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($datos['items'] as $item): ?>
                                            <tr style="border-bottom: 1px dotted #f1f5f9;">
                                                <td style="padding: 8px 0;"><?php echo htmlspecialchars($item['producto']); ?></td>
                                                <td style="padding: 8px 0; text-align: center;"><?php echo $item['cantidad']; ?></td>
                                                <td style="padding: 8px 0; text-align: right;">Bs <?php echo number_format($item['costo'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="card-footer" style="padding: 10px 15px; background: #fff; border-top: 1px solid #f1f5f9;">
                                <p style="margin: 0; font-size: 0.85em; color: #475569;">
                                    <strong>Comentario:</strong> <i><?php echo htmlspecialchars($datos['info']['comentario'] ?: 'Sin observaciones'); ?></i>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top: 20px; text-align: right; padding: 15px; background: #f1f5f9; border-radius: 8px;">
                    <span style="font-size: 1.1em;">TOTAL PÉRDIDA EN INVENTARIO: <strong style="color: #be123c;">Bs <?php echo number_format($total_costo_egresos, 2); ?></strong></span>
                </div>

                <?php else: ?>
                    <p style="color: #666; text-align: center; padding: 20px;">No se registraron egresos especiales en esta jornada.</p>
                <?php endif; ?>
            </section>

            <section class="cierre-final" style="margin-top:20px;">
                <p><strong>Observaciones:</strong> <?php echo htmlspecialchars($datos_jornada['observaciones'] ?? 'Sin observaciones'); ?></p>
            </section>
        <?php else: ?>
            <div class="card" style="text-align:center; color: #666;">
                <h3>Seleccione una jornada para ver el reporte preliminar.</h3>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>