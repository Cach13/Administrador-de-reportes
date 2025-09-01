<?php
/**
 * Procesador específico para archivos Martin Marieta Materials
 * Extrae: Ship Date, Location, Ticket Number, Haul Rate, Quantity, Amount, Vehicle Number
 * Filtra por: Caracteres 4-6 del Vehicle Number para determinar empresa
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
        'ship_date' => 'trip_date',      // Ship Date → trip_date
        'location' => 'location',        // Location → location  
        'ticket_number' => 'ticket_number', // Ticket Number → ticket_number
        'haul_rate' => 'haul_rate',      // Haul Rate → haul_rate
        'quantity' => 'quantity',        // Quantity → quantity
        'amount' => 'amount',            // Amount → amount
        'vehicle_number' => 'vehicle_number' // Vehicle Number → vehicle_number
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
     * Extraer datos desde PDF Martin Marieta
     */
    private function extractFromPDF() {
        $parser = new Parser();
        $document = $parser->parseFile($this->file_path);
        $text = $document->getText();
        
        $extracted_data = [];
        $lines = explode("\n", $text);
        
        foreach ($lines as $line_number => $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Patrón específico para Martin Marieta PDF
            // Ejemplo: "07/14/2025 PLANT 001 H2648318 41142689 21.56 10.60 228.54 RMTJAV001"
            $pattern = '/(\d{2}\/\d{2}\/\d{4})\s+([A-Z\s]+)\s+([H]\w+)\s+(\d+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([A-Z0-9]{9})/';
            
            if (preg_match($pattern, $line, $matches)) {
                $extracted_data[] = [
                    'ship_date' => $this->parseDate($matches[1]),
                    'location' => trim($matches[2]),
                    'ticket_number' => $matches[3],
                    'reference_number' => $matches[4], // Campo adicional
                    'haul_rate' => floatval($matches[6]),
                    'quantity' => floatval($matches[5]),
                    'amount' => floatval($matches[7]),
                    'vehicle_number' => $matches[8],
                    'source_row' => $line_number + 1,
                    'source_line' => $line,
                    'confidence' => 0.95 // Alta confianza para patrón exacto
                ];
            }
        }
        
        return $extracted_data;
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
            'ship_date' => ['ship date', 'fecha', 'date', 'fecha envio'],
            'location' => ['location', 'ubicacion', 'lugar', 'planta'],
            'ticket_number' => ['ticket number', 'ticket', 'numero ticket', 'folio'],
            'haul_rate' => ['haul rate', 'rate', 'tarifa', 'precio'],
            'quantity' => ['quantity', 'cantidad', 'toneladas', 'tons'],
            'amount' => ['amount', 'monto', 'importe', 'total'],
            'vehicle_number' => ['vehicle number', 'vehicle', 'vehiculo', 'placa']
        ];
        
        $headers = ['columns' => [], 'header_row' => 1];
        $max_row_to_check = min(5, $worksheet->getHighestRow());
        
        for ($row = 1; $row <= $max_row_to_check; $row++) {
            $highest_col = $worksheet->getHighestColumn();
            $found_headers = 0;
            $temp_columns = [];
            
            for ($col = 'A'; $col <= $highest_col; $col++) {
                $cell_value = strtolower(trim($worksheet->getCell($col . $row)->getCalculatedValue() ?? ''));
                
                foreach ($possible_headers as $field => $variations) {
                    foreach ($variations as $variation) {
                        if ($cell_value === $variation || strpos($cell_value, $variation) !== false) {
                            $temp_columns[$field] = $col;
                            $found_headers++;
                            break 2;
                        }
                    }
                }
            }
            
            // Requerimos al menos 5 headers críticos
            if ($found_headers >= 5) {
                $headers['columns'] = $temp_columns;
                $headers['header_row'] = $row;
                break;
            }
        }
        
        return $headers;
    }
    
    /**
     * Filtrar datos por empresas seleccionadas
     * Extrae caracteres 4-6 del Vehicle Number para determinar empresa
     */
    private function filterByCompanies($raw_data) {
        if (empty($this->selected_companies)) {
            return $raw_data; // Si no hay filtro, devolver todo
        }
        
        $filtered_data = [];
        
        foreach ($raw_data as $row) {
            $vehicle_number = $row['vehicle_number'] ?? '';
            
            if (strlen($vehicle_number) >= 6) {
                // Extraer caracteres 4-6 (posiciones 3-5 en índice 0)
                $company_identifier = substr($vehicle_number, 3, 3);
                
                // Verificar si esta empresa está en las seleccionadas
                if (in_array($company_identifier, $this->selected_companies)) {
                    $row['company_identifier'] = $company_identifier;
                    $filtered_data[] = $row;
                }
            }
        }
        
        return $filtered_data;
    }
    
    /**
     * Guardar trips extraídos en la base de datos
     */
    private function saveTrips($filtered_data) {
        $saved_count = 0;
        
        foreach ($filtered_data as $row) {
            // Buscar company_id por identifier
            $company = $this->db->fetch(
                "SELECT id FROM companies WHERE identifier = ? AND is_active = 1",
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
                    'location' => $row['location'],
                    'ticket_number' => $row['ticket_number'],
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
    
    /**
     * Obtener empresas disponibles para selección
     */
    public static function getAvailableCompanies() {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT id, name, identifier, capital_percentage FROM companies WHERE is_active = 1 ORDER BY name"
        );
    }
    
    /**
     * Previsualizar datos antes de procesamiento
     */
    public function preview($max_rows = 10) {
        try {
            $raw_data = [];
            if ($this->file_info['file_format'] === 'pdf') {
                $raw_data = $this->extractFromPDF();
            } else {
                $raw_data = $this->extractFromExcel();
            }
            
            // Limitar resultados para preview
            $preview_data = array_slice($raw_data, 0, $max_rows);
            
            return [
                'success' => true,
                'total_rows' => count($raw_data),
                'preview_rows' => count($preview_data),
                'data' => $preview_data,
                'companies_found' => $this->getCompaniesFound($raw_data)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
?>