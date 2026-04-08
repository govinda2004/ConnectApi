<?php
/**
 * POST /like_post
 * Auth: Bearer token required
 * Body: post_id
 * Toggles like: if already liked, unlikes. If not liked, likes.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$userId = requireAuth();
$postId = (int)($_POST['post_id'] ?? 0);

if ($postId <= 0) {
    jsonError('post_id is required');
}

$db = getDB();

// Check if post exists
$stmt = $db->prepare('SELECT id FROM posts WHERE id = ?');
$stmt->execute([$postId]);
if (!$stmt->fetch()) {
    jsonError('Post not found', 404);
}

// Check if already liked
$stmt = $db->prepare('SELECT id FROM post_likes WHERE user_id = ? AND post_id = ?');
$stmt->execute([$userId, $postId]);
$existing = $stmt->fetch();

if ($existing) {
    // Unlike
    $db->prepare('DELETE FROM post_likes WHERE user_id = ? AND post_id = ?')
       ->execute([$userId, $postId]);
    $liked = false;
} else {
    // Like
    $db->prepare('INSERT INTO post_likes (user_id, post_id) VALUES (?, ?)')
       ->execute([$userId, $postId]);
    $liked = true;
}

// Notify post owner on like
if ($liked) {
    require_once __DIR__ . '/../helpers/notifications.php';
    $stmt = $db->prepare('SELECT user_id FROM posts WHERE id = ?');
    $stmt->execute([$postId]);
    $postOwner = $stmt->fetch();
    if ($postOwner) {
        $stmt = $db->prepare('SELECT name FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $actorName = $stmt->fetchColumn();
        createNotification($db, (int)$postOwner['user_id'], 'like', $userId, $postId, "$actorName liked your post");
    }
}

// Get total likes count
$stmt = $db->prepare('SELECT COUNT(*) FROM post_likes WHERE post_id = ?');
$stmt->execute([$postId]);
$count = (int)$stmt->fetchColumn();

jsonSuccess([
    'liked' => $liked,
    'likes_count' => $count,
], $liked ? 'Post liked' : 'Post unliked');
