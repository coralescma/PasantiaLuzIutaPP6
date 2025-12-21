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

        // A. INSERTAR CABECERA EN 'transacciones'
        $sql_trans = "INSERT INTO transacciones (id_usuario_cajero_fk, id_jornada_fk, fecha_transaccion, es_egreso) 
                      VALUES ($id_usuario, $id_jornada, NOW(), 0)";

        if ($conn->query($sql_trans)) {
            $id_venta = $conn->insert_id;

            // B. INSERTAR PAGO EN 'detalle_pago'
            $sql_pago = "INSERT INTO detalle_pago (id_transaccion_fk, id_metodo_fk, monto_pago) 
                         VALUES ($id_venta, $id_metodo_pago, $monto_total)";
            $conn->query($sql_pago);

            // C. INSERTAR PRODUCTOS EN 'detalle_transaccion'
            $detalles = json_decode($_POST['detalles_json'], true);
            if ($detalles) {
                foreach ($detalles as $item) {
                    $id_p = intval($item['id_producto']);
                    $cant = intval($item['cantidad']);
                    $precio = floatval($item['precio_venta']);

                    $sql_det = "INSERT INTO detalle_transaccion (id_transaccion_fk, id_producto_fk, cantidad, precio_venta) 
                                VALUES ($id_venta, $id_p, $cant, $precio)";
                    $conn->query($sql_det);
                    
                    // D. ACTUALIZAR STOCK EN 'inventario'
                    $conn->query("UPDATE inventario SET stock_actual = stock_actual - $cant WHERE id_producto = $id_p");
                }
            }

            $mensaje = "✅ Venta #$id_venta registrada exitosamente.";
            $clase_mensaje = "alerta-verde";
        } else {
            $mensaje = "❌ Error en Transacción: " . $conn->error;
            $clase_mensaje = "alerta-roja";
        }
    }
}

// 3. CARGAR PRODUCTOS (Se incluye stock_actual en la consulta)
$res_prod = $conn->query("SELECT id_producto, nombre_producto, costo_unitario, stock_actual FROM inventario WHERE stock_actual > 0");
$productos_json = [];
while($p = $res_prod->fetch_assoc()) { $productos_json[] = $p; }

// 4. CARGAR MÉTODOS DE PAGO ACTIVOS
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
        .btn-add { background: #475569; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; transition: 0.3s; }
        .btn-add:hover { background: #1e293b; }
        .alerta-verde { background: #dcfce7; color: #166534; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #bbf7d0; }
        .alerta-roja { background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #fecaca; }
    </style>
</head>
<body>
    <?php include '../includes/menu.php'; ?>

    <div style="padding: 20px; max-width: 1200px; margin: 0 auto;">
        <h1>🛒 Punto de Venta</h1>
        
        <?php if ($mensaje): ?>
            <div class="<?php echo $clase_mensaje; ?>"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <?php if (!$id_jornada): ?>
            <div class="alerta-roja">⛔ No hay jornada abierta. <a href="form_jornada.php">Inicie jornada</a>.</div>
        <?php else: ?>

        <form id="formVenta" method="POST" class="venta-container">
            <div class="panel">
                <h3>Detalle de Productos</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th width="100">Cantidad</th>
                            <th width="130">Precio Unit.</th>
                            <th width="120">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="cuerpoTabla"></tbody>
                </table>
                <div style="margin-top: 20px;">
                    <button type="button" class="btn-add" onclick="agregarFila()">+ Añadir Producto (F8)</button>
                </div>
            </div>

            <div class="panel">
                <h3>Finalizar Venta</h3>
                <div style="margin-bottom: 15px;">
                    <label>Método de Pago:</label>
                    <select name="id_metodo_pago" required>
                        <option value="">-- Seleccione --</option>
                        <?php while($m = $res_metodos->fetch_assoc()): ?>
                            <option value="<?php echo $m['id_metodo']; ?>"><?php echo $m['nombre_metodo']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div style="margin-bottom: 15px;">
                    <label>Referencia / Lote:</label>
                    <input type="text" name="referencia_banco" placeholder="Opcional">
                </div>

                <div class="total-box">$ <span id="totalTxt">0.00</span></div>
                
                <input type="hidden" name="monto_total_oculto" id="monto_total_oculto" value="0">
                <input type="hidden" name="detalles_json" id="detalles_json">
                
                <button type="submit" name="btn_guardar_venta" class="btn-login" style="width:100%; background:#059669; color:white; padding:15px; font-size:1.1em; border-radius:8px; border:none; cursor:pointer;">
                    CONFIRMAR VENTA
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <script>
        const productosDisponibles = <?php echo json_encode($productos_json); ?>;
        
        function agregarFila() {
            const tbody = document.getElementById('cuerpoTabla');
            const tr = document.createElement('tr');
            let options = '<option value="">-- Seleccionar --</option>';
            
            // Ajuste aquí para mostrar el stock restante en el select
            productosDisponibles.forEach(p => {
                options += `<option value="${p.id_producto}" data-precio="${p.costo_unitario}">
                    ${p.nombre_producto} (Stock: ${p.stock_actual})
                </option>`;
            });

            tr.innerHTML = `
                <td><select class="p-select" required onchange="actualizarPrecio(this)">${options}</select></td>
                <td><input type="number" class="c-input" value="1" min="1" onchange="recalcular()"></td>
                <td><input type="number" class="pre-input" step="0.01" onchange="recalcular()"></td>
                <td style="font-weight:bold" class="subtotal-cell">$ 0.00</td>
            `;
            tbody.appendChild(tr);
        }

        function actualizarPrecio(select) {
            const precio = select.options[select.selectedIndex].getAttribute('data-precio');
            select.closest('tr').querySelector('.pre-input').value = precio;
            recalcular();
        }

        function recalcular() {
            let totalGeneral = 0;
            const dataVenta = [];
            document.querySelectorAll('#cuerpoTabla tr').forEach(f => {
                const id = f.querySelector('.p-select').value;
                const cant = parseFloat(f.querySelector('.c-input').value) || 0;
                const precio = parseFloat(f.querySelector('.pre-input').value) || 0;
                const subtotal = cant * precio;
                f.querySelector('.subtotal-cell').innerText = '$ ' + subtotal.toFixed(2);
                totalGeneral += subtotal;
                if(id) dataVenta.push({ id_producto: id, cantidad: cant, precio_venta: precio });
            });
            document.getElementById('totalTxt').innerText = totalGeneral.toFixed(2);
            document.getElementById('monto_total_oculto').value = totalGeneral;
            document.getElementById('detalles_json').value = JSON.stringify(dataVenta);
        }

        window.addEventListener('keydown', (e) => { if(e.key === 'F8') { e.preventDefault(); agregarFila(); } });
        agregarFila();
    </script>
</body>
</html>