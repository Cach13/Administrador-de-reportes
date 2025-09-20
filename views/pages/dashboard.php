<?php
/**
 * views/pages/dashboard.php - PASO 15
 * Dashboard independiente que funciona sin layout principal
 * Incluye todo el HTML necesario en un solo archivo
 */

// Verificar que las variables estén disponibles
$stats = $stats ?? [];
$recentActivity = $recentActivity ?? [];
$pendingVouchers = $pendingVouchers ?? [];
$recentReports = $recentReports ?? [];
$currentUser = $currentUser ?? [];

// Función helper para obtener base URL
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Obtener el directorio base del proyecto
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    
    // Si estamos en /views/pages/, subir dos niveles para llegar a la raíz
    if (strpos($scriptDir, '/views/pages') !== false) {
        $scriptDir = dirname(dirname($scriptDir));
    }
    
    return $protocol . '://' . $host . $scriptDir;
}

// Función helper para formatear bytes
function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Principal - Transport Management</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #4e73df;
            --success-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --info-color: #36b9cc;
            --light-gray: #f8f9fc;
            --border-color: #e3e6f0;
        }

        body {
            background-color: var(--light-gray);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--primary-color) !important;
        }

        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .card-header {
            background-color: #fff;
            border-bottom: 1px solid var(--border-color);
        }

        .border-left-primary {
            border-left: 0.25rem solid var(--primary-color) !important;
        }

        .border-left-success {
            border-left: 0.25rem solid var(--success-color) !important;
        }

        .border-left-warning {
            border-left: 0.25rem solid var(--warning-color) !important;
        }

        .border-left-info {
            border-left: 0.25rem solid var(--info-color) !important;
        }

        .text-primary {
            color: var(--primary-color) !important;
        }

        .text-success {
            color: var(--success-color) !important;
        }

        .text-warning {
            color: var(--warning-color) !important;
        }

        .text-danger {
            color: var(--danger-color) !important;
        }

        .text-info {
            color: var(--info-color) !important;
        }

        .text-gray-800 {
            color: #5a5c69 !important;
        }

        .text-gray-600 {
            color: #6c757d !important;
        }

        .text-gray-500 {
            color: #858796 !important;
        }

        .text-gray-400 {
            color: #d1d3e2 !important;
        }

        .text-gray-300 {
            color: #dddfeb !important;
        }

        .fa-2x {
            font-size: 2em;
        }

        .spin {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .chart-area {
            position: relative;
            height: 320px;
        }

        .chart-pie {
            position: relative;
            height: 245px;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0,0,0,.075);
        }

        .badge {
            font-size: 0.75em;
        }

        .btn-group-sm .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, var(--primary-color) 10%, #224abe 100%);
        }

        .sidebar .nav-link {
            color: rgba(255,255,255,.8);
            padding: 0.75rem 1rem;
            margin: 0.125rem 0;
        }

        .sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255,255,255,.1);
        }

        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255,255,255,.2);
        }

        .main-content {
            margin-left: 0;
        }

        @media (min-width: 768px) {
            .main-content {
                margin-left: 250px;
            }
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            min-width: 300px;
            max-width: 500px;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .loading-spinner {
            background: #fff;
            padding: 2rem;
            border-radius: 0.5rem;
            text-align: center;
        }
    </style>
</head>

<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <!-- Sidebar -->
        <ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar" style="position: fixed; top: 0; left: 0; width: 250px; height: 100vh; z-index: 1000;">
            <!-- Sidebar - Brand -->
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?php echo getBaseUrl(); ?>">
                <div class="sidebar-brand-icon rotate-n-15">
                    <i class="bi bi-truck"></i>
                </div>
                <div class="sidebar-brand-text mx-3">Transport <sup>v2</sup></div>
            </a>

            <!-- Divider -->
            <hr class="sidebar-divider my-0">

            <!-- Nav Item - Dashboard -->
            <li class="nav-item active">
                <a class="nav-link" href="<?php echo getBaseUrl(); ?>/views/pages/dashboard.php">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider">

            <!-- Heading -->
            <div class="sidebar-heading" style="color: rgba(255,255,255,.4); font-size: 0.75rem; padding: 0.5rem 1rem;">
                Operaciones
            </div>

            <!-- Nav Item - Processing -->
            <li class="nav-item">
                <a class="nav-link" href="<?php echo getBaseUrl(); ?>/views/pages/processing.php">
                    <i class="bi bi-upload"></i>
                    <span>Procesar Vouchers</span>
                </a>
            </li>

            <!-- Nav Item - Management -->
            <li class="nav-item">
                <a class="nav-link" href="<?php echo getBaseUrl(); ?>/views/pages/management.php">
                    <i class="bi bi-gear"></i>
                    <span>Gestión</span>
                </a>
            </li>

            <!-- Nav Item - Reports -->
            <li class="nav-item">
                <a class="nav-link" href="<?php echo getBaseUrl(); ?>/views/pages/reports.php">
                    <i class="bi bi-file-earmark-bar-graph"></i>
                    <span>Reportes</span>
                </a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider">

            <!-- Heading -->
            <div class="sidebar-heading" style="color: rgba(255,255,255,.4); font-size: 0.75rem; padding: 0.5rem 1rem;">
                Sistema
            </div>

            <!-- Nav Item - Settings -->
            <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo getBaseUrl(); ?>/views/pages/settings.php">
                    <i class="bi bi-sliders"></i>
                    <span>Configuración</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Nav Item - Logout -->
            <li class="nav-item">
                <a class="nav-link" href="<?php echo getBaseUrl(); ?>/logout.php" onclick="return confirm('¿Cerrar sesión?')">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider d-none d-md-block">
        </ul>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column main-content">
            <!-- Main Content -->
            <div id="content">
                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <!-- Sidebar Toggle (Topbar) -->
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="bi bi-list"></i>
                    </button>

                    <!-- Topbar Navbar -->
                    <ul class="navbar-nav ml-auto">
                        <!-- Nav Item - User Information -->
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small">
                                    <?php echo htmlspecialchars($currentUser['username'] ?? 'Usuario'); ?>
                                </span>
                                <i class="bi bi-person-circle"></i>
                            </a>
                            <!-- Dropdown - User Information -->
                            <div class="dropdown-menu dropdown-menu-end shadow">
                                <a class="dropdown-item" href="#">
                                    <i class="bi bi-person me-2"></i>
                                    Perfil
                                </a>
                                <a class="dropdown-item" href="#">
                                    <i class="bi bi-gear me-2"></i>
                                    Configuración
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="<?php echo getBaseUrl(); ?>/logout.php" onclick="return confirm('¿Cerrar sesión?')">
                                    <i class="bi bi-box-arrow-right me-2"></i>
                                    Cerrar Sesión
                                </a>
                            </div>
                        </li>
                    </ul>
                </nav>
                <!-- End of Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <!-- Dashboard Header -->
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <div>
                            <h1 class="h2">
                                <i class="bi bi-speedometer2 text-primary"></i>
                                Dashboard Principal
                            </h1>
                            <p class="text-muted">Resumen de actividades del sistema de gestión de transporte</p>
                        </div>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <div class="btn-group me-2">
                                <button type="button" class="btn btn-outline-primary" onclick="refreshDashboard()" id="refreshBtn">
                                    <i class="bi bi-arrow-clockwise"></i> Actualizar
                                </button>
                                <a href="<?php echo getBaseUrl(); ?>/views/pages/processing.php" class="btn btn-success">
                                    <i class="bi bi-upload"></i> Nuevo Voucher
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Stats Cards Row -->
                    <div class="row mb-4" id="statsContainer">
                        <!-- Total Empresas -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                Total Empresas
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="totalCompanies">
                                                <?php echo number_format($stats['totalCompanies'] ?? 0); ?>
                                            </div>
                                            <div class="mt-2">
                                                <span class="text-success">
                                                    <i class="bi bi-check-circle"></i> Activas
                                                </span>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="bi bi-building fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Total Vouchers -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Total Vouchers
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="totalVouchers">
                                                <?php echo number_format($stats['totalVouchers'] ?? 0); ?>
                                            </div>
                                            <div class="mt-2">
                                                <span class="text-info">
                                                    <i class="bi bi-file-earmark-pdf"></i> 
                                                    Procesados: <?php echo number_format($stats['processedVouchers'] ?? 0); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="bi bi-file-earmark-text fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Total Trips -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-warning shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                Total Trips
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="totalTrips">
                                                <?php echo number_format($stats['totalTrips'] ?? 0); ?>
                                            </div>
                                            <div class="mt-2">
                                                <span class="text-primary">
                                                    <i class="bi bi-truck"></i> Este mes: <?php echo number_format($stats['monthlyTrips'] ?? 0); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="bi bi-truck fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Monto Total -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                Monto Total
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="totalAmount">
                                                <?php echo $stats['totalAmountFormatted'] ?? '$0.00'; ?>
                                            </div>
                                            <div class="mt-2">
                                                <span class="<?php echo ($stats['monthlyAmountChange'] ?? 0) >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                    <i class="bi bi-<?php echo ($stats['monthlyAmountChange'] ?? 0) >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                                    <?php echo abs($stats['monthlyAmountChange'] ?? 0); ?>%
                                                </span>
                                                <span class="text-muted"> vs mes anterior</span>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="bi bi-currency-dollar fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row -->
                    <div class="row mb-4">
                        <!-- Monthly Trends Chart -->
                        <div class="col-xl-8 col-lg-7">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="bi bi-graph-up"></i>
                                        Tendencias Mensuales
                                    </h6>
                                    <div class="dropdown no-arrow">
                                        <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots-vertical text-gray-400"></i>
                                        </a>
                                        <div class="dropdown-menu dropdown-menu-end shadow">
                                            <div class="dropdown-header">Opciones del Gráfico:</div>
                                            <a class="dropdown-item" href="#" onclick="updateChartPeriod('6months')">Últimos 6 meses</a>
                                            <a class="dropdown-item" href="#" onclick="updateChartPeriod('12months')">Últimos 12 meses</a>
                                            <a class="dropdown-item" href="#" onclick="exportChart('monthly')">Exportar gráfico</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="chart-area">
                                        <canvas id="monthlyChart" style="height: 320px;"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Company Distribution Pie Chart -->
                        <div class="col-xl-4 col-lg-5">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="bi bi-pie-chart"></i>
                                        Top 5 Empresas
                                    </h6>
                                    <div class="dropdown no-arrow">
                                        <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots-vertical text-gray-400"></i>
                                        </a>
                                        <div class="dropdown-menu dropdown-menu-end shadow">
                                            <a class="dropdown-item" href="#" onclick="exportChart('companies')">Exportar gráfico</a>
                                            <a class="dropdown-item" href="<?php echo getBaseUrl(); ?>/views/pages/companies.php">Ver todas las empresas</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="chart-pie pt-4 pb-2">
                                        <canvas id="companiesChart" style="height: 245px;"></canvas>
                                    </div>
                                    <div class="mt-4 text-center small">
                                        <?php if (!empty($stats['topCompanies'])): ?>
                                            <?php foreach (array_slice($stats['topCompanies'], 0, 3) as $index => $company): ?>
                                                <span class="mr-2">
                                                    <i class="fas fa-circle" style="color: <?php echo ['#4e73df', '#1cc88a', '#f6c23e'][$index]; ?>"></i>
                                                    <?php echo htmlspecialchars($company['identifier']); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-muted">No hay datos de empresas</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Activity and Pending Items Row -->
                    <div class="row">
                        <!-- Recent Activity -->
                        <div class="col-xl-6 col-lg-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="bi bi-clock-history"></i>
                                        Actividad Reciente
                                    </h6>
                                    <a href="#" class="text-primary" onclick="refreshActivity()">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </a>
                                </div>
                                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                    <div id="recentActivity">
                                        <?php if (!empty($recentActivity)): ?>
                                            <?php foreach ($recentActivity as $activity): ?>
                                                <div class="d-flex align-items-center border-left-primary border-bottom py-3">
                                                    <div class="flex-grow-1 ms-3">
                                                        <div class="fw-bold text-gray-800">
                                                            <?php echo htmlspecialchars($activity['description'] ?? ''); ?>
                                                        </div>
                                                        <?php if (!empty($activity['details'])): ?>
                                                            <div class="small text-gray-600">
                                                                <?php echo htmlspecialchars($activity['details']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="small text-gray-500">
                                                            <i class="bi bi-clock"></i>
                                                            <?php echo date('d/m/Y H:i', strtotime($activity['created_at'] ?? 'now')); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="text-center py-4">
                                                <i class="bi bi-clock-history text-gray-300" style="font-size: 3rem;"></i>
                                                <p class="text-muted mt-3">No hay actividad reciente</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pending Vouchers -->
                        <div class="col-xl-6 col-lg-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="bi bi-hourglass-split"></i>
                                        Vouchers Pendientes
                                        <?php if (!empty($pendingVouchers)): ?>
                                            <span class="badge bg-warning ms-2"><?php echo count($pendingVouchers); ?></span>
                                        <?php endif; ?>
                                    </h6>
                                    <a href="<?php echo getBaseUrl(); ?>/views/pages/processing.php" class="text-success">
                                        <i class="bi bi-upload"></i> Subir nuevo
                                    </a>
                                </div>
                                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                    <div id="pendingVouchers">
                                        <?php if (!empty($pendingVouchers)): ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Archivo</th>
                                                            <th>Estado</th>
                                                            <th>Fecha</th>
                                                            <th>Acciones</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($pendingVouchers as $voucher): ?>
                                                            <tr>
                                                                <td>
                                                                    <div class="d-flex align-items-center">
                                                                        <i class="bi bi-file-earmark-pdf text-danger me-2"></i>
                                                                        <div>
                                                                            <div class="fw-bold text-truncate" style="max-width: 150px;">
                                                                                <?php echo htmlspecialchars($voucher['filename'] ?? ''); ?>
                                                                            </div>
                                                                            <small class="text-muted">
                                                                                <?php echo formatBytes($voucher['file_size'] ?? 0); ?>
                                                                            </small>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <?php
                                                                    $statusClass = 'bg-secondary';
                                                                    $statusIcon = 'bi-question';
                                                                    switch ($voucher['status']) {
                                                                        case 'uploaded':
                                                                            $statusClass = 'bg-warning';
                                                                            $statusIcon = 'bi-clock';
                                                                            break;
                                                                        case 'processing':
                                                                            $statusClass = 'bg-info';
                                                                            $statusIcon = 'bi-gear';
                                                                            break;
                                                                        case 'error':
                                                                            $statusClass = 'bg-danger';
                                                                            $statusIcon = 'bi-exclamation-triangle';
                                                                            break;
                                                                    }
                                                                    ?>
                                                                    <span class="badge <?php echo $statusClass; ?>">
                                                                        <i class="bi <?php echo $statusIcon; ?>"></i>
                                                                        <?php echo ucfirst($voucher['status'] ?? 'unknown'); ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <small>
                                                                        <?php echo date('d/m/Y', strtotime($voucher['created_at'] ?? 'now')); ?>
                                                                        <br>
                                                                        <?php echo date('H:i', strtotime($voucher['created_at'] ?? 'now')); ?>
                                                                    </small>
                                                                </td>
                                                                <td>
                                                                    <div class="btn-group-sm">
                                                                        <?php if ($voucher['status'] === 'uploaded'): ?>
                                                                            <button class="btn btn-sm btn-success" 
                                                                                    onclick="processVoucher(<?php echo $voucher['id']; ?>)"
                                                                                    title="Procesar">
                                                                                <i class="bi bi-play"></i>
                                                                            </button>
                                                                        <?php endif; ?>
                                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                                onclick="viewVoucher(<?php echo $voucher['id']; ?>)"
                                                                                title="Ver detalles">
                                                                            <i class="bi bi-eye"></i>
                                                                        </button>
                                                                        <?php if ($voucher['status'] === 'error'): ?>
                                                                            <button class="btn btn-sm btn-warning" 
                                                                                    onclick="retryVoucher(<?php echo $voucher['id']; ?>)"
                                                                                    title="Reintentar">
                                                                                <i class="bi bi-arrow-clockwise"></i>
                                                                            </button>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-5">
                                                <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                                                <h5 class="mt-3 text-success">¡Todo al día!</h5>
                                                <p class="text-muted">No hay vouchers pendientes de procesar</p>
                                                <a href="<?php echo getBaseUrl(); ?>/views/pages/processing.php" class="btn btn-success">
                                                    <i class="bi bi-upload"></i> Subir Nuevo Voucher
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Reports Row (if any) -->
                    <?php if (!empty($recentReports)): ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="bi bi-file-earmark-bar-graph"></i>
                                        Reportes Recientes
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Reporte</th>
                                                    <th>Empresa</th>
                                                    <th>Generado</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentReports as $report): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <i class="bi bi-file-earmark-pdf text-danger me-2"></i>
                                                                <div>
                                                                    <div class="fw-bold">
                                                                        <?php echo htmlspecialchars($report['report_name'] ?? ''); ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($report['company_name'] ?? ''); ?></div>
                                                                <small class="text-muted"><?php echo htmlspecialchars($report['company_identifier'] ?? ''); ?></small>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <small>
                                                                <?php echo date('d/m/Y H:i', strtotime($report['created_at'] ?? 'now')); ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group-sm">
                                                                <button class="btn btn-sm btn-primary" 
                                                                        onclick="downloadReport(<?php echo $report['id']; ?>)"
                                                                        title="Descargar">
                                                                    <i class="bi bi-download"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-secondary" 
                                                                        onclick="shareReport(<?php echo $report['id']; ?>)"
                                                                        title="Compartir">
                                                                    <i class="bi bi-share"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
                <!-- End of Main Content -->
            </div>
            <!-- End of Content Wrapper -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; Transport Management System <?php echo date('Y'); ?> - v2.0.0</span>
                    </div>
                </div>
            </footer>
            <!-- End of Footer -->

        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay" style="display: none;">
        <div class="loading-spinner">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <div class="mt-3">Cargando datos...</div>
        </div>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Dashboard JavaScript -->
    <script>
        // Dashboard variables globales
        let monthlyChart;
        let companiesChart;
        let refreshInterval;

        // Inicializar dashboard cuando se carga la página
        document.addEventListener('DOMContentLoaded', function() {
            initializeDashboard();
        });

        function initializeDashboard() {
            console.log('Inicializando dashboard...');
            initializeCharts();
            setupAutoRefresh();
            setupEventListeners();
            
            // Mostrar mensaje de bienvenida
            showWelcomeMessage();
            
            // Ocultar loading si está visible
            hideLoading();
        }

        function initializeCharts() {
            console.log('Inicializando gráficos...');
            
            // Datos para el gráfico mensual desde PHP (datos de prueba si no hay datos)
            const monthlyData = <?php echo json_encode($stats['monthlyTrends'] ?? [
                ['month' => date('Y-m', strtotime('-5 months')), 'trip_count' => 45, 'total_amount' => 12500],
                ['month' => date('Y-m', strtotime('-4 months')), 'trip_count' => 52, 'total_amount' => 14200],
                ['month' => date('Y-m', strtotime('-3 months')), 'trip_count' => 48, 'total_amount' => 13100],
                ['month' => date('Y-m', strtotime('-2 months')), 'trip_count' => 61, 'total_amount' => 16800],
                ['month' => date('Y-m', strtotime('-1 month')), 'trip_count' => 58, 'total_amount' => 15900],
                ['month' => date('Y-m'), 'trip_count' => 42, 'total_amount' => 11500]
            ]); ?>;
            
            // Preparar datos del gráfico mensual
            const monthlyLabels = monthlyData.map(item => {
                const date = new Date(item.month + '-01');
                return date.toLocaleDateString('es-ES', { month: 'short', year: '2-digit' });
            });
            const monthlyTrips = monthlyData.map(item => parseInt(item.trip_count));
            const monthlyAmounts = monthlyData.map(item => parseFloat(item.total_amount));
            
            // Gráfico de tendencias mensuales
            const monthlyCtx = document.getElementById('monthlyChart');
            if (monthlyCtx) {
                monthlyChart = new Chart(monthlyCtx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: monthlyLabels,
                        datasets: [{
                            label: 'Número de Trips',
                            data: monthlyTrips,
                            borderColor: '#4e73df',
                            backgroundColor: 'rgba(78, 115, 223, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.3,
                            pointRadius: 5,
                            pointHoverRadius: 7
                        }, {
                            label: 'Monto Total ($)',
                            data: monthlyAmounts,
                            borderColor: '#1cc88a',
                            backgroundColor: 'rgba(28, 200, 138, 0.1)',
                            borderWidth: 3,
                            fill: false,
                            tension: 0.3,
                            pointRadius: 5,
                            pointHoverRadius: 7,
                            yAxisID: 'y1'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'Número de Trips'
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Monto ($)'
                                },
                                grid: {
                                    drawOnChartArea: false,
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                callbacks: {
                                    afterLabel: function(context) {
                                        if (context.datasetIndex === 1) {
                                            return 'Monto: $' + new Intl.NumberFormat().format(context.parsed.y);
                                        }
                                        return '';
                                    }
                                }
                            }
                        },
                        interaction: {
                            mode: 'nearest',
                            axis: 'x',
                            intersect: false
                        }
                    }
                });
            }

            // Datos para el gráfico de empresas (datos de prueba si no hay datos)
            const companiesData = <?php echo json_encode($stats['topCompanies'] ?? [
                ['identifier' => 'Martin Marieta', 'total_amount' => 45000],
                ['identifier' => 'Construction Co', 'total_amount' => 32000],
                ['identifier' => 'Transport LLC', 'total_amount' => 28000],
                ['identifier' => 'Logistics Inc', 'total_amount' => 22000],
                ['identifier' => 'Heavy Haul', 'total_amount' => 18000]
            ]); ?>;
            
            const companyLabels = companiesData.map(company => company.identifier);
            const companyAmounts = companiesData.map(company => parseFloat(company.total_amount));

            // Gráfico de distribución por empresas
            const companiesCtx = document.getElementById('companiesChart');
            if (companiesCtx) {
                companiesChart = new Chart(companiesCtx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: companyLabels,
                        datasets: [{
                            data: companyAmounts,
                            backgroundColor: [
                                '#4e73df',
                                '#1cc88a',
                                '#f6c23e',
                                '#e74a3b',
                                '#858796'
                            ],
                            borderColor: [
                                '#4e73df',
                                '#1cc88a',
                                '#f6c23e',
                                '#e74a3b',
                                '#858796'
                            ],
                            borderWidth: 2,
                            hoverOffset: 10
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const value = context.parsed;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((value / total) * 100).toFixed(1);
                                        return context.label + ': $' + new Intl.NumberFormat().format(value) + ' (' + percentage + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            console.log('Gráficos inicializados correctamente');
        }

        function setupEventListeners() {
            // Toggle sidebar en móvil
            const sidebarToggle = document.getElementById('sidebarToggleTop');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    const sidebar = document.querySelector('.sidebar');
                    sidebar.classList.toggle('d-none');
                });
            }

            // Event listeners para botones y acciones
            document.addEventListener('click', function(e) {
                // Manejar clics en botones de vouchers
                if (e.target.closest('.btn[onclick*="processVoucher"]')) {
                    e.preventDefault();
                }
            });
        }

        function setupAutoRefresh() {
            // Auto refresh cada 5 minutos
            refreshInterval = setInterval(() => {
                refreshDashboard(true); // true = silencioso
            }, 300000);
        }

        // Refresh del dashboard
        async function refreshDashboard(silent = false) {
            const refreshBtn = document.getElementById('refreshBtn');
            const originalContent = refreshBtn.innerHTML;
            
            if (!silent) {
                refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Actualizando...';
                refreshBtn.disabled = true;
                showLoading();
            }
            
            try {
                // Simular llamada a API (reemplazar con API real)
                await new Promise(resolve => setTimeout(resolve, 1000));
                
                // TODO: Implementar llamada real a API
                // const response = await fetch('<?php echo getBaseUrl(); ?>/api.php?action=getDashboardData');
                // const data = await response.json();
                
                // Por ahora, simular datos actualizados
                const simulatedData = {
                    success: true,
                    data: {
                        stats: {
                            totalCompanies: Math.floor(Math.random() * 50) + 20,
                            totalVouchers: Math.floor(Math.random() * 500) + 200,
                            totalTrips: Math.floor(Math.random() * 1000) + 500,
                            totalAmountFormatted: '$' + (Math.floor(Math.random() * 100000) + 50000).toLocaleString()
                        }
                    }
                };
                
                if (simulatedData.success) {
                    updateDashboardData(simulatedData.data);
                    
                    if (!silent) {
                        showNotification('Dashboard actualizado correctamente', 'success');
                    }
                } else {
                    throw new Error('Error en la respuesta del servidor');
                }
            } catch (error) {
                console.error('Error refreshing dashboard:', error);
                
                if (!silent) {
                    showNotification('Error actualizando dashboard: ' + error.message, 'danger');
                }
            } finally {
                if (!silent) {
                    refreshBtn.innerHTML = originalContent;
                    refreshBtn.disabled = false;
                    hideLoading();
                }
            }
        }

        function updateDashboardData(data) {
            // Actualizar estadísticas
            if (data.stats) {
                updateStats(data.stats);
            }
            
            // Actualizar actividad
            if (data.recentActivity) {
                updateActivity(data.recentActivity);
            }
            
            // Actualizar vouchers pendientes
            if (data.pendingVouchers) {
                updatePendingVouchers(data.pendingVouchers);
            }
            
            // Actualizar gráficos si es necesario
            if (data.stats && data.stats.monthlyTrends) {
                updateCharts(data.stats);
            }
        }

        function updateStats(stats) {
            const elements = {
                'totalCompanies': stats.totalCompanies || 0,
                'totalVouchers': stats.totalVouchers || 0,
                'totalTrips': stats.totalTrips || 0
            };
            
            Object.keys(elements).forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.textContent = new Intl.NumberFormat().format(elements[id]);
                }
            });
            
            if (stats.totalAmountFormatted) {
                const amountElement = document.getElementById('totalAmount');
                if (amountElement) {
                    amountElement.textContent = stats.totalAmountFormatted;
                }
            }
        }

        function updateActivity(activities) {
            const container = document.getElementById('recentActivity');
            if (container && activities && activities.length > 0) {
                container.innerHTML = activities.map(activity => `
                    <div class="d-flex align-items-center border-left-primary border-bottom py-3">
                        <div class="flex-grow-1 ms-3">
                            <div class="fw-bold text-gray-800">
                                ${escapeHtml(activity.description || '')}
                            </div>
                            ${activity.details ? `<div class="small text-gray-600">${escapeHtml(activity.details)}</div>` : ''}
                            <div class="small text-gray-500">
                                <i class="bi bi-clock"></i>
                                ${formatDate(activity.created_at)}
                            </div>
                        </div>
                    </div>
                `).join('');
            }
        }

        // Funciones de acciones de vouchers
        async function processVoucher(voucherId) {
            if (!confirm('¿Procesar este voucher?')) return;
            
            showLoading();
            
            try {
                // TODO: Implementar llamada real a API
                await new Promise(resolve => setTimeout(resolve, 2000));
                
                showNotification('Voucher procesado correctamente', 'success');
                refreshDashboard();
            } catch (error) {
                showNotification('Error procesando voucher: ' + error.message, 'danger');
            } finally {
                hideLoading();
            }
        }

        function viewVoucher(voucherId) {
            // Abrir modal o página de detalles del voucher
            showNotification('Funcionalidad de ver voucher próximamente', 'info');
        }

        function retryVoucher(voucherId) {
            if (!confirm('¿Reintentar procesamiento de este voucher?')) return;
            processVoucher(voucherId);
        }

        function downloadReport(reportId) {
            showNotification('Descargando reporte...', 'info');
            // TODO: Implementar descarga real
        }

        function shareReport(reportId) {
            showNotification('Funcionalidad de compartir próximamente', 'info');
        }

        function refreshActivity() {
            showNotification('Actualizando actividad...', 'info');
            // TODO: Implementar refresh de actividad
        }

        function updateChartPeriod(period) {
            showNotification('Actualizando gráfico para ' + period, 'info');
            // TODO: Implementar actualización de período
        }

        function exportChart(chartType) {
            showNotification('Exportando gráfico ' + chartType, 'info');
            // TODO: Implementar exportación
        }

        // Funciones utilitarias
        function showWelcomeMessage() {
            const hour = new Date().getHours();
            let greeting = 'Buenos días';
            
            if (hour >= 12 && hour < 18) {
                greeting = 'Buenas tardes';
            } else if (hour >= 18) {
                greeting = 'Buenas noches';
            }
            
            const userName = '<?php echo htmlspecialchars($currentUser["username"] ?? "Usuario"); ?>';
            
            setTimeout(() => {
                showNotification(greeting + ', ' + userName + '! Dashboard cargado correctamente.', 'info', 3000);
            }, 1000);
        }

        function showNotification(message, type = 'info', duration = 5000) {
            // Remover notificaciones existentes
            const existingNotifications = document.querySelectorAll('.notification');
            existingNotifications.forEach(n => n.remove());
            
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show notification`;
            notification.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="bi bi-${getNotificationIcon(type)} me-2"></i>
                    <div>${message}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, duration);
        }

        function getNotificationIcon(type) {
            const icons = {
                'success': 'check-circle',
                'danger': 'exclamation-triangle',
                'warning': 'exclamation-triangle',
                'info': 'info-circle'
            };
            return icons[type] || 'info-circle';
        }

        function showLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.style.display = 'flex';
            }
        }

        function hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.style.display = 'none';
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('es-ES', {
                day: '2-digit',
                month: '2-digit', 
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Cleanup al salir de la página
        window.addEventListener('beforeunload', function() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        });

        // Manejar errores globales
        window.addEventListener('error', function(e) {
            console.error('Error en dashboard:', e.error);
            showNotification('Se produjo un error inesperado', 'danger');
        });

        // Responsive charts
        window.addEventListener('resize', function() {
            if (monthlyChart) {
                monthlyChart.resize();
            }
            if (companiesChart) {
                companiesChart.resize();
            }
        });

    </script>
</body>
</html>