<?php
// ========================================
// app/Models/Voucher.php
// Modelo para entidad Voucher
// ========================================

namespace App\Models;

use Database;
use Exception;

/**
 * Voucher Model - Representa un voucher/archivo procesado
 * 
 * Maneja todas las operaciones relacionadas con vouchers:
 * - Gestión de archivos subidos
 * - Estados de procesamiento
 * - Estadísticas de extracción
 * - Relaciones con trips
 */
class Voucher
{
    /** @var Database */
    private $db;
    
    /** @var array */
    private $data = [];
    
    /** @var array */
    private $fillable = [
        'voucher_number', 'original_filename', 'file_path', 'file_size',
        'file_format', 'status', 'total_rows_found', 'valid_rows_extracted',
        'extraction_confidence', 'upload_date'
    ];
    
    /**
     * Estados válidos de voucher
     */
    const STATUS_UPLOADED = 'uploaded';
    const STATUS_PROCESSING = 'processing';
    const STATUS_PROCESSED = 'processed';
    const STATUS_ERROR = 'error';
    
    /**
     * Constructor
     */
    public function __construct($data = [])
    {
        $this->db = Database::getInstance();
        
        if (!empty($data)) {
            $this->fill($data);
        }
    }
    
    /**
     * Llenar modelo con datos
     */
    public function fill(array $data)
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $this->fillable) || $key === 'id') {
                $this->data[$key] = $value;
            }
        }
        return $this;
    }
    
    /**
     * Obtener valor de atributo
     */
    public function __get($key)
    {
        return $this->data[$key] ?? null;
    }
    
    /**
     * Establecer valor de atributo
     */
    public function __set($key, $value)
    {
        if (in_array($key, $this->fillable)) {
            $this->data[$key] = $value;
        }
    }
    
    /**
     * Guardar voucher
     */
    public function save()
    {
        if (isset($this->data['id'])) {
            return $this->update();
        } else {
            return $this->create();
        }
    }
    
    /**
     * Crear voucher en base de datos
     */
    private function create()
    {
        $this->validate();
        
        // Generar voucher_number si no existe
        if (empty($this->data['voucher_number'])) {
            $this->data['voucher_number'] = $this->generateVoucherNumber();
        }
        
        $fields = array_keys($this->data);
        $placeholders = ':' . implode(', :', $fields);
        
        $sql = "INSERT INTO vouchers (" . implode(', ', $fields) . ") 
                VALUES (" . $placeholders . ")";
        
        try {
            $this->db->execute($sql, $this->data);
            $this->data['id'] = $this->db->lastInsertId();
            return true;
            
        } catch (Exception $e) {
            throw new Exception("Error creando voucher: " . $e->getMessage());
        }
    }
    
    /**
     * Actualizar voucher existente
     */
    private function update()
    {
        if (!isset($this->data['id'])) {
            throw new Exception("Cannot update voucher without ID");
        }
        
        $id = $this->data['id'];
        unset($this->data['id']);
        
        $setClause = [];
        foreach ($this->data as $key => $value) {
            $setClause[] = "$key = :$key";
        }
        
        $sql = "UPDATE vouchers SET " . implode(', ', $setClause) . " WHERE id = :id";
        $this->data['id'] = $id;
        
        try {
            return $this->db->execute($sql, $this->data);
            
        } catch (Exception $e) {
            throw new Exception("Error actualizando voucher: " . $e->getMessage());
        }
    }
    
    /**
     * Validar datos del voucher
     */
    private function validate()
    {
        $errors = [];
        
        // Original filename requerido
        if (empty($this->data['original_filename'])) {
            $errors[] = "Nombre de archivo es requerido";
        }
        
        // File path requerido
        if (empty($this->data['file_path'])) {
            $errors[] = "Ruta de archivo es requerida";
        }
        
        // File format válido
        if (!empty($this->data['file_format'])) {
            $validFormats = ['pdf', 'xlsx', 'xls'];
            if (!in_array($this->data['file_format'], $validFormats)) {
                $errors[] = "Formato de archivo no válido";
            }
        }
        
        // Status válido
        if (!empty($this->data['status'])) {
            $validStatuses = [self::STATUS_UPLOADED, self::STATUS_PROCESSING, self::STATUS_PROCESSED, self::STATUS_ERROR];
            if (!in_array($this->data['status'], $validStatuses)) {
                $errors[] = "Estado no válido";
            }
        }
        
        if (!empty($errors)) {
            throw new Exception("Errores de validación: " . implode(', ', $errors));
        }
    }
    
    /**
     * Generar número de voucher único
     */
    private function generateVoucherNumber()
    {
        $prefix = 'MM-' . date('Y') . '-';
        
        // Obtener último número del año
        $lastVoucher = $this->db->fetch(
            "SELECT voucher_number FROM vouchers 
             WHERE voucher_number LIKE ? 
             ORDER BY id DESC LIMIT 1",
            [$prefix . '%']
        );
        
        if ($lastVoucher) {
            $lastNumber = intval(substr($lastVoucher['voucher_number'], strlen($prefix)));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
    }
    
    /**
     * Buscar voucher por ID
     */
    public static function find($id)
    {
        $db = Database::getInstance();
        $data = $db->fetch("SELECT * FROM vouchers WHERE id = ?", [$id]);
        
        return $data ? new self($data) : null;
    }
    
    /**
     * Buscar voucher por número
     */
    public static function findByNumber($voucherNumber)
    {
        $db = Database::getInstance();
        $data = $db->fetch("SELECT * FROM vouchers WHERE voucher_number = ?", [$voucherNumber]);
        
        return $data ? new self($data) : null;
    }
    
    /**
     * Obtener todos los vouchers
     */
    public static function all($limit = null)
    {
        $db = Database::getInstance();
        $limitClause = $limit ? "LIMIT " . intval($limit) : "";
        $results = $db->fetchAll("SELECT * FROM vouchers ORDER BY upload_date DESC $limitClause");
        
        $vouchers = [];
        foreach ($results as $data) {
            $vouchers[] = new self($data);
        }
        
        return $vouchers;
    }
    
    /**
     * Obtener vouchers por estado
     */
    public static function getByStatus($status, $limit = null)
    {
        $db = Database::getInstance();
        $limitClause = $limit ? "LIMIT " . intval($limit) : "";
        $results = $db->fetchAll(
            "SELECT * FROM vouchers WHERE status = ? ORDER BY upload_date DESC $limitClause",
            [$status]
        );
        
        $vouchers = [];
        foreach ($results as $data) {
            $vouchers[] = new self($data);
        }
        
        return $vouchers;
    }
    
    /**
     * Actualizar estado de procesamiento
     */
    public function updateStatus($status, $notes = null)
    {
        if (!isset($this->data['id'])) {
            throw new Exception("Voucher ID is required");
        }
        
        $validStatuses = [self::STATUS_UPLOADED, self::STATUS_PROCESSING, self::STATUS_PROCESSED, self::STATUS_ERROR];
        if (!in_array($status, $validStatuses)) {
            throw new Exception("Estado no válido: $status");
        }
        
        $this->data['status'] = $status;
        
        $sql = "UPDATE vouchers SET status = ?";
        $params = [$status];
        
        if ($notes !== null) {
            $sql .= ", processing_notes = ?";
            $params[] = $notes;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $this->data['id'];
        
        return $this->db->execute($sql, $params);
    }
    
    /**
     * Actualizar estadísticas de extracción
     */
    public function updateExtractionStats($totalRows, $validRows, $confidence = null)
    {
        if (!isset($this->data['id'])) {
            throw new Exception("Voucher ID is required");
        }
        
        $this->data['total_rows_found'] = $totalRows;
        $this->data['valid_rows_extracted'] = $validRows;
        
        if ($confidence !== null) {
            $this->data['extraction_confidence'] = $confidence;
        }
        
        $sql = "UPDATE vouchers SET 
                total_rows_found = ?, 
                valid_rows_extracted = ?, 
                extraction_confidence = ? 
                WHERE id = ?";
        
        return $this->db->execute($sql, [
            $totalRows, 
            $validRows, 
            $this->data['extraction_confidence'],
            $this->data['id']
        ]);
    }
    
    /**
     * Obtener trips asociados al voucher
     */
    public function getTrips()
    {
        if (!isset($this->data['id'])) {
            return [];
        }
        
        return $this->db->fetchAll("
            SELECT t.*, c.name as company_name, c.identifier as company_identifier
            FROM trips t
            LEFT JOIN companies c ON t.company_id = c.id
            WHERE t.voucher_id = ?
            ORDER BY t.trip_date DESC, t.company_id
        ", [$this->data['id']]);
    }
    
    /**
     * Obtener estadísticas de trips por empresa
     */
    public function getTripStatsByCompany()
    {
        if (!isset($this->data['id'])) {
            return [];
        }
        
        return $this->db->fetchAll("
            SELECT 
                c.id as company_id,
                c.name as company_name,
                c.identifier as company_identifier,
                COUNT(t.id) as trip_count,
                SUM(t.amount) as total_amount,
                AVG(t.amount) as avg_amount,
                COUNT(DISTINCT t.vehicle_number) as vehicle_count
            FROM trips t
            LEFT JOIN companies c ON t.company_id = c.id
            WHERE t.voucher_id = ?
            GROUP BY c.id, c.name, c.identifier
            ORDER BY total_amount DESC
        ", [$this->data['id']]);
    }
    
    /**
     * Obtener reportes generados desde este voucher
     */
    public function getReports()
    {
        if (!isset($this->data['id'])) {
            return [];
        }
        
        return $this->db->fetchAll("
            SELECT r.*, c.name as company_name
            FROM reports r
            LEFT JOIN companies c ON r.company_id = c.id
            WHERE r.voucher_id = ?
            ORDER BY r.generation_date DESC
        ", [$this->data['id']]);
    }
    
    /**
     * Verificar si el archivo existe físicamente
     */
    public function fileExists()
    {
        return !empty($this->data['file_path']) && file_exists($this->data['file_path']);
    }
    
    /**
     * Obtener tamaño de archivo formateado
     */
    public function getFormattedFileSize()
    {
        if (empty($this->data['file_size'])) {
            return 'Desconocido';
        }
        
        $size = $this->data['file_size'];
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, 2) . ' ' . $units[$i];
    }
    
    /**
     * Obtener porcentaje de éxito en extracción
     */
    public function getExtractionSuccessRate()
    {
        if (empty($this->data['total_rows_found']) || $this->data['total_rows_found'] == 0) {
            return 0;
        }
        
        return round(($this->data['valid_rows_extracted'] / $this->data['total_rows_found']) * 100, 2);
    }
    
    /**
     * Verificar si está procesado
     */
    public function isProcessed()
    {
        return $this->data['status'] === self::STATUS_PROCESSED;
    }
    
    /**
     * Verificar si tiene error
     */
    public function hasError()
    {
        return $this->data['status'] === self::STATUS_ERROR;
    }
    
    /**
     * Eliminar voucher y archivos asociados
     */
    public function delete()
    {
        if (!isset($this->data['id'])) {
            return false;
        }
        
        // Eliminar archivo físico si existe
        if ($this->fileExists()) {
            unlink($this->data['file_path']);
        }
        
        // Eliminar trips asociados (CASCADE)
        $this->db->execute("DELETE FROM trips WHERE voucher_id = ?", [$this->data['id']]);
        
        // Eliminar voucher
        return $this->db->execute("DELETE FROM vouchers WHERE id = ?", [$this->data['id']]);
    }
    
    /**
     * Convertir a array
     */
    public function toArray()
    {
        $result = $this->data;
        
        // Agregar campos calculados
        $result['formatted_file_size'] = $this->getFormattedFileSize();
        $result['extraction_success_rate'] = $this->getExtractionSuccessRate();
        $result['is_processed'] = $this->isProcessed();
        $result['has_error'] = $this->hasError();
        $result['file_exists'] = $this->fileExists();
        
        return $result;
    }
    
    /**
     * Convertir a JSON
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }
}
?>