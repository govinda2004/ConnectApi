<?php

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../helpers/response.php';

$connected = false;
$error = null;
$ssl_active = false;

try {
    $db = getDB();
    // Test the connection
    $stmt = $db->query("SHOW STATUS LIKE 'Ssl_cipher'");
    $ssl_info = $stmt->fetch();
    $ssl_active = !empty($ssl_info['Value']);

    $connected = true;
} catch (Throwable $e) {
    $error = $e->getMessage();
}

// Get current config values (matching config/database.php logic)
$host = getenv('DB_HOST') ?: 'gateway01.ap-southeast-1.prod.aws.tidbcloud.com';
$port = getenv('DB_PORT') ?: '4000';
$name = getenv('DB_NAME') ?: 'sys';
$user = getenv('DB_USER') ?: '2oU8khtXMM7Ygx9.root';

jsonSuccess([
    'connected' => $connected,
    'driver' => 'mysql (TiDB)',
    'host' => $host,
    'port' => $port,
    'database' => $name,
    'user' => $user,
    'ssl_active' => $ssl_active,
    'error' => $error,
    'environment' => [
        'php_version' => PHP_VERSION,
        'has_openssl' => extension_loaded('openssl'),
        'pdo_drivers' => PDO::getAvailableDrivers()
    ]
], $connected ? 'Database connected successfully via TiDB Cloud' : 'Database connection failed', 200);
