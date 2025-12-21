<?php
// gestion/admin_metodos_pago.php
include '../includes/auth.php'; 
require_login(); 
include '../includes/db_connect.php'; 

// Verificación estricta de privilegios para el usuario Administrador
if ($_SESSION['user_role'] != 'Administrador') {
    header("Location: ../dashboard.php?error=sin_permiso");
    exit();
}

$mensaje = "";

// --- PROCESAR ACCIONES DEL CRUD ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. AGREGAR NUEVO MÉTODO
    if (isset($_POST['btn_guardar'])) {
        $nombre = $conn->real_escape_string($_POST['nombre_metodo']);
        $sql = "INSERT INTO metodos_pago (nombre_metodo, activo) VALUES ('$nombre', 1)";
        if ($conn->query($sql)) $mensaje = "✅ Nuevo método registrado.";
    }
    
    // 2. MODIFICAR NOMBRE EXISTENTE
    if (isset($_POST['btn_editar'])) {
        $id = intval($_POST['id_metodo']);
        $nombre = $conn->real_escape_string($_POST['nombre_editado']);
        $sql = "UPDATE metodos_pago SET nombre_metodo = '$nombre' WHERE id_metodo = $id";
        if ($conn->query($sql)) $mensaje = "✅ Nombre actualizado correctamente.";
    }
    
    // 3. CAMBIAR ESTADO (Activar/Desactivar)
    if (isset($_POST['btn_estado'])) {
        $id = intval($_POST['id_metodo']);
        $nuevo_estado = intval($_POST['estado_current']) == 1 ? 0 : 1;
        $conn->query("UPDATE metodos_pago SET activo = $nuevo_estado WHERE id_metodo = $id");
        $mensaje = "✅ Estado de disponibilidad actualizado.";
    }
}

// OBTENER TODOS LOS MÉTODOS
$resultado = $conn->query("SELECT * FROM metodos_pago ORDER BY id_metodo ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administrar Métodos de Pago</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .badge-activo { background: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; }
        .badge-inactivo { background: #fee2e2; color: #991b1b; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; }
        .input-edit { padding: 5px; border: 1px solid #ccc; border-radius: 4px; width: 150px; }
    </style>
</head>
<body>
    <?php include '../includes/menu.php'; ?>

    <div style="padding: 30px; max-width: 900px; margin: 0 auto;">
        <h1>💳 Configuración de Métodos de Pago</h1>
        
        <?php if ($mensaje): ?>
            <div class="alerta-verde" style="margin-bottom: 20px; padding: 15px;"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <div class="panel" style="background:white; padding:20px; border-radius:10px; margin-bottom:30px; border: 1px solid #e2e8f0;">
            <h3>➕ Registrar Nuevo Método</h3>
            <form method="POST" style="display:flex; gap:10px; margin-top:10px;">
                <input type="text" name="nombre_metodo" placeholder="Nombre (ej. Zelle, Transferencia Banesco)" required style="flex:1; padding:10px;">
                <button type="submit" name="btn_guardar" class="btn-login" style="margin:0; padding:10px 25px; background: #2563eb;">Añadir</button>
            </form>
        </div>

        <div class="panel" style="background:white; padding:20px; border-radius:10px; border: 1px solid #e2e8f0;">
            <h3>Métodos Registrados</h3>
            <table style="width:100%; border-collapse:collapse; margin-top:15px;">
                <thead>
                    <tr style="background:#f8fafc; text-align:left; border-bottom: 2px solid #e2e8f0;">
                        <th style="padding:12px;">ID</th>
                        <th style="padding:12px;">Nombre del Método</th>
                        <th style="padding:12px;">Estado</th>
                        <th style="padding:12px; text-align:center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $resultado->fetch_assoc()): ?>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:12px;"><?php echo $row['id_metodo']; ?></td>
                        
                        <td style="padding:12px;">
                            <form method="POST" style="display:flex; gap:5px;">
                                <input type="hidden" name="id_metodo" value="<?php echo $row['id_metodo']; ?>">
                                <input type="text" name="nombre_editado" value="<?php echo $row['nombre_metodo']; ?>" class="input-edit">
                                <button type="submit" name="btn_editar" style="background:#64748b; color:white; border:none; padding:5px; border-radius:4px; cursor:pointer; font-size:0.75rem;">💾 Guardar</button>
                            </form>
                        </td>

                        <td style="padding:12px;">
                            <span class="<?php echo $row['activo'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                                <?php echo $row['activo'] ? '● Disponible' : '○ Deshabilitado'; ?>
                            </span>
                        </td>

                        <td style="padding:12px; text-align:center;">
                            <form method="POST">
                                <input type="hidden" name="id_metodo" value="<?php echo $row['id_metodo']; ?>">
                                <input type="hidden" name="estado_current" value="<?php echo $row['activo']; ?>">
                                <button type="submit" name="btn_estado" style="padding:6px 12px; border-radius:4px; border:1px solid #ccc; cursor:pointer; background: <?php echo $row['activo'] ? '#fff1f2' : '#f0fdf4'; ?>;">
                                    <?php echo $row['activo'] ? '🚫 Desactivar' : '✅ Activar'; ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>