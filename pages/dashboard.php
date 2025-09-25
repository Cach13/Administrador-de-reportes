<?php
/**
 * dashboard.php
 * Dashboard Principal - Menú de Navegación
 * Transport Management System
 * UBICACIÓN: pages/dashboard.php
 */

// Incluir configuración y dependencias
require_once '../config/config.php';
require_once '../config/Database.php';
require_once '../config/AuthManager.php';

// Inicializar componentes
$auth = new AuthManager();
$db = Database::getInstance();

// Verificar que usuario esté logueado
$auth->requireLogin();

// Obtener usuario actual
$currentUser = $auth->getCurrentUser();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SYSTEM_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
            line-height: 1.6;
            min-height: 100vh;
        }
        
        /* HEADER */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        /* MAIN CONTAINER */
        .container {
            max-width: 1200px;
            margin: 3rem auto;
            padding: 0 2rem;
        }
        
        /* WELCOME MESSAGE */
        .welcome-section {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .welcome-title {
            font-size: 2.5rem;
            color: #2d3748;
            margin-bottom: 1rem;
            font-weight: 300;
        }
        
        .welcome-subtitle {
            font-size: 1.1rem;
            color: #718096;
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* MENU CARDS */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .menu-card {
            background: white;
            border-radius: 15px;
            padding: 2.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }
        
        .menu-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .menu-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            border-color: #667eea;
        }
        
        .menu-card.primary::before {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
        }
        
        .menu-card.secondary::before {
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
        }
        
        .menu-card.tertiary::before {
            background: linear-gradient(135deg, #9f7aea 0%, #805ad5 100%);
        }
        
        .menu-icon {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            display: block;
        }
        
        .menu-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 1rem;
        }
        
        .menu-description {
            color: #718096;
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        
        .menu-features {
            list-style: none;
            padding: 0;
            margin-bottom: 1.5rem;
        }
        
        .menu-features li {
            color: #4a5568;
            font-size: 0.9rem;
            padding: 0.3rem 0;
            position: relative;
            padding-left: 1.5rem;
        }
        
        .menu-features li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #48bb78;
            font-weight: bold;
        }
        
        .menu-action {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #667eea;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .menu-action::after {
            content: '→';
            transition: transform 0.3s ease;
        }
        
        .menu-card:hover .menu-action::after {
            transform: translateX(3px);
        }
        
        /* QUICK INFO */
        .quick-info {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .quick-info h3 {
            color: #2d3748;
            margin-bottom: 1rem;
        }
        
        .quick-info p {
            color: #718096;
            max-width: 500px;
            margin: 0 auto;
        }
        
        /* RESPONSIVE */
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
                margin: 2rem auto;
            }
            
            .menu-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .menu-card {
                padding: 2rem;
            }
            
            .welcome-title {
                font-size: 2rem;
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
        }
        
        /* ANIMATIONS */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .menu-card {
            animation: fadeInUp 0.6s ease-out forwards;
        }
        
        .menu-card:nth-child(1) { animation-delay: 0.1s; }
        .menu-card:nth-child(2) { animation-delay: 0.2s; }
        .menu-card:nth-child(3) { animation-delay: 0.3s; }
        .menu-card:nth-child(4) { animation-delay: 0.4s; }
    </style>
</head>
<body>
    <!-- HEADER -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <span>🚛</span>
                <span><?php echo SYSTEM_NAME; ?></span>
            </div>
            
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($currentUser['full_name'], 0, 2)); ?>
                </div>
                <div>
                    <div style="font-weight: bold;"><?php echo $currentUser['full_name']; ?></div>
                    <div style="font-size: 0.9rem; opacity: 0.8;"><?php echo ucfirst($currentUser['role']); ?></div>
                </div>
                <a href="../logout.php" class="logout-btn">Cerrar Sesión</a>
            </div>
        </div>
    </header>
    
    <!-- MAIN CONTAINER -->
    <div class="container">
        
        <!-- WELCOME SECTION -->
        <div class="welcome-section">
            <h1 class="welcome-title">Bienvenido, <?php echo explode(' ', $currentUser['full_name'])[0]; ?></h1>
            <p class="welcome-subtitle">
                Sistema de gestión de vouchers y generación de reportes. 
                Selecciona la opción que necesites para comenzar.
            </p>
        </div>
        
        <!-- MENU PRINCIPAL -->
        <div class="menu-grid">
            
            <!-- 1. SUBIR VOUCHER -->
            <a href="upload-voucher.php" class="menu-card primary">
                <span class="menu-icon">📤</span>
                <h2 class="menu-title">Subir Voucher</h2>
                <p class="menu-description">
                    Sube tu archivo PDF de Martin Marieta para extraer automáticamente los datos de viajes.
                </p>
                <ul class="menu-features">
                    <li>Extracción automática de datos</li>
                    <li>Validación de información</li>
                    <li>Análisis de confianza</li>
                    <li>Selección de empresa</li>
                </ul>
                <span class="menu-action">Comenzar proceso</span>
            </a>
            
            <!-- 2. HISTORIAL DE REPORTES -->
            <a href="reports-history.php" class="menu-card secondary">
                <span class="menu-icon">📊</span>
                <h2 class="menu-title">Historial de Reportes</h2>
                <p class="menu-description">
                    Consulta, descarga y gestiona todos los reportes generados organizados por empresa.
                </p>
                <ul class="menu-features">
                    <li>Filtros por empresa y fecha</li>
                    <li>Descarga de reportes</li>
                    <li>Estados de pago</li>
                    <li>Búsqueda avanzada</li>
                </ul>
                <span class="menu-action">Ver historial</span>
            </a>
            
            <!-- 3. GESTIÓN DE EMPRESAS -->
            <a href="manage-companies.php" class="menu-card tertiary">
                <span class="menu-icon">🏢</span>
                <h2 class="menu-title">Gestión de Empresas</h2>
                <p class="menu-description">
                    Administra las empresas registradas, sus porcentajes y configuraciones de pago.
                </p>
                <ul class="menu-features">
                    <li>Agregar/editar empresas</li>
                    <li>Configurar porcentajes</li>
                    <li>Datos bancarios</li>
                    <li>Identificadores únicos</li>
                </ul>
                <span class="menu-action">Administrar</span>
            </a>
            
            <!-- 4. CONFIGURACIÓN (OPCIONAL) -->
            <a href="settings.php" class="menu-card">
                <span class="menu-icon">⚙️</span>
                <h2 class="menu-title">Configuración</h2>
                <p class="menu-description">
                    Configuraciones generales del sistema y preferencias de usuario.
                </p>
                <ul class="menu-features">
                    <li>Configuración de usuario</li>
                    <li>Preferencias del sistema</li>
                    <li>Logs de actividad</li>
                    <li>Respaldos</li>
                </ul>
                <span class="menu-action">Configurar</span>
            </a>
            
        </div>
        
        <!-- QUICK INFO -->
        <div class="quick-info">
            <h3>Flujo de Trabajo</h3>
            <p>
                <strong>1.</strong> Sube tu voucher PDF &rarr; 
                <strong>2.</strong> Revisa datos extraídos &rarr; 
                <strong>3.</strong> Selecciona empresa &rarr; 
                <strong>4.</strong> Genera y descarga reporte
            </p>
        </div>
        
    </div>
    
    <script>
        // Mostrar mensaje de bienvenida si viene del login
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('login') === 'success') {
            // Remover parámetro de la URL
            window.history.replaceState({}, document.title, window.location.pathname);
            
            // Mostrar notificación
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
                color: white;
                padding: 1rem 1.5rem;
                border-radius: 8px;
                z-index: 1000;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                animation: slideInRight 0.3s ease-out;
            `;
            notification.innerHTML = `
                <div style="font-weight: bold;">¡Bienvenido!</div>
                <div style="font-size: 0.9rem; opacity: 0.9;">Sesión iniciada correctamente</div>
            `;
            
            // CSS para la animación
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
            `;
            document.head.appendChild(style);
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideInRight 0.3s ease-out reverse';
                setTimeout(() => notification.remove(), 300);
            }, 4000);
        }
    </script>
</body>
</html>