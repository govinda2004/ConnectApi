<?php
/**
 * POST /forgot_password
 * Body: email, new_password (optional - if Firebase already reset, just sync)
 *
 * This endpoint syncs the password in MySQL after Firebase handles the reset.
 * If new_password is provided, it updates the MySQL password.
 * If only email, it just confirms the user exists.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$email       = trim($_POST['email'] ?? '');
$newPassword = $_POST['new_password'] ?? '';

if (empty($email)) {
    jsonError('email is required');
}

$db = getDB();

$stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    jsonError('User not found', 404);
}

if (!empty($newPassword)) {
    // Update password in MySQL
    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
    $stmt = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
    $stmt->execute([$hash, $user['id']]);
    jsonSuccess(null, 'Password updated successfully');
}

jsonSuccess(['email' => $email], 'User exists, reset via Firebase');
