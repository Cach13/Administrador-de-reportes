<?php
/**
 * API para generar reportes Capital Transport - SOLO PDF
 * Ruta: /api/generate-capital-report.php
 */

ob_clean();
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

session_start();

try {
    // Verificar autenticación
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Usuario no autenticado');
    }
    
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    // Obtener parámetros
    $voucher_id = $_POST['voucher_id'] ?? null;
    $company_id = $_POST['company_id'] ?? null;
    $format = $_POST['format'] ?? 'pdf'; // Solo PDF ahora
    $action = $_POST['action'] ?? 'generate';
    
    // Fechas (usar las del POST o calcular automáticamente)
    $week_start = $_POST['week_start'] ?? null;
    $week_end = $_POST['week_end'] ?? null;
    $payment_date = $_POST['payment_date'] ?? null;
    $ytd_amount = floatval($_POST['ytd_amount'] ?? 0);
    
    if (!$voucher_id || !$company_id) {
        throw new Exception('Se requieren voucher_id y company_id');
    }
    
    // Incluir dependencias
    require_once '../config/config.php';
    require_once '../classes/Database.php';
    require_once '../classes/CapitalTransportReportGenerator.php';
    
    $db = Database::getInstance();
    
    // Verificar que existen los datos
    $voucher = $db->fetch("SELECT * FROM vouchers WHERE id = ?", [$voucher_id]);
    if (!$voucher) {
        throw new Exception('Voucher no encontrado');
    }
    
    $company = $db->fetch("SELECT * FROM companies WHERE id = ?", [$company_id]);
    if (!$company) {
        throw new Exception('Empresa no encontrada');
    }
    
    // Verificar que hay trips
    $trips_count = $db->fetch("
        SELECT COUNT(*) as total 
        FROM trips 
        WHERE voucher_id = ? AND company_id = ?
    ", [$voucher_id, $company_id]);
    
    if ($trips_count['total'] == 0) {
        throw new Exception('No se encontraron viajes para esta empresa en este voucher');
    }
    
    // Crear generador usando la clase actualizada
    $generator = new CapitalTransportReportGenerator($company_id, $voucher_id);
    
    // Validar antes de generar
    $validation = $generator->validateReportGeneration();
    if ($validation !== true) {
        throw new Exception('Errores de validación: ' . implode(', ', $validation));
    }
    
    // Si no se proporcionaron fechas, calcularlas automáticamente
    if (!$week_start || !$week_end) {
        $trips_dates = $db->fetch("
            SELECT MIN(trip_date) as first_date, MAX(trip_date) as last_date 
            FROM trips 
            WHERE voucher_id = ? AND company_id = ?
        ", [$voucher_id, $company_id]);
        
        $first_date = $trips_dates['first_date'];
        $last_date = $trips_dates['last_date'];
        
        // Calcular semana basada en las fechas de trips
        $week_start = date('Y-m-d', strtotime('monday this week', strtotime($first_date)));
        $week_end = date('Y-m-d', strtotime('sunday this week', strtotime($last_date)));
    }
    
    // Payment date por defecto: una semana después del week_end
    if (!$payment_date) {
        $payment_date = date('Y-m-d', strtotime($week_end . ' +7 days'));
    }
    
    // Calcular YTD si no se proporcionó
    if ($ytd_amount <= 0) {
        $ytd_amount = CapitalTransportReportGenerator::calculateYTD($company_id, $payment_date);
    }
    
    if ($action === 'download') {
        // DESCARGA DIRECTA DE ARCHIVO EXISTENTE
        $report = $db->fetch("
            SELECT * FROM reports 
            WHERE company_id = ? AND voucher_id = ? 
            ORDER BY id DESC LIMIT 1
        ", [$company_id, $voucher_id]);
        
        if (!$report || !file_exists($report['file_path'])) {
            throw new Exception('Archivo de reporte no encontrado');
        }
        
        $file_path = $report['file_path'];
        $filename = basename($file_path);
        
        // Headers para descarga PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        readfile($file_path);
        exit;
        
    } else {
        // GENERAR NUEVO REPORTE PDF
        $result = $generator->generateReport(
            $week_start,
            $week_end, 
            $payment_date,
            $ytd_amount
        );
        
        if (!$result['success']) {
            throw new Exception('Error en la generación del reporte: ' . ($result['message'] ?? 'Error desconocido'));
        }
        
        // Obtener información del archivo PDF generado
        $pdf_file = $result['pdf_file'];
        
        if (!$pdf_file || !file_exists($pdf_file['file_path'])) {
            throw new Exception('No se pudo generar el archivo PDF');
        }
        
        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'message' => 'Reporte PDF generado exitosamente',
            'data' => [
                'report_id' => $result['report_id'],
                'payment_no' => $result['payment_no'],
                'filename' => $pdf_file['filename'],
                'file_path' => $pdf_file['file_path'],
                'file_size' => $pdf_file['file_size'],
                'format' => 'pdf',
                'download_url' => "api/generate-capital-report.php?action=download&voucher_id={$voucher_id}&company_id={$company_id}",
                'direct_download' => "download-report.php?id={$result['report_id']}",
                'capital_data' => $result['capital_data'],
                'trips_count' => $result['trips_count']
            ]
        ]);
    }
    
} catch (Exception $e) {
    error_log("Generate Capital Report Error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

exit;