<?php
/**
 * POST /delete_post
 * Auth: Bearer token required
 * Body: post_id
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$userId = requireAuth();
$postId = (int)($_POST['post_id'] ?? 0);
if ($postId <= 0) jsonError('post_id is required');

$db = getDB();

// Verify ownership
$stmt = $db->prepare('SELECT id FROM posts WHERE id = ? AND user_id = ?');
$stmt->execute([$postId, $userId]);
if (!$stmt->fetch()) jsonError('Post not found or not yours', 403);

// Delete likes, comments, then post
$db->prepare('DELETE FROM post_likes WHERE post_id = ?')->execute([$postId]);
$db->prepare('DELETE FROM post_comments WHERE post_id = ?')->execute([$postId]);
$db->prepare('DELETE FROM posts WHERE id = ? AND user_id = ?')->execute([$postId, $userId]);

jsonSuccess(null, 'Post deleted successfully');
