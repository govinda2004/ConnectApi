<?php
/**
 * POST /add_post
 * Auth: Bearer token required
 * Body: content, image (optional file upload)
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

$imageUrl = null;

// Handle file upload
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif'];

    if (!in_array($ext, $allowedExt)) {
        jsonError('Only JPG, PNG, GIF, WEBP images allowed');
    }
    if ($_FILES['image']['size'] > 10 * 1024 * 1024) {
        jsonError('Image must be under 10MB');
    }

    $filename = 'post_' . $userId . '_' . time() . '.' . $ext;
    $destDir = __DIR__ . '/../uploads/posts/';
    if (!is_dir($destDir)) mkdir($destDir, 0777, true);
    $dest = $destDir . $filename;

    if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
        $imageUrl = '/uploads/posts/' . $filename;
    }
} elseif (!empty($_POST['image_url'])) {
    $imageUrl = trim($_POST['image_url']);
}

$db = getDB();
$stmt = $db->prepare('INSERT INTO posts (user_id, content, image) VALUES (?, ?, ?)');
$stmt->execute([$userId, $content, $imageUrl]);
$postId = (int)$db->lastInsertId();

$stmt = $db->prepare('SELECT * FROM posts WHERE id = ?');
$stmt->execute([$postId]);
$post = $stmt->fetch();

jsonSuccess($post, 'Post created successfully');
