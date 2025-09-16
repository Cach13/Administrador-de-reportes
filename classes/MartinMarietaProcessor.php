<?php
/**
 * 🔧 ARREGLO CRÍTICO: MartinMarietaProcessor.php
 * ⚠️  PROBLEMA: Location y Ticket Number mal extraídos
 * ✅  SOLUCIÓN: Patrón regex correcto para formato Martin Marieta
 * 
 * ANTES: "PLANT 082", "T840437" (incorrecto)
 * DESPUÉS: "16431", "31733474" (correcto)
 * 
 * Ruta: /classes/MartinMarietaProcessor.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Logger.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Smalot\PdfParser\Parser;

class MartinMarietaProcessor {
    
    private $db;
    private $logger;
    private $voucher_id;
    private $file_path;
    private $file_info;
    private $selected_companies = [];
    
    // Mapeo de campos Martin Marieta
    private $field_mapping = [
        'ship_date' => 'trip_date',
        'location' => 'location',
        'ticket_number' => 'ticket_number',
        'haul_rate' => 'haul_rate',
        'quantity' => 'quantity',
        'amount' => 'amount',
        'vehicle_number' => 'vehicle_number'
    ];
    
    public function __construct($voucher_id, $selected_companies = []) {
        $this->db = Database::getInstance();
        $this->logger = new Logger();
        $this->voucher_id = $voucher_id;
        $this->selected_companies = $selected_companies;
        
        $this->loadVoucherInfo();
    }
    
    /**
     * Cargar información del voucher
     */
    private function loadVoucherInfo() {
        $sql = "SELECT * FROM vouchers WHERE id = ?";
        $this->file_info = $this->db->fetch($sql, [$this->voucher_id]);
        
        if (!$this->file_info) {
            throw new Exception("Voucher no encontrado: {$this->voucher_id}");
        }
        
        $this->file_path = $this->file_info['file_path'];
        
        if (!file_exists($this->file_path)) {
            throw new Exception("Archivo no encontrado: {$this->file_path}");
        }
    }
    
    /**
     * Procesar archivo Martin Marieta
     */
    public function process() {
        try {
            $this->updateVoucherStatus('processing');
            $this->logger->log(null, 'PROCESSING_START', "Iniciando procesamiento Martin Marieta: {$this->voucher_id}");
            
            // Determinar tipo de archivo y extraer datos
            $raw_data = [];
            if ($this->file_info['file_format'] === 'pdf') {
                $raw_data = $this->extractFromPDF();
            } else {
                $raw_data = $this->extractFromExcel();
            }
            
            $this->logger->log(null, 'EXTRACTION_COMPLETE', "Extraídos " . count($raw_data) . " registros raw");
            
            // Filtrar por empresas seleccionadas
            $filtered_data = $this->filterByCompanies($raw_data);
            $this->logger->log(null, 'FILTERING_COMPLETE', "Filtrados " . count($filtered_data) . " registros por empresa");
            
            // Normalizar y guardar datos
            $saved_trips = $this->saveTrips($filtered_data);
            
            // Actualizar estadísticas del voucher
            $this->updateVoucherStats($raw_data, $filtered_data, $saved_trips);
            
            $this->updateVoucherStatus('processed');
            $this->logger->log(null, 'PROCESSING_COMPLETE', "Procesamiento completado: {$saved_trips} trips guardados");
            
            return [
                'success' => true,
                'total_rows' => count($raw_data),
                'filtered_rows' => count($filtered_data),
                'saved_trips' => $saved_trips,
                'companies_found' => $this->getCompaniesFound($raw_data)
            ];
            
        } catch (Exception $e) {
            $this->updateVoucherStatus('error');
            $this->logger->log(null, 'PROCESSING_ERROR', "Error procesamiento: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 🔧 MÉTODO CORREGIDO: Extraer datos desde PDF Martin Marieta
     * ✅ PATRÓN ACTUALIZADO para formato real del voucher
     */
    private function extractFromPDF() {
        $parser = new Parser();
        $document = $parser->parseFile($this->file_path);
        $text = $document->getText();
        
        $extracted_data = [];
        $lines = explode("\n", $text);
        
        $this->logger->log(null, 'PDF_EXTRACTION_DEBUG', "Iniciando extracción PDF con " . count($lines) . " líneas");
        
        foreach ($lines as $line_number => $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // 🔧 PATRÓN CORREGIDO para formato Martin Marieta real:
            // Ejemplo: "02 16431 PH 15089949 08/22/2025 31733474 0 RMTMVT007 28.53 8.32 TN 237.37"
            // Grupos: $1=oper, $2=location, $3=doc_type, $4=doc_number, $5=ship_date, $6=ticket_number, $7=replaced, $8=vehicle, $9=haul_rate, $10=quantity, $11=uom, $12=amount
            
            $pattern = '/^(\d{2})\s+(\d{4,6})\s+([A-Z]{2})\s+(\d+)\s+(\d{2}\/\d{2}\/\d{4})\s+(\d+)\s+(\d+)\s+([A-Z0-9]{9})\s+([\d.]+)\s+([\d.]+)\s+([A-Z]{2})\s+([\d.]+)$/';
            
            if (preg_match($pattern, $line, $matches)) {
                // ✅ EXTRACCIÓN CORRECTA con índices actualizados
                $extracted_row = [
                    'ship_date' => $this->parseDate($matches[5]),      // Posición 5: "08/22/2025"
                    'location' => $matches[2],                         // ✅ Posición 2: "16431" (CORRECTO)
                    'ticket_number' => $matches[6],                    // ✅ Posición 6: "31733474" (CORRECTO)
                    'haul_rate' => floatval($matches[9]),             // Posición 9: "28.53"
                    'quantity' => floatval($matches[10]),             // Posición 10: "8.32"
                    'amount' => floatval($matches[12]),               // Posición 12: "237.37"
                    'vehicle_number' => $matches[8],                   // Posición 8: "RMTMVT007"
                    'source_row' => $line_number + 1,
                    'source_line' => $line,
                    'confidence' => 0.95,
                    'oper_code' => $matches[1],                       // Extra: "02"
                    'doc_type' => $matches[3],                        // Extra: "PH"
                    'doc_number' => $matches[4],                      // Extra: "15089949"
                    'uom' => $matches[11]                             // Extra: "TN"
                ];
                
                $extracted_data[] = $extracted_row;
                
                // 🔍 DEBUG: Log de extracción exitosa
                $this->logger->log(null, 'PDF_ROW_EXTRACTED', 
                    "Línea {$line_number}: Location={$matches[2]}, Ticket={$matches[6]}, Vehicle={$matches[8]}, Amount={$matches[12]}");
            } else {
                // 🔍 DEBUG: Log de líneas no coincidentes (solo para debugging)
                if (strpos($line, 'RMTMVT') !== false || strpos($line, 'RMTCTL') !== false || strpos($line, 'RMTJAV') !== false) {
                    $this->logger->log(null, 'PDF_PATTERN_MISMATCH', "Línea {$line_number} no coincide: {$line}");
                }
            }
        }
        
        $this->logger->log(null, 'PDF_EXTRACTION_COMPLETE', "Extraídos " . count($extracted_data) . " registros del PDF");
        
        return $extracted_data;
    }
    
    /**
     * 🔧 MÉTODO ACTUALIZADO: Búsqueda de patrones alternativos para casos edge
     */
    private function extractAlternativePatterns($lines) {
        $alternative_data = [];
        
        foreach ($lines as $line_number => $line) {
            $line = trim($line);
            
            // Patrón alternativo sin oper code al inicio
            $alt_pattern = '/(\d{4,6})\s+([A-Z]{2})\s+(\d+)\s+(\d{2}\/\d{2}\/\d{4})\s+(\d+)\s+(\d+)\s+([A-Z0-9]{9})\s+([\d.]+)\s+([\d.]+)\s+([A-Z]{2})\s+([\d.]+)/';
            
            if (preg_match($alt_pattern, $line, $matches)) {
                $alternative_data[] = [
                    'ship_date' => $this->parseDate($matches[4]),
                    'location' => $matches[1],           // Location
                    'ticket_number' => $matches[5],      // Ticket Number
                    'haul_rate' => floatval($matches[8]),
                    'quantity' => floatval($matches[9]),
                    'amount' => floatval($matches[11]),
                    'vehicle_number' => $matches[7],
                    'source_row' => $line_number + 1,
                    'source_line' => $line,
                    'confidence' => 0.85  // Menor confianza para patrón alternativo
                ];
            }
        }
        
        return $alternative_data;
    }
    
    /**
     * Extraer datos desde Excel Martin Marieta
     */
    private function extractFromExcel() {
        $spreadsheet = IOFactory::load($this->file_path);
        $worksheet = $spreadsheet->getActiveSheet();
        
        // Buscar headers
        $headers = $this->findExcelHeaders($worksheet);
        if (empty($headers)) {
            throw new Exception("No se encontraron headers válidos en el archivo Excel");
        }
        
        $extracted_data = [];
        $highest_row = $worksheet->getHighestRow();
        
        for ($row = $headers['header_row'] + 1; $row <= $highest_row; $row++) {
            $row_data = [];
            
            foreach ($headers['columns'] as $field => $col) {
                $cell_value = $worksheet->getCell($col . $row)->getCalculatedValue();
                $row_data[$field] = $cell_value;
            }
            
            // Validar que la fila tenga datos mínimos requeridos
            if (!empty($row_data['vehicle_number']) && !empty($row_data['amount'])) {
                $extracted_data[] = [
                    'ship_date' => $this->parseDate($row_data['ship_date'] ?? ''),
                    'location' => trim($row_data['location'] ?? ''),
                    'ticket_number' => trim($row_data['ticket_number'] ?? ''),
                    'haul_rate' => floatval($row_data['haul_rate'] ?? 0),
                    'quantity' => floatval($row_data['quantity'] ?? 0),
                    'amount' => floatval($row_data['amount'] ?? 0),
                    'vehicle_number' => trim($row_data['vehicle_number'] ?? ''),
                    'source_row' => $row,
                    'confidence' => 0.90
                ];
            }
        }
        
        return $extracted_data;
    }
    
    /**
     * Buscar headers en Excel
     */
    private function findExcelHeaders($worksheet) {
        $possible_headers = [
            'ship_date' => ['Ship Date', 'Date', 'Ship_Date', 'Fecha'],
            'location' => ['Location', 'Oper Location', 'Location Description', 'Ubicación'],
            'ticket_number' => ['Ticket Number', 'Ticket', 'Ticket_Number', 'Numero_Ticket'],
            'haul_rate' => ['Haul Rate', 'Rate', 'Haul_Rate', 'Tarifa'],
            'quantity' => ['Quantity', 'Qty', 'Amount', 'Cantidad'],
            'amount' => ['Amount', 'Total', 'Monto'],
            'vehicle_number' => ['Vehicle Number', 'Vehicle', 'Vehicle_Number', 'Vehiculo']
        ];
        
        $found_headers = [];
        $header_row = 1;
        
        // Buscar en las primeras 10 filas
        for ($row = 1; $row <= 10; $row++) {
            $headers_in_row = [];
            
            for ($col = 'A'; $col <= 'Z'; $col++) {
                $cell_value = trim($worksheet->getCell($col . $row)->getValue());
                
                foreach ($possible_headers as $field => $header_variants) {
                    foreach ($header_variants as $variant) {
                        if (strcasecmp($cell_value, $variant) === 0) {
                            $headers_in_row[$field] = $col;
                            break 2;
                        }
                    }
                }
            }
            
            if (count($headers_in_row) >= 4) { // Al menos 4 headers encontrados
                $found_headers = $headers_in_row;
                $header_row = $row;
                break;
            }
        }
        
        return [
            'columns' => $found_headers,
            'header_row' => $header_row
        ];
    }
    
    /**
     * Filtrar datos por empresas seleccionadas
     */
    private function filterByCompanies($raw_data) {
        if (empty($this->selected_companies)) {
            return $raw_data; // Si no hay filtro, devolver todos
        }
        
        $filtered_data = [];
        
        foreach ($raw_data as $row) {
            $vehicle_number = $row['vehicle_number'] ?? '';
            
            if (strlen($vehicle_number) >= 6) {
                $company_identifier = substr($vehicle_number, 3, 3); // Caracteres 4-6
                
                if (in_array($company_identifier, $this->selected_companies)) {
                    $row['company_identifier'] = $company_identifier;
                    $filtered_data[] = $row;
                }
            }
        }
        
        return $filtered_data;
    }
    
    /**
     * Guardar trips en base de datos
     */
    private function saveTrips($filtered_data) {
        if (empty($filtered_data)) {
            return 0;
        }
        
        $saved_count = 0;
        
        foreach ($filtered_data as $row) {
            // Buscar empresa por identificador
            $company = $this->db->fetch(
                "SELECT * FROM companies WHERE company_identifier = ? AND is_active = 1",
                [$row['company_identifier']]
            );
            
            if (!$company) {
                $this->logger->log(null, 'COMPANY_NOT_FOUND', "Empresa no encontrada: " . $row['company_identifier']);
                continue;
            }
            
            // Insertar trip
            try {
                $trip_data = [
                    'voucher_id' => $this->voucher_id,
                    'company_id' => $company['id'],
                    'trip_date' => $row['ship_date'],
                    'location' => $row['location'],              // ✅ AHORA CORRECTO: "16431"
                    'ticket_number' => $row['ticket_number'],    // ✅ AHORA CORRECTO: "31733474"
                    'haul_rate' => $row['haul_rate'],
                    'quantity' => $row['quantity'],
                    'amount' => $row['amount'],
                    'vehicle_number' => $row['vehicle_number'],
                    'source_row_number' => $row['source_row'] ?? null,
                    'extraction_confidence' => $row['confidence'] ?? 0.90
                ];
                
                $trip_id = $this->db->insert('trips', $trip_data);
                if ($trip_id) {
                    $saved_count++;
                    $this->logger->log(null, 'TRIP_SAVED', 
                        "Trip guardado: Location={$row['location']}, Ticket={$row['ticket_number']}, Vehicle={$row['vehicle_number']}");
                }
                
            } catch (Exception $e) {
                $this->logger->log(null, 'TRIP_SAVE_ERROR', "Error guardando trip: " . $e->getMessage());
            }
        }
        
        return $saved_count;
    }
    
    /**
     * Actualizar estadísticas del voucher
     */
    private function updateVoucherStats($raw_data, $filtered_data, $saved_trips) {
        $update_data = [
            'total_rows_found' => count($raw_data),
            'valid_rows_extracted' => count($filtered_data),
            'rows_with_errors' => count($raw_data) - count($filtered_data),
            'extraction_confidence' => $this->calculateAverageConfidence($filtered_data),
            'processing_completed_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->update('vouchers', $update_data, 'id = ?', [$this->voucher_id]);
    }
    
    /**
     * Obtener empresas encontradas en los datos
     */
    private function getCompaniesFound($raw_data) {
        $companies = [];
        
        foreach ($raw_data as $row) {
            $vehicle_number = $row['vehicle_number'] ?? '';
            if (strlen($vehicle_number) >= 6) {
                $identifier = substr($vehicle_number, 3, 3);
                if (!in_array($identifier, $companies)) {
                    $companies[] = $identifier;
                }
            }
        }
        
        return $companies;
    }
    
    /**
     * Calcular confianza promedio
     */
    private function calculateAverageConfidence($data) {
        if (empty($data)) return 0.00;
        
        $total = array_sum(array_column($data, 'confidence'));
        return round($total / count($data), 2);
    }
    
    /**
     * Parsear fecha en diferentes formatos
     */
    private function parseDate($date_string) {
        if (empty($date_string)) return null;
        
        // Si es timestamp de Excel
        if (is_numeric($date_string)) {
            return Date::excelToDateTimeObject($date_string)->format('Y-m-d');
        }
        
        // Formatos comunes
        $formats = ['m/d/Y', 'd/m/Y', 'Y-m-d', 'd-m-Y'];
        
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $date_string);
            if ($date && $date->format($format) === $date_string) {
                return $date->format('Y-m-d');
            }
        }
        
        return null;
    }
    
    /**
     * Actualizar status del voucher
     */
    private function updateVoucherStatus($status) {
        $update_data = ['status' => $status];
        
        if ($status === 'processing') {
            $update_data['processing_started_at'] = date('Y-m-d H:i:s');
        } elseif ($status === 'processed') {
            $update_data['processing_completed_at'] = date('Y-m-d H:i:s');
        }
        
        $this->db->update('vouchers', $update_data, 'id = ?', [$this->voucher_id]);
    }
}