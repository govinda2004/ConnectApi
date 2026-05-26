<?php
/**
 * MySQL Database Connection
 * Configuration for InfinityFree Hosting
 */

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    // Database Credentials from InfinityFree Dashboard
    $host = 'sql205.infinityfree.com';
    $port = '3306';
    $name = 'if0_42023594_connectin';
    $user = 'if0_42023594';
    $pass = 'HknPk5y8A324';

    // Override with environment variables if present (optional)
    $host = getenv('DB_HOST') ?: $host;
    $port = getenv('DB_PORT') ?: $port;
    $name = getenv('DB_NAME') ?: $name;
    $user = getenv('DB_USER') ?: $user;
    $pass = getenv('DB_PASS') ?: $pass;

    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // SSL options (not usually required for InfinityFree)
    $sslCert = getenv('DB_SSL_CA');
    if ($sslCert) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCert;
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        // Keep DB session in UTC
        $pdo->exec("SET time_zone = '+00:00'");
    } catch (PDOException $e) {
        // Handle connection error
        die("Database Connection Failed: " . $e->getMessage());
    }

    return $pdo;
}
