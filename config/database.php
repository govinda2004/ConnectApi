<?php
/**
 * Database Connection - Optimized for TiDB Cloud & Render
 */

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    // Use 'test' as default because 'sys' is a system-reserved database
    $host = getenv('DB_HOST') ?: 'gateway01.ap-southeast-1.prod.aws.tidbcloud.com';
    $port = getenv('DB_PORT') ?: '4000';
    $name = getenv('DB_NAME') ?: 'test';
    $user = getenv('DB_USER') ?: '2oU8khtXMM7Ygx9.root';
    $pass = getenv('DB_PASS') ?: 'kEjpG0huEYVdPnb5';

    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";

    try {
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 15,
        ];

        $caPath = '/etc/ssl/certs/ca-certificates.crt';
        if (!file_exists($caPath)) {
            $caPath = '/etc/ssl/cert.pem';
        }

        if (file_exists($caPath)) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
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
            'error'   => $e->getMessage()
        ]);
        exit;
    }
}
