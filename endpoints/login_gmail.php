<?php
/**
 * POST /login_gmail
 * Body: email, name (optional)
 * Creates user if not exists, returns token + user.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$email = trim($_POST['email'] ?? '');
$name  = trim($_POST['name'] ?? '');

if (empty($email)) {
    jsonError('email is required');
}

$db = getDB();

// Check existing user
$stmt = $db->prepare('SELECT id, name, email FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    // Existing user - update name if provided
    if (!empty($name) && $name !== $user['name']) {
        $db->prepare('UPDATE users SET name = ? WHERE id = ?')->execute([$name, $user['id']]);
        $user['name'] = $name;
    }
    $userId = (int)$user['id'];
} else {
    // New user - create
    $userName = !empty($name) ? $name : explode('@', $email)[0];
    $stmt = $db->prepare('INSERT INTO users (name, email, login_type) VALUES (?, ?, ?)');
    $stmt->execute([$userName, $email, 'google']);
    $userId = (int)$db->lastInsertId();

    // Create profile row
    $db->prepare('INSERT INTO profiles (user_id) VALUES (?)')->execute([$userId]);

    $user = [
        'id'    => $userId,
        'name'  => $userName,
        'email' => $email,
    ];
}

$token = createToken($userId);

jsonAuth($token, [
    'id'    => $userId,
    'name'  => $user['name'],
    'email' => $user['email'],
], 'Gmail login successful');
