<?php
// ========================================
// app/Controllers/BaseController.php
// Controlador base con funcionalidad común
// ========================================

namespace App\Controllers;

// Importar clases del namespace global para que el IDE las reconozca
use Database;
use Logger;
use Exception;

/**
 * BaseController - Controlador base con funcionalidad común
 * 
 * Todas las funciones compartidas entre controladores:
 * - Manejo de respuestas JSON
 * - Validaciones básicas
 * - Autenticación
 * - Logging
 * - Redirecciones
 */
class BaseController
{
    /** @var Database */
    protected $db;
    
    /** @var Logger */
    protected $logger;
    
    /** @var array|null */
    protected $currentUser;
    
    /** @var array */
    protected $request;
    
    /** @var array */
    protected $response;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        // Inicializar sesión si no está iniciada
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Cargar dependencias
        $this->initializeDependencies();
        
        // Cargar usuario actual
        $this->loadCurrentUser();
        
        // Procesar request
        $this->processRequest();
    }
    
    /**
     * Inicializar dependencias
     */
    protected function initializeDependencies()
    {
        try {
            // Database - singleton pattern (sin backslash)
            $this->db = Database::getInstance();
            
            // Logger - nueva instancia (sin backslash)
            $this->logger = new Logger();
            
        } catch (Exception $e) {
            $this->handleError('DEPENDENCY_ERROR', 'Error inicializando dependencias: ' . $e->getMessage());
        }
    }
    
    /**
     * Cargar información del usuario actual
     */
    protected function loadCurrentUser()
    {
        if (isset($_SESSION['user_id'])) {
            try {
                $this->currentUser = $this->db->fetch(
                    "SELECT id, username, email, full_name, role, last_login FROM users WHERE id = ? AND is_active = 1",
                    [$_SESSION['user_id']]
                );
            } catch (Exception $e) {
                $this->logger->log($_SESSION['user_id'] ?? null, 'AUTH_ERROR', 'Error cargando usuario: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Procesar request actual
     */
    protected function processRequest()
    {
        $this->request = [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'path' => parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),
            'query' => $_GET,
            'post' => $_POST,
            'files' => $_FILES,
            'headers' => function_exists('getallheaders') ? getallheaders() : [],
            'ip' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => time()
        ];
    }
    
    // ========================================
    // MÉTODOS DE AUTENTICACIÓN
    // ========================================
    
    /**
     * Verificar si el usuario está autenticado
     */
    protected function requireAuth()
    {
        if (!$this->isAuthenticated()) {
            $this->redirectToLogin();
        }
    }
    
    /**
     * Verificar autenticación
     */
    protected function isAuthenticated()
    {
        return isset($_SESSION['user_id']) && $this->currentUser !== null;
    }
    
    /**
     * Verificar permisos
     */
    protected function requirePermission($permission)
    {
        if (!$this->hasPermission($permission)) {
            $this->sendErrorResponse('Acceso denegado: No tienes permisos para esta acción', 403);
        }
    }
    
    /**
     * Verificar si tiene permiso
     */
    protected function hasPermission($permission)
    {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        $userRole = $this->currentUser['role'];
        $permissions = defined('PERMISSIONS') ? PERMISSIONS[$userRole] ?? [] : [];
        
        return in_array($permission, $permissions);
    }
    
    /**
     * Verificar si es admin
     */
    protected function isAdmin()
    {
        return $this->isAuthenticated() && $this->currentUser['role'] === 'admin';
    }
    
    // ========================================
    // MÉTODOS DE RESPUESTA
    // ========================================
    
    /**
     * Enviar respuesta JSON
     */
    protected function sendJsonResponse($data, $statusCode = 200, $message = null)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $response = [
            'success' => $statusCode < 400,
            'status_code' => $statusCode,
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => $data
        ];
        
        if ($message) {
            $response['message'] = $message;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit();
    }
    
    /**
     * Enviar respuesta de éxito
     */
    protected function sendSuccessResponse($data = null, $message = 'Operación exitosa')
    {
        $this->sendJsonResponse($data, 200, $message);
    }
    
    /**
     * Enviar respuesta de error
     */
    protected function sendErrorResponse($message, $statusCode = 400, $details = null)
    {
        $data = ['error' => $message];
        if ($details) {
            $data['details'] = $details;
        }
        
        $this->sendJsonResponse($data, $statusCode, $message);
    }
    
    /**
     * Redirigir con mensaje
     */
    protected function redirect($url, $message = null, $type = 'info')
    {
        if ($message) {
            $_SESSION['flash_message'] = $message;
            $_SESSION['flash_type'] = $type;
        }
        
        header('Location: ' . $url);
        exit();
    }
    
    /**
     * Redirigir al login
     */
    protected function redirectToLogin($returnUrl = null)
    {
        $loginUrl = '/login.php';
        if ($returnUrl) {
            $loginUrl .= '?redirect=' . urlencode($returnUrl);
        }
        
        $this->redirect($loginUrl);
    }
    
    // ========================================
    // MÉTODOS DE VALIDACIÓN
    // ========================================
    
    /**
     * Validar campos requeridos
     */
    protected function validateRequired($data, $requiredFields)
    {
        $missing = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            $this->sendErrorResponse(
                'Campos requeridos faltantes: ' . implode(', ', $missing),
                400,
                ['missing_fields' => $missing]
            );
        }
        
        return true;
    }
    
    /**
     * Validar archivo subido
     */
    protected function validateFile($file, $allowedTypes = null, $maxSize = null)
    {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            $this->sendErrorResponse('Error en la subida del archivo');
        }
        
        // Validar tipo
        if ($allowedTypes && !in_array(pathinfo($file['name'], PATHINFO_EXTENSION), $allowedTypes)) {
            $this->sendErrorResponse('Tipo de archivo no permitido. Permitidos: ' . implode(', ', $allowedTypes));
        }
        
        // Validar tamaño
        $maxSizeBytes = $maxSize ?? (defined('MAX_UPLOAD_SIZE') ? MAX_UPLOAD_SIZE : 20971520);
        if ($file['size'] > $maxSizeBytes) {
            $this->sendErrorResponse('Archivo demasiado grande. Máximo: ' . $this->formatBytes($maxSizeBytes));
        }
        
        return true;
    }
    
    /**
     * Validar email
     */
    protected function validateEmail($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->sendErrorResponse('Email inválido');
        }
        
        return true;
    }
    
    // ========================================
    // MÉTODOS DE UTILIDAD
    // ========================================
    
    /**
     * Obtener IP del cliente
     */
    protected function getClientIP()
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                return trim($ips[0]);
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Formatear bytes a tamaño legible
     */
    protected function formatBytes($size, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Generar token CSRF
     */
    protected function generateCSRFToken()
    {
        $expiry = defined('CSRF_TOKEN_EXPIRY') ? CSRF_TOKEN_EXPIRY : 3600;
        
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time']) || 
            (time() - $_SESSION['csrf_token_time']) > $expiry) {
            
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verificar token CSRF
     */
    protected function validateCSRFToken($token)
    {
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            $this->sendErrorResponse('Token CSRF inválido', 403);
        }
        
        return true;
    }
    
    /**
     * Sanitizar input
     */
    protected function sanitizeInput($input)
    {
        if (is_array($input)) {
            return array_map([$this, 'sanitizeInput'], $input);
        }
        
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Escapar para SQL (aunque usemos prepared statements)
     */
    protected function escapeSql($input)
    {
        return $this->db->getConnection()->quote($input);
    }
    
    // ========================================
    // MÉTODOS DE LOGGING Y ERRORES
    // ========================================
    
    /**
     * Log de actividad
     */
    protected function logActivity($action, $details = null, $level = 'INFO')
    {
        $userId = $this->currentUser['id'] ?? null;
        $this->logger->log($userId, $action, $details, $level);
    }
    
    /**
     * Manejar errores
     */
    protected function handleError($code, $message, $details = null)
    {
        $this->logActivity('ERROR', "[$code] $message" . ($details ? " - Details: $details" : ""), 'ERROR');
        
        $isDebug = defined('APP_DEBUG') ? APP_DEBUG : false;
        
        if ($isDebug) {
            $this->sendErrorResponse($message, 500, ['code' => $code, 'details' => $details]);
        } else {
            $this->sendErrorResponse('Error interno del sistema', 500, ['code' => $code]);
        }
    }
    
    // ========================================
    // MÉTODOS DE VISTA (PARA PÁGINAS HTML)
    // ========================================
    
    /**
     * Renderizar vista
     */
    protected function render($view, $data = [])
    {
        // Datos comunes para todas las vistas
        $commonData = [
            'currentUser' => $this->currentUser,
            'isAuthenticated' => $this->isAuthenticated(),
            'isAdmin' => $this->isAdmin(),
            'appName' => defined('APP_NAME') ? APP_NAME : 'Transport System',
            'appVersion' => defined('APP_VERSION') ? APP_VERSION : '1.0.0',
            'csrfToken' => $this->generateCSRFToken(),
            'flashMessage' => $_SESSION['flash_message'] ?? null,
            'flashType' => $_SESSION['flash_type'] ?? null
        ];
        
        // Limpiar flash messages
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        
        // Combinar datos
        $viewData = array_merge($commonData, $data);
        
        // Extraer variables para la vista
        extract($viewData);
        
        // Incluir vista
        $viewsPath = defined('VIEWS_PATH') ? VIEWS_PATH : 'views';
        $viewFile = $viewsPath . '/' . $view . '.php';
        
        if (file_exists($viewFile)) {
            include $viewFile;
        } else {
            $this->handleError('VIEW_NOT_FOUND', "Vista no encontrada: $view");
        }
    }
    
    /**
     * Incluir layout
     */
    protected function layout($layout, $content, $data = [])
    {
        $data['content'] = $content;
        $this->render("layouts/$layout", $data);
    }
}
?>