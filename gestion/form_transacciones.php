<?php
// gestion/form_transacciones.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../includes/auth.php'; 
require_login(); 
include '../includes/db_connect.php'; 

$mensaje = "";
$clase_mensaje = "";

if (isset($_GET['status']) && $_GET['status'] == 'success') {
    $id_v = $_GET['id'] ?? '';
    $mensaje = "✅ Venta #$id_v registrada exitosamente.";
    $clase_mensaje = "alerta-verde";
}

$id_jornada = obtenerJornadaActiva($conn);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_guardar_venta'])) {
    if (!$id_jornada) {
        $mensaje = "❌ Error: No hay jornada abierta.";
        $clase_mensaje = "alerta-roja";
    } else {
        $id_usuario = $_SESSION['user_id'];
        $monto_total = floatval($_POST['monto_total_oculto']);
        $id_metodo_pago = intval($_POST['id_metodo_pago']); 
        $referencia = $conn->real_escape_string($_POST['referencia_banco'] ?? '');

        $sql_trans = "INSERT INTO transacciones (id_usuario_cajero_fk, id_jornada_fk, fecha_transaccion, monto_total, es_egreso) 
                      VALUES ($id_usuario, $id_jornada, NOW(), $monto_total, 0)";

        if ($conn->query($sql_trans)) {
            $id_venta = $conn->insert_id;
            $conn->query("INSERT INTO detalle_pago (id_transaccion_fk, id_metodo_fk, monto_pago) VALUES ($id_venta, $id_metodo_pago, $monto_total)");

            $detalles = json_decode($_POST['detalles_json'], true);
            foreach ($detalles as $item) {
                $id_p = intval($item['id_producto']);
                $cant = intval($item['cantidad']);
                $precio = floatval($item['precio_venta']);
                $conn->query("INSERT INTO detalle_transaccion (id_transaccion_fk, id_producto_fk, cantidad, precio_venta) VALUES ($id_venta, $id_p, $cant, $precio)");
                $conn->query("UPDATE inventario SET stock_actual = stock_actual - $cant WHERE id_producto = $id_p");
            }
            header("Location: form_transacciones.php?status=success&id=$id_venta");
            exit(); 
        }
    }
}

// Consultas para los desplegables
$res_prod = $conn->query("SELECT id_producto, nombre_producto, costo_unitario, stock_actual FROM inventario WHERE stock_actual > 0");
$productos_json = [];
while($p = $res_prod->fetch_assoc()) { $productos_json[] = $p; }

// IMPORTANTE: Solo métodos ACTIVOS
$res_metodos = $conn->query("SELECT id_metodo, nombre_metodo FROM metodos_pago WHERE activo = 1");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Ventas</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .venta-container { max-width: 1100px; margin: 20px auto; display: grid; grid-template-columns: 1fr 350px; gap: 20px; }
        .panel { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .total-box { font-size: 2.5em; font-weight: bold; color: #059669; text-align: right; margin: 15px 0; border-top: 1px solid #eee; padding-top: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; background: #f1f5f9; }
        td { padding: 10px; border-bottom: 1px solid #f1f5f9; }
        .oculto { display: none; }
    </style>
</head>
<body>
    <?php include '../includes/menu.php'; ?>
    <div style="padding: 20px;">
        <?php if ($mensaje): ?><div class="<?php echo $clase_mensaje; ?>"><?php echo $mensaje; ?></div><?php endif; ?>
        
        <form id="formVenta" method="POST" class="venta-container">
            <div class="panel">
                <h3>Detalle de Productos</h3>
                <table>
                    <thead>
                        <tr><th>Producto [Stock]</th><th width="100">Cant.</th><th width="120">Precio</th><th width="120">Subtotal</th><th></th></tr>
                    </thead>
                    <tbody id="cuerpoTabla"></tbody>
                </table>
                <button type="button" class="btn-add" onclick="agregarFila()" style="margin-top:15px;">+ Añadir (F8)</button>
            </div>

            <div class="panel">
                <h3>Finalizar</h3>
                <label>Método de Pago:</label>
                <select name="id_metodo_pago" required style="margin-bottom:15px; width:100%;">
                    <option value="">-- Seleccione --</option>
                    <?php while($m = $res_metodos->fetch_assoc()): ?>
                        <option value="<?php echo $m['id_metodo']; ?>"><?php echo $m['nombre_metodo']; ?></option>
                    <?php endwhile; ?>
                </select>
                <div class="total-box">$ <span id="totalTxt">0.00</span></div>
                <input type="hidden" name="monto_total_oculto" id="monto_total_oculto">
                <input type="hidden" name="detalles_json" id="detalles_json">
                <button type="submit" name="btn_guardar_venta" class="btn-login" style="width:100%; background:#059669;">CONFIRMAR</button>
            </div>
        </form>
    </div>

    <script>
        const productos = <?php echo json_encode($productos_json); ?>;
        function agregarFila() {
            const tbody = document.getElementById('cuerpoTabla');
            const tr = document.createElement('tr');
            let opts = '<option value="">-- Seleccionar --</option>';
            productos.forEach(p => {
                opts += `<option value="${p.id_producto}" data-precio="${p.costo_unitario}" data-stock="${p.stock_actual}">
                    ${p.nombre_producto} [Stock: ${p.stock_actual}]
                </option>`;
            });
            tr.innerHTML = `
                <td><select class="p-select" required onchange="actualizarFila(this)">${opts}</select></td>
                <td><input type="number" class="c-input" value="1" min="1" onchange="validarStock(this)"></td>
                <td><input type="number" class="pre-input" step="0.01" onchange="recalcular()"></td>
                <td class="subtotal-cell">$ 0.00</td>
                <td><button type="button" onclick="this.closest('tr').remove(); recalcular();">✕</button></td>`;
            tbody.appendChild(tr);
        }

        function actualizarFila(sel) {
            const opt = sel.options[sel.selectedIndex];
            const row = sel.closest('tr');
            row.querySelector('.pre-input').value = opt.dataset.precio;
            validarStock(row.querySelector('.c-input'));
        }

        function validarStock(inp) {
            const row = inp.closest('tr');
            const stock = parseInt(row.querySelector('.p-select').options[row.querySelector('.p-select').selectedIndex].dataset.stock);
            if(inp.value > stock) { alert("Stock insuficiente"); inp.value = stock; }
            recalcular();
        }

        function recalcular() {
            let total = 0; let items = [];
            document.querySelectorAll('#cuerpoTabla tr').forEach(r => {
                const id = r.querySelector('.p-select').value;
                const c = parseFloat(r.querySelector('.c-input').value) || 0;
                const p = parseFloat(r.querySelector('.pre-input').value) || 0;
                const sub = c * p;
                r.querySelector('.subtotal-cell').innerText = '$ ' + sub.toFixed(2);
                total += sub;
                if(id) items.push({id_producto: id, cantidad: c, precio_venta: p});
            });
            document.getElementById('totalTxt').innerText = total.toFixed(2);
            document.getElementById('monto_total_oculto').value = total;
            document.getElementById('detalles_json').value = JSON.stringify(items);
        }
        window.addEventListener('keydown', e => { if(e.key==='F8') agregarFila(); });
        agregarFila();
    </script>
</body>
</html>