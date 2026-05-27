<?php
/**
 * GET /get_messages?recipient_id=X&limit=50&before_id=0
 * Auth: Bearer token required
 * Returns: paginated conversation history between two users
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Method not allowed', 405);

$userId = requireAuth();
$recipientId = (int)($_GET['recipient_id'] ?? 0);
$limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
$beforeId = (int)($_GET['before_id'] ?? 0);

if ($recipientId <= 0) jsonError('recipient_id is required');

$db = getDB();

// Fetch messages between the two users
$sql = '
    SELECT m.*, u.name AS sender_name, pr.profile_image AS sender_image
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    LEFT JOIN profiles pr ON m.sender_id = pr.user_id
    WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
    AND m.is_deleted = 0
';
$params = [$userId, $recipientId, $recipientId, $userId];

if ($beforeId > 0) {
    $sql .= ' AND m.id < ?';
    $params[] = $beforeId;
}

$sql .= ' ORDER BY m.id DESC LIMIT ?';

$stmt = $db->prepare($sql);
$i = 1;
foreach ($params as $p) {
    $stmt->bindValue($i++, $p);
}
$stmt->bindValue($i++, (int)$limit, PDO::PARAM_INT);
$stmt->execute();

$messages = array_reverse($stmt->fetchAll()); // reverse so oldest first

foreach ($messages as &$msg) {
    $msg['id'] = (int)$msg['id'];
    $msg['sender_id'] = (int)$msg['sender_id'];
    $msg['receiver_id'] = (int)$msg['receiver_id'];
    $msg['is_mine'] = ($msg['sender_id'] === $userId);
    $msg['is_read'] = !empty($msg['read_at']);
}

// Also mark received messages as read
$db->prepare('UPDATE messages SET read_at = NOW() WHERE sender_id = ? AND receiver_id = ? AND read_at IS NULL')
   ->execute([$recipientId, $userId]);

jsonSuccess([
    'messages' => $messages,
    'has_more' => count($messages) >= $limit,
], 'Messages fetched');
