<?php
/**
 * GET /post_list&page=1
 * Auth: Bearer token (optional)
 * Returns: paginated posts with author info, likes, comments
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

// Get current user if authenticated
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

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$db = getDB();

$total = (int)$db->query('SELECT COUNT(*) FROM posts')->fetchColumn();

$stmt = $db->prepare('
    SELECT p.*, u.name AS author_name, u.email AS author_email,
           pr.profile_image AS author_image, pr.headline AS author_headline,
           (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) AS likes_count,
           (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) AS comments_count
    FROM posts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN profiles pr ON p.user_id = pr.user_id
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
');
$stmt->execute([$perPage, $offset]);
$posts = $stmt->fetchAll();

foreach ($posts as &$post) {
    $post['id'] = (int)$post['id'];
    $post['user_id'] = (int)$post['user_id'];
    $post['likes_count'] = (int)$post['likes_count'];
    $post['comments_count'] = (int)$post['comments_count'];

    // Check if current user liked this post
    $post['is_liked'] = false;
    if ($currentUserId > 0) {
        $stmt3 = $db->prepare('SELECT id FROM post_likes WHERE user_id = ? AND post_id = ?');
        $stmt3->execute([$currentUserId, $post['id']]);
        $post['is_liked'] = (bool)$stmt3->fetch();
    }
}

jsonSuccess([
    'posts'        => $posts,
    'current_page' => $page,
    'per_page'     => $perPage,
    'total'        => $total,
    'last_page'    => (int)ceil($total / $perPage),
], 'Posts fetched');
