<?php

require_once __DIR__ . '/_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$admin = adminRequireAuth();
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$current = (string)($input['current_password'] ?? '');
$new = (string)($input['new_password'] ?? '');

if ($current === '' || $new === '') jsonError('current_password and new_password are required');
if (strlen($new) < 6) jsonError('new_password must be at least 6 characters');

$db = getDB();
$stmt = $db->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
$stmt->execute([(int)$admin['id']]);
$hash = (string)$stmt->fetchColumn();
if ($hash === '' || !password_verify($current, $hash)) {
    jsonError('Current password is incorrect', 401);
}

$newHash = password_hash($new, PASSWORD_BCRYPT);
$upd = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
$upd->execute([$newHash, (int)$admin['id']]);

jsonSuccess([], 'Password changed');
