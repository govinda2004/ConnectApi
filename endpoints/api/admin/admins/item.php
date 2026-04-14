<?php

require_once __DIR__ . '/_admins_common.php';

$db = getDB();
$me = adminRequireAuth();
ensureSuperAdminsMetaColumns($db);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) jsonError('Invalid admin id');

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $name = trim((string)($input['name'] ?? ''));
    $role = trim((string)($input['role'] ?? 'admin'));
    $permissions = normalizePermissions($input['permissions'] ?? []);
    if ($role === '') $role = 'admin';

    $currentStmt = $db->prepare('SELECT email FROM super_admins WHERE id = ? LIMIT 1');
    $currentStmt->execute([$id]);
    $current = $currentStmt->fetch();
    if (!$current) jsonError('Admin not found', 404);
    $currentEmail = strtolower((string)$current['email']);

    if ($email !== '') {
        $stmt = $db->prepare('UPDATE super_admins SET email = ?, role = ?, permissions = ? WHERE id = ?');
        $stmt->execute([$email, $role, json_encode($permissions), $id]);
    } else {
        $stmt = $db->prepare('UPDATE super_admins SET role = ?, permissions = ? WHERE id = ?');
        $stmt->execute([$role, json_encode($permissions), $id]);
    }
    if ($stmt->rowCount() === 0) {
        $c = $db->prepare('SELECT id FROM super_admins WHERE id = ? LIMIT 1');
        $c->execute([$id]);
        if (!$c->fetch()) jsonError('Admin not found', 404);
    }

    // Keep users table name/email in sync (if linked user exists).
    $targetEmail = $email !== '' ? $email : $currentEmail;
    if ($name !== '') {
        $updName = $db->prepare('UPDATE users SET name = ? WHERE email = ?');
        $updName->execute([$name, $targetEmail]);
    }
    if ($email !== '' && $email !== $currentEmail) {
        $updEmail = $db->prepare('UPDATE users SET email = ? WHERE email = ?');
        $updEmail->execute([$email, $currentEmail]);
    }

    jsonSuccess(['id' => $id], 'Admin updated');
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $stmt = $db->prepare('SELECT id, email FROM super_admins WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) jsonError('Admin not found', 404);
    if (strtolower((string)$row['email']) === strtolower((string)$me['email'])) {
        jsonError('You cannot remove yourself', 400);
    }

    $del = $db->prepare('UPDATE super_admins SET is_active = 0 WHERE id = ?');
    $del->execute([$id]);
    jsonSuccess(['id' => $id], 'Admin removed');
}

jsonError('Method not allowed', 405);
