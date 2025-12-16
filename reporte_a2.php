<?php
// ===============================================
// Bloque PHP 1: SEGURIDAD, CONEXIÓN y LÓGICA
// ===============================================

// 1. Incluir la seguridad y la sesión
include 'includes/auth.php'; 

// Protege la página de acceso no autorizado. Este reporte es crucial para el Contador.
// Aunque el Cajero y Supervisor podrían verlo, le daremos prioridad al rol de Contador/Admin.
require_login(); 

// Asumimos que $conn está disponible globalmente después de incluir 'auth.php'
// Define la página activa para el menú
$pagina_activa = 'reporte_a2'; 


// --- Parámetros de la Prueba A.2 ---
$fecha_reporte = '2025-12-13'; // Día con Descuadre Bancario simulado
$contador_responsable = $_SESSION['user_full_name'] ?? 'María Castillo'; // Usa el usuario logueado
$supervisor_cierre_a2 = 'Jefe Financiero'; 

// --- Lógica de Conciliación (Proceso 4.0) ---

// 1. Obtener Totales Registrados (D1) para TPV y Pago Móvil del día
$sql_registrado = "SELECT 
    tipo_cobro, 
    SUM(monto_venta) AS total_registrado 
FROM transacciones 
WHERE fecha_venta = '{$fecha_reporte}' AND tipo_cobro IN ('TPV', 'Pago_Movil') AND es_egreso = FALSE
GROUP BY tipo_cobro";
$resultado_registrado = $conn->query($sql_registrado);

$registrado = [];
while ($row = $resultado_registrado->fetch_assoc()) {
    $registrado[$row['tipo_cobro']] = $row['total_registrado'];
}

$total_registrado_tpv = $registrado['TPV'] ?? 0.00;
$total_registrado_movil = $registrado['Pago_Movil'] ?? 0.00;

// 2. Simular Totales Liquidados (D2/D3 del Banco - Recibido al día siguiente)
// Simulamos el fallo: TPV registró 4000.00, pero el banco liquidó 3950.00
$total_liquidado_tpv = 3950.00; 
$total_liquidado_movil = $total_registrado_movil; // Asumimos Pago Móvil cuadró (0.00)

// 3. Calcular Desviaciones
$desviacion_tpv = $total_liquidado_tpv - $total_registrado_tpv;
$desviacion_movil = $total_liquidado_movil - $total_registrado_movil;

// Determinar el Código de Validación A.2 (Si cualquier desviación es crítica, el reporte es 'X')
$codigo_final = 'Z (CONCILIADO)';
$clase_alerta = 'alerta-verde';
$recomendacion = 'CONCILIACIÓN OK: Todos los medios de pago electrónicos han sido verificados contra el extracto bancario.';

// Usamos un pequeño umbral de tolerancia para el cero
$umbral_cero = 0.01; 

if (abs($desviacion_tpv) > $umbral_cero || abs($desviacion_movil) > $umbral_cero) {
    $codigo_final = 'X (CRÍTICO)';
    $clase_alerta = 'alerta-roja';
    $recomendacion = 'AUDITORÍA FINANCIERA: Existe una Desviación No Conciliada. Iniciar proceso de Reclamo Bancario.';
}

// --- Detalle de Transacciones No Conciliadas (Simulación de IDs Faltantes) ---
$transacciones_faltantes = [
    ['id' => '400201', 'monto' => 30.00, 'tipo' => 'TPV', 'motivo' => 'Fallo en Lote (Batch Fail)'],
    ['id' => '400202', 'monto' => 20.00, 'tipo' => 'TPV', 'motivo' => 'Reverso No Confirmado'],
];
$total_transacciones_faltantes = 50.00; // Coincide con la Desviación
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte A.2 - Conciliación Bancaria</title>
    <link rel="stylesheet" href="css/style.css" media="all">
</head>
<body>
    <?php include 'includes/menu.php'; // Incluimos el menú ?>
    
    <div class="report-container">
        <header class="report-header">
            <h1>REPORTE A.2: CONCILIACIÓN DE PAGOS ELECTRÓNICOS</h1>
            <p><strong>Institución:</strong> Comedor Central SCL | <strong>Formato:</strong> Auditoría Financiera</p>
            <hr>
            <div class="metadata">
                <span><strong>Fecha de Venta:</strong> <?php echo $fecha_reporte; ?></span><br>
                <span><strong>Fecha de Liquidación (Simulada):</strong> <?php echo date('Y-m-d', strtotime($fecha_reporte . ' +1 day')); ?></span><br>
                <span><strong>Contador Responsable:</strong> <?php echo $contador_responsable; ?></span>
            </div>
        </header>

        <section class="cuadre-caja">
            <h2>🏦 Resumen de Liquidación Bancaria</h2>
            <table>
                <tr>
                    <th>Medio de Pago</th>
                    <th>Total Registrado (D1)</th>
                    <th>Total Liquidado (D2/D3)</th>
                    <th>Desviación (Falta de Ingreso)</th>
                    <th>Código A.2</th>
                </tr>
                <tr>
                    <td>TPV</td>
                    <td>$<?php echo number_format($total_registrado_tpv, 2); ?></td>
                    <td>$<?php echo number_format($total_liquidado_tpv, 2); ?></td>
                    <td class="<?php echo (abs($desviacion_tpv) > $umbral_cero) ? 'alerta-roja' : 'alerta-verde'; ?>">
                        $<?php echo number_format($desviacion_tpv, 2); ?>
                    </td>
                    <td class="<?php echo $clase_alerta; ?>">
                        **<?php echo $codigo_final; ?>**
                    </td>
                </tr>
                <tr>
                    <td>Pago Móvil</td>
                    <td>$<?php echo number_format($total_registrado_movil, 2); ?></td>
                    <td>$<?php echo number_format($total_liquidado_movil, 2); ?></td>
                    <td class="<?php echo (abs($desviacion_movil) > $umbral_cero) ? 'alerta-roja' : 'alerta-verde'; ?>">
                        $<?php echo number_format($desviacion_movil, 2); ?>
                    </td>
                    <td>**<?php echo (abs($desviacion_movil) > $umbral_cero) ? 'X' : 'Z'; ?>**</td>
                </tr>
            </table>
        </section>

        <section class="detalle-egresos">
            <h2>🚨 Transacciones No Conciliadas (Prueba de Pérdida de Lote)</h2>
            <p>Esta sección es la **Prueba de Reclamo** para el banco.</p>

            <?php if (!empty($transacciones_faltantes)): ?>
                <table>
                    <tr>
                        <th>ID Transacción (D1)</th>
                        <th>Medio</th>
                        <th>Monto Faltante</th>
                        <th>Motivo Detectado</th>
                    </tr>
                    <?php foreach($transacciones_faltantes as $item): ?>
                    <tr>
                        <td><?php echo $item['id']; ?></td>
                        <td><?php echo $item['tipo']; ?></td>
                        <td>$<?php echo number_format($item['monto'], 2); ?></td>
                        <td><?php echo $item['motivo']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tfoot>
                        <tr>
                            <td colspan="2" style="text-align: right;"><strong>TOTAL DESVIACIÓN NO CONCILIADA:</strong></td>
                            <td colspan="2">$<?php echo number_format($total_transacciones_faltantes, 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            <?php else: ?>
                <p class="alerta-verde">No se encontraron transacciones faltantes en la liquidación.</p>
            <?php endif; ?>
        </section>

        <section class="cierre-final">
            <h2>✍️ Auditoría y Cierre Financiero</h2>
            <div class="recomendacion <?php echo $clase_alerta; ?>">
                <strong>ACCIÓN REQUERIDA:</strong> <?php echo $recomendacion; ?>
            </div>
            
            <p><strong>Comentarios del Contador:</strong> ____________________________________________________________________</p>
            <p><strong>Estado del Archivo:</strong> Extracto Bancario (D2/D3) adjunto como soporte.</p>

            <div class="firmas">
                <div class="firma-box">
                    <span>_________________________</span>
                    <p>Firma del Contador/Analista Financiero</p>
                </div>
                <div class="firma-box">
                    <span>_________________________</span>
                    <p>Firma del Jefe de Finanzas</p>
                </div>
            </div>
        </section>

        <p class="back-link"><a href="index.php">Volver al Dashboard</a></p>
    </div>
</body>
</html>