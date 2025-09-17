<?php
// ========================================
// config/constants.php - Constantes del Sistema
// Requerido por composer.json autoload
// ========================================

// Estados de archivos
define('FILE_STATUS', [
    'uploaded' => 'Subido',
    'processing' => 'Procesando',
    'processed' => 'Procesado',
    'error' => 'Error',
    'quarantine' => 'En Cuarentena'
]);

// Tipos de archivo soportados
define('SUPPORTED_FILE_TYPES', [
    'pdf' => [
        'extension' => '.pdf',
        'mime_types' => ['application/pdf'],
        'max_size' => 20 * 1024 * 1024, // 20MB
        'description' => 'Archivos PDF de vouchers'
    ],
    'excel' => [
        'extensions' => ['.xlsx', '.xls'],
        'mime_types' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel'
        ],
        'max_size' => 10 * 1024 * 1024, // 10MB
        'description' => 'Archivos Excel de datos'
    ]
]);

// Configuración de procesamiento
define('PROCESSING_CONFIG', [
    'max_concurrent_files' => 3,
    'processing_timeout' => 300, // 5 minutos
    'quality_threshold' => 0.75,
    'auto_process' => false,
    'backup_originals' => true
]);

// Configuración de reportes
define('REPORT_CONFIG', [
    'formats' => ['pdf', 'excel'],
    'default_template' => 'standard',
    'include_charts' => true,
    'auto_email' => false
]);

// Mensajes del sistema
define('SYSTEM_MESSAGES', [
    'upload_success' => 'Archivo subido correctamente',
    'upload_error' => 'Error al subir archivo',
    'processing_started' => 'Procesamiento iniciado',
    'processing_complete' => 'Procesamiento completado',
    'processing_error' => 'Error en procesamiento',
    'invalid_file_type' => 'Tipo de archivo no válido',
    'file_too_large' => 'Archivo demasiado grande',
    'no_data_found' => 'No se encontraron datos válidos'
]);

// Configuración de calidad de datos
define('DATA_QUALITY', [
    'excellent' => 0.95,
    'good' => 0.80,
    'fair' => 0.65,
    'poor' => 0.50
]);

// Configuración de logs
define('LOG_CONFIG', [
    'max_file_size' => 10 * 1024 * 1024, // 10MB
    'retention_days' => 30,
    'levels' => ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL']
]);

// Códigos de error del sistema
define('ERROR_CODES', [
    'FILE_NOT_FOUND' => 1001,
    'INVALID_FORMAT' => 1002,
    'PROCESSING_FAILED' => 1003,
    'DATABASE_ERROR' => 1004,
    'PERMISSION_DENIED' => 1005,
    'AUTHENTICATION_FAILED' => 1006,
    'VALIDATION_ERROR' => 1007,
    'UPLOAD_ERROR' => 1008,
    'GENERATION_ERROR' => 1009,
    'EMAIL_ERROR' => 1010
]);

// Rutas de directorios (complementa config.php)
define('UPLOAD_DIRS', [
    'pdf' => 'assets/uploads/pdf/',
    'excel' => 'assets/uploads/excel/',
    'processed' => 'assets/uploads/processed/',
    'backup' => 'assets/uploads/backup/',
    'temp' => 'assets/uploads/temp/'
]);

// Configuraciones Martin Marieta específicas
define('MARTIN_MARIETA_CONFIG', [
    'company_identifier_position' => [4, 6], // Posiciones 4-6 del Vehicle Number
    'required_fields' => [
        'ship_date',
        'location', 
        'ticket_number',
        'haul_rate',
        'quantity',
        'amount',
        'vehicle_number'
    ],
    'date_formats' => [
        'Y-m-d',
        'm/d/Y',
        'd/m/Y',
        'Y-m-d H:i:s'
    ]
]);

// Configuraciones Capital Transport específicas
define('CAPITAL_TRANSPORT_CONFIG', [
    'default_percentage' => 5.00,
    'payment_reset_month' => 1, // Enero
    'report_template' => 'capital_transport_standard',
    'logo_path' => 'assets/img/capital-transport-logo.png'
]);

?>