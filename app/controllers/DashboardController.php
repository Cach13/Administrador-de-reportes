<?php
// ========================================
// app/Controllers/DashboardController.php
// Controlador para el dashboard principal
// ========================================

namespace App\Controllers;

use Database;
use Logger;
use Exception;

/**
 * DashboardController - Controlador del dashboard principal
 * 
 * Funcionalidades:
 * - Mostrar estadísticas del sistema
 * - Actividad reciente
 * - Resumen de vouchers
 * - Gráficas básicas
 * - Estado del sistema
 */
class DashboardController extends BaseController
{
    /**
     * Página principal del dashboard
     */
    public function index()
    {
        // Verificar autenticación
        $this->requireAuth();
        
        try {
            // Obtener estadísticas generales
            $stats = $this->getSystemStats();
            
            // Obtener actividad reciente
            $recentActivity = $this->getRecentActivity();
            
            // Obtener vouchers pendientes
            $pendingVouchers = $this->getPendingVouchers();
            
            // Obtener reportes recientes
            $recentReports = $this->getRecentReports();
            
            // Log de acceso al dashboard
            $this->logActivity('DASHBOARD_ACCESS', 'Usuario accedió al dashboard');
            
            // Renderizar vista
            $this->render('pages/dashboard', [
                'stats' => $stats,
                'recentActivity' => $recentActivity,
                'pendingVouchers' => $pendingVouchers,
                'recentReports' => $recentReports,
                'pageTitle' => 'Dashboard Principal'
            ]);
            
        } catch (Exception $e) {
            $this->handleError('DASHBOARD_ERROR', 'Error cargando dashboard: ' . $e->getMessage());
        }
    }
    
    /**
     * API: Obtener estadísticas para AJAX
     */
    public function getStats()
    {
        // Verificar autenticación
        $this->requireAuth();
        
        try {
            $stats = $this->getSystemStats();
            $this->sendSuccessResponse($stats, 'Estadísticas obtenidas correctamente');
            
        } catch (Exception $e) {
            $this->sendErrorResponse('Error obteniendo estadísticas: ' . $e->getMessage());
        }
    }
    
    /**
     * API: Obtener actividad reciente para AJAX
     */
    public function getRecentActivityApi()
    {
        // Verificar autenticación
        $this->requireAuth();
        
        try {
            $activity = $this->getRecentActivity();
            $this->sendSuccessResponse($activity, 'Actividad reciente obtenida');
            
        } catch (Exception $e) {
            $this->sendErrorResponse('Error obteniendo actividad: ' . $e->getMessage());
        }
    }
    
    /**
     * API: Obtener estado del sistema
     */
    public function getSystemHealth()
    {
        // Verificar autenticación (solo admin)
        $this->requirePermission('system_settings');
        
        try {
            $health = $this->checkSystemHealth();
            $this->sendSuccessResponse($health, 'Estado del sistema obtenido');
            
        } catch (Exception $e) {
            $this->sendErrorResponse('Error verificando sistema: ' . $e->getMessage());
        }
    }
    
    // ========================================
    // MÉTODOS PRIVADOS PARA DATOS
    // ========================================
    
    /**
     * Obtener estadísticas generales del sistema
     */
    private function getSystemStats()
    {
        $stats = [];
        
        try {
            // Estadísticas básicas
            $stats['totalCompanies'] = $this->db->fetch(
                "SELECT COUNT(*) as total FROM companies WHERE is_active = 1"
            )['total'] ?? 0;
            
            $stats['totalVouchers'] = $this->db->fetch(
                "SELECT COUNT(*) as total FROM vouchers"
            )['total'] ?? 0;
            
            $stats['totalTrips'] = $this->db->fetch(
                "SELECT COUNT(*) as total FROM trips"
            )['total'] ?? 0;
            
            $stats['pendingVouchers'] = $this->db->fetch(
                "SELECT COUNT(*) as total FROM vouchers WHERE status = 'uploaded'"
            )['total'] ?? 0;
            
            // Estadísticas financieras
            $financialData = $this->db->fetch(
                "SELECT 
                    SUM(amount) as totalAmount,
                    COUNT(DISTINCT voucher_id) as processedVouchers
                 FROM trips"
            );
            
            $stats['totalAmount'] = $financialData['totalAmount'] ?? 0;
            $stats['processedVouchers'] = $financialData['processedVouchers'] ?? 0;
            
            // Estadísticas del mes actual
            $monthlyData = $this->db->fetch(
                "SELECT 
                    SUM(amount) as monthlyAmount,
                    COUNT(*) as monthlyTrips
                 FROM trips 
                 WHERE MONTH(trip_date) = MONTH(CURRENT_DATE()) 
                   AND YEAR(trip_date) = YEAR(CURRENT_DATE())"
            );
            
            $stats['monthlyAmount'] = $monthlyData['monthlyAmount'] ?? 0;
            $stats['monthlyTrips'] = $monthlyData['monthlyTrips'] ?? 0;
            
            // Estadísticas por estado de vouchers
            $statusStats = $this->db->fetchAll(
                "SELECT status, COUNT(*) as count FROM vouchers GROUP BY status"
            );
            
            $stats['vouchersByStatus'] = [];
            foreach ($statusStats as $status) {
                $stats['vouchersByStatus'][$status['status']] = $status['count'];
            }
            
            // Top 5 empresas por volumen
            $topCompanies = $this->db->fetchAll(
                "SELECT c.name, c.identifier, COUNT(t.id) as trip_count, SUM(t.amount) as total_amount
                 FROM companies c
                 LEFT JOIN trips t ON c.id = t.company_id
                 WHERE c.is_active = 1
                 GROUP BY c.id, c.name, c.identifier
                 ORDER BY total_amount DESC
                 LIMIT 5"
            );
            
            $stats['topCompanies'] = $topCompanies;
            
            // Formatear montos para display
            $stats['totalAmountFormatted'] = $this->formatCurrency($stats['totalAmount']);
            $stats['monthlyAmountFormatted'] = $this->formatCurrency($stats['monthlyAmount']);
            
            return $stats;
            
        } catch (Exception $e) {
            $this->logActivity('ERROR', 'Error obteniendo estadísticas: ' . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Obtener actividad reciente
     */
    private function getRecentActivity()
    {
        try {
            // Actividad de usuarios
            $userActivity = $this->db->fetchAll(
                "SELECT 
                    al.action, 
                    al.description, 
                    al.created_at,
                    u.full_name as user_name,
                    u.username
                 FROM activity_logs al
                 LEFT JOIN users u ON al.user_id = u.id
                 ORDER BY al.created_at DESC
                 LIMIT 10"
            );
            
            // Vouchers recientes
            $recentVouchers = $this->db->fetchAll(
                "SELECT 
                    v.id,
                    v.original_filename,
                    v.status,
                    v.upload_date,
                    u.full_name as uploaded_by_name
                 FROM vouchers v
                 LEFT JOIN users u ON v.uploaded_by = u.id
                 ORDER BY v.upload_date DESC
                 LIMIT 5"
            );
            
            // Reportes generados recientemente
            $recentReports = $this->db->fetchAll(
                "SELECT 
                    r.id,
                    r.report_type,
                    r.status,
                    r.generated_at,
                    c.name as company_name,
                    u.full_name as generated_by_name
                 FROM reports r
                 LEFT JOIN companies c ON r.company_id = c.id
                 LEFT JOIN users u ON r.generated_by = u.id
                 ORDER BY r.generated_at DESC
                 LIMIT 5"
            );
            
            return [
                'userActivity' => $userActivity,
                'recentVouchers' => $recentVouchers,
                'recentReports' => $recentReports
            ];
            
        } catch (Exception $e) {
            $this->logActivity('ERROR', 'Error obteniendo actividad reciente: ' . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Obtener vouchers pendientes
     */
    private function getPendingVouchers()
    {
        try {
            return $this->db->fetchAll(
                "SELECT 
                    v.id,
                    v.original_filename,
                    v.file_format,
                    v.file_size,
                    v.upload_date,
                    u.full_name as uploaded_by_name
                 FROM vouchers v
                 LEFT JOIN users u ON v.uploaded_by = u.id
                 WHERE v.status = 'uploaded'
                 ORDER BY v.upload_date ASC
                 LIMIT 10"
            );
            
        } catch (Exception $e) {
            $this->logActivity('ERROR', 'Error obteniendo vouchers pendientes: ' . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Obtener reportes recientes
     */
    private function getRecentReports()
    {
        try {
            return $this->db->fetchAll(
                "SELECT 
                    r.id,
                    r.report_type,
                    r.file_path,
                    r.file_size,
                    r.generated_at,
                    c.name as company_name,
                    c.identifier as company_identifier,
                    u.full_name as generated_by_name
                 FROM reports r
                 LEFT JOIN companies c ON r.company_id = c.id
                 LEFT JOIN users u ON r.generated_by = u.id
                 WHERE r.status = 'completed'
                 ORDER BY r.generated_at DESC
                 LIMIT 5"
            );
            
        } catch (Exception $e) {
            $this->logActivity('ERROR', 'Error obteniendo reportes recientes: ' . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Verificar estado del sistema
     */
    private function checkSystemHealth()
    {
        $health = [
            'overall' => 'healthy',
            'checks' => []
        ];
        
        try {
            // Verificar conexión a BD
            $health['checks']['database'] = [
                'status' => 'healthy',
                'message' => 'Base de datos conectada',
                'details' => $this->db->fetch("SELECT VERSION() as version")['version'] ?? 'Unknown'
            ];
            
            // Verificar directorios escribibles
            $writableDirs = [
                'uploads' => defined('UPLOAD_PATH') ? UPLOAD_PATH : 'public/uploads',
                'logs' => defined('LOG_PATH') ? LOG_PATH : 'storage/logs',
                'cache' => defined('CACHE_PATH') ? CACHE_PATH : 'storage/cache'
            ];
            
            foreach ($writableDirs as $name => $path) {
                $fullPath = defined('ROOT_PATH') ? ROOT_PATH . '/' . $path : $path;
                $health['checks'][$name . '_directory'] = [
                    'status' => is_writable($fullPath) ? 'healthy' : 'warning',
                    'message' => is_writable($fullPath) ? 'Directorio escribible' : 'Directorio no escribible',
                    'path' => $fullPath
                ];
                
                if (!is_writable($fullPath)) {
                    $health['overall'] = 'warning';
                }
            }
            
            // Verificar espacio en disco
            $freeSpace = disk_free_space('.');
            $totalSpace = disk_total_space('.');
            $usedPercentage = round((($totalSpace - $freeSpace) / $totalSpace) * 100, 2);
            
            $health['checks']['disk_space'] = [
                'status' => $usedPercentage > 90 ? 'critical' : ($usedPercentage > 80 ? 'warning' : 'healthy'),
                'message' => "Uso de disco: {$usedPercentage}%",
                'free_space' => $this->formatBytes($freeSpace),
                'total_space' => $this->formatBytes($totalSpace)
            ];
            
            if ($usedPercentage > 90) {
                $health['overall'] = 'critical';
            } elseif ($usedPercentage > 80 && $health['overall'] === 'healthy') {
                $health['overall'] = 'warning';
            }
            
            // Verificar versión PHP
            $phpVersion = PHP_VERSION;
            $health['checks']['php_version'] = [
                'status' => version_compare($phpVersion, '7.4.0', '>=') ? 'healthy' : 'warning',
                'message' => "PHP {$phpVersion}",
                'recommended' => 'PHP 7.4+'
            ];
            
            return $health;
            
        } catch (Exception $e) {
            $health['overall'] = 'critical';
            $health['checks']['system_error'] = [
                'status' => 'critical',
                'message' => 'Error verificando sistema: ' . $e->getMessage()
            ];
            
            return $health;
        }
    }
    
    // ========================================
    // MÉTODOS DE UTILIDAD
    // ========================================
    
    /**
     * Formatear moneda
     */
    private function formatCurrency($amount)
    {
        return '$' . number_format($amount, 2, '.', ',');
    }
    
    /**
     * Formatear fecha relativa
     */
    private function timeAgo($datetime)
    {
        $time = time() - strtotime($datetime);
        
        if ($time < 60) return 'hace ' . $time . ' segundos';
        if ($time < 3600) return 'hace ' . round($time/60) . ' minutos';
        if ($time < 86400) return 'hace ' . round($time/3600) . ' horas';
        if ($time < 2592000) return 'hace ' . round($time/86400) . ' días';
        
        return date('d/m/Y', strtotime($datetime));
    }
    
    /**
     * Obtener color de estado
     */
    private function getStatusColor($status)
    {
        $colors = [
            'uploaded' => 'warning',
            'processing' => 'info', 
            'processed' => 'success',
            'error' => 'danger',
            'completed' => 'success',
            'failed' => 'danger'
        ];
        
        return $colors[$status] ?? 'secondary';
    }
}
?>