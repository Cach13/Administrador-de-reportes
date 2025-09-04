<?php
/**
 * Dashboard Principal - Limpio y Enfocado
 * Transport Management System
 */

// Verificar autenticación
require_once '../includes/auth-check.php';
require_once '../classes/Database.php';

$db = Database::getInstance();

// Obtener estadísticas del sistema
try {
    $stats = [];
    
    // Estadísticas básicas - CORREGIDO: usar fetch() en lugar de fetchValue()
    $companies_result = $db->fetch("SELECT COUNT(*) as total FROM companies WHERE active = 1");
    $stats['total_companies'] = $companies_result['total'] ?? 0;
    
    $vouchers_result = $db->fetch("SELECT COUNT(*) as total FROM vouchers");
    $stats['total_vouchers'] = $vouchers_result['total'] ?? 0;
    
    $trips_result = $db->fetch("SELECT COUNT(*) as total FROM trips");
    $stats['total_trips'] = $trips_result['total'] ?? 0;
    
    $pending_result = $db->fetch("SELECT COUNT(*) as total FROM vouchers WHERE status = 'pending'");
    $stats['pending_vouchers'] = $pending_result['total'] ?? 0;
    
    // Estadísticas financieras - CORREGIDO: usar fetch() en lugar de fetchValue()
    $amount_result = $db->fetch("SELECT SUM(total_amount) as total FROM trips");
    $stats['total_amount'] = $amount_result['total'] ?? 0;
    
    $month_result = $db->fetch("SELECT SUM(total_amount) as total FROM trips WHERE MONTH(trip_date) = MONTH(CURRENT_DATE()) AND YEAR(trip_date) = YEAR(CURRENT_DATE())");
    $stats['this_month'] = $month_result['total'] ?? 0;
    
    // Últimos vouchers procesados
    $recent_vouchers = $db->fetchAll("
        SELECT v.*, 
               DATE_FORMAT(v.processed_at, '%d/%m/%Y %H:%i') as formatted_date,
               COUNT(t.id) as trip_count,
               SUM(t.total_amount) as total_value
        FROM vouchers v 
        LEFT JOIN trips t ON v.id = t.voucher_id 
        WHERE v.status = 'processed' 
        GROUP BY v.id 
        ORDER BY v.processed_at DESC 
        LIMIT 5
    ");
    
} catch (Exception $e) {
    $stats = array_fill_keys(['total_companies', 'total_vouchers', 'total_trips', 'pending_vouchers', 'total_amount', 'this_month'], 0);
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
    <title><?php echo $page_title; ?> - Transport Management</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Dashboard CSS -->
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-red: #dc2626;
            --dark-gray: #2c2c2c;
            --light-gray: #f5f5f5;
            --white: #ffffff;
            --text-muted: #6b7280;
            --border-light: #e5e7eb;
            --success-green: #059669;
            --warning-yellow: #d97706;
        }

        body {
            background: var(--light-gray);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .dashboard-container {
            min-height: 100vh;
            padding: 0;
        }

        /* HEADER */
        .dashboard-header {
            background: linear-gradient(135deg, var(--primary-red), #b91c1c);
            color: white;
            padding: 2rem 0;
            box-shadow: 0 4px 20px rgba(220, 38, 38, 0.2);
        }

        .welcome-section h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .welcome-section p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        /* STATS CARDS */
        .stats-row {
            margin: -3rem 0 2rem 0;
            position: relative;
            z-index: 10;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            border: 2px solid transparent;
            transition: all 0.3s ease;
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.companies { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .stat-icon.vouchers { background: linear-gradient(135deg, #059669, #047857); }
        .stat-icon.trips { background: linear-gradient(135deg, #d97706, #c2410c); }
        .stat-icon.amount { background: linear-gradient(135deg, #7c3aed, #6d28d9); }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--dark-gray);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--text-muted);
            font-weight: 600;
        }

        /* MAIN CONTENT */
        .main-content {
            padding: 2rem 0;
        }

        .action-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        /* UPLOAD CARD */
        .upload-card {
            background: white;
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }

        .upload-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .upload-header h3 {
            color: var(--dark-gray);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .upload-zone {
            border: 3px dashed #cbd5e1;
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
        }

        .upload-subtext {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .file-input {
            display: none;
        }

        .upload-btn {
            background: var(--primary-red);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .upload-btn:hover {
            background: #b91c1c;
            transform: translateY(-2px);
        }

        /* QUICK ACTIONS */
        .quick-actions {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }

        .quick-actions h4 {
            color: var(--dark-gray);
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 1rem;
            width: 100%;
            padding: 1rem;
            background: white;
            border: 2px solid var(--border-light);
            border-radius: 12px;
            text-decoration: none;
            color: var(--dark-gray);
            font-weight: 600;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            border-color: var(--primary-red);
            color: var(--primary-red);
            transform: translateX(4px);
        }

        .action-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .action-icon.companies { background: var(--primary-red); }
        .action-icon.reports { background: var(--success-green); }
        .action-icon.vouchers { background: var(--warning-yellow); }
        .action-icon.settings { background: var(--text-muted); }

        /* RECENT ACTIVITY */
        .recent-activity {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            margin-top: 2rem;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid var(--border-light);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-red);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 0.25rem;
        }

        .activity-meta {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .action-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-row .row > div {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <div class="dashboard-header">
            <div class="container">
                <div class="welcome-section">
                    <h1><i class="fas fa-tachometer-alt"></i> Dashboard Principal</h1>
                    <p>Bienvenido, <?php echo htmlspecialchars($full_name ?: $username); ?> • Sistema de Gestión de Transporte</p>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="container">
            <div class="stats-row">
                <div class="row">
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-icon companies">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="stat-number"><?php echo number_format($stats['total_companies']); ?></div>
                            <div class="stat-label">Empresas Activas</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-icon vouchers">
                                <i class="fas fa-file-invoice"></i>
                            </div>
                            <div class="stat-number"><?php echo number_format($stats['total_vouchers']); ?></div>
                            <div class="stat-label">Vouchers Procesados</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-icon trips">
                                <i class="fas fa-truck"></i>
                            </div>
                            <div class="stat-number"><?php echo number_format($stats['total_trips']); ?></div>
                            <div class="stat-label">Viajes Registrados</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-icon amount">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="stat-number">$<?php echo number_format($stats['this_month']); ?></div>
                            <div class="stat-label">Este Mes</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="main-content">
                <div class="action-grid">
                    <!-- Upload Section -->
                    <div class="upload-card">
                        <div class="upload-header">
                            <h3><i class="fas fa-cloud-upload-alt"></i> Subir Voucher Martin Marieta</h3>
                            <p class="text-muted">Arrastra tu archivo PDF o haz clic para seleccionar</p>
                        </div>
                        
                        <form id="uploadForm" enctype="multipart/form-data">
                            <div class="upload-zone" id="uploadZone">
                                <div class="upload-icon">
                                    <i class="fas fa-file-pdf"></i>
                                </div>
                                <div class="upload-text">Arrastra tu archivo PDF aquí</div>
                                <div class="upload-subtext">o haz clic para seleccionar</div>
                                <input type="file" id="fileInput" name="voucher_file" class="file-input" accept=".pdf" required>
                                <button type="button" class="upload-btn" onclick="document.getElementById('fileInput').click()">
                                    <i class="fas fa-folder-open"></i> Seleccionar Archivo
                                </button>
                            </div>
                            
                            <div id="uploadProgress" class="mt-3" style="display: none;">
                                <div class="progress">
                                    <div class="progress-bar bg-danger" role="progressbar" style="width: 0%"></div>
                                </div>
                                <div class="text-center mt-2">
                                    <small class="text-muted">Subiendo archivo...</small>
                                </div>
                            </div>
                            
                            <div id="uploadResult" class="mt-3"></div>
                        </form>
                    </div>

                    <!-- Quick Actions -->
                    <div class="quick-actions">
                        <h4><i class="fas fa-bolt"></i> Acciones Rápidas</h4>
                        
                        <a href="companies.php" class="action-btn">
                            <div class="action-icon companies">
                                <i class="fas fa-building"></i>
                            </div>
                            <div>
                                <div>Gestionar Empresas</div>
                                <small class="text-muted">Crear y editar empresas</small>
                            </div>
                        </a>
                        
                        <a href="reports.php" class="action-btn">
                            <div class="action-icon reports">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div>
                                <div>Generar Reportes</div>
                                <small class="text-muted">Capital Transport reports</small>
                            </div>
                        </a>
                        
                        <a href="vouchers.php" class="action-btn">
                            <div class="action-icon vouchers">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </div>
                            <div>
                                <div>Ver Vouchers</div>
                                <small class="text-muted">Historial y gestión</small>
                            </div>
                        </a>
                        
                        <?php if ($is_admin): ?>
                        <a href="settings.php" class="action-btn">
                            <div class="action-icon settings">
                                <i class="fas fa-cog"></i>
                            </div>
                            <div>
                                <div>Configuración</div>
                                <small class="text-muted">Ajustes del sistema</small>
                            </div>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Activity -->
                <?php if (!empty($recent_vouchers)): ?>
                <div class="recent-activity">
                    <h4><i class="fas fa-history"></i> Últimos Vouchers Procesados</h4>
                    
                    <?php foreach ($recent_vouchers as $voucher): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="fas fa-file-check"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">
                                <?php echo htmlspecialchars($voucher['original_filename']); ?>
                            </div>
                            <div class="activity-meta">
                                <i class="fas fa-calendar"></i> <?php echo $voucher['formatted_date']; ?> • 
                                <i class="fas fa-truck"></i> <?php echo $voucher['trip_count']; ?> viajes • 
                                <i class="fas fa-dollar-sign"></i> $<?php echo number_format($voucher['total_value'] ?? 0, 2); ?>
                            </div>
                        </div>
                        <div>
                            <a href="view-data.php?id=<?php echo $voucher['id']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye"></i> Ver
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Pending Items Alert -->
                <?php if ($stats['pending_vouchers'] > 0): ?>
                <div class="alert alert-warning mt-3" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Atención:</strong> Tienes <?php echo $stats['pending_vouchers']; ?> voucher(s) pendiente(s) de procesar.
                    <a href="process.php" class="alert-link">Procesar ahora</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div id="uploadToast" class="toast" role="alert">
            <div class="toast-header">
                <strong class="me-auto">Upload Status</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                <!-- Mensaje dinámico -->
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const uploadZone = document.getElementById('uploadZone');
            const fileInput = document.getElementById('fileInput');
            const uploadForm = document.getElementById('uploadForm');
            const uploadProgress = document.getElementById('uploadProgress');
            const uploadResult = document.getElementById('uploadResult');

            // Upload zone click handler
            uploadZone.addEventListener('click', function(e) {
                if (e.target.type !== 'file') {
                    fileInput.click();
                }
            });

            // Drag and drop handlers
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
                    handleFileUpload(files[0]);
                }
            });

            // File input change handler
            fileInput.addEventListener('change', function(e) {
                if (e.target.files.length > 0) {
                    handleFileUpload(e.target.files[0]);
                }
            });

            function handleFileUpload(file) {
                // Validar tipo de archivo
                if (!file.type.includes('pdf')) {
                    showToast('Error: Solo se permiten archivos PDF', 'error');
                    return;
                }

                // Validar tamaño (max 20MB)
                if (file.size > 20 * 1024 * 1024) {
                    showToast('Error: El archivo es demasiado grande (máximo 20MB)', 'error');
                    return;
                }

                const formData = new FormData();
                formData.append('voucher_file', file);

                // Mostrar progreso
                uploadProgress.style.display = 'block';
                uploadResult.innerHTML = '';

                // Realizar upload
                fetch('../api/upload-file.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    uploadProgress.style.display = 'none';
                    
                    if (data.success) {
                        showToast(`Archivo subido exitosamente: ${file.name}`, 'success');
                        uploadResult.innerHTML = `
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i>
                                <strong>¡Éxito!</strong> Archivo subido correctamente.
                                <a href="process.php?voucher_id=${data.voucher_id}" class="alert-link">Procesar ahora</a>
                            </div>
                        `;
                        
                        // Limpiar input
                        fileInput.value = '';
                        
                        // Actualizar stats después de 2 segundos
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        showToast(`Error: ${data.message}`, 'error');
                        uploadResult.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Error:</strong> ${data.message}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    uploadProgress.style.display = 'none';
                    showToast('Error de conexión', 'error');
                    console.error('Upload error:', error);
                });
            }

            function showToast(message, type = 'info') {
                const toast = document.getElementById('uploadToast');
                const toastBody = toast.querySelector('.toast-body');
                
                toastBody.innerHTML = `
                    <i class="fas fa-${type === 'success' ? 'check-circle text-success' : 'exclamation-triangle text-danger'}"></i>
                    ${message}
                `;
                
                const bsToast = new bootstrap.Toast(toast);
                bsToast.show();
            }
        });
    </script>
</body>
</html>