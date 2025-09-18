<?php
// ========================================
// classes/Database.php - VERSIÓN MEJORADA CON MÉTODO EXECUTE
// Mantiene compatibilidad total + métodos faltantes
// ========================================

class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new Exception("Error de conexión a la base de datos: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() { 
        return $this->pdo; 
    }
    
    private function __clone() {}
    public function __wakeup() { throw new Exception("Cannot unserialize singleton"); }
    
    // ========================================
    // MÉTODOS PRINCIPALES (MANTENER IGUALES)
    // ========================================
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query error: " . $e->getMessage() . " | SQL: " . $sql);
            throw new Exception("Error en la consulta de base de datos: " . $e->getMessage());
        }
    }
    
    // ========================================
    // 🆕 MÉTODO EXECUTE - PARA COMPATIBILIDAD CON MODELS
    // ========================================
    
    /**
     * Método execute para compatibilidad con Models
     * Ejecuta SQL y devuelve TRUE/FALSE en lugar de statement
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Database execute error: " . $e->getMessage() . " | SQL: " . $sql);
            throw new Exception("Error ejecutando consulta: " . $e->getMessage());
        }
    }
    
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    // ========================================
    // MÉTODO NUEVO: fetchColumn() - PARA AUTHSERVICE
    // ========================================
    
    /**
     * Obtener un solo valor (primera columna de la primera fila)
     * Compatible con AuthService y otros sistemas
     */
    public function fetchColumn($sql, $params = [], $columnIndex = 0) {
        try {
            $stmt = $this->query($sql, $params);
            $result = $stmt->fetchColumn($columnIndex);
            return $result !== false ? $result : null;
        } catch (Exception $e) {
            error_log("fetchColumn error: " . $e->getMessage() . " | SQL: " . $sql);
            throw new Exception("Error obteniendo columna: " . $e->getMessage());
        }
    }
    
    // ========================================
    // MÉTODO NUEVO: fetchOne() - ALIAS PARA COMPATIBILIDAD
    // ========================================
    
    /**
     * Alias de fetchColumn para mayor claridad
     * Obtiene un solo valor escalar
     */
    public function fetchOne($sql, $params = []) {
        return $this->fetchColumn($sql, $params);
    }
    
    // ========================================
    // MÉTODOS CRUD EXISTENTES (MANTENER IGUALES)
    // ========================================
    
    public function insert($table, $data) {
        try {
            $columns = implode(",", array_keys($data));
            $placeholders = ":" . implode(", :", array_keys($data));
            $sql = "INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})";
            $this->query($sql, $data);
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("Insert error in {$table}: " . $e->getMessage());
            throw new Exception("Error insertando en {$table}: " . $e->getMessage());
        }
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        try {
            $setClause = implode(" = ?, ", array_keys($data)) . " = ?";
            $sql = "UPDATE `{$table}` SET {$setClause} WHERE {$where}";
            $params = array_merge(array_values($data), $whereParams);
            $stmt = $this->query($sql, $params);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Update error in {$table}: " . $e->getMessage());
            throw new Exception("Error actualizando {$table}: " . $e->getMessage());
        }
    }
    
    public function delete($table, $where, $params = []) {
        try {
            $sql = "DELETE FROM `{$table}` WHERE {$where}";
            $stmt = $this->query($sql, $params);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Delete error in {$table}: " . $e->getMessage());
            throw new Exception("Error eliminando de {$table}: " . $e->getMessage());
        }
    }
    
    // ========================================
    // MÉTODOS NUEVOS PARA AUTHSERVICE
    // ========================================
    
    /**
     * Verificar si existe un registro
     */
    public function exists($table, $where, $params = []) {
        try {
            $sql = "SELECT 1 FROM `{$table}` WHERE {$where} LIMIT 1";
            $result = $this->fetchColumn($sql, $params);
            return $result !== null;
        } catch (Exception $e) {
            error_log("Exists check error in {$table}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Contar registros en una tabla
     */
    public function count($table, $where = '1=1', $params = []) {
        try {
            $sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$where}";
            return (int) $this->fetchColumn($sql, $params);
        } catch (Exception $e) {
            error_log("Count error in {$table}: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Insertar o actualizar (UPSERT)
     */
    public function upsert($table, $data, $updateData = null) {
        try {
            $columns = implode(",", array_keys($data));
            $placeholders = ":" . implode(", :", array_keys($data));
            
            $sql = "INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})";
            
            if ($updateData !== null) {
                $updateClause = implode(" = VALUES(", array_keys($updateData)) . " = VALUES(" . implode("), ", array_keys($updateData)) . ")";
                $sql .= " ON DUPLICATE KEY UPDATE " . $updateClause;
            } else {
                $updateClause = implode(" = VALUES(", array_keys($data)) . " = VALUES(" . implode("), ", array_keys($data)) . ")";
                $sql .= " ON DUPLICATE KEY UPDATE " . $updateClause;
            }
            
            $stmt = $this->query($sql, $data);
            return $this->pdo->lastInsertId() ?: true;
        } catch (Exception $e) {
            error_log("Upsert error in {$table}: " . $e->getMessage());
            throw new Exception("Error en upsert {$table}: " . $e->getMessage());
        }
    }
    
    /**
     * Ejecutar múltiples queries en transacción
     */
    public function transaction($callback) {
        try {
            $this->beginTransaction();
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            error_log("Transaction error: " . $e->getMessage());
            throw new Exception("Error en transacción: " . $e->getMessage());
        }
    }
    
    /**
     * Obtener último ID insertado
     */
    public function getLastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Preparar statement (para queries optimizadas)
     */
    public function prepare($sql) {
        try {
            return $this->pdo->prepare($sql);
        } catch (PDOException $e) {
            error_log("Prepare error: " . $e->getMessage() . " | SQL: " . $sql);
            throw new Exception("Error preparando statement: " . $e->getMessage());
        }
    }
    
    // ========================================
    // MÉTODOS DE TRANSACCIÓN (MANTENER IGUALES)
    // ========================================
    
    public function beginTransaction() { 
        return $this->pdo->beginTransaction(); 
    }
    
    public function commit() { 
        return $this->pdo->commit(); 
    }
    
    public function rollback() { 
        return $this->pdo->rollback(); 
    }
    
    public function lastInsertId() { 
        return $this->pdo->lastInsertId(); 
    }
    
    // ========================================
    // MÉTODOS DE DEBUGGING Y UTILIDADES
    // ========================================
    
    /**
     * Obtener información de la base de datos
     */
    public function getInfo() {
        try {
            return [
                'server_version' => $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
                'client_version' => $this->pdo->getAttribute(PDO::ATTR_CLIENT_VERSION),
                'connection_status' => $this->pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS),
                'autocommit' => $this->pdo->getAttribute(PDO::ATTR_AUTOCOMMIT),
                'charset' => DB_CHARSET,
                'database' => DB_NAME
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Verificar conexión
     */
    public function ping() {
        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Escapar string (para casos especiales)
     */
    public function quote($string) {
        return $this->pdo->quote($string);
    }
    
    /**
     * Obtener estadísticas de tablas
     */
    public function getTableStats($table) {
        try {
            $stats = $this->fetch("SHOW TABLE STATUS LIKE ?", [$table]);
            if ($stats) {
                return [
                    'name' => $stats['Name'],
                    'engine' => $stats['Engine'],
                    'rows' => $stats['Rows'],
                    'data_length' => $stats['Data_length'],
                    'index_length' => $stats['Index_length'],
                    'auto_increment' => $stats['Auto_increment'],
                    'create_time' => $stats['Create_time'],
                    'update_time' => $stats['Update_time']
                ];
            }
            return null;
        } catch (Exception $e) {
            error_log("Table stats error for {$table}: " . $e->getMessage());
            return null;
        }
    }
    
    // ========================================
    // MÉTODOS ESPECÍFICOS PARA AUTHSERVICE
    // ========================================
    
    /**
     * Verificar credenciales de usuario
     */
    public function findUserByCredentials($username) {
        return $this->fetch(
            "SELECT id, username, email, password, full_name, role, company_id, is_active 
             FROM users 
             WHERE (username = ? OR email = ?) AND is_active = 1",
            [$username, $username]
        );
    }
    
    /**
     * Obtener intentos de login fallidos
     */
    public function getLoginAttempts($username) {
        return $this->fetch(
            "SELECT attempts, last_attempt 
             FROM login_attempts 
             WHERE username = ? AND last_attempt > DATE_SUB(NOW(), INTERVAL 900 SECOND)",
            [$username]
        );
    }
    
    /**
     * Registrar intento de login fallido
     */
    public function recordFailedAttempt($username, $ipAddress) {
        return $this->query(
            "INSERT INTO login_attempts (username, ip_address, attempts, last_attempt) 
             VALUES (?, ?, 1, NOW()) 
             ON DUPLICATE KEY UPDATE 
             attempts = attempts + 1, 
             last_attempt = NOW(), 
             ip_address = VALUES(ip_address)",
            [$username, $ipAddress]
        );
    }
    
    /**
     * Limpiar intentos de login fallidos
     */
    public function clearFailedAttempts($username) {
        return $this->delete('login_attempts', 'username = ?', [$username]);
    }
    
    /**
     * Actualizar último login del usuario
     */
    public function updateLastLogin($userId) {
        return $this->query(
            "UPDATE users SET last_login = NOW(), login_count = login_count + 1 WHERE id = ?",
            [$userId]
        );
    }
    
    /**
     * Actualizar actividad del usuario
     */
    public function updateUserActivity($userId) {
        return $this->query(
            "UPDATE users SET last_activity = NOW() WHERE id = ?",
            [$userId]
        );
    }
    
    // ========================================
    // LOGGING MEJORADO
    // ========================================
    
    /**
     * Log de query lenta
     */
    private function logSlowQuery($sql, $params, $executionTime) {
        if ($executionTime > 1.0) { // Más de 1 segundo
            error_log("SLOW QUERY ({$executionTime}s): {$sql} | Params: " . json_encode($params));
        }
    }
    
    /**
     * Query con timing
     */
    public function queryWithTiming($sql, $params = []) {
        $startTime = microtime(true);
        $result = $this->query($sql, $params);
        $executionTime = microtime(true) - $startTime;
        
        $this->logSlowQuery($sql, $params, $executionTime);
        
        return $result;
    }
}
?>