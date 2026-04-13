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
$activeUsers = (int)$db->query('SELECT COUNT(*) FROM auth_tokens')->fetchColumn();

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
