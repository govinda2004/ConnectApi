<?php
/**
 * POST /view_story
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

// Check story exists
$stmt = $db->prepare('SELECT id, user_id FROM stories WHERE id = ? AND expires_at > NOW()');
$stmt->execute([$storyId]);
$story = $stmt->fetch();
if (!$story) jsonError('Story not found or expired', 404);
$ownerId = (int)($story['user_id'] ?? 0);

// Record view only for non-owner (ignore if already viewed)
if ($ownerId !== $userId) {
    $stmt = $db->prepare('SELECT id FROM story_views WHERE user_id = ? AND story_id = ?');
    $stmt->execute([$userId, $storyId]);
    if (!$stmt->fetch()) {
        $db->prepare('INSERT INTO story_views (user_id, story_id) VALUES (?, ?)')->execute([$userId, $storyId]);
    }
}

// Get view count
$stmt = $db->prepare('SELECT COUNT(*) FROM story_views WHERE story_id = ?');
$stmt->execute([$storyId]);
$count = (int)$stmt->fetchColumn();

jsonSuccess(['view_count' => $count], 'Story viewed');
