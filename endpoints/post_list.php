<?php
/**
 * GET /post_list?page=1&type=all|connections|trending&cursor=0
 * Auth: Bearer token (optional)
 * Returns: paginated posts with author info, likes, comments, save/share data
 *
 * Feed types:
 *   all         — all posts, newest first (default)
 *   connections — only posts from connected users
 *   trending    — sorted by engagement score
 *
 * Supports both page-based and cursor-based pagination.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/time.php';

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

$feedType = strtolower(trim($_GET['type'] ?? 'all'));
if (!in_array($feedType, ['all', 'connections', 'trending'], true)) {
    $feedType = 'all';
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(50, max(1, (int)($_GET['limit'] ?? 20)));
$cursor = (int)($_GET['cursor'] ?? 0);
$offset = ($page - 1) * $perPage;

$db = getDB();

// Build blocked user IDs list for filtering
$blockedIds = [];
if ($currentUserId > 0) {
    $stmt = $db->prepare('SELECT blocked_id FROM blocks WHERE blocker_id = ? UNION SELECT blocker_id FROM blocks WHERE blocked_id = ?');
    $stmt->execute([$currentUserId, $currentUserId]);
    $blockedIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}
$blockedPlaceholders = !empty($blockedIds) ? implode(',', $blockedIds) : '0';

// Build WHERE clause based on feed type
$where = "WHERE p.user_id NOT IN ($blockedPlaceholders)";
$params = [];

if ($cursor > 0 && $feedType !== 'trending') {
    $where .= " AND p.id < ?";
    $params[] = $cursor;
}

if ($feedType === 'connections' && $currentUserId > 0) {
    $where .= " AND (p.user_id IN (
        SELECT receiver_id FROM connections WHERE sender_id = ? AND status = 'accepted'
        UNION
        SELECT sender_id FROM connections WHERE receiver_id = ? AND status = 'accepted'
    ) OR p.user_id = ?)";
    $params[] = $currentUserId;
    $params[] = $currentUserId;
    $params[] = $currentUserId;
}

// Order and scoring
if ($feedType === 'trending') {
    $orderBy = "ORDER BY (
        (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id)
        + (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) * 2
    ) / (TIMESTAMPDIFF(HOUR, p.created_at, NOW()) + 1) DESC, p.id DESC";
} else {
    $orderBy = "ORDER BY p.created_at DESC";
}

// Total count (for page-based pagination)
$countSql = "SELECT COUNT(*) FROM posts p $where";
$stmt = $db->prepare($countSql);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

// Fetch posts
$sql = "
    SELECT p.*, u.name AS author_name, u.email AS author_email,
           u.account_type AS author_account_type,
           pr.profile_image AS author_image, pr.headline AS author_headline,
           (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) AS likes_count,
           (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) AS comments_count
    FROM posts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN profiles pr ON p.user_id = pr.user_id
    $where
    $orderBy
    LIMIT ? OFFSET ?
";
$allParams = array_merge($params, [$perPage, $cursor > 0 ? 0 : $offset]);
$stmt = $db->prepare($sql);
$stmt->execute($allParams);
$posts = $stmt->fetchAll();

// Check saved_posts and post_shares tables exist
$hasSavedPosts = true;
$hasPostShares = true;
try { $db->query('SELECT 1 FROM saved_posts LIMIT 0'); } catch (PDOException $e) { $hasSavedPosts = false; }
try { $db->query('SELECT 1 FROM post_shares LIMIT 0'); } catch (PDOException $e) { $hasPostShares = false; }

$nextCursor = null;

foreach ($posts as $i => &$post) {
    $post['id'] = (int)$post['id'];
    $post['user_id'] = (int)$post['user_id'];
    $post['likes_count'] = (int)$post['likes_count'];
    $post['comments_count'] = (int)$post['comments_count'];
    $post['created_at_utc'] = toUtcIso8601($post['created_at'] ?? null);

    // Media type
    $mediaPath = strtolower((string)($post['image'] ?? ''));
    if (preg_match('/\.(mp4|mov|avi|mkv|webm|3gp|m4v)(\?.*)?$/', $mediaPath) === 1) {
        $post['media_type'] = 'video';
    } elseif ($mediaPath !== '') {
        $post['media_type'] = 'image';
    } else {
        $post['media_type'] = 'text';
    }

    // Like status
    $post['is_liked'] = false;
    if ($currentUserId > 0) {
        $stmt3 = $db->prepare('SELECT id FROM post_likes WHERE user_id = ? AND post_id = ?');
        $stmt3->execute([$currentUserId, $post['id']]);
        $post['is_liked'] = (bool)$stmt3->fetch();
    }

    // Save status
    $post['is_saved'] = false;
    if ($currentUserId > 0 && $hasSavedPosts) {
        $stmt4 = $db->prepare('SELECT id FROM saved_posts WHERE user_id = ? AND post_id = ?');
        $stmt4->execute([$currentUserId, $post['id']]);
        $post['is_saved'] = (bool)$stmt4->fetch();
    }

    // Share count
    $post['shares_count'] = 0;
    if ($hasPostShares) {
        $stmt5 = $db->prepare('SELECT COUNT(*) FROM post_shares WHERE post_id = ?');
        $stmt5->execute([$post['id']]);
        $post['shares_count'] = (int)$stmt5->fetchColumn();
    }

    // Track cursor
    if ($i === count($posts) - 1) {
        $nextCursor = $post['id'];
    }
}

jsonSuccess([
    'posts'        => $posts,
    'current_page' => $page,
    'per_page'     => $perPage,
    'total'        => $total,
    'last_page'    => (int)ceil($total / $perPage),
    'feed_type'    => $feedType,
    'cursor'       => $cursor > 0 ? $cursor : null,
    'next_cursor'  => $nextCursor,
    'has_more'     => count($posts) >= $perPage,
], 'Posts fetched');
