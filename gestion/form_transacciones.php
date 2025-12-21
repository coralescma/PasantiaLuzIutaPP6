<?php
// gestion/form_transacciones.php - Punto de Venta Sincronizado
ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../includes/auth.php'; 
require_login(); // Permite acceso según sesión activa
include '../includes/db_connect.php'; 

$mensaje = "";
$clase_mensaje = "";

// 1. VERIFICAR JORNADA ACTIVA (Requisito para vender)
$id_jornada = obtenerJornadaActiva($conn);

// 2. PROCESAR VENTA (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_guardar_venta'])) {
    if (!$id_jornada) {
        $mensaje = "❌ No se puede vender: No hay una jornada abierta.";
        $clase_mensaje = "alerta-roja";
    } else {
        $id_usuario_cajero = $_SESSION['user_id'];
        $monto_total = floatval($_POST['monto_total_oculto']);
        $metodo_pago = $conn->real_escape_string($_POST['metodo_pago']);
        $referencia = $conn->real_escape_string($_POST['referencia_banco'] ?? '');

        // INSERTAR CABECERA DE TRANSACCIÓN
        $sql_trans = "INSERT INTO transacciones (id_jornada_fk, id_usuario_cajero_fk, fecha_transaccion, monto_total, metodo_pago, tipo_transaccion, referencia_bancaria) 
                      VALUES ($id_jornada, $id_usuario_cajero, NOW(), $monto_total, '$metodo_pago', 'Venta', '$referencia')";

        if ($conn->query($sql_trans)) {
            $id_venta = $conn->insert_id;
            
            // PROCESAR DETALLES (JSON enviado desde el JS)
            $detalles = json_decode($_POST['detalles_json'], true);
            foreach ($detalles as $item) {
                $id_p = intval($item['id_producto']);
                $cant = intval($item['cantidad']);
                $precio = floatval($item['precio_venta']);

                // Insertar detalle
                $conn->query("INSERT INTO detalle_transacciones (id_transaccion_fk, id_producto_fk, cantidad, precio_unitario) 
                              VALUES ($id_venta, $id_p, $cant, $precio)");
                
                // Descontar Inventario
                $conn->query("UPDATE inventario SET stock_actual = stock_actual - $cant WHERE id_producto = $id_p");
            }

            $mensaje = "✅ Venta #$id_venta registrada y stock actualizado.";
            $clase_mensaje = "alerta-verde";
        } else {
            $mensaje = "❌ Error en SQL: " . $conn->error;
            $clase_mensaje = "alerta-roja";
        }
    }
}

// 3. OBTENER PRODUCTOS PARA EL SELECT
$res_prod = $conn->query("SELECT id_producto, nombre_producto, costo_unitario, stock_actual FROM inventario WHERE stock_actual > 0");
$productos_json = [];
while($p = $res_prod->fetch_assoc()) { $productos_json[] = $p; }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SCL - Registro de Ventas</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .venta-container { max-width: 900px; margin: 20px auto; display: grid; grid-template-columns: 1fr 300px; gap: 20px; }
        .panel-izq, .panel-der { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; text-align: left; padding: 10px; }
        input, select { padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; }
        .total-box { font-size: 2em; font-weight: bold; color: #2563eb; text-align: right; margin-top: 10px; }
    </style>
</head>
<body>
    <?php include '../includes/menu.php'; ?>

    <div style="padding: 20px; max-width: 1200px; margin: 0 auto;">
        <h1>🛒 Punto de Venta (A.4)</h1>
        
        <?php if ($mensaje): ?>
            <div class="<?php echo $clase_mensaje; ?>" style="padding:15px; margin-bottom:20px; border-radius:5px;"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <?php if (!$id_jornada): ?>
            <div class="alerta-roja">⛔ BLOQUEADO: Debe <a href="form_jornada.php">abrir una jornada</a> para poder vender.</div>
        <?php else: ?>

        <form id="formVenta" method="POST" class="venta-container">
            <div class="panel-izq">
                <h3>Productos en Carrito</h3>
                <table id="tablaProductos">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th width="100">Cant.</th>
                            <th width="120">Precio</th>
                            <th width="100">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="cuerpoTabla">
                        </tbody>
                </table>
                <button type="button" onclick="agregarFila()" style="margin-top:15px; background:#64748b; color:white; border:none; padding:8px 15px; border-radius:4px; cursor:pointer;">+ Agregar Producto (F8)</button>
            </div>

            <div class="panel-der">
                <h3>Finalizar Pago</h3>
                <label>Método de Pago:</label>
                <select name="metodo_pago" required style="width:100%; margin-bottom:15px;">
                    <option value="Efectivo">💵 Efectivo</option>
                    <option value="Pago Movil">📱 Pago Móvil</option>
                    <option value="TPV">💳 Punto de Venta (Tarjeta)</option>
                </select>

                <label>Ref. Bancaria (Si aplica):</label>
                <input type="text" name="referencia_banco" style="width:100%; margin-bottom:15px;" placeholder="Últimos 4 dígitos">

                <div class="total-box">$ <span id="totalTxt">0.00</span></div>
                
                <input type="hidden" name="monto_total_oculto" id="monto_total_oculto">
                <input type="hidden" name="detalles_json" id="detalles_json">
                
                <button type="submit" name="btn_guardar_venta" class="btn-login" style="width:100%; margin-top:20px; background:#10b981;">✅ REGISTRAR VENTA</button>
                <p style="font-size:0.8em; color:gray; text-align:center; margin-top:10px;">Cajero: <?php echo $_SESSION['user_full_name']; ?></p>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <script>
        const productosDisponibles = <?php echo json_encode($productos_json); ?>;
        
        function agregarFila() {
            const tr = document.createElement('tr');
            let options = '<option value="">-- Seleccione --</option>';
            productosDisponibles.forEach(p => {
                options += `<option value="${p.id_producto}" data-precio="${p.costo_unitario}">${p.nombre_producto} (Stock: ${p.stock_actual})</option>`;
            });

            tr.innerHTML = `
                <td><select class="prod-select" required onchange="actualizarPrecio(this)" style="width:100%">${options}</select></td>
                <td><input type="number" class="cant-input" value="1" min="1" onchange="calcularTotales()" style="width:80px"></td>
                <td><input type="number" class="precio-input" step="0.01" onchange="calcularTotales()" style="width:100px"></td>
                <td class="subtotal-txt">$ 0.00</td>
            `;
            document.getElementById('cuerpoTabla').appendChild(tr);
        }

        function actualizarPrecio(select) {
            const precio = select.options[select.selectedIndex].getAttribute('data-precio');
            const fila = select.closest('tr');
            fila.querySelector('.precio-input').value = precio;
            calcularTotales();
        }

        function calcularTotales() {
            let totalGeneral = 0;
            const filas = document.querySelectorAll('#cuerpoTabla tr');
            const detalles = [];

            filas.forEach(fila => {
                const id = fila.querySelector('.prod-select').value;
                const cant = fila.querySelector('.cant-input').value;
                const precio = fila.querySelector('.precio-input').value;
                const subtotal = cant * precio;
                
                fila.querySelector('.subtotal-txt').innerText = '$ ' + subtotal.toFixed(2);
                totalGeneral += subtotal;

                if(id) detalles.push({ id_producto: id, cantidad: cant, precio_venta: precio });
            });

            document.getElementById('totalTxt').innerText = totalGeneral.toFixed(2);
            document.getElementById('monto_total_oculto').value = totalGeneral;
            document.getElementById('detalles_json').value = JSON.stringify(detalles);
        }

        document.getElementById('formVenta')?.addEventListener('submit', function(e) {
            if(document.querySelectorAll('#cuerpoTabla tr').length === 0) {
                alert("Agregue al menos un producto.");
                e.preventDefault();
            }
        });

        // Iniciar con una fila
        if(document.getElementById('cuerpoTabla')) agregarFila();
        
        // Atajo F8
        window.addEventListener('keydown', (e) => { if(e.key === 'F8') agregarFila(); });
    </script>
</body>
</html>