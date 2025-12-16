<?php
// login.php
include 'includes/auth.php'; // Usa el nuevo archivo de autenticación

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Intenta verificar las credenciales
    if (verificar_usuario($username, $password, $conn)) {
        // Redirigir al Dashboard (index.php)
        header("Location: index.php");
        exit();
    } else {
        $mensaje = "Error: Usuario o contraseña incorrectos.";
    }
}

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
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
        body {
            background-color: #f4f7f6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
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
        }
        .login-box button:hover {
            background-color: #2980b9;
        }
        .error-message {
            color: #d9534f;
            margin-bottom: 15px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>🔐 SCL - Acceso al Sistema</h1>
        <?php if ($mensaje): ?>
            <p class="error-message"><?php echo $mensaje; ?></p>
        <?php endif; ?>
        
        <form action="login.php" method="post">
            <input type="text" name="username" placeholder="Usuario (Ej: ana.lopez)" required>
            <input type="password" name="password" placeholder="Contraseña (Ej: hash123)" required>
            <button type="submit">Iniciar Sesión</button>
        </form>
        
        <p style="margin-top: 20px; font-size: 0.9em; color: #777;">
            **Usuarios de Prueba:**<br>
            Cajero: **ana.lopez** | Supervisor: **gerente** | Contador: **m.castillo**<br>
            Contraseña para todos: **hash123**
        </p>
    </div>
</body>
</html>