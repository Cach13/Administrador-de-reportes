<?php
/**
 * index.php
 * Entry point - Splash Screen → Login Flow
 * Transport Management System
 */
session_start();
session_unset();
session_destroy();
setcookie(session_name(), '', time()-3600, '/');
session_start();

// Verificar archivos
if (!file_exists('config/config.php')) {
    die("ERROR: No existe config/config.php");
}
if (!file_exists('config/Database.php')) {
    die("ERROR: No existe config/Database.php");
}
if (!file_exists('config/AuthManager.php')) {
    die("ERROR: No existe config/AuthManager.php");
}

// Incluir dependencias
require_once 'config/config.php';
require_once 'config/Database.php'; 
require_once 'config/AuthManager.php';


// Incluir dependencias
// Incluir dependencias EN EL ORDEN CORRECTO
require_once 'config/config.php';
require_once 'config/Database.php'; 
require_once 'config/AuthManager.php'; // ← DESPUÉS AuthManager;


// Inicializar componentes
try {
    $auth = new AuthManager();
    $db = Database::getInstance();
    
    // Verificar estructura de BD
    $dbCheck = $db->checkDatabaseStructure();
    if ($dbCheck !== true) {
        throw new Exception("Base de datos no configurada correctamente. Tablas faltantes: " . implode(', ', $dbCheck));
    }
    
} catch (Exception $e) {
    die("Error del sistema: " . $e->getMessage() . "<br><small>Revisa la configuración de la base de datos.</small>");
}

// Si ya está logueado, redirigir al dashboard
if ($auth->isLoggedIn()) {
    header('Location: pages/dashboard.php');
    exit;
}

// Verificar si mostrar splash o ir directo al login
$showSplash = !isset($_GET['skip_splash']);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transport Management System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        /* SPLASH SCREEN */
        .splash-container {
            text-align: center;
            color: white;
            animation: fadeInUp 1s ease-out;
        }
        
        .splash-logo {
            width: 120px;
            height: 120px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            margin: 0 auto 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            border: 3px solid rgba(255,255,255,0.2);
            animation: pulse 2s infinite;
        }
        
        .splash-title {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 300;
            letter-spacing: 2px;
        }
        
        .splash-subtitle {
            font-size: 1.1rem;
            opacity: 0.8;
            margin-bottom: 40px;
        }
        
        .splash-loading {
            display: flex;
            justify-content: center;
            margin: 40px 0;
        }
        
        .loading-dots {
            display: flex;
            gap: 8px;
        }
        
        .loading-dot {
            width: 12px;
            height: 12px;
            background: white;
            border-radius: 50%;
            opacity: 0.4;
            animation: loadingDot 1.5s infinite;
        }
        
        .loading-dot:nth-child(2) { animation-delay: 0.2s; }
        .loading-dot:nth-child(3) { animation-delay: 0.4s; }
        
        /* LOGIN FORM */
        .login-container {
            display: none;
            background: rgba(255,255,255,0.95);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 400px;
            width: 100%;
            animation: fadeInUp 0.5s ease-out;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }
        
        .login-title {
            color: #333;
            font-size: 1.8rem;
            margin-bottom: 5px;
        }
        
        .login-subtitle {
            color: #666;
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn:disabled {
            opacity: 0.7;
            transform: none;
            cursor: not-allowed;
        }
        
        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .alert-danger {
            background: #fee;
            color: #c53030;
            border: 1px solid #fecaca;
        }
        
        .alert-success {
            background: #efe;
            color: #38a169;
            border: 1px solid #c6f6d5;
        }
        
        /* ANIMACIONES */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        @keyframes loadingDot {
            0%, 80%, 100% { opacity: 0.4; }
            40% { opacity: 1; }
        }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        
        /* RESPONSIVE */
        @media (max-width: 480px) {
            .login-container {
                margin: 20px;
                padding: 30px 20px;
            }
            
            .splash-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    
    <!-- SPLASH SCREEN -->
    <?php if ($showSplash): ?>
    <div id="splash" class="splash-container">
        <div class="splash-logo">🚛</div>
        <h1 class="splash-title">TRANSPORT</h1>
        <p class="splash-subtitle">Management System</p>
        
        <div class="splash-loading">
            <div class="loading-dots">
                <div class="loading-dot"></div>
                <div class="loading-dot"></div>
                <div class="loading-dot"></div>
            </div>
        </div>
        
        <p style="opacity: 0.6; font-size: 0.9rem; margin-top: 20px;">
            Cargando sistema...
        </p>
    </div>
    <?php endif; ?>
    
    <!-- LOGIN FORM -->
    <div id="loginForm" class="login-container" <?php echo $showSplash ? '' : 'style="display: block;"'; ?>>
        <div class="login-header">
            <div class="login-logo">🚛</div>
            <h2 class="login-title">Iniciar Sesión</h2>
            <p class="login-subtitle">Transport Management System</p>
        </div>
        
        <!-- Mensajes de error/éxito -->
        <div id="alertContainer"></div>
        
        <form id="loginFormElement" method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Usuario o Email</label>
                <input type="text" id="username" name="username" class="form-control" 
                       placeholder="Ingresa tu usuario o email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" class="form-control" 
                       placeholder="Ingresa tu contraseña" required>
            </div>
            
            <button type="submit" class="btn" id="loginBtn">
                <span id="loginBtnText">Iniciar Sesión</span>
            </button>
        </form>
        
        <div style="text-align: center; margin-top: 20px; font-size: 0.8rem; color: #666;">
            Transport Management System v1.0<br>
            <small><?php echo date('Y'); ?> - Todos los derechos reservados</small>
        </div>
    </div>
    

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($showSplash): ?>
            // Mostrar splash por 3 segundos, luego mostrar login
            setTimeout(() => {
                const splash = document.getElementById('splash');
                const loginForm = document.getElementById('loginForm');
                
                splash.style.animation = 'fadeOut 0.5s ease-out forwards';
                
                setTimeout(() => {
                    splash.style.display = 'none';
                    loginForm.style.display = 'block';
                }, 500);
            }, 3000);
            <?php endif; ?>
            
            // Manejar envío del formulario
            const form = document.getElementById('loginFormElement');
            const btn = document.getElementById('loginBtn');
            const btnText = document.getElementById('loginBtnText');
            const alertContainer = document.getElementById('alertContainer');
            
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Deshabilitar botón
                btn.disabled = true;
                btnText.textContent = 'Iniciando sesión...';
                
                // Limpiar alertas
                alertContainer.innerHTML = '';
                
                // Enviar datos
                const formData = new FormData(form);
                
                fetch('login.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Sesión iniciada correctamente. Redirigiendo...', 'success');
                        setTimeout(() => {
                            window.location.href = 'pages/dashboard.php';
                        }, 1000);
                    } else {
                        showAlert(data.message || 'Error al iniciar sesión', 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error de conexión. Intenta nuevamente.', 'danger');
                })
                .finally(() => {
                    // Rehabilitar botón
                    btn.disabled = false;
                    btnText.textContent = 'Iniciar Sesión';
                });
            });
            
            // Función para mostrar alertas
            function showAlert(message, type) {
                const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
                alertContainer.innerHTML = `
                    <div class="alert ${alertClass}">
                        ${message}
                    </div>
                `;
            }
            
            // Focus automático en el primer campo
            setTimeout(() => {
                document.getElementById('username').focus();
            }, <?php echo $showSplash ? '3500' : '100'; ?>);
        });
    </script>
</body>
</html>