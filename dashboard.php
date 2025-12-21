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

// 2. OBTENER ESTADÍSTICAS RÁPIDAS (Solo si hay jornada abierta)
$ventas_hoy = 0;
$monto_hoy = 0.00;
$productos_criticos = 0;

if ($id_jornada_activa) {
    // CORRECCIÓN: Contar transacciones de la jornada actual que NO sean egresos
    $sql_conteo = "SELECT COUNT(id_registro) as total_c 
                   FROM transacciones 
                   WHERE id_jornada_fk = $id_jornada_activa AND es_egreso = 0";
    $res_c = $conn->query($sql_conteo);
    if ($res_c) {
        $ventas_hoy = $res_c->fetch_assoc()['total_c'] ?? 0;
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

// 3. ALERTAS DE INVENTARIO (Semáforo)
$res_inv = $conn->query("SELECT COUNT(*) as criticos FROM inventario WHERE stock_actual <= 5");
$productos_criticos = $res_inv->fetch_assoc()['criticos'] ?? 0;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SCL - Dashboard Ejecutivo</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px; }
        .card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-left: 5px solid #2563eb; }
        .card.critico { border-left-color: #e11d48; }
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

            <div class="card exito">
                <h3>Total Ingresos (Bruto)</h3>
                <div class="valor">$<?php echo number_format($monto_hoy, 2); ?></div>
                <p style="font-size: 0.8rem; color: #94a3b8;">Monto acumulado hoy</p>
            </div>

            <div class="card <?php echo ($productos_criticos > 0) ? 'critico' : ''; ?>">
                <h3>Alertas de Stock</h3>
                <div class="valor"><?php echo $productos_criticos; ?></div>
                <p style="font-size: 0.8rem; color: #94a3b8;">Productos por agotarse</p>
            </div>
        </div>

        <?php if (($_SESSION['user_role'] ?? '') != 'Vendedor'): ?>
        <div style="margin-top: 40px;">
            <h2>Control Administrativo</h2>
            <div style="display: flex; gap: 15px; margin-top: 15px;">
                <a href="reporte_a1.php" class="card" style="text-decoration: none; flex: 1; border-left: 5px solid #6366f1;">
                    <h3 style="color: #4338ca;">Reporte A.1</h3>
                    <p style="margin-top: 5px; color: #1e293b;">Conciliación de Caja</p>
                </a>
                <a href="gestion/form_inventario.php" class="card" style="text-decoration: none; flex: 1; border-left: 5px solid #f59e0b;">
                    <h3 style="color: #b45309;">Inventario</h3>
                    <p style="margin-top: 5px; color: #1e293b;">Gestión de Productos</p>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>