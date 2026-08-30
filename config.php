<?php

/**
 * config.php
 * ----------
 * Central bootstrap for the framework.
 * Required by: api/ drivers, src/ classes, router.php
 *
 * Usage in api drivers:
 *   require_once __DIR__ . '/../config.php';
 *
 * Usage outside the index.php chain (e.g. standalone api file):
 *   if (!defined('APP_ENV')) require_once __DIR__ . '/../config.php';
 */

// -----------------------------------------------------------------------
// Guard — safe to include multiple times
// -----------------------------------------------------------------------

if (defined('APP_ENV')) return;

// -----------------------------------------------------------------------
// Environment
// -----------------------------------------------------------------------

define('APP_ENV', $_ENV['APP_ENV'] ?? 'production'); // 'local' | 'staging' | 'production'
define('APP_DEBUG', APP_ENV === 'local');

// -----------------------------------------------------------------------
// Database
// -----------------------------------------------------------------------

define('DB_HOST',     $_ENV['DB_HOST']     ?? 'localhost');
define('DB_USER',     $_ENV['DB_USER']     ?? 'root');
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? '');
define('DB_NAME',     $_ENV['DB_NAME']     ?? 'my_database');

// -----------------------------------------------------------------------
// Application
// -----------------------------------------------------------------------

define('APP_NAME',    'MyApp');
define('BASE_URL',    rtrim($_ENV['BASE_URL'] ?? 'http://localhost', '/'));
define('ASSETS_URL',  BASE_URL . '/assets');

// -----------------------------------------------------------------------
// Timezone
// -----------------------------------------------------------------------

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Kolkata');

// -----------------------------------------------------------------------
// Error handling — behaviour changes per environment
// -----------------------------------------------------------------------

if (APP_DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
    // In production: wire this to your logger instead
    // set_error_handler(fn(...$args) => Logger::error($args));
}

// -----------------------------------------------------------------------
// .env loader — only if not already populated by the server
// -----------------------------------------------------------------------

// If your host injects $_ENV via the server config (cPanel, Docker, etc.)
// you don't need this block. For local dev with a .env file:

$envFile = __DIR__ . '/.env';
if (APP_ENV === 'local' && file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;  // skip comments
        if (!str_contains($line, '=')) continue;           // skip malformed lines
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}
