<?php
// ========================================
// app/Services/FileProcessingService.php - PASO 9
// Servicio de procesamiento de archivos - INTEGRA tu MartinMarietaProcessor
// ========================================

namespace App\Services;

use Database;
use Logger;
use MartinMarietaProcessor;
use Exception;

/**
 * FileProcessingService - Servicio centralizado de procesamiento de archivos
 * 
 * 🎯 INTEGRA TU MARTINMARIETAPROCESSOR.PHP EN LA NUEVA ARQUITECTURA
 * 
 * Funcionalidades:
 * - Upload y validación de archivos
 * - Integración con MartinMarietaProcessor existente
 * - Gestión de colas de procesamiento
 * - Seguimiento de progreso en tiempo real
 * - Manejo de errores robusto
 * - Procesamiento asíncrono
 * - Cache de resultados
 */
class FileProcessingService
{
    /** @var Database */
    private $db;
    
    /** @var Logger */
    private $logger;
    
    /** @var array */
    private $config;
    
    /** @var string */
    private $uploadPath;
    
    /** @var string */
    private $tempPath;
    
    /** @var array */
    private $allowedTypes;
    
    /** @var int */
    private $maxFileSize;
    
    /** @var array */
    private $processingQueue = [];
    
    /** @var array */
    private $processingStats = [];
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger();
        $this->config = $this->loadConfig();
        
        // Configuración de paths
        $this->uploadPath = defined('UPLOAD_PATH') ? UPLOAD_PATH : ROOT_PATH . '/uploads';
        $this->tempPath = defined('TEMP_PATH') ? TEMP_PATH : ROOT_PATH . '/temp';
        $this->allowedTypes = defined('ALLOWED_FILE_TYPES') ? ALLOWED_FILE_TYPES : ['pdf', 'xlsx', 'xls'];
        $this->maxFileSize = defined('MAX_UPLOAD_SIZE') ? MAX_UPLOAD_SIZE : 20 * 1024 * 1024; // 20MB
        
        // Crear directorios si no existen
        $this->ensureDirectories();
    }
    
    /**
     * Cargar configuración del servicio
     */
    private function loadConfig()
    {
        return [
            'max_concurrent_processes' => defined('MAX_CONCURRENT_PROCESSES') ? MAX_CONCURRENT_PROCESSES : 3,
            'processing_timeout' => defined('PROCESSING_TIMEOUT') ? PROCESSING_TIMEOUT : 300, // 5 minutos
            'quality_threshold' => defined('QUALITY_THRESHOLD') ? QUALITY_THRESHOLD : 0.75,
            'auto_process' => defined('AUTO_PROCESS_FILES') ? AUTO_PROCESS_FILES : false,
            'backup_originals' => defined('BACKUP_ORIGINAL_FILES') ? BACKUP_ORIGINAL_FILES : true,
            'retry_attempts' => 3,
            'chunk_size' => 1000, // Registros por chunk
            'memory_limit' => '512M'
        ];
    }
    
    /**
     * Crear directorios necesarios
     */
    private function ensureDirectories()
    {
        $directories = [
            $this->uploadPath,
            $this->uploadPath . '/vouchers',
            $this->uploadPath . '/processed',
            $this->uploadPath . '/failed',
            $this->tempPath,
            $this->tempPath . '/processing'
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    throw new Exception("No se pudo crear directorio: {$dir}");
                }
            }
        }
    }
    
    // ========================================
    // MÉTODOS PRINCIPALES DE PROCESAMIENTO
    // ========================================
    
    /**
     * Upload y procesar archivo
     */
    public function uploadAndProcess($fileData, $selectedCompanies = [], $userId = null)
    {
        try {
            // 1. Upload del archivo
            $uploadResult = $this->uploadFile($fileData, $userId);
            
            // 2. Crear registro de voucher
            $voucherId = $this->createVoucherRecord($uploadResult, $userId);
            
            // 3. Auto-procesar si está habilitado
            if ($this->config['auto_process']) {
                return $this->processVoucher($voucherId, $selectedCompanies);
            }
            
            return [
                'success' => true,
                'voucher_id' => $voucherId,
                'file_info' => $uploadResult,
                'message' => 'Archivo subido exitosamente. Listo para procesar.'
            ];
            
        } catch (Exception $e) {
            $this->logger->log($userId, 'UPLOAD_ERROR', "Error en upload: " . $e->getMessage());
            throw new Exception("Error en upload: " . $e->getMessage());
        }
    }
    
    /**
     * 🎯 MÉTODO PRINCIPAL: Procesar voucher usando TU MartinMarietaProcessor
     */
    public function processVoucher($voucherId, $selectedCompanies = [])
    {
        try {
            $startTime = microtime(true);
            $this->logger->log(null, 'PROCESSING_START', "Iniciando procesamiento voucher ID: {$voucherId}");
            
            // Validar voucher
            $voucher = $this->getVoucherById($voucherId);
            if (!$voucher) {
                throw new Exception("Voucher no encontrado: {$voucherId}");
            }
            
            // Actualizar estado a procesando
            $this->updateVoucherStatus($voucherId, 'processing');
            
            // 🔥 AQUÍ INTEGRAS TU MARTINMARIETAPROCESSOR EXISTENTE
            $processor = new MartinMarietaProcessor($voucherId, $selectedCompanies);
            
            // Configurar límites de memoria y tiempo
            ini_set('memory_limit', $this->config['memory_limit']);
            set_time_limit($this->config['processing_timeout']);
            
            // 🚀 EJECUTAR TU PROCESADOR - ¡MANTIENE TODA TU LÓGICA!
            $processingResult = $processor->process();
            
            // Calcular estadísticas
            $processingTime = microtime(true) - $startTime;
            $this->updateProcessingStats($voucherId, $processingResult, $processingTime);
            
            // Log de éxito
            $this->logger->log(null, 'PROCESSING_SUCCESS', 
                "Voucher {$voucherId} procesado exitosamente: {$processingResult['saved_trips']} trips guardados");
            
            return [
                'success' => true,
                'voucher_id' => $voucherId,
                'processing_time' => round($processingTime, 2),
                'results' => $processingResult,
                'message' => "Procesamiento completado: {$processingResult['saved_trips']} registros guardados"
            ];
            
        } catch (Exception $e) {
            // Actualizar estado a error
            $this->updateVoucherStatus($voucherId, 'error', $e->getMessage());
            
            $this->logger->log(null, 'PROCESSING_ERROR', 
                "Error procesando voucher {$voucherId}: " . $e->getMessage());
            
            throw new Exception("Error en procesamiento: " . $e->getMessage());
        }
    }
    
    /**
     * Procesar múltiples vouchers en lote
     */
    public function processBatch($voucherIds, $selectedCompanies = [])
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($voucherIds as $voucherId) {
            try {
                $result = $this->processVoucher($voucherId, $selectedCompanies);
                $results[$voucherId] = $result;
                $successCount++;
                
            } catch (Exception $e) {
                $results[$voucherId] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                $errorCount++;
            }
        }
        
        return [
            'total_processed' => count($voucherIds),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results
        ];
    }
    
    // ========================================
    // MÉTODOS DE UPLOAD Y VALIDACIÓN
    // ========================================
    
    /**
     * Upload de archivo con validación
     */
    public function uploadFile($fileData, $userId = null)
    {
        try {
            // Validar archivo
            $this->validateFileUpload($fileData);
            
            // Generar información del archivo
            $fileInfo = $this->generateFileInfo($fileData);
            
            // Mover archivo a ubicación final
            $finalPath = $this->moveUploadedFile($fileData, $fileInfo);
            
            // Crear backup si está habilitado
            if ($this->config['backup_originals']) {
                $this->createBackup($finalPath, $fileInfo);
            }
            
            $this->logger->log($userId, 'FILE_UPLOADED', 
                "Archivo subido: {$fileInfo['original_name']} -> {$fileInfo['stored_name']}");
            
            return [
                'original_name' => $fileInfo['original_name'],
                'stored_name' => $fileInfo['stored_name'],
                'file_path' => $finalPath,
                'file_size' => $fileInfo['size'],
                'file_hash' => $fileInfo['hash'],
                'file_type' => $fileInfo['type'],
                'mime_type' => $fileInfo['mime_type']
            ];
            
        } catch (Exception $e) {
            $this->logger->log($userId, 'UPLOAD_ERROR', "Error en upload: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Validar archivo de upload
     */
    private function validateFileUpload($fileData)
    {
        // Verificar que se recibió archivo
        if (!isset($fileData) || !is_array($fileData)) {
            throw new Exception("No se recibió archivo válido");
        }
        
        // Verificar errores de PHP
        if ($fileData['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Error en upload PHP: " . $this->getUploadErrorMessage($fileData['error']));
        }
        
        // Verificar tamaño
        if ($fileData['size'] > $this->maxFileSize) {
            $maxSizeMB = round($this->maxFileSize / (1024 * 1024), 1);
            throw new Exception("Archivo demasiado grande. Máximo permitido: {$maxSizeMB}MB");
        }
        
        // Verificar tipo de archivo
        $extension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedTypes)) {
            throw new Exception("Tipo de archivo no permitido. Tipos válidos: " . implode(', ', $this->allowedTypes));
        }
        
        // Verificar MIME type
        $allowedMimeTypes = [
            'pdf' => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel'
        ];
        
        $detectedMimeType = mime_content_type($fileData['tmp_name']);
        if (!in_array($detectedMimeType, $allowedMimeTypes)) {
            throw new Exception("MIME type no válido: {$detectedMimeType}");
        }
        
        return true;
    }
    
    /**
     * Generar información del archivo
     */
    private function generateFileInfo($fileData)
    {
        $extension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
        $hash = hash_file('sha256', $fileData['tmp_name']);
        
        return [
            'original_name' => $fileData['name'],
            'stored_name' => date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $extension,
            'size' => $fileData['size'],
            'hash' => $hash,
            'type' => $extension,
            'mime_type' => mime_content_type($fileData['tmp_name'])
        ];
    }
    
    /**
     * Mover archivo a ubicación final
     */
    private function moveUploadedFile($fileData, $fileInfo)
    {
        $targetDir = $this->uploadPath . '/vouchers/' . date('Y-m');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        $finalPath = $targetDir . '/' . $fileInfo['stored_name'];
        
        if (!move_uploaded_file($fileData['tmp_name'], $finalPath)) {
            throw new Exception("No se pudo mover el archivo a la ubicación final");
        }
        
        return $finalPath;
    }
    
    // ========================================
    // MÉTODOS DE GESTIÓN DE VOUCHERS
    // ========================================
    
    /**
     * Crear registro de voucher en BD
     */
    private function createVoucherRecord($uploadResult, $userId)
    {
        try {
            $voucherData = [
                'voucher_number' => $this->generateVoucherNumber(),
                'original_filename' => $uploadResult['original_name'],
                'file_path' => $uploadResult['file_path'],
                'file_size' => $uploadResult['file_size'],
                'file_hash' => $uploadResult['file_hash'],
                'file_type' => 'martin_marieta',
                'file_format' => $uploadResult['file_type'],
                'status' => 'uploaded',
                'upload_date' => date('Y-m-d H:i:s'),
                'uploaded_by' => $userId ?: 1
            ];
            
            return $this->db->insert('vouchers', $voucherData);
            
        } catch (Exception $e) {
            throw new Exception("Error creando registro de voucher: " . $e->getMessage());
        }
    }
    
    /**
     * Generar número único de voucher
     */
    private function generateVoucherNumber()
    {
        $prefix = 'MM'; // Martin Marieta
        $date = date('Ymd');
        $sequence = $this->getNextSequenceNumber();
        
        return $prefix . $date . sprintf('%04d', $sequence);
    }
    
    /**
     * Obtener siguiente número de secuencia
     */
    private function getNextSequenceNumber()
    {
        $today = date('Y-m-d');
        $count = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM vouchers WHERE DATE(upload_date) = ?",
            [$today]
        );
        
        return ($count ?: 0) + 1;
    }
    
    /**
     * Actualizar estado de voucher
     */
    private function updateVoucherStatus($voucherId, $status, $notes = null)
    {
        $updateData = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($status === 'processing') {
            $updateData['processing_started_at'] = date('Y-m-d H:i:s');
        } elseif ($status === 'processed') {
            $updateData['processing_completed_at'] = date('Y-m-d H:i:s');
        }
        
        if ($notes) {
            $updateData['processing_notes'] = $notes;
        }
        
        $this->db->update('vouchers', $updateData, 'id = ?', [$voucherId]);
    }
    
    /**
     * Actualizar estadísticas de procesamiento
     */
    private function updateProcessingStats($voucherId, $result, $processingTime)
    {
        $updateData = [
            'total_rows_found' => $result['total_rows'],
            'valid_rows_extracted' => $result['saved_trips'],
            'rows_with_errors' => $result['total_rows'] - $result['filtered_rows'],
            'extraction_confidence' => min($result['filtered_rows'] / max($result['total_rows'], 1), 1.0)
        ];
        
        $this->db->update('vouchers', $updateData, 'id = ?', [$voucherId]);
        
        // Guardar estadísticas detalladas
        $this->processingStats[$voucherId] = [
            'processing_time' => $processingTime,
            'memory_used' => memory_get_peak_usage(true),
            'companies_found' => count($result['companies_found'] ?? []),
            'timestamp' => time()
        ];
    }
    
    // ========================================
    // MÉTODOS DE INFORMACIÓN Y ESTADÍSTICAS
    // ========================================
    
    /**
     * Obtener información de voucher
     */
    public function getVoucherInfo($voucherId)
    {
        $voucher = $this->getVoucherById($voucherId);
        if (!$voucher) {
            throw new Exception("Voucher no encontrado");
        }
        
        // Obtener estadísticas de trips
        $tripStats = $this->db->fetch(
            "SELECT 
                COUNT(*) as total_trips,
                COUNT(DISTINCT company_id) as companies_count,
                SUM(amount) as total_amount,
                MIN(trip_date) as first_trip_date,
                MAX(trip_date) as last_trip_date
             FROM trips 
             WHERE voucher_id = ?",
            [$voucherId]
        );
        
        return [
            'voucher' => $voucher,
            'trip_stats' => $tripStats,
            'processing_stats' => $this->processingStats[$voucherId] ?? null
        ];
    }
    
    /**
     * Obtener estadísticas generales del servicio
     */
    public function getServiceStats()
    {
        try {
            return [
                'total_vouchers' => $this->db->fetchColumn("SELECT COUNT(*) FROM vouchers"),
                'vouchers_today' => $this->db->fetchColumn(
                    "SELECT COUNT(*) FROM vouchers WHERE DATE(upload_date) = CURDATE()"
                ),
                'processing_queue' => count($this->processingQueue),
                'status_breakdown' => $this->getStatusBreakdown(),
                'recent_activity' => $this->getRecentActivity(),
                'performance_metrics' => $this->getPerformanceMetrics()
            ];
        } catch (Exception $e) {
            $this->logger->log(null, 'STATS_ERROR', "Error obteniendo estadísticas: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtener breakdown por estado
     */
    private function getStatusBreakdown()
    {
        return $this->db->fetchAll(
            "SELECT status, COUNT(*) as count 
             FROM vouchers 
             GROUP BY status 
             ORDER BY count DESC"
        );
    }
    
    /**
     * Obtener actividad reciente
     */
    private function getRecentActivity($limit = 10)
    {
        return $this->db->fetchAll(
            "SELECT v.*, u.full_name as uploaded_by_name 
             FROM vouchers v
             LEFT JOIN users u ON v.uploaded_by = u.id
             ORDER BY v.upload_date DESC 
             LIMIT ?",
            [$limit]
        );
    }
    
    /**
     * Obtener métricas de rendimiento
     */
    private function getPerformanceMetrics()
    {
        $avgProcessingTime = 0;
        $totalMemoryUsed = 0;
        $processedCount = 0;
        
        foreach ($this->processingStats as $stats) {
            $avgProcessingTime += $stats['processing_time'];
            $totalMemoryUsed += $stats['memory_used'];
            $processedCount++;
        }
        
        return [
            'avg_processing_time' => $processedCount > 0 ? round($avgProcessingTime / $processedCount, 2) : 0,
            'avg_memory_usage' => $processedCount > 0 ? round($totalMemoryUsed / $processedCount / (1024*1024), 2) : 0,
            'processed_today' => $processedCount
        ];
    }
    
    // ========================================
    // MÉTODOS DE UTILIDAD
    // ========================================
    
    /**
     * Obtener voucher por ID
     */
    private function getVoucherById($voucherId)
    {
        return $this->db->fetch("SELECT * FROM vouchers WHERE id = ?", [$voucherId]);
    }
    
    /**
     * Crear backup del archivo original
     */
    private function createBackup($filePath, $fileInfo)
    {
        $backupDir = $this->uploadPath . '/backup/' . date('Y-m');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $backupPath = $backupDir . '/' . $fileInfo['stored_name'];
        copy($filePath, $backupPath);
    }
    
    /**
     * Obtener mensaje de error de upload
     */
    private function getUploadErrorMessage($errorCode)
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por PHP',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo del formulario',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta directorio temporal',
            UPLOAD_ERR_CANT_WRITE => 'Error escribiendo archivo al disco',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensión PHP'
        ];
        
        return $messages[$errorCode] ?? "Error desconocido: {$errorCode}";
    }
    
    /**
     * Limpiar archivos temporales antiguos
     */
    public function cleanupTempFiles($olderThanHours = 24)
    {
        $cutoffTime = time() - ($olderThanHours * 3600);
        $cleanedCount = 0;
        
        $tempDirs = [
            $this->tempPath,
            $this->tempPath . '/processing'
        ];
        
        foreach ($tempDirs as $dir) {
            if (!is_dir($dir)) continue;
            
            $files = glob($dir . '/*');
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < $cutoffTime) {
                    if (unlink($file)) {
                        $cleanedCount++;
                    }
                }
            }
        }
        
        $this->logger->log(null, 'CLEANUP_TEMP', "Limpiados {$cleanedCount} archivos temporales");
        return $cleanedCount;
    }
    
    /**
     * Validar integridad de archivo
     */
    public function validateFileIntegrity($voucherId)
    {
        $voucher = $this->getVoucherById($voucherId);
        if (!$voucher) {
            return false;
        }
        
        if (!file_exists($voucher['file_path'])) {
            return false;
        }
        
        $currentHash = hash_file('sha256', $voucher['file_path']);
        return $currentHash === $voucher['file_hash'];
    }
}
?>