<?php
// gestion/form_inventario.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Incluir seguridad y conexión
include '../includes/auth.php'; 
require_login(); 

include '../includes/db_connect.php'; 

$mensaje = "";
$clase_mensaje = "";

// --- Lógica: Registrar Nuevo Producto (Ajustado a BD Antigua) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_guardar'])) {
    $nombre = $conn->real_escape_string($_POST['nombre_producto']);
    $stock = intval($_POST['stock_actual']);
    $costo = floatval($_POST['costo_unitario']);
    $fecha_actual_sql = date('Y-m-d'); // Para la columna ultima_venta_fecha o registro

    // SQL sin precio_venta
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
    $conn->query("DELETE FROM inventario WHERE id_producto = $id_del");
    header("Location: form_inventario.php?deleted=1");
    exit();
}

if (isset($_GET['deleted'])) {
    $mensaje = "✅ Producto eliminado.";
    $clase_mensaje = "alerta-verde";
}

// 2. Obtener lista de productos
$resultado_inventario = $conn->query("SELECT * FROM inventario ORDER BY id_producto DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SCL - Gestión de Inventario</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .form-box { background: white; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #ddd; }
        /* Reajustado a 3 columnas para que quepa mejor sin el precio */
        .grid-inputs { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 15px; }
        table { width: 100%; border-collapse: collapse; background: white; margin-top: 10px; }
        th, td { padding: 12px; border: 1px solid #eee; text-align: left; }
        th { background: #f8fafc; color: #334155; }
        .btn-add { background: #2563eb; color: white; padding: 10px; border: none; border-radius: 4px; cursor: pointer; width: 100%; height: 45px; margin-top: 24px; font-weight: bold;}
        .btn-del { color: #ef4444; text-decoration: none; font-weight: bold; }
        .alerta-verde { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alerta-roja { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body>
    <?php include '../includes/menu.php'; ?>

    <div class="report-container">
        <h1>📦 Gestión de Inventario</h1>

        <?php if ($mensaje): ?>
            <div class="<?php echo $clase_mensaje; ?>" style="padding:15px; margin-bottom:20px; border-radius:5px;">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <div class="form-box">
            <h3>Registrar Nuevo Producto</h3>
            <form method="POST">
                <div class="grid-inputs">
                    <div>
                        <label>Nombre del Producto:</label>
                        <input type="text" name="nombre_producto" required style="width:100%; padding:10px; border: 1px solid #ccc; border-radius:4px;">
                    </div>
                    <div>
                        <label>Stock Inicial:</label>
                        <input type="number" name="stock_actual" value="0" style="width:100%; padding:10px; border: 1px solid #ccc; border-radius:4px;">
                    </div>
                    <div>
                        <label>Costo Unitario ($):</label>
                        <input type="number" step="0.01" name="costo_unitario" required style="width:100%; padding:10px; border: 1px solid #ccc; border-radius:4px;">
                    </div>
                </div>
                <button type="submit" name="btn_guardar" class="btn-add">GUARDAR PRODUCTO EN BASE DE DATOS</button>
            </form>
        </div>

        <section>
            <h3>Existencias actuales</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Producto</th>
                        <th>Stock Actual</th>
                        <th>Costo Unitario</th>
                        <th>Última Actividad</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resultado_inventario && $resultado_inventario->num_rows > 0): ?>
                        <?php while($row = $resultado_inventario->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id_producto']; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['nombre_producto']); ?></strong></td>
                            <td><?php echo $row['stock_actual']; ?></td>
                            <td>$<?php echo number_format($row['costo_unitario'], 2); ?></td>
                            <td><?php echo $row['ultima_venta_fecha'] ?? 'Sin datos'; ?></td>
                            <td>
                                <a href="?delete_id=<?php echo $row['id_producto']; ?>" 
                                   onclick="return confirm('¿Eliminar producto definitivamente?')" class="btn-del">Eliminar</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: gray;">No hay productos registrados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </div>
</body>
</html>