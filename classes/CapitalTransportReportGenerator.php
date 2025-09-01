<?php
/**
 * Generador de reportes Capital Transport LLP Payment Information
 * VERSIÓN LIMPIA Y COMPLETA
 * Ruta: /classes/CapitalTransportReportGenerator.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Logger.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CapitalTransportReportGenerator {
    
    private $db;
    private $logger;
    private $company_id;
    private $voucher_id;
    private $trips_data;
    private $company_info;
    
    public function __construct($company_id, $voucher_id) {
        $this->db = Database::getInstance();
        $this->logger = new Logger();
        $this->company_id = $company_id;
        $this->voucher_id = $voucher_id;
        
        $this->loadCompanyInfo();
        $this->loadTripsData();
    }
    
    /**
     * Cargar información de la empresa
     */
    private function loadCompanyInfo() {
        $this->company_info = $this->db->fetch(
            "SELECT * FROM companies WHERE id = ? AND is_active = 1",
            [$this->company_id]
        );
        
        if (!$this->company_info) {
            throw new Exception("Company not found: {$this->company_id}");
        }
    }
    
    /**
     * Cargar datos de trips
     */
    private function loadTripsData() {
        $this->trips_data = $this->db->fetchAll(
            "SELECT * FROM trips 
             WHERE voucher_id = ? AND company_id = ? 
             ORDER BY vehicle_number ASC, trip_date ASC",
            [$this->voucher_id, $this->company_id]
        );
        
        if (empty($this->trips_data)) {
            throw new Exception("No trip data found for report generation");
        }
    }
    
    /**
     * Generar reporte completo Capital Transport
     */
    public function generateReport($week_start, $week_end, $payment_date = null, $ytd_amount = 0) {
        try {
            // Calcular Payment No (auto-increment por empresa/año)
            $payment_no = $this->calculateNextPaymentNo();
            
            // Calcular totales financieros
            $financial_data = $this->calculateFinancialTotals();
            
            // Datos específicos Capital Transport
            $capital_data = [
                'payment_no' => $payment_no,
                'week_start' => $week_start,
                'week_end' => $week_end,
                'payment_date' => $payment_date ?: date('Y-m-d', strtotime('+7 days')),
                'subtotal' => $financial_data['subtotal'],
                'capital_percentage' => $this->company_info['capital_percentage'],
                'capital_deduction' => $financial_data['capital_deduction'],
                'total_payment' => $financial_data['total_payment'],
                'payment_total' => $financial_data['total_payment'], // Mismo valor
                'ytd_amount' => $ytd_amount
            ];
            
            // Guardar en BD
            $report_id = $this->saveReportToDB($capital_data, $financial_data);
            
            // Generar archivos
            $files_generated = [];
            $files_generated['excel'] = $this->generateExcelReport($report_id, $capital_data);
            $files_generated['pdf'] = $this->generatePDFReport($report_id, $capital_data);
            
            // Actualizar Payment No de la empresa
            $this->updateCompanyPaymentNo($payment_no);
            
            $this->logger->log(null, 'REPORT_GENERATED', 
                "Capital Transport report generated - Company: {$this->company_info['name']}, Payment No: {$payment_no}");
            
            return [
                'success' => true,
                'report_id' => $report_id,
                'payment_no' => $payment_no,
                'capital_data' => $capital_data,
                'financial_data' => $financial_data,
                'files' => $files_generated,
                'trips_count' => count($this->trips_data)
            ];
            
        } catch (Exception $e) {
            $this->logger->log(null, 'REPORT_ERROR', "Error generating report: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Calcular siguiente Payment No
     */
    private function calculateNextPaymentNo() {
        $current_year = date('Y');
        
        // Si es año diferente, resetear contador
        if ($this->company_info['last_payment_year'] != $current_year) {
            $this->db->update('companies', 
                ['current_payment_no' => 1, 'last_payment_year' => $current_year],
                'id = ?',
                [$this->company_id]
            );
            return 1;
        }
        
        return $this->company_info['current_payment_no'];
    }
    
    /**
     * Calcular totales financieros
     */
    private function calculateFinancialTotals() {
        $subtotal = 0;
        
        foreach ($this->trips_data as $trip) {
            $subtotal += floatval($trip['amount']);
        }
        
        // Capital's deduction (porcentaje variable por empresa)
        $capital_percentage = floatval($this->company_info['capital_percentage']);
        $capital_deduction = round($subtotal * ($capital_percentage / 100), 2);
        
        // Total payment (subtotal - deducción)
        $total_payment = round($subtotal - $capital_deduction, 2);
        
        return [
            'subtotal' => $subtotal,
            'capital_percentage' => $capital_percentage,
            'capital_deduction' => $capital_deduction,
            'total_payment' => $total_payment,
            'trips_count' => count($this->trips_data),
            'total_quantity' => array_sum(array_column($this->trips_data, 'quantity'))
        ];
    }
    
    /**
     * Guardar reporte en BD
     */
    private function saveReportToDB($capital_data, $financial_data) {
        $report_data = [
            'company_id' => $this->company_id,
            'voucher_id' => $this->voucher_id,
            'payment_no' => $capital_data['payment_no'],
            'week_start' => $capital_data['week_start'],
            'week_end' => $capital_data['week_end'],
            'payment_date' => $capital_data['payment_date'],
            'payment_total' => $capital_data['payment_total'],
            'ytd_amount' => $capital_data['ytd_amount'],
            'subtotal' => $capital_data['subtotal'],
            'capital_percentage' => $capital_data['capital_percentage'],
            'capital_deduction' => $capital_data['capital_deduction'],
            'total_payment' => $capital_data['total_payment'],
            'total_trips' => $financial_data['trips_count'],
            'total_vehicle_count' => count(array_unique(array_column($this->trips_data, 'vehicle_number'))),
            'generated_by' => $_SESSION['user_id'] ?? 1
        ];
        
        return $this->db->insert('reports', $report_data);
    }
    
    /**
     * Generar reporte Excel
     */
    private function generateExcelReport($report_id, $capital_data) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // HEADER - CAPITAL TRANSPORT LLP PAYMENT INFORMATION
        $sheet->setCellValue('A1', 'CAPITAL TRANSPORT LLP PAYMENT INFORMATION');
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Información de pago
        $current_row = 3;
        $sheet->setCellValue("A{$current_row}", "Payment No:");
        $sheet->setCellValue("B{$current_row}", $capital_data['payment_no']);
        $current_row++;
        
        $sheet->setCellValue("A{$current_row}", "Week Start:");
        $sheet->setCellValue("B{$current_row}", date('m/d/Y', strtotime($capital_data['week_start'])));
        $current_row++;
        
        $sheet->setCellValue("A{$current_row}", "Week End:");
        $sheet->setCellValue("B{$current_row}", date('m/d/Y', strtotime($capital_data['week_end'])));
        $current_row++;
        
        $sheet->setCellValue("A{$current_row}", "Payment Date:");
        $sheet->setCellValue("B{$current_row}", date('m/d/Y', strtotime($capital_data['payment_date'])));
        $current_row++;
        
        $sheet->setCellValue("A{$current_row}", "Payment Total:");
        $sheet->setCellValue("B{$current_row}", '$' . number_format($capital_data['payment_total'], 2));
        $current_row++;
        
        $sheet->setCellValue("A{$current_row}", "YTD:");
        $sheet->setCellValue("B{$current_row}", '$' . number_format($capital_data['ytd_amount'], 2));
        $current_row += 2;
        
        // HEADERS DE DATOS
        $headers = ['Invoice Date', 'Location', 'Ticket Number', 'Rate', 'Quantity/TON', 'Amount', 'Vehicle ID'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $current_row, $header);
            $sheet->getStyle($col . $current_row)->getFont()->setBold(true);
            $col++;
        }
        $current_row++;
        
        // DATOS DE TRIPS (mapeados desde Martin Marieta)
        foreach ($this->trips_data as $trip) {
            $sheet->setCellValue('A' . $current_row, date('m/d/Y', strtotime($trip['trip_date']))); // Ship Date → Invoice Date
            $sheet->setCellValue('B' . $current_row, $trip['location']);                           // Location → Location
            $sheet->setCellValue('C' . $current_row, $trip['ticket_number']);                     // Ticket Number → Ticket Number
            $sheet->setCellValue('D' . $current_row, '$' . number_format($trip['haul_rate'], 2)); // Haul Rate → Rate
            $sheet->setCellValue('E' . $current_row, number_format($trip['quantity'], 2));        // Quantity → Quantity/TON
            $sheet->setCellValue('F' . $current_row, '$' . number_format($trip['amount'], 2));    // Amount → Amount
            $sheet->setCellValue('G' . $current_row, $trip['vehicle_number']);                    // Vehicle Number → Vehicle ID
            
            $current_row++;
        }
        
        $current_row++;
        
        // TOTALES FINALES
        $sheet->setCellValue("E{$current_row}", "SUBTOTAL");
        $sheet->setCellValue("F{$current_row}", '$' . number_format($capital_data['subtotal'], 2));
        $sheet->getStyle("E{$current_row}:F{$current_row}")->getFont()->setBold(true);
        $current_row++;
        
        $sheet->setCellValue("E{$current_row}", "CAPITAL'S {$capital_data['capital_percentage']}%");
        $sheet->setCellValue("F{$current_row}", '$' . number_format($capital_data['capital_deduction'], 2));
        $sheet->getStyle("E{$current_row}:F{$current_row}")->getFont()->setBold(true);
        $current_row++;
        
        $sheet->setCellValue("E{$current_row}", "TOTAL PAYMENT");
        $sheet->setCellValue("F{$current_row}", '$' . number_format($capital_data['total_payment'], 2));
        $sheet->getStyle("E{$current_row}:F{$current_row}")->getFont()->setBold(true);
        
        // Ajustar anchos de columna
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Guardar archivo Excel
        $filename = "Capital_Transport_Payment_{$capital_data['payment_no']}_" . date('Y-m-d') . ".xlsx";
        $file_path = REPORTS_PATH . $filename;
        
        // Crear directorio si no existe
        if (!is_dir(REPORTS_PATH)) {
            mkdir(REPORTS_PATH, 0755, true);
        }
        
        $writer = new Xlsx($spreadsheet);
        $writer->save($file_path);
        
        // Actualizar ruta en BD
        $this->db->update('reports', ['file_path' => $file_path], 'id = ?', [$report_id]);
        
        return [
            'filename' => $filename,
            'file_path' => $file_path,
            'file_size' => filesize($file_path)
        ];
    }
    
    /**
     * Generar reporte PDF
     */
    private function generatePDFReport($report_id, $capital_data) {
        require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
        
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // Configurar documento
        $pdf->SetCreator('Capital Transport LLP');
        $pdf->SetTitle('Payment Information - Payment No. ' . $capital_data['payment_no']);
        $pdf->SetSubject('Transport Payment Report');
        
        // Configurar página
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $pdf->SetMargins(15, 20, 15);
        $pdf->SetAutoPageBreak(TRUE, 20);
        
        // Agregar página
        $pdf->AddPage();
        
        // HEADER
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->Cell(0, 10, 'CAPITAL TRANSPORT LLP PAYMENT INFORMATION', 0, 1, 'C');
        $pdf->Ln(5);
        
        // Información de pago
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(40, 6, 'Payment No:', 0, 0, 'L');
        $pdf->Cell(40, 6, $capital_data['payment_no'], 0, 1, 'L');
        
        $pdf->Cell(40, 6, 'Week Start:', 0, 0, 'L');
        $pdf->Cell(40, 6, date('m/d/Y', strtotime($capital_data['week_start'])), 0, 1, 'L');
        
        $pdf->Cell(40, 6, 'Week End:', 0, 0, 'L');
        $pdf->Cell(40, 6, date('m/d/Y', strtotime($capital_data['week_end'])), 0, 1, 'L');
        
        $pdf->Cell(40, 6, 'Payment Date:', 0, 0, 'L');
        $pdf->Cell(40, 6, date('m/d/Y', strtotime($capital_data['payment_date'])), 0, 1, 'L');
        
        $pdf->Cell(40, 6, 'Payment Total:', 0, 0, 'L');
        $pdf->Cell(40, 6, '$' . number_format($capital_data['payment_total'], 2), 0, 1, 'L');
        
        $pdf->Cell(40, 6, 'YTD:', 0, 0, 'L');
        $pdf->Cell(40, 6, '$' . number_format($capital_data['ytd_amount'], 2), 0, 1, 'L');
        
        $pdf->Ln(10);
        
        // TABLA DE DATOS
        $pdf->SetFont('helvetica', 'B', 10);
        
        // Headers
        $pdf->Cell(25, 8, 'Invoice Date', 1, 0, 'C');
        $pdf->Cell(30, 8, 'Location', 1, 0, 'C');
        $pdf->Cell(25, 8, 'Ticket Number', 1, 0, 'C');
        $pdf->Cell(20, 8, 'Rate', 1, 0, 'C');
        $pdf->Cell(20, 8, 'Quantity/TON', 1, 0, 'C');
        $pdf->Cell(25, 8, 'Amount', 1, 0, 'C');
        $pdf->Cell(25, 8, 'Vehicle ID', 1, 1, 'C');
        
        // Datos
        $pdf->SetFont('helvetica', '', 9);
        foreach ($this->trips_data as $trip) {
            // Verificar si necesita nueva página
            if ($pdf->GetY() > 250) {
                $pdf->AddPage();
                // Repetir headers
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->Cell(25, 8, 'Invoice Date', 1, 0, 'C');
                $pdf->Cell(30, 8, 'Location', 1, 0, 'C');
                $pdf->Cell(25, 8, 'Ticket Number', 1, 0, 'C');
                $pdf->Cell(20, 8, 'Rate', 1, 0, 'C');
                $pdf->Cell(20, 8, 'Quantity/TON', 1, 0, 'C');
                $pdf->Cell(25, 8, 'Amount', 1, 0, 'C');
                $pdf->Cell(25, 8, 'Vehicle ID', 1, 1, 'C');
                $pdf->SetFont('helvetica', '', 9);
            }
            
            $pdf->Cell(25, 6, date('m/d/Y', strtotime($trip['trip_date'])), 1, 0, 'C');
            $pdf->Cell(30, 6, substr($trip['location'], 0, 15), 1, 0, 'L');
            $pdf->Cell(25, 6, $trip['ticket_number'], 1, 0, 'C');
            $pdf->Cell(20, 6, '$' . number_format($trip['haul_rate'], 2), 1, 0, 'R');
            $pdf->Cell(20, 6, number_format($trip['quantity'], 2), 1, 0, 'R');
            $pdf->Cell(25, 6, '$' . number_format($trip['amount'], 2), 1, 0, 'R');
            $pdf->Cell(25, 6, $trip['vehicle_number'], 1, 1, 'C');
        }
        
        $pdf->Ln(5);
        
        // TOTALES
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(120, 8, '', 0, 0, 'L'); // Espaciador
        $pdf->Cell(25, 8, 'SUBTOTAL', 1, 0, 'L');
        $pdf->Cell(25, 8, '$' . number_format($capital_data['subtotal'], 2), 1, 1, 'R');
        
        $pdf->Cell(120, 8, '', 0, 0, 'L');
        $pdf->Cell(25, 8, "CAPITAL'S {$capital_data['capital_percentage']}%", 1, 0, 'L');
        $pdf->Cell(25, 8, '$' . number_format($capital_data['capital_deduction'], 2), 1, 1, 'R');
        
        $pdf->Cell(120, 8, '', 0, 0, 'L');
        $pdf->Cell(25, 8, 'TOTAL PAYMENT', 1, 0, 'L');
        $pdf->Cell(25, 8, '$' . number_format($capital_data['total_payment'], 2), 1, 1, 'R');
        
        // Guardar archivo PDF
        $filename = "Capital_Transport_Payment_{$capital_data['payment_no']}_" . date('Y-m-d') . ".pdf";
        $file_path = REPORTS_PATH . $filename;
        
        $pdf->Output($file_path, 'F');
        
        return [
            'filename' => $filename,
            'file_path' => $file_path,
            'file_size' => filesize($file_path)
        ];
    }
    
    /**
     * Actualizar Payment No de la empresa
     */
    private function updateCompanyPaymentNo($payment_no) {
        $this->db->update('companies', 
            [
                'current_payment_no' => $payment_no + 1,
                'last_payment_year' => date('Y')
            ],
            'id = ?',
            [$this->company_id]
        );
    }
    
    /**
     * Calcular YTD acumulado para una empresa
     */
    public static function calculateYTD($company_id, $up_to_date = null) {
        $db = Database::getInstance();
        $up_to_date = $up_to_date ?: date('Y-m-d');
        $current_year = date('Y', strtotime($up_to_date));
        
        $result = $db->fetch(
            "SELECT COALESCE(SUM(total_payment), 0) as ytd_total
             FROM reports 
             WHERE company_id = ? 
             AND YEAR(payment_date) = ?
             AND payment_date <= ?",
            [$company_id, $current_year, $up_to_date]
        );
        
        return floatval($result['ytd_total'] ?? 0);
    }
    
    /**
     * Obtener reportes por empresa
     */
    public static function getCompanyReports($company_id, $year = null) {
        $db = Database::getInstance();
        $year = $year ?: date('Y');
        
        return $db->fetchAll(
            "SELECT 
                r.*,
                v.voucher_number,
                v.original_filename,
                c.name as company_name,
                u.full_name as generated_by_name
             FROM reports r
             JOIN companies c ON r.company_id = c.id
             JOIN vouchers v ON r.voucher_id = v.id
             LEFT JOIN users u ON r.generated_by = u.id
             WHERE r.company_id = ? 
             AND YEAR(r.payment_date) = ?
             ORDER BY r.payment_no DESC",
            [$company_id, $year]
        );
    }
    
    /**
     * Obtener siguiente número de pago disponible
     */
    public static function getNextPaymentNo($company_id) {
        $db = Database::getInstance();
        $company = $db->fetch(
            "SELECT current_payment_no, last_payment_year FROM companies WHERE id = ?",
            [$company_id]
        );
        
        if (!$company) {
            throw new Exception("Company not found");
        }
        
        $current_year = date('Y');
        
        // Si es año diferente, resetear
        if ($company['last_payment_year'] != $current_year) {
            return 1;
        }
        
        return $company['current_payment_no'];
    }
    
    /**
     * Validar si se puede generar reporte
     */
    public function validateReportGeneration() {
        $errors = [];
        
        if (empty($this->trips_data)) {
            $errors[] = "No trip data available for report generation";
        }
        
        if ($this->company_info['capital_percentage'] <= 0 || $this->company_info['capital_percentage'] > 100) {
            $errors[] = "Invalid capital percentage: " . $this->company_info['capital_percentage'] . "%";
        }
        
        foreach ($this->trips_data as $trip) {
            if (empty($trip['vehicle_number']) || strlen($trip['vehicle_number']) !== 9) {
                $errors[] = "Invalid Vehicle Number in trip ID: " . $trip['id'];
                break; // Solo reportar el primer error
            }
        }
        
        return empty($errors) ? true : $errors;
    }
}
?>