<?php

require_once __DIR__ . '/_admins_common.php';

$db = getDB();
$me = adminRequireAuth();
ensureSuperAdminsMetaColumns($db);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = 'SELECT sa.id, sa.email, sa.role, sa.permissions, sa.is_active, u.name
            FROM super_admins sa
            LEFT JOIN users u ON u.email = sa.email
            WHERE sa.is_active = 1
            ORDER BY sa.id DESC';
    $rows = $db->query($sql)->fetchAll();
    foreach ($rows as &$r) {
        $r['permissions'] = normalizePermissions($r['permissions'] ?? null);
        $r['role'] = $r['role'] ?: 'admin';
    }
    jsonSuccess(['items' => $rows], 'Admins fetched');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $role = trim((string)($input['role'] ?? 'admin'));
    $permissions = normalizePermissions($input['permissions'] ?? []);
    $name = trim((string)($input['name'] ?? ''));

    if ($email === '') jsonError('email is required');
    if ($role === '') $role = 'admin';

    // Create/update linked user profile so name edit reflects.
    $userStmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $userStmt->execute([$email]);
    $user = $userStmt->fetch();
    if (!$user) {
        $pwd = trim((string)($input['password'] ?? ''));
        if ($pwd === '') {
            jsonError('password is required for new admin user');
        }
        $pwdHash = password_hash($pwd, PASSWORD_BCRYPT);
        $ins = $db->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
        $ins->execute([$name, $email, $pwdHash]);
    } elseif ($name !== '') {
        $upd = $db->prepare('UPDATE users SET name = ? WHERE email = ?');
        $upd->execute([$name, $email]);
    }

    $stmt = $db->prepare('
        INSERT INTO super_admins (email, role, permissions, is_active)
        VALUES (?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE role = VALUES(role), permissions = VALUES(permissions), is_active = 1
    ');
    $stmt->execute([$email, $role, json_encode($permissions)]);

    jsonSuccess(['email' => $email, 'role' => $role, 'permissions' => $permissions], 'Admin saved');
}

jsonError('Method not allowed', 405);
