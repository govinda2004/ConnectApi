<?php

require_once __DIR__ . '/../_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

adminRequireAuth();
$db = getDB();

$totalUsers = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalAdmins = count(adminAllowedEmailList());
if ($totalAdmins === 0) $totalAdmins = 1; // fallback mode
$activeUsers = 0;
try {
    $activeUsers = (int)$db->query('
        SELECT COUNT(DISTINCT at.user_id)
        FROM auth_tokens at
        INNER JOIN users u ON u.id = at.user_id
    ')->fetchColumn();
} catch (Throwable $e) {
    $activeUsers = 0;
}
if ($activeUsers > $totalUsers) $activeUsers = $totalUsers;

$tables = ['users', 'profiles', 'posts', 'jobs', 'messages', 'connections', 'notifications'];
$totalRecords = 0;
foreach ($tables as $t) {
    try {
        $totalRecords += (int)$db->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
    } catch (Throwable $e) {
        // ignore missing table
    }
}

jsonSuccess([
    'total_users' => $totalUsers,
    'total_admins' => $totalAdmins,
    'active_users' => $activeUsers,
    'total_records' => $totalRecords,
], 'Dashboard stats fetched');
