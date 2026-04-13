<?php
/**
 * PlanetScale MySQL Database Connection (PDO + SSL)
 *
 * Set these environment variables on Render:
 *   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
 */

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'connectin';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';

    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // PlanetScale requires SSL
    $sslCert = getenv('DB_SSL_CA');
    if ($sslCert) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCert;
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }

    $pdo = new PDO($dsn, $user, $pass, $options);
    // Keep DB session in UTC so all CURRENT_TIMESTAMP values are consistent.
    try {
        $pdo->exec("SET time_zone = '+00:00'");
    } catch (Throwable $e) {
        // Best effort only; do not block API if this fails.
    }
    return $pdo;
}
