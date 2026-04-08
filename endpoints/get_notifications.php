<?php
/**
 * GET /get_notifications?page=1&limit=20
 * Auth: Bearer token required
 * Returns: paginated notifications grouped by date
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

// Total + unread count
$stmt = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ?');
$stmt->execute([$userId]);
$total = (int)$stmt->fetchColumn();

$stmt = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$stmt->execute([$userId]);
$unreadCount = (int)$stmt->fetchColumn();

// Fetch notifications with actor info
$stmt = $db->prepare('
    SELECT n.*, u.name AS actor_name, pr.profile_image AS actor_image
    FROM notifications n
    JOIN users u ON n.actor_id = u.id
    LEFT JOIN profiles pr ON n.actor_id = pr.user_id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
    LIMIT ? OFFSET ?
');
$stmt->execute([$userId, $limit, $offset]);
$notifications = $stmt->fetchAll();

foreach ($notifications as &$n) {
    $n['id'] = (int)$n['id'];
    $n['user_id'] = (int)$n['user_id'];
    $n['actor_id'] = (int)$n['actor_id'];
    $n['target_id'] = $n['target_id'] ? (int)$n['target_id'] : null;
    $n['is_read'] = (bool)$n['is_read'];
}

jsonSuccess([
    'notifications' => $notifications,
    'unread_count' => $unreadCount,
    'total' => $total,
    'page' => $page,
    'has_more' => ($offset + $limit) < $total,
], 'Notifications fetched');
