<?php
// form_registro_egresos.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../includes/auth.php'; 
require_login(['Administrador', 'Gerente', 'Supervisor', 'Cajero']); 
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
        $detalles = json_decode($_POST['detalles_json'], true);
        $motivo_comentario = $conn->real_escape_string($_POST['comentario_egreso'] ?? '');

        if (empty($detalles)) {
            $mensaje = "❌ Debe agregar al menos un producto.";
            $clase_mensaje = "alerta-roja";
        } else {
            $conn->begin_transaction();
            try {
                $id_usuario = $_SESSION['user_id'];
                
                $sql_trans = "INSERT INTO transacciones (id_usuario_cajero_fk, id_jornada_fk, fecha_transaccion, es_egreso, motivo_egreso) 
                            VALUES ($id_usuario, $id_jornada, NOW(), 1, '$motivo_comentario')";
                $conn->query($sql_trans);
                $id_transaccion = $conn->insert_id;

                foreach ($detalles as $item) {
                    $id_p = intval($item['id_producto']);
                    $cant = intval($item['cantidad']);
                    $id_tipo_mov = intval($item['id_tipo_movimiento']); 
                    $id_usuario_autoriza = $_SESSION['user_id'];

                    $sql_det = "INSERT INTO detalle_egresos (id_transaccion_fk, id_producto_fk, id_tipo_movimiento_fk, cantidad, usuario_autorizador) 
                                VALUES ($id_transaccion, $id_p, $id_tipo_mov, $cant, $id_usuario_autoriza)";
                    $conn->query($sql_det);

                    $conn->query("UPDATE inventario SET stock_actual = stock_actual - $cant WHERE id_producto = $id_p");
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

$productos_json = [];
$lista_motivos = [];

if ($id_jornada) {
    // 1. Obtener productos con stock
    $res_prod = $conn->query("SELECT id_producto, nombre_producto, stock_actual FROM inventario WHERE stock_actual > 0 ORDER BY nombre_producto");
    while($p = $res_prod->fetch_assoc()) { 
        $p['nombre_producto'] = htmlspecialchars($p['nombre_producto'], ENT_QUOTES, 'UTF-8');
        $productos_json[] = $p; 
    }
    
    // 2. Obtener motivos de salida que estén ACTIVOS (Corrección aquí)
    $res_mot = $conn->query("SELECT id_tipo_movimiento, nombre_movimiento FROM tipo_movimiento WHERE tipo_flujo = 'Salida' AND activo = 1");
    
    while($m = $res_mot->fetch_assoc()) { 
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
        .btn-add { background: var(--primary); color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-confirm { background: var(--danger); color: white; width: 100%; padding: 18px; border: none; border-radius: 10px; cursor: pointer; font-size: 1.2em; font-weight: bold; margin-top: 20px; }
        .ts-control { border-radius: 8px !important; padding: 8px !important; }
        .alerta-roja { background: #fee2e2; color: #991b1b; border: 1px solid #f87171; }
        .alerta-verde { background: #dcfce7; color: #166534; border: 1px solid #4ade80; }
    </style>
</head>
<body>
    <?php include '../includes/menu.php'; ?>

    <div style="max-width: 1200px; margin: 30px auto; padding: 0 20px;">
        <h1>📉 Registro de Egresos Especiales</h1>
        
        <?php if ($mensaje): ?>
            <div class="<?php echo $clase_mensaje; ?>" style="padding:15px; margin-bottom:20px; border-radius:8px;">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <?php if (!$id_jornada): ?>
            <div style="background:#fee2e2; border:2px solid var(--danger); color:#991b1b; padding:30px; border-radius:12px; text-align:center;">
                <h2>⚠️ ATENCIÓN: SISTEMA BLOQUEADO</h2>
                <p>No existe una Jornada de Trabajo abierta.</p>
                <a href="../dashboard.php" class="btn-add" style="text-decoration:none;">Ir a Inicio</a>
            </div>
        <?php else: ?>
            <form id="formEgreso" method="POST" class="venta-container" onsubmit="return validarEnvio()">
                <div class="panel">
                    <h3>Productos para Salida</h3>
                    <table>
                        <thead>
                            <tr>
                                <th width="50%">Producto</th>
                                <th width="15%">Cant.</th>
                                <th width="30%">Motivo (Tipo de Salida)</th>
                                <th width="5%"></th>
                            </tr>
                        </thead>
                        <tbody id="cuerpoTabla"></tbody>
                    </table>
                    <button type="button" class="btn-add" style="margin-top:20px;" onclick="agregarFila()">+ Añadir Producto (F8)</button>
                </div>

                <div class="panel">
                    <h3>Resumen de Egreso</h3>
                    <p style="color: #64748b; font-size: 0.9em;">Describa detalladamente el motivo de este egreso.</p>
                    
                    <label><strong>Comentario de la Transacción:</strong></label>
                    <textarea name="comentario_egreso" id="comentario_egreso" rows="4" placeholder="Ej: Merma por ruptura..." required style="margin-top:10px; margin-bottom:15px; font-family:inherit;"></textarea>

                    <label>Autorizado por:</label>
                    <input type="text" value="<?php echo htmlspecialchars($_SESSION['user_full_name'] ?? $_SESSION['username']); ?>" disabled style="background:#f8fafc; margin-bottom:15px;">

                    <input type="hidden" name="detalles_json" id="detalles_json">
                    <button type="submit" name="btn_guardar_egreso" class="btn-confirm">CONFIRMAR EGRESO</button>
                </div>
            </form>

            <script>
                const productosMaster = <?php echo json_encode($productos_json); ?>;
                const motivosMaster = <?php echo json_encode($lista_motivos); ?>;

                window.agregarFila = function() {
                    const tbody = document.getElementById('cuerpoTabla');
                    const tr = document.createElement('tr');
                    
                    let optionsMotivos = '<option value="">-- Seleccione --</option>';
                    motivosMaster.forEach(m => {
                        optionsMotivos += `<option value="${m.id_tipo_movimiento}">${m.nombre_movimiento}</option>`;
                    });

                    tr.innerHTML = `
                        <td><select class="p-select" required></select></td>
                        <td><input type="number" class="c-input" value="1" min="1"></td>
                        <td><select class="m-select" required onchange="recalcular()">${optionsMotivos}</select></td>
                        <td><button type="button" onclick="eliminarFila(this)" style="background:none; border:none; color:#ef4444; font-size:1.5em; cursor:pointer;">&times;</button></td>
                    `;
                    tbody.appendChild(tr);

                    const selectElement = tr.querySelector('.p-select');
                    const inputCantidad = tr.querySelector('.c-input');

                    // Crear instancia de TomSelect
                    new TomSelect(selectElement, {
                        create: false,
                        placeholder: "Buscar producto...",
                        onChange: function() {
                            validarStock(inputCantidad);
                            recalcular();
                            actualizarOpcionesDisponibles();
                        }
                    });
                    
                    actualizarOpcionesDisponibles();

                    inputCantidad.addEventListener('change', () => {
                        validarStock(inputCantidad);
                        recalcular();
                    });
                };

                window.actualizarOpcionesDisponibles = function() {
                    const IDsSeleccionados = Array.from(document.querySelectorAll('.p-select'))
                                                  .map(s => s.value)
                                                  .filter(v => v !== "");

                    document.querySelectorAll('.p-select').forEach(select => {
                        const ts = select.tomselect;
                        const valorActual = select.value;

                        ts.clearOptions();

                        productosMaster.forEach(p => {
                            // Mostrar si no está seleccionado en otro lado, o si es el valor actual de este select
                            if (!IDsSeleccionados.includes(p.id_producto.toString()) || p.id_producto.toString() === valorActual) {
                                ts.addOption({
                                    value: p.id_producto,
                                    text: `${p.nombre_producto} (${p.stock_actual})`
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
                        const stock = parseFloat(productData.stock_actual);
                        if (parseFloat(input.value) > stock) {
                            alert("No hay suficiente stock. Disponible: " + stock);
                            input.value = stock;
                        }
                    }
                };

                window.recalcular = function() {
                    const data = [];
                    document.querySelectorAll('#cuerpoTabla tr').forEach(f => {
                        const idProd = f.querySelector('.p-select').value;
                        const cant = f.querySelector('.c-input').value;
                        const idMot = f.querySelector('.m-select').value;
                        if(idProd && idMot) {
                            data.push({ id_producto: idProd, cantidad: cant, id_tipo_movimiento: idMot });
                        }
                    });
                    document.getElementById('detalles_json').value = JSON.stringify(data);
                };

                window.eliminarFila = function(btn) {
                    const fila = btn.closest('tr');
                    const select = fila.querySelector('.p-select');
                    if(select.tomselect) {
                        select.tomselect.destroy();
                    }
                    fila.remove();
                    recalcular();
                    actualizarOpcionesDisponibles();
                };

                window.validarEnvio = function() {
                    recalcular();
                    const json = document.getElementById('detalles_json').value;
                    const comentario = document.getElementById('comentario_egreso').value.trim();
                    const data = JSON.parse(json || "[]");

                    if (comentario === "") { alert("Por favor escriba el motivo."); return false; }
                    if (data.length === 0) { alert("Debe agregar al menos un producto."); return false; }
                    return confirm("¿Confirmar registro?");
                };

                document.addEventListener('keydown', (e) => { if(e.key === 'F8') { e.preventDefault(); agregarFila(); } });
                document.addEventListener('DOMContentLoaded', () => { agregarFila(); });
            </script>
        <?php endif; ?>
    </div>
</body>
</html>