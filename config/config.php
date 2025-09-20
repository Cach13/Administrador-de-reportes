<?php
// ========================================
// config/config.php - ARREGLO DE SESIONES - PASO 14 FIX
// ========================================

// Cargar autoload de Composer
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

// ========================================
// CONFIGURACIÓN DE PHP - ANTES DE CUALQUIER OUTPUT
// ========================================

// Configuración de errores según entorno
if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
} else {
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Configuración de zona horaria
date_default_timezone_set($_ENV['TIMEZONE'] ?? 'America/Mexico_City');

// Configuración de memoria y tiempo de ejecución
ini_set('memory_limit', '256M');
ini_set('max_execution_time', $_ENV['PROCESSING_TIMEOUT'] ?? '300');
ini_set('upload_max_filesize', '20M');
ini_set('post_max_size', '25M');

// ========================================
// CONFIGURACIÓN DE SESIONES - SOLO SI NO HAY HEADERS ENVIADOS
// ========================================

// Solo configurar sesiones si no se han enviado headers
if (!headers_sent() && session_status() == PHP_SESSION_NONE) {
    ini_set('session.name', $_ENV['SESSION_NAME'] ?? 'transport_session');
    ini_set('session.cookie_lifetime', $_ENV['SESSION_LIFETIME'] ?? '1440');
    ini_set('session.cookie_secure', $_ENV['SESSION_SECURE'] ?? '0');
    ini_set('session.cookie_httponly', $_ENV['SESSION_HTTPONLY'] ?? '1');
    ini_set('session.cookie_samesite', $_ENV['SESSION_SAMESITE'] ?? 'Lax');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', $_ENV['SESSION_LIFETIME'] ?? '1440');
    
    // Iniciar sesión
    session_start();
} elseif (session_status() == PHP_SESSION_NONE) {
    // Si ya se enviaron headers, al menos intentar iniciar la sesión
    @session_start();
}

// ========================================
// CONSTANTES DEL SISTEMA
// ========================================

// Información del sistema
define('APP_NAME', $_ENV['APP_NAME'] ?? 'Transport Management System');
define('APP_VERSION', $_ENV['APP_VERSION'] ?? '2.0.0');
define('APP_ENV', $_ENV['APP_ENV'] ?? 'production');
define('APP_DEBUG', filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN));
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost');

// Información de la empresa (manteniendo compatibilidad)
define('SITE_NAME', APP_NAME);
define('COMPANY_NAME', 'Capital Transport LLP');
define('VERSION', APP_VERSION);

// ========================================
// CONFIGURACIÓN DE RUTAS Y DIRECTORIOS
// ========================================

define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('APP_PATH', ROOT_PATH . '/app');
define('CLASSES_PATH', ROOT_PATH . '/classes');
define('VIEWS_PATH', ROOT_PATH . '/views');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');

// Rutas de uploads y archivos
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('REPORTS_PATH', ROOT_PATH . '/reports');
define('LOGS_PATH', ROOT_PATH . '/logs');
define('TEMPLATES_PATH', ROOT_PATH . '/templates');
define('CACHE_PATH', ROOT_PATH . '/cache');

// ========================================
// CONFIGURACIÓN DE BASE DE DATOS
// ========================================

define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'transport_db');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');
define('DB_COLLATION', $_ENV['DB_COLLATION'] ?? 'utf8mb4_unicode_ci');

// Configuración adicional de BD
define('DB_PREFIX', $_ENV['DB_PREFIX'] ?? '');
define('DB_POOL_SIZE', (int)($_ENV['DB_POOL_SIZE'] ?? 10));
define('DB_TIMEOUT', (int)($_ENV['DB_TIMEOUT'] ?? 30));

// ========================================
// CONFIGURACIÓN DE LOGGING
// ========================================

define('LOG_LEVEL', $_ENV['LOG_LEVEL'] ?? 'INFO');
define('LOG_MAX_FILES', (int)($_ENV['LOG_MAX_FILES'] ?? 30));
define('LOG_MAX_SIZE', $_ENV['LOG_MAX_SIZE'] ?? '10MB');
define('LOG_FORMAT', $_ENV['LOG_FORMAT'] ?? '[%datetime%] %level_name%: %message%');

// ========================================
// CONFIGURACIÓN DE SEGURIDAD
// ========================================

define('SECRET_KEY', $_ENV['SECRET_KEY'] ?? 'your-secret-key-here');
define('CSRF_TOKEN_LIFETIME', (int)($_ENV['CSRF_TOKEN_LIFETIME'] ?? 3600));
define('PASSWORD_MIN_LENGTH', (int)($_ENV['PASSWORD_MIN_LENGTH'] ?? 8));
define('LOGIN_MAX_ATTEMPTS', (int)($_ENV['LOGIN_MAX_ATTEMPTS'] ?? 5));
define('LOGIN_LOCKOUT_TIME', (int)($_ENV['LOGIN_LOCKOUT_TIME'] ?? 900));

// ========================================
// CONFIGURACIÓN DE PROCESAMIENTO
// ========================================

define('PROCESSING_MAX_FILE_SIZE', $_ENV['PROCESSING_MAX_FILE_SIZE'] ?? '20M');
define('PROCESSING_TIMEOUT', (int)($_ENV['PROCESSING_TIMEOUT'] ?? 300));
define('PROCESSING_CONCURRENT_LIMIT', (int)($_ENV['PROCESSING_CONCURRENT_LIMIT'] ?? 5));
define('PROCESSING_RETRY_ATTEMPTS', (int)($_ENV['PROCESSING_RETRY_ATTEMPTS'] ?? 3));

// ========================================
// CONFIGURACIÓN DE REPORTES
// ========================================

define('REPORT_DEFAULT_FORMAT', $_ENV['REPORT_DEFAULT_FORMAT'] ?? 'pdf');
define('REPORT_INCLUDE_CHARTS', filter_var($_ENV['REPORT_INCLUDE_CHARTS'] ?? 'true', FILTER_VALIDATE_BOOLEAN));
define('REPORT_AUTO_EMAIL', filter_var($_ENV['REPORT_AUTO_EMAIL'] ?? 'false', FILTER_VALIDATE_BOOLEAN));
define('REPORT_COMPANY_LOGO', $_ENV['REPORT_COMPANY_LOGO'] ?? 'public/assets/img/logo.png');

// ========================================
// CONFIGURACIÓN DE API
// ========================================

define('API_RATE_LIMIT', (int)($_ENV['API_RATE_LIMIT'] ?? 60));
define('API_RATE_LIMIT_WINDOW', (int)($_ENV['API_RATE_LIMIT_WINDOW'] ?? 60));
define('API_TIMEOUT', (int)($_ENV['API_TIMEOUT'] ?? 30));

// ========================================
// CONFIGURACIÓN DE AUTENTICACIÓN
// ========================================

define('ROLES', [
    'admin' => 'Administrador',
    'operator' => 'Operador'
]);

define('PERMISSIONS', [
    'admin' => [
        'upload_vouchers',
        'process_vouchers', 
        'manage_companies',
        'generate_reports',
        'view_reports',
        'manage_users',
        'system_settings',
        'view_logs',
        'export_data'
    ],
    'operator' => [
        'upload_vouchers',
        'process_vouchers',
        'view_companies',
        'generate_reports',
        'view_reports'
    ]
]);

// ========================================
// CONFIGURACIÓN DE CACHE
// ========================================

define('CACHE_DRIVER', $_ENV['CACHE_DRIVER'] ?? 'file');
define('CACHE_DEFAULT_TTL', (int)($_ENV['CACHE_DEFAULT_TTL'] ?? 3600));

// ========================================
// CONFIGURACIÓN DE DESARROLLO
// ========================================

if (APP_ENV === 'development') {
    define('DEV_SHOW_ERRORS', filter_var($_ENV['DEV_SHOW_ERRORS'] ?? 'true', FILTER_VALIDATE_BOOLEAN));
    define('DEV_LOG_QUERIES', filter_var($_ENV['DEV_LOG_QUERIES'] ?? 'false', FILTER_VALIDATE_BOOLEAN));
    define('DEV_CACHE_DISABLE', filter_var($_ENV['DEV_CACHE_DISABLE'] ?? 'false', FILTER_VALIDATE_BOOLEAN));
}

// ========================================
// VALIDACIÓN DE CONFIGURACIÓN CRÍTICA
// ========================================

// Verificar configuración de BD en producción
if (APP_ENV === 'production' && (empty(DB_PASS) || DB_PASS === 'default_password')) {
    if (APP_DEBUG) {
        echo "CRITICAL CONFIG WARNING: DB_PASSWORD not properly configured for production\n";
    }
    // En producción real, esto debería ser un error fatal
    // throw new Exception('Database password must be configured for production');
}
// ========================================
// CONFIGURACIÓN DE ARCHIVOS - AGREGAR ESTO
// ========================================

// Tamaño máximo de upload (20MB)
define('MAX_UPLOAD_SIZE', (int)($_ENV['UPLOAD_MAX_SIZE'] ?? 20971520));

// Tipos de archivo permitidos
define('ALLOWED_FILE_TYPES', explode(',', $_ENV['UPLOAD_ALLOWED_TYPES'] ?? 'pdf,xlsx,xls'));

// ========================================
// AUTOLOAD PARA CLASES EXISTENTES
// ========================================

spl_autoload_register(function ($class) {
    // Mapeo de clases existentes
    $classMap = [
        'Database' => CLASSES_PATH . '/Database.php',
        'Logger' => CLASSES_PATH . '/Logger.php', 
        'MartinMarietaProcessor' => CLASSES_PATH . '/MartinMarietaProcessor.php',
        'CapitalTransportReportGenerator' => CLASSES_PATH . '/CapitalTransportReportGenerator.php'
    ];
    
    if (isset($classMap[$class]) && file_exists($classMap[$class])) {
        require_once $classMap[$class];
        return true;
    }
    
    // PSR-4 para namespace App
    if (strpos($class, 'App\\') === 0) {
        $file = APP_PATH . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
    
    return false;
});

// ========================================
// FUNCIONES HELPER GLOBALES
// ========================================

/**
 * Obtener URL base de la aplicación
 */
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = dirname($_SERVER['SCRIPT_NAME']);
    return rtrim($protocol . $host . $path, '/');
}

/**
 * Verificar si estamos en modo debug
 */
function isDebugMode() {
    return defined('APP_DEBUG') ? APP_DEBUG : false;
}

/**
 * Generar token CSRF
 */
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    return '';
}

/**
 * Verificar token CSRF
 */
function verifyCSRFToken($token) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    return false;
}

/**
 * Obtener configuración por clave
 */
function config($key, $default = null) {
    $configs = [
        'app.name' => APP_NAME,
        'app.version' => APP_VERSION,
        'app.env' => APP_ENV,
        'app.debug' => APP_DEBUG,
        'db.host' => DB_HOST,
        'db.name' => DB_NAME,
        'cache.ttl' => CACHE_DEFAULT_TTL
    ];
    
    return $configs[$key] ?? $default;
}

// ========================================
// INICIALIZACIÓN FINAL
// ========================================

// Crear directorios necesarios
$directories = [
    UPLOAD_PATH,
    REPORTS_PATH, 
    LOGS_PATH,
    CACHE_PATH,
    STORAGE_PATH
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// Configurar zona horaria del sistema
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set($_ENV['TIMEZONE'] ?? 'America/Mexico_City');
}

// ========================================
// FIN DE CONFIGURACIÓN
// ========================================
?>