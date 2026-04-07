<?php
/**
 * Router script for PHP built-in server
 * All requests are routed through this file
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// If the file exists, serve it directly (for static files)
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Store original URI for index.php to use
$_SERVER['ORIGINAL_URI'] = $uri;

// Route everything else through index.php
require_once __DIR__ . '/index.php';
