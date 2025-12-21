<?php
// reporte_a2.php - PANEL DE AUDITORÍA Y VALIDACIÓN BANCARIA
include 'includes/auth.php'; 
require_login(['Administrador', 'Contador']); 
include 'includes/db_connect.php'; 

$pagina_activa = 'reporte_a2'; 

// --- 1. LÓGICA DE ACTUALIZACIÓN ---
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
    <title>SCL - Auditoría Bancaria</title>
    <link rel="stylesheet" href="assets/css/estilos.css">
    <link rel="stylesheet" href="css/style.css"> 
    <style>
        /* Estilos específicos para armonizar con el resto del sistema */
        body { margin: 0; background-color: #f4f7f6; }
        .main-wrapper { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .card-a2 { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .table-custom { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table-custom th { text-align: left; padding: 12px; background: #f8fafc; border-bottom: 2px solid #edf2f7; }
        .table-custom td { padding: 12px; border-bottom: 1px solid #edf2f7; }
        .badge { padding: 5px 10px; border-radius: 4px; font-size: 0.85em; font-weight: bold; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .btn-action { padding: 8px 12px; border-radius: 4px; border: none; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>

    <?php include 'includes/menu.php'; ?>

    <div class="main-wrapper">
        <div class="card-a2">
            <h1 style="color: #333;">🔍 Reporte A.2: Auditoría de Pagos</h1>
            
            <div style="margin: 20px 0; background: #f9fafb; padding: 15px; border-radius: 6px;">
                <form method="GET" style="display: flex; align-items: center; gap: 10px;">
                    <strong>Jornada:</strong>
                    <select name="id_jornada" onchange="this.form.submit()" style="padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                        <option value="0">-- Seleccione una jornada --</option>
                        <?php while($j = $lista_jornadas->fetch_assoc()): ?>
                            <option value="<?php echo $j['id_jornada']; ?>" <?php echo ($id_jornada_consulta == $j['id_jornada']) ? 'selected' : ''; ?>>
                                #<?php echo $j['id_jornada']; ?> - <?php echo date('d/m/Y', strtotime($j['fecha_apertura'])); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </form>
            </div>

            <?php if ($id_jornada_consulta > 0): ?>
                <?php
                $sql_pagos = "SELECT dp.*, m.nombre_metodo, t.fecha_transaccion, t.id_registro 
                              FROM detalle_pago dp
                              JOIN transacciones t ON dp.id_transaccion_fk = t.id_registro
                              JOIN metodos_pago m ON dp.id_metodo_fk = m.id_metodo
                              WHERE t.id_jornada_fk = $id_jornada_consulta 
                              AND m.id_metodo IN (2, 3)"; // Tarjeta y Transferencia
                $res = $conn->query($sql_pagos);
                ?>

                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Venta #</th>
                            <th>Fecha</th>
                            <th>Método</th>
                            <th>Monto</th>
                            <th>Estado Banco</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($p = $res->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?php echo $p['id_registro']; ?></strong></td>
                                <td><?php echo $p['fecha_transaccion']; ?></td>
                                <td><?php echo $p['nombre_metodo']; ?></td>
                                <td>$<?php echo number_format($p['monto_pago'], 2); ?></td>
                                <td>
                                    <?php if($p['conciliado_banco']): ?>
                                        <span class="badge badge-success">✓ Conciliado</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">● Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="id_pago" value="<?php echo $p['id_pago']; ?>">
                                        <input type="hidden" name="nuevo_estado" value="<?php echo $p['conciliado_banco'] ? '0' : '1'; ?>">
                                        <button type="submit" name="toggle_conciliacion" class="btn-action" style="background: <?php echo $p['conciliado_banco'] ? '#64748b' : '#2563eb'; ?>; color: white;">
                                            <?php echo $p['conciliado_banco'] ? 'Deshacer' : 'Confirmar'; ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; padding: 40px; color: #666;">Seleccione una jornada para ver los pagos electrónicos.</p>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>