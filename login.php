<?php
// login.php

// 1. Incluimos el archivo de autenticación que centraliza la sesión y la conexión a la DB
// Este archivo ya utiliza la tabla 'usuarios' y el campo 'password_hash'
include 'includes/auth.php';

$mensaje = "";

// 2. Procesamiento del formulario de inicio de sesión
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Intenta verificar las credenciales usando la función unificada en auth.php
    // Esta función valida contra la tabla 'usuarios' y el campo 'estado'
    if (verificar_usuario($username, $password, $conn)) {
        // Redirigir al Dashboard (dashboard.php) tras éxito
        header("Location: dashboard.php");
        exit();
    } else {
        $mensaje = "Error: Usuario o contraseña incorrectos, o cuenta inactiva.";
    }
}

// 3. Si el usuario ya tiene sesión activa, redirigir al dashboard inmediatamente
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SCL - Iniciar Sesión</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Estilos optimizados para una presentación profesional */
        body {
            background-color: #f4f7f6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            font-family: Arial, sans-serif;
        }
        .login-box {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 350px;
            text-align: center;
        }
        .login-box h1 {
            color: #2c3e50;
            margin-bottom: 30px;
            font-size: 1.5em;
        }
        .login-box input[type="text"],
        .login-box input[type="password"] {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .login-box button {
            background-color: #3498db;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-size: 1em;
            transition: background-color 0.3s;
            font-weight: bold;
        }
        .login-box button:hover {
            background-color: #2980b9;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 0.9em;
            border: 1px solid #f5c6cb;
        }
        .demo-info {
            margin-top: 20px;
            font-size: 0.85em;
            color: #777;
            text-align: left;
            background: #f9f9f9;
            padding: 10px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>🔐 SCL - Acceso Unificado</h1>
        
        <?php if ($mensaje): ?>
            <div class="error-message"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        
        <form action="login.php" method="post">
            <input type="text" name="username" placeholder="Usuario" required autofocus>
            <input type="password" name="password" placeholder="Contraseña" required>
            <button type="submit">INGRESAR</button>
        </form>
        
        <div class="demo-info">
            <strong>Usuarios Disponibles (Tabla usuarios):</strong><br>
            • Admin: <b>admin</b><br>
            • Supervisor: <b>mcorales</b>
        </div>
    </div>
</body>
</html>