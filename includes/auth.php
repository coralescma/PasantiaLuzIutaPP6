<?php
// includes/auth.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$db_path = __DIR__ . '/db_connect.php';
if (file_exists($db_path)) {
    require_once $db_path;
}

function verificar_usuario($usuario_input, $contrasena_input, $conn) {
    $usuario_input = $conn->real_escape_string($usuario_input);
    
    $sql = "SELECT u.id_usuario, u.password_hash, u.user_full_name, r.nombre_rol 
            FROM usuarios u 
            JOIN roles_y_privilegios r ON u.id_rol_fk = r.id_rol 
            WHERE u.username = '$usuario_input' AND u.estado = 'Activo'";
            
    $resultado = $conn->query($sql);

    if ($resultado && $resultado->num_rows == 1) {
        $user_data = $resultado->fetch_assoc();
        
        if (password_verify($contrasena_input, $user_data['password_hash']) || $contrasena_input === $user_data['password_hash']) { 
            
            $_SESSION['user_id'] = $user_data['id_usuario'];
            $_SESSION['username'] = $usuario_input;
            $_SESSION['user_full_name'] = $user_data['user_full_name']; 
            $_SESSION['user_role'] = $user_data['nombre_rol'];
            
            return true;
        }
    }
    return false;
}

function obtenerJornadaActiva($conn) {
    // Usamos una consulta simple para evitar errores de interpretación de tipos
    $sql = "SELECT id_jornada FROM control_jornadas WHERE estado_jornada IN ('Abierta', '1') LIMIT 1";
    $res = $conn->query($sql);
    
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        return $row['id_jornada'];
    }
    return false;
}

function require_login($allowed_roles = null) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../index.php");
        exit();
    }
    
    if ($allowed_roles !== null) {
        $roles = is_array($allowed_roles) ? $allowed_roles : [$allowed_roles];
        if (!in_array($_SESSION['user_role'], $roles)) {
            header("Location: ../dashboard.php?error=acceso_denegado");
            exit();
        }
    }
}
?>