<?php
/**
 * POST /add_post
 * Auth: Bearer token required
 * Body: content, image (optional file upload or URL)
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
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($_FILES['image']['type'], $allowed)) {
        jsonError('Only JPEG, PNG, GIF, WEBP images allowed');
    }
    if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
        jsonError('Image must be under 5MB');
    }

    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $filename = 'post_' . $userId . '_' . time() . '.' . $ext;
    $destDir = __DIR__ . '/../uploads/posts/';
    if (!is_dir($destDir)) mkdir($destDir, 0777, true);
    $dest = $destDir . $filename;

    if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
        $imageUrl = '/uploads/posts/' . $filename;
    }
} elseif (!empty($_POST['image_url'])) {
    // Accept image URL directly
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
