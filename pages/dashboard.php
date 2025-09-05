<?php
/**
 * Dashboard Principal - Estilo actualizado Capital Transport
 * Transport Management System
 */

// Verificar autenticación
require_once '../includes/auth-check.php';
require_once '../classes/Database.php';

$db = Database::getInstance();

// Obtener estadísticas del sistema
try {
    $stats = [];
    
    // Estadísticas básicas
    $companies_result = $db->fetch("SELECT COUNT(*) as total FROM companies WHERE is_active = 1");
    $stats['total_companies'] = $companies_result['total'] ?? 0;
    
    $vouchers_result = $db->fetch("SELECT COUNT(*) as total FROM vouchers");
    $stats['total_vouchers'] = $vouchers_result['total'] ?? 0;
    
    $trips_result = $db->fetch("SELECT COUNT(*) as total FROM trips");
    $stats['total_trips'] = $trips_result['total'] ?? 0;
    
    $pending_result = $db->fetch("SELECT COUNT(*) as total FROM vouchers WHERE status = 'uploaded'");
    $stats['pending_vouchers'] = $pending_result['total'] ?? 0;
    
    // Estadísticas financieras
    $amount_result = $db->fetch("SELECT SUM(amount) as total FROM trips");
    $stats['total_amount'] = $amount_result['total'] ?? 0;
    
    $month_result = $db->fetch("SELECT SUM(amount) as total FROM trips WHERE MONTH(trip_date) = MONTH(CURRENT_DATE()) AND YEAR(trip_date) = YEAR(CURRENT_DATE())");
    $stats['this_month'] = $month_result['total'] ?? 0;
    
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
            max-width: 1400px;
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

        .page-actions {
            display: flex;
            gap: 1rem;
        }

        /* STATS CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 2px solid transparent;
            transition: all 0.3s ease;
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
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

        .stat-icon.companies { background: linear-gradient(135deg, var(--info-blue), #2563eb); }
        .stat-icon.vouchers { background: linear-gradient(135deg, var(--success-green), #047857); }
        .stat-icon.trips { background: linear-gradient(135deg, var(--warning-orange), #c2410c); }
        .stat-icon.amount { background: linear-gradient(135deg, var(--primary-red), #b91c1c); }

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

        .btn-success {
            background: var(--success-green);
            color: var(--white);
        }

        .btn-success:hover {
            background: #059669;
            color: var(--white);
            text-decoration: none;
        }

        .btn-secondary {
            background: var(--text-muted);
            color: var(--white);
        }

        .btn-secondary:hover {
            background: #4b5563;
            color: var(--white);
            text-decoration: none;
        }

        .btn-sm {
            padding: 0.35rem 0.75rem;
            font-size: 0.85rem;
        }

        /* QUICK ACTIONS */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .action-card {
            background: var(--white);
            border: 2px solid var(--border-light);
            border-radius: 12px;
            padding: 1.5rem;
            text-decoration: none;
            color: var(--dark-gray);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .action-card:hover {
            border-color: var(--primary-red);
            color: var(--primary-red);
            transform: translateY(-2px);
            text-decoration: none;
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
            flex-shrink: 0;
        }

        .action-icon.companies { background: var(--info-blue); }
        .action-icon.reports { background: var(--success-green); }
        .action-icon.vouchers { background: var(--warning-orange); }
        .action-icon.data { background: var(--primary-red); }

        .action-content h4 {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .action-content p {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin: 0;
        }

        /* RECENT ACTIVITY */
        .recent-table {
            width: 100%;
            border-collapse: collapse;
        }

        .recent-table th {
            background: var(--dark-gray);
            color: var(--white);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .recent-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
        }

        .recent-table tr:hover {
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
            padding: 3rem 2rem;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--border-light);
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

            .page-actions {
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .actions-grid {
                grid-template-columns: 1fr;
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
            <div class="page-actions">
                <button class="btn btn-secondary" onclick="refreshStats()">
                    <i class="fas fa-sync"></i>
                    Refresh
                </button>
                <a href="view-extracted-data.php" class="btn btn-primary">
                    <i class="fas fa-database"></i>
                    Ver Datos
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon companies">
                    <i class="fas fa-building"></i>
                </div>
                <div class="stat-number" id="totalCompanies"><?php echo number_format($stats['total_companies']); ?></div>
                <div class="stat-label">Empresas Activas</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon vouchers">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="stat-number" id="totalVouchers"><?php echo number_format($stats['total_vouchers']); ?></div>
                <div class="stat-label">Vouchers Subidos</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon trips">
                    <i class="fas fa-truck"></i>
                </div>
                <div class="stat-number" id="totalTrips"><?php echo number_format($stats['total_trips']); ?></div>
                <div class="stat-label">Viajes Extraídos</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon amount">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-number" id="totalAmount">$<?php echo number_format($stats['total_amount']); ?></div>
                <div class="stat-label">Total Procesado</div>
            </div>
        </div>

        <!-- Upload Section -->
        <div class="section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-cloud-upload-alt"></i>
                    Subir Voucher Martin Marieta
                </div>
                <?php if ($stats['pending_vouchers'] > 0): ?>
                <div style="background: var(--warning-orange); color: var(--white); padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.9rem; font-weight: 600;">
                    <?php echo $stats['pending_vouchers']; ?> pendientes
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

        <!-- Quick Actions -->
        <div class="section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-bolt"></i>
                    Acciones Rápidas
                </div>
            </div>
            <div class="section-content">
                <div class="actions-grid">
                    <a href="view-extracted-data.php" class="action-card">
                        <div class="action-icon data">
                            <i class="fas fa-database"></i>
                        </div>
                        <div class="action-content">
                            <h4>Ver Datos Extraídos</h4>
                            <p>Revisar y procesar vouchers</p>
                        </div>
                    </a>
                    
                    <a href="companies.php" class="action-card">
                        <div class="action-icon companies">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="action-content">
                            <h4>Gestionar Empresas</h4>
                            <p>Crear y editar empresas</p>
                        </div>
                    </a>
                    
                    <a href="reports.php" class="action-card">
                        <div class="action-icon reports">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="action-content">
                            <h4>Generar Reportes</h4>
                            <p>Capital Transport reports</p>
                        </div>
                    </a>
                    
                    <a href="vouchers.php" class="action-card">
                        <div class="action-icon vouchers">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <div class="action-content">
                            <h4>Historial Vouchers</h4>
                            <p>Ver todos los vouchers</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <?php if (!empty($recent_vouchers)): ?>
        <div class="section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-history"></i>
                    Últimos Vouchers Procesados
                </div>
                <div style="background: var(--white); color: var(--primary-red); padding: 0.5rem 1rem; border-radius: 20px; font-weight: 600;">
                    <?php echo count($recent_vouchers); ?> recientes
                </div>
            </div>
            <div class="section-content">
                <table class="recent-table">
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

        <!-- No Data State -->
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

        <!-- Pending Vouchers Alert -->
        <?php if ($stats['pending_vouchers'] > 0): ?>
        <div class="section">
            <div class="section-content">
                <div style="background: linear-gradient(135deg, var(--warning-orange), #ea580c); color: var(--white); padding: 1.5rem; border-radius: 12px; text-align: center;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                    <h3 style="margin-bottom: 0.5rem;">Atención: Vouchers Pendientes</h3>
                    <p style="margin-bottom: 1.5rem;">Tienes <?php echo $stats['pending_vouchers']; ?> voucher(s) pendiente(s) de procesar</p>
                    <a href="view-extracted-data.php" class="btn" style="background: var(--white); color: var(--warning-orange); font-weight: 600;">
                        <i class="fas fa-cog"></i>
                        Procesar Ahora
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>