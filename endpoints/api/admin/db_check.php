<?php

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../helpers/response.php';

$driver = strtolower((string)(getenv('DB_DRIVER') ?: 'mysql'));
$host = (string)(getenv('DB_HOST') ?: 'localhost');
$port = (string)(getenv('DB_PORT') ?: ($driver === 'pgsql' ? '5432' : '3306'));
$name = (string)(getenv('DB_NAME') ?: 'connectin');
$user = (string)(getenv('DB_USER') ?: 'root');
$databaseUrl = (string)(getenv('DATABASE_URL') ?: getenv('MYSQL_URL') ?: '');

$connected = false;
$error = null;
try {
    $db = getDB();
    $db->query('SELECT 1');
    $connected = true;
} catch (Throwable $e) {
    $error = $e->getMessage();
}

jsonSuccess([
    'connected' => $connected,
    'driver' => $driver,
    'host' => $host,
    'port' => $port,
    'database' => $name,
    'user' => $user,
    'has_database_url' => $databaseUrl !== '',
    'error' => $error,
], $connected ? 'Database connected' : 'Database connection failed', $connected ? 200 : 500);
