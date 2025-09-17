<?php
// ========================================
// app/Controllers/ManagementController.php
// Controlador para gestión y administración
// ========================================

namespace App\Controllers;

use Database;
use Logger;
use Exception;
use CapitalTransportReportGenerator;

/**
 * ManagementController - Controlador para gestión y administración
 * 
 * Funcionalidades:
 * - Gestión de empresas
 * - Gestión de vouchers
 * - Gestión de reportes
 * - Gestión de usuarios (admin)
 * - Configuración del sistema
 */
class ManagementController extends BaseController
{
    /**
     * Página principal de gestión
     */
    public function index()
    {
        // Verificar autenticación
        $this->requireAuth();
        
        try {
            // Obtener resumen de gestión
            $managementSummary = $this->getManagementSummary();
            
            // Log de acceso
            $this->logActivity('MANAGEMENT_ACCESS', 'Usuario accedió a gestión');
            
            // Renderizar vista
            $this->render('pages/management', [
                'summary' => $managementSummary,
                'pageTitle' => 'Gestión del Sistema',
                'isAdmin' => $this->isAdmin()
            ]);
            
        } catch (Exception $e) {
            $this->handleError('MANAGEMENT_PAGE_ERROR', 'Error cargando página de gestión: ' . $e->getMessage());
        }
    }
    
    /**
     * Gestión de empresas
     */
    public function companies()
    {
        // Verificar autenticación y permisos
        $this->requireAuth();
        $this->requirePermission('view_companies');
        
        try {
            // Obtener empresas con estadísticas
            $companies = $this->getCompaniesWithStats();
            
            // Log de acceso
            $this->logActivity('COMPANIES_VIEW', 'Usuario accedió a gestión de empresas');
            
            // Renderizar vista
            $this->render('pages/companies', [
                'companies' => $companies,
                'pageTitle' => 'Gestión de Empresas',
                'canManage' => $this->hasPermission('manage_companies')
            ]);
            
        } catch (Exception $e) {
            $this->handleError('COMPANIES_PAGE_ERROR', 'Error cargando empresas: ' . $e->getMessage());
        }
    }
    
    /**
     * Crear/Editar empresa
     */
    public function createCompany()
    {
        // Verificar autenticación y permisos
        $this->requireAuth();
        $this->requirePermission('manage_companies');
        
        if ($this->request['method'] === 'POST') {
            return $this->processCreateCompany();
        }
        
        // Mostrar formulario
        $this->render('pages/company-form', [
            'company' => null,
            'pageTitle' => 'Nueva Empresa',
            'action' => 'create'
        ]);
    }
    
    /**
     * Editar empresa
     */
    public function editCompany($companyId)
    {
        // Verificar autenticación y permisos
        $this->requireAuth();
        $this->requirePermission('manage_companies');
        
        $company = $this->getCompanyById($companyId);
        if (!$company) {
            $this->sendErrorResponse('Empresa no encontrada', 404);
        }
        
        if ($this->request['method'] === 'POST') {
            return $this->processEditCompany($companyId);
        }
        
        // Mostrar formulario
        $this->render('pages/company-form', [
            'company' => $company,
            'pageTitle' => 'Editar Empresa: ' . $company['name'],
            'action' => 'edit'
        ]);
    }
    
    /**
     * Gestión de vouchers
     */
    public function vouchers()
    {
        // Verificar autenticación
        $this->requireAuth();
        
        try {
            // Obtener vouchers con información completa
            $vouchers = $this->getVouchersWithDetails();
            
            // Log de acceso
            $this->logActivity('VOUCHERS_VIEW', 'Usuario accedió a gestión de vouchers');
            
            // Renderizar vista
            $this->render('pages/vouchers', [
                'vouchers' => $vouchers,
                'pageTitle' => 'Gestión de Vouchers',
                'canProcess' => $this->hasPermission('process_vouchers')
            ]);
            
        } catch (Exception $e) {
            $this->handleError('VOUCHERS_PAGE_ERROR', 'Error cargando vouchers: ' . $e->getMessage());
        }
    }
    
    /**
     * Gestión de reportes
     */
    public function reports()
    {
        // Verificar autenticación y permisos
        $this->requireAuth();
        $this->requirePermission('view_reports');
        
        try {
            // Obtener reportes con detalles
            $reports = $this->getReportsWithDetails();
            
            // Obtener empresas para filtros
            $companies = $this->getActiveCompanies();
            
            // Log de acceso
            $this->logActivity('REPORTS_VIEW', 'Usuario accedió a gestión de reportes');
            
            // Renderizar vista
            $this->render('pages/reports', [
                'reports' => $reports,
                'companies' => $companies,
                'pageTitle' => 'Gestión de Reportes',
                'canGenerate' => $this->hasPermission('generate_reports')
            ]);
            
        } catch (Exception $e) {
            $this->handleError('REPORTS_PAGE_ERROR', 'Error cargando reportes: ' . $e->getMessage());
        }
    }
    
    /**
     * Generar reporte Capital Transport
     */
    public function generateReport()
    {
        // Verificar autenticación y permisos
        $this->requireAuth();
        $this->requirePermission('generate_reports');
        
        // Verificar método POST
        if ($this->request['method'] !== 'POST') {
            $this->sendErrorResponse('Método no permitido', 405);
        }
        
        try {
            // Validar datos requeridos
            $this->validateRequired($this->request['post'], [
                'company_id', 'voucher_id', 'week_start', 'week_end', 'payment_date'
            ]);
            
            $companyId = intval($this->request['post']['company_id']);
            $voucherId = intval($this->request['post']['voucher_id']);
            $weekStart = $this->request['post']['week_start'];
            $weekEnd = $this->request['post']['week_end'];
            $paymentDate = $this->request['post']['payment_date'];
            $ytdAmount = floatval($this->request['post']['ytd_amount'] ?? 0);
            
            // Validar que la empresa y voucher existen
            $company = $this->getCompanyById($companyId);
            $voucher = $this->getVoucherById($voucherId);
            
            if (!$company || !$voucher) {
                $this->sendErrorResponse('Empresa o voucher no encontrado');
            }
            
            if ($voucher['status'] !== 'processed') {
                $this->sendErrorResponse('El voucher debe estar procesado para generar reportes');
            }
            
            // Crear registro de reporte
            $reportData = [
                'company_id' => $companyId,
                'voucher_id' => $voucherId,
                'report_type' => 'capital_transport',
                'status' => 'generating',
                'generated_by' => $this->currentUser['id'],
                'generated_at' => date('Y-m-d H:i:s'),
                'week_start' => $weekStart,
                'week_end' => $weekEnd,
                'payment_date' => $paymentDate,
                'ytd_amount' => $ytdAmount
            ];
            
            $reportId = $this->db->insert('reports', $reportData);
            
            // Generar reporte usando CapitalTransportReportGenerator
            $generator = new CapitalTransportReportGenerator($companyId, $voucherId);
            $result = $generator->generateReport($weekStart, $weekEnd, $paymentDate, $ytdAmount);
            
            // Actualizar registro con resultado
            $this->db->update('reports', [
                'status' => 'completed',
                'file_path' => $result['file_path'],
                'file_size' => $result['file_size'],
                'completed_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$reportId]);
            
            // Log de generación
            $this->logActivity('REPORT_GENERATED', "Reporte generado: {$result['filename']} para empresa {$company['name']}");
            
            // Respuesta exitosa
            $this->sendSuccessResponse([
                'report_id' => $reportId,
                'filename' => $result['filename'],
                'file_size' => $this->formatBytes($result['file_size']),
                'download_url' => "/management/download-report/{$reportId}"
            ], 'Reporte generado correctamente');
            
        } catch (Exception $e) {
            // Actualizar estado de error si existe el reporte
            if (isset($reportId)) {
                $this->db->update('reports', [
                    'status' => 'failed',
                    'error_message' => $e->getMessage()
                ], 'id = ?', [$reportId]);
            }
            
            $this->handleError('REPORT_GENERATION_ERROR', 'Error generando reporte: ' . $e->getMessage());
        }
    }
    
    /**
     * Descargar reporte
     */
    public function downloadReport($reportId)
    {
        // Verificar autenticación y permisos
        $this->requireAuth();
        $this->requirePermission('view_reports');
        
        try {
            $report = $this->getReportById($reportId);
            if (!$report || $report['status'] !== 'completed') {
                $this->sendErrorResponse('Reporte no encontrado o no completado', 404);
            }
            
            if (!file_exists($report['file_path'])) {
                $this->sendErrorResponse('Archivo de reporte no encontrado', 404);
            }
            
            // Log de descarga
            $this->logActivity('REPORT_DOWNLOAD', "Descarga de reporte: {$report['id']}");
            
            // Enviar archivo
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($report['file_path']) . '"');
            header('Content-Length: ' . filesize($report['file_path']));
            readfile($report['file_path']);
            exit();
            
        } catch (Exception $e) {
            $this->handleError('REPORT_DOWNLOAD_ERROR', 'Error descargando reporte: ' . $e->getMessage());
        }
    }
    
    /**
     * Gestión de usuarios (solo admin)
     */
    public function users()
    {
        // Verificar autenticación y permisos de admin
        $this->requireAuth();
        $this->requirePermission('manage_users');
        
        try {
            // Obtener usuarios con estadísticas
            $users = $this->getUsersWithStats();
            
            // Log de acceso
            $this->logActivity('USERS_VIEW', 'Administrador accedió a gestión de usuarios');
            
            // Renderizar vista
            $this->render('pages/users', [
                'users' => $users,
                'pageTitle' => 'Gestión de Usuarios',
                'roles' => ROLES
            ]);
            
        } catch (Exception $e) {
            $this->handleError('USERS_PAGE_ERROR', 'Error cargando usuarios: ' . $e->getMessage());
        }
    }
    
    // ========================================
    // MÉTODOS PRIVADOS DE PROCESAMIENTO
    // ========================================
    
    /**
     * Procesar creación de empresa
     */
    private function processCreateCompany()
    {
        try {
            // Validar datos requeridos
            $this->validateRequired($this->request['post'], [
                'name', 'identifier', 'capital_percentage'
            ]);
            
            $companyData = [
                'name' => $this->sanitizeInput($this->request['post']['name']),
                'identifier' => strtoupper($this->sanitizeInput($this->request['post']['identifier'])),
                'legal_name' => $this->sanitizeInput($this->request['post']['legal_name'] ?? ''),
                'tax_id' => $this->sanitizeInput($this->request['post']['tax_id'] ?? ''),
                'contact_person' => $this->sanitizeInput($this->request['post']['contact_person'] ?? ''),
                'phone' => $this->sanitizeInput($this->request['post']['phone'] ?? ''),
                'email' => $this->sanitizeInput($this->request['post']['email'] ?? ''),
                'address' => $this->sanitizeInput($this->request['post']['address'] ?? ''),
                'city' => $this->sanitizeInput($this->request['post']['city'] ?? ''),
                'capital_percentage' => floatval($this->request['post']['capital_percentage']),
                'created_by' => $this->currentUser['id'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Validar email si se proporciona
            if (!empty($companyData['email'])) {
                $this->validateEmail($companyData['email']);
            }
            
            // Verificar que el identificador sea único
            $existing = $this->db->fetch(
                "SELECT id FROM companies WHERE identifier = ?",
                [$companyData['identifier']]
            );
            
            if ($existing) {
                $this->sendErrorResponse('El identificador de empresa ya existe');
            }
            
            $companyId = $this->db->insert('companies', $companyData);
            
            // Log de creación
            $this->logActivity('COMPANY_CREATED', "Empresa creada: {$companyData['name']} ({$companyData['identifier']})");
            
            $this->sendSuccessResponse([
                'company_id' => $companyId,
                'redirect' => '/management/companies'
            ], 'Empresa creada correctamente');
            
        } catch (Exception $e) {
            $this->sendErrorResponse('Error creando empresa: ' . $e->getMessage());
        }
    }
    
    /**
     * Procesar edición de empresa
     */
    private function processEditCompany($companyId)
    {
        try {
            // Validar datos requeridos
            $this->validateRequired($this->request['post'], [
                'name', 'capital_percentage'
            ]);
            
            $updateData = [
                'name' => $this->sanitizeInput($this->request['post']['name']),
                'legal_name' => $this->sanitizeInput($this->request['post']['legal_name'] ?? ''),
                'tax_id' => $this->sanitizeInput($this->request['post']['tax_id'] ?? ''),
                'contact_person' => $this->sanitizeInput($this->request['post']['contact_person'] ?? ''),
                'phone' => $this->sanitizeInput($this->request['post']['phone'] ?? ''),
                'email' => $this->sanitizeInput($this->request['post']['email'] ?? ''),
                'address' => $this->sanitizeInput($this->request['post']['address'] ?? ''),
                'city' => $this->sanitizeInput($this->request['post']['city'] ?? ''),
                'capital_percentage' => floatval($this->request['post']['capital_percentage']),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Validar email si se proporciona
            if (!empty($updateData['email'])) {
                $this->validateEmail($updateData['email']);
            }
            
            $this->db->update('companies', $updateData, 'id = ?', [$companyId]);
            
            // Log de actualización
            $this->logActivity('COMPANY_UPDATED', "Empresa actualizada: {$updateData['name']} (ID: {$companyId})");
            
            $this->sendSuccessResponse([
                'company_id' => $companyId,
                'redirect' => '/management/companies'
            ], 'Empresa actualizada correctamente');
            
        } catch (Exception $e) {
            $this->sendErrorResponse('Error actualizando empresa: ' . $e->getMessage());
        }
    }
    
    // ========================================
    // MÉTODOS PRIVADOS DE DATOS
    // ========================================
    
    /**
     * Obtener resumen de gestión
     */
    private function getManagementSummary()
    {
        return [
            'total_companies' => $this->db->fetch("SELECT COUNT(*) as count FROM companies WHERE is_active = 1")['count'] ?? 0,
            'total_vouchers' => $this->db->fetch("SELECT COUNT(*) as count FROM vouchers")['count'] ?? 0,
            'total_reports' => $this->db->fetch("SELECT COUNT(*) as count FROM reports")['count'] ?? 0,
            'pending_vouchers' => $this->db->fetch("SELECT COUNT(*) as count FROM vouchers WHERE status = 'uploaded'")['count'] ?? 0,
            'failed_reports' => $this->db->fetch("SELECT COUNT(*) as count FROM reports WHERE status = 'failed'")['count'] ?? 0
        ];
    }
    
    /**
     * Obtener empresas con estadísticas
     */
    private function getCompaniesWithStats()
    {
        return $this->db->fetchAll(
            "SELECT 
                c.*,
                COUNT(DISTINCT t.voucher_id) as vouchers_processed,
                COUNT(t.id) as total_trips,
                SUM(t.amount) as total_amount,
                MAX(t.trip_date) as last_trip_date
             FROM companies c
             LEFT JOIN trips t ON c.id = t.company_id
             WHERE c.is_active = 1
             GROUP BY c.id
             ORDER BY c.name ASC"
        );
    }
    
    /**
     * Obtener vouchers con detalles
     */
    private function getVouchersWithDetails()
    {
        return $this->db->fetchAll(
            "SELECT 
                v.*,
                u.full_name as uploaded_by_name,
                COUNT(t.id) as trips_count,
                SUM(t.amount) as total_amount,
                COUNT(DISTINCT t.company_id) as companies_count
             FROM vouchers v
             LEFT JOIN users u ON v.uploaded_by = u.id
             LEFT JOIN trips t ON v.id = t.voucher_id
             GROUP BY v.id
             ORDER BY v.upload_date DESC"
        );
    }
    
    /**
     * Obtener reportes con detalles
     */
    private function getReportsWithDetails()
    {
        return $this->db->fetchAll(
            "SELECT 
                r.*,
                c.name as company_name,
                c.identifier as company_identifier,
                u.full_name as generated_by_name,
                v.original_filename as voucher_filename
             FROM reports r
             LEFT JOIN companies c ON r.company_id = c.id
             LEFT JOIN users u ON r.generated_by = u.id
             LEFT JOIN vouchers v ON r.voucher_id = v.id
             ORDER BY r.generated_at DESC"
        );
    }
    
    /**
     * Obtener usuarios con estadísticas
     */
    private function getUsersWithStats()
    {
        return $this->db->fetchAll(
            "SELECT 
                u.*,
                COUNT(DISTINCT v.id) as vouchers_uploaded,
                COUNT(DISTINCT r.id) as reports_generated,
                MAX(al.created_at) as last_activity
             FROM users u
             LEFT JOIN vouchers v ON u.id = v.uploaded_by
             LEFT JOIN reports r ON u.id = r.generated_by
             LEFT JOIN activity_logs al ON u.id = al.user_id
             GROUP BY u.id
             ORDER BY u.created_at DESC"
        );
    }
    
    /**
     * Obtener empresa por ID
     */
    private function getCompanyById($companyId)
    {
        return $this->db->fetch("SELECT * FROM companies WHERE id = ?", [$companyId]);
    }
    
    /**
     * Obtener voucher por ID
     */
    private function getVoucherById($voucherId)
    {
        return $this->db->fetch("SELECT * FROM vouchers WHERE id = ?", [$voucherId]);
    }
    
    /**
     * Obtener reporte por ID
     */
    private function getReportById($reportId)
    {
        return $this->db->fetch("SELECT * FROM reports WHERE id = ?", [$reportId]);
    }
    
    /**
     * Obtener empresas activas
     */
    private function getActiveCompanies()
    {
        return $this->db->fetchAll(
            "SELECT id, name, identifier, capital_percentage 
             FROM companies 
             WHERE is_active = 1 
             ORDER BY name ASC"
        );
    }
}
?>