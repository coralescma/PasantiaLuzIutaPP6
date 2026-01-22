<?php
include 'includes/auth.php'; 
require_login(['Administrador', 'Contador']); 
include 'includes/db_connect.php'; 

// 1. Capturar parámetros de búsqueda
$anio_sel = isset($_GET['anio']) ? intval($_GET['anio']) : date('Y');
$mes_sel  = isset($_GET['mes']) ? intval($_GET['mes']) : date('m');
$periodo  = isset($_GET['periodo']) ? $_GET['periodo'] : 'personalizado';

$condicion_fecha = "";
$titulo_p = "Periodo: $mes_sel/$anio_sel";

try {
    // 2. Lógica de Filtros Dinámicos
    if ($periodo == 'dia') {
        $condicion_fecha = "DATE(t.fecha_transaccion) = CURDATE()";
        $titulo_p = "Hoy (" . date('d/m/Y') . ")";
    } elseif ($periodo == 'semana') {
        $condicion_fecha = "YEARWEEK(t.fecha_transaccion, 1) = YEARWEEK(CURDATE(), 1)";
        $titulo_p = "Esta Semana";
    } else {
        // Filtro por selección de Año y Mes (Soluciona tu problema de datos de 2025)
        $condicion_fecha = "YEAR(t.fecha_transaccion) = $anio_sel AND MONTH(t.fecha_transaccion) = $mes_sel";
    }

    // 3. Consulta de Ingresos y Egresos (Cabecera + Detalle)
    // Usamos el detalle para los ingresos para mayor precisión de inventario
    $sql_resumen = "SELECT 
        SUM(CASE WHEN t.es_egreso = 0 THEN (dt.cantidad * dt.precio_venta) ELSE 0 END) as ingresos,
        SUM(CASE WHEN t.es_egreso = 1 THEN t.monto_total ELSE 0 END) as egresos
        FROM transacciones t 
        LEFT JOIN detalle_transaccion dt ON t.id_registro = dt.id_transaccion_fk
        WHERE $condicion_fecha";
    
    $res_resumen = $conn->query($sql_resumen);
    $resumen = $res_resumen->fetch_assoc();

    // 4. Top de Productos del periodo seleccionado
    $sql_productos = "SELECT i.nombre_producto, SUM(dt.cantidad) as total_cant, SUM(dt.cantidad * dt.precio_venta) as total_usd
                      FROM detalle_transaccion dt
                      JOIN transacciones t ON dt.id_transaccion_fk = t.id_registro
                      JOIN inventario i ON dt.id_producto_fk = i.id_producto
                      WHERE $condicion_fecha AND t.es_egreso = 0
                      GROUP BY i.id_producto ORDER BY total_usd DESC LIMIT 10";
    $res_prod = $conn->query($sql_productos);

} catch (Exception $e) {
    $error_msg = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Avanzado de Ventas</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-4 md:p-8">

    <div class="max-w-6xl mx-auto">
        <div class="bg-white p-6 rounded-xl shadow-md mb-6 border-l-4 border-blue-600">
            <form method="GET" class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Consultar Año</label>
                    <select name="anio" class="border rounded-lg p-2 bg-gray-50 focus:ring-2 focus:ring-blue-400 outline-none">
                        <?php 
                        for($i = date('Y'); $i >= 2024; $i--) {
                            echo "<option value='$i' ".($anio_sel == $i ? 'selected' : '').">$i</option>";
                        }
                        ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Mes</label>
                    <select name="mes" class="border rounded-lg p-2 bg-gray-50 focus:ring-2 focus:ring-blue-400 outline-none">
                        <?php 
                        $meses = [1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril', 5=>'Mayo', 6=>'Junio', 
                                  7=>'Julio', 8=>'Agosto', 9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre'];
                        foreach($meses as $num => $nombre) {
                            echo "<option value='$num' ".($mes_sel == $num ? 'selected' : '').">$nombre</option>";
                        }
                        ?>
                    </select>
                </div>
                <button type="submit" name="periodo" value="personalizado" class="bg-blue-600 text-white px-6 py-2 rounded-lg font-bold hover:bg-blue-700 transition">
                    Filtrar Datos
                </button>
                <div class="h-10 border-l mx-2 hidden md:block"></div>
                <div class="flex gap-2">
                    <a href="?periodo=dia" class="px-4 py-2 bg-gray-200 rounded-lg text-sm font-semibold hover:bg-gray-300">Hoy</a>
                    <a href="?periodo=semana" class="px-4 py-2 bg-gray-200 rounded-lg text-sm font-semibold hover:bg-gray-300">Semana</a>
                </div>
            </form>
        </div>

        <h2 class="text-xl font-bold text-gray-800 mb-4"><?php echo $titulo_p; ?></h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                <span class="text-gray-400 text-sm font-medium">Ventas Totales (Bruto)</span>
                <div class="text-3xl font-black text-emerald-600">$<?php echo number_format($resumen['ingresos'] ?? 0, 2); ?></div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                <span class="text-gray-400 text-sm font-medium">Egresos / Cortesías</span>
                <div class="text-3xl font-black text-rose-500">$<?php echo number_format($resumen['egresos'] ?? 0, 2); ?></div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 bg-blue-50">
                <span class="text-blue-400 text-sm font-medium">Balance Neto</span>
                <div class="text-3xl font-black text-blue-700">$<?php echo number_format(($resumen['ingresos'] ?? 0) - ($resumen['egresos'] ?? 0), 2); ?></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-gray-800 text-white uppercase text-xs">
                    <tr>
                        <th class="px-6 py-4">Producto</th>
                        <th class="px-6 py-4 text-center">Unidades</th>
                        <th class="px-6 py-4 text-right">Monto Generado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if($res_prod && $res_prod->num_rows > 0): ?>
                        <?php while($p = $res_prod->fetch_assoc()): ?>
                        <tr class="hover:bg-blue-50">
                            <td class="px-6 py-4 font-bold text-gray-700"><?php echo $p['nombre_producto']; ?></td>
                            <td class="px-6 py-4 text-center text-gray-600"><?php echo $p['total_cant']; ?></td>
                            <td class="px-6 py-4 text-right font-mono font-bold">$<?php echo number_format($p['total_usd'], 2); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="p-10 text-center text-gray-400 italic">No hay registros para este periodo.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>