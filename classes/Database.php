<?php
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
    
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
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
    
    public function beginTransaction() { return $this->pdo->beginTransaction(); }
    public function commit() { return $this->pdo->commit(); }
    public function rollback() { return $this->pdo->rollback(); }
    public function lastInsertId() { return $this->pdo->lastInsertId(); }
}