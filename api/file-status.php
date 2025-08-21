<?php
/**
 * API para obtener estado de archivos y estadísticas
 * Ruta: /api/file-status.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Logger.php';

try {
    // Verificar autenticación
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Usuario no autenticado', 401);
    }
    
    $db = Database::getInstance();
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            $response = getFileList($db);
            break;
            
        case 'status':
            $voucher_id = $_GET['voucher_id'] ?? null;
            if (!$voucher_id) {
                throw new Exception('ID de voucher requerido', 400);
            }
            $response = getFileStatus($db, $voucher_id);
            break;
            
        case 'statistics':
            $response = getStatistics($db);
            break;
            
        case 'recent':
            $limit = $_GET['limit'] ?? 10;
            $response = getRecentFiles($db, $limit);
            break;
            
        default:
            throw new Exception('Acción no válida', 400);
    }
    
    http_response_code(200);
    echo json_encode($response);
    
} catch (Exception $e) {
    $error_code = $e->getCode() ?: 500;
    http_response_code($error_code);
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'error_code' => $error_code
    ]);
}

/**
 * Obtener lista completa de archivos
 */
function getFileList($db) {
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 20;
    $filter_type = $_GET['type'] ?? '';
    $filter_status = $_GET['status'] ?? '';
    
    $offset = ($page - 1) * $limit;
    
    // Construir query con filtros
    $where_conditions = [];
    $params = [];
    
    if ($filter_type) {
        $where_conditions[] = "v.file_type = ?";
        $params[] = $filter_type;
    }
    
    if ($filter_status) {
        $where_conditions[] = "v.status = ?";
        $params[] = $filter_status;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Query principal
    $sql = "
        SELECT 
            v.id,
            v.voucher_number,
            v.original_filename,
            v.file_type,
            v.file_format,
            v.file_size,
            v.upload_date,
            v.status,
            v.data_quality_score,
            v.total_trips,
            v.total_companies,
            v.total_amount,
            v.processing_time_seconds,
            u.full_name as uploaded_by_name,
            COUNT(t.id) as trips_count,
            COUNT(DISTINCT t.company_id) as companies_count
        FROM vouchers v
        LEFT JOIN users u ON v.uploaded_by = u.id
        LEFT JOIN trips t ON v.id = t.voucher_id
        {$where_clause}
        GROUP BY v.id
        ORDER BY v.upload_date DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $files = $db->fetchAll($sql, $params);
    
    // Contar total para paginación
    $count_sql = "SELECT COUNT(*) as total FROM vouchers v {$where_clause}";
    $total_params = array_slice($params, 0, -2); // Remover limit y offset
    $total_result = $db->fetch($count_sql, $total_params);
    $total_files = $total_result['total'];
    
    // Formatear datos
    foreach ($files as &$file) {
        $file['file_size_formatted'] = formatFileSize($file['file_size']);
        $file['upload_date_formatted'] = date('d/m/Y H:i', strtotime($file['upload_date']));
        $file['total_amount_formatted'] = number_format($file['total_amount'], 2);
        $file['processing_time_formatted'] = formatProcessingTime($file['processing_time_seconds']);
        $file['quality_percentage'] = round($file['data_quality_score'] * 100);
        
        // Estado traducido
        $file['status_text'] = getStatusText($file['status']);
        $file['status_color'] = getStatusColor($file['status']);
    }
    
    return [
        'success' => true,
        'data' => [
            'files' => $files,
            'pagination' => [
                'current_page' => (int)$page,
                'per_page' => (int)$limit,
                'total' => (int)$total_files,
                'total_pages' => ceil($total_files / $limit)
            ]
        ]
    ];
}

/**
 * Obtener estado específico de un archivo
 */
function getFileStatus($db, $voucher_id) {
    // Información del voucher
    $voucher = $db->fetch("
        SELECT v.*, u.full_name as uploaded_by_name
        FROM vouchers v
        LEFT JOIN users u ON v.uploaded_by = u.id
        WHERE v.id = ?
    ", [$voucher_id]);
    
    if (!$voucher) {
        throw new Exception('Archivo no encontrado', 404);
    }
    
    // Logs de procesamiento
    $processing_logs = $db->fetchAll("
        SELECT * FROM file_processing_logs 
        WHERE voucher_id = ? 
        ORDER BY created_at ASC
    ", [$voucher_id]);
    
    // Errores de validación
    $validation_errors = $db->fetchAll("
        SELECT * FROM data_validation_errors 
        WHERE voucher_id = ? 
        ORDER BY row_number ASC
    ", [$voucher_id]);
    
    // Estadísticas de trips
    $trip_stats = $db->fetch("
        SELECT 
            COUNT(*) as total_trips,
            COUNT(DISTINCT company_id) as unique_companies,
            SUM(total_amount) as total_amount,
            AVG(extraction_confidence) as avg_confidence,
            COUNT(CASE WHEN manual_review_required = 1 THEN 1 END) as trips_needing_review
        FROM trips 
        WHERE voucher_id = ?
    ", [$voucher_id]);
    
    return [
        'success' => true,
        'data' => [
            'voucher' => $voucher,
            'processing_logs' => $processing_logs,
            'validation_errors' => $validation_errors,
            'trip_statistics' => $trip_stats,
            'status_info' => [
                'text' => getStatusText($voucher['status']),
                'color' => getStatusColor($voucher['status']),
                'can_process' => in_array($voucher['status'], ['uploaded', 'error']),
                'can_reprocess' => $voucher['status'] === 'processed'
            ]
        ]
    ];
}

/**
 * Obtener estadísticas generales
 */
function getStatistics($db) {
    // Estadísticas por tipo de archivo
    $type_stats = $db->fetchAll("
        SELECT 
            file_type,
            COUNT(*) as total_files,
            COUNT(CASE WHEN status = 'processed' THEN 1 END) as processed_files,
            COUNT(CASE WHEN status = 'error' THEN 1 END) as failed_files,
            AVG(data_quality_score) as avg_quality,
            SUM(total_trips) as total_trips,
            SUM(total_amount) as total_amount
        FROM vouchers 
        WHERE upload_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY file_type
    ");
    
    // Estadísticas por estado
    $status_stats = $db->fetchAll("
        SELECT 
            status,
            COUNT(*) as count,
            ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM vouchers)), 2) as percentage
        FROM vouchers 
        GROUP BY status
    ");
    
    // Actividad reciente (últimos 7 días)
    $recent_activity = $db->fetchAll("
        SELECT 
            DATE(upload_date) as date,
            COUNT(*) as files_uploaded,
            COUNT(CASE WHEN status = 'processed' THEN 1 END) as files_processed
        FROM vouchers 
        WHERE upload_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(upload_date)
        ORDER BY date DESC
    ");
    
    // Top empresas por cantidad de viajes
    $top_companies = $db->fetchAll("
        SELECT 
            c.name,
            COUNT(t.id) as total_trips,
            SUM(t.total_amount) as total_amount
        FROM companies c
        JOIN trips t ON c.id = t.company_id
        JOIN vouchers v ON t.voucher_id = v.id
        WHERE v.upload_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY c.id, c.name
        ORDER BY total_trips DESC
        LIMIT 10
    ");
    
    return [
        'success' => true,
        'data' => [
            'file_type_stats' => $type_stats,
            'status_distribution' => $status_stats,
            'recent_activity' => $recent_activity,
            'top_companies' => $top_companies,
            'summary' => [
                'total_files' => array_sum(array_column($type_stats, 'total_files')),
                'processed_files' => array_sum(array_column($type_stats, 'processed_files')),
                'total_trips' => array_sum(array_column($type_stats, 'total_trips')),
                'total_amount' => array_sum(array_column($type_stats, 'total_amount'))
            ]
        ]
    ];
}

/**
 * Obtener archivos recientes
 */
function getRecentFiles($db, $limit) {
    $files = $db->fetchAll("
        SELECT 
            v.id,
            v.voucher_number,
            v.original_filename,
            v.file_type,
            v.status,
            v.upload_date,
            v.total_trips,
            v.total_amount,
            u.full_name as uploaded_by_name
        FROM vouchers v
        LEFT JOIN users u ON v.uploaded_by = u.id
        ORDER BY v.upload_date DESC
        LIMIT ?
    ", [$limit]);
    
    // Formatear datos
    foreach ($files as &$file) {
        $file['upload_date_formatted'] = date('d/m/Y H:i', strtotime($file['upload_date']));
        $file['status_text'] = getStatusText($file['status']);
        $file['status_color'] = getStatusColor($file['status']);
    }
    
    return [
        'success' => true,
        'data' => $files
    ];
}

/**
 * Utilidades
 */

function formatProcessingTime($seconds) {
    if (!$seconds) return '-';
    
    if ($seconds < 60) {
        return "{$seconds}s";
    } else {
        $minutes = floor($seconds / 60);
        $remaining_seconds = $seconds % 60;
        return "{$minutes}m {$remaining_seconds}s";
    }
}

function getStatusText($status) {
    $status_map = [
        'uploaded' => 'Subido',
        'processing' => 'Procesando',
        'processed' => 'Procesado',
        'error' => 'Error'
    ];
    
    return $status_map[$status] ?? $status;
}

function getStatusColor($status) {
    $color_map = [
        'uploaded' => '#6b7280',
        'processing' => '#f59e0b',
        'processed' => '#10b981',
        'error' => '#ef4444'
    ];
    
    return $color_map[$status] ?? '#6b7280';
}
?>