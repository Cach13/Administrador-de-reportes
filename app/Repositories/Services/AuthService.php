<?php
// ========================================
// app/Services/AuthService.php - PASO 8
// Servicio centralizado de autenticación
// ========================================

namespace App\Services;

use Database;
use Logger;
use Exception;

/**
 * AuthService - Manejo centralizado de autenticación
 * 
 * Todas las operaciones de autenticación y autorización:
 * - Login/Logout
 * - Verificación de permisos
 * - Gestión de sesiones
 * - Seguridad y tokens
 * - Password hashing
 */
class AuthService
{
    /** @var Database */
    private $db;
    
    /** @var Logger */
    private $logger;
    
    /** @var array */
    private $config;
    
    /** @var int Intentos máximos de login */
    private const MAX_LOGIN_ATTEMPTS = 5;
    
    /** @var int Tiempo de bloqueo en segundos */
    private const LOCKOUT_TIME = 900; // 15 minutos
    
    /** @var int Tiempo de sesión en segundos */
    private const SESSION_LIFETIME = 7200; // 2 horas
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger();
        $this->config = $this->loadConfig();
        
        // Configurar sesión segura
        $this->configureSession();
    }
    
    /**
     * Cargar configuración de autenticación
     */
    private function loadConfig()
    {
        return [
            'password_min_length' => 8,
            'password_require_special' => true,
            'password_require_number' => true,
            'password_require_uppercase' => true,
            'session_regenerate_interval' => 1800, // 30 minutos
            'remember_me_duration' => 2592000, // 30 días
            'csrf_token_lifetime' => 3600 // 1 hora
        ];
    }
    
    /**
     * Configurar sesión segura
     */
    private function configureSession()
    {
        if (session_status() == PHP_SESSION_NONE) {
            // Configuración segura de sesión
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Strict');
            
            session_start();
            
            // Regenerar ID de sesión periódicamente
            $this->regenerateSessionIfNeeded();
        }
    }
    
    // ========================================
    // MÉTODOS DE AUTENTICACIÓN PRINCIPAL
    // ========================================
    
    /**
     * Intentar login de usuario
     */
    public function login($username, $password, $rememberMe = false)
    {
        try {
            // Validar datos de entrada
            $validation = $this->validateLoginData($username, $password);
            if (!$validation['valid']) {
                return $this->createAuthResponse(false, $validation['message']);
            }
            
            // Verificar bloqueo por intentos fallidos
            if ($this->isUserLocked($username)) {
                $this->logger->log(null, 'AUTH_BLOCKED', "Login bloqueado para usuario: {$username}");
                return $this->createAuthResponse(false, 'Usuario temporalmente bloqueado por múltiples intentos fallidos');
            }
            
            // Buscar usuario en la base de datos
            $user = $this->findUserByCredentials($username);
            if (!$user) {
                $this->recordFailedAttempt($username);
                return $this->createAuthResponse(false, 'Credenciales inválidas');
            }
            
            // Verificar password
            if (!$this->verifyPassword($password, $user['password'])) {
                $this->recordFailedAttempt($username);
                $this->logger->log($user['id'], 'AUTH_FAILED', "Password incorrecto para usuario: {$username}");
                return $this->createAuthResponse(false, 'Credenciales inválidas');
            }
            
            // Verificar si el usuario está activo
            if (!$user['is_active']) {
                $this->logger->log($user['id'], 'AUTH_INACTIVE', "Intento de login de usuario inactivo: {$username}");
                return $this->createAuthResponse(false, 'Cuenta desactivada');
            }
            
            // Login exitoso
            $this->processSuccessfulLogin($user, $rememberMe);
            
            return $this->createAuthResponse(true, 'Login exitoso', [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
                'redirect_url' => $this->getRedirectUrl($user['role'])
            ]);
            
        } catch (Exception $e) {
            $this->logger->log(null, 'AUTH_ERROR', "Error en login: " . $e->getMessage());
            return $this->createAuthResponse(false, 'Error interno del sistema');
        }
    }
    
    /**
     * Logout de usuario
     */
    public function logout()
    {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            
            if ($userId) {
                // Actualizar última actividad
                $this->updateLastActivity($userId);
                
                // Log del logout
                $this->logger->log($userId, 'AUTH_LOGOUT', 'Usuario cerró sesión');
                
                // Limpiar token de "recordarme" si existe
                $this->clearRememberToken($userId);
            }
            
            // Destruir sesión
            session_unset();
            session_destroy();
            
            // Limpiar cookies
            $this->clearAuthCookies();
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->log($userId ?? null, 'AUTH_ERROR', "Error en logout: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar si el usuario está autenticado
     */
    public function isAuthenticated()
    {
        // Verificar sesión básica
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
            return false;
        }
        
        // Verificar timeout de sesión
        if ($this->isSessionExpired()) {
            $this->logout();
            return false;
        }
        
        // Verificar integridad de la sesión
        if (!$this->validateSessionIntegrity()) {
            $this->logout();
            return false;
        }
        
        // Actualizar timestamp de actividad
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    // ========================================
    // MÉTODOS DE AUTORIZACIÓN
    // ========================================
    
    /**
     * Verificar si el usuario tiene un permiso específico
     */
    public function hasPermission($permission)
    {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        $userRole = $_SESSION['user_role'];
        
        // Admin tiene todos los permisos
        if ($userRole === 'admin') {
            return true;
        }
        
        // Verificar permisos específicos del rol
        if (defined('PERMISSIONS') && isset(PERMISSIONS[$userRole])) {
            return in_array($permission, PERMISSIONS[$userRole]);
        }
        
        return false;
    }
    
    /**
     * Verificar si el usuario es admin
     */
    public function isAdmin()
    {
        return $this->isAuthenticated() && $_SESSION['user_role'] === 'admin';
    }
    
    /**
     * Verificar acceso a empresa específica
     */
    public function canAccessCompany($companyId)
    {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        // Admin puede acceder a todas las empresas
        if ($this->isAdmin()) {
            return true;
        }
        
        // Verificar si el usuario pertenece a la empresa
        $userCompanyId = $_SESSION['company_id'] ?? null;
        return $userCompanyId && $userCompanyId == $companyId;
    }
    
    // ========================================
    // MÉTODOS DE GESTIÓN DE USUARIOS
    // ========================================
    
    /**
     * Crear nuevo usuario
     */
    public function createUser($userData)
    {
        try {
            // Validar datos del usuario
            $validation = $this->validateUserData($userData);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => $validation['message']];
            }
            
            // Verificar si el usuario ya existe
            if ($this->userExists($userData['username'], $userData['email'])) {
                return ['success' => false, 'message' => 'Usuario o email ya existe'];
            }
            
            // Hash del password
            $hashedPassword = $this->hashPassword($userData['password']);
            
            // Insertar usuario
            $userId = $this->db->query(
                "INSERT INTO users (username, email, password, full_name, role, company_id, is_active, created_by, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW())",
                [
                    $userData['username'],
                    $userData['email'],
                    $hashedPassword,
                    $userData['full_name'],
                    $userData['role'] ?? 'user',
                    $userData['company_id'] ?? null,
                    $_SESSION['user_id']
                ]
            );
            
            $this->logger->log($_SESSION['user_id'], 'USER_CREATED', "Usuario creado: {$userData['username']} (ID: {$userId})");
            
            return ['success' => true, 'user_id' => $userId, 'message' => 'Usuario creado exitosamente'];
            
        } catch (Exception $e) {
            $this->logger->log($_SESSION['user_id'] ?? null, 'USER_ERROR', "Error creando usuario: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error interno del sistema'];
        }
    }
    
    /**
     * Actualizar usuario
     */
    public function updateUser($userId, $userData)
    {
        try {
            // Validar que el usuario existe
            $existingUser = $this->getUserById($userId);
            if (!$existingUser) {
                return ['success' => false, 'message' => 'Usuario no encontrado'];
            }
            
            // Construir query de actualización
            $updateFields = [];
            $values = [];
            
            if (isset($userData['full_name'])) {
                $updateFields[] = 'full_name = ?';
                $values[] = $userData['full_name'];
            }
            
            if (isset($userData['email'])) {
                $updateFields[] = 'email = ?';
                $values[] = $userData['email'];
            }
            
            if (isset($userData['role'])) {
                $updateFields[] = 'role = ?';
                $values[] = $userData['role'];
            }
            
            if (isset($userData['company_id'])) {
                $updateFields[] = 'company_id = ?';
                $values[] = $userData['company_id'];
            }
            
            if (isset($userData['is_active'])) {
                $updateFields[] = 'is_active = ?';
                $values[] = $userData['is_active'] ? 1 : 0;
            }
            
            if (isset($userData['password']) && !empty($userData['password'])) {
                $updateFields[] = 'password = ?';
                $values[] = $this->hashPassword($userData['password']);
            }
            
            if (empty($updateFields)) {
                return ['success' => false, 'message' => 'No hay campos para actualizar'];
            }
            
            // Agregar campos de auditoría
            $updateFields[] = 'updated_by = ?';
            $updateFields[] = 'updated_at = NOW()';
            $values[] = $_SESSION['user_id'];
            $values[] = $userId;
            
            $this->db->query(
                "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?",
                $values
            );
            
            $this->logger->log($_SESSION['user_id'], 'USER_UPDATED', "Usuario actualizado: {$existingUser['username']} (ID: {$userId})");
            
            return ['success' => true, 'message' => 'Usuario actualizado exitosamente'];
            
        } catch (Exception $e) {
            $this->logger->log($_SESSION['user_id'] ?? null, 'USER_ERROR', "Error actualizando usuario: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error interno del sistema'];
        }
    }
    
    // ========================================
    // MÉTODOS DE SEGURIDAD
    // ========================================
    
    /**
     * Generar token CSRF
     */
    public function generateCSRFToken()
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        return $token;
    }
    
    /**
     * Verificar token CSRF
     */
    public function verifyCSRFToken($token)
    {
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }
        
        // Verificar expiración
        if (time() - $_SESSION['csrf_token_time'] > $this->config['csrf_token_lifetime']) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Generar password seguro
     */
    public function generateSecurePassword($length = 12)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $password;
    }
    
    /**
     * Validar fortaleza del password
     */
    public function validatePasswordStrength($password)
    {
        $errors = [];
        
        if (strlen($password) < $this->config['password_min_length']) {
            $errors[] = "Mínimo {$this->config['password_min_length']} caracteres";
        }
        
        if ($this->config['password_require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Debe contener al menos una letra mayúscula";
        }
        
        if ($this->config['password_require_number'] && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Debe contener al menos un número";
        }
        
        if ($this->config['password_require_special'] && !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $errors[] = "Debe contener al menos un carácter especial";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    // ========================================
    // MÉTODOS PRIVADOS DE APOYO
    // ========================================
    
    /**
     * Buscar usuario por credenciales
     */
    private function findUserByCredentials($username)
    {
        return $this->db->fetch(
            "SELECT id, username, email, password, full_name, role, company_id, is_active 
             FROM users 
             WHERE (username = ? OR email = ?) AND is_active = 1",
            [$username, $username]
        );
    }
    
    /**
     * Verificar password
     */
    private function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }
    
    /**
     * Hash de password
     */
    private function hashPassword($password)
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
    
    /**
     * Procesar login exitoso
     */
    private function processSuccessfulLogin($user, $rememberMe)
    {
        // Regenerar ID de sesión
        session_regenerate_id(true);
        
        // Establecer variables de sesión
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['company_id'] = $user['company_id'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['session_token'] = bin2hex(random_bytes(32));
        
        // Limpiar intentos fallidos
        $this->clearFailedAttempts($user['username']);
        
        // Actualizar último login
        $this->updateLastLogin($user['id']);
        
        // Manejar "recordarme"
        if ($rememberMe) {
            $this->setRememberToken($user['id']);
        }
        
        // Log del login exitoso
        $this->logger->log($user['id'], 'AUTH_SUCCESS', "Login exitoso desde IP: " . $this->getClientIP());
    }
    
    /**
     * Validar datos de login
     */
    private function validateLoginData($username, $password)
    {
        if (empty($username) || empty($password)) {
            return ['valid' => false, 'message' => 'Usuario y contraseña son requeridos'];
        }
        
        if (strlen($username) > 100) {
            return ['valid' => false, 'message' => 'Usuario demasiado largo'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Verificar si usuario está bloqueado
     */
    private function isUserLocked($username)
    {
        $attempts = $this->db->fetch(
            "SELECT attempts, last_attempt 
             FROM login_attempts 
             WHERE username = ? AND last_attempt > DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [$username, self::LOCKOUT_TIME]
        );
        
        return $attempts && $attempts['attempts'] >= self::MAX_LOGIN_ATTEMPTS;
    }
    
    /**
     * Registrar intento fallido
     */
    private function recordFailedAttempt($username)
    {
        $this->db->query(
            "INSERT INTO login_attempts (username, ip_address, attempts, last_attempt) 
             VALUES (?, ?, 1, NOW()) 
             ON DUPLICATE KEY UPDATE 
             attempts = attempts + 1, 
             last_attempt = NOW(), 
             ip_address = VALUES(ip_address)",
            [$username, $this->getClientIP()]
        );
    }
    
    /**
     * Limpiar intentos fallidos
     */
    private function clearFailedAttempts($username)
    {
        $this->db->query("DELETE FROM login_attempts WHERE username = ?", [$username]);
    }
    
    /**
     * Verificar si la sesión ha expirado
     */
    private function isSessionExpired()
    {
        if (!isset($_SESSION['last_activity'])) {
            return true;
        }
        
        return (time() - $_SESSION['last_activity']) > self::SESSION_LIFETIME;
    }
    
    /**
     * Validar integridad de la sesión
     */
    private function validateSessionIntegrity()
    {
        // Verificar que existan las variables esenciales
        $requiredVars = ['user_id', 'username', 'user_role', 'login_time', 'session_token'];
        
        foreach ($requiredVars as $var) {
            if (!isset($_SESSION[$var])) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Regenerar sesión si es necesario
     */
    private function regenerateSessionIfNeeded()
    {
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > $this->config['session_regenerate_interval']) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
    
    /**
     * Crear respuesta de autenticación
     */
    private function createAuthResponse($success, $message, $data = null)
    {
        $response = [
            'success' => $success,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($data) {
            $response['data'] = $data;
        }
        
        return $response;
    }
    
    /**
     * Obtener URL de redirección según rol
     */
    private function getRedirectUrl($role)
    {
        switch ($role) {
            case 'admin':
                return '/management';
            case 'manager':
                return '/dashboard';
            default:
                return '/processing';
        }
    }
    
    /**
     * Obtener IP del cliente
     */
    private function getClientIP()
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Actualizar último login
     */
    private function updateLastLogin($userId)
    {
        $this->db->query(
            "UPDATE users SET last_login = NOW(), login_count = login_count + 1 WHERE id = ?",
            [$userId]
        );
    }
    
    /**
     * Actualizar última actividad
     */
    private function updateLastActivity($userId)
    {
        $this->db->query(
            "UPDATE users SET last_activity = NOW() WHERE id = ?",
            [$userId]
        );
    }
    
    /**
     * Establecer token de recordar
     */
    private function setRememberToken($userId)
    {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + $this->config['remember_me_duration']);
        
        // Guardar en BD
        $this->db->query(
            "INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?) 
             ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)",
            [$userId, hash('sha256', $token), $expires]
        );
        
        // Establecer cookie
        setcookie('remember_token', $token, time() + $this->config['remember_me_duration'], '/', '', true, true);
    }
    
    /**
     * Limpiar token de recordar
     */
    private function clearRememberToken($userId)
    {
        $this->db->query("DELETE FROM remember_tokens WHERE user_id = ?", [$userId]);
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }
    
    /**
     * Limpiar cookies de autenticación
     */
    private function clearAuthCookies()
    {
        $cookies = ['remember_token', 'session_token'];
        
        foreach ($cookies as $cookie) {
            if (isset($_COOKIE[$cookie])) {
                setcookie($cookie, '', time() - 3600, '/', '', true, true);
            }
        }
    }
    
    /**
     * Validar datos de usuario
     */
    private function validateUserData($userData)
    {
        $required = ['username', 'email', 'password', 'full_name'];
        $missing = [];
        
        foreach ($required as $field) {
            if (empty($userData[$field])) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            return ['valid' => false, 'message' => 'Campos requeridos: ' . implode(', ', $missing)];
        }
        
        // Validar email
        if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'message' => 'Email inválido'];
        }
        
        // Validar password
        $passwordValidation = $this->validatePasswordStrength($userData['password']);
        if (!$passwordValidation['valid']) {
            return ['valid' => false, 'message' => 'Password: ' . implode(', ', $passwordValidation['errors'])];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Verificar si usuario existe
     */
    private function userExists($username, $email)
    {
        $user = $this->db->fetch(
            "SELECT id FROM users WHERE username = ? OR email = ?",
            [$username, $email]
        );
        
        return $user !== null;
    }
    
    /**
     * Obtener usuario por ID
     */
    private function getUserById($userId)
    {
        return $this->db->fetch(
            "SELECT id, username, email, full_name, role, company_id, is_active FROM users WHERE id = ?",
            [$userId]
        );
    }
    
    // ========================================
    // MÉTODOS PÚBLICOS DE INFORMACIÓN
    // ========================================
    
    /**
     * Obtener información del usuario actual
     */
    public function getCurrentUser()
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return $this->getUserById($_SESSION['user_id']);
    }
    
    /**
     * Obtener estadísticas de autenticación
     */
    public function getAuthStats()
    {
        try {
            return [
                'total_users' => $this->db->fetchColumn("SELECT COUNT(*) FROM users WHERE is_active = 1"),
                'online_users' => $this->db->fetchColumn(
                    "SELECT COUNT(*) FROM users WHERE last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE) AND is_active = 1"
                ),
                'failed_attempts_today' => $this->db->fetchColumn(
                    "SELECT COUNT(*) FROM login_attempts WHERE DATE(last_attempt) = CURDATE()"
                ),
                'active_sessions' => isset($_SESSION) ? 1 : 0
            ];
        } catch (Exception $e) {
            $this->logger->log(null, 'AUTH_ERROR', "Error obteniendo estadísticas: " . $e->getMessage());
            return null;
        }
    }
}
?>