<?php
/**
 * API para upload de archivos multi-formato (PDF + Excel)
 * Ruta: /api/upload-file.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

// Incluir dependencias
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Logger.php';
require_once '../classes/FileProcessorFactory.php';

try {
    // Verificar autenticación
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Usuario no autenticado', 401);
    }
    
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido', 405);
    }
    
    // Verificar que se envió un archivo
    if (!isset($_FILES['file'])) {
        throw new Exception('No se recibió ningún archivo', 400);
    }
    
    $file = $_FILES['file'];
    
    // Verificar errores de upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por PHP',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo del formulario',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta directorio temporal',
            UPLOAD_ERR_CANT_WRITE => 'Error escribiendo archivo al disco',
            UPLOAD_ERR_EXTENSION => 'Subida detenida por extensión PHP'
        ];
        
        $message = $error_messages[$file['error']] ?? 'Error desconocido en la subida';
        throw new Exception($message, 400);
    }
    
    // Información del archivo
    $original_name = $file['name'];
    $temp_path = $file['tmp_name'];
    $file_size = $file['size'];
    
    // Validar archivo usando Factory
    $validation = FileProcessorFactory::validateFile($temp_path);
    
    if (!$validation['is_valid']) {
        throw new Exception('Archivo inválido: ' . implode(', ', $validation['errors']), 400);
    }
    
    $file_type = $validation['file_info']['detected_type'];
    $file_format = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $mime_type = $validation['file_info']['mime_type'];
    
    // Verificar límites específicos del tipo
    $limits = FileProcessorFactory::getFileLimits($file_type);
    if ($file_size > $limits['max_size']) {
        $max_mb = round($limits['max_size'] / 1024 / 1024, 1);
        throw new Exception("Archivo excede el tamaño máximo para {$file_type}: {$max_mb}MB", 400);
    }
    
    // Crear directorio de destino
    $upload_base_dir = ROOT_PATH . '/assets/uploads/';
    $type_dir = $upload_base_dir . $file_type . '/';
    
    if (!is_dir($type_dir)) {
        if (!mkdir($type_dir, 0755, true)) {
            throw new Exception('Error creando directorio de destino', 500);
        }
    }
    
    // Generar nombre único para el archivo
    $file_extension = pathinfo($original_name, PATHINFO_EXTENSION);
    $unique_name = date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $file_extension;
    $destination_path = $type_dir . $unique_name;
    
    // Mover archivo
    if (!move_uploaded_file($temp_path, $destination_path)) {
        throw new Exception('Error moviendo archivo a destino final', 500);
    }
    
    // Generar número de voucher único
    $voucher_number = generateVoucherNumber();
    
    // Guardar en base de datos
    $db = Database::getInstance();
    $logger = new Logger();
    
    $voucher_data = [
        'voucher_number' => $voucher_number,
        'original_filename' => $original_name,
        'file_path' => $destination_path,
        'file_size' => $file_size,
        'file_type' => $file_type,
        'file_format' => $file_format,
        'mime_type' => $mime_type,
        'status' => 'uploaded',
        'uploaded_by' => $_SESSION['user_id'],
        'total_rows_detected' => $validation['file_info']['rows'] ?? 0
    ];
    
    $voucher_id = $db->insert('vouchers', $voucher_data);
    
    if (!$voucher_id) {
        // Si falla la inserción, eliminar archivo
        @unlink($destination_path);
        throw new Exception('Error guardando información del archivo', 500);
    }
    
    // Log de la subida
    $logger->logCrud($_SESSION['user_id'], 'CREATE', 'vouchers', $voucher_id, 
        "Archivo {$file_type} subido: {$original_name}");
    
    // Preparar respuesta con información detallada
    $response = [
        'success' => true,
        'message' => 'Archivo subido exitosamente',
        'data' => [
            'voucher_id' => $voucher_id,
            'voucher_number' => $voucher_number,
            'original_filename' => $original_name,
            'file_type' => $file_type,
            'file_format' => $file_format,
            'file_size' => $file_size,
            'file_size_formatted' => formatFileSize($file_size),
            'upload_date' => date('Y-m-d H:i:s'),
            'status' => 'uploaded'
        ],
        'file_info' => $validation['file_info'],
        'warnings' => $validation['warnings'] ?? []
    ];
    
    // Si el procesamiento automático está habilitado
    $auto_process = getSetting('auto_process_files', false);
    if ($auto_process) {
        // Procesar archivo en background (opcional)
        $response['auto_processing'] = true;
        $response['message'] .= ' - Iniciando procesamiento automático';
    }
    
    http_response_code(200);
    echo json_encode($response);
    
} catch (Exception $e) {
    $error_code = $e->getCode() ?: 500;
    http_response_code($error_code);
    
    $error_response = [
        'success' => false,
        'error' => $e->getMessage(),
        'error_code' => $error_code
    ];
    
    // Log del error
    if (isset($_SESSION['user_id'])) {
        $logger = new Logger();
        $logger->logError($_SESSION['user_id'], 
            "Error en upload: " . $e->getMessage(),
            ['file' => $_FILES['file']['name'] ?? 'unknown']
        );
    }
    
    echo json_encode($error_response);
}

/**
 * Generar número único de voucher
 */
function generateVoucherNumber() {
    $prefix = 'VCH';
    $date = date('Ymd');
    $time = date('His');
    $random = str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
    
    return $prefix . '-' . $date . '-' . $time . '-' . $random;
}

/**
 * Formatear tamaño de archivo
 */
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

/**
 * Obtener configuración del sistema
 */
function getSetting($key, $default = null) {
    try {
        $db = Database::getInstance();
        $setting = $db->fetch("SELECT setting_value, setting_type FROM system_settings WHERE setting_key = ?", [$key]);
        
        if (!$setting) {
            return $default;
        }
        
        $value = $setting['setting_value'];
        $type = $setting['setting_type'];
        
        switch ($type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'number':
                return is_numeric($value) ? (float)$value : $default;
            case 'json':
                return json_decode($value, true) ?: $default;
            default:
                return $value;
        }
        
    } catch (Exception $e) {
        return $default;
    }
}
?>