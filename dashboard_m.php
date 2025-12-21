<?php
// dashboard.php

// ===============================================
// Bloque PHP 1: SEGURIDAD, CONEXIÓN y LÓGICA (Versión 1.1)
// ===============================================

// HABILITAR REPORTE DE ERRORES (temporal)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'includes/auth.php'; 
require_login(); 
include 'includes/db_connect.php'; 

$pagina_activa = 'dashboard'; 
$fecha_actual = date('Y-m-d'); 

// 1. OBTENER PARÁMETROS CRÍTICOS (Desde la fila ID=1)
// Usamos los campos reales de la tabla parametros_negocio
$sql_params = "SELECT umbral_tolerancia_efectivo, umbral_conciliacion_bancaria FROM parametros_negocio WHERE id_parametro = 1";
$resultado_params = $conn->query($sql_params); 

if (!$resultado_params || $resultado_params->num_rows === 0) {
    // Si la tabla está vacía o hay error, usamos valores por defecto seguros
    $params_db = ['umbral_tolerancia_efectivo' => 5.0, 'umbral_conciliacion_bancaria' => 2.0];
} else {
    $params_db = $resultado_params->fetch_assoc();
}

// SIMULACIÓN DE PARÁMETROS NO ALMACENADOS EN LA DB (Por ahora, son fijos en el código)
// Usamos el umbral_conciliacion_bancaria del Reporte A.2 como la Tasa TDC
$umbral_riesgo_tdc = $params_db['umbral_conciliacion_bancaria'] / 100; // ej: 2.0% -> 0.02
$dias_stock_seguridad = 5; // Simulación: 5 días fijos
$dias_obsolescencia = 60; // Simulación: 60 días fijos

// --- 2. Cálculo del KPI 1: Tasa de Descuadre Crítico (TDC) ---
// La consulta asume que transacciones existe y tiene la columna es_egreso
$sql_total_ventas = "SELECT SUM(monto_venta) AS total_general FROM transacciones WHERE es_egreso = 0";
$resultado_ventas = $conn->query($sql_total_ventas);
$total_ventas = ($resultado_ventas && $resultado_ventas->num_rows > 0) ? $resultado_ventas->fetch_assoc()['total_general'] : 0;
$total_descuadre_critico_sim = 75.00; // Descuadre simulado

$tdc = ($total_ventas > 0) ? ($total_descuadre_critico_sim / $total_ventas) : 0;
$tdc_porcentaje = $tdc * 100;

if ($tdc > $umbral_riesgo_tdc) {
    $alerta_tdc = 'alerta-roja';
    $mensaje_tdc = "CRÍTICO (Riesgo Alto). Supera el umbral del " . ($umbral_riesgo_tdc * 100) . "%.";
} else {
    $alerta_tdc = 'alerta-verde';
    $mensaje_tdc = "OK (Riesgo Bajo).";
}


// --- 3. Cálculo del KPI 7: Días de Rotación de Inventario (DRI) ---
// Consulta asume que inventario existe
$sql_stock = "SELECT stock_actual FROM inventario WHERE id_producto = 1"; 
$resultado_stock = $conn->query($sql_stock);
$datos_stock = ($resultado_stock && $resultado_stock->num_rows > 0) ? $resultado_stock->fetch_assoc() : ['stock_actual' => 0];
$stock_actual = $datos_stock['stock_actual'] ?? 0;
$promedio_venta_diaria = 4; // Promedio simulado

$dri = ($promedio_venta_diaria > 0) ? ($stock_actual / $promedio_venta_diaria) : 0;

if ($dri < $dias_stock_seguridad) {
    $alerta_dri = 'alerta-roja';
    $mensaje_dri = "CRÍTICO (Quiebre Inminente). Quedan " . number_format($dri, 1) . " días. Comprar URGENTE.";
} else {
    $alerta_dri = 'alerta-verde';
    $mensaje_dri = "OK. " . number_format($dri, 1) . " días de stock restantes.";
}


// --- 4. Cálculo del KPI 8: Índice de Obsolescencia (IO) ---
// Consulta asume que inventario existe y tiene la columna ultima_venta_fecha
$sql_obsolescencia = "SELECT nombre_producto, ultima_venta_fecha FROM inventario WHERE id_producto = 2";
$resultado_obsolescencia = $conn->query($sql_obsolescencia);
$datos_obsolescencia = ($resultado_obsolescencia && $resultado_obsolescencia->num_rows > 0) ? $resultado_obsolescencia->fetch_assoc() : ['ultima_venta_fecha' => $fecha_actual, 'nombre_producto' => 'N/A'];
$fecha_ultima_venta = $datos_obsolescencia['ultima_venta_fecha'] ?? $fecha_actual;

$diff = date_diff(date_create($fecha_ultima_venta), date_create($fecha_actual));
$dias_inactividad = $diff->days;

if ($dias_inactividad > $dias_obsolescencia) {
    $alerta_io = 'alerta-roja';
    $mensaje_io = "ALERTA (Inventario Muerto). $dias_inactividad días sin venta. Liquidar.";
} else {
    $alerta_io = 'alerta-verde';
    $mensaje_io = "OK. $dias_inactividad días sin venta.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SCL - Dashboard Ejecutivo</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/menu.php'; ?> 
    
    <div class="report-container"> 
        <header class="report-header">
            <h1>🎯 DASHBOARD EJECUTIVO SCL</h1>
            <p><strong>Visión de Control, Riesgo y Gestión de Inventario</strong> | Periodo Acumulado</p>
            <hr>
        </header>
        
        <h2>Resumen de Riesgos (Alerta Semáforo)</h2>
        <p>El sistema detectó los siguientes riesgos basados en la data histórica y parámetros de configuración:</p>
        
        <div class="kpi-grid">
            <div class="kpi-card <?php echo $alerta_tdc; ?>">
                <h3>1. RIESGO FINANCIERO (TDC)</h3>
                <p>Métrica: **<?php echo number_format($tdc_porcentaje, 2); ?>%** de Ingresos totales son Desviaciones Críticas.</p>
                <strong><?php echo $mensaje_tdc; ?></strong>
            </div>

            <div class="kpi-card <?php echo $alerta_dri; ?>">
                <h3>2. RIESGO DE STOCK (DRI - Café)</h3>
                <p>Quedan **<?php echo number_format($dri, 1); ?> días** de stock de seguridad (Umbral: <?php echo $dias_stock_seguridad; ?> días).</p>
                <strong><?php echo $mensaje_dri; ?></strong>
            </div>

            <div class="kpi-card <?php echo $alerta_io; ?>">
                <h3>3. CAPITAL MUERTO (IO - Bebida X)</h3>
                <p>Producto lleva **<?php echo $dias_inactividad; ?> días** sin venta (Umbral: <?php echo $dias_obsolescencia; ?> días).</p>
                <strong><?php echo $mensaje_io; ?></strong>
            </div>
        </div>

        <section class="links-acceso cierre-final">
            <h2>Acceso a Documentación de Detalle</h2>
            <p>Para la auditoría y el archivo legal, acceda a los reportes firmados:</p>
            <div class="firmas">
                <div class="firma-box">
                    <a href="reporte_a1.php" class="button button-a1">Ver Reporte A.1 (Cierre Diario)</a>
                    <p>Auditoría Operacional y Conteo Físico</p>
                </div>
                <div class="firma-box">
                    <a href="reporte_a2.php" class="button button-a2">Ver Reporte A.2 (Conciliación Bancaria)</a>
                    <p>Auditoría Financiera y Reclamos Bancarios</p>
                </div>
            </div>
        </section>

    </div>
</body>
</html>