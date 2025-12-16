<?php
// ===============================================
// Bloque PHP 1: SEGURIDAD, CONEXIÓN y LÓGICA (CORREGIDO)
// ===============================================

// HABILITAR REPORTE DE ERRORES (Temporalmente para depuración)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'includes/auth.php'; 
require_login(); 
include 'includes/db_connect.php'; 

$pagina_activa = 'reporte_a1'; 

// --- 1. Parámetros de Prueba y Variables ---
// Puedes cambiar la fecha si quieres probar días anteriores con datos
$fecha_reporte = '2025-12-11'; 
$cajero_responsable = $_SESSION['user_full_name'] ?? 'N/A';
$supervisor_cierre = 'Gerente de Turno'; // Fijo por ahora
$conteo_manual_efectivo = 1475.00; // El conteo manual (Código X)

$fondo_caja_inicial = 200.00; // SIMULADO: Se debería obtener del Formulario de Apertura.

// --- 2. Obtener Parámetros de la DB (D4) ---
$sql_params = "SELECT umbral_tolerancia_efectivo, efectivo_requiere_conteo_inicial FROM parametros_negocio WHERE id_parametro = 1";
$resultado_params = $conn->query($sql_params);
$params = ($resultado_params && $resultado_params->num_rows > 0) ? $resultado_params->fetch_assoc() : [];

$umbral_tolerancia = $params['umbral_tolerancia_efectivo'] ?? 5.00; // En valor absoluto (ej: $5.00)
$requiere_conteo_inicial = $params['efectivo_requiere_conteo_inicial'] ?? 1; // 1 = Sí requiere

// --- 3. Lógica del Cuadre de Caja (Proceso 1.4) ---
$sql_efectivo = "SELECT SUM(monto_venta) AS total_registrado FROM transacciones 
                 WHERE fecha_venta = '{$fecha_reporte}' AND tipo_cobro = 'Efectivo' AND es_egreso = 0";
$resultado_efectivo = $conn->query($sql_efectivo);
$total_registrado = ($resultado_efectivo && $resultado_efectivo->num_rows > 0) 
    ? $resultado_efectivo->fetch_assoc()['total_registrado'] ?? 0.00
    : 0.00;

// Calcular el total de efectivo esperado.
// Si requiere conteo inicial, se suma al total registrado para el cuadre.
$total_efectivo_esperado = $total_registrado;
if ($requiere_conteo_inicial) {
    $total_efectivo_esperado += $fondo_caja_inicial;
}

// La diferencia se calcula contra el total esperado
$diferencia = $conteo_manual_efectivo - $total_efectivo_esperado;

// Determinar el Código de Validación
if (abs($diferencia) > $umbral_tolerancia) {
    $codigo_final = 'X (CRÍTICO)';
    $clase_alerta = 'alerta-roja';
    $recomendacion = 'AUDITORÍA INMEDIATA: La diferencia excede el umbral de $' . number_format($umbral_tolerancia, 2) . '. Se requiere explicación formal y firma del Supervisor.';
} else {
    $codigo_final = 'Z (TOLERADO)';
    $clase_alerta = 'alerta-verde';
    $recomendacion = 'OK: Cierre dentro de la tolerancia de $' . number_format($umbral_tolerancia, 2) . '.';
}

// --- 4. Lógica de Egresos (Pérdidas) ---
$sql_egresos = "SELECT 
    dt.id_detalle, 
    i.nombre_producto, 
    dt.cantidad, 
    dt.motivo, 
    dt.usuario_autorizador,
    (dt.cantidad * i.costo_unitario) AS costo_asociado
FROM detalle_transaccion dt
JOIN transacciones t ON dt.id_transaccion_fk = t.id_transaccion -- CORRECCIÓN: Usar id_transaccion
JOIN inventario i ON dt.id_producto_fk = i.id_producto
WHERE t.fecha_venta = '{$fecha_reporte}' AND t.es_egreso = 1"; // es_egreso = 1 (TRUE)

$resultado_egresos = $conn->query($sql_egresos);
$total_costo_egresos = 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte A.1 - Cierre Diario de Caja</title>
    <link rel="stylesheet" href="css/style.css" media="all">
</head>
<body>
    <?php include 'includes/menu.php'; ?>
    
    <div class="report-container">
        <header class="report-header">
            <h1>REPORTE A.1: CIERRE DIARIO DE CAJA Y STOCK</h1>
            <p><strong>Institución:</strong> Comedor Central SCL | <strong>Formato:</strong> Operacional Diario</p>
            <hr>
            <div class="metadata">
                <span><strong>Fecha Consultada:</strong> <?php echo $fecha_reporte; ?></span><br>
                <span><strong>Cajero Responsable:</strong> <?php echo $cajero_responsable; ?></span><br>
                <span><strong>Supervisor de Cierre:</strong> <?php echo $supervisor_cierre; ?></span>
            </div>
        </header>

        <section class="cuadre-caja">
            <h2>💰 Cuadre de Efectivo</h2>
            <table>
                <tr>
                    <th>Fondo Inicial (Si aplica)</th>
                    <th>Venta Efectivo Registrada</th>
                    <th>Total Esperado</th>
                    <th>Monto Contado (Físico)</th>
                    <th>Diferencia/Descuadre</th>
                    <th>Código de Validación</th>
                </tr>
                <tr>
                    <td>$<?php echo number_format($requiere_conteo_inicial ? $fondo_caja_inicial : 0.00, 2); ?></td>
                    <td>$<?php echo number_format($total_registrado, 2); ?></td>
                    <td>$<?php echo number_format($total_efectivo_esperado, 2); ?></td>
                    <td>$<?php echo number_format($conteo_manual_efectivo, 2); ?></td>
                    <td class="<?php echo $clase_alerta; ?>" style="font-weight: bold;">
                        $<?php echo number_format($diferencia, 2); ?>
                    </td>
                    <td class="<?php echo $clase_alerta; ?>">
                        **<?php echo $codigo_final; ?>**
                    </td>
                </tr>
            </table>
        </section>

        <section class="detalle-egresos">
            <h2>📉 Detalle de Egresos Especiales (Pérdidas de Inventario)</h2>
            <?php if ($resultado_egresos && $resultado_egresos->num_rows > 0): ?>
                <table>
                    <tr>
                        <th>ID Reg.</th>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Motivo del Egreso</th>
                        <th>Autorizador</th>
                        <th>Costo Asociado</th>
                    </tr>
                    <?php while($egreso = $resultado_egresos->fetch_assoc()): 
                        $total_costo_egresos += $egreso['costo_asociado'];
                    ?>
                    <tr>
                        <td><?php echo $egreso['id_detalle']; ?></td>
                        <td><?php echo $egreso['nombre_producto']; ?></td>
                        <td><?php echo $egreso['cantidad']; ?></td>
                        <td><?php echo $egreso['motivo']; ?></td>
                        <td><?php echo $egreso['usuario_autorizador']; ?></td>
                        <td>$<?php echo number_format($egreso['costo_asociado'], 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                    <tfoot>
                        <tr>
                            <td colspan="5" style="text-align: right;"><strong>COSTO TOTAL DE EGRESOS DEL TURNO:</strong></td>
                            <td style="font-weight: bold;">$<?php echo number_format($total_costo_egresos, 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            <?php else: ?>
                <p>No se registraron egresos especiales (pérdidas, cortesías, mermas) este día.</p>
            <?php endif; ?>
        </section>

        <section class="cierre-final">
            <h2>✍️ Cierre Operacional y Recomendaciones</h2>
            <div class="recomendacion <?php echo $clase_alerta; ?>">
                <strong>RECOMENDACIÓN DEL SISTEMA:</strong> <?php echo $recomendacion; ?>
            </div>
            
            <p><strong>Observaciones del Cajero (Requerido si Código X):</strong> __________________________________________________</p>
            <p><strong>Estado del Inventario:</strong> Verificado y cargado al sistema SCL.</p>

            <div class="firmas">
                <div class="firma-box">
                    <span>_________________________</span>
                    <p>Firma del Cajero Responsable</p>
                </div>
                <div class="firma-box">
                    <span>_________________________</span>
                    <p>Firma del Supervisor/Auditor</p>
                </div>
            </div>
        </section>

        <p class="back-link"><a href="dashboard.php">Volver al Dashboard</a></p>
    </div>
</body>
</html>