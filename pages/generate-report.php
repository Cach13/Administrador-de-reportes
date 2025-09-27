<?php
/**
 * generate-report.php
 * Genera reportes de pago por empresa basados en datos de voucher procesado
 * Formato: Capital Transport LLP Payment Information
 * Transport Management System
 * UBICACIÓN: pages/generate-report.php
 */

// Incluir configuración y dependencias
require_once '../config/config.php';
require_once '../config/Database.php';
require_once '../config/AuthManager.php';

// Inicializar componentes
$auth = new AuthManager();
$db = Database::getInstance();

// Verificar que usuario esté logueado
$auth->requireLogin();
$currentUser = $auth->getCurrentUser();

// Obtener parámetros
$voucher_id = $_GET['voucher_id'] ?? null;
$company_id = $_GET['company_id'] ?? null;

if (!$voucher_id || !$company_id) {
    header('Location: dashboard.php?error=missing_parameters');
    exit;
}

// Variables de estado
$report_error = null;
$report_success = null;
$report_data = null;
$company_info = null;
$voucher_info = null;

try {
    // Verificar que el voucher existe y pertenece al usuario
    $voucher_info = $db->selectOne(
        "SELECT * FROM vouchers WHERE id = ? AND uploaded_by = ?",
        [$voucher_id, $currentUser['id']]
    );
    
    if (!$voucher_info) {
        throw new Exception("Voucher no encontrado o sin permisos para acceder");
    }
    
    if ($voucher_info['status'] !== 'processed') {
        throw new Exception("El voucher debe estar procesado antes de generar reportes");
    }
    
    // Obtener información de la empresa
    $company_info = $db->selectOne(
        "SELECT * FROM companies WHERE id = ? AND is_active = 1",
        [$company_id]
    );
    
    if (!$company_info) {
        throw new Exception("Empresa no encontrada o inactiva");
    }
    
    // Obtener trips de la empresa en este voucher
    $trips_data = $db->select(
        "SELECT t.*, 
                COALESCE(c.name, 'Empresa Desconocida') as company_name, 
                COALESCE(c.identifier, 'N/A') as company_identifier
         FROM trips t
         LEFT JOIN companies c ON t.company_id = c.id
         WHERE t.voucher_id = ? AND (t.company_id = ? OR t.company_id IS NULL)
         ORDER BY t.trip_date ASC, t.location ASC, t.id ASC",
        [$voucher_id, $company_id]
    );

    // DEBUG: Log la consulta
    logMessage('DEBUG', "Consulta trips - voucher_id: {$voucher_id}, company_id: {$company_id}, resultados: " . count($trips_data));
    
    if (empty($trips_data)) {
        throw new Exception("No se encontraron datos para esta empresa en el voucher seleccionado");
    }
    
    // Calcular estadísticas del reporte
    $total_trips = count($trips_data);
    $subtotal = array_sum(array_column($trips_data, 'amount'));
    $capital_percentage = $company_info['capital_percentage'];
    $capital_deduction = $subtotal * ($capital_percentage / 100);
    $total_payment = $subtotal - $capital_deduction;
    
    // Determinar fechas del reporte
    $trip_dates = array_column($trips_data, 'trip_date');
    $week_start = min($trip_dates);
    $week_end = max($trip_dates);
    $payment_date = date('Y-m-d'); // Fecha actual como fecha de pago
    
    // Obtener siguiente número de pago para la empresa
    $last_payment = $db->selectOne(
        "SELECT MAX(payment_no) as max_payment FROM reports WHERE company_id = ? AND payment_year = YEAR(?)",
        [$company_id, $payment_date]
    );
    
    $payment_no = ($last_payment['max_payment'] ?? 0) + 1;
    
    // Calcular YTD (Year To Date)
    $ytd_total = $db->selectOne(
        "SELECT SUM(total_payment) as ytd_amount FROM reports 
         WHERE company_id = ? AND payment_year = YEAR(?) AND id < ?",
        [$company_id, $payment_date, PHP_INT_MAX]
    );
    
    $ytd_amount = ($ytd_total['ytd_amount'] ?? 0) + $total_payment;
    
    // Preparar datos del reporte
    $report_data = [
        'company_info' => $company_info,
        'voucher_info' => $voucher_info,
        'payment_no' => $payment_no,
        'payment_year' => date('Y'),
        'week_start' => $week_start,
        'week_end' => $week_end,
        'payment_date' => $payment_date,
        'trips_data' => $trips_data,
        'total_trips' => $total_trips,
        'subtotal' => $subtotal,
        'capital_percentage' => $capital_percentage,
        'capital_deduction' => $capital_deduction,
        'total_payment' => $total_payment,
        'ytd_amount' => $ytd_amount
    ];
    
    // Procesar formulario de guardado
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'save_report') {
            
            // Verificar que no existe ya un reporte para esta combinación
            $existing_report = $db->selectOne(
                "SELECT id FROM reports WHERE company_id = ? AND voucher_id = ?",
                [$company_id, $voucher_id]
            );
            
            if ($existing_report) {
                throw new Exception("Ya existe un reporte generado para esta empresa y voucher");
            }
            
            // Insertar reporte en la base de datos
            $report_id = $db->insert('reports', [
                'company_id' => $company_id,
                'voucher_id' => $voucher_id,
                'payment_no' => $payment_no,
                'payment_year' => date('Y'),
                'week_start' => $week_start,
                'week_end' => $week_end,
                'payment_date' => $payment_date,
                'payment_total' => $total_payment,
                'ytd_amount' => $ytd_amount,
                'subtotal' => $subtotal,
                'capital_percentage' => $capital_percentage,
                'capital_deduction' => $capital_deduction,
                'total_payment' => $total_payment,
                'total_trips' => $total_trips,
                'total_vehicle_count' => count(array_unique(array_column($trips_data, 'vehicle_number'))),
                'generated_by' => $currentUser['id'],
                'status' => 'generated'
            ]);
            
            // Actualizar contador de pagos de la empresa
            $db->update('companies', ['current_payment_no' => $payment_no], ['id' => $company_id]);
            
            // Log de actividad
            logMessage('INFO', "Reporte generado exitosamente", [
                'report_id' => $report_id,
                'company_id' => $company_id,
                'voucher_id' => $voucher_id,
                'payment_no' => $payment_no,
                'total_amount' => $total_payment
            ]);
            
            $report_success = "Reporte generado y guardado exitosamente (ID: #{$report_id})";
            $report_data['report_id'] = $report_id;
        }
        
        elseif ($_POST['action'] === 'download_pdf') {
            // Generar y descargar PDF
            generatePDFReport($report_data);
            exit;
        }
    }
    
} catch (Exception $e) {
    $report_error = $e->getMessage();
    logMessage('ERROR', "Error generando reporte: " . $e->getMessage(), [
        'voucher_id' => $voucher_id,
        'company_id' => $company_id,
        'user_id' => $currentUser['id']
    ]);
}

/**
 * Función para generar PDF del reporte usando TCPDF
 */
function generatePDFReport($data) {
    require_once '../vendor/autoload.php';
    
    // Crear nueva clase TCPDF personalizada para el footer
    class CustomTCPDF extends TCPDF {
        
        // Eliminar header por defecto
        public function Header() {
            // No hacer nada - elimina cualquier header
        }
        
        public function Footer() {
            // Posición a 15mm del final
            $this->SetY(-15);
            // Arial italic 10
            $this->SetFont('helvetica', 'I', 10);
            // Footer centrado en todas las páginas
            $this->Cell(0, 10, 'Thank you!', 0, false, 'C', 0, '', 0, false, 'T', 'M');
        }
    }
    
    // Crear nuevo documento PDF con clase personalizada
    $pdf = new CustomTCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Configurar información del documento
    $pdf->SetCreator('Capital Transport LLP System');
    $pdf->SetAuthor('Capital Transport LLP');
    $pdf->SetTitle('Payment Report - ' . $data['company_info']['name']);
    $pdf->SetSubject('Payment Information Report');
    
    // Configurar márgenes
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    
    // Auto page breaks
    $pdf->SetAutoPageBreak(TRUE, 25); // Más espacio para el footer
    
    // Configurar fuente
    $pdf->SetFont('helvetica', '', 10);
    
    // Agregar página
    $pdf->AddPage();
    
    // Generar contenido HTML del reporte
    $html = generateReportHTMLForPDF($data);
    
    // Escribir HTML al PDF
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Headers para descarga
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Payment_' . $data['company_info']['identifier'] . '_' . $data['payment_no'] . '.pdf"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Salida del PDF
    $pdf->Output('Payment_' . $data['company_info']['identifier'] . '_' . $data['payment_no'] . '.pdf', 'D');
}

/**
 * Función para generar HTML del reporte optimizado para PDF - FORMATO CORRECTO
 */
function generateReportHTMLForPDF($data) {
    ob_start();
    ?>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 15px; 
            font-size: 10pt; 
            color: #000;
        }
        
        .header { 
            text-align: center; 
            margin-bottom: 25px;
        }
        
        .company-title { 
            font-size: 14pt; 
            font-weight: bold; 
            margin-bottom: 3px;
            color: #000;
        }
        
        .report-title { 
            font-size: 12pt; 
            font-weight: normal;
            margin-bottom: 15px;
            color: #8A8A8A;
        }
        
        .client-name {
            font-size: 11pt;
            font-weight: bold;
            text-align: left;
            margin-bottom: 15px;
            color: #000;
        }
        
        .payment-info { 
            margin: 15px 0;
            font-size: 10pt;
        }
        
        .info-row { 
            margin: 4px 0;
            display: flex;
            justify-content: space-between;
        }
        
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }
        
        .info-value {
            display: inline-block;
        }
        
        .trips-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 15px 0;
            font-size: 9pt;
        }
        
        .trips-table th { 
            border: 1px solid #FFFFFF; 
            padding: 12px 10px; 
            background-color: #000000;
            color: #FFFFFF;
            font-weight: bold; 
            text-align: center;
            font-size: 8pt;
        }
        
        .trips-table td { 
            border: 1px solid #FFFFFF; 
            padding: 10px 8px; 
            text-align: center;
            font-size: 8pt;
        }
        
        .amount-cell { 
            text-align: right !important;
        }
        
        .date-cell {
            text-align: center;
        }
        
        .totals { 
            margin-top: 20px; 
            text-align: right;
        }
        
        .total-row { 
            margin: 5px 0; 
            font-size: 10pt;
            display: flex;
            justify-content: flex-end;
            align-items: center;
        }
        
        .total-label {
            margin-right: 20px;
            font-weight: bold;
        }
        
        .total-value {
            font-weight: bold;
            min-width: 80px;
            text-align: right;
        }
        
        .final-total { 
            font-weight: bold; 
            font-size: 12pt;
            margin-top: 8px;
        }
        
        .footer { 
            margin-top: 30px; 
            font-size: 7pt; 
            color: #666; 
            text-align: center;
        }
        
        .thank-you {
            font-style: italic;
            font-size: 10pt;
            text-align: center;
            position: fixed;
            bottom: 20px;
            left: 0;
            right: 0;
            color: #000;
        }
        
        .legal-notice {
            margin-top: 25px;
            font-size: 8pt;
            color: #000;
            line-height: 1.4;
            text-align: center;
            page-break-before: always;
            padding-top: 50px;
        }
        
        .legal-notice a, .legal-notice .email {
            color: #0462C0;
            text-decoration: none;
        }
    </style>
    
    <div class="header">
        <div class="company-title">CAPITAL TRANSPORT LLP</div>
        <div class="report-title">PAYMENT INFORMATION</div>
    </div>
    
    <div class="client-name"><?php echo strtoupper($data['company_info']['name']); ?></div>
    
    <div class="payment-info">
        <div class="info-row">
            <span class="info-label">Payment No:</span>
            <span class="info-value"><?php echo $data['payment_no']; ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Week Start:</span>
            <span class="info-value"><?php echo date('m/d/Y', strtotime($data['week_start'])); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Week End:</span>
            <span class="info-value"><?php echo date('m/d/Y', strtotime($data['week_end'])); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Payment Date:</span>
            <span class="info-value"><?php echo date('m/d/Y', strtotime($data['payment_date'])); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Payment Total:</span>
            <span class="info-value"><strong>$ <?php echo number_format($data['total_payment'], 2); ?></strong></span>
        </div>
        <div class="info-row">
            <span class="info-label">YTD:</span>
            <span class="info-value"><strong>$ <?php echo number_format($data['ytd_amount'], 2); ?></strong></span>
        </div>
    </div>
    
    <table class="trips-table">
        <thead>
            <tr>
                <th>Invoice Date</th>
                <th>Location</th>
                <th>Ticket No.</th>
                <th>Vehicle ID</th>
                <th>Rate</th>
                <th>Quantity/TON</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $row_count = 0;
            foreach ($data['trips_data'] as $trip): 
                $row_count++;
                $bg_color = ($row_count % 2 == 1) ? '#A5A5A5' : '#D8D8D8';
            ?>
            <tr style="background-color: <?php echo $bg_color; ?>;">
                <td><?php echo date('m/d/Y', strtotime($trip['trip_date'])); ?></td>
                <td><?php echo htmlspecialchars($trip['location']); ?></td>
                <td><?php echo htmlspecialchars($trip['ticket_number']); ?></td>
                <td><?php echo htmlspecialchars($trip['vehicle_number']); ?></td>
                <td><?php echo number_format($trip['haul_rate'], 2); ?></td>
                <td><?php echo number_format($trip['quantity'], 2); ?></td>
                <td><?php echo number_format($trip['amount'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="totals">
        <div class="total-row">
            <span class="total-label">SUBTOTAL</span>
            <span class="total-value">$ <?php echo number_format($data['subtotal'], 2); ?></span>
        </div>
        <div class="total-row">
            <span class="total-label">CAPITAL'S <?php echo $data['capital_percentage']; ?>%</span>
            <span class="total-value">$ <?php echo number_format($data['capital_deduction'], 2); ?></span>
        </div>
        <div class="total-row final-total">
            <span class="total-label">TOTAL PAYMENT</span>
            <span class="total-value">$ <?php echo number_format($data['total_payment'], 2); ?></span>
        </div>
    </div>
    
    <div class="legal-notice">
        <p><strong>Please confirm receipt.</strong> If there is a claim, it must be within the next 72 hours, otherwise it will no longer be possible to make any adjustment. For any inquiries, please send email to <span class="email">info@capitaltransportllp.com</span> or call <strong>720-319-4201</strong></p>
    </div>
    <?php
    return ob_get_clean();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Reporte - <?php echo SYSTEM_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
            line-height: 1.6;
        }
        
        /* HEADER */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .back-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        /* MAIN CONTAINER */
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        /* ALERT MESSAGES */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }
        
        .alert-success {
            background: #f0fff4;
            color: #22543d;
            border: 1px solid #c6f6d5;
        }
        
        .alert-error {
            background: #fff5f5;
            color: #822727;
            border: 1px solid #fed7d7;
        }
        
        /* REPORT SUMMARY CARD */
        .report-summary {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .summary-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .summary-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2d3748;
        }
        
        .company-badge {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: bold;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        
        .summary-item {
            text-align: center;
            padding: 1rem;
            background: #f7fafc;
            border-radius: 8px;
        }
        
        .summary-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }
        
        .summary-value.money {
            color: #38a169;
        }
        
        .summary-label {
            color: #718096;
            font-size: 0.9rem;
        }
        
        /* TRIPS TABLE */
        .trips-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 1rem;
        }
        
        .trips-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }
        
        .trips-table th,
        .trips-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .trips-table th {
            background: #f7fafc;
            font-weight: 600;
            color: #2d3748;
            font-size: 0.9rem;
        }
        
        .trips-table tr:hover {
            background: #f7fafc;
        }
        
        .trips-table td {
            font-size: 0.9rem;
        }
        
        .amount-cell {
            text-align: right;
            font-weight: 600;
            color: #38a169;
        }
        
        /* TOTALS SECTION */
        .totals-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .totals-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            max-width: 400px;
            margin-left: auto;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .total-row:last-child {
            border-bottom: 2px solid #2d3748;
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .total-label {
            color: #2d3748;
        }
        
        .total-value {
            font-weight: 600;
            color: #38a169;
        }
        
        /* ACTION BUTTONS */
        .actions-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin-right: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(72, 187, 120, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid #667eea;
            color: #667eea;
        }
        
        .btn-outline:hover {
            background: #667eea;
            color: white;
        }
        
        /* RESPONSIVE */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .container {
                padding: 0 1rem;
            }
            
            .summary-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .trips-table {
                font-size: 0.8rem;
            }
            
            .trips-table th,
            .trips-table td {
                padding: 0.5rem 0.25rem;
            }
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <header class="header">
        <div class="header-content">
            <div class="header-left">
                <a href="process-voucher.php?id=<?php echo $voucher_id; ?>" class="back-btn">
                    <span>←</span> Volver al Voucher
                </a>
                <div class="page-title">Generar Reporte de Pago</div>
            </div>
            
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($currentUser['full_name'], 0, 2)); ?>
                </div>
                <div>
                    <div style="font-weight: bold;"><?php echo $currentUser['full_name']; ?></div>
                    <div style="font-size: 0.9rem; opacity: 0.8;"><?php echo ucfirst($currentUser['role']); ?></div>
                </div>
            </div>
        </div>
    </header>
    
    <!-- MAIN CONTAINER -->
    <div class="container">
        
        <!-- ALERT MESSAGES -->
        <?php if ($report_error): ?>
        <div class="alert alert-error">
            <strong>Error:</strong> <?php echo htmlspecialchars($report_error); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($report_success): ?>
        <div class="alert alert-success">
            <strong>¡Éxito!</strong> <?php echo htmlspecialchars($report_success); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($report_data): ?>
        
        <!-- REPORT SUMMARY -->
        <div class="report-summary">
            <div class="summary-header">
                <div class="summary-title">Reporte de Pago</div>
                <div class="company-badge"><?php echo htmlspecialchars($report_data['company_info']['name']); ?></div>
            </div>
            
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-value"><?php echo $report_data['payment_no']; ?></div>
                    <div class="summary-label">Número de Pago</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value"><?php echo $report_data['total_trips']; ?></div>
                    <div class="summary-label">Total de Viajes</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value money">$<?php echo number_format($report_data['total_payment'], 2); ?></div>
                    <div class="summary-label">Pago Total</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value money">$<?php echo number_format($report_data['ytd_amount'], 2); ?></div>
                    <div class="summary-label">YTD Amount</div>
                </div>
            </div>
        </div>
        
        <!-- TRIPS TABLE -->
        <div class="trips-section">
            <div class="section-title">Detalle de Viajes</div>
            <p style="color: #718096; margin-bottom: 1rem;">
                Semana del <?php echo date('d/m/Y', strtotime($report_data['week_start'])); ?> 
                al <?php echo date('d/m/Y', strtotime($report_data['week_end'])); ?>
            </p>
            
            <table class="trips-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Location</th>
                        <th>Ticket No.</th>
                        <th>Vehicle ID</th>
                        <th>Rate</th>
                        <th>Quantity</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data['trips_data'] as $trip): ?>
                    <tr>
                        <td><?php echo date('m/d/Y', strtotime($trip['trip_date'])); ?></td>
                        <td><?php echo htmlspecialchars($trip['location']); ?></td>
                        <td><?php echo htmlspecialchars($trip['ticket_number']); ?></td>
                        <td><?php echo htmlspecialchars($trip['vehicle_number']); ?></td>
                        <td><?php echo number_format($trip['haul_rate'], 2); ?></td>
                        <td><?php echo number_format($trip['quantity'], 2); ?></td>
                        <td class="amount-cell">$<?php echo number_format($trip['amount'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- TOTALS SECTION -->
        <div class="totals-section">
            <div class="section-title">Resumen de Pagos</div>
            
            <div class="totals-grid">
                <div class="total-row">
                    <span class="total-label">SUBTOTAL</span>
                    <span class="total-value">$<?php echo number_format($report_data['subtotal'], 2); ?></span>
                </div>
                <div class="total-row">
                    <span class="total-label">CAPITAL'S <?php echo $report_data['capital_percentage']; ?>%</span>
                    <span class="total-value">-$<?php echo number_format($report_data['capital_deduction'], 2); ?></span>
                </div>
                <div class="total-row">
                    <span class="total-label">TOTAL PAYMENT</span>
                    <span class="total-value">$<?php echo number_format($report_data['total_payment'], 2); ?></span>
                </div>
            </div>
        </div>
        
        <!-- ACTIONS SECTION -->
        <div class="actions-section">
            <div class="section-title">Acciones Disponibles</div>
            
            <?php if (!isset($report_data['report_id'])): ?>
            <!-- GUARDAR REPORTE -->
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="save_report">
                <button type="submit" class="btn btn-success">
                    💾 Guardar Reporte
                </button>
            </form>
            <?php else: ?>
            <!-- REPORTE YA GUARDADO -->
            <div style="background: #f0fff4; padding: 1rem; border-radius: 8px; border: 1px solid #c6f6d5; margin-bottom: 1rem;">
                <strong>✅ Reporte guardado exitosamente</strong><br>
                ID del reporte: #<?php echo $report_data['report_id']; ?>
            </div>
            <?php endif; ?>
            
            <!-- DESCARGAR PDF -->
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="download_pdf">
                <button type="submit" class="btn btn-primary">
                    📄 Descargar PDF
                </button>
            </form>
            
            <!-- PREVIEW DEL REPORTE -->
            <button type="button" class="btn btn-outline" onclick="openPreview()">
                👁️ Vista Previa
            </button>
            
            <!-- VOLVER AL VOUCHER -->
            <a href="process-voucher.php?id=<?php echo $voucher_id; ?>" class="btn btn-outline">
                ← Volver al Voucher
            </a>
        </div>
        
        <?php endif; ?>
        
        <!-- INFORMACIÓN ADICIONAL -->
        <div style="background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); padding: 2rem;">
            <h3 style="color: #2d3748; margin-bottom: 1rem;">Información del Reporte</h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
                <div>
                    <h4 style="color: #4a5568; margin-bottom: 0.5rem;">Voucher Original</h4>
                    <p style="color: #718096; margin-bottom: 0.5rem;">
                        <strong>Archivo:</strong> <?php echo htmlspecialchars($voucher_info['original_filename'] ?? 'N/A'); ?><br>
                        <strong>Procesado:</strong> <?php echo isset($voucher_info['updated_at']) ? date('d/m/Y H:i', strtotime($voucher_info['updated_at'])) : 'N/A'; ?>
                    </p>
                </div>
                
                <div>
                    <h4 style="color: #4a5568; margin-bottom: 0.5rem;">Empresa</h4>
                    <p style="color: #718096; margin-bottom: 0.5rem;">
                        <strong>Nombre Legal:</strong> <?php echo htmlspecialchars($company_info['legal_name'] ?? $company_info['name']); ?><br>
                        <strong>Identificador:</strong> <?php echo htmlspecialchars($company_info['identifier']); ?><br>
                        <strong>Porcentaje Capital:</strong> <?php echo $company_info['capital_percentage']; ?>%
                    </p>
                </div>
            </div>
        </div>
        
    </div>
    
    <!-- MODAL PARA VISTA PREVIA -->
    <div id="previewModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: white; border-radius: 10px; max-width: 90%; max-height: 90%; overflow: auto; position: relative;">
            <div style="padding: 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: #2d3748;">Vista Previa del Reporte</h3>
                <button onclick="closePreview()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #718096;">×</button>
            </div>
            <iframe id="previewFrame" style="width: 800px; height: 600px; border: none;"></iframe>
        </div>
    </div>
    
    <script>
        function openPreview() {
            // Generar URL para vista previa
            const previewUrl = 'generate-report.php?voucher_id=<?php echo $voucher_id; ?>&company_id=<?php echo $company_id; ?>&preview=1';
            
            document.getElementById('previewFrame').src = previewUrl;
            document.getElementById('previewModal').style.display = 'flex';
        }
        
        function closePreview() {
            document.getElementById('previewModal').style.display = 'none';
            document.getElementById('previewFrame').src = '';
        }
        
        // Cerrar modal con ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePreview();
            }
        });
        
        // Cerrar modal haciendo clic fuera
        document.getElementById('previewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePreview();
            }
        });
    </script>
    
</body>
</html>

<?php
        // MANEJO DE VISTA PREVIA
        if (isset($_GET['preview']) && $_GET['preview'] == '1' && $report_data) {
            echo generateReportHTML($report_data);
            exit;
        }
        
        /**
         * Función para generar HTML del reporte (para vista previa web) - FORMATO CORRECTO
         */
        function generateReportHTML($data) {
            ob_start();
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Payment Report - <?php echo $data['company_info']['name']; ?></title>
                <style>
                    body { 
                        font-family: Arial, sans-serif; 
                        margin: 20px; 
                        background: white; 
                        color: #000;
                        font-size: 12px;
                    }
                    
                    .header { 
                        text-align: center; 
                        margin-bottom: 25px;
                    }
                    
                    .company-name { 
                        font-size: 18px; 
                        font-weight: bold; 
                        margin-bottom: 5px;
                        color: #000;
                    }
                    
                    .report-title { 
                        font-size: 16px; 
                        font-weight: normal; 
                        margin-bottom: 15px;
                        color: #8A8A8A;
                    }
                    
                    .client-name {
                        font-size: 14px;
                        font-weight: bold;
                        text-align: left;
                        margin-bottom: 20px;
                        color: #000;
                    }
                    
                    .payment-info { 
                        margin: 20px 0; 
                        background: #f9f9f9; 
                        padding: 15px; 
                        border-radius: 5px; 
                    }
                    
                    .info-row { 
                        margin: 8px 0; 
                        display: flex; 
                        justify-content: space-between;
                    }
                    
                    .info-label {
                        font-weight: bold;
                        width: 150px;
                    }
                    
                    .trips-table { 
                        width: 100%; 
                        border-collapse: collapse; 
                        margin: 20px 0; 
                    }
                    
                    .trips-table th, .trips-table td { 
                        border: 1px solid #FFFFFF; 
                        padding: 10px; 
                        text-align: left; 
                    }
                    
                    .trips-table th { 
                        background-color: #000000; 
                        color: #FFFFFF;
                        font-weight: bold; 
                        text-align: center;
                    }
                    
                    .trips-table tbody tr:nth-child(odd) { 
                        background-color: #A5A5A5;
                    }
                    
                    .trips-table tbody tr:nth-child(even) { 
                        background-color: #D8D8D8;
                    }
                    
                    .amount-cell { 
                        text-align: right; 
                    }
                    
                    .date-cell {
                        text-align: center;
                    }
                    
                    .totals { 
                        margin-top: 20px; 
                        text-align: right;
                    }
                    
                    .total-row { 
                        margin: 8px 0; 
                        display: flex;
                        justify-content: flex-end;
                        gap: 20px;
                    }
                    
                    .final-total { 
                        font-weight: bold; 
                        font-size: 16px; 
                        margin-top: 8px;
                    }
                    
                    .thank-you {
                        font-style: italic;
                        font-size: 12px;
                        text-align: center;
                        margin-top: 15px;
                        color: #000;
                    }
                    
                    .legal-notice {
                        margin-top: 25px;
                        font-size: 10px;
                        color: #000;
                        line-height: 1.4;
                        text-align: center;
                        page-break-before: always;
                        padding-top: 50px;
                    }
                    
                    .legal-notice a, .legal-notice .email {
                        color: #0462C0;
                        text-decoration: none;
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <div class="company-name">CAPITAL TRANSPORT LLP</div>
                    <div class="report-title">PAYMENT INFORMATION</div>
                </div>
                
                <div class="client-name"><?php echo strtoupper($data['company_info']['name']); ?></div>
                
                <div class="payment-info">
                    <div class="info-row">
                        <span class="info-label">Payment No:</span>
                        <span><?php echo $data['payment_no']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Week Start:</span>
                        <span><?php echo date('m/d/Y', strtotime($data['week_start'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Week End:</span>
                        <span><?php echo date('m/d/Y', strtotime($data['week_end'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Payment Date:</span>
                        <span><?php echo date('m/d/Y', strtotime($data['payment_date'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Payment Total:</span>
                        <span><strong>$ <?php echo number_format($data['total_payment'], 2); ?></strong></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">YTD:</span>
                        <span><strong>$ <?php echo number_format($data['ytd_amount'], 2); ?></strong></span>
                    </div>
                </div>
                
                <table class="trips-table">
                    <thead>
                        <tr>
                            <th>Invoice Date</th>
                            <th>Location</th>
                            <th>Ticket No.</th>
                            <th>Vehicle ID</th>
                            <th>Rate</th>
                            <th>Quantity/TON</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['trips_data'] as $trip): ?>
                        <tr>
                            <td class="date-cell"><?php echo date('m/d/Y', strtotime($trip['trip_date'])); ?></td>
                            <td><?php echo htmlspecialchars($trip['location']); ?></td>
                            <td><?php echo htmlspecialchars($trip['ticket_number']); ?></td>
                            <td><?php echo htmlspecialchars($trip['vehicle_number']); ?></td>
                            <td class="amount-cell"><?php echo number_format($trip['haul_rate'], 2); ?></td>
                            <td class="amount-cell"><?php echo number_format($trip['quantity'], 2); ?></td>
                            <td class="amount-cell"><?php echo number_format($trip['amount'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="totals">
                    <div class="total-row">
                        <span>SUBTOTAL</span>
                        <span>$ <?php echo number_format($data['subtotal'], 2); ?></span>
                    </div>
                    <div class="total-row">
                        <span>CAPITAL'S <?php echo $data['capital_percentage']; ?>%</span>
                        <span>$ <?php echo number_format($data['capital_deduction'], 2); ?></span>
                    </div>
                    <div class="total-row final-total">
                        <span>TOTAL PAYMENT</span>
                        <span>$ <?php echo number_format($data['total_payment'], 2); ?></span>
                    </div>
                </div>
                
                <div class="thank-you">Thank you!</div>
                
                <div class="legal-notice">
                    <p><strong>Please confirm receipt.</strong> If there is a claim, it must be within the next 72 hours, otherwise it will no longer be possible to make any adjustment. For any inquiries, please send email to <span class="email">info@capitaltransportllp.com</span> or call <strong>720-319-4201</strong></p>
                </div>
            </body>
            </html>
            <?php
            return ob_get_clean();
        }
        ?>