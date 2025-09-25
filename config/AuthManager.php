<?php
/**
 * AuthManager.php
 * Sistema de Autenticación y Manejo de Sesiones
 * Transport Management System
 */

class AuthManager {
    
    private $db;
    private $maxLoginAttempts = 5;
    private $lockoutTime = 15; // minutos
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->startSession();
    }
    
    /**
     * Iniciar sesión segura
     */
    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Configuración segura de sesión
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', 0); // Cambiar a 1 en HTTPS
            ini_set('session.use_strict_mode', 1);
            
            session_start();
            
            // Regenerar ID de sesión para seguridad
            if (!isset($_SESSION['initiated'])) {
                session_regenerate_id(true);
                $_SESSION['initiated'] = true;
            }
        }
    }
    
    /**
     * Intentar login
     */
    public function login($username, $password) {
        try {
            // Limpiar datos de entrada
            $username = trim($username);
            
            // Validar datos básicos
            if (empty($username) || empty($password)) {
                return [
                    'success' => false,
                    'message' => 'Usuario y contraseña son requeridos'
                ];
            }
            
            // Obtener usuario
            $user = $this->getUserByUsername($username);
            
            if (!$user) {
                // Log intento fallido
                $this->logActivity(null, 'LOGIN_FAILED', "Intento de login con usuario inexistente: {$username}");
                return [
                    'success' => false,
                    'message' => 'Credenciales incorrectas'
                ];
            }
            
            // Verificar si cuenta está bloqueada
            if ($this->isAccountLocked($user)) {
                return [
                    'success' => false,
                    'message' => 'Cuenta bloqueada temporalmente. Intente más tarde.'
                ];
            }
            
            // Verificar si cuenta está activa
            if (!$user['is_active']) {
                return [
                    'success' => false,
                    'message' => 'Cuenta desactivada. Contacte al administrador.'
                ];
            }
            
            // Verificar contraseña
            if (!password_verify($password, $user['password_hash'])) {
                // Incrementar intentos fallidos
                $this->incrementFailedAttempts($user['id']);
                
                $this->logActivity($user['id'], 'LOGIN_FAILED', "Intento de login con contraseña incorrecta");
                
                return [
                    'success' => false,
                    'message' => 'Credenciales incorrectas'
                ];
            }
            
            // Login exitoso
            $this->handleSuccessfulLogin($user);
            
            return [
                'success' => true,
                'message' => 'Login exitoso',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role'],
                    'email' => $user['email']
                ]
            ];
            
        } catch (Exception $e) {
            error_log("AuthManager Login Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error interno del sistema'
            ];
        }
    }
    
    /**
     * Manejar login exitoso
     */
    private function handleSuccessfulLogin($user) {
        // Limpiar intentos fallidos
        $this->clearFailedAttempts($user['id']);
        
        // Actualizar último login
        $this->updateLastLogin($user['id']);
        
        // Crear sesión
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['login_time'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Log actividad
        $this->logActivity($user['id'], 'LOGIN_SUCCESS', "Login exitoso desde IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }
    
    /**
     * Cerrar sesión
     */
    public function logout() {
        if ($this->isLoggedIn()) {
            $user_id = $_SESSION['user_id'];
            $username = $_SESSION['username'];
            
            // Log actividad
            $this->logActivity($user_id, 'LOGOUT', "Usuario {$username} cerró sesión");
            
            // Limpiar sesión
            $_SESSION = [];
            
            // Destruir cookie de sesión
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            // Destruir sesión
            session_destroy();
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Verificar si usuario está logueado
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Obtener usuario actual
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'full_name' => $_SESSION['full_name'],
            'role' => $_SESSION['role'],
            'email' => $_SESSION['email'],
            'login_time' => $_SESSION['login_time'] ?? null,
            'ip_address' => $_SESSION['ip_address'] ?? null
        ];
    }
    
    /**
     * Verificar si usuario tiene rol específico
     */
    public function hasRole($role) {
        return $this->isLoggedIn() && $_SESSION['role'] === $role;
    }
    
    /**
     * Verificar si usuario es admin
     */
    public function isAdmin() {
        return $this->hasRole('admin');
    }
    
    /**
     * Obtener usuario por username
     */
    private function getUserByUsername($username) {
        $query = "SELECT * FROM users WHERE username = ? OR email = ?";
        return $this->db->selectOne($query, [$username, $username]);
    }
    
    /**
     * Verificar si cuenta está bloqueada
     */
    private function isAccountLocked($user) {
        if ($user['failed_login_attempts'] >= $this->maxLoginAttempts) {
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Incrementar intentos fallidos
     */
    private function incrementFailedAttempts($userId) {
        $attempts = $this->db->selectOne("SELECT failed_login_attempts FROM users WHERE id = ?", [$userId])['failed_login_attempts'] + 1;
        
        $lockedUntil = null;
        if ($attempts >= $this->maxLoginAttempts) {
            $lockedUntil = date('Y-m-d H:i:s', time() + ($this->lockoutTime * 60));
        }
        
        $this->db->update(
            "UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?",
            [$attempts, $lockedUntil, $userId]
        );
    }
    
    /**
     * Limpiar intentos fallidos
     */
    private function clearFailedAttempts($userId) {
        $this->db->update(
            "UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?",
            [$userId]
        );
    }
    
    /**
     * Actualizar último login
     */
    private function updateLastLogin($userId) {
        $this->db->update(
            "UPDATE users SET last_login = NOW() WHERE id = ?",
            [$userId]
        );
    }
    
    /**
     * Registrar actividad
     */
    public function logActivity($userId, $action, $description, $ipAddress = null) {
        $ipAddress = $ipAddress ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $this->db->insert(
            "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)",
            [$userId, $action, $description, $ipAddress, $userAgent]
        );
    }
    
    /**
     * Requerir login
     */
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            // Detectar si estamos en una subcarpeta
            $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
            $rootPath = ($scriptDir === '/' || $scriptDir === '') ? '' : '..';
            
            header("Location: {$rootPath}/index.php");
            exit;
        }
    }
    
    /**
     * Requerir rol admin
     */
    public function requireAdmin() {
        $this->requireLogin();
        if (!$this->isAdmin()) {
            header('Location: pages/dashboard.php?error=access_denied');
            exit;
        }
    }
    
    /**
     * Generar token CSRF
     */
    public function generateCsrfToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verificar token CSRF
     */
    public function verifyCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Validar sesión (verificar que sigue siendo válida)
     */
    public function validateSession() {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        // Verificar timeout de sesión (4 horas)
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 14400)) {
            $this->logout();
            return false;
        }
        
        return true;
    }
}