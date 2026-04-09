<?php
/**
 * GET /get_story_views?story_id=X
 * Auth: Bearer token required
 * Returns: list of users who viewed the story (only own stories)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Method not allowed', 405);

$userId = requireAuth();
$storyId = (int)($_GET['story_id'] ?? 0);
if ($storyId <= 0) jsonError('story_id is required');

$db = getDB();

// Verify ownership
$stmt = $db->prepare('SELECT id FROM stories WHERE id = ? AND user_id = ?');
$stmt->execute([$storyId, $userId]);
if (!$stmt->fetch()) jsonError('Story not found or not yours', 403);

$stmt = $db->prepare('
    SELECT sv.created_at AS viewed_at, u.id AS user_id, u.name AS user_name,
           pr.profile_image AS user_image
    FROM story_views sv
    JOIN users u ON sv.user_id = u.id
    LEFT JOIN profiles pr ON sv.user_id = pr.user_id
    WHERE sv.story_id = ?
    ORDER BY sv.created_at DESC
');
$stmt->execute([$storyId]);
$views = $stmt->fetchAll();
foreach ($views as &$v) {
    $v['user_id'] = (int)($v['user_id'] ?? 0);
}

jsonSuccess($views, 'Story views fetched');
