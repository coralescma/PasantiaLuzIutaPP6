<?php
// reporte_a2.php - PANEL DE AUDITORÍA Y VALIDACIÓN BANCARIA (V.4.5)
include 'includes/auth.php'; 
require_login(['Administrador', 'Contador']); 
include 'includes/db_connect.php'; 

$pagina_activa = 'reporte_a2'; 

// --- 1. LÓGICA DEL INTERRUPTOR (0 o 1) ---
if (isset($_POST['toggle_conciliacion'])) {
    $id_pago_val = intval($_POST['id_pago']);
    $nuevo_estado = intval($_POST['nuevo_estado']); 
    
    $sql_update = "UPDATE detalle_pago SET conciliado_banco = $nuevo_estado WHERE id_pago = $id_pago_val";
    $conn->query($sql_update);
}

// --- 2. SELECTOR DE JORNADAS ---
$sql_jornadas = "SELECT id_jornada, fecha_apertura FROM control_jornadas ORDER BY id_jornada DESC";
$lista_jornadas = $conn->query($sql_jornadas);
$id_jornada_consulta = isset($_GET['id_jornada']) ? intval($_GET['id_jornada']) : 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Auditoría Bancaria - A2</title>
    <link rel="stylesheet" href="assets/css/estilos.css">
    <style>
        :root { --primary: #2563eb; --success: #10b981; --danger: #ef4444; --slate: #64748b; }
        body { background: #f1f5f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; }
        .container { padding: 30px; max-width: 1100px; margin: auto; }
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .header { margin-bottom: 25px; border-left: 5px solid var(--primary); padding-left: 20px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #f8fafc; color: var(--slate); text-align: left; padding: 15px; border-bottom: 2px solid #e2e8f0; font-size: 0.9em; }
        td { padding: 15px; border-bottom: 1px solid #e2e8f0; color: #1e293b; }
        
        .badge { padding: 6px 12px; border-radius: 50px; font-size: 0.75em; font-weight: bold; display: inline-block; }
        .badge-pendiente { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .badge-ok { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        
        .btn-toggle { border: none; padding: 10px 16px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.85em; transition: 0.2s; }
        .btn-check { background: var(--success); color: white; }
        .btn-undo { background: var(--slate); color: white; }
        .btn-toggle:hover { filter: brightness(1.1); transform: translateY(-1px); }
        
        .selector-box { margin-bottom: 25px; display: flex; align-items: center; gap: 15px; background: #fff; padding: 15px; border-radius: 10px; }
        select { padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; min-width: 250px; font-size: 1em; }
    </style>
</head>
<body>

    <div class="container">
        <div class="header">
            <h1 style="margin:0; color: #1e293b;">Reporte A.2: Auditoría de Pagos</h1>
            <p style="margin:5px 0 0; color: var(--slate);">Validación de transferencias y tarjetas (Interruptor de Banco)</p>
        </div>

        <div class="selector-box card">
            <strong>Seleccionar Jornada:</strong>
            <form method="GET" action="">
                <select name="id_jornada" onchange="this.form.submit()">
                    <option value="">--- Seleccione una Jornada ---</option>
                    <?php while($j = $lista_jornadas->fetch_assoc()): ?>
                        <option value="<?php echo $j['id_jornada']; ?>" <?php echo ($id_jornada_consulta == $j['id_jornada']) ? 'selected' : ''; ?>>
                            Jornada #<?php echo $j['id_jornada']; ?> (<?php echo date('d/m/Y', strtotime($j['fecha_apertura'])); ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </form>
        </div>

        <?php if ($id_jornada_consulta > 0): ?>
            <div class="card">
                <?php
                // CONSULTA AJUSTADA A pmv (5).sql
                // Filtramos métodos 2 (Tarjeta) y 3 (Transferencia)
                $sql_auditoria = "SELECT dp.id_pago, dp.monto_pago, dp.conciliado_banco, 
                                         mp.nombre_metodo, t.fecha_transaccion, t.id_registro as ref_venta
                                  FROM detalle_pago dp
                                  JOIN metodos_pago mp ON dp.id_metodo_fk = mp.id_metodo
                                  JOIN transacciones t ON dp.id_transaccion_fk = t.id_registro
                                  WHERE t.id_jornada_fk = $id_jornada_consulta 
                                  AND mp.id_metodo IN (2, 3) 
                                  ORDER BY t.id_registro DESC";
                
                $res_auditoria = $conn->query($sql_auditoria);

                if ($res_auditoria && $res_auditoria->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID Venta</th>
                                <th>Fecha Transacción</th>
                                <th>Método de Pago</th>
                                <th>Monto</th>
                                <th>Estado Auditoría</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $res_auditoria->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?php echo $row['ref_venta']; ?></strong></td>
                                    <td><?php echo date('H:i:s', strtotime($row['fecha_transaccion'])); ?></td>
                                    <td><span style="color:var(--primary); font-weight:bold;"><?php echo $row['nombre_metodo']; ?></span></td>
                                    <td><strong>$<?php echo number_format($row['monto_pago'], 2); ?></strong></td>
                                    <td>
                                        <?php if($row['conciliado_banco'] == 1): ?>
                                            <span class="badge badge-ok">✓ CONCILIADO EN BANCO</span>
                                        <?php else: ?>
                                            <span class="badge badge-pendiente">● PENDIENTE DE REVISIÓN</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" style="margin:0;">
                                            <input type="hidden" name="id_pago" value="<?php echo $row['id_pago']; ?>">
                                            <?php if($row['conciliado_banco'] == 0): ?>
                                                <input type="hidden" name="nuevo_estado" value="1">
                                                <button type="submit" name="toggle_conciliacion" class="btn-toggle btn-check">Confirmar Banco</button>
                                            <?php else: ?>
                                                <input type="hidden" name="nuevo_estado" value="0">
                                                <button type="submit" name="toggle_conciliacion" class="btn-toggle btn-undo">Deshacer</button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align:center; padding: 50px; color: var(--slate);">
                        <img src="https://cdn-icons-png.flaticon.com/512/2748/2748614.png" width="60" style="opacity: 0.2; margin-bottom: 15px;"><br>
                        No se encontraron pagos con Tarjeta o Transferencia en esta jornada.
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div style="text-align:center; padding: 100px; color: var(--slate);">
                <h3 style="font-weight: 400;">Seleccione una jornada arriba para auditar los pagos electrónicos.</h3>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>