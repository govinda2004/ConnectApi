<?php
/**
 * GET /setup_db
 * Runs schema.sql to create all tables
 * WARNING: Only run this once to initialize the database
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';

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

// Backward-compatible migration for old deployments where users.account_type is missing.
try {
    $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS account_type ENUM('normal','organization') NULL AFTER firebase_uid");
} catch (PDOException $e) {
    $errors[] = $e->getMessage();
}

// Backward-compatible migration for FCM token column.
try {
    $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS fcm_token VARCHAR(512) NULL AFTER device_token");
} catch (PDOException $e) {
    $errors[] = $e->getMessage();
}

jsonSuccess([
    'tables_created' => $created,
    'errors' => $errors,
], 'Database setup complete');
