<?php
// gestion/form_parametros.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../includes/auth.php';
require_login();
include '../includes/db_connect.php';

$mensaje = "";

// 1. PROCESAR ACTUALIZACIÓN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_params'])) {
    $id = intval($_POST['id_parametro']);
    $umbral_efectivo = $_POST['umbral_efectivo'];
    $umbral_banco = $_POST['umbral_banco'];
    $conteo_inicial = $_POST['conteo_inicial'];

    $sql = "UPDATE parametros_negocio SET 
            umbral_tolerancia_efectivo = ?, 
            umbral_conciliacion_bancaria = ?, 
            efectivo_requiere_conteo_inicial = ? 
            WHERE id_parametro = ?";
            
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ddii", $umbral_efectivo, $umbral_banco, $conteo_inicial, $id);
        if ($stmt->execute()) {
            $mensaje = "<div class='success'>✅ Parámetros actualizados con éxito.</div>";
        } else {
            $mensaje = "<div class='error'>❌ Error al ejecutar: " . $stmt->error . "</div>";
        }
    }
}

// 2. OBTENER DATOS (Se ejecuta siempre, para mostrar los datos actuales o los recién actualizados)
$res = $conn->query("SELECT * FROM parametros_negocio LIMIT 1");
$p = ($res) ? $res->fetch_assoc() : null;

// Si la tabla está vacía, intentamos crear el registro inicial para que siempre haya algo que editar
if (!$p) {
    $conn->query("INSERT INTO parametros_negocio (id_parametro, umbral_tolerancia_efectivo, umbral_conciliacion_bancaria, efectivo_requiere_conteo_inicial) VALUES (1, 5.00, 2.00, 1)");
    $res = $conn->query("SELECT * FROM parametros_negocio LIMIT 1");
    $p = $res->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SCL - Parámetros</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .split-container { display: flex; gap: 20px; margin-top: 20px; }
        .form-side { flex: 1; background: #fff; padding: 25px; border-radius: 8px; border: 1px solid #ddd; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .info-side { flex: 1.5; background: #f8f9fa; padding: 25px; border-radius: 8px; border: 1px solid #ddd; color: #444; }
        .input-box { width: 100%; padding: 12px; margin: 8px 0 18px 0; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; display: block; font-size: 14px; }
        .success { background: #f0fdf4; color: #166534; padding: 15px; border-radius: 6px; border-left: 5px solid #22c55e; margin-bottom: 20px; }
        .error { background: #fef2f2; color: #991b1b; padding: 15px; border-radius: 6px; border-left: 5px solid #ef4444; margin-bottom: 20px; }
        h3 { margin-top: 0; color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        label { font-weight: bold; color: #374151; }
    </style>
</head>
<body>
    <?php include '../includes/menu.php'; ?>

    <div class="report-container">
        <h1>⚙️ Configuración Maestra del Sistema</h1>
        <?php echo $mensaje; ?>

        <div class="split-container">
            <div class="form-side">
                <h3>Editar Parámetros</h3>
                <form method="POST">
                    <input type="hidden" name="actualizar_params" value="1">
                    <input type="hidden" name="id_parametro" value="<?php echo $p['id_parametro'] ?? 1; ?>">

                    <label>Umbral Tolerancia Efectivo ($)</label>
                    <input type="number" step="0.01" name="umbral_efectivo" class="input-box" 
                           value="<?php echo htmlspecialchars($p['umbral_tolerancia_efectivo'] ?? '0.00'); ?>">

                    <label>Umbral Conciliación Banco (%)</label>
                    <input type="number" step="0.01" name="umbral_banco" class="input-box" 
                           value="<?php echo htmlspecialchars($p['umbral_conciliacion_bancaria'] ?? '0.00'); ?>">

                    <label>Protocolo de Apertura</label>
                    <select name="conteo_inicial" class="input-box">
                        <option value="1" <?php echo (isset($p['efectivo_requiere_conteo_inicial']) && $p['efectivo_requiere_conteo_inicial'] == 1) ? 'selected' : ''; ?>>SÍ - Conteo Obligatorio</option>
                        <option value="0" <?php echo (isset($p['efectivo_requiere_conteo_inicial']) && $p['efectivo_requiere_conteo_inicial'] == 0) ? 'selected' : ''; ?>>NO - Opcional</option>
                    </select>

                    <button type="submit" class="button" style="width:100%; padding:12px; background: #2563eb; color:white; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">💾 GUARDAR CONFIGURACIÓN</button>
                </form>
            </div>

            <div class="info-side">
                <h3>Guía de Control de Riesgos</h3>
                <p>Estos valores afectan directamente los semáforos del Dashboard:</p>
                <ul>
                    <li><strong>Tolerancia Efectivo:</strong> Si el faltante en el arqueo supera este monto, el sistema marcará el reporte A.1 con "Código X" (Investigación).</li>
                    <li><strong>Umbral Banco:</strong> Porcentaje máximo de diferencia permitido entre lo que el cajero marcó como TPV y lo que el banco depositó realmente.</li>
                    <li><strong>Protocolo de Apertura:</strong> Si está activo, el cajero debe contar el fondo de caja antes de iniciar ventas.</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>