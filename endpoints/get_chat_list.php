<?php
/**
 * GET /get_chat_list
 * Auth: Bearer token required
 * Returns: list of conversations with last message
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

$userId = requireAuth();
$db = getDB();

// Get all unique conversation partners with latest message
$stmt = $db->prepare('
    SELECT
        m.id,
        CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END AS other_user_id,
        m.message AS last_message,
        m.created_at AS last_message_time,
        m.sender_id
    FROM messages m
    INNER JOIN (
        SELECT
            LEAST(sender_id, receiver_id) AS u1,
            GREATEST(sender_id, receiver_id) AS u2,
            MAX(id) AS max_id
        FROM messages
        WHERE sender_id = ? OR receiver_id = ?
        GROUP BY u1, u2
    ) latest ON m.id = latest.max_id
    ORDER BY m.created_at DESC
');
$stmt->execute([$userId, $userId, $userId]);
$conversations = $stmt->fetchAll();

// Enrich with user info
$result = [];
foreach ($conversations as $conv) {
    $otherId = (int)$conv['other_user_id'];
    $stmt2 = $db->prepare('
        SELECT u.id, u.name, u.email, pr.profile_image, pr.headline
        FROM users u
        LEFT JOIN profiles pr ON u.id = pr.user_id
        WHERE u.id = ?
    ');
    $stmt2->execute([$otherId]);
    $otherUser = $stmt2->fetch();

    $result[] = [
        'user_id'           => $otherId,
        'name'              => $otherUser['name'] ?? '',
        'email'             => $otherUser['email'] ?? '',
        'profile_image'     => $otherUser['profile_image'] ?? null,
        'headline'          => $otherUser['headline'] ?? null,
        'last_message'      => $conv['last_message'],
        'last_message_time' => $conv['last_message_time'],
        'is_sent_by_me'     => (int)$conv['sender_id'] === $userId,
    ];
}

jsonSuccess($result, 'Chat list fetched');
