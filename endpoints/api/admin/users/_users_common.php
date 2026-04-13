<?php

require_once __DIR__ . '/../_common.php';

function ensureUsersIsActiveColumn(PDO $db): void
{
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1");
        return;
    } catch (Throwable $e) {
        // Fallback for old MySQL versions.
    }

    try {
        $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'is_active'");
        $exists = $stmt !== false && $stmt->fetch() !== false;
        if (!$exists) {
            $db->exec("ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
        }
    } catch (Throwable $e) {
        // Best effort only.
    }
}

function normalizeUserStatus(array $user): array
{
    $active = isset($user['is_active']) ? (int)$user['is_active'] === 1 : true;
    $user['status'] = $active ? 'active' : 'inactive';
    return $user;
}
