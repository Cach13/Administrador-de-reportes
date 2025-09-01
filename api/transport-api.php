<?php
/**
 * API para upload y procesamiento de archivos Martin Marieta
 * Endpoints: /api/upload-voucher.php, /api/process-voucher.php, /api/generate-report.php
 * Ruta: /api/transport-api.php
 */

require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Logger.php';
require_once '../classes/MartinMarietaProcessor.php';
require_once '../classes/CapitalTransportReportGenerator.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

// Verificar autenticación (excepto para endpoints públicos)
if (!isset($_SESSION['user_id']) && !in_array($_GET['action'] ?? '', ['ping'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit();
}

$db = Database::getInstance();
$logger = new Logger();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        
        // UPLOAD VOUCHER
        case 'upload_voucher':
            handleUploadVoucher();
            break;
            
        // PREVIEW VOUCHER DATA
        case 'preview_voucher':
            handlePreviewVoucher();
            break;
            
        // PROCESS VOUCHER
        case 'process_voucher':
            handleProcessVoucher();
            break;
            
        // GENERATE CAPITAL TRANSPORT REPORT
        case 'generate_report':
            handleGenerateReport();
            break;
            
        // GET COMPANIES
        case 'get_companies':
            handleGetCompanies();
            break;
            
        // GET VOUCHER STATUS
        case 'get_voucher_status':
            handleGetVoucherStatus();
            break;
            
        // GET REPORTS
        case 'get_reports':
            handleGetReports();
            break;
            
        // PING
        case 'ping':
            echo json_encode(['success' => true, 'message' => 'API funcionando', 'timestamp' => time()]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    $logger->log($_SESSION['user_id'] ?? null, 'API_ERROR', "Error en API: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * UPLOAD VOUCHER - Subir archivo Martin Marieta
 */
function handleUploadVoucher() {
    global $db, $logger;
    
    if (!isset($_FILES['voucher_file'])) {
        throw new Exception('No se envió ningún archivo');
    }
    
    $file = $_FILES['voucher_file'];
    
    // Validar archivo
    $allowed_types = ['application/pdf', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'];
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Tipo de archivo no permitido: ' . $file['type']);
    }
    
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        throw new Exception('Archivo demasiado grande. Máximo: ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB');
    }
    
    // Determinar formato
    $file_format = 'pdf';
    if (in_array($file['type'], ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])) {
        $file_format = pathinfo($file['name'], PATHINFO_EXTENSION);
    }
    
    // Crear directorio de upload si no existe
    $upload_dir = UPLOAD_PATH . 'vouchers/' . date('Y/m/');
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generar nombre único
    $file_hash = md5_file($file['tmp_name']);
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = 'voucher_' . date('YmdHis') . '_' . substr($file_hash, 0, 8) . '.' . $file_extension;
    $file_path = $upload_dir . $new_filename;
    
    // Mover archivo
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        throw new Exception('Error moviendo archivo al directorio de destino');
    }
    
    // Generar voucher number único
    $voucher_number = 'MM' . date('YmdHis') . rand(100, 999);
    
    // Guardar en BD
    $voucher_data = [
        'voucher_number' => $voucher_number,
        'original_filename' => $file['name'],
        'file_path' => $file_path,
        'file_size' => $file['size'],
        'file_hash' => $file_hash,
        'file_type' => 'martin_marieta',
        'file_format' => $file_format,
        'status' => 'uploaded',
        'uploaded_by' => $_SESSION['user_id']
    ];
    
    $voucher_id = $db->insert('vouchers', $voucher_data);
    
    if (!$voucher_id) {
        // Limpiar archivo si no se pudo guardar en BD
        unlink($file_path);
        throw new Exception('Error guardando voucher en base de datos');
    }
    
    $logger->log($_SESSION['user_id'], 'VOUCHER_UPLOAD', "Voucher subido: {$voucher_number} - {$file['name']}");
    
    echo json_encode([
        'success' => true,
        'voucher_id' => $voucher_id,
        'voucher_number' => $voucher_number,
        'file_format' => $file_format,
        'file_size' => $file['size'],
        'message' => 'Voucher subido exitosamente'
    ]);
}

/**
 * PREVIEW VOUCHER - Previsualizar datos antes de procesamiento
 */
function handlePreviewVoucher() {
    $voucher_id = $_POST['voucher_id'] ?? null;
    
    if (!$voucher_id) {
        throw new Exception('voucher_id requerido');
    }
    
    $processor = new MartinMarietaProcessor($voucher_id);
    $preview_result = $processor->preview(15); // Máximo 15 filas para preview
    
    echo json_encode($preview_result);
}

/**
 * PROCESS VOUCHER - Procesar voucher con empresas seleccionadas
 */
function handleProcessVoucher() {
    global $logger;
    
    $voucher_id = $_POST['voucher_id'] ?? null;
    $selected_companies = $_POST['selected_companies'] ?? [];
    
    if (!$voucher_id) {
        throw new Exception('voucher_id requerido');
    }
    
    if (empty($selected_companies)) {
        throw new Exception('Debe seleccionar al menos una empresa');
    }
    
    // Validar que las empresas existen
    $valid_companies = MartinMarietaProcessor::getAvailableCompanies();
    $valid_identifiers = array_column($valid_companies, 'identifier');
    
    foreach ($selected_companies as $company_id) {
        $company = array_filter($valid_companies, fn($c) => $c['identifier'] === $company_id);
        if (empty($company)) {
            throw new Exception("Empresa no válida: {$company_id}");
        }
    }
    
    // Procesar con empresas seleccionadas
    $processor = new MartinMarietaProcessor($voucher_id, $selected_companies);
    $processing_result = $processor->process();
    
    $logger->log($_SESSION['user_id'], 'VOUCHER_PROCESSED', 
        "Voucher procesado: {$voucher_id} - {$processing_result['saved_trips']} trips guardados");
    
    echo json_encode($processing_result);
}

/**
 * GENERATE REPORT - Generar reporte Capital Transport
 */
function handleGenerateReport() {
    global $logger;
    
    $company_id = $_POST['company_id'] ?? null;
    $voucher_id = $_POST['voucher_id'] ?? null;
    $week_start = $_POST['week_start'] ?? null;
    $week_end = $_POST['week_end'] ?? null;
    $payment_date = $_POST['payment_date'] ?? null;
    $ytd_amount = floatval($_POST['ytd_amount'] ?? 0);
    
    if (!$company_id || !$voucher_id || !$week_start || !$week_end) {
        throw new Exception('company_id, voucher_id, week_start, week_end son requeridos');
    }
    
    // Validar fechas
    if (!strtotime($week_start) || !strtotime($week_end)) {
        throw new Exception('Fechas inválidas');
    }
    
    if (strtotime($week_end) < strtotime($week_start)) {
        throw new Exception('Fecha de fin debe ser posterior a fecha de inicio');
    }
    
    // Generar reporte
    $report_generator = new CapitalTransportReportGenerator($company_id, $voucher_id);
    $report_result = $report_generator->generateReport($week_start, $week_end, $payment_date, $ytd_amount);
    
    $logger->log($_SESSION['user_id'], 'REPORT_GENERATED', 
        "Reporte Capital Transport generado - Payment No: {$report_result['payment_no']}");
    
    echo json_encode($report_result);
}

/**
 * GET COMPANIES - Obtener empresas disponibles
 */
function handleGetCompanies() {
    $companies = MartinMarietaProcessor::getAvailableCompanies();
    
    echo json_encode([
        'success' => true,
        'companies' => $companies
    ]);
}

/**
 * GET VOUCHER STATUS - Obtener estado del voucher
 */
function handleGetVoucherStatus() {
    global $db;
    
    $voucher_id = $_GET['voucher_id'] ?? null;
    
    if (!$voucher_id) {
        throw new Exception('voucher_id requerido');
    }
    
    $voucher = $db->fetch(
        "SELECT 
            v.*,
            u.full_name as uploaded_by_name,
            COUNT(t.id) as trips_count,
            COUNT(DISTINCT t.company_id) as companies_count
         FROM vouchers v
         LEFT JOIN users u ON v.uploaded_by = u.id
         LEFT JOIN trips t ON v.id = t.voucher_id
         WHERE v.id = ?
         GROUP BY v.id",
        [$voucher_id]
    );
    
    if (!$voucher) {
        throw new Exception('Voucher no encontrado');
    }
    
    // Obtener empresas con trips
    $companies_with_trips = $db->fetchAll(
        "SELECT 
            c.id,
            c.name,
            c.identifier,
            c.capital_percentage,
            COUNT(t.id) as trips_count,
            SUM(t.amount) as total_amount
         FROM companies c
         JOIN trips t ON c.id = t.company_id
         WHERE t.voucher_id = ?
         GROUP BY c.id, c.name, c.identifier, c.capital_percentage",
        [$voucher_id]
    );
    
    echo json_encode([
        'success' => true,
        'voucher' => $voucher,
        'companies_with_trips' => $companies_with_trips
    ]);
}

/**
 * GET REPORTS - Obtener reportes generados
 */
function handleGetReports() {
    global $db;
    
    $company_id = $_GET['company_id'] ?? null;
    $year = $_GET['year'] ?? date('Y');
    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);
    
    $where_clause = "WHERE 1=1";
    $params = [];
    
    if ($company_id) {
        $where_clause .= " AND r.company_id = ?";
        $params[] = $company_id;
    }
    
    $where_clause .= " AND YEAR(r.payment_date) = ?";
    $params[] = $year;
    
    $reports = $db->fetchAll(
        "SELECT 
            r.*,
            c.name as company_name,
            c.identifier as company_identifier,
            v.voucher_number,
            v.original_filename,
            u.full_name as generated_by_name
         FROM reports r
         JOIN companies c ON r.company_id = c.id
         JOIN vouchers v ON r.voucher_id = v.id
         LEFT JOIN users u ON r.generated_by = u.id
         {$where_clause}
         ORDER BY r.payment_date DESC, r.payment_no DESC
         LIMIT {$limit} OFFSET {$offset}",
        $params
    );
    
    // Contar total de reportes
    $total_count = $db->fetch(
        "SELECT COUNT(*) as total
         FROM reports r
         JOIN companies c ON r.company_id = c.id
         JOIN vouchers v ON r.voucher_id = v.id
         {$where_clause}",
        $params
    )['total'];
    
    echo json_encode([
        'success' => true,
        'reports' => $reports,
        'total_count' => $total_count,
        'current_page' => floor($offset / $limit) + 1,
        'total_pages' => ceil($total_count / $limit)
    ]);
}
?>