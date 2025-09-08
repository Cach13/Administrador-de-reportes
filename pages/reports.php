<?php
/**
 * Reports Page - Generación de PDFs Capital Transport
 * Ruta: /pages/reports.php
 */

require_once '../includes/auth-check.php';
require_once '../classes/Database.php';

$db = Database::getInstance();

// Obtener vouchers procesados con datos de empresas
$vouchers = $db->fetchAll("
    SELECT 
        v.id,
        v.voucher_number,
        v.original_filename,
        v.status,
        v.upload_date,
        COUNT(DISTINCT t.company_id) as companies_count,
        COUNT(t.id) as trips_count,
        SUM(t.amount) as total_amount
    FROM vouchers v
    LEFT JOIN trips t ON v.id = t.voucher_id
    WHERE v.status = 'processed'
    GROUP BY v.id, v.voucher_number, v.original_filename, v.status, v.upload_date
    HAVING trips_count > 0
    ORDER BY v.upload_date DESC
");

// Obtener empresas activas
$companies = $db->fetchAll("
    SELECT * FROM companies WHERE is_active = 1 ORDER BY name
");

// Si hay un voucher seleccionado, obtener sus empresas
$selected_voucher_companies = [];
$selected_voucher = null;

if (isset($_GET['voucher']) && is_numeric($_GET['voucher'])) {
    $voucher_id = intval($_GET['voucher']);
    
    $selected_voucher = $db->fetch("
        SELECT * FROM vouchers WHERE id = ? AND status = 'processed'
    ", [$voucher_id]);
    
    if ($selected_voucher) {
        $selected_voucher_companies = $db->fetchAll("
            SELECT 
                c.*,
                COUNT(t.id) as trips_count,
                SUM(t.amount) as total_amount,
                MIN(t.trip_date) as first_trip,
                MAX(t.trip_date) as last_trip
            FROM companies c
            JOIN trips t ON c.id = t.company_id
            WHERE t.voucher_id = ?
            GROUP BY c.id, c.name, c.identifier, c.capital_percentage
            ORDER BY trips_count DESC
        ", [$voucher_id]);
    }
}

// Obtener reportes generados recientes
$recent_reports = $db->fetchAll("
    SELECT 
        r.*,
        c.name as company_name,
        c.identifier as company_identifier,
        v.voucher_number,
        u.full_name as generated_by_name
    FROM reports r
    JOIN companies c ON r.company_id = c.id
    JOIN vouchers v ON r.voucher_id = v.id
    LEFT JOIN users u ON r.generated_by = u.id
    ORDER BY r.generation_date DESC
    LIMIT 10
");

$page_title = "Generar Reportes";
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

        /* VOUCHER SELECTION */
        .voucher-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .voucher-card {
            border: 2px solid var(--border-light);
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .voucher-card:hover {
            border-color: var(--primary-red);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .voucher-card.selected {
            border-color: var(--primary-red);
            background: #fef2f2;
        }

        .voucher-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .voucher-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-red);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
        }

        .voucher-info h4 {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .voucher-info p {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin: 0;
        }

        .voucher-stats {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
        }

        .stat {
            text-align: center;
        }

        .stat-number {
            font-weight: 600;
            color: var(--primary-red);
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        /* COMPANY SELECTION */
        .companies-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
        }

        .company-card {
            border: 2px solid var(--border-light);
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .company-card:hover {
            border-color: var(--success-green);
            transform: translateY(-2px);
        }

        .company-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .company-name {
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 0.5rem;
        }

        .company-identifier {
            background: var(--primary-red);
            color: var(--white);
            padding: 0.25rem 0.6rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .company-stats {
            margin: 1rem 0;
        }

        .company-stat {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .company-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        /* FORM CONTROLS */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark-gray);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border-light);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-red);
            outline: none;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }

        .form-help {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        /* TABLES */
        .reports-table {
            width: 100%;
            border-collapse: collapse;
        }

        .reports-table th {
            background: var(--dark-gray);
            color: var(--white);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .reports-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
        }

        .reports-table tr:hover {
            background: #f8f9fa;
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

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid var(--info-blue);
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

        /* ANIMATIONS */
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .loading {
            opacity: 0.6;
            pointer-events: none;
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

            .voucher-grid,
            .companies-grid {
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
                    <p>Payment Information Reports</p>
                </div>
            </div>
            <div class="nav-links">
                <a href="dashboard.php" class="nav-link">
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
                <a href="reports.php" class="nav-link active">
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
                <i class="fas fa-file-pdf"></i>
                Generar Reportes Capital Transport
            </h1>
            <div class="page-actions">
                <button class="btn btn-secondary" onclick="refreshData()">
                    <i class="fas fa-sync"></i>
                    Refresh
                </button>
                <a href="view-extracted-data.php" class="btn btn-primary">
                    <i class="fas fa-database"></i>
                    Ver Datos
                </a>
            </div>
        </div>

        <!-- Step 1: Select Voucher -->
        <?php if (!$selected_voucher): ?>
        <div class="section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-file-invoice"></i>
                    Paso 1: Seleccionar Voucher Procesado
                </div>
                <div style="background: var(--white); color: var(--primary-red); padding: 0.5rem 1rem; border-radius: 20px; font-weight: 600;">
                    <?php echo count($vouchers); ?> disponibles
                </div>
            </div>
            <div class="section-content">
                <?php if (empty($vouchers)): ?>
                    <div class="empty-state">
                        <i class="fas fa-file-invoice"></i>
                        <h3>No hay vouchers procesados</h3>
                        <p>Primero debes subir y procesar algunos archivos PDF</p>
                        <a href="view-extracted-data.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Ir a Subir Archivos
                        </a>
                    </div>
                <?php else: ?>
                    <div class="voucher-grid">
                        <?php foreach ($vouchers as $voucher): ?>
                        <div class="voucher-card" onclick="selectVoucher(<?php echo $voucher['id']; ?>)">
                            <div class="voucher-header">
                                <div class="voucher-icon">
                                    <i class="fas fa-file-pdf"></i>
                                </div>
                                <div class="voucher-info">
                                    <h4><?php echo htmlspecialchars($voucher['voucher_number']); ?></h4>
                                    <p><?php echo htmlspecialchars($voucher['original_filename']); ?></p>
                                </div>
                            </div>
                            
                            <div class="voucher-stats">
                                <div class="stat">
                                    <div class="stat-number"><?php echo $voucher['companies_count']; ?></div>
                                    <div class="stat-label">Empresas</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-number"><?php echo $voucher['trips_count']; ?></div>
                                    <div class="stat-label">Viajes</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-number">$<?php echo number_format($voucher['total_amount'], 0); ?></div>
                                    <div class="stat-label">Total</div>
                                </div>
                            </div>
                            
                            <div style="margin-top: 1rem;">
                                <small style="color: var(--text-muted);">
                                    Subido: <?php echo date('d/m/Y', strtotime($voucher['upload_date'])); ?>
                                </small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Step 2: Company Selection -->
        <?php else: ?>
        <div class="section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-file-invoice"></i>
                    Voucher Seleccionado: <?php echo htmlspecialchars($selected_voucher['voucher_number']); ?>
                </div>
                <a href="reports.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i>
                    Cambiar Voucher
                </a>
            </div>
            <div class="section-content">
                <div style="background: var(--light-gray); padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                    <strong>Archivo:</strong> <?php echo htmlspecialchars($selected_voucher['original_filename']); ?><br>
                    <strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($selected_voucher['upload_date'])); ?>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-building"></i>
                    Paso 2: Seleccionar Empresa para Generar Reporte
                </div>
                <div style="background: var(--white); color: var(--primary-red); padding: 0.5rem 1rem; border-radius: 20px; font-weight: 600;">
                    <?php echo count($selected_voucher_companies); ?> empresas
                </div>
            </div>
            <div class="section-content">
                <?php if (empty($selected_voucher_companies)): ?>
                    <div class="empty-state">
                        <i class="fas fa-building"></i>
                        <h3>No hay empresas en este voucher</h3>
                        <p>Este voucher no tiene datos de empresas extraídos</p>
                    </div>
                <?php else: ?>
                    <div class="companies-grid">
                        <?php foreach ($selected_voucher_companies as $company): ?>
                        <div class="company-card">
                            <div class="company-header">
                                <h4 class="company-name"><?php echo htmlspecialchars($company['name']); ?></h4>
                                <span class="company-identifier"><?php echo htmlspecialchars($company['identifier']); ?></span>
                            </div>
                            
                            <div class="company-stats">
                                <div class="company-stat">
                                    <span>Viajes:</span>
                                    <strong><?php echo $company['trips_count']; ?></strong>
                                </div>
                                <div class="company-stat">
                                    <span>Total:</span>
                                    <strong>$<?php echo number_format($company['total_amount'], 2); ?></strong>
                                </div>
                                <div class="company-stat">
                                    <span>Capital %:</span>
                                    <strong><?php echo $company['capital_percentage']; ?>%</strong>
                                </div>
                                <div class="company-stat">
                                    <span>Período:</span>
                                    <small><?php echo date('d/m', strtotime($company['first_trip'])); ?> - <?php echo date('d/m', strtotime($company['last_trip'])); ?></small>
                                </div>
                            </div>
                            
                            <div class="company-actions">
                                <button class="btn btn-success btn-sm" onclick="generateReport(<?php echo $selected_voucher['id']; ?>, <?php echo $company['id']; ?>, 'pdf')">
                                    <i class="fas fa-file-pdf"></i>
                                    PDF
                                </button>
                                <button class="btn btn-primary btn-sm" onclick="generateReport(<?php echo $selected_voucher['id']; ?>, <?php echo $company['id']; ?>, 'excel')">
                                    <i class="fas fa-file-excel"></i>
                                    Excel
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

       <!-- Recent Reports -->
        <?php if (!empty($recent_reports)): ?>
        <div class="section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-history"></i>
                    Reportes Generados Recientes
                </div>
                <div style="background: var(--white); color: var(--primary-red); padding: 0.5rem 1rem; border-radius: 20px; font-weight: 600;">
                    <?php echo count($recent_reports); ?> reportes
                </div>
            </div>
            <div class="section-content">
                <table class="reports-table">
                    <thead>
                        <tr>
                            <th>Payment No.</th>
                            <th>Empresa</th>
                            <th>Voucher</th>
                            <th>Período</th>
                            <th>Total Payment</th>
                            <th>Generado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_reports as $report): ?>
                        <tr>
                            <td>
                                <strong><?php echo str_pad($report['payment_no'], 3, '0', STR_PAD_LEFT); ?></strong>
                            </td>
                            <td>
                                <div>
                                    <strong><?php echo htmlspecialchars($report['company_name']); ?></strong>
                                    <br><span style="font-size: 0.8rem; color: var(--text-muted);"><?php echo $report['company_identifier']; ?></span>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($report['voucher_number']); ?></td>
                            <td>
                                <?php echo date('d/m/Y', strtotime($report['week_start'])); ?>
                                <br><small>a <?php echo date('d/m/Y', strtotime($report['week_end'])); ?></small>
                            </td>
                            <td>
                                <strong>$<?php echo number_format($report['total_payment'], 2); ?></strong>
                                <br><small style="color: var(--text-muted);">-$<?php echo number_format($report['capital_deduction'], 2); ?> (<?php echo $report['capital_percentage']; ?>%)</small>
                            </td>
                            <td>
                                <?php echo date('d/m/Y H:i', strtotime($report['generation_date'])); ?>
                                <br><small style="color: var(--text-muted);"><?php echo htmlspecialchars($report['generated_by_name']); ?></small>
                            </td>
                            <td>
                                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                    <a href="download-report.php?id=<?php echo $report['id']; ?>&format=pdf" class="btn btn-danger btn-sm">
                                        <i class="fas fa-file-pdf"></i>
                                        PDF
                                    </a>
                                    <a href="download-report.php?id=<?php echo $report['id']; ?>&format=excel" class="btn btn-success btn-sm">
                                        <i class="fas fa-file-excel"></i>
                                        Excel
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- JavaScript -->
    <script>
        // Variables globales
        let generatingReport = false;

        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Reports page loaded');
        });

        function selectVoucher(voucherId) {
            window.location.href = `reports.php?voucher=${voucherId}`;
        }

        function generateReport(voucherId, companyId, format) {
            if (generatingReport) {
                showAlert('warning', 'Ya hay un reporte generándose. Espera un momento.');
                return;
            }

            if (!confirm(`¿Generar reporte ${format.toUpperCase()} para esta empresa?`)) {
                return;
            }

            generatingReport = true;
            const btn = event.target.closest('button');
            const originalContent = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando...';
            btn.disabled = true;

            // Preparar datos del formulario
            const formData = new FormData();
            formData.append('voucher_id', voucherId);
            formData.append('company_id', companyId);
            formData.append('format', format);
            formData.append('action', 'generate');

            // Calcular fechas automáticamente (esto se puede hacer mejor en el backend)
            const today = new Date();
            const weekStart = new Date(today.setDate(today.getDate() - today.getDay()));
            const weekEnd = new Date(today.setDate(today.getDate() - today.getDay() + 6));
            
            formData.append('week_start', weekStart.toISOString().split('T')[0]);
            formData.append('week_end', weekEnd.toISOString().split('T')[0]);

            showAlert('info', `Generando reporte ${format.toUpperCase()}...`);

            fetch('../api/generate-capital-report.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', `Reporte ${format.toUpperCase()} generado exitosamente`);
                    
                    // Descargar automáticamente con formato correcto
                    if (data.data.report_id) {
                        window.open(`download-report.php?id=${data.data.report_id}&format=${format}`, '_blank');
                    }
                    
                    // Actualizar página después de un momento
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                    
                } else {
                    showAlert('error', `Error generando reporte: ${data.message}`);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', 'Error de conexión al generar reporte');
            })
            .finally(() => {
                generatingReport = false;
                btn.innerHTML = originalContent;
                btn.disabled = false;
            });
        }

        function refreshData() {
            showAlert('info', 'Actualizando datos...');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }

        function showAlert(type, message) {
            const alertContainer = document.getElementById('alertContainer');
            const alertId = 'alert_' + Date.now();
            
            const icons = {
                success: 'fas fa-check-circle',
                error: 'fas fa-exclamation-triangle',
                warning: 'fas fa-exclamation-circle',
                info: 'fas fa-info-circle'
            };
            
            const alertHTML = `
                <div id="${alertId}" class="alert alert-${type}">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <i class="${icons[type]}"></i>
                        <span>${message}</span>
                    </div>
                    <button type="button" onclick="removeAlert('${alertId}')" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; opacity: 0.7;">&times;</button>
                </div>
            `;
            
            alertContainer.insertAdjacentHTML('beforeend', alertHTML);
            
            // Auto-remover después de 5 segundos
            setTimeout(() => removeAlert(alertId), 5000);
        }

        function removeAlert(alertId) {
            const alert = document.getElementById(alertId);
            if (alert) alert.remove();
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape = volver a selección de vouchers
            if (e.key === 'Escape') {
                window.location.href = 'reports.php';
            }
            
            // Ctrl + R = refresh
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                refreshData();
            }
        });

        // Auto-refresh cada 5 minutos para ver nuevos reportes
        setInterval(refreshData, 300000);
    </script>
</body>
</html>
