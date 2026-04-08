<?php
/**
 * POST /add_story
 * Auth: Bearer token required
 * Body: text (optional), image (file, optional - at least one required)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$userId = requireAuth();
$text = trim($_POST['text'] ?? '');
$imageUrl = null;

// Handle image upload
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif'];
    if (!in_array($ext, $allowedExt)) jsonError('Only image files allowed');
    if ($_FILES['image']['size'] > 10 * 1024 * 1024) jsonError('Image must be under 10MB');

    $filename = 'story_' . $userId . '_' . time() . '.' . $ext;
    $destDir = __DIR__ . '/../uploads/stories/';
    if (!is_dir($destDir)) mkdir($destDir, 0777, true);
    if (move_uploaded_file($_FILES['image']['tmp_name'], $destDir . $filename)) {
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
            . '://' . $_SERVER['HTTP_HOST'];
        $imageUrl = $baseUrl . '/uploads/stories/' . $filename;
    }
}

if (empty($text) && empty($imageUrl)) {
    jsonError('Text or image is required');
}

$db = getDB();
$expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

$stmt = $db->prepare('INSERT INTO stories (user_id, text_content, image_url, expires_at) VALUES (?, ?, ?, ?)');
$stmt->execute([$userId, $text ?: null, $imageUrl, $expiresAt]);
$storyId = (int)$db->lastInsertId();

$stmt = $db->prepare('SELECT * FROM stories WHERE id = ?');
$stmt->execute([$storyId]);
$story = $stmt->fetch();
$story['id'] = (int)$story['id'];

jsonSuccess($story, 'Story added successfully');
