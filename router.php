<?php
/**
 * Router script for PHP built-in server
 * All requests are routed through this file
 */

// If the file exists, serve it directly (for static files)
if (php_sapi_name() === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($path !== '/' && file_exists(__DIR__ . $path)) {
        return false; // serve the file directly
    }
}

// Route everything else through index.php
require_once __DIR__ . '/index.php';
