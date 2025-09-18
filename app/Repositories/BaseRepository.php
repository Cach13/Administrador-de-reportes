<?php
// ========================================
// app/Repositories/BaseRepository.php
// Repositorio base con funcionalidad común
// ========================================

namespace App\Repositories;

use Database;
use Exception;

/**
 * BaseRepository - Repositorio base para acceso a datos
 * 
 * Proporciona funcionalidad común para todos los repositorios:
 * - Operaciones CRUD básicas
 * - Consultas paginadas
 * - Filtros y búsquedas
 * - Agregaciones
 * - Cache de consultas
 */
abstract class BaseRepository
{
    /** @var Database */
    protected $db;
    
    /** @var string */
    protected $table;
    
    /** @var string */
    protected $primaryKey = 'id';
    
    /** @var array */
    protected $fillable = [];
    
    /** @var array */
    protected $searchable = [];
    
    /** @var array */
    protected $sortable = [];
    
    /** @var string */
    protected $defaultSort = 'id';
    
    /** @var string */
    protected $defaultOrder = 'DESC';
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Encontrar registro por ID
     */
    public function find($id)
    {
        if (empty($id)) {
            return null;
        }
        
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
        return $this->db->fetch($sql, [$id]);
    }
    
    /**
     * Encontrar múltiples registros por IDs
     */
    public function findMany(array $ids)
    {
        if (empty($ids)) {
            return [];
        }
        
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} IN ($placeholders)";
        
        return $this->db->fetchAll($sql, $ids);
    }
    
    /**
     * Obtener todos los registros
     */
    public function all($limit = null, $offset = null)
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY {$this->defaultSort} {$this->defaultOrder}";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
            if ($offset) {
                $sql .= " OFFSET " . intval($offset);
            }
        }
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Crear nuevo registro
     */
    public function create(array $data)
    {
        // Filtrar solo campos permitidos
        $data = $this->filterFillable($data);
        
        if (empty($data)) {
            throw new Exception("No hay datos válidos para crear");
        }
        
        $fields = array_keys($data);
        $placeholders = ':' . implode(', :', $fields);
        
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") 
                VALUES (" . $placeholders . ")";
        
        $this->db->execute($sql, $data);
        return $this->db->lastInsertId();
    }
    
    /**
     * Actualizar registro
     */
    public function update($id, array $data)
    {
        // Filtrar solo campos permitidos
        $data = $this->filterFillable($data);
        
        if (empty($data)) {
            throw new Exception("No hay datos válidos para actualizar");
        }
        
        $setClause = [];
        foreach ($data as $key => $value) {
            $setClause[] = "$key = :$key";
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $setClause) . 
               " WHERE {$this->primaryKey} = :id";
        
        $data['id'] = $id;
        return $this->db->execute($sql, $data);
    }
    
    /**
     * Eliminar registro
     */
    public function delete($id)
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        return $this->db->execute($sql, [$id]);
    }
    
    /**
     * Contar registros
     */
    public function count($where = null, $params = [])
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        
        if ($where) {
            $sql .= " WHERE $where";
        }
        
        $result = $this->db->fetch($sql, $params);
        return intval($result['total']);
    }
    
    /**
     * Verificar si existe registro
     */
    public function exists($id)
    {
        $sql = "SELECT 1 FROM {$this->table} WHERE {$this->primaryKey} = ? LIMIT 1";
        $result = $this->db->fetch($sql, [$id]);
        return !empty($result);
    }
    
    /**
     * Buscar registros con filtros
     */
    public function search($filters = [], $limit = null, $offset = null)
    {
        $where = [];
        $params = [];
        
        // Procesar filtros
        foreach ($filters as $field => $value) {
            if (in_array($field, $this->searchable) && !empty($value)) {
                $where[] = "$field LIKE ?";
                $params[] = "%$value%";
            }
        }
        
        $sql = "SELECT * FROM {$this->table}";
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " ORDER BY {$this->defaultSort} {$this->defaultOrder}";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
            if ($offset) {
                $sql .= " OFFSET " . intval($offset);
            }
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Consulta paginada
     */
    public function paginate($page = 1, $perPage = 15, $filters = [], $sort = null, $order = null)
    {
        $page = max(1, intval($page));
        $perPage = max(1, min(100, intval($perPage))); // Máximo 100 por página
        $offset = ($page - 1) * $perPage;
        
        // Validar sort y order
        $sort = in_array($sort, $this->sortable) ? $sort : $this->defaultSort;
        $order = in_array(strtoupper($order ?? ''), ['ASC', 'DESC']) ? strtoupper($order) : $this->defaultOrder;
        
        $where = [];
        $params = [];
        
        // Procesar filtros
        foreach ($filters as $field => $value) {
            if (in_array($field, $this->searchable) && !empty($value)) {
                $where[] = "$field LIKE ?";
                $params[] = "%$value%";
            }
        }
        
        $whereClause = !empty($where) ? " WHERE " . implode(' AND ', $where) : "";
        
        // Contar total de registros
        $countSql = "SELECT COUNT(*) as total FROM {$this->table}" . $whereClause;
        $totalResult = $this->db->fetch($countSql, $params);
        $total = intval($totalResult['total']);
        
        // Obtener datos paginados
        $dataSql = "SELECT * FROM {$this->table}" . $whereClause . 
                  " ORDER BY {$sort} {$order} LIMIT {$perPage} OFFSET {$offset}";
        $data = $this->db->fetchAll($dataSql, $params);
        
        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => $total > 0 ? min($offset + $perPage, $total) : 0
            ]
        ];
    }
    
    /**
     * Filtrar datos por campos permitidos
     */
    protected function filterFillable(array $data)
    {
        if (empty($this->fillable)) {
            return $data; // Si no hay restricciones, permitir todo
        }
        
        return array_intersect_key($data, array_flip($this->fillable));
    }
    
    /**
     * Buscar por campo específico
     */
    public function findBy($field, $value, $operator = '=')
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$field} {$operator} ?";
        return $this->db->fetch($sql, [$value]);
    }
    
    /**
     * Buscar múltiples registros por campo
     */
    public function findAllBy($field, $value, $operator = '=', $limit = null)
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$field} {$operator} ?";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        return $this->db->fetchAll($sql, [$value]);
    }
    
    /**
     * Obtener registros más recientes
     */
    public function latest($limit = 10)
    {
        $sql = "SELECT * FROM {$this->table} 
                ORDER BY {$this->defaultSort} {$this->defaultOrder} 
                LIMIT " . intval($limit);
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Obtener registros más antiguos
     */
    public function oldest($limit = 10)
    {
        $order = $this->defaultOrder === 'DESC' ? 'ASC' : 'DESC';
        $sql = "SELECT * FROM {$this->table} 
                ORDER BY {$this->defaultSort} {$order} 
                LIMIT " . intval($limit);
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Crear o actualizar registro
     */
    public function createOrUpdate(array $data, array $conditions)
    {
        // Verificar si existe
        $existing = $this->findWhere($conditions);
        
        if ($existing) {
            // Actualizar
            $this->update($existing[$this->primaryKey], $data);
            return $existing[$this->primaryKey];
        } else {
            // Crear
            return $this->create(array_merge($data, $conditions));
        }
    }
    
    /**
     * Buscar con condiciones WHERE personalizadas
     */
    public function findWhere(array $conditions)
    {
        $where = [];
        $params = [];
        
        foreach ($conditions as $field => $value) {
            $where[] = "$field = ?";
            $params[] = $value;
        }
        
        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where);
        return $this->db->fetch($sql, $params);
    }
    
    /**
     * Buscar múltiples con condiciones WHERE
     */
    public function findAllWhere(array $conditions, $limit = null)
    {
        $where = [];
        $params = [];
        
        foreach ($conditions as $field => $value) {
            $where[] = "$field = ?";
            $params[] = $value;
        }
        
        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where);
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Obtener estadísticas de la tabla
     */
    public function getStats()
    {
        $stats = $this->db->fetch("
            SELECT 
                COUNT(*) as total_records,
                MAX({$this->primaryKey}) as max_id,
                MIN({$this->primaryKey}) as min_id
            FROM {$this->table}
        ");
        
        return $stats ?: [];
    }
    
    /**
     * Truncar tabla (usar con precaución)
     */
    public function truncate()
    {
        return $this->db->execute("TRUNCATE TABLE {$this->table}");
    }
    
    /**
     * Eliminar múltiples registros
     */
    public function deleteWhere(array $conditions)
    {
        $where = [];
        $params = [];
        
        foreach ($conditions as $field => $value) {
            $where[] = "$field = ?";
            $params[] = $value;
        }
        
        $sql = "DELETE FROM {$this->table} WHERE " . implode(' AND ', $where);
        $stmt = $this->db->query($sql, $params);
        
        return $stmt->rowCount();
    }
    
    /**
     * Ejecutar query personalizada
     */
    protected function query($sql, $params = [])
    {
        return $this->db->query($sql, $params);
    }
    
    /**
     * Ejecutar fetch personalizado
     */
    protected function fetch($sql, $params = [])
    {
        return $this->db->fetch($sql, $params);
    }
    
    /**
     * Ejecutar fetchAll personalizado
     */
    protected function fetchAll($sql, $params = [])
    {
        return $this->db->fetchAll($sql, $params);
    }
}
?>