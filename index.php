<?php
// ===============================================================
// HABILITAR REPORTE DE ERRORES (QUITAR EN PRODUCCIÓN)
// Esto ayuda a evitar la pantalla en blanco (WSOD)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ===============================================================

session_start();

// Si el usuario ya está logueado, redirigir al dashboard inmediatamente
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Incluir la conexión a la base de datos
// Asumimos que este archivo define $conn
include 'includes/db_connect.php'; 

$login_error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // 1. LIMPIEZA DE ENTRADA
    $username = $conn->real_escape_string($username);
    // Nota: No saneamos la contraseña, ya que debe ser verificada en su forma original.

    // 2. CONSULTA SQL CORREGIDA
    // La corrección clave: usar 'usuarios' en lugar de 'usuario'
    $sql = "SELECT id_usuario, password_hash, user_full_name, rol, estado FROM usuarios WHERE username = '$username'";
    
    $resultado = $conn->query($sql);
    
    // VERIFICACIÓN DE SEGURIDAD PARA EVITAR EL FATAL ERROR
    if ($resultado === FALSE) {
        // Error de SQL (ej: la tabla 'usuarios' sigue sin existir o está mal escrita)
        error_log("Error SQL en login: " . $conn->error);
        $login_error = "Error interno del sistema. Intente más tarde.";
        
    } elseif ($resultado->num_rows === 1) {
        $usuario = $resultado->fetch_assoc();
        
        // 3. VERIFICACIÓN DE CONTRASEÑA
        // Usamos password_verify para trabajar con el hash que insertamos
        if (password_verify($password, $usuario['password_hash'])) {
            
            // 4. VERIFICACIÓN DE ESTADO
            if ($usuario['estado'] === 'Activo') {
                
                // 5. INICIO DE SESIÓN EXITOSO
                $_SESSION['user_id'] = $usuario['id_usuario'];
                $_SESSION['username'] = $usuario['username'];
                $_SESSION['user_full_name'] = $usuario['user_full_name'];
                $_SESSION['rol'] = $usuario['rol'];
                
                header('Location: dashboard.php');
                exit;
                
            } else {
                $login_error = "Su cuenta está inactiva. Contacte al administrador.";
            }
        } else {
            // Contraseña incorrecta
            $login_error = "Nombre de usuario o contraseña incorrectos.";
        }
    } else {
        // Usuario no encontrado
        $login_error = "Nombre de usuario o contraseña incorrectos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SCL - Iniciar Sesión</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="login-body">
    <div class="login-container">
        <header>
            <h1>Sistema de Control y Liquidación (SCL)</h1>
            <h2>Inicio de Sesión</h2>
        </header>
        
        <?php if ($login_error): ?>
            <div class="alerta-roja" style="padding: 10px; margin-bottom: 15px;"><?php echo $login_error; ?></div>
        <?php endif; ?>

        <form action="index.php" method="post" class="login-form">
            <div class="form-group">
                <label for="username">Usuario:</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="button button-a1">Entrar</button>
        </form>
    </div>
</body>
</html>