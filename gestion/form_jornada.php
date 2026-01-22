<?php
// gestion/form_jornada.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../includes/auth.php'; 
require_login(['Administrador', 'Gerente', 'Supervisor']); 

include '../includes/db_connect.php'; 

$mensaje = "";
$clase_mensaje = "";

// 1. VERIFICAR JORNADA ACTIVA
$id_jornada_activa = obtenerJornadaActiva($conn);

// 2. PROCESAR APERTURA
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_abrir_jornada'])) {
    if ($id_jornada_activa) {
        $mensaje = "⚠️ Ya existe una jornada abierta.";
        $clase_mensaje = "alerta-roja";
    } else {
        $id_usuario = $_SESSION['user_id'];
        $monto = floatval($_POST['monto_apertura']);

        $sql_ins = "INSERT INTO control_jornadas (id_usuario_apertura_fk, fecha_apertura, monto_apertura, estado_jornada) 
                    VALUES ($id_usuario, NOW(), $monto, 'Abierta')";
        
        if ($conn->query($sql_ins)) {
            header("Location: ../dashboard.php?msj=exito");
            exit();
        } else {
            $mensaje = "❌ Error en BD: " . $conn->error;
            $clase_mensaje = "alerta-roja";
        }
    }
}

// 3. CONSULTAR HISTORIAL (Consulta ajustada para evitar duplicidad)
$sql_historial = "SELECT j.id_jornada, j.fecha_apertura, j.monto_apertura, j.estado_jornada, u.user_full_name 
                  FROM control_jornadas j 
                  LEFT JOIN usuarios u ON j.id_usuario_apertura_fk = u.id_usuario 
                  ORDER BY j.id_jornada DESC LIMIT 10";
$historial = $conn->query($sql_historial);

if (!$historial) {
    $error_historial = "Error al cargar historial: " . $conn->error;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Apertura de Jornada - SCL</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .container { max-width: 700px; margin: 30px auto; background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .alerta-roja { background: #fee2e2; color: #b91c1c; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #fecaca; }
        .btn-abrir { background: #10b981; color: white; width: 100%; padding: 15px; border: none; border-radius: 5px; font-size: 1.1em; cursor: pointer; }
        .btn-abrir:hover { background: #059669; }
    </style>
</head>
<body>
    <?php include '../includes/menu.php'; ?>

    <div class="container">
        <h1>☀️ Control de Jornada</h1>

        <?php if ($mensaje): ?>
            <div class="<?php echo $clase_mensaje; ?>"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <?php if (!$id_jornada_activa): ?>
            <form method="POST">
                <label>Monto Inicial en Caja (Fondo):</label>
                <input type="number" step="0.01" name="monto_apertura" required 
                       style="width:100%; padding:12px; margin:10px 0; font-size:1.5em; text-align:center;">
                <button type="submit" name="btn_abrir_jornada" class="btn-abrir">INICIAR JORNADA AHORA</button>
            </form>
        <?php else: ?>
            <div style="text-align:center; padding:20px; background:#dcfce7; color:#166534; border-radius:8px;">
                <h3>Jornada Activa detectada</h3>
                <a href="form_transacciones.php" class="btn-login">Ir a Ventas</a>
            </div>
        <?php endif; ?>

        <h3 style="margin-top:40px;">Historial de Aperturas</h3>
        <?php if (isset($error_historial)): ?>
            <p style="color:red;"><?php echo $error_historial; ?></p>
        <?php else: ?>
            <table width="100%" border="1" style="border-collapse:collapse; margin-top:10px;">
                <thead>
                    <tr style="background:#f8fafc;">
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Monto</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
    <?php 
    // Se eliminó data_seek(0) para evitar la repetición de datos en servidores remotos
    if ($historial && $historial->num_rows > 0):
        while($row = $historial->fetch_assoc()): 
    ?>
    <tr>
        <td><?php echo htmlspecialchars($row['id_jornada']); ?></td>
        <td><?php echo htmlspecialchars($row['fecha_apertura']); ?></td>
        <td>Bs <?php echo number_format($row['monto_apertura'], 2); ?></td>
        <td><?php echo htmlspecialchars($row['estado_jornada']); ?></td>
    </tr>
    <?php 
        endwhile; 
    else:
    ?>
    <tr><td colspan="4">No hay registros previos.</td></tr>
    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>