<?php

require_once __DIR__ . '/../_common.php';
require_once __DIR__ . '/../admins/_admins_common.php';
require_once __DIR__ . '/../../../../helpers/migrations.php';

function adminDataResourceConfig(string $resource): ?array
{
    $map = [
        'users' => ['table' => 'users', 'id' => 'id'],
        'admins' => ['table' => 'super_admins', 'id' => 'id', 'ensure_admins' => true],
        'posts' => ['table' => 'posts', 'id' => 'id'],
        'jobs' => ['table' => 'jobs', 'id' => 'id'],
        'activities' => ['table' => 'notifications', 'id' => 'id'],
        'blocks' => ['table' => 'blocks', 'id' => 'id'],
        'reports' => ['table' => 'reports', 'id' => 'id'],
        'feedbacks' => ['table' => 'feedbacks', 'id' => 'id', 'ensure_feedbacks' => true],
        'app_contents' => ['table' => 'app_contents', 'id' => 'id', 'ensure_app_contents' => true],
    ];
    return $map[$resource] ?? null;
}

function adminDataEnsureTable(PDO $db, array $cfg): void
{
    if (!empty($cfg['ensure_admins'])) {
        ensureSuperAdminsMetaColumns($db);
    }
    if (!empty($cfg['ensure_feedbacks'])) {
        ensureFeedbacksTable($db);
    }
    if (!empty($cfg['ensure_app_contents'])) {
        ensureAppContentsTable($db);
    }
}
