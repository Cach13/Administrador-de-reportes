<?php
/**
 * API para eliminar archivos y sus datos relacionados
 * Ruta: /api/delete-file.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE');
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
    
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        throw new Exception('Método no permitido', 405);
    }
    
    // Obtener datos de entrada
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['voucher_id'])) {
        throw new Exception('ID de voucher requerido', 400);
    }
    
    $voucher_id = intval($input['voucher_id']);
    $force_delete = $input['force_delete'] ?? false;
    
    // Verificar permisos
    if (!hasPermission('manage_users') && !$force_delete) {
        // Solo admins pueden eliminar, operadores solo si es su propio archivo
        $db = Database::getInstance();
        $voucher = $db->fetch("SELECT uploaded_by FROM vouchers WHERE id = ?", [$voucher_id]);
        
        if (!$voucher || $voucher['uploaded_by'] != $_SESSION['user_id']) {
            throw new Exception('No tienes permisos para eliminar este archivo', 403);
        }
    }
    
    // Eliminar archivo y datos
    $result = deleteVoucherComplete($voucher_id);
    
    if ($result['success']) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Archivo eliminado correctamente',
            'data' => $result['data']
        ]);
    } else {
        throw new Exception($result['message'], 500);
    }
    
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
 * Eliminar voucher completo con todos sus datos relacionados
 */
function deleteVoucherComplete($voucher_id) {
    $db = Database::getInstance();
    $logger = new Logger();
    
    try {
        // Obtener información del voucher
        $voucher = $db->fetch("SELECT * FROM vouchers WHERE id = ?", [$voucher_id]);
        
        if (!$voucher) {
            return [
                'success' => false,
                'message' => 'Archivo no encontrado'
            ];
        }
        
        // Iniciar transacción
        $db->beginTransaction();
        
        $deleted_data = [
            'voucher_id' => $voucher_id,
            'filename' => $voucher['original_filename'],
            'file_type' => $voucher['file_type']
        ];
        
        // 1. Eliminar viajes relacionados
        $trips_deleted = $db->delete('trips', 'voucher_id = ?', [$voucher_id]);
        $deleted_data['trips_deleted'] = $trips_deleted;
        
        // 2. Eliminar errores de validación
        $errors_deleted = $db->delete('data_validation_errors', 'voucher_id = ?', [$voucher_id]);
        $deleted_data['validation_errors_deleted'] = $errors_deleted;
        
        // 3. Eliminar logs de procesamiento
        $logs_deleted = $db->delete('file_processing_logs', 'voucher_id = ?', [$voucher_id]);
        $deleted_data['processing_logs_deleted'] = $logs_deleted;
        
        // 4. Eliminar reportes generados (si existen)
        $reports_deleted = $db->delete('reports', 'voucher_id = ?', [$voucher_id]);
        $deleted_data['reports_deleted'] = $reports_deleted;
        
        // 5. Eliminar archivo físico
        $file_deleted = false;
        if (file_exists($voucher['file_path'])) {
            $file_deleted = @unlink($voucher['file_path']);
            
            if (!$file_deleted) {
                // Log warning pero no fallar la operación
                $logger->logError($_SESSION['user_id'], 
                    "No se pudo eliminar archivo físico: {$voucher['file_path']}");
            }
        }
        $deleted_data['physical_file_deleted'] = $file_deleted;
        
        // 6. Eliminar registro del voucher
        $voucher_deleted = $db->delete('vouchers', 'id = ?', [$voucher_id]);
        
        if (!$voucher_deleted) {
            throw new Exception('Error eliminando registro del voucher');
        }
        
        // Confirmar transacción
        $db->commit();
        
        // Log de la eliminación
        $logger->logCrud($_SESSION['user_id'], 'DELETE', 'vouchers', $voucher_id, 
            "Archivo eliminado completamente: {$voucher['original_filename']}");
        
        return [
            'success' => true,
            'message' => 'Archivo eliminado correctamente',
            'data' => $deleted_data
        ];
        
    } catch (Exception $e) {
        // Revertir transacción
        $db->rollback();
        
        $logger->logError($_SESSION['user_id'], 
            "Error eliminando voucher {$voucher_id}: " . $e->getMessage());
        
        return [
            'success' => false,
            'message' => 'Error eliminando archivo: ' . $e->getMessage()
        ];
    }
}

/**
 * Eliminar solo archivo físico (para limpieza)
 */
function deletePhysicalFileOnly($voucher_id) {
    $db = Database::getInstance();
    
    try {
        $voucher = $db->fetch("SELECT file_path, original_filename FROM vouchers WHERE id = ?", [$voucher_id]);
        
        if (!$voucher) {
            return false;
        }
        
        if (file_exists($voucher['file_path'])) {
            return @unlink($voucher['file_path']);
        }
        
        return true; // El archivo ya no existe
        
    } catch (Exception $e) {
        error_log("Error eliminando archivo físico: " . $e->getMessage());
        return false;
    }
}

/**
 * Limpiar archivos huérfanos (archivos sin registro en BD)
 */
function cleanupOrphanedFiles() {
    $cleaned = [
        'pdf' => 0,
        'excel' => 0,
        'total_size' => 0
    ];
    
    $upload_dirs = [
        'pdf' => UPLOAD_PATH . 'pdf/',
        'excel' => UPLOAD_PATH . 'excel/',
        'processed' => UPLOAD_PATH . 'processed/'
    ];
    
    $db = Database::getInstance();
    
    foreach ($upload_dirs as $type => $dir) {
        if (!is_dir($dir)) continue;
        
        $files = glob($dir . '*');
        
        foreach ($files as $file_path) {
            if (!is_file($file_path)) continue;
            
            // Verificar si el archivo existe en la BD
            $filename = basename($file_path);
            $voucher = $db->fetch("SELECT id FROM vouchers WHERE file_path LIKE ?", ["%{$filename}"]);
            
            if (!$voucher) {
                // Archivo huérfano - eliminarlo
                $file_size = filesize($file_path);
                
                if (@unlink($file_path)) {
                    $cleaned[$type]++;
                    $cleaned['total_size'] += $file_size;
                }
            }
        }
    }
    
    return $cleaned;
}
?>