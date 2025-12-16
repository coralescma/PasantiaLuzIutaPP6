<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'includes/auth.php';
require_login();
include 'includes/db_connect.php';

// --- 1. KPI DATA - TRANSACCIONES (VENTAS Y EGRESOS) ---
$sql_ops = "SELECT 
    COUNT(id_transaccion) as total_ops,
    SUM(CASE WHEN es_egreso = 0 THEN monto_venta ELSE 0 END) as ingresos_brutos,
    SUM(CASE WHEN es_egreso = 1 THEN monto_venta ELSE 0 END) as egresos_totales,
    AVG(CASE WHEN es_egreso = 0 THEN monto_venta ELSE NULL END) as ticket_promedio
    FROM transacciones";
$res_ops_query = $conn->query($sql_ops);
$ops = ($res_ops_query) ? $res_ops_query->fetch_assoc() : ['total_ops'=>0, 'ingresos_brutos'=>0, 'egresos_totales'=>0, 'ticket_promedio'=>0];

// --- 2. KPI DATA - JORNADAS (RIESGO Y ERRORES) ---
$sql_jornadas = "SELECT 
    COUNT(id_jornada) as total_jornadas,
    SUM(ABS(diferencia)) as suma_desviaciones,
    SUM(CASE WHEN estado_jornada = 2 AND (codigo_validacion = 'X' OR ABS(diferencia) > 5) THEN 1 ELSE 0 END) as cierres_criticos
    FROM control_jornadas";
$res_jor_query = $conn->query($sql_jornadas);
$jor = ($res_jor_query) ? $res_jor_query->fetch_assoc() : ['total_jornadas'=>0, 'suma_desviaciones'=>0, 'cierres_criticos'=>0];

// --- 3. CÁLCULOS LÓGICOS DE LOS 9 KPIs ---
$ingresos = $ops['ingresos_brutos'] ?? 0;
$egresos = $ops['egresos_totales'] ?? 0;

$kpi1_riesgo = ($ingresos > 0) ? (($jor['suma_desviaciones'] ?? 0) / $ingresos) * 100 : 0;
$kpi2_error_rate = ($jor['total_jornadas'] > 0) ? (($jor['cierres_criticos'] ?? 0) / $jor['total_jornadas']) * 100 : 0;
$kpi3_impacto_egresos = ($ingresos > 0) ? ($egresos / $ingresos) * 100 : 0;
$kpi4_ticket_promedio = $ops['ticket_promedio'] ?? 0;
$kpi5_volumen = $ops['total_ops'] ?? 0;
$kpi6_eficiencia = 100 - $kpi2_error_rate; // Inverso de la tasa de error
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SCL - Dashboard Ejecutivo</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .grid-dashboard { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; padding: 10px; }
        .kpi-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 6px solid #ccc; }
        .kpi-card h3 { font-size: 0.9rem; color: #666; margin: 0; text-transform: uppercase; }
        .kpi-card .valor { font-size: 1.8rem; font-weight: bold; margin: 10px 0; display: block; }
        .kpi-card .footer { font-size: 0.8rem; color: #888; }
        /* Colores de estado */
        .border-red { border-left-color: #e74c3c; }
        .border-green { border-left-color: #2ecc71; }
        .border-blue { border-left-color: #3498db; }
        .border-orange { border-left-color: #f39c12; }
    </style>
</head>
<body>
    <?php include 'includes/menu.php'; ?>

    <div class="report-container">
        <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1>🚀 Dashboard Ejecutivo SCL</h1>
            <div style="background: #eee; padding: 10px; border-radius: 5px; font-size: 0.9em;">Fecha: <?php echo date('d/m/Y'); ?></div>
        </header>

        <div class="grid-dashboard">
            <div class="kpi-card <?php echo ($kpi1_riesgo > 2) ? 'border-red' : 'border-green'; ?>">
                <h3>1. Riesgo Financiero (TDC)</h3>
                <span class="valor"><?php echo number_format($kpi1_riesgo, 2); ?>%</span>
                <p class="footer">Meta: < 2.0% de Ingresos</p>
            </div>

            <div class="kpi-card <?php echo ($kpi2_error_rate > 10) ? 'border-orange' : 'border-blue'; ?>">
                <h3>2. Tasa de Error en Cierre</h3>
                <span class="valor"><?php echo number_format($kpi2_error_rate, 1); ?>%</span>
                <p class="footer">Basado en Cierres Código X</p>
            </div>

            <div class="kpi-card border-blue">
                <h3>3. Impacto de Egresos</h3>
                <span class="valor"><?php echo number_format($kpi3_impacto_egresos, 1); ?>%</span>
                <p class="footer">Egresos vs. Ingresos Brutos</p>
            </div>

            <div class="kpi-card border-blue">
                <h3>4. Ticket Promedio</h3>
                <span class="valor">$<?php echo number_format($kpi4_ticket_promedio, 2); ?></span>
                <p class="footer">Promedio por Venta</p>
            </div>

            <div class="kpi-card border-blue">
                <h3>5. Volumen de Operaciones</h3>
                <span class="valor"><?php echo $kpi5_volumen; ?></span>
                <p class="footer">Transacciones totales registradas</p>
            </div>

            <div class="kpi-card border-green">
                <h3>6. Eficiencia de Cierre</h3>
                <span class="valor"><?php echo number_format($kpi6_eficiencia, 1); ?>%</span>
                <p class="footer">Cierres exitosos sin desviaciones</p>
            </div>

            <div class="kpi-card border-green">
                <h3>7. Ingresos Brutos</h3>
                <span class="valor">$<?php echo number_format($ingresos, 2); ?></span>
                <p class="footer">Total recaudado en el sistema</p>
            </div>

            <div class="kpi-card border-red">
                <h3>8. Egresos Totales</h3>
                <span class="valor">$<?php echo number_format($egresos, 2); ?></span>
                <p class="footer">Dinero fuera del flujo</p>
            </div>

            <div class="kpi-card border-orange">
                <h3>9. Margen Operativo</h3>
                <span class="valor">$<?php echo number_format($ingresos - $egresos, 2); ?></span>
                <p class="footer">Remanente de Caja Actual</p>
            </div>
        </div>
    </div>
</body>
</html>