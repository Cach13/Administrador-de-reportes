<?php
// ========================================
// app/Models/User.php
// Modelo para entidad Usuario
// ========================================

namespace App\Models;

use Database;
use Exception;

/**
 * User Model - Representa un usuario del sistema
 * 
 * Maneja todas las operaciones relacionadas con usuarios:
 * - Autenticación
 * - Gestión de perfiles
 * - Validaciones
 * - Relaciones con otras entidades
 */
class User
{
    /** @var Database */
    private $db;
    
    /** @var array */
    private $data = [];
    
    /** @var array */
    private $fillable = [
        'username', 'email', 'password', 'full_name', 
        'last_login', 'is_active'
    ];
    
    /** @var array */
    private $hidden = ['password'];
    
    /**
     * Constructor
     */
    public function __construct($data = [])
    {
        $this->db = Database::getInstance();
        
        if (!empty($data)) {
            $this->fill($data);
        }
    }
    
    /**
     * Llenar modelo con datos
     */
    public function fill(array $data)
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $this->fillable) || $key === 'id') {
                $this->data[$key] = $value;
            }
        }
        return $this;
    }
    
    /**
     * Obtener valor de atributo
     */
    public function __get($key)
    {
        return $this->data[$key] ?? null;
    }
    
    /**
     * Establecer valor de atributo
     */
    public function __set($key, $value)
    {
        if (in_array($key, $this->fillable)) {
            $this->data[$key] = $value;
        }
    }
    
    /**
     * Verificar si atributo existe
     */
    public function __isset($key)
    {
        return isset($this->data[$key]);
    }
    
    /**
     * Crear nuevo usuario
     */
    public function save()
    {
        if (isset($this->data['id'])) {
            return $this->update();
        } else {
            return $this->create();
        }
    }
    
    /**
     * Crear usuario en base de datos
     */
    private function create()
    {
        // Validar datos requeridos
        $this->validate();
        
        // Hash password si está presente
        if (isset($this->data['password'])) {
            $this->data['password'] = password_hash($this->data['password'], PASSWORD_DEFAULT);
        }
        
        $fields = array_keys($this->data);
        $placeholders = ':' . implode(', :', $fields);
        
        $sql = "INSERT INTO users (" . implode(', ', $fields) . ") 
                VALUES (" . $placeholders . ")";
        
        try {
            $this->db->execute($sql, $this->data);
            $this->data['id'] = $this->db->lastInsertId();
            return true;
            
        } catch (Exception $e) {
            throw new Exception("Error creando usuario: " . $e->getMessage());
        }
    }
    
    /**
     * Actualizar usuario existente
     */
    private function update()
    {
        if (!isset($this->data['id'])) {
            throw new Exception("Cannot update user without ID");
        }
        
        $id = $this->data['id'];
        unset($this->data['id']);
        
        // Hash password si está siendo actualizada
        if (isset($this->data['password'])) {
            $this->data['password'] = password_hash($this->data['password'], PASSWORD_DEFAULT);
        }
        
        $setClause = [];
        foreach ($this->data as $key => $value) {
            $setClause[] = "$key = :$key";
        }
        
        $sql = "UPDATE users SET " . implode(', ', $setClause) . " WHERE id = :id";
        $this->data['id'] = $id;
        
        try {
            return $this->db->execute($sql, $this->data);
            
        } catch (Exception $e) {
            throw new Exception("Error actualizando usuario: " . $e->getMessage());
        }
    }
    
    /**
     * Validar datos del usuario
     */
    private function validate()
    {
        $errors = [];
        
        // Username requerido y único
        if (empty($this->data['username'])) {
            $errors[] = "Username es requerido";
        } else {
            $existing = $this->db->fetch(
                "SELECT id FROM users WHERE username = ? AND id != ?",
                [$this->data['username'], $this->data['id'] ?? 0]
            );
            if ($existing) {
                $errors[] = "Username ya existe";
            }
        }
        
        // Email requerido y válido
        if (empty($this->data['email'])) {
            $errors[] = "Email es requerido";
        } elseif (!filter_var($this->data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Email no es válido";
        } else {
            $existing = $this->db->fetch(
                "SELECT id FROM users WHERE email = ? AND id != ?",
                [$this->data['email'], $this->data['id'] ?? 0]
            );
            if ($existing) {
                $errors[] = "Email ya existe";
            }
        }
        
        // Full name requerido
        if (empty($this->data['full_name'])) {
            $errors[] = "Nombre completo es requerido";
        }
        
        if (!empty($errors)) {
            throw new Exception("Errores de validación: " . implode(', ', $errors));
        }
    }
    
    /**
     * Buscar usuario por ID
     */
    public static function find($id)
    {
        $db = Database::getInstance();
        $data = $db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
        
        return $data ? new self($data) : null;
    }
    
    /**
     * Buscar usuario por username
     */
    public static function findByUsername($username)
    {
        $db = Database::getInstance();
        $data = $db->fetch("SELECT * FROM users WHERE username = ?", [$username]);
        
        return $data ? new self($data) : null;
    }
    
    /**
     * Buscar usuario por email
     */
    public static function findByEmail($email)
    {
        $db = Database::getInstance();
        $data = $db->fetch("SELECT * FROM users WHERE email = ?", [$email]);
        
        return $data ? new self($data) : null;
    }
    
    /**
     * Obtener todos los usuarios
     */
    public static function all($activeOnly = true)
    {
        $db = Database::getInstance();
        $whereClause = $activeOnly ? "WHERE is_active = 1" : "";
        $results = $db->fetchAll("SELECT * FROM users $whereClause ORDER BY full_name");
        
        $users = [];
        foreach ($results as $data) {
            $users[] = new self($data);
        }
        
        return $users;
    }
    
    /**
     * Verificar password
     */
    public function verifyPassword($password)
    {
        if (!isset($this->data['password'])) {
            return false;
        }
        
        return password_verify($password, $this->data['password']);
    }
    
    /**
     * Actualizar último login
     */
    public function updateLastLogin()
    {
        if (!isset($this->data['id'])) {
            return false;
        }
        
        $sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
        return $this->db->execute($sql, [$this->data['id']]);
    }
    
    /**
     * Eliminar usuario (soft delete)
     */
    public function delete()
    {
        if (!isset($this->data['id'])) {
            return false;
        }
        
        $sql = "UPDATE users SET is_active = 0 WHERE id = ?";
        return $this->db->execute($sql, [$this->data['id']]);
    }
    
    /**
     * Convertir a array (sin campos ocultos)
     */
    public function toArray()
    {
        $result = $this->data;
        
        // Remover campos ocultos
        foreach ($this->hidden as $field) {
            unset($result[$field]);
        }
        
        return $result;
    }
    
    /**
     * Convertir a JSON
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }
    
    /**
     * Verificar si usuario está activo
     */
    public function isActive()
    {
        return !empty($this->data['is_active']);
    }
    
    /**
     * Obtener estadísticas del usuario
     */
    public function getStats()
    {
        if (!isset($this->data['id'])) {
            return null;
        }
        
        $stats = $this->db->fetch("
            SELECT 
                COUNT(DISTINCT v.id) as vouchers_uploaded,
                COUNT(DISTINCT t.id) as trips_processed,
                COUNT(DISTINCT r.id) as reports_generated,
                MAX(v.upload_date) as last_upload,
                MAX(r.generation_date) as last_report
            FROM users u
            LEFT JOIN vouchers v ON u.id = v.uploaded_by
            LEFT JOIN trips t ON v.id = t.voucher_id
            LEFT JOIN reports r ON v.id = r.voucher_id
            WHERE u.id = ?
        ", [$this->data['id']]);
        
        return $stats ?: [];
    }
}
?>