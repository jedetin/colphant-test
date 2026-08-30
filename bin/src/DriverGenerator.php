<?php

final class DriverGenerator extends CPGenerator
{
    public function name(): string
    {
        return 'model';
    }

    public function toClassNameD(string $table): string
    {
        return implode('', array_map('ucfirst', explode('_', $table)));
    }

    /** Indent a block of text by N spaces */
    public function indent(string $text, int $spaces = 8): string
    {
        $pad = str_repeat(' ', $spaces);
        return implode("\n", array_map(
            fn($line) => $line === '' ? '' : $pad . $line,
            explode("\n", $text)
        ));
    }

    /**
     * Build the 'create' case body.
     * - Lists every insertable field as a required/optional input line
     * - Adds enum validation comment per enum field
     * - Adds FK note per FK field
     */
    public function buildCreateCase(string $table, array $fields): string
    {
        $insertable = array_filter($fields, fn($m) => !empty($m['insertable']));

        $inputLines   = [];
        $requiredKeys = [];

        foreach ($insertable as $col => $meta) {
            $nullable = !empty($meta['nullable']) ? 'optional' : 'required';
            $note     = '';

            if (($meta['type'] ?? '') === 'enum') {
                $vals = implode(', ', $meta['enum_values'] ?? []);
                $note = " // enum: [{$vals}]";
            } elseif (!empty($meta['fk'])) {
                $ref  = $meta['fk']['ref_table'] . '.' . $meta['fk']['ref_column'];
                $note = " // FK → {$ref}";
            }

            $inputLines[] = "        '{$col}' => \$input['{$col}'] ?? null,{$note} // {$nullable}";

            if ($nullable === 'required') {
                $requiredKeys[] = $col;
            }
        }

        $inputBlock = implode("\n", $inputLines);

        // Required field guard
        $requiredCheck = '';
        if (!empty($requiredKeys)) {
            $quoted = array_map(fn($k) => "'{$k}'", $requiredKeys);
            $list   = implode(', ', $quoted);
            $requiredCheck = <<<PHP
        \$required = [{$list}];
        foreach (\$required as \$field) {
            if (empty(\$input[\$field])) {
                respond(false, null, "Field '{{\$field}}' is required.");
            }
        }

PHP;
        }

        return <<<PHP
        requireMethod('POST');
        \$input = jsonBody();

{$requiredCheck}        \$data = [
{$inputBlock}
        ];

        // Null out optional fields that were not sent — avoids inserting empty strings
        \$data = array_filter(\$data, fn(\$v) => \$v !== null);

        \$newId = \$model->add(\$data);
        respond(true, ['id' => \$newId], null);
PHP;
    }

    /**
     * Build the 'update' case body.
     */
    public function buildUpdateCase(string $table, array $fields): string
    {
        $updatable = array_filter($fields, fn($m) => !empty($m['updatable']));

        $inputLines = [];
        foreach ($updatable as $col => $meta) {
            $note = '';
            if (($meta['type'] ?? '') === 'enum') {
                $vals = implode(', ', $meta['enum_values'] ?? []);
                $note = " // enum: [{$vals}]";
            } elseif (!empty($meta['fk'])) {
                $note = " // FK → " . $meta['fk']['ref_table'] . '.' . $meta['fk']['ref_column'];
            }
            $inputLines[] = "            '{$col}' => \$input['{$col}'] ?? null,{$note}";
        }

        $inputBlock = implode("\n", $inputLines);

        return <<<PHP
        requireMethod('POST');
        \$id    = (int)(\$_GET['id'] ?? 0);
        \$input = jsonBody();

        if (!\$id) respond(false, null, "Missing or invalid 'id' parameter.");

        // Only pass fields that were actually sent in the request
        \$data = array_filter([
{$inputBlock}
        ], fn(\$v) => \$v !== null);

        if (empty(\$data)) respond(false, null, "No updatable fields provided.");

        \$ok = \$model->edit(\$id, \$data);
        respond(\$ok, ['updated' => \$ok], \$ok ? null : "Update failed or row not found.");
PHP;
    }

    /**
     * Build the 'list' case body — includes filter support for filterable fields.
     */
    public function buildListCase(string $table, array $fields): string
    {
        $filterable = array_keys(array_filter($fields, fn($m) => !empty($m['filterable'])));

        if (empty($filterable)) {
            return <<<PHP
        requireMethod('GET');
        \$rows = \$model->getAll();
        respond(true, \$rows, null);
PHP;
        }

        $filterLines = [];
        foreach ($filterable as $col) {
            $filterLines[] = "    if (!empty(\$_GET['{$col}'])) \$rows = \$model->getBy('{$col}', \$_GET['{$col}']);";
        }
        $filterBlock = implode("\n", $filterLines);

        return <<<PHP
        requireMethod('GET');

        // Filterable columns (from spec filterable:true): {$table}
        // Usage: ?action=list&status=pending  or  ?action=list  (returns all)
        \$rows = \$model->getAll();

{$filterBlock}

        respond(true, \$rows, null);
PHP;
    }


    public function generate(): CPGeneratorResult
    {
        $opts     = getopt('', ['spec::', 'out::']);
        $specPath = $opts['spec'] ?? __DIR__ . '\..\spec.json';
        $outDir   = $opts['out']  ?? $_SERVER['DOCUMENT_ROOT'] . '/api';
        // $outDir   = $opts['out']  ?? $_SERVER['DOCUMENT_ROOT'] . '\src\App';


        // -----------------------------------------------------------------------
        // Bootstrap
        // -----------------------------------------------------------------------

        if (!file_exists($specPath)) {
            exit("[ERROR] spec.json not found at: {$specPath}\n");
        }

        $spec = json_decode(file_get_contents($specPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            exit("[ERROR] spec.json invalid JSON: " . json_last_error_msg() . "\n");
        }

        if (!is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        $generated = 0;

        foreach ($spec as $table => $def) {

            $className = $this->toClassNameD($table) . 'Model';
            $fields    = $def['fields'] ?? [];
            $pk        = $def['primary_key'] ?? 'id';

            // Build each case body individually
            $createBody = $this->buildCreateCase($table, $fields);
            $updateBody = $this->buildUpdateCase($table, $fields);
            $listBody   = $this->buildListCase($table, $fields);

            $driverBody = <<<PHP
            <?php

            /**
             * API Driver: {$table}
             *
             * AUTO-GENERATED by generate_drivers.php — do not edit directly.
             * Regenerate by running:  php generate_drivers.php
             *
             * Actions (via ?action=):
             *   list      GET   — all rows, supports filterable query params
             *   read      GET   — single row by ?id=
             *   create    POST  — insert a new row (JSON body)
             *   update    POST  — update a row by ?id= (JSON body, partial allowed)
             *   delete    POST  — delete a row by ?id=
             *
             * Response envelope:
             *   { "success": bool, "data": mixed, "error": string|null }
             */

            // -----------------------------------------------------------------------
            // Bootstrap — adjust path depth to match your project layout
            // -----------------------------------------------------------------------

            require_once __DIR__ . '/../config.php';
            require_once __DIR__ . '/../src/Core/Database.php';
            require_once __DIR__ . '/../src/Base/BaseModel.php';
            require_once __DIR__ . '/../src/App/{$className}.php';

            header('Content-Type: application/json');

            // -----------------------------------------------------------------------
            // Shared helpers (inline — or extract to api/helpers.php if shared)
            // -----------------------------------------------------------------------

            /**
             * Terminate with a JSON response.
             * Always exits — call only once per request.
             */
            function respond(bool \$success, mixed \$data, ?string \$error): never
            {
                echo json_encode([
                    'success' => \$success,
                    'data'    => \$data,
                    'error'   => \$error,
                ]);
                exit;
            }

            /**
             * Enforce HTTP method. Responds with 405 if method doesn't match.
             */
            function requireMethod(string \$method): void
            {
                if (\$_SERVER['REQUEST_METHOD'] !== strtoupper(\$method)) {
                    http_response_code(405);
                    respond(false, null, "Method not allowed. Expected " . \$method . ".");
                }
            }

            /**
             * Decode JSON request body. Responds with 400 on malformed input.
             */
            function jsonBody(): array
            {
                \$raw  = file_get_contents('php://input');
                \$data = json_decode(\$raw, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    http_response_code(400);
                    respond(false, null, "Invalid JSON body: " . json_last_error_msg());
                }
                return \$data ?? [];
            }

            // -----------------------------------------------------------------------
            // Initialise model
            // -----------------------------------------------------------------------

            try {
                \$db    = new Database(DB_NAME);
                \$model = new {$className}(\$db);
            } catch (RuntimeException \$e) {
                http_response_code(500);
                respond(false, null, "Database connection failed.");
            }

            // -----------------------------------------------------------------------
            // Dispatch
            // -----------------------------------------------------------------------

            \$action = \$_GET['action'] ?? '';

            switch (\$action) {

                // ------------------------------------------------------------------
                case 'list':
                // GET /api/{$table}.php?action=list
                // Filterable: pass any filterable column as a query param
                // ------------------------------------------------------------------
            {$listBody}
                    break;

                // ------------------------------------------------------------------
                case 'read':
                // GET /api/{$table}.php?action=read&id=1
                // ------------------------------------------------------------------
                    requireMethod('GET');
                    \$id = (int)(\$_GET['{$pk}'] ?? 0);
                    if (!\$id) respond(false, null, "Missing or invalid 'id' parameter.");

                    \$row = \$model->getById(\$id);
                    if (!\$row) respond(false, null, "Record not found.");

                    respond(true, \$row, null);
                    break;

                // ------------------------------------------------------------------
                case 'create':
                // POST /api/{$table}.php?action=create
                // Body: JSON object with insertable fields
                // ------------------------------------------------------------------
            {$createBody}
                    break;

                // ------------------------------------------------------------------
                case 'update':
                // POST /api/{$table}.php?action=update&id=1
                // Body: JSON object with updatable fields (partial allowed)
                // ------------------------------------------------------------------
            {$updateBody}
                    break;

                // ------------------------------------------------------------------
                case 'delete':
                // POST /api/{$table}.php?action=delete&id=1
                // ------------------------------------------------------------------
                    requireMethod('POST');
                    \$id = (int)(\$_GET['{$pk}'] ?? 0);
                    if (!\$id) respond(false, null, "Missing or invalid 'id' parameter.");

                    \$ok = \$model->remove(\$id);
                    respond(\$ok, ['deleted' => \$ok], \$ok ? null : "Delete failed or row not found.");
                    break;

                // ------------------------------------------------------------------
                default:
                // ------------------------------------------------------------------
                    http_response_code(400);
                    respond(false, null, "Unknown action '" . \$action . "'. Valid: list, read, create, update, delete.");
            }
            PHP;

            $outFile = "{$outDir}/{$table}.php";
            file_put_contents($outFile, $driverBody);

            echo "[OK] Generated: {$outFile} <br/>\n";
            $generated++;
        }

        echo "\nDone. {$generated} driver(s) generated.\n";
        // $this->log('Reading spec.json');

        // $spec = $this->context->spec();

        // generate model classes

        return CPGeneratorResult::success("Successfully Generated");
    }
}
