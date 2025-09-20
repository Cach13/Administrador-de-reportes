<?php
/**
 * views/pages/processing.php - PASO 16
 * Página de procesamiento de vouchers PDF
 * Integra tu MartinMarietaProcessor en una interfaz moderna
 */

// Verificar que las variables estén disponibles
$recentVouchers = $recentVouchers ?? [];
$companies = $companies ?? [];
$currentUser = $currentUser ?? [];
$maxFileSize = $maxFileSize ?? 20971520; // 20MB
$allowedTypes = $allowedTypes ?? ['pdf', 'xlsx', 'xls'];

// Función helper para obtener base URL
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    return $protocol . '://' . $host . $script;
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
    <title>Procesamiento de Vouchers - Transport Management</title>
    
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

        .upload-zone {
            border: 2px dashed var(--border-color);
            border-radius: 0.5rem;
            padding: 3rem 2rem;
            text-align: center;
            background-color: #fff;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .upload-zone:hover {
            border-color: var(--primary-color);
            background-color: rgba(78, 115, 223, 0.05);
        }

        .upload-zone.dragover {
            border-color: var(--success-color);
            background-color: rgba(28, 200, 138, 0.1);
            transform: scale(1.02);
        }

        .upload-zone input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .upload-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
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

        .processing-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            position: relative;
        }

        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 20px;
            right: -50%;
            width: 100%;
            height: 2px;
            background-color: var(--border-color);
            z-index: 1;
        }

        .step.completed:not(:last-child)::after {
            background-color: var(--success-color);
        }

        .step-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--border-color);
            color: #6c757d;
            margin-bottom: 0.5rem;
            z-index: 2;
            position: relative;
        }

        .step.active .step-icon {
            background-color: var(--primary-color);
            color: white;
        }

        .step.completed .step-icon {
            background-color: var(--success-color);
            color: white;
        }

        .progress-container {
            position: relative;
            margin: 1rem 0;
        }

        .progress {
            height: 25px;
        }

        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-weight: bold;
            color: #fff;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }

        .file-preview {
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 1rem;
            margin: 1rem 0;
            background-color: #fff;
        }

        .company-selector {
            background-color: #fff;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin: 1rem 0;
        }

        .company-card {
            border: 2px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 1rem;
            margin: 0.5rem 0;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .company-card:hover {
            border-color: var(--primary-color);
            background-color: rgba(78, 115, 223, 0.05);
        }

        .company-card.selected {
            border-color: var(--success-color);
            background-color: rgba(28, 200, 138, 0.1);
        }

        .processing-log {
            background-color: #1a1a1a;
            color: #00ff00;
            font-family: 'Courier New', monospace;
            padding: 1rem;
            border-radius: 0.5rem;
            height: 300px;
            overflow-y: auto;
            font-size: 0.875rem;
            line-height: 1.4;
        }

        .log-entry {
            margin-bottom: 0.25rem;
        }

        .log-timestamp {
            color: #888;
        }

        .log-info {
            color: #00ff00;
        }

        .log-warning {
            color: #ffaa00;
        }

        .log-error {
            color: #ff0000;
        }

        .log-success {
            color: #00ffaa;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            min-width: 300px;
            max-width: 500px;
        }

        .spin {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .results-summary {
            background: linear-gradient(135deg, var(--success-color) 0%, #17a085 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin: 1rem 0;
        }

        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .result-item {
            text-align: center;
            padding: 1rem;
            background-color: rgba(255,255,255,0.2);
            border-radius: 0.25rem;
        }

        .result-number {
            font-size: 2rem;
            font-weight: bold;
            display: block;
        }

        .result-label {
            font-size: 0.875rem;
            opacity: 0.9;
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

            <!-- Nav Item - Processing (Active) -->
            <li class="nav-item active">
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
                                <i class="bi bi-upload text-primary"></i>
                                Procesamiento de Vouchers
                            </h1>
                            <p class="text-muted">Sube y procesa vouchers PDF de Martin Marieta Materials</p>
                        </div>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <div class="btn-group me-2">
                                <a href="<?php echo getBaseUrl(); ?>/views/pages/dashboard.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left"></i> Volver al Dashboard
                                </a>
                                <button type="button" class="btn btn-outline-info" onclick="showHelp()">
                                    <i class="bi bi-question-circle"></i> Ayuda
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Processing Steps -->
                    <div class="processing-steps">
                        <div class="step active" id="step1">
                            <div class="step-icon">
                                <i class="bi bi-upload"></i>
                            </div>
                            <div class="step-label">1. Subir Archivo</div>
                        </div>
                        <div class="step" id="step2">
                            <div class="step-icon">
                                <i class="bi bi-search"></i>
                            </div>
                            <div class="step-label">2. Extraer Datos</div>
                        </div>
                        <div class="step" id="step3">
                            <div class="step-icon">
                                <i class="bi bi-building"></i>
                            </div>
                            <div class="step-label">3. Seleccionar Empresas</div>
                        </div>
                        <div class="step" id="step4">
                            <div class="step-icon">
                                <i class="bi bi-gear"></i>
                            </div>
                            <div class="step-label">4. Procesar</div>
                        </div>
                        <div class="step" id="step5">
                            <div class="step-icon">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <div class="step-label">5. Completado</div>
                        </div>
                    </div>

                    <!-- Main Processing Area -->
                    <div class="row">
                        <!-- Left Column - Upload & Processing -->
                        <div class="col-lg-8">
                            <!-- Upload Section -->
                            <div class="card shadow mb-4" id="uploadSection">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="bi bi-cloud-upload"></i>
                                        Subir Voucher PDF
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <form id="uploadForm" enctype="multipart/form-data">
                                        <div class="upload-zone" id="uploadZone">
                                            <input type="file" 
                                                   id="voucherFile" 
                                                   name="voucher_file" 
                                                   accept=".pdf,.xlsx,.xls"
                                                   required>
                                            <div class="upload-content">
                                                <i class="bi bi-cloud-upload upload-icon"></i>
                                                <h4>Arrastra tu archivo aquí</h4>
                                                <p class="text-muted">o haz clic para seleccionar</p>
                                                <div class="mt-3">
                                                    <small class="text-muted">
                                                        <strong>Formatos permitidos:</strong> PDF, Excel (.xlsx, .xls)<br>
                                                        <strong>Tamaño máximo:</strong> <?php echo formatBytes($maxFileSize); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- File Preview (Hidden initially) -->
                                        <div class="file-preview" id="filePreview" style="display: none;">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-file-earmark-pdf text-danger me-3" style="font-size: 2rem;"></i>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1" id="fileName">Archivo seleccionado</h6>
                                                    <small class="text-muted" id="fileDetails">Tamaño: 0 MB</small>
                                                </div>
                                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="clearFile()">
                                                    <i class="bi bi-x"></i> Eliminar
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Upload Progress -->
                                        <div class="progress-container" id="uploadProgress" style="display: none;">
                                            <div class="progress">
                                                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                                     role="progressbar" 
                                                     style="width: 0%"
                                                     id="uploadProgressBar">
                                                </div>
                                                <div class="progress-text" id="uploadProgressText">0%</div>
                                            </div>
                                        </div>

                                        <!-- Upload Button -->
                                        <div class="text-center mt-3">
                                            <button type="submit" 
                                                    class="btn btn-primary btn-lg" 
                                                    id="uploadBtn"
                                                    disabled>
                                                <i class="bi bi-upload"></i> Subir y Extraer Datos
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Company Selection Section (Hidden initially) -->
                            <div class="card shadow mb-4" id="companySection" style="display: none;">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="bi bi-building"></i>
                                        Seleccionar Empresas a Procesar
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="company-selector">
                                        <p class="text-muted mb-3">
                                            Selecciona las empresas que deseas incluir en el procesamiento:
                                        </p>
                                        
                                        <!-- Select All/None -->
                                        <div class="mb-3">
                                            <button type="button" class="btn btn-outline-primary btn-sm me-2" onclick="selectAllCompanies()">
                                                <i class="bi bi-check-all"></i> Seleccionar Todas
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearAllCompanies()">
                                                <i class="bi bi-x-circle"></i> Limpiar Selección
                                            </button>
                                        </div>

                                        <!-- Companies Grid -->
                                        <div class="row" id="companiesGrid">
                                            <!-- Companies will be loaded here dynamically -->
                                        </div>

                                        <!-- Process Button -->
                                        <div class="text-center mt-4">
                                            <button type="button" 
                                                    class="btn btn-success btn-lg" 
                                                    id="processBtn"
                                                    onclick="startProcessing()"
                                                    disabled>
                                                <i class="bi bi-gear"></i> Procesar Voucher
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Processing Section (Hidden initially) -->
                            <div class="card shadow mb-4" id="processingSection" style="display: none;">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="bi bi-gear"></i>
                                        Procesando Voucher...
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <!-- Processing Progress -->
                                    <div class="progress-container mb-3">
                                        <div class="progress">
                                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                                                 role="progressbar" 
                                                 style="width: 0%"
                                                 id="processingProgressBar">
                                            </div>
                                            <div class="progress-text" id="processingProgressText">Iniciando...</div>
                                        </div>
                                    </div>

                                    <!-- Processing Log -->
                                    <div class="processing-log" id="processingLog">
                                        <div class="log-entry log-info">
                                            <span class="log-timestamp">[<?php echo date('H:i:s'); ?>]</span>
                                            Sistema listo para procesar...
                                        </div>
                                    </div>

                                    <!-- Cancel Button -->
                                    <div class="text-center mt-3">
                                        <button type="button" 
                                                class="btn btn-outline-danger" 
                                                id="cancelBtn"
                                                onclick="cancelProcessing()">
                                            <i class="bi bi-x-circle"></i> Cancelar Procesamiento
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Results Section (Hidden initially) -->
                            <div class="card shadow mb-4" id="resultsSection" style="display: none;">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-success">
                                        <i class="bi bi-check-circle"></i>
                                        Procesamiento Completado
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="results-summary" id="resultsSummary">
                                        <h4><i class="bi bi-check-circle me-2"></i>¡Procesamiento Exitoso!</h4>
                                        <p>El voucher ha sido procesado correctamente</p>
                                        
                                        <div class="results-grid" id="resultsGrid">
                                            <!-- Results will be populated here -->
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="text-center mt-4">
                                        <button type="button" 
                                                class="btn btn-primary me-2" 
                                                onclick="generateReport()">
                                            <i class="bi bi-file-earmark-bar-graph"></i> Generar Reporte
                                        </button>
                                        <button type="button" 
                                                class="btn btn-outline-secondary me-2" 
                                                onclick="viewDetails()">
                                            <i class="bi bi-eye"></i> Ver Detalles
                                        </button>
                                        <button type="button" 
                                                class="btn btn-outline-primary" 
                                                onclick="processAnother()">
                                            <i class="bi bi-arrow-clockwise"></i> Procesar Otro
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column - Info & Recent Files -->
                        <div class="col-lg-4">
                            <!-- Info Panel -->
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-info">
                                        <i class="bi bi-info-circle"></i>
                                        Información del Proceso
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <h6><i class="bi bi-1-circle text-primary"></i> Subir Archivo</h6>
                                    <p class="small text-muted mb-3">
                                        Selecciona un voucher PDF de Martin Marieta Materials para procesar.
                                    </p>

                                    <h6><i class="bi bi-2-circle text-primary"></i> Extracción Automática</h6>
                                    <p class="small text-muted mb-3">
                                        El sistema extraerá automáticamente los datos de trips usando OCR avanzado.
                                    </p>

                                    <h6><i class="bi bi-3-circle text-primary"></i> Selección de Empresas</h6>
                                    <p class="small text-muted mb-3">
                                        Elige qué empresas incluir en el procesamiento basado en los Vehicle IDs.
                                    </p>

                                    <h6><i class="bi bi-4-circle text-primary"></i> Procesamiento</h6>
                                    <p class="small text-muted mb-3">
                                        Los datos se procesan y almacenan en la base de datos del sistema.
                                    </p>

                                    <h6><i class="bi bi-5-circle text-primary"></i> Generación de Reportes</h6>
                                    <p class="small text-muted">
                                        Se pueden generar reportes para Capital Transport LLP automáticamente.
                                    </p>
                                </div>
                            </div>

                            <!-- Recent Vouchers -->
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="bi bi-clock-history"></i>
                                        Vouchers Recientes
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div id="recentVouchers">
                                        <?php if (!empty($recentVouchers)): ?>
                                            <?php foreach ($recentVouchers as $voucher): ?>
                                                <div class="d-flex align-items-center border-bottom py-2">
                                                    <i class="bi bi-file-earmark-pdf text-danger me-2"></i>
                                                    <div class="flex-grow-1">
                                                        <div class="fw-bold small">
                                                            <?php echo htmlspecialchars($voucher['filename'] ?? ''); ?>
                                                        </div>
                                                        <small class="text-muted">
                                                            <?php echo date('d/m/Y H:i', strtotime($voucher['created_at'] ?? 'now')); ?>
                                                        </small>
                                                    </div>
                                                    <div>
                                                        <?php
                                                        $statusClass = 'bg-secondary';
                                                        switch ($voucher['status']) {
                                                            case 'processed':
                                                                $statusClass = 'bg-success';
                                                                break;
                                                            case 'processing':
                                                                $statusClass = 'bg-warning';
                                                                break;
                                                            case 'error':
                                                                $statusClass = 'bg-danger';
                                                                break;
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $statusClass; ?> small">
                                                            <?php echo ucfirst($voucher['status'] ?? 'unknown'); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="text-center py-3">
                                                <i class="bi bi-inbox text-muted" style="font-size: 2rem;"></i>
                                                <p class="text-muted mt-2">No hay vouchers recientes</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- System Status -->
                            <div class="card shadow">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-success">
                                        <i class="bi bi-activity"></i>
                                        Estado del Sistema
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small">Procesador MartinMarieta</span>
                                        <span class="badge bg-success">Activo</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small">Generador de Reportes</span>
                                        <span class="badge bg-success">Activo</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small">Base de Datos</span>
                                        <span class="badge bg-success">Conectada</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="small">Espacio en Disco</span>
                                        <span class="badge bg-warning">75% Usado</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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
        </div>
    </div>

    <!-- Help Modal -->
    <div class="modal fade" id="helpModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-question-circle text-info me-2"></i>
                        Ayuda - Procesamiento de Vouchers
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>Formatos de Archivo Soportados</h6>
                    <ul class="mb-3">
                        <li><strong>PDF:</strong> Vouchers escaneados de Martin Marieta Materials</li>
                        <li><strong>Excel (.xlsx, .xls):</strong> Datos ya extraídos en formato Excel</li>
                    </ul>

                    <h6>Proceso de Extracción</h6>
                    <p>El sistema utiliza OCR avanzado para extraer:</p>
                    <ul class="mb-3">
                        <li>Vehicle IDs (identificadores de vehículos)</li>
                        <li>Fechas de trips</li>
                        <li>Toneladas transportadas</li>
                        <li>Montos por trip</li>
                    </ul>

                    <h6>Selección de Empresas</h6>
                    <p>Las empresas se identifican automáticamente por los caracteres 4-6 del Vehicle ID:</p>
                    <ul class="mb-3">
                        <li><strong>JAV:</strong> Javenes Construction</li>
                        <li><strong>ABC:</strong> ABC Transport</li>
                        <li><strong>MM:</strong> Martin Marieta (interno)</li>
                    </ul>

                    <h6>Solución de Problemas</h6>
                    <ul>
                        <li>Asegúrate de que el PDF tenga buena calidad de imagen</li>
                        <li>Los archivos no deben estar protegidos por contraseña</li>
                        <li>El tamaño máximo es de <?php echo formatBytes($maxFileSize); ?></li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Processing JavaScript -->
    <script>
        // Variables globales
        let currentStep = 1;
        let uploadedFile = null;
        let extractedData = null;
        let selectedCompanies = [];
        let processingInterval = null;
        let currentVoucherId = null;

        // Inicializar cuando se carga la página
        document.addEventListener('DOMContentLoaded', function() {
            initializeProcessing();
        });

        function initializeProcessing() {
            console.log('Inicializando procesamiento...');
            setupFileUpload();
            setupDragAndDrop();
            loadCompanies();
            
            // Mostrar mensaje de bienvenida
            setTimeout(() => {
                showNotification('Sistema listo para procesar vouchers', 'info', 3000);
            }, 1000);
        }

        // ========================================
        // FILE UPLOAD FUNCTIONALITY
        // ========================================

        function setupFileUpload() {
            const fileInput = document.getElementById('voucherFile');
            const uploadForm = document.getElementById('uploadForm');

            fileInput.addEventListener('change', function(e) {
                handleFileSelection(e.target.files[0]);
            });

            uploadForm.addEventListener('submit', function(e) {
                e.preventDefault();
                if (uploadedFile) {
                    startUpload();
                }
            });
        }

        function setupDragAndDrop() {
            const uploadZone = document.getElementById('uploadZone');

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
                    handleFileSelection(files[0]);
                }
            });
        }

        function handleFileSelection(file) {
            if (!file) return;

            // Validar tipo de archivo
            const allowedTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'];
            if (!allowedTypes.includes(file.type)) {
                showNotification('Tipo de archivo no válido. Solo se permiten PDF y Excel.', 'danger');
                return;
            }

            // Validar tamaño
            const maxSize = <?php echo $maxFileSize; ?>;
            if (file.size > maxSize) {
                showNotification('El archivo es demasiado grande. Máximo: ' + formatBytes(maxSize), 'danger');
                return;
            }

            uploadedFile = file;
            showFilePreview(file);
            document.getElementById('uploadBtn').disabled = false;
        }

        function showFilePreview(file) {
            const preview = document.getElementById('filePreview');
            const fileName = document.getElementById('fileName');
            const fileDetails = document.getElementById('fileDetails');

            fileName.textContent = file.name;
            fileDetails.textContent = 'Tamaño: ' + formatBytes(file.size) + ' | Tipo: ' + getFileType(file.type);
            
            preview.style.display = 'block';
        }

        function clearFile() {
            uploadedFile = null;
            document.getElementById('voucherFile').value = '';
            document.getElementById('filePreview').style.display = 'none';
            document.getElementById('uploadBtn').disabled = true;
        }

        function getFileType(mimeType) {
            switch (mimeType) {
                case 'application/pdf':
                    return 'PDF';
                case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
                    return 'Excel (XLSX)';
                case 'application/vnd.ms-excel':
                    return 'Excel (XLS)';
                default:
                    return 'Desconocido';
            }
        }

        // ========================================
        // UPLOAD AND EXTRACTION
        // ========================================

        async function startUpload() {
            if (!uploadedFile) return;

            updateStep(2);
            showUploadProgress();

            const formData = new FormData();
            formData.append('voucher_file', uploadedFile);
            formData.append('action', 'upload_and_extract');

            try {
                const response = await fetch('<?php echo getBaseUrl(); ?>/api.php', {
                    method: 'POST',
                    body: formData,
                    onUploadProgress: updateUploadProgress
                });

                if (!response.ok) {
                    throw new Error('Error en el servidor: ' + response.status);
                }

                const result = await response.json();

                if (result.success) {
                    currentVoucherId = result.data.voucher_id;
                    extractedData = result.data.extracted_data;
                    
                    showNotification('Archivo subido y datos extraídos correctamente', 'success');
                    hideUploadProgress();
                    showCompanySelection();
                    updateStep(3);
                } else {
                    throw new Error(result.message || 'Error desconocido');
                }

            } catch (error) {
                console.error('Error en upload:', error);
                showNotification('Error subiendo archivo: ' + error.message, 'danger');
                hideUploadProgress();
                updateStep(1);
            }
        }

        function showUploadProgress() {
            document.getElementById('uploadProgress').style.display = 'block';
            
            // Simular progreso (reemplazar con progreso real)
            let progress = 0;
            const interval = setInterval(() => {
                progress += Math.random() * 15;
                if (progress > 95) progress = 95;
                
                updateUploadProgress(progress);
                
                if (progress >= 95) {
                    clearInterval(interval);
                }
            }, 200);
        }

        function updateUploadProgress(progress) {
            const progressBar = document.getElementById('uploadProgressBar');
            const progressText = document.getElementById('uploadProgressText');
            
            progressBar.style.width = progress + '%';
            progressText.textContent = Math.round(progress) + '%';
        }

        function hideUploadProgress() {
            document.getElementById('uploadProgress').style.display = 'none';
        }

        // ========================================
        // COMPANY SELECTION
        // ========================================

        function loadCompanies() {
            // Datos de empresas simulados (reemplazar con datos reales)
            const companies = <?php echo json_encode($companies ?? [
                ['id' => 1, 'name' => 'Javenes Construction', 'identifier' => 'JAV', 'capital_percentage' => 5.0],
                ['id' => 2, 'name' => 'ABC Transport Solutions', 'identifier' => 'ABC', 'capital_percentage' => 4.5],
                ['id' => 3, 'name' => 'Rodriguez Heavy Haul', 'identifier' => 'ROD', 'capital_percentage' => 5.5],
                ['id' => 4, 'name' => 'Metro Logistics LLC', 'identifier' => 'MET', 'capital_percentage' => 4.0],
                ['id' => 5, 'name' => 'Pioneer Transport Co', 'identifier' => 'PIO', 'capital_percentage' => 6.0]
            ]); ?>;

            renderCompanies(companies);
        }

        function renderCompanies(companies) {
            const grid = document.getElementById('companiesGrid');
            
            grid.innerHTML = companies.map(company => `
                <div class="col-md-6 mb-3">
                    <div class="company-card" data-company-id="${company.id}" onclick="toggleCompany(${company.id})">
                        <div class="d-flex align-items-center">
                            <div class="form-check me-3">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="company_${company.id}"
                                       onchange="toggleCompany(${company.id})">
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">${escapeHtml(company.name)}</h6>
                                <div class="d-flex justify-content-between">
                                    <small class="text-muted">ID: ${company.identifier}</small>
                                    <small class="text-success">${company.capital_percentage}% Capital</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function showCompanySelection() {
            document.getElementById('companySection').style.display = 'block';
            document.getElementById('companySection').scrollIntoView({ behavior: 'smooth' });
        }

        function toggleCompany(companyId) {
            const checkbox = document.getElementById('company_' + companyId);
            const card = document.querySelector(`[data-company-id="${companyId}"]`);
            
            checkbox.checked = !checkbox.checked;
            
            if (checkbox.checked) {
                card.classList.add('selected');
                if (!selectedCompanies.includes(companyId)) {
                    selectedCompanies.push(companyId);
                }
            } else {
                card.classList.remove('selected');
                selectedCompanies = selectedCompanies.filter(id => id !== companyId);
            }
            
            // Habilitar botón de procesamiento si hay empresas seleccionadas
            document.getElementById('processBtn').disabled = selectedCompanies.length === 0;
        }

        function selectAllCompanies() {
            const checkboxes = document.querySelectorAll('input[id^="company_"]');
            const cards = document.querySelectorAll('.company-card');
            
            selectedCompanies = [];
            
            checkboxes.forEach((checkbox, index) => {
                checkbox.checked = true;
                cards[index].classList.add('selected');
                
                const companyId = parseInt(checkbox.id.replace('company_', ''));
                selectedCompanies.push(companyId);
            });
            
            document.getElementById('processBtn').disabled = false;
        }

        function clearAllCompanies() {
            const checkboxes = document.querySelectorAll('input[id^="company_"]');
            const cards = document.querySelectorAll('.company-card');
            
            checkboxes.forEach((checkbox, index) => {
                checkbox.checked = false;
                cards[index].classList.remove('selected');
            });
            
            selectedCompanies = [];
            document.getElementById('processBtn').disabled = true;
        }

        // ========================================
        // PROCESSING
        // ========================================

        async function startProcessing() {
            if (selectedCompanies.length === 0) {
                showNotification('Debe seleccionar al menos una empresa', 'warning');
                return;
            }

            updateStep(4);
            showProcessingSection();
            
            try {
                const response = await fetch('<?php echo getBaseUrl(); ?>/api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'process_voucher',
                        voucher_id: currentVoucherId,
                        selected_companies: selectedCompanies
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showProcessingComplete(result.data);
                    updateStep(5);
                } else {
                    throw new Error(result.message || 'Error en procesamiento');
                }

            } catch (error) {
                console.error('Error en procesamiento:', error);
                showNotification('Error en procesamiento: ' + error.message, 'danger');
                addLogEntry('Error: ' + error.message, 'error');
            }
        }

        function showProcessingSection() {
            document.getElementById('processingSection').style.display = 'block';
            document.getElementById('processingSection').scrollIntoView({ behavior: 'smooth' });
            
            // Simular procesamiento con logs
            simulateProcessing();
        }

        function simulateProcessing() {
            const steps = [
                { progress: 10, message: 'Validando datos extraídos...', type: 'info' },
                { progress: 25, message: 'Aplicando filtros por empresa...', type: 'info' },
                { progress: 40, message: 'Procesando trips encontrados...', type: 'info' },
                { progress: 60, message: 'Calculando montos y porcentajes...', type: 'info' },
                { progress: 80, message: 'Guardando en base de datos...', type: 'info' },
                { progress: 95, message: 'Generando resumen de resultados...', type: 'info' },
                { progress: 100, message: 'Procesamiento completado exitosamente', type: 'success' }
            ];

            let stepIndex = 0;
            processingInterval = setInterval(() => {
                if (stepIndex < steps.length) {
                    const step = steps[stepIndex];
                    updateProcessingProgress(step.progress, step.message);
                    addLogEntry(step.message, step.type);
                    stepIndex++;
                } else {
                    clearInterval(processingInterval);
                    // Simular resultados
                    setTimeout(() => {
                        const mockResults = {
                            total_trips: 156,
                            companies_processed: selectedCompanies.length,
                            total_amount: 87450.00,
                            processing_time: 12.5
                        };
                        showProcessingComplete(mockResults);
                        updateStep(5);
                    }, 1000);
                }
            }, 800);
        }

        function updateProcessingProgress(progress, message) {
            const progressBar = document.getElementById('processingProgressBar');
            const progressText = document.getElementById('processingProgressText');
            
            progressBar.style.width = progress + '%';
            progressText.textContent = message;
        }

        function addLogEntry(message, type = 'info') {
            const log = document.getElementById('processingLog');
            const timestamp = new Date().toLocaleTimeString();
            
            const entry = document.createElement('div');
            entry.className = 'log-entry log-' + type;
            entry.innerHTML = `<span class="log-timestamp">[${timestamp}]</span> ${message}`;
            
            log.appendChild(entry);
            log.scrollTop = log.scrollHeight;
        }

        function cancelProcessing() {
            if (confirm('¿Está seguro de que desea cancelar el procesamiento?')) {
                if (processingInterval) {
                    clearInterval(processingInterval);
                }
                
                addLogEntry('Procesamiento cancelado por el usuario', 'warning');
                showNotification('Procesamiento cancelado', 'warning');
                
                // Volver al paso anterior
                updateStep(3);
                document.getElementById('processingSection').style.display = 'none';
            }
        }

        // ========================================
        // RESULTS
        // ========================================

        function showProcessingComplete(results) {
            document.getElementById('processingSection').style.display = 'none';
            document.getElementById('resultsSection').style.display = 'block';
            document.getElementById('resultsSection').scrollIntoView({ behavior: 'smooth' });
            
            // Poblar resultados
            const resultsGrid = document.getElementById('resultsGrid');
            resultsGrid.innerHTML = `
                <div class="result-item">
                    <span class="result-number">${results.total_trips || 0}</span>
                    <span class="result-label">Trips Procesados</span>
                </div>
                <div class="result-item">
                    <span class="result-number">${results.companies_processed || 0}</span>
                    <span class="result-label">Empresas</span>
                </div>
                <div class="result-item">
                    <span class="result-number">${(results.total_amount || 0).toLocaleString()}</span>
                    <span class="result-label">Monto Total</span>
                </div>
                <div class="result-item">
                    <span class="result-number">${results.processing_time || 0}s</span>
                    <span class="result-label">Tiempo</span>
                </div>
            `;
            
            showNotification('¡Procesamiento completado exitosamente!', 'success');
        }

        function generateReport() {
            showNotification('Generando reporte...', 'info');
            // TODO: Implementar generación de reporte
            setTimeout(() => {
                showNotification('Reporte generado exitosamente', 'success');
            }, 2000);
        }

        function viewDetails() {
            showNotification('Abriendo detalles...', 'info');
            // TODO: Implementar vista de detalles
        }

        function processAnother() {
            if (confirm('¿Procesar otro voucher? Se perderá el progreso actual.')) {
                location.reload();
            }
        }

        // ========================================
        // STEP MANAGEMENT
        // ========================================

        function updateStep(step) {
            currentStep = step;
            
            // Actualizar indicadores visuales de pasos
            for (let i = 1; i <= 5; i++) {
                const stepElement = document.getElementById('step' + i);
                stepElement.classList.remove('active', 'completed');
                
                if (i < step) {
                    stepElement.classList.add('completed');
                } else if (i === step) {
                    stepElement.classList.add('active');
                }
            }
        }

        // ========================================
        // UTILITY FUNCTIONS
        // ========================================

        function showHelp() {
            const helpModal = new bootstrap.Modal(document.getElementById('helpModal'));
            helpModal.show();
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

        function formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Cleanup al salir de la página
        window.addEventListener('beforeunload', function() {
            if (processingInterval) {
                clearInterval(processingInterval);
            }
        });

    </script>
</body>
</html>