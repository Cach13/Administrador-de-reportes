<?php
/**
 * Clase Auth - Sistema Completo de Autenticación
 */

class Auth {
    private $db;
    private $logger;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->logger = new Logger();
    }
    
    /**
     * Iniciar sesión de usuario
     */
    public function login($username, $password, $remember = false) {
        try {
            // Verificar si el usuario existe y está activo
            $stmt = $this->db->query("
                SELECT id, username, email, password_hash, full_name, role, 
                       failed_login_attempts, locked_until, is_active
                FROM users 
                WHERE (username = ? OR email = ?) 
                AND is_active = 1
            ", [$username, $username]);
            
            $user = $stmt->fetch();
            
            if (!$user) {
                $this->logger->logFailedLogin($username, 'Usuario no encontrado');
                return [
                    'success' => false,
                    'message' => 'Usuario no encontrado o inactivo'
                ];
            }
            
            // Verificar si la cuenta está bloqueada
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                $remaining = ceil((strtotime($user['locked_until']) - time()) / 60);
                return [
                    'success' => false,
                    'message' => "Cuenta bloqueada. Intenta nuevamente en {$remaining} minutos"
                ];
            }
            
            // Verificar contraseña
            if (!password_verify($password, $user['password_hash'])) {
                // Incrementar intentos fallidos
                $this->incrementFailedAttempts($user['id']);
                $this->logger->logFailedLogin($username, 'Contraseña incorrecta');
                
                return [
                    'success' => false,
                    'message' => 'Contraseña incorrecta'
                ];
            }
            
            // Login exitoso - resetear intentos fallidos
            $this->resetFailedAttempts($user['id']);
            
            // Crear sesión
            $this->createSession($user, $remember);
            
            // Actualizar último login
            $this->updateLastLogin($user['id']);
            
            // Log de login exitoso
            $this->logger->logLogin($user['id'], $user['username']);
            
            return [
                'success' => true,
                'message' => 'Login exitoso',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role']
                ]
            ];
            
        } catch (Exception $e) {
            $this->logger->logError(null, "Error en login: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error interno del sistema'
            ];
        }
    }
    
    /**
     * Crear sesión de usuario
     */
    private function createSession($user, $remember = false) {
        // Regenerar ID de sesión por seguridad
        session_regenerate_id(true);
        
        // Establecer variables de sesión
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // Si se selecciona "recordar sesión"
        if ($remember) {
            // Extender tiempo de vida de la cookie de sesión a 30 días
            $lifetime = 30 * 24 * 60 * 60; // 30 días
            setcookie(session_name(), session_id(), time() + $lifetime, '/');
        }
    }
    
    /**
     * Cerrar sesión
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            // Log de logout
            $this->logger->logLogout($_SESSION['user_id'], $_SESSION['username']);
        }
        
        // Destruir todas las variables de sesión
        $_SESSION = array();
        
        // Eliminar cookie de sesión
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destruir la sesión
        session_destroy();
    }
    
    /**
     * Verificar si el usuario está autenticado
     */
    public function isAuthenticated() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_time'])) {
            return false;
        }
        
        // Verificar timeout de sesión
        if (isset($_SESSION['last_activity'])) {
            $inactive = time() - $_SESSION['last_activity'];
            if ($inactive >= SESSION_TIMEOUT) {
                $this->logout();
                return false;
            }
        }
        
        // Actualizar última actividad
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    /**
     * Obtener usuario actual
     */
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email'],
            'full_name' => $_SESSION['full_name'],
            'role' => $_SESSION['role'],
            'login_time' => $_SESSION['login_time']
        ];
    }
    
    /**
     * Verificar permisos del usuario
     */
    public function hasPermission($permission) {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        $userPermissions = PERMISSIONS[$_SESSION['role']] ?? [];
        return in_array($permission, $userPermissions);
    }
    
    /**
     * Crear nuevo usuario
     */
    public function createUser($userData) {
        try {
            // Validar datos requeridos
            $required = ['username', 'email', 'password', 'full_name'];
            foreach ($required as $field) {
                if (empty($userData[$field])) {
                    return ['success' => false, 'message' => "Campo {$field} es requerido"];
                }
            }
            
            // Verificar que username/email no existan
            $stmt = $this->db->query("SELECT id FROM users WHERE username = ? OR email = ?", 
                [$userData['username'], $userData['email']]);
            
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Usuario o email ya existe'];
            }
            
            // Validar fortaleza de contraseña
            $validation = $this->validatePasswordStrength($userData['password']);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => implode(', ', $validation['errors'])];
            }
            
            // Hash de la contraseña
            $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT, ['cost' => BCRYPT_COST]);
            
            // Insertar usuario
            $userId = $this->db->insert('users', [
                'username' => $userData['username'],
                'email' => $userData['email'],
                'password_hash' => $hashedPassword,
                'full_name' => $userData['full_name'],
                'role' => $userData['role'] ?? 'operator',
                'phone' => $userData['phone'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            if ($userId) {
                $this->logger->logCrud($_SESSION['user_id'] ?? null, 'CREATE', 'users', $userId, 
                    "Nuevo usuario creado: " . $userData['username']);
                
                return [
                    'success' => true,
                    'message' => 'Usuario creado correctamente',
                    'user_id' => $userId
                ];
            }
            
            return ['success' => false, 'message' => 'Error al crear usuario'];
            
        } catch (Exception $e) {
            $this->logger->logError($_SESSION['user_id'] ?? null, "Error creando usuario: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error interno del sistema'];
        }
    }
    
    /**
     * Incrementar intentos fallidos de login
     */
    private function incrementFailedAttempts($userId) {
        try {
            $this->db->query("
                UPDATE users 
                SET failed_login_attempts = failed_login_attempts + 1,
                    locked_until = CASE 
                        WHEN failed_login_attempts + 1 >= ? 
                        THEN DATE_ADD(NOW(), INTERVAL ? MINUTE)
                        ELSE locked_until 
                    END
                WHERE id = ?
            ", [MAX_LOGIN_ATTEMPTS, LOCKOUT_TIME / 60, $userId]);
            
        } catch (Exception $e) {
            error_log("Error incrementando intentos fallidos: " . $e->getMessage());
        }
    }
    
    /**
     * Resetear intentos fallidos
     */
    private function resetFailedAttempts($userId) {
        try {
            $this->db->query("
                UPDATE users 
                SET failed_login_attempts = 0, locked_until = NULL 
                WHERE id = ?
            ", [$userId]);
            
        } catch (Exception $e) {
            error_log("Error reseteando intentos fallidos: " . $e->getMessage());
        }
    }
    
    /**
     * Actualizar último login
     */
    private function updateLastLogin($userId) {
        try {
            $this->db->query("UPDATE users SET last_login = NOW() WHERE id = ?", [$userId]);
        } catch (Exception $e) {
            error_log("Error actualizando último login: " . $e->getMessage());
        }
    }
    
    /**
     * Validar fuerza de contraseña
     */
    public function validatePasswordStrength($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'La contraseña debe tener al menos 8 caracteres';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'La contraseña debe contener al menos una letra mayúscula';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'La contraseña debe contener al menos una letra minúscula';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'La contraseña debe contener al menos un número';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
?>