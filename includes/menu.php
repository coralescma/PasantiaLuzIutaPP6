<?php
// includes/menu.php

$base_path = '';
if (basename(getcwd()) === 'gestion') {
    $base_path = '../';
}

$logged_in = isset($_SESSION['user_id']);
$user_display = $logged_in ? 
    ($_SESSION['user_full_name'] . ' (' . $_SESSION['user_role'] . ')') : 
    'Invitado';

// Variable para marcar la página activa (puedes definirla en cada archivo antes de incluir el menú)
$pagina_activa = $pagina_activa ?? '';
?>

<nav class="main-menu">
    <div class="logo">SCL | Control y Liquidación</div>
    
    <ul>
        <li><a href="<?php echo $base_path; ?>index.php" class="<?php echo ($pagina_activa === 'inicio' ? 'active' : ''); ?>">Dashboard</a></li>
                
        <li class="dropdown">
            <a href="#">Operaciones Diarias</a>
            <div class="dropdown-content">
                <a href="<?php echo $base_path; ?>gestion/form_jornada.php">Gestión de Jornada (C.4)</a>
                <hr>
                <a href="<?php echo $base_path; ?>gestion/form_transacciones.php">Registro de Venta (A.4)</a>
            </div>
        </li>

        <li class="dropdown">
            <a href="#">Administración</a>
            <div class="dropdown-content">
                <a href="<?php echo $base_path; ?>gestion/gestion_usuarios.php">👤 Usuarios y Privilegios</a>
                <a href="<?php echo $base_path; ?>gestion/form_parametros.php">⚙️ Parámetros (D.4)</a>
                <a href="<?php echo $base_path; ?>gestion/gestion_movimientos.php">🔄 Tipos de Movimiento</a>
                <hr>
                <a href="<?php echo $base_path; ?>gestion/form_inventario.php">📦 Inventario (D.5)</a>
            </div>
        </li>
        
        <li class="dropdown">
            <a href="#">Reportes Firmados</a>
            <div class="dropdown-content">
                <a href="<?php echo $base_path; ?>reporte_a1.php">Reporte A.1 (Caja)</a>
                <a href="<?php echo $base_path; ?>reporte_a2.php">Reporte A.2 (Banco)</a>
                <hr>
                <a href="<?php echo $base_path; ?>dashboard_ejecutivo.php" style="font-weight: bold; color: #2c3e50;">📊 Resumen Ejecutivo</a>
            </div>
        </li>

        <li class="dropdown" style="margin-left: auto;"> 
            <a href="#">👤 <?php echo $user_display; ?></a>
            <div class="dropdown-content">
                <?php if ($logged_in): ?>
                    <a href="<?php echo $base_path; ?>logout.php">Cerrar Sesión</a>
                <?php else: ?>
                    <a href="<?php echo $base_path; ?>login.php">Iniciar Sesión</a>
                <?php endif; ?>
            </div>
        </li>
    </ul>
</nav>