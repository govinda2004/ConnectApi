<?php
/**
 * Lightweight runtime-safe schema guards for incremental deployments.
 */

function ensureAccountTypeColumn(PDO $db): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS account_type ENUM('normal','organization') NULL AFTER firebase_uid");
    } catch (Throwable $e) {
        // Fallback for MySQL versions that don't support "ADD COLUMN IF NOT EXISTS".
        try {
            $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'account_type'");
            $exists = $stmt !== false && $stmt->fetch() !== false;
            if (!$exists) {
                $db->exec("ALTER TABLE users ADD COLUMN account_type ENUM('normal','organization') NULL AFTER firebase_uid");
            }
        } catch (Throwable $inner) {
            // Best-effort migration guard; endpoints should continue to run.
        }
    }
}
