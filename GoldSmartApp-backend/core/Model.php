<?php declare(strict_types=1);

/**
 * Base Model Class - Optimized
 * Features:
 * - Prepared statement caching
 * - Query builder optimization
 * - Batch operations
 * - Efficient existence checks
 */
class Model
{
    protected PDO $db;
    protected string $table;
    protected string $primaryKey = 'id';
    private array $stmtCache = [];
    private static array $queryCache = [];

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Find all records (optimized with query caching)
     */
    public function findAll(array $conditions = [], ?string $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        $cacheKey = $this->table . '_' . md5(serialize([$conditions, $orderBy, $limit, $offset]));

        if (!isset(self::$queryCache[$cacheKey])) {
            $sql = "SELECT * FROM {$this->table}";

            if (!empty($conditions)) {
                $where = [];
                foreach (array_keys($conditions) as $key) {
                    $where[] = "{$key} = :{$key}";
                }
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }

            if ($orderBy !== null) {
                $sql .= " ORDER BY {$orderBy}";
            }

            if ($limit !== null) {
                $sql .= " LIMIT {$limit}";
            }

            if ($offset !== null) {
                $sql .= " OFFSET {$offset}";
            }

            self::$queryCache[$cacheKey] = $sql;
        }

        $stmt = $this->db->prepare(self::$queryCache[$cacheKey]);
        $stmt->execute($conditions);
        return $stmt->fetchAll();
    }

    /**
     * Find one record by ID (cached statement)
     */
    public function findById($id): array|false
    {
        $cacheKey = 'findById_' . $this->table;

        if (!isset($this->stmtCache[$cacheKey])) {
            $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
            $this->stmtCache[$cacheKey] = $this->db->prepare($sql);
        }

        $this->stmtCache[$cacheKey]->execute(['id' => $id]);
        return $this->stmtCache[$cacheKey]->fetch();
    }

    /**
     * Alias for findById
     */
    public function find($id): array|false
    {
        return $this->findById($id);
    }

    /**
     * Find one record by conditions (optimized)
     */
    public function findOne(array $conditions): array|false
    {
        if (empty($conditions)) {
            $sql = "SELECT * FROM {$this->table} LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetch();
        }

        $where = [];
        foreach (array_keys($conditions) as $key) {
            $where[] = "{$key} = :{$key}";
        }

        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where) . ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($conditions);
        return $stmt->fetch();
    }

    /**
     * Create a new record (optimized)
     */
    public function create(array $data): string
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);

        return $this->db->lastInsertId();
    }

    /**
     * Batch insert (significantly faster for multiple records)
     */
    public function batchInsert(array $rows): bool
    {
        if (empty($rows)) {
            return false;
        }

        $firstRow = reset($rows);
        $columns = array_keys($firstRow);
        $columnStr = implode(', ', $columns);

        $placeholders = [];
        $params = [];
        $idx = 0;

        foreach ($rows as $row) {
            $rowPlaceholders = [];
            foreach ($columns as $col) {
                $param = "p{$idx}";
                $rowPlaceholders[] = ":{$param}";
                $params[$param] = $row[$col] ?? null;
                $idx++;
            }
            $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
        }

        $sql = "INSERT INTO {$this->table} ({$columnStr}) VALUES " . implode(', ', $placeholders);
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Update a record (optimized with LIMIT 1 for safety)
     */
    public function update($id, array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $set = [];
        foreach (array_keys($data) as $key) {
            $set[] = "{$key} = :{$key}";
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $set) . " WHERE {$this->primaryKey} = :id LIMIT 1";
        $data['id'] = $id;

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    /**
     * Update multiple records by condition
     */
    public function updateWhere(array $conditions, array $data): int
    {
        if (empty($data) || empty($conditions)) {
            return 0;
        }

        $set = [];
        foreach (array_keys($data) as $key) {
            $set[] = "{$key} = :set_{$key}";
        }

        $where = [];
        foreach (array_keys($conditions) as $key) {
            $where[] = "{$key} = :where_{$key}";
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $set) . ' WHERE ' . implode(' AND ', $where);

        $params = [];
        foreach ($data as $key => $value) {
            $params["set_{$key}"] = $value;
        }
        foreach ($conditions as $key => $value) {
            $params["where_{$key}"] = $value;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Delete a record (cached statement with LIMIT 1 for safety)
     */
    public function delete($id): bool
    {
        $cacheKey = 'delete_' . $this->table;

        if (!isset($this->stmtCache[$cacheKey])) {
            $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
            $this->stmtCache[$cacheKey] = $this->db->prepare($sql);
        }

        return $this->stmtCache[$cacheKey]->execute(['id' => $id]);
    }

    /**
     * Delete multiple records by condition
     */
    public function deleteWhere(array $conditions): int
    {
        if (empty($conditions)) {
            return 0;
        }

        $where = [];
        foreach (array_keys($conditions) as $key) {
            $where[] = "{$key} = :{$key}";
        }

        $sql = "DELETE FROM {$this->table} WHERE " . implode(' AND ', $where);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($conditions);
        return $stmt->rowCount();
    }

    /**
     * Count records (optimized with fetchColumn)
     */
    public function count(array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}";

        if (!empty($conditions)) {
            $where = [];
            foreach (array_keys($conditions) as $key) {
                $where[] = "{$key} = :{$key}";
            }
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($conditions);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Check if record exists (faster than count for existence check)
     */
    public function exists(array $conditions): bool
    {
        if (empty($conditions)) {
            return false;
        }

        $where = [];
        foreach (array_keys($conditions) as $key) {
            $where[] = "{$key} = :{$key}";
        }

        $sql = "SELECT 1 FROM {$this->table} WHERE " . implode(' AND ', $where) . ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($conditions);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Paginate records
     */
    public function paginate(int $page = 1, int $perPage = 10, array $conditions = [], ?string $orderBy = null): array
    {
        $offset = ($page - 1) * $perPage;
        $total = $this->count($conditions);
        $data = $this->findAll($conditions, $orderBy, $perPage, $offset);

        return [
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total)
            ]
        ];
    }

    /**
     * Execute raw query
     */
    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Execute raw statement
     */
    public function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Get database connection (for transactions)
     */
    public function getDb(): PDO
    {
        return $this->db;
    }

    /**
     * Begin database transaction
     */
    public function beginTransaction(): bool
    {
        return $this->db->beginTransaction();
    }

    /**
     * Commit database transaction
     */
    public function commit(): bool
    {
        return $this->db->commit();
    }

    /**
     * Rollback database transaction
     */
    public function rollBack(): bool
    {
        return $this->db->rollBack();
    }

    /**
     * Increment a column value (atomic operation)
     */
    public function increment($id, string $column, int|float $amount = 1): bool
    {
        $sql = "UPDATE {$this->table} SET {$column} = {$column} + :amount WHERE {$this->primaryKey} = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['amount' => $amount, 'id' => $id]);
    }

    /**
     * Decrement a column value (atomic operation)
     */
    public function decrement($id, string $column, int|float $amount = 1): bool
    {
        $sql = "UPDATE {$this->table} SET {$column} = {$column} - :amount WHERE {$this->primaryKey} = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['amount' => $amount, 'id' => $id]);
    }

    /**
     * Get first record
     */
    public function first(array $conditions = [], ?string $orderBy = null): array|false
    {
        $results = $this->findAll($conditions, $orderBy, 1);
        return $results[0] ?? false;
    }

    /**
     * Get last record
     */
    public function last(array $conditions = [], ?string $column = null): array|false
    {
        $orderBy = $column ?? $this->primaryKey;
        $results = $this->findAll($conditions, "{$orderBy} DESC", 1);
        return $results[0] ?? false;
    }
}
