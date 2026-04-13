<?php

require_once __DIR__ . '/../_common.php';
require_once __DIR__ . '/../admins/_admins_common.php';

function adminDataResourceConfig(string $resource): ?array
{
    $map = [
        'users' => ['table' => 'users', 'id' => 'id'],
        'admins' => ['table' => 'super_admins', 'id' => 'id', 'ensure_admins' => true],
        'posts' => ['table' => 'posts', 'id' => 'id'],
        'jobs' => ['table' => 'jobs', 'id' => 'id'],
        'activities' => ['table' => 'notifications', 'id' => 'id'],
    ];
    return $map[$resource] ?? null;
}

function adminDataEnsureTable(PDO $db, array $cfg): void
{
    if (!empty($cfg['ensure_admins'])) {
        ensureSuperAdminsMetaColumns($db);
    }
}
