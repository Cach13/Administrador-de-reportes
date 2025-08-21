<?php
/**
 * Clase base abstracta para procesamiento de archivos
 * Ruta: /classes/FileProcessor.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Logger.php';

abstract class FileProcessor {
    protected $db;
    protected $logger;
    protected $voucher_id;
    protected $file_path;
    protected $file_info;
    protected $processing_start_time;
    
    public function __construct($voucher_id) {
        $this->db = Database::getInstance();
        $this->logger = new Logger();
        $this->voucher_id = $voucher_id;
        $this->processing_start_time = microtime(true);
        
        // Obtener información del voucher
        $this->loadVoucherInfo();
    }
    
    /**
     * Cargar información del voucher desde la base de datos
     */
    protected function loadVoucherInfo() {
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
     * Procesar archivo - Método principal público
     */
    public function process() {
        try {
            $this->logProcessingStep('processing', 'started', 'Iniciando procesamiento');
            $this->updateVoucherStatus('processing');
            
            // Validar archivo
            $this->validateFile();
            $this->logProcessingStep('validation', 'completed', 'Archivo validado');
            
            // Extraer datos
            $raw_data = $this->extractData();
            $this->logProcessingStep('extraction', 'completed', 
                "Datos extraídos: " . count($raw_data) . " registros");
            
            // Normalizar datos
            $normalized_data = $this->normalizeData($raw_data);
            $this->logProcessingStep('normalization', 'completed', 
                "Datos normalizados: " . count($normalized_data) . " registros válidos");
            
            // Guardar en base de datos
            $saved_count = $this->saveTrips($normalized_data);
            $this->logProcessingStep('database_save', 'completed', 
                "Guardados {$saved_count} viajes en base de datos");
            
            // Finalizar procesamiento
            $this->finalizeProcessing($normalized_data);
            
            return [
                'success' => true,
                'trips_processed' => $saved_count,
                'quality_score' => $this->calculateQualityScore($raw_data, $normalized_data),
                'processing_time' => microtime(true) - $this->processing_start_time
            ];
            
        } catch (Exception $e) {
            $this->handleProcessingError($e);
            throw $e;
        }
    }
    
    /**
     * Validar archivo - Implementación por defecto
     */
    protected function validateFile() {
        // Verificar que el archivo existe y es legible
        if (!is_readable($this->file_path)) {
            throw new Exception("Archivo no es legible: {$this->file_path}");
        }
        
        // Verificar tamaño del archivo
        $file_size = filesize($this->file_path);
        $max_size = $this->getMaxFileSize();
        
        if ($file_size > $max_size) {
            throw new Exception("Archivo excede el tamaño máximo permitido");
        }
        
        // Validación específica del tipo de archivo
        $this->validateFileType();
    }
    
    /**
     * Normalizar datos extraídos
     */
    protected function normalizeData($raw_data) {
        $normalized = [];
        $row_number = 0;
        
        foreach ($raw_data as $row) {
            $row_number++;
            
            try {
                $normalized_row = $this->normalizeRow($row, $row_number);
                
                if ($normalized_row) {
                    $normalized_row['source_row_number'] = $row_number;
                    $normalized_row['voucher_id'] = $this->voucher_id;
                    $normalized_row['data_source_type'] = $this->getFileType();
                    $normalized[] = $normalized_row;
                }
                
            } catch (Exception $e) {
                $this->logValidationError($row_number, 'general', $e->getMessage());
            }
        }
        
        return $normalized;
    }
    
    /**
     * Normalizar una fila individual
     */
    /**
     * Normalizar una fila individual - ACTUALIZADO para Martin Marietta
     */
    protected function normalizeRow($row, $row_number) {
        $normalized = [];
        
        // === CAMPOS REQUERIDOS DE MARTIN MARIETTA ===
        
        // Doc Type (requerido)
        if (isset($row['doc_type'])) {
            $normalized['doc_type'] = $this->normalizeText($row['doc_type'], $row_number, 'doc_type');
        }
        
        // Doc Number (requerido)
        if (isset($row['doc_number'])) {
            $normalized['doc_number'] = $this->normalizeText($row['doc_number'], $row_number, 'doc_number');
        }
        
        // Ship Date (requerido) - priorizar ship_date sobre date
        $date_field = $row['ship_date'] ?? $row['date'] ?? '';
        $normalized['ship_date'] = $this->normalizeDate($date_field, $row_number);
        
        // Ticket Number (requerido)
        $ticket_field = $row['ticket_number'] ?? $row['ticket'] ?? '';
        if (!empty($ticket_field)) {
            $normalized['ticket_number'] = $this->normalizeText($ticket_field, $row_number, 'ticket_number');
        }
        
        // Replaced Ticket (opcional)
        if (isset($row['replaced_ticket']) && $row['replaced_ticket'] !== '0') {
            $normalized['replaced_ticket'] = $this->normalizeText($row['replaced_ticket'], $row_number, 'replaced_ticket', false);
        }
        
        // Vehicle Number (requerido)
        $vehicle_field = $row['vehicle_number'] ?? $row['vehicle'] ?? '';
        if (!empty($vehicle_field)) {
            $normalized['vehicle_number'] = $this->normalizeText($vehicle_field, $row_number, 'vehicle_number');
        }
        
        // === CAMPOS ESTÁNDAR DEL SISTEMA ===
        
        // Fecha del viaje (usar ship_date)
        $normalized['trip_date'] = $normalized['ship_date'];
        
        // Empresa - buscar en varios campos posibles
        $company_field = $row['company'] ?? 'CAPITAL TRANSPORT LLC';
        $normalized['company_id'] = $this->findOrCreateCompany($company_field, $row_number);
        
        // Origen y destino
        $normalized['origin'] = $this->normalizeText(
            $row['origin'] ?? 'Martin Marietta Materials', 
            $row_number, 'origin', false
        );
        $normalized['destination'] = $this->normalizeText(
            $row['destination'] ?? 'Destino no especificado', 
            $row_number, 'destination', false
        );
        
        // Peso y tarifa
        $weight = $row['weight'] ?? $row['quantity'] ?? 0;
        $rate = $row['rate'] ?? $row['unit_rate'] ?? $row['haul_rate'] ?? 0;
        
        $normalized['weight_tons'] = $this->normalizeWeight($weight, $row_number);
        $normalized['unit_rate'] = $this->normalizeAmount($rate, $row_number);
        
        // Calcular subtotal - usar amount si está disponible, sino calcular
        if (isset($row['amount']) && $row['amount'] > 0) {
            $normalized['subtotal'] = abs(floatval($row['amount'])); // Usar valor absoluto
        } else {
            $normalized['subtotal'] = $normalized['weight_tons'] * $normalized['unit_rate'];
        }
        
        // Datos opcionales
        $normalized['vehicle_plate'] = $normalized['vehicle_number'] ?? $this->normalizeText($row['vehicle'] ?? '', $row_number, 'vehicle', false);
        $normalized['driver_name'] = $this->normalizeText($row['driver'] ?? '', $row_number, 'driver', false);
        $normalized['product_type'] = $this->normalizeText($row['product'] ?? 'Material', $row_number, 'product', false);
        
        // === CAMPOS ESPECÍFICOS DE MARTIN MARIETTA ===
        
        // Campos adicionales del voucher
        if (isset($row['oper_unit'])) {
            $normalized['oper_unit'] = $this->normalizeText($row['oper_unit'], $row_number, 'oper_unit', false);
        }
        if (isset($row['location'])) {
            $normalized['location'] = $this->normalizeText($row['location'], $row_number, 'location', false);
        }
        if (isset($row['description'])) {
            $normalized['description'] = $this->normalizeText($row['description'], $row_number, 'description', false);
        }
        if (isset($row['uom'])) {
            $normalized['uom'] = $this->normalizeText($row['uom'], $row_number, 'uom', false);
        }
        if (isset($row['payment_number'])) {
            $normalized['payment_number'] = $this->normalizeText($row['payment_number'], $row_number, 'payment_number', false);
        }
        if (isset($row['payment_date'])) {
            $normalized['payment_date'] = $row['payment_date'];
        }
        
        // Aplicar deducciones
        $this->applyDeductions($normalized);
        
        // Calcular confianza de extracción
        $normalized['extraction_confidence'] = $this->calculateRowConfidence($row, $normalized);
        
        // Validar campos específicos si es de Martin Marietta
        if (isset($row['doc_type']) || isset($row['vehicle_number'])) {
            $this->validateMartinMariettaFields($normalized, $row_number);
        }
        
        return $normalized;
    }
    
    /**
     * Validar campos específicos de Martin Marietta
     */
    protected function validateMartinMariettaFields($normalized, $row_number) {
        // Validar formato Doc Type si está presente
        if (isset($normalized['doc_type']) && $normalized['doc_type'] !== 'PH') {
            $this->logValidationError($row_number, 'doc_type', 
                "Doc Type esperado 'PH', encontrado: " . $normalized['doc_type'], 
                $normalized['doc_type']);
        }
        
        // Validar formato Vehicle Number si está presente
        if (isset($normalized['vehicle_number']) && !preg_match('/^RMT[A-Z0-9]{6}$/', $normalized['vehicle_number'])) {
            $this->logValidationError($row_number, 'vehicle_number', 
                "Formato de Vehicle Number no coincide con patrón RMT + 6 caracteres: " . $normalized['vehicle_number'], 
                $normalized['vehicle_number']);
        }
        
        // Validar que Doc Number sea numérico si está presente
        if (isset($normalized['doc_number']) && !is_numeric($normalized['doc_number'])) {
            $this->logValidationError($row_number, 'doc_number', 
                "Doc Number debe ser numérico: " . $normalized['doc_number'], 
                $normalized['doc_number']);
        }
    }
    /**
     * Guardar viajes en la base de datos
     */
    protected function saveTrips($trips) {
        $saved_count = 0;
        
        $this->db->beginTransaction();
        
        try {
            foreach ($trips as $trip) {
                $trip_id = $this->db->insert('trips', $trip);
                if ($trip_id) {
                    $saved_count++;
                }
            }
            
            $this->db->commit();
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Error guardando viajes: " . $e->getMessage());
        }
        
        return $saved_count;
    }
    
    /**
     * Finalizar procesamiento y actualizar estadísticas
     */
    protected function finalizeProcessing($processed_data) {
        $processing_time = microtime(true) - $this->processing_start_time;
        $quality_score = $this->calculateQualityScore([], $processed_data);
        
        // Actualizar voucher
        $this->db->update('vouchers', [
            'status' => 'processed',
            'processed_at' => date('Y-m-d H:i:s'),
            'processed_by' => $_SESSION['user_id'] ?? null,
            'processing_time_seconds' => round($processing_time),
            'data_quality_score' => $quality_score,
            'total_rows_valid' => count($processed_data),
            'total_trips' => count($processed_data),
            'total_companies' => $this->countUniqueCompanies($processed_data),
            'total_amount' => $this->calculateTotalAmount($processed_data)
        ], 'id = ?', [$this->voucher_id]);
        
        $this->logProcessingStep('finalization', 'completed', 'Procesamiento finalizado exitosamente');
    }
    
    /**
     * Manejar errores de procesamiento
     */
    protected function handleProcessingError($exception) {
        $this->updateVoucherStatus('error', $exception->getMessage());
        $this->logProcessingStep('error', 'failed', $exception->getMessage());
        $this->logger->logError($_SESSION['user_id'] ?? null, 
            "Error procesando voucher {$this->voucher_id}: " . $exception->getMessage());
    }
    
    /**
     * Registrar paso de procesamiento
     */
    protected function logProcessingStep($step, $status, $details = null) {
        $step_time = microtime(true) - $this->processing_start_time;
        
        $this->db->insert('file_processing_logs', [
            'voucher_id' => $this->voucher_id,
            'processing_step' => $step,
            'step_status' => $status,
            'step_details' => $details ? json_encode(['details' => $details]) : null,
            'processing_time_ms' => round($step_time * 1000),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
        ]);
    }
    
    /**
     * Registrar error de validación
     */
    protected function logValidationError($row_number, $field, $message, $original_value = null) {
        $this->db->insert('data_validation_errors', [
            'voucher_id' => $this->voucher_id,
            'row_number' => $row_number,
            'field_name' => $field,
            'original_value' => $original_value,
            'error_type' => 'validation_error',
            'error_message' => $message,
            'severity' => 'error'
        ]);
    }
    
    /**
     * Actualizar estado del voucher
     */
    protected function updateVoucherStatus($status, $notes = null) {
        $update_data = ['status' => $status];
        if ($notes) {
            $update_data['processing_notes'] = $notes;
        }
        
        $this->db->update('vouchers', $update_data, 'id = ?', [$this->voucher_id]);
    }
    
    /**
     * Calcular puntuación de calidad
     */
    protected function calculateQualityScore($raw_data, $processed_data) {
        if (empty($raw_data)) return 1.0;
        
        $total_rows = count($raw_data);
        $valid_rows = count($processed_data);
        
        return $total_rows > 0 ? round($valid_rows / $total_rows, 2) : 0.0;
    }
    
    /**
     * Normalizar fecha
     */
    protected function normalizeDate($date_string, $row_number) {
        if (empty($date_string)) {
            $this->logValidationError($row_number, 'date', 'Fecha requerida', $date_string);
            throw new Exception("Fecha requerida en fila {$row_number}");
        }
        
        // Intentar varios formatos de fecha
        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'Y/m/d'];
        
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, trim($date_string));
            if ($date && $date->format($format) === trim($date_string)) {
                return $date->format('Y-m-d');
            }
        }
        
        $this->logValidationError($row_number, 'date', 'Formato de fecha inválido', $date_string);
        throw new Exception("Formato de fecha inválido en fila {$row_number}: {$date_string}");
    }
    
    /**
     * Encontrar o crear empresa
     */
    protected function findOrCreateCompany($company_name, $row_number) {
        if (empty($company_name)) {
            $this->logValidationError($row_number, 'company', 'Nombre de empresa requerido', $company_name);
            throw new Exception("Nombre de empresa requerido en fila {$row_number}");
        }
        
        $company_name = trim($company_name);
        
        // Buscar empresa existente
        $company = $this->db->fetch(
            "SELECT id FROM companies WHERE name = ? OR legal_name = ?", 
            [$company_name, $company_name]
        );
        
        if ($company) {
            return $company['id'];
        }
        
        // Crear nueva empresa
        $company_id = $this->db->insert('companies', [
            'name' => $company_name,
            'legal_name' => $company_name,
            'created_by' => $_SESSION['user_id'] ?? 1,
            'deduction_type' => 'percentage',
            'deduction_value' => 5.00
        ]);
        
        return $company_id;
    }
    
    // MÉTODOS ABSTRACTOS - Deben ser implementados por las clases hijas
    abstract protected function extractData();
    abstract protected function validateFileType();
    abstract protected function getFileType();
    abstract protected function getMaxFileSize();
    
    // MÉTODOS DE NORMALIZACIÓN ADICIONALES
    protected function normalizeText($text, $row_number, $field, $required = true) {
        $text = trim($text ?? '');
        
        if ($required && empty($text)) {
            $this->logValidationError($row_number, $field, "Campo {$field} requerido", $text);
            throw new Exception("Campo {$field} requerido en fila {$row_number}");
        }
        
        return $text;
    }
    
    protected function normalizeWeight($weight, $row_number) {
        $weight = floatval($weight);
        
        if ($weight <= 0) {
            $this->logValidationError($row_number, 'weight', 'Peso debe ser mayor a 0', $weight);
            throw new Exception("Peso inválido en fila {$row_number}: {$weight}");
        }
        
        return $weight;
    }
    
    protected function normalizeAmount($amount, $row_number) {
        $amount = floatval($amount);
        
        if ($amount <= 0) {
            $this->logValidationError($row_number, 'amount', 'Monto debe ser mayor a 0', $amount);
            throw new Exception("Monto inválido en fila {$row_number}: {$amount}");
        }
        
        return $amount;
    }
    
    protected function applyDeductions(&$trip) {
        // Obtener configuración de deducciones (por defecto)
        $deduction_type = 'percentage';
        $deduction_value = 5.00;
        
        $trip['deduction_type'] = $deduction_type;
        $trip['deduction_value'] = $deduction_value;
        
        if ($deduction_type === 'percentage') {
            $trip['deduction_amount'] = $trip['subtotal'] * ($deduction_value / 100);
        } else {
            $trip['deduction_amount'] = $deduction_value;
        }
        
        $trip['total_amount'] = $trip['subtotal'] - $trip['deduction_amount'];
    }
    
    protected function calculateRowConfidence($raw_row, $normalized_row) {
        $confidence = 1.0;
        
        // Reducir confianza si hay campos faltantes
        $required_fields = ['date', 'company', 'origin', 'destination', 'weight', 'rate'];
        $missing_fields = 0;
        
        foreach ($required_fields as $field) {
            if (empty($raw_row[$field])) {
                $missing_fields++;
            }
        }
        
        if ($missing_fields > 0) {
            $confidence -= ($missing_fields / count($required_fields)) * 0.3;
        }
        
        return max(0.1, round($confidence, 2));
    }
    
    protected function countUniqueCompanies($trips) {
        $companies = array_unique(array_column($trips, 'company_id'));
        return count($companies);
    }
    
    protected function calculateTotalAmount($trips) {
        return array_sum(array_column($trips, 'total_amount'));
    }
}