<?php
// 1. Habilitar errores para ver qué falla exactamente si queda en blanco
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Ruta de seguridad: Intentar cargar auth.php
// Verifica si el archivo existe antes de cargarlo para evitar el "pantallazo blanco"
$auth_path = 'includes/auth.php';

if (file_exists($auth_path)) {
    require_once $auth_path;
} else {
    die("Error crítico: No se encontró el archivo $auth_path. Verifica las carpetas.");
}

// 3. Redirección si ya está logueado
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$login_error = "";

// 4. Lógica de Post
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // La función verificar_usuario ya usa la tabla 'usuarios' según lo que definimos
    if (verificar_usuario($username, $password, $conn)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $login_error = "Credenciales incorrectas o usuario inactivo.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SCL - Acceso</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 300px; }
        h2 { text-align: center; color: #333; }
        input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        button:hover { background: #2980b9; }
        .error { color: #721c24; background: #f8d7da; padding: 10px; border-radius: 4px; font-size: 13px; margin-bottom: 10px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Sistema SCL</h2>
        
        <?php if ($login_error): ?>
            <div class="error"><?php echo $login_error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <label>Usuario</label>
            <input type="text" name="username" required autofocus>
            
            <label>Contraseña</label>
            <input type="password" name="password" required>
            
            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>