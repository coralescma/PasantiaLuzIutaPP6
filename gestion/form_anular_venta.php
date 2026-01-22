<?php
// gestion/form_anular_venta.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../includes/auth.php'; 
require_login(['Administrador', 'Gerente']); 
include '../includes/db_connect.php'; 

// Función para obtener la jornada activa
if (!function_exists('obtenerJornadaActiva')) {
    function obtenerJornadaActiva($conn) {
        $res = $conn->query("SELECT id_jornada FROM jornadas WHERE estado = 'Abierta' LIMIT 1");
        if ($res && $fila = $res->fetch_assoc()) {
            return $fila['id_jornada'];
        }
        return false;
    }
}

$mensaje = "";
$clase_mensaje = "";
$venta_encontrada = null;
$detalles_venta = [];

// 0. Identificar Jornada
$id_jornada = obtenerJornadaActiva($conn);

// 1. Lógica de Anulación (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_confirmar_anulacion'])) {
    $id_trans = intval($_POST['id_transaccion_anular']);
    $motivo = $conn->real_escape_string($_POST['motivo_anulacion']);
    
    $conn->begin_transaction();
    try {
        // Devolver stock
        $res_prod = $conn->query("SELECT id_producto_fk, cantidad FROM detalle_transaccion WHERE id_transaccion_fk = $id_trans");
        while ($item = $res_prod->fetch_assoc()) {
            $id_p = $item['id_producto_fk'];
            $cant = $item['cantidad'];
            $conn->query("UPDATE inventario SET stock_actual = stock_actual + $cant WHERE id_producto = $id_p");
        }

        // Cambiar estado en tabla transacciones
        $conn->query("UPDATE transacciones SET estado = 'Anulada', motivo_anulacion = '$motivo' WHERE id_transaccion = $id_trans");

        $conn->commit();
        $mensaje = "✅ Venta #$id_trans anulada correctamente.";
        $clase_mensaje = "alerta-verde";
    } catch (Exception $e) {
        $conn->rollback();
        $mensaje = "❌ Error: " . $e->getMessage();
        $clase_mensaje = "alerta-roja";
    }
}

// 2. Cargar datos de la venta seleccionada (GET)
$id_seleccionado = $_GET['id_venta'] ?? null;
if ($id_seleccionado) {
    $id_v = intval($id_seleccionado);
    $res_v = $conn->query("SELECT t.*, u.full_name FROM transacciones t JOIN usuarios u ON t.id_usuario_cajero_fk = u.id_usuario WHERE t.id_transaccion = $id_v");
    $venta_encontrada = $res_v->fetch_assoc();

    if ($venta_encontrada) {
        $res_d = $conn->query("SELECT d.*, i.nombre_producto FROM detalle_transaccion d JOIN inventario i ON d.id_producto_fk = i.id_producto WHERE d.id_transaccion_fk = $id_v");
        while($row = $res_d->fetch_assoc()) { $detalles_venta[] = $row; }
    }
}

// 3. Obtener lista de ventas de la tabla transacciones para el SELECT
$ventas_lista = [];
if ($id_jornada) {
    $id_j_int = intval($id_jornada);
    // Solo traemos ventas (es_egreso = 0) que estén Activas
    $sql_opciones = "SELECT id_transaccion, fecha_transaccion, monto_total 
                     FROM transacciones 
                     WHERE id_jornada_fk = $id_j_int 
                     AND es_egreso = 0 
                     AND estado = 'Activa' 
                     ORDER BY id_transaccion DESC";
    $res_op = $conn->query($sql_opciones);
    if ($res_op) {
        while($row = $res_op->fetch_assoc()) { $ventas_lista[] = $row; }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Anular Venta - SCL</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .box { max-width: 700px; margin: 40px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        select { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 16px; background-color: #f8fafc; }
        .detalle-venta { background: #f1f5f9; padding: 20px; border-radius: 8px; margin-top: 20px; border-left: 5px solid #64748b; }
        table { width: 100%; margin-top: 15px; border-collapse: collapse; }
        th { text-align: left; border-bottom: 2px solid #cbd5e1; padding: 8px; }
        td { padding: 8px; border-bottom: 1px solid #e2e8f0; }
        .btn-anular { background: #dc2626; color: white; border: none; padding: 15px; width: 100%; border-radius: 8px; font-weight: bold; cursor: pointer; margin-top: 20px; }
        .btn-anular:hover { background: #b91c1c; }
        .alerta-verde { color: #15803d; background: #dcfce7; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <?php include '../includes/menu.php'; ?>

    <div class="box">
        <h1>Anulación de Ventas</h1>
        
        <?php if ($mensaje): ?>
            <div class="<?php echo $clase_mensaje; ?>"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <form method="GET">
            <div class="form-group">
                <label><strong>Seleccione una venta de la jornada:</strong></label>
                <select name="id_venta" onchange="this.form.submit()">
                    <option value="">-- Seleccione el Ticket --</option>
                    <?php foreach ($ventas_lista as $v): ?>
                        <option value="<?php echo $v['id_transaccion']; ?>" <?php echo ($id_seleccionado == $v['id_transaccion']) ? 'selected' : ''; ?>>
                            #<?php echo $v['id_transaccion']; ?> | Hora: <?php echo date('H:i', strtotime($v['fecha_transaccion'])); ?> | Total: Bs <?php echo number_format($v['monto_total'], 2); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php if ($venta_encontrada): ?>
            <div class="detalle-venta">
                <h3>Resumen del Ticket #<?php echo $venta_encontrada['id_transaccion']; ?></h3>
                <p><strong>Cajero:</strong> <?php echo $venta_encontrada['full_name']; ?></p>
                
                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cant.</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalles_venta as $d): ?>
                        <tr>
                            <td><?php echo $d['nombre_producto']; ?></td>
                            <td><?php echo $d['cantidad']; ?></td>
                            <td>Bs <?php echo number_format($d['cantidad'] * $d['precio_venta'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <form method="POST">
                    <input type="hidden" name="id_transaccion_anular" value="<?php echo $venta_encontrada['id_transaccion']; ?>">
                    <label style="display:block; margin-top:15px;"><strong>Motivo de anulación:</strong></label>
                    <textarea name="motivo_anulacion" required style="width:100%; height:80px; padding:10px; margin-top:5px; border-radius:8px; border:1px solid #cbd5e1;"></textarea>
                    
                    <button type="submit" name="btn_confirmar_anulacion" class="btn-anular">CONFIRMAR ANULACIÓN Y DEVOLVER STOCK</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>