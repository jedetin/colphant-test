<?php
require_once 'Generator.php';
require_once 'CPGeneratorResult.php';

final class ModelGenerator extends CPGenerator
{
    /**
     * 'bookings' → 'Bookings'  (for class name)
     * 'booking_items' → 'BookingItems'
     */
    public function toClassName(string $table): string
    {
        return implode('', array_map('ucfirst', explode('_', $table)));
    }

    /**
     * Map spec field type → MySQLi bind char for PK.
     * Full field binding is handled by BaseModel::getParamTypes().
     */
    public function pkBindType(string $type): string
    {
        return match ($type) {
            'i', 'int', 'bigint', 'tinyint', 'smallint', 'mediumint' => 'i',
            'f', 'float', 'double', 'decimal'                         => 'd',
            default                                                    => 's',
        };
    }

    /**
     * Emit a PHP flat array literal from a string array.
     * ['a', 'b', 'c']
     */
    public function phpStringArray(array $items): string
    {
        if (empty($items)) return '[]';
        $quoted = array_map(fn($v) => "'{$v}'", $items);
        return '[' . implode(', ', $quoted) . ']';
    }

    /**
     * Emit a PHP associative array for column → type map.
     * ['name' => 'varchar', 'status' => 'enum', ...]
     */
    public function phpColumnTypesMap(array $columnTypes): string
    {
        if (empty($columnTypes)) return '[]';

        $lines = [];
        foreach ($columnTypes as $col => $type) {
            $lines[] = "        '{$col}' => '{$type}'";
        }
        return "[\n" . implode(",\n", $lines) . "\n    ]";
    }

    /**
     * Emit a PHP array-of-arrays for enum field map.
     * [
     *     'status' => ['pending', 'confirmed', 'cancelled'],
     * ]
     */
    public function phpEnumMap(array $enumFields): string
    {
        if (empty($enumFields)) return '[]';

        $lines = [];
        foreach ($enumFields as $col => $values) {
            $quoted  = array_map(fn($v) => "'{$v}'", $values);
            $lines[] = "        '{$col}' => [" . implode(', ', $quoted) . "]";
        }
        return "[\n" . implode(",\n", $lines) . "\n    ]";
    }

    public function name(): string
    {
        return 'model';
    }

    public function generate(): CPGeneratorResult
    {
        $opts     = getopt('', ['spec::', 'out::']);
        $specPath = $opts['spec'] ?? __DIR__ . '\..\spec.json';
        $outDir   = $opts['out']  ?? $_SERVER['DOCUMENT_ROOT'] . '\src\App';

        if (!file_exists($specPath)) {
            exit("[ERROR] spec.json not found at: {$specPath}\n");
        }

        $spec = json_decode(file_get_contents($specPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            exit("[ERROR] spec.json is not valid JSON: " . json_last_error_msg() . "\n");
        }

        if (!is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        $generated = 0;

        foreach ($spec as $table => $def) {

            $className  = $this->toClassName($table) . 'Model';
            $primaryKey = $def['primary_key']      ?? 'id';
            $pkRawType  = $def['primary_key_type'] ?? 'i';
            $pkBindChar = $this->pkBindType($pkRawType);
            $fields     = $def['fields']           ?? [];

            // ---------------------------------------------------------------
            // Single loop — derive all field lists from spec
            // ---------------------------------------------------------------

            $allowedColumns    = []; // filterable:true  → exact match via findByColumn()
            $searchableColumns = []; // searchable:true  → LIKE or exact via search()
            $insertable        = []; // insertable:true  → included in create()
            $updatable         = []; // updatable:true   → included in update()
            $enumFields        = []; // type:enum        → validated before write
            $columnTypes       = []; // col → type       → drives search strategy

            foreach ($fields as $col => $meta) {
                if (!empty($meta['filterable']))  $allowedColumns[]    = $col;
                if (!empty($meta['searchable']))  $searchableColumns[] = $col;
                if (!empty($meta['insertable']))  $insertable[]        = $col;
                if (!empty($meta['updatable']))   $updatable[]         = $col;

                if (($meta['type'] ?? '') === 'enum' && !empty($meta['enum_values'])) {
                    $enumFields[$col] = $meta['enum_values'];
                }

                $columnTypes[$col] = $meta['type'] ?? 'varchar';
            }

            // ---------------------------------------------------------------
            // Build string representations for the class body
            // ---------------------------------------------------------------

            $allowedStr     = $this->phpStringArray($allowedColumns);
            $searchableStr  = $this->phpStringArray($searchableColumns);
            $insertStr      = $this->phpStringArray($insertable);
            $updatStr       = $this->phpStringArray($updatable);
            $enumStr        = $this->phpEnumMap($enumFields);
            $columnTypesStr = $this->phpColumnTypesMap($columnTypes);

            // ---------------------------------------------------------------
            // Enum validation method — only emitted if table has enum fields
            // ---------------------------------------------------------------

            $enumValidateMethod = '';
            if (!empty($enumFields)) {
                $enumValidateMethod = <<<PHP

    /**
     * Validate that any enum fields in \$data contain only allowed values.
     * Called internally before create() and update().
     * Throws InvalidArgumentException on violation.
     */
    protected function validateEnums(array \$data): void
    {
        foreach (\$this->enumFields as \$col => \$allowed) {
            if (isset(\$data[\$col]) && !in_array(\$data[\$col], \$allowed, true)) {
                throw new \\InvalidArgumentException(
                    "Invalid value for column '{$table}.{\$col}'. "
                    . "Allowed: " . implode(', ', \$allowed)
                );
            }
        }
    }

PHP;
            }

            $enumCallCreate = !empty($enumFields) ? "\n        \$this->validateEnums(\$data);" : '';
            $enumCallUpdate = !empty($enumFields) ? "\n        \$this->validateEnums(\$data);" : '';
            $stripComment   = "// Strip any fields the spec marks as non-insertable/non-updatable.\n        // Caller should not send them, but we enforce it here as a safety net.";

            // ---------------------------------------------------------------
            // Class body
            // ---------------------------------------------------------------

            $classBody = <<<PHP
<?php

/**
 * {$className}
 *
 * AUTO-GENERATED by ModelGenerator — do not edit directly.
 * Regenerate via the generator UI or CLI.
 *
 * Source table : {$table}
 * Primary key  : {$primaryKey} ({$pkBindChar})
 * Generated    : {$_SERVER['REQUEST_TIME_FLOAT']}
 */
class {$className} extends BaseModel
{
    protected \$table          = '{$table}';
    protected \$primaryKey     = '{$primaryKey}';
    protected \$primaryKeyType = '{$pkBindChar}';

    /**
     * Columns safe for exact-match filtering via findByColumn().
     * Derived from filterable:true in spec.json.
     */
    protected \$allowedColumns = {$allowedStr};

    /**
     * Columns available for free-text search (LIKE or exact by type).
     * Derived from searchable:true in spec.json.
     */
    protected \$searchableColumns = {$searchableStr};

    /**
     * Column type map — drives LIKE vs exact match strategy in search().
     * Derived from field type in spec.json.
     */
    protected \$columnTypes = {$columnTypesStr};

    /**
     * Fields the spec permits in INSERT payloads.
     * Auto/non-insertable fields (e.g. id, created_at) are excluded.
     */
    protected \$insertableFields = {$insertStr};

    /**
     * Fields the spec permits in UPDATE payloads.
     */
    protected \$updatableFields = {$updatStr};

    /**
     * Enum fields and their allowed values.
     * Validated before every create() and update().
     */
    protected \$enumFields = {$enumStr};

    // ------------------------------------------------------------------
    // Public CRUD surface
    // ------------------------------------------------------------------

    public function getById(int \$id): ?array
    {
        return \$this->findById(\$id);
    }

    public function getAll(int \$limit = 25, int \$offset = 0): array
    {
        return \$this->findAll(\$limit, \$offset);
    }

    public function total(): int
    {
        return \$this->countAll();
    }

    public function getBy(string \$column, mixed \$value, ?int \$limit = null): array
    {
        return \$this->findByColumn(\$value, \$column, \$limit);
    }

    public function search(string \$term, ?string \$col = null, int \$limit = 25, int \$offset = 0): array
    {
        return parent::search(\$term, \$col, \$limit, \$offset);
    }

    public function countSearch(string \$term, ?string \$col = null): int
    {
        return parent::countSearch(\$term, \$col);
    }

    public function add(array \$data): int
    {{$enumCallCreate}
        {$stripComment}
        \$data = array_intersect_key(\$data, array_flip(\$this->insertableFields));
        return \$this->create(\$data);
    }

    public function edit(int \$id, array \$data): bool
    {{$enumCallUpdate}
        {$stripComment}
        \$data = array_intersect_key(\$data, array_flip(\$this->updatableFields));
        return \$this->update(\$id, \$data);
    }

    public function remove(int \$id): bool
    {
        return \$this->delete(\$id);
    }
{$enumValidateMethod}}
PHP;

            // ---------------------------------------------------------------
            // Write file
            // ---------------------------------------------------------------

            $outFile = "{$outDir}/{$className}.php";
            file_put_contents($outFile, $classBody);

            echo "[OK] Generated: {$outFile}<br/>";
            $generated++;
        }

        echo "\nDone. {$generated} model(s) generated.\n";

        return CPGeneratorResult::success("Generated Successfully.");
    }
}
