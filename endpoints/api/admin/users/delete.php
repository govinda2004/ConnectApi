<?php

require_once __DIR__ . '/_users_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    jsonError('Method not allowed', 405);
}

$admin = adminRequireAuth();
$db = getDB();
ensureUsersIsActiveColumn($db);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) jsonError('Invalid user id');

$stmt = $db->prepare('SELECT id, email FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) jsonError('User not found', 404);

if ((int)$user['id'] === (int)$admin['id']) {
    jsonError('You cannot delete yourself', 400);
}
if (isSuperAdminEmail((string)$user['email'])) {
    jsonError('Cannot delete super admin user', 403);
}

try {
    $del = $db->prepare('DELETE FROM users WHERE id = ?');
    $del->execute([$id]);
    jsonSuccess(['id' => $id], 'User deleted');
} catch (Throwable $e) {
    // Fallback when foreign-key constraints block hard delete.
    $upd = $db->prepare('UPDATE users SET is_active = 0 WHERE id = ?');
    $upd->execute([$id]);
    jsonSuccess(['id' => $id, 'status' => 'inactive'], 'User deactivated');
}
