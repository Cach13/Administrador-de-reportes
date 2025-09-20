<?php
// ========================================
// app/Models/Report.php
// Modelo para entidad Report (Capital Transport)
// ========================================

namespace App\Models;

use Database;
use Exception;

/**
 * Report Model - Representa un reporte Capital Transport generado
 * 
 * Maneja todas las operaciones relacionadas con reportes:
 * - Datos de pagos Capital Transport
 * - Cálculos YTD y deducciones
 * - Estados de reporte
 * - Archivos generados
 */
class Report
{
    /** @var Database */
    private $db;
    
    /** @var array */
    private $data = [];
    
    /** @var array */
    private $fillable = [
        'company_id', 'voucher_id', 'payment_no', 'week_start', 'week_end',
        'payment_date', 'payment_total', 'ytd_amount', 'subtotal',
        'capital_percentage', 'capital_deduction', 'total_payment',
        'total_trips', 'total_vehicle_count', 'file_path', 'status'
    ];
    
    /**
     * Estados válidos de reporte
     */
    const STATUS_GENERATED = 'generated';
    const STATUS_SENT = 'sent';
    const STATUS_PAID = 'paid';
    
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
     * Guardar reporte
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
     * Crear reporte en base de datos
     */
    private function create()
    {
        $this->validate();
        $this->calculateFields();
        
        $fields = array_keys($this->data);
        $placeholders = ':' . implode(', :', $fields);
        
        $sql = "INSERT INTO reports (" . implode(', ', $fields) . ") 
                VALUES (" . $placeholders . ")";
        
        try {
            $this->db->execute($sql, $this->data);
            $this->data['id'] = $this->db->lastInsertId();
            return true;
            
        } catch (Exception $e) {
            throw new Exception("Error creando reporte: " . $e->getMessage());
        }
    }
    
    /**
     * Actualizar reporte existente
     */
    private function update()
    {
        if (!isset($this->data['id'])) {
            throw new Exception("Cannot update report without ID");
        }
        
        $id = $this->data['id'];
        unset($this->data['id']);
        
        $this->calculateFields();
        
        $setClause = [];
        foreach ($this->data as $key => $value) {
            $setClause[] = "$key = :$key";
        }
        
        $sql = "UPDATE reports SET " . implode(', ', $setClause) . " WHERE id = :id";
        $this->data['id'] = $id;
        
        try {
            return $this->db->execute($sql, $this->data);
            
        } catch (Exception $e) {
            throw new Exception("Error actualizando reporte: " . $e->getMessage());
        }
    }
    
    /**
     * Calcular campos automáticos
     */
    private function calculateFields()
    {
        // Calcular deducción capital
        if (isset($this->data['subtotal']) && isset($this->data['capital_percentage'])) {
            $this->data['capital_deduction'] = round(
                ($this->data['subtotal'] * $this->data['capital_percentage']) / 100, 
                2
            );
        }
        
        // Calcular pago total
        if (isset($this->data['subtotal']) && isset($this->data['capital_deduction'])) {
            $this->data['total_payment'] = round(
                $this->data['subtotal'] - $this->data['capital_deduction'], 
                2
            );
        }
        
        // Calcular YTD si no está establecido
        if (empty($this->data['ytd_amount']) && isset($this->data['company_id']) && isset($this->data['payment_date'])) {
            $this->data['ytd_amount'] = $this->calculateYTD();
        }
    }
    
    /**
     * Calcular YTD (Year To Date)
     */
    private function calculateYTD()
    {
        if (!isset($this->data['company_id']) || !isset($this->data['payment_date'])) {
            return 0;
        }
        
        $year = date('Y', strtotime($this->data['payment_date']));
        $upToDate = $this->data['payment_date'];
        
        $result = $this->db->fetch(
            "SELECT COALESCE(SUM(total_payment), 0) as ytd_total
             FROM reports 
             WHERE company_id = ? 
             AND YEAR(payment_date) = ?
             AND payment_date <= ?
             AND id != ?",
            [
                $this->data['company_id'], 
                $year, 
                $upToDate,
                $this->data['id'] ?? 0
            ]
        );
        
        $previousYTD = floatval($result['ytd_total'] ?? 0);
        $currentPayment = floatval($this->data['total_payment'] ?? 0);
        
        return $previousYTD + $currentPayment;
    }
    
    /**
     * Validar datos del reporte
     */
    private function validate()
    {
        $errors = [];
        
        // Company ID requerido
        if (empty($this->data['company_id'])) {
            $errors[] = "Company ID es requerido";
        }
        
        // Voucher ID requerido
        if (empty($this->data['voucher_id'])) {
            $errors[] = "Voucher ID es requerido";
        }
        
        // Payment number requerido
        if (empty($this->data['payment_no'])) {
            $errors[] = "Número de pago es requerido";
        }
        
        // Fechas requeridas
        $requiredDates = ['week_start', 'week_end', 'payment_date'];
        foreach ($requiredDates as $dateField) {
            if (empty($this->data[$dateField])) {
                $errors[] = ucfirst(str_replace('_', ' ', $dateField)) . " es requerido";
            }
        }
        
        // Montos requeridos
        $requiredAmounts = ['payment_total', 'subtotal', 'capital_percentage'];
        foreach ($requiredAmounts as $amountField) {
            if (!isset($this->data[$amountField]) || $this->data[$amountField] < 0) {
                $errors[] = ucfirst(str_replace('_', ' ', $amountField)) . " debe ser mayor o igual a 0";
            }
        }
        
        // Status válido
        if (!empty($this->data['status'])) {
            $validStatuses = [self::STATUS_GENERATED, self::STATUS_SENT, self::STATUS_PAID];
            if (!in_array($this->data['status'], $validStatuses)) {
                $errors[] = "Estado no válido";
            }
        }
        
        if (!empty($errors)) {
            throw new Exception("Errores de validación: " . implode(', ', $errors));
        }
    }
    
    /**
     * Buscar reporte por ID
     */
    public static function find($id)
    {
        $db = Database::getInstance();
        $data = $db->fetch("SELECT * FROM reports WHERE id = ?", [$id]);
        
        return $data ? new self($data) : null;
    }
    
    /**
     * Obtener todos los reportes
     */
    public static function all($limit = null)
    {
        $db = Database::getInstance();
        $limitClause = $limit ? "LIMIT " . intval($limit) : "";
        $results = $db->fetchAll("SELECT * FROM reports ORDER BY generation_date DESC $limitClause");
        
        $reports = [];
        foreach ($results as $data) {
            $reports[] = new self($data);
        }
        
        return $reports;
    }
    
    /**
     * Obtener reportes por empresa
     */
    public static function getByCompany($companyId, $year = null, $limit = null)
    {
        $db = Database::getInstance();
        $year = $year ?: date('Y');
        $limitClause = $limit ? "LIMIT " . intval($limit) : "";
        
        $results = $db->fetchAll(
            "SELECT * FROM reports 
             WHERE company_id = ? AND YEAR(payment_date) = ?
             ORDER BY payment_no DESC $limitClause",
            [$companyId, $year]
        );
        
        $reports = [];
        foreach ($results as $data) {
            $reports[] = new self($data);
        }
        
        return $reports;
    }
    
    /**
     * Obtener reportes por voucher
     */
    public static function getByVoucher($voucherId)
    {
        $db = Database::getInstance();
        $results = $db->fetchAll(
            "SELECT * FROM reports WHERE voucher_id = ? ORDER BY generation_date DESC",
            [$voucherId]
        );
        
        $reports = [];
        foreach ($results as $data) {
            $reports[] = new self($data);
        }
        
        return $reports;
    }
    
    /**
     * Obtener siguiente número de pago para empresa
     */
    public static function getNextPaymentNumber($companyId)
    {
        $db = Database::getInstance();
        $currentYear = date('Y');
        
        $result = $db->fetch(
            "SELECT MAX(payment_no) as max_payment 
             FROM reports 
             WHERE company_id = ? AND YEAR(payment_date) = ?",
            [$companyId, $currentYear]
        );
        
        return ($result['max_payment'] ?? 0) + 1;
    }
    
    /**
     * Actualizar estado del reporte
     */
    public function updateStatus($status)
    {
        if (!isset($this->data['id'])) {
            throw new Exception("Report ID is required");
        }
        
        $validStatuses = [self::STATUS_GENERATED, self::STATUS_SENT, self::STATUS_PAID];
        if (!in_array($status, $validStatuses)) {
            throw new Exception("Estado no válido: $status");
        }
        
        $this->data['status'] = $status;
        
        return $this->db->execute(
            "UPDATE reports SET status = ? WHERE id = ?",
            [$status, $this->data['id']]
        );
    }
    
    /**
     * Obtener empresa asociada
     */
    public function getCompany()
    {
        if (!isset($this->data['company_id'])) {
            return null;
        }
        
        return Company::find($this->data['company_id']);
    }
    
    /**
     * Obtener voucher asociado
     */
    public function getVoucher()
    {
        if (!isset($this->data['voucher_id'])) {
            return null;
        }
        
        return Voucher::find($this->data['voucher_id']);
    }
    
    /**
     * Obtener trips asociados
     */
    public function getTrips()
    {
        if (!isset($this->data['voucher_id']) || !isset($this->data['company_id'])) {
            return [];
        }
        
        return $this->db->fetchAll("
            SELECT * FROM trips 
            WHERE voucher_id = ? AND company_id = ?
            ORDER BY trip_date, vehicle_number
        ", [$this->data['voucher_id'], $this->data['company_id']]);
    }
    
    /**
     * Verificar si el archivo existe físicamente
     */
    public function fileExists()
    {
        return !empty($this->data['file_path']) && file_exists($this->data['file_path']);
    }
    
    /**
     * Obtener nombre de archivo para descarga
     */
    public function getDownloadFilename()
    {
        $company = $this->getCompany();
        $companyName = $company ? $company->identifier : 'UNK';
        
        return sprintf(
            'CapitalTransport_%s_Payment_%03d_%s.pdf',
            $companyName,
            $this->data['payment_no'],
            date('Y-m-d', strtotime($this->data['payment_date']))
        );
    }
    
    /**
     * Calcular porcentaje de deducción efectivo
     */
    public function getEffectiveDeductionPercentage()
    {
        if (empty($this->data['subtotal']) || $this->data['subtotal'] == 0) {
            return 0;
        }
        
        return round(($this->data['capital_deduction'] / $this->data['subtotal']) * 100, 2);
    }
    
    /**
     * Verificar si está pagado
     */
    public function isPaid()
    {
        return $this->data['status'] === self::STATUS_PAID;
    }
    
    /**
     * Verificar si está enviado
     */
    public function isSent()
    {
        return in_array($this->data['status'], [self::STATUS_SENT, self::STATUS_PAID]);
    }
    
    /**
     * Eliminar reporte y archivo asociado
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
        
        return $this->db->execute("DELETE FROM reports WHERE id = ?", [$this->data['id']]);
    }
    
    /**
     * Convertir a array
     */
    public function toArray()
    {
        $result = $this->data;
        
        // Agregar campos calculados
        $result['effective_deduction_percentage'] = $this->getEffectiveDeductionPercentage();
        $result['download_filename'] = $this->getDownloadFilename();
        $result['is_paid'] = $this->isPaid();
        $result['is_sent'] = $this->isSent();
        $result['file_exists'] = $this->fileExists();
        
        // Formatear fechas
        if (!empty($result['week_start'])) {
            $result['formatted_week_start'] = date('d/m/Y', strtotime($result['week_start']));
        }
        if (!empty($result['week_end'])) {
            $result['formatted_week_end'] = date('d/m/Y', strtotime($result['week_end']));
        }
        if (!empty($result['payment_date'])) {
            $result['formatted_payment_date'] = date('d/m/Y', strtotime($result['payment_date']));
        }
        
        // Formatear montos
        $amountFields = ['payment_total', 'ytd_amount', 'subtotal', 'capital_deduction', 'total_payment'];
        foreach ($amountFields as $field) {
            if (isset($result[$field])) {
                $result['formatted_' . $field] = '$' . number_format($result[$field], 2);
            }
        }
        
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