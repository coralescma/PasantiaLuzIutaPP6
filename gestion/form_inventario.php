<?php
// gestion/form_inventario.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../includes/auth.php'; 
require_login(['Administrador', 'Gerente', 'Supervisor']); 

include '../includes/db_connect.php'; 

$mensaje = "";
$clase_mensaje = "";

// --- Obtener margen de ganancia ---
$res_margen = $conn->query("SELECT margen_ganancia_estandar FROM parametros_negocio LIMIT 1");
$fila_margen = $res_margen->fetch_assoc();
$margen_porcentaje = $fila_margen ? $fila_margen['margen_ganancia_estandar'] : 0;

// --- Lógica: Actualizar Stock y COSTO (Actualizada) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_actualizar_stock'])) {
    $id_p = intval($_POST['id_producto_edit']);
    $nuevo_stock = intval($_POST['nuevo_stock']);
    $nuevo_costo = floatval($_POST['nuevo_costo']); // Nuevo campo capturado
    
    $sql_update = "UPDATE inventario SET stock_actual = $nuevo_stock, costo_unitario = $nuevo_costo WHERE id_producto = $id_p";
    if ($conn->query($sql_update)) {
        $mensaje = "✅ Datos actualizados correctamente.";
        $clase_mensaje = "alerta-verde";
    } else {
        $mensaje = "❌ Error al actualizar: " . $conn->error;
        $clase_mensaje = "alerta-roja";
    }
}

// --- Lógica: Registrar Nuevo Producto ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_guardar'])) {
    $nombre = $conn->real_escape_string($_POST['nombre_producto']);
    $stock = intval($_POST['stock_actual']);
    $costo = floatval($_POST['costo_unitario']);
    $fecha_actual_sql = date('Y-m-d'); 

    $sql_ins = "INSERT INTO inventario (nombre_producto, stock_actual, costo_unitario, ultima_venta_fecha) 
                VALUES ('$nombre', $stock, $costo, '$fecha_actual_sql')";
    
    if ($conn->query($sql_ins)) {
        $mensaje = "✅ Producto registrado con éxito.";
        $clase_mensaje = "alerta-verde";
    } else {
        $mensaje = "❌ Error: " . $conn->error;
        $clase_mensaje = "alerta-roja";
    }
}

// --- Lógica: Eliminar Producto ---
if (isset($_GET['delete_id'])) {
    $id_del = intval($_GET['delete_id']);
    if ($conn->query("DELETE FROM inventario WHERE id_producto = $id_del")) {
        $mensaje = "✅ Producto eliminado.";
        $clase_mensaje = "alerta-verde";
    }
}

$resultado_inventario = $conn->query("SELECT * FROM inventario ORDER BY id_producto DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SCL - Gestión de Inventario</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .container { max-width: 1100px; margin: 20px auto; padding: 20px; }
        .grid-inv { display: grid; grid-template-columns: 1fr 3fr; gap: 20px; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        th { background: #f8fafc; }
        .alerta-verde { background: #dcfce7; color: #166534; padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .alerta-roja { background: #fee2e2; color: #991b1b; padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .btn-del { color: #ef4444; text-decoration: none; font-weight: bold; font-size: 0.9em; }
        .form-row-edit { display: flex; gap: 5px; align-items: center; }
        .input-inline { padding: 5px; border: 1px solid #cbd5e1; border-radius: 4px; text-align: center; }
        .w-stock { width: 60px; }
        .w-costo { width: 80px; }
        .btn-save-stock { background: #6366f1; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; }
        .txt-precio { color: #059669; font-weight: bold; }
    </style>
</head>
<body>
    <?php include '../includes/menu.php'; ?>

    <div class="container">
        <h1>📦 Gestión de Inventario</h1>

        <?php if($mensaje): ?>
            <div class="<?php echo $clase_mensaje; ?>"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <div class="grid-inv">
            <section class="card">
                <h3>Nuevo Producto</h3>
                <form method="POST">
                    <label>Nombre:</label>
                    <input type="text" name="nombre_producto" required style="width:100%; margin-bottom:10px; padding:8px;">
                    
                    <label>Stock Inicial:</label>
                    <input type="number" name="stock_actual" value="0" required style="width:100%; margin-bottom:10px; padding:8px;">
                    
                    <label>Costo Unitario (Bs):</label>
                    <input type="number" step="0.01" name="costo_unitario" required style="width:100%; margin-bottom:20px; padding:8px;">
                    
                    <button type="submit" name="btn_guardar" class="btn-login" style="width:100%;">Registrar</button>
                </form>
            </section>

            <section class="card">
                <h3>Listado de Existencias</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th width="80">Stock</th>
                            <th width="110">Costo (Bs)</th>
                            <th>P. Venta</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultado_inventario && $resultado_inventario->num_rows > 0): ?>
                            <?php while($row = $resultado_inventario->fetch_assoc()): 
                                $costo = $row['costo_unitario'];
                                $precio_venta = $costo + ($costo * ($margen_porcentaje / 100));
                            ?>
                            <tr>
                                <form method="POST">
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['nombre_producto']); ?></strong>
                                        <input type="hidden" name="id_producto_edit" value="<?php echo $row['id_producto']; ?>">
                                    </td>
                                    <td>
                                        <input type="number" name="nuevo_stock" value="<?php echo $row['stock_actual']; ?>" class="input-inline w-stock">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="nuevo_costo" value="<?php echo $costo; ?>" class="input-inline w-costo">
                                    </td>
                                    <td class="txt-precio">Bs <?php echo number_format($precio_venta, 2); ?></td>
                                    <td>
                                        <button type="submit" name="btn_actualizar_stock" class="btn-save-stock" title="Guardar cambios">💾</button>
                                        <a href="?delete_id=<?php echo $row['id_producto']; ?>" 
                                           onclick="return confirm('¿Eliminar producto?')" class="btn-del" style="margin-left:10px;">✕</a>
                                    </td>
                                </form>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align: center; color: gray;">No hay productos.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>
        </div>
    </div>
</body>
</html>