<?php
// includes/auth.php

// ===============================================
// 1. INICIALIZACIÓN (DEBE IR SIEMPRE AL PRINCIPIO)
// ===============================================

// Inicia la sesión de PHP
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. CONEXIÓN A LA BASE DE DATOS
// Asegura que la conexión $conn esté disponible globalmente
include 'db_connect.php'; 

// ===============================================
// 2. FUNCIONES DE SEGURIDAD
// ===============================================

/**
 * Función para verificar el usuario y la contraseña contra la DB.
 * Nota: Asume que las contraseñas en la DB están en texto plano 'hash123' (POC)
 */
function verificar_usuario($usuario, $contrasena, $conn) {
    $usuario = $conn->real_escape_string($usuario);
    
    // Consulta para obtener la data completa del usuario
    $sql = "SELECT u.id_usuario, u.contrasena_hash, u.nombre, r.nombre_rol 
            FROM usuario u 
            JOIN roles_y_privilegios r ON u.id_rol_fk = r.id_rol 
            WHERE u.usuario = '$usuario' AND u.estado = 'Activo'";
            
    $resultado = $conn->query($sql);

    if ($resultado && $resultado->num_rows == 1) {
        $usuario_data = $resultado->fetch_assoc();
        
        // VERIFICACIÓN POC: Compara la contraseña ingresada con el valor de la DB ('hash123')
        if ($contrasena == $usuario_data['contrasena_hash']) { 
            
            // Autenticación exitosa: Establecer variables de sesión
            $_SESSION['user_id'] = $usuario_data['id_usuario'];
            $_SESSION['username'] = $usuario;
            $_SESSION['user_full_name'] = $usuario_data['nombre']; 
            $_SESSION['user_role'] = $usuario_data['nombre_rol'];
            
            return true;
        }
    }
    return false;
}

/**
 * Función para restringir el acceso a páginas.
 * Si el usuario no ha iniciado sesión, lo redirige al login.
 */
function require_login($allowed_roles = null) {
    if (!isset($_SESSION['user_id'])) {
        // Redireccionar al login
        header("Location: login.php");
        exit();
    }
    
    // Si se especifican roles permitidos, verifica el rol del usuario
    if ($allowed_roles !== null && !in_array($_SESSION['user_role'], (array)$allowed_roles)) {
        // Acceso Denegado
        header("Location: index.php?error=access_denied");
        exit();
    }
}
?>