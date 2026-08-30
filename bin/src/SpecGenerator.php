<?php
require_once 'Generator.php';
require_once 'CPGeneratorResult.php';


class SpecGenerator extends CPGenerator
{
    public function name(): string
    {
        return 'spec';
    }
    /**
     * Normalize MySQL DATA_TYPE into types useful to the CRUD generator.
     */
    public function normalizeType(
        string $dataType,
        string $columnType
    ): string {
        $dataType = strtolower($dataType);
        $columnType = strtolower($columnType);

        if ($dataType === 'enum') {
            return 'enum';
        }

        if ($dataType === 'set') {
            return 'set';
        }

        return match ($dataType) {
            'tinyint', 'smallint', 'mediumint', 'int', 'integer',
            'bigint' => $dataType === 'bigint' ? 'bigint' : 'int',

            'decimal', 'numeric' => 'decimal',
            'float' => 'float',
            'double', 'real' => 'double',

            'date' => 'date',
            'datetime' => 'datetime',
            'timestamp' => 'timestamp',
            'time' => 'time',
            'year' => 'year',

            'boolean', 'bool' => 'boolean',

            'json' => 'json',

            'text', 'tinytext', 'mediumtext', 'longtext' => 'text',

            'blob', 'tinyblob', 'mediumblob', 'longblob' => 'blob',

            'binary', 'varbinary' => 'binary',

            default => 'string',
        };
    }


    /**
     * Extract ENUM values from:
     *
     * enum('pending','confirmed','cancelled')
     */
    public function parseEnumValues(string $columnType): array
    {
        if (
            !preg_match(
                '/^enum\((.*)\)$/i',
                $columnType,
                $matches
            )
        ) {
            return [];
        }

        $values = str_getcsv(
            $matches[1],
            ',',
            "'",
            "\\"
        );

        return array_map(
            static fn(string $value): string =>
            trim($value, "'\""),
            $values
        );
    }


    /**
     * Try to find a sensible display column for a referenced table.
     *
     * This is necessarily heuristic because MySQL FK metadata does not
     * contain UI/display semantics.
     */
    public function inferDisplayColumn(
        PDO $pdo,
        string $database,
        string $table
    ): ?string {
        $stmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = :database
          AND TABLE_NAME = :table
        ORDER BY ORDINAL_POSITION
    ");

        $stmt->execute([
            'database' => $database,
            'table'    => $table
        ]);

        $columns = array_column(
            $stmt->fetchAll(),
            'COLUMN_NAME'
        );

        $preferred = [
            'name',
            'title',
            'label',
            'display_name',
            'username',
            'email',
            'room_number',
            'code',
            'description'
        ];

        foreach ($preferred as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        /*
     * If nothing obvious exists, use the first non-ID column.
     */
        foreach ($columns as $column) {
            if (!in_array($column, ['id', 'uuid'], true)) {
                return $column;
            }
        }

        return $columns[0] ?? null;
    }


    /**
     * Infer how the CRUD UI should render the field.
     */
    public function inferUiType(
        string $name,
        string $type,
        array $column,
        bool $isForeignKey
    ): string {
        if ($isForeignKey) {
            return 'dropdown';
        }

        if ($type === 'enum') {
            return 'dropdown';
        }

        if ($type === 'boolean') {
            return 'checkbox';
        }

        if ($type === 'date') {
            return 'date';
        }

        if ($type === 'datetime' || $type === 'timestamp') {
            return 'datetime';
        }

        if (
            in_array(
                $type,
                ['int', 'bigint', 'decimal', 'float', 'double'],
                true
            )
        ) {
            return 'number';
        }

        if ($type === 'text') {
            return 'textarea';
        }

        if ($type === 'json') {
            return 'json';
        }

        if (
            preg_match(
                '/(password|token|secret)/i',
                $name
            )
        ) {
            return 'password';
        }

        if (
            preg_match(
                '/(email)/i',
                $name
            )
        ) {
            return 'email';
        }

        if (
            preg_match(
                '/(url|website|link)/i',
                $name
            )
        ) {
            return 'url';
        }

        return 'text';
    }


    /**
     * Fields that make sense in a generic search box.
     */
    public function isSearchableField(
        string $name,
        string $type,
        bool $isForeignKey
    ): bool {
        if ($isForeignKey) {
            return false;
        }

        if (
            in_array(
                $type,
                ['text', 'string'],
                true
            )
        ) {
            return true;
        }

        return preg_match(
            '/(name|title|email|username|code|number)/i',
            $name
        ) === 1;
    }


    /**
     * Fields that can reasonably become exact filters.
     */
    public function isFilterableField(
        string $type,
        bool $isForeignKey
    ): bool {
        return $isForeignKey ||
            in_array(
                $type,
                [
                    'int',
                    'bigint',
                    'decimal',
                    'float',
                    'double',
                    'date',
                    'datetime',
                    'timestamp',
                    'enum',
                    'boolean'
                ],
                true
            );
    }


    public function generate(): CPGeneratorResult
    {
        $this->section();


        $dsn = 'mysql:host=localhost;dbname=test;charset=utf8mb4';
        $username = 'root';
        $password = '';

        // $outputFile = __DIR__ . '/spec.json';
        $outputFile = 'spec.json';

        // $this->writeFile(
        //     'output/spec.json',
        //     $json
        // );
        $this->info('Reading database schema');
        $pdo = new PDO(
            $dsn,
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );

        $database = $pdo->query('SELECT DATABASE()')->fetchColumn();

        if (!$database) {
            throw new RuntimeException('Could not determine current database.');
        }

        $spec = [];
        $this->info('Building specification');
        /*
     * Get all tables.
     */
        $tablesStmt = $pdo->prepare("
        SELECT TABLE_NAME
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = :database
          AND TABLE_TYPE = 'BASE TABLE'
        ORDER BY TABLE_NAME
    ");

        $tablesStmt->execute([
            'database' => $database
        ]);

        $tables = $tablesStmt->fetchAll();

        foreach ($tables as $tableRow) {
            $table = $tableRow['TABLE_NAME'];

            /*
         * Columns
         */
            $columnsStmt = $pdo->prepare("
            SELECT
                COLUMN_NAME,
                DATA_TYPE,
                COLUMN_TYPE,
                IS_NULLABLE,
                COLUMN_DEFAULT,
                EXTRA,
                COLUMN_KEY,
                ORDINAL_POSITION
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = :database
              AND TABLE_NAME = :table
            ORDER BY ORDINAL_POSITION
        ");

            $columnsStmt->execute([
                'database' => $database,
                'table'    => $table
            ]);

            $columns = $columnsStmt->fetchAll();

            /*
         * Foreign keys
         */
            $fkStmt = $pdo->prepare("
            SELECT
                kcu.COLUMN_NAME,
                kcu.REFERENCED_TABLE_NAME,
                kcu.REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE kcu
            WHERE kcu.TABLE_SCHEMA = :database
              AND kcu.TABLE_NAME = :table
              AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
        ");

            $fkStmt->execute([
                'database' => $database,
                'table'    => $table
            ]);

            $foreignKeys = [];

            foreach ($fkStmt->fetchAll() as $fk) {
                $foreignKeys[$fk['COLUMN_NAME']] = [
                    'ref_table'  => $fk['REFERENCED_TABLE_NAME'],
                    'ref_column' => $fk['REFERENCED_COLUMN_NAME'],
                ];
            }

            /*
         * Primary key
         */
            $primaryKey = null;

            foreach ($columns as $column) {
                if ($column['COLUMN_KEY'] === 'PRI') {
                    $primaryKey = $column['COLUMN_NAME'];
                    break;
                }
            }

            /*
         * Build field specification.
         */
            $fields = [];

            foreach ($columns as $column) {
                $name = $column['COLUMN_NAME'];

                $type = $this->normalizeType(
                    $column['DATA_TYPE'],
                    $column['COLUMN_TYPE']
                );

                $isAuto = str_contains(
                    strtolower($column['EXTRA'] ?? ''),
                    'auto_increment'
                );

                $isGenerated = str_contains(
                    strtolower($column['EXTRA'] ?? ''),
                    'generated'
                );

                $insertable = !$isAuto && !$isGenerated;
                $updatable = !$isAuto && !$isGenerated;

                /*
             * Common timestamp fields are usually managed by DB/application.
             */
                if (in_array($name, [
                    'created_at',
                    'updated_at',
                    'deleted_at'
                ], true)) {
                    $insertable = false;
                    $updatable = false;
                }

                $field = [
                    'type'       => $type,
                    'nullable'   => $column['IS_NULLABLE'] === 'YES',
                    'auto'       => $isAuto,
                    'insertable' => $insertable,
                    'updatable'  => $updatable,
                ];

                /*
             * Default value
             */
                if ($column['COLUMN_DEFAULT'] !== null) {
                    $field['default'] = $column['COLUMN_DEFAULT'];
                }

                /*
             * ENUM values
             */
                if ($type === 'enum') {
                    $field['enum_values'] = $this->parseEnumValues(
                        $column['COLUMN_TYPE']
                    );
                }

                /*
             * Foreign key
             */
                if (isset($foreignKeys[$name])) {
                    $fk = $foreignKeys[$name];

                    $field['fk'] = [
                        'ref_table'    => $fk['ref_table'],
                        'ref_column'   => $fk['ref_column'],
                        'display_column' => $this->inferDisplayColumn(
                            $pdo,
                            $database,
                            $fk['ref_table']
                        ),
                    ];
                }

                /*
             * UI hints.
             */
                $field['ui'] = $this->inferUiType(
                    $name,
                    $type,
                    $column,
                    isset($foreignKeys[$name])
                );

                /*
             * Search/filter behavior.
             */
                $field['searchable'] = $this->isSearchableField(
                    $name,
                    $type,
                    isset($foreignKeys[$name])
                );

                $field['filterable'] = $this->isFilterableField(
                    $type,
                    isset($foreignKeys[$name])
                );

                $fields[$name] = $field;
            }

            /*
         * Query parameters.
         */
            $queryParams = [
                'page' => [
                    'type'        => 'int',
                    'default'     => 1,
                    'description' => 'Page number'
                ],
                'per_page' => [
                    'type'        => 'int',
                    'default'     => 20,
                    'description' => 'Records per page (max 100)'
                ],
            ];

            foreach ($fields as $fieldName => $field) {
                if (!($field['filterable'] ?? false)) {
                    continue;
                }

                $queryParams[$fieldName] = [
                    'type'       => $field['type'],
                    'description' => 'Filter by exact ' . $fieldName,
                    'filterable' => true,
                ];

                if (isset($field['enum_values'])) {
                    $queryParams[$fieldName]['enum_values'] =
                        $field['enum_values'];
                }
            }

            /** Default generation of the  visual columns (LIMIT 5)
             * For ≤5 columns, all columns are included. For >5, you'll get exactly first 3 + last 2.
             */
            $displayColumns = array_keys($fields);

            if (count($displayColumns) > 5) {
                $displayColumns = array_merge(
                    array_slice($displayColumns, 0, 3),
                    array_slice($displayColumns, -2)
                );
            }



            $spec[$table] = [
                'primary_key' => $primaryKey,
                'fields'      => $fields,
                'query_params' => $queryParams,
                'display_columns' => $displayColumns,
            ];
        }
        $this->info('Generating the file');
        // echo CPGeneratorResult::message("Generating the file");
        /*
     * Write JSON.
     */
        $json = json_encode(
            $spec,
            JSON_PRETTY_PRINT |
                JSON_UNESCAPED_SLASHES |
                JSON_UNESCAPED_UNICODE
        );

        if ($json === false) {
            throw new RuntimeException(
                'Failed to encode JSON: ' . json_last_error_msg()
            );
        }

        file_put_contents($outputFile, $json . PHP_EOL);

        echo "Generated: {$outputFile}" . PHP_EOL;
        echo "Tables: " . count($spec) . PHP_EOL;


        return CPGeneratorResult::success(
            'Specification generated'
        );
    }
}
