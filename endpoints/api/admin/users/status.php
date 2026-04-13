<?php

require_once __DIR__ . '/_users_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
    jsonError('Method not allowed', 405);
}

adminRequireAuth();
$db = getDB();
ensureUsersIsActiveColumn($db);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) jsonError('Invalid user id');

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$status = trim((string)($input['status'] ?? ''));
if (!in_array($status, ['active', 'inactive'], true)) {
    jsonError('status must be active or inactive');
}

$isActive = $status === 'active' ? 1 : 0;
$stmt = $db->prepare('UPDATE users SET is_active = ? WHERE id = ?');
$stmt->execute([$isActive, $id]);
if ($stmt->rowCount() === 0) {
    $check = $db->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
    $check->execute([$id]);
    if (!$check->fetch()) jsonError('User not found', 404);
}

jsonSuccess(['id' => $id, 'status' => $status], 'User status updated');
