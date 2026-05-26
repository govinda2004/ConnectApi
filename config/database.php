<?php
/**
 * MySQL Database Connection
 * Configuration optimized for Railway/Render
 */

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    // Default Railway Credentials (InfinityFree will NOT work on Render)
    $host = getenv('DB_HOST') ?: 'junction.proxy.rlwy.net';
    $port = getenv('DB_PORT') ?: '19383';
    $name = getenv('DB_NAME') ?: 'connectin';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: 'connectin2024';

    // Support for DATABASE_URL (If provided by Render/Railway)
    $databaseUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL') ?: '';
    if ($databaseUrl) {
        $parts = parse_url($databaseUrl);
        if (is_array($parts)) {
            $host = $parts['host'] ?? $host;
            $port = isset($parts['port']) ? (string)$parts['port'] : $port;
            $name = ltrim($parts['path'] ?? '', '/') ?: $name;
            $user = $parts['user'] ?? $user;
            $pass = $parts['pass'] ?? $pass;
        }
    }

    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
        return $pdo;
    } catch (PDOException $e) {
        // We throw the error so that the calling script (like db-check) can catch and display it
        throw new Exception("Connection failed: " . $e->getMessage());
    }
}
