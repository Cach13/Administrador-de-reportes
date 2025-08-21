<?php
/**
 * Procesador de archivos PDF - VERSIÓN CORREGIDA
 * Optimizado para vouchers de Martin Marietta Materials
 * Ruta: /classes/PDFExtractor.php
 */

require_once __DIR__ . '/FileProcessor.php';

use Smalot\PdfParser\Parser;
use Smalot\PdfParser\Document;

class PDFExtractor extends FileProcessor {
    
    private $parser;
    private $document;
    private $text_content;
    private $extraction_patterns;
    
    public function __construct($voucher_id) {
        parent::__construct($voucher_id);
        $this->parser = new Parser();
        $this->setupExtractionPatterns();
    }
    
    /**
     * Configurar patrones específicos para Martin Marietta
     */
    private function setupExtractionPatterns() {
        $this->extraction_patterns = [
            // Patrón principal para líneas de datos de Martin Marietta
            // Formato: 16146 PH 07/14/2025H2648318 41142689 -21.56 10.60 TN -228.54 1488527942 RMTAAT101Wrong Customer
            'martin_marietta_row' => '/(\d+)\s+(PH)\s+(\d{2}\/\d{2}\/\d{4})([H]\d+)\s+(\d+)\s+([-]?\d+\.\d{2})\s+(\d+\.\d{2})\s+(TN)\s+([-]?\d+\.\d{2})\s+(\d+)\s+([A-Z0-9]+)/i',
            
            // Patrones de fecha más específicos
            'date_patterns' => [
                '/(\d{2}\/\d{2}\/\d{4})/',           // MM/DD/YYYY
                '/(\d{4}-\d{2}-\d{2})/',             // YYYY-MM-DD
            ],
            
            // Patrones de empresa
            'company_patterns' => [
                '/CAPITAL TRANSPORT LLC/i',
                '/Martin Marietta Materials/i',
                '/Haul Voucher/i'
            ],
            
            // Patrones de montos
            'amount_patterns' => [
                '/([-]?\d+\.\d{2})\s+\d+\.\d{2}\s+TN\s+([-]?\d+\.\d{2})/i', // Peso y monto
            ],
            
            // Patrones de tickets
            'ticket_patterns' => [
                '/([H]\d+)/i',                       // H2648318 formato
                '/(\d{8})/i'                         // 41142689 formato
            ]
        ];
    }
    
    /**
     * Validar tipo de archivo PDF
     */
    protected function validateFileType() {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $this->file_path);
        finfo_close($finfo);
        
        if ($mime_type !== 'application/pdf') {
            throw new Exception("Tipo de archivo no es PDF: {$mime_type}");
        }
        
        $extension = strtolower(pathinfo($this->file_path, PATHINFO_EXTENSION));
        if ($extension !== 'pdf') {
            throw new Exception("Extensión de archivo no válida: {$extension}");
        }
        
        try {
            $test_document = $this->parser->parseFile($this->file_path);
            if (!$test_document) {
                throw new Exception("No se puede abrir el archivo PDF");
            }
        } catch (Exception $e) {
            throw new Exception("PDF corrupto o inválido: " . $e->getMessage());
        }
    }
    
    /**
     * Extraer datos del archivo PDF
     */
    protected function extractData() {
        try {
            $this->document = $this->parser->parseFile($this->file_path);
            $this->text_content = $this->document->getText();
            
            $this->logProcessingStep('pdf_load', 'completed', 'Archivo PDF cargado exitosamente');
            
            // Verificar si es voucher de Martin Marietta
            if (!$this->isMarinMariettaVoucher()) {
                throw new Exception("Este PDF no parece ser un voucher de Martin Marietta Materials");
            }
            
            // Extraer datos usando patrón específico
            $structured_data = $this->extractMarinMariettaData();
            
            $this->logProcessingStep('pdf_extraction', 'completed', 
                "Extraídos " . count($structured_data) . " registros de datos");
            
            return $structured_data;
            
        } catch (Exception $e) {
            throw new Exception("Error extrayendo datos de PDF: " . $e->getMessage());
        }
    }
    
    /**
     * Verificar si es voucher de Martin Marietta
     */
    private function isMarinMariettaVoucher() {
        $indicators = [
            'Martin Marietta Materials',
            'Haul Voucher',
            'CAPITAL TRANSPORT LLC',
            'Payment Number'
        ];
        
        foreach ($indicators as $indicator) {
            if (stripos($this->text_content, $indicator) === false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Extraer datos específicos de Martin Marietta
     */
    private function extractMarinMariettaData() {
        $lines = explode("\n", $this->text_content);
        $extracted_data = [];
        
        foreach ($lines as $line_number => $line) {
            $line = trim($line);
            
            // Aplicar patrón principal para líneas de datos
            if (preg_match($this->extraction_patterns['martin_marietta_row'], $line, $matches)) {
                
                // Extraer datos de los grupos capturados
                $row_data = $this->parseMarinMariettaRow($matches, $line_number + 1);
                
                if ($row_data) {
                    $extracted_data[] = $row_data;
                }
            }
        }
        
        // Log del procesamiento
        $this->logProcessingStep('martin_marietta_parsing', 'completed', 
            "Procesadas " . count($extracted_data) . " líneas de datos de Martin Marietta");
        
        return $extracted_data;
    }
    
    /**
     * Parsear una línea específica de Martin Marietta
     */
    private function parseMarinMariettaRow($matches, $line_number) {
        try {
            // Estructura del patrón:
            // 1: Código ubicación (16146)
            // 2: Tipo (PH) 
            // 3: Fecha (07/14/2025)
            // 4: Ticket H (H2648318)
            // 5: Ticket número (41142689)
            // 6: Peso con signo (-21.56)
            // 7: Tarifa (10.60)
            // 8: Unidad (TN)
            // 9: Monto total con signo (-228.54)
            // 10: Código conductor (1488527942)
            // 11: Código destino (RMTAAT101...)
            
            $location_code = $matches[1];
            $transport_type = $matches[2];
            $trip_date = $matches[3];
            $ticket_h = $matches[4];
            $ticket_number = $matches[5];
            $weight_signed = floatval($matches[6]);
            $unit_rate = floatval($matches[7]);
            $unit_type = $matches[8];
            $total_amount_signed = floatval($matches[9]);
            $driver_code = $matches[10];
            $destination_code = $matches[11];
            
            // Solo procesar registros positivos (las correcciones negativas las ignoramos)
            if ($weight_signed <= 0 || $total_amount_signed <= 0) {
                return null;
            }
            
            // Convertir fecha de MM/DD/YYYY a YYYY-MM-DD
            $date_parts = explode('/', $trip_date);
            if (count($date_parts) === 3) {
                $formatted_date = $date_parts[2] . '-' . str_pad($date_parts[0], 2, '0', STR_PAD_LEFT) . '-' . str_pad($date_parts[1], 2, '0', STR_PAD_LEFT);
            } else {
                $formatted_date = date('Y-m-d'); // Fecha actual como fallback
            }
            
            // Construir registro normalizado
            $row_data = [
                'date' => $formatted_date,
                'company' => 'Martin Marietta Materials',
                'origin' => 'Martin Marietta - ' . $location_code,
                'destination' => $this->decodeDestination($destination_code),
                'weight' => $weight_signed,
                'rate' => $unit_rate,
                'vehicle' => $driver_code, // Usamos código conductor como vehículo por ahora
                'driver' => 'Conductor ' . $driver_code,
                'ticket' => $ticket_h . '/' . $ticket_number,
                'product' => 'Material de Construcción',
                'amount' => $total_amount_signed,
                'raw_line' => implode('|', $matches), // Para debugging
                'line_number' => $line_number
            ];
            
            return $row_data;
            
        } catch (Exception $e) {
            $this->logValidationError($line_number, 'parsing', 
                "Error parseando línea Martin Marietta: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Decodificar código de destino
     */
    private function decodeDestination($code) {
        // Códigos comunes de Martin Marietta
        $destination_codes = [
            'RMTAAT101' => 'RMT Plant - Austin TX',
            'RMTAAT102' => 'RMT Plant - Austin TX Alt',
            'RMTPLA101' => 'RMT Plant - Plano TX',
            'RMTDAL101' => 'RMT Plant - Dallas TX',
            'RMTHOU101' => 'RMT Plant - Houston TX'
        ];
        
        // Extraer código base (primeros 9 caracteres)
        $base_code = substr($code, 0, 9);
        
        return $destination_codes[$base_code] ?? 'Destino ' . $base_code;
    }
    
    /**
     * Obtener tipo de archivo
     */
    protected function getFileType() {
        return 'pdf';
    }
    
    /**
     * Obtener tamaño máximo de archivo
     */
    protected function getMaxFileSize() {
        return 20 * 1024 * 1024; // 20MB para PDF
    }
    
    /**
     * Obtener información del PDF
     */
    public function getPDFInfo() {
        if (!$this->document) {
            return [];
        }
        
        $details = $this->document->getDetails();
        
        return [
            'title' => $details['Title'] ?? '',
            'author' => $details['Author'] ?? '',
            'creator' => $details['Creator'] ?? '',
            'producer' => $details['Producer'] ?? '',
            'creation_date' => $details['CreationDate'] ?? '',
            'modification_date' => $details['ModDate'] ?? '',
            'pages' => $this->document->getPages() ? count($this->document->getPages()) : 0,
            'text_length' => strlen($this->text_content),
            'file_size' => filesize($this->file_path),
            'voucher_type' => 'Martin Marietta Materials'
        ];
    }
    
    /**
     * Previsualizar contenido del PDF
     */
    public function previewContent($max_length = 1000) {
        try {
            if (!$this->document) {
                $this->document = $this->parser->parseFile($this->file_path);
                $this->text_content = $this->document->getText();
            }
            
            // Buscar líneas de datos válidas
            $lines = explode("\n", $this->text_content);
            $sample_data_lines = [];
            
            foreach ($lines as $line) {
                if (preg_match($this->extraction_patterns['martin_marietta_row'], trim($line))) {
                    $sample_data_lines[] = trim($line);
                    if (count($sample_data_lines) >= 5) break; // Solo 5 ejemplos
                }
            }
            
            $preview = [
                'text_sample' => substr($this->text_content, 0, $max_length),
                'total_text_length' => strlen($this->text_content),
                'is_martin_marietta' => $this->isMarinMariettaVoucher(),
                'sample_data_lines' => $sample_data_lines,
                'estimated_rows' => count($sample_data_lines) > 0 ? 
                    substr_count($this->text_content, ' PH ') : 0
            ];
            
            return $preview;
            
        } catch (Exception $e) {
            throw new Exception("Error previsualizando PDF: " . $e->getMessage());
        }
    }
    
    /**
     * Obtener estadísticas específicas de Martin Marietta
     */
    public function getMarinMariettaStats() {
        if (!$this->text_content) {
            return [];
        }
        
        $lines = explode("\n", $this->text_content);
        $total_rows = 0;
        $positive_rows = 0;
        $negative_rows = 0;
        $total_amount = 0;
        $total_weight = 0;
        
        foreach ($lines as $line) {
            if (preg_match($this->extraction_patterns['martin_marietta_row'], trim($line), $matches)) {
                $total_rows++;
                $weight = floatval($matches[6]);
                $amount = floatval($matches[9]);
                
                if ($weight > 0 && $amount > 0) {
                    $positive_rows++;
                    $total_weight += $weight;
                    $total_amount += $amount;
                } else {
                    $negative_rows++;
                }
            }
        }
        
        return [
            'total_rows_found' => $total_rows,
            'positive_rows' => $positive_rows,
            'negative_rows' => $negative_rows,
            'total_weight_tons' => $total_weight,
            'total_amount_dollars' => $total_amount,
            'average_rate' => $positive_rows > 0 ? ($total_amount / $total_weight) : 0
        ];
    }
    
    /**
     * Debug: Obtener líneas que coinciden con el patrón
     */
    public function debugGetMatchingLines($limit = 10) {
        $lines = explode("\n", $this->text_content);
        $matching_lines = [];
        
        foreach ($lines as $line_number => $line) {
            if (preg_match($this->extraction_patterns['martin_marietta_row'], trim($line), $matches)) {
                $matching_lines[] = [
                    'line_number' => $line_number + 1,
                    'content' => trim($line),
                    'matches' => $matches
                ];
                
                if (count($matching_lines) >= $limit) break;
            }
        }
        
        return $matching_lines;
    }
    
    /**
     * Limpiar recursos
     */
    public function __destruct() {
        unset($this->document);
        unset($this->parser);
    }
}