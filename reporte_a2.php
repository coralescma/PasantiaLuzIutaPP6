<?php
// reporte_a2.php - PANEL DE AUDITORÍA Y CONCILIACIÓN BANCARIA (SCL)
include 'includes/auth.php'; 
require_login(['Administrador', 'Contador']); 
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

// --- 2. LÓGICA: CONCILIACIÓN INDIVIDUAL (CON PROTECCIÓN) ---
if (isset($_POST['toggle_conciliacion'])) {
    $id_pago_val = intval($_POST['id_pago']);
    $nuevo_estado = intval($_POST['nuevo_estado']); 
    $id_jor_check = intval($_POST['id_jornada_hidden']);

    $stmt_check = $conn->prepare("SELECT estado_jornada FROM control_jornadas WHERE id_jornada = ?");
    $stmt_check->bind_param("i", $id_jor_check);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result()->fetch_assoc();

    if ($res_check && strtolower(trim($res_check['estado_jornada'])) !== 'validada') {
        $stmt_upd = $conn->prepare("UPDATE detalle_pago SET conciliado_banco = ? WHERE id_pago = ?");
        $stmt_upd->bind_param("ii", $nuevo_estado, $id_pago_val);
        $stmt_upd->execute();
    }
}

// --- 3. OBTENCIÓN DE DATOS PARA LA VISTA ---
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
    <title>Reporte A.2 - Conciliación Bancaria</title>
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
        .dato-label { color: #64748b; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.025em; margin-bottom: 8px; display: block; }
        .dato-val { font-size: 1.6rem; font-weight: 800; color: #1e293b; }

        .btn-finalizar { background: var(--success); color: white; padding: 12px 24px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; display: flex; align-items: center; gap: 8px; }
        .btn-finalizar:hover { background: #047857; transform: translateY(-1px); }
        .btn-toggle { padding: 6px 12px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; transition: 0.2s; color: white; }
        .btn-toggle:disabled { opacity: 0.5; cursor: not-allowed; }

        .table-custom { width: 100%; border-collapse: collapse; }
        .table-custom th { background: #f8fafc; padding: 14px; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
        .table-custom td { padding: 16px 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        
        .badge { padding: 4px 8px; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; }
        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-danger { background: #fee2e2; color: #b91c1c; }

        /* Estilo para el botón PDF */
        .btn-pdf { background: #0f172a; color: white; padding: 10px 18px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; transition: 0.2s; border: 1px solid #0f172a; }
        .btn-pdf:hover { background: #1e293b; }
    </style>
</head>
<body>
    <?php include 'includes/menu.php'; ?>

    <div class="main-wrapper">
        <div class="card-a2">
            <header class="header-report">
                <div>
                    <h1 style="margin:0; color: #0f172a; font-size: 1.5rem;">AUDITORÍA A.2 - CONCILIACIÓN BANCARIA</h1>
                    <p style="margin:4px 0 0; color: #64748b;">Validación de transacciones electrónicas (Tarjetas / Transferencias)</p>
                </div>
                <?php if ($estado_actual == 'cerrado' || $estado_actual == 'cerrada'): ?>
                    <form method="POST" onsubmit="return confirm('¿BLOQUEAR AUDITORÍA? Esta acción es definitiva.');">
                        <input type="hidden" name="id_jornada_validar" value="<?php echo $id_jornada_consulta; ?>">
                        <button type="submit" name="btn_finalizar_auditoria" class="btn-finalizar">🔒 FINALIZAR Y VALIDAR</button>
                    </form>
                <?php endif; ?>
            </header>

            <div style="background: #ffffff; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between; gap: 15px;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <label style="font-weight: 600; color: #475569;">Historial de Jornadas:</label>
                    <form method="GET">
                        <select name="id_jornada" onchange="this.form.submit()" style="padding: 10px; width: 350px; border-radius: 6px; border: 1px solid #cbd5e1; outline: none;">
                            <option value="0">-- Seleccione una jornada --</option>
                            <?php while($j = $lista_jornadas->fetch_assoc()): ?>
                                <option value="<?php echo $j['id_jornada']; ?>" <?php echo ($id_jornada_consulta == $j['id_jornada']) ? 'selected' : ''; ?>>
                                    Jornada #<?php echo $j['id_jornada']; ?> - <?php echo $j['fecha_apertura']; ?> [<?php echo strtoupper($j['estado_jornada']); ?>]
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </form>
                </div>

                <?php if ($id_jornada_consulta > 0): ?>
                    <a href="reporte_ventas_pdf.php?id_jornada=<?php echo $id_jornada_consulta; ?>" target="_blank" class="btn-pdf">
                        <span>📄</span> Imprimir Reporte para Libro
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($id_jornada_consulta > 0): 
                $res_total = $conn->query("SELECT SUM(dp.monto_pago) as total FROM detalle_pago dp JOIN transacciones t ON dp.id_transaccion_fk = t.id_registro WHERE t.id_jornada_fk = $id_jornada_consulta AND dp.id_metodo_fk IN (2,3)")->fetch_assoc();
                $res_conc = $conn->query("SELECT SUM(dp.monto_pago) as total FROM detalle_pago dp JOIN transacciones t ON dp.id_transaccion_fk = t.id_registro WHERE t.id_jornada_fk = $id_jornada_consulta AND dp.id_metodo_fk IN (2,3) AND dp.conciliado_banco = 1")->fetch_assoc();
                
                $monto_registrado = $res_total['total'] ?? 0;
                $monto_liquidado = $res_conc['total'] ?? 0;
                $desviacion = $monto_liquidado - $monto_registrado;
            ?>

                <?php if ($estado_actual == 'abierto' || $estado_actual == 'abierta'): ?>
                    <div class="banner banner-open"><span>🔵</span> <strong>JORNADA EN CURSO:</strong> Datos preliminares.</div>
                <?php elseif ($estado_actual == 'cerrado' || $estado_actual == 'cerrada'): ?>
                    <div class="banner banner-closed"><span>🟠</span> <strong>CIERRE CAJERO DETECTADO:</strong> Realice la conciliación.</div>
                <?php elseif ($estado_actual == 'validada'): ?>
                    <div class="banner banner-validated"><span>✅</span> <strong>AUDITORÍA FINALIZADA:</strong> Registros protegidos.</div>
                <?php endif; ?>

                <div class="resumen-auditoria">
                    <div class="grid-resumen">
                        <div class="dato-box">
                            <span class="dato-label">Sistema (Ventas)</span>
                            <span class="dato-val">$<?php echo number_format($monto_registrado, 2); ?></span>
                        </div>
                        <div class="dato-box">
                            <span class="dato-label">Banco (Efectivo)</span>
                            <span class="dato-val" style="color: var(--primary);">$<?php echo number_format($monto_liquidado, 2); ?></span>
                        </div>
                        <div class="dato-box">
                            <span class="dato-label">Diferencia</span>
                            <span class="dato-val <?php echo ($desviacion < -0.01) ? 'badge-danger' : 'badge-success'; ?>" style="background: none; font-size: 1.6rem;">
                                $<?php echo number_format($desviacion, 2); ?>
                            </span>
                        </div>
                        <div class="dato-box">
                            <span class="dato-label">Status A.2</span>
                            <span class="badge <?php echo ($desviacion < -0.01) ? 'badge-danger' : 'badge-success'; ?>" style="font-size: 1rem; padding: 8px 15px;">
                                <?php echo ($desviacion < -0.01) ? '⚠️ DESCUADRADO' : '✅ CONCILIADO'; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <h3 style="color: #1e293b; margin-bottom: 15px;">Listado de Pagos Electrónicos</h3>
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>ID Trans.</th>
                            <th>Método de Pago</th>
                            <th>Monto</th>
                            <th>Referencia</th>
                            <th>Estatus Banco</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql_det = "SELECT dp.*, m.nombre_metodo 
                                    FROM detalle_pago dp 
                                    JOIN transacciones t ON dp.id_transaccion_fk = t.id_registro 
                                    JOIN metodos_pago m ON dp.id_metodo_fk = m.id_metodo
                                    WHERE t.id_jornada_fk = $id_jornada_consulta AND m.id_metodo IN (2,3)";
                        $res_det = $conn->query($sql_det);
                        while($p = $res_det->fetch_assoc()): ?>
                            <tr>
                                <td style="color: #64748b; font-weight: 500;">#<?php echo $p['id_transaccion_fk']; ?></td>
                                <td><strong><?php echo $p['nombre_metodo']; ?></strong></td>
                                <td style="font-weight: 700;">$<?php echo number_format($p['monto_pago'], 2); ?></td>
                                <td><code style="background: #f1f5f9; padding: 4px 8px; border-radius: 4px;"><?php echo $p['referencia_banco'] ?: '---'; ?></code></td>
                                <td>
                                    <?php if ($p['conciliado_banco']): ?>
                                        <span class="badge badge-success">✓ CONFIRMADO</span>
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
                                            <button type="submit" name="toggle_conciliacion" class="btn-toggle"
                                                    style="background: <?php echo $p['conciliado_banco'] ? '#64748b' : 'var(--primary)'; ?>;">
                                                <?php echo $p['conciliado_banco'] ? 'Desmarcar' : 'Confirmar'; ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-size: 0.85rem; font-style: italic;">🔒 Protegido</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>