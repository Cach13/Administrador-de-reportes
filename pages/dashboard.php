<?php
/**
 * Dashboard Principal - Versión Simplificada Capital Transport
 * Transport Management System
 */

// Verificar autenticación
require_once '../includes/auth-check.php';
require_once '../classes/Database.php';

$db = Database::getInstance();

// Obtener solo lo esencial
try {
    // Solo vouchers pendientes para la alerta
    $pending_result = $db->fetch("SELECT COUNT(*) as total FROM vouchers WHERE status = 'uploaded'");
    $pending_vouchers = $pending_result['total'] ?? 0;
    
    // Últimos vouchers procesados
    $recent_vouchers = $db->fetchAll("
        SELECT v.*, 
               u.full_name as uploaded_by_name,
               COUNT(t.id) as trip_count,
               SUM(t.amount) as total_value
        FROM vouchers v 
        LEFT JOIN users u ON v.uploaded_by = u.id
        LEFT JOIN trips t ON v.id = t.voucher_id 
        WHERE v.status = 'processed' 
        GROUP BY v.id 
        ORDER BY v.upload_date DESC 
        LIMIT 8
    ");
    
} catch (Exception $e) {
    $pending_vouchers = 0;
    $recent_vouchers = [];
    error_log("Dashboard stats error: " . $e->getMessage());
}

$page_title = "Dashboard Principal";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Capital Transport</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* PALETA DE COLORES CAPITAL TRANSPORT */
        :root {
            --primary-red: #dc2626;
            --dark-gray: #2c2c2c;
            --darker-gray: #1a1a1a;
            --light-gray: #f5f5f5;
            --success-green: #10b981;
            --warning-orange: #f59e0b;
            --error-red: #ef4444;
            --info-blue: #3b82f6;
            --white: #ffffff;
            --border-light: #e5e5e5;
            --text-muted: #6b7280;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--light-gray);
            color: var(--dark-gray);
            line-height: 1.6;
        }

        /* HEADER */
        .header {
            background: linear-gradient(135deg, var(--dark-gray) 0%, var(--darker-gray) 100%);
            color: var(--white);
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
            max-width: 1200px;
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
            background: var(--primary-red);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .header-logo i {
            color: var(--white);
            font-size: 1.2rem;
        }

        .company-info h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-red);
        }

        .company-info p {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .nav-links {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }

        .nav-link {
            color: var(--white);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--white);
            text-decoration: none;
        }

        .nav-link.active {
            background: var(--primary-red);
        }

        .logout-btn {
            background: var(--primary-red);
            color: var(--white);
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
            color: var(--white);
            text-decoration: none;
        }

        /* MAIN CONTENT */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 600;
            color: var(--dark-gray);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-title i {
            color: var(--primary-red);
        }

        /* SECTIONS */
        .section {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .section-header {
            background: var(--primary-red);
            color: var(--white);
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-content {
            padding: 2rem;
        }

        /* UPLOAD SECTION */
        .upload-zone {
            border: 3px dashed var(--border-light);
            border-radius: 12px;
            padding: 3rem 2rem;
            text-align: center;
            background: #fafafa;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .upload-zone:hover {
            border-color: var(--primary-red);
            background: #fef2f2;
        }

        .upload-zone.dragover {
            border-color: var(--primary-red);
            background: #fef2f2;
            transform: scale(1.02);
        }

        .upload-icon {
            font-size: 3rem;
            color: var(--primary-red);
            margin-bottom: 1rem;
        }

        .upload-text {
            font-size: 1.1rem;
            color: var(--dark-gray);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .upload-subtext {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .file-input {
            display: none;
        }

        /* BUTTONS */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: var(--primary-red);
            color: var(--white);
        }

        .btn-primary:hover {
            background: #b91c1c;
            transform: translateY(-1px);
            color: var(--white);
            text-decoration: none;
        }

        .btn-sm {
            padding: 0.35rem 0.75rem;
            font-size: 0.85rem;
        }

        /* VOUCHERS TABLE */
        .vouchers-table {
            width: 100%;
            border-collapse: collapse;
        }

        .vouchers-table th {
            background: var(--dark-gray);
            color: var(--white);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .vouchers-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
        }

        .vouchers-table tr:hover {
            background: #f8f9fa;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .file-icon {
            width: 35px;
            height: 35px;
            background: var(--primary-red);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            flex-shrink: 0;
        }

        .file-details h4 {
            font-weight: 600;
            margin-bottom: 0.25rem;
            font-size: 0.95rem;
        }

        .file-details p {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin: 0;
        }

        .status-badge {
            background: var(--success-green);
            color: var(--white);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .metric-badge {
            background: var(--info-blue);
            color: var(--white);
            padding: 0.25rem 0.6rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--border-light);
        }

        .empty-state h3 {
            margin-bottom: 0.5rem;
            color: var(--dark-gray);
        }

        /* ALERTS */
        .alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            max-width: 400px;
        }

        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            animation: slideIn 0.3s ease;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success-green);
        }

        .alert-error {
            background: #fee2e2;
            color: var(--error-red);
            border-left: 4px solid var(--error-red);
        }

        /* PROGRESS */
        .progress-container {
            margin-top: 1rem;
            display: none;
        }

        .progress {
            width: 100%;
            height: 8px;
            background: var(--border-light);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: var(--primary-red);
            width: 0%;
            transition: width 0.3s ease;
        }

        .progress-text {
            text-align: center;
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        /* PENDING ALERT */
        .pending-alert {
            background: linear-gradient(135deg, var(--warning-orange), #ea580c);
            color: var(--white);
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 2rem;
        }

        .pending-alert i {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .pending-alert h3 {
            margin-bottom: 0.5rem;
        }

        .pending-alert p {
            margin-bottom: 1.5rem;
        }

        .pending-alert .btn {
            background: var(--white);
            color: var(--warning-orange);
            font-weight: 600;
        }

        .pending-alert .btn:hover {
            background: #f9fafb;
            color: var(--warning-orange);
        }

        /* ANIMATIONS */
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .loading {
            animation: pulse 1.5s ease-in-out infinite;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }

            .main-content {
                padding: 1rem;
            }

            .page-header {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }

            .vouchers-table {
                font-size: 0.8rem;
            }

            .vouchers-table th,
            .vouchers-table td {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Alert Container -->
    <div class="alert-container" id="alertContainer"></div>

    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo-section">
                <div class="header-logo">
                    <i class="fas fa-truck"></i>
                </div>
                <div class="company-info">
                    <h1>Capital Transport LLP</h1>
                    <p>Management Dashboard</p>
                </div>
            </div>
            <div class="nav-links">
                <a href="dashboard.php" class="nav-link active">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
                <a href="view-extracted-data.php" class="nav-link">
                    <i class="fas fa-database"></i>
                    Data
                </a>
                <a href="companies.php" class="nav-link">
                    <i class="fas fa-building"></i>
                    Companies
                </a>
                <a href="reports.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i>
                    Reports
                </a>
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-tachometer-alt"></i>
                Dashboard Principal
            </h1>
        </div>

        <!-- Pending Vouchers Alert -->
        <?php if ($pending_vouchers > 0): ?>
        <div class="pending-alert">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Atención: Vouchers Pendientes</h3>
            <p>Tienes <?php echo $pending_vouchers; ?> voucher(s) pendiente(s) de procesar</p>
            <a href="view-extracted-data.php" class="btn">
                <i class="fas fa-cog"></i>
                Procesar Ahora
            </a>
        </div>
        <?php endif; ?>

        <!-- Upload Section -->
        <div class="section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-cloud-upload-alt"></i>
                    Subir Voucher Martin Marieta
                </div>
                <?php if ($pending_vouchers > 0): ?>
                <div style="background: var(--warning-orange); color: var(--white); padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.9rem; font-weight: 600;">
                    <?php echo $pending_vouchers; ?> pendientes
                </div>
                <?php endif; ?>
            </div>
            <div class="section-content">
                <form id="uploadForm" enctype="multipart/form-data">
                    <div class="upload-zone" id="uploadZone">
                        <div class="upload-icon">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <div class="upload-text">Arrastra tu archivo PDF aquí</div>
                        <div class="upload-subtext">o haz clic para seleccionar (máx 20MB)</div>
                        <input type="file" id="fileInput" name="voucher_file" class="file-input" accept=".pdf" required>
                    </div>
                    
                    <div id="progressContainer" class="progress-container">
                        <div class="progress">
                            <div class="progress-bar" id="progressBar"></div>
                        </div>
                        <div class="progress-text" id="progressText">Subiendo archivo...</div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Recent Vouchers -->
        <?php if (!empty($recent_vouchers)): ?>
        <div class="section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-history"></i>
                    Vouchers Procesados Recientes
                </div>
                <div style="background: var(--white); color: var(--primary-red); padding: 0.5rem 1rem; border-radius: 20px; font-weight: 600;">
                    <?php echo count($recent_vouchers); ?> vouchers
                </div>
            </div>
            <div class="section-content">
                <table class="vouchers-table">
                    <thead>
                        <tr>
                            <th>Archivo</th>
                            <th>Estado</th>
                            <th>Viajes</th>
                            <th>Total</th>
                            <th>Subido por</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_vouchers as $voucher): ?>
                        <tr>
                            <td>
                                <div class="file-info">
                                    <div class="file-icon">
                                        <i class="fas fa-file-pdf"></i>
                                    </div>
                                    <div class="file-details">
                                        <h4><?php echo htmlspecialchars($voucher['voucher_number']); ?></h4>
                                        <p><?php echo htmlspecialchars($voucher['original_filename']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge">Procesado</span>
                            </td>
                            <td>
                                <span class="metric-badge"><?php echo $voucher['trip_count']; ?> viajes</span>
                            </td>
                            <td>
                                <strong>$<?php echo number_format($voucher['total_value'] ?? 0, 2); ?></strong>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($voucher['uploaded_by_name']); ?>
                                <br><small style="color: var(--text-muted);"><?php echo date('d/m/Y', strtotime($voucher['upload_date'])); ?></small>
                            </td>
                            <td>
                                <a href="view-extracted-data.php?voucher=<?php echo $voucher['id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i>
                                    Ver
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Empty State -->
        <?php if (empty($recent_vouchers)): ?>
        <div class="section">
            <div class="section-content">
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No hay vouchers procesados</h3>
                    <p>Sube tu primer archivo PDF para comenzar</p>
                    <button class="btn btn-primary" onclick="document.getElementById('fileInput').click()">
                        <i class="fas fa-plus"></i>
                        Subir Archivo
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- JavaScript para Upload -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const uploadZone = document.getElementById('uploadZone');
            const fileInput = document.getElementById('fileInput');
            const uploadForm = document.getElementById('uploadForm');
            const progressContainer = document.getElementById('progressContainer');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');

            // Click para abrir file picker
            uploadZone.addEventListener('click', () => {
                fileInput.click();
            });

            // Drag & Drop handlers
            uploadZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadZone.classList.add('dragover');
            });

            uploadZone.addEventListener('dragleave', () => {
                uploadZone.classList.remove('dragover');
            });

            uploadZone.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadZone.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    handleFileUpload();
                }
            });

            // Cambio de archivo
            fileInput.addEventListener('change', () => {
                if (fileInput.files.length > 0) {
                    handleFileUpload();
                }
            });

            function handleFileUpload() {
                const file = fileInput.files[0];
                
                // Validaciones
                if (!file.type.includes('pdf')) {
                    showAlert('error', 'Solo se permiten archivos PDF');
                    return;
                }
                
                if (file.size > 20 * 1024 * 1024) {
                    showAlert('error', 'El archivo es muy grande (máx 20MB)');
                    return;
                }

                // Preparar upload
                const formData = new FormData();
                formData.append('voucher_file', file);

                // Mostrar progress
                progressContainer.style.display = 'block';
                progressBar.style.width = '0%';
                progressText.textContent = 'Subiendo archivo...';

                // Enviar archivo
                const xhr = new XMLHttpRequest();
                
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        progressBar.style.width = percentComplete + '%';
                        progressText.textContent = `Subiendo... ${Math.round(percentComplete)}%`;
                    }
                });

                xhr.addEventListener('load', () => {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                progressText.textContent = 'Archivo subido correctamente';
                                showAlert('success', response.message);
                                
                                // Recargar página después de 2 segundos
                                setTimeout(() => {
                                    window.location.reload();
                                }, 2000);
                            } else {
                                showAlert('error', response.message || 'Error subiendo archivo');
                            }
                        } catch (e) {
                            showAlert('error', 'Error procesando respuesta del servidor');
                        }
                    } else {
                        showAlert('error', 'Error en la conexión al servidor');
                    }
                    
                    // Ocultar progress después de un momento
                    setTimeout(() => {
                        progressContainer.style.display = 'none';
                        fileInput.value = '';
                    }, 3000);
                });

                xhr.addEventListener('error', () => {
                    showAlert('error', 'Error de conexión');
                    progressContainer.style.display = 'none';
                    fileInput.value = '';
                });

                xhr.open('POST', '../api/upload-file.php');
                xhr.send(formData);
            }

            function showAlert(type, message) {
                const alertContainer = document.getElementById('alertContainer');
                const alert = document.createElement('div');
                alert.className = `alert alert-${type}`;
                alert.innerHTML = `
                    <span>${message}</span>
                    <button onclick="this.parentElement.remove()" style="background: none; border: none; color: inherit; cursor: pointer; font-size: 1.2rem;">&times;</button>
                `;
                
                alertContainer.appendChild(alert);
                
                // Auto-remove después de 5 segundos
                setTimeout(() => {
                    if (alert.parentElement) {
                        alert.remove();
                    }
                }, 5000);
            }
        });
    </script>
</body>
</html>