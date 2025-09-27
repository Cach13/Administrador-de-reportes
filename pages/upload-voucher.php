<?php
/**
 * upload-voucher.php
 * Página para subir vouchers PDF de Martin Marieta
 * Transport Management System
 * UBICACIÓN: pages/upload-voucher.php
 */

// Incluir configuración y dependencias
require_once '../config/config.php';
require_once '../config/Database.php';
require_once '../config/AuthManager.php';

// Inicializar componentes
$auth = new AuthManager();
$db = Database::getInstance();

// Verificar que usuario esté logueado
$auth->requireLogin();
$currentUser = $auth->getCurrentUser();

// Variables de estado
$uploadMessage = '';
$uploadSuccess = false;
$uploadError = '';

// Procesar upload si se envió formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['voucher_file'])) {
    try {
        $file = $_FILES['voucher_file'];
        
        // Validaciones básicas
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error al subir el archivo. Código: ' . $file['error']);
        }
        
        if ($file['size'] > MAX_UPLOAD_SIZE) {
            throw new Exception('El archivo es demasiado grande. Máximo ' . humanFileSize(MAX_UPLOAD_SIZE));
        }
        
        if (!isValidFileType($file['name'])) {
            throw new Exception('Tipo de archivo no válido. Solo se permiten: ' . implode(', ', ALLOWED_FILE_TYPES));
        }
        
        // Generar nombre único y ruta
        $originalName = $file['name'];
        $uniqueName = generateUniqueFilename($originalName);
        $uploadPath = UPLOADS_PATH . '/vouchers/' . $uniqueName;
        
        // Mover archivo
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception('Error al guardar el archivo');
        }
        
        // Calcular hash del archivo
        $fileHash = hash_file('sha256', $uploadPath);
        /*
        // Verificar si ya existe un archivo con el mismo hash
        $existingVoucher = $db->selectOne(
            "SELECT id, voucher_number, original_filename FROM vouchers WHERE file_hash = ?",
            [$fileHash]
        );
        
        if ($existingVoucher) {
            // Eliminar archivo duplicado
            unlink($uploadPath);
            throw new Exception("Este archivo ya fue procesado anteriormente como voucher: " . $existingVoucher['voucher_number']);
        }
        */
        // Generar número de voucher único
        $voucherNumber = 'V' . date('YmdHis') . substr(md5(uniqid()), 0, 4);
        
        // Determinar formato del archivo
        $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $fileFormat = $fileExtension === 'xlsx' || $fileExtension === 'xls' ? $fileExtension : 'pdf';
        
        // Guardar en base de datos
        $voucherId = $db->insert(
            "INSERT INTO vouchers (
                voucher_number, original_filename, file_path, file_size, file_hash,
                file_type, file_format, status, uploaded_by
            ) VALUES (?, ?, ?, ?, ?, 'martin_marieta', ?, 'uploaded', ?)",
            [
                $voucherNumber,
                $originalName,
                $uploadPath,
                $file['size'],
                $fileHash,
                $fileFormat,
                $currentUser['id']
            ]
        );
        
        if (!$voucherId) {
            unlink($uploadPath); // Limpiar archivo si falla BD
            throw new Exception('Error al guardar en base de datos');
        }
        
        // Log de actividad
        $auth->logActivity(
            $currentUser['id'],
            'VOUCHER_UPLOADED',
            "Voucher subido: {$originalName} ({$voucherNumber})"
        );
        
        $uploadSuccess = true;
        $uploadMessage = "Archivo subido exitosamente como voucher: {$voucherNumber}";
        
        // Redirigir a página de procesamiento después de 2 segundos
        header("Refresh: 2; URL=process-voucher.php?id={$voucherId}");
        
    } catch (Exception $e) {
        $uploadError = $e->getMessage();
        logMessage('ERROR', 'Error al subir voucher: ' . $e->getMessage(), [
            'user_id' => $currentUser['id'],
            'file_name' => $file['name'] ?? 'unknown'
        ]);
    }
}

// Obtener estadísticas rápidas para mostrar
$stats = [
    'total_vouchers' => $db->selectOne("SELECT COUNT(*) as total FROM vouchers")['total'] ?? 0,
    'processed_today' => $db->selectOne("SELECT COUNT(*) as total FROM vouchers WHERE DATE(upload_date) = CURDATE()")['total'] ?? 0,
    'processing_queue' => $db->selectOne("SELECT COUNT(*) as total FROM vouchers WHERE status IN ('uploaded', 'processing')")['total'] ?? 0
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subir Voucher - <?php echo SYSTEM_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
            line-height: 1.6;
            min-height: 100vh;
        }
        
        /* HEADER */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .back-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        /* MAIN CONTAINER */
        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        /* UPLOAD SECTION */
        .upload-card {
            background: white;
            border-radius: 15px;
            padding: 3rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .upload-icon {
            font-size: 4rem;
            color: #667eea;
            margin-bottom: 1.5rem;
        }
        
        .upload-title {
            font-size: 1.8rem;
            color: #2d3748;
            margin-bottom: 1rem;
        }
        
        .upload-description {
            color: #718096;
            margin-bottom: 2rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* FILE UPLOAD ZONE */
        .upload-zone {
            border: 3px dashed #cbd5e0;
            border-radius: 10px;
            padding: 3rem;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }
        
        .upload-zone:hover,
        .upload-zone.dragover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        
        .upload-zone-content {
            pointer-events: none;
        }
        
        .upload-zone-icon {
            font-size: 2.5rem;
            color: #a0aec0;
            margin-bottom: 1rem;
        }
        
        .upload-zone-text {
            color: #4a5568;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        
        .upload-zone-subtext {
            color: #718096;
            font-size: 0.9rem;
        }
        
        #fileInput {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        
        /* FORM */
        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #4a5568;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        /* ALERTS */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }
        
        .alert-success {
            background: #f0fff4;
            color: #22543d;
            border: 1px solid #c6f6d5;
        }
        
        .alert-error {
            background: #fff5f5;
            color: #822727;
            border: 1px solid #fed7d7;
        }
        
        .alert-info {
            background: #ebf8ff;
            color: #2c5282;
            border: 1px solid #bee3f8;
        }
        
        /* STATS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
            border-left: 4px solid #667eea;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2d3748;
        }
        
        .stat-label {
            color: #718096;
            font-size: 0.9rem;
        }
        
        /* FILE INFO */
        .file-info {
            background: #f7fafc;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: none;
        }
        
        .file-info.show {
            display: block;
        }
        
        .file-name {
            font-weight: 500;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }
        
        .file-details {
            color: #718096;
            font-size: 0.9rem;
        }
        
        /* REQUIREMENTS */
        .requirements {
            background: #fffaf0;
            border: 1px solid #fbd38d;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .requirements h4 {
            color: #744210;
            margin-bottom: 1rem;
        }
        
        .requirements ul {
            color: #744210;
            padding-left: 1.5rem;
        }
        
        .requirements li {
            margin-bottom: 0.5rem;
        }
        
        /* RESPONSIVE */
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
                margin: 1rem auto;
            }
            
            .upload-card {
                padding: 2rem 1.5rem;
            }
            
            .upload-zone {
                padding: 2rem 1rem;
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <header class="header">
        <div class="header-content">
            <div class="header-left">
                <a href="dashboard.php" class="back-btn">
                    <span>←</span> Dashboard
                </a>
                <div class="page-title">Subir Voucher</div>
            </div>
            
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($currentUser['full_name'], 0, 2)); ?>
                </div>
                <div>
                    <div style="font-weight: bold;"><?php echo $currentUser['full_name']; ?></div>
                    <div style="font-size: 0.9rem; opacity: 0.8;"><?php echo ucfirst($currentUser['role']); ?></div>
                </div>
            </div>
        </div>
    </header>
    
    <!-- MAIN CONTAINER -->
    <div class="container">
        
        <!-- STATS RÁPIDAS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_vouchers']; ?></div>
                <div class="stat-label">Total Vouchers</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['processed_today']; ?></div>
                <div class="stat-label">Procesados Hoy</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['processing_queue']; ?></div>
                <div class="stat-label">En Cola</div>
            </div>
        </div>
        
        <!-- MENSAJES -->
        <?php if ($uploadSuccess): ?>
        <div class="alert alert-success">
            <strong>¡Éxito!</strong> <?php echo $uploadMessage; ?>
            <br><small>Redirigiendo a procesamiento en unos segundos...</small>
        </div>
        <?php endif; ?>
        
        <?php if ($uploadError): ?>
        <div class="alert alert-error">
            <strong>Error:</strong> <?php echo $uploadError; ?>
        </div>
        <?php endif; ?>
        
        <!-- UPLOAD FORM -->
        <div class="upload-card">
            <div class="upload-icon">📤</div>
            <h1 class="upload-title">Subir Voucher PDF</h1>
            <p class="upload-description">
                Sube tu archivo PDF de Martin Marieta para extraer automáticamente 
                los datos de viajes y generar reportes por empresa.
            </p>
            
            <!-- REQUIREMENTS -->
            <div class="requirements">
                <h4>Requisitos del Archivo:</h4>
                <ul>
                    <li>Formato: PDF, XLSX o XLS</li>
                    <li>Tamaño máximo: <?php echo humanFileSize(MAX_UPLOAD_SIZE); ?></li>
                    <li>Debe contener datos de Martin Marieta</li>
                    <li>Con información de Vehicle Number (9 caracteres)</li>
                </ul>
            </div>
            
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <!-- UPLOAD ZONE -->
                <div class="upload-zone" id="uploadZone">
                    <div class="upload-zone-content">
                        <div class="upload-zone-icon">📁</div>
                        <div class="upload-zone-text">
                            Arrastra tu archivo aquí o haz clic para seleccionar
                        </div>
                        <div class="upload-zone-subtext">
                            Tipos soportados: PDF, XLSX, XLS
                        </div>
                    </div>
                    <input type="file" 
                           name="voucher_file" 
                           id="fileInput" 
                           accept=".pdf,.xlsx,.xls"
                           required>
                </div>
                
                <!-- FILE INFO -->
                <div class="file-info" id="fileInfo">
                    <div class="file-name" id="fileName"></div>
                    <div class="file-details" id="fileDetails"></div>
                </div>
                
                <!-- ADDITIONAL INFO -->
                <div class="form-group">
                    <label class="form-label" for="notes">Notas Adicionales (Opcional)</label>
                    <textarea class="form-control" 
                              id="notes" 
                              name="notes" 
                              rows="3" 
                              placeholder="Agregar cualquier nota o comentario sobre este voucher..."></textarea>
                </div>
                
                <!-- SUBMIT BUTTON -->
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <span>📤</span>
                    Subir y Procesar Voucher
                </button>
            </form>
            
            <!-- FLOW INFO -->
            <div class="alert alert-info" style="margin-top: 2rem;">
                <strong>Próximos pasos:</strong> Una vez subido el archivo, serás redirigido automáticamente 
                a la página de procesamiento donde podrás revisar los datos extraídos y seleccionar 
                la empresa para generar el reporte.
            </div>
        </div>
        
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const uploadZone = document.getElementById('uploadZone');
            const fileInput = document.getElementById('fileInput');
            const fileInfo = document.getElementById('fileInfo');
            const fileName = document.getElementById('fileName');
            const fileDetails = document.getElementById('fileDetails');
            const submitBtn = document.getElementById('submitBtn');
            const uploadForm = document.getElementById('uploadForm');
            
            // Handle drag and drop
            uploadZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                uploadZone.classList.add('dragover');
            });
            
            uploadZone.addEventListener('dragleave', function(e) {
                e.preventDefault();
                uploadZone.classList.remove('dragover');
            });
            
            uploadZone.addEventListener('drop', function(e) {
                e.preventDefault();
                uploadZone.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    showFileInfo(files[0]);
                }
            });
            
            // Handle file selection
            fileInput.addEventListener('change', function(e) {
                if (e.target.files.length > 0) {
                    showFileInfo(e.target.files[0]);
                }
            });
            
            // Show file information
            function showFileInfo(file) {
                fileName.textContent = file.name;
                fileDetails.textContent = `Tamaño: ${formatFileSize(file.size)} | Tipo: ${file.type || 'Desconocido'}`;
                fileInfo.classList.add('show');
                
                // Validate file
                const validTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'];
                const maxSize = <?php echo MAX_UPLOAD_SIZE; ?>;
                
                if (!validTypes.includes(file.type) && !file.name.match(/\.(pdf|xlsx|xls)$/i)) {
                    fileName.style.color = '#e53e3e';
                    fileDetails.textContent += ' - ⚠️ Tipo de archivo no válido';
                    submitBtn.disabled = true;
                } else if (file.size > maxSize) {
                    fileName.style.color = '#e53e3e';
                    fileDetails.textContent += ' - ⚠️ Archivo demasiado grande';
                    submitBtn.disabled = true;
                } else {
                    fileName.style.color = '#38a169';
                    fileDetails.textContent += ' - ✅ Archivo válido';
                    submitBtn.disabled = false;
                }
            }
            
            // Format file size
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 B';
                const k = 1024;
                const sizes = ['B', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
            
            // Handle form submission
            uploadForm.addEventListener('submit', function(e) {
                if (!fileInput.files.length) {
                    e.preventDefault();
                    alert('Por favor selecciona un archivo');
                    return;
                }
                
                // Show loading state
                submitBtn.innerHTML = '<span>⏳</span> Subiendo...';
                submitBtn.disabled = true;
            });
        });
    </script>
</body>
</html>