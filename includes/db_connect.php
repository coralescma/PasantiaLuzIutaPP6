<?php
$servername = "localhost";
$username = "root";  // Usuario por defecto en XAMPP/WAMP
$password = "";      // Contraseña por defecto vacía
$dbname = "pmv";  // El nombre de tu base de datos

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}
?>