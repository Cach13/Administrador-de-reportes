<?php
/**
 * Database.php - Versión Corregida con Manejo de Errores Mejorado
 */

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
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE " . DB_CHARSET . "_unicode_ci"
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
    
    // Prevenir clonación
    private function __clone() {}
    
    // Prevenir deserialización
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
    
    /**
     * Ejecutar una consulta preparada
     */
    public function query($sql, $params = []) {
        try {
            if (!$this->pdo) {
                throw new Exception("No hay conexión a la base de datos");
            }
            
            $stmt = $this->pdo->prepare($sql);
            
            if (!$stmt) {
                $errorInfo = $this->pdo->errorInfo();
                throw new Exception("Error preparando consulta: " . $errorInfo[2]);
            }
            
            $result = $stmt->execute($params);
            
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                throw new Exception("Error ejecutando consulta: " . $errorInfo[2]);
            }
            
            return $stmt;
            
        } catch (PDOException $e) {
            error_log("Database query error: " . $e->getMessage() . " | SQL: " . $sql . " | Params: " . print_r($params, true));
            throw new Exception("Error en la consulta de base de datos: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("Database error: " . $e->getMessage() . " | SQL: " . $sql . " | Params: " . print_r($params, true));
            throw $e;
        }
    }
    
    /**
     * Obtener un solo registro
     */
    public function fetch($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Database fetch error: " . $e->getMessage());
            throw new Exception("Error obteniendo registro: " . $e->getMessage());
        }
    }
    
    /**
     * Obtener múltiples registros
     */
    public function fetchAll($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Database fetchAll error: " . $e->getMessage());
            throw new Exception("Error obteniendo registros: " . $e->getMessage());
        }
    }
    
    /**
     * Insertar registro y obtener ID
     */
    public function insert($table, $data) {
        try {
            if (empty($table) || empty($data)) {
                throw new Exception("Tabla y datos son requeridos para insertar");
            }
            
            $columns = implode(',', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            
            $sql = "INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})";
            
            $stmt = $this->query($sql, $data);
            
            return $this->pdo->lastInsertId();
            
        } catch (Exception $e) {
            error_log("Database insert error: " . $e->getMessage() . " | Table: {$table} | Data: " . print_r($data, true));
            throw new Exception("Error insertando en {$table}: " . $e->getMessage());
        }
    }
    
    /**
     * Actualizar registros
     */
    public function update($table, $data, $where, $whereParams = []) {
        try {
            if (empty($table) || empty($data) || empty($where)) {
                throw new Exception("Tabla, datos y condición WHERE son requeridos");
            }
            
            $setClause = [];
            foreach (array_keys($data) as $column) {
                $setClause[] = "`{$column}` = :{$column}";
            }
            $setClause = implode(', ', $setClause);
            
            $sql = "UPDATE `{$table}` SET {$setClause} WHERE {$where}";
            
            $params = array_merge($data, $whereParams);
            $stmt = $this->query($sql, $params);
            
            return $stmt->rowCount();
            
        } catch (Exception $e) {
            error_log("Database update error: " . $e->getMessage() . " | Table: {$table}");
            throw new Exception("Error actualizando {$table}: " . $e->getMessage());
        }
    }
    
    /**
     * Eliminar registros
     */
    public function delete($table, $where, $params = []) {
        try {
            if (empty($table) || empty($where)) {
                throw new Exception("Tabla y condición WHERE son requeridos");
            }
            
            $sql = "DELETE FROM `{$table}` WHERE {$where}";
            $stmt = $this->query($sql, $params);
            
            return $stmt->rowCount();
            
        } catch (Exception $e) {
            error_log("Database delete error: " . $e->getMessage() . " | Table: {$table}");
            throw new Exception("Error eliminando de {$table}: " . $e->getMessage());
        }
    }
    
    /**
     * Iniciar transacción
     */
    public function beginTransaction() {
        try {
            return $this->pdo->beginTransaction();
        } catch (PDOException $e) {
            error_log("Database beginTransaction error: " . $e->getMessage());
            throw new Exception("Error iniciando transacción: " . $e->getMessage());
        }
    }
    
    /**
     * Confirmar transacción
     */
    public function commit() {
        try {
            return $this->pdo->commit();
        } catch (PDOException $e) {
            error_log("Database commit error: " . $e->getMessage());
            throw new Exception("Error confirmando transacción: " . $e->getMessage());
        }
    }
    
    /**
     * Revertir transacción
     */
    public function rollback() {
        try {
            return $this->pdo->rollback();
        } catch (PDOException $e) {
            error_log("Database rollback error: " . $e->getMessage());
            throw new Exception("Error revirtiendo transacción: " . $e->getMessage());
        }
    }
    
    /**
     * Obtener último ID insertado
     */
    public function lastInsertId() {
        try {
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Database lastInsertId error: " . $e->getMessage());
            throw new Exception("Error obteniendo último ID: " . $e->getMessage());
        }
    }
    
    /**
     * Verificar si una tabla existe
     */
    public function tableExists($tableName) {
        try {
            $sql = "SHOW TABLES LIKE :table_name";
            $stmt = $this->query($sql, ['table_name' => $tableName]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Database tableExists error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener información de conexión para debug
     */
    public function getConnectionInfo() {
        try {
            return [
                'server_version' => $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
                'driver_name' => $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
                'connection_status' => $this->pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS)
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}