<?php
// ========================================
// app/Repositories/VoucherRepository.php
// Repositorio para gestión de vouchers
// ========================================

namespace App\Repositories;

use Exception;

/**
 * VoucherRepository - Repositorio especializado para vouchers
 * 
 * Maneja consultas complejas relacionadas con vouchers:
 * - Estados de procesamiento
 * - Estadísticas de extracción
 * - Relaciones con trips y reportes
 * - Filtros por fecha y usuario
 */
class VoucherRepository extends BaseRepository
{
    /** @var string */
    protected $table = 'vouchers';
    
    /** @var array */
    protected $fillable = [
        'voucher_number', 'original_filename', 'file_path', 'file_size',
        'file_format', 'status', 'total_rows_found', 'valid_rows_extracted',
        'extraction_confidence', 'upload_date'
    ];
    
    /** @var array */
    protected $searchable = ['voucher_number', 'original_filename'];
    
    /** @var array */
    protected $sortable = ['id', 'voucher_number', 'upload_date', 'status', 'file_size'];
    
    /** @var string */
    protected $defaultSort = 'upload_date';
    
    /**
     * Buscar voucher por número
     */
    public function findByNumber($voucherNumber)
    {
        return $this->findBy('voucher_number', $voucherNumber);
    }
    
    /**
     * Obtener vouchers por estado
     */
    public function getByStatus($status)
    {
        return $this->findAllBy('status', $status);
    }
    
    /**
     * Obtener vouchers pendientes de procesar
     */
    public function getPending()
    {
        return $this->getByStatus('uploaded');
    }
    
    /**
     * Obtener vouchers procesados
     */
    public function getProcessed()
    {
        return $this->getByStatus('processed');
    }
    
    /**
     * Obtener vouchers con errores
     */
    public function getWithErrors()
    {
        return $this->getByStatus('error');
    }
    
    /**
     * Obtener vouchers con estadísticas completas
     */
    public function getWithStats()
    {
        $sql = "
            SELECT 
                v.*,
                COUNT(DISTINCT t.id) as trips_count,
                COUNT(DISTINCT t.company_id) as companies_count,
                COALESCE(SUM(t.amount), 0) as total_amount,
                COUNT(DISTINCT r.id) as reports_generated,
                u.full_name as uploaded_by_name
            FROM vouchers v
            LEFT JOIN trips t ON v.id = t.voucher_id
            LEFT JOIN reports r ON v.id = r.voucher_id
            LEFT JOIN users u ON v.uploaded_by = u.id
            GROUP BY v.id
            ORDER BY v.upload_date DESC
        ";
        
        return $this->fetchAll($sql);
    }
    
    /**
     * Obtener estadísticas por voucher
     */
    public function getVoucherStats($voucherId)
    {
        $sql = "
            SELECT 
                v.*,
                COUNT(DISTINCT t.id) as trips_extracted,
                COUNT(DISTINCT t.company_id) as companies_found,
                COUNT(DISTINCT t.vehicle_number) as vehicles_count,
                COALESCE(SUM(t.amount), 0) as total_amount,
                COALESCE(AVG(t.amount), 0) as avg_amount,
                MIN(t.trip_date) as earliest_trip,
                MAX(t.trip_date) as latest_trip,
                COUNT(DISTINCT r.id) as reports_generated,
                u.full_name as uploaded_by_name
            FROM vouchers v
            LEFT JOIN trips t ON v.id = t.voucher_id
            LEFT JOIN reports r ON v.id = r.voucher_id
            LEFT JOIN users u ON v.uploaded_by = u.id
            WHERE v.id = ?
            GROUP BY v.id
        ";
        
        return $this->fetch($sql, [$voucherId]);
    }
    
    /**
     * Obtener vouchers por rango de fechas
     */
    public function getByDateRange($startDate, $endDate)
    {
        $sql = "
            SELECT * FROM vouchers 
            WHERE upload_date >= ? AND upload_date <= ?
            ORDER BY upload_date DESC
        ";
        
        return $this->fetchAll($sql, [$startDate, $endDate]);
    }
    
    /**
     * Obtener vouchers recientes
     */
    public function getRecent($days = 7, $limit = 20)
    {
        $sql = "
            SELECT v.*,
                   u.full_name as uploaded_by_name
            FROM vouchers v
            LEFT JOIN users u ON v.uploaded_by = u.id
            WHERE v.upload_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ORDER BY v.upload_date DESC
            LIMIT ?
        ";
        
        return $this->fetchAll($sql, [$days, $limit]);
    }
    
    /**
     * Obtener estadísticas de extracción por período
     */
    public function getExtractionStats($period = 'month')
    {
        $dateFormat = $period === 'day' ? '%Y-%m-%d' : 
                     ($period === 'week' ? '%Y-%u' : '%Y-%m');
        
        $sql = "
            SELECT 
                DATE_FORMAT(upload_date, ?) as period,
                COUNT(*) as vouchers_count,
                SUM(total_rows_found) as total_rows,
                SUM(valid_rows_extracted) as valid_rows,
                AVG(extraction_confidence) as avg_confidence,
                COUNT(CASE WHEN status = 'processed' THEN 1 END) as processed_count,
                COUNT(CASE WHEN status = 'error' THEN 1 END) as error_count
            FROM vouchers
            GROUP BY DATE_FORMAT(upload_date, ?)
            ORDER BY period DESC
        ";
        
        return $this->fetchAll($sql, [$dateFormat, $dateFormat]);
    }
    
    /**
     * Obtener vouchers con baja confianza de extracción
     */
    public function getLowConfidence($threshold = 0.8)
    {
        $sql = "
            SELECT v.*,
                   COUNT(t.id) as trips_count
            FROM vouchers v
            LEFT JOIN trips t ON v.id = t.voucher_id
            WHERE v.extraction_confidence < ?
            AND v.status = 'processed'
            GROUP BY v.id
            ORDER BY v.extraction_confidence ASC
        ";
        
        return $this->fetchAll($sql, [$threshold]);
    }
    
    /**
     * Obtener vouchers por formato de archivo
     */
    public function getByFormat($format)
    {
        return $this->findAllBy('file_format', $format);
    }
    
    /**
     * Obtener estadísticas por formato
     */
    public function getFormatStats()
    {
        $sql = "
            SELECT 
                file_format,
                COUNT(*) as count,
                AVG(file_size) as avg_size,
                SUM(file_size) as total_size,
                AVG(extraction_confidence) as avg_confidence,
                COUNT(CASE WHEN status = 'processed' THEN 1 END) as processed_count,
                COUNT(CASE WHEN status = 'error' THEN 1 END) as error_count
            FROM vouchers
            GROUP BY file_format
            ORDER BY count DESC
        ";
        
        return $this->fetchAll($sql);
    }
    
    /**
     * Obtener vouchers grandes (por tamaño de archivo)
     */
    public function getLargeFiles($minSize = 5242880) // 5MB default
    {
        $sql = "
            SELECT *,
                   ROUND(file_size/1024/1024, 2) as size_mb
            FROM vouchers 
            WHERE file_size >= ?
            ORDER BY file_size DESC
        ";
        
        return $this->fetchAll($sql, [$minSize]);
    }
    
    /**
     * Obtener vouchers por usuario
     */
    public function getByUser($userId, $limit = null)
    {
        $sql = "
            SELECT v.*,
                   COUNT(t.id) as trips_count,
                   COUNT(DISTINCT r.id) as reports_count
            FROM vouchers v
            LEFT JOIN trips t ON v.id = t.voucher_id
            LEFT JOIN reports r ON v.id = r.voucher_id
            WHERE v.uploaded_by = ?
            GROUP BY v.id
            ORDER BY v.upload_date DESC
        ";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        return $this->fetchAll($sql, [$userId]);
    }
    
    /**
     * Obtener resumen de vouchers por estado
     */
    public function getStatusSummary()
    {
        $sql = "
            SELECT 
                status,
                COUNT(*) as count,
                AVG(file_size) as avg_size,
                SUM(total_rows_found) as total_rows,
                SUM(valid_rows_extracted) as valid_rows,
                AVG(extraction_confidence) as avg_confidence
            FROM vouchers
            GROUP BY status
            ORDER BY 
                CASE status 
                    WHEN 'uploaded' THEN 1
                    WHEN 'processing' THEN 2
                    WHEN 'processed' THEN 3
                    WHEN 'error' THEN 4
                END
        ";
        
        return $this->fetchAll($sql);
    }
    
    /**
     * Obtener vouchers que requieren revisión manual
     */
    public function getRequiringReview()
    {
        $sql = "
            SELECT v.*,
                   COUNT(t.id) as trips_count,
                   t.manual_review_required
            FROM vouchers v
            LEFT JOIN trips t ON v.id = t.voucher_id
            WHERE v.extraction_confidence < 0.9
               OR EXISTS (
                   SELECT 1 FROM trips tr 
                   WHERE tr.voucher_id = v.id 
                   AND tr.manual_review_required = 1
               )
            GROUP BY v.id
            ORDER BY v.extraction_confidence ASC
        ";
        
        return $this->fetchAll($sql);
    }
    
    /**
     * Buscar vouchers duplicados por nombre de archivo
     */
    public function findDuplicates()
    {
        $sql = "
            SELECT 
                original_filename,
                COUNT(*) as count,
                GROUP_CONCAT(id) as voucher_ids,
                GROUP_CONCAT(voucher_number) as voucher_numbers
            FROM vouchers
            GROUP BY original_filename
            HAVING count > 1
            ORDER BY count DESC
        ";
        
        return $this->fetchAll($sql);
    }
    
    /**
     * Obtener vouchers con archivos faltantes
     */
    public function getMissingFiles()
    {
        // Esta función requiere verificación del sistema de archivos
        // Por ahora retornamos la consulta básica
        $sql = "
            SELECT * FROM vouchers 
            WHERE file_path IS NOT NULL
            ORDER BY upload_date DESC
        ";
        
        $vouchers = $this->fetchAll($sql);
        $missing = [];
        
        foreach ($vouchers as $voucher) {
            if (!file_exists($voucher['file_path'])) {
                $missing[] = $voucher;
            }
        }
        
        return $missing;
    }
    
    /**
     * Actualizar estadísticas de extracción
     */
    public function updateExtractionStats($voucherId, $totalRows, $validRows, $confidence)
    {
        $data = [
            'total_rows_found' => $totalRows,
            'valid_rows_extracted' => $validRows,
            'extraction_confidence' => $confidence
        ];
        
        return $this->update($voucherId, $data);
    }
    
    /**
     * Marcar voucher como procesado
     */
    public function markAsProcessed($voucherId, $notes = null)
    {
        $data = [
            'status' => 'processed',
            'processing_notes' => $notes
        ];
        
        return $this->update($voucherId, $data);
    }
    
    /**
     * Marcar voucher con error
     */
    public function markAsError($voucherId, $errorMessage)
    {
        $data = [
            'status' => 'error',
            'processing_notes' => $errorMessage
        ];
        
        return $this->update($voucherId, $data);
    }
    
    /**
     * Búsqueda avanzada con múltiples filtros
     */
    public function searchWithFilters($filters = [])
    {
        $where = [];
        $params = [];
        
        // Filtro por estado
        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $placeholders = str_repeat('?,', count($filters['status']) - 1) . '?';
                $where[] = "v.status IN ($placeholders)";
                $params = array_merge($params, $filters['status']);
            } else {
                $where[] = "v.status = ?";
                $params[] = $filters['status'];
            }
        }
        
        // Filtro por rango de fechas
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(v.upload_date) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(v.upload_date) <= ?";
            $params[] = $filters['date_to'];
        }
        
        // Filtro por formato
        if (!empty($filters['format'])) {
            $where[] = "v.file_format = ?";
            $params[] = $filters['format'];
        }
        
        // Filtro por tamaño mínimo
        if (!empty($filters['min_size'])) {
            $where[] = "v.file_size >= ?";
            $params[] = $filters['min_size'];
        }
        
        // Filtro por confianza mínima
        if (!empty($filters['min_confidence'])) {
            $where[] = "v.extraction_confidence >= ?";
            $params[] = $filters['min_confidence'];
        }
        
        // Filtro por nombre de archivo
        if (!empty($filters['filename'])) {
            $where[] = "v.original_filename LIKE ?";
            $params[] = "%" . $filters['filename'] . "%";
        }
        
        // Filtro por número de voucher
        if (!empty($filters['voucher_number'])) {
            $where[] = "v.voucher_number LIKE ?";
            $params[] = "%" . $filters['voucher_number'] . "%";
        }
        
        $whereClause = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";
        
        $sql = "
            SELECT v.*,
                   u.full_name as uploaded_by_name,
                   COUNT(DISTINCT t.id) as trips_count,
                   COUNT(DISTINCT t.company_id) as companies_count,
                   COALESCE(SUM(t.amount), 0) as total_amount
            FROM vouchers v
            LEFT JOIN users u ON v.uploaded_by = u.id
            LEFT JOIN trips t ON v.id = t.voucher_id
            {$whereClause}
            GROUP BY v.id
            ORDER BY v.upload_date DESC
        ";
        
        return $this->fetchAll($sql, $params);
    }
    
    /**
     * Limpiar vouchers antiguos (más de X días sin actividad)
     */
    public function cleanupOld($days = 365, $dryRun = true)
    {
        $sql = "
            SELECT v.*
            FROM vouchers v
            LEFT JOIN trips t ON v.id = t.voucher_id
            LEFT JOIN reports r ON v.id = r.voucher_id
            WHERE v.upload_date < DATE_SUB(NOW(), INTERVAL ? DAY)
            AND t.id IS NULL
            AND r.id IS NULL
            ORDER BY v.upload_date ASC
        ";
        
        $oldVouchers = $this->fetchAll($sql, [$days]);
        
        if (!$dryRun && !empty($oldVouchers)) {
            foreach ($oldVouchers as $voucher) {
                // Eliminar archivo físico
                if (file_exists($voucher['file_path'])) {
                    unlink($voucher['file_path']);
                }
                
                // Eliminar registro
                $this->delete($voucher['id']);
            }
        }
        
        return $oldVouchers;
    }
    
    /**
     * Obtener estadísticas generales de la tabla
     */
    public function getGeneralStats()
    {
        $sql = "
            SELECT 
                COUNT(*) as total_vouchers,
                SUM(file_size) as total_size,
                AVG(file_size) as avg_size,
                MAX(file_size) as max_size,
                MIN(file_size) as min_size,
                SUM(total_rows_found) as total_rows_found,
                SUM(valid_rows_extracted) as total_valid_rows,
                AVG(extraction_confidence) as avg_confidence,
                COUNT(CASE WHEN status = 'uploaded' THEN 1 END) as pending_count,
                COUNT(CASE WHEN status = 'processing' THEN 1 END) as processing_count,
                COUNT(CASE WHEN status = 'processed' THEN 1 END) as processed_count,
                COUNT(CASE WHEN status = 'error' THEN 1 END) as error_count,
                MIN(upload_date) as oldest_upload,
                MAX(upload_date) as newest_upload
            FROM vouchers
        ";
        
        return $this->fetch($sql);
    }
    
    /**
     * Generar nuevo número de voucher
     */
    public function generateVoucherNumber()
    {
        $prefix = 'MM-' . date('Y') . '-';
        
        $sql = "
            SELECT voucher_number 
            FROM vouchers 
            WHERE voucher_number LIKE ? 
            ORDER BY CAST(SUBSTRING(voucher_number, ?) AS UNSIGNED) DESC 
            LIMIT 1
        ";
        
        $result = $this->fetch($sql, [$prefix . '%', strlen($prefix) + 1]);
        
        if ($result) {
            $lastNumber = intval(substr($result['voucher_number'], strlen($prefix)));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
    }
}
?> 