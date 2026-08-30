<?php
/**
 * router.php
 *
 * Called via POST by spa.js with JSON body: { "url": "/path?query=value" }
 * Called internally by index.php on first load (hard refresh / direct URL).
 *
 * Never called directly via GET from outside. GET is not a supported entrypoint.
 *
 * Exposes to views:
 *   $request['path']   — string, e.g. "/ticket"
 *   $request['params'] — array,  e.g. ["ticket" => "14"]
 *
 * Does NOT touch $_GET, $_POST, or any superglobal.
 */

// -----------------------------------------------
// 1. Determine entrypoint: SPA POST or internal call
// -----------------------------------------------

// When called internally from index.php, $routerUrl is pre-set
// When called by spa.js POST, we read the JSON body

if (!isset($routerUrl)) {
    // SPA fetch: enforce POST + JSON Content-Type
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method Not Allowed');
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') === false) {
        http_response_code(415);
        exit('Unsupported Media Type');
    }

    $body = file_get_contents('php://input');
    $data = json_decode($body, true);

    if (!is_array($data) || !isset($data['url']) || !is_string($data['url'])) {
        http_response_code(400);
        exit('Bad Request');
    }

    $routerUrl = $data['url'];
}

// -----------------------------------------------
// 2. Validate and parse the URL
// -----------------------------------------------

// Must start with /
if (!str_starts_with($routerUrl, '/')) {
    $routerUrl = '/' . $routerUrl;
}

// Reject anything with null bytes, encoded traversal, or protocol tricks
if (
    str_contains($routerUrl, "\0")
    || str_contains($routerUrl, '..')
    || preg_match('#(^|/)\.\.?(\/|$)#', rawurldecode($routerUrl))
) {
    http_response_code(400);
    exit('Invalid URL');
}

$path  = parse_url($routerUrl, PHP_URL_PATH)  ?? '/';
$query = parse_url($routerUrl, PHP_URL_QUERY) ?? '';

// Normalize: strip trailing slash except root
if ($path !== '/' && str_ends_with($path, '/')) {
    $path = rtrim($path, '/');
}

// Parse query into a clean array — never touches $_GET
$queryParams = [];
if ($query !== '') {
    parse_str($query, $queryParams);
}

// Expose a typed request context to views (read-only intent)
$request = [
    'path'   => $path,
    'params' => $queryParams,
];

// -----------------------------------------------
// 3. Load and validate route map
// -----------------------------------------------

$routesFile = __DIR__ . '/routes.json';

if (!is_file($routesFile)) {
    http_response_code(500);
    exit('Route map missing');
}

$routes = json_decode(file_get_contents($routesFile), true);

if (!is_array($routes)) {
    http_response_code(500);
    exit('Route map invalid');
}

// -----------------------------------------------
// 4. Resolve route → view file
// -----------------------------------------------

$viewBase = realpath(__DIR__ . '/view');

function resolve_view(string $viewBase, string $relative): string|false
{
    // $relative comes from routes.json — e.g. "tickets/detail"
    // Construct full path and verify it's inside /view/
    $candidate = realpath($viewBase . '/' . $relative . '.php');

    if ($candidate === false) {
        return false; // file doesn't exist
    }

    // Whitelist: must be inside /view/
    if (!str_starts_with($candidate, $viewBase . DIRECTORY_SEPARATOR)) {
        return false; // traversal attempt via routes.json
    }

    return $candidate;
}

if ($path === '/' || $path === '/home') {
    $viewFile = resolve_view($viewBase, 'static/home');
} elseif (isset($routes[$path])) {
    $viewFile = resolve_view($viewBase, $routes[$path]);
} else {
    $viewFile = false;
}

// -----------------------------------------------
// 5. Include view or 404
// -----------------------------------------------

if ($viewFile !== false) {
    include $viewFile;
} else {
    http_response_code(404);
    echo '<div class="card border-round bg-white my-5 py-5">
            <div class="card-body"><h1>404 — Page Not Found</h1></div>
          </div>';
}