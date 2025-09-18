<?php
// ========================================
// app/Repositories/CompanyRepository.php
// Repositorio para gestión de empresas
// ========================================

namespace App\Repositories;

use Exception;

/**
 * CompanyRepository - Repositorio especializado para empresas
 * 
 * Maneja consultas complejas relacionadas con empresas:
 * - Búsquedas por identificador
 * - Estadísticas de empresas
 * - Reportes y trips por empresa
 * - Configuraciones Capital Transport
 */
class CompanyRepository extends BaseRepository
{
    /** @var string */
    protected $table = 'companies';
    
    /** @var array */
    protected $fillable = [
        'name', 'identifier', 'legal_name', 'contact_person', 
        'phone', 'email', 'capital_percentage', 'current_payment_no',
        'last_payment_year', 'is_active'
    ];
    
    /** @var array */
    protected $searchable = ['name', 'identifier', 'legal_name', 'contact_person'];
    
    /** @var array */
    protected $sortable = ['id', 'name', 'identifier', 'capital_percentage', 'created_at'];
    
    /** @var string */
    protected $defaultSort = 'name';
    
    /** @var string */
    protected $defaultOrder = 'ASC';
    
    /**
     * Buscar empresa por identificador
     */
    public function findByIdentifier($identifier)
    {
        return $this->findBy('identifier', $identifier);
    }
    
    /**
     * Obtener empresas activas
     */
    public function getActive()
    {
        return $this->findAllBy('is_active', 1);
    }
    
    /**
     * Obtener empresas con estadísticas completas
     */
    public function getWithStats()
    {
        $sql = "
            SELECT 
                c.*,
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
            WHERE c.is_active = 1
            GROUP BY c.id
            ORDER BY c.name ASC
        ";
        
        return $this->fetchAll($sql);
    }
    
    /**
     * Obtener estadísticas por empresa
     */
    public function getCompanyStats($companyId)
    {
        $sql = "
            SELECT 
                c.name,
                c.identifier,
                c.capital_percentage,
                COUNT(DISTINCT v.id) as vouchers_processed,
                COUNT(DISTINCT t.id) as trips_extracted,
                COUNT(DISTINCT r.id) as reports_generated,
                COALESCE(SUM(t.amount), 0) as total_amount,
                COALESCE(SUM(r.total_payment), 0) as total_payments,
                COALESCE(SUM(r.capital_deduction), 0) as total_deductions,
                COALESCE(AVG(t.amount), 0) as avg_trip_amount,
                COUNT(DISTINCT t.vehicle_number) as unique_vehicles,
                MIN(t.trip_date) as first_trip_date,
                MAX(t.trip_date) as last_trip_date
            FROM companies c
            LEFT JOIN trips t ON c.id = t.company_id
            LEFT JOIN vouchers v ON t.voucher_id = v.id
            LEFT JOIN reports r ON c.id = r.company_id
            WHERE c.id = ?
            GROUP BY c.id
        ";
        
        return $this->fetch($sql, [$companyId]);
    }
    
    /**
     * Obtener estadísticas mensuales por empresa
     */
    public function getMonthlyStats($companyId, $year = null)
    {
        $year = $year ?: date('Y');
        
        $sql = "
            SELECT 
                MONTH(t.trip_date) as month,
                MONTHNAME(t.trip_date) as month_name,
                COUNT(t.id) as trips_count,
                SUM(t.amount) as total_amount,
                AVG(t.amount) as avg_amount,
                COUNT(DISTINCT t.voucher_id) as vouchers_count,
                COUNT(DISTINCT t.vehicle_number) as vehicles_count,
                MIN(t.trip_date) as first_trip,
                MAX(t.trip_date) as last_trip
            FROM trips t
            WHERE t.company_id = ? AND YEAR(t.trip_date) = ?
            GROUP BY MONTH(t.trip_date), MONTHNAME(t.trip_date)
            ORDER BY MONTH(t.trip_date)
        ";
        
        return $this->fetchAll($sql, [$companyId, $year]);
    }
    
    /**
     * Obtener top empresas por ingresos
     */
    public function getTopByRevenue($limit = 10)
    {
        $sql = "
            SELECT 
                c.*,
                COALESCE(SUM(t.amount), 0) as total_revenue,
                COUNT(t.id) as trip_count
            FROM companies c
            LEFT JOIN trips t ON c.id = t.company_id
            WHERE c.is_active = 1
            GROUP BY c.id
            ORDER BY total_revenue DESC
            LIMIT ?
        ";
        
        return $this->fetchAll($sql, [$limit]);
    }
    
    /**
     * Obtener empresas por rango de porcentaje capital
     */
    public function getByCapitalPercentageRange($min, $max)
    {
        $sql = "
            SELECT * FROM companies 
            WHERE capital_percentage >= ? AND capital_percentage <= ?
            AND is_active = 1
            ORDER BY capital_percentage ASC
        ";
        
        return $this->fetchAll($sql, [$min, $max]);
    }
    
    /**
     * Buscar empresas con actividad reciente
     */
    public function getWithRecentActivity($days = 30)
    {
        $sql = "
            SELECT DISTINCT c.*
            FROM companies c
            INNER JOIN trips t ON c.id = t.company_id
            WHERE t.trip_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            AND c.is_active = 1
            ORDER BY c.name ASC
        ";
        
        return $this->fetchAll($sql, [$days]);
    }
    
    /**
     * Obtener empresas sin actividad
     */
    public function getInactive($days = 90)
    {
        $sql = "
            SELECT c.*
            FROM companies c
            LEFT JOIN trips t ON c.id = t.company_id
            WHERE c.is_active = 1
            AND (
                t.id IS NULL 
                OR MAX(t.trip_date) < DATE_SUB(CURDATE(), INTERVAL ? DAY)
            )
            GROUP BY c.id
            ORDER BY c.name ASC
        ";
        
        return $this->fetchAll($sql, [$days]);
    }
    
    /**
     * Actualizar número de pago de empresa
     */
    public function updatePaymentNumber($companyId, $paymentNo, $year = null)
    {
        $year = $year ?: date('Y');
        
        $data = [
            'current_payment_no' => $paymentNo,
            'last_payment_year' => $year
        ];
        
        return $this->update($companyId, $data);
    }
    
    /**
     * Resetear numeración de pagos por año nuevo
     */
    public function resetPaymentNumbers()
    {
        $currentYear = date('Y');
        
        $sql = "
            UPDATE companies 
            SET current_payment_no = 1, last_payment_year = ?
            WHERE last_payment_year < ? OR last_payment_year IS NULL
        ";
        
        return $this->db->execute($sql, [$currentYear, $currentYear]);
    }
    
    /**
     * Obtener configuración Capital Transport de todas las empresas
     */
    public function getCapitalConfigs()
    {
        $sql = "
            SELECT 
                id,
                name,
                identifier,
                capital_percentage,
                current_payment_no,
                last_payment_year
            FROM companies 
            WHERE is_active = 1
            ORDER BY name ASC
        ";
        
        return $this->fetchAll($sql);
    }
    
    /**
     * Buscar empresas por múltiples identificadores
     */
    public function findByIdentifiers(array $identifiers)
    {
        if (empty($identifiers)) {
            return [];
        }
        
        $placeholders = str_repeat('?,', count($identifiers) - 1) . '?';
        $sql = "SELECT * FROM companies WHERE identifier IN ($placeholders) AND is_active = 1";
        
        return $this->fetchAll($sql, $identifiers);
    }
    
    /**
     * Obtener resumen financiero por empresa
     */
    public function getFinancialSummary($companyId, $year = null)
    {
        $year = $year ?: date('Y');
        
        $sql = "
            SELECT 
                c.name,
                c.identifier,
                c.capital_percentage,
                COUNT(r.id) as reports_count,
                COALESCE(SUM(r.subtotal), 0) as total_subtotal,
                COALESCE(SUM(r.capital_deduction), 0) as total_deductions,
                COALESCE(SUM(r.total_payment), 0) as total_payments,
                COALESCE(AVG(r.capital_deduction), 0) as avg_deduction,
                MIN(r.payment_date) as first_payment,
                MAX(r.payment_date) as last_payment,
                MAX(r.ytd_amount) as current_ytd
            FROM companies c
            LEFT JOIN reports r ON c.id = r.company_id AND YEAR(r.payment_date) = ?
            WHERE c.id = ?
            GROUP BY c.id
        ";
        
        return $this->fetch($sql, [$year, $companyId]);
    }
    
    /**
     * Validar identificador único
     */
    public function validateUniqueIdentifier($identifier, $excludeId = null)
    {
        $sql = "SELECT COUNT(*) as count FROM companies WHERE identifier = ?";
        $params = [$identifier];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $result = $this->fetch($sql, $params);
        return $result['count'] == 0;
    }
    
    /**
     * Buscar con filtros avanzados
     */
    public function searchWithFilters($filters = [])
    {
        $where = ["c.is_active = 1"];
        $params = [];
        
        // Filtro por nombre
        if (!empty($filters['name'])) {
            $where[] = "c.name LIKE ?";
            $params[] = "%" . $filters['name'] . "%";
        }
        
        // Filtro por identificador
        if (!empty($filters['identifier'])) {
            $where[] = "c.identifier LIKE ?";
            $params[] = "%" . $filters['identifier'] . "%";
        }
        
        // Filtro por rango de porcentaje
        if (!empty($filters['min_percentage'])) {
            $where[] = "c.capital_percentage >= ?";
            $params[] = $filters['min_percentage'];
        }
        
        if (!empty($filters['max_percentage'])) {
            $where[] = "c.capital_percentage <= ?";
            $params[] = $filters['max_percentage'];
        }
        
        // Filtro por actividad reciente
        if (!empty($filters['has_recent_activity'])) {
            $days = intval($filters['activity_days'] ?? 30);
            $where[] = "EXISTS (SELECT 1 FROM trips t WHERE t.company_id = c.id AND t.trip_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY))";
            $params[] = $days;
        }
        
        $sql = "
            SELECT c.*,
                   COUNT(DISTINCT t.id) as trip_count,
                   MAX(t.trip_date) as last_trip_date
            FROM companies c
            LEFT JOIN trips t ON c.id = t.company_id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY c.id
            ORDER BY c.name ASC
        ";
        
        return $this->fetchAll($sql, $params);
    }
}
?>