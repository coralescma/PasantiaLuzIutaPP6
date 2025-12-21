<?php
// gestion/form_cierre_caja.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../includes/auth.php'; 
// RESTRICCIÓN: Solo personal con rango puede cerrar jornada
require_login(['Administrador', 'Supervisor']); 
include '../includes/db_connect.php'; 

$mensaje_estado = "";
$clase_mensaje = "";

// 1. IDENTIFICAR USUARIO LOGUEADO
$id_usuario_actual = $_SESSION['user_id'];
$nombre_usuario_actual = $_SESSION['user_full_name'];

// 2. BUSCAR JORNADA ACTIVA
$id_jornada = obtenerJornadaActiva($conn);

if (!$id_jornada) {
    $mensaje_estado = "❌ No hay ninguna jornada abierta para cerrar.";
    $clase_mensaje = "alerta-roja";
}

// 3. OBTENER DATOS PARA EL ARQUEO (D1)
$total_venta_efectivo = 0;
$monto_apertura = 0;

if ($id_jornada) {
    // Sumar ventas en efectivo de esta jornada
    $sql_v = "SELECT SUM(monto_total) as total FROM transacciones 
              WHERE id_jornada_fk = $id_jornada AND metodo_pago = 'Efectivo'";
    $res_v = $conn->query($sql_v);
    if ($res_v) $total_venta_efectivo = $res_v->fetch_assoc()['total'] ?? 0;

    // Obtener monto de apertura
    $sql_j = "SELECT monto_apertura FROM control_jornadas WHERE id_jornada = $id_jornada";
    $res_j = $conn->query($sql_j);
    if ($res_j) $monto_apertura = $res_j->fetch_assoc()['monto_apertura'] ?? 0;
}

$total_esperado = $monto_apertura + $total_venta_efectivo;

// 4. PROCESAR EL CIERRE
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_finalizar_cierre'])) {
    $conteo_fisico = floatval($_POST['conteo_manual_efectivo']);
    $observaciones = $conn->real_escape_string($_POST['observaciones']);
    $diferencia = $conteo_fisico - $total_esperado;

    // Actualizar la tabla de jornadas con el usuario logueado como responsable del cierre
    $sql_update = "UPDATE control_jornadas SET 
                   id_usuario_cierre_fk = $id_usuario_actual, 
                   fecha_cierre = NOW(), 
                   monto_cierre_real = $conteo_fisico, 
                   estado_jornada = 'Cerrada' 
                   WHERE id_jornada = $id_jornada";

    if ($conn->query($sql_update)) {
        $mensaje_estado = "✅ Jornada #$id_jornada cerrada exitosamente por $nombre_usuario_actual.";
        $clase_mensaje = "alerta-verde";
        $id_jornada = false; // Para ocultar el formulario tras el éxito
    } else {
        $mensaje_estado = "❌ Error al cerrar: " . $conn->error;
        $clase_mensaje = "alerta-roja";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cierre de Jornada - SCL</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .cierre-box { max-width: 600px; margin: 30px auto; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .dato-linea { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 1.1em; }
        .total-highlight { border-top: 2px solid #333; padding-top: 10px; font-weight: bold; color: #1e40af; font-size: 1.3em; }
        .alerta-roja { background: #fee2e2; color: #b91c1c; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alerta-verde { background: #dcfce7; color: #166534; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .user-badge { background: #f1f5f9; padding: 10px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #2563eb; }
    </style>
</head>
<body>
    <?php include '../includes/menu.php'; ?>

    <div class="cierre-box">
        <h1>🔒 Cierre de Caja y Jornada</h1>

        <?php if ($mensaje_estado): ?>
            <div class="<?php echo $clase_mensaje; ?>"><?php echo $mensaje_estado; ?></div>
        <?php endif; ?>

        <?php if ($id_jornada): ?>
            <div class="user-badge">
                Responsable del Cierre: <strong><?php echo $nombre_usuario_actual; ?></strong><br>
                Privilegio: <small><?php echo $_SESSION['user_role']; ?></small>
            </div>

            <form method="POST">
                <div class="dato-linea">
                    <span>Monto de Apertura:</span>
                    <span>$<?php echo number_format($monto_apertura, 2); ?></span>
                </div>
                <div class="dato-linea">
                    <span>Ventas Efectivo (Sistema):</span>
                    <span>$<?php echo number_format($total_venta_efectivo, 2); ?></span>
                </div>
                <div class="dato-linea total-highlight">
                    <span>TOTAL ESPERADO:</span>
                    <span>$<?php echo number_format($total_esperado, 2); ?></span>
                </div>

                <div style="margin-top: 25px;">
                    <label><strong>Monto Contado Físicamente:</strong></label>
                    <input type="number" step="0.01" name="conteo_manual_efectivo" required 
                           style="width: 100%; padding: 12px; font-size: 1.5em; text-align: center; border: 2px solid #2563eb; border-radius: 5px;">
                </div>

                <div style="margin-top: 15px;">
                    <label>Observaciones de Cierre:</label>
                    <textarea name="observaciones" rows="3" style="width: 100%; padding: 10px;" placeholder="Escriba aquí si hubo descuadre o novedades..."></textarea>
                </div>

                <button type="submit" name="btn_finalizar_cierre" class="btn-login" 
                        style="width: 100%; margin-top: 20px; background: #e11d48;" 
                        onclick="return confirm('¿Está seguro de cerrar la jornada? No podrá registrar más ventas hoy.')">
                    FINALIZAR Y CERRAR JORNADA
                </button>
            </form>
        <?php else: ?>
            <p style="text-align:center;">No hay acciones pendientes.</p>
            <a href="../dashboard.php" class="btn-action" style="display:block; text-align:center;">Volver al Dashboard</a>
        <?php endif; ?>
    </div>
</body>
</html>