<?php
/**
 * Clase Auth - Manejo de autenticación y sesiones
 */

class Auth {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    /**
     * Iniciar sesión de usuario
     */
    public function login($username, $password, $remember = false) {
        try {
            // Verificar si el usuario existe y está activo
            $stmt = $this->pdo->prepare("
                SELECT id, username, email, password_hash, full_name, role, 
                       failed_login_attempts, locked_until, is_active
                FROM users 
                WHERE (username = :username OR email = :username) 
                AND is_active = 1
            ");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();
            
            if (!$user) {
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
            $this->logActivity($user['id'], 'USER_LOGIN', 'Usuario inició sesión correctamente');
            
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
            
        } catch (PDOException $e) {
            error_log("Error en login: " . $e->getMessage());
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
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        
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
            $this->logActivity($_SESSION['user_id'], 'USER_LOGOUT', 'Usuario cerró sesión');
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
     * Requerir autenticación
     */
    public function requireAuth() {
        if (!$this->isAuthenticated()) {
            header('Location: /login.php');
            exit();
        }
    }
    
    /**
     * Requerir permiso específico
     */
    public function requirePermission($permission) {
        $this->requireAuth();
        
        if (!$this->hasPermission($permission)) {
            header('HTTP/1.1 403 Forbidden');
            die('Acceso denegado: No tienes permisos para realizar esta acción.');
        }
    }
    
    /**
     * Incrementar intentos fallidos de login
     */
    private function incrementFailedAttempts($userId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET failed_login_attempts = failed_login_attempts + 1,
                    locked_until = CASE 
                        WHEN failed_login_attempts + 1 >= :max_attempts 
                        THEN DATE_ADD(NOW(), INTERVAL :lockout_minutes MINUTE)
                        ELSE locked_until 
                    END
                WHERE id = :user_id
            ");
            
            $stmt->execute([
                'max_attempts' => MAX_LOGIN_ATTEMPTS,
                'lockout_minutes' => LOCKOUT_TIME / 60,
                'user_id' => $userId
            ]);
            
            // Log del intento fallido
            $this->logActivity($userId, 'LOGIN_FAILED', 'Intento de login fallido');
            
        } catch (PDOException $e) {
            error_log("Error incrementando intentos fallidos: " . $e->getMessage());
        }
    }
    
    /**
     * Resetear intentos fallidos
     */
    private function resetFailedAttempts($userId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET failed_login_attempts = 0, locked_until = NULL 
                WHERE id = :user_id
            ");
            $stmt->execute(['user_id' => $userId]);
            
        } catch (PDOException $e) {
            error_log("Error reseteando intentos fallidos: " . $e->getMessage());
        }
    }
    
    /**
     * Actualizar último login
     */
    private function updateLastLogin($userId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET last_login = NOW() 
                WHERE id = :user_id
            ");
            $stmt->execute(['user_id' => $userId]);
            
        } catch (PDOException $e) {
            error_log("Error actualizando último login: " . $e->getMessage());
        }
    }
    
    /**
     * Registrar actividad en logs
     */
    private function logActivity($userId, $action, $description) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent, created_at)
                VALUES (:user_id, :action, :description, :ip_address, :user_agent, NOW())
            ");
            
            $stmt->execute([
                'user_id' => $userId,
                'action' => $action,
                'description' => $description,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
        } catch (PDOException $e) {
            error_log("Error registrando actividad: " . $e->getMessage());
        }
    }
    
    /**
     * Cambiar contraseña
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            // Verificar contraseña actual
            $stmt = $this->pdo->prepare("SELECT password_hash FROM users WHERE id = :user_id");
            $stmt->execute(['user_id' => $userId]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                return [
                    'success' => false,
                    'message' => 'Contraseña actual incorrecta'
                ];
            }
            
            // Actualizar contraseña
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT, ['cost' => BCRYPT_COST]);
            
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET password_hash = :password_hash 
                WHERE id = :user_id
            ");
            
            $stmt->execute([
                'password_hash' => $hashedPassword,
                'user_id' => $userId
            ]);
            
            // Log del cambio
            $this->logActivity($userId, 'PASSWORD_CHANGED', 'Usuario cambió su contraseña');
            
            return [
                'success' => true,
                'message' => 'Contraseña actualizada correctamente'
            ];
            
        } catch (PDOException $e) {
            error_log("Error cambiando contraseña: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error interno del sistema'
            ];
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
    
    /**
     * Generar token para recuperación de contraseña
     */
    public function generatePasswordResetToken($email) {
        try {
            // Verificar si el usuario existe
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = :email AND is_active = 1");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Email no encontrado'
                ];
            }
            
            // Generar token único
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Guardar token (se necesitaría crear tabla password_resets)
            // Por ahora solo simulamos
            
            return [
                'success' => true,
                'message' => 'Token generado correctamente',
                'token' => $token
            ];
            
        } catch (PDOException $e) {
            error_log("Error generando token: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error interno del sistema'
            ];
        }
    }
}
?>