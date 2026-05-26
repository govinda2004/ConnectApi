<?php

require_once __DIR__ . '/_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$setupKey = trim((string)(getenv('SUPER_ADMIN_SETUP_KEY') ?: ''));

if ($setupKey === '') {
    jsonError('Super admin setup is disabled. Set SUPER_ADMIN_SETUP_KEY env var first.', 403);
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$key = trim((string)($input['setup_key'] ?? $_POST['setup_key'] ?? ''));
$name = trim((string)($input['name'] ?? $_POST['name'] ?? 'Super Admin'));
$email = strtolower(trim((string)($input['email'] ?? $_POST['email'] ?? '')));
$password = (string)($input['password'] ?? $_POST['password'] ?? '');


if ($key === '' || !hash_equals($setupKey, $key)) {
    jsonError('Invalid setup key', 403);
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError('Valid email is required');
}
if (strlen($password) < 6) {
    jsonError('Password must be at least 6 characters');
}

$db = getDB();
ensureSuperAdminsTable($db);

// Temporary relaxed security:
// - If SUPER_ADMIN_SETUP_KEY is configured, validate it.
// - If not configured, allow bootstrap without setup key.
if ($setupKey !== '' && ($key === '' || !hash_equals($setupKey, $key))) {
    jsonError('Invalid setup key', 403);
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

jsonAuth($token, [
    'id' => $userId,
    'name' => $name,
    'email' => $email,
jsonSuccess([
    'token' => $token,
    'admin' => [
        'id' => $userId,
        'name' => $name,
        'email' => $email,
    ],
], 'Super admin account ready');
