<?php
// includes/menu.php

// 1. Configuración de rutas
$base_path = '';
if (basename(getcwd()) === 'gestion') {
    $base_path = '../';
}

// 2. Información del usuario
$logged_in = isset($_SESSION['user_id']);
$user_role = $_SESSION['user_role'] ?? 'Invitado';
$user_display = $logged_in ? 
    ($_SESSION['user_full_name'] . ' (' . $user_role . ')') : 
    'Invitado';

// 3. Definición de privilegios (Centralizado)
$es_gerente_o_admin = ($user_role === 'Gerente' || $user_role === 'Administrador');
$es_solo_admin = ($user_role === 'Administrador');

// 4. LÓGICA DE REDIRECCIÓN: Si intenta entrar al Dashboard sin ser Gerente/Admin
$pagina_activa = $pagina_activa ?? '';
if ($pagina_activa === 'inicio' && !$es_gerente_o_admin) {
    header("Location: " . $base_path . "gestion/form_transacciones.php");
    exit();
}
?>

<nav class="main-menu">
    <div class="logo">SCL | Control y Liquidación</div>
    
    <ul>
        <?php if ($es_gerente_o_admin): ?>
            <li>
                <a href="<?php echo $base_path; ?>index.php" class="<?php echo ($pagina_activa === 'inicio' ? 'active' : ''); ?>">
                    Dashboard
                </a>
            </li>
        <?php endif; ?>
                
        <li class="dropdown">
            <a href="#">Operaciones Diarias</a>
            <div class="dropdown-content">
                <a href="<?php echo $base_path; ?>gestion/form_jornada.php">Gestión de Jornada (C.4)</a>
                <hr>
                <a href="<?php echo $base_path; ?>gestion/form_transacciones.php">Registro de Venta (A.4)</a>
                <hr>
                <a href="<?php echo $base_path; ?>gestion/form_registro_egresos.php">Registro de egresos</a>
                <hr>
                <a href="<?php echo $base_path; ?>gestion/form_inventario.php">📦 Inventario (D.5)</a>
            </div>
        </li>

        <?php if ($es_gerente_o_admin): ?>
            <li class="dropdown">
                <a href="#">Administración</a>
                <div class="dropdown-content">
                    <a href="<?php echo $base_path; ?>gestion/gestion_usuarios.php">👤 Usuarios y Privilegios</a>
                    <a href="<?php echo $base_path; ?>gestion/form_parametros.php">⚙️ Parámetros (D.4)</a>
                    <a href="<?php echo $base_path; ?>gestion/admin_metodos_pago.php">💳 Métodos de pago</a>
                    <a href="<?php echo $base_path; ?>gestion/gestion_movimientos.php">🔄 Tipos de Movimiento</a>
                </div>
            </li>
        <?php endif; ?>
        
        <?php if ($es_gerente_o_admin): ?>
            <li class="dropdown">
                <a href="#">Reportes Firmados</a>
                <div class="dropdown-content">
                    <a href="<?php echo $base_path; ?>reporte_a1.php">Reporte A.1 (Caja)</a>
                    <a href="<?php echo $base_path; ?>reporte_a2.php">Reporte A.2 (Banco)</a>
                </div>
            </li>
        <?php endif; ?>

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