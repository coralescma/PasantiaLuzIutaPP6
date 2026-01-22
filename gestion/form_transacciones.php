<?php
// gestion/form_transacciones.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../includes/auth.php'; 
require_login(); 
include '../includes/db_connect.php'; 

$mensaje = "";
$clase_mensaje = "";

$id_jornada = obtenerJornadaActiva($conn);

// --- NUEVO: Obtener margen de ganancia de la tabla parametros_negocio ---
$margen_ganancia = 0;
$res_param = $conn->query("SELECT margen_ganancia_estandar FROM parametros_negocio LIMIT 1");
if ($res_param && $fila_p = $res_param->fetch_assoc()) {
    $margen_ganancia = floatval($fila_p['margen_ganancia_estandar']);
}
// -----------------------------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_guardar_venta'])) {
    if (!$id_jornada) {
        $mensaje = "❌ Error: La jornada se cerró mientras procesaba la venta.";
        $clase_mensaje = "alerta-roja";
    } else {
        $conn->begin_transaction();
        try {
            $id_usuario = $_SESSION['user_id'];
            $monto_total = floatval($_POST['monto_total_oculto']);
            
            // Cabecera de transacción
            $sql_trans = "INSERT INTO transacciones (id_usuario_cajero_fk, id_jornada_fk, fecha_transaccion, es_egreso, monto_total) 
                          VALUES ($id_usuario, $id_jornada, NOW(), 0, $monto_total)";
            if (!$conn->query($sql_trans)) throw new Exception("Error en cabecera: " . $conn->error);

            $id_venta = $conn->insert_id;
            
            // --- NUEVO: Procesar Pagos Múltiples ---
            $pagos = json_decode($_POST['pagos_json'], true);
            if (!$pagos) throw new Exception("Debe registrar al menos un pago.");
            
            foreach ($pagos as $pago) {
                $id_metodo = intval($pago['id_metodo']);
                $monto_p = floatval($pago['monto']);
                $ref = $conn->real_escape_string($pago['referencia'] ?? '');
                
                $sql_pago = "INSERT INTO detalle_pago (id_transaccion_fk, id_metodo_fk, monto_pago, referencia_banco) 
                             VALUES ($id_venta, $id_metodo, $monto_p, '$ref')";
                if (!$conn->query($sql_pago)) throw new Exception("Error en registro de pago: " . $conn->error);
            }
            // ---------------------------------------
            
            $detalles = json_decode($_POST['detalles_json'], true);
            if ($detalles) {
                foreach ($detalles as $item) {
                    $id_p   = intval($item['id_producto']);
                    $cant   = intval($item['cantidad']);
                    $precio = floatval($item['precio_venta']);
                    $id_usuario_registro = intval($_SESSION['user_id']);
                    
                    $sql_det = "INSERT INTO detalle_transaccion (id_transaccion_fk, id_producto_fk, cantidad, precio_venta, usuario_autorizador) 
                                VALUES ($id_venta, $id_p, $cant, $precio, $id_usuario_registro)";
                    if (!$conn->query($sql_det)) throw new Exception("Error en detalle");
                    
                    $sql_inv = "UPDATE inventario SET stock_actual = stock_actual - $cant WHERE id_producto = $id_p";
                    if (!$conn->query($sql_inv)) throw new Exception("Error al descontar stock");
                }
            }
            $conn->commit();
            $mensaje = "✅ Venta #$id_venta registrada exitosamente.";
            $clase_mensaje = "alerta-verde";
        } catch (Exception $e) {
            $conn->rollback();
            $mensaje = "❌ Error: " . $e->getMessage();
            $clase_mensaje = "alerta-roja";
        }
    }
}

$productos_json = [];
$metodos_array = [];
if ($id_jornada) {
    $res_prod = $conn->query("SELECT id_producto, nombre_producto, costo_unitario, stock_actual FROM inventario WHERE stock_actual > 0");
    while($p = $res_prod->fetch_assoc()) { 
        $p['nombre_producto'] = htmlspecialchars($p['nombre_producto'], ENT_QUOTES, 'UTF-8');
        $factor = 1 + ($margen_ganancia / 100);
        $p['precio_con_ganancia'] = round($p['costo_unitario'] * $factor, 2);
        $productos_json[] = $p; 
    }
    
    $res_metodos = $conn->query("SELECT id_metodo, nombre_metodo FROM metodos_pago WHERE activo = 1");
    while($m = $res_metodos->fetch_assoc()) {
        $metodos_array[] = $m;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <title>SCL - Registro de Ventas</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <style>
        :root { --primary: #475569; --success: #059669; --danger: #ef4444; }
        .venta-container { max-width: 1300px; margin: 20px auto; display: grid; grid-template-columns: 1fr 420px; gap: 25px; padding: 0 20px; }
        .panel { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); overflow-x: auto; }
        .total-box { font-size: 2.8em; font-weight: 800; color: var(--success); text-align: right; margin: 20px 0; border-top: 2px solid #f1f5f9; padding-top: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; background: #f1f5f9; color: #64748b; font-size: 0.9em; }
        td { padding: 12px; border-bottom: 1px solid #f1f5f9; }
        input, select { padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; width: 100%; box-sizing: border-box; }
        .btn-add { background: var(--primary); color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-confirm { background: var(--success); color: white; width: 100%; padding: 18px; border: none; border-radius: 10px; cursor: pointer; font-size: 1.2em; font-weight: bold; }
        
        .pago-fila { background: #f8fafc; padding: 10px; border-radius: 8px; margin-bottom: 10px; border-left: 4px solid var(--primary); }
        .pago-grid { display: grid; grid-template-columns: 1fr 100px 40px; gap: 8px; margin-top: 5px; }

        .ts-control { border-radius: 8px !important; padding: 10px !important; min-height: 40px; }
        .ts-dropdown { width: auto !important; min-width: 100% !important; max-width: 900px !important; }
    </style>
</head>
<body>
    <?php include '../includes/menu.php'; ?>
    <div style="max-width: 1300px; margin: 30px auto; padding: 0 20px;">
        <h1>🛒 Punto de Venta (POS)</h1>
        <?php if ($mensaje): ?><div class="<?php echo $clase_mensaje; ?>" style="padding:15px; margin-bottom:20px; border-radius:8px;"><?php echo $mensaje; ?></div><?php endif; ?>

        <?php if (!$id_jornada): ?>
            <div style="background:#fee2e2; border:2px solid var(--danger); color:#991b1b; padding:30px; border-radius:12px; text-align:center;">
                <h2>⚠️ ATENCIÓN: SISTEMA BLOQUEADO</h2>
                <p>No existe una Jornada de Trabajo abierta.</p>
                <a href="../dashboard.php" class="btn-add" style="text-decoration:none;">Ir a Inicio</a>
            </div>
        <?php else: ?>
            <form id="formVenta" method="POST" class="venta-container" onsubmit="return validarEnvio()">
                <div class="panel">
                    <h3>Productos en Carrito</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Descripción del Producto (Buscador)</th>
                                <th width="90">Cant.</th>
                                <th width="120">Precio Bs</th>
                                <th width="120">Subtotal</th>
                                <th width="40"></th>
                            </tr>
                        </thead>
                        <tbody id="cuerpoTabla"></tbody>
                    </table>
                    <button type="button" class="btn-add" style="margin-top:20px;" onclick="agregarFila()">+ Añadir Producto (F8)</button>
                </div>

                <div class="panel">
                    <h3>Finalizar Transacción</h3>
                    <p style="font-size:0.85em; color:#64748b; margin-bottom:15px;">Margen de ganancia aplicado: <strong><?php echo $margen_ganancia; ?>%</strong></p>
                    
                    <div id="contenedorPagos">
                        <label>Pagos Registrados:</label>
                        </div>
                    <button type="button" class="btn-add" style="width:100%; margin-bottom:15px; background:#64748b;" onclick="agregarPago()">+ Agregar Pago</button>

                    <div class="total-box">Bs <span id="totalTxt">0.00</span></div>
                    <div style="text-align:right; margin-bottom:15px; font-weight:bold;">
                        Restante: <span id="restanteTxt" style="color:var(--danger)">Bs 0.00</span>
                    </div>

                    <input type="hidden" name="monto_total_oculto" id="monto_total_oculto">
                    <input type="hidden" name="detalles_json" id="detalles_json">
                    <input type="hidden" name="pagos_json" id="pagos_json">
                    
                    <button type="submit" name="btn_guardar_venta" class="btn-confirm">REGISTRAR VENTA</button>
                </div>
            </form>

            <script>
                const productosMaster = <?php echo json_encode($productos_json); ?>;
                const metodosMaster = <?php echo json_encode($metodos_array); ?>;

                // --- LÓGICA DE PRODUCTOS ---
                window.agregarFila = function() {
                    const tbody = document.getElementById('cuerpoTabla');
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><select class="p-select" required></select></td>
                        <td><input type="number" class="c-input" value="1" min="1"></td>
                        <td><input type="number" class="pre-input" step="0.01"></td>
                        <td style="font-weight:bold" class="subtotal-cell">Bs 0.00</td>
                        <td><button type="button" onclick="eliminarFila(this)" style="background:none; border:none; color:#ef4444; font-size:1.5em; cursor:pointer;">&times;</button></td>
                    `;
                    tbody.appendChild(tr);

                    const selectElement = tr.querySelector('.p-select');
                    new TomSelect(selectElement, {
                        create: false,
                        placeholder: "-- Buscar producto... --",
                        maxOptions: 200,
                        onChange: function() { manejarCambio(selectElement); actualizarOpcionesDisponibles(); }
                    });

                    tr.querySelector('.c-input').addEventListener('change', function() { validarStock(this); recalcular(); });
                    tr.querySelector('.pre-input').addEventListener('change', recalcular);
                    actualizarOpcionesDisponibles();
                };

                // --- LÓGICA DE PAGOS MÚLTIPLES ---
                window.agregarPago = function() {
                    const container = document.getElementById('contenedorPagos');
                    const div = document.createElement('div');
                    div.className = 'pago-fila';
                    
                    let opcionesMetodos = metodosMaster.map(m => `<option value="${m.id_metodo}">${m.nombre_metodo}</option>`).join('');
                    
                    div.innerHTML = `
                        <select class="pago-metodo" required>${opcionesMetodos}</select>
                        <div class="pago-grid">
                            <input type="text" class="pago-ref" placeholder="Referencia/Nota">
                            <input type="number" class="pago-monto" step="0.01" placeholder="Monto" required>
                            <button type="button" onclick="this.parentElement.parentElement.remove(); recalcular();" style="color:var(--danger); border:none; background:none; cursor:pointer; font-size:1.2em;">&times;</button>
                        </div>
                    `;
                    container.appendChild(div);
                    
                    // Sugerir el monto restante en el nuevo campo de pago
                    const totalVenta = parseFloat(document.getElementById('monto_total_oculto').value) || 0;
                    let pagado = 0;
                    document.querySelectorAll('.pago-monto').forEach(i => { if(i !== div.querySelector('.pago-monto')) pagado += parseFloat(i.value) || 0; });
                    div.querySelector('.pago-monto').value = Math.max(0, totalVenta - pagado).toFixed(2);
                    
                    div.querySelector('.pago-monto').addEventListener('change', recalcular);
                    recalcular();
                };

    window.recalcular = function() {
    let totalVenta = 0;
    const dataProd = [];
    
    // 1. Calcular el total de la venta basado en productos
    document.querySelectorAll('#cuerpoTabla tr').forEach(f => {
        const id = f.querySelector('.p-select').value;
        const cant = parseFloat(f.querySelector('.c-input').value) || 0;
        const pre = parseFloat(f.querySelector('.pre-input').value) || 0;
        const sub = cant * pre;
        f.querySelector('.subtotal-cell').innerText = 'Bs ' + sub.toFixed(2);
        totalVenta += sub;
        if(id) dataProd.push({ id_producto: id, cantidad: cant, precio_venta: pre });
    });

    // Actualizar visualización del total
    document.getElementById('totalTxt').innerText = totalVenta.toFixed(2);
    document.getElementById('monto_total_oculto').value = totalVenta.toFixed(2);

    // 2. Ajustar el monto del ÚLTIMO pago registrado automáticamente
    const filasPagos = document.querySelectorAll('.pago-fila');
    if (filasPagos.length > 0) {
        let pagadoAnterior = 0;
        // Sumamos todos los pagos excepto el último
        for (let i = 0; i < filasPagos.length - 1; i++) {
            pagadoAnterior += parseFloat(filasPagos[i].querySelector('.pago-monto').value) || 0;
        }
        
        // El último input de pago absorbe la diferencia
        const ultimoInputPago = filasPagos[filasPagos.length - 1].querySelector('.pago-monto');
        const nuevoMontoUltimo = Math.max(0, totalVenta - pagadoAnterior);
        ultimoInputPago.value = nuevoMontoUltimo.toFixed(2);
    }

    // 3. Recopilar datos finales de pagos para el JSON
    let totalPagadoReal = 0;
    const dataPagos = [];
    document.querySelectorAll('.pago-fila').forEach(p => {
        const met = p.querySelector('.pago-metodo').value;
        const mon = parseFloat(p.querySelector('.pago-monto').value) || 0;
        const ref = p.querySelector('.pago-ref').value;
        totalPagadoReal += mon;
        if(met) dataPagos.push({ id_metodo: met, monto: mon, referencia: ref });
    });

    // Actualizar indicador de restante
    const restante = totalVenta - totalPagadoReal;
    document.getElementById('restanteTxt').innerText = 'Bs ' + restante.toFixed(2);
    
    // Guardar en campos ocultos
    document.getElementById('detalles_json').value = JSON.stringify(dataProd);
    document.getElementById('pagos_json').value = JSON.stringify(dataPagos);
                };

                // Funciones auxiliares originales
                window.actualizarOpcionesDisponibles = function() {
                    const IDsSeleccionados = Array.from(document.querySelectorAll('.p-select')).map(s => s.value).filter(val => val !== "");
                    document.querySelectorAll('.p-select').forEach(select => {
                        const ts = select.tomselect;
                        if(!ts) return;
                        const valorActual = select.value;
                        ts.clearOptions();
                        productosMaster.forEach(p => {
                            if (!IDsSeleccionados.includes(p.id_producto.toString()) || p.id_producto.toString() === valorActual) {
                                ts.addOption({ value: p.id_producto, text: p.nombre_producto + " (Stock: " + p.stock_actual + ") - PVP: Bs" + p.precio_con_ganancia, precio: p.precio_con_ganancia });
                            }
                        });
                        ts.refreshOptions(false);
                    });
                };

                window.manejarCambio = function(select) {
                    const ts = select.tomselect;
                    const item = ts.options[select.value];
                    const fila = select.closest('tr');
                    if(item) { fila.querySelector('.pre-input').value = item.precio; validarStock(fila.querySelector('.c-input')); }
                    recalcular();
                };

                window.validarStock = function(input) {
                    const fila = input.closest('tr');
                    const sel = fila.querySelector('.p-select');
                    const productData = productosMaster.find(p => p.id_producto == sel.value);
                    if (productData && parseFloat(input.value) > parseFloat(productData.stock_actual)) {
                        alert("Stock insuficiente. Disponible: " + productData.stock_actual);
                        input.value = productData.stock_actual;
                    }
                };

                window.eliminarFila = function(btn) {
                    const fila = btn.closest('tr');
                    if(fila.querySelector('.p-select').tomselect) fila.querySelector('.p-select').tomselect.destroy();
                    fila.remove();
                    actualizarOpcionesDisponibles();
                    recalcular();
                };

                window.validarEnvio = function() {
                    const prodJson = document.getElementById('detalles_json').value;
                    const pagoJson = document.getElementById('pagos_json').value;
                    const totalVenta = parseFloat(document.getElementById('monto_total_oculto').value);
                    
                    let pagadoTotal = 0;
                    JSON.parse(pagoJson).forEach(p => pagadoTotal += p.monto);

                    if (!prodJson || prodJson === "[]") { alert("Agregue al menos un producto."); return false; }
                    if (!pagoJson || pagoJson === "[]") { alert("Agregue al menos un método de pago."); return false; }
                    if (pagadoTotal.toFixed(2) !== totalVenta.toFixed(2)) {
                        alert("El total pagado (Bs " + pagadoTotal.toFixed(2) + ") no coincide con el total de la venta (Bs " + totalVenta.toFixed(2) + ")");
                        return false;
                    }
                    return true;
                };

                document.addEventListener('keydown', (e) => { if(e.key === 'F8') { e.preventDefault(); agregarFila(); } });
                document.addEventListener('DOMContentLoaded', () => { agregarFila(); agregarPago(); });
            </script>
        <?php endif; ?>
    </div>
</body>
</html>