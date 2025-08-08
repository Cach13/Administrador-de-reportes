<?php
// ========================================
// config/config.php - Configuración Principal
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
define('ALLOWED_FILE_TYPES', ['pdf']);

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
// config/database.php - Configuración de Base de Datos
// ========================================

// Incluir la clase Database si existe, sino usar conexión directa
if (file_exists(__DIR__ . '/../classes/Database.php')) {
    require_once __DIR__ . '/../classes/Database.php';
    
    try {
        $database = Database::getInstance();
        $pdo = $database->getConnection();
    } catch (Exception $e) {
        die("Error de conexión: " . $e->getMessage());
    }
} else {
    // Conexión directa como fallback
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE " . DB_CHARSET . "_unicode_ci"
        ]);
    } catch (PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }
}

// ========================================
// config/auth.php - Configuración de Autenticación
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

// Funciones de verificación de permisos
function hasPermission($permission) {
    if (!isset($_SESSION['role'])) {
        return false;
    }
    
    $userPermissions = PERMISSIONS[$_SESSION['role']] ?? [];
    return in_array($permission, $userPermissions);
}

function requirePermission($permission) {
    if (!hasPermission($permission)) {
        header('HTTP/1.1 403 Forbidden');
        die('Acceso denegado: No tienes permisos para realizar esta acción.');
    }
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isOperator() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'operator';
}

// ========================================
// config/constants.php - Constantes del Sistema
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
?>