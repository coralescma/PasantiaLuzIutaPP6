<?php
// ===============================================
// Bloque PHP 1: SEGURIDAD, CONEXIÓN y LÓGICA DE CARGA
// ===============================================
include '../includes/auth.php'; 
require_login(); 
include '../includes/db_connect.php'; 

$mensaje_estado = "";

// 1. Obtener parámetros y fondos
$sql_params = "SELECT umbral_tolerancia_efectivo, efectivo_requiere_conteo_inicial, fondo_caja_inicial FROM parametros_negocio WHERE id_parametro = 1";
$params_result = $conn->query($sql_params);
$params = $params_result->fetch_assoc();
$fondo_caja_inicial = $params['fondo_caja_inicial'] ?? 200.00;
$requiere_conteo_inicial = $params['efectivo_requiere_conteo_inicial'] ?? 1;

// 2. Obtener lista de supervisores para el campo de selección
$sql_supervisores = "SELECT id_usuario, user_full_name FROM usuarios WHERE role = 'Supervisor' OR role = 'Admin'";
$resultado_supervisores = $conn->query($sql_supervisores);

// 3. Obtener el total de efectivo registrado para el día (solo lectura/validación)
$fecha_hoy = date('Y-m-d');
$sql_venta_registrada = "SELECT SUM(monto_venta) AS total_registrado FROM transacciones 
                         WHERE fecha_venta = '{$fecha_hoy}' AND tipo_cobro = 'Efectivo' AND es_egreso = 0";
$resultado_venta = $conn->query($sql_venta_registrada);
$total_venta_registrada = $resultado_venta->fetch_assoc()['total_registrado'] ?? 0.00;

$total_efectivo_esperado = $total_venta_registrada;
if ($requiere_conteo_inicial) {
    $total_efectivo_esperado += $fondo_caja_inicial;
}


// ===============================================
// Bloque PHP 2: LÓGICA DE PROCESAMIENTO (POST)
// ===============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conteo_manual = floatval($_POST['conteo_manual_efectivo']);
    $supervisor_id = intval($_POST['supervisor_cierre']);
    $observaciones = $conn->real_escape_string($_POST['observaciones']);
    $cajero_id = $_SESSION['user_id'];

    $diferencia = $conteo_manual - $total_efectivo_esperado;
    $umbral = $params['umbral_tolerancia_efectivo'];
    
    // Determinar el Código de Validación basado en la lógica del Reporte A.1
    $codigo_final = (abs($diferencia) > $umbral) ? 'X' : 'Z';
    
    // Aquí se insertaría el registro de cierre en una nueva tabla 'cierres_caja'
    $sql_insert = "INSERT INTO cierres_caja 
                   (fecha_cierre, id_cajero_fk, id_supervisor_fk, monto_contado, monto_esperado, diferencia, codigo_validacion, observaciones) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                   
    $stmt = $conn->prepare($sql_insert);
    $stmt->bind_param("siiiddss", $fecha_hoy, $cajero_id, $supervisor_id, $conteo_manual, $total_efectivo_esperado, $diferencia, $codigo_final, $observaciones);

    if ($stmt->execute()) {
        $mensaje_estado = "<div class='alerta-verde'>✅ Cierre de caja registrado exitosamente. Código: **{$codigo_final}**.</div>";
        // Aquí se podría redirigir al Reporte A.1 para que el cajero imprima.
    } else {
        $mensaje_estado = "<div class='alerta-roja'>❌ Error al registrar el cierre: " . $stmt->error . "</div>";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Formulario Cierre de Caja</title>
    <link rel="stylesheet" href="../css/style.css"> 
</head>
<body>
    <?php include '../includes/menu.php'; ?>
    
    <div class="report-container">
        <h1>🔒 Cierre Diario de Caja (Día: <?php echo $fecha_hoy; ?>)</h1>
        <?php echo $mensaje_estado; ?>
        
        <form method="POST" action="form_cierre_caja.php">
            
            <fieldset>
                <legend>Datos de Trazabilidad</legend>
                <p><strong>Cajero Responsable:</strong> <?php echo $_SESSION['user_full_name'] ?? 'N/A'; ?></p>
                
                <label for="supervisor_cierre">Supervisor/Auditor de Cierre (D4):</label>
                <select name="supervisor_cierre" id="supervisor_cierre" required>
                    <option value="">-- Seleccione Supervisor --</option>
                    <?php while ($sup = $resultado_supervisores->fetch_assoc()): ?>
                        <option value="<?php echo $sup['id_usuario']; ?>"><?php echo $sup['user_full_name']; ?></option>
                    <?php endwhile; ?>
                </select>
            </fieldset>

            <fieldset>
                <legend>Cuadre de Efectivo</legend>
                
                <p><strong>Fondo de Caja (Inicial):</strong> $<?php echo number_format($requiere_conteo_inicial ? $fondo_caja_inicial : 0.00, 2); ?></p>
                <p><strong>Venta Efectivo Registrada (D1):</strong> $<?php echo number_format($total_venta_registrada, 2); ?></p>
                <p style="font-size: 1.2em; font-weight: bold;">TOTAL ESPERADO EN CAJA: $<?php echo number_format($total_efectivo_esperado, 2); ?></p>
                
                <hr>

                <label for="conteo_manual_efectivo">Monto Contado Físicamente (Código X/Z):</label>
                <input type="number" step="0.01" min="0" name="conteo_manual_efectivo" id="conteo_manual_efectivo" required placeholder="Ej: 1475.00">
            </fieldset>
            
            <fieldset>
                <legend>Observaciones</legend>
                <label for="observaciones">Observaciones (Requerido si hay descuadre):</label>
                <textarea name="observaciones" id="observaciones" rows="3"></textarea>
            </fieldset>

            <button type="submit" class="boton-primario">Registrar Cierre y Generar Reporte A.1</button>
        </form>
    </div>
</body>
</html>