<?php
// reporte_ventas_pdf.php
require('libs/fpdf/fpdf.php'); 
include 'includes/auth.php'; 
require_login(['Administrador', 'Gerente', 'Contador']); // Solo personal autorizado
include 'includes/db_connect.php'; 

$id_jornada_consulta = isset($_GET['id_jornada']) ? intval($_GET['id_jornada']) : 0;

if ($id_jornada_consulta <= 0) {
    die(utf8_decode("Error: ID de jornada no válido."));
}

// --- 1. EXTRACCIÓN DE DATOS ---

// A. Datos de la Jornada y Usuario que la ABRIÓ
$sql_jornada = "SELECT cj.*, u.user_full_name as nombre_usuario_apertura 
                FROM control_jornadas cj 
                LEFT JOIN usuarios u ON cj.id_usuario_apertura_fk = u.id_usuario 
                WHERE cj.id_jornada = $id_jornada_consulta";
$datos_j = $conn->query($sql_jornada)->fetch_assoc();

// B. Usuario que GENERA el reporte (Desde la sesión activa)
$generado_por = $_SESSION['user_full_name'] ?? 'Usuario no identificado';

// C. Ventas Totales
$sql_v = "SELECT SUM(dp.monto_pago) as total FROM detalle_pago dp 
          JOIN transacciones t ON dp.id_transaccion_fk = t.id_registro 
          WHERE t.id_jornada_fk = $id_jornada_consulta AND t.es_egreso = 0";
$ventas_totales = $conn->query($sql_v)->fetch_assoc()['total'] ?? 0;

// D. Dinero en Banco
$sql_b = "SELECT SUM(dp.monto_pago) as total FROM detalle_pago dp 
          JOIN transacciones t ON dp.id_transaccion_fk = t.id_registro 
          WHERE t.id_jornada_fk = $id_jornada_consulta AND dp.conciliado_banco = 1 AND dp.id_metodo_fk IN (2,3)";
$dinero_banco = $conn->query($sql_b)->fetch_assoc()['total'] ?? 0;

$diferencia_contable = $ventas_totales - $dinero_banco;

// --- 2. CONFIGURACIÓN PDF ---

class PDF extends FPDF {
    function Header() {
        $fecha_generacion = date('d/m/Y H:i:s');
        $this->SetFont('Arial', 'B', 15);
        $this->SetTextColor(30, 41, 59);
        $this->Cell(0, 10, utf8_decode('REPORTE DE RESUMEN DIARIO'), 0, 1, 'C');
        
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 5, utf8_decode('Fecha y hora de emisión: ' . $fecha_generacion), 0, 1, 'R');
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Documento para Libro Contable - Página ').$this->PageNo().'/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// --- ENCABEZADO DE DATOS ---
$pdf->SetFillColor(241, 245, 249);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 8, utf8_decode('  IDENTIFICACIÓN DE LA OPERACIÓN'), 0, 1, 'L', true);
$pdf->Ln(2);

// Fila 1: ID Jornada y Estado
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(40, 7, utf8_decode('Número de Jornada:'), 0);
$pdf->SetFont('Arial', '', 9); $pdf->Cell(55, 7, $id_jornada_consulta, 0);
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(40, 7, utf8_decode('Estado Actual:'), 0);
$pdf->SetFont('Arial', 'B', 9);
$estado = strtoupper($datos_j['estado_jornada']);
if($estado == 'VALIDADA') $pdf->SetTextColor(16, 185, 129); else $pdf->SetTextColor(245, 158, 11);
$pdf->Cell(55, 7, utf8_decode($estado), 0, 1);

// Fila 2: Fecha y Responsable de Apertura
$pdf->SetTextColor(0);
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(40, 7, utf8_decode('Fecha Jornada:'), 0);
$pdf->SetFont('Arial', '', 9); $pdf->Cell(55, 7, $datos_j['fecha_apertura'], 0);
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(40, 7, utf8_decode('Apertura por:'), 0);
$pdf->SetFont('Arial', '', 9); $pdf->Cell(55, 7, utf8_decode($datos_j['nombre_usuario_apertura']), 0, 1);

// NUEVA Fila 3: Quién genera este reporte actualmente
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(40, 7, utf8_decode('Reporte creado por:'), 0);
$pdf->SetFont('Arial', 'I', 9); $pdf->SetTextColor(37, 99, 235); // Color azul suave para diferenciar
$pdf->Cell(55, 7, utf8_decode($generado_por), 0, 1);
$pdf->SetTextColor(0);

$pdf->Ln(8);

// --- BALANCE ---
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 8, utf8_decode('  RESUMEN CONTABLE'), 0, 1, 'L', true);
$pdf->Ln(2);

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(115, 10, utf8_decode('Total Ventas del Sistema (A):'), 1);
$pdf->Cell(45, 10, 'Bs ' . number_format($ventas_totales, 2), 1, 1, 'R');

$pdf->Cell(115, 10, utf8_decode('Dinero Confirmado en Banco (B):'), 1);
$pdf->Cell(45, 10, 'Bs ' . number_format($dinero_banco, 2), 1, 1, 'R');



$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(254, 249, 195);
$pdf->Cell(115, 12, utf8_decode('DIFERENCIA (Efectivo Neto A - B):'), 1, 0, 'L', true);
$pdf->Cell(45, 12, 'Bs ' . number_format($diferencia_contable, 2), 1, 1, 'R', true);
$pdf->Ln(10);

// --- TABLA DETALLADA DE PAGOS RECIBIDOS ---
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(241, 245, 249);
$pdf->Cell(0, 8, utf8_decode('  DETALLE DE PAGOS RECIBIDOS'), 0, 1, 'L', true);
$pdf->SetFont('Arial', 'B', 8);

$pdf->Cell(15, 8, 'ID', 1, 0, 'C');
$pdf->Cell(35, 8, 'Cajero', 1, 0, 'C'); 
$pdf->Cell(35, 8, 'Metodo', 1, 0, 'C');
$pdf->Cell(45, 8, utf8_decode('Nº Referencia'), 1, 0, 'C');
$pdf->Cell(30, 8, 'Monto', 1, 0, 'C');
$pdf->Cell(30, 8, 'Conciliado', 1, 1, 'C');

$pdf->SetFont('Arial', '', 8);

$sql_det = "SELECT dp.*, mp.nombre_metodo, u.user_full_name as nombre_completo_cajero 
            FROM detalle_pago dp 
            JOIN transacciones t ON dp.id_transaccion_fk = t.id_registro 
            JOIN metodos_pago mp ON dp.id_metodo_fk = mp.id_metodo 
            LEFT JOIN usuarios u ON t.id_usuario_cajero_fk = u.id_usuario 
            WHERE t.id_jornada_fk = $id_jornada_consulta AND t.es_egreso = 0";

$res_det = $conn->query($sql_det);

if($res_det->num_rows > 0) {
    while ($p = $res_det->fetch_assoc()) {
        $pdf->Cell(15, 7, $p['id_pago'], 1, 0, 'C');
        // CAMBIO AQUÍ: Usamos el alias nombre_completo_cajero
        $pdf->Cell(35, 7, utf8_decode($p['nombre_completo_cajero'] ?: 'N/A'), 1, 0, 'L');
        $pdf->Cell(35, 7, utf8_decode($p['nombre_metodo']), 1, 0, 'L');
        $referencia = $p['referencia_banco'] ?: ($p['referencia'] ?: 'S/R');
        $pdf->Cell(45, 7, utf8_decode($referencia), 1, 0, 'C');
        $pdf->Cell(30, 7, 'Bs ' . number_format($p['monto_pago'], 2), 1, 0, 'R');
        $status = ($p['conciliado_banco']) ? 'SI' : 'NO';
        $pdf->Cell(30, 7, $status, 1, 1, 'C');
    }
} else {
    $pdf->Cell(190, 7, utf8_decode('Sin movimientos de ingreso registrados.'), 1, 1, 'C');
}

// --- SECCIÓN COMPACTA: RESUMEN DE EGRESOS (Audit-Friendly) ---
$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(254, 226, 226); 
$pdf->Cell(0, 8, utf8_decode('  RESUMEN DE SALIDAS Y EGRESOS ESPECIALES'), 0, 1, 'L', true);

$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(100, 8, utf8_decode('Motivo o Concepto de Salida'), 1, 0, 'C');
$pdf->Cell(40, 8, utf8_decode('Cant. Artículos'), 1, 0, 'C');
$pdf->Cell(50, 8, 'Impacto en Costo (Total)', 1, 1, 'C');

$pdf->SetFont('Arial', '', 9);

// Consulta compactada por tipo de movimiento para auditoría
$sql_egresos_compactos = "SELECT 
                    tm.nombre_movimiento AS motivo, 
                    SUM(de.cantidad) as total_items,
                    SUM(de.cantidad * i.costo_unitario) AS costo_total_grupo
                FROM detalle_egresos de
                JOIN transacciones t ON de.id_transaccion_fk = t.id_registro
                JOIN inventario i ON de.id_producto_fk = i.id_producto
                LEFT JOIN tipo_movimiento tm ON de.id_tipo_movimiento_fk = tm.id_tipo_movimiento
                WHERE t.id_jornada_fk = $id_jornada_consulta 
                AND t.es_egreso = 1
                GROUP BY tm.nombre_movimiento";

$res_eg = $conn->query($sql_egresos_compactos);
$total_final_egresos = 0;

if($res_eg && $res_eg->num_rows > 0) {
    while($eg = $res_eg->fetch_assoc()){
        $pdf->Cell(100, 8, utf8_decode($eg['motivo'] ?: 'Otros Egresos'), 1, 0, 'L');
        $pdf->Cell(40, 8, $eg['total_items'], 1, 0, 'C');
        $pdf->Cell(50, 8, 'Bs -' . number_format($eg['costo_total_grupo'], 2), 1, 1, 'R');
        $total_final_egresos += $eg['costo_total_grupo'];
    }
    
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(140, 8, 'TOTAL SALIDAS DE INVENTARIO:', 1, 0, 'R');
    $pdf->Cell(50, 8, 'Bs -' . number_format($total_final_egresos, 2), 1, 1, 'R');
} else {
    $pdf->Cell(190, 8, utf8_decode('No se registraron egresos en esta jornada.'), 1, 1, 'C');
}

// --- BLOQUE DE OBSERVACIONES Y FIRMAS ---
$pdf->Ln(10);
if(!empty($datos_j['observaciones'])){
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 6, 'OBSERVACIONES DE LA JORNADA:', 0, 1, 'L');
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->MultiCell(0, 5, utf8_decode($datos_j['observaciones']), 1, 'L');
}

$pdf->Ln(20);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(60, 5, '_______________________', 0, 0, 'C');
$pdf->Cell(70, 5, '', 0, 0);
$pdf->Cell(60, 5, '_______________________', 0, 1, 'C');
$pdf->Cell(60, 5, 'FIRMA CAJERO', 0, 0, 'C');
$pdf->Cell(70, 5, '', 0, 0);
$pdf->Cell(60, 5, 'FIRMA CONTADOR', 0, 1, 'C');

$pdf->Output('I', "Reporte_Contable_J$id_jornada_consulta.pdf");
?>