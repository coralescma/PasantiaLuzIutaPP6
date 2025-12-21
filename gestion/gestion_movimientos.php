<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../includes/auth.php';
require_login();
include '../includes/db_connect.php';

$mensaje = "";

// 1. PROCESAR GUARDADO (NUEVO O ACTUALIZACIÓN)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_modificar = isset($_POST['id_tipo_movimiento']) ? intval($_POST['id_tipo_movimiento']) : 0;
    $nombre = $_POST['nombre_movimiento'];
    // Ajustamos para que coincida con ENUM('Entrada', 'Salida') de pmv.sql
    $flujo = $_POST['tipo_flujo']; 

    if ($id_modificar > 0) {
        // ACTUALIZAR EXISTENTE
        $stmt = $conn->prepare("UPDATE tipo_movimiento SET nombre_movimiento = ?, tipo_flujo = ? WHERE id_tipo_movimiento = ?");
        $stmt->bind_param("ssi", $nombre, $flujo, $id_modificar);
        $texto_exito = "actualizada";
    } else {
        // INSERTAR NUEVO
        $stmt = $conn->prepare("INSERT INTO tipo_movimiento (nombre_movimiento, tipo_flujo) VALUES (?, ?)");
        $stmt->bind_param("ss", $nombre, $flujo);
        $texto_exito = "creada";
    }

    if ($stmt->execute()) {
        $mensaje = "<div class='success'>✅ Categoría '$nombre' $texto_exito con éxito.</div>";
    } else {
        $mensaje = "<div class='error'>❌ Error: " . $conn->error . "</div>";
    }
}

// 2. OBTENER LISTA ACTUALIZADA
$sql_list = "SELECT id_tipo_movimiento, nombre_movimiento, tipo_flujo FROM tipo_movimiento ORDER BY id_tipo_movimiento DESC";
$res = $conn->query($sql_list);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SCL - Tipos de Movimiento</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .split-container { display: flex; gap: 20px; margin-top: 20px; }
        .form-side, .table-side { background: #fff; padding: 25px; border-radius: 8px; border: 1px solid #ddd; }
        .form-side { flex: 1; height: fit-content; position: sticky; top: 20px; }
        .table-side { flex: 2; }
        .input-box { width: 100%; padding: 10px; margin: 8px 0 15px 0; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn-edit { color: #3498db; cursor: pointer; text-decoration: underline; font-weight: bold; }
        .mode-indicator { font-size: 0.8em; color: #e67e22; font-weight: bold; display: none; }
    </style>
</head>
<body>
    <?php include '../includes/menu.php'; ?>

    <div class="report-container" style="padding: 20px;">
        <h1>🔄 Configuración de Movimientos</h1>
        <?php echo $mensaje; ?>

        <div class="split-container">
            <div class="form-side" id="form-container">
                <h3 id="form-title">Nueva Categoría</h3>
                <span id="edit-mode" class="mode-indicator">⚠️ MODO EDICIÓN ACTIVO</span>
                
                <form method="POST" id="movimiento-form">
                    <input type="hidden" name="id_tipo_movimiento" id="id_tipo_movimiento" value="">
                    
                    <label>Nombre del Movimiento</label>
                    <input type="text" name="nombre_movimiento" id="nombre_movimiento" class="input-box" required>
                    
                    <label>Tipo de Flujo</label>
                    <select name="tipo_flujo" id="tipo_flujo" class="input-box">
                        <option value="Entrada">🟢 ENTRADA (Ingreso)</option>
                        <option value="Salida">🔴 SALIDA (Egreso)</option>
                    </select>
                    
                    <button type="submit" class="boton-primario" id="btn-submit" style="width:100%; padding:12px; background: #2563eb; color:white; border:none; border-radius:4px; cursor:pointer;">💾 Guardar Categoría</button>
                    <button type="button" onclick="cancelarEdicion()" id="btn-cancel" style="width:100%; margin-top:10px; display:none; padding:10px;">Cancelar</button>
                </form>
            </div>

            <div class="table-side">
                <h3>Categorías Registradas</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Descripción</th>
                            <th>Flujo</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $res->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['nombre_movimiento']); ?></strong></td>
                            <td>
                                <span style="color: <?php echo ($row['tipo_flujo'] == 'Entrada') ? '#10b981' : '#e11d48'; ?>; font-weight:bold;">
                                    <?php echo strtoupper($row['tipo_flujo']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="btn-edit" onclick="editarMovimiento(
                                    '<?php echo $row['id_tipo_movimiento']; ?>', 
                                    '<?php echo addslashes($row['nombre_movimiento']); ?>', 
                                    '<?php echo $row['tipo_flujo']; ?>'
                                )">Editar</span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function editarMovimiento(id, nombre, flujo) {
            document.getElementById('form-title').innerText = 'Editar Categoría';
            document.getElementById('edit-mode').style.display = 'block';
            document.getElementById('btn-submit').innerText = '🚀 Actualizar Categoría';
            document.getElementById('btn-cancel').style.display = 'block';
            
            document.getElementById('id_tipo_movimiento').value = id;
            document.getElementById('nombre_movimiento').value = nombre;
            document.getElementById('tipo_flujo').value = flujo;
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function cancelarEdicion() {
            document.getElementById('movimiento-form').reset();
            document.getElementById('id_tipo_movimiento').value = '';
            document.getElementById('form-title').innerText = 'Nueva Categoría';
            document.getElementById('edit-mode').style.display = 'none';
            document.getElementById('btn-submit').innerText = '💾 Guardar Categoría';
            document.getElementById('btn-cancel').style.display = 'none';
        }
    </script>
</body>
</html>