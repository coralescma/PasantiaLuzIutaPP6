<?php
// Reporte de errores para diagnóstico
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../includes/auth.php'; 
require_login(); 
include '../includes/db_connect.php'; 

$user_id = $_SESSION['user_id'] ?? 0;
$hoy = date('Y-m-d');

// --- 1. VERIFICAR JORNADA ACTIVA (BANDERA 1) ---
// Usamos una consulta simple. Si falla, $jornada_activa será null.
$jornada_activa = null;
$sql_check = "SELECT * FROM control_jornadas WHERE estado_jornada = 1 LIMIT 1";
$res_check = $conn->query($sql_check);
if ($res_check && $res_check->num_rows > 0) {
    $jornada_activa = $res_check->fetch_assoc();
}

// --- 2. LÓGICA DE APERTURA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_apertura'])) {
    $stmt = $conn->prepare("INSERT INTO control_jornadas (fecha, id_usuario_fk, monto_apertura, estado_jornada) VALUES (?, ?, 0.00, 1)");
    $stmt->bind_param("si", $hoy, $user_id);
    if ($stmt->execute()) {
        header("Location: form_jornada.php");
        exit();
    }
}

// --- 3. LÓGICA DE CIERRE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_cierre']) && $jornada_activa) {
    $id_jornada = $jornada_activa['id_jornada'];
    $ts_apertura = $jornada_activa['apertura_timestamp'];
    
    // Sumar ventas desde la apertura
    $sql_sum = "SELECT SUM(monto_venta) as total FROM transacciones WHERE es_egreso = 0 AND fecha_venta >= '$ts_apertura'";
    $res_sum = $conn->query($sql_sum);
    $total_ciclo = ($res_sum) ? ($res_sum->fetch_assoc()['total'] ?? 0) : 0;
    
    // Actualizar tabla y cerrar bandera (estado 2)
    $stmt = $conn->prepare("UPDATE control_jornadas SET monto_ventas_sistema = ?, monto_conteo_fisico = ?, diferencia = 0, estado_jornada = 2, id_usuario_cierre_fk = ?, cierre_timestamp = NOW() WHERE id_jornada = ?");
    $stmt->bind_param("ddii", $total_ciclo, $total_ciclo, $user_id, $id_jornada);
    
    if ($stmt->execute()) {
        header("Location: ../reporte_a1.php?id_jornada=" . $id_jornada);
        exit();
    }
}

// --- 4. LISTA DE VENTAS PENDIENTES ---
$ventas_pendientes = [];
$total_acumulado = 0;

if ($jornada_activa) {
    $ts = $jornada_activa['apertura_timestamp'];
    $sql_lista = "SELECT t.id_transaccion, t.fecha_venta, t.monto_venta, t.tipo_cobro, u.user_full_name 
                  FROM transacciones t 
                  JOIN usuarios u ON t.id_usuario_fk = u.id_usuario
                  WHERE t.es_egreso = 0 AND t.fecha_venta >= '$ts'
                  ORDER BY t.fecha_venta ASC";
    
    $res_lista = $conn->query($sql_lista);
    if ($res_lista) {
        while ($row = $res_lista->fetch_assoc()) {
            $ventas_pendientes[] = $row;
            $total_acumulado += $row['monto_venta'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SCL - Gestión de Jornada</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <?php include '../includes/menu.php'; ?>

    <div class="report-container" style="max-width: 900px; margin: 20px auto; font-family: sans-serif;">
        <h1>📑 Gestión de Ciclo Operativo</h1>

        <?php if (!$jornada_activa): ?>
            <div style="background: #fdf2f2; border: 1px solid #f5c6cb; padding: 20px; border-radius: 8px; text-align: center;">
                <h2>🌅 No hay un ciclo abierto</h2>
                <p>Para registrar y liquidar transacciones, debe iniciar una nueva jornada.</p>
                <form method="POST">
                    <input type="hidden" name="accion_apertura" value="1">
                    <button type="submit" style="background: #28a745; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 1.1em;">🚀 Abrir Ciclo de Hoy</button>
                </form>
            </div>
        <?php else: ?>
            <div style="background: #fff; border: 1px solid #ddd; padding: 20px; border-radius: 8px;">
                <div style="background: #e8f5e9; color: #2e7d32; padding: 10px; border-radius: 5px; font-weight: bold; margin-bottom: 20px;">
                    ● CICLO ACTIVO DESDE: <?php echo $jornada_activa['apertura_timestamp']; ?>
                </div>
                
                <h3>Transacciones por Liquidar</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f4f4f4;">
                            <th style="padding: 10px; border: 1px solid #ddd;">ID</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">Fecha</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">Cajero</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($ventas_pendientes) > 0): ?>
                            <?php foreach ($ventas_pendientes as $v): ?>
                                <tr>
                                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo $v['id_transaccion']; ?></td>
                                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo $v['fecha_venta']; ?></td>
                                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo $v['user_full_name']; ?></td>
                                    <td style="padding: 10px; border: 1px solid #ddd;">$<?php echo number_format($v['monto_venta'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="text-align:center; padding: 20px;">No hay ventas registradas desde la apertura.</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr style="font-weight:bold; background:#eee;">
                            <td colspan="3" style="text-align:right; padding: 10px;">TOTAL A LIQUIDAR:</td>
                            <td style="padding: 10px;">$<?php echo number_format($total_acumulado, 2); ?></td>
                        </tr>
                    </tfoot>
                </table>

                <form method="POST" style="margin-top: 30px;">
                    <input type="hidden" name="accion_cierre" value="1">
                    <button type="submit" style="background: #34495e; color: white; padding: 15px; width: 100%; border: none; border-radius: 5px; cursor: pointer; font-size: 1.1em;">✅ Finalizar y Generar Reporte A.1</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>