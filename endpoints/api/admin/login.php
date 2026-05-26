<?php

require_once __DIR__ . '/_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$email = strtolower(trim((string)($input['email'] ?? $_POST['email'] ?? '')));
$password = (string)($input['password'] ?? $_POST['password'] ?? '');

if ($email === '' || $password === '') {
    jsonError('Email and password are required');
}

if (!isSuperAdminEmail($email)) {
    jsonError('Forbidden: not a super admin', 403);
}

$db = getDB();
$stmt = $db->prepare('SELECT id, name, password FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    jsonError('Invalid email or password');
}

$userId = (int)$user['id'];
$token = createToken($userId, 'SuperAdminWeb');

jsonAuth($token, [
    'id' => $userId,
    'name' => $user['name'],
    'email' => $email,
], 'Login successful');
