<?php
// includes/auth.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include 'db_connect.php'; 

function verificar_usuario($usuario_input, $contrasena_input, $conn) {
    $usuario_input = $conn->real_escape_string($usuario_input);
    
    // Consulta a la tabla 'usuarios' (plural)
    $sql = "SELECT u.id_usuario, u.password_hash, u.user_full_name, r.nombre_rol 
            FROM usuarios u 
            JOIN roles_y_privilegios r ON u.id_rol_fk = r.id_rol 
            WHERE u.username = '$usuario_input' AND u.estado = 'Activo'";
            
    $resultado = $conn->query($sql);

    if ($resultado && $resultado->num_rows == 1) {
        $user_data = $resultado->fetch_assoc();
        
        $hash_db = $user_data['password_hash'];

        // VERIFICACIÓN: Probamos hash de PHP o texto plano
        if (password_verify($contrasena_input, $hash_db) || $contrasena_input === $hash_db) { 
            
            $_SESSION['user_id'] = $user_data['id_usuario'];
            $_SESSION['username'] = $usuario_input;
            $_SESSION['user_full_name'] = $user_data['user_full_name']; 
            $_SESSION['user_role'] = $user_data['nombre_rol'];
            
            return true;
        } else {
            // ESTO ES PARA DEPURAR: Si falla, te dirá qué encontró
            // Borra estas líneas una vez funcione
            echo "";
        }
    }
    return false;
}

function require_login($allowed_roles = null) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit();
    }
}
?>