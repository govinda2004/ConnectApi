<?php
/**
 * POST /login
 * Body: email, password
 * Returns: token + user
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    jsonError('email and password are required');
}

$db = getDB();

$stmt = $db->prepare('SELECT id, name, email, password FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    jsonError('Invalid email or password', 401);
}

$token = createToken((int)$user['id']);

jsonAuth($token, [
    'id'    => (int)$user['id'],
    'name'  => $user['name'],
    'email' => $user['email'],
], 'Login successful');
