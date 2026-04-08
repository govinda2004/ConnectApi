<?php
/**
 * POST /remove_profile_image
 * Auth: Bearer token required
 * Body: type (profile_image or profile_banner)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$userId = requireAuth();
$type = trim($_POST['type'] ?? '');

if (!in_array($type, ['profile_image', 'profile_banner'])) {
    jsonError('type must be profile_image or profile_banner');
}

$db = getDB();
$db->prepare("UPDATE profiles SET $type = NULL WHERE user_id = ?")->execute([$userId]);

jsonSuccess(null, ucfirst(str_replace('_', ' ', $type)) . ' removed successfully');
