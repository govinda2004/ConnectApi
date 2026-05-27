<?php
/**
 * GET /get_user_activity?user_id=123&page=1&limit=20
 * Auth: Bearer token (optional — own profile if omitted)
 * Returns: paginated timeline of user activities
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Method not allowed', 405);

// Optionally authenticate
$currentUserId = 0;
$header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!empty($header) && str_starts_with($header, 'Bearer ')) {
    $token = substr($header, 7);
    $db2 = getDB();
    $stmt2 = $db2->prepare('SELECT user_id FROM auth_tokens WHERE token = ?');
    $stmt2->execute([$token]);
    $row = $stmt2->fetch();
    if ($row) $currentUserId = (int)$row['user_id'];
}

$targetUserId = (int)($_GET['user_id'] ?? $currentUserId);
if ($targetUserId <= 0) jsonError('user_id is required');

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$db = getDB();

// UNION query across activity tables
$stmt = $db->prepare("
    (SELECT 'post' AS activity_type, p.id AS target_id, p.content AS detail, p.created_at
     FROM posts p WHERE p.user_id = ?)
    UNION ALL
    (SELECT 'comment' AS activity_type, pc.post_id AS target_id, pc.comment AS detail, pc.created_at
     FROM post_comments pc WHERE pc.user_id = ?)
    UNION ALL
    (SELECT 'connection' AS activity_type, c.receiver_id AS target_id,
            (SELECT name FROM users WHERE id = c.receiver_id) AS detail, c.created_at
     FROM connections c WHERE c.sender_id = ? AND c.status = 'accepted')
    UNION ALL
    (SELECT 'job_application' AS activity_type, ja.job_id AS target_id,
            (SELECT title FROM jobs WHERE id = ja.job_id) AS detail, ja.created_at
     FROM job_applications ja WHERE ja.user_id = ?)
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
");

$stmt->bindValue(1, $targetUserId, PDO::PARAM_INT);
$stmt->bindValue(2, $targetUserId, PDO::PARAM_INT);
$stmt->bindValue(3, $targetUserId, PDO::PARAM_INT);
$stmt->bindValue(4, $targetUserId, PDO::PARAM_INT);
$stmt->bindValue(5, (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(6, (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$activities = $stmt->fetchAll();

foreach ($activities as &$a) {
    $a['target_id'] = (int)$a['target_id'];
}

jsonSuccess([
    'activities' => $activities,
    'user_id' => $targetUserId,
    'page' => $page,
    'has_more' => count($activities) >= $limit,
], 'Activity fetched');
