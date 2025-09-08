<?php
/**
 * Generador de reportes Capital Transport LLP Payment Information - SOLO PDF
 * VERSIÓN SIMPLIFICADA SIN EXCEL - SOLO GENERACIÓN DE PDF
 * Ruta: /classes/CapitalTransportReportGenerator.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Logger.php';

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
             ORDER BY trip_date ASC, ticket_number ASC",
            [$this->voucher_id, $this->company_id]
        );
        
        if (empty($this->trips_data)) {
            throw new Exception("No trip data found for report generation");
        }
    }
    
    /**
     * Generar reporte PDF Capital Transport
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
            
            // Generar SOLO archivo PDF
            $pdf_file = $this->generatePDFReport($report_id, $capital_data);
            
            // Actualizar Payment No de la empresa
            $this->updateCompanyPaymentNo($payment_no);
            
            $this->logger->log(null, 'REPORT_GENERATED', 
                "Capital Transport PDF report generated - Company: {$this->company_info['name']}, Payment No: {$payment_no}");
            
            return [
                'success' => true,
                'report_id' => $report_id,
                'payment_no' => $payment_no,
                'capital_data' => $capital_data,
                'financial_data' => $financial_data,
                'pdf_file' => $pdf_file,
                'trips_count' => count($this->trips_data)
            ];
            
        } catch (Exception $e) {
            $this->logger->log(null, 'REPORT_ERROR', "Error generating PDF report: " . $e->getMessage());
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
     * Generar reporte PDF - FORMATO EXACTO CON TÉRMINOS ACTUALIZADOS
     */
    private function generatePDFReport($report_id, $capital_data) {
        require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
        
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // Configurar documento
        $pdf->SetCreator('Capital Transport LLP');
        $pdf->SetTitle('Payment Information - Payment No. ' . $capital_data['payment_no']);
        $pdf->SetSubject('Capital Transport Payment Report');
        
        // Eliminar header y footer por defecto
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Configurar márgenes
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(TRUE, 25);
        
        // ====================================
        // PÁGINA 1: INFORMACIÓN DE PAGO
        // ====================================
        
        $pdf->AddPage();
        
        // HEADER - "Thank you!" (top left)
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Thank you!', 0, 1, 'L');
        $pdf->Ln(5);
        
        // TÍTULO PRINCIPAL (centrado)
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->Cell(0, 10, 'CAPITAL TRANSPORT LLP', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 8, 'PAYMENT INFORMATION', 0, 1, 'C');
        $pdf->Ln(10);
        
        // NOMBRE DE LA EMPRESA (centrado)
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, strtoupper($this->company_info['name']), 0, 1, 'C');
        $pdf->Ln(5);
        
        // ====================================
        // INFORMACIÓN DEL PAGO - ALINEADA A LA IZQUIERDA
        // ====================================
        $pdf->SetFont('helvetica', '', 12);
        
        // Payment No
        $pdf->Cell(0, 8, 'Payment No: ' . $capital_data['payment_no'], 0, 1, 'L');
        
        // Week Start
        $pdf->Cell(0, 8, 'Week Start: ' . date('m/d/Y', strtotime($capital_data['week_start'])), 0, 1, 'L');
        
        // Week End
        $pdf->Cell(0, 8, 'Week End: ' . date('m/d/Y', strtotime($capital_data['week_end'])), 0, 1, 'L');
        
        // Payment Date
        $pdf->Cell(0, 8, 'Payment Date: ' . date('m/d/Y', strtotime($capital_data['payment_date'])), 0, 1, 'L');
        
        // Payment Total
        $pdf->Cell(0, 8, 'Payment Total: $ ' . number_format($capital_data['payment_total'], 2), 0, 1, 'L');
        
        // YTD
        $pdf->Cell(0, 8, 'YTD: $ ' . number_format($capital_data['ytd_amount'], 2), 0, 1, 'L');
        
        $pdf->Ln(10);
        
        // TABLA DE TRIPS CON PAGINACIÓN AUTOMÁTICA Y COLORES
        $this->createTripsTableWithPagination($pdf, $this->trips_data, $capital_data);
        
        // ====================================
        // ÚLTIMA PÁGINA: TÉRMINOS Y CONDICIONES ACTUALIZADOS
        // ====================================
        
        $pdf->AddPage();
        
        // "Thank you!" centrado - MÁS GRANDE Y EN NEGRITA
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->Cell(0, 15, 'Thank you!', 0, 1, 'C');
        $pdf->Ln(20);
        
        // TEXTO DE TÉRMINOS ACTUALIZADO - CENTRADO
        $pdf->SetFont('helvetica', '', 12);
        $terms_text = "Please confirm receipt. If there is a claim, it must be within the next 72 hours, otherwise it will no longer be possible to make any adjustment.";
        
        $pdf->MultiCell(0, 8, $terms_text, 0, 'C');
        $pdf->Ln(10);
        
        // INFORMACIÓN DE CONTACTO ACTUALIZADA - LÍNEA POR LÍNEA CENTRADA
        $pdf->SetFont('helvetica', '', 12);
        $pdf->SetTextColor(0, 0, 0); // Negro
        
        // Primera parte del texto
        $contact_line1 = "For any inquiries, please send email to ";
        $email = "info@capitaltransportllp.com";
        $contact_line2 = " or call 720-319-4201";
        
        // Construir línea completa para centrar
        $full_contact_line = $contact_line1 . $email . $contact_line2;
        
        // Calcular posición centrada
        $pdf->SetXY(20, $pdf->GetY());
        
        // Escribir primera parte
        $pdf->Write(8, $contact_line1);
        
        // Email con estilo de enlace (color azul #0462C0 y subrayado)
        $pdf->SetTextColor(4, 98, 192); // #0462C0 en RGB
        $pdf->SetFont('helvetica', 'U', 12); // Underlined (subrayado)
        $pdf->Write(8, $email);
        
        // Restaurar color y fuente normal para el resto
        $pdf->SetTextColor(0, 0, 0); // Negro
        $pdf->SetFont('helvetica', '', 12); // Normal
        $pdf->Write(8, $contact_line2);
        
        $pdf->Ln(15); // Más espacio al final
        
        // Guardar archivo PDF
        $filename = "Capital_Transport_Payment_{$capital_data['payment_no']}_" . date('Y-m-d', strtotime($capital_data['payment_date'])) . ".pdf";
        $file_path = REPORTS_PATH . $filename;
        
        // Crear directorio si no existe
        if (!is_dir(REPORTS_PATH)) {
            mkdir(REPORTS_PATH, 0755, true);
        }
        
        $pdf->Output($file_path, 'F');
        
        // Actualizar ruta en BD
        $this->db->update('reports', ['file_path' => $file_path], 'id = ?', [$report_id]);
        
        return [
            'filename' => $filename,
            'file_path' => $file_path,
            'file_size' => filesize($file_path)
        ];
    }
    
    /**
     * Crear tabla de trips con paginación automática y formato exacto con colores
     */
    private function createTripsTableWithPagination($pdf, $trips, $capital_data) {
        $widths = [25, 20, 25, 25, 20, 25, 25]; // Anchos de columnas
        $row_height = 7;
        $header_height = 8;
        $trips_per_page = 20; // Reducido para dejar espacio para totales
        
        // Dividir trips en páginas
        $total_trips = count($trips);
        $pages_needed = ceil($total_trips / $trips_per_page);
        $current_trip_index = 0;
        
        for ($page = 1; $page <= $pages_needed; $page++) {
            // Si no es la primera página, agregar nueva página
            if ($page > 1) {
                $pdf->AddPage();
                $pdf->Ln(10); // Espacio al inicio de página
            }
            
            // Headers de la tabla - FONDO NEGRO CON BORDES BLANCOS
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(0, 0, 0); // Negro #000000
            $pdf->SetTextColor(255, 255, 255); // Texto blanco
            $pdf->SetDrawColor(255, 255, 255); // Líneas blancas
            
            $headers = ['Invoice Date', 'Location', 'Ticket No.', 'Vehicle ID', 'Rate', 'Quantity/TON', 'Amount'];
            
            foreach ($headers as $i => $header) {
                $pdf->Cell($widths[$i], $header_height, $header, 1, 0, 'C', true);
            }
            $pdf->Ln();
            
            // Restaurar color de texto a negro para datos (mantener líneas blancas)
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', '', 9);
            
            // Datos de trips con colores alternados
            $trips_in_this_page = 0;
            $row_color_index = 0; // Para alternar colores
            
            while ($current_trip_index < $total_trips && $trips_in_this_page < $trips_per_page) {
                $trip = $trips[$current_trip_index];
                
                // Alternar colores de fila
                if ($row_color_index % 2 == 0) {
                    $pdf->SetFillColor(165, 165, 165); // Gris claro #A5A5A5
                } else {
                    $pdf->SetFillColor(216, 216, 216); // Gris más claro #D8D8D8
                }
                
                // Formatear datos
                $invoice_date = date('m/d/Y', strtotime($trip['trip_date']));
                $location = substr($trip['location'], 0, 15); // Truncar si es muy largo
                $ticket_no = $trip['ticket_number'];
                $vehicle_id = $trip['vehicle_number'];
                $rate = number_format($trip['haul_rate'], 2);
                $quantity = number_format($trip['quantity'], 2);
                $amount = number_format($trip['amount'], 2);
                
                // Crear fila con fondo de color y bordes blancos
                $pdf->Cell($widths[0], $row_height, $invoice_date, 1, 0, 'C', true);
                $pdf->Cell($widths[1], $row_height, $location, 1, 0, 'L', true);
                $pdf->Cell($widths[2], $row_height, $ticket_no, 1, 0, 'C', true);
                $pdf->Cell($widths[3], $row_height, $vehicle_id, 1, 0, 'C', true);
                $pdf->Cell($widths[4], $row_height, $rate, 1, 0, 'R', true);
                $pdf->Cell($widths[5], $row_height, $quantity, 1, 0, 'R', true);
                $pdf->Cell($widths[6], $row_height, $amount, 1, 0, 'R', true);
                $pdf->Ln();
                
                $current_trip_index++;
                $trips_in_this_page++;
                $row_color_index++;
            }
            
            // Si es la última página, agregar totales
            if ($page === $pages_needed) {
                $this->addTotalsToPage($pdf, $capital_data);
            } else {
                // En páginas intermedias, agregar indicador de continuación
                $pdf->Ln(5);
                $pdf->SetFont('helvetica', 'I', 10);
                $pdf->SetTextColor(100, 100, 100); // Gris para texto de continuación
                $pdf->Cell(0, 6, "Continued on next page...", 0, 1, 'C');
                $pdf->SetTextColor(0, 0, 0); // Restaurar negro
            }
        }
    }
    
    /**
     * Agregar totales a la página en el lado derecho
     */
    private function addTotalsToPage($pdf, $capital_data) {
        $pdf->Ln(10); // Espacio entre tabla y totales
        
        // Configurar fuente para totales
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(0, 0, 0); // Negro
        $pdf->SetDrawColor(255, 255, 255); // Líneas blancas
        
        // Ancho de la tabla para alinear totales a la derecha
        $table_width = array_sum([25, 20, 25, 25, 20, 25, 25]); // Total: 165
        $totals_width = 60; // Ancho para las dos columnas de totales
        $left_spacing = $table_width - $totals_width; // Espacio a la izquierda
        
        // SUBTOTAL
        $pdf->Cell($left_spacing, 8, '', 0, 0, 'C'); // Espaciado izquierdo
        $pdf->Cell(30, 8, 'SUBTOTAL', 1, 0, 'L', false);
        $pdf->Cell(30, 8, '$ ' . number_format($capital_data['subtotal'], 2), 1, 1, 'R', false);
        
        // CAPITAL'S PERCENTAGE
        $pdf->Cell($left_spacing, 8, '', 0, 0, 'C'); // Espaciado izquierdo
        $pdf->Cell(30, 8, "CAPITAL'S {$capital_data['capital_percentage']}%", 1, 0, 'L', false);
        $pdf->Cell(30, 8, '$ ' . number_format($capital_data['capital_deduction'], 2), 1, 1, 'R', false);
        
        // TOTAL PAYMENT
        $pdf->Cell($left_spacing, 8, '', 0, 0, 'C'); // Espaciado izquierdo
        $pdf->Cell(30, 8, 'TOTAL PAYMENT', 1, 0, 'L', false);
        $pdf->Cell(30, 8, '$ ' . number_format($capital_data['total_payment'], 2), 1, 1, 'R', false);
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