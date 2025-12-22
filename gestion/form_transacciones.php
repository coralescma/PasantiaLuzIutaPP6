<?php
// gestion/form_transacciones.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../includes/auth.php'; 
require_login(); 
include '../includes/db_connect.php'; 

$mensaje = "";
$clase_mensaje = "";

// 1. OBTENER JORNADA ACTIVA
$id_jornada = obtenerJornadaActiva($conn);

// 2. PROCESAR EL REGISTRO DE LA VENTA
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_guardar_venta'])) {
    if (!$id_jornada) {
        $mensaje = "❌ Error: No se puede vender sin una jornada abierta.";
        $clase_mensaje = "alerta-roja";
    } else {
        $id_usuario = $_SESSION['user_id'];
        $monto_total = floatval($_POST['monto_total_oculto']);
        $id_metodo_pago = intval($_POST['id_metodo_pago']); 
        $referencia = $conn->real_escape_string($_POST['referencia_banco'] ?? ''); 

        $sql_trans = "INSERT INTO transacciones (id_usuario_cajero_fk, id_jornada_fk, fecha_transaccion, es_egreso) 
                      VALUES ($id_usuario, $id_jornada, NOW(), 0)";

        if ($conn->query($sql_trans)) {
            $id_venta = $conn->insert_id;
            $sql_pago = "INSERT INTO detalle_pago (id_transaccion_fk, id_metodo_fk, monto_pago, referencia_banco) 
                         VALUES ($id_venta, $id_metodo_pago, $monto_total, '$referencia')";
            $conn->query($sql_pago);

            $detalles = json_decode($_POST['detalles_json'], true);
            if ($detalles) {
                foreach ($detalles as $item) {
                    $id_p = intval($item['id_producto']);
                    $cant = intval($item['cantidad']);
                    $precio = floatval($item['precio_venta']);
                    $sql_det = "INSERT INTO detalle_transaccion (id_transaccion_fk, id_producto_fk, cantidad, precio_venta) 
                                VALUES ($id_venta, $id_p, $cant, $precio)";
                    $conn->query($sql_det);
                    $conn->query("UPDATE inventario SET stock_actual = stock_actual - $cant WHERE id_producto = $id_p");
                }
            }
            $mensaje = "✅ Venta #$id_venta registrada exitosamente.";
            $clase_mensaje = "alerta-verde";
        }
    }
}

// 3. CARGAR PRODUCTOS
$res_prod = $conn->query("SELECT id_producto, nombre_producto, costo_unitario, stock_actual FROM inventario WHERE stock_actual > 0");
$productos_json = [];
while($p = $res_prod->fetch_assoc()) { $productos_json[] = $p; }

// 4. CARGAR MÉTODOS
$res_metodos = $conn->query("SELECT id_metodo, nombre_metodo FROM metodos_pago WHERE activo = 1");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SCL - Registro de Ventas</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .venta-container { max-width: 1100px; margin: 20px auto; display: grid; grid-template-columns: 1fr 350px; gap: 20px; }
        .panel { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .total-box { font-size: 2.5em; font-weight: bold; color: #059669; text-align: right; margin: 15px 0; border-top: 1px solid #eee; padding-top: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; background: #f1f5f9; color: #475569; }
        td { padding: 10px; border-bottom: 1px solid #f1f5f9; }
        input, select { padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; width: 100%; }
        .btn-add { background: #475569; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; }
        .btn-remove { background: #ef4444; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>
    <?php include '../includes/menu.php'; ?>

    <div style="padding: 20px; max-width: 1200px; margin: 0 auto;">
        <h1>🛒 Punto de Venta</h1>
        <?php if ($mensaje): ?><div class="<?php echo $clase_mensaje; ?>"><?php echo $mensaje; ?></div><?php endif; ?>

        <?php if ($id_jornada): ?>
        <form id="formVenta" method="POST" class="venta-container">
            <div class="panel">
                <h3>Detalle de Productos</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Producto (Stock)</th>
                            <th width="80">Cant.</th>
                            <th width="120">Precio</th>
                            <th width="120">Subtotal</th>
                            <th width="40"></th>
                        </tr>
                    </thead>
                    <tbody id="cuerpoTabla"></tbody>
                </table>
                <button type="button" class="btn-add" style="margin-top:15px;" onclick="agregarFila()">+ Añadir (F8)</button>
            </div>

            <div class="panel">
                <h3>Finalizar Venta</h3>
                <label>Método de Pago:</label>
                <select name="id_metodo_pago" required style="margin-bottom:15px;">
                    <option value="">-- Seleccione --</option>
                    <?php while($m = $res_metodos->fetch_assoc()): ?>
                        <option value="<?php echo $m['id_metodo']; ?>"><?php echo $m['nombre_metodo']; ?></option>
                    <?php endwhile; ?>
                </select>

                <label>Referencia / Lote:</label>
                <input type="text" name="referencia_banco" placeholder="Opcional" style="margin-bottom:15px;">

                <div class="total-box">$ <span id="totalTxt">0.00</span></div>
                <input type="hidden" name="monto_total_oculto" id="monto_total_oculto">
                <input type="hidden" name="detalles_json" id="detalles_json">
                <button type="submit" name="btn_guardar_venta" class="btn-login" style="width:100%; background:#059669; color:white; padding:15px; border:none; border-radius:8px; cursor:pointer;">CONFIRMAR VENTA</button>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <script>
        const productosMaster = <?php echo json_encode($productos_json); ?>;

        function obtenerIdsSeleccionados() {
            return Array.from(document.querySelectorAll('.p-select'))
                        .map(sel => sel.value)
                        .filter(id => id !== "");
        }

        function actualizarTodosLosSelects() {
            const seleccionados = obtenerIdsSeleccionados();
            
            document.querySelectorAll('.p-select').forEach(select => {
                const valorActual = select.value;
                
                // Limpiar y reconstruir opciones
                select.innerHTML = '<option value="">-- Seleccionar --</option>';
                
                productosMaster.forEach(p => {
                    // Mostrar si no está seleccionado en otro select, O si es el valor actual de este select
                    if (!seleccionados.includes(p.id_producto.toString()) || p.id_producto.toString() === valorActual) {
                        const opt = document.createElement('option');
                        opt.value = p.id_producto;
                        opt.text = `${p.nombre_producto} (Stock: ${p.stock_actual})`;
                        opt.dataset.precio = p.costo_unitario;
                        opt.dataset.stock = p.stock_actual;
                        if(p.id_producto.toString() === valorActual) opt.selected = true;
                        select.appendChild(opt);
                    }
                });
            });
        }

        function agregarFila() {
            const tbody = document.getElementById('cuerpoTabla');
            const tr = document.createElement('tr');
            
            tr.innerHTML = `
                <td><select class="p-select" required onchange="manejarCambioProducto(this)"></select></td>
                <td><input type="number" class="c-input" value="1" min="1" onchange="validarStock(this)"></td>
                <td><input type="number" class="pre-input" step="0.01" onchange="recalcular()"></td>
                <td style="font-weight:bold" class="subtotal-cell">$ 0.00</td>
                <td><button type="button" class="btn-remove" onclick="eliminarFila(this)">×</button></td>
            `;
            tbody.appendChild(tr);
            actualizarTodosLosSelects();
        }

        function manejarCambioProducto(select) {
            const opt = select.options[select.selectedIndex];
            const fila = select.closest('tr');
            if(opt.value) {
                fila.querySelector('.pre-input').value = opt.dataset.precio;
                validarStock(fila.querySelector('.c-input'));
            }
            actualizarTodosLosSelects();
            recalcular();
        }

        function eliminarFila(btn) {
            btn.closest('tr').remove();
            actualizarTodosLosSelects();
            recalcular();
        }

        function validarStock(input) {
            const fila = input.closest('tr');
            const sel = fila.querySelector('.p-select');
            const stock = parseFloat(sel.options[sel.selectedIndex]?.dataset.stock || 0);
            if (parseFloat(input.value) > stock) {
                alert("Stock insuficiente. Máximo: " + stock);
                input.value = stock;
            }
            recalcular();
        }

        function recalcular() {
            let total = 0;
            const data = [];
            document.querySelectorAll('#cuerpoTabla tr').forEach(f => {
                const id = f.querySelector('.p-select').value;
                const cant = parseFloat(f.querySelector('.c-input').value) || 0;
                const pre = parseFloat(f.querySelector('.pre-input').value) || 0;
                const sub = cant * pre;
                f.querySelector('.subtotal-cell').innerText = '$ ' + sub.toFixed(2);
                total += sub;
                if(id) data.push({ id_producto: id, cantidad: cant, precio_venta: pre });
            });
            document.getElementById('totalTxt').innerText = total.toFixed(2);
            document.getElementById('monto_total_oculto').value = total;
            document.getElementById('detalles_json').value = JSON.stringify(data);
        }

        window.addEventListener('keydown', (e) => { if(e.key === 'F8') { e.preventDefault(); agregarFila(); } });
        agregarFila();
    </script>
</body>
</html>