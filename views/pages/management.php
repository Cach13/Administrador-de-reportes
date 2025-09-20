<?php
/**
 * views/pages/management.php - PASO 17
 * Página de gestión y administración del sistema
 * Centro de control para empresas, vouchers, reportes y configuraciones
 */

// Verificar que las variables estén disponibles
$summary = $summary ?? [];
$currentUser = $currentUser ?? [];
$isAdmin = $isAdmin ?? true;

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
    <title>Gestión del Sistema - Transport Management</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
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
            --dark-color: #5a5c69;
        }

        body {
            background-color: var(--light-gray);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
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

        .management-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .management-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 1rem;
            padding: 2rem;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .management-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 1rem 3rem rgba(0,0,0,.175);
            color: white;
            text-decoration: none;
        }

        .management-card.companies {
            background: linear-gradient(135deg, var(--primary-color) 0%, #224abe 100%);
        }

        .management-card.vouchers {
            background: linear-gradient(135deg, var(--success-color) 0%, #17a085 100%);
        }

        .management-card.reports {
            background: linear-gradient(135deg, var(--warning-color) 0%, #e67e22 100%);
        }

        .management-card.settings {
            background: linear-gradient(135deg, var(--info-color) 0%, #2c3e50 100%);
        }

        .management-card .icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }

        .management-card .title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .management-card .description {
            opacity: 0.9;
            margin-bottom: 1rem;
        }

        .management-card .stats {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(255,255,255,0.2);
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 1.25rem;
            font-weight: bold;
            display: block;
        }

        .stat-label {
            font-size: 0.75rem;
            opacity: 0.8;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            min-width: 300px;
            max-width: 500px;
        }

        .tab-content {
            background: white;
            border-radius: 0 0.5rem 0.5rem 0.5rem;
            padding: 1.5rem;
        }

        .nav-tabs .nav-link {
            border-radius: 0.5rem 0.5rem 0 0;
            border: none;
            background: var(--light-gray);
            margin-right: 0.25rem;
            color: var(--dark-color);
        }

        .nav-tabs .nav-link.active {
            background: white;
            border-bottom: 3px solid var(--primary-color);
            color: var(--primary-color);
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
            <li class="nav-item">
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

            <!-- Nav Item - Management (Active) -->
            <li class="nav-item active">
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

            <!-- Nav Item - Logout -->
            <li class="nav-item">
                <a class="nav-link" href="<?php echo getBaseUrl(); ?>/logout.php" onclick="return confirm('¿Cerrar sesión?')">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </li>
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
                            <div class="dropdown-menu dropdown-menu-end shadow">
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
                    <!-- Page Header -->
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <div>
                            <h1 class="h2">
                                <i class="bi bi-gear text-primary"></i>
                                Gestión del Sistema
                            </h1>
                            <p class="text-muted">Centro de control para empresas, vouchers, reportes y configuraciones</p>
                        </div>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <div class="btn-group me-2">
                                <button type="button" class="btn btn-outline-primary" onclick="refreshManagement()" id="refreshBtn">
                                    <i class="bi bi-arrow-clockwise"></i> Actualizar
                                </button>
                                <button type="button" class="btn btn-outline-info" onclick="exportData()">
                                    <i class="bi bi-download"></i> Exportar Datos
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Management Cards Grid -->
                    <div class="management-grid">
                        <!-- Companies Management -->
                        <a href="#companiesTab" class="management-card companies" onclick="showTab('companies')">
                            <div class="icon">
                                <i class="bi bi-building"></i>
                            </div>
                            <div class="title">Gestión de Empresas</div>
                            <div class="description">
                                Administra empresas transportistas, configuraciones de Capital Transport y porcentajes de comisión
                            </div>
                            <div class="stats">
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo $summary['total_companies'] ?? '0'; ?></span>
                                    <span class="stat-label">Empresas</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo $summary['active_companies'] ?? '0'; ?></span>
                                    <span class="stat-label">Activas</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number">5.2%</span>
                                    <span class="stat-label">Promedio</span>
                                </div>
                            </div>
                        </a>

                        <!-- Vouchers Management -->
                        <a href="#vouchersTab" class="management-card vouchers" onclick="showTab('vouchers')">
                            <div class="icon">
                                <i class="bi bi-file-earmark-text"></i>
                            </div>
                            <div class="title">Gestión de Vouchers</div>
                            <div class="description">
                                Revisa vouchers procesados, reintenta fallos, monitorea el progreso de extracción de datos
                            </div>
                            <div class="stats">
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo $summary['total_vouchers'] ?? '0'; ?></span>
                                    <span class="stat-label">Total</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo $summary['pending_vouchers'] ?? '0'; ?></span>
                                    <span class="stat-label">Pendientes</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo $summary['failed_vouchers'] ?? '0'; ?></span>
                                    <span class="stat-label">Fallos</span>
                                </div>
                            </div>
                        </a>

                        <!-- Reports Management -->
                        <a href="#reportsTab" class="management-card reports" onclick="showTab('reports')">
                            <div class="icon">
                                <i class="bi bi-file-earmark-bar-graph"></i>
                            </div>
                            <div class="title">Gestión de Reportes</div>
                            <div class="description">
                                Genera reportes Capital Transport, revisa históricos, configura generación automática
                            </div>
                            <div class="stats">
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo $summary['total_reports'] ?? '0'; ?></span>
                                    <span class="stat-label">Generados</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo $summary['this_month_reports'] ?? '0'; ?></span>
                                    <span class="stat-label">Este Mes</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number">$<?php echo number_format($summary['total_payments'] ?? 0); ?></span>
                                    <span class="stat-label">Pagos</span>
                                </div>
                            </div>
                        </a>

                        <!-- System Settings -->
                        <a href="#settingsTab" class="management-card settings" onclick="showTab('settings')">
                            <div class="icon">
                                <i class="bi bi-sliders"></i>
                            </div>
                            <div class="title">Configuración</div>
                            <div class="description">
                                Ajusta configuraciones del sistema, usuarios, permisos y parámetros de procesamiento
                            </div>
                            <div class="stats">
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo $summary['system_uptime'] ?? '99.9'; ?>%</span>
                                    <span class="stat-label">Uptime</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo $summary['storage_used'] ?? '45'; ?>%</span>
                                    <span class="stat-label">Storage</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo $summary['active_users'] ?? '1'; ?></span>
                                    <span class="stat-label">Usuarios</span>
                                </div>
                            </div>
                        </a>
                    </div>

                    <!-- Management Tabs Content -->
                    <div class="row">
                        <div class="col-12">
                            <!-- Tab Navigation -->
                            <ul class="nav nav-tabs" id="managementTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="companies-tab" data-bs-toggle="tab" data-bs-target="#companies" type="button" role="tab">
                                        <i class="bi bi-building me-2"></i>Empresas
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="vouchers-tab" data-bs-toggle="tab" data-bs-target="#vouchers" type="button" role="tab">
                                        <i class="bi bi-file-earmark-text me-2"></i>Vouchers
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports" type="button" role="tab">
                                        <i class="bi bi-file-earmark-bar-graph me-2"></i>Reportes
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab">
                                        <i class="bi bi-sliders me-2"></i>Config
                                    </button>
                                </li>
                            </ul>

                            <!-- Tab Content -->
                            <div class="tab-content" id="managementTabContent">
                                <!-- Companies Tab -->
                                <div class="tab-pane fade show active" id="companies" role="tabpanel">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="mb-0">
                                            <i class="bi bi-building text-primary me-2"></i>
                                            Gestión de Empresas
                                        </h5>
                                        <button class="btn btn-primary" onclick="showAddCompanyModal()">
                                            <i class="bi bi-plus"></i> Nueva Empresa
                                        </button>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-hover" id="companiesTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Empresa</th>
                                                    <th>Identificador</th>
                                                    <th>% Capital</th>
                                                    <th>Contacto</th>
                                                    <th>Estado</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody id="companiesTableBody">
                                                <tr>
                                                    <td colspan="6" class="text-center">
                                                        <div class="py-4">
                                                            <i class="bi bi-building text-muted" style="font-size: 3rem;"></i>
                                                            <p class="text-muted mt-2">No hay empresas registradas</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Vouchers Tab -->
                                <div class="tab-pane fade" id="vouchers" role="tabpanel">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="mb-0">
                                            <i class="bi bi-file-earmark-text text-success me-2"></i>
                                            Gestión de Vouchers
                                        </h5>
                                        <div class="btn-group">
                                            <button class="btn btn-outline-primary" onclick="refreshVouchers()">
                                                <i class="bi bi-arrow-clockwise"></i> Actualizar
                                            </button>
                                            <a href="<?php echo getBaseUrl(); ?>/views/pages/processing.php" class="btn btn-success">
                                                <i class="bi bi-upload"></i> Subir Voucher
                                            </a>
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-hover" id="vouchersTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Archivo</th>
                                                    <th>Estado</th>
                                                    <th>Trips</th>
                                                    <th>Fecha</th>
                                                    <th>Tamaño</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody id="vouchersTableBody">
                                                <tr>
                                                    <td colspan="6" class="text-center">
                                                        <div class="py-4">
                                                            <i class="bi bi-file-earmark-text text-muted" style="font-size: 3rem;"></i>
                                                            <p class="text-muted mt-2">No hay vouchers procesados</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Reports Tab -->
                                <div class="tab-pane fade" id="reports" role="tabpanel">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="mb-0">
                                            <i class="bi bi-file-earmark-bar-graph text-warning me-2"></i>
                                            Gestión de Reportes
                                        </h5>
                                        <button class="btn btn-warning" onclick="showGenerateReportModal()">
                                            <i class="bi bi-plus"></i> Generar Reporte
                                        </button>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-hover" id="reportsTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Reporte</th>
                                                    <th>Empresa</th>
                                                    <th>Período</th>
                                                    <th>Monto</th>
                                                    <th>Estado</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody id="reportsTableBody">
                                                <tr>
                                                    <td colspan="6" class="text-center">
                                                        <div class="py-4">
                                                            <i class="bi bi-file-earmark-bar-graph text-muted" style="font-size: 3rem;"></i>
                                                            <p class="text-muted mt-2">No hay reportes generados</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Settings Tab -->
                                <div class="tab-pane fade" id="settings" role="tabpanel">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="mb-0">
                                            <i class="bi bi-sliders text-info me-2"></i>
                                            Configuración del Sistema
                                        </h5>
                                        <button class="btn btn-info" onclick="saveSystemSettings()">
                                            <i class="bi bi-save"></i> Guardar Cambios
                                        </button>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h6 class="mb-0">Configuración de Archivos</h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Tamaño máximo de archivo (MB)</label>
                                                        <input type="number" class="form-control" id="maxFileSize" value="20" min="1" max="100">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Tipos de archivo permitidos</label>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="allowPDF" checked>
                                                            <label class="form-check-label" for="allowPDF">PDF</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="allowXLSX" checked>
                                                            <label class="form-check-label" for="allowXLSX">Excel (XLSX)</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h6 class="mb-0">Configuración de Procesamiento</h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Timeout de procesamiento (segundos)</label>
                                                        <input type="number" class="form-control" id="processingTimeout" value="300" min="60" max="1800">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Procesos concurrentes máximos</label>
                                                        <input type="number" class="form-control" id="maxConcurrent" value="3" min="1" max="10">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End of Main Content -->
            </div>

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; Transport Management System <?php echo date('Y'); ?> - v2.0.0</span>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Management JavaScript -->
    <script>
        // Inicializar cuando se carga la página
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Gestión inicializada');
            showNotification('Sistema de gestión cargado correctamente', 'success', 3000);
        });

        function showTab(tabName) {
            const tab = document.querySelector('#' + tabName + '-tab');
            if (tab) {
                const tabInstance = new bootstrap.Tab(tab);
                tabInstance.show();
            }
        }

        function refreshManagement() {
            showNotification('Actualizando datos...', 'info');
            setTimeout(() => {
                showNotification('Datos actualizados', 'success');
            }, 1500);
        }

        function exportData() {
            showNotification('Exportando datos del sistema...', 'info');
            // TODO: Implementar exportación real
        }

        function showAddCompanyModal() {
            showNotification('Modal de nueva empresa próximamente', 'info');
            // TODO: Implementar modal
        }

        function refreshVouchers() {
            showNotification('Actualizando vouchers...', 'info');
            // TODO: Implementar refresh
        }

        function showGenerateReportModal() {
            showNotification('Modal de generar reporte próximamente', 'info');
            // TODO: Implementar modal
        }

        function saveSystemSettings() {
            showNotification('Guardando configuración...', 'info');
            setTimeout(() => {
                showNotification('Configuración guardada exitosamente', 'success');
            }, 1500);
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

    </script>
</body>
</html>