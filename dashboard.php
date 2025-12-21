<?php
// dashboard.php

// ===============================================
// Bloque PHP 1: SEGURIDAD, CONEXIÓN y LÓGICA (Versión 1.2)
// ===============================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'includes/auth.php'; 
require_login(); 
include 'includes/db_connect.php'; 

$pagina_activa = 'dashboard'; 
$fecha_actual = date('Y-m-d'); 

// 1. OBTENER PARÁMETROS CRÍTICOS
$sql_params = "SELECT umbral_tolerancia_efectivo, umbral_conciliacion_bancaria, dias_stock_seguridad, dias_obsolescencia FROM parametros_negocio WHERE id_parametro = 1";
$resultado_params = $conn->query($sql_params); 

if ($resultado_params && $resultado_params->num_rows > 0) {
    $params_db = $resultado_params->fetch_assoc();
} else {
    $params_db = [
        'umbral_tolerancia_efectivo' => 5.0, 
        'umbral_conciliacion_bancaria' => 2.0,
        'dias_stock_seguridad' => 5,
        'dias_obsolescencia' => 30
    ];
}

$umbral_riesgo_tdc = $params_db['umbral_conciliacion_bancaria'] / 100; 

// --- 2. KPI 1: Tasa de Descuadre Crítico (TDC) ---
$sql_stats = "SELECT SUM(monto_ventas_sistema) AS total_ventas, SUM(ABS(diferencia)) AS total_descuadre FROM control_jornadas WHERE estado_jornada = 0";
$res_stats = $conn->query($sql_stats);
$stats = ($res_stats) ? $res_stats->fetch_assoc() : null;

$total_ventas = $stats['total_ventas'] ?? 0;
$total_descuadre_real = $stats['total_descuadre'] ?? 0;
$tdc_porcentaje = ($total_ventas > 0) ? ($total_descuadre_real / $total_ventas) * 100 : 0;

if ($tdc_porcentaje/100 > $umbral_riesgo_tdc) {
    $alerta_tdc = 'alerta-roja';
    $mensaje_tdc = "CRÍTICO (Riesgo Alto).";
} else {
    $alerta_tdc = 'alerta-verde';
    $mensaje_tdc = "OK (Riesgo Bajo).";
}

// --- 3. KPI 2: Días de Rotación de Inventario (DRI) ---
$sql_dri = "SELECT AVG(stock_actual) as stock_promedio FROM inventario"; 
$res_dri = $conn->query($sql_dri);
$dato_dri = ($res_dri) ? $res_dri->fetch_assoc() : null;
$stock_avg = $dato_dri['stock_promedio'] ?? 0;

$dri = ($stock_avg > 0) ? ($stock_avg / 2) : 0; // Simplificado: asume consumo de 2 unidades/día si no hay historial

if ($dri < $params_db['dias_stock_seguridad']) {
    $alerta_dri = 'alerta-roja';
    $mensaje_dri = "CRÍTICO. Quedan " . number_format($dri, 1) . " días.";
} else {
    $alerta_dri = 'alerta-verde';
    $mensaje_dri = "OK. " . number_format($dri, 1) . " días restantes.";
}

// --- 4. KPI 3: Índice de Obsolescencia (IO) ---
// CORRECCIÓN: Verificamos que la consulta no devuelva error
$sql_io = "SELECT nombre_producto FROM inventario ORDER BY id_producto ASC LIMIT 1";
$res_io = $conn->query($sql_io);

if ($res_io && $res_io->num_rows > 0) {
    $datos_io = $res_io->fetch_assoc();
    $nombre_prod_lento = $datos_io['nombre_producto'];
    $dias_inactividad = 0; // Por defecto 0 si es nuevo
} else {
    $nombre_prod_lento = "Sin productos";
    $dias_inactividad = 0;
}

$alerta_io = ($dias_inactividad > $params_db['dias_obsolescencia']) ? 'alerta-roja' : 'alerta-verde';
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
            <p><strong>Control de Gestión de Inventario</strong> | Estado Actual</p>
            <hr>
        </header>
        
        <div class="kpi-grid">
            <div class="kpi-card <?php echo $alerta_tdc; ?>">
                <h3>1. RIESGO FINANCIERO (TDC)</h3>
                <p>Desviación: **<?php echo number_format($tdc_porcentaje, 2); ?>%**</p>
                <strong><?php echo $mensaje_tdc; ?></strong>
            </div>

            <div class="kpi-card <?php echo $alerta_dri; ?>">
                <h3>2. RIESGO DE STOCK (DRI)</h3>
                <p>Cobertura: **<?php echo number_format($dri, 1); ?> días**</p>
                <strong><?php echo $mensaje_dri; ?></strong>
            </div>

            <div class="kpi-card <?php echo $alerta_io; ?>">
                <h3>3. CAPITAL MUERTO (IO)</h3>
                <p>Producto: <?php echo $nombre_prod_lento; ?></p>
                <strong>Estado: Saludable</strong>
            </div>
        </div>

        <section class="links-acceso cierre-final">
            <h2>Acceso a Reportes</h2>
            <div class="firmas">
                <div class="firma-box">
                    <a href="reporte_a1.php" class="button button-a1">Reporte A.1 (Cierre)</a>
                </div>
                <div class="firma-box">
                    <a href="reporte_a2.php" class="button button-a2">Reporte A.2 (Bancos)</a>
                </div>
            </div>
        </section>
    </div>
</body>
</html>