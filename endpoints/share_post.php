<?php
/**
 * POST /share_post
 * Auth: Bearer token required
 * Body: post_id
 * Tracks share and notifies post author
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$userId = requireAuth();
$postId = (int)($_POST['post_id'] ?? 0);

if ($postId <= 0) jsonError('post_id is required');

$db = getDB();

// Ensure table exists
try { $db->exec("CREATE TABLE IF NOT EXISTS post_shares (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, post_id INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_post (post_id))"); } catch (PDOException $e) {}

// Verify post exists and get author
$stmt = $db->prepare('SELECT id, user_id FROM posts WHERE id = ?');
$stmt->execute([$postId]);
$post = $stmt->fetch();
if (!$post) jsonError('Post not found', 404);

// Record share
$stmt = $db->prepare('INSERT INTO post_shares (user_id, post_id) VALUES (?, ?)');
$stmt->execute([$userId, $postId]);

// Get shares count
$stmt = $db->prepare('SELECT COUNT(*) FROM post_shares WHERE post_id = ?');
$stmt->execute([$postId]);
$sharesCount = (int)$stmt->fetchColumn();

// Notify post author (not for chat — stored in DB)
$postAuthorId = (int)$post['user_id'];
if ($postAuthorId !== $userId) {
    require_once __DIR__ . '/../helpers/notifications.php';
    $stmt = $db->prepare('SELECT name FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $sharerName = $stmt->fetchColumn() ?: 'Someone';
    createNotification($db, $postAuthorId, 'share', $userId, $postId, "$sharerName shared your post");
}

jsonSuccess([
    'post_id' => $postId,
    'shares_count' => $sharesCount,
], 'Post shared');
