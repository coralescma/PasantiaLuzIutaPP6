<?php
// logout.php
session_start();
session_unset(); // Elimina las variables de sesión
session_destroy(); // Destruye la sesión
header("Location: login.php");
exit();
?>