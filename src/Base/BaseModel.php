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

    protected function findById($id)
    {
        $stmt = $this->database->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey}  = ? LIMIT 1");
        $stmt->bind_param($this->primaryKeyType, $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function findByName($value, $column)
    {
        // [Uniformization] Allowing search by any key dynamically
        $query = "SELECT * FROM {$this->table} WHERE `{$column}` = ?";
        $stmt = $this->database->prepare($query);
        $stmt->bind_param($this->primaryKeyType, $value);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    protected function findAll()
    {
        $result = $this->database->query("SELECT * FROM {$this->table}");
        return $result->fetch_all(MYSQLI_ASSOC);
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
}
