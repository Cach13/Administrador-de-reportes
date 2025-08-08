<?php
/**
 * Auth Check - Verificación de autenticación
 * Este archivo debe incluirse en todas las páginas protegidas
 */

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Incluir configuración y clases necesarias
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/classes/Auth.php';

$auth = new Auth();

// Verificar autenticación
if (!$auth->isAuthenticated()) {
    // Usuario no autenticado - redirigir al login
    $redirect_url = '/login.php';
    
    // Si hay una URL específica que se intentaba acceder, guardarla
    if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] !== '/') {
        $redirect_url .= '?redirect=' . urlencode($_SERVER['REQUEST_URI']);
    }
    
    header('Location: ' . $redirect_url);
    exit();
}

// Obtener información del usuario actual
$current_user = $auth->getCurrentUser();

// Definir variables globales para usar en las páginas
$user_id = $current_user['id'];
$username = $current_user['username'];
$full_name = $current_user['full_name'];
$user_role = $current_user['role'];
$is_admin = ($user_role === 'admin');
$is_operator = ($user_role === 'operator');

// Función helper para verificar permisos
function checkPermission($permission) {
    global $auth;
    return $auth->hasPermission($permission);
}

// Función helper para requerir permisos
function requirePermission($permission) {
    global $auth;
    $auth->requirePermission($permission);
}

// Función para mostrar nombre del usuario
function getUserDisplayName() {
    global $full_name, $username;
    return !empty($full_name) ? $full_name : $username;
}

// Función para obtener rol del usuario en español
function getUserRoleText() {
    global $user_role;
    return ROLES[$user_role] ?? $user_role;
}

// Prevenir acceso directo a este archivo
if (basename($_SERVER['PHP_SELF']) == 'auth-check.php') {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso directo no permitido');
}
?>