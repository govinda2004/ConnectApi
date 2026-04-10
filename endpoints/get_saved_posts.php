<?php
/**
 * GET /get_saved_posts?page=1&limit=20
 * Auth: Bearer token required
 * Returns: paginated list of user's saved posts with full post data
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Method not allowed', 405);

$userId = requireAuth();
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$db = getDB();

$stmt = $db->prepare('SELECT COUNT(*) FROM saved_posts WHERE user_id = ?');
$stmt->execute([$userId]);
$total = (int)$stmt->fetchColumn();

$stmt = $db->prepare('
    SELECT p.*, u.name AS author_name, u.email AS author_email,
           pr.profile_image AS author_image, pr.headline AS author_headline,
           (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) AS likes_count,
           (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) AS comments_count,
           sp.created_at AS saved_at
    FROM saved_posts sp
    JOIN posts p ON sp.post_id = p.id
    JOIN users u ON p.user_id = u.id
    LEFT JOIN profiles pr ON p.user_id = pr.user_id
    WHERE sp.user_id = ?
    ORDER BY sp.created_at DESC
    LIMIT ? OFFSET ?
');
$stmt->execute([$userId, $limit, $offset]);
$posts = $stmt->fetchAll();

foreach ($posts as &$post) {
    $post['id'] = (int)$post['id'];
    $post['user_id'] = (int)$post['user_id'];
    $post['likes_count'] = (int)$post['likes_count'];
    $post['comments_count'] = (int)$post['comments_count'];
    $post['is_saved'] = true;

    // Check if liked
    $stmt2 = $db->prepare('SELECT id FROM post_likes WHERE user_id = ? AND post_id = ?');
    $stmt2->execute([$userId, $post['id']]);
    $post['is_liked'] = (bool)$stmt2->fetch();

    // Media type detection
    $mediaPath = strtolower((string)($post['image'] ?? ''));
    if (preg_match('/\.(mp4|mov|avi|mkv|webm|3gp|m4v)(\?.*)?$/', $mediaPath) === 1) {
        $post['media_type'] = 'video';
    } elseif ($mediaPath !== '') {
        $post['media_type'] = 'image';
    } else {
        $post['media_type'] = 'text';
    }
}

jsonSuccess([
    'posts' => $posts,
    'total' => $total,
    'page' => $page,
    'has_more' => ($offset + $limit) < $total,
], 'Saved posts fetched');
