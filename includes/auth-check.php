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
require_once dirname(__DIR__) . '/classes/Database.php';
require_once dirname(__DIR__) . '/classes/Logger.php';
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

// ========================================
// FUNCIONES DE AUTENTICACIÓN Y PERMISOS
// ========================================

/**
 * Verificar permisos del usuario
 */
function hasPermission($permission) {
    if (!isset($_SESSION['role'])) {
        return false;
    }
    
    $userPermissions = PERMISSIONS[$_SESSION['role']] ?? [];
    return in_array($permission, $userPermissions);
}

/**
 * Requerir permiso específico
 */
function requirePermission($permission) {
    if (!hasPermission($permission)) {
        header('HTTP/1.1 403 Forbidden');
        die('Acceso denegado: No tienes permisos para realizar esta acción.');
    }
}

/**
 * Verificar si es admin
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Verificar si es operador
 */
function isOperator() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'operator';
}

/**
 * Función helper para verificar permisos (alias)
 */
function checkPermission($permission) {
    return hasPermission($permission);
}

/**
 * Función helper para mostrar nombre del usuario
 */
function getUserDisplayName() {
    global $full_name, $username;
    return !empty($full_name) ? $full_name : $username;
}

/**
 * Función helper para obtener rol del usuario en español
 */
function getUserRoleText() {
    global $user_role;
    return ROLES[$user_role] ?? $user_role;
}

/**
 * Función para cerrar sesión
 */
function logout() {
    global $auth;
    $auth->logout();
}

/**
 * Función para obtener información completa del usuario
 */
function getCurrentUserInfo() {
    global $current_user;
    return $current_user;
}

/**
 * Función para verificar si la sesión está activa
 */
function isSessionActive() {
    global $auth;
    return $auth->isAuthenticated();
}

/**
 * Función para renovar sesión
 */
function renewSession() {
    if (isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
    }
}

/**
 * Función para obtener tiempo restante de sesión
 */
function getSessionTimeRemaining() {
    if (!isset($_SESSION['last_activity'])) {
        return 0;
    }
    
    $elapsed = time() - $_SESSION['last_activity'];
    $remaining = SESSION_TIMEOUT - $elapsed;
    
    return max(0, $remaining);
}

/**
 * Función para verificar si la sesión expirará pronto
 */
function isSessionExpiringSoon($threshold = 300) { // 5 minutos
    $remaining = getSessionTimeRemaining();
    return $remaining > 0 && $remaining <= $threshold;
}

// Renovar automáticamente la sesión en cada verificación
renewSession();

// Prevenir acceso directo a este archivo
if (basename($_SERVER['PHP_SELF']) == 'auth-check.php') {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso directo no permitido');
}
?>