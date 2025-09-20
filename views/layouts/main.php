<?php
// ========================================
// views/layouts/main.php
// Layout principal del sistema
// ========================================

$pageTitle = $pageTitle ?? 'Transport Management System';
$currentUser = $_SESSION['user'] ?? null;
$flashMessage = App\Utils\ResponseHelper::getFlashMessage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo App\Utils\ResponseHelper::generateCSRFToken(); ?>">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    
    <!-- CSS Framework - Bootstrap 5 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Icons - Bootstrap Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.0/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="/public/assets/css/app.css" rel="stylesheet">
    
    <!-- Additional CSS per page -->
    <?php if (isset($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <link href="<?php echo $css; ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="bg-light">
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="/dashboard">
                <i class="bi bi-truck me-2"></i>
                Capital Transport
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <!-- Main Navigation -->
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($activePage ?? '') === 'dashboard' ? 'active' : ''; ?>" 
                           href="/dashboard">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($activePage ?? '') === 'processing' ? 'active' : ''; ?>" 
                           href="/processing">
                            <i class="bi bi-upload me-1"></i>Procesar Archivos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($activePage ?? '') === 'management' ? 'active' : ''; ?>" 
                           href="/management">
                            <i class="bi bi-building me-1"></i>Gestión
                        </a>
                    </li>
                </ul>
                
                <!-- User Menu -->
                <?php if ($currentUser): ?>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i>
                            <?php echo htmlspecialchars($currentUser['full_name'] ?? 'Usuario'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <span class="dropdown-item-text text-muted">
                                    <small><?php echo htmlspecialchars($currentUser['email'] ?? ''); ?></small>
                                </span>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="/profile">
                                    <i class="bi bi-person me-2"></i>Mi Perfil
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="/settings">
                                    <i class="bi bi-gear me-2"></i>Configuración
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="/logout">
                                    <i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
                <?php else: ?>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="/login">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Iniciar Sesión
                        </a>
                    </li>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Flash Messages -->
    <?php if ($flashMessage): ?>
    <div class="container-fluid mt-3">
        <div class="alert alert-<?php echo $flashMessage['type'] === 'error' ? 'danger' : $flashMessage['type']; ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?php echo $flashMessage['type'] === 'error' ? 'exclamation-triangle' : ($flashMessage['type'] === 'success' ? 'check-circle' : 'info-circle'); ?> me-2"></i>
            <?php echo htmlspecialchars($flashMessage['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Page Breadcrumb -->
    <?php if (isset($breadcrumb) && !empty($breadcrumb)): ?>
    <div class="container-fluid mt-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-white p-3 rounded shadow-sm">
                <li class="breadcrumb-item">
                    <a href="/dashboard" class="text-decoration-none">
                        <i class="bi bi-house me-1"></i>Inicio
                    </a>
                </li>
                <?php foreach ($breadcrumb as $item): ?>
                    <?php if (isset($item['url'])): ?>
                        <li class="breadcrumb-item">
                            <a href="<?php echo $item['url']; ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($item['title']); ?>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="breadcrumb-item active" aria-current="page">
                            <?php echo htmlspecialchars($item['title']); ?>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ol>
        </nav>
    </div>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="container-fluid py-4">
        <!-- Page Header -->
        <?php if (isset($pageHeader)): ?>
        <div class="row mb-4">
            <div class="col">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2 mb-1 text-gray-800"><?php echo htmlspecialchars($pageHeader['title']); ?></h1>
                        <?php if (isset($pageHeader['subtitle'])): ?>
                            <p class="text-muted mb-0"><?php echo htmlspecialchars($pageHeader['subtitle']); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if (isset($pageHeader['actions'])): ?>
                    <div>
                        <?php echo $pageHeader['actions']; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Page Content -->
        <div class="row">
            <div class="col">
                <?php 
               // Include the page content
                if (isset($contentFile)) {
                    include $contentFile;
                } elseif (isset($dashboardContent)) {
                    echo $dashboardContent;
                } else {
                    echo $content ?? '';
                }
                ?>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-top mt-5 py-4">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <p class="text-muted mb-0">
                        <i class="bi bi-c-circle me-1"></i>
                        2025 Capital Transport LLP - Sistema de Gestión de Transportes
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-muted mb-0">
                        Versión <?php echo APP_VERSION ?? '2.0.0'; ?>
                        <span class="mx-2">•</span>
                        <a href="/help" class="text-decoration-none text-muted">Ayuda</a>
                        <span class="mx-2">•</span>
                        <a href="/support" class="text-decoration-none text-muted">Soporte</a>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <!-- Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    
    <!-- Chart.js (para gráficos) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="/public/assets/js/app.js"></script>
    
    <!-- Additional JavaScript per page -->
    <?php if (isset($additionalJS)): ?>
        <?php foreach ($additionalJS as $js): ?>
            <script src="<?php echo $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Inline JavaScript -->
    <?php if (isset($inlineJS)): ?>
    <script>
        <?php echo $inlineJS; ?>
    </script>
    <?php endif; ?>

    <!-- Global JavaScript variables -->
    <script>
        window.App = {
            baseUrl: '<?php echo getBaseUrl() ?? ''; ?>',
            csrfToken: '<?php echo App\Utils\ResponseHelper::generateCSRFToken(); ?>',
            user: <?php echo json_encode($currentUser ?? null); ?>,
            isDebug: <?php echo json_encode(isDebugMode() ?? false); ?>
        };
    </script>
</body>
</html>