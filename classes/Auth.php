<?php
/**
 * Auth Simple - Compatible con BD actual
 * Versión simplificada que funciona con cualquier estructura de BD
 */

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Login simple - sin bloqueos ni features avanzadas
     */
    public function login($username, $password, $remember = false) {
        try {
            // Query básico compatible con cualquier estructura
            $user = $this->db->fetch("
                SELECT id, username, email, password_hash, full_name, role
                FROM users 
                WHERE (username = ? OR email = ?) 
                AND is_active = 1
            ", [$username, $username]);
            
            if (!$user) {
                error_log("Login failed: Usuario no encontrado - {$username}");
                return [
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ];
            }
            
            // Verificar contraseña
            if (!password_verify($password, $user['password_hash'])) {
                error_log("Login failed: Contraseña incorrecta - {$username}");
                return [
                    'success' => false,
                    'message' => 'Contraseña incorrecta'
                ];
            }
            
            // Login exitoso - crear sesión
            $this->createSession($user);
            
            // Log simple en archivo
            error_log("Login successful: {$username} - ID: {$user['id']}");
            
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
            error_log("Login error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error interno del sistema'
            ];
        }
    }
    
    /**
     * Crear sesión simple
     */
    private function createSession($user) {
        // Regenerar ID de sesión por seguridad
        session_regenerate_id(true);
        
        // Variables básicas de sesión
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'] ?? '';
        $_SESSION['full_name'] = $user['full_name'] ?? '';
        $_SESSION['role'] = $user['role'] ?? 'operator';
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * Logout simple
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            error_log("Logout: {$_SESSION['username']} - ID: {$_SESSION['user_id']}");
        }
        
        // Limpiar sesión
        $_SESSION = array();
        
        // Eliminar cookie de sesión
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $params["path"]);
        }
        
        session_destroy();
    }
    
    /**
     * Verificar autenticación
     */
    public function isAuthenticated() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_time'])) {
            return false;
        }
        
        // Timeout simple de 2 horas
        if (isset($_SESSION['last_activity'])) {
            $inactive = time() - $_SESSION['last_activity'];
            if ($inactive >= 7200) { // 2 horas
                $this->logout();
                return false;
            }
        }
        
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
            'email' => $_SESSION['email'] ?? '',
            'full_name' => $_SESSION['full_name'] ?? '',
            'role' => $_SESSION['role'] ?? 'operator'
        ];
    }
    
    /**
     * Verificar si es admin
     */
    public function isAdmin() {
        return $this->isAuthenticated() && $_SESSION['role'] === 'admin';
    }
    
    /**
     * Verificar si es operador
     */
    public function isOperator() {
        return $this->isAuthenticated() && $_SESSION['role'] === 'operator';
    }
    
    /**
     * Crear usuario simple
     */
    public function createUser($userData) {
        try {
            // Validaciones básicas
            if (empty($userData['username']) || empty($userData['password']) || empty($userData['email'])) {
                return ['success' => false, 'message' => 'Faltan campos requeridos'];
            }
            
            // Verificar que no exista
            $existing = $this->db->fetch("SELECT id FROM users WHERE username = ? OR email = ?", 
                [$userData['username'], $userData['email']]);
            
            if ($existing) {
                return ['success' => false, 'message' => 'Usuario o email ya existe'];
            }
            
            // Hash de contraseña
            $password_hash = password_hash($userData['password'], PASSWORD_DEFAULT);
            
            // Insertar usuario
            $user_id = $this->db->insert('users', [
                'username' => $userData['username'],
                'email' => $userData['email'],
                'password_hash' => $password_hash,
                'full_name' => $userData['full_name'] ?? $userData['username'],
                'role' => $userData['role'] ?? 'operator'
            ]);
            
            if ($user_id) {
                error_log("User created: {$userData['username']} - ID: {$user_id}");
                return [
                    'success' => true,
                    'message' => 'Usuario creado correctamente',
                    'user_id' => $user_id
                ];
            }
            
            return ['success' => false, 'message' => 'Error creando usuario'];
            
        } catch (Exception $e) {
            error_log("Create user error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
}
?>