<?php
/**
 * API y página para descarga de reportes generados
 * Ruta: /pages/download-reports.php
 */

require_once '../includes/auth-check.php';
require_once '../classes/Database.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Obtener parámetros
$voucher_id = $_GET['voucher_id'] ?? null;
$format = $_GET['format'] ?? 'excel'; // excel, pdf, csv
$company_id = $_GET['company_id'] ?? null;

if (!$voucher_id) {
    header('Location: dashboard.php?error=missing_voucher');
    exit();
}

try {
    $db = Database::getInstance();
    
    // Verificar que el voucher existe y está procesado
    $voucher = $db->fetch("
        SELECT v.*, u.full_name as uploaded_by_name
        FROM vouchers v
        LEFT JOIN users u ON v.uploaded_by = u.id
        WHERE v.id = ? AND v.status = 'processed'
    ", [$voucher_id]);
    
    if (!$voucher) {
        header('Location: dashboard.php?error=voucher_not_found');
        exit();
    }
    
    // Obtener trips del voucher
    $where_clause = "t.voucher_id = ?";
    $params = [$voucher_id];
    
    if ($company_id) {
        $where_clause .= " AND t.company_id = ?";
        $params[] = $company_id;
    }
    
    $trips = $db->fetchAll("
        SELECT 
            t.*,
            c.name as company_name,
            c.tax_id as company_tax_id,
            c.deduction_type,
            c.deduction_value as company_deduction_value
        FROM trips t
        JOIN companies c ON t.company_id = c.id
        WHERE {$where_clause}
        ORDER BY c.name, t.trip_date
    ", $params);
    
    if (empty($trips)) {
        header('Location: dashboard.php?error=no_trips');
        exit();
    }
    
    // Generar reporte según formato
    switch ($format) {
        case 'excel':
            generateExcelReport($voucher, $trips);
            break;
        case 'csv':
            generateCSVReport($voucher, $trips);
            break;
        case 'pdf':
            generatePDFReport($voucher, $trips);
            break;
        default:
            generateExcelReport($voucher, $trips);
    }
    
} catch (Exception $e) {
    error_log("Error generating report: " . $e->getMessage());
    header('Location: dashboard.php?error=report_generation_failed');
    exit();
}

/**
 * Generar reporte en Excel
 */
function generateExcelReport($voucher, $trips) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Configurar metadatos
    $spreadsheet->getProperties()
        ->setCreator('Transport Management System')
        ->setTitle('Reporte de Viajes - ' . $voucher['voucher_number'])
        ->setDescription('Reporte generado desde ' . $voucher['original_filename']);
    
    // Título del reporte
    $sheet->setCellValue('A1', 'REPORTE DE VIAJES');
    $sheet->setCellValue('A2', 'Voucher: ' . $voucher['voucher_number']);
    $sheet->setCellValue('A3', 'Archivo: ' . $voucher['original_filename']);
    $sheet->setCellValue('A4', 'Fecha de Generación: ' . date('d/m/Y H:i'));
    $sheet->setCellValue('A5', 'Generado por: ' . $voucher['uploaded_by_name']);
    
    // Estilo del título
    $sheet->mergeCells('A1:K1');
    $sheet->getStyle('A1')->applyFromArray([
        'font' => ['bold' => true, 'size' => 16],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'E3F2FD']]
    ]);
    
    // Headers de la tabla (fila 7)
    $headers = [
        'A7' => 'Fecha',
        'B7' => 'Empresa',
        'C7' => 'RFC',
        'D7' => 'Origen',
        'E7' => 'Destino',
        'F7' => 'Vehículo',
        'G7' => 'Conductor',
        'H7' => 'Toneladas',
        'I7' => 'Tarifa',
        'J7' => 'Subtotal',
        'K7' => 'Deducción',
        'L7' => 'Total'
    ];
    
    foreach ($headers as $cell => $header) {
        $sheet->setCellValue($cell, $header);
    }
    
    // Estilo de headers
    $sheet->getStyle('A7:L7')->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'F5F5F5']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    
    // Datos de los viajes
    $row = 8;
    $total_subtotal = 0;
    $total_deductions = 0;
    $total_amount = 0;
    
    foreach ($trips as $trip) {
        $sheet->setCellValue('A' . $row, date('d/m/Y', strtotime($trip['trip_date'])));
        $sheet->setCellValue('B' . $row, $trip['company_name']);
        $sheet->setCellValue('C' . $row, $trip['company_tax_id']);
        $sheet->setCellValue('D' . $row, $trip['origin']);
        $sheet->setCellValue('E' . $row, $trip['destination']);
        $sheet->setCellValue('F' . $row, $trip['vehicle_plate']);
        $sheet->setCellValue('G' . $row, $trip['driver_name']);
        $sheet->setCellValue('H' . $row, $trip['weight_tons']);
        $sheet->setCellValue('I' . $row, $trip['unit_rate']);
        $sheet->setCellValue('J' . $row, $trip['subtotal']);
        $sheet->setCellValue('K' . $row, $trip['deduction_amount']);
        $sheet->setCellValue('L' . $row, $trip['total_amount']);
        
        $total_subtotal += $trip['subtotal'];
        $total_deductions += $trip['deduction_amount'];
        $total_amount += $trip['total_amount'];
        
        $row++;
    }
    
    // Fila de totales
    $sheet->setCellValue('I' . $row, 'TOTALES:');
    $sheet->setCellValue('J' . $row, $total_subtotal);
    $sheet->setCellValue('K' . $row, $total_deductions);
    $sheet->setCellValue('L' . $row, $total_amount);
    
    // Estilo de totales
    $sheet->getStyle('I' . $row . ':L' . $row)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FFF3CD']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    
    // Formato de números
    $sheet->getStyle('H8:L' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
    
    // Ajustar ancho de columnas
    foreach (range('A', 'L') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Generar archivo
    $filename = 'reporte_' . $voucher['voucher_number'] . '_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    
    // Log de la descarga
    $logger = new Logger();
    $logger->log($_SESSION['user_id'], 'REPORT_DOWNLOAD', 
        "Descarga de reporte Excel: {$filename} (Voucher: {$voucher['voucher_number']})");
    
    exit();
}

/**
 * Generar reporte en CSV
 */
function generateCSVReport($voucher, $trips) {
    $filename = 'reporte_' . $voucher['voucher_number'] . '_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, [
        'Fecha',
        'Empresa',
        'RFC',
        'Origen',
        'Destino',
        'Vehículo',
        'Conductor',
        'Toneladas',
        'Tarifa',
        'Subtotal',
        'Deducción',
        'Total'
    ]);
    
    // Datos
    foreach ($trips as $trip) {
        fputcsv($output, [
            date('d/m/Y', strtotime($trip['trip_date'])),
            $trip['company_name'],
            $trip['company_tax_id'],
            $trip['origin'],
            $trip['destination'],
            $trip['vehicle_plate'],
            $trip['driver_name'],
            $trip['weight_tons'],
            $trip['unit_rate'],
            $trip['subtotal'],
            $trip['deduction_amount'],
            $trip['total_amount']
        ]);
    }
    
    fclose($output);
    
    // Log de la descarga
    $logger = new Logger();
    $logger->log($_SESSION['user_id'], 'REPORT_DOWNLOAD', 
        "Descarga de reporte CSV: {$filename} (Voucher: {$voucher['voucher_number']})");
    
    exit();
}

/**
 * Generar reporte en PDF
 */
function generatePDFReport($voucher, $trips) {
    require_once '../vendor/tecnickcom/tcpdf/tcpdf.php';
    
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Configurar documento
    $pdf->SetCreator('Transport Management System');
    $pdf->SetTitle('Reporte de Viajes - ' . $voucher['voucher_number']);
    $pdf->SetSubject('Reporte de Viajes');
    
    // Configurar página
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(15, 27, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(TRUE, 25);
    
    // Agregar página
    $pdf->AddPage();
    
    // Título
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 15, 'REPORTE DE VIAJES', 0, 1, 'C');
    
    // Información del voucher
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Voucher: ' . $voucher['voucher_number'], 0, 1);
    $pdf->Cell(0, 6, 'Archivo: ' . $voucher['original_filename'], 0, 1);
    $pdf->Cell(0, 6, 'Fecha de Generación: ' . date('d/m/Y H:i'), 0, 1);
    $pdf->Ln(5);
    
    // Tabla de datos
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(240, 240, 240);
    
    // Headers
    $pdf->Cell(20, 8, 'Fecha', 1, 0, 'C', true);
    $pdf->Cell(40, 8, 'Empresa', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Origen', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Destino', 1, 0, 'C', true);
    $pdf->Cell(15, 8, 'Tons', 1, 0, 'C', true);
    $pdf->Cell(20, 8, 'Tarifa', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Total', 1, 1, 'C', true);
    
    // Datos
    $pdf->SetFont('helvetica', '', 7);
    $total = 0;
    
    foreach ($trips as $trip) {
        $pdf->Cell(20, 6, date('d/m/Y', strtotime($trip['trip_date'])), 1, 0, 'C');
        $pdf->Cell(40, 6, substr($trip['company_name'], 0, 25), 1, 0, 'L');
        $pdf->Cell(30, 6, substr($trip['origin'], 0, 20), 1, 0, 'L');
        $pdf->Cell(30, 6, substr($trip['destination'], 0, 20), 1, 0, 'L');
        $pdf->Cell(15, 6, number_format($trip['weight_tons'], 1), 1, 0, 'R');
        $pdf->Cell(20, 6, '$' . number_format($trip['unit_rate'], 2), 1, 0, 'R');
        $pdf->Cell(25, 6, '$' . number_format($trip['total_amount'], 2), 1, 1, 'R');
        
        $total += $trip['total_amount'];
    }
    
    // Total
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(155, 8, 'TOTAL GENERAL:', 1, 0, 'R', true);
    $pdf->Cell(25, 8, '$' . number_format($total, 2), 1, 1, 'R', true);
    
    // Generar archivo
    $filename = 'reporte_' . $voucher['voucher_number'] . '_' . date('Y-m-d_H-i-s') . '.pdf';
    
    // Log de la descarga
    $logger = new Logger();
    $logger->log($_SESSION['user_id'], 'REPORT_DOWNLOAD', 
        "Descarga de reporte PDF: {$filename} (Voucher: {$voucher['voucher_number']})");
    
    $pdf->Output($filename, 'D');
    exit();
}
?>