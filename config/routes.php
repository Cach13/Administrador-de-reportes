<?php
// ========================================
// config/routes.php - Sistema de Rutas
// ========================================

/**
 * Configuración de rutas para el sistema de transporte
 * Mapea URLs a controladores y acciones
 */

// Cargar configuración si no está cargada
if (!defined('APP_NAME')) {
    require_once __DIR__ . '/config.php';
}

// ========================================
// DEFINICIÓN DE RUTAS
// ========================================

/**
 * Estructura de rutas:
 * 'ruta' => [
 *     'controller' => 'NombreController',
 *     'action' => 'nombreMetodo',
 *     'middleware' => ['auth', 'permission:nombre'],
 *     'methods' => ['GET', 'POST']
 * ]
 */

$routes = [
    
    // ========================================
    // RUTAS PÚBLICAS (sin autenticación)
    // ========================================
    
    '' => [
        'controller' => 'HomeController',
        'action' => 'index',
        'methods' => ['GET']
    ],
    
    'login' => [
        'controller' => 'AuthController', 
        'action' => 'showLogin',
        'methods' => ['GET']
    ],
    
    'login/process' => [
        'controller' => 'AuthController',
        'action' => 'processLogin', 
        'methods' => ['POST']
    ],
    
    'logout' => [
        'controller' => 'AuthController',
        'action' => 'logout',
        'methods' => ['GET', 'POST']
    ],
    
    // ========================================
    // RUTAS PROTEGIDAS - DASHBOARD
    // ========================================
    
    'dashboard' => [
        'controller' => 'DashboardController',
        'action' => 'index',
        'middleware' => ['auth'],
        'methods' => ['GET']
    ],
    
    'dashboard/stats' => [
        'controller' => 'DashboardController',
        'action' => 'getStats',
        'middleware' => ['auth'],
        'methods' => ['GET']
    ],
    
    'dashboard/recent-activity' => [
        'controller' => 'DashboardController',
        'action' => 'getRecentActivity',
        'middleware' => ['auth'], 
        'methods' => ['GET']
    ],
    
    // ========================================
    // RUTAS DE PROCESAMIENTO
    // ========================================
    
    'processing' => [
        'controller' => 'ProcessingController',
        'action' => 'index',
        'middleware' => ['auth', 'permission:upload_vouchers'],
        'methods' => ['GET']
    ],
    
    'processing/upload' => [
        'controller' => 'ProcessingController',
        'action' => 'upload',
        'middleware' => ['auth', 'permission:upload_vouchers'],
        'methods' => ['POST']
    ],
    
    'processing/extract/{voucher_id}' => [
        'controller' => 'ProcessingController',
        'action' => 'extract',
        'middleware' => ['auth', 'permission:process_vouchers'],
        'methods' => ['POST']
    ],
    
    'processing/preview/{voucher_id}' => [
        'controller' => 'ProcessingController',
        'action' => 'preview',
        'middleware' => ['auth', 'permission:process_vouchers'],
        'methods' => ['GET']
    ],
    
    'processing/process/{voucher_id}' => [
        'controller' => 'ProcessingController',
        'action' => 'process',
        'middleware' => ['auth', 'permission:process_vouchers'],
        'methods' => ['POST']
    ],
    
    'processing/status/{voucher_id}' => [
        'controller' => 'ProcessingController',
        'action' => 'getStatus',
        'middleware' => ['auth'],
        'methods' => ['GET']
    ],
    
    // ========================================
    // RUTAS DE GESTIÓN/ADMINISTRACIÓN
    // ========================================
    
    'management' => [
        'controller' => 'ManagementController',
        'action' => 'index',
        'middleware' => ['auth'],
        'methods' => ['GET']
    ],
    
    'management/companies' => [
        'controller' => 'ManagementController',
        'action' => 'companies',
        'middleware' => ['auth', 'permission:view_companies'],
        'methods' => ['GET']
    ],
    
    'management/companies/create' => [
        'controller' => 'ManagementController', 
        'action' => 'createCompany',
        'middleware' => ['auth', 'permission:manage_companies'],
        'methods' => ['GET', 'POST']
    ],
    
    'management/companies/{company_id}/edit' => [
        'controller' => 'ManagementController',
        'action' => 'editCompany',
        'middleware' => ['auth', 'permission:manage_companies'],
        'methods' => ['GET', 'POST']
    ],
    
    'management/vouchers' => [
        'controller' => 'ManagementController',
        'action' => 'vouchers',
        'middleware' => ['auth'],
        'methods' => ['GET']
    ],
    
    'management/reports' => [
        'controller' => 'ManagementController',
        'action' => 'reports',
        'middleware' => ['auth', 'permission:view_reports'],
        'methods' => ['GET']
    ],
    
    'management/users' => [
        'controller' => 'ManagementController',
        'action' => 'users',
        'middleware' => ['auth', 'permission:manage_users'],
        'methods' => ['GET']
    ],
    
    // ========================================
    // RUTAS DE REPORTES
    // ========================================
    
    'reports/generate' => [
        'controller' => 'ReportController',
        'action' => 'generate',
        'middleware' => ['auth', 'permission:generate_reports'],
        'methods' => ['POST']
    ],
    
    'reports/download/{report_id}' => [
        'controller' => 'ReportController',
        'action' => 'download',
        'middleware' => ['auth', 'permission:view_reports'],
        'methods' => ['GET']
    ],
    
    'reports/preview/{report_id}' => [
        'controller' => 'ReportController',
        'action' => 'preview',
        'middleware' => ['auth', 'permission:view_reports'],
        'methods' => ['GET']
    ],
    
    'reports/email/{report_id}' => [
        'controller' => 'ReportController',
        'action' => 'email',
        'middleware' => ['auth', 'permission:generate_reports'],
        'methods' => ['POST']
    ],
    
    // ========================================
    // RUTAS DE API
    // ========================================
    
    'api/companies/search' => [
        'controller' => 'ApiController',
        'action' => 'searchCompanies',
        'middleware' => ['auth'],
        'methods' => ['GET']
    ],
    
    'api/vouchers/status/{voucher_id}' => [
        'controller' => 'ApiController',
        'action' => 'getVoucherStatus',
        'middleware' => ['auth'],
        'methods' => ['GET']
    ],
    
    'api/processing/progress/{voucher_id}' => [
        'controller' => 'ApiController',
        'action' => 'getProcessingProgress',
        'middleware' => ['auth'],
        'methods' => ['GET']
    ],
    
    'api/system/health' => [
        'controller' => 'ApiController',
        'action' => 'healthCheck',
        'middleware' => ['auth'],
        'methods' => ['GET']
    ],
    
    // ========================================
    // RUTAS DE CONFIGURACIÓN (ADMIN)
    // ========================================
    
    'settings' => [
        'controller' => 'SettingsController',
        'action' => 'index',
        'middleware' => ['auth', 'permission:system_settings'],
        'methods' => ['GET']
    ],
    
    'settings/system' => [
        'controller' => 'SettingsController',
        'action' => 'system',
        'middleware' => ['auth', 'permission:system_settings'],
        'methods' => ['GET', 'POST']
    ],
    
    'settings/users' => [
        'controller' => 'SettingsController',
        'action' => 'users',
        'middleware' => ['auth', 'permission:manage_users'],
        'methods' => ['GET', 'POST']
    ],
    
    // ========================================
    // RUTAS DE LOGS Y MONITOREO
    // ========================================
    
    'logs' => [
        'controller' => 'LogController',
        'action' => 'index',
        'middleware' => ['auth', 'permission:view_logs'],
        'methods' => ['GET']
    ],
    
    'logs/view/{date}' => [
        'controller' => 'LogController',
        'action' => 'viewDate',
        'middleware' => ['auth', 'permission:view_logs'],
        'methods' => ['GET']
    ],
    
    'logs/download/{date}' => [
        'controller' => 'LogController',
        'action' => 'download',
        'middleware' => ['auth', 'permission:view_logs'],
        'methods' => ['GET']
    ]
];

// ========================================
// FUNCIONES DE AUTENTICACIÓN PARA ROUTES
// ========================================

/**
 * Verificar permisos del usuario
 */
function hasPermission($permission) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['role'])) {
        return false;
    }
    
    // Cargar permisos si están definidos
    if (defined('PERMISSIONS')) {
        $userPermissions = PERMISSIONS[$_SESSION['role']] ?? [];
        return in_array($permission, $userPermissions);
    }
    
    // Fallback básico
    return $_SESSION['role'] === 'admin';
}

/**
 * Verificar si es admin
 */
function isAdmin() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Verificar si está autenticado
 */
function isAuthenticated() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

// ========================================
// MIDDLEWARE DEFINITIONS
// ========================================

$middleware = [
    'auth' => function() {
        if (!isAuthenticated()) {
            header('Location: /login');
            exit();
        }
        return true;
    },
    
    'permission' => function($permission) {
        if (!hasPermission($permission)) {
            http_response_code(403);
            die('Acceso denegado: No tienes permisos para realizar esta acción.');
        }
        return true;
    },
    
    'admin' => function() {
        if (!isAdmin()) {
            http_response_code(403);
            die('Acceso denegado: Se requieren privilegios de administrador.');
        }
        return true;
    }
];

// ========================================
// FUNCIONES DE ROUTING
// ========================================

/**
 * Obtener la ruta actual
 */
function getCurrentRoute() {
    $uri = $_SERVER['REQUEST_URI'];
    $path = parse_url($uri, PHP_URL_PATH);
    return trim($path, '/');
}

/**
 * Obtener el método HTTP actual
 */
function getCurrentMethod() {
    return $_SERVER['REQUEST_METHOD'];
}

/**
 * Resolver ruta con parámetros
 */
function resolveRoute($pattern, $uri) {
    // Convertir {param} a regex
    $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $pattern);
    $pattern = '#^' . $pattern . '$#';
    
    if (preg_match($pattern, $uri, $matches)) {
        array_shift($matches); // Remover match completo
        return $matches;
    }
    
    return false;
}

/**
 * Encontrar ruta que coincida
 */
function findMatchingRoute($routes, $uri, $method) {
    foreach ($routes as $pattern => $config) {
        // Verificar método HTTP
        if (isset($config['methods']) && !in_array($method, $config['methods'])) {
            continue;
        }
        
        // Ruta exacta
        if ($pattern === $uri) {
            return ['route' => $config, 'params' => []];
        }
        
        // Ruta con parámetros
        $params = resolveRoute($pattern, $uri);
        if ($params !== false) {
            return ['route' => $config, 'params' => $params];
        }
    }
    
    return null;
}

/**
 * Ejecutar middleware
 */
function executeMiddleware($middlewareList, $middlewareFunctions) {
    foreach ($middlewareList as $middleware) {
        if (strpos($middleware, ':') !== false) {
            list($name, $param) = explode(':', $middleware, 2);
            if (isset($middlewareFunctions[$name])) {
                $middlewareFunctions[$name]($param);
            }
        } else {
            if (isset($middlewareFunctions[$middleware])) {
                $middlewareFunctions[$middleware]();
            }
        }
    }
}

/**
 * Generar URL
 */
function route($name, $params = []) {
    global $routes;
    
    foreach ($routes as $pattern => $config) {
        if (isset($config['name']) && $config['name'] === $name) {
            $url = $pattern;
            foreach ($params as $key => $value) {
                $url = str_replace('{' . $key . '}', $value, $url);
            }
            return getBaseUrl() . '/' . ltrim($url, '/');
        }
    }
    
    return getBaseUrl();
}

/**
 * Redirigir a ruta
 */
function redirect($route, $params = []) {
    $url = is_array($route) ? route($route[0], $params) : getBaseUrl() . '/' . ltrim($route, '/');
    header('Location: ' . $url);
    exit();
}

// ========================================
// EXPORTAR CONFIGURACIÓN
// ========================================

return [
    'routes' => $routes,
    'middleware' => $middleware
];
?>