<?php
/**
 * API Process File - USA MartinMarietaProcessor existente
 * Ruta: /api/process-file.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

try {
    // Incluir dependencias
    require_once '../config/config.php';
    require_once '../classes/Database.php';
    require_once '../classes/Logger.php';
    require_once '../classes/MartinMarietaProcessor.php'; // 🔥 USAR ESTA CLASE QUE YA EXISTE
    
    // Verificar autenticación
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Usuario no autenticado');
    }
    
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    // Obtener datos - puede venir como JSON o form data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        // Fallback a $_POST si no es JSON
        $input = $_POST;
    }
    
    if (!isset($input['voucher_id'])) {
        throw new Exception('ID de voucher requerido');
    }
    
    $voucher_id = intval($input['voucher_id']);
    $selected_companies = $input['companies'] ?? [];
    
    if ($voucher_id <= 0) {
        throw new Exception('ID de voucher inválido');
    }
    
    if (empty($selected_companies)) {
        throw new Exception('Debes seleccionar al menos una empresa');
    }
    
    // Verificar que el voucher existe
    $db = Database::getInstance();
    $voucher = $db->fetch("SELECT * FROM vouchers WHERE id = ?", [$voucher_id]);
    
    if (!$voucher) {
        throw new Exception('Voucher no encontrado');
    }
    
    // Verificar estado del voucher
    if ($voucher['status'] === 'processed') {
        $trips_count = $db->fetch("SELECT COUNT(*) as total FROM trips WHERE voucher_id = ?", [$voucher_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Archivo ya fue procesado anteriormente',
            'data' => [
                'voucher_id' => $voucher_id,
                'trips_processed' => $trips_count['total'],
                'status' => 'processed'
            ]
        ]);
        exit();
    }
    
    if ($voucher['status'] === 'processing') {
        throw new Exception('El archivo ya se está procesando');
    }
    
    // Verificar que el archivo físico existe
    if (!file_exists($voucher['file_path'])) {
        throw new Exception('Archivo físico no encontrado: ' . $voucher['file_path']);
    }
    
    // 🔥 USAR MartinMarietaProcessor QUE YA EXISTS Y FUNCIONA
    $logger = new Logger();
    $logger->log($_SESSION['user_id'], 'PROCESSING_START', "Iniciando procesamiento voucher {$voucher_id}");
    
    // Actualizar estado a procesando
    $db->update('vouchers', [
        'status' => 'processing',
        'processing_started_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$voucher_id]);
    
    $start_time = microtime(true);
    
    // 🚀 CREAR PROCESSOR CON EMPRESAS SELECCIONADAS
    $processor = new MartinMarietaProcessor($voucher_id, $selected_companies);
    $processing_result = $processor->process();
    
    $processing_time = microtime(true) - $start_time;
    
    if ($processing_result['success']) {
        // Actualizar voucher con resultados exitosos
        $db->update('vouchers', [
            'status' => 'processed',
            'processing_completed_at' => date('Y-m-d H:i:s'),
            'total_rows_found' => $processing_result['total_rows'],
            'valid_rows_extracted' => $processing_result['saved_trips'],
            'extraction_confidence' => 0.90
        ], 'id = ?', [$voucher_id]);
        
        $logger->log($_SESSION['user_id'], 'PROCESSING_COMPLETE', 
            "Voucher {$voucher_id} procesado: {$processing_result['saved_trips']} trips guardados");
        
        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'message' => 'Archivo procesado exitosamente',
            'data' => [
                'voucher_id' => $voucher_id,
                'processing_time' => round($processing_time, 2),
                'trips_processed' => $processing_result['saved_trips'],
                'total_rows' => $processing_result['total_rows'],
                'filtered_rows' => $processing_result['filtered_rows'],
                'companies_found' => $processing_result['companies_found'],
                'status' => 'processed'
            ]
        ]);
        
    } else {
        throw new Exception($processing_result['error'] ?? 'Error desconocido en procesamiento');
    }
    
} catch (Exception $e) {
    $error_code = $e->getCode() ?: 500;
    
    // Log del error
    if (isset($voucher_id) && isset($logger)) {
        $logger->logError($_SESSION['user_id'] ?? null, 
            "Error procesando voucher {$voucher_id}: " . $e->getMessage());
        
        // Actualizar voucher con error
        if (isset($db)) {
            $db->update('vouchers', [
                'status' => 'error',
                'processing_notes' => $e->getMessage()
            ], 'id = ?', [$voucher_id]);
        }
    }
    
    error_log("Process file error: " . $e->getMessage());
    
    http_response_code($error_code);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $error_code
    ]);
}
?>