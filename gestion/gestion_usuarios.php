<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Importante: Asegúrate que estas rutas sean correctas según tu carpeta
include '../includes/auth.php';
require_login();
include '../includes/db_connect.php';

$mensaje = "";

// 1. PROCESAR GUARDADO (NUEVO O ACTUALIZACIÓN)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_user'])) {
    $id_user   = isset($_POST['id_usuario']) ? intval($_POST['id_usuario']) : 0;
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $username  = mysqli_real_escape_string($conn, $_POST['username']);
    $id_rol    = intval($_POST['id_rol']);
    $estado    = 'Activo';

    if ($id_user > 0) {
        // --- MODO EDICIÓN ---
        if (!empty($_POST['password'])) {
            $pass_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $sql = "UPDATE usuarios SET user_full_name=?, username=?, password_hash=?, id_rol_fk=? WHERE id_usuario=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssii", $full_name, $username, $pass_hash, $id_rol, $id_user);
        } else {
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
        $mensaje = "<div class='success' style='padding:15px; background:#d4edda; color:#155724; border-radius:5px; margin-bottom:20px;'>✅ Usuario <b>$username</b> $res_msg con éxito.</div>";
    } else {
        $mensaje = "<div class='error' style='padding:15px; background:#f8d7da; color:#721c24; border-radius:5px; margin-bottom:20px;'>❌ Error: " . $conn->error . " (Probablemente el nombre de usuario ya existe)</div>";
    }
}

// 2. OBTENER ROLES Y LISTADO DE USUARIOS
$res_roles = $conn->query("SELECT * FROM roles_y_privilegios ORDER BY nombre_rol ASC");
// Definimos la jerarquía manualmente en la consulta
$sql_list = "SELECT u.id_usuario, u.user_full_name, u.username, r.id_rol, r.nombre_rol 
             FROM usuarios u
             LEFT JOIN roles_y_privilegios r ON u.id_rol_fk = r.id_rol
             ORDER BY 
                CASE r.nombre_rol
                    WHEN 'Administrador' THEN 1
                    WHEN 'Gerente'       THEN 2
                    WHEN 'Supervisor'    THEN 3
                    WHEN 'Vendedor'      THEN 4
                    ELSE 5 
                END ASC, 
                u.user_full_name ASC";
$res_users = $conn->query($sql_list);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SCL - Gestión de Usuarios</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .split-container { display: flex; gap: 20px; margin-top: 20px; align-items: flex-start; }
        .form-side, .table-side { background: #fff; padding: 25px; border-radius: 8px; border: 1px solid #ddd; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .form-side { flex: 1; position: sticky; top: 20px; }
        .table-side { flex: 2; }
        .input-box { width: 100%; padding: 10px; margin: 8px 0 15px 0; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .badge-rol { background: #eff6ff; color: #1e40af; padding: 4px 10px; border-radius: 12px; font-size: 0.85em; font-weight: bold; border: 1px solid #bfdbfe; }
        .btn-edit { color: #3498db; cursor: pointer; text-decoration: none; font-weight: bold; padding: 5px 10px; border: 1px solid #3498db; border-radius: 4px; transition: 0.3s; }
        .btn-edit:hover { background: #3498db; color: white; }
        .edit-info { background: #fff3cd; color: #856404; padding: 10px; border-radius: 4px; font-size: 0.85em; font-weight: bold; display: none; margin-bottom: 15px; border: 1px solid #ffeeba; }
    </style>
</head>
<body>
    <?php include '../includes/menu.php'; ?>

    <div class="report-container" style="max-width: 1200px; margin: auto; padding: 20px;">
        <h1>👤 Gestión de Usuarios y Accesos</h1>
        <?php echo $mensaje; ?>

        <div class="split-container">
            <div class="form-side">
                <h3 id="form-title" style="margin-top:0;">Añadir Nuevo Personal</h3>
                <div id="edit-info" class="edit-info">✍️ MODO EDICIÓN ACTIVADO</div>
                
                <form method="POST" id="user-form">
                    <input type="hidden" name="guardar_user" value="1">
                    <input type="hidden" name="id_usuario" id="id_usuario" value="">
                    
                    <label><b>Nombre Completo</b></label>
                    <input type="text" name="full_name" id="full_name" class="input-box" placeholder="Ej. Juan Pérez" required>
                    
                    <label><b>Nombre de Usuario (Login)</b></label>
                    <input type="text" name="username" id="username" class="input-box" placeholder="Ej. jperez" required>
                    
                    <label id="label-pass"><b>Contraseña</b></label>
                    <input type="password" name="password" id="password" class="input-box" required>
                    
                    <label><b>Rol asignado</b></label>
                    <select name="id_rol" id="id_rol" class="input-box">
                        <?php 
                        while($rol = $res_roles->fetch_assoc()){ 
                            echo "<option value='".$rol['id_rol']."'>".$rol['nombre_rol']."</option>";
                        } 
                        ?>
                    </select>
                    
                    <button type="submit" id="btn-submit" class="boton-primario" style="width:100%; padding:12px; cursor:pointer;">Crear Usuario</button>
                    <button type="button" onclick="cancelarEdicion()" id="btn-cancel" style="width:100%; margin-top:10px; padding:10px; background:#6c757d; color:white; border:none; border-radius:4px; display:none; cursor:pointer;">Cancelar Edición</button>
                </form>
            </div>

            <div class="table-side">
                <h3 style="margin-top:0;">Usuarios Registrados</h3>
                <table style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background:#f8f9fa;">
                            <th style="padding:12px; border-bottom:2px solid #dee2e6;">Nombre</th>
                            <th style="padding:12px; border-bottom:2px solid #dee2e6;">Usuario</th>
                            <th style="padding:12px; border-bottom:2px solid #dee2e6;">Rol</th>
                            <th style="padding:12px; border-bottom:2px solid #dee2e6;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($res_users->num_rows > 0): ?>
                            <?php while($user = $res_users->fetch_assoc()): ?>
                            <tr>
                                <td style="padding:12px; border-bottom:1px solid #eee;"><b><?php echo htmlspecialchars($user['user_full_name']); ?></b></td>
                                <td style="padding:12px; border-bottom:1px solid #eee;"><code><?php echo htmlspecialchars($user['username']); ?></code></td>
                                <td style="padding:12px; border-bottom:1px solid #eee;"><span class="badge-rol"><?php echo htmlspecialchars($user['nombre_rol']); ?></span></td>
                                <td style="padding:12px; border-bottom:1px solid #eee;">
                                    <span class="btn-edit" onclick="editarUser(
                                        '<?php echo $user['id_usuario']; ?>', 
                                        '<?php echo addslashes($user['user_full_name']); ?>', 
                                        '<?php echo addslashes($user['username']); ?>', 
                                        '<?php echo $user['id_rol']; ?>'
                                    )">Editar</span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="text-align:center; padding:20px;">No hay usuarios registrados.</td></tr>
                        <?php endif; ?>
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
            document.getElementById('btn-submit').style.background = '#28a745';
            document.getElementById('btn-cancel').style.display = 'block';
            document.getElementById('label-pass').innerHTML = '<b>Nueva Contraseña</b> <small>(dejar vacío para mantener actual)</small>';
            
            document.getElementById('id_usuario').value = id;
            document.getElementById('full_name').value = nombre;
            document.getElementById('username').value = login;
            document.getElementById('id_rol').value = rol;
            document.getElementById('password').required = false;
            document.getElementById('password').placeholder = "********";
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function cancelarEdicion() {
            document.getElementById('user-form').reset();
            document.getElementById('id_usuario').value = '';
            document.getElementById('form-title').innerText = 'Añadir Nuevo Personal';
            document.getElementById('edit-info').style.display = 'none';
            document.getElementById('btn-submit').innerText = 'Crear Usuario';
            document.getElementById('btn-submit').style.background = ''; // Vuelve al color original del CSS
            document.getElementById('btn-cancel').style.display = 'none';
            document.getElementById('label-pass').innerHTML = '<b>Contraseña</b>';
            document.getElementById('password').required = true;
            document.getElementById('password').placeholder = "";
        }
    </script>
</body>
</html>