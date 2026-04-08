<?php
/**
 * GET /unread_count
 * Auth: Bearer token required
 * Returns: total unread + per-conversation unread counts
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Method not allowed', 405);

$userId = requireAuth();
$db = getDB();

// Total unread
$stmt = $db->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND read_at IS NULL AND is_deleted = 0');
$stmt->execute([$userId]);
$total = (int)$stmt->fetchColumn();

// Per sender unread
$stmt = $db->prepare('
    SELECT sender_id, COUNT(*) AS count, u.name AS sender_name
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.receiver_id = ? AND m.read_at IS NULL AND m.is_deleted = 0
    GROUP BY m.sender_id
');
$stmt->execute([$userId]);
$perSender = $stmt->fetchAll();

foreach ($perSender as &$row) {
    $row['sender_id'] = (int)$row['sender_id'];
    $row['count'] = (int)$row['count'];
}

jsonSuccess([
    'total' => $total,
    'per_sender' => $perSender,
], 'Unread count fetched');
