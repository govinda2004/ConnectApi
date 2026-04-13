<?php

require_once __DIR__ . '/../_common.php';

function ensureSuperAdminsMetaColumns(PDO $db): void
{
    static $checked = false;
    if ($checked) return;
    $checked = true;

    ensureSuperAdminsTable($db);

    $columns = ['role' => "ALTER TABLE super_admins ADD COLUMN role VARCHAR(100) NULL", 'permissions' => "ALTER TABLE super_admins ADD COLUMN permissions JSON NULL"];
    foreach ($columns as $name => $sql) {
        try {
            $stmt = $db->query("SHOW COLUMNS FROM super_admins LIKE '{$name}'");
            $exists = $stmt !== false && $stmt->fetch() !== false;
            if (!$exists) $db->exec($sql);
        } catch (Throwable $e) {
            // Best effort only.
        }
    }
}

function normalizePermissions($value): array
{
    if (is_array($value)) return array_values($value);
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    if (is_array($decoded)) return array_values($decoded);
    return [];
}
