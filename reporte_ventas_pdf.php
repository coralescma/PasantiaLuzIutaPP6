<?php
// ===============================================
// Bloque PHP 1: SEGURIDAD, CONEXIÓN y LÓGICA
// ===============================================

// 1. INCLUSIÓN DE LIBRERÍAS (Si usas Composer)
require 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// 2. SEGURIDAD Y CONEXIÓN
include 'includes/auth.php'; 
require_login(); 
include 'includes/db_connect.php'; 

// 3. OBTENCIÓN DE DATOS (Personalización del reporte)
$fecha_inicio = '2025-12-01'; // Ejemplo: Personalización por rango de fecha
$fecha_fin = '2025-12-15';

$sql_ventas = "
    SELECT 
        t.id_transaccion, 
        t.fecha_venta, 
        t.monto_venta, 
        t.tipo_cobro,
        u.user_full_name AS cajero
    FROM transacciones t
    JOIN usuarios u ON t.id_usuario_fk = u.id_usuario
    WHERE t.es_egreso = 0 AND t.fecha_venta BETWEEN '{$fecha_inicio}' AND '{$fecha_fin}'
    ORDER BY t.fecha_venta DESC;
";

$resultado_ventas = $conn->query($sql_ventas);
$total_ventas_periodo = 0;
$datos_reporte = [];

if ($resultado_ventas) {
    while ($fila = $resultado_ventas->fetch_assoc()) {
        $datos_reporte[] = $fila;
        $total_ventas_periodo += $fila['monto_venta'];
    }
}


// ===============================================
// Bloque PHP 2: GENERACIÓN DEL HTML PARA EL PDF
// (Esta es la parte más personalizable)
// ===============================================

// Iniciar la captura del output buffer para guardar todo el HTML
ob_start(); 
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reporte de Ventas SCL</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
        .header { background-color: #f2f2f2; padding: 10px; text-align: center; border-bottom: 2px solid #333; }
        .header h1 { color: #004d40; margin: 0; }
        .periodo { text-align: right; font-size: 10px; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 10px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #004d40; color: white; }
        .total-box { margin-top: 20px; padding: 10px; border: 2px solid #004d40; width: 300px; float: right; }
        .total-box p { margin: 0; font-size: 14px; font-weight: bold; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 8px; color: #777; }
    </style>
</head>
<body>

    <div class="header">
        <h1>REPORTE DE VENTAS DETALLADO (SCL)</h1>
    </div>

    <div class="periodo">
        Generado por: <?php echo $_SESSION['user_full_name'] ?? 'Administrador'; ?><br>
        Periodo: <?php echo $fecha_inicio; ?> al <?php echo $fecha_fin; ?><br>
        Fecha de Emisión: <?php echo date('Y-m-d H:i:s'); ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Cajero</th>
                <th>Tipo Cobro</th>
                <th>Monto Venta</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($datos_reporte)): ?>
                <?php foreach ($datos_reporte as $venta): ?>
                <tr>
                    <td><?php echo $venta['id_transaccion']; ?></td>
                    <td><?php echo $venta['fecha_venta']; ?></td>
                    <td><?php echo $venta['cajero']; ?></td>
                    <td><?php echo $venta['tipo_cobro']; ?></td>
                    <td>$<?php echo number_format($venta['monto_venta'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center;">No hay transacciones registradas en este periodo.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="total-box">
        <p>TOTAL GENERAL DE VENTAS: $<?php echo number_format($total_ventas_periodo, 2); ?></p>
    </div>
    
    <div class="footer">
        Este reporte es un documento de control interno generado por el Sistema SCL.
    </div>

</body>
</html>

<?php
// Obtener el contenido HTML capturado
$html = ob_get_clean();


// ===============================================
// Bloque PHP 3: CONFIGURACIÓN Y GENERACIÓN DEL PDF
// ===============================================

$options = new Options();
// Habilitar el procesamiento de imágenes y estilos remotos si es necesario
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true); 

$dompdf = new Dompdf($options);

// Cargar el HTML que generamos
$dompdf->loadHtml($html);

// (Opcional) Configurar tamaño y orientación del papel (A4 es estándar)
$dompdf->setPaper('A4', 'portrait');

// Renderizar el PDF
$dompdf->render();

// Enviar el PDF al navegador
$filename = "Reporte_Ventas_{$fecha_inicio}_a_{$fecha_fin}.pdf";
$dompdf->stream($filename, ["Attachment" => true]); // 'true' fuerza la descarga, 'false' lo muestra en el navegador
?>