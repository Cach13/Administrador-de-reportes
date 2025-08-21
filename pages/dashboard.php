<?php
/**
 * Dashboard actualizado con funcionalidad completa - SIN BUGS
 * Ruta: /pages/dashboard.php
 */

// Verificar autenticación
require_once '../includes/auth-check.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo COMPANY_NAME ?? 'Transport Management'; ?></title>
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #2c2c2c;
            line-height: 1.6;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #2c2c2c 0%, #1a1a1a 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-logo {
            width: 40px;
            height: 40px;
            background: #dc2626;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .header-logo i {
            color: white;
            font-size: 1.2rem;
        }

        .company-info h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #dc2626;
        }

        .company-info p {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            font-size: 1rem;
        }

        .user-role {
            font-size: 0.8rem;
            opacity: 0.8;
            text-transform: capitalize;
        }

        .logout-btn {
            background: #dc2626;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .logout-btn:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }

        /* Main Content */
        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Upload Section */
        .upload-section {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
            border-left: 4px solid #dc2626;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2c2c2c;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .upload-area {
            border: 2px dashed #dc2626;
            border-radius: 12px;
            padding: 3rem 2rem;
            text-align: center;
            background: #fef2f2;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .upload-area:hover {
            background: #fee2e2;
            border-color: #b91c1c;
        }

        .upload-area.dragover {
            background: #dc2626;
            color: white;
            transform: scale(1.02);
        }

        .file-type-icons {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 1.5rem;
        }

        .file-type-icon {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem;
            border-radius: 12px;
            background: rgba(220, 38, 38, 0.1);
            border: 2px solid rgba(220, 38, 38, 0.2);
            transition: all 0.3s ease;
            min-width: 120px;
        }

        .upload-area.dragover .file-type-icon {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .file-type-icon:hover {
            background: rgba(220, 38, 38, 0.2);
            transform: translateY(-2px);
        }

        .file-type-icon i {
            font-size: 2.5rem;
            color: #dc2626;
        }

        .upload-area.dragover .file-type-icon i {
            color: white;
        }

        .file-type-icon span {
            font-weight: 600;
            font-size: 0.9rem;
            color: #dc2626;
        }

        .upload-area.dragover .file-type-icon span {
            color: white;
        }

        .pdf-icon i {
            color: #dc2626 !important;
        }

        .excel-icon i {
            color: #10b981 !important;
        }

        .upload-area.dragover .pdf-icon i,
        .upload-area.dragover .excel-icon i {
            color: white !important;
        }

        .upload-text {
            font-size: 1.4rem;
            font-weight: 600;
            color: #2c2c2c;
            margin-bottom: 0.5rem;
        }

        .upload-area.dragover .upload-text {
            color: white;
        }

        .upload-subtitle {
            color: #666;
            font-size: 1rem;
            margin-bottom: 1.5rem;
        }

        .upload-area.dragover .upload-subtitle {
            color: rgba(255, 255, 255, 0.9);
        }

        .file-input {
            display: none;
        }

        .upload-btn {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
        }

        .upload-btn:hover {
            background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(220, 38, 38, 0.4);
        }

        .supported-formats {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1rem;
            font-size: 0.85rem;
            color: #666;
        }

        .format-badge {
            background: #f1f5f9;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
        }

        /* Progress Bar */
        .progress-container {
            display: none;
            margin-top: 1.5rem;
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .progress-file-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .progress-file-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .progress-bar {
            width: 100%;
            height: 12px;
            background: #f1f5f9;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #dc2626, #ef4444);
            width: 0%;
            transition: width 0.3s ease;
            border-radius: 6px;
        }

        .progress-text {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: #666;
        }

        /* Vouchers Table Section */
        .vouchers-section {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .table-filters {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-select {
            padding: 0.5rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            background: white;
            min-width: 120px;
        }

        .filter-select:focus {
            border-color: #dc2626;
            outline: none;
        }

        .refresh-btn {
            background: #10b981;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .refresh-btn:hover {
            background: #059669;
        }

        .table-container {
            overflow-x: auto;
            margin-top: 1rem;
        }

        .vouchers-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .vouchers-table th {
            background: #f8f9fa;
            color: #2c2c2c;
            font-weight: 600;
            padding: 1rem;
            text-align: left;
            border-bottom: 2px solid #e9ecef;
            white-space: nowrap;
        }

        .vouchers-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .vouchers-table tr:hover {
            background: #f8f9fa;
        }

        .file-type-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .file-type-pdf {
            background: #fee2e2;
            color: #dc2626;
        }

        .file-type-excel {
            background: #d1fae5;
            color: #059669;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-processed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-processing {
            background: #fef3c7;
            color: #92400e;
        }

        .status-uploaded {
            background: #e0e7ff;
            color: #3730a3;
        }

        .status-error {
            background: #fecaca;
            color: #dc2626;
        }

        .amount {
            font-weight: 600;
            color: #059669;
        }

        .quality-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quality-bar {
            width: 60px;
            height: 8px;
            background: #f1f5f9;
            border-radius: 4px;
            overflow: hidden;
        }

        .quality-fill {
            height: 100%;
            transition: width 0.3s ease;
        }

        .quality-high { background: #10b981; }
        .quality-medium { background: #f59e0b; }
        .quality-low { background: #ef4444; }

        .quality-text {
            font-size: 0.8rem;
            font-weight: 500;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 0.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
        }

        .btn-view {
            background: #0284c7;
            color: white;
        }

        .btn-view:hover {
            background: #0369a1;
        }

        .btn-process {
            background: #dc2626;
            color: white;
        }

        .btn-process:hover {
            background: #b91c1c;
        }

        .btn-download {
            background: #059669;
            color: white;
        }

        .btn-download:hover {
            background: #047857;
        }

        .btn-delete {
            background: #dc2626;
            color: white;
        }

        .btn-delete:hover {
            background: #b91c1c;
        }

        .no-data {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .no-data i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: #666;
        }

        .loading i {
            font-size: 2rem;
            margin-bottom: 1rem;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .main-content {
                padding: 1rem;
            }

            .upload-section, .vouchers-section {
                padding: 1.5rem;
            }

            .upload-area {
                padding: 2rem 1rem;
            }

            .file-type-icons {
                flex-direction: column;
                gap: 1rem;
            }

            .file-type-icon {
                min-width: auto;
            }

            .vouchers-table {
                font-size: 0.8rem;
            }

            .vouchers-table th,
            .vouchers-table td {
                padding: 0.75rem 0.5rem;
            }

            .actions {
                flex-direction: column;
            }

            .table-filters {
                flex-direction: column;
                width: 100%;
            }

            .filter-select {
                width: 100%;
            }

            .table-header {
                flex-direction: column;
                align-items: stretch;
            }
        }

        /* Animations */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .upload-section {
            animation: slideInUp 0.6s ease-out;
        }

        .vouchers-section {
            animation: slideInUp 0.6s ease-out 0.2s both;
        }

        /* Notificaciones */
        #alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        }

        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border-left: 4px solid #ef4444;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }

        .alert-info {
            background: #e0e7ff;
            color: #3730a3;
            border-left: 4px solid #6366f1;
        }

        .btn-close {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: inherit;
            opacity: 0.7;
        }

        .btn-close:hover {
            opacity: 1;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo-section">
                <div class="header-logo">
                    <i class="fas fa-truck"></i>
                </div>
                <div class="company-info">
                    <h1><?php echo COMPANY_NAME ?? 'Capital Transport LLP'; ?></h1>
                    <p>Report Manager System</p>
                </div>
            </div>
            
            <div class="user-section">
                <div class="user-info">
                    <div class="user-name"><?php echo getUserDisplayName(); ?></div>
                    <div class="user-role"><?php echo getUserRoleText(); ?></div>
                </div>
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Upload Section -->
        <?php if (checkPermission('upload_vouchers')): ?>
        <section class="upload-section">
            <h2 class="section-title">
                <i class="fas fa-cloud-upload-alt"></i>
                Upload Voucher Files
            </h2>
            
            <div class="upload-area" id="uploadArea">
                <div class="file-type-icons">
                    <div class="file-type-icon pdf-icon">
                        <i class="fas fa-file-pdf"></i>
                        <span>PDF</span>
                    </div>
                    <div class="file-type-icon excel-icon">
                        <i class="fas fa-file-excel"></i>
                        <span>Excel</span>
                    </div>
                </div>
                
                <div class="upload-text">Drop your voucher files here</div>
                <div class="upload-subtitle">or click to browse files</div>
                
                <button type="button" class="upload-btn" onclick="document.getElementById('fileInput').click()">
                    <i class="fas fa-folder-open"></i>
                    Choose Files
                </button>
                
                <div class="supported-formats">
                    <span class="format-badge">PDF</span>
                    <span class="format-badge">XLSX</span>
                    <span class="format-badge">XLS</span>
                </div>
            </div>
            
            <input type="file" id="fileInput" class="file-input" accept=".pdf,.xlsx,.xls" multiple>
            
            <!-- Progress Bar -->
            <div class="progress-container" id="progressContainer">
                <div class="progress-header">
                    <div class="progress-file-info">
                        <div class="progress-file-icon" id="progressIcon">
                            <i class="fas fa-file"></i>
                        </div>
                        <span id="progressFileName">Uploading file...</span>
                    </div>
                    <span id="progressPercentage">0%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
                <div class="progress-text">
                    <span id="progressStatus">Preparing upload...</span>
                    <span id="progressSize"></span>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Vouchers Table -->
        <section class="vouchers-section">
            <div class="table-header">
                <h2 class="section-title">
                    <i class="fas fa-table"></i>
                    Uploaded Files
                </h2>
                
                <div class="table-filters">
                    <select class="filter-select" id="fileTypeFilter">
                        <option value="">All Types</option>
                        <option value="pdf">PDF Only</option>
                        <option value="excel">Excel Only</option>
                    </select>
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="uploaded">Uploaded</option>
                        <option value="processing">Processing</option>
                        <option value="processed">Processed</option>
                        <option value="error">Error</option>
                    </select>
                    <button class="refresh-btn" onclick="refreshFileTable()">
                        <i class="fas fa-sync-alt"></i>
                        Refresh
                    </button>
                </div>
            </div>
            
            <div class="table-container">
                <div id="loading" class="loading" style="display: none;">
                    <i class="fas fa-spinner"></i>
                    <p>Loading files...</p>
                </div>
                
                <table class="vouchers-table" id="vouchersTable">
                    <thead>
                        <tr>
                            <th>File Name</th>
                            <th>Type</th>
                            <th>Upload Date</th>
                            <th>Status</th>
                            <th>Quality</th>
                            <th>Companies</th>
                            <th>Total Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="vouchersTableBody">
                        <!-- Data will be loaded via JavaScript -->
                    </tbody>
                </table>
                
                <div id="noData" class="no-data" style="display: none;">
                    <i class="fas fa-inbox"></i>
                    <p>No files uploaded yet</p>
                    <small>Upload your first voucher file to get started</small>
                </div>
            </div>
        </section>
    </main>

    <!-- Container for notifications -->
    <div id="alert-container"></div>

    <!-- JavaScript -->
    <script src="../assets/js/upload-handler.js"></script>
    <script>
        // Variables globales
        let currentFiles = [];
        let uploadHandler;

        // Inicializar cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Inicializando dashboard...');
            
            // Evitar inicialización múltiple
            if (window.dashboardInitialized) {
                console.log('⚠️ Dashboard ya inicializado, saltando...');
                return;
            }
            
            // Inicializar upload handler SOLO UNA VEZ
            if (document.getElementById('uploadArea') && document.getElementById('fileInput')) {
                uploadHandler = new UploadHandler({
                    autoProcess: false,
                    uploadUrl: '../api/upload-file.php',
                    processUrl: '../api/process-file.php',
                    statusUrl: '../api/file-status.php'
                });

                // Event listeners para uploads
                document.addEventListener('upload-success', function(e) {
                    console.log('✅ Upload successful:', e.detail);
                    setTimeout(() => {
                        refreshFileTable();
                    }, 1000);
                });

                console.log('✅ UploadHandler inicializado correctamente');
            }

            // Cargar archivos inicial
            refreshFileTable();

            // Event listeners para filtros
            const typeFilter = document.getElementById('fileTypeFilter');
            const statusFilter = document.getElementById('statusFilter');
            
            if (typeFilter) typeFilter.addEventListener('change', applyFilters);
            if (statusFilter) statusFilter.addEventListener('change', applyFilters);
            
            // Marcar como inicializado
            window.dashboardInitialized = true;
            console.log('✅ Dashboard inicializado completamente');
        });

        // Función para refrescar la tabla de archivos
        async function refreshFileTable() {
            const loading = document.getElementById('loading');
            const tableBody = document.getElementById('vouchersTableBody');
            const noData = document.getElementById('noData');
            const table = document.getElementById('vouchersTable');

            try {
                if (loading) loading.style.display = 'block';
                if (table) table.style.display = 'none';
                if (noData) noData.style.display = 'none';

                console.log('📊 Cargando lista de archivos...');
                const response = await fetch('../api/file-status.php?action=list');
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                console.log('📋 Respuesta de API:', result);

                if (result.success) {
                    currentFiles = result.data.files || [];
                    renderFileTable(currentFiles);
                    
                    if (currentFiles.length === 0) {
                        if (table) table.style.display = 'none';
                        if (noData) noData.style.display = 'block';
                    } else {
                        if (table) table.style.display = 'table';
                        if (noData) noData.style.display = 'none';
                    }
                    
                    console.log(`✅ Cargados ${currentFiles.length} archivos`);
                } else {
                    throw new Error(result.error || 'Error loading files');
                }

            } catch (error) {
                console.error('❌ Error refreshing file table:', error);
                showNotification('Error cargando archivos: ' + error.message, 'error');
                if (noData) noData.style.display = 'block';
                if (table) table.style.display = 'none';
            } finally {
                if (loading) loading.style.display = 'none';
            }
        }

        // Función para renderizar la tabla
        function renderFileTable(files) {
            const tableBody = document.getElementById('vouchersTableBody');
            
            if (!tableBody) {
                console.error('❌ No se encontró vouchersTableBody');
                return;
            }
            
            if (!files || files.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 2rem; color: #666;">No hay archivos para mostrar</td></tr>';
                return;
            }

            tableBody.innerHTML = files.map(file => {
                const safeFilename = escapeHtml(file.original_filename || 'Sin nombre');
                const statusText = escapeHtml(file.status_text || file.status || 'Desconocido');
                const totalAmount = formatCurrency(file.total_amount || 0);
                const qualityPercent = file.quality_percentage || 0;
                const uploadDate = file.upload_date_formatted || 'N/A';
                
                return `
                    <tr data-file-id="${file.id}">
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-file-${file.file_type === 'pdf' ? 'pdf' : 'excel'}" 
                                   style="color: ${file.file_type === 'pdf' ? '#dc2626' : '#10b981'};"></i>
                                ${safeFilename}
                            </div>
                        </td>
                        <td>
                            <span class="file-type-indicator file-type-${file.file_type}">
                                <i class="fas fa-file-${file.file_type === 'pdf' ? 'pdf' : 'excel'}"></i>
                                ${file.file_type ? file.file_type.toUpperCase() : 'N/A'}
                            </span>
                        </td>
                        <td>${uploadDate}</td>
                        <td>
                            <span class="status-badge status-${file.status}">${statusText}</span>
                        </td>
                        <td>
                            <div class="quality-indicator">
                                <div class="quality-bar">
                                    <div class="quality-fill ${getQualityClass(qualityPercent)}" 
                                         style="width: ${qualityPercent}%;"></div>
                                </div>
                                <span class="quality-text">${qualityPercent}%</span>
                            </div>
                        </td>
                        <td>${file.total_companies || 0}</td>
                        <td class="amount">${totalAmount}</td>
                        <td>
                            <div class="actions">
                                <button class="action-btn btn-view" onclick="viewFile(${file.id})" title="Ver Detalles">
                                    <i class="fas fa-eye"></i>
                                </button>
                                ${file.status === 'uploaded' || file.status === 'error' ? 
                                    `<button class="action-btn btn-process" onclick="processFile(${file.id})" title="Procesar Archivo">
                                        <i class="fas fa-play"></i>
                                    </button>` : ''
                                }
                                ${file.status === 'processed' ? 
                                    `<button class="action-btn btn-download" onclick="downloadReports(${file.id})" title="Descargar Reportes">
                                        <i class="fas fa-download"></i>
                                    </button>` : ''
                                }
                                <button class="action-btn btn-delete" onclick="deleteFile(${file.id})" title="Eliminar">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
            
            console.log(`🎨 Renderizados ${files.length} archivos en la tabla`);
        }

        // Función para aplicar filtros
        function applyFilters() {
            const typeFilter = document.getElementById('fileTypeFilter');
            const statusFilter = document.getElementById('statusFilter');
            
            if (!typeFilter || !statusFilter) return;
            
            const typeValue = typeFilter.value;
            const statusValue = statusFilter.value;

            let filteredFiles = [...currentFiles];

            if (typeValue) {
                filteredFiles = filteredFiles.filter(file => file.file_type === typeValue);
            }

            if (statusValue) {
                filteredFiles = filteredFiles.filter(file => file.status === statusValue);
            }

            renderFileTable(filteredFiles);
            console.log(`🔍 Filtros aplicados: ${filteredFiles.length} archivos mostrados`);
        }

        // Funciones de acciones
        async function processFile(voucherId) {
            if (!confirm('¿Procesar este archivo y extraer datos de empresas?')) {
                return;
            }

            try {
                console.log(`⚙️ Iniciando procesamiento del archivo ${voucherId}`);
                showNotification('Iniciando procesamiento...', 'info');

                const response = await fetch('../api/process-file.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ voucher_id: voucherId })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();
                console.log('📊 Resultado del procesamiento:', result);

                if (result.success) {
                    showNotification('Archivo procesado exitosamente', 'success');
                    refreshFileTable();
                } else {
                    throw new Error(result.error || 'Error en procesamiento');
                }

            } catch (error) {
                console.error('❌ Error processing file:', error);
                showNotification('Error procesando archivo: ' + error.message, 'error');
            }
        }

        function viewFile(voucherId) {
            console.log(`👁️ Abriendo vista de archivo ${voucherId}`);
            const url = `view-file.php?id=${voucherId}`;
            window.open(url, '_blank');
        }

        function downloadReports(voucherId) {
            console.log(`⬇️ Descargando reportes del archivo ${voucherId}`);
            const url = `download-reports.php?voucher_id=${voucherId}`;
            window.open(url, '_blank');
        }

        async function deleteFile(voucherId) {
            if (!confirm('¿Estás seguro de que quieres eliminar este archivo? Esta acción no se puede deshacer.')) {
                return;
            }

            try {
                console.log(`🗑️ Eliminando archivo ${voucherId}`);
                showNotification('Eliminando archivo...', 'info');

                const response = await fetch('../api/delete-file.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ voucher_id: voucherId })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();
                console.log('🗑️ Resultado de eliminación:', result);

                if (result.success) {
                    showNotification('Archivo eliminado correctamente', 'success');
                    refreshFileTable();
                } else {
                    throw new Error(result.error || 'Error eliminando archivo');
                }

            } catch (error) {
                console.error('❌ Error deleting file:', error);
                showNotification('Error eliminando archivo: ' + error.message, 'error');
            }
        }

        // Funciones de utilidad
        function getQualityClass(percentage) {
            if (percentage >= 80) return 'quality-high';
            if (percentage >= 60) return 'quality-medium';
            return 'quality-low';
        }

        function formatCurrency(amount) {
            return new Intl.NumberFormat('es-MX', {
                style: 'currency',
                currency: 'MXN',
                minimumFractionDigits: 2
            }).format(amount || 0);
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showNotification(message, type = 'info') {
            console.log(`📢 ${type.toUpperCase()}: ${message}`);
            
            const className = {
                error: 'alert-error',
                warning: 'alert-warning',
                info: 'alert-info',
                success: 'alert-success'
            }[type] || 'alert-info';

            const alert = document.createElement('div');
            alert.className = `alert ${className}`;
            alert.innerHTML = `
                <span>${escapeHtml(message)}</span>
                <button type="button" class="btn-close" onclick="this.parentElement.remove()">×</button>
            `;

            let alertContainer = document.getElementById('alert-container');
            if (!alertContainer) {
                alertContainer = document.createElement('div');
                alertContainer.id = 'alert-container';
                alertContainer.style.position = 'fixed';
                alertContainer.style.top = '20px';
                alertContainer.style.right = '20px';
                alertContainer.style.zIndex = '9999';
                alertContainer.style.maxWidth = '400px';
                document.body.appendChild(alertContainer);
            }

            alertContainer.appendChild(alert);

            // Auto-remove después de 5 segundos
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 5000);
        }

        // Hacer funciones globales para acceso externo
        window.refreshFileTable = refreshFileTable;
        window.processFile = processFile;
        window.viewFile = viewFile;
        window.downloadReports = downloadReports;
        window.deleteFile = deleteFile;
        
        console.log('🎯 Funciones globales registradas');
    </script>
</body>
</html>