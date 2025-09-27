<?php
/**
 * MartinMarietaProcessor.php - VERSIÓN FINAL CORREGIDA
 * Procesador específico para archivos Martin Marieta Materials
 * 
 * FUNCIONALIDADES:
 * - Extrae datos de PDF Martin Marieta
 * - Auto-crea empresas que no existen
 * - Preview sin guardar datos
 * - Procesamiento completo con guardado en BD
 * 
 * Transport Management System
 * UBICACIÓN: classes/MartinMarietaProcessor.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/Database.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Smalot\PdfParser\Parser;

class MartinMarietaProcessor {
    
    private $db;
    private $voucher_id;
    private $file_path;
    private $file_info;
    private $selected_companies = [];
    private $extracted_data = [];
    private $stats = [];
    
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
        $this->voucher_id = $voucher_id;
        $this->selected_companies = $selected_companies;
        $this->extracted_data = [];
        $this->stats = [
            'total_rows_found' => 0,
            'valid_rows_extracted' => 0,
            'rows_with_errors' => 0,
            'companies_found' => [],
            'extraction_confidence' => 0.0
        ];
        
        $this->loadVoucherInfo();
    }
    
    /**
     * Preview de datos sin guardar en BD (para mostrar en interfaz)
     */
    public function preview($limit = 20) {
        try {
            logMessage('INFO', "=== INICIO PREVIEW ===");
            logMessage('INFO', "Voucher ID: {$this->voucher_id}");
            logMessage('INFO', "File path: {$this->file_path}");
            logMessage('INFO', "File exists: " . (file_exists($this->file_path) ? 'YES' : 'NO'));
            
            // Determinar tipo de archivo y extraer datos
            $raw_data = [];
            if ($this->file_info['file_format'] === 'pdf') {
                logMessage('INFO', "Procesando como PDF");
                $raw_data = $this->extractFromPDF();
            } else {
                logMessage('INFO', "Procesando como Excel");
                $raw_data = $this->extractFromExcel();
            }
            
            logMessage('INFO', "Raw data extraída: " . count($raw_data) . " registros");
            
            // Filtrar por empresas seleccionadas (si las hay)
            $filtered_data = $this->filterByCompanies($raw_data);
            logMessage('INFO', "Filtered data: " . count($filtered_data) . " registros");
            
            // Limitar resultados para preview
            $preview_data = array_slice($filtered_data, 0, $limit);
            logMessage('INFO', "Preview data: " . count($preview_data) . " registros");
            
            // Obtener empresas encontradas
            $companies_found = $this->getCompaniesFound($raw_data);
            logMessage('INFO', "Empresas encontradas: " . implode(', ', $companies_found));
            
            logMessage('INFO', "=== FIN PREVIEW ===");
            
            return [
                'success' => true,
                'total_rows' => count($raw_data),
                'filtered_rows' => count($filtered_data),
                'saved_trips' => 0, // No se guardan en preview
                'companies_found' => $companies_found,
                'data' => $preview_data,
                'stats' => [
                    'total_rows_found' => count($raw_data),
                    'valid_rows_extracted' => count($filtered_data),
                    'rows_with_errors' => count($raw_data) - count($filtered_data),
                    'extraction_confidence' => $this->calculateAverageConfidence($filtered_data),
                    'companies_found' => $companies_found
                ]
            ];
            
        } catch (Exception $e) {
            logMessage('ERROR', "Error en preview: " . $e->getMessage());
            logMessage('ERROR', "Stack trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'voucher_id' => $this->voucher_id
            ];
        }
    }
    
    /**
     * Cargar información del voucher
     */
    private function loadVoucherInfo() {
        $sql = "SELECT * FROM vouchers WHERE id = ?";
        $this->file_info = $this->db->selectOne($sql, [$this->voucher_id]);
        
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
            logMessage('INFO', "Iniciando procesamiento Martin Marieta: {$this->voucher_id}");
            
            // Determinar tipo de archivo y extraer datos
            $raw_data = [];
            if ($this->file_info['file_format'] === 'pdf') {
                $raw_data = $this->extractFromPDF();
            } else {
                $raw_data = $this->extractFromExcel();
            }
            
            logMessage('INFO', "Extraídos " . count($raw_data) . " registros raw");
            
            // Filtrar por empresas seleccionadas
            $filtered_data = $this->filterByCompanies($raw_data);
            logMessage('INFO', "Filtrados " . count($filtered_data) . " registros por empresa");
            
            // Asignar a la propiedad de clase para usar en saveTripsToDatabase()
            $this->extracted_data = $filtered_data;
            
            // Normalizar y guardar datos CON AUTO-CREACIÓN DE EMPRESAS
            $saved_trips = $this->saveTripsToDatabase();
            
            // Actualizar estadísticas del voucher
            $this->updateVoucherStats($raw_data, $filtered_data, $saved_trips);
            
            $this->updateVoucherStatus('processed');
            logMessage('INFO', "Procesamiento completado: {$saved_trips} trips guardados");
            
            return [
                'success' => true,
                'total_rows' => count($raw_data),
                'filtered_rows' => count($filtered_data),
                'saved_trips' => $saved_trips,
                'companies_found' => $this->getCompaniesFound($raw_data),
                'stats' => [
                    'total_rows_found' => count($raw_data),
                    'valid_rows_extracted' => count($filtered_data),
                    'rows_with_errors' => count($raw_data) - count($filtered_data),
                    'extraction_confidence' => $this->calculateAverageConfidence($filtered_data),
                    'companies_found' => $this->getCompaniesFound($raw_data)
                ]
            ];
            
        } catch (Exception $e) {
            $this->updateVoucherStatus('error');
            logMessage('ERROR', "Error procesamiento: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * MÉTODO CORREGIDO: Extraer datos desde PDF Martin Marieta
     */
    private function extractFromPDF() {
        try {
            logMessage('INFO', "=== INICIO extractFromPDF ===");
            
            $parser = new Parser();
            $document = $parser->parseFile($this->file_path);
            $text = $document->getText();
            
            logMessage('INFO', "PDF parseado correctamente");
            logMessage('INFO', "Texto total length: " . strlen($text));
            
            $extracted_data = [];
            $lines = explode("\n", $text);
            
            logMessage('INFO', "Total líneas en PDF: " . count($lines));
            
            // DEBUG: Mostrar las primeras 100 líneas para identificar el formato real
            logMessage('INFO', "=== PRIMERAS 100 LÍNEAS DEL PDF ===");
            for ($i = 0; $i < min(100, count($lines)); $i++) {
                $line = trim($lines[$i]);
                if (!empty($line)) {
                    logMessage('DEBUG', "Línea {$i}: [{$line}]");
                }
            }
            logMessage('INFO', "=== FIN DEBUG LÍNEAS ===");
            
            // Buscar líneas que contengan vehículos (RMT...)
            logMessage('INFO', "=== BUSCANDO LÍNEAS CON VEHÍCULOS ===");
            foreach ($lines as $line_number => $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                // Si la línea contiene RMT (vehículos), mostrarla
                if (strpos($line, 'RMT') !== false) {
                    logMessage('DEBUG', "LÍNEA CON VEHÍCULO {$line_number}: [{$line}]");
                }
            }
            logMessage('INFO', "=== FIN BÚSQUEDA VEHÍCULOS ===");
            
            foreach ($lines as $line_number => $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                // Múltiples patrones para probar diferentes formatos
                $patterns = [
                    // Patrón original
                    'original' => '/^(\d{2})\s+(\d{4,6})\s+([A-Z]{2})\s+(\d+)\s+(\d{2}\/\d{2}\/\d{4})\s+(\d+)\s+(\d+)\s+([A-Z0-9]{9})\s+([\d.]+)\s+([\d.]+)\s+([A-Z]{2})\s+([\d.]+)$/',
                    
                    // Patrón alternativo sin oper code
                    'alt1' => '/(\d{4,6})\s+([A-Z]{2})\s+(\d+)\s+(\d{2}\/\d{2}\/\d{4})\s+(\d+)\s+(\d+)\s+([A-Z0-9]{9})\s+([\d.]+)\s+([\d.]+)\s+([A-Z]{2})\s+([\d.]+)/',
                    
                    // Patrón más flexible con tabs
                    'tabs' => '/(\d{4,6})\s+([A-Z]{2})\s*\t?\s*(\d{2}\/\d{2}\/\d{4})(\d+)\s+(\d+)\s+([\d.]+)\s+([\d.]+)\s+([A-Z]{2})\s+([\d.]+)\s+(\d+)\s*\t?\s*([A-Z0-9]{9})/',
                    
                    // Patrón muy flexible
                    'flexible' => '/(\d{4,6}).*?([A-Z]{2}).*?(\d{2}\/\d{2}\/\d{4}).*?(\d{8}).*?([A-Z0-9]{9}).*?([\d.]+).*?([\d.]+).*?([\d.]+)/'
                ];
                
                foreach ($patterns as $pattern_name => $pattern) {
                    if (preg_match($pattern, $line, $matches)) {
                        logMessage('INFO', "PATRÓN {$pattern_name} COINCIDE en línea {$line_number}");
                        logMessage('INFO', "Matches: " . json_encode($matches));
                        
                        // Mapear los matches según el patrón
                        if ($pattern_name === 'original') {
                            $extracted_row = [
                                'ship_date' => $this->parseDate($matches[5]),
                                'location' => $matches[2],
                                'ticket_number' => $matches[6],
                                'haul_rate' => floatval($matches[9]),
                                'quantity' => floatval($matches[10]),
                                'amount' => floatval($matches[12]), // CORREGIDO: era $matches[8]
                                'vehicle_number' => $matches[8], // CORREGIDO: estaba duplicado
                                'source_row' => $line_number + 1,
                                'source_line' => $line,
                                'confidence' => 0.95
                            ];
                        } elseif ($pattern_name === 'alt1') {
                            $extracted_row = [
                                'ship_date' => $this->parseDate($matches[4]),
                                'location' => $matches[1],
                                'ticket_number' => $matches[5],
                                'haul_rate' => floatval($matches[8]),
                                'quantity' => floatval($matches[9]),
                                'amount' => floatval($matches[11]),
                                'vehicle_number' => $matches[7],
                                'source_row' => $line_number + 1,
                                'source_line' => $line,
                                'confidence' => 0.85
                            ];
                        } elseif ($pattern_name === 'tabs') {
                            $extracted_row = [
                                'ship_date' => $this->parseDate($matches[3]),
                                'location' => $matches[1],
                                'ticket_number' => $matches[4],
                                'haul_rate' => floatval($matches[6]),
                                'quantity' => floatval($matches[7]),
                                'amount' => floatval($matches[9]),
                                'vehicle_number' => $matches[11], // CORREGIDO: era $matches[12]
                                'source_row' => $line_number + 1,
                                'source_line' => $line,
                                'confidence' => 0.80
                            ];
                        } elseif ($pattern_name === 'flexible') {
                            $extracted_row = [
                                'ship_date' => $this->parseDate($matches[3]),
                                'location' => $matches[1],
                                'ticket_number' => $matches[4],
                                'haul_rate' => floatval(isset($matches[6]) ? $matches[6] : 0),
                                'quantity' => floatval(isset($matches[7]) ? $matches[7] : 0),
                                'amount' => floatval(isset($matches[8]) ? $matches[8] : 0),
                                'vehicle_number' => $matches[5],
                                'source_row' => $line_number + 1,
                                'source_line' => $line,
                                'confidence' => 0.70
                            ];
                        }
                        
                        // LOGGING MEJORADO: Agregar company_identifier con diagnóstico completo
                        if (isset($extracted_row['vehicle_number']) && strlen($extracted_row['vehicle_number']) >= 6) {
                            $extracted_row['company_identifier'] = substr($extracted_row['vehicle_number'], 3, 3);
                            
                            logMessage('DEBUG', "=== COMPANY IDENTIFIER EXTRAÍDO ===");
                            logMessage('DEBUG', "Vehicle Number: '{$extracted_row['vehicle_number']}'");
                            logMessage('DEBUG', "Company Identifier: '{$extracted_row['company_identifier']}'");
                            logMessage('DEBUG', "Proceso: substr('{$extracted_row['vehicle_number']}', 3, 3) = '{$extracted_row['company_identifier']}'");
                            
                            // Verificar si la empresa existe en BD
                            $company_exists = $this->db->selectOne(
                                "SELECT id, name FROM companies WHERE identifier = ? AND is_active = 1",
                                [$extracted_row['company_identifier']]
                            );
                            
                            if ($company_exists) {
                                logMessage('DEBUG', "✓ Empresa EXISTE en BD: ID={$company_exists['id']}, Name='{$company_exists['name']}'");
                            } else {
                                logMessage('DEBUG', "✗ Empresa NO existe en BD - Se creará automáticamente");
                            }
                            logMessage('DEBUG', "=== FIN COMPANY IDENTIFIER ===");
                        } else {
                            $vehicle_display = isset($extracted_row['vehicle_number']) ? $extracted_row['vehicle_number'] : 'NULL';
                            logMessage('DEBUG', "✗ Vehicle number inválido para extraer company_identifier: '{$vehicle_display}'");
                        }
                        
                        $extracted_data[] = $extracted_row;
                        logMessage('INFO', "EXTRAÍDO CON {$pattern_name}: Location={$extracted_row['location']}, Vehicle={$extracted_row['vehicle_number']}, Amount={$extracted_row['amount']}");
                        
                        break; // Solo usar el primer patrón que coincida
                    }
                }
            }
            
            // RESUMEN COMPLETO DE EXTRACCIÓN
            logMessage('INFO', "=== RESUMEN DE EXTRACCIÓN ===");
            logMessage('INFO', "Total registros extraídos: " . count($extracted_data));

            // Agrupar por company_identifier
            $companies_summary = [];
            foreach ($extracted_data as $row) {
                if (isset($row['company_identifier'])) {
                    $companies_summary[$row['company_identifier']] = (isset($companies_summary[$row['company_identifier']]) ? $companies_summary[$row['company_identifier']] : 0) + 1;
                }
            }

            logMessage('INFO', "Empresas encontradas:");
            foreach ($companies_summary as $identifier => $count) {
                logMessage('INFO', "  - {$identifier}: {$count} registros");
            }

            // Verificar qué empresas existen en BD
            logMessage('INFO', "=== VERIFICACIÓN EN BASE DE DATOS ===");
            $existing_companies = $this->db->select("SELECT identifier, name FROM companies WHERE is_active = 1 ORDER BY identifier");
            logMessage('INFO', "Empresas existentes en BD:");
            foreach ($existing_companies as $company) {
                logMessage('INFO', "  - '{$company['identifier']}': {$company['name']}");
            }

            $companies_to_create = array_diff(array_keys($companies_summary), array_column($existing_companies, 'identifier'));
            if (!empty($companies_to_create)) {
                logMessage('INFO', "Empresas que se crearán automáticamente: " . implode(', ', $companies_to_create));
            } else {
                logMessage('INFO', "Todas las empresas ya existen en la BD");
            }

            logMessage('INFO', "=== FIN RESUMEN ===");
            
            logMessage('INFO', "=== FIN extractFromPDF - Extraídos " . count($extracted_data) . " registros ===");
            
            return $extracted_data;
            
        } catch (Exception $e) {
            logMessage('ERROR', "Error en extractFromPDF: " . $e->getMessage());
            throw $e;
        }
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
                $extracted_row = [
                    'ship_date' => $this->parseDate(isset($row_data['ship_date']) ? $row_data['ship_date'] : ''),
                    'location' => trim(isset($row_data['location']) ? $row_data['location'] : ''),
                    'ticket_number' => trim(isset($row_data['ticket_number']) ? $row_data['ticket_number'] : ''),
                    'haul_rate' => floatval(isset($row_data['haul_rate']) ? $row_data['haul_rate'] : 0),
                    'quantity' => floatval(isset($row_data['quantity']) ? $row_data['quantity'] : 0),
                    'amount' => floatval(isset($row_data['amount']) ? $row_data['amount'] : 0),
                    'vehicle_number' => trim(isset($row_data['vehicle_number']) ? $row_data['vehicle_number'] : ''),
                    'source_row' => $row,
                    'confidence' => 0.90
                ];
                
                if (strlen($extracted_row['vehicle_number']) >= 6) {
                    $extracted_row['company_identifier'] = substr($extracted_row['vehicle_number'], 3, 3);
                }
                
                $extracted_data[] = $extracted_row;
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
        // Si no hay filtro específico, devolver todos con company_identifier
        if (empty($this->selected_companies)) {
            return $raw_data;
        }
        
        $filtered_data = [];
        
        foreach ($raw_data as $row) {
            $company_identifier = isset($row['company_identifier']) ? $row['company_identifier'] : '';
            
            if (!empty($company_identifier) && in_array($company_identifier, $this->selected_companies)) {
                $filtered_data[] = $row;
            }
        }
        
        return $filtered_data;
    }
    
    /**
     * FUNCIÓN MEJORADA: Guardar trips en base de datos
     * NUEVA FUNCIONALIDAD: Auto-crear empresas que no existen
     */
    private function saveTripsToDatabase() {
        if (empty($this->extracted_data)) {
            logMessage('WARNING', "No hay datos para guardar en BD");
            return 0;
        }
        
        $saved_count = 0;
        $skipped_count = 0;
        $created_companies = [];
        
        logMessage('INFO', "Iniciando guardado en BD - Total registros: " . count($this->extracted_data));
        
        foreach ($this->extracted_data as $row_data) {
            // Verificar company_identifier antes de usarlo
            if (!isset($row_data['company_identifier']) || empty($row_data['company_identifier'])) {
                $skipped_count++;
                $vehicle_display = isset($row_data['vehicle_number']) ? $row_data['vehicle_number'] : 'N/A';
                logMessage('DEBUG', "Registro sin company_identifier válido, saltando - Vehicle: {$vehicle_display}");
                continue;
            }
            
            $company_identifier = $row_data['company_identifier'];
            
            // Buscar empresa por identificador
            $company = $this->db->selectOne(
                "SELECT id FROM companies WHERE identifier = ? AND is_active = 1",
                [$company_identifier]
            );
            
            // SI LA EMPRESA NO EXISTE, CREARLA AUTOMÁTICAMENTE
            if (!$company) {
                // Verificar si ya la creamos en esta sesión
                if (!isset($created_companies[$company_identifier])) {
                    try {
                        // Crear nueva empresa automáticamente
                        $company_data = [
                            'name' => $this->generateCompanyName($company_identifier),
                            'identifier' => $company_identifier,
                            'legal_name' => $this->generateCompanyLegalName($company_identifier),
                            'capital_percentage' => 5.00, // Porcentaje por defecto
                            'created_by' => 1, // Admin por defecto
                            'is_active' => 1
                        ];
                        
                        $company_id = $this->db->insert('companies', $company_data);
                        
                        if ($company_id) {
                            $created_companies[$company_identifier] = $company_id;
                            $company = ['id' => $company_id]; // Para usar abajo
                            
                            logMessage('INFO', "NUEVA EMPRESA CREADA: {$company_identifier} - {$company_data['name']} (ID: {$company_id})");
                        } else {
                            $skipped_count++;
                            logMessage('ERROR', "Error creando empresa: {$company_identifier}");
                            continue;
                        }
                        
                    } catch (Exception $e) {
                        $skipped_count++;
                        logMessage('ERROR', "Error creando empresa {$company_identifier}: " . $e->getMessage());
                        continue;
                    }
                } else {
                    // Ya creamos esta empresa en esta sesión
                    $company = ['id' => $created_companies[$company_identifier]];
                }
            }
            
            // Ahora guardar el trip (empresa existe o fue creada)
            try {
                $trip_data = [
                    'voucher_id' => $this->voucher_id,
                    'company_id' => $company['id'],
                    'trip_date' => isset($row_data['ship_date']) ? $row_data['ship_date'] : null,
                    'location' => isset($row_data['location']) ? $row_data['location'] : '',
                    'ticket_number' => isset($row_data['ticket_number']) ? $row_data['ticket_number'] : '',
                    'haul_rate' => floatval(isset($row_data['haul_rate']) ? $row_data['haul_rate'] : 0),
                    'quantity' => floatval(isset($row_data['quantity']) ? $row_data['quantity'] : 0),
                    'amount' => floatval(isset($row_data['amount']) ? $row_data['amount'] : 0),
                    'vehicle_number' => isset($row_data['vehicle_number']) ? $row_data['vehicle_number'] : '',
                    'source_row_number' => intval(isset($row_data['source_row']) ? $row_data['source_row'] : 0),
                    'extraction_confidence' => floatval(isset($row_data['confidence']) ? $row_data['confidence'] : 0.0)
                ];
                
                $trip_id = $this->db->insert('trips', $trip_data);
                
                if ($trip_id) {
                    $saved_count++;
                    logMessage('DEBUG', "Trip guardado ID: {$trip_id} - Company: {$company_identifier}, Location: {$trip_data['location']}, Vehicle: {$trip_data['vehicle_number']}");
                } else {
                    $skipped_count++;
                    logMessage('ERROR', "Falló insertar trip");
                }
                
            } catch (Exception $e) {
                $skipped_count++;
                logMessage('ERROR', "Error guardando trip: " . $e->getMessage());
            }
        }
        
        // Log final con resumen
        $companies_created_count = count($created_companies);
        logMessage('INFO', "Guardado completado - Guardados: {$saved_count}, Saltados: {$skipped_count}, Empresas creadas: {$companies_created_count}");
        
        if ($companies_created_count > 0) {
            logMessage('INFO', "Empresas creadas automáticamente: " . implode(', ', array_keys($created_companies)));
        }
        
        return $saved_count;
    }
    
    /**
     * Generar nombre de empresa basado en identifier
     */
    private function generateCompanyName($identifier) {
        $company_names = [
            'MVT' => 'Mountain View Transport',
            'LEO' => 'Leo Transport Services',
            'AAT' => 'American Auto Transport',
            'SWT' => 'Southwest Transport',
            'CTL' => 'Control Transport',
            'GRE' => 'Greene Transport',
            'CCL' => 'Clarksville Cargo Lines',
            'GVR' => 'Greenvale Transport',
            'ANI' => 'Animals Transport',
            'LWH' => 'Lawrenceville Warehouse',
            'RUS' => 'Russell Transport',
            'JAV' => 'Johnson & Associates',
            'MAR' => 'Martin Construction',
            'BRN' => 'Brown Transport',
            'WIL' => 'Wilson Logistics',
            'CAP' => 'Capital Partners'
        ];
        
        return isset($company_names[$identifier]) ? $company_names[$identifier] : ($identifier . ' Transport LLC');
    }
    
    /**
     * Generar nombre legal de empresa basado en identifier
     */
    private function generateCompanyLegalName($identifier) {
        return $this->generateCompanyName($identifier) . ' LLC';
    }
    
    /**
     * Actualizar estadísticas del voucher
     */
    private function updateVoucherStats($raw_data, $filtered_data, $saved_trips) {
        try {
            $update_data = [
                'total_rows_found' => count($raw_data),
                'valid_rows_extracted' => count($filtered_data),
                'rows_with_errors' => count($raw_data) - count($filtered_data),
                'extraction_confidence' => $this->calculateAverageConfidence($filtered_data),
                'processing_completed_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->update('vouchers', $update_data, ['id' => $this->voucher_id]);
            logMessage('INFO', "Estadísticas del voucher actualizadas");
            
        } catch (Exception $e) {
            logMessage('ERROR', "Error actualizando estadísticas: " . $e->getMessage());
        }
    }
    
    /**
     * Obtener empresas encontradas en los datos
     */
    private function getCompaniesFound($raw_data) {
        $companies = [];
        
        foreach ($raw_data as $row) {
            $company_identifier = isset($row['company_identifier']) ? $row['company_identifier'] : '';
            if (!empty($company_identifier) && !in_array($company_identifier, $companies)) {
                $companies[] = $company_identifier;
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
        try {
            $update_data = ['status' => $status];
            
            if ($status === 'processing') {
                $update_data['processing_started_at'] = date('Y-m-d H:i:s');
            } elseif ($status === 'processed') {
                $update_data['processing_completed_at'] = date('Y-m-d H:i:s');
            }
            
            $this->db->update('vouchers', $update_data, ['id' => $this->voucher_id]);
            logMessage('INFO', "Status del voucher actualizado a: {$status}");
            
        } catch (Exception $e) {
            logMessage('ERROR', "Error actualizando status: " . $e->getMessage());
        }
    }
}
?>