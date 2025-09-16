<?php
/**
 * 🔧 API FINAL CORREGIDA: process-file.php
 * ❌ ELIMINADO: TODO el código que creaba datos falsos (PLANT 082, T840437)
 * ✅ AGREGADO: Solo uso real de MartinMarietaProcessor
 * ✅ CORREGIDO: Formato JSON de entrada desde frontend
 * 
 * Ruta: /api/process-file.php
 */

// Limpiar cualquier output previo
if (ob_get_level()) {
    ob_end_clean();
}

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
    
    // ✅ OBTENER PARÁMETROS DESDE JSON (NO $_POST)
    $raw_input = file_get_contents('php://input');
    
    if (empty($raw_input)) {
        throw new Exception('No se recibieron datos de entrada');
    }
    
    $input = json_decode($raw_input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Error decodificando JSON: ' . json_last_error_msg());
    }
    
    $voucher_id = $input['voucher_id'] ?? null;
    $selected_companies = $input['companies'] ?? [];  // Mapear correctamente
    
    if (!$voucher_id) {
        throw new Exception('voucher_id es requerido');
    }
    
    if (empty($selected_companies)) {
        throw new Exception('companies es requerido');
    }
    
    // Incluir dependencias REALES
    require_once '../config/config.php';
    require_once '../classes/Database.php';
    require_once '../classes/Logger.php';
    require_once '../classes/MartinMarietaProcessor.php';  // ✅ PROCESSOR REAL
    
    $db = Database::getInstance();
    $logger = new Logger();
    
    // Verificar que el voucher existe y no está procesado
    $voucher = $db->fetch("SELECT * FROM vouchers WHERE id = ?", [$voucher_id]);
    
    if (!$voucher) {
        throw new Exception('Voucher no encontrado');
    }
    
    if ($voucher['status'] === 'processed') {
        // Si ya está procesado, devolver éxito sin procesar de nuevo
        echo json_encode([
            'success' => true,
            'message' => 'Voucher ya está procesado',
            'data' => [
                'voucher_id' => $voucher_id,
                'status' => 'processed',
                'trips_processed' => 0,
                'was_already_processed' => true
            ]
        ]);
        exit;
    }
    
    if ($voucher['status'] === 'processing') {
        throw new Exception('Voucher ya está siendo procesado');
    }
    
    // Verificar que el archivo existe
    if (!file_exists($voucher['file_path'])) {
        throw new Exception('Archivo del voucher no encontrado: ' . $voucher['file_path']);
    }
    
    $logger->log($_SESSION['user_id'], 'PROCESSING_API_START', "Iniciando procesamiento API para voucher: {$voucher_id}");
    
    // ✅ USAR PROCESSOR REAL - NUNCA MÁS DATOS FALSOS
    try {
        // Crear instancia del processor real
        $processor = new MartinMarietaProcessor($voucher_id, $selected_companies);
        
        // Procesar archivo real con MartinMarietaProcessor
        $result = $processor->process();
        
        if ($result['success']) {
            $logger->log($_SESSION['user_id'], 'PROCESSING_API_SUCCESS', 
                "Procesamiento completado - Trips guardados: {$result['saved_trips']}, Empresas: " . 
                implode(',', $result['companies_found']));
            
            // ✅ RESPUESTA EXITOSA CON DATOS REALES ÚNICAMENTE
            echo json_encode([
                'success' => true,
                'message' => 'Archivo procesado exitosamente con MartinMarietaProcessor',
                'data' => [
                    'voucher_id' => $voucher_id,
                    'total_rows_found' => $result['total_rows'],
                    'filtered_rows' => $result['filtered_rows'], 
                    'trips_processed' => $result['saved_trips'],
                    'companies_processed' => count($result['companies_found']),
                    'companies_found' => $result['companies_found'],
                    'status' => 'processed',
                    'processor_used' => 'MartinMarietaProcessor',
                    'processing_method' => 'real_pdf_extraction'
                ]
            ]);
            
        } else {
            throw new Exception('Error en procesamiento: ' . ($result['message'] ?? 'Error desconocido'));
        }
        
    } catch (Exception $processor_error) {
        // Log del error específico del processor
        $logger->log($_SESSION['user_id'], 'PROCESSING_ERROR', 
            "Error en MartinMarietaProcessor: " . $processor_error->getMessage());
        
        // Marcar voucher como error
        $db->update('vouchers', [
            'status' => 'error',
            'processing_notes' => $processor_error->getMessage()
        ], 'id = ?', [$voucher_id]);
        
        throw new Exception('Error procesando archivo: ' . $processor_error->getMessage());
    }
    
} catch (Exception $e) {
    // Log interno del error
    if (isset($logger) && isset($_SESSION['user_id'])) {
        $logger->log($_SESSION['user_id'], 'PROCESSING_API_ERROR', 
            "Error en API process-file: " . $e->getMessage());
    } else {
        error_log("Process API Error: " . $e->getMessage());
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => 'processing_error',
        'debug_info' => [
            'voucher_id' => $voucher_id ?? null,
            'companies_count' => isset($selected_companies) ? count($selected_companies) : 0,
            'has_logger' => isset($logger) ? 'yes' : 'no',
            'has_session' => isset($_SESSION['user_id']) ? 'yes' : 'no'
        ]
    ]);
}

// Asegurar que no hay output adicional
exit;
?>