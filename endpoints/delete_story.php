<?php
/**
 * POST /delete_story
 * Auth: Bearer token required
 * Body: story_id
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$userId = requireAuth();
$storyId = (int)($_POST['story_id'] ?? 0);
if ($storyId <= 0) jsonError('story_id is required');

$db = getDB();

$stmt = $db->prepare('SELECT id FROM stories WHERE id = ? AND user_id = ?');
$stmt->execute([$storyId, $userId]);
if (!$stmt->fetch()) jsonError('Story not found or not yours', 403);

$db->prepare('DELETE FROM story_views WHERE story_id = ?')->execute([$storyId]);
$db->prepare('DELETE FROM stories WHERE id = ? AND user_id = ?')->execute([$storyId, $userId]);

jsonSuccess(null, 'Story deleted');
