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

function ensureFcmTokenColumn(PDO $db): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS fcm_token VARCHAR(512) NULL AFTER device_token");
    } catch (Throwable $e) {
        // Fallback for MySQL versions that don't support "ADD COLUMN IF NOT EXISTS".
        try {
            $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'fcm_token'");
            $exists = $stmt !== false && $stmt->fetch() !== false;
            if (!$exists) {
                $db->exec("ALTER TABLE users ADD COLUMN fcm_token VARCHAR(512) NULL AFTER device_token");
            }
        } catch (Throwable $inner) {
            // Best-effort migration guard; endpoints should continue to run.
        }
    }
}

function ensureNotificationsImageColumn(PDO $db): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $db->exec("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS image_url VARCHAR(512) NULL AFTER message");
    } catch (Throwable $e) {
        // Fallback for MySQL versions that don't support "ADD COLUMN IF NOT EXISTS".
        try {
            $stmt = $db->query("SHOW COLUMNS FROM notifications LIKE 'image_url'");
            $exists = $stmt !== false && $stmt->fetch() !== false;
            if (!$exists) {
                $db->exec("ALTER TABLE notifications ADD COLUMN image_url VARCHAR(512) NULL AFTER message");
            }
        } catch (Throwable $inner) {
            // Best-effort migration guard; endpoints should continue to run.
        }
    }
}

function ensureNotificationsBroadcastBatchColumn(PDO $db): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $db->exec("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS broadcast_batch_id VARCHAR(64) NULL AFTER image_url");
    } catch (Throwable $e) {
        try {
            $stmt = $db->query("SHOW COLUMNS FROM notifications LIKE 'broadcast_batch_id'");
            $exists = $stmt !== false && $stmt->fetch() !== false;
            if (!$exists) {
                $db->exec("ALTER TABLE notifications ADD COLUMN broadcast_batch_id VARCHAR(64) NULL AFTER image_url");
            }
        } catch (Throwable $inner) {
            // Best-effort migration guard; endpoints should continue to run.
        }
    }
}
