<?php

require_once __DIR__ . '/_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$setupKey = trim((string)(getenv('SUPER_ADMIN_SETUP_KEY') ?: ''));

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$key = trim((string)($input['setup_key'] ?? $_POST['setup_key'] ?? ''));
$name = trim((string)($input['name'] ?? $_POST['name'] ?? 'Super Admin'));
$email = strtolower(trim((string)($input['email'] ?? $_POST['email'] ?? '')));
$password = (string)($input['password'] ?? $_POST['password'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError('Valid email is required');
}
if (strlen($password) < 6) {
    jsonError('Password must be at least 6 characters');
}

$db = getDB();
ensureSuperAdminsTable($db);

// Security rule:
// - If SUPER_ADMIN_SETUP_KEY is configured, always require it.
// - If not configured, allow bootstrap only when there are zero active super admins (first-time setup).
$activeAdmins = 0;
try {
    $c = $db->query('SELECT COUNT(*) FROM super_admins WHERE is_active = 1');
    $activeAdmins = (int)$c->fetchColumn();
} catch (Throwable $e) {
    $activeAdmins = 0;
}

if ($setupKey !== '') {
    if ($key === '' || !hash_equals($setupKey, $key)) {
        jsonError('Invalid setup key', 403);
    }
} else {
    if ($activeAdmins > 0) {
        jsonError('Super admin setup is locked. Set SUPER_ADMIN_SETUP_KEY env var to reset/create additional admins.', 403);
    }
}

$hash = password_hash($password, PASSWORD_BCRYPT);
$db->beginTransaction();
try {
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $userId = (int)$user['id'];
        $upd = $db->prepare('UPDATE users SET name = ?, password = ? WHERE id = ?');
        $upd->execute([$name !== '' ? $name : 'Super Admin', $hash, $userId]);
    } else {
        $ins = $db->prepare('INSERT INTO users (name, email, password, login_type) VALUES (?, ?, ?, ?)');
        $ins->execute([$name !== '' ? $name : 'Super Admin', $email, $hash, 'email']);
        $userId = (int)$db->lastInsertId();
    }

    $sa = $db->prepare('INSERT INTO super_admins (email, is_active) VALUES (?, 1)
        ON DUPLICATE KEY UPDATE is_active = 1');
    $sa->execute([$email]);

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    jsonError('Failed to create super admin: ' . $e->getMessage(), 500);
}

$token = createToken($userId, 'SuperAdminWeb');

jsonSuccess([
    'token' => $token,
    'admin' => [
        'id' => $userId,
        'name' => $name,
        'email' => $email,
    ],
], 'Super admin account ready');
