<?php

// include 'Generator.php';

final class UIGenerator extends CPGenerator
{
    public function name(): string
    {
        return 'model';
    }

    function addRoutes(array $tableNames): array
    {
        $routesFile = $_SERVER['DOCUMENT_ROOT'] . '/routes.json';

        $routes = [];

        if (is_file($routesFile)) {
            $routes = json_decode(
                (string) file_get_contents($routesFile),
                true
            ) ?: [];
        }

        foreach ($tableNames as $tableName) {
            $routes['/' . $tableName] = '/app/' . $tableName;
        }

        file_put_contents(
            $routesFile,
            json_encode(
                $routes,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ) . PHP_EOL
        );

        return $routes;
    }

    function updateHeaderNavbar(array $routes): void
    {
        $headerFile = $_SERVER['DOCUMENT_ROOT'] . '/view/include/header.php';

        if (!is_file($headerFile)) {
            return;
        }

        $header = file_get_contents($headerFile);

        $items = '';

        foreach ($routes as $route => $target) {
            $name = trim($route, '/');
            $name = str_replace(['-', '_'], ' ', $name);
            $name = ucwords($name);

            $items .= sprintf(
                '                <li><a class="dropdown-item" href="%s">%s</a></li>' . PHP_EOL,
                htmlspecialchars($target, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
            );
        }

        $dropdown = <<<HTML
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        Pages
                    </a>
                    <ul class="dropdown-menu">
                        $items </ul>
                </li>
                HTML;

        // Replace the generated marker.
        $marker = '<!-- CRUD_ROUTES_DROPDOWN -->';

        if (str_contains($header, $marker)) {
            $header = str_replace($marker, $dropdown, $header);
        }

        file_put_contents($headerFile, $header);
    }

    function generateTablePage(string $tableName, array $table, string $outputDir): void
    {


        if (!function_exists('jsValue')) {
            function jsValue(mixed $value): string
            {
                return json_encode(
                    $value,
                    JSON_HEX_TAG |
                        JSON_HEX_AMP |
                        JSON_HEX_APOS |
                        JSON_HEX_QUOT |
                        JSON_UNESCAPED_SLASHES
                );
            }
        }

        if (!function_exists('labelize')) {
            function labelize(string $name): string
            {
                $name = preg_replace('/[_\-]+/', ' ', $name) ?? $name;
                $name = preg_replace('/([a-z\d])([A-Z])/', '$1 $2', $name) ?? $name;
                return ucwords(trim($name));
            }
        }

        if (!function_exists('fieldType')) {
            function fieldType(array $field): string
            {
                if (($field['ui'] ?? '') === 'dropdown' || isset($field['enum_values']) || isset($field['fk'])) {
                    return 'select';
                }

                $type = strtolower((string) ($field['type'] ?? 'varchar'));

                return match (true) {
                    str_contains($type, 'bool') => 'checkbox',
                    $type === 'date' => 'date',
                    str_contains($type, 'datetime') || str_contains($type, 'timestamp') => 'datetime-local',
                    $type === 'time' => 'time',
                    str_contains($type, 'decimal') || str_contains($type, 'numeric') || str_contains($type, 'float') || str_contains($type, 'double') => 'number',
                    preg_match('/^(tiny|small|medium|int|bigint|integer)/', $type) === 1 => 'number',
                    str_contains($type, 'text') => 'textarea',
                    default => 'text',
                };
            }
        }

        if (!function_exists('fieldType')) {
            function inputAttributes(array $field): string
            {
                $type = fieldType($field);
                $attrs = [];

                if (in_array($type, ['number'], true)) {
                    $attrs[] = 'step="' . (str_contains(strtolower((string)($field['type'] ?? '')), 'int') ? '1' : 'any') . '"';
                }

                if (($field['nullable'] ?? true) === false) {
                    $attrs[] = 'required';
                }

                return implode(' ', $attrs);
            }
        }



        $fields = isset($table['fields']) && is_array($table['fields']) ? $table['fields'] : [];
        $primaryKey = (string) ($table['primary_key'] ?? array_key_first($fields) ?? 'id');

        $display = $table['display']['columns'] ?? [];
        $defaultColumns = isset($display['default']) && is_array($display['default'])
            ? $display['default']
            : array_keys($fields);
        $maxColumns = isset($display['max']) ? max(1, (int) $display['max']) : count($defaultColumns);
        $userSelectable = (bool) ($display['user_selectable'] ?? false);

        $defaultColumns = array_values(array_filter(
            $defaultColumns,
            static fn($column) => is_string($column) && isset($fields[$column])
        ));
        $defaultColumns = array_slice($defaultColumns, 0, $maxColumns);

        if ($defaultColumns === []) {
            $defaultColumns = array_slice(array_keys($fields), 0, $maxColumns);
        }

        $selectableColumns = array_values(array_filter(
            array_keys($fields),
            static fn($column) => isset($fields[$column]) && (($fields[$column]['ui'] ?? '') !== 'hidden')
        ));
        $selectableColumns = array_slice($selectableColumns, 0, max($maxColumns, count($selectableColumns)));

        $searchableFields = array_values(array_filter(
            array_keys($fields),
            static fn($column) => !empty($fields[$column]['searchable'])
        ));

        $filterableFields = array_values(array_filter(
            array_keys($fields),
            static fn($column) => !empty($fields[$column]['filterable'])
        ));

        $insertableFields = array_values(array_filter(
            array_keys($fields),
            static fn($column) => !empty($fields[$column]['insertable']) && (($fields[$column]['ui'] ?? '') !== 'hidden')
        ));

        $updatableFields = array_values(array_filter(
            array_keys($fields),
            static fn($column) => !empty($fields[$column]['updatable']) && (($fields[$column]['ui'] ?? '') !== 'hidden')
        ));

        $pageTitle = labelize($tableName);
        $entityLabel = rtrim($pageTitle, 's');
        $apiUrl = 'api/' . rawurlencode($tableName) . '.php';

        // ... existing schema mapping logic unchanged ...


        $jsConfig = [
            'table' => $tableName,
            'primaryKey' => $primaryKey,
            'title' => $pageTitle,
            'entityLabel' => $entityLabel,
            'apiUrl' => $apiUrl,
            'fields' => $fields,
            'defaultColumns' => $defaultColumns,
            'maxColumns' => $maxColumns,
            'userSelectableColumns' => $userSelectable ? $selectableColumns : [],
            'searchableFields' => $searchableFields,
            'filterableFields' => $filterableFields,
            'insertableFields' => $insertableFields,
            'updatableFields' => $updatableFields,
        ];

        ob_start();
?>


        <main class="container-fluid py-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
                <div>
                    <h1 class="h3 mb-1"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="text-muted mb-0">Manage <?= htmlspecialchars(strtolower($pageTitle), ENT_QUOTES, 'UTF-8') ?> records.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary" onclick="resetView()">
                        <i class="fa-solid fa-rotate-right me-1"></i> Reset View
                    </button>
                    <button type="button" class="btn btn-primary" onclick="openCreateModal()">
                        <i class="fa-solid fa-plus me-1"></i> Add Entry
                    </button>
                    <button type="button" class="btn btn-outline-success" onclick="exportCSV()">
                        <i class="fa-solid fa-file-csv me-1"></i> Export CSV
                    </button>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="row g-3 align-items-end" id="filterRow">
                        <?php if ($searchableFields): ?>
                            <div class="col-12 col-lg-5">
                                <label for="searchInput" class="form-label small fw-semibold">Search</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass"></i></span>
                                    <input type="search" id="searchInput" class="form-control"
                                        placeholder="Search <?= htmlspecialchars(strtolower($pageTitle), ENT_QUOTES, 'UTF-8') ?>...">
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($filterableFields as $column): ?>
                            <?php
                            $field = $fields[$column];
                            $filterId = 'filter_' . preg_replace('/[^A-Za-z0-9_]/', '_', $column);
                            ?>
                            <div class="col-12 col-md-4 col-lg-3">
                                <label for="<?= htmlspecialchars($filterId, ENT_QUOTES, 'UTF-8') ?>" class="form-label small fw-semibold">
                                    <?= htmlspecialchars(labelize($column), ENT_QUOTES, 'UTF-8') ?>
                                </label>
                                <?php if (isset($field['enum_values']) && is_array($field['enum_values'])): ?>
                                    <select id="<?= htmlspecialchars($filterId, ENT_QUOTES, 'UTF-8') ?>" class="form-select schema-filter" data-field="<?= htmlspecialchars($column, ENT_QUOTES, 'UTF-8') ?>">
                                        <option value="">All <?= htmlspecialchars(strtolower(labelize($column)), ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php foreach ($field['enum_values'] as $value): ?>
                                            <option value="<?= htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(labelize((string)$value), ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input type="<?= htmlspecialchars(fieldType($field) === 'number' ? 'number' : 'text', ENT_QUOTES, 'UTF-8') ?>"
                                        id="<?= htmlspecialchars($filterId, ENT_QUOTES, 'UTF-8') ?>"
                                        class="form-control schema-filter"
                                        data-field="<?= htmlspecialchars($column, ENT_QUOTES, 'UTF-8') ?>"
                                        placeholder="Filter <?= htmlspecialchars(strtolower(labelize($column)), ENT_QUOTES, 'UTF-8') ?>">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <div class="col-12 col-md-4 col-lg-2">
                            <div class="small text-muted mb-2">Records</div>
                            <div class="fw-semibold"><span id="recordCount">0</span> results</div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($userSelectable): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body py-3">
                        <div class="d-flex flex-column flex-lg-row gap-3 align-items-lg-center justify-content-between">
                            <div>
                                <div class="small fw-semibold">Visible columns</div>
                                <div class="small text-muted">Choose up to <?= (int)$maxColumns ?> columns.</div>
                            </div>
                            <div class="d-flex flex-wrap gap-3" id="columnPicker"></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="h6 mb-0 fw-semibold"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> Records</h2>
                        <span class="badge text-bg-light border" id="pageInfo">Page 1 of 1</span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="crudTable">
                        <thead class="table-light">
                            <tr id="tableHead"></tr>
                        </thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>

                <div class="card-footer bg-white border-0 py-3">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3">
                        <div class="small text-muted">
                            Showing <span id="showingFrom">0</span>–<span id="showingTo">0</span> of <span id="totalRecords">0</span>
                        </div>
                        <nav aria-label="Pagination">
                            <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
                        </nav>
                    </div>
                </div>
            </div>
        </main>

        <div class="modal fade" id="crudModal" tabindex="-1" aria-labelledby="crudModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="crudModalLabel">Add Entry</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="crudForm">
                        <input type="hidden" id="recordId">
                        <div class="modal-body">
                            <div class="row g-3" id="formFields"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Save
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="viewModalLabel"><?= htmlspecialchars($entityLabel, ENT_QUOTES, 'UTF-8') ?> Details</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="viewModalBody"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="deleteModalLabel">Delete <?= htmlspecialchars($entityLabel, ENT_QUOTES, 'UTF-8') ?></h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center py-2">
                            <div class="rounded-circle bg-danger-subtle text-danger d-inline-flex align-items-center justify-content-center mb-3"
                                style="width:64px;height:64px;">
                                <i class="fa-solid fa-trash fs-4"></i>
                            </div>
                            <p class="mb-1 fw-semibold">Are you sure you want to delete this record?</p>
                            <p class="text-muted small mb-0" id="deleteDescription"></p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                            <i class="fa-solid fa-trash me-1"></i> Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            const CONFIG = <?= jsValue($jsConfig) ?>;

            let records = [];
            let filteredRecords = [];
            let currentPage = 1;
            let pageSize = 25;
            let sortKey = "";
            let sortDirection = "asc";
            let deleteId = null;
            let visibleColumns = [...CONFIG.defaultColumns];

            const crudModal = new bootstrap.Modal(document.getElementById("crudModal"));
            const viewModal = new bootstrap.Modal(document.getElementById("viewModal"));
            const deleteModal = new bootstrap.Modal(document.getElementById("deleteModal"));

            function escapeHTML(value) {
                return String(value ?? "").replace(/[&<>"']/g, char => ({
                    "&": "&amp;",
                    "<": "&lt;",
                    ">": "&gt;",
                    '"': "&quot;",
                    "'": "&#039;"
                } [char]));
            }

            function labelize(name) {
                return String(name)
                    .replace(/[_-]+/g, " ")
                    .replace(/([a-z0-9])([A-Z])/g, "$1 $2")
                    .replace(/\b\w/g, char => char.toUpperCase());
            }

            function getField(name) {
                return CONFIG.fields[name] || {};
            }

            function formatValue(value, field = {}) {
                if (value === null || value === undefined || value === "") return "";

                if (field.type === "bool" || field.type === "boolean") {
                    return Number(value) ? "Yes" : "No";
                }

                if (field.type === "date") {
                    return escapeHTML(String(value));
                }

                return escapeHTML(String(value));
            }

            function renderBadge(value) {
                if (value === null || value === undefined || value === "") return "";
                const normalized = String(value).toLowerCase();

                const classes = {
                    pending: "text-bg-warning",
                    confirmed: "text-bg-primary",
                    active: "text-bg-success",
                    enabled: "text-bg-success",
                    cancelled: "text-bg-danger",
                    inactive: "text-bg-secondary",
                    disabled: "text-bg-secondary",
                    completed: "text-bg-success",
                    failed: "text-bg-danger"
                };

                return `<span class="badge ${classes[normalized] || "text-bg-light"}">${escapeHTML(value)}</span>`;
            }

            function renderCell(record, column) {
                const field = getField(column);
                const value = record[column];

                if ((field.type === "enum" || field.ui === "dropdown") && value !== null && value !== undefined && value !== "") {
                    return renderBadge(value);
                }

                return formatValue(value, field);
            }

            function makeColumnPicker() {
                const picker = document.getElementById("columnPicker");
                if (!picker || !CONFIG.userSelectableColumns.length) return;

                picker.innerHTML = CONFIG.userSelectableColumns.map(column => `
                        <div class="form-check">
                            <input class="form-check-input column-toggle" type="checkbox"
                                id="col_${escapeHTML(column)}" value="${escapeHTML(column)}"
                                ${visibleColumns.includes(column) ? "checked" : ""}>
                            <label class="form-check-label small" for="col_${escapeHTML(column)}">${escapeHTML(labelize(column))}</label>
                        </div>
                    `).join("");

                picker.querySelectorAll(".column-toggle").forEach(input => {
                    input.addEventListener("change", () => {
                        const selected = [...picker.querySelectorAll(".column-toggle:checked")].map(i => i.value);

                        if (selected.length === 0) {
                            input.checked = true;
                            return;
                        }

                        if (selected.length > CONFIG.maxColumns) {
                            input.checked = false;
                            return;
                        }

                        visibleColumns = selected;
                        renderHeader();
                        renderTable();
                    });
                });
            }

            function renderHeader() {
                const head = document.getElementById("tableHead");
                head.innerHTML = visibleColumns.map(column => `
                        <th scope="col">
                            <button class="btn btn-sm p-0 fw-semibold text-dark border-0"
                                    onclick="sortTable('${escapeHTML(column)}')">
                                ${escapeHTML(labelize(column))}
                                <i class="fa-solid fa-sort ms-1 text-muted"></i>
                            </button>
                        </th>
                    `).join("") + `
                        <th scope="col" class="text-end">Actions</th>
                    `;
            }

            function collectFilters() {
                const filters = {};
                document.querySelectorAll(".schema-filter").forEach(element => {
                    filters[element.dataset.field] = element.value;
                });
                return filters;
            }

            function applyFilters() {
                const search = (document.getElementById("searchInput")?.value || "").toLowerCase().trim();
                const filters = collectFilters();

                filteredRecords = records.filter(record => {
                    const matchesSearch = !search || CONFIG.searchableFields.some(field =>
                        String(record[field] ?? "").toLowerCase().includes(search)
                    );

                    if (!matchesSearch) return false;

                    return Object.entries(filters).every(([field, value]) =>
                        !value || String(record[field] ?? "") === String(value)
                    );
                });

                if (sortKey) sortData();

                currentPage = 1;
                renderTable();
            }

            function sortTable(key) {
                if (sortKey === key) {
                    sortDirection = sortDirection === "asc" ? "desc" : "asc";
                } else {
                    sortKey = key;
                    sortDirection = "asc";
                }

                sortData();
                renderTable();
            }

            function sortData() {
                filteredRecords.sort((a, b) => {
                    const first = String(a[sortKey] ?? "").toLowerCase();
                    const second = String(b[sortKey] ?? "").toLowerCase();
                    return first.localeCompare(second, undefined, {
                            numeric: true
                        }) *
                        (sortDirection === "asc" ? 1 : -1);
                });
            }

            function updateSortIcons() {
                document.querySelectorAll("#tableHead button").forEach(button => {
                    const icon = button.querySelector("i");
                    if (!icon) return;

                    icon.className = "fa-solid fa-sort ms-1 text-muted";

                    const column = button.closest("th")?.querySelector("button")?.textContent
                        .trim().toLowerCase();

                    if (sortKey && column === labelize(sortKey).toLowerCase()) {
                        icon.className = `fa-solid fa-sort-${sortDirection === "asc" ? "up" : "down"} ms-1 text-primary`;
                    }
                });
            }

            function renderTable() {
                const pageRows = records;
                const tbody = document.getElementById("tableBody");

                tbody.innerHTML = pageRows.length ?
                    pageRows.map(record => `
            <tr>
                ${visibleColumns.map(column => `<td>${renderCell(record, column)}</td>`).join("")}
                <td class="text-end">
                    <div class="btn-group btn-group-sm" role="group" aria-label="Actions">
                        <button class="btn btn-outline-secondary btn-view" title="View"
                                data-id="${escapeHTML(record[CONFIG.primaryKey])}">
                            <i class="fa-solid fa-eye"></i>
                        </button>

                        <button class="btn btn-outline-primary btn-edit" title="Update"
                                data-id="${escapeHTML(record[CONFIG.primaryKey])}">
                            <i class="fa-solid fa-pen"></i>
                        </button>

                        <button class="btn btn-outline-danger btn-delete" title="Delete"
                                data-id="${escapeHTML(record[CONFIG.primaryKey])}">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join("") :
                    `
            <tr>
                <td colspan="${visibleColumns.length + 1}" class="text-center py-5">
                    <i class="fa-solid fa-inbox text-muted fs-2 mb-3"></i>
                    <div class="fw-semibold">No records found</div>
                    <div class="small text-muted">No records available.</div>
                </td>
            </tr>
        `;

                tbody.querySelectorAll(".btn-view").forEach(button => {
                    button.addEventListener("click", () => {
                        viewRecord(button.dataset.id);
                    });
                });

                tbody.querySelectorAll(".btn-edit").forEach(button => {
                    button.addEventListener("click", () => {
                        editRecord(button.dataset.id);
                    });
                });

                tbody.querySelectorAll(".btn-delete").forEach(button => {
                    button.addEventListener("click", () => {
                        deleteRecord(button.dataset.id);
                    });
                });

                const showingFrom = totalRecords ?
                    ((currentPage - 1) * pageSize) + 1 :
                    0;

                const showingTo = Math.min(
                    ((currentPage - 1) * pageSize) + pageRows.length,
                    totalRecords
                );

                document.getElementById("recordCount").textContent = totalRecords;
                document.getElementById("totalRecords").textContent = totalRecords;
                document.getElementById("showingFrom").textContent = showingFrom;
                document.getElementById("showingTo").textContent = showingTo;
                document.getElementById("pageInfo").textContent =
                    `Page ${currentPage} of ${totalPages}`;

                renderPagination(totalPages);
                updateSortIcons();
            }


            function renderPagination(totalPages) {
                const pagination = document.getElementById("pagination");
                let html = `
                        <li class="page-item ${currentPage === 1 ? "disabled" : ""}">
                            <button class="page-link" onclick="goToPage(${currentPage - 1})" aria-label="Previous">
                                <i class="fa-solid fa-chevron-left"></i>
                            </button>
                        </li>
                    `;

                for (let i = 1; i <= totalPages; i++) {
                    html += `
                            <li class="page-item ${i === currentPage ? "active" : ""}">
                                <button class="page-link" onclick="goToPage(${i})">${i}</button>
                            </li>
                        `;
                }

                html += `
                        <li class="page-item ${currentPage === totalPages ? "disabled" : ""}">
                            <button class="page-link" onclick="goToPage(${currentPage + 1})" aria-label="Next">
                                <i class="fa-solid fa-chevron-right"></i>
                            </button>
                        </li>
                    `;

                pagination.innerHTML = html;
            }

            async function goToPage(page) {
                if (page < 1 || page > totalPages || page === currentPage) {
                    return;
                }

                currentPage = page;

                await refreshTable();
            }


            function buildField(column, mode) {
                const field = getField(column);
                const type = getInputType(field);
                const id = `field_${column.replace(/[^A-Za-z0-9_]/g, "_")}`;
                const name = column;
                const label = labelize(column);

                const editable = mode === "create" ?
                    CONFIG.insertableFields.includes(column) :
                    CONFIG.updatableFields.includes(column);

                const disabled = !editable;
                const required = field.nullable === false && !field.auto ? "required" : "";
                const requiredMark = required ? ' <span class="text-danger">*</span>' : "";

                let control = "";

                if (type === "select") {
                    let options = [`<option value="">Select ${escapeHTML(label.toLowerCase())}</option>`];

                    if (Array.isArray(field.enum_values)) {
                        options.push(...field.enum_values.map(value =>
                            `<option value="${escapeHTML(value)}">${escapeHTML(labelize(value))}</option>`
                        ));
                    } else if (field.fk) {
                        const fk = field.fk;

                        // The actual options are populated asynchronously.
                        // ref_column is the submitted value.
                        // display_columns are the columns shown to the user.
                        options.push(`
                            <option value="" disabled
                                    data-fk-table="${escapeHTML(fk.ref_table)}"
                                    data-fk-column="${escapeHTML(fk.ref_column)}">
                                Loading ${escapeHTML(label.toLowerCase())}...
                            </option>
                        `);
                    }

                    control = `
                        <select class="form-select schema-input" id="${escapeHTML(id)}"
                                name="${escapeHTML(name)}" data-field="${escapeHTML(column)}"
                                ${required} ${disabled ? "disabled" : ""}>
                            ${options.join("")}
                        </select>
                    `;
                } else if (type === "textarea") {
                    control = `
                        <textarea class="form-control schema-input" id="${escapeHTML(id)}"
                                name="${escapeHTML(name)}" data-field="${escapeHTML(column)}"
                                rows="3" ${required} ${disabled ? "disabled" : ""}></textarea>
                    `;
                } else if (type === "checkbox") {
                    control = `
                        <div class="form-check mt-2">
                            <input class="form-check-input schema-input" type="checkbox"
                                id="${escapeHTML(id)}" name="${escapeHTML(name)}"
                                data-field="${escapeHTML(column)}" value="1"
                                ${disabled ? "disabled" : ""}>
                        </div>
                    `;
                } else {
                    control = `
                            <input type="${escapeHTML(type)}" class="form-control schema-input"
                                id="${escapeHTML(id)}" name="${escapeHTML(name)}"
                                data-field="${escapeHTML(column)}"
                                ${required} ${disabled ? "disabled" : ""}>
                        `;
                }

                return `
                        <div class="${type === "textarea" ? "col-12" : "col-md-6"}">
                            <label for="${escapeHTML(id)}" class="form-label">${escapeHTML(label)}${requiredMark}</label>
                            ${control}
                        </div>
                    `;
            }

            function getInputType(field) {
                if (field.ui === "dropdown" || Array.isArray(field.enum_values) || field.fk) return "select";

                switch (String(field.type || "varchar").toLowerCase()) {
                    case "date":
                        return "date";
                    case "time":
                        return "time";
                    case "datetime":
                    case "datetime-local":
                    case "timestamp":
                        return "datetime-local";
                    case "bool":
                    case "boolean":
                        return "checkbox";
                    case "tinyint":
                    case "smallint":
                    case "mediumint":
                    case "int":
                    case "integer":
                    case "bigint":
                    case "decimal":
                    case "numeric":
                    case "float":
                    case "double":
                        return "number";
                    case "text":
                    case "mediumtext":
                    case "longtext":
                        return "textarea";
                    default:
                        return "text";
                }
            }

            function renderForm(mode, record = {}) {
                const columns = mode === "create" ? CONFIG.insertableFields : CONFIG.updatableFields;
                document.getElementById("formFields").innerHTML = columns.map(column => buildField(column, mode)).join("");

                document.getElementById("recordId").value = record[CONFIG.primaryKey] ?? "";

                columns.forEach(column => {
                    const field = getField(column);
                    const input = document.querySelector(`[data-field="${CSS.escape(column)}"]`);
                    if (!input) return;

                    const value = record[column];

                    if (input.type === "checkbox") {
                        input.checked = Boolean(Number(value)) || value === true;
                    } else {
                        input.value = value ?? "";
                    }
                });
            }

            async function loadFKOptions() {
                const fkSelects = document.querySelectorAll(
                    '#formFields select.schema-input'
                );

                for (const select of fkSelects) {
                    const fieldName = select.dataset.field;
                    const field = getField(fieldName);

                    if (!field.fk) continue;

                    const fk = field.fk;

                    try {
                        const response = await fetch(
                            `api/${encodeURIComponent(fk.ref_table)}.php?action=list`
                        );

                        const json = await response.json();

                        if (!json.success || !Array.isArray(json.data)) {
                            throw new Error(json.error || "Unable to load FK options");
                        }

                        const currentValue = select.value;

                        select.innerHTML = json.data.map(row => {
                            const value = row[fk.ref_column];

                            const display = (fk.display_columns || [fk.ref_column])
                                .map(column => row[column] ?? "")
                                .join(" | ");

                            return `
                    <option value="${escapeHTML(value)}">
                        ${escapeHTML(display)}
                    </option>
                `;
                        }).join("");

                        select.value = currentValue;
                    } catch (error) {
                        console.error(`Failed loading FK: ${fieldName}`, error);

                        select.innerHTML = `
                <option value="">
                    Unable to load ${escapeHTML(labelize(fk.ref_table))}
                </option>
            `;
                    }
                }
            }

            async function apiRequest(action, data = {}, method = "POST") {
                console.log("apiRequest-action", action)
                console.log("apiRequest-data", data)
                console.log("apiRequest-method", method)
                const params = new URLSearchParams();

                params.set("action", action);

                const pk = CONFIG.primaryKey;


                // if (
                //     data[CONFIG.primaryKey] !== undefined &&
                //     data[CONFIG.primaryKey] !== null &&
                //     data[CONFIG.primaryKey] !== ""
                // ) {
                //     console.error("apiRequest wrong - data[pk]")
                //     console.log("apiRequest-action", CONFIG.primaryKey)
                //     console.log("apiRequest-data", data[pk])

                //     params.set(data[CONFIG.primaryKey], data[pk]);
                // }
                // Primary key is handled separately because it is part of the API contract.
                if (data[pk] !== undefined && data[pk] !== null && data[pk] !== "") {
                    params.set(CONFIG.primaryKey, data[pk]);
                }

                // Add non-PK GET parameters such as page, limit, search, etc.
                for (const [key, value] of Object.entries(data)) {
                    if (
                        key !== pk &&
                        value !== undefined &&
                        value !== null &&
                        value !== ""
                    ) {
                        params.set(key, value);
                    }
                }
                const options = {
                    method,
                    headers: {
                        "Accept": "application/json"
                    }
                };

                if (method === "POST") {
                    options.headers["Content-Type"] = "application/json";

                    const body = {
                        ...data
                    };
                    delete body[pk];

                    options.body = JSON.stringify(body);
                }

                const response = await fetch(
                    `${CONFIG.apiUrl}?${params.toString()}`,
                    options
                );

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const json = await response.json();

                if (json.success === false) {
                    throw json;
                }

                return json;
            }
            async function refreshTable() {
                try {
                    const json = await apiRequest("list", {
                        page: currentPage,
                        limit: pageSize
                    }, "GET");

                    records = Array.isArray(json.data) ? json.data : [];

                    totalRecords = Number(json.total ?? 0);
                    totalPages = Number(json.pages ?? 1);
                    currentPage = Number(json.page ?? currentPage);

                    renderTable();

                } catch (error) {
                    console.error("Load failed:", error);
                    showApiError(`Unable to load ${CONFIG.title.toLowerCase()} records.`);
                }
            }

            function openCreateModal() {
                document.getElementById("crudForm").reset();
                renderForm("create");
                loadFKOptions();
                document.getElementById("crudModalLabel").textContent = "Add Entry";
                clearFormErrors();
                crudModal.show();
            }

            async function editRecord(id) {
                try {
                    const json = await apiRequest("read", {
                        [CONFIG.primaryKey]: id
                    }, "GET");
                    if (!json.data) {
                        showApiError("Record was not found.");
                        return;
                    }

                    renderForm("update", json.data);
                    document.getElementById("crudModalLabel").textContent = "Update Entry";
                    clearFormErrors();
                    crudModal.show();
                } catch (error) {
                    console.error("Edit failed:", error);
                    showApiError("Unable to load the record for editing.");
                }
            }

            document.getElementById("crudForm").addEventListener("submit", async function(event) {
                event.preventDefault();

                const form = event.target;
                const data = {};

                form.querySelectorAll(".schema-input").forEach(input => {
                    if (input.disabled) return;

                    data[input.name] = input.type === "checkbox" ?
                        (input.checked ? 1 : 0) :
                        input.value;
                });

                const id = document.getElementById("recordId").value;
                if (id) {
                    data[CONFIG.primaryKey] = id;
                }

                const action = id ? "update" : "create";
                const submitButton = form.querySelector('button[type="submit"]');

                try {
                    setButtonLoading(submitButton, true);
                    const json = await apiRequest(action, data);

                    if (json.success === false) {
                        showErrors(json.errors || {
                            general: "Unable to save record."
                        });
                        return;
                    }

                    crudModal.hide();
                    clearFormErrors();
                    await refreshTable();
                } catch (error) {
                    console.error("Save failed:", error);
                    showErrors(error.errors || {
                        general: "Unable to save the record."
                    });
                } finally {
                    setButtonLoading(submitButton, false);
                }
            });

            async function viewRecord(id) {
                try {
                    const json = await apiRequest("read", {
                        [CONFIG.primaryKey]: id
                    }, "GET");
                    const record = json.data;

                    if (!record) {
                        showApiError("Record was not found.");
                        return;
                    }

                    document.getElementById("viewModalBody").innerHTML = `
            <div class="row g-3">
                ${Object.keys(CONFIG.fields).map(column => `
                    <div class="${String(CONFIG.fields[column].type || "").includes("text") ? "col-12" : "col-md-6"}">
                        <div class="small text-muted mb-1">${escapeHTML(labelize(column))}</div>
                        <div class="border rounded p-3 bg-light">
                            ${CONFIG.fields[column].type === "enum" || CONFIG.fields[column].ui === "dropdown"
                                ? renderBadge(record[column])
                                : formatValue(record[column], CONFIG.fields[column]) || '<span class="text-muted">Not set</span>'}
                        </div>
                    </div>
                `).join("")}
            </div>
            `;
                    viewModal.show();
                } catch (error) {
                    console.error("View failed:", error);
                    showApiError("Unable to load the record details.");
                }
            }

            function deleteRecord(id) {
                deleteId = id;
                const record = records.find(item => String(item[CONFIG.primaryKey]) === String(id));

                document.getElementById("deleteDescription").textContent = record ?
                    `${labelize(CONFIG.primaryKey)}: ${record[CONFIG.primaryKey]}` :
                    `${labelize(CONFIG.primaryKey)}: ${id}`;

                deleteModal.show();
            }

            async function confirmDelete() {
                if (deleteId === null) return;

                const button = document.querySelector("#deleteModal .btn-danger");

                try {
                    setButtonLoading(button, true);
                    const json = await apiRequest("delete", {
                        [CONFIG.primaryKey]: deleteId
                    });

                    if (json.success === false) {
                        showApiError(json.message || "Unable to delete the record.");
                        return;
                    }

                    deleteId = null;
                    deleteModal.hide();
                    await refreshTable();
                } catch (error) {
                    console.error("Delete failed:", error);
                    showApiError("Unable to delete the record.");
                } finally {
                    setButtonLoading(button, false);
                }
            }

            function resetView() {
                const search = document.getElementById("searchInput");
                if (search) search.value = "";

                document.querySelectorAll(".schema-filter").forEach(element => element.value = "");

                sortKey = "";
                sortDirection = "asc";
                currentPage = 1;
                refreshTable();
            }

            function exportCSV() {
                showApiError("CSV export is not implemented by this API.");
            }

            function showErrors(errors) {
                clearFormErrors();

                Object.entries(errors || {}).forEach(([field, message]) => {
                    if (field === "general") {
                        showApiError(message);
                        return;
                    }

                    const input = document.querySelector(`[data-field="${CSS.escape(field)}"]`);
                    if (!input) return;

                    input.classList.add("is-invalid");

                    let feedback = input.parentElement.querySelector(".invalid-feedback");
                    if (!feedback) {
                        feedback = document.createElement("div");
                        feedback.className = "invalid-feedback";
                        input.parentElement.appendChild(feedback);
                    }

                    feedback.textContent = message;
                });
            }

            function clearFormErrors() {
                document.querySelectorAll("#crudForm .is-invalid")
                    .forEach(element => element.classList.remove("is-invalid"));

                document.querySelectorAll("#crudForm .invalid-feedback")
                    .forEach(element => element.remove());
            }

            function showApiError(message) {
                showToast('Failed: {message}', 'danger');
                console.error(message);
                alert(message);
            }

            function setButtonLoading(button, loading) {
                if (!button) return;

                if (loading) {
                    button.dataset.originalHtml = button.innerHTML;
                    button.disabled = true;
                    button.innerHTML = `
            <span class="spinner-border spinner-border-sm me-1"
                  role="status" aria-hidden="true"></span>
            Saving...
                `;
                } else {
                    button.disabled = false;
                    if (button.dataset.originalHtml) {
                        button.innerHTML = button.dataset.originalHtml;
                        delete button.dataset.originalHtml;
                    }
                }
            }

            if (document.getElementById("searchInput")) {
                document.getElementById("searchInput").addEventListener("input", applyFilters);
            }

            document.querySelectorAll(".schema-filter").forEach(element => {
                element.addEventListener("change", applyFilters);
            });

            makeColumnPicker();
            renderHeader();
            refreshTable();
        </script>

<?php
        $html = ob_get_clean();

        $outputFile = $outputDir . '/' . $tableName . '.php';

        file_put_contents($outputFile, $html);
    }

    public function generate(): CPGeneratorResult
    {

        $specPath = $_GET['spec'] ?? __DIR__ . '\..\spec.json';

        $outputDir   = $opts['out']  ?? $_SERVER['DOCUMENT_ROOT'] . '\view\app';

        if (!is_string($specPath) || $specPath === '') {
            http_response_code(400);
            exit('Invalid spec path.');
        }

        if (!preg_match('/\.json$/i', $specPath)) {
            http_response_code(400);
            exit('Spec must be a JSON file.');
        }

        if (!str_starts_with($specPath, '/') && !preg_match('/^[A-Za-z]:[\\\\\/]/', $specPath)) {
            $specPath = __DIR__ . '/' . ltrim($specPath, '/');
        }

        if (!is_file($specPath) || !is_readable($specPath)) {
            http_response_code(404);
            exit('Spec file not found.');
        }

        $spec = json_decode((string) file_get_contents($specPath), true);

        if (!is_array($spec) || $spec === []) {
            http_response_code(400);
            exit('Spec JSON must contain at least one table.');
        }

        /*
 * This PHP script is a generator.
 * It reads every table from spec.json and emits one standalone
 * PHP/HTML page per table.
 */
        // $outputDir = "gen/";
        $outputDir   = $opts['out']  ?? $_SERVER['DOCUMENT_ROOT'] . '\view\app';

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        }



        // file_put_contents($outputFile, $html);

        $tableNames = [];
        echo "call GenerateTablePage";
        foreach ($spec as $tableName => $table) {
            $this->generateTablePage($tableName, $table, $outputDir);
            $tableNames[] = $tableName;
        }

        echo ("Running addRoutes");
        print_r($tableNames);

        try {
            $routes = $this->addRoutes($tableNames);

            if (empty($routes)) {
                throw new Exception('Failed to add routes');
            }

            $this->updateHeaderNavbar(
                array_combine(
                    array_map(
                        fn($table) => '/' . $table,
                        $tableNames
                    ),
                    array_map(
                        fn($table) => $table,
                        $tableNames
                    )
                )
            );
            echo 'Success';
        } catch (Throwable $e) {
            echo 'Failed: ' . $e->getMessage();
        }


        echo "Generated " . count($spec) . " CRUD page(s).";

        return CPGeneratorResult::success("UI Generated successfully.");
    }
}
