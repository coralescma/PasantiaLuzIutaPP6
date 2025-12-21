<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../includes/auth.php';
require_login();
include '../includes/db_connect.php';

$mensaje = "";

// 1. PROCESAR GUARDADO (NUEVO O ACTUALIZACIÓN)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_user'])) {
    $id_user = isset($_POST['id_usuario']) ? intval($_POST['id_usuario']) : 0;
    $full_name = $_POST['full_name'];
    $username = $_POST['username'];
    $id_rol = intval($_POST['id_rol']);
    $estado = 1;

    if ($id_user > 0) {
        // --- MODO EDICIÓN ---
        if (!empty($_POST['password'])) {
            // Si escribió una nueva clave, la actualizamos
            $pass_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $sql = "UPDATE usuarios SET user_full_name=?, username=?, password_hash=?, id_rol_fk=? WHERE id_usuario=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssii", $full_name, $username, $pass_hash, $id_rol, $id_user);
        } else {
            // Si NO escribió clave, actualizamos todo lo demás menos la clave
            $sql = "UPDATE usuarios SET user_full_name=?, username=?, id_rol_fk=? WHERE id_usuario=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssii", $full_name, $username, $id_rol, $id_user);
        }
        $res_msg = "actualizado";
    } else {
        // --- MODO NUEVO ---
        $pass_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql = "INSERT INTO usuarios (user_full_name, username, password_hash, id_rol_fk, estado) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssii", $full_name, $username, $pass_hash, $id_rol, $estado);
        $res_msg = "creado";
    }

    if ($stmt && $stmt->execute()) {
        $mensaje = "<div class='success'>✅ Usuario <b>$username</b> $res_msg con éxito.</div>";
    } else {
        $mensaje = "<div class='error'>❌ Error: " . $conn->error . "</div>";
    }
}

// 2. OBTENER ROLES Y USUARIOS
$res_roles = $conn->query("SELECT * FROM roles_y_privilegios");
$sql_list = "SELECT u.id_usuario, u.user_full_name, u.username, r.id_rol, r.nombre_rol 
             FROM usuarios u
             LEFT JOIN roles_y_privilegios r ON u.id_rol_fk = r.id_rol
             ORDER BY u.id_usuario DESC";
$res_users = $conn->query($sql_list);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SCL - Gestión de Usuarios</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .split-container { display: flex; gap: 20px; margin-top: 20px; }
        .form-side, .table-side { background: #fff; padding: 25px; border-radius: 8px; border: 1px solid #ddd; }
        .form-side { flex: 1; height: fit-content; position: sticky; top: 20px; }
        .table-side { flex: 2; }
        .input-box { width: 100%; padding: 10px; margin: 8px 0 15px 0; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .badge-rol { background: #eff6ff; color: #1e40af; padding: 4px 8px; border-radius: 6px; font-size: 0.85em; font-weight: bold; }
        .btn-edit { color: #3498db; cursor: pointer; text-decoration: underline; font-weight: bold; }
        .edit-info { font-size: 0.8em; color: #e67e22; font-weight: bold; display: none; margin-bottom: 10px; }
    </style>
</head>
<body>
    <?php include '../includes/menu.php'; ?>

    <div class="report-container">
        <h1>👤 Gestión de Usuarios y Accesos</h1>
        <?php echo $mensaje; ?>

        <div class="split-container">
            <div class="form-side">
                <h3 id="form-title">Añadir Personal</h3>
                <div id="edit-info" class="edit-info">✍️ EDITANDO USUARIO</div>
                
                <form method="POST" id="user-form">
                    <input type="hidden" name="guardar_user" value="1">
                    <input type="hidden" name="id_usuario" id="id_usuario" value="">
                    
                    <label>Nombre Completo</label>
                    <input type="text" name="full_name" id="full_name" class="input-box" required>
                    
                    <label>Usuario (Login)</label>
                    <input type="text" name="username" id="username" class="input-box" required>
                    
                    <label id="label-pass">Contraseña</label>
                    <input type="password" name="password" id="password" class="input-box" placeholder="Dejar en blanco para no cambiar">
                    
                    <label>Rol del Usuario</label>
                    <select name="id_rol" id="id_rol" class="input-box">
                        <?php 
                        $roles_data = []; // Guardamos para el JS
                        while($rol = $res_roles->fetch_assoc()){ 
                            $roles_data[] = $rol;
                            echo "<option value='".$rol['id_rol']."'>".$rol['nombre_rol']."</option>";
                        } 
                        ?>
                    </select>
                    
                    <button type="submit" id="btn-submit" class="boton-primario" style="width:100%; padding:12px;">Crear Usuario</button>
                    <button type="button" onclick="cancelarEdicion()" id="btn-cancel" style="width:100%; margin-top:10px; display:none;">Cancelar</button>
                </form>
            </div>

            <div class="table-side">
                <h3 style="margin:0 0 15px 0;">Usuarios Activos</h3>
                <table>
                    <thead>
                        <tr><th>Nombre</th><th>Login</th><th>Rol</th><th>Acción</th></tr>
                    </thead>
                    <tbody>
                        <?php while($user = $res_users->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($user['user_full_name']); ?></strong></td>
                            <td><code><?php echo htmlspecialchars($user['username']); ?></code></td>
                            <td><span class="badge-rol"><?php echo htmlspecialchars($user['nombre_rol']); ?></span></td>
                            <td>
                                <span class="btn-edit" onclick="editarUser(
                                    '<?php echo $user['id_usuario']; ?>', 
                                    '<?php echo addslashes($user['user_full_name']); ?>', 
                                    '<?php echo addslashes($user['username']); ?>', 
                                    '<?php echo $user['id_rol']; ?>'
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
        function editarUser(id, nombre, login, rol) {
            document.getElementById('form-title').innerText = 'Modificar Usuario';
            document.getElementById('edit-info').style.display = 'block';
            document.getElementById('btn-submit').innerText = '🚀 Actualizar Datos';
            document.getElementById('btn-cancel').style.display = 'block';
            document.getElementById('label-pass').innerText = 'Nueva Contraseña (opcional)';
            
            document.getElementById('id_usuario').value = id;
            document.getElementById('full_name').value = nombre;
            document.getElementById('username').value = login;
            document.getElementById('id_rol').value = rol;
            document.getElementById('password').required = false;
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function cancelarEdicion() {
            document.getElementById('user-form').reset();
            document.getElementById('id_usuario').value = '';
            document.getElementById('form-title').innerText = 'Añadir Personal';
            document.getElementById('edit-info').style.display = 'none';
            document.getElementById('btn-submit').innerText = 'Crear Usuario';
            document.getElementById('btn-cancel').style.display = 'none';
            document.getElementById('label-pass').innerText = 'Contraseña';
            document.getElementById('password').required = true;
        }
    </script>
</body>
</html>