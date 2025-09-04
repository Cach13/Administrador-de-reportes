<?php
/**
 * Logger Simple - Compatible con cualquier BD
 * Solo logging básico sin features avanzadas
 */

class Logger {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Log básico - solo campos esenciales
     */
    public function log($userId, $action, $description, $ipAddress = null) {
        try {
            // Datos básicos
            $data = [
                'user_id' => $userId,
                'action' => $action,
                'description' => $description,
                'ip_address' => $ipAddress ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            return $this->db->insert('activity_logs', $data);
            
        } catch (Exception $e) {
            // Si falla, log en archivo
            error_log("Logger BD error: " . $e->getMessage());
            $this->logToFile($action, $description, $userId);
            return false;
        }
    }
    
    /**
     * Shortcuts para logs comunes
     */
    public function logLogin($userId, $username) {
        return $this->log($userId, 'USER_LOGIN', "Usuario {$username} inició sesión");
    }
    
    public function logLogout($userId, $username) {
        return $this->log($userId, 'USER_LOGOUT', "Usuario {$username} cerró sesión");
    }
    
    public function logFailedLogin($username, $reason = '') {
        $description = "Login fallido: {$username}" . ($reason ? " | {$reason}" : '');
        return $this->log(null, 'LOGIN_FAILED', $description);
    }
    
    public function logError($userId, $error, $context = []) {
        $description = "ERROR: " . $error;
        if (!empty($context)) {
            $description .= " | " . json_encode($context);
        }
        return $this->log($userId, 'ERROR', $description);
    }
    
    public function logCrud($userId, $operation, $table, $recordId, $details = '') {
        $description = "{$operation} en {$table}";
        if ($recordId) $description .= " (ID: {$recordId})";
        if ($details) $description .= " | {$details}";
        return $this->log($userId, strtoupper($operation), $description);
    }
    
    /**
     * Log en archivo (backup)
     */
    private function logToFile($action, $description, $userId = null) {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_part = $userId ? " [User: {$userId}]" : '';
        $message = "[{$timestamp}]{$user_part} [{$action}] {$description} [IP: {$ip}]";
        
        error_log($message);
    }
}
?>