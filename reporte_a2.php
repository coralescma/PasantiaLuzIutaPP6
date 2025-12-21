<?php
// reporte_a2.php - PANEL DE AUDITORÍA Y VALIDACIÓN BANCARIA
include 'includes/auth.php'; 
require_login(['Administrador', 'Contador']); 
include 'includes/db_connect.php'; 

$pagina_activa = 'reporte_a2'; 

// --- 1. LÓGICA DE ACTUALIZACIÓN (VALIDACIÓN) ---
if (isset($_POST['toggle_conciliacion'])) {
    $id_pago = intval($_POST['id_pago']);
    $nuevo_estado = intval($_POST['nuevo_estado']);
    $sql_update = "UPDATE detalle_pago SET conciliado_banco = $nuevo_estado WHERE id_pago = $id_pago";
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
    <title>Auditoría A2 - Validación Bancaria</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .audit-container { max-width: 1000px; margin: 20px auto; padding: 20px; font-family: sans-serif; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .btn-validar { padding: 8px 15px; cursor: pointer; border-radius: 4px; border: none; font-weight: bold; }
        .btn-pendiente { background: #fee2e2; color: #991b1b; border: 1px solid #f87171; }
        .btn-conciliado { background: #dcfce7; color: #166534; border: 1px solid #4ade80; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #f1f5f9; padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        .status-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    </style>
</head>
<body>
    <?php include 'includes/menu.php'; ?>

    <div class="audit-container">
        <div class="status-header">
            <h1>🔍 Auditoría A.2: Validación de Bancos</h1>
            <form method="GET">
                <select name="id_jornada" onchange="this.form.submit()" style="padding: 10px; border-radius: 5px;">
                    <option value="">-- Seleccionar Jornada --</option>
                    <?php while($j = $lista_jornadas->fetch_assoc()): ?>
                        <option value="<?php echo $j['id_jornada']; ?>" <?php echo ($id_jornada_consulta == $j['id_jornada']) ? 'selected' : ''; ?>>
                            Jornada #<?php echo $j['id_jornada']; ?> (<?php echo $j['fecha_apertura']; ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </form>
        </div>

        <?php if ($id_jornada_consulta): 
            // CONSULTA SEGURA: Verificamos si la tabla tiene los campos necesarios
            $sql_det = "SELECT dp.id_pago, t.id_registro, t.fecha_hora, mp.nombre_metodo, 
                               dp.monto_pago, dp.conciliado_banco, t.referencia_banco
                        FROM detalle_pago dp
                        JOIN transacciones t ON dp.id_transaccion_fk = t.id_registro
                        JOIN metodos_pago mp ON dp.id_metodo_fk = mp.id_metodo
                        WHERE t.id_jornada_fk = $id_jornada_consulta 
                        AND mp.nombre_metodo != 'Efectivo'
                        ORDER BY t.fecha_hora ASC";
            
            $res_det = $conn->query($sql_det);
            
            if ($res_det): ?>
            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>Método</th>
                            <th>Ref. Banco</th>
                            <th>Monto</th>
                            <th>Estado de Acreditación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($res_det->num_rows > 0): ?>
                            <?php while($row = $res_det->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $row['id_registro']; ?></td>
                                <td><strong><?php echo $row['nombre_metodo']; ?></strong></td>
                                <td><code><?php echo $row['referencia_banco'] ?: '---'; ?></code></td>
                                <td>$<?php echo number_format($row['monto_pago'], 2); ?></td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="id_pago" value="<?php echo $row['id_pago']; ?>">
                                        <input type="hidden" name="nuevo_estado" value="<?php echo $row['conciliado_banco'] ? 0 : 1; ?>">
                                        <button type="submit" name="toggle_conciliacion" 
                                                class="btn-validar <?php echo $row['conciliado_banco'] ? 'btn-conciliado' : 'btn-pendiente'; ?>">
                                            <?php echo $row['conciliado_banco'] ? '✔ Acreditado' : '⌛ Pendiente'; ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center;">No hay transacciones electrónicas en esta jornada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div style="color:red; background:#fee2e2; padding:15px; border-radius:8px;">
                    <strong>Error de base de datos:</strong> <?php echo $conn->error; ?>. 
                    <br>Asegúrate de haber ejecutado los comandos SQL de actualización.
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div style="text-align:center; padding:40px; color:#64748b;">
                <h3>Por favor, selecciona una jornada para auditar los pagos bancarios.</h3>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>