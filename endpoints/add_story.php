<?php
/**
 * POST /add_story
 * Auth: Bearer token required
 * Body:
 *   - text (optional)
 *   - image (single file, optional)
 *   - images[] (multiple files, optional)
 * At least one of text/image(s) is required.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$userId = requireAuth();
$text = trim($_POST['text'] ?? '');
$imageUrls = [];

function makeStoryUrl(string $filename): string {
    $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
        || strtolower((string)$forwardedProto) === 'https';
    $scheme = $isHttps ? 'https' : 'http';
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($scriptDir === '/' || $scriptDir === '.') {
        $scriptDir = '';
    }
    return $scheme . '://' . $_SERVER['HTTP_HOST'] . $scriptDir . '/uploads/stories/' . $filename;
}

function normalizeStoryFiles(): array {
    $files = [];
    if (isset($_FILES['image']) && is_array($_FILES['image'])) {
        $files[] = $_FILES['image'];
    }
    if (isset($_FILES['images']) && is_array($_FILES['images'])) {
        $multi = $_FILES['images'];
        if (isset($multi['name']) && is_array($multi['name'])) {
            $count = count($multi['name']);
            for ($i = 0; $i < $count; $i++) {
                $files[] = [
                    'name' => $multi['name'][$i] ?? '',
                    'type' => $multi['type'][$i] ?? '',
                    'tmp_name' => $multi['tmp_name'][$i] ?? '',
                    'error' => $multi['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $multi['size'][$i] ?? 0,
                ];
            }
        }
    }
    return $files;
}

// Handle image uploads (single + multi)
$uploadedFiles = normalizeStoryFiles();
$allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif'];
$destDir = __DIR__ . '/../uploads/stories/';
if (!is_dir($destDir)) mkdir($destDir, 0777, true);

foreach ($uploadedFiles as $f) {
    $error = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        continue;
    }
    if ($error !== UPLOAD_ERR_OK) {
        jsonError('Story image upload failed', 400);
    }
    $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif'];
    if (!in_array($ext, $allowedExt)) jsonError('Only image files allowed');
    if ((int)($f['size'] ?? 0) > 10 * 1024 * 1024) jsonError('Image must be under 10MB');

    $filename = 'story_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (move_uploaded_file((string)$f['tmp_name'], $destDir . $filename)) {
        $imageUrls[] = makeStoryUrl($filename);
    } else {
        jsonError('Failed to store uploaded image', 500);
    }
}

if (empty($text) && empty($imageUrls)) {
    jsonError('Text or image is required');
}

$db = getDB();
$expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
$created = [];

if (!empty($imageUrls)) {
    $stmt = $db->prepare('INSERT INTO stories (user_id, text_content, image_url, expires_at) VALUES (?, ?, ?, ?)');
    foreach ($imageUrls as $idx => $url) {
        $storyText = ($idx === 0 && $text !== '') ? $text : null;
        $stmt->execute([$userId, $storyText, $url, $expiresAt]);
        $storyId = (int)$db->lastInsertId();
        $stmtOne = $db->prepare('SELECT * FROM stories WHERE id = ?');
        $stmtOne->execute([$storyId]);
        $story = $stmtOne->fetch();
        if ($story) {
            $story['id'] = (int)$story['id'];
            $created[] = $story;
        }
    }
} else {
    $stmt = $db->prepare('INSERT INTO stories (user_id, text_content, image_url, expires_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, $text ?: null, null, $expiresAt]);
    $storyId = (int)$db->lastInsertId();
    $stmtOne = $db->prepare('SELECT * FROM stories WHERE id = ?');
    $stmtOne->execute([$storyId]);
    $story = $stmtOne->fetch();
    if ($story) {
        $story['id'] = (int)$story['id'];
        $created[] = $story;
    }
}

jsonSuccess([
    'stories' => $created,
    'created_count' => count($created),
], count($created) > 1 ? 'Stories added successfully' : 'Story added successfully');
