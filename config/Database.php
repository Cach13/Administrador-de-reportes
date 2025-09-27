<?php
/**
 * Database.php
 * Clase para manejo de conexión a Base de Datos
 * Transport Management System
 */

class Database {
    
    // Configuración de BD
    private $host = 'localhost';
    private $db_name = 'transport_management';
    private $username = 'root';
    private $password = '';
    private $charset = 'utf8mb4';
    
    // Conexión única (Singleton)
    private static $instance = null;
    private $connection = null;
    
    /**
     * Constructor privado - Patrón Singleton
     */
    private function __construct() {
        $this->connect();
    }
    
    /**
     * Obtener instancia única de Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Conectar a la base de datos
     */
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];
            
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            
            // Log exitoso
            error_log("Database: Conexión exitosa a {$this->db_name}");
            
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            throw new Exception("Error de conexión a la base de datos: " . $e->getMessage());
        }
    }
    
    /**
     * Obtener conexión PDO
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Verificar si la conexión está activa
     */
    public function isConnected() {
        return $this->connection !== null;
    }
    
    /**
     * Ejecutar query SELECT
     */
    public function select($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Database SELECT Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ejecutar query INSERT
     */
    public function insert($query_or_table, $params_or_data = []) {
    try {
        // Si el primer parámetro contiene "INSERT", es una query completa
        if (stripos($query_or_table, 'INSERT') !== false) {
            // Formato 1: Query SQL completa
            $stmt = $this->connection->prepare($query_or_table);
            $result = $stmt->execute($params_or_data);
            return $result ? $this->connection->lastInsertId() : false;
        } else {
            // Formato 2: Tabla y array de datos
            $table = $query_or_table;
            $data = $params_or_data;
            
            if (empty($data) || !is_array($data)) {
                throw new Exception("Datos de inserción inválidos");
            }
            
            $columns = array_keys($data);
            $placeholders = array_fill(0, count($columns), '?');
            
            $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            
            $stmt = $this->connection->prepare($sql);
            $result = $stmt->execute(array_values($data));
            
            return $result ? $this->connection->lastInsertId() : false;
        }
        
    } catch (PDOException $e) {
        error_log("Database INSERT Error: " . $e->getMessage());
        return false;
    }
}
    
    /**
     * Ejecutar query UPDATE
     */
    public function update($query_or_table, $params_or_data = [], $where = []) {
    try {
        // Si el primer parámetro contiene "UPDATE", es una query completa
        if (stripos($query_or_table, 'UPDATE') !== false) {
            // Formato 1: Query SQL completa
            $stmt = $this->connection->prepare($query_or_table);
            $stmt->execute($params_or_data);
            return $stmt->rowCount();
        } else {
            // Formato 2: Tabla, datos y WHERE
            $table = $query_or_table;
            $data = $params_or_data;
            
            if (empty($data) || !is_array($data)) {
                throw new Exception("Datos de actualización inválidos");
            }
            
            if (empty($where) || !is_array($where)) {
                throw new Exception("Condición WHERE requerida para UPDATE");
            }
            
            $set_parts = [];
            $values = [];
            
            foreach ($data as $column => $value) {
                $set_parts[] = "{$column} = ?";
                $values[] = $value;
            }
            
            $where_parts = [];
            foreach ($where as $column => $value) {
                $where_parts[] = "{$column} = ?";
                $values[] = $value;
            }
            
            $sql = "UPDATE {$table} SET " . implode(', ', $set_parts) . " WHERE " . implode(' AND ', $where_parts);
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($values);
            return $stmt->rowCount();
        }
        
    } catch (PDOException $e) {
        error_log("Database UPDATE Error: " . $e->getMessage());
        return false;
    }
}
    
    /**
     * Ejecutar query DELETE
     */
    public function delete($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Database DELETE Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener un solo registro
     */
    public function selectOne($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Database SELECT ONE Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Iniciar transacción
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * Confirmar transacción
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    /**
     * Revertir transacción
     */
    public function rollback() {
        return $this->connection->rollBack();
    }
    
    /**
     * Verificar estructura de BD
     */
    public function checkDatabaseStructure() {
        $tables = ['users', 'companies', 'vouchers', 'trips', 'reports', 'activity_logs'];
        $missing = [];
        
        foreach ($tables as $table) {
            $query = "SHOW TABLES LIKE '{$table}'";
            $result = $this->select($query);
            
            if (empty($result)) {
                $missing[] = $table;
            }
        }
        
        return empty($missing) ? true : $missing;
    }
    
    /**
     * Obtener estadísticas de la BD
     */
    public function getStats() {
        $stats = [];
        
        try {
            $stats['users'] = $this->selectOne("SELECT COUNT(*) as total FROM users")['total'] ?? 0;
            $stats['companies'] = $this->selectOne("SELECT COUNT(*) as total FROM companies")['total'] ?? 0;
            $stats['vouchers'] = $this->selectOne("SELECT COUNT(*) as total FROM vouchers")['total'] ?? 0;
            $stats['trips'] = $this->selectOne("SELECT COUNT(*) as total FROM trips")['total'] ?? 0;
            $stats['reports'] = $this->selectOne("SELECT COUNT(*) as total FROM reports")['total'] ?? 0;
            
        } catch (Exception $e) {
            error_log("Database Stats Error: " . $e->getMessage());
            $stats = ['error' => 'No se pudieron obtener las estadísticas'];
        }
        
        return $stats;
    }
    
    /**
     * Limpiar recursos
     */
    public function __destruct() {
        $this->connection = null;
    }
}