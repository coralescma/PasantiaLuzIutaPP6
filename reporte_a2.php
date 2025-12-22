<?php
// reporte_a2.php - PANEL DE AUDITORÍA Y VALIDACIÓN BANCARIA
include 'includes/auth.php'; 
require_login(['Administrador', 'Contador']); 
include 'includes/db_connect.php'; 

$pagina_activa = 'reporte_a2'; 

// --- 1. LÓGICA PARA CAMBIAR ESTADO A "VALIDADA" (CERRAR AUDITORÍA) ---
if (isset($_POST['btn_finalizar_auditoria'])) {
    $id_jor_v = intval($_POST['id_jornada_validar']);
    // Término administrativo: Pasamos de 'Cerrada' a 'Validada'
    $sql_v = "UPDATE control_jornadas SET estado_jornada = 'validada' WHERE id_jornada = $id_jor_v";
    if ($conn->query($sql_v)) {
        echo "<script>alert('Auditoría Cerrada: La jornada ha sido bloqueada para modificaciones.'); window.location.href='reporte_a2.php?id_jornada=$id_jor_v';</script>";
    }
}

// --- 2. LÓGICA DE ACTUALIZACIÓN DE CONCILIACIÓN ---
if (isset($_POST['toggle_conciliacion'])) {
    $id_pago_val = intval($_POST['id_pago']);
    $nuevo_estado = intval($_POST['nuevo_estado']); 
    $id_jor_check = intval($_POST['id_jornada_hidden']);

    $check = $conn->query("SELECT estado_jornada FROM control_jornadas WHERE id_jornada = $id_jor_check")->fetch_assoc();
    if (strtolower($check['estado_jornada']) !== 'validada') {
        $sql_update = "UPDATE detalle_pago SET conciliado_banco = $nuevo_estado WHERE id_pago = $id_pago_val";
        $conn->query($sql_update);
    }
}

// --- 3. SELECTOR DE JORNADAS ---
$sql_jornadas = "SELECT id_jornada, fecha_apertura, estado_jornada FROM control_jornadas ORDER BY id_jornada DESC";
$lista_jornadas = $conn->query($sql_jornadas);

$id_jornada_consulta = isset($_GET['id_jornada']) ? intval($_GET['id_jornada']) : 0;

$estado_actual = '';
if ($id_jornada_consulta > 0) {
    $res_est = $conn->query("SELECT estado_jornada FROM control_jornadas WHERE id_jornada = $id_jornada_consulta");
    if ($f = $res_est->fetch_assoc()) {
        $estado_actual = strtolower(trim($f['estado_jornada']));
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SCL - Auditoría Bancaria</title>
    <link rel="stylesheet" href="css/style.css"> 
    <style>
        .main-wrapper { padding: 20px; max-width: 1200px; margin: 0 auto; font-family: 'Segoe UI', sans-serif; }
        .card-a2 { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        
        /* Banners */
        .banner { padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid; display: flex; align-items: center; gap: 10px; }
        .banner-open { background: #e0f2fe; color: #0369a1; border-color: #7dd3fc; }
        .banner-closed { background: #fff7ed; color: #9a3412; border-color: #fdba74; }
        .banner-validated { background: #d1fae5; color: #064e3b; border-color: #6ee7b7; }

        /* Botones */
        .btn-finalizar { background: #10b981; color: white; padding: 12px 20px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 0.95rem; }
        .btn-finalizar:hover { background: #059669; }
        .btn-action { padding: 8px 12px; border-radius: 4px; border: none; font-weight: bold; cursor: pointer; }
        
        .table-custom { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table-custom th { text-align: left; padding: 12px; background: #f8fafc; border-bottom: 2px solid #cbd5e1; }
        .table-custom td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.85em; font-weight: bold; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

    <?php include 'includes/menu.php'; ?>

    <div class="main-wrapper">
        <div class="card-a2">
            <div class="header-flex">
                <h1>🔍 Auditoría de Movimientos</h1>
                
                <?php if ($estado_actual == 'cerrada' || $estado_actual == 'cerrado'): ?>
                    <form method="POST" onsubmit="return confirm('¿Confirma el CIERRE DE AUDITORÍA? Esto bloqueará los registros permanentemente.');">
                        <input type="hidden" name="id_jornada_validar" value="<?php echo $id_jornada_consulta; ?>">
                        <button type="submit" name="btn_finalizar_auditoria" class="btn-finalizar">
                            🔒 Cerrar Auditoría
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            
            <div style="margin-bottom: 25px; padding: 15px; background: #f1f5f9; border-radius: 8px;">
                <form method="GET">
                    <strong>Jornada:</strong>
                    <select name="id_jornada" onchange="this.form.submit()" style="padding: 10px; width: 320px; border-radius: 5px; border: 1px solid #ccc;">
                        <option value="0">-- Seleccione Jornada --</option>
                        <?php $lista_jornadas->data_seek(0); while($j = $lista_jornadas->fetch_assoc()): ?>
                            <option value="<?php echo $j['id_jornada']; ?>" <?php echo ($id_jornada_consulta == $j['id_jornada']) ? 'selected' : ''; ?>>
                                #<?php echo $j['id_jornada']; ?> - <?php echo $j['fecha_apertura']; ?> (<?php echo strtoupper($j['estado_jornada']); ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </form>
            </div>

            <?php if ($id_jornada_consulta > 0): ?>
                
                <?php 
                $bloquear = false;
                if ($estado_actual == 'abierta' || $estado_actual == 'abierto'): ?>
                    <div class="banner banner-open">
                        <span>ℹ️</span>
                        <div><strong>Jornada Abierta:</strong> Auditoría preliminar permitida. El botón de Cerrar Auditoría aparecerá cuando la caja sea cerrada.</div>
                    </div>
                <?php elseif ($estado_actual == 'cerrada' || $estado_actual == 'cerrado'): ?>
                    <div class="banner banner-closed">
                        <span>⚠️</span>
                        <div><strong>Jornada Cerrada:</strong> Verifique los depósitos bancarios. Al finalizar, presione el botón superior <strong>"Cerrar Auditoría"</strong>.</div>
                    </div>
                <?php elseif ($estado_actual == 'validada'): 
                    $bloquear = true; ?>
                    <div class="banner banner-validated">
                        <span>🔒</span>
                        <div><strong>Jornada Validada:</strong> La auditoría de esta jornada ha finalizado. Los registros están bloqueados para garantizar la integridad contable.</div>
                    </div>
                <?php endif; ?>

                <?php
                // Consultamos solo pagos con tarjeta (2) y transferencia (3)
                $sql_p = "SELECT dp.*, m.nombre_metodo, t.fecha_transaccion, t.id_registro 
                          FROM detalle_pago dp
                          JOIN transacciones t ON dp.id_transaccion_fk = t.id_registro
                          JOIN metodos_pago m ON dp.id_metodo_fk = m.id_metodo
                          WHERE t.id_jornada_fk = $id_jornada_consulta AND m.id_metodo IN (2,3)";
                $res = $conn->query($sql_p);
                ?>

                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>ID Venta</th>
                            <th>Fecha</th>
                            <th>Método</th>
                            <th>Monto</th>
                            <th>Estado Banco</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($res && $res->num_rows > 0): ?>
                            <?php while($p = $res->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $p['id_registro']; ?></td>
                                    <td><?php echo $p['fecha_transaccion']; ?></td>
                                    <td><?php echo $p['nombre_metodo']; ?></td>
                                    <td><strong>$<?php echo number_format($p['monto_pago'], 2); ?></strong></td>
                                    <td>
                                        <?php echo $p['conciliado_banco'] ? 
                                            '<span class="badge badge-success">Conciliado</span>' : 
                                            '<span class="badge badge-warning">Pendiente</span>'; ?>
                                    </td>
                                    <td>
                                        <form method="POST">
                                            <input type="hidden" name="id_pago" value="<?php echo $p['id_pago']; ?>">
                                            <input type="hidden" name="id_jornada_hidden" value="<?php echo $id_jornada_consulta; ?>">
                                            <input type="hidden" name="nuevo_estado" value="<?php echo $p['conciliado_banco'] ? '0' : '1'; ?>">
                                            
                                            <button type="submit" name="toggle_conciliacion" 
                                                class="btn-action" 
                                                <?php echo $bloquear ? 'disabled' : ''; ?>
                                                style="background: <?php echo $bloquear ? '#cbd5e1' : ($p['conciliado_banco'] ? '#64748b' : '#2563eb'); ?>; 
                                                       color: white; 
                                                       cursor: <?php echo $bloquear ? 'not-allowed' : 'pointer'; ?>;">
                                                <?php echo $p['conciliado_banco'] ? 'Deshacer' : 'Confirmar'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" align="center" style="padding:30px;">No hay registros de tarjetas o transferencias para esta jornada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align:center; padding:50px; color:#64748b;">Seleccione una jornada para auditar.</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>