<?php
// ========================================
// app/Models/Company.php
// Modelo para entidad Empresa
// ========================================

namespace App\Models;

use Database;
use Exception;

/**
 * Company Model - Representa una empresa del sistema
 * 
 * Maneja todas las operaciones relacionadas con empresas:
 * - Gestión de datos de empresa
 * - Configuración de porcentajes Capital Transport
 * - Numeración de pagos
 * - Estadísticas de trips y reportes
 */
class Company
{
    /** @var Database */
    private $db;
    
    /** @var array */
    private $data = [];
    
    /** @var array */
    private $fillable = [
        'name', 'identifier', 'legal_name', 'contact_person', 
        'phone', 'email', 'capital_percentage', 'current_payment_no',
        'last_payment_year', 'is_active'
    ];
    
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
     * Guardar empresa
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
     * Crear empresa en base de datos
     */
    private function create()
    {
        $this->validate();
        
        $fields = array_keys($this->data);
        $placeholders = ':' . implode(', :', $fields);
        
        $sql = "INSERT INTO companies (" . implode(', ', $fields) . ") 
                VALUES (" . $placeholders . ")";
        
        try {
            $this->db->execute($sql, $this->data);
            $this->data['id'] = $this->db->lastInsertId();
            return true;
            
        } catch (Exception $e) {
            throw new Exception("Error creando empresa: " . $e->getMessage());
        }
    }
    
    /**
     * Actualizar empresa existente
     */
    private function update()
    {
        if (!isset($this->data['id'])) {
            throw new Exception("Cannot update company without ID");
        }
        
        $id = $this->data['id'];
        unset($this->data['id']);
        
        $setClause = [];
        foreach ($this->data as $key => $value) {
            $setClause[] = "$key = :$key";
        }
        
        $sql = "UPDATE companies SET " . implode(', ', $setClause) . " WHERE id = :id";
        $this->data['id'] = $id;
        
        try {
            return $this->db->execute($sql, $this->data);
            
        } catch (Exception $e) {
            throw new Exception("Error actualizando empresa: " . $e->getMessage());
        }
    }
    
    /**
     * Validar datos de la empresa
     */
    private function validate()
    {
        $errors = [];
        
        // Name requerido
        if (empty($this->data['name'])) {
            $errors[] = "Nombre de empresa es requerido";
        }
        
        // Identifier requerido, único y 3 caracteres
        if (empty($this->data['identifier'])) {
            $errors[] = "Identificador es requerido";
        } elseif (strlen($this->data['identifier']) !== 3) {
            $errors[] = "Identificador debe tener exactamente 3 caracteres";
        } else {
            $existing = $this->db->fetch(
                "SELECT id FROM companies WHERE identifier = ? AND id != ?",
                [$this->data['identifier'], $this->data['id'] ?? 0]
            );
            if ($existing) {
                $errors[] = "Identificador ya existe";
            }
        }
        
        // Capital percentage debe ser válido
        if (isset($this->data['capital_percentage'])) {
            $percentage = floatval($this->data['capital_percentage']);
            if ($percentage < 0 || $percentage > 100) {
                $errors[] = "Porcentaje capital debe estar entre 0 y 100";
            }
        }
        
        if (!empty($errors)) {
            throw new Exception("Errores de validación: " . implode(', ', $errors));
        }
    }
    
    /**
     * Buscar empresa por ID
     */
    public static function find($id)
    {
        $db = Database::getInstance();
        $data = $db->fetch("SELECT * FROM companies WHERE id = ?", [$id]);
        
        return $data ? new self($data) : null;
    }
    
    /**
     * Buscar empresa por identificador
     */
    public static function findByIdentifier($identifier)
    {
        $db = Database::getInstance();
        $data = $db->fetch("SELECT * FROM companies WHERE identifier = ?", [$identifier]);
        
        return $data ? new self($data) : null;
    }
    
    /**
     * Obtener todas las empresas
     */
    public static function all($activeOnly = true)
    {
        $db = Database::getInstance();
        $whereClause = $activeOnly ? "WHERE is_active = 1" : "";
        $results = $db->fetchAll("SELECT * FROM companies $whereClause ORDER BY name");
        
        $companies = [];
        foreach ($results as $data) {
            $companies[] = new self($data);
        }
        
        return $companies;
    }
    
    /**
     * Obtener siguiente número de pago
     */
    public function getNextPaymentNumber()
    {
        if (!isset($this->data['id'])) {
            throw new Exception("Company ID is required");
        }
        
        $current_year = date('Y');
        
        // Si es un año diferente, resetear contador
        if ($this->data['last_payment_year'] != $current_year) {
            $this->data['current_payment_no'] = 1;
            $this->data['last_payment_year'] = $current_year;
            $this->save();
            return 1;
        }
        
        return $this->data['current_payment_no'];
    }
    
    /**
     * Incrementar número de pago
     */
    public function incrementPaymentNumber()
    {
        if (!isset($this->data['id'])) {
            throw new Exception("Company ID is required");
        }
        
        $current_year = date('Y');
        
        // Si es año nuevo, resetear
        if ($this->data['last_payment_year'] != $current_year) {
            $this->data['current_payment_no'] = 1;
            $this->data['last_payment_year'] = $current_year;
        }
        
        $this->data['current_payment_no']++;
        $this->save();
        
        return $this->data['current_payment_no'];
    }
    
    /**
     * Obtener trips de la empresa
     */
    public function getTrips($limit = null)
    {
        if (!isset($this->data['id'])) {
            return [];
        }
        
        $limitClause = $limit ? "LIMIT " . intval($limit) : "";
        
        return $this->db->fetchAll("
            SELECT t.*, v.voucher_number, v.original_filename
            FROM trips t
            LEFT JOIN vouchers v ON t.voucher_id = v.id
            WHERE t.company_id = ?
            ORDER BY t.trip_date DESC
            $limitClause
        ", [$this->data['id']]);
    }
    
    /**
     * Obtener reportes de la empresa
     */
    public function getReports($year = null, $limit = null)
    {
        if (!isset($this->data['id'])) {
            return [];
        }
        
        $year = $year ?: date('Y');
        $limitClause = $limit ? "LIMIT " . intval($limit) : "";
        
        return $this->db->fetchAll("
            SELECT r.*, v.voucher_number
            FROM reports r
            LEFT JOIN vouchers v ON r.voucher_id = v.id
            WHERE r.company_id = ? AND YEAR(r.payment_date) = ?
            ORDER BY r.payment_no DESC
            $limitClause
        ", [$this->data['id'], $year]);
    }
    
    /**
     * Obtener estadísticas de la empresa
     */
    public function getStats()
    {
        if (!isset($this->data['id'])) {
            return null;
        }
        
        $stats = $this->db->fetch("
            SELECT 
                COUNT(DISTINCT v.id) as total_vouchers_processed,
                COUNT(DISTINCT t.id) as total_trips_extracted,
                COUNT(DISTINCT r.id) as total_reports_generated,
                COALESCE(SUM(t.amount), 0) as total_trip_amount,
                COALESCE(SUM(r.total_payment), 0) as total_payments_made,
                COALESCE(SUM(r.capital_deduction), 0) as total_capital_deductions,
                MAX(t.trip_date) as last_trip_date,
                MAX(r.payment_date) as last_payment_date
            FROM companies c
            LEFT JOIN trips t ON c.id = t.company_id
            LEFT JOIN vouchers v ON t.voucher_id = v.id
            LEFT JOIN reports r ON c.id = r.company_id
            WHERE c.id = ?
        ", [$this->data['id']]);
        
        return $stats ?: [];
    }
    
    /**
     * Obtener estadísticas por mes
     */
    public function getMonthlyStats($year = null)
    {
        if (!isset($this->data['id'])) {
            return [];
        }
        
        $year = $year ?: date('Y');
        
        return $this->db->fetchAll("
            SELECT 
                MONTH(t.trip_date) as month,
                MONTHNAME(t.trip_date) as month_name,
                COUNT(t.id) as trips_count,
                SUM(t.amount) as total_amount,
                COUNT(DISTINCT t.voucher_id) as vouchers_count
            FROM trips t
            WHERE t.company_id = ? AND YEAR(t.trip_date) = ?
            GROUP BY MONTH(t.trip_date), MONTHNAME(t.trip_date)
            ORDER BY MONTH(t.trip_date)
        ", [$this->data['id'], $year]);
    }
    
    /**
     * Eliminar empresa (soft delete)
     */
    public function delete()
    {
        if (!isset($this->data['id'])) {
            return false;
        }
        
        // Verificar que no tenga trips asociados
        $tripsCount = $this->db->fetch(
            "SELECT COUNT(*) as count FROM trips WHERE company_id = ?",
            [$this->data['id']]
        )['count'];
        
        if ($tripsCount > 0) {
            throw new Exception("No se puede eliminar empresa con trips asociados");
        }
        
        $sql = "UPDATE companies SET is_active = 0 WHERE id = ?";
        return $this->db->execute($sql, [$this->data['id']]);
    }
    
    /**
     * Convertir a array
     */
    public function toArray()
    {
        return $this->data;
    }
    
    /**
     * Convertir a JSON
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }
    
    /**
     * Verificar si empresa está activa
     */
    public function isActive()
    {
        return !empty($this->data['is_active']);
    }
}
?>