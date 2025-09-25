<?php
/**
 * config.php
 * Configuración General del Sistema
 * Transport Management System
 */

// Prevenir acceso directo
if (!defined('SYSTEM_INIT')) {
    define('SYSTEM_INIT', true);
}

// ========================================
// CONFIGURACIÓN DE ERROR REPORTING
// ========================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ========================================
// CONFIGURACIÓN DE ZONA HORARIA
// ========================================
date_default_timezone_set('America/Mexico_City');

// ========================================
// CONFIGURACIÓN DE SISTEMA
// ========================================
define('SYSTEM_NAME', 'Transport Management System');
define('SYSTEM_VERSION', '1.0.0');
define('SYSTEM_AUTHOR', 'Transport Team');

// ========================================
// CONFIGURACIÓN DE RUTAS
// ========================================
define('ROOT_PATH', __DIR__ . '/..');
define('CONFIG_PATH', __DIR__);
define('CLASSES_PATH', ROOT_PATH . '/classes');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('REPORTS_PATH', ROOT_PATH . '/reports');
define('LOGS_PATH', ROOT_PATH . '/logs');

// ========================================
// CONFIGURACIÓN DE BASE DE DATOS
// ========================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'transport_management');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ========================================
// CONFIGURACIÓN DE SEGURIDAD
// ========================================
define('SESSION_LIFETIME', 14400); // 4 horas
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 15); // minutos
define('PASSWORD_MIN_LENGTH', 6);

// ========================================
// CONFIGURACIÓN DE ARCHIVOS
// ========================================
define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_FILE_TYPES', ['pdf', 'xlsx', 'xls']);

// ========================================
// CONFIGURACIÓN DE PROCESSING
// ========================================
define('DEFAULT_CAPITAL_PERCENTAGE', 5.00);
define('REPORTS_PER_PAGE', 20);
define('VOUCHERS_PER_PAGE', 15);

// ========================================
// CONFIGURACIÓN DE LOGS
// ========================================
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR
define('LOG_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('LOG_ROTATION_COUNT', 5);

// ========================================
// FUNCIONES AUXILIARES
// ========================================

/**
 * Autoloader simple para clases
 */
spl_autoload_register(function ($className) {
    $paths = [
        CONFIG_PATH . '/' . $className . '.php',
        CLASSES_PATH . '/' . $className . '.php',
        ROOT_PATH . '/app/Controllers/' . $className . '.php',
        ROOT_PATH . '/app/Services/' . $className . '.php',
        ROOT_PATH . '/app/Models/' . $className . '.php',
        ROOT_PATH . '/app/Utils/' . $className . '.php'
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

/**
 * Crear directorios necesarios
 */
function createSystemDirectories() {
    $dirs = [
        UPLOADS_PATH,
        UPLOADS_PATH . '/vouchers',
        REPORTS_PATH,
        LOGS_PATH
    ];
    
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            
            // Crear .htaccess para seguridad
            if (strpos($dir, 'uploads') !== false) {
                file_put_contents($dir . '/.htaccess', "deny from all\n");
            }
        }
    }
}

/**
 * Función de logging simple
 */
function logMessage($level, $message, $context = []) {
    $logFile = LOGS_PATH . '/system-' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    
    $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
    $logLine = "[{$timestamp}] {$level}: {$message}{$contextStr}\n";
    
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

/**
 * Función para sanitizar entrada
 */
function sanitizeInput($input, $type = 'string') {
    switch ($type) {
        case 'string':
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        case 'email':
            return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
        case 'int':
            return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        default:
            return trim($input);
    }
}

/**
 * Función para formatear dinero
 */
function formatMoney($amount, $currency = '$') {
    return $currency . ' ' . number_format($amount, 2);
}

/**
 * Función para formatear fechas
 */
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) return '-';
    
    if (is_string($date)) {
        $date = new DateTime($date);
    }
    
    return $date->format($format);
}

/**
 * Función para verificar si archivo es válido
 */
function isValidFileType($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, ALLOWED_FILE_TYPES);
}

/**
 * Función para generar nombre único de archivo
 */
function generateUniqueFilename($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $timestamp = date('YmdHis');
    $random = substr(md5(uniqid(rand(), true)), 0, 6);
    
    return $timestamp . '_' . $random . '.' . $extension;
}

/**
 * Función para obtener tamaño de archivo legible
 */
function humanFileSize($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}

/**
 * Función para validar estructura de BD
 */
function validateSystemRequirements() {
    $errors = [];
    
    // Verificar extensiones PHP
    $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'fileinfo'];
    foreach ($requiredExtensions as $ext) {
        if (!extension_loaded($ext)) {
            $errors[] = "Extensión PHP requerida: {$ext}";
        }
    }
    
    // Verificar permisos de escritura
    $writableDirs = [UPLOADS_PATH, REPORTS_PATH, LOGS_PATH];
    foreach ($writableDirs as $dir) {
        if (!is_writable($dir)) {
            $errors[] = "Directorio sin permisos de escritura: {$dir}";
        }
    }
    
    return empty($errors) ? true : $errors;
}

// ========================================
// INICIALIZACIÓN AUTOMÁTICA
// ========================================
try {
    // Crear directorios necesarios
    createSystemDirectories();
    
    // Log de inicialización
    logMessage('INFO', 'Sistema inicializado correctamente');
    
} catch (Exception $e) {
    logMessage('ERROR', 'Error en inicialización del sistema: ' . $e->getMessage());
    die('Error crítico del sistema. Revisa los logs.');
}

// ========================================
// CONFIGURACIÓN DE SESIÓN
// ========================================
if (session_status() === PHP_SESSION_NONE) {
    // Configuración segura de sesión
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // Cambiar a 1 en HTTPS
    ini_set('session.use_strict_mode', 1);
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    
    session_start();
}
?>