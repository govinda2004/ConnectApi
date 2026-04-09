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
        // Ignore to keep existing flows alive on DB engines without IF NOT EXISTS.
    }
}
