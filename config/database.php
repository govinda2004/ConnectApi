<?php
/**
 * Database Connection - Optimized for TiDB Cloud & Render
 */

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    // 1. TiDB Cloud Credentials from your screenshot
    $host = getenv('DB_HOST') ?: 'gateway01.ap-southeast-1.prod.aws.tidbcloud.com';
    $port = getenv('DB_PORT') ?: '4000';
    $name = getenv('DB_NAME') ?: 'sys'; // You can change 'sys' to your actual database name later
    $user = getenv('DB_USER') ?: '2oU8khtXMM7Ygx9.root';
    $pass = getenv('DB_PASS') ?: 'kEjpG0huEYVdPnb5';

    // Support for DATABASE_URL if set in Render
    $dbUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL');
    if ($dbUrl) {
        $p = parse_url($dbUrl);
        $host = $p['host'] ?? $host;
        $port = isset($p['port']) ? (string)$p['port'] : $port;
        $name = ltrim($p['path'] ?? '', '/') ?: $name;
        $user = $p['user'] ?? $user;
        $pass = $p['pass'] ?? $pass;
    }

    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";

    try {
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 15,
        ];

        // TiDB Cloud REQUIRES SSL.
        // Render and most Linux servers have CA certs at this path:
        $caPath = '/etc/ssl/certs/ca-certificates.crt';

        // If not found, try common alternate path
        if (!file_exists($caPath)) {
            $caPath = '/etc/ssl/cert.pem';
        }

        if (file_exists($caPath)) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
            // Necessary for some PHP versions to verify TiDB's certificate correctly
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        }

        $pdo = new PDO($dsn, $user, $pass, $options);
        return $pdo;
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'TiDB Cloud Connection Failed',
            'error'   => $e->getMessage(),
            'debug'   => [
                'host' => $host,
                'port' => $port,
                'user' => $user
            ]
        ]);
        exit;
    }
}
