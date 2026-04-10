<?php
/**
 * POST /send_message
 * Auth: Bearer token required
 * Body: receiver_id, message, client_message_id (optional UUID for deduplication)
 * Returns: saved message with id, timestamps
 *
 * Deduplication: If client_message_id is provided and a message with the same
 * client_message_id from this sender already exists, the existing message is
 * returned instead of inserting a duplicate.
 *
 * Chat notifications are sent via FCM push only — NOT stored in the
 * notifications table (requirement #5).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/migrations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$userId = requireAuth();
$receiverId = (int)($_POST['receiver_id'] ?? 0);
$message = trim($_POST['message'] ?? '');
$clientMessageId = trim($_POST['client_message_id'] ?? '');

if ($receiverId <= 0) jsonError('receiver_id is required');
if (empty($message)) jsonError('message is required');

$db = getDB();
ensureFcmTokenColumn($db);

// Ensure client_message_id column exists (backward-compatible migration)
try {
    $db->exec("ALTER TABLE messages ADD COLUMN client_message_id VARCHAR(64) NULL");
    $db->exec("CREATE UNIQUE INDEX idx_client_msg ON messages (sender_id, client_message_id)");
} catch (PDOException $e) {
    // Column/index already exists — ignore
}

$stmt = $db->prepare('SELECT id, name, fcm_token, device_token FROM users WHERE id = ?');
$stmt->execute([$receiverId]);
$receiver = $stmt->fetch();
if (!$receiver) jsonError('Receiver not found', 404);

// Block check: either direction prevents messaging
$stmt = $db->prepare('SELECT id FROM blocks WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)');
$stmt->execute([$userId, $receiverId, $receiverId, $userId]);
if ($stmt->fetch()) jsonError('Cannot send message to this user', 403);

$stmt = $db->prepare('SELECT name FROM users WHERE id = ?');
$stmt->execute([$userId]);
$sender = $stmt->fetch();

// --- Deduplication: check if this client_message_id was already sent ---
if ($clientMessageId !== '') {
    $stmt = $db->prepare('SELECT * FROM messages WHERE sender_id = ? AND client_message_id = ?');
    $stmt->execute([$userId, $clientMessageId]);
    $existing = $stmt->fetch();
    if ($existing) {
        // Return the existing message instead of inserting a duplicate
        jsonSuccess([
            'id'                => (int)$existing['id'],
            'sender_id'         => $userId,
            'receiver_id'       => $receiverId,
            'sender_name'       => $sender['name'] ?? '',
            'message'           => $existing['message'],
            'client_message_id' => $clientMessageId,
            'created_at'        => $existing['created_at'],
            'is_read'           => $existing['read_at'] !== null,
            'deduplicated'      => true,
        ], 'Message already sent');
    }
}

// --- Insert new message ---
$stmt = $db->prepare('INSERT INTO messages (sender_id, receiver_id, message, client_message_id) VALUES (?, ?, ?, ?)');
$stmt->execute([$userId, $receiverId, $message, $clientMessageId !== '' ? $clientMessageId : null]);
$msgId = (int)$db->lastInsertId();

$stmt = $db->prepare('SELECT * FROM messages WHERE id = ?');
$stmt->execute([$msgId]);
$msg = $stmt->fetch();

$senderName = $sender['name'] ?? '';
$snippet = mb_substr($message, 0, 40);

// Chat notifications: FCM push ONLY — do NOT store in notifications DB.
// This ensures chat messages don't appear in the notification list screen.
require_once __DIR__ . '/../helpers/fcm.php';
$receiverFcmToken = trim((string)($receiver['fcm_token'] ?? ''));
if ($receiverFcmToken === '') {
    $receiverFcmToken = trim((string)($receiver['device_token'] ?? ''));
}
if ($receiverFcmToken !== '') {
    $pushOk = sendFcmToDeviceToken(
        $receiverFcmToken,
        $senderName !== '' ? $senderName : 'New message',
        $snippet,
        [
            'type' => 'chat',
            'notification_type' => 'chat',
            'sender_id' => (string)$userId,
            'sender_name' => (string)$senderName,
            'receiver_id' => (string)$receiverId,
            'message_id' => (string)$msgId,
        ]
    );
    if (!$pushOk) {
        error_log('[CHAT_PUSH] FCM send failed for message_id=' . $msgId . ', receiver_id=' . $receiverId);
    } else {
        error_log('[CHAT_PUSH] FCM send success for message_id=' . $msgId . ', receiver_id=' . $receiverId);
    }
} else {
    error_log('[CHAT_PUSH] skipped: no receiver token for receiver_id=' . $receiverId);
}

jsonSuccess([
    'id'                => $msgId,
    'sender_id'         => $userId,
    'receiver_id'       => $receiverId,
    'sender_name'       => $senderName,
    'message'           => $message,
    'client_message_id' => $clientMessageId !== '' ? $clientMessageId : null,
    'created_at'        => $msg['created_at'],
    'is_read'           => false,
], 'Message sent');
