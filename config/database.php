<?php
/**
 * MySQL Database Connection
 * Configuration for InfinityFree Hosting
 */

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'connectin';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $driver = strtolower((string)(getenv('DB_DRIVER') ?: 'mysql'));

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

    // Support common Render-style single connection string as fallback.
    $databaseUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL') ?: '';
    if ($databaseUrl && (getenv('DB_HOST') === false || getenv('DB_NAME') === false || getenv('DB_USER') === false)) {
        $parts = parse_url($databaseUrl);
        if (is_array($parts)) {
            $scheme = strtolower($parts['scheme'] ?? '');
            if (in_array($scheme, ['mysql', 'mariadb'], true)) {
                $driver = 'mysql';
                $host = $parts['host'] ?? $host;
                $port = isset($parts['port']) ? (string)$parts['port'] : $port;
                $path = $parts['path'] ?? '';
                $name = ltrim($path, '/') ?: $name;
                $user = $parts['user'] ?? $user;
                $pass = $parts['pass'] ?? $pass;
            } elseif (in_array($scheme, ['postgres', 'postgresql'], true)) {
                $driver = 'pgsql';
                $host = $parts['host'] ?? $host;
                $port = isset($parts['port']) ? (string)$parts['port'] : '5432';
                $path = $parts['path'] ?? '';
                $name = ltrim($path, '/') ?: $name;
                $user = $parts['user'] ?? $user;
                $pass = $parts['pass'] ?? $pass;
            }
        }
    }

    if ($driver === 'pgsql') {
        $dsn = "pgsql:host=$host;port=$port;dbname=$name";
    } else {
        $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // SSL options (not usually required for InfinityFree)
    $sslCert = getenv('DB_SSL_CA');
    if ($sslCert && $driver === 'mysql') {
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
