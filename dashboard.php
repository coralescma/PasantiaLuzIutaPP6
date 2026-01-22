<?php
// dashboard.php - Panel Principal del Sistema SCL
ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'includes/auth.php';
require_login(); 
include 'includes/db_connect.php';

$pagina_activa = 'inicio';

// 1. OBTENER ESTADO DE LA JORNADA
$id_jornada_activa = obtenerJornadaActiva($conn);

// 2. OBTENER ESTADÍSTICAS RÁPIDAS
$ventas_hoy = 0;
$monto_hoy = 0.00;
$egresos_hoy = 0; 
$productos_criticos = 0;
$productos_agotados = 0; 

// INICIALIZACIÓN DE VARIABLES DE INVENTARIO (Para evitar el error de "Undefined variable")
$inversion = 0;
$venta_est = 0;
$utilidad_est = 0;

if ($id_jornada_activa) {
    // CORRECCIÓN: Contar transacciones de la jornada actual que NO sean egresos
    $sql_conteo = "SELECT COUNT(id_registro) as total_c 
                   FROM transacciones 
                   WHERE id_jornada_fk = $id_jornada_activa AND es_egreso = 0";
    $res_c = $conn->query($sql_conteo);
    if ($res_c) {
        $ventas_hoy = $res_c->fetch_assoc()['total_c'] ?? 0;
    }

    // NUEVA CONSULTA: Contar transacciones que SÍ sean egresos
    $sql_egr_count = "SELECT COUNT(id_registro) as total_e 
                      FROM transacciones 
                      WHERE id_jornada_fk = $id_jornada_activa AND es_egreso = 1";
    $res_e = $conn->query($sql_egr_count);
    if ($res_e) {
        $egresos_hoy = $res_e->fetch_assoc()['total_e'] ?? 0;
    }

    // CORRECCIÓN: Sumar montos desde 'detalle_pago' vinculados a la jornada activa
    $sql_monto = "SELECT SUM(dp.monto_pago) as total_m 
                  FROM detalle_pago dp
                  JOIN transacciones t ON dp.id_transaccion_fk = t.id_registro
                  WHERE t.id_jornada_fk = $id_jornada_activa AND t.es_egreso = 0";
    $res_m = $conn->query($sql_monto);
    if ($res_m) {
        $monto_hoy = $res_m->fetch_assoc()['total_m'] ?? 0.00;
    }
}

// --- LÓGICA DE VALORIZACIÓN: FUERA DEL IF PARA QUE FUNCIONE SIEMPRE ---
$sql_valorizacion = "SELECT 
    SUM(stock_actual * costo_unitario) as inversion_total,
    SUM(stock_actual * costo_unitario * (1 + (SELECT COALESCE(margen_ganancia_estandar, 30) / 100 FROM parametros_negocio LIMIT 1))) as venta_proyectada
    FROM inventario";
$res_val = $conn->query($sql_valorizacion);
if ($res_val) {
    $data_val = $res_val->fetch_assoc();
    $inversion = $data_val['inversion_total'] ?? 0;
    $venta_est = $data_val['venta_proyectada'] ?? 0;
    $utilidad_est = $venta_est - $inversion;
}

// 3. ALERTAS DE INVENTARIO (Semáforo)
$res_inv = $conn->query("SELECT COUNT(*) as criticos FROM inventario WHERE stock_actual <= 5 AND stock_actual > 0");
$productos_criticos = $res_inv->fetch_assoc()['criticos'] ?? 0;

// NUEVA CONSULTA: PRODUCTOS AGOTADOS (Stock 0)
$res_agotados = $conn->query("SELECT COUNT(*) as agotados FROM inventario WHERE stock_actual <= 0");
$productos_agotados = $res_agotados->fetch_assoc()['agotados'] ?? 0;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SCL - Dashboard Ejecutivo</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px; }
        .card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-left: 5px solid #2563eb; }
        .card.critico { border-left-color: #e11d48; }
        .card.agotado { border-left-color: #475569; background: #f1f5f9; }
        .card.exito { border-left-color: #10b981; }
        .card h3 { margin: 0; color: #64748b; font-size: 0.9rem; text-transform: uppercase; }
        .card .valor { font-size: 1.8rem; font-weight: bold; margin-top: 10px; color: #1e293b; }
        .status-bar { background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #e2e8f0; }
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: bold; }
        .badge-open { background: #dcfce7; color: #166534; }
        .badge-closed { background: #fee2e2; color: #991b1b; }
        .btn-action { display: inline-block; padding: 12px 24px; background: #2563eb; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; transition: 0.3s; }
        .btn-action:hover { background: #1d4ed8; }
    </style>
</head>
<body>

    <?php include 'includes/menu.php'; ?>

    <div style="padding: 30px; max-width: 1200px; margin: 0 auto;">
        
        <header style="margin-bottom: 30px;">
            <h1>Bienvenido, <?php echo htmlspecialchars($_SESSION['user_full_name'] ?? 'Usuario'); ?></h1>
            <p style="color: #64748b;">Rol: <strong><?php echo $_SESSION['user_role'] ?? 'Invitado'; ?></strong> | <?php echo date('d/m/Y'); ?></p>
        </header>

        <div class="status-bar">
            <div>
                <span>Estado de Caja: </span>
                <?php if ($id_jornada_activa): ?>
                    <span class="badge badge-open">● JORNADA ABIERTA (#<?php echo $id_jornada_activa; ?>)</span>
                <?php else: ?>
                    <span class="badge badge-closed">○ JORNADA CERRADA</span>
                <?php endif; ?>
            </div>
            <div>
                <?php if ($id_jornada_activa): ?>
                    <a href="gestion/form_transacciones.php" class="btn-action">🛒 IR A VENTAS (F8)</a>
                    <?php if (($_SESSION['user_role'] ?? '') == 'Supervisor' || ($_SESSION['user_role'] ?? '') == 'Administrador'): ?>
                        <a href="gestion/form_cierre_caja.php" style="color: #e11d48; margin-left: 15px; font-weight: bold;">Cerrar Jornada</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="gestion/form_jornada.php" class="btn-action" style="background: #10b981;">🟢 ABRIR JORNADA</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <h3>Ventas Realizadas</h3>
                <div class="valor"><?php echo $ventas_hoy; ?></div>
                <p style="font-size: 0.8rem; color: #94a3b8;">En la jornada actual</p>
            </div>

            <div class="card" style="border-left-color: #f59e0b;">
                <h3>Egresos</h3>
                <div class="valor"><?php echo $egresos_hoy; ?></div>
                <p style="font-size: 0.8rem; color: #94a3b8;">Operaciones especiales</p>
            </div>

            <div class="card exito">
                <h3>Total Ingresos (Bruto)</h3>
                <div class="valor">Bs <?php echo number_format($monto_hoy, 2); ?></div>
                <p style="font-size: 0.8rem; color: #94a3b8;">Monto acumulado hoy</p>
            </div>

            <div class="card <?php echo ($productos_criticos > 0) ? 'critico' : ''; ?>">
                <h3>Alertas de Stock</h3>
                <div class="valor"><?php echo $productos_criticos; ?></div>
                <p style="font-size: 0.8rem; color: #94a3b8;">Productos por agotarse</p>

                <?php if ($productos_criticos > 0): ?>
                    <div style="margin-top: 10px; border-top: 1px solid #fee2e2; pt: 10px;">
                        <ul style="margin: 0; padding: 0; list-style: none; max-height: 120px; overflow-y: auto;">
                            <?php
                            $res_bajos = $conn->query("SELECT nombre_producto, stock_actual FROM inventario WHERE stock_actual <= 5 AND stock_actual > 0 ORDER BY stock_actual ASC LIMIT 10");
                            while($p_bajo = $res_bajos->fetch_assoc()):
                            ?>
                                <li style="font-size: 0.75rem; color: #b91c1c; padding: 4px 0; display: flex; justify-content: space-between;">
                                    <span><?php echo htmlspecialchars($p_bajo['nombre_producto']); ?></span>
                                    <strong><?php echo $p_bajo['stock_actual']; ?></strong>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card agotado">
                <h3>Sin Existencia</h3>
                <div class="valor"><?php echo $productos_agotados; ?></div>
                <p style="font-size: 0.8rem; color: #64748b;">Productos con stock 0</p>

                <?php if ($productos_agotados > 0): ?>
                    <div style="margin-top: 10px; border-top: 1px solid #cbd5e1; pt: 10px;">
                        <ul style="margin: 0; padding: 0; list-style: none; max-height: 120px; overflow-y: auto;">
                            <?php
                            $res_sin_stock = $conn->query("SELECT nombre_producto FROM inventario WHERE stock_actual <= 0 ORDER BY nombre_producto ASC LIMIT 10");
                            while($p_sin = $res_sin_stock->fetch_assoc()):
                            ?>
                                <li style="font-size: 0.75rem; color: #334155; padding: 4px 0; display: flex; justify-content: space-between;">
                                    <span><?php echo htmlspecialchars($p_sin['nombre_producto']); ?></span>
                                    <strong style="color: #64748b;">0</strong>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <h2 style="margin-top: 30px;">Análisis de Inversión (Inventario)</h2>

        <div style="display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: nowrap;">
            
            <div class="card" style="flex: 1; border-left: 5px solid #3b82f6; min-width: 200px;">
                <span style="color: #64748b; font-size: 0.85rem; font-weight: bold; text-transform: uppercase;">Capital Invertido</span>
                <div style="font-size: 1.6rem; font-weight: bold; color: #1e293b; margin-top: 5px;">
                    Bs <?php echo number_format($inversion, 2); ?>
                </div>
                <p style="font-size: 0.75rem; color: #94a3b8; margin-top: 5px;">Costo total en stock</p>
            </div>

            <div class="card" style="flex: 1; border-left: 5px solid #10b981; min-width: 200px;">
                <span style="color: #64748b; font-size: 0.85rem; font-weight: bold; text-transform: uppercase;">Retorno Estimado</span>
                <div style="font-size: 1.6rem; font-weight: bold; color: #059669; margin-top: 5px;">
                    Bs <?php echo number_format($venta_est, 2); ?>
                </div>
                <p style="font-size: 0.75rem; color: #94a3b8; margin-top: 5px;">Valor de venta proyectado</p>
            </div>

            <div class="card" style="flex: 1; border-left: 5px solid #f59e0b; min-width: 200px;">
                <span style="color: #64748b; font-size: 0.85rem; font-weight: bold; text-transform: uppercase;">Ganancia Proyectada</span>
                <div style="font-size: 1.6rem; font-weight: bold; color: #d97706; margin-top: 5px;">
                    Bs <?php echo number_format($utilidad_est, 2); ?>
                </div>
                <p style="font-size: 0.75rem; color: #94a3b8; margin-top: 5px;">Utilidad bruta total</p>
            </div>

        </div>

        <?php if (($_SESSION['user_role'] ?? '') != 'Vendedor'): ?>
        <div style="margin-top: 40px;">
            </div>
        <?php endif; ?>
    </div>
</body>
</html>