<?php
/**
 * MartinMarietaProcessor.php
 * Procesador específico para archivos Martin Marieta Materials
 * 
 * Extrae: Ship Date, Location, Ticket Number, Haul Rate, Quantity, Amount, Vehicle Number
 * Filtra por: Caracteres 4-6 del Vehicle Number para determinar empresa
 * Soporta: PDF únicamente
 * 
 * Ejemplo de línea PDF:
 * "02 16431 PH 15089949 08/22/2025 31733474 0 RMTMVT007 28.53 8.32 TN 237.37"
 * 
 * Transport Management System
 * UBICACIÓN: classes/MartinMarietaProcessor.php
 */

// Incluir dependencias necesarias
require_once __DIR__ . '/../vendor/autoload.php';

use Smalot\PdfParser\Parser;

class MartinMarietaProcessor {
    
    private $voucher_id;
    private $file_path;
    private $file_info;
    private $db;
    private $extracted_data;
    private $stats;
    
    // Patrón regex para líneas de datos Martin Marieta
    // Formato: "02 16431 PH 15089949 08/22/2025 31733474 0 RMTMVT007 28.53 8.32 TN 237.37"
    private $pdf_pattern = '/^(\d{2})\s+(\d{4,6})\s+([A-Z]{2})\s+(\d+)\s+(\d{2}\/\d{2}\/\d{4})\s+(\d+)\s+(\d+)\s+([A-Z0-9]{9})\s+([\d.]+)\s+([\d.]+)\s+([A-Z]{2})\s+([\d.]+)$/';
    
    // Patrón alternativo sin código operador al inicio
    private $pdf_alt_pattern = '/(\d{4,6})\s+([A-Z]{2})\s+(\d+)\s+(\d{2}\/\d{2}\/\d{4})\s+(\d+)\s+(\d+)\s+([A-Z0-9]{9})\s+([\d.]+)\s+([\d.]+)\s+([A-Z]{2})\s+([\d.]+)/';
    
    public function __construct($voucher_id) {
        $this->voucher_id = $voucher_id;
        $this->db = Database::getInstance();
        $this->extracted_data = [];
        $this->stats = [
            'total_rows_found' => 0,
            'valid_rows_extracted' => 0,
            'rows_with_errors' => 0,
            'companies_found' => [],
            'extraction_confidence' => 0.0
        ];
        
        // Obtener información del voucher
        $this->file_info = $this->db->selectOne(
            "SELECT * FROM vouchers WHERE id = ?",
            [$voucher_id]
        );
        
        if (!$this->file_info) {
            throw new Exception("Voucher no encontrado: {$voucher_id}");
        }
        
        $this->file_path = $this->file_info['file_path'];
        
        if (!file_exists($this->file_path)) {
            throw new Exception("Archivo no encontrado: {$this->file_path}");
        }
    }
    
    /**
     * Procesar archivo PDF y extraer datos
     */
    public function process() {
        try {
            // Validar que sea PDF
            if ($this->file_info['file_format'] !== 'pdf') {
                throw new Exception("Solo se soportan archivos PDF");
            }
            
            // Marcar como en procesamiento
            $this->updateVoucherStatus('processing');
            
            logMessage('INFO', "Iniciando procesamiento de voucher PDF: {$this->voucher_id}");
            
            // Extraer datos del PDF
            $this->extracted_data = $this->extractFromPDF();
            
            // Calcular estadísticas
            $this->calculateStats();
            
            // Guardar en base de datos
            $saved_count = $this->saveTripsToDatabase();
            
            // Actualizar voucher con estadísticas
            $this->updateVoucherWithStats();
            
            // Marcar como procesado
            $this->updateVoucherStatus('processed');
            
            logMessage('INFO', "Procesamiento completado. Extraídos: {$this->stats['valid_rows_extracted']}, Guardados: {$saved_count}");
            
            return [
                'success' => true,
                'voucher_id' => $this->voucher_id,
                'stats' => $this->stats,
                'data' => $this->extracted_data,
                'saved_count' => $saved_count
            ];
            
        } catch (Exception $e) {
            $this->updateVoucherStatus('error', $e->getMessage());
            logMessage('ERROR', "Error procesando voucher {$this->voucher_id}: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'voucher_id' => $this->voucher_id
            ];
        }
    }
    
    /**
     * Extraer datos desde PDF Martin Marieta
     */
    private function extractFromPDF() {
        $parser = new Parser();
        $pdf = $parser->parseFile($this->file_path);
        $text = $pdf->getText();
        
        $extracted_data = [];
        $lines = explode("\n", $text);
        
        logMessage('INFO', "PDF contiene " . count($lines) . " líneas para analizar");
        
        foreach ($lines as $line_number => $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $this->stats['total_rows_found']++;
            
            // Intentar patrón principal
            if (preg_match($this->pdf_pattern, $line, $matches)) {
                $row_data = $this->parsePDFMatches($matches, $line_number, $line, 0.95);
                if ($row_data) {
                    $extracted_data[] = $row_data;
                    $this->stats['valid_rows_extracted']++;
                }
            }
            // Intentar patrón alternativo
            elseif (preg_match($this->pdf_alt_pattern, $line, $matches)) {
                $row_data = $this->parsePDFMatchesAlt($matches, $line_number, $line, 0.85);
                if ($row_data) {
                    $extracted_data[] = $row_data;
                    $this->stats['valid_rows_extracted']++;
                }
            }
            // Línea no reconocida
            else {
                // Solo contar como error si parece contener datos de vehículo
                if (preg_match('/[A-Z0-9]{9}/', $line)) {
                    $this->stats['rows_with_errors']++;
                    logMessage('DEBUG', "Línea no reconocida {$line_number}: {$line}");
                }
            }
        }
        
        return $extracted_data;
    }
    
    /**
     * Parsear matches del patrón principal PDF
     */
    private function parsePDFMatches($matches, $line_number, $line, $confidence) {
        try {
            $row_data = [
                'ship_date' => $this->parseDate($matches[5]),        // Posición 5: "08/22/2025"
                'location' => $matches[2],                           // Posición 2: "16431"
                'ticket_number' => $matches[6],                      // Posición 6: "31733474"
                'haul_rate' => floatval($matches[9]),               // Posición 9: "28.53"
                'quantity' => floatval($matches[10]),               // Posición 10: "8.32"
                'amount' => floatval($matches[12]),                 // Posición 12: "237.37"
                'vehicle_number' => $matches[8],                     // Posición 8: "RMTMVT007"
                'source_row' => $line_number + 1,
                'source_line' => $line,
                'confidence' => $confidence,
                // Datos adicionales para debugging
                'oper_code' => $matches[1],                         // "02"
                'doc_type' => $matches[3],                          // "PH"
                'doc_number' => $matches[4],                        // "15089949"
                'replaced' => $matches[7],                          // "0"
                'uom' => $matches[11]                               // "TN"
            ];
            
            // Validar datos críticos
            if (!$this->validateRowData($row_data)) {
                $this->stats['rows_with_errors']++;
                return null;
            }
            
            // Extraer identificador de empresa del vehicle number
            $company_identifier = $this->extractCompanyIdentifier($row_data['vehicle_number']);
            if ($company_identifier) {
                $row_data['company_identifier'] = $company_identifier;
                $this->stats['companies_found'][$company_identifier] = 
                    ($this->stats['companies_found'][$company_identifier] ?? 0) + 1;
            }
            
            return $row_data;
            
        } catch (Exception $e) {
            $this->stats['rows_with_errors']++;
            logMessage('ERROR', "Error parseando línea {$line_number}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Parsear matches del patrón alternativo PDF
     */
    private function parsePDFMatchesAlt($matches, $line_number, $line, $confidence) {
        try {
            $row_data = [
                'ship_date' => $this->parseDate($matches[4]),        // Posición 4
                'location' => $matches[1],                           // Posición 1
                'ticket_number' => $matches[5],                      // Posición 5
                'haul_rate' => floatval($matches[8]),               // Posición 8
                'quantity' => floatval($matches[9]),                // Posición 9
                'amount' => floatval($matches[11]),                 // Posición 11
                'vehicle_number' => $matches[7],                     // Posición 7
                'source_row' => $line_number + 1,
                'source_line' => $line,
                'confidence' => $confidence
            ];
            
            if (!$this->validateRowData($row_data)) {
                $this->stats['rows_with_errors']++;
                return null;
            }
            
            $company_identifier = $this->extractCompanyIdentifier($row_data['vehicle_number']);
            if ($company_identifier) {
                $row_data['company_identifier'] = $company_identifier;
                $this->stats['companies_found'][$company_identifier] = 
                    ($this->stats['companies_found'][$company_identifier] ?? 0) + 1;
            }
            
            return $row_data;
            
        } catch (Exception $e) {
            $this->stats['rows_with_errors']++;
            return null;
        }
    }
    
    /**
     * Extraer identificador de empresa del vehicle number
     * Caracteres 4-6 del Vehicle Number (posiciones 3-5 en índice base 0)
     */
    private function extractCompanyIdentifier($vehicle_number) {
        if (strlen($vehicle_number) < 6) {
            return null;
        }
        
        return substr($vehicle_number, 3, 3); // Caracteres 4-6
    }
    
    /**
     * Validar datos de una fila
     */
    private function validateRowData($row_data) {
        // Campos requeridos
        $required_fields = ['ship_date', 'vehicle_number', 'amount'];
        
        foreach ($required_fields as $field) {
            if (!isset($row_data[$field]) || empty($row_data[$field])) {
                return false;
            }
        }
        
        // Validar vehicle number (debe ser exactamente 9 caracteres)
        if (strlen($row_data['vehicle_number']) !== 9) {
            return false;
        }
        
        // Validar que amount sea mayor a 0
        if ($row_data['amount'] <= 0) {
            return false;
        }
        
        // Validar fecha
        if (!$row_data['ship_date'] || $row_data['ship_date'] === '0000-00-00') {
            return false;
        }
        
        return true;
    }
    
    /**
     * Parsear fecha a formato MySQL
     */
    private function parseDate($date_string) {
        try {
            if (empty($date_string)) return null;
            
            // Intentar varios formatos
            $formats = ['m/d/Y', 'Y-m-d', 'd/m/Y', 'm-d-Y'];
            
            foreach ($formats as $format) {
                $date = DateTime::createFromFormat($format, $date_string);
                if ($date !== false) {
                    return $date->format('Y-m-d');
                }
            }
            
            // Último intento con strtotime
            $timestamp = strtotime($date_string);
            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }
            
            return null;
            
        } catch (Exception $e) {
            logMessage('ERROR', "Error parseando fecha '{$date_string}': " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Calcular estadísticas de extracción
     */
    private function calculateStats() {
        $total_extracted = $this->stats['valid_rows_extracted'];
        $total_found = $this->stats['total_rows_found'];
        
        if ($total_found > 0) {
            $this->stats['extraction_confidence'] = round(($total_extracted / $total_found), 2);
        }
        
        logMessage('INFO', "Estadísticas calculadas: " . json_encode($this->stats));
    }
    
    /**
     * Guardar trips en base de datos
     */
    private function saveTripsToDatabase() {
        if (empty($this->extracted_data)) {
            return 0;
        }
        
        $saved_count = 0;
        
        foreach ($this->extracted_data as $row_data) {
            if (!isset($row_data['company_identifier'])) {
                continue;
            }
            
            // Buscar empresa por identificador
            $company = $this->db->selectOne(
                "SELECT id FROM companies WHERE identifier = ? AND is_active = 1",
                [$row_data['company_identifier']]
            );
            
            if (!$company) {
                logMessage('WARNING', "Empresa no encontrada para identificador: {$row_data['company_identifier']}");
                continue;
            }
            
            try {
                $trip_data = [
                    'voucher_id' => $this->voucher_id,
                    'company_id' => $company['id'],
                    'trip_date' => $row_data['ship_date'],
                    'location' => $row_data['location'] ?? '',
                    'ticket_number' => $row_data['ticket_number'] ?? '',
                    'haul_rate' => $row_data['haul_rate'] ?? 0,
                    'quantity' => $row_data['quantity'] ?? 0,
                    'amount' => $row_data['amount'],
                    'vehicle_number' => $row_data['vehicle_number'],
                    'source_row_number' => $row_data['source_row'] ?? 0,
                    'extraction_confidence' => $row_data['confidence']
                ];
                
                $trip_id = $this->db->insert(
                    "INSERT INTO trips (voucher_id, company_id, trip_date, location, ticket_number, haul_rate, quantity, amount, vehicle_number, source_row_number, extraction_confidence) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    array_values($trip_data)
                );
                
                if ($trip_id) {
                    $saved_count++;
                }
                
            } catch (Exception $e) {
                logMessage('ERROR', "Error guardando trip: " . $e->getMessage());
                $this->stats['rows_with_errors']++;
            }
        }
        
        return $saved_count;
    }
    
    /**
     * Actualizar voucher con estadísticas
     */
    private function updateVoucherWithStats() {
        $update_data = [
            'total_rows_found' => $this->stats['total_rows_found'],
            'valid_rows_extracted' => $this->stats['valid_rows_extracted'],
            'rows_with_errors' => $this->stats['rows_with_errors'],
            'extraction_confidence' => $this->stats['extraction_confidence'],
            'processing_completed_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->update(
            "UPDATE vouchers SET total_rows_found = ?, valid_rows_extracted = ?, rows_with_errors = ?, extraction_confidence = ?, processing_completed_at = ? WHERE id = ?",
            array_merge(array_values($update_data), [$this->voucher_id])
        );
    }
    
    /**
     * Actualizar status del voucher
     */
    private function updateVoucherStatus($status, $notes = null) {
        $update_data = ['status' => $status];
        
        if ($status === 'processing') {
            $update_data['processing_started_at'] = date('Y-m-d H:i:s');
        }
        
        if ($notes) {
            $update_data['processing_notes'] = $notes;
        }
        
        $set_clause = implode(' = ?, ', array_keys($update_data)) . ' = ?';
        $this->db->update(
            "UPDATE vouchers SET {$set_clause} WHERE id = ?",
            array_merge(array_values($update_data), [$this->voucher_id])
        );
    }
    
    /**
     * Obtener preview de datos extraídos (sin guardar en BD)
     */
    public function preview($max_rows = 10) {
        try {
            $raw_data = $this->extractFromPDF();
            
            $this->calculateStats();
            
            // Limitar resultados para preview
            $preview_data = array_slice($raw_data, 0, $max_rows);
            
            return [
                'success' => true,
                'voucher_id' => $this->voucher_id,
                'stats' => $this->stats,
                'total_rows' => count($raw_data),
                'preview_rows' => count($preview_data),
                'data' => $preview_data,
                'companies_found' => array_keys($this->stats['companies_found'])
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'voucher_id' => $this->voucher_id
            ];
        }
    }
}
?>