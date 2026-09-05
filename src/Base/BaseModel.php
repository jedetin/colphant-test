<?php

class BaseModel
{

    protected $primaryKey = 'id';      // default, overridable per model
    protected $primaryKeyType = 'i';   // 'i' for int, 's' for UUID/string

    protected $database;
    protected $table;

    public function __construct($database)
    {
        // If $database is just a string (the name), we create the actual Connection Object here.
        if (is_string($database)) {
            $this->database = new Database($database);
        } else {
            $this->database = $database;
        }
    }

    protected function findById($id, ?string $key = null, ?string $keyType = null): ?array
    {
        // [1] Falls back to model's primaryKey if no override given
        $col  = $key     ?? $this->primaryKey;
        $type = $keyType ?? $this->primaryKeyType;

        $stmt = $this->database->prepare(
            "SELECT * FROM {$this->table} WHERE `{$col}` = ? LIMIT 1"
        );
        $stmt->bind_param($type, $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result ?: null; // explicit null instead of false
    }

    public function findByName(mixed $value, string $column, ?int $limit = null): array
    {
        // Security: column must be in allowedColumns if the list is defined
        if (!empty($this->allowedColumns) && !in_array($column, $this->allowedColumns, true)) {
            throw new \InvalidArgumentException("Column '{$column}' is not queryable.");
        }

        // [2] Infer bind type from value — not from primaryKeyType (that was a bug)
        $type  = is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');

        // [2] Optional limit — default null means no cap (caller's choice)
        $limitSql = $limit ? " LIMIT {$limit}" : '';

        $stmt = $this->database->prepare(
            "SELECT * FROM {$this->table} WHERE `{$column}` = ?{$limitSql}"
        );
        $stmt->bind_param($type, $value);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    protected function findAll(int $limit = 25, int $offset = 0): array
    {
        // [3] Sane default: 25 rows. Caller passes limit/offset for pagination.
        // Passing limit=0 is treated as "no limit" — use deliberately, not by default.
        if ($limit === 0) {
            $stmt = $this->database->prepare("SELECT * FROM {$this->table}");
        } else {
            $stmt = $this->database->prepare(
                "SELECT * FROM {$this->table} LIMIT ? OFFSET ?"
            );
            $stmt->bind_param('ii', $limit, $offset);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    protected function create($data)
    {
        $columns = implode(", ", array_keys($data));
        $placeholders = implode(", ", array_fill(0, count($data), "?"));
        $stmt = $this->database->prepare("INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})");

        $types = $this->getParamTypes($data);
        $stmt->bind_param($types, ...array_values($data));

        $stmt->execute();
        return $this->database->connection->insert_id;
    }

    protected function update($id, $data)
    {
        $setClause = implode(" = ?, ", array_keys($data)) . " = ?";
        $stmt = $this->database->prepare("UPDATE {$this->table} SET {$setClause} WHERE {$this->primaryKey} = ?");

        $values = array_values($data);
        $values[] = $id;

        $types = $this->getParamTypes($data) . $this->primaryKeyType;
        $stmt->bind_param($types, ...$values);

        return $stmt->execute();
    }

    /** [Efficiency] Helper to determine types (integer vs string) instead of forcing "s" for everything.
     */
    private function getParamTypes($data)
    {
        $types = '';
        foreach ($data as $value) {
            if (is_null($value))   $types .= 's'; // bind null as string, set actual null below
            elseif (is_int($value))    $types .= 'i';
            elseif (is_float($value))  $types .= 'd';
            else                       $types .= 's';
        }
        return $types;
    }

    protected function delete($id)
    {
        $stmt = $this->database->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->bind_param($this->primaryKeyType, $id);
        return $stmt->execute();
    }

    protected function countAll(): int
    {
        $stmt = $this->database->prepare(
            "SELECT COUNT(*) FROM {$this->table}"
        );
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        return (int) $count;
    }
}
