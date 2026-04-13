<?php

require_once __DIR__ . '/_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim((string)($input['email'] ?? $_POST['email'] ?? ''));
$password = (string)($input['password'] ?? $_POST['password'] ?? '');

if ($email === '' || $password === '') {
    jsonError('email and password are required');
}

$db = getDB();
$stmt = $db->prepare('SELECT id, name, email, password FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, (string)$user['password'])) {
    jsonError('Invalid email or password', 401);
}

if (!isSuperAdminEmail((string)$user['email'])) {
    jsonError('Forbidden: super admin access required', 403);
}

$token = createToken((int)$user['id'], 'SuperAdminWeb');

jsonSuccess([
    'token' => $token,
    'admin' => [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
    ],
], 'Admin login successful');
