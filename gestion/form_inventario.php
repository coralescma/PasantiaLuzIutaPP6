<?php
// ===============================================
// Bloque PHP 1: SEGURIDAD y CONEXIÓN (Ruta Corregida)
// ===============================================

// 1. Incluir la seguridad y la sesión (auth.php)
include '../includes/auth.php'; 
// Protege la página y restringe el acceso solo a roles de control/administración.
// Un Cajero (rol 2) no debería poder eliminar o registrar costos.
require_login(['Administrador', 'Supervisor']); 

// 2. Conexión a DB (Asegura que $conn esté disponible)
include '../includes/db_connect.php'; 

$mensaje = "";
$clase_mensaje = "";

// Define la página activa para el menú (si se usa para highlighting)
$pagina_activa = 'gestion'; 


// --- Lógica de Procesamiento de Inserción (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre_producto = $conn->real_escape_string($_POST['nombre_producto']);
    $stock_actual = (int)$_POST['stock_actual'];
    $costo_unitario = (float)$_POST['costo_unitario'];
    $ultima_venta_fecha = $conn->real_escape_string($_POST['ultima_venta_fecha']);

    if (empty($nombre_producto) || $stock_actual < 0 || $costo_unitario <= 0) {
        $mensaje = "Error: Todos los campos obligatorios deben ser llenados correctamente.";
        $clase_mensaje = "alerta-roja";
    } else {
        $sql = "INSERT INTO inventario (nombre_producto, stock_actual, costo_unitario, ultima_venta_fecha) 
                 VALUES ('$nombre_producto', $stock_actual, $costo_unitario, '$ultima_venta_fecha')";

        if ($conn->query($sql) === TRUE) {
            $mensaje = "✅ Producto '$nombre_producto' agregado exitosamente.";
            $clase_mensaje = "alerta-verde";
        } else {
            $mensaje = "Error al agregar el producto: " . $conn->error;
            $clase_mensaje = "alerta-roja";
        }
    }
}

// --- Lógica de Eliminación (GET) ---
if (isset($_GET['delete_id'])) {
    // Solo permitir la eliminación al Administrador
    if ($_SESSION['user_role'] != 'Administrador') {
        $mensaje = "🚫 ACCESO DENEGADO. Solo el Administrador puede eliminar registros de inventario.";
        $clase_mensaje = "alerta-roja";
    } else {
        $delete_id = (int)$_GET['delete_id'];
        
        // Ejecutar la eliminación
        // IMPORTANTE: Asegurarse de que no haya claves foráneas que lo impidan (si el producto está en detalle_transaccion)
        $sql_delete = "DELETE FROM inventario WHERE id_producto = $delete_id";
        if ($conn->query($sql_delete) === TRUE) {
            $mensaje = "✅ Producto ID **$delete_id** eliminado exitosamente.";
            $clase_mensaje = "alerta-verde";
            // Redirigir para limpiar el parámetro GET de la URL
            // Nota: urlencode no es estrictamente necesario aquí si no pasas variables GET, pero es una buena práctica.
            header("Location: form_inventario.php"); 
            exit();
        } else {
            // El error más común aquí es la restricción de clave foránea.
            $mensaje = "Error al eliminar el producto. Podría estar asociado a transacciones existentes: " . $conn->error;
            $clase_mensaje = "alerta-roja";
        }
    }
}

// Lógica para mostrar mensaje después de la redirección
// Recuperamos el mensaje si existe, aunque no usamos el `urlencode` en la redirección.
// Si deseas mostrar el mensaje después de la redirección de eliminación, usa sesiones. 
// Por simplicidad, volvemos a la lógica de POST/GET directo.


// --- Lógica para mostrar el inventario actual ---
$sql_inventario = "SELECT id_producto, nombre_producto, stock_actual, costo_unitario, ultima_venta_fecha FROM inventario ORDER BY id_producto DESC";
$resultado_inventario = $conn->query($sql_inventario);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Inventario (D5)</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <?php include '../includes/menu.php'; ?>
    <div class="report-container">
        <header class="report-header">
            <h1>📦 Gestión de Inventario (D5)</h1>
            <p>Control de Stock Actual y Costo Unitario para el cálculo de KPIs (DRI e IO).</p>
        </header>

        <?php if (!empty($mensaje)): ?>
            <div class="recomendacion <?php echo $clase_mensaje; ?>"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <section class="form-section">
            <h2>➕ Registrar Nuevo Producto / Stock</h2>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="data-form">
                
                <label for="nombre_producto">Nombre del Producto:</label>
                <input type="text" id="nombre_producto" name="nombre_producto" required>

                <label for="stock_actual">Stock Inicial/Actual (Unidades):</label>
                <input type="number" id="stock_actual" name="stock_actual" required min="0">

                <label for="costo_unitario">Costo Unitario ($):</label>
                <input type="number" id="costo_unitario" name="costo_unitario" step="0.01" required min="0.01">

                <label for="ultima_venta_fecha">Fecha de Última Venta (Para KPI 8 - Obsolescencia):</label>
                <input type="date" id="ultima_venta_fecha" name="ultima_venta_fecha" value="<?php echo date('Y-m-d'); ?>" required>

                <button type="submit" class="button button-a1">Guardar Producto</button>
            </form>
        </section>

        <section class="current-inventory">
            <h2>🛒 Inventario Actual Registrado</h2>
            <?php if ($resultado_inventario->num_rows > 0): ?>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Producto</th>
                        <th>Stock Actual</th>
                        <th>Costo Unitario ($)</th>
                        <th>Fecha Última Venta</th>
                        <th style="text-align: center;">Acciones</th> </tr>
                    <?php while($row = $resultado_inventario->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id_producto']; ?></td>
                        <td><?php echo $row['nombre_producto']; ?></td>
                        <td><?php echo $row['stock_actual']; ?></td>
                        <td><?php echo $row['costo_unitario']; ?></td>
                        <td><?php echo $row['ultima_venta_fecha']; ?></td>
                        <td style="text-align: center;">
                            <a href="?delete_id=<?php echo $row['id_producto']; ?>" 
                               onclick="return confirm('¿Estás seguro de que deseas eliminar permanentemente el producto ID <?php echo $row['id_producto']; ?>?')" 
                               class="button button-delete">Eliminar</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </table>
            <?php else: ?>
                <p>No hay productos registrados en el inventario.</p>
            <?php endif; ?>
        </section>

    </div>
</body>
</html>