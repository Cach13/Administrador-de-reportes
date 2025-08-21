<?php
// ========================================
// config/config.php - Configuración Principal CORREGIDA
// ========================================

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuración de zona horaria
date_default_timezone_set('America/Mexico_City');

// Configuración de sesiones ANTES de iniciar cualquier sesión
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 0);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
}

// Configuración del sitio
define('SITE_NAME', 'Sistema de Gestión de Transporte');
define('COMPANY_NAME', 'TransportMX');
define('VERSION', '1.0.0');

// Configuración de rutas
define('ROOT_PATH', dirname(dirname(__FILE__)));
define('UPLOAD_PATH', ROOT_PATH . '/assets/uploads/');
define('REPORTS_PATH', ROOT_PATH . '/assets/reports/');

// Crear directorios si no existen
if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}
if (!is_dir(REPORTS_PATH)) {
    mkdir(REPORTS_PATH, 0755, true);
}

// Configuración de archivos
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_TYPES', ['pdf', 'xlsx', 'xls']);

// Configuración de seguridad
define('BCRYPT_COST', 10);
define('SESSION_TIMEOUT', 3600); // 1 hora
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutos

// Configuración de base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'transport_management');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ========================================
// CONFIGURACIÓN DE AUTENTICACIÓN
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
        'view_logs'
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
// CONSTANTES DEL SISTEMA
// ========================================

// Estados de vouchers
define('VOUCHER_STATUSES', [
    'uploaded' => 'Subido',
    'processing' => 'Procesando', 
    'processed' => 'Procesado',
    'error' => 'Error'
]);

// Estados de reportes
define('REPORT_STATUSES', [
    'generated' => 'Generado',
    'sent' => 'Enviado',
    'paid' => 'Pagado',
    'cancelled' => 'Cancelado'
]);

// Tipos de deducciones
define('DEDUCTION_TYPES', [
    'percentage' => 'Porcentaje',
    'fixed_amount' => 'Monto Fijo'
]);

// Configuración de paginación
define('RECORDS_PER_PAGE', 20);

// Formatos de fecha
define('DATE_FORMAT', 'd/m/Y');
define('DATETIME_FORMAT', 'd/m/Y H:i:s');
define('DB_DATE_FORMAT', 'Y-m-d');
define('DB_DATETIME_FORMAT', 'Y-m-d H:i:s');

// Monedas
define('CURRENCIES', [
    'MXN' => 'Peso Mexicano',
    'USD' => 'Dólar Americano'
]);

define('DEFAULT_CURRENCY', 'MXN');
define('CURRENCY_SYMBOL', '$');

// Configuración de reportes
define('REPORTS_PER_PAGE', 15);
define('MAX_EXPORT_RECORDS', 10000);

// Configuración de logs
define('LOG_LEVELS', [
    'INFO' => 'Información',
    'WARNING' => 'Advertencia', 
    'ERROR' => 'Error',
    'CRITICAL' => 'Crítico'
]);

// Configuración de notificaciones
define('NOTIFICATION_TYPES', [
    'success' => 'success',
    'error' => 'danger',
    'warning' => 'warning',
    'info' => 'info'
]);

// ========================================
// CONFIGURACIONES ESPECÍFICAS PARA ARCHIVOS
// ========================================

// Tipos MIME permitidos
define('ALLOWED_MIME_TYPES', [
    'pdf' => ['application/pdf'],
    'excel' => [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
        'application/vnd.ms-excel' // .xls
    ]
]);

// Tamaños máximos por tipo
define('MAX_FILE_SIZES', [
    'pdf' => 20 * 1024 * 1024,  // 20MB para PDF
    'excel' => 10 * 1024 * 1024  // 10MB para Excel
]);

// Configuración de procesamiento
define('PROCESSING_CONFIG', [
    'pdf' => [
        'extraction_method' => 'auto', // auto, text, ocr
        'min_confidence' => 0.7,
        'ocr_language' => 'spa'
    ],
    'excel' => [
        'header_row' => 1,
        'skip_empty_rows' => true,
        'date_format' => 'auto'
    ]
]);

// ========================================
// FUNCIONES DE UTILIDAD - DECLARAR SOLO UNA VEZ
// ========================================

/**
 * Verificar permisos del usuario
 */
if (!function_exists('hasPermission')) {
    function hasPermission($permission) {
        if (!isset($_SESSION['role'])) {
            return false;
        }
        
        $userPermissions = PERMISSIONS[$_SESSION['role']] ?? [];
        return in_array($permission, $userPermissions);
    }
}

/**
 * Requerir permiso específico
 */
if (!function_exists('requirePermission')) {
    function requirePermission($permission) {
        if (!hasPermission($permission)) {
            header('HTTP/1.1 403 Forbidden');
            die('Acceso denegado: No tienes permisos para realizar esta acción.');
        }
    }
}

/**
 * Verificar si es admin
 */
if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
}

/**
 * Verificar si es operador
 */
if (!function_exists('isOperator')) {
    function isOperator() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'operator';
    }
}

/**
 * Formatear fecha
 */
if (!function_exists('formatDate')) {
    function formatDate($date, $format = DATE_FORMAT) {
        if (empty($date)) return '-';
        return date($format, strtotime($date));
    }
}

/**
 * Formatear moneda
 */
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount, $currency = DEFAULT_CURRENCY) {
        return CURRENCY_SYMBOL . number_format($amount, 2);
    }
}

/**
 * Sanitizar entrada
 */
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($input) {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Formatear tamaño de archivo
 */
if (!function_exists('formatFileSize')) {
    function formatFileSize($bytes) {
        if ($bytes === 0) return '0 Bytes';
        
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
}

/**
 * Obtener configuración de tipo de archivo
 */
if (!function_exists('getFileTypeConfig')) {
    function getFileTypeConfig($fileType) {
        return PROCESSING_CONFIG[$fileType] ?? [];
    }
}

/**
 * Verificar si el tipo de archivo es permitido
 */
if (!function_exists('isAllowedFileType')) {
    function isAllowedFileType($mimeType) {
        foreach (ALLOWED_MIME_TYPES as $types) {
            if (in_array($mimeType, $types)) {
                return true;
            }
        }
        return false;
    }
}

/**
 * Obtener tipo de archivo por MIME type
 */
if (!function_exists('getFileTypeByMime')) {
    function getFileTypeByMime($mimeType) {
        foreach (ALLOWED_MIME_TYPES as $type => $mimes) {
            if (in_array($mimeType, $mimes)) {
                return $type;
            }
        }
        return false;
    }
}

/**
 * Generar nombre único para archivo
 */
if (!function_exists('generateUniqueFileName')) {
    function generateUniqueFileName($originalName, $type) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $timestamp = date('Y-m-d_H-i-s');
        $random = substr(md5(uniqid()), 0, 8);
        
        return "{$type}_{$timestamp}_{$random}_{$baseName}.{$extension}";
    }
}

/**
 * Validar archivo subido
 */
if (!function_exists('validateUploadedFile')) {
    function validateUploadedFile($file) {
        $errors = [];
        
        // Verificar que no hay errores de upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Error en la subida del archivo';
            return $errors;
        }
        
        // Verificar tamaño
        if ($file['size'] > MAX_UPLOAD_SIZE) {
            $errors[] = 'El archivo excede el tamaño máximo permitido';
        }
        
        // Verificar tipo MIME
        if (!isAllowedFileType($file['type'])) {
            $errors[] = 'Tipo de archivo no permitido';
        }
        
        // Verificar que el archivo existe
        if (!is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'Archivo inválido';
        }
        
        return $errors;
    }
}

/**
 * Enviar respuesta JSON
 */
if (!function_exists('sendJsonResponse')) {
    function sendJsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit();
    }
}

/**
 * Enviar respuesta de éxito
 */
if (!function_exists('sendSuccessResponse')) {
    function sendSuccessResponse($message, $data = []) {
        sendJsonResponse([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }
}

/**
 * Enviar respuesta de error
 */
if (!function_exists('sendErrorResponse')) {
    function sendErrorResponse($message, $errors = [], $statusCode = 400) {
        sendJsonResponse([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], $statusCode);
    }
}

/**
 * Función helper para escape HTML
 */
if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}
?>