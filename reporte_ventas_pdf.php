<?php
// reporte_contable_libro_final.php
require('libs/fpdf/fpdf.php'); 
include 'includes/auth.php'; 
require_login(['Administrador', 'Contador']); 
include 'includes/db_connect.php'; 

$id_jornada_consulta = isset($_GET['id_jornada']) ? intval($_GET['id_jornada']) : 0;

if ($id_jornada_consulta <= 0) {
    die(utf8_decode("Error: ID de jornada no válido."));
}

// --- 1. EXTRACCIÓN DE DATOS ---

// A. Datos Maestros e Identidad
$sql_jornada = "SELECT cj.*, u.username as nombre_usuario 
                FROM control_jornadas cj 
                LEFT JOIN usuarios u ON cj.id_usuario_apertura_fk = u.id_usuario 
                WHERE cj.id_jornada = $id_jornada_consulta";
$datos_j = $conn->query($sql_jornada)->fetch_assoc();

// B. Ventas Totales
$sql_v = "SELECT SUM(dp.monto_pago) as total FROM detalle_pago dp 
          JOIN transacciones t ON dp.id_transaccion_fk = t.id_registro 
          WHERE t.id_jornada_fk = $id_jornada_consulta AND t.es_egreso = 0";
$ventas_totales = $conn->query($sql_v)->fetch_assoc()['total'] ?? 0;

// C. Dinero en Banco (Solo conciliados)
$sql_b = "SELECT SUM(dp.monto_pago) as total FROM detalle_pago dp 
          JOIN transacciones t ON dp.id_transaccion_fk = t.id_registro 
          WHERE t.id_jornada_fk = $id_jornada_consulta AND dp.conciliado_banco = 1 AND dp.id_metodo_fk IN (2,3)";
$dinero_banco = $conn->query($sql_b)->fetch_assoc()['total'] ?? 0;

// D. Diferencia Solicitada (Ventas - Banco)
$diferencia_contable = $ventas_totales - $dinero_banco;

// --- 2. CONFIGURACIÓN PDF ---

class PDF extends FPDF {
    function Header() {
        $fecha_generacion = date('d/m/Y H:i:s');
        $this->SetFont('Arial', 'B', 15);
        $this->SetTextColor(30, 41, 59);
        $this->Cell(0, 10, utf8_decode('REPORTE AUDITADO DE JORNADA'), 0, 1, 'C');
        
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

$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(40, 7, utf8_decode('Número de Jornada:'), 0);
$pdf->SetFont('Arial', '', 9); $pdf->Cell(55, 7, $id_jornada_consulta, 0);
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(40, 7, utf8_decode('Estado Actual:'), 0);
$pdf->SetFont('Arial', 'B', 9);
$estado = strtoupper($datos_j['estado_jornada']);
if($estado == 'VALIDADA') $pdf->SetTextColor(16, 185, 129); else $pdf->SetTextColor(245, 158, 11);
$pdf->Cell(55, 7, utf8_decode($estado), 0, 1);

$pdf->SetTextColor(0);
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(40, 7, utf8_decode('Fecha Jornada:'), 0);
$pdf->SetFont('Arial', '', 9); $pdf->Cell(55, 7, $datos_j['fecha_apertura'], 0);
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(40, 7, utf8_decode('Usuario Responsable:'), 0);
$pdf->SetFont('Arial', '', 9); $pdf->Cell(55, 7, utf8_decode($datos_j['nombre_usuario']), 0, 1);
$pdf->Ln(8);

// --- BALANCE ---
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 8, utf8_decode('  RESUMEN CONTABLE'), 0, 1, 'L', true);
$pdf->Ln(2);

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(115, 10, utf8_decode('Total Ventas del Sistema (A):'), 1);
$pdf->Cell(45, 10, '$ ' . number_format($ventas_totales, 2), 1, 1, 'R');

$pdf->Cell(115, 10, utf8_decode('Dinero Confirmado en Banco (B):'), 1);
$pdf->Cell(45, 10, '$ ' . number_format($dinero_banco, 2), 1, 1, 'R');

$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(254, 249, 195);
$pdf->Cell(115, 12, utf8_decode('DIFERENCIA (Efectivo Neto A - B):'), 1, 0, 'L', true);
$pdf->Cell(45, 12, '$ ' . number_format($diferencia_contable, 2), 1, 1, 'R', true);
$pdf->Ln(10);

// --- TABLA DETALLADA (CON REFERENCIA BANCARIA) ---
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(241, 245, 249);
$pdf->Cell(0, 8, utf8_decode('  DETALLE DE REFERENCIAS BANCARIAS'), 0, 1, 'L', true);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(20, 8, 'ID Pago', 1, 0, 'C');
$pdf->Cell(45, 8, 'Metodo', 1, 0, 'C');
$pdf->Cell(55, 8, utf8_decode('Nº Referencia Banco'), 1, 0, 'C'); // Campo solicitado
$pdf->Cell(35, 8, 'Monto', 1, 0, 'C');
$pdf->Cell(35, 8, 'Conciliado', 1, 1, 'C');

$pdf->SetFont('Arial', '', 9);
$sql_det = "SELECT dp.*, mp.nombre_metodo FROM detalle_pago dp 
            JOIN transacciones t ON dp.id_transaccion_fk = t.id_registro 
            JOIN metodos_pago mp ON dp.id_metodo_fk = mp.id_metodo 
            WHERE t.id_jornada_fk = $id_jornada_consulta AND dp.id_metodo_fk IN (2,3)";
$res_det = $conn->query($sql_det);

if($res_det->num_rows > 0) {
    while ($p = $res_det->fetch_assoc()) {
        $pdf->Cell(20, 7, $p['id_pago'], 1, 0, 'C');
        $pdf->Cell(45, 7, utf8_decode($p['nombre_metodo']), 1);
        // Aquí mostramos la referencia exactamente como en la DB
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(55, 7, utf8_decode($p['referencia'] ?: 'S/R'), 1, 0, 'C');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(35, 7, '$ ' . number_format($p['monto_pago'], 2), 1, 0, 'R');
        
        $status = ($p['conciliado_banco']) ? 'SI' : 'NO';
        if($status == 'SI') $pdf->SetTextColor(16, 185, 129); else $pdf->SetTextColor(239, 68, 68);
        $pdf->Cell(35, 7, $status, 1, 1, 'C');
        $pdf->SetTextColor(0);
    }
} else {
    $pdf->Cell(190, 7, utf8_decode('Sin movimientos bancarios registrados.'), 1, 1, 'C');
}

// --- FIRMAS ---
$pdf->Ln(30);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(60, 5, '_______________________', 0, 0, 'C');
$pdf->Cell(70, 5, '', 0, 0);
$pdf->Cell(60, 5, '_______________________', 0, 1, 'C');
$pdf->Cell(60, 5, 'FIRMA CAJERO', 0, 0, 'C');
$pdf->Cell(70, 5, '', 0, 0);
$pdf->Cell(60, 5, 'FIRMA CONTADOR', 0, 1, 'C');

$pdf->Output('I', "Auditoria_Contable_J$id_jornada_consulta.pdf");
?>