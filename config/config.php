<?php
// ========================================
// config/config.php - Configuración Principal MODERNIZADA
// Integra tu configuración actual con nuevo sistema .env
// ========================================

// Cargar autoload de Composer
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

// ========================================
// CONFIGURACIÓN DE PHP
// ========================================

// Configuración de errores según entorno
if ($_ENV['APP_ENV'] === 'development') {
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
// CONFIGURACIÓN DE SESIONES
// ========================================

if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.name', $_ENV['SESSION_NAME'] ?? 'transport_session');
    ini_set('session.cookie_lifetime', $_ENV['SESSION_LIFETIME'] ?? '1440');
    ini_set('session.cookie_secure', $_ENV['SESSION_SECURE'] ?? '0');
    ini_set('session.cookie_httponly', $_ENV['SESSION_HTTPONLY'] ?? '1');
    ini_set('session.cookie_samesite', $_ENV['SESSION_SAMESITE'] ?? 'Lax');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', $_ENV['SESSION_LIFETIME'] ?? '1440');
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
define('COMPANY_NAME', 'Capital Transport LLP'); // Tu empresa
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
define('UPLOAD_PATH', ROOT_PATH . '/' . ($_ENV['UPLOAD_PATH'] ?? 'public/uploads'));
define('REPORTS_PATH', ROOT_PATH . '/' . ($_ENV['REPORTS_PATH'] ?? 'public/uploads/reports'));
define('TEMP_PATH', ROOT_PATH . '/' . ($_ENV['TEMP_PATH'] ?? 'storage/temp'));
define('BACKUP_PATH', ROOT_PATH . '/' . ($_ENV['BACKUP_PATH'] ?? 'storage/backup'));
define('LOG_PATH', ROOT_PATH . '/' . ($_ENV['LOG_PATH'] ?? 'storage/logs'));
define('CACHE_PATH', ROOT_PATH . '/' . ($_ENV['CACHE_PATH'] ?? 'storage/cache'));

// Crear directorios si no existen
$directories = [
    UPLOAD_PATH,
    REPORTS_PATH, 
    TEMP_PATH,
    BACKUP_PATH,
    LOG_PATH,
    CACHE_PATH,
    UPLOAD_PATH . '/pdf',
    UPLOAD_PATH . '/excel',
    UPLOAD_PATH . '/processed'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// ========================================
// CONFIGURACIÓN DE BASE DE DATOS
// ========================================

define('DB_CONNECTION', $_ENV['DB_CONNECTION'] ?? 'mysql');
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
define('DB_NAME', $_ENV['DB_DATABASE'] ?? 'transport_management');
define('DB_USER', $_ENV['DB_USERNAME'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASSWORD'] ?? '');
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');
define('DB_COLLATION', $_ENV['DB_COLLATION'] ?? 'utf8mb4_unicode_ci');

// Compatibilidad con código existente
define('DB_DATABASE', DB_NAME);
define('DB_USERNAME', DB_USER);
define('DB_PASSWORD', DB_PASS);

// ========================================
// CONFIGURACIÓN DE ARCHIVOS
// ========================================

define('MAX_UPLOAD_SIZE', (int)($_ENV['UPLOAD_MAX_SIZE'] ?? 20971520)); // 20MB por defecto
define('ALLOWED_FILE_TYPES', explode(',', $_ENV['UPLOAD_ALLOWED_TYPES'] ?? 'pdf,xlsx,xls'));

// Configuración de procesamiento
define('PROCESSING_TIMEOUT', (int)($_ENV['PROCESSING_TIMEOUT'] ?? 300));
define('MAX_CONCURRENT_PROCESSES', (int)($_ENV['MAX_CONCURRENT_PROCESSES'] ?? 3));
define('QUALITY_THRESHOLD', (float)($_ENV['QUALITY_THRESHOLD'] ?? 0.75));
define('AUTO_PROCESS_FILES', filter_var($_ENV['AUTO_PROCESS_FILES'] ?? 'false', FILTER_VALIDATE_BOOLEAN));
define('BACKUP_ORIGINAL_FILES', filter_var($_ENV['BACKUP_ORIGINAL_FILES'] ?? 'true', FILTER_VALIDATE_BOOLEAN));

// ========================================
// CONFIGURACIÓN DE SEGURIDAD
// ========================================

define('BCRYPT_COST', 10);
define('SESSION_TIMEOUT', (int)($_ENV['SESSION_LIFETIME'] ?? 3600));
define('MAX_LOGIN_ATTEMPTS', (int)($_ENV['MAX_LOGIN_ATTEMPTS'] ?? 5));
define('LOCKOUT_TIME', (int)($_ENV['LOCKOUT_DURATION'] ?? 900));
define('ENCRYPTION_KEY', $_ENV['ENCRYPTION_KEY'] ?? 'default-key-change-in-production');
define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? 'default-jwt-secret-change-in-production');
define('CSRF_TOKEN_EXPIRY', (int)($_ENV['CSRF_TOKEN_EXPIRY'] ?? 3600));

// ========================================
// CONFIGURACIÓN DE EMAIL
// ========================================

define('MAIL_DRIVER', $_ENV['MAIL_DRIVER'] ?? 'smtp');
define('MAIL_HOST', $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com');
define('MAIL_PORT', (int)($_ENV['MAIL_PORT'] ?? 587));
define('MAIL_USERNAME', $_ENV['MAIL_USERNAME'] ?? '');
define('MAIL_PASSWORD', $_ENV['MAIL_PASSWORD'] ?? '');
define('MAIL_ENCRYPTION', $_ENV['MAIL_ENCRYPTION'] ?? 'tls');
define('MAIL_FROM_ADDRESS', $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@capitaltransport.com');
define('MAIL_FROM_NAME', $_ENV['MAIL_FROM_NAME'] ?? 'Capital Transport System');

// ========================================
// CONFIGURACIÓN DE LOGGING
// ========================================

define('LOG_LEVEL', $_ENV['LOG_LEVEL'] ?? 'info');
define('LOG_MAX_FILES', (int)($_ENV['LOG_MAX_FILES'] ?? 30));
define('LOG_MAX_SIZE', (int)($_ENV['LOG_MAX_SIZE'] ?? 10485760)); // 10MB

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
// CONFIGURACIÓN DE AUTENTICACIÓN (MANTENIENDO COMPATIBILIDAD)
// ========================================

// Configuración de roles
define('ROLES', [
    'admin' => 'Administrador',
    'operator' => 'Operador'
]);

// Permisos por rol
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
// AUTOLOAD MANUAL PARA CLASES EXISTENTES
// ========================================

/**
 * Autoloader para clases existentes que no usan PSR-4
 * Esto mantiene compatibilidad con tu código actual
 */
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
    
    // Intentar cargar desde directorio classes/
    $classFile = CLASSES_PATH . '/' . $class . '.php';
    if (file_exists($classFile)) {
        require_once $classFile;
        return true;
    }
    
    return false;
});

// ========================================
// FUNCIONES DE UTILIDAD GLOBALES
// ========================================

/**
 * Obtener valor de configuración con valor por defecto
 */
function config($key, $default = null) {
    return $_ENV[$key] ?? $default;
}

/**
 * Verificar si estamos en entorno de desarrollo
 */
function isDebugMode() {
    return APP_DEBUG && APP_ENV === 'development';
}

/**
 * Obtener URL base de la aplicación
 */
function getBaseUrl() {
    return rtrim(APP_URL, '/');
}

/**
 * Obtener ruta completa a un archivo
 */
function getPath($relativePath) {
    return ROOT_PATH . '/' . ltrim($relativePath, '/');
}

/**
 * Verificar si un directorio es escribible
 */
function isWritableDir($path) {
    return is_dir($path) && is_writable($path);
}

// ========================================
// VALIDACIONES DE CONFIGURACIÓN
// ========================================

// Verificar configuración crítica
if (APP_ENV === 'production') {
    $criticalChecks = [
        'ENCRYPTION_KEY' => (ENCRYPTION_KEY !== 'default-key-change-in-production'),
        'JWT_SECRET' => (JWT_SECRET !== 'default-jwt-secret-change-in-production'),
        'DB_PASSWORD' => !empty(DB_PASS),
        'UPLOAD_PATH' => isWritableDir(UPLOAD_PATH),
        'LOG_PATH' => isWritableDir(LOG_PATH)
    ];
    
    foreach ($criticalChecks as $check => $valid) {
        if (!$valid) {
            error_log("CRITICAL CONFIG WARNING: {$check} not properly configured for production");
        }
    }
}

// ========================================
// INICIALIZACIÓN FINAL
// ========================================

// Registrar función de manejo de errores personalizada
if (APP_ENV !== 'development') {
    set_error_handler(function($severity, $message, $file, $line) {
        error_log("PHP Error [{$severity}]: {$message} in {$file} on line {$line}");
        return false;
    });
}

// Log de inicio del sistema (solo en desarrollo)
if (isDebugMode()) {
    error_log("Transport Management System v" . APP_VERSION . " initialized successfully");
}
?>