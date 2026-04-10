<?php
/**
 * GET /setup_db
 * Runs schema.sql to create all tables
 * WARNING: Only run this once to initialize the database
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/migrations.php';

$db = getDB();

$schema = file_get_contents(__DIR__ . '/../schema.sql');

// Split by semicolons and execute each statement
$statements = array_filter(array_map('trim', explode(';', $schema)));

$created = [];
$errors = [];

foreach ($statements as $sql) {
    if (empty($sql)) continue;
    try {
        $db->exec($sql);
        // Extract table name
        if (preg_match('/CREATE TABLE.*?(\w+)\s*\(/i', $sql, $m)) {
            $created[] = $m[1];
        }
    } catch (PDOException $e) {
        $errors[] = $e->getMessage();
    }
}

// Backward-compatible migrations for old deployments.
ensureAccountTypeColumn($db);
ensureFcmTokenColumn($db);

// Verify expected columns and report if still missing.
$accountTypeExists = false;
$fcmTokenExists = false;
try {
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'account_type'");
    $accountTypeExists = $stmt !== false && $stmt->fetch() !== false;
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
}
try {
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'fcm_token'");
    $fcmTokenExists = $stmt !== false && $stmt->fetch() !== false;
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
}
if (!$accountTypeExists) $errors[] = "Missing expected column users.account_type";
if (!$fcmTokenExists) $errors[] = "Missing expected column users.fcm_token";

jsonSuccess([
    'tables_created' => $created,
    'errors' => $errors,
], 'Database setup complete');
