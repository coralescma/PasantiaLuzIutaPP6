<?php
// form_registro_egresos.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../includes/auth.php'; 
require_login(['Administrador', 'Supervisor']); 
include '../includes/db_connect.php'; 

$mensaje = "";
$clase_mensaje = "";

// 1. Validar Jornada Abierta
$id_jornada = obtenerJornadaActiva($conn);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_guardar_egreso'])) {
    if (!$id_jornada) {
        $mensaje = "❌ Error: No hay una jornada abierta para registrar egresos.";
        $clase_mensaje = "alerta-roja";
    } else {
        // Decodificar el JSON de detalles
        $detalles = json_decode($_POST['detalles_json'], true);
        $motivo_comentario = $conn->real_escape_string(trim($_POST['comentario_egreso'] ?? ''));

        if (empty($detalles)) {
            $mensaje = "❌ Debe agregar al menos un producto.";
            $clase_mensaje = "alerta-roja";
        } else {
            $conn->begin_transaction();
            try {
                $id_usuario = $_SESSION['user_id'];
                
                // 1. Insertar en transacciones (Cabecera)
                // Se marca es_egreso = 1 y el monto_total suele ser 0 o nulo en mermas/salidas
                $sql_trans = "INSERT INTO transacciones (id_usuario_cajero_fk, id_jornada_fk, fecha_transaccion, es_egreso, motivo_egreso, monto_total) 
                            VALUES ($id_usuario, $id_jornada, NOW(), 1, '$motivo_comentario', 0)";
                
                if (!$conn->query($sql_trans)) {
                    throw new Exception("Error al crear cabecera de transacción: " . $conn->error);
                }
                
                $id_transaccion = $conn->insert_id;

                foreach ($detalles as $item) {
                    $id_p = intval($item['id_producto']);
                    $cant = intval($item['cantidad']);
                    $id_tipo_mov = intval($item['id_tipo_movimiento']); 
                    $id_usuario_autoriza = intval($_SESSION['user_id']); // Aseguramos que sea entero

                    // Validar stock real en servidor antes de descontar
                    $check_stock = $conn->query("SELECT stock_actual FROM inventario WHERE id_producto = $id_p FOR UPDATE");
                    $row_stock = $check_stock->fetch_assoc();
                    
                    if (!$row_stock || $row_stock['stock_actual'] < $cant) {
                        throw new Exception("Stock insuficiente para el producto ID: $id_p");
                    }

                    // 2. Insertar detalle
                    $sql_det = "INSERT INTO detalle_egresos (id_transaccion_fk, id_producto_fk, id_tipo_movimiento_fk, cantidad, usuario_autorizador) 
                                VALUES ($id_transaccion, $id_p, $id_tipo_mov, $cant, $id_usuario_autoriza)";
                    
                    if (!$conn->query($sql_det)) {
                        throw new Exception("Error al insertar detalle de egreso: " . $conn->error);
                    }

                    // 3. Descontar inventario
                    $sql_update_inv = "UPDATE inventario SET stock_actual = stock_actual - $cant WHERE id_producto = $id_p";
                    if (!$conn->query($sql_update_inv)) {
                        throw new Exception("Error al actualizar stock: " . $conn->error);
                    }
                }

                $conn->commit();
                $mensaje = "✅ Egresos registrados correctamente y stock actualizado.";
                $clase_mensaje = "alerta-verde";
            } catch (Exception $e) {
                $conn->rollback();
                $mensaje = "❌ Error en el proceso: " . $e->getMessage();
                $clase_mensaje = "alerta-roja";
            }
        }
    }
}

// Consultas para el formulario
$productos_json = [];
$lista_motivos = [];
if ($id_jornada) {
    $res_prod = $conn->query("SELECT id_producto, nombre_producto, stock_actual FROM inventario WHERE stock_actual > 0 ORDER BY nombre_producto");
    while($p = $res_prod->fetch_assoc()) { 
        $p['nombre_producto'] = htmlspecialchars($p['nombre_producto'], ENT_QUOTES, 'UTF-8');
        $productos_json[] = $p; 
    }
    
    $res_mot = $conn->query("SELECT id_tipo_movimiento, nombre_movimiento FROM tipo_movimiento WHERE tipo_flujo = 'Salida' AND activo = 1");
    while($m = $res_mot->fetch_assoc()) { 
        $m['nombre_movimiento'] = htmlspecialchars($m['nombre_movimiento'], ENT_QUOTES, 'UTF-8');
        $lista_motivos[] = $m; 
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SCL - Salidas y Mermas</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <style>
        :root { --primary: #475569; --success: #059669; --danger: #ef4444; --warning: #f59e0b; }
        .venta-container { max-width: 1200px; margin: 20px auto; display: grid; grid-template-columns: 1fr 380px; gap: 25px; padding: 0 20px; }
        .panel { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); height: fit-content; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; background: #f1f5f9; color: #64748b; font-size: 0.9em; }
        td { padding: 12px; border-bottom: 1px solid #f1f5f9; }
        input, select, textarea { padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; width: 100%; box-sizing: border-box; }
        .btn-add { background: var(--primary); color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 600; transition: opacity 0.2s; }
        .btn-add:hover { opacity: 0.9; }
        .btn-confirm { background: var(--danger); color: white; width: 100%; padding: 18px; border: none; border-radius: 10px; cursor: pointer; font-size: 1.2em; font-weight: bold; margin-top: 20px; }
        .btn-confirm:disabled { background: #94a3b8; cursor: not-allowed; }
        .btn-delete { background: none; border: none; color: #ef4444; font-size: 1.5em; cursor: pointer; padding: 0 10px; }
        .ts-control { border-radius: 8px !important; padding: 10px !important; min-height: 40px; }
        .ts-dropdown { width: auto !important; min-width: 100% !important; }
    </style>
</head>
<body>
    <?php include '../includes/menu.php'; ?>

    <div style="max-width: 1200px; margin: 30px auto; padding: 0 20px;">
        <h1>📉 Registro de Egresos Especiales</h1>
        
        <?php if ($mensaje): ?>
            <div class="<?php echo $clase_mensaje; ?>" style="padding:15px; margin-bottom:20px; border-radius:8px; border: 1px solid transparent;">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <?php if (!$id_jornada): ?>
            <div style="background:#fee2e2; border:2px solid var(--danger); color:#991b1b; padding:30px; border-radius:12px; text-align:center;">
                <h2>⚠️ ATENCIÓN: SISTEMA BLOQUEADO</h2>
                <p>No existe una Jornada de Trabajo abierta para registrar movimientos de salida.</p>
                <a href="../dashboard.php" class="btn-add" style="text-decoration:none; display:inline-block; margin-top:15px;">Ir a Inicio</a>
            </div>
        <?php else: ?>
            <form id="formEgreso" method="POST" class="venta-container">
                <div class="panel">
                    <h3>Productos para Salida</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Producto (Buscador)</th>
                                <th width="100">Cant.</th>
                                <th>Motivo (Tipo de Salida)</th>
                                <th width="50"></th>
                            </tr>
                        </thead>
                        <tbody id="cuerpoTabla"></tbody>
                    </table>
                    <button type="button" class="btn-add" id="btnAgregarFila" style="margin-top:20px;" onclick="agregarFila()">+ Añadir Producto (F8)</button>
                </div>

                <div class="panel">
                    <h3>Resumen de Egreso</h3>
                    <p style="color: #64748b; font-size: 0.9em;">Describa detalladamente el por qué de este egreso especial.</p>
                    
                    <label><strong>Comentario de la Transacción:</strong></label>
                    <textarea name="comentario_egreso" id="comentario_egreso" rows="4" placeholder="Ej: Merma por ruptura de empaque..." required style="margin-top:10px; margin-bottom:15px; font-family:inherit;"></textarea>

                    <label>Autorizado por:</label>
                    <input type="text" value="<?php echo htmlspecialchars($_SESSION['user_full_name'] ?? $_SESSION['username']); ?>" disabled style="background:#f8fafc; margin-bottom:15px;">

                    <input type="hidden" name="detalles_json" id="detalles_json">
                    
                    <button type="submit" name="btn_guardar_egreso" class="btn-confirm" id="btnConfirmar">CONFIRMAR EGRESO</button>
                </div>
            </form>

            <script>
                const productosMaster = <?php echo json_encode($productos_json); ?>;
                const motivosMaster = <?php echo json_encode($lista_motivos); ?>;

                window.agregarFila = function() {
                    const tbody = document.getElementById('cuerpoTabla');
                    const tr = document.createElement('tr');
                    
                    let opcionesMotivos = motivosMaster.map(m => `<option value="${m.id_tipo_movimiento}">${m.nombre_movimiento}</option>`).join('');

                    tr.innerHTML = `
                        <td><select class="p-select" required></select></td>
                        <td><input type="number" class="c-input" value="1" min="1" step="1"></td>
                        <td><select class="m-select" required><option value="">-- Seleccione --</option>${opcionesMotivos}</select></td>
                        <td><button type="button" class="btn-delete" onclick="eliminarFila(this)" title="Quitar">&times;</button></td>
                    `;

                    tbody.appendChild(tr);

                    const selectElement = tr.querySelector('.p-select');
                    new TomSelect(selectElement, {
                        create: false,
                        placeholder: "-- Buscar producto... --",
                        maxOptions: 100,
                        onChange: function() { 
                            validarStock(tr.querySelector('.c-input')); 
                            actualizarOpcionesDisponibles(); 
                        }
                    });

                    tr.querySelector('.c-input').addEventListener('change', function() { 
                        validarStock(this); 
                        recalcular();
                    });
                    tr.querySelector('.m-select').addEventListener('change', recalcular);
                    
                    actualizarOpcionesDisponibles();
                    recalcular();
                };

                window.actualizarOpcionesDisponibles = function() {
                    const IDsSeleccionados = Array.from(document.querySelectorAll('.p-select')).map(s => s.value).filter(val => val !== "");
                    
                    document.querySelectorAll('.p-select').forEach(select => {
                        const ts = select.tomselect;
                        if(!ts) return;

                        const valorActual = select.value;
                        ts.clearOptions();
                        
                        productosMaster.forEach(p => {
                            if (!IDsSeleccionados.includes(p.id_producto.toString()) || p.id_producto.toString() === valorActual) {
                                ts.addOption({ 
                                    value: p.id_producto, 
                                    text: p.nombre_producto + " (Stock: " + p.stock_actual + ")" 
                                });
                            }
                        });
                        ts.refreshOptions(false);
                    });
                };

                window.validarStock = function(input) {
                    const fila = input.closest('tr');
                    const sel = fila.querySelector('.p-select');
                    const productData = productosMaster.find(p => p.id_producto == sel.value);
                    
                    if (productData) {
                        let cant = parseInt(input.value);
                        let stock = parseInt(productData.stock_actual);
                        
                        if (isNaN(cant) || cant < 1) input.value = 1;
                        if (cant > stock) {
                            alert("⚠️ Cantidad excede el stock disponible (" + stock + ")");
                            input.value = stock;
                        }
                    }
                    recalcular();
                };

                window.eliminarFila = function(btn) {
                    const fila = btn.closest('tr');
                    const select = fila.querySelector('.p-select');
                    if(select && select.tomselect) select.tomselect.destroy();
                    fila.remove();
                    actualizarOpcionesDisponibles();
                    recalcular();
                };

                window.recalcular = function() {
                    const data = [];
                    document.querySelectorAll('#cuerpoTabla tr').forEach(f => {
                        const idProd = f.querySelector('.p-select').value;
                        const cant = f.querySelector('.c-input').value;
                        const idMot = f.querySelector('.m-select').value;
                        
                        if(idProd && idMot && cant > 0) {
                            data.push({
                                id_producto: parseInt(idProd),
                                cantidad: parseInt(cant),
                                id_tipo_movimiento: parseInt(idMot)
                            });
                        }
                    });
                    document.getElementById('detalles_json').value = JSON.stringify(data);
                };

                document.getElementById('formEgreso').addEventListener('submit', function(e) {
                    recalcular();
                    const jsonString = document.getElementById('detalles_json').value;
                    const data = JSON.parse(jsonString || "[]");
                    const filasCount = document.querySelectorAll('#cuerpoTabla tr').length;

                    if (filasCount === 0) {
                        e.preventDefault();
                        alert("Debe agregar al menos un producto.");
                        return;
                    }

                    if (data.length < filasCount) {
                        e.preventDefault();
                        alert("Por favor, complete todos los campos (Producto y Motivo) de las filas agregadas.");
                        return;
                    }

                    if (!confirm("¿Está seguro de registrar estos egresos? El stock se descontará inmediatamente.")) {
                        e.preventDefault();
                    } else {
                        document.getElementById('btnConfirmar').disabled = true;
                        document.getElementById('btnConfirmar').textContent = "PROCESANDO...";
                    }
                });

                document.addEventListener('keydown', (e) => { 
                    if(e.key === 'F8') { e.preventDefault(); agregarFila(); } 
                });

                document.addEventListener('DOMContentLoaded', () => {
                    agregarFila();
                });
            </script>
        <?php endif; ?>
    </div>
</body>
</html>