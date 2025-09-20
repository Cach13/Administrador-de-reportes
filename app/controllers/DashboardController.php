<?php
// ========================================
// app/Controllers/DashboardController.php - PASO 15 ARREGLADO
// Controlador del dashboard principal - SIN ERRORES DE SINTAXIS
// ========================================

namespace App\Controllers;

use Database;
use Logger;
use Exception;

/**
 * DashboardController - Dashboard principal con estadísticas en tiempo real
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
            // Obtener todos los datos del dashboard
            $stats = $this->getSystemStats();
            $recentActivity = $this->getRecentActivity();
            $pendingVouchers = $this->getPendingVouchers();
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
        $this->requireAuth();
        
        try {
            $activity = $this->getRecentActivity();
            $this->sendSuccessResponse($activity, 'Actividad reciente obtenida');
            
        } catch (Exception $e) {
            $this->sendErrorResponse('Error obteniendo actividad: ' . $e->getMessage());
        }
    }
    
    /**
     * API: Obtener datos completos del dashboard para refresh
     */
    public function getDashboardData()
    {
        $this->requireAuth();
        
        try {
            $dashboardData = [
                'stats' => $this->getSystemStats(),
                'recentActivity' => $this->getRecentActivity(),
                'pendingVouchers' => $this->getPendingVouchers(),
                'systemHealth' => $this->getSystemHealth()
            ];
            
            $this->sendSuccessResponse($dashboardData, 'Datos del dashboard obtenidos');
            
        } catch (Exception $e) {
            $this->sendErrorResponse('Error obteniendo datos del dashboard: ' . $e->getMessage());
        }
    }
    
    /**
     * API: Obtener estado del sistema
     */
    public function getSystemHealth()
    {
        $this->requireAuth();
        
        try {
            $health = $this->checkSystemHealth();
            $this->sendSuccessResponse($health, 'Estado del sistema obtenido');
            
        } catch (Exception $e) {
            $this->sendErrorResponse('Error verificando sistema: ' . $e->getMessage());
        }
    }
    
    // ========================================
    // MÉTODOS PRIVADOS PARA OBTENER DATOS
    // ========================================
    
    /**
     * Obtener estadísticas generales del sistema
     */
    private function getSystemStats()
    {
        try {
            $stats = [];
            
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
                    COALESCE(SUM(amount), 0) as totalAmount,
                    COUNT(DISTINCT voucher_id) as processedVouchers
                 FROM trips"
            );
            
            $stats['totalAmount'] = $financialData['totalAmount'] ?? 0;
            $stats['processedVouchers'] = $financialData['processedVouchers'] ?? 0;
            
            // Estadísticas del mes actual
            $monthlyData = $this->db->fetch(
                "SELECT 
                    COALESCE(SUM(amount), 0) as monthlyAmount,
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
                "SELECT 
                    c.name, 
                    c.identifier, 
                    COUNT(t.id) as trip_count, 
                    COALESCE(SUM(t.amount), 0) as total_amount
                 FROM companies c
                 LEFT JOIN trips t ON c.id = t.company_id
                 WHERE c.is_active = 1
                 GROUP BY c.id, c.name, c.identifier
                 ORDER BY total_amount DESC
                 LIMIT 5"
            );
            
            $stats['topCompanies'] = $topCompanies;
            
            // Datos para gráficos mensuales (últimos 12 meses)
            $monthlyTrends = $this->db->fetchAll(
                "SELECT 
                    DATE_FORMAT(trip_date, '%Y-%m') as month,
                    COUNT(*) as trip_count,
                    COALESCE(SUM(amount), 0) as total_amount
                 FROM trips 
                 WHERE trip_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)
                 GROUP BY DATE_FORMAT(trip_date, '%Y-%m')
                 ORDER BY month ASC"
            );
            
            $stats['monthlyTrends'] = $monthlyTrends;
            
            // Calcular cambios porcentuales vs mes anterior
            $lastMonthData = $this->db->fetch(
                "SELECT 
                    COALESCE(SUM(amount), 0) as lastMonthAmount,
                    COUNT(*) as lastMonthTrips
                 FROM trips 
                 WHERE MONTH(trip_date) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) 
                   AND YEAR(trip_date) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))"
            );
            
            $lastMonthAmount = $lastMonthData['lastMonthAmount'] ?? 0;
            $currentMonthAmount = $stats['monthlyAmount'];
            
            if ($lastMonthAmount > 0) {
                $stats['monthlyAmountChange'] = round((($currentMonthAmount - $lastMonthAmount) / $lastMonthAmount) * 100, 1);
            } else {
                $stats['monthlyAmountChange'] = $currentMonthAmount > 0 ? 100 : 0;
            }
            
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
     * Obtener actividad reciente del sistema
     */
    private function getRecentActivity($limit = 10)
    {
        try {
            $activities = $this->db->fetchAll(
                "SELECT 
                    action,
                    details,
                    level,
                    created_at,
                    CASE 
                        WHEN action = 'LOGIN' THEN '🔐 Inicio de sesión'
                        WHEN action = 'VOUCHER_UPLOAD' THEN '📄 Voucher cargado'
                        WHEN action = 'VOUCHER_PROCESS' THEN '⚙️ Voucher procesado'
                        WHEN action = 'REPORT_GENERATE' THEN '📊 Reporte generado'
                        WHEN action = 'COMPANY_CREATE' THEN '🏢 Empresa creada'
                        WHEN action = 'COMPANY_UPDATE' THEN '✏️ Empresa actualizada'
                        WHEN action = 'ERROR' THEN '❌ Error del sistema'
                        ELSE CONCAT('📝 ', action)
                    END as description
                 FROM activity_logs 
                 WHERE level != 'DEBUG'
                 ORDER BY created_at DESC 
                 LIMIT ?",
                [$limit]
            );
            
            return $activities;
            
        } catch (Exception $e) {
            $this->logActivity('ERROR', 'Error obteniendo actividad reciente: ' . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * Obtener vouchers pendientes de procesar
     */
    private function getPendingVouchers($limit = 10)
    {
        try {
            $pendingVouchers = $this->db->fetchAll(
                "SELECT 
                    id,
                    filename,
                    file_path,
                    status,
                    file_size,
                    created_at,
                    CASE 
                        WHEN status = 'uploaded' THEN 'Cargado'
                        WHEN status = 'processing' THEN 'Procesando'
                        WHEN status = 'processed' THEN 'Procesado'
                        WHEN status = 'error' THEN 'Error'
                        ELSE 'Desconocido'
                    END as status_text
                 FROM vouchers 
                 WHERE status IN ('uploaded', 'processing', 'error')
                 ORDER BY created_at DESC 
                 LIMIT ?",
                [$limit]
            );
            
            return $pendingVouchers;
            
        } catch (Exception $e) {
            $this->logActivity('ERROR', 'Error obteniendo vouchers pendientes: ' . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * Obtener reportes recientes
     */
    private function getRecentReports($limit = 5)
    {
        try {
            $recentReports = $this->db->fetchAll(
                "SELECT 
                    r.id,
                    r.report_name,
                    r.file_path,
                    r.status,
                    r.created_at,
                    c.name as company_name,
                    c.identifier as company_identifier
                 FROM reports r
                 LEFT JOIN companies c ON r.company_id = c.id
                 WHERE r.status = 'completed'
                 ORDER BY r.created_at DESC 
                 LIMIT ?",
                [$limit]
            );
            
            return $recentReports;
            
        } catch (Exception $e) {
            $this->logActivity('ERROR', 'Error obteniendo reportes recientes: ' . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * Verificar estado del sistema
     */
    private function checkSystemHealth()
    {
        try {
            $health = [
                'status' => 'healthy',
                'checks' => [],
                'warnings' => [],
                'errors' => []
            ];
            
            // Check 1: Conexión a base de datos
            try {
                $this->db->fetch("SELECT 1");
                $health['checks'][] = '✅ Conexión a base de datos: OK';
            } catch (Exception $e) {
                $health['errors'][] = '❌ Conexión a base de datos: ERROR - ' . $e->getMessage();
                $health['status'] = 'error';
            }
            
            // Check 2: Directorio de uploads
            $uploadPath = defined('UPLOAD_PATH') ? UPLOAD_PATH : ROOT_PATH . '/uploads';
            if (is_dir($uploadPath) && is_writable($uploadPath)) {
                $health['checks'][] = '✅ Directorio de uploads: OK';
            } else {
                $health['warnings'][] = '⚠️ Directorio de uploads: No escribible o no existe';
                $health['status'] = $health['status'] === 'error' ? 'error' : 'warning';
            }
            
            // Check 3: Directorio de reportes
            $reportsPath = defined('REPORTS_PATH') ? REPORTS_PATH : ROOT_PATH . '/reports';
            if (is_dir($reportsPath) && is_writable($reportsPath)) {
                $health['checks'][] = '✅ Directorio de reportes: OK';
            } else {
                $health['warnings'][] = '⚠️ Directorio de reportes: No escribible o no existe';
                $health['status'] = $health['status'] === 'error' ? 'error' : 'warning';
            }
            
            // Check 4: Espacio en disco
            $freeSpace = disk_free_space(ROOT_PATH);
            $totalSpace = disk_total_space(ROOT_PATH);
            
            if ($freeSpace && $totalSpace) {
                $usedPercentage = (($totalSpace - $freeSpace) / $totalSpace) * 100;
                
                if ($usedPercentage < 80) {
                    $health['checks'][] = '✅ Espacio en disco: ' . round(100 - $usedPercentage, 1) . '% disponible';
                } elseif ($usedPercentage < 90) {
                    $health['warnings'][] = '⚠️ Espacio en disco: Solo ' . round(100 - $usedPercentage, 1) . '% disponible';
                    $health['status'] = $health['status'] === 'error' ? 'error' : 'warning';
                } else {
                    $health['errors'][] = '❌ Espacio en disco: Crítico - Solo ' . round(100 - $usedPercentage, 1) . '% disponible';
                    $health['status'] = 'error';
                }
            }
            
            // Check 5: Memoria PHP
            $memoryLimit = ini_get('memory_limit');
            $memoryUsage = memory_get_usage(true);
            
            $health['checks'][] = '✅ Memoria PHP: ' . $this->formatBytes($memoryUsage) . ' / ' . $memoryLimit;
            
            // Check 6: Errores recientes
            $recentErrors = $this->db->fetch(
                "SELECT COUNT(*) as error_count 
                 FROM activity_logs 
                 WHERE level = 'ERROR' 
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
            );
            
            $errorCount = $recentErrors['error_count'] ?? 0;
            if ($errorCount == 0) {
                $health['checks'][] = '✅ Sin errores en la última hora';
            } elseif ($errorCount < 5) {
                $health['warnings'][] = "⚠️ $errorCount errores en la última hora";
                $health['status'] = $health['status'] === 'error' ? 'error' : 'warning';
            } else {
                $health['errors'][] = "❌ $errorCount errores en la última hora";
                $health['status'] = 'error';
            }
            
            return $health;
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'checks' => [],
                'warnings' => [],
                'errors' => ['❌ Error verificando estado del sistema: ' . $e->getMessage()]
            ];
        }
    }
    
    // ========================================
    // MÉTODOS AUXILIARES
    // ========================================
    
    /**
     * Formatear moneda
     */
    private function formatCurrency($amount)
    {
        return '$' . number_format($amount, 2, '.', ',');
    }
    
    // Método formatBytes() heredado del BaseController - no necesita redefinirse
}
?>