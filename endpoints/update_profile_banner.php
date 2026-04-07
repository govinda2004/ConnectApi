<?php
/**
 * POST /update_profile_banner
 * Auth: Bearer token required
 * Content-Type: multipart/form-data
 * Field: image (file)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$userId = requireAuth();

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    jsonError('No image file uploaded');
}

$file = $_FILES['image'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif'];

if (!in_array($ext, $allowedExt)) {
    jsonError('Invalid image type. Allowed: jpg, png, webp, gif');
}

if ($file['size'] > 10 * 1024 * 1024) {
    jsonError('Image must be less than 10MB');
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'banner_' . $userId . '_' . time() . '.' . $ext;
$uploadDir = __DIR__ . '/../uploads/';
$destination = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    jsonError('Failed to save image', 500);
}

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . $_SERVER['HTTP_HOST'];
$imageUrl = $baseUrl . '/uploads/' . $filename;

$db = getDB();
$db->prepare('UPDATE profiles SET profile_banner = ? WHERE user_id = ?')
   ->execute([$imageUrl, $userId]);

jsonSuccess(['profile_banner' => $imageUrl], 'Banner updated');
