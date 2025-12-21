<?php
// gestion/form_ventas.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../includes/auth.php'; 
require_login(); 
include '../includes/db_connect.php'; 

$id_usuario_sesion = $_SESSION['user_id'];
$mensaje = "";

// 1. VERIFICAR SI HAY UNA JORNADA ABIERTA
$sql_jornada = "SELECT id_jornada FROM control_jornadas WHERE estado_jornada = 1 LIMIT 1";
$res_jornada = $conn->query($sql_jornada);
$jornada = $res_jornada->fetch_assoc();

// 2. PROCESAR LA VENTA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($jornada)) {
    $id_producto = intval($_POST['id_producto']);
    $cantidad = intval($_POST['cantidad']);
    $tipo_cobro = $_POST['tipo_cobro'];
    $id_jornada_activa = $jornada['id_jornada'];

    // Obtener datos del producto (Precio y Stock actual)
    $res_prod = $conn->query("SELECT precio_venta, stock_actual, nombre_producto FROM inventario WHERE id_producto = $id_producto");
    $prod = $res_prod->fetch_assoc();

    if ($prod && $prod['stock_actual'] >= $cantidad) {
        $monto_total = $prod['precio_venta'] * $cantidad;
        $fecha_hoy = date('Y-m-d');

        // A. Insertar la Transacción
        $sql_trans = "INSERT INTO transacciones (id_jornada_fk, fecha_venta, monto_venta, tipo_cobro, es_egreso, id_usuario_fk) 
                      VALUES ($id_jornada_activa, '$fecha_hoy', $monto_total, '$tipo_cobro', 0, $id_usuario_sesion)";
        
        if ($conn->query($sql_trans)) {
            $id_venta = $conn->insert_id;

            // B. Insertar el Detalle de la Venta
            $conn->query("INSERT INTO detalle_transaccion (id_transaccion_fk, id_producto_fk, cantidad, precio_unitario_venta, subtotal) 
                          VALUES ($id_venta, $id_producto, $cantidad, {$prod['precio_venta']}, $monto_total)");

            // C. Descontar Stock del Inventario
            $conn->query("UPDATE inventario SET stock_actual = stock_actual - $cantidad WHERE id_producto = $id_producto");

            $mensaje = "<div class='alerta-verde'>✅ Venta de {$prod['nombre_producto']} registrada ($$monto_total)</div>";
        }
    } else {
        $mensaje = "<div class='alerta-roja'>❌ Error: Stock insuficiente o producto no encontrado.</div>";
    }
}

// 3. OBTENER PRODUCTOS PARA EL SELECT
$productos = $conn->query("SELECT id_producto, nombre_producto, precio_venta, stock_actual FROM inventario WHERE stock_actual > 0");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SCL - Registrar Venta</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .venta-container { max-width: 600px; margin: 30px auto; background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); border: 1px solid #ddd; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        select, input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        .btn-venta { width: 100%; padding: 15px; background: #2563eb; color: white; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; font-size: 1.1em; }
        .no-jornada { background: #fee2e2; color: #b91c1c; padding: 20px; text-align: center; border-radius: 10px; }
    </style>
</head>
<body>
    <?php include '../includes/menu.php'; ?>

    <div class="report-container">
        <h1>🛒 Registrar Venta</h1>
        <?php echo $mensaje; ?>

        <?php if (!$jornada): ?>
            <div class="no-jornada">
                <h3>⚠️ No hay una jornada abierta</h3>
                <p>Debe abrir la jornada laboral antes de poder registrar ventas.</p>
                <a href="form_jornada.php" class="button">Ir a Abrir Jornada</a>
            </div>
        <?php else: ?>
            <div class="venta-container">
                <form method="POST">
                    <div class="form-group">
                        <label>Seleccionar Producto:</label>
                        <select name="id_producto" required>
                            <?php while($p = $productos->fetch_assoc()): ?>
                                <option value="<?php echo $p['id_producto']; ?>">
                                    <?php echo $p['nombre_producto']; ?> - $<?php echo number_format($p['precio_venta'], 2); ?> (Stock: <?php echo $p['stock_actual']; ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Cantidad:</label>
                        <input type="number" name="cantidad" value="1" min="1" required>
                    </div>

                    <div class="form-group">
                        <label>Método de Pago:</label>
                        <select name="tipo_cobro" required>
                            <option value="Efectivo">Efectivo</option>
                            <option value="TPV">Punto de Venta (Tarjeta)</option>
                            <option value="Pago_Movil">Pago Móvil</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-venta">REGISTRAR VENTA</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>