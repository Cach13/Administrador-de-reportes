<?php
/**
 * Login Page - Simple y Limpio
 */

session_start();

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: pages/dashboard.php');
    exit();
}

// Incluir configuración y clases
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Logger.php';
require_once 'classes/Auth.php';

$auth = new Auth();
$error_message = '';
$success_message = '';

// Verificar si viene de logout
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success_message = 'Sesión cerrada correctamente';
}

// Manejar el login
if ($_POST && isset($_POST['username']) && isset($_POST['password'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        $error_message = 'Por favor ingresa usuario y contraseña';
    } else {
        $result = $auth->login($username, $password, $remember);
        
        if ($result['success']) {
            // Login exitoso - redirigir
            $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'pages/dashboard.php';
            header('Location: ' . $redirect);
            exit();
        } else {
            $error_message = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME ?? 'Transport Management'; ?></title>
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body, html {
            height: 100%;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #2c2c2c 0%, #1a1a1a 100%);
            overflow: hidden;
        }

        .login-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 3rem 2.5rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transform: scale(0.9);
            opacity: 0;
            animation: slideIn 0.8s ease-out forwards;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .logo {
            width: 70px;
            height: 70px;
            background: #dc2626;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 8px 25px rgba(220, 38, 38, 0.3);
        }

        .logo img {
            width: 40px;
            height: 40px;
            object-fit: contain;
            filter: brightness(0) invert(1);
        }

        .logo i {
            font-size: 2rem;
            color: white;
        }

        .login-title {
            color: #2c2c2c;
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .login-subtitle {
            color: #666;
            font-size: 0.95rem;
            font-weight: 400;
        }

        .login-form {
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-label {
            display: block;
            color: #2c2c2c;
            font-weight: 500;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid #e5e5e5;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-input:focus {
            outline: none;
            border-color: #dc2626;
            background: white;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 1.1rem;
            margin-top: 0.75rem;
        }

        .login-button {
            width: 100%;
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(220, 38, 38, 0.3);
        }

        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(220, 38, 38, 0.4);
            background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .login-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            border: 1px solid #fecaca;
            text-align: center;
        }

        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            border: 1px solid #a7f3d0;
            text-align: center;
        }

        .loading-spinner {
            display: none;
            margin-right: 0.5rem;
        }

        .loading .loading-spinner {
            display: inline-block;
        }

        .loading .button-text {
            opacity: 0.7;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .checkbox-group input[type="checkbox"] {
            margin-right: 0.5rem;
        }

        .checkbox-group label {
            color: #666;
            font-size: 0.9rem;
            cursor: pointer;
        }

        .back-link {
            text-align: center;
            margin-top: 1rem;
        }

        .back-link a {
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .back-link a:hover {
            color: #dc2626;
        }

        .test-credentials {
            background: #f8f9fa;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.8rem;
            color: #666;
        }

        .test-credentials strong {
            color: #2c2c2c;
        }

        .test-credentials code {
            background: #e5e5e5;
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
        }

        /* Animations */
        @keyframes slideIn {
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .loading-spinner {
            animation: spin 1s linear infinite;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-container {
                padding: 1rem;
            }
            
            .login-card {
                padding: 2rem 1.5rem;
            }
            
            .login-title {
                font-size: 1.5rem;
            }
        }

        /* Input validation states */
        .form-input.error {
            border-color: #dc2626;
            background: #fef2f2;
        }

        .form-input.success {
            border-color: #059669;
            background: #f0fdf4;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <?php if (file_exists('assets/img/logo.png')): ?>
                        <img src="assets/img/logo.png" alt="Logo">
                    <?php else: ?>
                        <i class="fas fa-truck"></i>
                    <?php endif; ?>
                </div>
                <h1 class="login-title"><?php echo COMPANY_NAME ?? 'Capital Transport LLP'; ?></h1>
                <p class="login-subtitle">Report Manager System</p>
            </div>

            <?php if ($error_message): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <form class="login-form" method="POST" id="loginForm">
                <div class="form-group">
                    <label class="form-label" for="username">Usuario</label>
                    <div style="position: relative;">
                        <i class="fas fa-user input-icon"></i>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="form-input" 
                            placeholder="Ingresa tu usuario"
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                            required
                            autocomplete="username"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Contraseña</label>
                    <div style="position: relative;">
                        <i class="fas fa-lock input-icon"></i>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-input" 
                            placeholder="Ingresa tu contraseña"
                            required
                            autocomplete="current-password"
                        >
                    </div>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="remember" name="remember" value="1">
                    <label for="remember">Recordar sesión</label>
                </div>

                <button type="submit" class="login-button" id="loginButton">
                    <i class="fas fa-spinner loading-spinner"></i>
                    <span class="button-text">Iniciar Sesión</span>
                </button>
            </form>

            <div class="test-credentials">
                <strong>Credenciales de prueba:</strong><br>
                Usuario: <code>admin</code> | Contraseña: <code>admin123</code><br>
                Usuario: <code>operador</code> | Contraseña: <code>admin123</code>
            </div>

            <div class="back-link">
                <a href="splash.php">
                    <i class="fas fa-arrow-left"></i> Volver al inicio
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const button = document.getElementById('loginButton');
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');

            // Focus en username al cargar
            usernameInput.focus();

            // Manejar submit del form
            form.addEventListener('submit', function(e) {
                const username = usernameInput.value.trim();
                const password = passwordInput.value;
                
                // Validación básica
                if (!username || !password) {
                    e.preventDefault();
                    alert('Por favor completa todos los campos');
                    return;
                }
                
                // Mostrar loading state
                button.classList.add('loading');
                button.disabled = true;
                
                // En caso de error en servidor, remover loading después de tiempo
                setTimeout(() => {
                    button.classList.remove('loading');
                    button.disabled = false;
                }, 5000);
            });

            // Validación en tiempo real
            function validateInput(input) {
                if (input.value.trim().length > 0) {
                    input.classList.remove('error');
                    input.classList.add('success');
                } else {
                    input.classList.remove('success');
                }
            }

            usernameInput.addEventListener('input', () => validateInput(usernameInput));
            passwordInput.addEventListener('input', () => validateInput(passwordInput));

            // Enter para cambiar entre campos
            usernameInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    passwordInput.focus();
                }
            });

            // Auto-hide messages after 5 seconds
            setTimeout(() => {
                const messages = document.querySelectorAll('.error-message, .success-message');
                messages.forEach(msg => {
                    msg.style.transition = 'opacity 0.5s ease';
                    msg.style.opacity = '0';
                    setTimeout(() => {
                        if (msg.parentNode) {
                            msg.parentNode.removeChild(msg);
                        }
                    }, 500);
                });
            }, 5000);
        });
    </script>
</body>
</html>