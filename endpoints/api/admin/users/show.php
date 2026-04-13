<?php

require_once __DIR__ . '/_users_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

adminRequireAuth();
$db = getDB();
ensureUsersIsActiveColumn($db);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) jsonError('Invalid user id');

$stmt = $db->prepare('SELECT id, name, email, phone, account_type, created_at, is_active FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) jsonError('User not found', 404);

$user = normalizeUserStatus($user);
jsonSuccess($user, 'User details fetched');
