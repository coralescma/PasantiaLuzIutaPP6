<?php
// gestion/form_cierre_caja.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../includes/auth.php'; 
require_login(['Administrador', 'Supervisor']); 
include '../includes/db_connect.php'; 

$mensaje_estado = "";
$clase_mensaje = "";

$id_usuario_actual = $_SESSION['user_id'];
$id_jornada = obtenerJornadaActiva($conn);

// 1. PROCESAR CIERRE
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_finalizar_cierre'])) {
    $monto_fisico = floatval($_POST['conteo_manual_efectivo']);
    $monto_esperado_efe = floatval($_POST['monto_esperado_oculto']);
    $diferencia = $monto_fisico - $monto_esperado_efe;
    $observaciones = trim($_POST['observaciones']);

    // NUEVO: Validar si la diferencia excede el umbral y faltan observaciones
    if (abs($diferencia) > $umbral_permitido && empty($observaciones)) {
        $mensaje_estado = "⚠️ La diferencia (Bs. " . abs($diferencia) . ") excede el umbral de Bs. $umbral_permitido. Debe explicar el motivo en observaciones.";
        $clase_mensaje = "alerta-roja";
        // Aquí NO ejecutamos el INSERT para obligar al usuario a corregir o explicar
    } else {

    $conn->begin_transaction();
    try {
        $sql_cierre = "INSERT INTO cierres_caja (fecha, id_cajero_fk, id_supervisor_fk, monto_registrado_efectivo, monto_contado_fisico, diferencia, observaciones) 
                       VALUES (CURDATE(), $id_usuario_actual, $id_usuario_actual, $monto_esperado_efe, $monto_fisico, $diferencia, '$observaciones')";
        $conn->query($sql_cierre);

        $sql_upd = "UPDATE control_jornadas SET estado_jornada = 'Cerrada', fecha_cierre = NOW() WHERE id_jornada = $id_jornada";
        $conn->query($sql_upd);

        $conn->commit();
        header("Location: ../dashboard.php?msj=cierre_exitoso");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $mensaje_estado = "❌ Error: " . $e->getMessage();
        $clase_mensaje = "alerta-roja";
    }
}

// 2. CÁLCULOS DINÁMICOS PARA LA VISTA
$total_venta_efectivo = 0;
$total_venta_otros = 0;
$monto_apertura = 0;

if ($id_jornada) {
    // Monto Apertura
    $res_j = $conn->query("SELECT monto_apertura FROM control_jornadas WHERE id_jornada = $id_jornada");
    $monto_apertura = $res_j->fetch_assoc()['monto_apertura'] ?? 0;

    // Sumar Efectivo (Integridad por nombre)
    $sql_efe = "SELECT SUM(dp.monto_pago) as total 
                FROM detalle_pago dp
                JOIN metodos_pago mp ON dp.id_metodo_fk = mp.id_metodo
                JOIN transacciones t ON dp.id_transaccion_fk = t.id_registro
                WHERE t.id_jornada_fk = $id_jornada AND mp.nombre_metodo = 'Efectivo' AND t.es_egreso = 0";
    $total_venta_efectivo = $conn->query($sql_efe)->fetch_assoc()['total'] ?? 0;

    // Sumar Otros Métodos (Integridad por exclusión)
    $sql_otros = "SELECT SUM(dp.monto_pago) as total 
                  FROM detalle_pago dp
                  JOIN metodos_pago mp ON dp.id_metodo_fk = mp.id_metodo
                  JOIN transacciones t ON dp.id_transaccion_fk = t.id_registro
                  WHERE t.id_jornada_fk = $id_jornada AND mp.nombre_metodo != 'Efectivo' AND t.es_egreso = 0";
    $total_venta_otros = $conn->query($sql_otros)->fetch_assoc()['total'] ?? 0;
}

$total_esperado_efectivo = $monto_apertura + $total_venta_efectivo;
$gran_total_general = $total_esperado_efectivo + $total_venta_otros;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cierre de Caja - SCL</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .cierre-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
        .card-resumen { background: #f8fafc; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0; }
        .monto-grande { font-size: 1.8em; font-weight: bold; }
        .label-cierre { color: #64748b; font-size: 0.85em; text-transform: uppercase; letter-spacing: 0.5px; }
        .gran-total-card { 
            grid-column: span 2; 
            background: #1e293b; 
            color: white; 
            text-align: center; 
            padding: 25px; 
            border-radius: 12px; 
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <?php include '../includes/menu.php'; ?>

    <div style="max-width: 900px; margin: 30px auto; padding: 20px; background: white; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);">
        <h2>Cierre de Jornada</h2>

        <?php if ($id_jornada): ?>
            <div class="cierre-grid">
                <div class="card-resumen">
                    <p class="label-cierre">Efectivo (Apertura + Ventas)</p>
                    <p class="monto-grande" style="color: #0f172a;">$ <?php echo number_format($total_esperado_efectivo, 2); ?></p>
                </div>

                <div class="card-resumen">
                    <p class="label-cierre">Ventas Otros Métodos</p>
                    <p class="monto-grande" style="color: #2563eb;">$ <?php echo number_format($total_venta_otros, 2); ?></p>
                </div>

                <div class="gran-total-card">
                    <p class="label-cierre" style="color: #94a3b8;">Gran Total Esperado (Ventas Totales + Base)</p>
                    <p style="font-size: 2.5em; font-weight: bold; margin: 5px 0;">$ <?php echo number_format($gran_total_general, 2); ?></p>
                </div>
            </div>

            <form method="POST" style="margin-top: 30px; background: #fff1f2; padding: 25px; border-radius: 10px; border: 1px solid #fecaca;">
                <h3 style="color: #991b1b; margin-top:0;">Arqueo de Efectivo Físico</h3>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold;">Monto contado en caja (Solo Efectivo):</label>
                    <input type="number" step="0.01" name="conteo_manual_efectivo" style="width: 100%; padding: 15px; font-size: 1.3em; border: 2px solid #f87171; border-radius: 8px;" required placeholder="0.00">
                </div>

                <label style="display: block; margin-bottom: 8px;">Observaciones:</label>
                <textarea name="observaciones" rows="2" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1;"></textarea>

                <input type="hidden" name="monto_esperado_oculto" value="<?php echo $total_esperado_efectivo; ?>">

                <button type="submit" name="btn_finalizar_cierre" class="btn-login" 
                        style="width: 100%; margin-top: 20px; background: #e11d48; padding: 18px; font-weight: bold;"
                        onclick="return confirm('¿Confirmar cierre?')">
                    FINALIZAR Y CERRAR JORNADA
                </button>
            </form>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; color: #64748b;">No hay jornadas abiertas.</div>
        <?php endif; ?>
    </div>
</body>
</html>