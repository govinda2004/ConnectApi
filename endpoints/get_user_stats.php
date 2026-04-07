<?php
/**
 * GET /get_user_stats
 * Auth: Bearer token required
 * Returns: connections count, posts count, profile views
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

$userId = requireAuth();
$db = getDB();

// Connections count (accepted)
$stmt = $db->prepare('
    SELECT COUNT(*) FROM connections
    WHERE (sender_id = ? OR receiver_id = ?) AND status = ?
');
$stmt->execute([$userId, $userId, 'accepted']);
$connections = (int)$stmt->fetchColumn();

// Posts count
$stmt = $db->prepare('SELECT COUNT(*) FROM posts WHERE user_id = ?');
$stmt->execute([$userId]);
$posts = (int)$stmt->fetchColumn();

// Total likes received
$stmt = $db->prepare('
    SELECT COUNT(*) FROM post_likes pl
    JOIN posts p ON pl.post_id = p.id
    WHERE p.user_id = ?
');
$stmt->execute([$userId]);
$likesReceived = (int)$stmt->fetchColumn();

jsonSuccess([
    'connections' => $connections,
    'posts' => $posts,
    'likes_received' => $likesReceived,
], 'Stats fetched');
