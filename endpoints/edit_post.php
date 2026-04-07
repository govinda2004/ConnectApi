<?php
/**
 * POST /edit_post
 * Auth: Bearer token required
 * Body: post_id, content
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$userId = requireAuth();
$postId = (int)($_POST['post_id'] ?? 0);
$content = trim($_POST['content'] ?? '');

if ($postId <= 0) jsonError('post_id is required');
if (empty($content)) jsonError('content is required');

$db = getDB();

// Verify ownership
$stmt = $db->prepare('SELECT id FROM posts WHERE id = ? AND user_id = ?');
$stmt->execute([$postId, $userId]);
if (!$stmt->fetch()) jsonError('Post not found or not yours', 403);

$stmt = $db->prepare('UPDATE posts SET content = ? WHERE id = ? AND user_id = ?');
$stmt->execute([$content, $postId, $userId]);

$stmt = $db->prepare('SELECT * FROM posts WHERE id = ?');
$stmt->execute([$postId]);
$post = $stmt->fetch();

jsonSuccess($post, 'Post updated successfully');
