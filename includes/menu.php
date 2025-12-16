<?php
// includes/menu.php

// Define la ruta base para asegurar que los enlaces funcionen desde cualquier carpeta.
// Si estamos en la carpeta 'gestion', la ruta base es '../'. Si estamos en la raíz, es ''.
$base_path = '';
if (basename(getcwd()) === 'gestion') {
    $base_path = '../';
}

// Verifica si la sesión está iniciada (solo para seguridad extra, aunque auth.php lo hace)
$logged_in = isset($_SESSION['user_id']);
$user_display = $logged_in ? 
    ($_SESSION['user_full_name'] . ' (' . $_SESSION['user_role'] . ')') : 
    'Invitado';
?>

<nav class="main-menu">
    <div class="logo">SCL | Control y Liquidación</div>
    
    <ul>
        <li><a href="<?php echo $base_path; ?>index.php" class="<?php echo ($pagina_activa === 'dashboard' ? 'active' : ''); ?>">Dashboard</a></li>
        
        <li class="dropdown">
            <a href="#">Operaciones Diarias</a>
            <div class="dropdown-content">
                <a href="<?php echo $base_path; ?>gestion/form_cierre_caja.php" class="<?php echo ($pagina_activa === 'cierre_caja' ? 'active' : ''); ?>">Cierre de Caja (C.4)</a>
                <hr>
                <a href="<?php echo $base_path; ?>gestion/form_transacciones.php">Registro de Venta (A.4)</a>
            </div>
        </li>

        <li class="dropdown">
            <a href="#">Administración</a>
            <div class="dropdown-content">
                <a href="<?php echo $base_path; ?>gestion/form_inventario.php">Inventario (D.5)</a>
                <a href="<?php echo $base_path; ?>gestion/form_parametros.php">Parámetros (D.4)</a>
            </div>
        </li>
        
        <li class="dropdown">
            <a href="#">Reportes Firmados</a>
            <div class="dropdown-content">
                <a href="<?php echo $base_path; ?>reporte_a1.php">Reporte A.1 (Caja)</a>
                <a href="<?php echo $base_path; ?>reporte_a2.php">Reporte A.2 (Banco)</a>
            </div>
        </li>

        <li class="dropdown" style="margin-left: auto;"> <a href="#">👤 <?php echo $user_display; ?></a>
            <div class="dropdown-content">
                <?php if ($logged_in): ?>
                    <a href="<?php echo $base_path; ?>logout.php" title="Cerrar la sesión actual">Cerrar Sesión</a>
                <?php else: ?>
                    <a href="<?php echo $base_path; ?>login.php" title="Iniciar Sesión">Iniciar Sesión</a>
                <?php endif; ?>
            </div>
        </li>
    </ul>
</nav>