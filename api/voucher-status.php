<?php
/**
 * Voucher Status API - REEMPLAZO de file-status.php
 * Compatible con la nueva estructura de BD
 * Ruta: /api/voucher-status.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Usuario no autenticado'
    ]);
    exit;
}

require_once '../config/config.php';
require_once '../classes/Database.php';

try {
    $db = Database::getInstance();
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            $response = getVouchersList($db);
            break;
            
        case 'stats':
            $response = getSystemStats($db);
            break;
            
        case 'recent':
            $limit = intval($_GET['limit'] ?? 5);
            $response = getRecentVouchers($db, $limit);
            break;
            
        case 'detail':
            $voucher_id = intval($_GET['voucher_id'] ?? 0);
            if (!$voucher_id) {
                throw new Exception('ID de voucher requerido');
            }
            $response = getVoucherDetail($db, $voucher_id);
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $response,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Obtener lista de vouchers con paginación
 */
function getVouchersList($db) {
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 10);
    $status = $_GET['status'] ?? '';
    
    $offset = ($page - 1) * $limit;
    
    // Construir WHERE clause
    $where_conditions = [];
    $params = [];
    
    if ($status && in_array($status, ['uploaded', 'processing', 'processed', 'error'])) {
        $where_conditions[] = "v.status = ?";
        $params[] = $status;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Query principal
    $sql = "
        SELECT 
            v.id,
            v.voucher_number,
            v.original_filename,
            v.status,
            v.file_format,
            v.file_size,
            v.total_rows_found,
            v.valid_rows_extracted,
            v.rows_with_errors,
            v.extraction_confidence,
            v.upload_date,
            v.processing_completed_at,
            u.full_name as uploaded_by,
            COUNT(t.id) as trips_count,
            COUNT(DISTINCT t.company_id) as companies_found,
            COALESCE(SUM(t.amount), 0) as total_amount
        FROM vouchers v
        LEFT JOIN users u ON v.uploaded_by = u.id
        LEFT JOIN trips t ON v.id = t.voucher_id
        {$where_clause}
        GROUP BY v.id, v.voucher_number, v.original_filename, v.status, 
                 v.file_format, v.file_size, v.total_rows_found, 
                 v.valid_rows_extracted, v.rows_with_errors, 
                 v.extraction_confidence, v.upload_date, 
                 v.processing_completed_at, u.full_name
        ORDER BY v.upload_date DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $vouchers = $db->fetchAll($sql, $params);
    
    // Contar total para paginación
    $count_sql = "
        SELECT COUNT(DISTINCT v.id) as total
        FROM vouchers v
        LEFT JOIN users u ON v.uploaded_by = u.id
        {$where_clause}
    ";
    
    $count_params = array_slice($params, 0, count($params) - 2); // Remover LIMIT y OFFSET
    $total = $db->fetch($count_sql, $count_params)['total'];
    
    // Formatear datos
    foreach ($vouchers as &$voucher) {
        $voucher['file_size_formatted'] = formatFileSize($voucher['file_size']);
        $voucher['extraction_confidence_percent'] = round($voucher['extraction_confidence'] * 100, 1);
        $voucher['status_label'] = getStatusLabel($voucher['status']);
        $voucher['upload_date_formatted'] = date('d/m/Y H:i', strtotime($voucher['upload_date']));
    }
    
    return [
        'vouchers' => $vouchers,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'total_records' => intval($total),
            'per_page' => $limit
        ]
    ];
}

/**
 * Obtener estadísticas del sistema
 */
function getSystemStats($db) {
    // Estadísticas de vouchers
    $voucher_stats = $db->fetchAll("
        SELECT 
            status,
            COUNT(*) as count
        FROM vouchers 
        GROUP BY status
    ");
    
    // Estadísticas generales
    $general_stats = [
        'total_vouchers' => $db->fetch("SELECT COUNT(*) as count FROM vouchers")['count'],
        'total_trips' => $db->fetch("SELECT COUNT(*) as count FROM trips")['count'],
        'total_companies' => $db->fetch("SELECT COUNT(DISTINCT company_id) as count FROM trips")['count'],
        'total_amount' => $db->fetch("SELECT COALESCE(SUM(amount), 0) as total FROM trips")['total'],
        'active_companies' => $db->fetch("SELECT COUNT(*) as count FROM companies WHERE is_active = 1")['count'],
        'total_reports' => $db->fetch("SELECT COUNT(*) as count FROM reports")['count']
    ];
    
    // Formatear estadísticas por status
    $status_stats = [];
    foreach ($voucher_stats as $stat) {
        $status_stats[$stat['status']] = intval($stat['count']);
    }
    
    // Asegurar que todos los status estén presentes
    $all_statuses = ['uploaded', 'processing', 'processed', 'error'];
    foreach ($all_statuses as $status) {
        if (!isset($status_stats[$status])) {
            $status_stats[$status] = 0;
        }
    }
    
    // Estadísticas de actividad reciente (última semana)
    $recent_activity = $db->fetch("
        SELECT COUNT(*) as uploads_this_week
        FROM vouchers 
        WHERE upload_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    
    return [
        'general' => $general_stats,
        'by_status' => $status_stats,
        'recent_activity' => $recent_activity
    ];
}

/**
 * Obtener vouchers recientes
 */
function getRecentVouchers($db, $limit = 5) {
    $sql = "
        SELECT 
            v.id,
            v.voucher_number,
            v.original_filename,
            v.status,
            v.file_format,
            v.valid_rows_extracted,
            v.upload_date,
            u.full_name as uploaded_by,
            COUNT(t.id) as trips_count
        FROM vouchers v
        LEFT JOIN users u ON v.uploaded_by = u.id
        LEFT JOIN trips t ON v.id = t.voucher_id
        GROUP BY v.id, v.voucher_number, v.original_filename, v.status,
                 v.file_format, v.valid_rows_extracted, v.upload_date, u.full_name
        ORDER BY v.upload_date DESC
        LIMIT ?
    ";
    
    $vouchers = $db->fetchAll($sql, [$limit]);
    
    // Formatear datos
    foreach ($vouchers as &$voucher) {
        $voucher['status_label'] = getStatusLabel($voucher['status']);
        $voucher['upload_date_formatted'] = date('d/m/Y H:i', strtotime($voucher['upload_date']));
        $voucher['time_ago'] = timeAgo($voucher['upload_date']);
    }
    
    return $vouchers;
}

/**
 * Obtener detalle completo de un voucher
 */
function getVoucherDetail($db, $voucher_id) {
    // Información del voucher
    $voucher = $db->fetch("
        SELECT 
            v.*,
            u.full_name as uploaded_by_name,
            u.username as uploaded_by_username
        FROM vouchers v
        LEFT JOIN users u ON v.uploaded_by = u.id
        WHERE v.id = ?
    ", [$voucher_id]);
    
    if (!$voucher) {
        throw new Exception('Voucher no encontrado');
    }
    
    // Trips asociados agrupados por empresa
    $trips_by_company = $db->fetchAll("
        SELECT 
            c.id as company_id,
            c.name as company_name,
            c.identifier as company_identifier,
            COUNT(t.id) as trips_count,
            SUM(t.amount) as total_amount,
            MIN(t.trip_date) as first_trip_date,
            MAX(t.trip_date) as last_trip_date
        FROM trips t
        LEFT JOIN companies c ON t.company_id = c.id
        WHERE t.voucher_id = ?
        GROUP BY c.id, c.name, c.identifier
        ORDER BY trips_count DESC
    ", [$voucher_id]);
    
    // Formatear datos del voucher
    $voucher['file_size_formatted'] = formatFileSize($voucher['file_size']);
    $voucher['extraction_confidence_percent'] = round($voucher['extraction_confidence'] * 100, 1);
    $voucher['status_label'] = getStatusLabel($voucher['status']);
    $voucher['upload_date_formatted'] = date('d/m/Y H:i:s', strtotime($voucher['upload_date']));
    
    return [
        'voucher' => $voucher,
        'companies' => $trips_by_company,
        'summary' => [
            'total_companies' => count($trips_by_company),
            'total_trips' => array_sum(array_column($trips_by_company, 'trips_count')),
            'total_amount' => array_sum(array_column($trips_by_company, 'total_amount'))
        ]
    ];
}

/**
 * Formatear tamaño de archivo
 */
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Obtener etiqueta de status
 */
function getStatusLabel($status) {
    $labels = [
        'uploaded' => 'Subido',
        'processing' => 'Procesando',
        'processed' => 'Procesado',
        'error' => 'Error'
    ];
    
    return $labels[$status] ?? $status;
}

/**
 * Calcular tiempo transcurrido
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Hace menos de 1 minuto';
    if ($time < 3600) return 'Hace ' . floor($time/60) . ' minutos';
    if ($time < 86400) return 'Hace ' . floor($time/3600) . ' horas';
    if ($time < 2592000) return 'Hace ' . floor($time/86400) . ' días';
    
    return date('d/m/Y', strtotime($datetime));
}
?>