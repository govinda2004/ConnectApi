<?php

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../helpers/response.php';

$connected = false;
$error = null;
$details = [];

try {
    // Attempt to get the DB instance
    $db = getDB();
    $db->query('SELECT 1');
    $connected = true;
} catch (Throwable $e) {
    $error = $e->getMessage();
}

// We pull these again to show what's currently being used in the config
// Note: In a real production app, you might want to hide sensitive info,
// but for debugging connection issues, this is helpful.
$host = getenv('DB_HOST') ?: 'junction.proxy.rlwy.net';
$port = getenv('DB_PORT') ?: '19383';
$name = getenv('DB_NAME') ?: 'connectin';
$user = getenv('DB_USER') ?: 'root';

jsonSuccess([
    'connected' => $connected,
    'driver' => 'mysql',
    'host' => $host,
    'port' => $port,
    'database' => $name,
    'user' => $user,
    'error' => $error,
    'tip' => 'If Host is sqlXXX.infinityfree.com, it will FAIL on Render because InfinityFree blocks remote access.'
], $connected ? 'Database connected successfully' : 'Database connection failed', $connected ? 200 : 200); // 200 so UI can show the error details
