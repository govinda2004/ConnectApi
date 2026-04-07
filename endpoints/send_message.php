<?php
/**
 * POST /send_message
 * Auth: Bearer token required
 * Body: receiver_id, message
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$userId = requireAuth();
$receiverId = (int)($_POST['receiver_id'] ?? 0);
$message    = trim($_POST['message'] ?? '');

if ($receiverId <= 0) {
    jsonError('receiver_id is required');
}
if (empty($message)) {
    jsonError('message is required');
}

$db = getDB();

// Verify receiver exists
$stmt = $db->prepare('SELECT id FROM users WHERE id = ?');
$stmt->execute([$receiverId]);
if (!$stmt->fetch()) {
    jsonError('Receiver not found', 404);
}

$stmt = $db->prepare('INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)');
$stmt->execute([$userId, $receiverId, $message]);

jsonSuccess([
    'id'          => (int)$db->lastInsertId(),
    'sender_id'   => $userId,
    'receiver_id' => $receiverId,
    'message'     => $message,
], 'Message sent');
