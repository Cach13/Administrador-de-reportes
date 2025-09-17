<?php
// ========================================
// app/Controllers/ProcessingController.php
// Controlador para procesamiento de archivos
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
            
            // Renderizar vista
            $this->render('pages/processing', [
                'recentVouchers' => $recentVouchers,
                'companies' => $companies,
                'pageTitle' => 'Procesamiento de Archivos',
                'maxFileSize' => MAX_UPLOAD_SIZE,
                'allowedTypes' => ALLOWED_FILE_TYPES
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
            $this->validateFile($file, ALLOWED_FILE_TYPES, MAX_UPLOAD_SIZE);
            
            // Crear directorio de upload si no existe
            $uploadDir = UPLOAD_PATH . '/vouchers/' . date('Y/m');
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
                'original_filename' => $originalName,
                'stored_filename' => $fileName,
                'file_path' => $filePath,
                'file_format' => $fileFormat,
                'file_size' => $file['size'],
                'mime_type' => $file['type'],
                'status' => 'uploaded',
                'uploaded_by' => $this->currentUser['id'],
                'upload_date' => date('Y-m-d H:i:s')
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
     * Extraer datos del voucher
     */
    public function extract($voucherId)
    {
        // Verificar autenticación y permisos
        $this->requireAuth();
        $this->requirePermission('process_vouchers');
        
        try {
            // Validar voucher
            $voucher = $this->getVoucherById($voucherId);
            if (!$voucher) {
                $this->sendErrorResponse('Voucher no encontrado', 404);
            }
            
            if ($voucher['status'] !== 'uploaded') {
                $this->sendErrorResponse('Voucher ya procesado o en estado inválido');
            }
            
            // Actualizar estado
            $this->updateVoucherStatus($voucherId, 'extracted');
            
            // Obtener empresas disponibles directamente de la BD
            $availableCompanies = $this->getActiveCompanies();
            
            // Log de extracción
            $this->logActivity('DATA_EXTRACTION', "Voucher {$voucherId} listo para selección de empresas");
            
            // Respuesta con empresas disponibles para selección
            $this->sendSuccessResponse([
                'voucher_id' => $voucherId,
                'status' => 'extracted',
                'available_companies' => $availableCompanies,
                'message' => 'Voucher listo para procesamiento. Seleccione las empresas a procesar.'
            ], 'Voucher extraído correctamente');
            
        } catch (Exception $e) {
            // Actualizar estado de error
            $this->updateVoucherStatus($voucherId, 'error', $e->getMessage());
            $this->handleError('EXTRACTION_ERROR', 'Error extrayendo datos: ' . $e->getMessage());
        }
    }
    
    /**
     * Vista previa de datos extraídos
     */
    public function preview($voucherId)
    {
        // Verificar autenticación
        $this->requireAuth();
        $this->requirePermission('process_vouchers');
        
        try {
            $voucher = $this->getVoucherById($voucherId);
            if (!$voucher || !in_array($voucher['status'], ['extracted', 'processing'])) {
                $this->sendErrorResponse('Voucher no encontrado o no extraído', 404);
            }
            
            // Obtener empresas disponibles directamente de la BD
            $availableCompanies = $this->getActiveCompanies();
            
            // Información básica del voucher
            $this->sendSuccessResponse([
                'voucher' => $voucher,
                'available_companies' => $availableCompanies,
                'file_info' => [
                    'filename' => $voucher['original_filename'],
                    'format' => $voucher['file_format'],
                    'size' => $this->formatBytes($voucher['file_size']),
                    'upload_date' => $voucher['upload_date']
                ],
                'message' => 'Seleccione las empresas a procesar'
            ]);
            
        } catch (Exception $e) {
            $this->sendErrorResponse('Error obteniendo vista previa: ' . $e->getMessage());
        }
    }
    
    /**
     * Procesar voucher con empresas seleccionadas
     */
    public function process($voucherId)
    {
        // Verificar autenticación y permisos
        $this->requireAuth();
        $this->requirePermission('process_vouchers');
        
        // Verificar método POST
        if ($this->request['method'] !== 'POST') {
            $this->sendErrorResponse('Método no permitido', 405);
        }
        
        try {
            // Validar datos requeridos
            $this->validateRequired($this->request['post'], ['selected_companies']);
            
            $selectedCompanies = $this->request['post']['selected_companies'];
            if (!is_array($selectedCompanies) || empty($selectedCompanies)) {
                $this->sendErrorResponse('Debe seleccionar al menos una empresa');
            }
            
            // Validar voucher
            $voucher = $this->getVoucherById($voucherId);
            if (!$voucher || $voucher['status'] !== 'extracted') {
                $this->sendErrorResponse('Voucher no encontrado o no extraído', 404);
            }
            
            // Actualizar estado
            $this->updateVoucherStatus($voucherId, 'processing');
            
            // Crear procesador con empresas seleccionadas
            $processor = new MartinMarietaProcessor($voucherId, $selectedCompanies);
            
            // Procesar completamente
            $result = $processor->process();
            
            // Actualizar estado final
            $this->updateVoucherStatus($voucherId, 'processed');
            
            // Log de procesamiento
            $this->logActivity('VOUCHER_PROCESSED', "Voucher {$voucherId} procesado: {$result['total_trips']} trips insertados");
            
            // Respuesta exitosa
            $this->sendSuccessResponse([
                'voucher_id' => $voucherId,
                'processed_trips' => $result['total_trips'],
                'companies_processed' => $result['companies'],
                'total_amount' => $result['total_amount'],
                'processing_time' => $result['processing_time'] ?? null
            ], 'Voucher procesado correctamente');
            
        } catch (Exception $e) {
            // Actualizar estado de error
            $this->updateVoucherStatus($voucherId, 'error', $e->getMessage());
            $this->handleError('PROCESSING_ERROR', 'Error procesando voucher: ' . $e->getMessage());
        }
    }
    
    /**
     * API: Obtener estado de voucher
     */
    public function getStatus($voucherId)
    {
        // Verificar autenticación
        $this->requireAuth();
        
        try {
            $voucher = $this->getVoucherById($voucherId);
            if (!$voucher) {
                $this->sendErrorResponse('Voucher no encontrado', 404);
            }
            
            // Obtener información adicional según estado
            $additionalInfo = [];
            
            if ($voucher['status'] === 'processed') {
                // Contar trips generados
                $tripsCount = $this->db->fetch(
                    "SELECT COUNT(*) as count FROM trips WHERE voucher_id = ?",
                    [$voucherId]
                )['count'] ?? 0;
                
                $additionalInfo['trips_count'] = $tripsCount;
            }
            
            $this->sendSuccessResponse([
                'voucher' => $voucher,
                'additional_info' => $additionalInfo
            ]);
            
        } catch (Exception $e) {
            $this->sendErrorResponse('Error obteniendo estado: ' . $e->getMessage());
        }
    }
    
    // ========================================
    // MÉTODOS PRIVADOS DE DATOS
    // ========================================
    
    /**
     * Obtener vouchers recientes
     */
    private function getRecentVouchers()
    {
        return $this->db->fetchAll(
            "SELECT 
                v.*,
                u.full_name as uploaded_by_name,
                COUNT(t.id) as trips_count
             FROM vouchers v
             LEFT JOIN users u ON v.uploaded_by = u.id
             LEFT JOIN trips t ON v.id = t.voucher_id
             GROUP BY v.id
             ORDER BY v.upload_date DESC
             LIMIT 20"
        );
    }
    
    /**
     * Obtener empresas activas
     */
    private function getActiveCompanies()
    {
        return $this->db->fetchAll(
            "SELECT id, name, identifier, capital_percentage 
             FROM companies 
             WHERE is_active = 1 
             ORDER BY name ASC"
        );
    }
    
    /**
     * Obtener voucher por ID
     */
    private function getVoucherById($voucherId)
    {
        return $this->db->fetch(
            "SELECT * FROM vouchers WHERE id = ?",
            [$voucherId]
        );
    }
    
    /**
     * Actualizar estado de voucher
     */
    private function updateVoucherStatus($voucherId, $status, $errorMessage = null)
    {
        $data = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($errorMessage) {
            $data['error_message'] = $errorMessage;
        }
        
        return $this->db->update('vouchers', $data, 'id = ?', [$voucherId]);
    }
    
    /**
     * Detectar formato de archivo
     */
    private function detectFileFormat($filePath, $extension)
    {
        $extension = strtolower($extension);
        
        // Mapeo de extensiones a formatos
        $formatMap = [
            'pdf' => 'pdf',
            'xlsx' => 'excel',
            'xls' => 'excel'
        ];
        
        return $formatMap[$extension] ?? 'unknown';
    }
    
    /**
     * Obtener rango de fechas de los datos
     */
    private function getDateRange($data)
    {
        if (empty($data)) {
            return ['from' => null, 'to' => null];
        }
        
        $dates = array_column($data, 'ship_date');
        $dates = array_filter($dates); // Remover fechas vacías
        
        if (empty($dates)) {
            return ['from' => null, 'to' => null];
        }
        
        return [
            'from' => min($dates),
            'to' => max($dates)
        ];
    }
}
?>