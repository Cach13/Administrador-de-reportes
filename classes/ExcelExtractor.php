<?php
/**
 * Procesador de archivos Excel (.xlsx, .xls)
 * Ruta: /classes/ExcelExtractor.php
 */

require_once __DIR__ . '/FileProcessor.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ExcelExtractor extends FileProcessor {
    
    private $spreadsheet;
    private $worksheet;
    private $column_mapping;
    private $header_row;
    
    public function __construct($voucher_id) {
        parent::__construct($voucher_id);
        $this->loadColumnMapping();
    }
    
    /**
     * Cargar mapeo de columnas desde configuración
     */
    private function loadColumnMapping() {
        // Mapeo por defecto - puede ser configurado desde base de datos
        $this->column_mapping = [
            'date' => ['fecha', 'date', 'fecha_viaje', 'trip_date', 'fecha del viaje'],
            'company' => ['empresa', 'company', 'transportista', 'carrier', 'compañia'],
            'origin' => ['origen', 'origin', 'pickup', 'carga', 'punto de carga'],
            'destination' => ['destino', 'destination', 'delivery', 'descarga', 'punto de descarga'],
            'weight' => ['toneladas', 'tons', 'weight', 'peso', 'ton', 'kg'],
            'rate' => ['tarifa', 'rate', 'precio', 'price', 'costo', 'monto'],
            'vehicle' => ['vehiculo', 'vehicle', 'placa', 'plate', 'camion', 'truck'],
            'driver' => ['chofer', 'driver', 'conductor', 'operador', 'piloto'],
            'ticket' => ['ticket', 'boleto', 'folio', 'documento', 'numero'],
            'product' => ['producto', 'product', 'mercancia', 'material', 'carga']
        ];
    }
    
    /**
     * Validar tipo de archivo Excel
     */
    protected function validateFileType() {
        $allowed_types = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
            'application/vnd.ms-excel', // .xls
            'application/excel',
            'application/x-excel',
            'application/x-msexcel'
        ];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $this->file_path);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            throw new Exception("Tipo de archivo Excel no válido: {$mime_type}");
        }
        
        // Validar extensión
        $extension = strtolower(pathinfo($this->file_path, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xls'])) {
            throw new Exception("Extensión de archivo no válida: {$extension}");
        }
    }
    
    /**
     * Extraer datos del archivo Excel
     */
    protected function extractData() {
        try {
            // Cargar archivo Excel
            $this->spreadsheet = IOFactory::load($this->file_path);
            $this->worksheet = $this->spreadsheet->getActiveSheet();
            
            $this->logProcessingStep('excel_load', 'completed', 'Archivo Excel cargado exitosamente');
            
            // Detectar headers
            $headers = $this->detectHeaders();
            $column_indices = $this->mapColumns($headers);
            
            // Extraer datos de filas
            $data = $this->extractRows($column_indices);
            
            $this->logProcessingStep('excel_extraction', 'completed', 
                "Extraídas " . count($data) . " filas de datos");
            
            return $data;
            
        } catch (Exception $e) {
            throw new Exception("Error extrayendo datos de Excel: " . $e->getMessage());
        }
    }
    
    /**
     * Detectar headers en el archivo Excel
     */
    private function detectHeaders() {
        $headers = [];
        $max_row_to_check = min(5, $this->worksheet->getHighestRow()); // Buscar en primeras 5 filas
        
        for ($row = 1; $row <= $max_row_to_check; $row++) {
            $row_data = [];
            $highest_col = $this->worksheet->getHighestColumn();
            
            for ($col = 'A'; $col <= $highest_col; $col++) {
                $cell_value = $this->worksheet->getCell($col . $row)->getCalculatedValue();
                $row_data[$col] = trim(strtolower($cell_value ?? ''));
            }
            
            // Verificar si esta fila contiene headers típicos
            $header_score = $this->calculateHeaderScore($row_data);
            
            if ($header_score > 0.5) { // Si el 50% o más de columnas coinciden con headers esperados
                $headers = $row_data;
                $this->header_row = $row;
                break;
            }
        }
        
        if (empty($headers)) {
            throw new Exception("No se pudieron detectar headers en el archivo Excel");
        }
        
        $this->logProcessingStep('header_detection', 'completed', 
            "Headers detectados en fila {$this->header_row}");
        
        return $headers;
    }
    
    /**
     * Calcular puntuación de headers
     */
    private function calculateHeaderScore($row_data) {
        $matches = 0;
        $total_fields = count($this->column_mapping);
        
        foreach ($this->column_mapping as $field => $variations) {
            foreach ($row_data as $cell_value) {
                if (in_array($cell_value, $variations)) {
                    $matches++;
                    break;
                }
            }
        }
        
        return $matches / $total_fields;
    }
    
    /**
     * Mapear columnas del Excel a nuestros campos
     */
    private function mapColumns($headers) {
        $column_indices = [];
        
        foreach ($this->column_mapping as $field => $variations) {
            foreach ($headers as $col => $header) {
                if (in_array($header, $variations)) {
                    $column_indices[$field] = $col;
                    break;
                }
            }
        }
        
        // Verificar campos requeridos
        $required_fields = ['date', 'company', 'origin', 'destination', 'weight', 'rate'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (!isset($column_indices[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            throw new Exception("Campos requeridos no encontrados: " . implode(', ', $missing_fields));
        }
        
        $this->logProcessingStep('column_mapping', 'completed', 
            "Mapeadas " . count($column_indices) . " columnas");
        
        return $column_indices;
    }
    
    /**
     * Extraer filas de datos
     */
    private function extractRows($column_indices) {
        $data = [];
        $start_row = ($this->header_row ?? 1) + 1;
        $highest_row = $this->worksheet->getHighestRow();
        
        for ($row = $start_row; $row <= $highest_row; $row++) {
            $row_data = $this->extractRow($row, $column_indices);
            
            // Saltar filas vacías
            if ($this->isRowEmpty($row_data)) {
                continue;
            }
            
            $data[] = $row_data;
        }
        
        return $data;
    }
    
    /**
     * Extraer una fila específica
     */
    private function extractRow($row_number, $column_indices) {
        $row_data = [];
        
        foreach ($column_indices as $field => $col) {
            $cell = $this->worksheet->getCell($col . $row_number);
            $value = $cell->getCalculatedValue();
            
            // Manejar fechas de Excel
            if ($field === 'date' && is_numeric($value)) {
                $value = Date::excelToDateTimeObject($value)->format('Y-m-d');
            }
            
            // Limpiar valores
            $row_data[$field] = $this->cleanCellValue($value, $field);
        }
        
        return $row_data;
    }
    
    /**
     * Limpiar valor de celda
     */
    private function cleanCellValue($value, $field) {
        if ($value === null) {
            return '';
        }
        
        // Convertir a string y limpiar
        $value = trim(strval($value));
        
        // Limpiar campos numéricos
        if (in_array($field, ['weight', 'rate'])) {
            // Remover caracteres no numéricos excepto punto y coma
            $value = preg_replace('/[^0-9.,]/', '', $value);
            // Convertir comas a puntos para decimales
            $value = str_replace(',', '.', $value);
            return floatval($value);
        }
        
        return $value;
    }
    
    /**
     * Verificar si una fila está vacía
     */
    private function isRowEmpty($row_data) {
        foreach ($row_data as $value) {
            if (!empty(trim($value))) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Obtener tipo de archivo
     */
    protected function getFileType() {
        return 'excel';
    }
    
    /**
     * Obtener tamaño máximo de archivo
     */
    protected function getMaxFileSize() {
        return 10 * 1024 * 1024; // 10MB para Excel
    }
    
    /**
     * Obtener información adicional del archivo Excel
     */
    public function getFileInfo() {
        if (!$this->spreadsheet) {
            return [];
        }
        
        $properties = $this->spreadsheet->getProperties();
        
        return [
            'title' => $properties->getTitle(),
            'creator' => $properties->getCreator(),
            'last_modified_by' => $properties->getLastModifiedBy(),
            'created' => $properties->getCreated(),
            'modified' => $properties->getModified(),
            'sheets_count' => $this->spreadsheet->getSheetCount(),
            'active_sheet' => $this->spreadsheet->getActiveSheet()->getTitle(),
            'total_rows' => $this->worksheet->getHighestRow(),
            'total_columns' => $this->worksheet->getHighestColumn()
        ];
    }
    
    /**
     * Validar estructura del archivo Excel
     */
    public function validateStructure() {
        $validation_results = [
            'is_valid' => true,
            'errors' => [],
            'warnings' => []
        ];
        
        try {
            // Verificar que hay al menos una hoja
            if ($this->spreadsheet->getSheetCount() === 0) {
                $validation_results['errors'][] = 'El archivo no contiene hojas de trabajo';
                $validation_results['is_valid'] = false;
            }
            
            // Verificar que hay datos
            if ($this->worksheet->getHighestRow() < 2) {
                $validation_results['errors'][] = 'El archivo no contiene datos suficientes';
                $validation_results['is_valid'] = false;
            }
            
            // Verificar límites razonables
            $total_rows = $this->worksheet->getHighestRow();
            if ($total_rows > 10000) {
                $validation_results['warnings'][] = "Archivo muy grande ({$total_rows} filas), el procesamiento puede ser lento";
            }
            
        } catch (Exception $e) {
            $validation_results['errors'][] = 'Error validando estructura: ' . $e->getMessage();
            $validation_results['is_valid'] = false;
        }
        
        return $validation_results;
    }
    
    /**
     * Previsualizar datos del archivo
     */
    public function previewData($max_rows = 5) {
        try {
            if (!$this->spreadsheet) {
                $this->spreadsheet = IOFactory::load($this->file_path);
                $this->worksheet = $this->spreadsheet->getActiveSheet();
            }
            
            $headers = $this->detectHeaders();
            $column_indices = $this->mapColumns($headers);
            
            $preview = [
                'headers' => $headers,
                'column_mapping' => $column_indices,
                'sample_data' => []
            ];
            
            $start_row = ($this->header_row ?? 1) + 1;
            $end_row = min($start_row + $max_rows - 1, $this->worksheet->getHighestRow());
            
            for ($row = $start_row; $row <= $end_row; $row++) {
                $row_data = $this->extractRow($row, $column_indices);
                if (!$this->isRowEmpty($row_data)) {
                    $preview['sample_data'][] = $row_data;
                }
            }
            
            return $preview;
            
        } catch (Exception $e) {
            throw new Exception("Error previsualizando datos: " . $e->getMessage());
        }
    }
    
    /**
     * Obtener estadísticas del archivo
     */
    public function getStatistics() {
        try {
            if (!$this->spreadsheet) {
                $this->spreadsheet = IOFactory::load($this->file_path);
                $this->worksheet = $this->spreadsheet->getActiveSheet();
            }
            
            $total_rows = $this->worksheet->getHighestRow();
            $header_row = $this->header_row ?? 1;
            $data_rows = $total_rows - $header_row;
            
            // Contar filas no vacías
            $non_empty_rows = 0;
            for ($row = $header_row + 1; $row <= $total_rows; $row++) {
                $highest_col = $this->worksheet->getHighestColumn();
                $is_empty = true;
                
                for ($col = 'A'; $col <= $highest_col; $col++) {
                    $value = $this->worksheet->getCell($col . $row)->getCalculatedValue();
                    if (!empty(trim($value))) {
                        $is_empty = false;
                        break;
                    }
                }
                
                if (!$is_empty) {
                    $non_empty_rows++;
                }
            }
            
            return [
                'total_rows' => $total_rows,
                'header_row' => $header_row,
                'data_rows' => $data_rows,
                'non_empty_rows' => $non_empty_rows,
                'empty_rows' => $data_rows - $non_empty_rows,
                'total_columns' => $this->worksheet->getHighestColumn(),
                'file_size' => filesize($this->file_path),
                'estimated_processing_time' => $this->estimateProcessingTime($non_empty_rows)
            ];
            
        } catch (Exception $e) {
            throw new Exception("Error obteniendo estadísticas: " . $e->getMessage());
        }
    }
    
    /**
     * Estimar tiempo de procesamiento
     */
    private function estimateProcessingTime($rows) {
        // Estimación aproximada: 100 filas por segundo
        $seconds = ceil($rows / 100);
        
        if ($seconds < 60) {
            return "{$seconds} segundos";
        } else {
            $minutes = ceil($seconds / 60);
            return "{$minutes} minutos";
        }
    }
    
    /**
     * Limpiar recursos
     */
    public function __destruct() {
        if ($this->spreadsheet) {
            $this->spreadsheet->disconnectWorksheets();
            unset($this->spreadsheet);
        }
    }
}