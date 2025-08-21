<?php
/**
 * API para procesar archivos subidos - VERSIÓN CORREGIDA
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

// Inicializar sesión
session_start();

// Error handling mejorado
error_reporting(E_ALL);
ini_set('display_errors', 0); // No mostrar errores en JSON
ini_set('log_errors', 1);

try {
    // Incluir dependencias
    require_once '../config/config.php';
    require_once '../classes/Database.php';
    require_once '../classes/Logger.php';
    
    // Verificar autenticación
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Usuario no autenticado', 401);
    }
    
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido', 405);
    }
    
    // Obtener datos de entrada
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['voucher_id'])) {
        throw new Exception('ID de voucher requerido', 400);
    }
    
    $voucher_id = intval($input['voucher_id']);
    
    if ($voucher_id <= 0) {
        throw new Exception('ID de voucher inválido', 400);
    }
    
    // Verificar que el voucher existe
    $db = Database::getInstance();
    $voucher = $db->fetch("SELECT * FROM vouchers WHERE id = ?", [$voucher_id]);
    
    if (!$voucher) {
        throw new Exception('Voucher no encontrado', 404);
    }
    
    // Verificar estado del voucher
    if ($voucher['status'] === 'processed') {
        // Si ya está procesado, retornar info existente
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
        throw new Exception('El archivo ya se está procesando', 400);
    }
    
    // Verificar permisos
    if (!hasPermission('process_vouchers')) {
        throw new Exception('No tienes permisos para procesar archivos', 403);
    }
    
    // Verificar que el archivo físico existe
    if (!file_exists($voucher['file_path'])) {
        throw new Exception('Archivo físico no encontrado: ' . $voucher['file_path'], 404);
    }
    
    // Iniciar procesamiento
    $logger = new Logger();
    $logger->log($_SESSION['user_id'], 'PROCESSING_START', 
        "Iniciando procesamiento de voucher {$voucher_id}");
    
    // Actualizar estado a procesando
    $db->update('vouchers', ['status' => 'processing'], 'id = ?', [$voucher_id]);
    
    // Determinar tipo de procesador según el archivo
    $file_type = $voucher['file_type'];
    
    $start_time = microtime(true);
    
    if ($file_type === 'pdf') {
        // Procesar PDF con nuestro extractor
        $result = processPDFFile($voucher_id, $voucher, $db, $logger);
    } elseif ($file_type === 'excel') {
        // Procesar Excel (por implementar)
        throw new Exception('Procesamiento de Excel aún no implementado', 501);
    } else {
        throw new Exception('Tipo de archivo no soportado: ' . $file_type, 400);
    }
    
    $processing_time = microtime(true) - $start_time;
    
    // Actualizar voucher con resultados
    $db->update('vouchers', [
        'status' => 'processed',
        'processed_at' => date('Y-m-d H:i:s'),
        'processed_by' => $_SESSION['user_id'],
        'processing_time_seconds' => round($processing_time),
        'data_quality_score' => $result['quality_score'],
        'total_trips' => $result['trips_processed'],
        'total_companies' => $result['unique_companies'] ?? 1,
        'total_amount' => $result['total_amount'] ?? 0
    ], 'id = ?', [$voucher_id]);
    
    // Log exitoso
    $logger->log($_SESSION['user_id'], 'PROCESSING_COMPLETE', 
        "Voucher {$voucher_id} procesado exitosamente: {$result['trips_processed']} viajes");
    
    // Respuesta exitosa
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Archivo procesado exitosamente',
        'data' => [
            'voucher_id' => $voucher_id,
            'processing_time' => round($processing_time, 2),
            'trips_processed' => $result['trips_processed'],
            'quality_score' => $result['quality_score'],
            'status' => 'processed',
            'total_amount' => $result['total_amount'] ?? 0
        ]
    ]);
    
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
    
    error_log("Process file error: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
    
    http_response_code($error_code);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'error_code' => $error_code
    ]);
}

/**
 * Procesar archivo PDF específicamente
 */
function processPDFFile($voucher_id, $voucher, $db, $logger) {
    try {
        // Cargar el PDFExtractor
        require_once '../classes/PDFExtractor.php';
        
        $extractor = new PDFExtractor($voucher_id);
        
        // Ejecutar el procesamiento
        $result = $extractor->process();
        
        return $result;
        
    } catch (Exception $e) {
        // Si PDFExtractor falla, intentar procesamiento básico
        error_log("PDFExtractor failed: " . $e->getMessage() . " - Attempting basic processing");
        
        return processBasicPDF($voucher_id, $voucher, $db, $logger);
    }
}

/**
 * Procesamiento básico de PDF como fallback
 */
function processBasicPDF($voucher_id, $voucher, $db, $logger) {
    try {
        // Verificar que las librerías PDF estén disponibles
        if (!class_exists('Smalot\PdfParser\Parser')) {
            require_once '../vendor/autoload.php';
        }
        
        $parser = new \Smalot\PdfParser\Parser();
        $document = $parser->parseFile($voucher['file_path']);
        $text = $document->getText();
        
        if (empty($text)) {
            throw new Exception('No se pudo extraer texto del PDF');
        }
        
        // Buscar datos de Martin Marietta con regex básico
        $lines = explode("\n", $text);
        $processed_trips = 0;
        $total_amount = 0;
        
        foreach ($lines as $line_number => $line) {
            $line = trim($line);
            
            // Patrón básico para líneas de Martin Marietta
            if (preg_match('/(\d+)\s+(PH)\s+(\d{2}\/\d{2}\/\d{4})([H]\d+)\s+(\d+)\s+([\d.]+)\s+([\d.]+)\s+(TN)\s+([\d.]+)/', $line, $matches)) {
                
                $weight = floatval($matches[6]);
                $rate = floatval($matches[7]);
                $amount = floatval($matches[9]);
                
                // Solo procesar líneas positivas
                if ($weight > 0 && $amount > 0) {
                    
                    // Convertir fecha MM/DD/YYYY a YYYY-MM-DD
                    $date_parts = explode('/', $matches[3]);
                    $trip_date = $date_parts[2] . '-' . str_pad($date_parts[0], 2, '0', STR_PAD_LEFT) . '-' . str_pad($date_parts[1], 2, '0', STR_PAD_LEFT);
                    
                    // Insertar trip básico
                    $trip_data = [
                        'voucher_id' => $voucher_id,
                        'company_id' => 6, // Martin Marietta Materials
                        'trip_date' => $trip_date,
                        'origin' => 'Martin Marietta - ' . $matches[1],
                        'destination' => 'Destino Cliente',
                        'weight_tons' => $weight,
                        'unit_rate' => $rate,
                        'subtotal' => $weight * $rate,
                        'deduction_type' => 'percentage',
                        'deduction_value' => 5.00,
                        'deduction_amount' => ($weight * $rate) * 0.05,
                        'total_amount' => $amount,
                        'vehicle_plate' => $matches[5],
                        'ticket_number' => $matches[4] . '/' . $matches[5],
                        'product_type' => 'Material de Construcción',
                        'extraction_confidence' => 0.85,
                        'data_source_type' => 'pdf',
                        'source_row_number' => $line_number + 1,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $trip_id = $db->insert('trips', $trip_data);
                    
                    if ($trip_id) {
                        $processed_trips++;
                        $total_amount += $amount;
                    }
                }
            }
        }
        
        if ($processed_trips === 0) {
            throw new Exception('No se encontraron datos válidos para procesar en el PDF');
        }
        
        return [
            'success' => true,
            'trips_processed' => $processed_trips,
            'quality_score' => 0.85,
            'total_amount' => $total_amount,
            'unique_companies' => 1
        ];
        
    } catch (Exception $e) {
        throw new Exception('Error en procesamiento básico de PDF: ' . $e->getMessage());
    }
}
?>