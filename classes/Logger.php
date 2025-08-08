<?php
/**
 * Clase Logger - Sistema de logging y auditoría
 */

class Logger {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Registrar actividad en la base de datos
     */
    public function log($userId, $action, $description, $ipAddress = null, $tableName = null, $recordId = null) {
        try {
            $data = [
                'user_id' => $userId,
                'action' => $action,
                'description' => $description,
                'ip_address' => $ipAddress ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'table_name' => $tableName,
                'record_id' => $recordId,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            return $this->db->insert('activity_logs', $data);
            
        } catch (Exception $e) {
            error_log("Error logging activity: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registrar error
     */
    public function logError($userId, $error, $context = []) {
        $description = "ERROR: " . $error;
        if (!empty($context)) {
            $description .= " | Context: " . json_encode($context);
        }
        
        return $this->log($userId, 'ERROR', $description);
    }
    
    /**
     * Registrar login exitoso
     */
    public function logLogin($userId, $username) {
        return $this->log($userId, 'USER_LOGIN', "Usuario {$username} inició sesión correctamente");
    }
    
    /**
     * Registrar logout
     */
    public function logLogout($userId, $username) {
        return $this->log($userId, 'USER_LOGOUT', "Usuario {$username} cerró sesión");
    }
    
    /**
     * Registrar intento de login fallido
     */
    public function logFailedLogin($username, $reason = '') {
        $description = "Intento de login fallido para usuario: {$username}";
        if ($reason) {
            $description .= " | Razón: {$reason}";
        }
        
        return $this->log(null, 'LOGIN_FAILED', $description);
    }
    
    /**
     * Registrar acceso a página
     */
    public function logPageAccess($userId, $page) {
        return $this->log($userId, 'PAGE_ACCESS', "Acceso a página: {$page}");
    }
    
    /**
     * Registrar operación CRUD
     */
    public function logCrud($userId, $operation, $table, $recordId, $details = '') {
        $description = "{$operation} en tabla {$table}" . ($details ? " | {$details}" : '');
        return $this->log($userId, strtoupper($operation), $description, null, $table, $recordId);
    }
    
    /**
     * Obtener logs de un usuario
     */
    public function getUserLogs($userId, $limit = 50) {
        $sql = "
            SELECT * FROM activity_logs 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ";
        
        return $this->db->fetchAll($sql, [$userId, $limit]);
    }
    
    /**
     * Obtener logs recientes
     */
    public function getRecentLogs($limit = 100) {
        $sql = "
            SELECT al.*, u.username, u.full_name 
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            ORDER BY al.created_at DESC 
            LIMIT ?
        ";
        
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    /**
     * Obtener estadísticas de actividad
     */
    public function getActivityStats($days = 30) {
        $sql = "
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as total_activities,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(CASE WHEN action = 'USER_LOGIN' THEN 1 END) as logins,
                COUNT(CASE WHEN action = 'ERROR' THEN 1 END) as errors
            FROM activity_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ";
        
        return $this->db->fetchAll($sql, [$days]);
    }
    
    /**
     * Limpiar logs antiguos
     */
    public function cleanOldLogs($daysToKeep = 90) {
        $sql = "DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $this->db->query($sql, [$daysToKeep]);
        return $stmt->rowCount();
    }
    
    /**
     * Registrar en archivo de log también (backup)
     */
    public function logToFile($message, $level = 'INFO') {
        $logFile = ROOT_PATH . '/logs/system.log';
        $logDir = dirname($logFile);
        
        // Crear directorio si no existe
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $logEntry = "[{$timestamp}] [{$level}] [IP: {$ip}] {$message}" . PHP_EOL;
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
?>