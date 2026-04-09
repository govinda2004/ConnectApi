<?php
/**
 * POST /register
 * Body: name, email, password
 * Returns: token + user
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/migrations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$name     = trim($_POST['name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($name) || empty($email) || empty($password)) {
    jsonError('name, email, and password are required');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError('Invalid email format');
}

if (strlen($password) < 6) {
    jsonError('Password must be at least 6 characters');
}

$db = getDB();
ensureAccountTypeColumn($db);

// Check if email exists
$stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    jsonError('Email already registered');
}

// Insert user
$hash = password_hash($password, PASSWORD_BCRYPT);
$stmt = $db->prepare('INSERT INTO users (name, email, password, login_type, account_type) VALUES (?, ?, ?, ?, ?)');
$stmt->execute([$name, $email, $hash, 'email', null]);
$userId = (int)$db->lastInsertId();

// Create profile row
$stmt = $db->prepare('INSERT INTO profiles (user_id) VALUES (?)');
$stmt->execute([$userId]);

// Generate token
$token = createToken($userId);

$user = [
    'id'    => $userId,
    'name'  => $name,
    'email' => $email,
    'account_type' => null,
];

jsonAuth($token, $user, 'Registration successful');
