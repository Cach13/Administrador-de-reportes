<?php
/**
 * Capital Transport Report Manager
 * Simple Professional Splash Screen
 */

session_start();

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: pages/dashboard.php');
    exit();
}

// Incluir configuración si existe
if (file_exists('config/config.php')) {
    require_once 'config/config.php';
}

// Definir constantes por defecto si no existen
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'Capital Transport Report Manager');
}
if (!defined('COMPANY_NAME')) {
    define('COMPANY_NAME', 'Capital Transport');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    
    <!-- Preload para mejor rendimiento -->
    <link rel="preload" href="login.php" as="document">
    <link rel="preload" href="assets/css/splash.css" as="style">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
     <!-- Splash CSS -->
    <link href="assets/css/splash.css" rel="stylesheet">
    
    
</head>
<body>
    <div class="splash-container" id="splashContainer">
        <div class="splash-content">
            <div class="logo-container">
                <div class="logo">
                    <?php if (file_exists('assets/img/logo.png')): ?>
                        <img src="assets/img/logo.png" alt="Logo">
                    <?php else: ?>
                        <i class="fas fa-truck"></i>
                    <?php endif; ?>
                </div>
            </div>
            
            <h1 class="welcome-text">Welcome to</h1>
            <h2 class="system-name">Capital Transport LLP Report Manager</h2>
            
            <div class="loading-bar">
                <div class="loading-progress"></div>
            </div>
            
            <p class="loading-text">Initializing system...</p>
        </div>
    </div>

    <script>
        // Auto redirect to login after animation
        setTimeout(() => {
            const container = document.getElementById('splashContainer');
            container.classList.add('fade-out');
            
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 800);
        }, 4500); // Total animation time: 4.5 seconds

        // Preload login page resources
        const link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = 'login.php';
        document.head.appendChild(link);
        
        // Preload login CSS
        const cssLink = document.createElement('link');
        cssLink.rel = 'prefetch';
        cssLink.href = 'assets/css/login.css';
        document.head.appendChild(cssLink);
    </script>
</body>
</html>