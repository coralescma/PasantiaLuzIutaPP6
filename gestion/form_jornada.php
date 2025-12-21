<?php
// gestion/form_jornada.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../includes/auth.php'; 
require_login(); 
include '../includes/db_connect.php'; 

$mensaje = "";
$clase_mensaje = "";

// 1. LÓGICA: PROCESAR APERTURA
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_abrir_jornada'])) {
    $fecha = date('Y-m-d');
    $id_usuario = $_SESSION['user_id'];
    $monto_apertura = floatval($_POST['monto_apertura']);

    // Intentamos insertar con los nombres de columna más probables de la BD antigua
    $sql_ins = "INSERT INTO control_jornadas (id_usuario_fk, fecha, monto_apertura, estado_jornada) 
                VALUES ($id_usuario, '$fecha', $monto_apertura, 1)";
    
    if ($conn->query($sql_ins)) {
        $mensaje = "✅ Jornada abierta con éxito.";
        $clase_mensaje = "alerta-verde";
    } else {
        // Si falla por nombres de columna, intentamos el segundo set de nombres comunes
        $sql_alt = "INSERT INTO control_jornadas (id_usuario_apertura_fk, fecha_apertura, fondo_apertura, estado_jornada) 
                    VALUES ($id_usuario, '$fecha', $monto_apertura, 1)";
        if($conn->query($sql_alt)){
            $mensaje = "✅ Jornada abierta (vía alterna).";
            $clase_mensaje = "alerta-verde";
        } else {
            $mensaje = "❌ Error crítico de BD: " . $conn->error;
            $clase_mensaje = "alerta-roja";
        }
    }
}

// 2. OBTENER LISTA DE JORNADAS
$resultado_jornadas = $conn->query("SELECT * FROM control_jornadas ORDER BY id_jornada DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SCL - Control de Jornadas</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .form-box { background: white; padding: 25px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #ddd; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 12px; border: 1px solid #eee; text-align: left; }
        th { background: #f8fafc; color: #475569; }
        .btn-add { background: #10b981; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .estado-abierta { color: #059669; font-weight: bold; background: #d1fae5; padding: 4px 8px; border-radius: 4px; }
        .estado-cerrada { color: #64748b; font-weight: bold; background: #f1f5f9; padding: 4px 8px; border-radius: 4px; }
        .alerta-verde { background: #dcfce7; color: #166534; padding: 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid #bbf7d0; }
        .alerta-roja { background: #fee2e2; color: #991b1b; padding: 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid #fecaca; }
    </style>
</head>
<body>
    <?php include '../includes/menu.php'; ?>

    <div class="report-container">
        <h1>🗓️ Control de Jornadas</h1>

        <?php if ($mensaje): ?>
            <div class="<?php echo $clase_mensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <div class="form-box">
            <h3>Nueva Apertura de Turno</h3>
            <form method="POST" style="display: flex; gap: 15px; align-items: flex-end;">
                <div style="flex: 1;">
                    <label style="font-weight: bold; display: block; margin-bottom: 5px;">Monto Inicial en Caja ($):</label>
                    <input type="number" step="0.01" name="monto_apertura" value="0.00" required style="width:100%; padding:10px; border: 1px solid #ccc; border-radius:4px;">
                </div>
                <button type="submit" name="btn_abrir_jornada" class="btn-add">REGISTRAR APERTURA</button>
            </form>
        </div>

        <section>
            <h3>Historial de Turnos</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Fondo Apertura</th>
                        <th>Estado</th>
                        <th>Diferencia</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($resultado_jornadas && $resultado_jornadas->num_rows > 0): 
                        while($row = $resultado_jornadas->fetch_assoc()): 
                            // SOLUCIÓN A LOS WARNINGS: Detectar nombres de columnas dinámicamente
                            $fecha_display = $row['fecha'] ?? $row['fecha_apertura'] ?? 'Sin fecha';
                            $monto_display = $row['monto_apertura'] ?? $row['fondo_apertura'] ?? 0.00;
                            $dif_display = $row['diferencia'] ?? 0.00;
                    ?>
                        <tr>
                            <td><?php echo $row['id_jornada']; ?></td>
                            <td><?php echo $fecha_display; ?></td>
                            <td>$<?php echo number_format($monto_display, 2); ?></td>
                            <td>
                                <span class="<?php echo ($row['estado_jornada'] == 1) ? 'estado-abierta' : 'estado-cerrada'; ?>">
                                    <?php echo ($row['estado_jornada'] == 1) ? 'ABIERTA' : 'CERRADA'; ?>
                                </span>
                            </td>
                            <td>$<?php echo number_format($dif_display, 2); ?></td>
                        </tr>
                    <?php 
                        endwhile; 
                    else: 
                    ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 20px; color: gray;">No hay jornadas registradas.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </div>
</body>
</html>