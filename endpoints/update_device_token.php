<?php
/**
 * POST /update_device_token
 * Diagnostic version to debug why tokens aren't saving
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/migrations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

// 1. Authenticate
$userId = requireAuth();
$db = getDB();
ensureFcmTokenColumn($db);

// 2. Read Input (Handle both standard POST and JSON body)
$rawBody = file_get_contents('php://input');
$jsonBody = json_decode($rawBody, true) ?: [];
$input = array_merge($_POST, $jsonBody);

// 3. Detect token using various possible keys
$token = trim((string)(
    $input['fcm_token'] ??
    $input['device_token'] ??
    $input['token'] ??
    $input['fcmToken'] ??
    ''
));

// 4. Update the database
$stmt = $db->prepare('UPDATE users SET fcm_token = ?, device_token = ? WHERE id = ?');
$stmt->execute([$token, $token, $userId]);
$affected = $stmt->rowCount();
// Note: rowCount() is 0 if the token was already the same in the database.

// 5. Verification - Read back what is actually in the DB
$stmt = $db->prepare('SELECT id, email, fcm_token, device_token FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    jsonError("User ID $userId not found in users table.");
}

jsonSuccess([
    'diagnostic' => [
        'user_id' => (int)$user['id'],
        'email' => $user['email'],
        'input_keys_received' => array_keys($input),
        'token_received_len' => strlen($token),
        'db_fcm_token_len' => strlen((string)$user['fcm_token']),
        'affected_rows' => $affected,
        'matches' => ($token === $user['fcm_token']),
        'is_empty' => empty($token)
    ],
    'received_token_preview' => $token !== '' ? substr($token, 0, 15) . '...' : 'EMPTY'
], $token !== '' ? 'Token update processed' : 'Token cleared/Empty token received');
