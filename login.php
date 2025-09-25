<?php
/**
 * login.php
 * Procesador de Login - Maneja la autenticación
 * Transport Management System
 */

// Headers para JSON response
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

// Incluir dependencias
require_once 'config/Database.php';
require_once 'config/AuthManager.php';

try {
    // Inicializar componentes
    $auth = new AuthManager();
    $db = Database::getInstance();
    
    // Verificar conexión a BD
    if (!$db->isConnected()) {
        throw new Exception("No se pudo conectar a la base de datos");
    }
    
    // Obtener datos del POST
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validación básica
    if (empty($username) || empty($password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Usuario y contraseña son requeridos'
        ]);
        exit;
    }
    
    // Rate limiting básico por IP
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimitKey = "login_attempts_{$clientIP}";
    
    // Verificar intentos por IP en los últimos 15 minutos
    $recentAttempts = $db->select(
        "SELECT COUNT(*) as attempts FROM activity_logs 
         WHERE action = 'LOGIN_FAILED' 
         AND ip_address = ? 
         AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
        [$clientIP]
    );
    
    if ($recentAttempts && $recentAttempts[0]['attempts'] >= 10) {
        echo json_encode([
            'success' => false,
            'message' => 'Demasiados intentos fallidos desde esta IP. Intente más tarde.'
        ]);
        exit;
    }
    
    // Intentar login
    $loginResult = $auth->login($username, $password);
    
    // Verificar resultado
    if ($loginResult['success']) {
        // Login exitoso
        echo json_encode([
            'success' => true,
            'message' => $loginResult['message'],
            'user' => $loginResult['user'],
            'redirect' => 'dashboard.php'
        ]);
        
        // Log adicional de éxito
        $auth->logActivity(
            $loginResult['user']['id'], 
            'LOGIN_SUCCESS_API', 
            "Login exitoso vía API desde {$clientIP}"
        );
        
    } else {
        // Login fallido
        echo json_encode([
            'success' => false,
            'message' => $loginResult['message']
        ]);
        
        // Log adicional de fallo
        $auth->logActivity(
            null, 
            'LOGIN_FAILED_API', 
            "Intento de login fallido vía API: {$username} desde {$clientIP}"
        );
    }
    
} catch (Exception $e) {
    // Error del sistema
    error_log("Login Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del sistema. Intente más tarde.'
    ]);
    
    // Log error crítico
    if (isset($auth)) {
        $auth->logActivity(
            null, 
            'LOGIN_ERROR', 
            "Error crítico en login: " . $e->getMessage()
        );
    }
}
?>