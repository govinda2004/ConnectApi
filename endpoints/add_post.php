<?php
/**
 * POST /add_post
 * Auth: Bearer token required
 * Body: content, image/video (optional file upload), media_type (image/video)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$userId = requireAuth();
$content = trim($_POST['content'] ?? '');
if (empty($content)) jsonError('content is required');

$mediaUrl = null;
$mediaType = 'text'; // text, image, video

// Handle image upload
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif'];
    $videoExts = ['mp4', 'mov', 'avi', 'mkv', 'webm', '3gp'];

    if (in_array($ext, $imageExts)) {
        $mediaType = 'image';
        $maxSize = 10 * 1024 * 1024; // 10MB
    } elseif (in_array($ext, $videoExts)) {
        $mediaType = 'video';
        $maxSize = 50 * 1024 * 1024; // 50MB
    } else {
        jsonError('Only image (JPG,PNG,GIF,WEBP) or video (MP4,MOV) files allowed');
    }

    if ($_FILES['image']['size'] > $maxSize) {
        jsonError("File must be under " . ($maxSize / 1024 / 1024) . "MB");
    }

    $filename = 'post_' . $userId . '_' . time() . '.' . $ext;
    $destDir = __DIR__ . '/../uploads/posts/';
    if (!is_dir($destDir)) mkdir($destDir, 0777, true);

    if (move_uploaded_file($_FILES['image']['tmp_name'], $destDir . $filename)) {
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
            . '://' . $_SERVER['HTTP_HOST'];
        $mediaUrl = $baseUrl . '/uploads/posts/' . $filename;
    }
}

$db = getDB();
$stmt = $db->prepare('INSERT INTO posts (user_id, content, image) VALUES (?, ?, ?)');
$stmt->execute([$userId, $content, $mediaUrl]);
$postId = (int)$db->lastInsertId();

$stmt = $db->prepare('SELECT * FROM posts WHERE id = ?');
$stmt->execute([$postId]);
$post = $stmt->fetch();
$post['media_type'] = $mediaType;

// Notify all connections about new post
require_once __DIR__ . '/../helpers/notifications.php';
$stmt = $db->prepare('SELECT name FROM users WHERE id = ?');
$stmt->execute([$userId]);
$posterName = $stmt->fetchColumn();

$stmt = $db->prepare('
    SELECT CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END AS friend_id
    FROM connections WHERE status = "accepted" AND (sender_id = ? OR receiver_id = ?)
');
$stmt->execute([$userId, $userId, $userId]);
$friends = $stmt->fetchAll();

$snippet = mb_substr($content, 0, 50);
foreach ($friends as $f) {
    createNotification($db, (int)$f['friend_id'], 'post_share', $userId, $postId, "$posterName shared a post: \"$snippet\"");
}

jsonSuccess($post, 'Post created successfully');
