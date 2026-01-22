<?php
// gestion/form_transacciones.php - Versión Blindada (Demo)
include '../includes/auth.php'; 
require_login(); 
include '../includes/db_connect.php'; 

$mensaje = "";
$clase_mensaje = "";
$pagina_activa = 'gestion'; 

// --- SIMULACIÓN DE DATOS (Para evitar error de SQL si la tabla inventario falla) ---
// Creamos un array de productos manual para la demo
$productos_raw = [
    ['id_producto' => 1, 'nombre_producto' => 'Almuerzo Ejecutivo', 'costo_unitario' => 12.50],
    ['id_producto' => 2, 'nombre_producto' => 'Refresco 600ml', 'costo_unitario' => 2.00],
    ['id_producto' => 3, 'nombre_producto' => 'Postre del Día', 'costo_unitario' => 4.50],
    ['id_producto' => 4, 'nombre_producto' => 'Café Expreso', 'costo_unitario' => 1.50]
];

// Generamos el HTML de las opciones para el SELECT
$productos_options_html = '<option value="">-- Seleccione Producto --</option>'; 
foreach ($productos_raw as $prod) {
    $precio = number_format($prod['costo_unitario'], 2);
    $productos_options_html .= "<option value='{$prod['id_producto']}'>{$prod['nombre_producto']} ($$precio)</option>";
}

// Convertimos a JSON para el JavaScript
$productos_json = json_encode($productos_raw);

// --- PROCESAMIENTO SIMULADO (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $monto = $_POST['monto_total_oculto'] ?? '0.00';
    $ref = $_POST['referencia_banco'] ?? 'N/A';
    $mensaje = "✅ VENTA SIMULADA REGISTRADA EXITOSAMENTE. ID #".rand(1000,9999)." | Total: $$monto | Ref: $ref";
    $clase_mensaje = "alerta-verde";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SCL - Registro de Ventas</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .report-container { max-width: 900px; margin: 20px auto; padding: 25px; background: #fff; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .data-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .data-table th, .data-table td { border: 1px solid #e2e8f0; padding: 12px; text-align: left; }
        .qty-input { width: 70px; padding: 5px; text-align: center; }
        .price-input { width: 90px; padding: 5px; }
        .button-delete-inline { color: white; background: #ef4444; border: none; padding: 5px 10px; cursor: pointer; border-radius: 4px; }
        .total-section { font-size: 1.5rem; font-weight: bold; text-align: right; margin-top: 15px; color: #1e293b; }
    </style>
</head>
<body>
    <?php if(file_exists('../includes/menu.php')) include '../includes/menu.php'; ?>
    
    <div class="report-container">
        <header>
            <h1>✍️ PUNTO DE VENTA (Registro A.4)</h1>
            <p><strong>Cajero:</strong> <?php echo $_SESSION['user_full_name'] ?? 'Demo User'; ?> | <strong>Fecha:</strong> <?php echo date('d-m-Y'); ?></p>
        </header>

        <?php if ($mensaje): ?>
            <div class="recomendacion <?php echo $clase_mensaje; ?>" style="padding: 15px; margin: 15px 0; border-radius: 5px; font-weight: bold;"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <form id="transaccion_form" action="" method="post">
            <div style="display: flex; gap: 20px; background: #f8fafc; padding: 15px; border-radius: 8px;">
                <div style="flex: 1;">
                    <label>Medio de Cobro:</label><br>
                    <select name="tipo_cobro" id="tipo_cobro" required style="width: 100%; padding: 8px;">
                        <option value="TPV">Tarjeta TPV</option>
                        <option value="Pago_Movil">Pago Móvil</option>
                    </select>
                </div>
                <div style="flex: 1;">
                    <label>Referencia Bancaria:</label><br>
                    <input type="text" name="referencia_banco" id="referencia_banco" required style="width: 100%; padding: 8px;" placeholder="Lote o Referencia">
                </div>
            </div>

            <table class="data-table">
                <thead>
                    <tr style="background: #f1f5f9;">
                        <th>Producto</th>
                        <th>Cant.</th>
                        <th>Precio Unit.</th>
                        <th>Subtotal</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody id="detalle_body">
                    </tbody>
            </table>

            <button type="button" onclick="addDetalleRow()" class="button" style="background: #2563eb; color: white; padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer;">+ Añadir Producto (F8)</button>

            <div class="total-section">
                TOTAL VENTA: <span id="total_display">$0.00</span>
            </div>

            <input type="hidden" name="monto_total_oculto" id="monto_total_oculto">
            <input type="hidden" name="detalles_json" id="detalles_json">
            
            <button type="submit" style="width: 100%; padding: 15px; margin-top: 20px; background: #10b981; color: white; font-size: 1.2rem; border: none; border-radius: 8px; cursor: pointer; font-weight: bold;">
                REGISTRAR VENTA FINAL
            </button>
        </form>
    </div>

<script>
    // Datos inyectados para que el JS no dependa de la BD hoy
    const PRODUCTOS = <?php echo $productos_json; ?>;
    const OPTIONS_HTML = `<?php echo $productos_options_html; ?>`;
    
    function addDetalleRow() {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <select class="prod-select" onchange="updateRow(this)" style="width:100%" required>${OPTIONS_HTML}</select>
            </td>
            <td><input type="number" class="qty-input" value="1" min="1" oninput="calculate(this)"></td>
            <td><input type="number" class="price-input" step="0.01" oninput="calculate(this)"></td>
            <td class="subtotal-cell">$0.00</td>
            <td><button type="button" class="button-delete-inline" onclick="this.closest('tr').remove(); updateTotal();">X</button></td>
        `;
        document.getElementById('detalle_body').appendChild(tr);
    }

    function updateRow(select) {
        const id = select.value;
        const prod = PRODUCTOS.find(p => p.id_producto == id);
        const tr = select.closest('tr');
        if(prod) {
            tr.querySelector('.price-input').value = prod.costo_unitario.toFixed(2);
            calculate(select);
        }
    }

    function calculate(el) {
        const tr = el.closest('tr');
        const qty = parseFloat(tr.querySelector('.qty-input').value) || 0;
        const price = parseFloat(tr.querySelector('.price-input').value) || 0;
        const subtotal = qty * price;
        tr.querySelector('.subtotal-cell').textContent = '$' + subtotal.toFixed(2);
        updateTotal();
    }

    function updateTotal() {
        let total = 0;
        document.querySelectorAll('.subtotal-cell').forEach(cell => {
            total += parseFloat(cell.textContent.replace('$', '')) || 0;
        });
        document.getElementById('total_display').textContent = '$' + total.toFixed(2);
        document.getElementById('monto_total_oculto').value = total.toFixed(2);
    }

    // Tecla rápida F8
    document.addEventListener('keydown', e => { if(e.key === 'F8') addDetalleRow(); });

    // Iniciar con una fila
    addDetalleRow();
</script>
</body>
</html>