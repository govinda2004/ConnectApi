<?php
/**
 * GET /get_comments&post_id=X
 * Auth: Bearer token (optional)
 * Returns: list of comments for a post
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

$postId = (int)($_GET['post_id'] ?? 0);
if ($postId <= 0) {
    jsonError('post_id is required');
}

$db = getDB();

$stmt = $db->prepare('
    SELECT c.id, c.user_id, c.comment, c.created_at,
           u.name AS user_name, p.profile_image AS user_image
    FROM post_comments c
    JOIN users u ON c.user_id = u.id
    LEFT JOIN profiles p ON c.user_id = p.user_id
    WHERE c.post_id = ?
    ORDER BY c.created_at ASC
');
$stmt->execute([$postId]);
$comments = $stmt->fetchAll();

foreach ($comments as &$c) {
    $c['id'] = (int)$c['id'];
    $c['user_id'] = (int)$c['user_id'];
}

jsonSuccess($comments, 'Comments fetched');
