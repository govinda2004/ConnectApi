<?php
/**
 * GET /get_stories
 * Auth: Bearer token required
 * Returns: grouped stories of self + connections (not expired)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Method not allowed', 405);

$userId = requireAuth();
$db = getDB();

// Delete expired stories
$db->exec("DELETE FROM stories WHERE expires_at < NOW()");

// Get connection user IDs (accepted)
$stmt = $db->prepare('
    SELECT CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END AS friend_id
    FROM connections WHERE status = "accepted" AND (sender_id = ? OR receiver_id = ?)
');
$stmt->execute([$userId, $userId, $userId]);
$friendIds = array_column($stmt->fetchAll(), 'friend_id');

// Include self
$allIds = array_merge([$userId], $friendIds);
$placeholders = implode(',', array_fill(0, count($allIds), '?'));

// Get all active stories from self + connections
$stmt = $db->prepare("
    SELECT s.*, u.name AS user_name, pr.profile_image AS user_image
    FROM stories s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN profiles pr ON s.user_id = pr.user_id
    WHERE s.user_id IN ($placeholders)
    AND s.expires_at > NOW()
    ORDER BY s.created_at DESC
");
$stmt->execute($allIds);
$stories = $stmt->fetchAll();

// Check which stories current user has viewed
$storyIds = array_column($stories, 'id');
$viewedIds = [];
if (!empty($storyIds)) {
    $ph = implode(',', array_fill(0, count($storyIds), '?'));
    $stmt = $db->prepare("SELECT story_id FROM story_views WHERE user_id = ? AND story_id IN ($ph)");
    $stmt->execute(array_merge([$userId], $storyIds));
    $viewedIds = array_column($stmt->fetchAll(), 'story_id');
}

// Group by user
$grouped = [];
foreach ($stories as &$s) {
    $s['id'] = (int)$s['id'];
    $s['user_id'] = (int)$s['user_id'];
    $s['is_viewed'] = in_array($s['id'], $viewedIds);

    // Get view count
    $stmt2 = $db->prepare('SELECT COUNT(*) FROM story_views WHERE story_id = ?');
    $stmt2->execute([$s['id']]);
    $s['view_count'] = (int)$stmt2->fetchColumn();

    $uid = $s['user_id'];
    if (!isset($grouped[$uid])) {
        $grouped[$uid] = [
            'user_id' => $uid,
            'user_name' => $s['user_name'],
            'user_image' => $s['user_image'],
            'is_own' => ($uid === $userId),
            'has_unviewed' => false,
            'stories' => [],
        ];
    }
    $grouped[$uid]['stories'][] = $s;
    if (!$s['is_viewed']) $grouped[$uid]['has_unviewed'] = true;
}

// Sort: own first, then by latest story
$result = array_values($grouped);
usort($result, function ($a, $b) {
    if ($a['is_own'] && !$b['is_own']) return -1;
    if (!$a['is_own'] && $b['is_own']) return 1;
    // Unviewed first
    if ($a['has_unviewed'] && !$b['has_unviewed']) return -1;
    if (!$a['has_unviewed'] && $b['has_unviewed']) return 1;
    return 0;
});

jsonSuccess($result, 'Stories fetched');
