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
            default                                                    => 's', // uuid, varchar, char
        };
    }

    /**
     * Emit a PHP array literal from a flat string array, inline style.
     * ['a', 'b', 'c']
     */
    public function phpStringArray(array $items): string
    {
        if (empty($items)) return '[]';
        $quoted = array_map(fn($v) => "'{$v}'", $items);
        return '[' . implode(', ', $quoted) . ']';
    }

    /**
     * Emit a PHP array-of-arrays for enum field map, multi-line.
     * [
     *     'status' => ['pending', 'confirmed', 'cancelled'],
     * ]
     */
    public function phpEnumMap(array $enumFields): string
    {
        if (empty($enumFields)) return '[]';

        $lines = [];
        foreach ($enumFields as $col => $values) {
            $quoted = array_map(fn($v) => "'{$v}'", $values);
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
        $opts    = getopt('', ['spec::', 'out::']);
        $specPath = $opts['spec'] ?? __DIR__ . '\..\spec.json';
        $outDir   = $opts['out']  ?? $_SERVER['DOCUMENT_ROOT'] . '\src\App';

        // -----------------------------------------------------------------------
        // Bootstrap
        // -----------------------------------------------------------------------

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
        // $this->log('Reading spec.json');

        // $spec = $this->context->spec();

        // generate model classes
        $generated = 0;

        foreach ($spec as $table => $def) {

            $className   = $this->toClassName($table) . 'Model';
            $primaryKey  = $def['primary_key']      ?? 'id';
            $pkRawType   = $def['primary_key_type'] ?? 'i';
            $pkBindChar  = $this->pkBindType($pkRawType);
            $fields      = $def['fields']           ?? [];

            // Derive field lists from spec
            $allowedColumns = []; // filterable:true → safe for findByColumn()
            $insertable     = []; // insertable:true → included in create()
            $updatable      = []; // updatable:true  → included in update()
            $enumFields     = []; // type:enum       → validated before write

            foreach ($fields as $col => $meta) {
                if (!empty($meta['filterable']))    $allowedColumns[] = $col;
                if (!empty($meta['insertable']))    $insertable[]     = $col;
                if (!empty($meta['updatable']))     $updatable[]      = $col;
                if (($meta['type'] ?? '') === 'enum' && !empty($meta['enum_values'])) {
                    $enumFields[$col] = $meta['enum_values'];
                }
            }

            // -----------------------------------------------------------------------
            // Build class body
            // -----------------------------------------------------------------------

            $allowedStr = $this->phpStringArray($allowedColumns);
            $insertStr  = $this->phpStringArray($insertable);
            $updatStr   = $this->phpStringArray($updatable);
            $enumStr    = $this->phpEnumMap($enumFields);

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
                    "Invalid value '\${\$col}' for column '{$table}.{\$col}'. "
                    . "Allowed: " . implode(', ', \$allowed)
                );
            }
        }
    }

PHP;
            }

            // Enum validation call — injected into add() and edit() if enums exist
            $enumCallCreate = !empty($enumFields) ? "\n        \$this->validateEnums(\$data);" : '';
            $enumCallUpdate = !empty($enumFields) ? "\n        \$this->validateEnums(\$data);" : '';

            // Strip non-insertable / non-updatable keys from payload defensively
            $stripComment = "// Strip any fields the spec marks as non-insertable/non-updatable.\n        // Caller should not send them, but we enforce it here as a safety net.";

            $classBody = <<<PHP
<?php

/**
 * {$className}
 *
 * AUTO-GENERATED by generate_models.php — do not edit directly.
 * Regenerate by running:  php generate_models.php
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
     * Columns safe to query via findByColumn().
     * Derived from filterable:true in spec.json.
     */
    protected \$allowedColumns = {$allowedStr};

    /**
     * Fields the spec permits in INSERT payloads.
     * Auto/non-insertable fields (e.g. id, created_at) are excluded.
     */
    protected \$insertableFields = {$insertStr};

    /**
     * Fields the spec permits in UPDATE payloads.
     */
    protected \$updatableFields  = {$updatStr};

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

    public function getBy(string \$column, mixed \$value, ?int \$limit = null): array
    {
        return \$this->findByColumn(\$value, \$column, \$limit);
    }

    public function total(): int
    {
        return \$this->countAll();
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

            // -----------------------------------------------------------------------
            // Write file
            // -----------------------------------------------------------------------

            $outFile = "{$outDir}/{$className}.php";
            file_put_contents($outFile, $classBody);

            echo "[OK] Generated: {$outFile}<br/>";
            $generated++;
        }

        echo "\nDone. {$generated} model(s) generated.\n";


        return CPGeneratorResult::success("Generated Successfully.");
    }
}
