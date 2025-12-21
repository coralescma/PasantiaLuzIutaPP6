<?php
// gestion/form_cierre_caja.php
include '../includes/auth.php'; 
require_login(['Administrador', 'Supervisor']); 
include '../includes/db_connect.php'; 

$mensaje_estado = "";
$clase_mensaje = "";

$id_usuario_actual = $_SESSION['user_id'];
$id_jornada = obtenerJornadaActiva($conn);

// 1. Cálculos de Arqueo
$total_venta_efectivo = 0;
$monto_apertura = 0;

if ($id_jornada) {
    // Sumamos ventas de la jornada actual
    $sql_v = "SELECT SUM(monto_total) as total FROM transacciones 
              WHERE id_jornada_fk = $id_jornada AND metodo_pago = 'Efectivo'";
    $res_v = $conn->query($sql_v);
    if ($res_v) $total_venta_efectivo = $res_v->fetch_assoc()['total'] ?? 0;

    $sql_j = "SELECT monto_apertura FROM control_jornadas WHERE id_jornada = $id_jornada";
    $res_j = $conn->query($sql_j);
    if ($res_j) $monto_apertura = $res_j->fetch_assoc()['monto_apertura'] ?? 0;
}

$total_esperado = $monto_apertura + $total_venta_efectivo;

// 2. Proceso de Cierre al enviar el formulario
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_finalizar_cierre'])) {
    $conteo_fisico = floatval($_POST['conteo_manual_efectivo']);
    $obs = $conn->real_escape_string($_POST['observaciones']);

    // SQL Corregido para coincidir con la estructura de arriba
    $sql_update = "UPDATE control_jornadas SET 
                   id_usuario_cierre_fk = $id_usuario_actual, 
                   fecha_cierre = NOW(), 
                   monto_cierre_real = $conteo_fisico, 
                   observaciones = '$obs',
                   estado_jornada = 'Cerrada' 
                   WHERE id_jornada = $id_jornada";

    if ($conn->query($sql_update)) {
        $mensaje_estado = "✅ Jornada cerrada con éxito. El sistema de ventas se ha bloqueado.";
        $clase_mensaje = "alerta-verde";
        $id_jornada = false; 
    } else {
        $mensaje_estado = "❌ Error técnico: " . $conn->error;
        $clase_mensaje = "alerta-roja";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cierre de Caja - SCL</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .cierre-container { max-width: 500px; margin: 40px auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .resumen-arqueo { background: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e2e8f0; }
        .monto-grande { font-size: 24px; font-weight: bold; color: #1e40af; display: block; margin-top: 5px; }
        .alerta-verde { color: #15803d; background: #f0fdf4; padding: 15px; border-radius: 8px; text-align: center; font-weight: bold; }
        .alerta-roja { color: #b91c1c; background: #fef2f2; padding: 15px; border-radius: 8px; text-align: center; }
    </style>
</head>
<body>
    <?php include '../includes/menu.php'; ?>

    <div class="cierre-container">
        <h2>Finalizar Jornada</h2>
        
        <?php if ($mensaje_estado): ?>
            <div class="<?php echo $clase_mensaje; ?>"><?php echo $mensaje_estado; ?></div>
            <div style="text-align:center; margin-top:20px;"><a href="../dashboard.php" class="btn-login">Ir al Dashboard</a></div>
        <?php elseif ($id_jornada): ?>
            
            <div class="resumen-arqueo">
                <small>EFECTIVO ESPERADO (Apertura + Ventas):</small>
                <span class="monto-grande">$<?php echo number_format($total_esperado, 2); ?></span>
            </div>

            <form method="POST">
                <label>Monto Real en Caja (Físico):</label>
                <input type="number" step="0.01" name="conteo_manual_efectivo" required autofocus
                       style="width:100%; padding:15px; font-size:20px; border: 2px solid #cbd5e1; border-radius:8px; margin-bottom:20px;">

                <label>Observaciones / Novedades:</label>
                <textarea name="observaciones" rows="3" style="width:100%; border-radius:8px; border: 1px solid #cbd5e1; padding:10px;"></textarea>

                <p style="font-size: 0.85rem; color: #64748b; margin-top: 15px;">
                    Responsable: <strong><?php echo $_SESSION['user_full_name']; ?></strong>
                </p>

                <button type="submit" name="btn_finalizar_cierre" class="btn-login" 
                        style="background:#e11d48; width:100%; margin-top:20px; padding:15px;">
                    CONFIRMAR CIERRE DE CAJA
                </button>
            </form>

        <?php endif; ?>
    </div>
</body>
</html>