<?php
// gestion/form_transacciones.php - Versión 4.0 (Solo Ventas)

// ===============================================
// Bloque PHP 1: SEGURIDAD, CONEXIÓN y LÓGICA
// ===============================================

include '../includes/auth.php'; 
require_login(['Administrador', 'Supervisor', 'Cajero']); 
include '../includes/db_connect.php'; 

$mensaje = "";
$clase_mensaje = "";
$pagina_activa = 'gestion'; 

// --- 3. Obtener listado de Productos ---
$sql_productos = "SELECT id_producto, nombre_producto, costo_unitario FROM inventario ORDER BY nombre_producto";
$resultado_productos = $conn->query($sql_productos);
$productos_raw = [];
$productos_options_html = '<option value="">-- Seleccione Producto --</option>'; 

while ($row = $resultado_productos->fetch_assoc()) {
    // Usamos el costo_unitario como precio_venta por defecto
    $row['precio_venta_defecto'] = (float)$row['costo_unitario'];
    $productos_raw[] = $row;
    
    // Generamos las opciones del select en PHP
    $productos_options_html .= '<option value="' . $row['id_producto'] . '">' . 
                                $row['nombre_producto'] . ' ($' . number_format($row['precio_venta_defecto'], 2) . ')' . 
                                '</option>';
}
$productos_json = json_encode($productos_raw);


// --- 4. Lógica de Procesamiento de Transacción (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- A. Recolección de Datos Principales ---
    $tipo_cobro = $conn->real_escape_string($_POST['tipo_cobro']);
    // Requerimiento 2: Usar la fecha del sistema (no modificable)
    $fecha_venta = date('Y-m-d'); 
    $es_egreso_global = 0; // Siempre 0, ya no gestionamos egresos aquí.
    $monto_venta = (float)$_POST['monto_total_oculto']; 
    $referencia_banco = $conn->real_escape_string($_POST['referencia_banco'] ?? null); 
    $usuario_registro = $_SESSION['username'] ?? 'Sistema'; 
    
    // --- B. Validación y Reconstrucción de Detalles desde el JSON del Frontend ---
    $detalles_json = json_decode($_POST['detalles_json'] ?? '[]', true);

    if (empty($detalles_json)) {
        $mensaje = "Error: Debe ingresar al menos un producto válido.";
        $clase_mensaje = "alerta-roja";
    } elseif ($tipo_cobro != 'N/A' && ($tipo_cobro == 'TPV' || $tipo_cobro == 'Pago_Movil') && empty($referencia_banco)) {
        $mensaje = "Error: Debe registrar el número de referencia bancaria.";
        $clase_mensaje = "alerta-roja";
    } else {
        
        // --- C. Inserción de Transacción Principal ---
        $sql_transaccion = "INSERT INTO transacciones (fecha_venta, monto_venta, tipo_cobro, es_egreso, usuario_registro, referencia_banco) 
                            VALUES ('$fecha_venta', $monto_venta, '$tipo_cobro', $es_egreso_global, '$usuario_registro', " . 
                            ($referencia_banco ? "'$referencia_banco'" : "NULL") . ")";
        
        if ($conn->query($sql_transaccion) === TRUE) {
            $id_transaccion = $conn->insert_id;
            $exito_detalles = true;
            
            // --- D. Inserción de Detalles y Actualización de Inventario ---
            foreach ($detalles_json as $detalle) {
                $id_producto = (int)$detalle['id_producto'];
                $cantidad = (int)$detalle['cantidad'];
                $precio_venta_registrado = (float)$detalle['precio_venta']; 
                // Los campos de egreso especial y motivo ya no existen en este formulario.
                
                // 1. Inserción del Detalle
                // Quitamos 'motivo' y 'es_egreso_especial' del INSERT
                $sql_detalle = "INSERT INTO detalle_transaccion (id_transaccion_fk, id_producto_fk, cantidad, precio_venta)
                                VALUES ($id_transaccion, {$id_producto}, {$cantidad}, {$precio_venta_registrado})";
                                
                if ($conn->query($sql_detalle) === FALSE) {
                    $exito_detalles = false;
                    break;
                }
                
                // 2. Actualización de Inventario (stock disminuye por venta)
                $sql_update_stock = "UPDATE inventario SET stock_actual = stock_actual - $cantidad WHERE id_producto = $id_producto";
                $conn->query($sql_update_stock);
                
                // 3. Actualizar la última venta
                $sql_update_fecha = "UPDATE inventario SET ultima_venta_fecha = '$fecha_venta' WHERE id_producto = $id_producto";
                $conn->query($sql_update_fecha);
            }
            
            if ($exito_detalles) {
                $mensaje = "✅ VENTA (A.4) con ID #{$id_transaccion} registrada por **{$usuario_registro}**. Total: $". number_format($monto_venta, 2);
                $clase_mensaje = "alerta-verde";
                
            } else {
                $mensaje = "Error al guardar el detalle. Por favor, revise manualmente la DB.";
                $clase_mensaje = "alerta-roja";
            }
            
        } else {
            $mensaje = "Error al crear la Transacción Principal: " . $conn->error;
            $clase_mensaje = "alerta-roja";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro A.4 - Transacciones de Venta</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* Requerimiento 3: Aumentar visibilidad del input de cantidad */
        .qty-input {
            width: 80px !important; 
            text-align: center;
        }
    </style>
</head>
<body>
    <?php include '../includes/menu.php'; ?>
    <div class="report-container">
        <header class="report-header">
            <h1>✍️ REGISTRO A.4: Transacciones de Venta (Solo Ingreso)</h1>
            <p><strong>Cajero:</strong> <?php echo $_SESSION['user_full_name'] ?? 'N/A'; ?> | Fecha: <?php echo date('Y-m-d'); ?></p>
        </header>

        <?php if (!empty($mensaje)): ?>
            <div class="recomendacion <?php echo $clase_mensaje; ?>"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <section class="form-section">
            <form id="transaccion_form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="data-form">
                
                <h2>Configuración de Venta</h2>
                <div class="form-group-inline">
                    <label for="fecha_venta">Fecha de Transacción (Sistema):</label>
                    <input type="date" id="fecha_venta" name="fecha_venta" value="<?php echo date('Y-m-d'); ?>" readonly> 
                    
                    <label for="tipo_cobro">Medio de Cobro:</label>
                    <select id="tipo_cobro" name="tipo_cobro" required>
                        <option value="TPV">Tarjeta TPV</option>
                        <option value="Pago_Movil">Pago Móvil</option>
                    </select>
                </div>

                <div id="referencia_section">
                    <label for="referencia_banco">Número de Referencia/Lote Bancario:</label>
                    <input type="text" id="referencia_banco" name="referencia_banco" placeholder="Escriba la referencia aquí" required>
                </div>
                
                <hr>

                <h2>Detalle de Productos</h2>
                
                <table id="detalle_table" class="data-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th style="width: 100px;">Cant.</th>
                            <th style="width: 120px;">Precio Unitario ($)</th>
                            <th style="width: 100px;">Subtotal ($)</th>
                            <th style="width: 50px;"></th>
                        </tr>
                    </thead>
                    <tbody id="detalle_body">
                        </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" id="colspan_total" style="text-align: right; font-weight: bold;">TOTAL VENTA:</td>
                            <td><span id="total_display">$0.00</span></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>

                <input type="hidden" name="monto_total_oculto" id="monto_total_oculto" value="0.00">
                <input type="hidden" name="detalles_json" id="detalles_json">
                
                <button type="button" onclick="addDetalleRow()" class="button button-a2" style="margin-top: 10px;">+ Añadir Producto (F8)</button>

                <div style="margin-top: 30px;">
                    <button type="submit" class="button button-a1">Registrar Venta (Enter)</button>
                </div>
            </form>
        </section>

    </div>

<script>
    // ===============================================
    // Bloque JavaScript para la Usabilidad (Versión 4.0)
    // ===============================================
    
    const PRODUCTOS = <?php echo $productos_json; ?>.reduce((obj, item) => {
        obj[item.id_producto] = item;
        return obj;
    }, {});
    
    const PRODUCTOS_OPTIONS_HTML = `<?php echo addslashes($productos_options_html); ?>`;
    
    const detalleBody = document.getElementById('detalle_body');
    const totalDisplay = document.getElementById('total_display');
    const montoTotalOculto = document.getElementById('monto_total_oculto');
    let rowId = 0;
    
    
    /**
     * Calcula el subtotal de una fila y actualiza el total general.
     */
    function calculateSubtotal(row) {
        const qtyInput = row.querySelector('.qty-input');
        const priceInput = row.querySelector('.price-input');
        const subtotalCell = row.querySelector('.subtotal-cell');

        const qty = parseFloat(qtyInput.value) || 0;
        const price_unitario = parseFloat(priceInput.value) || 0;
        
        const subtotal = qty * price_unitario;
        
        subtotalCell.textContent = '$' + subtotal.toFixed(2);
        calculateTotal();
    }
    
    /**
     * Calcula el total de la transacción.
     */
    function calculateTotal() {
        let total = 0;
        const rows = detalleBody.querySelectorAll('tr');
        
        rows.forEach(row => {
            const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
            const price_unitario = parseFloat(row.querySelector('.price-input').value) || 0;
            
            total += qty * price_unitario;
        });

        totalDisplay.textContent = '$' + total.toFixed(2);
        montoTotalOculto.value = total.toFixed(2);
    }
    
    /**
     * Cuando se selecciona un producto, llena el campo de precio.
     */
    function setPrice(selectElement) {
        const id_producto = selectElement.value;
        const row = selectElement.closest('tr');
        const priceInput = row.querySelector('.price-input');
        
        if (id_producto && PRODUCTOS[id_producto]) {
            // Usar costo_unitario como precio_venta por defecto
            priceInput.value = PRODUCTOS[id_producto].precio_venta_defecto.toFixed(2);
        } else {
            priceInput.value = ''; 
        }
        
        // Enfoca la cantidad después de seleccionar el producto
        calculateSubtotal(row);
        row.querySelector('.qty-input').focus(); 
    }

    /**
     * Añade una nueva fila a la tabla de detalles.
     */
    function addDetalleRow() {
        
        const newRow = document.createElement('tr');
        newRow.setAttribute('data-row-id', rowId++);
        newRow.innerHTML = `
            <td>
                <select class="product-select" onchange="setPrice(this)" required>
                    ${PRODUCTOS_OPTIONS_HTML}
                </select>
            </td>
            <td>
                <input type="number" class="qty-input" value="1" min="1" oninput="calculateSubtotal(this.closest('tr'))" required>
            </td>
            <td>
                <input type="number" class="price-input" step="0.01" placeholder="0.00" oninput="calculateSubtotal(this.closest('tr'))" required>
            </td>
            <td class="subtotal-cell">$0.00</td>
            <td>
                <button type="button" onclick="this.closest('tr').remove(); calculateTotal();" class="button-delete-inline">X</button>
            </td>
        `;
        
        detalleBody.appendChild(newRow);
        
        // Enfoca el nuevo select para agilizar la entrada
        newRow.querySelector('.product-select').focus();
    }
    
    /**
     * Prepara los datos del formulario para el envío (Serialización JSON).
     */
    document.getElementById('transaccion_form').addEventListener('submit', function(event) {
        event.preventDefault();

        const detalles = [];
        const rows = detalleBody.querySelectorAll('tr');
        let totalCalculated = 0;

        rows.forEach(row => {
            const id_producto = row.querySelector('.product-select').value;
            const cantidad = parseFloat(row.querySelector('.qty-input').value);
            const precio_venta = parseFloat(row.querySelector('.price-input').value);
            
            if (id_producto && cantidad > 0 && precio_venta >= 0) {
                
                totalCalculated += cantidad * precio_venta;
                
                detalles.push({
                    id_producto: id_producto,
                    cantidad: cantidad,
                    precio_venta: precio_venta, 
                });
            }
        });

        // Validaciones Finales
        if (detalles.length === 0) {
            alert('Debe ingresar al menos una línea de producto válida.');
            return;
        }
        
        // Validación de Referencia Bancaria
        const referenciaBanco = document.getElementById('referencia_banco').value.trim();
        if (!referenciaBanco) {
            alert('El número de referencia bancaria es obligatorio.');
            return;
        }

        // Asignar los datos serializados y el total final al formulario
        document.getElementById('detalles_json').value = JSON.stringify(detalles);
        document.getElementById('monto_total_oculto').value = totalCalculated.toFixed(2);

        // Finalmente, envía el formulario
        this.submit();
    });

    // ===============================================
    // Inicialización y Bindings
    // ===============================================

    document.addEventListener('DOMContentLoaded', () => {
        addDetalleRow(); 
    });

    // Atajo de teclado
    document.addEventListener('keydown', (e) => {
        if (e.key === 'F8') {
            e.preventDefault();
            addDetalleRow();
        }
    });
</script>
</body>
</html>