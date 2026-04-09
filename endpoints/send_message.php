<?php
/**
 * POST /send_message
 * Auth: Bearer token required
 * Body: receiver_id, message
 * Returns: saved message with id, timestamps
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$userId = requireAuth();
$receiverId = (int)($_POST['receiver_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

if ($receiverId <= 0) jsonError('receiver_id is required');
if (empty($message)) jsonError('message is required');

$db = getDB();

$stmt = $db->prepare('SELECT id, name, device_token FROM users WHERE id = ?');
$stmt->execute([$receiverId]);
$receiver = $stmt->fetch();
if (!$receiver) jsonError('Receiver not found', 404);

$stmt = $db->prepare('SELECT name FROM users WHERE id = ?');
$stmt->execute([$userId]);
$sender = $stmt->fetch();

$stmt = $db->prepare('INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)');
$stmt->execute([$userId, $receiverId, $message]);
$msgId = (int)$db->lastInsertId();

$stmt = $db->prepare('SELECT * FROM messages WHERE id = ?');
$stmt->execute([$msgId]);
$msg = $stmt->fetch();

// Notify receiver about new message
require_once __DIR__ . '/../helpers/notifications.php';
$senderName = $sender['name'] ?? '';
$snippet = mb_substr($message, 0, 40);
createNotification($db, $receiverId, 'message', $userId, $msgId, "$senderName sent you a message: \"$snippet\"");

// Push notification through FCM (if receiver token + service account are available)
require_once __DIR__ . '/../helpers/fcm.php';
$receiverDeviceToken = trim((string)($receiver['device_token'] ?? ''));
if ($receiverDeviceToken !== '') {
    sendFcmToDeviceToken(
        $receiverDeviceToken,
        $senderName !== '' ? $senderName : 'New message',
        $snippet,
        [
            'type' => 'chat',
            'sender_id' => (string)$userId,
            'receiver_id' => (string)$receiverId,
            'message_id' => (string)$msgId,
        ]
    );
}

jsonSuccess([
    'id'            => $msgId,
    'sender_id'     => $userId,
    'receiver_id'   => $receiverId,
    'sender_name'   => $senderName,
    'message'       => $message,
    'created_at'    => $msg['created_at'],
    'is_read'       => false,
], 'Message sent');
