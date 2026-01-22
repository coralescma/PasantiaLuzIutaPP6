<?php
// reporte_a2.php - PANEL DE AUDITORÍA INTEGRAL ACTUALIZADO
include 'includes/auth.php'; 
require_login(['Administrador', 'Gerente', 'Supervisor', 'Contador']); 
include 'includes/db_connect.php'; 

$pagina_activa = 'reporte_a2'; 
$id_jornada_consulta = isset($_GET['id_jornada']) ? intval($_GET['id_jornada']) : 0;

// --- 1. LÓGICA: FINALIZAR AUDITORÍA (Cambio de estado a Validada) ---
if (isset($_POST['btn_finalizar_auditoria'])) {
    $id_jor_v = intval($_POST['id_jornada_validar']);
    $stmt = $conn->prepare("UPDATE control_jornadas SET estado_jornada = 'validada' WHERE id_jornada = ?");
    $stmt->bind_param("i", $id_jor_v);
    if ($stmt->execute()) {
        echo "<script>alert('AUDITORÍA CERRADA: La jornada ha sido bloqueada definitivamente.'); window.location.href='reporte_a2.php?id_jornada=$id_jor_v';</script>";
    }
}

// --- 2. LÓGICA: CONCILIACIÓN INDIVIDUAL ---
if (isset($_POST['toggle_conciliacion'])) {
    $id_pago_val = intval($_POST['id_pago']);
    $nuevo_estado = intval($_POST['nuevo_estado']); 
    $id_jor_check = intval($_POST['id_jornada_hidden']);

    $stmt_check = $conn->prepare("SELECT estado_jornada FROM control_jornadas WHERE id_jornada = ?");
    $stmt_check->bind_param("i", $id_jor_check);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result()->fetch_assoc();

    // Solo permitir cambios si la jornada no está validada
    if ($res_check && strtolower(trim($res_check['estado_jornada'])) !== 'validada') {
        $stmt_upd = $conn->prepare("UPDATE detalle_pago SET conciliado_banco = ? WHERE id_pago = ?");
        $stmt_upd->bind_param("ii", $nuevo_estado, $id_pago_val);
        $stmt_upd->execute();
    }
}

// --- 3. OBTENCIÓN DE DATOS DE JORNADAS ---
$sql_jornadas = "SELECT id_jornada, fecha_apertura, estado_jornada FROM control_jornadas ORDER BY id_jornada DESC LIMIT 30";
$lista_jornadas = $conn->query($sql_jornadas);

$estado_actual = '';
if ($id_jornada_consulta > 0) {
    $stmt_est = $conn->prepare("SELECT estado_jornada FROM control_jornadas WHERE id_jornada = ?");
    $stmt_est->bind_param("i", $id_jornada_consulta);
    $stmt_est->execute();
    if ($f = $stmt_est->get_result()->fetch_assoc()) {
        $estado_actual = strtolower(trim($f['estado_jornada']));
    }
}
$bloquear = ($estado_actual === 'validada');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte A.2 - Auditoría de Pagos</title>
    <link rel="stylesheet" href="css/style.css"> 
    <style>
        :root { --primary: #2563eb; --success: #059669; --danger: #dc2626; --warning: #d97706; }
        .main-wrapper { padding: 20px; max-width: 1200px; margin: 0 auto; font-family: 'Inter', system-ui, sans-serif; }
        .card-a2 { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); border-top: 6px solid var(--primary); }
        .header-report { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 20px; margin-bottom: 25px; }
        
        .banner { padding: 16px; border-radius: 8px; margin-bottom: 25px; display: flex; align-items: center; gap: 12px; font-weight: 500; border: 1px solid; }
        .banner-open { background: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
        .banner-closed { background: #fffbeb; color: #92400e; border-color: #fde68a; }
        .banner-validated { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }

        .resumen-auditoria { background: #f8fafc; padding: 25px; border-radius: 12px; margin-bottom: 30px; border: 1px solid #e2e8f0; }
        .grid-resumen { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; }
        .dato-box { text-align: center; padding: 10px; border-right: 1px solid #e2e8f0; }
        .dato-box:last-child { border-right: none; }
        .dato-label { color: #64748b; font-size: 0.85rem; text-transform: uppercase; margin-bottom: 8px; display: block; }
        .dato-val { font-size: 1.6rem; font-weight: 800; color: #1e293b; }

        .table-custom { width: 100%; border-collapse: collapse; }
        .table-custom th { background: #f8fafc; padding: 14px; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
        .table-custom td { padding: 16px 14px; border-bottom: 1px solid #f1f5f9; }
        
        .badge { padding: 4px 8px; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; }
        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-danger { background: #fee2e2; color: #b91c1c; }
        .badge-neutral { background: #f1f5f9; color: #475569; }

        .btn-toggle { padding: 6px 12px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; color: white; transition: 0.2s; }
        .btn-finalizar { background: var(--success); color: white; padding: 12px 24px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; }
    </style>
</head>
<body>
    <?php include 'includes/menu.php'; ?>

    <div class="main-wrapper">
        <div class="card-a2">
            <header class="header-report">
                <div>
                    <h1 style="margin:0; color: #0f172a; font-size: 1.5rem;">AUDITORÍA A.2 - CONTROL TOTAL DE PAGOS</h1>
                    <p style="margin:4px 0 0; color: #64748b;">Verificación de ingresos y salidas de inventario</p>
                </div>
                <?php if (in_array($estado_actual, ['cerrado', 'cerrada'])): ?>
                    <form method="POST" onsubmit="return confirm('¿BLOQUEAR AUDITORÍA? No podrá revertir este proceso.');">
                        <input type="hidden" name="id_jornada_validar" value="<?php echo $id_jornada_consulta; ?>">
                        <button type="submit" name="btn_finalizar_auditoria" class="btn-finalizar">🔒 FINALIZAR AUDITORÍA</button>
                    </form>
                <?php endif; ?>
            </header>

            <div style="background: #ffffff; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 25px;">
                <form method="GET" style="display: flex; align-items: center; gap: 15px;">
                    <label style="font-weight: 600; color: #475569;">Jornada a Auditar:</label>
                    <select name="id_jornada" onchange="this.form.submit()" style="padding: 10px; width: 350px; border-radius: 6px; border: 1px solid #cbd5e1;">
                        <option value="0">-- Seleccione --</option>
                        <?php while($j = $lista_jornadas->fetch_assoc()): ?>
                            <option value="<?php echo $j['id_jornada']; ?>" <?php echo ($id_jornada_consulta == $j['id_jornada']) ? 'selected' : ''; ?>>
                                Jornada #<?php echo $j['id_jornada']; ?> - <?php echo $j['fecha_apertura']; ?> [<?php echo strtoupper($j['estado_jornada']); ?>]
                            </option>
                        <?php endwhile; ?>
                    </select>

                    <?php if ($id_jornada_consulta > 0): ?>
                        <a href="reporte_ventas_pdf.php?id_jornada=<?php echo $id_jornada_consulta; ?>" 
                           target="_blank" 
                           style="background: #475569; color: white; padding: 12px 20px; border-radius: 8px; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 8px;">
                           📄 GENERAR PDF
                        </a>
                    <?php else: ?>
                        <span style="background: #e2e8f0; color: #94a3b8; padding: 12px 20px; border-radius: 8px; font-weight: 700; cursor: not-allowed; display: flex; align-items: center; gap: 8px;" title="Seleccione una jornada primero">
                            📄 GENERAR PDF
                        </span>
                    <?php endif; ?>
                </form>
            </div>

            <?php
            if ($id_jornada_consulta > 0): 
                // 1. Total Ingresos (Ventas)
                $res_total = $conn->query("SELECT SUM(dp.monto_pago) as total FROM detalle_pago dp JOIN transacciones t ON dp.id_transaccion_fk = t.id_registro WHERE t.id_jornada_fk = $id_jornada_consulta")->fetch_assoc();
                
                // 2. Lógica de Egresos (Basada en reporte_a1.php)
                $sql_egresos_a2 = "SELECT t.id_registro AS id_trans, u.user_full_name AS cajero, i.nombre_producto, de.cantidad, tm.nombre_movimiento AS motivo, t.motivo_egreso AS comentario, (de.cantidad * i.costo_unitario) AS costo_asociado FROM detalle_egresos de JOIN transacciones t ON de.id_transaccion_fk = t.id_registro JOIN inventario i ON de.id_producto_fk = i.id_producto JOIN usuarios u ON t.id_usuario_cajero_fk = u.id_usuario LEFT JOIN tipo_movimiento tm ON de.id_tipo_movimiento_fk = tm.id_tipo_movimiento WHERE t.id_jornada_fk = $id_jornada_consulta AND t.es_egreso = 1";
                $res_egresos_a2 = $conn->query($sql_egresos_a2);
                $total_costo_egresos_a2 = 0;
                $egresos_lista = [];
                if ($res_egresos_a2) {
                    while($row = $res_egresos_a2->fetch_assoc()) {
                        $egresos_lista[] = $row;
                        $total_costo_egresos_a2 += $row['costo_asociado'];
                    }
                }

                // 3. Total Conciliado
                $res_conc = $conn->query("SELECT SUM(dp.monto_pago) as total FROM detalle_pago dp JOIN transacciones t ON dp.id_transaccion_fk = t.id_registro WHERE t.id_jornada_fk = $id_jornada_consulta AND dp.conciliado_banco = 1")->fetch_assoc();
                
                $monto_total_sistema = $res_total['total'] ?? 0;
                $monto_auditado = $res_conc['total'] ?? 0;
                $pendiente = $monto_total_sistema - $monto_auditado;
            ?>

                <div class="resumen-auditoria">
                    <div class="grid-resumen">
                        <div class="dato-box">
                            <span class="dato-label">Total Ingresos</span>
                            <span class="dato-val">Bs<?php echo number_format($monto_total_sistema, 2); ?></span>
                        </div>
                        <div class="dato-box">
                            <span class="dato-label">Monto Verificado</span>
                            <span class="dato-val" style="color: var(--success);">Bs<?php echo number_format($monto_auditado, 2); ?></span>
                        </div>
                        <div class="dato-box">
                            <span class="dato-label">Pendiente Revisar</span>
                            <span class="dato-val" style="color: <?php echo ($pendiente > 0) ? 'var(--danger)' : 'var(--success)'; ?>">
                                Bs<?php echo number_format($pendiente, 2); ?>
                            </span>
                        </div>
                        <div class="dato-box">
                            <span class="dato-label">Pérdida Inventario</span>
                            <span class="dato-val" style="color: var(--danger); font-size: 1.3rem;">Bs<?php echo number_format($total_costo_egresos_a2, 2); ?></span>
                        </div>
                    </div>
                </div>

                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>ID Trans.</th>
                            <th>Cajero</th>
                            <th>Método</th>
                            <th>Monto</th>
                            <th>Referencia / Nota</th>
                            <th>Estado Auditoría</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql_det = "SELECT dp.*, m.nombre_metodo, u.user_full_name FROM detalle_pago dp JOIN transacciones t ON dp.id_transaccion_fk = t.id_registro JOIN metodos_pago m ON dp.id_metodo_fk = m.id_metodo LEFT JOIN usuarios u ON t.id_usuario_cajero_fk = u.id_usuario WHERE t.id_jornada_fk = $id_jornada_consulta ORDER BY m.nombre_metodo ASC";
                        $res_det = $conn->query($sql_det);
                        while($p = $res_det->fetch_assoc()): 
                            $es_efectivo = (strtolower($p['nombre_metodo']) == 'efectivo');
                        ?>
                            <tr style="<?php echo $es_efectivo ? 'background-color: #fffaf0;' : ''; ?>">
                                <td>#<?php echo $p['id_transaccion_fk']; ?></td>
                                <td><span style="font-size: 0.85rem; font-weight: 500; color: #475569;"><?php echo htmlspecialchars($p['user_full_name'] ?? 'N/A'); ?></span></td>
                                <td><strong><?php echo $p['nombre_metodo']; ?></strong><?php if($es_efectivo): ?> <span style="font-size: 10px; color: #d97706;">(CAJA)</span> <?php endif; ?></td>
                                <td style="font-weight: 700;">Bs<?php echo number_format($p['monto_pago'], 2); ?></td>
                                <td>
                                    <?php if ($es_efectivo): ?>
                                        <span class="badge badge-neutral">Cobro Manual</span>
                                    <?php else: ?>
                                        <code style="background: #f1f5f9; padding: 4px;"><?php echo $p['referencia_banco'] ?: 'Sin Ref.'; ?></code>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($p['conciliado_banco']): ?>
                                        <span class="badge badge-success">✓ REVISADO</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">● PENDIENTE</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$bloquear): ?>
                                        <form method="POST">
                                            <input type="hidden" name="id_pago" value="<?php echo $p['id_pago']; ?>">
                                            <input type="hidden" name="id_jornada_hidden" value="<?php echo $id_jornada_consulta; ?>">
                                            <input type="hidden" name="nuevo_estado" value="<?php echo $p['conciliado_banco'] ? '0' : '1'; ?>">
                                            <button type="submit" name="toggle_conciliacion" class="btn-toggle" style="background: <?php echo $p['conciliado_banco'] ? '#64748b' : 'var(--primary)'; ?>;">
                                                <?php echo $p['conciliado_banco'] ? 'Desmarcar' : 'Validar'; ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-size: 0.8rem;">🔒 Protegido</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <h3 style="margin-top: 40px; color: #1e293b; border-left: 4px solid var(--danger); padding-left: 10px;">
                    📉 DETALLE DE EGRESOS DE INVENTARIO (Salidas Especiales)
                </h3>
                <table class="table-custom" style="border-top: 2px solid var(--danger);">
                    <thead>
                        <tr style="background: #fff5f5;">
                            <th>ID Trans.</th>
                            <th>Cajero</th>
                            <th>Producto</th>
                            <th>Cant.</th>
                            <th>Costo Total</th>
                            <th>Motivo / Comentario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($egresos_lista)): ?>
                            <?php foreach ($egresos_lista as $e): ?>
                                <tr>
                                    <td>#<?php echo $e['id_trans']; ?></td>
                                    <td><small><?php echo htmlspecialchars($e['cajero']); ?></small></td>
                                    <td><strong><?php echo htmlspecialchars($e['nombre_producto']); ?></strong></td>
                                    <td align="center"><?php echo $e['cantidad']; ?></td>
                                    <td style="color: var(--danger); font-weight: bold;">
                                        -Bs<?php echo number_format($e['costo_asociado'], 2); ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-neutral"><?php echo htmlspecialchars($e['motivo'] ?? 'Salida'); ?></span>
                                        <div style="font-size: 0.75rem; color: #64748b; margin-top: 4px;">
                                            <i><?php echo htmlspecialchars($e['comentario'] ?: 'Sin observaciones'); ?></i>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr style="background: #f8fafc; font-weight: bold;">
                                <td colspan="4" align="right">TOTAL PÉRDIDA EN ESTA JORNADA:</td>
                                <td style="color: var(--danger);">Bs<?php echo number_format($total_costo_egresos_a2, 2); ?></td>
                                <td></td>
                            </tr>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align: center; padding: 30px; color: #94a3b8;">No hay egresos registrados en esta jornada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
            <?php endif; ?>
        </div>
    </div>
</body>
</html>