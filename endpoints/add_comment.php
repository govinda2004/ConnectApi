<?php
/**
 * POST /add_comment
 * Auth: Bearer token required
 * Body: post_id, comment
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$userId = requireAuth();
$postId = (int)($_POST['post_id'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

if ($postId <= 0) {
    jsonError('post_id is required');
}
if (empty($comment)) {
    jsonError('comment is required');
}

$db = getDB();

// Check post exists
$stmt = $db->prepare('SELECT id FROM posts WHERE id = ?');
$stmt->execute([$postId]);
if (!$stmt->fetch()) {
    jsonError('Post not found', 404);
}

$stmt = $db->prepare('INSERT INTO post_comments (user_id, post_id, comment) VALUES (?, ?, ?)');
$stmt->execute([$userId, $postId, $comment]);
$commentId = (int)$db->lastInsertId();

// Get user name
$stmt = $db->prepare('SELECT name FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Notify post owner
require_once __DIR__ . '/../helpers/notifications.php';
$stmt2 = $db->prepare('SELECT user_id FROM posts WHERE id = ?');
$stmt2->execute([$postId]);
$postOwner = $stmt2->fetch();
if ($postOwner) {
    $actorName = $user['name'] ?? '';
    $snippet = mb_substr($comment, 0, 50);
    createNotification($db, (int)$postOwner['user_id'], 'comment', $userId, $postId, "$actorName commented: \"$snippet\"");
}

// Get total comments count
$stmt = $db->prepare('SELECT COUNT(*) FROM post_comments WHERE post_id = ?');
$stmt->execute([$postId]);
$count = (int)$stmt->fetchColumn();

jsonSuccess([
    'comment_id' => $commentId,
    'user_id' => $userId,
    'user_name' => $user['name'] ?? '',
    'comment' => $comment,
    'comments_count' => $count,
], 'Comment added');
