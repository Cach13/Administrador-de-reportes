<?php
// ========================================
// app/Controllers/ProcessingController.php - ARREGLADO
// Controlador para procesamiento de archivos - SIN ERRORES DE CONSTANTES
// ========================================

namespace App\Controllers;

use Database;
use Logger;
use Exception;
use MartinMarietaProcessor;

/**
 * ProcessingController - Controlador para procesamiento de archivos
 * 
 * Funcionalidades:
 * - Upload de vouchers PDF/Excel
 * - Extracción de datos con MartinMarietaProcessor
 * - Selección de empresas
 * - Procesamiento y almacenamiento
 * - APIs para seguimiento de progreso
 */
class ProcessingController extends BaseController
{
    /**
     * Página principal de procesamiento
     */
    public function index()
    {
        // Verificar autenticación y permisos
        $this->requireAuth();
        $this->requirePermission('upload_vouchers');
        
        try {
            // Obtener vouchers recientes
            $recentVouchers = $this->getRecentVouchers();
            
            // Obtener empresas activas para selector
            $companies = $this->getActiveCompanies();
            
            // Log de acceso
            $this->logActivity('PROCESSING_ACCESS', 'Usuario accedió a procesamiento');
            
            // Renderizar vista - ARREGLADO: usar constantes globales correctamente
            $this->render('pages/processing', [
                'recentVouchers' => $recentVouchers,
                'companies' => $companies,
                'pageTitle' => 'Procesamiento de Archivos',
                'maxFileSize' => $this->getMaxUploadSize(),
                'allowedTypes' => $this->getAllowedFileTypes()
            ]);
            
        } catch (Exception $e) {
            $this->handleError('PROCESSING_PAGE_ERROR', 'Error cargando página de procesamiento: ' . $e->getMessage());
        }
    }
    
    /**
     * Upload de archivo voucher
     */
    public function upload()
    {
        // Verificar autenticación y permisos
        $this->requireAuth();
        $this->requirePermission('upload_vouchers');
        
        // Verificar método POST
        if ($this->request['method'] !== 'POST') {
            $this->sendErrorResponse('Método no permitido', 405);
        }
        
        // Verificar CSRF
        $this->validateCSRFToken($this->request['post']['csrf_token'] ?? '');
        
        try {
            // Validar archivo
            if (!isset($_FILES['voucher_file'])) {
                $this->sendErrorResponse('No se recibió archivo');
            }
            
            $file = $_FILES['voucher_file'];
            // ARREGLADO: usar métodos de validación de la clase base
            $this->validateFile($file, $this->getAllowedFileTypes(), $this->getMaxUploadSize());
            
            // Crear directorio de upload si no existe
            $uploadDir = $this->getUploadDirectory();
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generar nombre único
            $originalName = $file['name'];
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $fileName = uniqid('voucher_') . '.' . $extension;
            $filePath = $uploadDir . '/' . $fileName;
            
            // Mover archivo
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                $this->sendErrorResponse('Error moviendo archivo');
            }
            
            // Detectar formato
            $fileFormat = $this->detectFileFormat($filePath, $extension);
            
            // Guardar en BD
            $voucherData = [
                'filename' => $originalName,
                'stored_filename' => $fileName,
                'file_path' => $filePath,
                'file_format' => $fileFormat,
                'file_size' => $file['size'],
                'mime_type' => $file['type'],
                'status' => 'uploaded',
                'uploaded_by' => $this->currentUser['id'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $voucherId = $this->db->insert('vouchers', $voucherData);
            
            // Log del upload
            $this->logActivity('VOUCHER_UPLOAD', "Archivo subido: {$originalName} (ID: {$voucherId})");
            
            // Respuesta exitosa
            $this->sendSuccessResponse([
                'voucher_id' => $voucherId,
                'filename' => $originalName,
                'size' => $this->formatBytes($file['size']),
                'format' => $fileFormat,
                'status' => 'uploaded'
            ], 'Archivo subido correctamente');
            
        } catch (Exception $e) {
            $this->handleError('UPLOAD_ERROR', 'Error en upload: ' . $e->getMessage());
        }
    }
    
    /**
     * Procesar voucher con MartinMarietaProcessor
     */
    public function processVoucher($voucherId = null)
    {
        // Verificar autenticación y permisos
        $this->requireAuth();
        $this->requirePermission('process_vouchers');
        
        // Obtener ID del voucher
        $voucherId = $voucherId ?? $this->request['post']['voucher_id'] ?? null;
        
        if (!$voucherId) {
            $this->sendErrorResponse('ID de voucher requerido');
        }
        
        try {
            // Obtener información del voucher
            $voucher = $this->db->fetch(
                "SELECT * FROM vouchers WHERE id = ? AND status IN ('uploaded', 'error')",
                [$voucherId]
            );
            
            if (!$voucher) {
                $this->sendErrorResponse('Voucher no encontrado o ya procesado');
            }
            
            // Verificar que el archivo existe
            if (!file_exists($voucher['file_path'])) {
                $this->sendErrorResponse('Archivo no encontrado en disco');
            }
            
            // Actualizar estado a procesando
            $this->db->update('vouchers', 
                ['status' => 'processing', 'processing_started' => date('Y-m-d H:i:s')],
                ['id' => $voucherId]
            );
            
            // Log inicio de procesamiento
            $this->logActivity('VOUCHER_PROCESS_START', "Iniciando procesamiento del voucher ID: {$voucherId}");
            
            // Procesar con MartinMarietaProcessor - ARREGLADO: constructor correcto
            $processor = new MartinMarietaProcessor($voucherId, $this->request['post']['selected_companies'] ?? []);
            $result = $processor->process();
            
            if ($result['success']) {
                // Guardar datos extraídos en la tabla trips
                $tripsInserted = $this->saveTripsData($voucherId, $result['data'] ?? []);
                
                // Actualizar estado a procesado
                $this->db->update('vouchers', [
                    'status' => 'processed',
                    'processing_completed' => date('Y-m-d H:i:s'),
                    'trips_extracted' => $result['total_rows'] ?? count($result['data'] ?? []),
                    'processing_notes' => "Procesado exitosamente. {$tripsInserted} viajes guardados."
                ], ['id' => $voucherId]);
                
                // Log éxito
                $this->logActivity('VOUCHER_PROCESS_SUCCESS', 
                    "Voucher procesado exitosamente. ID: {$voucherId}, Trips: {$tripsInserted}");
                
                // Respuesta exitosa
                $this->sendSuccessResponse([
                    'voucher_id' => $voucherId,
                    'status' => 'processed',
                    'trips_extracted' => $result['total_rows'] ?? 0,
                    'trips_saved' => $tripsInserted,
                    'processing_time' => 0,
                    'quality_score' => 1.0,
                    'companies_found' => $result['companies_found'] ?? []
                ], 'Voucher procesado exitosamente');
                
            } else {
                // Error en procesamiento
                $this->db->update('vouchers', [
                    'status' => 'error',
                    'processing_completed' => date('Y-m-d H:i:s'),
                    'processing_notes' => $result['error'] ?? 'Error desconocido en procesamiento'
                ], ['id' => $voucherId]);
                
                // Log error
                $this->logActivity('VOUCHER_PROCESS_ERROR', 
                    "Error procesando voucher ID: {$voucherId}. Error: " . ($result['error'] ?? 'Desconocido'), 'ERROR');
                
                $this->sendErrorResponse($result['error'] ?? 'Error en procesamiento', 500);
            }
            
        } catch (Exception $e) {
            // Error general - actualizar estado
            $this->db->update('vouchers', [
                'status' => 'error',
                'processing_completed' => date('Y-m-d H:i:s'),
                'processing_notes' => 'Error del sistema: ' . $e->getMessage()
            ], ['id' => $voucherId]);
            
            $this->handleError('PROCESS_ERROR', 'Error procesando voucher: ' . $e->getMessage());
        }
    }
    
    /**
     * API: Estado del procesamiento
     */
    public function getProcessingStatus($voucherId)
    {
        $this->requireAuth();
        
        try {
            $voucher = $this->db->fetch(
                "SELECT id, filename, status, trips_extracted, processing_notes, 
                        created_at, processing_started, processing_completed
                 FROM vouchers WHERE id = ?",
                [$voucherId]
            );
            
            if (!$voucher) {
                $this->sendErrorResponse('Voucher no encontrado', 404);
            }
            
            // Calcular tiempo de procesamiento si está en curso
            $processingTime = null;
            if ($voucher['status'] === 'processing' && $voucher['processing_started']) {
                $processingTime = time() - strtotime($voucher['processing_started']);
            } elseif ($voucher['processing_completed'] && $voucher['processing_started']) {
                $processingTime = strtotime($voucher['processing_completed']) - strtotime($voucher['processing_started']);
            }
            
            $this->sendSuccessResponse([
                'voucher_id' => $voucher['id'],
                'filename' => $voucher['filename'],
                'status' => $voucher['status'],
                'trips_extracted' => $voucher['trips_extracted'],
                'processing_notes' => $voucher['processing_notes'],
                'processing_time' => $processingTime,
                'created_at' => $voucher['created_at'],
                'processing_started' => $voucher['processing_started'],
                'processing_completed' => $voucher['processing_completed']
            ]);
            
        } catch (Exception $e) {
            $this->sendErrorResponse('Error obteniendo estado: ' . $e->getMessage());
        }
    }
    
    /**
     * API: Lista de vouchers
     */
    public function getVouchersList()
    {
        $this->requireAuth();
        
        try {
            $vouchers = $this->db->fetchAll(
                "SELECT id, filename, status, file_size, trips_extracted, created_at
                 FROM vouchers 
                 ORDER BY created_at DESC 
                 LIMIT 50"
            );
            
            // Formatear datos para la respuesta
            foreach ($vouchers as &$voucher) {
                $voucher['file_size_formatted'] = $this->formatBytes($voucher['file_size']);
                $voucher['created_at_formatted'] = date('d/m/Y H:i', strtotime($voucher['created_at']));
            }
            
            $this->sendSuccessResponse($vouchers);
            
        } catch (Exception $e) {
            $this->sendErrorResponse('Error obteniendo lista: ' . $e->getMessage());
        }
    }
    
    // ========================================
    // MÉTODOS PRIVADOS
    // ========================================
    
    /**
     * Obtener tamaño máximo de upload - ARREGLADO: namespace correcto
     */
    private function getMaxUploadSize()
    {
        return defined('MAX_UPLOAD_SIZE') ? MAX_UPLOAD_SIZE : 20971520; // 20MB
    }
    
    /**
     * Obtener tipos de archivo permitidos - ARREGLADO: namespace correcto  
     */
    private function getAllowedFileTypes()
    {
        return defined('ALLOWED_FILE_TYPES') ? ALLOWED_FILE_TYPES : ['pdf', 'xlsx', 'xls'];
    }
    
    /**
     * Obtener directorio de upload
     */
    private function getUploadDirectory()
    {
        $basePath = defined('UPLOAD_PATH') ? \UPLOAD_PATH : ROOT_PATH . '/uploads';
        return $basePath . '/vouchers/' . date('Y/m');
    }
    
    /**
     * Detectar formato del archivo
     */
    private function detectFileFormat($filePath, $extension)
    {
        $extension = strtolower($extension);
        
        switch ($extension) {
            case 'pdf':
                return 'pdf';
            case 'xlsx':
            case 'xls':
                return 'excel';
            default:
                // Intentar detectar por contenido
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $filePath);
                finfo_close($finfo);
                
                if (strpos($mimeType, 'pdf') !== false) {
                    return 'pdf';
                } elseif (strpos($mimeType, 'spreadsheet') !== false || strpos($mimeType, 'excel') !== false) {
                    return 'excel';
                }
                
                return 'unknown';
        }
    }
    
    /**
     * Guardar datos de trips extraídos
     */
    private function saveTripsData($voucherId, $tripsData)
    {
        $insertedCount = 0;
        
        foreach ($tripsData as $trip) {
            try {
                $tripData = [
                    'voucher_id' => $voucherId,
                    'truck_number' => $trip['truck_number'] ?? null,
                    'trip_date' => $trip['date'] ?? date('Y-m-d'),
                    'customer' => $trip['customer'] ?? null,
                    'material' => $trip['material'] ?? null,
                    'tons' => $trip['tons'] ?? 0,
                    'rate' => $trip['rate'] ?? 0,
                    'amount' => $trip['amount'] ?? 0,
                    'job_site' => $trip['job_site'] ?? null,
                    'plant' => $trip['plant'] ?? null,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                $this->db->insert('trips', $tripData);
                $insertedCount++;
                
            } catch (Exception $e) {
                $this->logger->log(
                    $this->currentUser['id'],
                    'TRIP_INSERT_ERROR',
                    "Error insertando trip del voucher {$voucherId}: " . $e->getMessage(),
                    'ERROR'
                );
                // Continuar con el siguiente trip
                continue;
            }
        }
        
        return $insertedCount;
    }
    
    /**
     * Obtener vouchers recientes
     */
    private function getRecentVouchers($limit = 10)
    {
        try {
            return $this->db->fetchAll(
                "SELECT id, filename, status, file_size, trips_extracted, created_at
                 FROM vouchers 
                 ORDER BY created_at DESC 
                 LIMIT ?",
                [$limit]
            );
        } catch (Exception $e) {
            $this->logger->log(
                $this->currentUser['id'] ?? null,
                'GET_RECENT_VOUCHERS_ERROR',
                'Error obteniendo vouchers recientes: ' . $e->getMessage(),
                'ERROR'
            );
            return [];
        }
    }
    
    /**
     * Obtener empresas activas
     */
    private function getActiveCompanies()
    {
        try {
            return $this->db->fetchAll(
                "SELECT id, name, identifier, capital_percentage
                 FROM companies 
                 WHERE is_active = 1 
                 ORDER BY name"
            );
        } catch (Exception $e) {
            $this->logger->log(
                $this->currentUser['id'] ?? null,
                'GET_COMPANIES_ERROR',
                'Error obteniendo empresas: ' . $e->getMessage(),
                'ERROR'
            );
            return [];
        }
    }
}
?>