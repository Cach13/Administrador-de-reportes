<?php
/**
 * Factory para crear procesadores de archivos
 * Ruta: /classes/FileProcessorFactory.php
 */

require_once __DIR__ . '/FileProcessor.php';
require_once __DIR__ . '/ExcelExtractor.php';
require_once __DIR__ . '/PDFExtractor.php';

class FileProcessorFactory {
    
    /**
     * Crear procesador según el tipo de archivo
     */
    public static function create($voucher_id) {
        // Obtener información del voucher
        $db = Database::getInstance();
        $voucher = $db->fetch("SELECT * FROM vouchers WHERE id = ?", [$voucher_id]);
        
        if (!$voucher) {
            throw new Exception("Voucher no encontrado: {$voucher_id}");
        }
        
        $file_type = $voucher['file_type'];
        
        switch ($file_type) {
            case 'excel':
                return new ExcelExtractor($voucher_id);
                
            case 'pdf':
                return new PDFExtractor($voucher_id);
                
            default:
                throw new Exception("Tipo de archivo no soportado: {$file_type}");
        }
    }
    
    /**
     * Determinar tipo de archivo por extensión/MIME
     */
    public static function detectFileType($file_path) {
        // Obtener MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file_path);
        finfo_close($finfo);
        
        // Obtener extensión
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        // Mapeo de tipos
        $type_mapping = [
            // Excel
            'xlsx' => 'excel',
            'xls' => 'excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'excel',
            'application/vnd.ms-excel' => 'excel',
            'application/excel' => 'excel',
            
            // PDF
            'pdf' => 'pdf',
            'application/pdf' => 'pdf'
        ];
        
        // Buscar por MIME type primero
        if (isset($type_mapping[$mime_type])) {
            return $type_mapping[$mime_type];
        }
        
        // Buscar por extensión
        if (isset($type_mapping[$extension])) {
            return $type_mapping[$extension];
        }
        
        throw new Exception("Tipo de archivo no soportado: {$extension} / {$mime_type}");
    }
    
    /**
     * Validar archivo antes de procesamiento
     */
    public static function validateFile($file_path) {
        $validation = [
            'is_valid' => true,
            'errors' => [],
            'warnings' => [],
            'file_info' => []
        ];
        
        try {
            // Verificar que el archivo existe
            if (!file_exists($file_path)) {
                $validation['errors'][] = 'Archivo no encontrado';
                $validation['is_valid'] = false;
                return $validation;
            }
            
            // Información básica del archivo
            $validation['file_info'] = [
                'size' => filesize($file_path),
                'extension' => pathinfo($file_path, PATHINFO_EXTENSION),
                'mime_type' => mime_content_type($file_path),
                'readable' => is_readable($file_path)
            ];
            
            // Verificar que es legible
            if (!$validation['file_info']['readable']) {
                $validation['errors'][] = 'Archivo no es legible';
                $validation['is_valid'] = false;
            }
            
            // Detectar tipo
            try {
                $file_type = self::detectFileType($file_path);
                $validation['file_info']['detected_type'] = $file_type;
            } catch (Exception $e) {
                $validation['errors'][] = $e->getMessage();
                $validation['is_valid'] = false;
                return $validation;
            }
            
            // Validaciones específicas por tipo
            switch ($file_type) {
                case 'excel':
                    $validation = self::validateExcelFile($file_path, $validation);
                    break;
                    
                case 'pdf':
                    $validation = self::validatePDFFile($file_path, $validation);
                    break;
            }
            
        } catch (Exception $e) {
            $validation['errors'][] = 'Error validando archivo: ' . $e->getMessage();
            $validation['is_valid'] = false;
        }
        
        return $validation;
    }
    
    /**
     * Validar archivo Excel específicamente
     */
    private static function validateExcelFile($file_path, $validation) {
        try {
            // Verificar tamaño
            $max_size = 10 * 1024 * 1024; // 10MB
            if ($validation['file_info']['size'] > $max_size) {
                $validation['warnings'][] = 'Archivo Excel muy grande, el procesamiento puede ser lento';
            }
            
            // Intentar abrir con PhpSpreadsheet
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file_path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file_path);
            
            // Verificar que tiene hojas
            if ($spreadsheet->getSheetCount() === 0) {
                $validation['errors'][] = 'El archivo Excel no contiene hojas de trabajo';
                $validation['is_valid'] = false;
            } else {
                $worksheet = $spreadsheet->getActiveSheet();
                $highest_row = $worksheet->getHighestRow();
                
                if ($highest_row < 2) {
                    $validation['errors'][] = 'El archivo Excel no contiene suficientes datos';
                    $validation['is_valid'] = false;
                } else {
                    $validation['file_info']['rows'] = $highest_row;
                    $validation['file_info']['columns'] = $worksheet->getHighestColumn();
                    
                    if ($highest_row > 5000) {
                        $validation['warnings'][] = "Archivo con muchas filas ({$highest_row}), considere dividirlo";
                    }
                }
            }
            
            // Limpiar memoria
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            
        } catch (Exception $e) {
            $validation['errors'][] = 'Error abriendo archivo Excel: ' . $e->getMessage();
            $validation['is_valid'] = false;
        }
        
        return $validation;
    }
    
    /**
     * Validar archivo PDF específicamente
     */
    private static function validatePDFFile($file_path, $validation) {
        try {
            // Verificar tamaño
            $max_size = 20 * 1024 * 1024; // 20MB
            if ($validation['file_info']['size'] > $max_size) {
                $validation['warnings'][] = 'Archivo PDF muy grande, el procesamiento puede ser lento';
            }
            
            // Intentar abrir con PdfParser
            $parser = new \Smalot\PdfParser\Parser();
            $document = $parser->parseFile($file_path);
            
            // Verificar que se puede extraer texto
            $text = $document->getText();
            $text_length = strlen(trim($text));
            
            if ($text_length === 0) {
                $validation['warnings'][] = 'PDF no contiene texto extraible, podría ser una imagen escaneada';
            } else {
                $validation['file_info']['text_length'] = $text_length;
                
                if ($text_length < 100) {
                    $validation['warnings'][] = 'PDF contiene muy poco texto';
                }
            }
            
            // Información adicional
            $pages = $document->getPages();
            $validation['file_info']['pages'] = $pages ? count($pages) : 0;
            
            // Limpiar memoria
            unset($document);
            unset($parser);
            
        } catch (Exception $e) {
            $validation['errors'][] = 'Error abriendo archivo PDF: ' . $e->getMessage();
            $validation['is_valid'] = false;
        }
        
        return $validation;
    }
    
    /**
     * Obtener límites de archivo por tipo
     */
    public static function getFileLimits($file_type) {
        $limits = [
            'excel' => [
                'max_size' => 10 * 1024 * 1024, // 10MB
                'max_rows' => 10000,
                'allowed_extensions' => ['xlsx', 'xls'],
                'mime_types' => [
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-excel'
                ]
            ],
            'pdf' => [
                'max_size' => 20 * 1024 * 1024, // 20MB
                'max_pages' => 100,
                'allowed_extensions' => ['pdf'],
                'mime_types' => ['application/pdf']
            ]
        ];
        
        return $limits[$file_type] ?? null;
    }
    
    /**
     * Obtener información sobre tipos de archivo soportados
     */
    public static function getSupportedTypes() {
        return [
            'excel' => [
                'name' => 'Microsoft Excel',
                'description' => 'Archivos de hoja de cálculo Excel',
                'extensions' => ['xlsx', 'xls'],
                'icon' => 'fas fa-file-excel',
                'color' => '#10b981',
                'max_size' => '10MB',
                'features' => [
                    'Detección automática de headers',
                    'Mapeo flexible de columnas',
                    'Validación de datos',
                    'Soporte para múltiples hojas'
                ]
            ],
            'pdf' => [
                'name' => 'Portable Document Format',
                'description' => 'Documentos PDF con texto extraible',
                'extensions' => ['pdf'],
                'icon' => 'fas fa-file-pdf',
                'color' => '#dc2626',
                'max_size' => '20MB',
                'features' => [
                    'Extracción de texto avanzada',
                    'Detección de tablas',
                    'Reconocimiento de patrones',
                    'Análisis inteligente de contenido'
                ]
            ]
        ];
    }
    
    /**
     * Procesar archivo completo (método de conveniencia)
     */
    public static function processFile($voucher_id) {
        try {
            $processor = self::create($voucher_id);
            $result = $processor->process();
            
            return [
                'success' => true,
                'data' => $result,
                'processor_type' => get_class($processor)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
    }
    
    /**
     * Previsualizar archivo sin procesamiento completo
     */
    public static function previewFile($voucher_id, $options = []) {
        try {
            $processor = self::create($voucher_id);
            
            // Obtener información del voucher para determinar el tipo
            $db = Database::getInstance();
            $voucher = $db->fetch("SELECT file_type FROM vouchers WHERE id = ?", [$voucher_id]);
            
            $preview = [
                'file_type' => $voucher['file_type'],
                'processor_class' => get_class($processor)
            ];
            
            // Obtener información específica según el tipo
            if ($processor instanceof ExcelExtractor) {
                $preview['excel_info'] = $processor->getFileInfo();
                $preview['preview_data'] = $processor->previewData($options['max_rows'] ?? 5);
                $preview['statistics'] = $processor->getStatistics();
            } elseif ($processor instanceof PDFExtractor) {
                $preview['pdf_info'] = $processor->getPDFInfo();
                $preview['content_preview'] = $processor->previewContent($options['max_length'] ?? 1000);
            }
            
            return [
                'success' => true,
                'preview' => $preview
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener estadísticas de procesamiento por tipo de archivo
     */
    public static function getProcessingStats($days = 30) {
        $db = Database::getInstance();
        
        $sql = "
            SELECT 
                file_type,
                COUNT(*) as total_files,
                COUNT(CASE WHEN status = 'processed' THEN 1 END) as processed_files,
                COUNT(CASE WHEN status = 'error' THEN 1 END) as failed_files,
                AVG(processing_time_seconds) as avg_processing_time,
                AVG(data_quality_score) as avg_quality_score,
                SUM(total_trips) as total_trips_extracted,
                SUM(total_amount) as total_amount_processed
            FROM vouchers 
            WHERE upload_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY file_type
        ";
        
        $stats = $db->fetchAll($sql, [$days]);
        
        return $stats;
    }
    
    /**
     * Limpiar archivos temporales y recursos
     */
    public static function cleanup() {
        // Limpiar archivos temporales de PhpSpreadsheet
        $temp_dir = sys_get_temp_dir();
        $phpspreadsheet_files = glob($temp_dir . '/PhpSpreadsheet*');
        
        foreach ($phpspreadsheet_files as $file) {
            if (is_file($file) && time() - filemtime($file) > 3600) { // Más de 1 hora
                @unlink($file);
            }
        }
        
        // Forzar limpieza de memoria
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }
}