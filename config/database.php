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

    // Support common Render-style single connection string as fallback.
    $databaseUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL') ?: '';
    if ($databaseUrl && (getenv('DB_HOST') === false || getenv('DB_NAME') === false || getenv('DB_USER') === false)) {
        $parts = parse_url($databaseUrl);
        if (is_array($parts)) {
            $scheme = strtolower($parts['scheme'] ?? '');
            // This API uses MySQL; accept mysql and mysql-compatible schemes only.
            if (in_array($scheme, ['mysql', 'mariadb'], true)) {
                $host = $parts['host'] ?? $host;
                $port = isset($parts['port']) ? (string)$parts['port'] : $port;
                $path = $parts['path'] ?? '';
                $name = ltrim($path, '/') ?: $name;
                $user = $parts['user'] ?? $user;
                $pass = $parts['pass'] ?? $pass;
            } elseif (in_array($scheme, ['postgres', 'postgresql'], true)) {
                throw new RuntimeException('PostgreSQL URL detected, but this API currently supports MySQL only. Configure DB_* for a MySQL database (port 3306).');
            }
        }
    }

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
