<?php
/**
 * POST /add_post
 * Auth: Bearer token required
 * Body: content
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$userId = requireAuth();

$content = trim($_POST['content'] ?? '');
if (empty($content)) {
    jsonError('content is required');
}

$db = getDB();
$stmt = $db->prepare('INSERT INTO posts (user_id, content) VALUES (?, ?)');
$stmt->execute([$userId, $content]);
$postId = (int)$db->lastInsertId();

$stmt = $db->prepare('SELECT * FROM posts WHERE id = ?');
$stmt->execute([$postId]);
$post = $stmt->fetch();

jsonSuccess($post, 'Post created successfully');
