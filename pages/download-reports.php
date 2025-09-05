<?php
/**
 * Download Report - Sistema de descarga de reportes generados
 * Ruta: /pages/download-report.php
 */

// Verificar autenticación
require_once '../includes/auth-check.php';
require_once '../classes/Database.php';

try {
    // Obtener ID del reporte
    $report_id = $_GET['id'] ?? null;
    
    if (!$report_id || !is_numeric($report_id)) {
        throw new Exception('ID de reporte inválido');
    }
    
    $db = Database::getInstance();
    
    // Obtener información del reporte
    $report = $db->fetch("
        SELECT 
            r.*,
            c.name as company_name,
            c.identifier as company_identifier,
            v.voucher_number,
            v.original_filename
        FROM reports r
        JOIN companies c ON r.company_id = c.id
        JOIN vouchers v ON r.voucher_id = v.id
        WHERE r.id = ?
    ", [$report_id]);
    
    if (!$report) {
        throw new Exception('Reporte no encontrado');
    }
    
    // Verificar que el archivo existe
    if (!$report['file_path'] || !file_exists($report['file_path'])) {
        throw new Exception('Archivo de reporte no encontrado en el servidor');
    }
    
    $file_path = $report['file_path'];
    $file_size = filesize($file_path);
    $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    
    // Generar nombre de archivo para descarga
    $download_filename = sprintf(
        "Capital_Transport_Payment_%s_%s_%s.%s",
        str_pad($report['payment_no'], 3, '0', STR_PAD_LEFT),
        $report['company_identifier'],
        date('Y-m-d', strtotime($report['payment_date'])),
        $file_extension
    );
    
    // Configurar headers según el tipo de archivo
    switch ($file_extension) {
        case 'pdf':
            $content_type = 'application/pdf';
            break;
        case 'xlsx':
            $content_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            break;
        case 'xls':
            $content_type = 'application/vnd.ms-excel';
            break;
        default:
            $content_type = 'application/octet-stream';
    }
    
    // Limpiar cualquier output previo
    ob_clean();
    
    // Headers para descarga
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: attachment; filename="' . $download_filename . '"');
    header('Content-Length: ' . $file_size);
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    header('Expires: 0');
    
    // Leer y enviar archivo
    if ($file_size > 10 * 1024 * 1024) { // Si es mayor a 10MB, leer en chunks
        $handle = fopen($file_path, 'rb');
        if ($handle) {
            while (!feof($handle)) {
                echo fread($handle, 8192);
                flush();
            }
            fclose($handle);
        }
    } else {
        readfile($file_path);
    }
    
    // Log de descarga
    $db->insert('activity_logs', [
        'user_id' => $_SESSION['user_id'],
        'action' => 'REPORT_DOWNLOAD',
        'description' => "Descarga de reporte Payment No. {$report['payment_no']} - {$report['company_name']}",
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    exit;
    
} catch (Exception $e) {
    // Error handling con página HTML
    $error_message = htmlspecialchars($e->getMessage());
    
    // Si ya se enviaron headers, solo mostrar el error
    if (headers_sent()) {
        echo "Error: " . $error_message;
        exit;
    }
    
    // Mostrar página de error
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error de Descarga - Capital Transport</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            :root {
                --primary-red: #dc2626;
                --dark-gray: #2c2c2c;
                --light-gray: #f5f5f5;
                --white: #ffffff;
                --text-muted: #6b7280;
            }
            
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: var(--light-gray);
                color: var(--dark-gray);
                margin: 0;
                padding: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
            }
            
            .error-container {
                background: var(--white);
                padding: 3rem;
                border-radius: 16px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                text-align: center;
                max-width: 500px;
                margin: 2rem;
            }
            
            .error-icon {
                font-size: 4rem;
                color: var(--primary-red);
                margin-bottom: 1.5rem;
            }
            
            .error-title {
                font-size: 1.5rem;
                font-weight: 600;
                color: var(--dark-gray);
                margin-bottom: 1rem;
            }
            
            .error-message {
                color: var(--text-muted);
                margin-bottom: 2rem;
                line-height: 1.6;
            }
            
            .btn {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.75rem 1.5rem;
                background: var(--primary-red);
                color: var(--white);
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
                transition: all 0.3s ease;
            }
            
            .btn:hover {
                background: #b91c1c;
                transform: translateY(-1px);
                color: var(--white);
                text-decoration: none;
            }
            
            .btn-secondary {
                background: var(--text-muted);
                margin-left: 1rem;
            }
            
            .btn-secondary:hover {
                background: #4b5563;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h1 class="error-title">Error en la Descarga</h1>
            <p class="error-message">
                <?php echo $error_message; ?>
            </p>
            <div>
                <a href="reports.php" class="btn">
                    <i class="fas fa-arrow-left"></i>
                    Volver a Reportes
                </a>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>